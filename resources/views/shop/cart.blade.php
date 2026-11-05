@extends('layouts.app')
@section('title','Keranjang Belanja')
@section('content')
<div class="page-heading">
<span class="eyebrow">LANGKAH PERTAMA BELANJA ANDA</span>
<h1>Keranjang Belanja</h1>
<p>Periksa produk dan jumlah sebelum melanjutkan.</p>
</div>
@if(!$lines)<div class="panel empty">
<div class="empty-icon">▱</div>
<h2>Keranjang Anda masih kosong</h2>
<p>Temukan kebutuhan proyek Anda di katalog Toko Maya.</p>
<a class="button" href="{{ route('catalog') }}">Mulai Belanja →</a>
</div>

@else

<div class="checkout-grid">
<section class="panel">@php($subtotal=0)@foreach($lines as $line)@php($p=$line['product'])@php($subtotal+=$p->price*$line['quantity'])<div class="cart-row">
<img src="{{ $p->image_url }}" alt="{{ $p->name }}">
<div>
<a href="{{ route('products.show',$p) }}">
<strong>{{ $p->name }}</strong>
</a>
<p>Rp {{ number_format($p->price,0,',','.') }}</p>
<small>Stok {{ $p->stock }} {{ $p->unit }}@unless($p->active) · Produk tidak aktif

@endunless

</small>
</div>
<form method="post" action="{{ route('cart.update',$p) }}" class="actions">@csrf @method('PATCH')<label class="sr-only" for="qty-{{ $p->id }}">Jumlah {{ $p->name }}</label>
<input id="qty-{{ $p->id }}" class="quantity" type="number" name="quantity" value="{{ $line['quantity'] }}" min="0" max="999" required>
<button class="outline small">Ubah</button>
<small>Isi 0 untuk hapus</small>
</form>
</div>

@endforeach

</section>
<aside class="panel summary">
<h2>Ringkasan Belanja</h2>
<div class="sum-row">
<span>Subtotal</span>
<strong>Rp {{ number_format($subtotal,0,',','.') }}</strong>
</div>
<p class="muted">Ongkir dihitung setelah memilih pengiriman. Stok diperiksa kembali saat checkout.</p>
<a class="button full" href="{{ route('checkout') }}">Lanjut Checkout →</a>
<a class="center block" href="{{ route('catalog') }}">Lanjut Belanja</a>
</aside>
</div>

@endif




@endsection
