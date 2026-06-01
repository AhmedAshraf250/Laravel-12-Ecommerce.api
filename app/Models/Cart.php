<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'cookie_id',
        'user_id',
        'product_id',
        'quantity',
        'options'
    ];

    protected $casts = [
        'options' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function (Cart $cart) {
            if (! $cart->user_id && ! $cart->cookie_id) {
                $cart->cookie_id = self::getCookieId();
            }
        });
    }

    public static function getCookieId(): string
    {
        $cookie_id = Cookie::get('cart_id');
        if (!$cookie_id) {
            $cookie_id = Str::uuid();
            Cookie::queue('cart_id', $cookie_id, 30 * 24 * 60);
        }
        return $cookie_id;
    }

    public static function ownerAttributes(?User $user = null, ?string $cookieId = null): array
    {
        if ($user) {
            return [
                'user_id' => $user->id,
                'cookie_id' => $cookieId,
            ];
        }

        return [
            'user_id' => null,
            'cookie_id' => $cookieId,
        ];
    }

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

        return $query->whereRaw('1 = 0');
    }

    public function scopeOwnedBy(Builder $query, ?User $user = null, ?string $cookieId = null): Builder
    {
        if ($user) {
            return $query->where('user_id', $user->id);
        }

        if ($cookieId) {
            return $query->whereNull('user_id')
                ->where('cookie_id', $cookieId);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function findOwnedProduct(int $productId, ?User $user = null, ?string $cookieId = null): ?self
    {
        if ($user) {
            $userItem = static::query()
                ->ownedBy($user, $cookieId)
                ->where('product_id', $productId)
                ->first();

            if ($userItem) {
                return $userItem;
            }

            $guestItem = $cookieId
                ? static::query()
                    ->ownedBy(null, $cookieId)
                    ->where('product_id', $productId)
                    ->first()
                : null;

            if ($guestItem) {
                $guestItem->forceFill([
                    'user_id' => $user->id,
                    'cookie_id' => $cookieId,
                ])->save();
            }

            return $guestItem;
        }

        return $cookieId
            ? static::query()
                ->ownedBy(null, $cookieId)
                ->where('product_id', $productId)
                ->first()
            : null;
    }

    //=== Relationships ===//
    //=====================//
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
