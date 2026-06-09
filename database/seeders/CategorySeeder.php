<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Electronics', 'description' => 'Phones, accessories, and smart devices.'],
            ['name' => 'Fashion', 'description' => 'Everyday clothing and wearable essentials.'],
            ['name' => 'Home', 'description' => 'Useful products for home and kitchen.'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                $category + [
                    'slug' => Str::slug($category['name']),
                    'is_active' => true,
                    'parent_id' => null,
                ],
            );
        }
    }
}
