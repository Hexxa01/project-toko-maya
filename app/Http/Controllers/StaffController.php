<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\StockMovement;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function warehouse(Request $r)
    {
        $r->validate(['stock' => 'nullable|in:low,empty,safe', 'fulfillment' => 'nullable|in:pickup,shipping']);
        $products = Product::with('category')->where('active', true)->when($r->stock === 'low', fn ($q) => $q->whereColumn('stock', '<=', 'minimum_stock')->where('stock', '>', 0))->when($r->stock === 'empty', fn ($q) => $q->where('stock', 0))->when($r->stock === 'safe', fn ($q) => $q->whereColumn('stock', '>', 'minimum_stock'))->orderBy('stock')->paginate(15, ['*'], 'products_page')->withQueryString();

        return view('staff.warehouse', ['products' => $products, 'orders' => Order::with('items')->where('payment_status', 'paid')->whereIn('status', ['processing', 'ready', 'shipped'])->when($r->fulfillment, fn ($q) => $q->where('fulfillment', $r->fulfillment))->latest()->paginate(10, ['*'], 'orders_page')->withQueryString(), 'lowCount' => Product::where('active', true)->whereColumn('stock', '<=', 'minimum_stock')->count(), 'queueCount' => Order::where('status', 'processing')->count(), 'movements' => StockMovement::with('product')->latest()->take(15)->get()]);
    }

    public function stock(Request $r, Product $product)
    {
        $d = $r->validate(['change' => 'required|integer|between:-100000,100000|not_in:0', 'reason' => 'required|string|max:255']);
        DB::transaction(function () use ($r, $product, $d) {
            $p = Product::lockForUpdate()->findOrFail($product->id);
            if ($p->stock + $d['change'] < 0) {
                throw ValidationException::withMessages(['change' => 'Penyesuaian tidak boleh menghasilkan stok negatif.']);
            }$p->increment('stock', $d['change']);
            StockMovement::create(['product_id' => $p->id, 'user_id' => $r->user()->id, 'change' => $d['change'], 'reason' => $d['reason']]);
        });

        return back()->with('success', 'Penyesuaian stok dicatat dalam riwayat.');
    }

    public function fulfill(Request $r, Order $order)
    {
        $d = $r->validate(['action' => 'required|in:verify,ready,ship,tracking', 'courier' => 'required_if:action,ship,tracking|nullable|string|max:80', 'tracking_number' => 'required_if:action,ship,tracking|nullable|string|max:100']);
        DB::transaction(function () use ($order, $d) {
            $o = Order::lockForUpdate()->findOrFail($order->id);
            abort_unless($o->payment_status === 'paid', 422);
            if ($d['action'] === 'verify') {
                abort_unless($o->status === 'processing', 422);
                $o->update(['verified_at' => now(), 'verified_by' => auth()->id()]);
            } elseif ($d['action'] === 'ready') {
                abort_unless($o->status === 'processing' && $o->fulfillment === 'pickup', 422);
                $o->update(['status' => 'ready']);
            } else {
                abort_unless($o->fulfillment === 'shipping' && (($d['action'] === 'ship' && $o->status === 'processing') || ($d['action'] === 'tracking' && $o->status === 'shipped')), 422);
                $o->update(['status' => 'shipped', 'courier' => $d['courier'], 'tracking_number' => $d['tracking_number']]);
            }
        });

        return back()->with('success', 'Status pemenuhan pesanan diperbarui.');
    }

    public function cashier(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:100', 'code' => 'nullable|string|max:80']);

        return view('staff.cashier', ['products' => Product::where('active', true)->where('stock', '>', 0)->when($r->q, fn ($q) => $q->where(fn ($search) => $search->where('name', 'like', '%'.$r->q.'%')->orWhere('sku', 'like', '%'.$r->q.'%')))->orderBy('name')->paginate(12)->withQueryString(), 'orders' => Order::with('items')->where(function ($q) {
            $q->where('channel', 'pos')->orWhere('fulfillment', 'pickup');
        })->when($r->code, fn ($q) => $q->where('code', $r->code))->latest()->paginate(10, ['*'], 'orders_page')->withQueryString(), 'online' => Order::where('channel', 'online')->where('fulfillment', 'shipping')->latest()->take(10)->get(), 'returns' => ProductReturn::with('item.order')->latest()->take(20)->get()]);
    }

    public function search(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:100']);
        $products = Product::where('active', true)->where('stock', '>', 0)->when($r->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$r->q.'%')->orWhere('sku', 'like', '%'.$r->q.'%')))->orderBy('name')->take(24)->get();

        return response()->json(['html' => view('staff.pos-products', compact('products'))->render(), 'count' => $products->count()]);
    }

    public function pos(Request $r, OrderService $service)
    {
        $d = $r->validate(['request_key' => 'required|uuid', 'items' => 'required|array|max:100', 'items.*' => 'nullable|integer|between:0,999', 'recipient' => 'required|string|max:120', 'payment_method' => 'required|in:cash,debit,midtrans', 'cash_received' => 'required_if:payment_method,cash|nullable|integer|min:0|max:999999999999', 'payment_reference' => 'required_if:payment_method,debit|nullable|string|max:100']);
        if ($d['payment_method'] === 'midtrans' && ! config('shop.midtrans.server_key')) {
            return back()->withErrors(['payment' => 'Pembayaran QRIS/digital belum diaktifkan.']);
        }
        $order = $service->create(array_filter($d['items']), [...$d, 'fulfillment' => 'pickup', 'channel' => 'pos'], null, $r->user()->id);

        return redirect()->route('orders.show', $order)->with('clear_pos', true)->with('success', $order->payment_status === 'paid' ? 'Transaksi POS selesai.' : 'Pesanan POS dibuat. Lanjutkan pembayaran digital.');
    }

    public function cash(Request $r, Order $order, OrderService $service)
    {
        abort_unless($order->payment_method === 'cash' && $order->fulfillment === 'pickup', 422);
        $d = $r->validate(['cash_received' => 'required|integer|min:0|max:999999999999']);
        $service->pay($order, 'CASH-'.$r->user()->id, $d['cash_received']);

        return back()->with('success', 'Pembayaran tunai diterima. Pesanan masuk antrean gudang.');
    }

    public function handover(Request $r, Order $order)
    {
        $changed = Order::whereKey($order->id)->where('fulfillment', 'pickup')->where('status', 'ready')->where('payment_status', 'paid')->update(['status' => 'completed', 'completed_at' => now(), 'cashier_id' => $r->user()->id]);
        abort_unless($changed, 422);

        return back()->with('success', 'Barang telah diserahkan. Pesanan selesai.');
    }

    public function returns(Request $r)
    {
        $d = $r->validate(['code' => 'required|string|max:80', 'sku' => 'required|string|max:80', 'quantity' => 'required|integer|between:1,999', 'reason' => 'required|string|max:1000', 'restock' => 'required|boolean']);
        DB::transaction(function () use ($r, $d) {
            $o = Order::where('code', $d['code'])->lockForUpdate()->firstOrFail();
            abort_unless($o->status === 'completed' && $o->payment_status === 'paid', 422);
            $i = $o->items()->where('sku', $d['sku'])->lockForUpdate()->firstOrFail();
            if ($d['quantity'] > $i->quantity - $i->returned_quantity) {
                throw ValidationException::withMessages(['quantity' => 'Jumlah retur melebihi barang yang belum diretur.']);
            }
            $i->increment('returned_quantity', $d['quantity']);
            ProductReturn::create(['code' => 'RET-'.Str::upper(Str::random(12)), 'order_item_id' => $i->id, 'user_id' => $r->user()->id, 'quantity' => $d['quantity'], 'reason' => $d['reason'], 'restock' => (bool) $d['restock'], 'refund_amount' => $i->price * $d['quantity']]);
            if ($d['restock']) {
                Product::whereKey($i->product_id)->increment('stock', $d['quantity']);
                StockMovement::create(['product_id' => $i->product_id, 'user_id' => $r->user()->id, 'order_id' => $o->id, 'change' => $d['quantity'], 'reason' => 'Retur barang layak jual']);
            }
        });

        return back()->with('success', 'Retur dicatat. Barang rusak dipisahkan dari stok jual; pengembalian dana menunggu penyelesaian pemilik.');
    }
}
