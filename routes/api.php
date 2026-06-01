<?php

use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\Webhooks\PayPalWebhookController;
use App\Http\Controllers\Api\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;


Route::apiResource('products', ProductController::class)->only(['index', 'show']);
Route::middleware(['auth:sanctum', 'permission:create products'])->group(function () {
    Route::apiResource('products', ProductController::class)->except(['index', 'show']);

    // Admin specific product routes
    Route::get('/products/admin', [ProductController::class, 'adminIndex']);
    Route::post('/products/{product}/restore', [ProductController::class, 'undoDelete']);
    Route::delete('/products/{product}/permanent', [ProductController::class, 'permanentDelete']);
});


Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
Route::middleware(['auth:sanctum', 'permission:create categories'])->group(function () {
    Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
});
Route::get('/categories/{category}/products', [CategoryController::class, 'products']);

Route::post('/webhooks/stripe', StripeWebhookController::class);
Route::post('/webhooks/paypal', PayPalWebhookController::class);

Route::middleware(['auth:sanctum', 'permission:create orders'])->group(function () {
    Route::apiResource('cart', CartController::class)->except(['show']);
});

Route::middleware(['auth:sanctum', 'permission:create orders'])->group(function () {
    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::post('/checkout/{order}/payments', [PaymentController::class, 'store']);
    Route::post('/checkout/payments/{payment}/confirm', [PaymentController::class, 'confirm']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
});

require __DIR__ . '/auth.php';
