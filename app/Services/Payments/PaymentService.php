<?php

namespace App\Services\Payments;

use App\Data\Payments\PaymentConfirmationResponse;
use App\Data\Payments\PaymentCreationResponse;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Exceptions\Payments\PaymentException;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
    ) {}

    public function createForOrder(Order $order, array $payload = []): PaymentCreationResponse
    {
        if (!$order->canAcceptPayment()) {
            throw new PaymentException('This order can no longer accept a new payment attempt.', 422);
        }

        $provider = $order->payment_method;

        if (!$provider instanceof PaymentProvider) {
            throw new PaymentException('This order does not have a payment method selected.', 422);
        }

        $currency = strtoupper((string) ($order->currency ?? config('payments.default_currency', 'USD')));

        return DB::transaction(function () use ($order, $provider, $payload, $currency) {
            $existingPayment = $order->payments()
                ->where('provider', $provider)
                ->where('status', PaymentStatus::PENDING)
                ->latest('id')
                ->first();

            if ($existingPayment) {
                return new PaymentCreationResponse(
                    $existingPayment->fresh(),
                    new \App\Data\Payments\PaymentInitializationResult(
                        status: $existingPayment->status,
                        providerReference: $existingPayment->provider_reference,
                        redirectUrl: $existingPayment->metadata['redirect_url'],
                        clientData: $existingPayment->metadata['client_data'],
                        metadata: $existingPayment->metadata,
                    ),
                );
            }

            $payment = $order->payments()->create([
                'user_id' => $order->user_id,
                'provider' => $provider,
                'amount' => $order->total,
                'currency' => $currency,
                'status' => PaymentStatus::PENDING,
                'metadata' => [
                    'return_url' => $payload['return_url'] ?? null,
                    'cancel_url' => $payload['cancel_url'] ?? null,
                ],
            ]);

            $result = $this->gatewayManager->resolve($provider)->createPayment($payment, $payload);

            Log::channel('payments')->info('Payment creation result', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'provider' => $provider->value,
                'status' => $result->status->value,
                'provider_reference' => $result->providerReference,
                'redirect_url' => $result->redirectUrl,
                'client_data' => $result->clientData,
                'metadata' => $result->metadata,
            ]);

            $this->applyResult(
                $payment,
                $result->status,
                $result->providerReference,
                array_merge($result->metadata, [
                    'redirect_url' => $result->redirectUrl,
                    'client_data' => $result->clientData,
                ]),
            );

            return new PaymentCreationResponse($payment->fresh(), $result);
        });
    }

    public function confirm(Payment $payment, array $payload = []): PaymentConfirmationResponse
    {
        if ($payment->isFinal()) {
            throw new PaymentException('This payment is already finalized.', 422);
        }

        $result = DB::transaction(function () use ($payment, $payload) {
            $result = $this->gatewayManager->resolve($payment->provider)->confirmPayment($payment, $payload);

            $this->applyResult($payment, $result->status, $result->providerReference, $result->metadata,);

            return $result;
        });

        return new PaymentConfirmationResponse($payment->fresh(), $result);
    }

    public function applyResult(
        Payment $payment,
        PaymentStatus $status,
        ?string $providerReference = null,
        array $metadata = [],
    ): Payment {
        $providerReference ??= $payment->provider_reference;

        if ($status === PaymentStatus::COMPLETED) {
            if ($payment->status === PaymentStatus::COMPLETED) {
                $payment->update([
                    'provider_reference' => $providerReference,
                    'metadata' => array_merge($payment->metadata ?? [], $metadata),
                ]);

                return $payment->fresh();
            }

            $payment->markAsCompleted($providerReference ?? $payment->provider_reference, $metadata);

            return $payment->fresh();
        }

        if ($status === PaymentStatus::FAILED) {
            if ($payment->status === PaymentStatus::FAILED) {
                $payment->update([
                    'provider_reference' => $providerReference ?? $payment->provider_reference,
                    'metadata' => array_merge($payment->metadata ?? [], $metadata),
                ]);

                return $payment->fresh();
            }

            $payment->markAsFailed($metadata);

            return $payment->fresh();
        }

        $payment->update([
            'provider_reference' => $providerReference,
            'status' => $status,
            'metadata' => array_merge($payment->metadata ?? [], $metadata),
        ]);

        return $payment->fresh();
    }
}
