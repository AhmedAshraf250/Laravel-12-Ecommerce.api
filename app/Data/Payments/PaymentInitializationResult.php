<?php

namespace App\Data\Payments;

use App\Enum\PaymentStatus;

readonly class PaymentInitializationResult
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $providerReference = null,
        public ?string $redirectUrl = null,
        public array $clientData = [],
        public array $metadata = [],
    ) {
    }
}
