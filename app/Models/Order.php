<?php

namespace App\Models;

use App\Enum\OrderStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Exceptions\Orders\OrderStatusTransitionException;
use App\Services\Orders\OrderStatusService;
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

    public function scopeForAdminIndex(Builder $query): Builder
    {
        return $query
            ->with([
                'user:id,name,email',
                'latestStatusHistory',
            ])
            ->withCount(['items', 'payments', 'statusHistories']);
    }

    public function scopeFilterAdminIndex(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('order_number', 'like', "%{$search}%")
                        ->orWhere('shipping_name', 'like', "%{$search}%")
                        ->orWhere('shipping_phone', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                            $userQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $paymentStatus) => $query->where('payment_status', $paymentStatus))
            ->when($filters['payment_method'] ?? null, fn (Builder $query, string $paymentMethod) => $query->where('payment_method', $paymentMethod))
            ->when($filters['user_id'] ?? null, fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $dateTo) => $query->whereDate('created_at', '<=', $dateTo));
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

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function latestStatusHistory()
    {
        return $this->hasOne(OrderStatusHistory::class)->latestOfMany();
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

    public function makePaid(Payment $payment, ?string $note = null): void
    {
        if ((int) $payment->order_id !== (int) $this->id) {
            throw new OrderStatusTransitionException('Payment does not belong to this order.');
        }

        if ($payment->status !== PaymentStatus::COMPLETED) {
            throw new OrderStatusTransitionException('Order can only be marked as paid by a completed payment.');
        }

        $this->update([
            'payment_status' => PaymentStatus::COMPLETED,
            'paid_at' => $this->paid_at ?? now(),
        ]);

        app(OrderStatusService::class)->transition(
            $this->fresh(),
            OrderStatus::PAID,
            note: $note,
            createdByType: 'payment_provider',
            createdById: "{$payment->provider->value}:{$payment->provider_reference}",
        );

        /* [order_status_histories table]
            from_status = pending
            to_status = paid
            created_by_type = payment_provider
            created_by_id = stripe:pi_xxx
        */
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
