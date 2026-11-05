<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Toko Maya') · Alat Bangunan & Bengkel</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body data-clear-pos="{{ session('clear_pos') ? auth()->id() : '' }}">
<div class="topbar">
<div class="container">
<span>Sistem Terintegrasi Toko Maya</span>
<span>Pasar Raya Padang · Sejak 1985</span>
</div>
</div>
<header class="header">
<div class="container nav">
<a href="{{ route('home') }}" class="brand">
<span class="brand-mark">M</span>
<span>TOKO MAYA<small>Alat Bangunan & Bengkel</small>
</span>
</a>
<form action="{{ route('catalog') }}" class="search" role="search">
<input aria-label="Cari produk" name="q" value="{{ request('q') }}" placeholder="Cari baut, perkakas, mesin teknik…">
<button>Cari</button>
</form>
<nav class="navlinks" aria-label="Navigasi utama">
<a href="{{ route('catalog') }}">Katalog</a>
<a href="{{ route('cart') }}">Keranjang <span class="counter">{{ array_sum(session('cart',[])) }}</span>
</a>@auth<a href="{{ route('addresses.index') }}">Alamat</a>
<a href="{{ route('dashboard') }}">{{ auth()->user()->name }}</a>
<form method="post" action="{{ route('logout') }}">@csrf<button class="text-button">Keluar</button>
</form>

@else

<a class="button small" href="{{ route('login') }}">Masuk / Daftar</a>

@endauth

</nav>
</div>
</header>
<main class="container main">
@if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>

@endif


@if($errors->any())<div class="notice error" role="alert">
<strong>Periksa kembali isian Anda.</strong>
<ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>

@endforeach

</ul>
</div>

@endif


@yield('content')
</main>
<footer>
<div class="container footer-grid">
<div>
<a class="brand" href="{{ route('home') }}">TOKO MAYA</a>
<p>Pusat alat bangunan, perkakas, dan perlengkapan bengkel.<br>Melayani kebutuhan Anda sejak 1985.</p>
</div>
<div>
<strong>Kunjungi toko kami</strong>
<p>{{ config('shop.address') }}<br>{{ config('shop.hours') }}</p>
</div>
<div>
<strong>Belanja lebih mudah</strong>
<p>Pesan online · Ambil di toko<br>Pengiriman sesuai layanan yang tersedia</p>
</div>
</div>
<div class="container copyright">© {{ date('Y') }} Toko Maya. Semua hak dilindungi.</div>
</footer>
</body>
</html>
