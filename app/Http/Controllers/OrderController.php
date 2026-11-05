<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Services\MidtransService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['status' => 'nullable|in:awaiting_payment,processing,ready,shipped,completed,cancelled']);

        return view('orders.index', ['orders' => Order::where('user_id', $r->user()->id)->when($r->status, fn ($q) => $q->where('status', $r->status))->latest()->paginate(10)->withQueryString()]);
    }

    public function checkout(Request $r, ShopController $shop)
    {
        return view('shop.checkout', ['lines' => $shop->lines($r), 'rates' => config('shop.shipping_rates'), 'addresses' => $r->user()->addresses()->orderByDesc('is_default')->latest()->get()]);
    }

    public function store(Request $r, OrderService $service)
    {
        if ($r->filled('address_id')) {
            $r->validate(['address_id' => 'integer']);
            $a = $r->user()->addresses()->findOrFail($r->input('address_id'));
            $r->merge(['recipient' => $a->recipient, 'phone' => $a->phone, 'address' => $a->address, 'shipping_zone' => $a->zone, 'postal_code' => $a->postal_code]);
        }
        $data = $r->validate(['address_id' => 'nullable|integer', 'shipping_zone' => 'nullable|string|max:50', 'postal_code' => 'nullable|digits:5', 'request_key' => 'required|uuid', 'recipient' => 'required|string|max:120', 'phone' => 'required|string|max:25', 'fulfillment' => 'required|in:pickup,shipping', 'address' => 'required_if:fulfillment,shipping|nullable|string|max:1000', 'courier' => 'required_if:fulfillment,shipping|nullable|string|max:80', 'payment_method' => 'required|in:cash,midtrans']);
        if ($data['payment_method'] === 'midtrans' && ! config('shop.midtrans.server_key')) {
            return back()->withInput()->withErrors(['payment' => 'Pembayaran digital belum diaktifkan. Pilih ambil di toko dan bayar tunai, atau hubungi toko.']);
        }
        $o = $service->create($r->session()->get('cart', []), $data, $r->user()->id);
        $r->session()->forget('cart');

        return redirect()->route('orders.show', $o)->with('success', 'Pesanan dibuat. Selesaikan pembayaran sebelum batas waktu.');
    }

    private function access(Request $r, Order $o)
    {
        abort_unless($r->user()->active && ($o->user_id === $r->user()->id || in_array($r->user()->role, ['pemilik', 'kasir', 'admin_gudang'])), 403);
    }

    public function show(Request $r, Order $order)
    {
        $this->access($r, $order);

        return view('orders.show', ['order' => $order->load(['items.review', 'cashier'])]);
    }

    public function payment(Request $r, Order $order, MidtransService $midtrans)
    {
        $this->access($r, $order);
        abort_unless($order->user_id === $r->user()->id || in_array($r->user()->role, ['kasir', 'pemilik']), 403);
        abort_unless($order->payment_method === 'midtrans' && $order->payment_status === 'pending' && $order->status === 'awaiting_payment' && $order->expires_at?->isFuture(), 422);

        if ($r->expectsJson()) {
            abort_unless(config('shop.midtrans.client_key'), 503, 'Pembayaran pop-up belum dikonfigurasi.');
            $midtrans->start($order);

            return response()->json(['token' => $order->fresh()->snap_token]);
        }

        return redirect()->away($midtrans->start($order));
    }

    public function cancel(Request $r, Order $order, OrderService $service)
    {
        abort_unless($order->user_id === $r->user()->id, 403);
        $service->cancel($order);

        return back()->with('success', 'Pesanan dibatalkan dan reservasi stok dilepas.');
    }

    public function complete(Request $r, Order $order)
    {
        abort_unless($order->user_id === $r->user()->id, 403);
        $changed = Order::whereKey($order->id)->where('status', 'shipped')->where('payment_status', 'paid')->update(['status' => 'completed', 'completed_at' => now()]);
        abort_unless($changed, 422);

        return back()->with('success', 'Terima kasih! Pesanan selesai.');
    }

    public function review(Request $r, OrderItem $item)
    {
        abort_unless($item->order->user_id === $r->user()->id && $item->order->status === 'completed', 403);
        $data = $r->validate(['rating' => 'required|integer|between:1,5', 'comment' => 'required|string|max:1500']);
        Review::updateOrCreate(['order_item_id' => $item->id], [...$data, 'product_id' => $item->product_id, 'user_id' => $r->user()->id]);

        return back()->with('success', 'Ulasan disimpan.');
    }

    public function webhook(Request $r, OrderService $service, MidtransService $midtrans)
    {
        $d = $r->validate(['order_id' => 'required|string', 'status_code' => 'required|string', 'gross_amount' => 'required|numeric', 'signature_key' => 'required|string', 'transaction_status' => 'required|string', 'transaction_id' => 'required|string', 'fraud_status' => 'nullable|string']);
        $key = config('shop.midtrans.server_key');
        abort_unless($key, 503);
        abort_unless(hash_equals(hash('sha512', $d['order_id'].$d['status_code'].$d['gross_amount'].$key), $d['signature_key']), 403);
        $o = Order::where('code', $d['order_id'])->firstOrFail();
        abort_unless($o->payment_method === 'midtrans' && (float) $d['gross_amount'] === (float) $o->total, 422);
        // The notification signature does not sign transaction_status; verify authoritative status server-to-server.
        $d = $midtrans->status($o);
        if (in_array($d['transaction_status'] ?? null, ['settlement', 'capture']) && ($d['transaction_status'] !== 'capture' || ($d['fraud_status'] ?? '') === 'accept')) {
            $service->pay($o, $d['transaction_id'] ?? null);
        } elseif (in_array($d['transaction_status'] ?? null, ['expire', 'cancel', 'deny'])) {
            // Lock and recheck: a late cancellation must never undo a settled payment.
            DB::transaction(function () use ($o, $service) {
                $locked = Order::lockForUpdate()->findOrFail($o->id);
                if ($locked->payment_status === 'pending') {
                    $service->cancel($locked);
                }
            });
        }

        return response()->json(['received' => true]);
    }
}
