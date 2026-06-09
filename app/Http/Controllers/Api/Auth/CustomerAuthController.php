<?php

namespace App\Http\Controllers\Api\Auth;

class CustomerAuthController extends AuthController
{
    protected function expectedUserType(): string
    {
        return 'customer';
    }
}
