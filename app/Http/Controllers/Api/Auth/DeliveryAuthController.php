<?php

namespace App\Http\Controllers\Api\Auth;

class DeliveryAuthController extends AuthController
{
    protected function expectedUserType(): string
    {
        return 'delivery';
    }
}
