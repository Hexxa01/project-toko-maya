@extends('layouts.app')
@section('title','Dashboard Pemilik')
@section('content')
<section class="staff-hero">
<span class="eyebrow">PORTAL EKSEKUTIF PEMILIK TOKO</span>
<h1>Dashboard Pemilik Toko Maya</h1>
<p>Analisis penjualan terpadu, kendali inventaris, dan pengelolaan operasional toko.</p>
</section>@include('components.staff-nav')
<form method="get" class="panel filters no-print">
<label>Dari tanggal<input type="date" name="from" value="{{ request('from') }}">
</label>
<label>Sampai tanggal<input type="date" name="to" value="{{ request('to') }}">
</label>
<button>Terapkan Periode</button>
<a href="{{ route('owner.index') }}">Semua waktu</a>
<a class="button outline" href="{{ route('owner.export',request()->only('from','to')) }}">Ekspor CSV</a>
<a class="button outline" href="{{ route('owner.xlsx',request()->only('from','to')) }}">Unduh Excel (.xlsx)</a>
<a class="button outline" href="{{ route('owner.pdf',request()->only('from','to')) }}">Unduh Laporan PDF</a>
<button class="outline" type="button" data-print>Cetak / PDF</button>
</form>
<div class="stats">
<div class="stat">
<span>Penerimaan Hari Ini</span>
<strong>Rp {{ number_format($today,0,',','.') }}</strong>
</div>
<div class="stat">
<span>Penerimaan Bulan Ini</span>
<strong>Rp {{ number_format($thisMonth,0,',','.') }}</strong>
</div>
<div class="stat">
<span>Rentang Grafik</span>
<strong>12 Bulan</strong>
<small>Termasuk bulan tanpa transaksi</small>
</div>
</div>
<section class="panel">
<h2>Performa Penjualan 12 Bulan Terakhir</h2>
<p class="muted">Penerimaan lunas bruto per bulan, termasuk ongkir. Grafik ini selalu mencakup 12 bulan terakhir; filter tanggal di atas berlaku pada rincian laporan.</p>
<div class="annual-chart" role="img" aria-label="Grafik penerimaan 12 bulan terakhir">@php($peak=max(1,$months->max(fn($m)=>max($m['online'],$m['pos']))))@foreach($months as $m)<div class="month-column">
<div class="month-bars">
<span class="online-bar" style="height:{{ $m['online']?max(2,round($m['online']/$peak*100)):0 }}%" title="Online: Rp {{ number_format($m['online'],0,',','.') }}">
</span>
<span class="pos-bar" style="height:{{ $m['pos']?max(2,round($m['pos']/$peak*100)):0 }}%" title="POS: Rp {{ number_format($m['pos'],0,',','.') }}">
</span>
</div>
<small>{{ $m['label'] }}</small>
</div>
@endforeach
</div>
<p class="muted">Hijau: Online · Biru: POS</p>
<details>
<summary>Lihat angka per bulan</summary>
<div class="table-wrap">
<table>
<tr>
<th>Bulan</th>
<th>Online (Rp)</th>
<th>POS (Rp)</th>
</tr>@foreach($months as $m)<tr>
<td>{{ $m['label'] }}</td>
<td>{{ number_format($m['online'],0,',','.') }}</td>
<td>{{ number_format($m['pos'],0,',','.') }}</td>
</tr>
@endforeach
</table>
</div>
</details>
</section>
<div class="stats four">
<div class="stat">
<span>Total Penerimaan Lunas</span>
<strong>Rp {{ number_format($revenue,0,',','.') }}</strong>
<small>Termasuk ongkir · sebelum retur</small>
</div>
<div class="stat">
<span>Transaksi Lunas</span>
<strong>{{ $salesCount }}</strong>
<small>Sesuai periode pembayaran</small>
</div>
<div class="stat">
<span>Inventaris Aktif</span>
<strong>{{ $productCount }} SKU</strong>
<small>{{ $low->count() }} perlu perhatian</small>
</div>
<div class="stat">
<span>Pengguna Terdaftar</span>
<strong>{{ $userCount }}</strong>
<small>Seluruh peran</small>
</div>
</div>
<div class="two-col">
<section class="panel">
<h2>Rekap Penjualan & Keuangan</h2>
<div class="metric">
<span>Website E-Commerce</span>
<strong>Rp {{ number_format($online,0,',','.') }}</strong>
</div>
<div class="metric">
<span>Kasir Walk-in / POS</span>
<strong>Rp {{ number_format($pos,0,',','.') }}</strong>
</div>
<p class="muted">Dana retur diselesaikan (seluruh waktu): Rp {{ number_format($refundTotal,0,',','.') }}. Nilai penerimaan di atas bukan perhitungan laba.</p>
<h3>Tren Penerimaan Harian</h3>@forelse($daily->reverse() as $day)<div class="chart-row">
<small>{{ $day->day }}</small>
<div>
<span style="width:{{ max(1,round($day->amount/max(1,$daily->max('amount'))*100)) }}%">
</span>
</div>
<strong>Rp {{ number_format($day->amount,0,',','.') }}</strong>
</div>

@empty

<p class="muted">Grafik tersedia setelah ada pembayaran lunas.</p>

@endforelse

</section>
<section class="panel">
<h2>5 Produk Terlaris</h2>@forelse($top as $p)<div class="rank-row">
<span class="rank">{{ $loop->iteration }}</span>
<strong>{{ $p->name }}</strong>
<span>{{ $p->sold }} terjual</span>
</div>

@empty

<p class="muted">Belum ada penjualan.</p>

@endforelse

<h3>Peringatan Stok Minimum</h3>@forelse($low as $p)<div class="sum-row">
<a href="{{ route('owner.products.edit',$p) }}">{{ $p->name }}</a>
<span class="badge pending">{{ $p->stock }} {{ $p->unit }}</span>
</div>

@empty

<p class="muted">Semua stok di atas batas minimum.</p>

@endforelse

</section>
</div>
<section class="panel">
<h2>Transaksi Lunas</h2>@include('components.orders-table')</section>
<section class="panel">
<h2>Penyelesaian Dana Retur</h2>
<p class="muted">Lakukan pengembalian dana melalui kas/bank/gateway terlebih dahulu. Formulir ini hanya mencatat bukti penyelesaian; tidak mentransfer uang otomatis.</p>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Retur / Pesanan</th>
<th>Barang & Kondisi</th>
<th>Nominal</th>
<th>Penyelesaian</th>
</tr>
</thead>
<tbody>@forelse($returns as $ret)<tr>
<td>{{ $ret->code }}<small>{{ $ret->item->order->code }}</small>
</td>
<td>{{ $ret->item->name }} × {{ $ret->quantity }}<small>{{ $ret->restock?'Layak jual':'Karantina' }} · {{ $ret->reason }}</small>
</td>
<td>Rp {{ number_format($ret->refund_amount,0,',','.') }}</td>
<td>@if($ret->refund_status==='pending')<form class="actions" method="post" action="{{ route('owner.refund',$ret) }}" data-confirm="Apakah dana retur sudah dikembalikan kepada pelanggan?">@csrf<input name="refund_reference" placeholder="Referensi bukti pembayaran" required maxlength="150">
<button class="small">Catat Selesai</button>
</form>

@else

<span class="badge paid">Diselesaikan</span>
<small>{{ $ret->refund_reference }}</small>

@endif

</td>
</tr>

@empty

<tr>
<td colspan="4">Tidak ada retur.</td>
</tr>

@endforelse

</tbody>
</table>
</div>
<div class="pagination">{{ $returns->links() }}</div>
</section>


@endsection
