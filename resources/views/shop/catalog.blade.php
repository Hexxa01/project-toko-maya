@extends('layouts.app')
@section('title','Katalog Produk')
@section('content')
<div class="page-heading">
<span class="eyebrow">KEBUTUHAN PROYEK DALAM SATU TEMPAT</span>
<h1>Katalog Produk</h1>
<p>Temukan perkakas yang tepat untuk pekerjaan Anda.</p>
</div>
<form class="panel filters" action="{{ route('catalog') }}">
<label>Pencarian<input name="q" placeholder="Nama produk…" value="{{ request('q') }}">
</label>
<label>Kategori<select name="category">
<option value="">Semua kategori</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category')==$c->id)>{{ $c->name }}</option>

@endforeach

</select>
</label>
<label>Urutkan<select name="sort">
<option value="newest">Terbaru</option>
<option value="price_asc" @selected(request('sort')==='price_asc')>Harga terendah</option>
<option value="price_desc" @selected(request('sort')==='price_desc')>Harga tertinggi</option>
</select>
</label>
<button>Terapkan</button>
<a href="{{ route('catalog') }}">Reset</a>
</form>
<p class="muted">{{ $products->total() }} produk ditemukan</p>
<div class="product-grid">@forelse($products as $product)@include('components.product-card')

@empty

<div class="panel empty">Tidak ada produk yang sesuai. Coba kata kunci lain.</div>

@endforelse

</div>
<div class="pagination">{{ $products->links() }}</div>


@endsection
