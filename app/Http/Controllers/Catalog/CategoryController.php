<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *   name="Category",
 *   description="Category management APIs"
 * )
 */
class CategoryController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/categories",
     *   tags={"Category"},
     *   summary="Public: List active categories",
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function publicIndex()
    {
        return response()->json(
            Category::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
        );
    }

    /**
     * @OA\Get(
     *   path="/api/categories/{id}",
     *   tags={"Category"},
     *   summary="Public: Show active category by ID",
     *   @OA\Parameter(
     *     name="id", in="path", required=true,
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function publicShow($id)
    {
        $cat = Category::where('is_active', true)->findOrFail($id);
        return response()->json($cat);
    }

    // =========================
    // ADMIN CRUD
    // =========================

    /**
     * @OA\Get(
     *   path="/api/admin/categories",
     *   tags={"Category"},
     *   summary="Admin: List categories (paginated)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="q", in="query", required=false,
     *     @OA\Schema(type="string", example="food")
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden (not ADMIN)")
     * )
     */
    public function index(Request $request)
    {
        $q = $request->query('q');

        return response()->json(
            Category::query()
                ->when($q, fn($qr) => $qr->where('name', 'ilike', "%{$q}%"))
                ->orderByDesc('id')
                ->paginate(20)
        );
    }

    /**
     * @OA\Post(
     *   path="/api/admin/categories",
     *   tags={"Category"},
     *   summary="Admin: Create category",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name"},
     *       @OA\Property(property="name", type="string", example="Beverages"),
     *       @OA\Property(property="image", type="string", example="uploads/categories/beverages.png"),
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
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
            'image' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $cat = Category::create([
            'name' => $data['name'],
            'image' => $data['image'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Category created',
            'category' => $cat,
        ], 201);
    }

    /**
     * @OA\Get(
     *   path="/api/admin/categories/{id}",
     *   tags={"Category"},
     *   summary="Admin: Show category by ID",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id", in="path", required=true,
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        return response()->json(Category::findOrFail($id));
    }

    /**
     * @OA\Put(
     *   path="/api/admin/categories/{id}",
     *   tags={"Category"},
     *   summary="Admin: Update category",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id", in="path", required=true,
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="name", type="string", example="Food"),
     *       @OA\Property(property="image", type="string", example="uploads/categories/food.png"),
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
        $cat = Category::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($cat->id)],
            'image' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $cat->update($data);

        return response()->json([
            'message' => 'Category updated',
            'category' => $cat->fresh(),
        ]);
    }

    /**
     * @OA\Delete(
     *   path="/api/admin/categories/{id}",
     *   tags={"Category"},
     *   summary="Admin: Delete category",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id", in="path", required=true,
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *   @OA\Response(response=200, description="Deleted"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $cat = Category::findOrFail($id);
        $cat->delete();

        return response()->json(['message' => 'Category deleted']);
    }
}
