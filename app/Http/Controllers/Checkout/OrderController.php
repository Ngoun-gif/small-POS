<?php

namespace App\Http\Controllers\Checkout;

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
 *   name="Order",
 *   description="Checkout & Order APIs"
 * )
 */
class OrderController extends Controller
{
    private function getCartOrFail($userId): Cart
    {
        $cart = Cart::where('user_id', $userId)->first();
        if (!$cart) {
            abort(422, 'Cart not found');
        }
        return $cart;
    }

    /**
     * @OA\Post(
     *   path="/api/checkout",
     *   tags={"Order"},
     *   summary="Checkout: Create order from current cart",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=201, description="Order created"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=422, description="Cart empty / stock issue")
     * )
     */
    public function checkout()
    {
        $user = auth('web')->user();
        $cart = $this->getCartOrFail($user->id);

        $items = CartItem::where('cart_id', $cart->id)->get();
        if ($items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 422);
        }

        $order = DB::transaction(function () use ($user, $cart, $items) {

            $orderNo = 'ORD-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);

            $order = Order::create([
                'user_id' => $user->id,
                'order_no' => $orderNo,
                'status' => 'PENDING',
                'total_amount' => 0,
            ]);

            $total = 0;

            foreach ($items as $item) {
                $variant = ProductVariant::lockForUpdate()->findOrFail($item->product_variant_id);

                if (!$variant->is_active) {
                    abort(422, 'Variant inactive: ' . $variant->id);
                }

                if ($variant->stock_qty < $item->qty) {
                    abort(422, 'Not enough stock for SKU: ' . $variant->sku);
                }

                // Deduct stock now (POS-safe). If you prefer deduct on payment, we can change later.
                $variant->update([
                    'stock_qty' => $variant->stock_qty - $item->qty,
                ]);

                $price = (float) $variant->price; // current price snapshot
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

            // clear cart after successful order creation
            CartItem::where('cart_id', $cart->id)->delete();

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
     *   path="/api/orders",
     *   tags={"Order"},
     *   summary="Get my orders",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function myOrders()
    {
        $user = auth('web')->user();

        return response()->json(
            Order::where('user_id', $user->id)
                ->orderByDesc('id')
                ->paginate(20)
        );
    }

    /**
     * @OA\Get(
     *   path="/api/orders/{id}",
     *   tags={"Order"},
     *   summary="Get my order detail",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function myOrderShow($id)
    {
        $user = auth('web')->user();

        $order = Order::with('items.variant.product')
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json($order);
    }

    // =========================
    // ADMIN
    // =========================

    /**
     * @OA\Get(
     *   path="/api/admin/orders",
     *   tags={"Order"},
     *   summary="Admin: List orders",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", example="PENDING")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function adminIndex(Request $request)
    {
        $status = $request->query('status');

        return response()->json(
            Order::with('user:id,name,email')
                ->when($status, fn($q) => $q->where('status', $status))
                ->orderByDesc('id')
                ->paginate(20)
        );
    }

    /**
     * @OA\Put(
     *   path="/api/admin/orders/{id}/status",
     *   tags={"Order"},
     *   summary="Admin: Update order status",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"status"},
     *       @OA\Property(property="status", type="string", example="PAID")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Updated"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function adminUpdateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:PENDING,PAID,CANCELLED'],
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $data['status']]);

        return response()->json([
            'message' => 'Order status updated',
            'order' => $order,
        ]);
    }
}
