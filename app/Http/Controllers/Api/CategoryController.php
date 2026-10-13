<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('children')->whereNull('parent_id')->get();
        return CategoryResource::collection($categories);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255|unique:categories,name',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Prevent assigning to a sub-category as parent (one level max)
        if ($request->filled('parent_id')) {
            $parent = Category::find($request->parent_id);
            if ($parent && $parent->parent_id !== null) {
                return response()->json(['message' => 'Une sous-catégorie ne peut pas être parente d\'une autre sous-catégorie.'], 422);
            }
        }

        $category = Category::create($validator->validated());
        return new CategoryResource($category);
    }

    public function show(Category $category)
    {
        return new CategoryResource($category->load('children', 'resources'));
    }

    public function update(Request $request, Category $category)
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'sometimes|required|string|max:255',
            'parent_id' => [
                'nullable',
                'exists:categories,id',
                // S1: prevent self-reference
                function ($attribute, $value, $fail) use ($category) {
                    if ($value == $category->id) {
                        $fail('Une catégorie ne peut pas être son propre parent.');
                    }
                    // Prevent circular hierarchy (descendant becoming parent)
                    if ($value && $this->isDescendant($category, (int) $value)) {
                        $fail('Impossible de créer une hiérarchie circulaire.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category->update($validator->validated());
        return new CategoryResource($category);
    }

    public function destroy(Category $category)
    {
        if ($category->resources()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer : des ressources sont encore rattachées à cette catégorie.'
            ], 409);
        }

        $category->delete();
        return response()->json(['message' => 'Catégorie supprimée'], 200);
    }

    /**
     * Check if $potentialDescendantId is a descendant of $category.
     */
    private function isDescendant(Category $category, int $potentialDescendantId): bool
    {
        foreach ($category->children as $child) {
            if ($child->id === $potentialDescendantId) return true;
            if ($this->isDescendant($child, $potentialDescendantId)) return true;
        }
        return false;
    }
}
