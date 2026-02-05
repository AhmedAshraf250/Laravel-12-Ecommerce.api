<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    public static function booted()
    {
        static::creating(fn(Product $product) => $product->slug = Str::slug($product->name));
        // static::updating(function (Product $product) {
        //     if ($product->isDirty('name')) {
        //         $product->slug = Str::slug($product->name);
        //     }
        // });

    }

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'stock',
        'sku',
        'is_active'
    ];

    public function inStock()
    {
        return $this->stock > 0;
    }

    // protected function name(): Attribute
    // {
    //     return Attribute::make(
    //         set: fn(string $value) => [
    //             'name' => $value,
    //             'slug' => Str::slug($value),
    //         ],
    //     );
    // }
}
