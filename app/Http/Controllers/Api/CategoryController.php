<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get all active categories
        $categories = Category::where('is_active', true)->latest()->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Categories retrieved successfully',
            'data' => $categories
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id', // Make sure parent exists if provided
            'is_active' => 'nullable|boolean',
        ]);

        if (! empty($validated['parent_id'])) {
            $parent = Category::find($validated['parent_id']);

            if (! $parent || ! $parent->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Parent category must be active.',
                ], 422);
            }
        }

        // Create the new category
        $category = Category::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true, // New categories are active by default
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Category created successfully',
            'data' => $category
        ], 201); // 201 Created status code
    }

    /**
     * Display the specified resource.
     */
    public function show(string $category)
    {
        $category = Category::query()
            ->where('is_active', true)
            ->with([
                'parent',
                'children' => fn($query) => $query->where('is_active', true),
            ])
            ->findOrFail($category);

        // Return the category as a JSON response
        return response()->json([
            'status' => 'success',
            'message' => 'Category retrieved successfully',
            'data' => $category
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'is_active'   => 'sometimes|boolean',
            'parent_id'   => [
                'sometimes',
                'nullable',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($category) {
                    if ($value == $category->id) {
                        $fail('A category cannot be its own parent.');
                    }
                },
            ],
        ]);

        if (array_key_exists('parent_id', $validated) && $validated['parent_id']) {
            if ($this->wouldCreateCategoryCycle($category, (int) $validated['parent_id'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A category cannot be moved under one of its descendants.',
                ], 422);
            }
        }

        $category->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Category updated successfully',
            'data'    => $category
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        DB::transaction(function () use ($category) {
            // Move children to the parent of the category being deleted to preserve the tree.
            $category->children()->update([
                'parent_id' => $category->parent_id,
            ]);

            $category->delete();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Category deleted successfully',
        ]);
    }

    public function products(string $category)
    {
        $category = Category::query()
            ->where('is_active', true)
            ->findOrFail($category);

        $products = $category->products()
            ->active()
            ->with(['categories', 'images'])
            ->latest()
            ->get();

        return ProductResource::collection($products)->additional([
            'status' => 'success',
            'message' => 'Products of Category retrieved successfully',
        ]);
    }

    /*
    This method checks the new parent and moves up through its parents.
    If it reaches the current category, assigning that parent would create a cycle.

    Safe example:
    - Electronics (1)
    - Phones (2) -> parent_id = 1
    - Android (3) -> parent_id = 2
    - Accessories (4) -> parent_id = 1

    Suppose we want to update:
    - Android (3) -> parent_id = 4

    Check:
    - Start from 4
    - Is 4 === 3? No
    - Parent of 4 is 1
    - Is 1 === 3? No
    - Parent of 1 is null

    Result:
    - false

    This means no cycle would be created, so the update is valid.
    */
    private function wouldCreateCategoryCycle(Category $category, int $parentId): bool
    {
        $currentParentId = $parentId;

        while ($currentParentId) {
            if ($currentParentId === $category->id) {
                return true;
            }

            $currentParentId = Category::whereKey($currentParentId)->value('parent_id');
        }

        return false;
    }
}
