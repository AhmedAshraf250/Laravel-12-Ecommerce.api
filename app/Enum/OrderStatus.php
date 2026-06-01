<?php

namespace App\Enum;

enum OrderStatus: string
{
    case PENDING = 'pending';    // Initial state when order is created
    case PAID = 'paid';          // Payment received
    case PROCESSING = 'processing'; // Preparing the order
    case SHIPPED = 'shipped';    // Order sent to delivery
    case DELIVERED = 'delivered'; // Order received by customer
    case CANCELLED = 'cancelled'; // Order cancelled

    // values
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
    /*
    [
        'pending',
        'paid',
        'processing',
        'shipped',
        'delivered',
        'cancelled',
    ]
    */
    // OrderStatus::PENDING->canBeCancelled() => true
    // OrderStatus::PAID->value => 'paid' // accessing the value of the enum case directly
    // OrderStatus::PAID->value // return 'paid'

    public function canBeCancelled(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::PAID,
        ], true);
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions(), true);
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::PAID, self::CANCELLED],
            self::PAID => [self::PROCESSING, self::CANCELLED],
            self::PROCESSING => [self::SHIPPED],
            self::SHIPPED => [self::DELIVERED],
            self::DELIVERED => [],
            self::CANCELLED => [],
        };
    }
}
