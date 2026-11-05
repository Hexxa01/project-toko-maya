@extends('layouts.app')
@section('title','Dashboard Gudang')
@section('content')
<section class="staff-hero">
<span class="eyebrow">OPERASIONAL & INVENTARIS</span>
<h1>Dashboard Admin Gudang</h1>
<p>Siapkan pesanan, kelola persediaan, dan perbarui resi pengiriman.</p>
</section>@include('components.staff-nav')
<div class="stats">
<div class="stat">
<span>Pesanan perlu disiapkan</span>
<strong>{{ $queueCount }}</strong>
<small>Sudah lunas</small>
</div>
<div class="stat">
<span>Stok perlu perhatian</span>
<strong>{{ $lowCount }}</strong>
<small>Produk menipis atau habis</small>
</div>
<div class="stat">
<span>Persediaan terintegrasi</span>
<strong>Online + POS</strong>
<small>Stok direservasi saat pesanan dibuat</small>
</div>
</div>
<section class="panel">
<div class="section-heading">
<h2>Antrean Pesanan & Pengiriman</h2>
<form class="actions">
<label class="sr-only" for="fulfillment">Jenis pemenuhan</label>
<select name="fulfillment" id="fulfillment">
<option value="">Semua metode</option>
<option value="pickup" @selected(request('fulfillment')==='pickup')>Ambil di toko</option>
<option value="shipping" @selected(request('fulfillment')==='shipping')>Ekspedisi</option>
</select>
<button class="small">Filter</button>
</form>
</div>
@forelse($orders as $o)<article class="queue-card">
<div class="section-heading">
<div>
<a href="{{ route('orders.show',$o) }}">
<strong>{{ $o->code }}</strong>
</a>
<p>{{ $o->recipient }} · {{ $o->fulfillment==='pickup'?'Ambil di Toko':'Kirim Ekspedisi' }}</p>
</div>
<span class="badge {{ $o->status }}">{{ $o->status_label }}</span>
</div>
<div class="actions">
<a href="{{ route('warehouse.document',[$o,'type'=>'delivery']) }}">Surat Jalan</a>
<a href="{{ route('warehouse.document',[$o,'type'=>'label']) }}">Label Paket</a>@if($o->status==='processing' && !$o->verified_at)<form method="post" action="{{ route('warehouse.fulfill',$o) }}">@csrf<input type="hidden" name="action" value="verify">
<button class="outline small">Verifikasi & Proses Pesanan</button>
</form>@elseif($o->verified_at)<span class="badge paid">Diverifikasi gudang</span>
@endif
</div>
<p class="muted">@foreach($o->items as $i){{ $i->name }} × {{ $i->quantity }}{{ $loop->last?'':' · ' }}

@endforeach

</p>
@if($o->status==='processing' && $o->fulfillment==='pickup')<form method="post" action="{{ route('warehouse.fulfill',$o) }}">@csrf<input type="hidden" name="action" value="ready">
<button class="small">Konfirmasi Siap Diambil</button>
</form>@elseif($o->fulfillment==='shipping')<form method="post" action="{{ route('warehouse.fulfill',$o) }}" class="filters">@csrf<input type="hidden" name="action" value="{{ $o->status==='shipped'?'tracking':'ship' }}">
<label>Ekspedisi<input name="courier" value="{{ $o->courier }}" required maxlength="80">
</label>
<label>Nomor resi<input name="tracking_number" value="{{ $o->tracking_number }}" required maxlength="100">
</label>
<button class="small">{{ $o->status==='shipped'?'Simpan Koreksi Resi':'Input Resi & Kirim' }}</button>
</form>

@endif

</article>

@empty

<div class="empty">Tidak ada pesanan lunas yang menunggu proses.</div>

@endforelse

<div class="pagination">{{ $orders->links() }}</div>
</section>
<section class="panel" id="stok">
<div class="section-heading">
<h2>Manajemen Stok Fisik</h2>
<form class="actions">
<label class="sr-only" for="stock">Filter stok</label>
<select name="stock" id="stock">
<option value="">Semua stok</option>@foreach(['safe'=>'Stok Aman','low'=>'Stok Menipis','empty'=>'Stok Habis'] as $key=>$label)<option value="{{ $key }}" @selected(request('stock')===$key)>{{ $label }}</option>

@endforeach

</select>
<button class="small">Filter</button>
</form>
</div>
<p class="muted">Stok tersedia tidak termasuk barang yang sudah direservasi pesanan. Gunakan penyesuaian untuk penerimaan barang atau koreksi fisik, bukan untuk penyerahan pesanan.</p>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Produk</th>
<th>Stok Tersedia</th>
<th>Status</th>
<th>Penyesuaian (+ / −)</th>
</tr>
</thead>
<tbody>@foreach($products as $p)<tr>
<td>
<strong>{{ $p->name }}</strong>
<small>{{ $p->sku }}</small>
</td>
<td>{{ $p->stock }} {{ $p->unit }}</td>
<td>
<span class="badge {{ $p->stock===0?'cancelled':($p->stock<=$p->minimum_stock?'pending':'paid') }}">{{ $p->stock===0?'Habis':($p->stock<=$p->minimum_stock?'Menipis':'Aman') }}</span>
</td>
<td>
<form method="post" action="{{ route('warehouse.stock',$p) }}" class="actions">@csrf<label class="sr-only" for="change-{{ $p->id }}">Perubahan stok {{ $p->name }}</label>
<input id="change-{{ $p->id }}" type="number" name="change" placeholder="+10 / -2" class="quantity" required>
<label class="sr-only" for="reason-{{ $p->id }}">Alasan</label>
<input id="reason-{{ $p->id }}" name="reason" placeholder="Alasan penyesuaian" required maxlength="255">
<button class="small">Simpan</button>
</form>
</td>
</tr>

@endforeach

</tbody>
</table>
</div>
<div class="pagination">{{ $products->links() }}</div>
</section>
<section class="panel">
<h2>Riwayat Pergerakan Stok Terbaru</h2>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Waktu</th>
<th>Produk</th>
<th>Perubahan</th>
<th>Alasan</th>
</tr>
</thead>
<tbody>@forelse($movements as $m)<tr>
<td>{{ $m->created_at->format('d/m H:i') }}</td>
<td>{{ $m->product->name }}</td>
<td>{{ $m->change>0?'+':'' }}{{ $m->change }}</td>
<td>{{ $m->reason }}</td>
</tr>

@empty

<tr>
<td colspan="4">Belum ada pergerakan stok.</td>
</tr>

@endforelse

</tbody>
</table>
</div>
</section>


@endsection
