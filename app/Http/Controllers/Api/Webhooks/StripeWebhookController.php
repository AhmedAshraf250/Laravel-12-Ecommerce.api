<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\Webhooks\StripeWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected StripeWebhookService $webhookService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->webhookService->handle(
            $request->getContent(),
            $request->header('Stripe-Signature'),
        );

        return response()->noContent();
    }
}
