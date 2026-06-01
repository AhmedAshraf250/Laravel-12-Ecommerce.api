<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cookieId = Cart::getCookieId();
        $cartItems = Cart::query()
            ->visibleTo($request->user(), $cookieId)
            ->with('product')
            ->get();

        return response()->json($this->cartPayload($cartItems, 'Cart items retrieved successfully'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id', 'int'],
            'quantity' => ['nullable', 'int', 'min:1'],
        ]);

        $quantity = $data['quantity'] ?? 1;
        $user = $request->user();
        $cookieId = Cart::getCookieId();

        $cartItem = DB::transaction(function () use ($data, $quantity, $user, $cookieId) {
            $cartItem = Cart::findOwnedProduct($data['product_id'], $user, $cookieId);

            if (! $cartItem) {
                return Cart::create([
                    ...Cart::ownerAttributes($user, $cookieId),
                    'product_id' => $data['product_id'],
                    'quantity' => $quantity,
                ]);
            }

            $cartItem->increment('quantity', $quantity);

            return $cartItem->fresh(['product']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Product added to cart',
            'cart_item' => $cartItem->loadMissing('product'),
        ], 201);
    }

    public function update(Request $request, string $cart)
    {
        $data = $request->validate([
            'quantity' => ['required', 'int', 'min:1'],
        ]);

        $cookieId = Cart::getCookieId();
        $cart = Cart::query()
            ->visibleTo($request->user(), $cookieId)
            ->findOrFail($cart);

        $cart->quantity = $data['quantity'];
        $cart->save();

        return response()->json([
            'success' => true,
            'message' => 'Cart item updated',
            'cart_item' => $cart->loadMissing('product'),
        ], 200);
    }

    public function destroy(Request $request, string $cart)
    {
        $cookieId = Cart::getCookieId();
        $cart = Cart::query()
            ->visibleTo($request->user(), $cookieId)
            ->findOrFail($cart);

        $cart->delete();
        return response()->json([
            'success' => true,
            'message' => 'Cart item removed'
        ]);
    }

    private function cartPayload(Collection $cartItems, string $message): array
    {
        $total = $cartItems->sum(function (Cart $item) {
            return ($item->product?->price ?? 0) * $item->quantity;
        });

        return [
            'success' => true,
            'message' => $message,
            'cart' => $cartItems,
            'total' => round($total, 2),
        ];
    }
}
