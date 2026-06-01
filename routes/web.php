<?php

use App\Http\Controllers\RouteExplorerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/_debug/routes', RouteExplorerController::class);
