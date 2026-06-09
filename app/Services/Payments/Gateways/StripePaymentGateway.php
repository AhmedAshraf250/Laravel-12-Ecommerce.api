<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGateway;
use App\Data\Payments\PaymentConfirmationResult;
use App\Data\Payments\PaymentInitializationResult;
use App\Enum\PaymentStatus;
use App\Exceptions\Payments\PaymentException;
use App\Models\Payment;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripePaymentGateway implements PaymentGateway
{
    protected StripeClient $client;

    public function __construct()
    {
        $secret = config('services.stripe.secret');

        if (!$secret) {
            throw new PaymentException('Stripe credentials are not configured yet.', 503);
        }

        $this->client = new StripeClient($secret);
    }

    public function createPayment(Payment $payment, array $payload = []): PaymentInitializationResult
    {
        try {
            $intent = $this->client->paymentIntents->create([
                'amount' => $this->toMinorUnits($payment->amount),
                'currency' => strtolower($payment->currency),
                'metadata' => [
                    'order_id' => (string) $payment->order_id,
                    'payment_id' => (string) $payment->id,
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                    // 'allow_redirects' => 'never',
                ],
            ]);
        } catch (ApiErrorException $exception) {
            throw new PaymentException($exception->getMessage(), 502);
        }

        return new PaymentInitializationResult(
            status: $this->mapStatus($intent->status),
            providerReference: $intent->id,
            clientData: [
                'client_secret' => $intent->client_secret,
                'publishable_key' => config('services.stripe.publishable_key'),
            ],
            metadata: [
                'provider_status' => $intent->status,
            ],
        );
    }

    public function confirmPayment(Payment $payment, array $payload = []): PaymentConfirmationResult
    {
        $reference = $payload['provider_reference'] ?? $payment->provider_reference;

        if (!$reference) {
            throw new PaymentException('Missing Stripe payment reference.', 422);
        }

        try {
            if (!empty($payload['payment_method_id'])) {
                $intent = $this->client->paymentIntents->confirm($reference, [
                    'payment_method' => $payload['payment_method_id'],
                    'return_url' => $payload['return_url'] ?? null,
                ]);
            } else {
                $intent = $this->client->paymentIntents->retrieve($reference, []);
            }
        } catch (ApiErrorException $exception) {
            throw new PaymentException($exception->getMessage(), 502);
        }

        return new PaymentConfirmationResult(
            status: $this->mapStatus($intent->status),
            providerReference: $intent->id,
            message: 'Stripe payment status retrieved successfully.',
            metadata: [
                'provider_status' => $intent->status,
            ],
        );
    }

    protected function mapStatus(string $status): PaymentStatus
    {
        return match ($status) {
            'succeeded' => PaymentStatus::COMPLETED,
            'canceled' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }

    protected function toMinorUnits(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
