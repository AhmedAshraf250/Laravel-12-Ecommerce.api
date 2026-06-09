<?php

namespace App\Http\Controllers\Api\Auth;

class AdminAuthController extends AuthController
{
    protected function expectedUserType(): string
    {
        return 'admin';
    }
}
