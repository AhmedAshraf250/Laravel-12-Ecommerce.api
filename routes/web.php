<?php

use App\Http\Controllers\RouteExplorerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/_debug/routes', RouteExplorerController::class);
Route::view('/_debug/reverb/order-status', 'debug.order-status-reverb');
