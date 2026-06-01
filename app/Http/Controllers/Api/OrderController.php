<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
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

    public function show(string $id)
    {
        $order = Order::query()
            ->visibleTo(Auth::user(), Cart::getCookieId())
            ->with('items')
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
}
