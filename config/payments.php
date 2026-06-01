<?php

use App\Enum\PaymentProvider;
use App\Services\Payments\Gateways\PayPalPaymentGateway;
use App\Services\Payments\Gateways\StripePaymentGateway;

return [
    'default_currency' => env('PAYMENTS_CURRENCY', 'USD'),

    'providers' => [
        PaymentProvider::STRIPE->value => [
            'gateway' => StripePaymentGateway::class,
        ],
        PaymentProvider::PAYPAL->value => [
            'gateway' => PayPalPaymentGateway::class,
        ],
    ],
];
