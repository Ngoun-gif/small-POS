<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *   name="KioskOrder",
 *   description="Kiosk checkout & order APIs (no login)"
 * )
 */
class KioskOrderController extends Controller
{
    private const INACTIVE_SECONDS = 180;

    private function cartBySessionOrFail(Request $request): Cart
    {
        $sessionKey = $request->header('X-Session-Key');
        if (! $sessionKey) abort(422, 'X-Session-Key header is required');

        $cart = Cart::where('session_key', $sessionKey)->firstOrFail();

        if ($cart->status !== 'ACTIVE') abort(440, 'SESSION_EXPIRED');

        if ($cart->last_activity_at && now()->diffInSeconds($cart->last_activity_at) > self::INACTIVE_SECONDS) {
            $cart->update(['status' => 'EXPIRED']);
            abort(440, 'SESSION_EXPIRED');
        }

        $cart->update(['last_activity_at' => now()]);

        return $cart;
    }

    /**
     * @OA\Post(
     *   path="/api/kiosk/checkout",
     *   tags={"KioskOrder"},
     *   summary="Checkout: convert kiosk cart to order",
     *   @OA\Parameter(name="X-Session-Key", in="header", required=true, @OA\Schema(type="string")),
     *   @OA\Response(response=201, description="Order created"),
     *   @OA\Response(response=422, description="Cart empty/stock issue"),
     *   @OA\Response(response=440, description="Session expired")
     * )
     */
    public function checkout(Request $request)
    {
        $cart = $this->cartBySessionOrFail($request);

        $items = CartItem::where('cart_id', $cart->id)->get();
        if ($items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 422);
        }

        $order = DB::transaction(function () use ($cart, $items) {
            $orderNo = 'ORD-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);

            $order = Order::create([
                'user_id' => null,
                'session_key' => $cart->session_key,
                'order_no' => $orderNo,
                'status' => 'PENDING',
                'total_amount' => 0,
            ]);

            $total = 0;

            foreach ($items as $item) {
                $variant = ProductVariant::lockForUpdate()->findOrFail($item->product_variant_id);

                if (! $variant->is_active) abort(422, 'Variant inactive: ' . $variant->id);
                if ($variant->stock_qty < $item->qty) abort(422, 'Not enough stock for SKU: ' . $variant->sku);

                $variant->update(['stock_qty' => $variant->stock_qty - $item->qty]);

                $price = (float) $variant->price;
                $subtotal = $price * $item->qty;
                $total += $subtotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $variant->id,
                    'qty' => $item->qty,
                    'price_snapshot' => $price,
                    'subtotal' => $subtotal,
                ]);
            }

            $order->update(['total_amount' => $total]);

            CartItem::where('cart_id', $cart->id)->delete();
            $cart->update(['status' => 'CHECKED_OUT']);

            return $order;
        });

        $order->load('items.variant.product');

        return response()->json([
            'message' => 'Order created',
            'order' => $order,
        ], 201);
    }

    /**
     * @OA\Get(
     *   path="/api/kiosk/orders/{orderNo}",
     *   tags={"KioskOrder"},
     *   summary="Get order by order_no (receipt screen)",
     *   @OA\Parameter(name="orderNo", in="path", required=true, @OA\Schema(type="string", example="ORD-20260115123000-1234")),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function showByOrderNo($orderNo)
    {
        $order = Order::with('items.variant.product')
            ->where('order_no', $orderNo)
            ->firstOrFail();

        return response()->json($order);
    }
}
