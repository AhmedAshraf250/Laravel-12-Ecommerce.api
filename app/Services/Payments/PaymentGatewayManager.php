<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Enum\PaymentProvider;
use App\Exceptions\Payments\PaymentException;

class PaymentGatewayManager
{
    public function resolve(PaymentProvider $provider): PaymentGateway
    {
        $gatewayClass = config("payments.providers.{$provider->value}.gateway");

        if (!$gatewayClass) {
            throw new PaymentException("Payment provider [{$provider->value}] is not configured.", 500);
        }

        $gateway = app($gatewayClass);

        if (!$gateway instanceof PaymentGateway) {
            throw new PaymentException("Payment provider [{$provider->value}] is invalid.", 500);
        }

        return $gateway;
    }
}
