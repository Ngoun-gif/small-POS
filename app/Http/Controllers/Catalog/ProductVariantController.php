<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *   name="ProductVariant",
 *   description="Product Variant management APIs"
 * )
 */
class ProductVariantController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/products/{productId}/variants",
     *   tags={"ProductVariant"},
     *   summary="Public: List active variants by product",
     *   @OA\Parameter(
     *     name="productId", in="path", required=true,
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function publicByProduct($productId)
    {
        return response()->json(
            ProductVariant::query()
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->orderBy('id')
                ->get()
        );
    }

    /**
     * @OA\Get(
     *   path="/api/variants/{id}",
     *   tags={"ProductVariant"},
     *   summary="Public: Show active variant by ID",
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
        $variant = ProductVariant::where('is_active', true)->findOrFail($id);
        return response()->json($variant);
    }

    // =========================
    // ADMIN CRUD
    // =========================

    /**
     * @OA\Get(
     *   path="/api/admin/variants",
     *   tags={"ProductVariant"},
     *   summary="Admin: List variants (paginated)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="product_id", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *   @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string", example="SKU-001")),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden (not ADMIN)")
     * )
     */
    public function index(Request $request)
    {
        $q = $request->query('q');
        $productId = $request->query('product_id');

        return response()->json(
            ProductVariant::query()
                ->when($productId, fn($qr) => $qr->where('product_id', $productId))
                ->when($q, fn($qr) => $qr->where('sku', 'ilike', "%{$q}%"))
                ->orderByDesc('id')
                ->paginate(20)
        );
    }

    /**
     * @OA\Post(
     *   path="/api/admin/variants",
     *   tags={"ProductVariant"},
     *   summary="Admin: Create variant",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"product_id","sku","price","stock_qty"},
     *       @OA\Property(property="product_id", type="integer", example=1),
     *       @OA\Property(property="sku", type="string", example="SKU-001"),
     *       @OA\Property(property="price", type="number", format="float", example=1.50),
     *       @OA\Property(property="stock_qty", type="integer", example=100),
     *       @OA\Property(property="image", type="string", example="uploads/variants/sku-001.png"),
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'sku' => ['required', 'string', 'max:255', 'unique:product_variants,sku'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock_qty' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $variant = ProductVariant::create([
            'product_id' => $data['product_id'],
            'sku' => $data['sku'],
            'price' => $data['price'],
            'stock_qty' => $data['stock_qty'],
            'image' => $data['image'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Variant created',
            'variant' => $variant,
        ], 201);
    }

    /**
     * @OA\Get(
     *   path="/api/admin/variants/{id}",
     *   tags={"ProductVariant"},
     *   summary="Admin: Show variant by ID",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=10)),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        return response()->json(ProductVariant::findOrFail($id));
    }

    /**
     * @OA\Put(
     *   path="/api/admin/variants/{id}",
     *   tags={"ProductVariant"},
     *   summary="Admin: Update variant",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=10)),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="product_id", type="integer", example=1),
     *       @OA\Property(property="sku", type="string", example="SKU-001-NEW"),
     *       @OA\Property(property="price", type="number", format="float", example=2.25),
     *       @OA\Property(property="stock_qty", type="integer", example=50),
     *       @OA\Property(property="image", type="string", example="uploads/variants/sku-001-new.png"),
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
        $variant = ProductVariant::findOrFail($id);

        $data = $request->validate([
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'sku' => ['sometimes', 'string', 'max:255', Rule::unique('product_variants', 'sku')->ignore($variant->id)],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'stock_qty' => ['sometimes', 'integer', 'min:0'],
            'image' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $variant->update($data);

        return response()->json([
            'message' => 'Variant updated',
            'variant' => $variant->fresh(),
        ]);
    }

    /**
     * @OA\Delete(
     *   path="/api/admin/variants/{id}",
     *   tags={"ProductVariant"},
     *   summary="Admin: Delete variant",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=10)),
     *   @OA\Response(response=200, description="Deleted"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $variant = ProductVariant::findOrFail($id);
        $variant->delete();

        return response()->json(['message' => 'Variant deleted']);
    }
}
