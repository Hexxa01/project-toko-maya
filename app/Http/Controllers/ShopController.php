<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function home()
    {
        return view('shop.home', ['categories' => Category::withCount(['products' => fn ($q) => $q->where('active', true)])->get(), 'products' => Product::with('category')->where('active', true)->latest()->take(8)->get()]);
    }

    public function catalog(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:100', 'category' => 'nullable|integer', 'sort' => 'nullable|in:newest,price_asc,price_desc']);
        $products = Product::with('category')->where('active', true)->when($r->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$r->q.'%'))->when($r->filled('category'), fn ($q) => $q->where('category_id', $r->category));
        match ($r->query('sort')) {
            'price_asc' => $products->orderBy('price'),'price_desc' => $products->orderByDesc('price'),default => $products->latest()
        };

        return view('shop.catalog', ['products' => $products->paginate(12)->withQueryString(), 'categories' => Category::all()]);
    }

    public function show(Product $product)
    {
        abort_unless($product->active, 404);

        return view('shop.product', ['product' => $product->load(['category', 'reviews.user'])]);
    }

    public function cart(Request $r)
    {
        return view('shop.cart', ['lines' => $this->lines($r)]);
    }

    public function lines(Request $r): array
    {
        $cart = $r->session()->get('cart', []);
        $products = Product::whereIn('id', array_keys($cart))->get()->keyBy('id');
        $lines = [];
        foreach ($cart as $id => $quantity) {
            if (isset($products[$id])) {
                $lines[] = ['product' => $products[$id], 'quantity' => $quantity];
            }
        }

        return $lines;
    }

    public function add(Request $r, Product $product)
    {
        $data = $r->validate(['quantity' => 'required|integer|min:1|max:999']);
        $cart = $r->session()->get('cart', []);
        $qty = ($cart[$product->id] ?? 0) + $data['quantity'];
        if (! $product->active || $qty > $product->stock || $qty > 999) {
            return back()->withErrors(['quantity' => 'Jumlah melebihi stok tersedia.']);
        }
        $cart[$product->id] = $qty;
        $r->session()->put('cart', $cart);

        return back()->with('success', 'Produk ditambahkan ke keranjang.');
    }

    public function update(Request $r, Product $product)
    {
        $data = $r->validate(['quantity' => 'required|integer|min:0|max:999']);
        $cart = $r->session()->get('cart', []);
        if ($data['quantity'] > $product->stock) {
            return back()->withErrors(['quantity' => 'Jumlah melebihi stok tersedia.']);
        }
        if ($data['quantity'] == 0) {
            unset($cart[$product->id]);
        } else {
            $cart[$product->id] = $data['quantity'];
        }
        $r->session()->put('cart', $cart);

        return back()->with('success', 'Keranjang diperbarui.');
    }
}
