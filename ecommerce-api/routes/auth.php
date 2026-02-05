<?php

use App\Http\Controllers\Api\Auth\AdminAuthController;
use App\Http\Controllers\Api\Auth\CustomerAuthController;
use App\Http\Controllers\Api\Auth\DeliveryAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Multiple Auth Routes //
Route::prefix('admin')->group(function () {
    Route::post('/register', [AdminAuthController::class, 'register'])->middleware(['guest::sanctum', 'throttle:6,1']);
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware(['guest::sanctum', 'throttle:6,1']);
    Route::middleware(['auth:sanctum', 'is.admin'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::get('/token', [AdminAuthController::class, 'getAccessToken']);
    });
});

Route::prefix('customer')->group(function () {
    Route::post('/register', [CustomerAuthController::class, 'register'])->middleware(['guest::sanctum', 'throttle:6,1']);
    Route::post('/login', [CustomerAuthController::class, 'login'])->middleware(['guest::sanctum', 'throttle:6,1']);
    Route::middleware(['auth:sanctum', 'is.customer'])->group(function () {
        Route::post('/logout', [CustomerAuthController::class, 'logout']);
        Route::get('/me', [CustomerAuthController::class, 'me']);
        Route::get('/token', [CustomerAuthController::class, 'getAccessToken']);
    });
});

Route::prefix('delivery')->group(function () {
    Route::post('/register', [DeliveryAuthController::class, 'register'])->middleware(['guest::sanctum', 'throttle:6,1']);
    Route::post('/login', [DeliveryAuthController::class, 'login'])->middleware(['guest::sanctum', 'throttle:6,1']);
    Route::middleware(['auth:sanctum', 'is.delivery'])->group(function () {
        Route::post('/logout', [DeliveryAuthController::class, 'logout']);
        Route::get('/me', [DeliveryAuthController::class, 'me']);
        Route::get('/token', [DeliveryAuthController::class, 'getAccessToken']);
    });
});
// Multiple Auth Routes // 