<?php

namespace App\Data\Payments;

use App\Models\Payment;

readonly class PaymentCreationResponse
{
    public function __construct(
        public Payment $payment,
        public PaymentInitializationResult $result,
    ) {
    }
}
