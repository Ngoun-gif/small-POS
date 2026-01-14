<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *   name="SubCategory",
 *   description="SubCategory management APIs"
 * )
 */
class SubCategoryController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/categories/{categoryId}/sub-categories",
     *   tags={"SubCategory"},
     *   summary="Public: List active subcategories by category",
     *   @OA\Parameter(
     *     name="categoryId", in="path", required=true,
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function publicByCategory($categoryId)
    {
        return response()->json(
            SubCategory::query()
                ->where('category_id', $categoryId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
        );
    }

    /**
     * @OA\Get(
     *   path="/api/sub-categories/{id}",
     *   tags={"SubCategory"},
     *   summary="Public: Show active subcategory by ID",
     *   @OA\Parameter(
     *     name="id", in="path", required=true,
     *     @OA\Schema(type="integer", example=10)
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function publicShow($id)
    {
        $sub = SubCategory::where('is_active', true)->findOrFail($id);
        return response()->json($sub);
    }

    // =========================
    // ADMIN CRUD
    // =========================

    /**
     * @OA\Get(
     *   path="/api/admin/sub-categories",
     *   tags={"SubCategory"},
     *   summary="Admin: List subcategories (paginated)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="category_id", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *   @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string", example="milk")),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden (not ADMIN)")
     * )
     */
    public function index(Request $request)
    {
        $q = $request->query('q');
        $categoryId = $request->query('category_id');

        return response()->json(
            SubCategory::query()
                ->when($categoryId, fn($qr) => $qr->where('category_id', $categoryId))
                ->when($q, fn($qr) => $qr->where('name', 'ilike', "%{$q}%"))
                ->orderByDesc('id')
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
     *     @OA\JsonContent(
     *       required={"category_id","name"},
     *       @OA\Property(property="category_id", type="integer", example=1),
     *       @OA\Property(property="name", type="string", example="Soft Drinks"),
     *       @OA\Property(property="image", type="string", example="uploads/sub_categories/soft-drinks.png"),
     *       @OA\Property(property="is_active", type="boolean", example=true)
     *     )
     *   ),
     *   @OA\Response(response=201, description="Created"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('sub_categories', 'name')
                    ->where(fn($q) => $q->where('category_id', $request->category_id)),
            ],
            'image' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

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
     *   summary="Admin: Show subcategory by ID",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=10)),
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
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=10)),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="category_id", type="integer", example=1),
     *       @OA\Property(property="name", type="string", example="Updated Name"),
     *       @OA\Property(property="image", type="string", example="uploads/sub_categories/updated.png"),
     *       @OA\Property(property="is_active", type="boolean", example=true)
     *     )
     *   ),
     *   @OA\Response(response=200, description="Updated"),
     *   @OA\Response(response=422, description="Validation error"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $sub = SubCategory::findOrFail($id);

        $data = $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'image' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // If name/category changed, ensure unique per category
        if (isset($data['name']) || isset($data['category_id'])) {
            $newCategoryId = $data['category_id'] ?? $sub->category_id;
            $newName = $data['name'] ?? $sub->name;

            $exists = SubCategory::where('category_id', $newCategoryId)
                ->where('name', $newName)
                ->where('id', '!=', $sub->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'The name has already been taken for this category.'
                ], 422);
            }
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
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=10)),
     *   @OA\Response(response=200, description="Deleted"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $sub = SubCategory::findOrFail($id);
        $sub->delete();

        return response()->json(['message' => 'SubCategory deleted']);
    }
}
