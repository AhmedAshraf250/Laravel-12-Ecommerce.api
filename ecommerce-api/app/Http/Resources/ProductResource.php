<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'product_name'  => $this->name,
            'slug'          => $this->slug,
            'description'   => $this->when($request->routeIs('products.show'), $this->description),
            'sku'           => $this->sku,
            'price_info'    => [
                'original' => $this->price,
                'formatted' => number_format($this->price, 2) // . ' EGP',
            ],
            'image_url'     => $this->image ? asset('storage/' . $this->image) : null,
            'gallery' => $this->getCachedGallery()->map(fn($img) => asset('storage/' . $img->image_path)),
            'stock_status'  => $this->stock > 0 ? 'In Stock' : 'Out of Stock',
            // 'categories'    => $this->whenLoaded('categories'),
            'categories' => $this->whenLoaded('categories', function () {
                return $this->categories->map(fn($category) => [
                    'id'   => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ]);
            }),
            'created_at'    => $this->created_at->format('Y-m-d'),
            // 'can_delete'    => $request->user()?->can('delete', $this->resource),
        ];
    }
}
