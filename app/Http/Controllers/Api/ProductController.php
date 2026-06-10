<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $version = Cache::get('products_cache_version', 1);
        $cacheParams = [
            'page' => (int) $request->integer('page', 1),
            'per_page' => (int) $request->integer('per_page', 9),
            'search' => $request->string('search')->toString(),
            'category' => $request->get('category'),
            'min_price' => $request->get('min_price'),
            'max_price' => $request->get('max_price'),
        ];
        $cacheKey = 'products:' . $version . ':' . md5(json_encode($cacheParams));

        $products = Cache::remember($cacheKey, 3600, function () use ($request) {
            return Product::query()
                ->active()
                ->with(['categories', 'images'])
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

        DB::beginTransaction();

        try {
            // `image` stores the main product image on the product record itself.
            if ($request->hasFile('image')) {
                $data['image'] = $this->uploadImage($request->file('image'));
            }

            $product = Product::create($data);

            // `gallery` stores any additional product images in the related table.
            if ($request->hasFile('gallery')) {
                foreach ($request->file('gallery') as $file) {
                    $path = $this->uploadGalleryImage($file);
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
    public function show(string $product)
    {
        $product = Product::query()
            ->active()
            ->with(['categories', 'images'])
            ->findOrFail($product);

        $productCached = Cache::remember('product_' . $product->id, 3600, function () use ($product) {
            return $product->load(['categories', 'images']);
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
        $oldImagePath = $product->image;
        $newImagePath = null;
        $newGalleryPaths = [];

        DB::beginTransaction();

        try {
            if ($request->hasFile('image')) {
                $newImagePath = $this->uploadImage($request->file('image'));
                $validated['image'] = $newImagePath;
            }

            $product->update($validated);

            if ($request->has('categories')) {
                $product->categories()->sync($request->categories);
            }

            if ($request->hasFile('gallery')) {
                foreach ($request->file('gallery') as $imageFile) {
                    $path = $this->uploadGalleryImage($imageFile);
                    $newGalleryPaths[] = $path;

                    $product->images()->create([
                        'image_path' => $path,
                    ]);
                }
            }

            DB::commit();

            if ($newImagePath && $oldImagePath) {
                Storage::disk('public')->delete($oldImagePath);
            }

            $this->clearCache($product);

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product->load(['categories', 'images']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }

            foreach ($newGalleryPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to update product: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();
        $this->clearCache($product);

        return response()->json([
            'success' => true,
            'message' => 'Product archived successfully',
        ], 200);
    }

    // undo soft delete
    public function undoDelete(Request $request, string $product)
    {
        if ($request->user()->hasRole('admin')) {
            $product = Product::withTrashed()->findOrFail($product);
            $product->restore();
            $this->clearCache($product);

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
    public function permanentDelete(Request $request, string $product)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to perform this action',
            ], 403);
        }

        $product = Product::withTrashed()
            ->with('images')
            ->findOrFail($product);

        $mainImagePath = $product->image;
        $galleryPaths = $product->images->pluck('image_path')->filter()->all();

        DB::transaction(function () use ($product) {
            $product->forceDelete();
        });

        if ($mainImagePath) {
            Storage::disk('public')->delete($mainImagePath);
        }

        if ($galleryPaths) {
            Storage::disk('public')->delete($galleryPaths);
        }

        $this->clearCache($product);

        return response()->json([
            'success' => true,
            'message' => 'Product permanently deleted successfully',
        ]);
    }

    public function adminIndex(Request $request)
    {
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data' => Product::withTrashed()
                ->with(['categories', 'images'])
                ->latest()
                ->paginate($perPage),
        ]);
    }

    private function clearCache(?Product $product): void
    {
        Cache::increment('products_cache_version');

        if ($product) {
            Cache::forget("product_{$product->id}");
            // Cache::forget("product_gallery_{$product->id}");
        }
    }

    private function uploadImage(UploadedFile $image): string
    {
        return $image->store('products', 'public');
    }

    private function uploadGalleryImage(UploadedFile $image): string
    {
        return $image->store('products/gallery', 'public');
    }
}
