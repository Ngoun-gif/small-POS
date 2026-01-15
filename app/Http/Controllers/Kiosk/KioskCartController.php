<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *   name="KioskCart",
 *   description="Kiosk cart APIs (no login, session-based)"
 * )
 */
class KioskCartController extends Controller
{
    private const INACTIVE_SECONDS = 180;

    private function getCartBySessionOrFail(Request $request): Cart
    {
        $sessionKey = $request->header('X-Session-Key');
        if (! $sessionKey) {
            abort(422, 'X-Session-Key header is required');
        }

        $cart = Cart::where('session_key', $sessionKey)->firstOrFail();

        if ($cart->last_activity_at && now()->diffInSeconds($cart->last_activity_at) > self::INACTIVE_SECONDS) {
            $cart->update(['status' => 'EXPIRED']);
            abort(440, 'SESSION_EXPIRED');
        }

        $cart->update(['last_activity_at' => now()]);
        return $cart;
    }

    /**
     * @OA\Post(
     *   path="/api/kiosk/cart/init",
     *   tags={"KioskCart"},
     *   summary="Create new kiosk cart session (returns session_key)",
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="session_key", type="string", example="b8b1c7b6-1b0a-4d06-b1d9-1a7d30b7a111"),
     *       @OA\Property(property="cart_id", type="integer", example=1)
     *     )
     *   )
     * )
     */
    public function init()
    {
        $cart = Cart::create([
            'session_key' => (string) Str::uuid(),
            'status' => 'ACTIVE',
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'session_key' => $cart->session_key,
            'cart_id' => $cart->id,
        ]);
    }

    /**
     * @OA\Get(
     *   path="/api/kiosk/cart",
     *   tags={"KioskCart"},
     *   summary="Get kiosk cart by session key",
     *   @OA\Parameter(
     *     name="X-Session-Key",
     *     in="header",
     *     required=true,
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=422, description="Missing session key"),
     *   @OA\Response(response=440, description="Session expired")
     * )
     */
    public function show(Request $request)
    {
        $cart = $this->getCartBySessionOrFail($request);

        $cart->load(['items.variant.product']);
        $total = $cart->items->sum(fn($i) => $i->qty * (float) $i->price_snapshot);

        return response()->json([
            'cart' => $cart,
            'total' => round($total, 2),
        ]);
    }

    /**
     * @OA\Post(
     *   path="/api/kiosk/cart/items",
     *   tags={"KioskCart"},
     *   summary="Add item to kiosk cart (or increase qty if exists)",
     *   @OA\Parameter(
     *     name="X-Session-Key",
     *     in="header",
     *     required=true,
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"product_variant_id","qty"},
     *       @OA\Property(property="product_variant_id", type="integer", example=1),
     *       @OA\Property(property="qty", type="integer", example=2)
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=422, description="Validation/stock error"),
     *   @OA\Response(response=440, description="Session expired")
     * )
     */
    public function addItem(Request $request)
    {
        $cart = $this->getCartBySessionOrFail($request);

        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        $variant = ProductVariant::where('is_active', true)->findOrFail($data['product_variant_id']);

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
            CartItem::create([
                'cart_id' => $cart->id,
                'product_variant_id' => $variant->id,
                'qty' => $data['qty'],
                'price_snapshot' => $variant->price,
            ]);
        }

        return $this->show($request);
    }

    /**
     * @OA\Put(
     *   path="/api/kiosk/cart/items/{id}",
     *   tags={"KioskCart"},
     *   summary="Update kiosk cart item qty",
     *   @OA\Parameter(name="X-Session-Key", in="header", required=true, @OA\Schema(type="string")),
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"qty"},
     *       @OA\Property(property="qty", type="integer", example=3)
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=422, description="Validation/stock error"),
     *   @OA\Response(response=440, description="Session expired")
     * )
     */
    public function updateItem(Request $request, $id)
    {
        $cart = $this->getCartBySessionOrFail($request);

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

        return $this->show($request);
    }

    /**
     * @OA\Delete(
     *   path="/api/kiosk/cart/items/{id}",
     *   tags={"KioskCart"},
     *   summary="Remove item from kiosk cart",
     *   @OA\Parameter(name="X-Session-Key", in="header", required=true, @OA\Schema(type="string")),
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=440, description="Session expired")
     * )
     */
    public function removeItem(Request $request, $id)
    {
        $cart = $this->getCartBySessionOrFail($request);

        $item = CartItem::where('cart_id', $cart->id)->findOrFail($id);
        $item->delete();

        return $this->show($request);
    }

    /**
     * @OA\Delete(
     *   path="/api/kiosk/cart/clear",
     *   tags={"KioskCart"},
     *   summary="Clear kiosk cart items",
     *   @OA\Parameter(name="X-Session-Key", in="header", required=true, @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=440, description="Session expired")
     * )
     */
    public function clear(Request $request)
    {
        $cart = $this->getCartBySessionOrFail($request);

        CartItem::where('cart_id', $cart->id)->delete();

        return $this->show($request);
    }

    /**
     * @OA\Post(
     *   path="/api/kiosk/cart/ping",
     *   tags={"KioskCart"},
     *   summary="Refresh session activity (Continue button)",
     *   @OA\Parameter(name="X-Session-Key", in="header", required=true, @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=440, description="Session expired")
     * )
     */
    public function ping(Request $request)
    {
        $cart = $this->getCartBySessionOrFail($request);

        return response()->json([
            'message' => 'OK',
            'last_activity_at' => $cart->last_activity_at,
        ]);
    }
}
