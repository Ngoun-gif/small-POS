<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *   name="SubCategory",
 *   description="SubCategory management APIs"
 * )
 */
class SubCategoryController extends Controller
{
    /* =========================================================
     | PUBLIC APIs
     ========================================================= */

    /**
     * @OA\Get(
     *   path="/api/categories/{categoryId}/sub-categories",
     *   tags={"SubCategory"},
     *   summary="Public: List active subcategories by category",
     *   @OA\Parameter(name="categoryId", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function publicByCategory($categoryId)
    {
        return response()->json(
            SubCategory::where('category_id', $categoryId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
        );
    }

    /**
     * @OA\Get(
     *   path="/api/sub-categories/{id}",
     *   tags={"SubCategory"},
     *   summary="Public: Show active subcategory",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function publicShow($id)
    {
        return response()->json(
            SubCategory::where('is_active', true)->findOrFail($id)
        );
    }

    /* =========================================================
     | ADMIN APIs
     ========================================================= */

    /**
     * @OA\Get(
     *   path="/api/admin/sub-categories",
     *   tags={"SubCategory"},
     *   summary="Admin: List subcategories",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="category_id", in="query", @OA\Schema(type="integer")),
     *   @OA\Parameter(name="q", in="query", @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function index(Request $request)
    {
        return response()->json(
            SubCategory::query()
                ->when($request->category_id, fn ($q) =>
                $q->where('category_id', $request->category_id)
                )
                ->when($request->q, fn ($q) =>
                $q->where('name', 'ilike', "%{$request->q}%")
                )
                ->latest()
                ->paginate(20)
        );
    }

    /**
     * @OA\Post(
     *   path="/api/admin/sub-categories",
     *   tags={"SubCategory"},
     *   summary="Admin: Create subcategory",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="multipart/form-data",
     *       @OA\Schema(
     *         required={"category_id","name"},
     *         @OA\Property(property="category_id", type="integer"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="is_active", type="boolean"),
     *         @OA\Property(property="image", type="string", format="binary")
     *       )
     *     )
     *   ),
     *   @OA\Response(response=201, description="Created")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('sub_categories', 'name')
                    ->where(fn ($q) => $q->where('category_id', $request->category_id)),
            ],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')
                ->store('sub_categories', 'public');
        }

        $sub = SubCategory::create([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'image' => $data['image'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'SubCategory created',
            'sub_category' => $sub,
        ], 201);
    }

    /**
     * @OA\Get(
     *   path="/api/admin/sub-categories/{id}",
     *   tags={"SubCategory"},
     *   summary="Admin: Show subcategory",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        return response()->json(SubCategory::findOrFail($id));
    }

    /**
     * @OA\Put(
     *   path="/api/admin/sub-categories/{id}",
     *   tags={"SubCategory"},
     *   summary="Admin: Update subcategory",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="multipart/form-data",
     *       @OA\Schema(
     *         @OA\Property(property="category_id", type="integer"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="is_active", type="boolean"),
     *         @OA\Property(property="image", type="string", format="binary")
     *       )
     *     )
     *   ),
     *   @OA\Response(response=200, description="Updated"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $sub = SubCategory::findOrFail($id);

        $data = $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (isset($data['name']) || isset($data['category_id'])) {
            $exists = SubCategory::where('category_id', $data['category_id'] ?? $sub->category_id)
                ->where('name', $data['name'] ?? $sub->name)
                ->where('id', '!=', $sub->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Name already exists in this category'
                ], 422);
            }
        }

        if ($request->hasFile('image')) {
            if ($sub->image) {
                Storage::disk('public')->delete($sub->image);
            }
            $data['image'] = $request->file('image')
                ->store('sub_categories', 'public');
        } else {
            unset($data['image']);
        }

        $sub->update($data);

        return response()->json([
            'message' => 'SubCategory updated',
            'sub_category' => $sub->fresh(),
        ]);
    }

    /**
     * @OA\Delete(
     *   path="/api/admin/sub-categories/{id}",
     *   tags={"SubCategory"},
     *   summary="Admin: Delete subcategory",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *   @OA\Response(response=200, description="Deleted"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $sub = SubCategory::findOrFail($id);

        // Delete image if exists
        if ($sub->image) {
            Storage::disk('public')->delete($sub->image);
        }

        $sub->delete();

        return response()->json(['message' => 'SubCategory deleted']);
    }
}
