@extends('layouts.app')
@section('title',request()->routeIs('register')?'Daftar Akun':'Masuk')
@section('content')
@php($register=request()->routeIs('register'))
<section class="auth-card">
<div class="auth-brand">M</div>
<span class="eyebrow">SELAMAT DATANG DI TOKO MAYA</span>
<h1>{{ $register?'Buat Akun Baru':'Masuk ke Akun Anda' }}</h1>
<p class="muted">{{ $register?'Belanja kebutuhan perkakas jadi lebih mudah.':'Kelola pesanan dan akses layanan toko Anda.' }}</p>
<form method="post" action="{{ $register?route('register'):route('login') }}" class="form-stack">@csrf @if($register)<label>Nama lengkap<input name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="120">
</label>

@endif

<label>Alamat email<input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
</label>
<label>Kata sandi<input name="password" type="password" minlength="8" autocomplete="{{ $register?'new-password':'current-password' }}" required>
</label>@if($register)<label>Konfirmasi kata sandi<input name="password_confirmation" type="password" autocomplete="new-password" required>
</label>

@else

<label class="check">
<input type="checkbox" name="remember" value="1"> Ingat saya</label>

@endif

<button class="full">{{ $register?'DAFTAR AKUN':'MASUK' }}</button>
</form>
<p class="center">{{ $register?'Sudah punya akun?':'Belum punya akun?' }} <a href="{{ $register?route('login'):route('register') }}">{{ $register?'Masuk':'Daftar sekarang' }}</a>
</p>
</section>


@endsection
