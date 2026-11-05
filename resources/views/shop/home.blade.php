@extends('layouts.app')
@section('title','Beranda')
@section('content')
<section class="hero">
<div>
<span class="eyebrow">MITRA ANDALAN PROYEK ANDA</span>
<h1>Belanja alat bangunan<br>
<em>Mudah, cepat, dan praktis.</em>
</h1>
<p>Perkakas berkualitas untuk pekerjaan terbaik Anda. Temukan kebutuhan bengkel dan bangunan dalam satu tempat.</p>
<div class="actions">
<a class="button" href="{{ route('catalog') }}">Jelajahi Katalog →</a>
<a class="button outline" href="{{ route('orders.index') }}">Lacak Pesanan</a>
</div>
<div class="hero-trust">
<span>✓ Pilihan lengkap</span>
<span>✓ Ambil di toko</span>
<span>✓ Stok terintegrasi</span>
</div>
</div>
<div class="hero-art">
<img src="{{ asset('images/workshop.svg') }}" alt="Ilustrasi perkakas bengkel Toko Maya">
<div class="art-caption">TOKO MAYA <small>PADANG · SEJAK 1985</small>
</div>
</div>
</section>
<section class="section">
<div class="section-heading">
<div>
<span class="eyebrow">CARI SESUAI KEBUTUHAN</span>
<h2>Kategori Pilihan</h2>
<p>Lengkapi pekerjaan Anda dengan peralatan yang tepat.</p>
</div>
<a href="{{ route('catalog') }}">Semua produk →</a>
</div>
<div class="categories">@forelse($categories as $category)<a class="category" href="{{ route('catalog',['category'=>$category->id]) }}">
<span class="category-icon">{{ ['⚒','⚙','▥','⌁','◈'][$loop->index%5] }}</span>
<strong>{{ $category->name }}</strong>
<small>{{ $category->products_count }} produk</small>
</a>

@empty

<div class="empty">Katalog sedang dipersiapkan.</div>

@endforelse

</div>
</section>
<section class="section">
<div class="section-heading">
<div>
<span class="eyebrow">UNTUK PEKERJAAN TERBAIK ANDA</span>
<h2>Produk Terbaru</h2>
<p>Pilihan perkakas dan perlengkapan di Toko Maya.</p>
</div>
<a href="{{ route('catalog') }}">Lihat katalog →</a>
</div>
<div class="product-grid">@foreach($products as $product)@include('components.product-card')

@endforeach

</div>
</section>
<section class="benefits">
<div>
<strong>01 · Temukan kebutuhan</strong>
<p>Cari produk dan periksa stok yang tersedia.</p>
</div>
<div>
<strong>02 · Pilih cara belanja</strong>
<p>Ambil langsung atau pilih layanan pengiriman.</p>
</div>
<div>
<strong>03 · Pantau pesanan</strong>
<p>Ikuti proses penyiapan melalui akun Anda.</p>
</div>
</section>


@endsection
