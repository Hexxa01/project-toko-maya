@extends('layouts.app')
@section('title',$product->name)
@section('content')
<div class="breadcrumb">
<a href="{{ route('catalog') }}">Katalog</a> / {{ $product->category->name }}</div>
<section class="product-detail panel">
<div class="detail-image">
<img src="{{ $product->image_url }}" alt="{{ $product->name }}">@unless($product->image)<p class="muted">Ilustrasi produk; foto asli belum tersedia.</p>

@endunless

</div>
<div>
<span class="eyebrow">{{ $product->category->name }} · {{ $product->sku }}</span>
<h1>{{ $product->name }}</h1>
<p class="detail-price">Rp {{ number_format($product->price,0,',','.') }} <small>/ {{ $product->unit }}</small>
</p>
<span class="badge {{ $product->stock?'paid':'cancelled' }}">{{ $product->stock }} {{ $product->unit }} tersedia</span>
<p class="description">{{ $product->description }}</p>
<dl class="specs">@foreach(['brand'=>'Merek','material'=>'Material','specification'=>'Ukuran / Tipe','warranty'=>'Garansi','weight'=>'Berat (gram)'] as $key=>$label)@if($product->$key)<div>
<dt>{{ $label }}</dt>
<dd>{{ $product->$key }}</dd>
</div>

@endif



@endforeach

</dl>
<form class="actions" method="post" action="{{ route('cart.add',$product) }}">@csrf<label>Jumlah<input type="number" name="quantity" value="1" min="1" max="{{ min(999,$product->stock) }}" class="quantity" required>
</label>
<button @disabled($product->stock<1)>+ Tambah ke Keranjang</button>
</form>
</div>
</section>
<section class="panel">
<h2>Ulasan Pelanggan</h2>@forelse($product->reviews as $review)<article class="review">
<strong>{{ $review->user->name }}</strong>
<span class="rating">{{ str_repeat('★',$review->rating) }}{{ str_repeat('☆',5-$review->rating) }}</span>
<p>{{ $review->comment }}</p>
</article>

@empty

<p class="muted">Belum ada ulasan untuk produk ini.</p>

@endforelse

</section>


@endsection
