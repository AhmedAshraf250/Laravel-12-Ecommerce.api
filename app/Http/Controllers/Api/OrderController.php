<?php

namespace App\Http\Controllers\Api;

use App\Enum\OrderStatus;
use App\Exceptions\Orders\OrderStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\AdminOrderIndexRequest;
use App\Http\Requests\Orders\CancelOrderRequest;
use App\Http\Requests\Orders\UpdateOrderStatusRequest;
use App\Models\Cart;
use App\Models\Order;
use App\Services\Orders\OrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function __construct(
        protected OrderStatusService $orderStatusService,
    ) {}

    public function index()
    {
        $orders = Order::query()
            ->visibleTo(Auth::user(), Cart::getCookieId())
            ->with('items')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Order history retrieved successfully',
            'orders' => $orders,
            'status' => true,
        ]);
    }

    public function adminIndex(AdminOrderIndexRequest $request): JsonResponse
    {
        $orders = Order::query()
            ->forAdminIndex()
            ->filterAdminIndex($request->filters())
            ->orderBy($request->sortBy(), $request->sortDirection())
            ->paginate($request->perPage())
            ->withQueryString();

        return response()->json([
            'message' => 'Orders retrieved successfully',
            'orders' => $orders,
            'status' => true,
        ]);
    }

    public function adminShow(Order $order): JsonResponse
    {
        return response()->json([
            'message' => 'Order details retrieved successfully',
            'order' => $order->load(['user', 'items', 'statusHistories']),
            'status' => true,
        ]);
    }

    public function show(string $id)
    {
        $order = Order::query()
            ->visibleTo(Auth::user(), Cart::getCookieId())
            ->with(['items', 'statusHistories'])
            ->find($id);

        if (! $order) {
            return response()->json([
                'message' => 'Order not found',
                'status' => false,
            ], 404);
        }

        return response()->json([
            'message' => 'Order details retrieved successfully',
            'order' => $order,
            'status' => true,
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        try {
            $order = $this->orderStatusService->transition(
                $order,
                $request->status(),
                $request->user(),
                $request->input('note'),
            );

            return response()->json([
                'message' => 'Order status updated successfully',
                'order' => $order,
                'status' => true,
            ]);
        } catch (OrderStatusTransitionException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'status' => false,
            ], $exception->statusCode());
        }
    }

    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        if (! $request->user()?->isAdmin() && (int) $order->user_id !== (int) $request->user()?->id) {
            return response()->json([
                'message' => 'Order not found',
                'status' => false,
            ], 404);
        }

        try {
            $order = $this->orderStatusService->transition(
                $order,
                OrderStatus::CANCELLED,
                $request->user(),
                $request->input('note'),
            );

            return response()->json([
                'message' => 'Order cancelled successfully',
                'order' => $order,
                'status' => true,
            ]);
        } catch (OrderStatusTransitionException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'status' => false,
            ], $exception->statusCode());
        }
    }
}
