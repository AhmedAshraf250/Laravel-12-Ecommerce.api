<?php

namespace App\Data\Payments;

use App\Enum\PaymentStatus;

readonly class PaymentConfirmationResult
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $providerReference = null,
        public ?string $message = null,
        public array $metadata = [],
    ) {
    }
}
