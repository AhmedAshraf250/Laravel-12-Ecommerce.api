<?php

namespace App\Exceptions\Payments;

use RuntimeException;

class PaymentException extends RuntimeException
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
