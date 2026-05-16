<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $version = Cache::get('products_cache_version', 1);
        $page = $request->get('page', 1);
        $cacheKey = "v{$version}_products_page_{$page}";

        $products = Cache::remember($cacheKey, 3600, function () use ($request) {
            return Product::with(['categories', 'images'])
                ->filter($request)
                ->latest()
                ->paginate($request->get('per_page', 9));
        });

        return ProductResource::collection($products)
            ->additional([
                'success' => true,
                'message' => 'Products retrieved successfully',
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'integer|min:0',
            'sku' => 'required|string|max:255|unique:products',
            'is_active' => 'boolean',
            'categories' => 'array',
            'categories.*' => 'exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // 2MB
            'gallery' => 'nullable|array',
            'gallery.*' => 'image|mimes:jpg,png,jpeg|max:2048',

        ]);

        // check if image uploaded
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->storeAs('products', $data['slug'], 'public');
        }

        DB::beginTransaction();

        try {
            $product = Product::create($data);

            if ($request->hasFile('gallery')) {
                foreach ($request->file('gallery') as $file) {
                    $path = $file->store('products/gallery', 'public');
                    $product->images()->create(['image_path' => $path]);
                }
            }
            // attach categories
            if ($request->has('categories')) {
                $product->categories()->attach($data['categories']);
                $product->load('categories');
            }

            $this->clearCache($product);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product->load(['categories', 'images'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {

        $productCached = Cache::remember('product_' . $product->id, 3600, function () use ($product) {
            return $product->load('categories');
        });

        return (new ProductResource($productCached))
            ->additional([
                'success' => true,
                'message' => 'Product retrieved successfully'
            ])
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {

        $validated = $request->validated();

        if ($request->hasFile('gallery')) {
            foreach ($request->file('gallery') as $imageFile) {
                $path = $imageFile->store('products/gallery', 'public');

                $product->images()->create([
                    'image_path' => $path
                ]);
            }

            Cache::forget("product_gallery_{$product->id}");
        }

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($product->image);

            $validated['image'] = $request->file('image')->storeAs('products', $product->slug, 'public');
        }

        // 3. تحديث الموديل بالكامل (بما في ذلك الصورة والبيانات الأخرى)
        $product->update($validated);

        // 4. تحديث التصنيفات (العلاقات)
        if ($request->has('categories')) {
            $product->categories()->sync($request->categories);
        }

        $this->clearCache($product);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data'    => $product->load('categories')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        foreach ($product->images as $img) {
            Storage::disk('public')->delete($img->image_path);
        }

        $this->clearCache($product);
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ], 200);
    }

    // undo soft delete
    public function undoDelete(Request $request, Product $product)
    {
        if ($request->user()->hasRole('admin')) {
            $product->restore();
            return response()->json([
                'success' => true,
                'message' => 'Product restored successfully',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'You are not authorized to perform this action',
        ], 403);
    }
    // permanent delete
    public function permanentDelete(Request $request, Product $product)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to perform this action',
            ], 403);
        }
        // $product = Product::withTrashed()->findOrFail($id);
        $product->withTrashed();

        // Delete image
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->forceDelete();

        $this->clearCache($product);

        return response()->json([
            'success' => true,
            'message' => 'Product permanently deleted successfully',
        ]);
    }

    // index of admin products
    public function adminIndex(Request $request)
    {
        // get all products (default)
        if ($request->user()->hasRole('admin')) {
            $products = Product::withTrashed()->get();
            return response()->json([
                'success' => true,
                'message' => 'Products retrieved successfully',
                'data' => $products
            ], 200);
        }
        return response()->json([
            'success' => false,
            'message' => 'You are not authorized to perform this action',
        ], 403);
    }

    private function clearCache(?Product $product): void
    {
        Cache::increment('products_cache_version');

        if ($product) {
            Cache::forget("product_{$product->id}");
            Cache::forget("product_gallery_{$product->id}");
        }
    }

    private function uploadImage($image, string $slug): string
    {
        $extension = $image->getClientOriginalExtension();
        $filename = $slug . '-' . time() . '.' . $extension;

        return $image->storeAs('products', $filename, 'public');
    }
}
