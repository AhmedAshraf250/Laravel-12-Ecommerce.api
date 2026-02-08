<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Product extends Model
{
    use SoftDeletes, HasFactory;

    public static function booted()
    {
        static::creating(fn(Product $product) => $product->slug = Str::slug($product->name));
        // static::updating(function (Product $product) {
        //     if ($product->isDirty('name')) {
        //         $product->slug = Str::slug($product->name);
        //     }
        // });

        static::addGlobalScope('active', function ($query) {
            $query->where('is_active', true);
        });
    }

    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'sku',
        'is_active',
        'image'
    ];

    public function inStock()
    {
        return $this->stock > 0;
    }

    //=== [Accessors & Mutators] ===//
    //==============================//
    // protected function name(): Attribute
    // {
    //     return Attribute::make(
    //         set: fn(string $value) => [
    //             'name' => $value,
    //             'slug' => Str::slug($value),
    //         ],
    //     );
    // }

    public function getFormattedNameAttribute()
    {
        // $product->formatted_name;
        return ucfirst($this->name);
    }
    public function getImageUrlAttribute()
    {
        // ahmed.com/storage/products/1.jpg
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    //=== [Scopes] ===//
    //================//
    public function scopePriceBetween(Builder $query, $min, $max): Builder
    {
        return $query->whereBetween('price', [$min, $max]);
    }
    public function scopeHasStock(Builder $query, $min = 1)
    {
        return $query->where('stock', '>=', $min);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFilter($query, $request)
    {
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->category);
            });
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        return $query;
    }

    // === Relationships ===//
    //=====================//
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function getCachedGallery()
    {
        return Cache::remember("product_gallery_{$this->id}", 3600, function () {
            return $this->images()->get();
        });
    }
}
