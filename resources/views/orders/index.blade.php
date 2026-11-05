@extends('layouts.app')
@section('title','Pesanan Saya')
@section('content')
<div class="page-heading">
<span class="eyebrow">DASHBOARD PELANGGAN</span>
<h1>Pesanan Saya</h1>
<p>Pantau perjalanan pesanan, mulai dari pembayaran hingga barang diterima.</p>
</div>
<nav class="tabs">
<a href="{{ route('orders.index') }}" class="{{ !request('status')?'active':'' }}">Semua</a>@foreach(['awaiting_payment'=>'Belum Bayar','processing'=>'Dikemas','ready'=>'Siap Diambil','shipped'=>'Dikirim','completed'=>'Selesai','cancelled'=>'Batal'] as $key=>$label)<a class="{{ request('status')===$key?'active':'' }}" href="{{ route('orders.index',['status'=>$key]) }}">{{ $label }}</a>

@endforeach

</nav>
<section class="panel">@include('components.orders-table')</section>


@endsection
