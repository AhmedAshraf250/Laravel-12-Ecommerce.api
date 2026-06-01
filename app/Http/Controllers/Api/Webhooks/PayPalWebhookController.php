<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Exceptions\Payments\PaymentException;
use App\Http\Controllers\Controller;
use App\Services\Payments\Webhooks\PayPalWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayPalWebhookController extends Controller
{
    public function __construct(
        protected PayPalWebhookService $webhookService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $this->webhookService->handle($request);

            return response()->json([
                'message' => 'PayPal webhook processed successfully.',
            ]);
        } catch (PaymentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }
    }
}
