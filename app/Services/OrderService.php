<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function create(array $cart, array $data, ?int $userId, ?int $cashierId = null, int $attempt = 0): Order
    {
        try {
            return DB::transaction(function () use ($cart, $data, $userId, $cashierId) {
                if (! empty($data['request_key'])) {
                    $existing = Order::where('request_key', $data['request_key'])->first();
                    if ($existing) {
                        abort_unless($existing->user_id === $userId && $existing->cashier_id === $cashierId, 403);

                        return $existing;
                    }
                }
                if (! $cart || count($cart) > 100) {
                    throw ValidationException::withMessages(['cart' => 'Keranjang kosong atau melebihi 100 jenis produk.']);
                }
                ksort($cart);
                $lines = [];
                $subtotal = 0;
                $weight = 0;
                foreach ($cart as $id => $qty) {
                    $qty = filter_var($qty, FILTER_VALIDATE_INT);
                    $p = Product::lockForUpdate()->find($id);
                    if (! $p || ! $p->active || ! $qty || $qty < 1 || $qty > 999 || $p->stock < $qty) {
                        throw ValidationException::withMessages(['cart' => 'Stok produk tidak mencukupi. Periksa kembali keranjang Anda.']);
                    }
                    $lines[] = [$p, $qty];
                    $subtotal += $p->price * $qty;
                    $weight += $p->weight * $qty;
                }
                $shipping = 0;
                if ($data['fulfillment'] === 'shipping') {
                    $rate = config('shop.shipping_rates')[$data['courier'] ?? ''] ?? null;
                    if (! $rate || empty($data['address']) || empty($data['phone'])) {
                        throw ValidationException::withMessages(['courier' => 'Alamat, telepon, dan tarif pengiriman yang berlaku harus tersedia.']);
                    }
                    $shipping = app(ShippingService::class)->quote($data['courier'], $data['shipping_zone'] ?? 'padang', $weight);
                    if ($data['payment_method'] !== 'midtrans') {
                        throw ValidationException::withMessages(['payment_method' => 'Pesanan ekspedisi wajib dibayar secara digital. COD tidak tersedia.']);
                    }
                }
                if (($data['channel'] ?? 'online') === 'online' && ! in_array($data['payment_method'], ['cash', 'midtrans'])) {
                    throw ValidationException::withMessages(['payment_method' => 'Metode pembayaran tidak tersedia.']);
                }
                $total = $subtotal + $shipping;
                $paid = $cashierId && $data['payment_method'] !== 'midtrans';
                if ($paid && $data['payment_method'] === 'cash' && ($data['cash_received'] ?? 0) < $total) {
                    throw ValidationException::withMessages(['cash_received' => 'Uang yang diterima kurang dari total tagihan.']);
                }
                $order = Order::create([
                    'request_key' => $data['request_key'] ?? null,
                    'code' => ($cashierId ? 'POS-' : 'ORD-').now()->format('Ymd').'-'.Str::upper(Str::random($cashierId ? 8 : 4)),
                    'user_id' => $userId, 'cashier_id' => $cashierId, 'channel' => $cashierId ? 'pos' : 'online',
                    'fulfillment' => $data['fulfillment'], 'payment_method' => $data['payment_method'],
                    'status' => $paid ? 'completed' : 'awaiting_payment', 'payment_status' => $paid ? 'paid' : 'pending',
                    'subtotal' => $subtotal, 'shipping_cost' => $shipping, 'total' => $total,
                    'recipient' => $data['recipient'], 'phone' => $data['phone'] ?? null, 'address' => $data['address'] ?? null,
                    'shipping_zone' => $data['fulfillment'] === 'shipping' ? ($data['shipping_zone'] ?? 'padang') : null, 'postal_code' => $data['postal_code'] ?? null,
                    'courier' => $data['fulfillment'] === 'shipping' ? $data['courier'] : null,
                    'cash_received' => $paid && $data['payment_method'] === 'cash' ? $data['cash_received'] : null,
                    'payment_reference' => $data['payment_reference'] ?? null,
                    'paid_at' => $paid ? now() : null, 'completed_at' => $paid ? now() : null, 'expires_at' => $paid ? null : now()->addDay(),
                ]);
                foreach ($lines as [$p,$qty]) {
                    // Atomic predicate also prevents overselling on database engines without row locks.
                    if (! Product::whereKey($p->id)->where('stock', '>=', $qty)->decrement('stock', $qty)) {
                        throw ValidationException::withMessages(['cart' => 'Stok baru saja berubah. Silakan coba kembali.']);
                    }
                    $order->items()->create(['product_id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'quantity' => $qty, 'price' => $p->price]);
                    StockMovement::create(['product_id' => $p->id, 'user_id' => $cashierId ?? $userId, 'order_id' => $order->id, 'change' => -$qty, 'reason' => $paid ? 'Penjualan POS' : 'Reservasi pesanan']);
                }

                return $order;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            $existing = empty($data['request_key']) ? null : Order::where('request_key', $data['request_key'])->first();
            if (! $existing) {
                if ($attempt < 3) {
                    return $this->create($cart, $data, $userId, $cashierId, $attempt + 1);
                }
                throw $e;
            }
            abort_unless($existing->user_id === $userId && $existing->cashier_id === $cashierId, 403);

            return $existing;
        }
    }

    public function pay(Order $order, ?string $reference = null, ?int $cash = null): Order
    {
        return DB::transaction(function () use ($order, $reference, $cash) {
            $o = Order::lockForUpdate()->findOrFail($order->id);
            if ($o->payment_status === 'paid') {
                return $o;
            }
            if ($o->stock_released || $o->status !== 'awaiting_payment' || ($o->expires_at && $o->expires_at->isPast())) {
                throw ValidationException::withMessages(['payment' => 'Pesanan sudah tidak dapat dibayar. Hubungi toko jika dana terlanjur masuk.']);
            }
            if ($o->payment_method === 'cash' && ($cash ?? 0) < $o->total) {
                throw ValidationException::withMessages(['cash_received' => 'Uang pembayaran kurang.']);
            }
            $o->update(['payment_status' => 'paid', 'payment_reference' => $reference, 'paid_at' => now(), 'cash_received' => $cash, 'status' => $o->channel === 'pos' ? 'completed' : 'processing', 'completed_at' => $o->channel === 'pos' ? now() : null]);

            return $o;
        }, 3);
    }

    public function cancel(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $o = Order::lockForUpdate()->findOrFail($order->id);
            if ($o->stock_released) {
                return;
            }
            if ($o->payment_status === 'paid') {
                throw ValidationException::withMessages(['order' => 'Pesanan lunas tidak dapat dibatalkan melalui alur ini.']);
            }
            foreach ($o->items()->orderBy('product_id')->get() as $item) {
                Product::whereKey($item->product_id)->increment('stock', $item->quantity);
                StockMovement::create(['product_id' => $item->product_id, 'order_id' => $o->id, 'change' => $item->quantity, 'reason' => 'Pelepasan reservasi pesanan batal']);
            }
            $o->update(['status' => 'cancelled', 'stock_released' => true, 'payment_status' => 'cancelled']);
        }, 3);
    }
}
