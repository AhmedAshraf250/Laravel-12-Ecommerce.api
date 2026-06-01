<?php

namespace App\Services\Payments\Webhooks;

use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Exceptions\Payments\PaymentException;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PayPalWebhookService
{
    public function __construct(
        protected PaymentService $paymentService,
    ) {
    }

    public function handle(Request $request): void
    {
        $payload = $request->json()->all();

        if (!$this->verifySignature($request, $payload)) {
            throw new PaymentException('Invalid PayPal webhook signature.', 400);
        }

        $eventType = (string) ($payload['event_type'] ?? '');

        match ($eventType) {
            'CHECKOUT.ORDER.APPROVED' => $this->captureApprovedOrder($payload),
            'PAYMENT.CAPTURE.COMPLETED' => $this->markCompleted($payload),
            'PAYMENT.CAPTURE.PENDING' => $this->markPending($payload),
            'PAYMENT.CAPTURE.DENIED', 'CHECKOUT.PAYMENT-APPROVAL.REVERSED' => $this->markFailed($payload),
            default => null,
        };
    }

    protected function verifySignature(Request $request, array $payload): bool
    {
        $webhookId = config('services.paypal.webhook_id');

        if (!$webhookId) {
            throw new PaymentException('PayPal webhook ID is not configured yet.', 503);
        }

        $response = Http::baseUrl((string) config('services.paypal.base_url'))
            ->withToken($this->accessToken())
            ->acceptJson()
            ->post('/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url' => $request->header('PAYPAL-CERT-URL'),
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                'webhook_id' => $webhookId,
                'webhook_event' => $payload,
            ]);

        if ($response->failed()) {
            throw new PaymentException('Failed to verify the PayPal webhook signature.', 502);
        }

        return $response->json('verification_status') === 'SUCCESS';
    }

    protected function captureApprovedOrder(array $payload): void
    {
        $orderId = (string) ($payload['resource']['id'] ?? '');

        if (!$orderId) {
            return;
        }

        $payment = $this->findPaymentByOrderReference($orderId);

        if (!$payment || $payment->isFinal()) {
            return;
        }

        $this->paymentService->confirm($payment, [
            'provider_reference' => $orderId,
            'metadata' => [
                'webhook_event_id' => $payload['id'] ?? null,
                'webhook_event_type' => $payload['event_type'] ?? null,
            ],
        ]);
    }

    protected function markCompleted(array $payload): void
    {
        $orderId = $this->extractOrderId($payload);
        $payment = $orderId ? $this->findPaymentByOrderReference($orderId) : null;

        if (!$payment) {
            return;
        }

        $this->paymentService->applyResult(
            $payment,
            PaymentStatus::COMPLETED,
            $payment->provider_reference ?? $orderId,
            [
                'capture_id' => $payload['resource']['id'] ?? null,
                'provider_status' => $payload['resource']['status'] ?? PaymentStatus::COMPLETED->value,
                'webhook_event_id' => $payload['id'] ?? null,
                'webhook_event_type' => $payload['event_type'] ?? null,
                'resource' => $payload['resource'] ?? [],
            ],
        );
    }

    protected function markPending(array $payload): void
    {
        $orderId = $this->extractOrderId($payload);
        $payment = $orderId ? $this->findPaymentByOrderReference($orderId) : null;

        if (!$payment) {
            return;
        }

        $this->paymentService->applyResult(
            $payment,
            PaymentStatus::PENDING,
            $payment->provider_reference ?? $orderId,
            [
                'capture_id' => $payload['resource']['id'] ?? null,
                'provider_status' => $payload['resource']['status'] ?? PaymentStatus::PENDING->value,
                'webhook_event_id' => $payload['id'] ?? null,
                'webhook_event_type' => $payload['event_type'] ?? null,
                'resource' => $payload['resource'] ?? [],
            ],
        );
    }

    protected function markFailed(array $payload): void
    {
        $orderId = $this->extractOrderId($payload) ?: (string) ($payload['resource']['id'] ?? '');
        $payment = $orderId ? $this->findPaymentByOrderReference($orderId) : null;

        if (!$payment) {
            return;
        }

        $this->paymentService->applyResult(
            $payment,
            PaymentStatus::FAILED,
            $payment->provider_reference ?? $orderId,
            [
                'provider_status' => $payload['resource']['status'] ?? PaymentStatus::FAILED->value,
                'webhook_event_id' => $payload['id'] ?? null,
                'webhook_event_type' => $payload['event_type'] ?? null,
                'resource' => $payload['resource'] ?? [],
            ],
        );
    }

    protected function findPaymentByOrderReference(string $orderId): ?Payment
    {
        return Payment::query()
            ->where('provider', PaymentProvider::PAYPAL)
            ->where('provider_reference', $orderId)
            ->latest('id')
            ->first();
    }

    protected function extractOrderId(array $payload): ?string
    {
        return $payload['resource']['supplementary_data']['related_ids']['order_id']
            ?? $payload['resource']['id']
            ?? null;
    }

    protected function accessToken(): string
    {
        $clientId = config('services.paypal.client_id');
        $secret = config('services.paypal.secret');

        if (!$clientId || !$secret) {
            throw new PaymentException('PayPal credentials are not configured yet.', 503);
        }

        $response = Http::baseUrl((string) config('services.paypal.base_url'))
            ->asForm()
            ->acceptJson()
            ->withBasicAuth($clientId, $secret)
            ->post('/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            throw new PaymentException('Failed to authenticate with PayPal.', 502);
        }

        return (string) $response->json('access_token');
    }
}
