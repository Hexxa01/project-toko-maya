@extends('layouts.app')
@section('title','Checkout')
@section('content')
<div class="page-heading">
<span class="eyebrow">SELESAIKAN PESANAN</span>
<h1>Checkout & Pengiriman</h1>
<p>Pesan online, pilih cara pengambilan yang paling nyaman.</p>
</div>
@if(!$lines)<div class="panel empty">
<h2>Keranjang masih kosong</h2>
<a class="button" href="{{ route('catalog') }}">Jelajahi Produk</a>
</div>

@else


@php($subtotal=collect($lines)->sum(fn($l)=>$l['product']->price*$l['quantity']))
@php($kg=(int)ceil(collect($lines)->sum(fn($l)=>$l['product']->weight*$l['quantity'])/1000))
<form method="post" action="{{ route('checkout.store') }}" class="checkout-grid" data-checkout data-subtotal="{{ $subtotal }}" data-weight="{{ $kg }}">@csrf<input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
<div class="panel form-stack">
<h2>1. Data Penerima</h2>
<button type="button" class="outline" data-open-dialog="address-book">Pilih / Tambah Alamat Tersimpan</button>
<label>Nama penerima<input name="recipient" value="{{ old('recipient',auth()->user()->name) }}" required maxlength="120">
</label>
<label>Nomor telepon<input name="phone" value="{{ old('phone') }}" required maxlength="25" autocomplete="tel">
</label>
<h2>2. Metode Pengambilan</h2>
<div class="choice-grid">
<label class="choice">
<input type="radio" name="fulfillment" value="pickup" @checked(old('fulfillment','pickup')==='pickup')>
<strong>Ambil Sendiri di Toko</strong>
<small>Gratis ongkir · Padang</small>
</label>
<label class="choice">
<input type="radio" name="fulfillment" value="shipping" @checked(old('fulfillment')==='shipping') @disabled(!$rates)>
<strong>Kirim via Ekspedisi</strong>
<small>{{ $rates?'Tarif toko per kg':'Belum tersedia' }}</small>
</label>
</div>
<div class="pickup-info notice">{{ config('shop.address') }}<br>{{ config('shop.hours') }}<br>Pesanan disiapkan setelah pembayaran diterima.</div>
<div class="shipping-fields form-stack">
<input type="hidden" name="address_id" value="{{ old('address_id') }}">
<label>Wilayah pengiriman<select name="shipping_zone">@foreach(config('shop.shipping_zones') as $key=>$zone)<option value="{{ $key }}" data-multiplier="{{ $zone['multiplier'] }}" @selected(old('shipping_zone','padang')===$key)>{{ $zone['name'] }}</option>
@endforeach
</select>
</label>
<label>Kode pos<input name="postal_code" value="{{ old('postal_code') }}" pattern="[0-9]{5}" maxlength="5" inputmode="numeric">
</label>
<label>Alamat lengkap<textarea name="address" maxlength="1000">{{ old('address') }}</textarea>
</label>
<label>Kurir<select name="courier">
<option value="">Pilih layanan</option>@foreach($rates as $courier=>$rate)<option value="{{ $courier }}" data-rate="{{ $rate }}" @selected(old('courier')===$courier)>{{ $courier }} · Rp {{ number_format($rate,0,',','.') }}/kg</option>

@endforeach

</select>
</label>
<p class="muted">Estimasi ongkir ditentukan tarif toko, wilayah, dan pembulatan berat per kg. Tarif bukan kutipan langsung API kurir. COD tidak tersedia.</p>
</div>
<h2>3. Metode Pembayaran</h2>
<label class="choice cash-option">
<input type="radio" name="payment_method" value="cash" @checked(old('payment_method','cash')==='cash')>
<strong>Tunai di Kasir</strong>
<small>Khusus ambil di toko. Bawa kode pesanan untuk pembayaran.</small>
</label>
<label class="choice">
<input type="radio" name="payment_method" value="midtrans" @checked(old('payment_method')==='midtrans') @disabled(!config('shop.midtrans.server_key'))>
<strong>QRIS / Virtual Account / E-Wallet</strong>
<small>{{ config('shop.midtrans.server_key')?'Pembayaran aman melalui Midtrans':'Pembayaran digital belum diaktifkan toko' }}</small>
</label>
</div>
<aside class="panel summary">
<h2>Ringkasan Pesanan</h2>@foreach($lines as $l)<div class="sum-row">
<span>{{ $l['product']->name }} <small>× {{ $l['quantity'] }}</small>
</span>
<strong>Rp {{ number_format($l['product']->price*$l['quantity'],0,',','.') }}</strong>
</div>

@endforeach

<div class="sum-row">
<span>Subtotal</span>
<strong>Rp {{ number_format($subtotal,0,',','.') }}</strong>
</div>
<div class="sum-row">
<span>Ongkir ({{ $kg }} kg)</span>
<strong data-shipping>Rp 0</strong>
</div>
<div class="sum-row total">
<span>Total</span>
<strong data-total>Rp {{ number_format($subtotal,0,',','.') }}</strong>
</div>
<p class="muted">Stok dipesan selama 24 jam. Pesanan yang belum dibayar akan dibatalkan setelah batas waktu.</p>
<button class="full">Konfirmasi Pesanan →</button>
</aside>
</form>


@endif



@include('shop.address-dialog')

@endsection
