<?php

namespace App\Exceptions\Orders;

use RuntimeException;

class OrderStatusTransitionException extends RuntimeException
{
    public function __construct(string $message, protected int $statusCode = 422)
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
