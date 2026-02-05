<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::GET('/products', function () {
    return response()->json(['message' => 'List of products']);
})->middleware(['auth:sanctum', 'permission:view products']);

require __DIR__ . '/auth.php';
