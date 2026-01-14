<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *   name="Product",
 *   description="Product management APIs"
 * )
 */
class ProductController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/sub-categories/{subCategoryId}/products",
     *   tags={"Product"},
     *   summary="Public: List active products by subcategory",
     *   @OA\Parameter(
     *     name="subCategoryId", in="path", required=true,
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function publicBySubCategory($subCategoryId)
    {
        return response()->json(
            Product::query()
                ->where('sub_category_id', $subCategoryId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
        );
    }

    /**
     * @OA\Get(
     *   path="/api/products/{id}",
     *   tags={"Product"},
     *   summary="Public: Show active product by ID",
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
        $product = Product::where('is_active', true)->findOrFail($id);
        return response()->json($product);
    }

    // =========================
    // ADMIN CRUD
    // =========================

    /**
     * @OA\Get(
     *   path="/api/admin/products",
     *   tags={"Product"},
     *   summary="Admin: List products (paginated)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="sub_category_id", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *   @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string", example="coke")),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden (not ADMIN)")
     * )
     */
    public function index(Request $request)
    {
        $q = $request->query('q');
        $subCategoryId = $request->query('sub_category_id');

        return response()->json(
            Product::query()
                ->when($subCategoryId, fn($qr) => $qr->where('sub_category_id', $subCategoryId))
                ->when($q, fn($qr) => $qr->where('name', 'ilike', "%{$q}%"))
                ->orderByDesc('id')
                ->paginate(20)
        );
    }

    /**
     * @OA\Post(
     *   path="/api/admin/products",
     *   tags={"Product"},
     *   summary="Admin: Create product",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"sub_category_id","name"},
     *       @OA\Property(property="sub_category_id", type="integer", example=1),
     *       @OA\Property(property="name", type="string", example="Coca Cola 330ml"),
     *       @OA\Property(property="description", type="string", example="Soft drink"),
     *       @OA\Property(property="thumbnail", type="string", example="uploads/products/coke.png"),
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
            'sub_category_id' => ['required', 'integer', 'exists:sub_categories,id'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('products', 'name')
                    ->where(fn($q) => $q->where('sub_category_id', $request->sub_category_id)),
            ],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $product = Product::create([
            'sub_category_id' => $data['sub_category_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'thumbnail' => $data['thumbnail'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Product created',
            'product' => $product,
        ], 201);
    }

    /**
     * @OA\Get(
     *   path="/api/admin/products/{id}",
     *   tags={"Product"},
     *   summary="Admin: Show product by ID",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=10)),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        return response()->json(Product::findOrFail($id));
    }

    /**
     * @OA\Put(
     *   path="/api/admin/products/{id}",
     *   tags={"Product"},
     *   summary="Admin: Update product",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=10)),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="sub_category_id", type="integer", example=1),
     *       @OA\Property(property="name", type="string", example="Updated Product"),
     *       @OA\Property(property="description", type="string", example="Updated desc"),
     *       @OA\Property(property="thumbnail", type="string", example="uploads/products/updated.png"),
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
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'sub_category_id' => ['sometimes', 'integer', 'exists:sub_categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // If name/sub_category changed, ensure unique per subcategory
        if (isset($data['name']) || isset($data['sub_category_id'])) {
            $newSubId = $data['sub_category_id'] ?? $product->sub_category_id;
            $newName = $data['name'] ?? $product->name;

            $exists = Product::where('sub_category_id', $newSubId)
                ->where('name', $newName)
                ->where('id', '!=', $product->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'The name has already been taken for this subcategory.'
                ], 422);
            }
        }

        $product->update($data);

        return response()->json([
            'message' => 'Product updated',
            'product' => $product->fresh(),
        ]);
    }

    /**
     * @OA\Delete(
     *   path="/api/admin/products/{id}",
     *   tags={"Product"},
     *   summary="Admin: Delete product",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=10)),
     *   @OA\Response(response=200, description="Deleted"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Product deleted']);
    }
}
