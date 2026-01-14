<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *   name="Cart",
 *   description="Cart APIs"
 * )
 */
class CartController extends Controller
{
    private function getOrCreateCart($userId): Cart
    {
        return Cart::firstOrCreate(['user_id' => $userId]);
    }

    /**
     * @OA\Get(
     *   path="/api/cart",
     *   tags={"Cart"},
     *   summary="Get current user's cart",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show()
    {
        $user = auth('web')->user();
        $cart = $this->getOrCreateCart($user->id);

        $cart->load([
            'items.variant.product'
        ]);

        $total = $cart->items->sum(fn($i) => $i->qty * (float) $i->price_snapshot);

        return response()->json([
            'cart' => $cart,
            'total' => round($total, 2),
        ]);
    }

    /**
     * @OA\Post(
     *   path="/api/cart/items",
     *   tags={"Cart"},
     *   summary="Add item to cart (or increase qty if exists)",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"product_variant_id","qty"},
     *       @OA\Property(property="product_variant_id", type="integer", example=1),
     *       @OA\Property(property="qty", type="integer", example=2)
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function addItem(Request $request)
    {
        $user = auth('web')->user();
        $cart = $this->getOrCreateCart($user->id);

        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        $variant = ProductVariant::where('is_active', true)->findOrFail($data['product_variant_id']);

        // Stock check (basic)
        if ($variant->stock_qty < $data['qty']) {
            return response()->json(['message' => 'Not enough stock'], 422);
        }

        $item = CartItem::where('cart_id', $cart->id)
            ->where('product_variant_id', $variant->id)
            ->first();

        if ($item) {
            $newQty = $item->qty + $data['qty'];

            if ($variant->stock_qty < $newQty) {
                return response()->json(['message' => 'Not enough stock'], 422);
            }

            $item->update([
                'qty' => $newQty,
                'price_snapshot' => $variant->price,
            ]);
        } else {
            $item = CartItem::create([
                'cart_id' => $cart->id,
                'product_variant_id' => $variant->id,
                'qty' => $data['qty'],
                'price_snapshot' => $variant->price,
            ]);
        }

        return $this->show();
    }

    /**
     * @OA\Put(
     *   path="/api/cart/items/{id}",
     *   tags={"Cart"},
     *   summary="Update cart item qty",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"qty"},
     *       @OA\Property(property="qty", type="integer", example=3)
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not found"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateItem(Request $request, $id)
    {
        $user = auth('web')->user();
        $cart = $this->getOrCreateCart($user->id);

        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        $item = CartItem::where('cart_id', $cart->id)->findOrFail($id);
        $variant = ProductVariant::findOrFail($item->product_variant_id);

        if ($variant->stock_qty < $data['qty']) {
            return response()->json(['message' => 'Not enough stock'], 422);
        }

        $item->update([
            'qty' => $data['qty'],
            'price_snapshot' => $variant->price,
        ]);

        return $this->show();
    }

    /**
     * @OA\Delete(
     *   path="/api/cart/items/{id}",
     *   tags={"Cart"},
     *   summary="Remove item from cart",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function removeItem($id)
    {
        $user = auth('web')->user();
        $cart = $this->getOrCreateCart($user->id);

        $item = CartItem::where('cart_id', $cart->id)->findOrFail($id);
        $item->delete();

        return $this->show();
    }

    /**
     * @OA\Delete(
     *   path="/api/cart/clear",
     *   tags={"Cart"},
     *   summary="Clear cart items",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function clear()
    {
        $user = auth('web')->user();
        $cart = $this->getOrCreateCart($user->id);

        CartItem::where('cart_id', $cart->id)->delete();

        return $this->show();
    }
}
