<?php

namespace App\Models;

use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'provider',
        'provider_reference',
        'amount',
        'currency',
        'status',
        'metadata',
        'completed_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'completed_at' => 'datetime',
        'amount' => 'decimal:2',
        'provider' => PaymentProvider::class,
        'status' => PaymentStatus::class,
    ];

    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        if ($user?->isAdmin()) {
            return $query;
        }

        if ($user) {
            return $query->where('user_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markAsCompleted(string $providerReference, array $metadata = []): void
    {
        $this->update([
            'status' => PaymentStatus::COMPLETED,
            'provider_reference' => $providerReference,
            'completed_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], $metadata)
        ]);

        $this->order->makePaid($this->fresh(), "Payment completed by {$this->provider->value}.");
    }

    public function markAsFailed(array $metadata = []): void
    {
        $this->update([
            'status' => PaymentStatus::FAILED,
            'metadata' => array_merge($this->metadata ?? [], $metadata)
        ]);

        $this->order->markAsFailed();
    }

    public function markAsPending(array $metadata = []): void
    {
        $this->update([
            'status' => PaymentStatus::PENDING,
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
    }

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }
}
