<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'return_url' => ['nullable', 'url'],
            'cancel_url' => ['nullable', 'url'],
        ];
    }
}
