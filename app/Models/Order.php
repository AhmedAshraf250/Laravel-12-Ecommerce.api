<?php

namespace App\Models;

use App\Enum\OrderStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'cookie_id',
        'status',
        'shipping_name',
        'shipping_address',
        'shipping_city',
        'shipping_state',
        'shipping_zipcode',
        'shipping_country',
        'shipping_phone',
        'subtotal',
        'tax',
        'shipping_cost',
        'total',
        'currency',
        'payment_method',
        'payment_status',
        'paid_at',
        'order_number',
        'notes',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'payment_method' => PaymentProvider::class,
        'payment_status' => PaymentStatus::class,
        'paid_at' => 'datetime',
    ];

    public function scopeVisibleTo(Builder $query, ?User $user = null, ?string $cookieId = null): Builder
    {
        if ($user?->isAdmin()) {
            return $query;
        }

        if ($user) {
            return $query->where('user_id', $user->id);
        }

        if ($cookieId) {
            return $query->where('cookie_id', $cookieId);
        }

        return $query->whereRaw('1 = 0'); // safety fallback if no user or cookie ID is provided
        // Order::query()->visibleTo(null, null)->get(); || X 
    }


    // Define the relationship with the User model
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    // Define the relationship with the OrderItem model
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // generate a unique order number
    public static function generateOrderNumber(): string
    {
        $year = date('Y');

        $randomNumber = strtoupper(substr(uniqid(), -6));
        return "ORD-{$year}-{$randomNumber}"; // e.g., ORD-2025-ABC123
    }

    public function canBeCancelled(): bool
    {
        return $this->status->canBeCancelled();
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status' => OrderStatus::PAID,
            'payment_status' => PaymentStatus::COMPLETED,
            'paid_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'payment_status' => PaymentStatus::FAILED,
        ]);
    }

    public function canAcceptPayment(): bool
    {
        return $this->payment_status->allowsNewAttempt();
    }
}
