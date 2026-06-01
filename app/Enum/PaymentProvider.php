<?php

namespace App\Enum;

enum PaymentProvider: string
{
    case STRIPE = 'stripe';
    case PAYPAL = 'paypal';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::STRIPE => 'Stripe',
            self::PAYPAL => 'PayPal',
        };
    }
}
