<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class Cart extends Model
{
    protected $fillable = [
        'cookie_id',
        'user_id',
        'product_id',
        'quantity',
        'options'
    ];

    protected static function booted()
    {
        static::creating(function (Cart $cart) {
            $cart->cookie_id = self::getCookieId();
        });

        static::addGlobalScope('cookie_id', function (Builder $builder) {
            if (request()->is('api/admin/*')) {
                return;
            }
            if (Auth::check()) {
                $builder->where('user_id', Auth::id());
            } else {
                $builder->where('cookie_id', self::getCookieId());
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
