<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'type'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // isAdmin
    public function isAdmin(): bool
    {
        return $this->type === 'admin';
    }
    // isCustomer
    public function isCustomer(): bool
    {
        return $this->type === 'customer';
    }
    // isdelivery
    public function isDelivery(): bool
    {
        return $this->type === 'delivery';
    }


    //=== Relationships ===//
    //=====================//
    public function cartItems()
    {
        return $this->hasMany(Cart::class, 'user_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function orderedProducts()
    {
        return $this->hasManyThrough(
            OrderItem::class, // the final model we want to access
            Order::class, // the intermediate model
            'user_id', // foreign key on the 'orders' table
            'order_id', // foreign key on the 'order_items' table
            'id', // local key on the users table
            'id' // local key on the orders table
        );
    }
}
