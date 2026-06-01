<?php

namespace App\Services\Payments\Webhooks;

use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookService
{
    public function __construct(
        protected PaymentService $paymentService,
    ) {
    }

    public function handle(string $payload, ?string $signature): void
    {
        $secret = config('services.stripe.webhook_secret');

        if (!$secret || !$signature) {
            abort(400, 'Missing Stripe webhook configuration or signature.');
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (UnexpectedValueException|SignatureVerificationException $exception) {
            abort(400, $exception->getMessage());
        }

        $object = $event->data->object;

        Log::channel('payments')->info('Stripe Webhook Received', [
            'provider' => PaymentProvider::STRIPE->value,
            'event_id' => $event->id,
            'event_type' => $event->type,
            'provider_reference' => $object->id ?? null,
            'provider_status' => $object->status ?? null,
        ]);

        match ($event->type) {
            'payment_intent.succeeded' => $this->markCompleted(
                providerReference: $object->id,
                metadata: [
                    'provider_status' => $object->status,
                    'webhook_event_id' => $event->id,
                ],
            ),
            'payment_intent.payment_failed' => $this->markFailed(
                providerReference: $object->id,
                metadata: [
                    'provider_status' => $object->status,
                    'error' => $object->last_payment_error->message ?? null,
                    'webhook_event_id' => $event->id,
                ],
            ),
            default => null,
        };
    }

    protected function markCompleted(string $providerReference, array $metadata = []): void
    {
        $payment = $this->findPayment($providerReference);

        if (!$payment) {
            return;
        }

        $this->paymentService->applyResult(
            $payment,
            PaymentStatus::COMPLETED,
            $providerReference,
            $metadata,
        );
    }

    protected function markFailed(string $providerReference, array $metadata = []): void
    {
        $payment = $this->findPayment($providerReference);

        if (!$payment) {
            return;
        }

        $this->paymentService->applyResult(
            $payment,
            PaymentStatus::FAILED,
            $payment->provider_reference,
            $metadata,
        );
    }

    protected function findPayment(string $providerReference): ?Payment
    {
        return Payment::query()
            ->where('provider', PaymentProvider::STRIPE)
            ->where('provider_reference', $providerReference)
            ->latest('id')
            ->first();
    }
}
