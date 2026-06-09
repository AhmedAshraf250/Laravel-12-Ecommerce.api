<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            ['category' => 'Electronics', 'name' => 'Wireless Earbuds', 'description' => 'Compact earbuds with charging case.', 'price' => 79.99, 'stock' => 40, 'sku' => 'ELEC-001'],
            ['category' => 'Electronics', 'name' => 'Smart Watch', 'description' => 'Fitness tracking smart watch.', 'price' => 149.99, 'stock' => 25, 'sku' => 'ELEC-002'],
            ['category' => 'Electronics', 'name' => 'USB-C Charger', 'description' => 'Fast charging USB-C wall charger.', 'price' => 24.99, 'stock' => 80, 'sku' => 'ELEC-003'],
            ['category' => 'Fashion', 'name' => 'Classic Hoodie', 'description' => 'Soft cotton hoodie for daily wear.', 'price' => 45.00, 'stock' => 35, 'sku' => 'FASH-001'],
            ['category' => 'Fashion', 'name' => 'Running Shoes', 'description' => 'Lightweight shoes for everyday running.', 'price' => 89.50, 'stock' => 30, 'sku' => 'FASH-002'],
            ['category' => 'Fashion', 'name' => 'Leather Belt', 'description' => 'Durable leather belt with metal buckle.', 'price' => 32.00, 'stock' => 50, 'sku' => 'FASH-003'],
            ['category' => 'Home', 'name' => 'Ceramic Mug Set', 'description' => 'Set of four ceramic mugs.', 'price' => 19.99, 'stock' => 60, 'sku' => 'HOME-001'],
            ['category' => 'Home', 'name' => 'Desk Lamp', 'description' => 'Adjustable LED desk lamp.', 'price' => 39.99, 'stock' => 22, 'sku' => 'HOME-002'],
            ['category' => 'Home', 'name' => 'Storage Basket', 'description' => 'Woven basket for home organization.', 'price' => 27.50, 'stock' => 45, 'sku' => 'HOME-003'],
            ['category' => 'Home', 'name' => 'Kitchen Scale', 'description' => 'Digital kitchen scale for precise cooking.', 'price' => 34.99, 'stock' => 28, 'sku' => 'HOME-004'],
        ];

        foreach ($products as $productData) {
            $category = Category::where('name', $productData['category'])->firstOrFail();

            $product = Product::firstOrNew(['sku' => $productData['sku']]);
            $product->forceFill([
                    'name' => $productData['name'],
                    'slug' => Str::slug($productData['name']),
                    'description' => $productData['description'],
                    'price' => $productData['price'],
                    'stock' => $productData['stock'],
                    'is_active' => true,
                    'image' => null,
            ])->save();

            $product->categories()->syncWithoutDetaching([$category->id]);
        }
    }
}
