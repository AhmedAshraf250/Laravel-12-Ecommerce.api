<?php

namespace App\Http\Requests\Orders;

use App\Enum\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && (
            $user->can('update orders')
            || $user->can('update delivery status')
        );
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(OrderStatus::values())],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function status(): OrderStatus
    {
        return OrderStatus::from($this->string('status')->toString());
    }
}
