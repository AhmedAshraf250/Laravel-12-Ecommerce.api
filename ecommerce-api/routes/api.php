<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
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


require __DIR__ . '/auth.php';
