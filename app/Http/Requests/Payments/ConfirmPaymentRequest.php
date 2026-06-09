<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider_reference' => ['nullable', 'string'],
            'payment_method_id' => ['nullable', 'string'],
            'return_url' => ['nullable', 'url'],
        ];
    }
}
