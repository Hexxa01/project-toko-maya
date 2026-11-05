<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class OwnerController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $orders = $this->sales($r);
        $months = collect(range(11, 0))->map(function ($offset) {
            $start = now()->startOfMonth()->subMonths($offset);
            $q = Order::where('payment_status', 'paid')->where('paid_at', '>=', $start)->where('paid_at', '<', $start->copy()->addMonth());

            return ['label' => $start->translatedFormat('M Y'), 'key' => $start->format('Y-m'), 'online' => (clone $q)->where('channel', 'online')->sum('total'), 'pos' => (clone $q)->where('channel', 'pos')->sum('total')];
        });
        $today = Order::where('payment_status', 'paid')->whereDate('paid_at', now()->toDateString())->sum('total');
        $thisMonth = Order::where('payment_status', 'paid')->where('paid_at', '>=', now()->startOfMonth())->where('paid_at', '<', now()->startOfMonth()->addMonth())->sum('total');

        return view('owner.index', ['months' => $months, 'today' => $today, 'thisMonth' => $thisMonth, 'revenue' => (clone $orders)->sum('total'), 'online' => (clone $orders)->where('channel', 'online')->sum('total'), 'pos' => (clone $orders)->where('channel', 'pos')->sum('total'), 'salesCount' => (clone $orders)->count(), 'productCount' => Product::where('active', true)->count(), 'userCount' => User::count(), 'low' => Product::where('active', true)->whereColumn('stock', '<=', 'minimum_stock')->get(), 'top' => OrderItem::select('product_id', 'name')->selectRaw('SUM(quantity - returned_quantity) as sold')->whereHas('order', fn ($q) => $q->where('payment_status', 'paid')->when($r->from, fn ($q) => $q->whereDate('paid_at', '>=', $r->from))->when($r->to, fn ($q) => $q->whereDate('paid_at', '<=', $r->to)))->groupBy('product_id', 'name')->orderByDesc('sold')->take(5)->get(), 'orders' => (clone $orders)->latest('paid_at')->paginate(15)->withQueryString(), 'returns' => ProductReturn::with('item.order')->latest()->paginate(10, ['*'], 'returns_page')->withQueryString(), 'refundTotal' => ProductReturn::where('refund_status', 'paid')->sum('refund_amount'), 'daily' => (clone $orders)->selectRaw('DATE(paid_at) as day, SUM(total) as amount')->groupByRaw('DATE(paid_at)')->orderByDesc('day')->limit(7)->get()]);
    }

    private function sales(Request $r)
    {
        return Order::where('payment_status', 'paid')->when($r->from, fn ($q) => $q->whereDate('paid_at', '>=', $r->from))->when($r->to, fn ($q) => $q->whereDate('paid_at', '<=', $r->to));
    }

    public function export(Request $r)
    {
        $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);

        return response()->streamDownload(function () use ($r) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, ['Kode', 'Tanggal Lunas', 'Kanal', 'Status', 'Subtotal', 'Ongkir', 'Total']);
            foreach ($this->sales($r)->orderBy('id')->cursor() as $o) {
                fputcsv($f, [$o->code, $o->paid_at->toDateTimeString(), $o->channel, $o->status, $o->subtotal, $o->shipping_cost, $o->total]);
            }fclose($f);
        }, 'laporan-penjualan-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function products(Request $r)
    {
        return view('owner.products', ['products' => Product::with('category')->orderBy('name')->paginate(15), 'categories' => Category::orderBy('name')->get()]);
    }

    public function form(?Product $product = null)
    {
        return view('owner.product-form', ['product' => $product ?? new Product, 'categories' => Category::all()]);
    }

    public function save(Request $r, ?Product $product = null)
    {
        $d = $r->validate(['name' => 'required|string|max:180', 'sku' => 'required|string|max:80|unique:products,sku,'.($product?->id ?? 'NULL'), 'category_id' => 'required|exists:categories,id', 'description' => 'required|string|max:5000', 'price' => 'required|integer|between:1,1000000000', 'minimum_stock' => 'required|integer|between:0,1000000', 'weight' => 'required|integer|between:1,1000000', 'unit' => 'required|string|max:30', 'brand' => 'nullable|string|max:100', 'material' => 'nullable|string|max:150', 'specification' => 'nullable|string|max:255', 'warranty' => 'nullable|string|max:150', 'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072', 'active' => 'required|boolean']);
        if ($r->hasFile('photo')) {
            $d['image'] = $r->file('photo')->store('products', 'public');
        }unset($d['photo']);
        if ($product) {
            $product->update($d);
        } else {
            Product::create([...$d, 'stock' => 0]);
        }

        return redirect()->route('owner.products')->with('success', 'Produk disimpan. Penambahan stok dicatat melalui panel gudang.');
    }

    public function category(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:100|unique:categories']);
        $slug = Str::slug($d['name']);
        Category::create([...$d, 'slug' => ($slug ?: 'kategori').'-'.Str::lower(Str::random(6))]);

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function users()
    {
        return view('owner.users', ['users' => User::orderBy('name')->paginate(20)]);
    }

    public function saveUser(Request $r, ?User $user = null)
    {
        $d = $r->validate(['name' => 'required|string|max:120', 'email' => 'required|email|max:255|unique:users,email,'.($user?->id ?? 'NULL'), 'role' => 'required|in:pemilik,kasir,admin_gudang,pelanggan', 'active' => 'required|boolean', 'password' => [$user ? 'nullable' : 'required', Password::min(8)]]);
        if ($user?->id === $r->user()->id && ($d['role'] !== 'pemilik' || ! $d['active'])) {
            throw ValidationException::withMessages(['role' => 'Akun pemilik yang sedang digunakan tidak dapat dinonaktifkan atau diturunkan perannya.']);
        }
        if (empty($d['password'])) {
            unset($d['password']);
        }
        // Role and active are intentionally not mass assignable on public registration.
        $user ??= new User;
        $user->forceFill($d)->save();

        return back()->with('success', 'Pengguna dan hak akses disimpan.');
    }

    public function refund(Request $r, ProductReturn $return)
    {
        $d = $r->validate(['refund_reference' => 'required|string|max:150']);
        $changed = ProductReturn::whereKey($return->id)->where('refund_status', 'pending')->update(['refund_status' => 'paid', 'refund_reference' => $d['refund_reference']]);
        abort_unless($changed, 422);

        return back()->with('success','Penyelesaian dana retur dicatat berdasarkan referensi pembayaran Anda.');
    }
}
