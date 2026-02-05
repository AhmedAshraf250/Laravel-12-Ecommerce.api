<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CustomerAuthController extends AuthController
{
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        return $this->processRegistration($validatedData, 'customer');
    }
}
