<?php

namespace App\Contracts\Payments;

use App\Data\Payments\PaymentConfirmationResult;
use App\Data\Payments\PaymentInitializationResult;
use App\Models\Payment;

interface PaymentGateway
{
    public function createPayment(Payment $payment, array $payload = []): PaymentInitializationResult;

    public function confirmPayment(Payment $payment, array $payload = []): PaymentConfirmationResult;
}
