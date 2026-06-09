<?php

namespace App\Http\Requests\Orders;

use App\Enum\OrderStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminOrderIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(OrderStatus::values())],
            'payment_status' => ['nullable', Rule::in(PaymentStatus::values())],
            'payment_method' => ['nullable', Rule::in(PaymentProvider::values())],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', Rule::in(['created_at', 'total', 'paid_at'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->integer('per_page') ?: 15);
    }

    public function sortBy(): string
    {
        return $this->string('sort_by')->toString() ?: 'created_at';
    }

    public function sortDirection(): string
    {
        return strtolower($this->string('sort_direction')->toString() ?: 'desc');
    }

    public function filters(): array
    {
        return array_filter([
            'search' => $this->filled('search') ? trim($this->string('search')->toString()) : null,
            'status' => $this->filled('status') ? $this->string('status')->toString() : null,
            'payment_status' => $this->filled('payment_status') ? $this->string('payment_status')->toString() : null,
            'payment_method' => $this->filled('payment_method') ? $this->string('payment_method')->toString() : null,
            'user_id' => $this->filled('user_id') ? $this->integer('user_id') : null,
            'date_from' => $this->filled('date_from') ? $this->date('date_from')?->toDateString() : null,
            'date_to' => $this->filled('date_to') ? $this->date('date_to')?->toDateString() : null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
