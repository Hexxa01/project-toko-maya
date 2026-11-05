@extends('layouts.app')
@section('title',$order->code)
@section('content')
@if(config('shop.midtrans.client_key'))
<script src="{{ config('shop.midtrans.production')?'https://app.midtrans.com/snap/snap.js':'https://app.sandbox.midtrans.com/snap/snap.js' }}" data-client-key="{{ config('shop.midtrans.client_key') }}" defer>
</script>

@endif


<div class="page-heading">
<span class="eyebrow">{{ $order->channel==='pos'?'STRUK PEMBELIAN':'DETAIL PESANAN' }}</span>
<h1>{{ $order->code }}</h1>
<p>{{ $order->created_at->format('d M Y, H:i') }} · {{ $order->recipient }}</p>
</div>
<div class="checkout-grid">
<div>
<section class="panel">
<div class="section-heading">
<h2>Rincian Barang</h2>
<span class="badge {{ $order->status }}">{{ $order->status_label }}</span>
</div>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Produk</th>
<th>Jumlah</th>
<th>Harga</th>
<th>Subtotal</th>
</tr>
</thead>
<tbody>@foreach($order->items as $item)<tr>
<td>{{ $item->name }}<small>{{ $item->sku }} @if($item->returned_quantity) · Diretur {{ $item->returned_quantity }}

@endif

</small>
</td>
<td>{{ $item->quantity }}</td>
<td>Rp {{ number_format($item->price,0,',','.') }}</td>
<td>Rp {{ number_format($item->quantity*$item->price,0,',','.') }}</td>
</tr>

@endforeach

</tbody>
</table>
</div>
<div class="sum-row">
<span>Subtotal</span>
<strong>Rp {{ number_format($order->subtotal,0,',','.') }}</strong>
</div>
<div class="sum-row">
<span>Ongkir</span>
<strong>Rp {{ number_format($order->shipping_cost,0,',','.') }}</strong>
</div>
<div class="sum-row total">
<span>Total Tagihan</span>
<strong>Rp {{ number_format($order->total,0,',','.') }}</strong>
</div>@if($order->cash_received!==null)<div class="sum-row">
<span>Uang diterima</span>
<strong>Rp {{ number_format($order->cash_received,0,',','.') }}</strong>
</div>
<div class="sum-row">
<span>Kembalian</span>
<strong>Rp {{ number_format($order->cash_received-$order->total,0,',','.') }}</strong>
</div>

@endif

 @if($order->cashier)<p class="muted">Kasir: {{ $order->cashier->name }}</p>

@endif

<div class="actions no-print">
<a class="button outline" href="{{ route('orders.receipt',[$order,'width'=>58]) }}">Struk 58 mm</a>
<a class="button outline" href="{{ route('orders.receipt',[$order,'width'=>80]) }}">Struk 80 mm</a>
</div>
</section>
@if($order->status==='completed' && $order->user_id===auth()->id())<section class="panel no-print">
<h2>Bagaimana produk Anda?</h2>@foreach($order->items as $item)<form class="review form-stack" method="post" action="{{ route('reviews.store',$item) }}">@csrf<strong>{{ $item->name }}</strong>
<label>Penilaian<select name="rating">@for($i=5;$i>=1;$i--)<option value="{{ $i }}" @selected($item->review?->rating===$i)>{{ $i }} bintang</option>@endfor</select>
</label>
<label>Ulasan<textarea name="comment" required maxlength="1500">{{ $item->review?->comment }}</textarea>
</label>
<button class="small">Simpan Ulasan</button>
</form>

@endforeach

</section>

@endif

</div>
<aside>
<section class="panel">
<h2>{{ $order->fulfillment==='pickup'?'Tiket Pengambilan':'Informasi Pengiriman' }}</h2>@if($order->fulfillment==='pickup')<div class="ticket-code">{{ $order->code }}</div>
<p>Tunjukkan kode ini kepada kasir.</p>
<p>{{ config('shop.address') }}<br>{{ config('shop.hours') }}</p>

@else

<p>
<strong>{{ $order->recipient }}</strong>
<br>{{ $order->phone }}<br>{{ $order->address }}</p>
<div class="notice">
<strong>{{ $order->courier }}</strong>
<br>Nomor resi: <strong>{{ $order->tracking_number??'Menunggu pengiriman' }}</strong>
</div>
<p class="muted">Gunakan nomor resi di situs resmi ekspedisi untuk melacak paket.</p>

@endif

</section>
<section class="panel no-print">
<h2>Pembayaran</h2>
<p>
<span class="badge {{ $order->payment_status }}">{{ $order->payment_status==='paid'?'LUNAS':($order->payment_status==='pending'?'BELUM BAYAR':'DIBATALKAN') }}</span> · {{ $order->payment_method==='midtrans'?'Digital / Midtrans':ucfirst($order->payment_method) }}</p>
@if($order->payment_status==='pending' && $order->expires_at)<p>Batas pembayaran:<br>
<strong>{{ $order->expires_at->format('d M Y, H:i') }} WIB</strong>
</p>@if($order->expires_at->isPast())<div class="notice error">Batas pembayaran berakhir. Pesanan menunggu pembatalan otomatis.</div>@elseif($order->payment_method==='midtrans' && ($order->user_id===auth()->id() || in_array(auth()->user()->role,['kasir','pemilik'])))<form method="post" action="{{ route('orders.payment',$order) }}">@csrf<button class="full" @if(config('shop.midtrans.client_key')) data-snap-url="{{ route('orders.payment',$order) }}"
@endif
>Buka Pembayaran Midtrans</button>
<p data-payment-message role="status">
</p>
</form>

@else

<p class="notice">Bayar tunai di kasir sebelum pesanan disiapkan gudang.</p>

@endif


@if($order->user_id===auth()->id())<form method="post" action="{{ route('orders.cancel',$order) }}" data-confirm="Batalkan pesanan dan lepaskan reservasi stok?">@csrf<button class="danger outline full">Batalkan Pesanan</button>
</form>

@endif



@endif


@if(in_array(auth()->user()->role,['kasir','pemilik']) && $order->payment_status==='pending' && $order->payment_method==='cash' && $order->expires_at?->isFuture())<form method="post" action="{{ route('cashier.cash',$order) }}" class="form-stack">@csrf<label>Uang tunai diterima<input type="number" name="cash_received" min="{{ $order->total }}" required>
</label>
<button>Validasi Pembayaran Tunai</button>
</form>

@endif


@if(in_array(auth()->user()->role,['kasir','pemilik']) && $order->status==='ready')<form method="post" action="{{ route('cashier.handover',$order) }}" data-confirm="Konfirmasi barang telah diterima pelanggan?">@csrf<button class="full">Konfirmasi Penyerahan Barang</button>
</form>

@endif


@if($order->user_id===auth()->id() && $order->status==='shipped')<form method="post" action="{{ route('orders.complete',$order) }}">@csrf<button class="full">Barang Sudah Diterima</button>
</form>

@endif


</section>
</aside>
</div>


@endsection
