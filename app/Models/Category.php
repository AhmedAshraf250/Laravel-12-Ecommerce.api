<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;
    // fillable 
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'parent_id'
    ];

    protected static function booted()
    {
        // static::creating(function ($category) {
        //     $slug = Str::slug($category->name);

        //     $originalSlug = $slug;
        //     $count = 1;

        //     while (static::where('slug', $slug)->exists()) {
        //         $slug = $originalSlug . '-' . $count++;
        //     }

        //     $category->slug = $slug;
        // });

        // saving() is included in both creating() and updating() events.
        static::saving(function ($category) {
            // Generate a slug from the name (for URLs) if the name has changed or if the name is being set for the first time
            if ($category->isDirty('name')) {
                $slug = Str::slug($category->name);
                $originalSlug = $slug;
                $count = 1;

                while (static::where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                    $slug = $originalSlug . '-' . $count++;
                }
                $category->slug = $slug;
            }
        });
    }

    // id 1, tech , parent_id = null
    // id 2, laptop, parent_id = 1
    // id 3, phone, parent_id = 1

    //=== Relationships ===//
    //=====================//
    // parent category
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // child categories
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }


    // is active childeren
    public function activeChildren()
    {
        return $this->children()->where('is_active', true);
    }


    // is top level category
    public function isTopLevel(): bool
    {
        return is_null($this->parent_id);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'category_product');
    }
}
