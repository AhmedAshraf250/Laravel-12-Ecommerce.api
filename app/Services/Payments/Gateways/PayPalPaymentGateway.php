<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGateway;
use App\Data\Payments\PaymentConfirmationResult;
use App\Data\Payments\PaymentInitializationResult;
use App\Enum\PaymentStatus;
use App\Exceptions\Payments\PaymentException;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PayPalPaymentGateway implements PaymentGateway
{
    public function createPayment(Payment $payment, array $payload = []): PaymentInitializationResult
    {
        $returnUrl = $payload['return_url'] ?? config('services.paypal.return_url');
        $cancelUrl = $payload['cancel_url'] ?? config('services.paypal.cancel_url');

        if (!$returnUrl || !$cancelUrl) {
            throw new PaymentException('PayPal return and cancel URLs must be configured.', 422);
        }

        $response = $this->client()
            ->post('/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string) $payment->id,
                    'custom_id' => (string) $payment->order_id,
                    'amount' => [
                        'currency_code' => strtoupper($payment->currency),
                        'value' => number_format((float) $payment->amount, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ],
            ]);

        if ($response->failed()) {
            throw new PaymentException('Failed to create PayPal order.', 502);
        }

        $body = $response->json();

        return new PaymentInitializationResult(
            status: $this->mapStatus($body['status'] ?? 'CREATED'),
            providerReference: $body['id'] ?? null,
            redirectUrl: $this->extractApproveUrl($body['links'] ?? []),
            clientData: [
                'order_id' => $body['id'] ?? null,
                'approve_url' => $this->extractApproveUrl($body['links'] ?? []),
            ],
            metadata: [
                'provider_status' => $body['status'] ?? null,
                'links' => $body['links'] ?? [],
            ],
        );
    }

    public function confirmPayment(Payment $payment, array $payload = []): PaymentConfirmationResult
    {
        $reference = $payload['provider_reference'] ?? $payment->provider_reference;

        if (!$reference) {
            throw new PaymentException('Missing PayPal order reference.', 422);
        }

        $response = $this->client()
            ->post("/v2/checkout/orders/{$reference}/capture");

        if ($response->failed()) {
            throw new PaymentException('Failed to capture the PayPal order.', 502);
        }

        $body = $response->json();

        return new PaymentConfirmationResult(
            status: $this->mapStatus($body['status'] ?? 'PAYER_ACTION_REQUIRED'),
            providerReference: $body['id'] ?? $reference,
            message: 'PayPal payment captured successfully.',
            metadata: [
                'provider_status' => $body['status'] ?? null,
                'capture_id' => $body['purchase_units'][0]['payments']['captures'][0]['id'] ?? null,
                'capture' => $body,
            ],
        );
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.paypal.base_url'))
            ->withToken($this->accessToken())
            ->acceptJson();
    }

    protected function accessToken(): string
    {
        return Cache::remember('paypal_access_token', 3000, function () {
            $clientId = config('services.paypal.client_id');
            $secret = config('services.paypal.secret');

            $response = Http::baseUrl((string) config('services.paypal.base_url'))
                ->asForm()
                ->acceptJson()
                ->withBasicAuth($clientId, $secret)
                ->post('/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('Failed to authenticate with PayPal.');
            }

            return (string) $response->json('access_token');
        });
    }

    protected function extractApproveUrl(array $links): ?string
    {
        foreach ($links as $link) {
            if (($link['rel'] ?? null) === 'approve') {
                return $link['href'] ?? null;
            }
        }

        return null;
    }

    protected function mapStatus(string $status): PaymentStatus
    {
        return match ($status) {
            'COMPLETED' => PaymentStatus::COMPLETED,
            'VOIDED' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }
}
