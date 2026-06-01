<?php

namespace App\Http\Controllers\Api;

use App\Enum\OrderStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'shipping_name' => 'required|string|max:255',
            'shipping_address' => 'required|string|max:255',
            'shipping_city' => 'required|string|max:255',
            'shipping_state' => 'nullable|string|max:255',
            'shipping_zipcode' => 'required|string|max:20',
            'shipping_country' => 'required|string|max:255',
            'shipping_phone' => 'required|string|max:20',
            'payment_method' => ['required', Rule::in(PaymentProvider::values())],
            'notes' => 'nullable|string',
        ]);

        $cartItems = Cart::query()
            ->visibleTo($request->user(), Cart::getCookieId())
            ->with('product')
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty'], 400);
        }

        $subtotal = 0;
        $items = [];

        foreach ($cartItems as $item) {
            $product = $item->product;

            if (! $product->is_active) {
                return response()
                    ->json(['message' => "Product '{$product->name}' is no longer available"], 400);
            }

            if ($product->stock < $item->quantity) {
                return response()
                    ->json(['message' => "not enogh stock for product '{$product->name}'"], 400);
            }

            $itemSubTotal = round($product->price * $item->quantity, 2);
            $subtotal += $itemSubTotal;

            $items[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $item->quantity,
                'price' => $product->price,
                'subtotal' => $itemSubTotal,
            ];
        }

        $tax = round($subtotal * 0.08, 2);
        $shippingCost = 5.00;
        $total = round($subtotal + $tax + $shippingCost, 2);

        DB::beginTransaction();

        try {
            $order = new Order([
                'user_id' => Auth::guard('sanctum')->id(),
                'cookie_id' => Cart::getCookieId(),
                'status' => OrderStatus::PENDING,
                'shipping_name' => $request->shipping_name,
                'shipping_address' => $request->shipping_address,
                'shipping_city' => $request->shipping_city,
                'shipping_state' => $request->shipping_state,
                'shipping_zipcode' => $request->shipping_zipcode,
                'shipping_country' => $request->shipping_country,
                'shipping_phone' => $request->shipping_phone,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'currency' => strtoupper((string) config('payments.default_currency', 'USD')),
                'payment_method' => $request->input('payment_method'),
                'payment_status' => PaymentStatus::PENDING,
                'order_number' => Order::generateOrderNumber(),
                'notes' => $request->notes,
            ]);
            $order->save();

            $order->items()->createMany($items);

            foreach ($items as $item) {
                Product::where('id', $item['product_id'])
                    ->decrement('stock', $item['quantity']);
            }

            Cart::query()
                ->visibleTo($request->user(), Cart::getCookieId())
                ->delete();

            DB::commit();

            return response()->json([
                'message' => 'Order placed successfully',
                'order' => $order->load('items'),
                'status' => true,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to place order: ' . $e->getMessage(),
                'status' => false,
            ], 500);
        }
    }
}
