<?php

namespace App\Models;

use App\Enum\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'created_by_type',
        'created_by_id',
        'from_status',
        'to_status',
        'note',
    ];

    protected $casts = [
        'from_status' => OrderStatus::class,
        'to_status' => OrderStatus::class,
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function wasCreatedByUser(): bool
    {
        return $this->created_by_type === 'user';
    }
    public function createdBy()
    {
        if ($this->created_by_type === 'user') {
            return $this->belongsTo(User::class, 'created_by_id');
        }

        return null;
    }
}
