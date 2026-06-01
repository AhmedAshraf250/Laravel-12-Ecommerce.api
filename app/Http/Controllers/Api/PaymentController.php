<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Payments\PaymentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\ConfirmPaymentRequest;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Cart;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
    ) {}

    public function store(StorePaymentRequest $request, string $order): JsonResponse
    {
        try {

            $order = Order::query()
                ->visibleTo($request->user(), Cart::getCookieId())
                ->find($order);

            if (!$order) {
                return response()->json([
                    'message' => 'Order not found.',
                    'status' => false,
                ], 404);
            }

            $response = $this->paymentService->createForOrder(
                $order,
                $request->validated(),
            );

            return response()->json([
                'message' => 'Payment initialized successfully.',
                'status' => true,
                'payment' => new PaymentResource($response->payment),
            ], 201);
        } catch (PaymentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'status' => false,
            ], $exception->statusCode());
        }
    }

    public function confirm(ConfirmPaymentRequest $request, string $payment): JsonResponse
    {
        try {
            $payment = Payment::query()
                ->visibleTo($request->user())
                ->findOrFail($payment);

            $response = $this->paymentService->confirm($payment, $request->validated());

            return response()->json([
                'message' => $response->result->message ?? 'Payment status updated successfully.',
                'status' => true,
                // 'payment' => new PaymentResource($response->payment->load('order')),
                'payment' => PaymentResource::collection($response->payment->load('order')),
            ]);
        } catch (PaymentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'status' => false,
            ], $exception->statusCode());
        }
    }
}
