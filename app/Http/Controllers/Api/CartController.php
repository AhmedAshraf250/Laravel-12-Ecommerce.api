<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index(Request $request)
    {
        // $user = $request->user();
        $cartItems = Cart::with('product')->get();
        $total = $cartItems->sum(function ($item) {
            return $item->product->price * $item->quantity;
        });
        return response()->json([
            'success' => true,
            'message' => 'Cart items retrieved successfully',
            'cart' => $cartItems,
            'total' => round($total, 2),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id', 'int'],
            'quantity' => ['nullable', 'int', 'min:1'],
        ]);

        $cartItem = Cart::withoutGlobalScopes()
            ->where('product_id', $data['product_id'])
            ->where(function ($q) {
                $q->where('user_id', Auth::id())
                    ->orWhere('cookie_id', Cart::getCookieId());
            })
            ->first();

        if (!$cartItem) {
            $cartItem = Cart::create([
                'user_id' => Auth::id(),
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity']
            ]);
        } else {
            $cartItem->increment('quantity', $data['quantity']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product added to cart',
            'cart_item' => $cartItem
        ], 201);
    }

    public function update(Request $request, Cart $cart)
    {

        $data = $request->validate([
            'quantity' => ['nullable', 'int', 'min:1'],
        ]);

        $cart->quantity = $data['quantity'];
        $cart->save();

        return response()->json([
            'success' => true,
            'message' => 'Cart item updated',
            'cart_item' => $cart
        ], 200);
    }

    public function destroy(Request $request, Cart $cart)
    {
        $cart->delete();
        return response()->json([
            'success' => true,
            'message' => 'Cart item removed'
        ]);
    }
}
