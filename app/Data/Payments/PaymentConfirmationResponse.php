<?php

namespace App\Data\Payments;

use App\Models\Payment;

readonly class PaymentConfirmationResponse
{
    public function __construct(
        public Payment $payment,
        public PaymentConfirmationResult $result,
    ) {
    }
}
