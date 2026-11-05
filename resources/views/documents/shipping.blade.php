<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dokumen {{ $order->code }}</title>
<style>@page{size:A4;margin:15mm}body{font:12px/1.5 DejaVu Sans,sans-serif;color:#182a48;max-width:760px;margin:25px auto;padding:20px}h1{font-size:23px;margin:0}h2{font-size:18px}.box{border:1px solid #aab7c9;padding:18px;margin:15px 0}table{width:100%;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #dbe3ef;text-align:left}.signatures{margin-top:50px;width:100%}.signatures td{width:50%;height:80px;text-align:center;border:0}@media print{nav{display:none}body{margin:0;padding:0}}</style>@if(!request()->has('format') || request('format')!=='pdf')@vite('resources/js/documents.js')
@endif
</head>
<body>
<nav>
<button data-print>Cetak Dokumen</button> <a href="{{ route('warehouse.document',[$order,'type'=>$type,'format'=>'pdf']) }}">Unduh PDF</a>
</nav>
<h1>TOKO MAYA</h1>
<p>{{ config('shop.address') }}</p>
<h2>{{ $type==='label'?'LABEL PAKET PENGIRIMAN':'SURAT JALAN / SERAH TERIMA' }}</h2>
<p>No. dokumen: SJ-{{ $order->code }}<br>Tanggal: {{ now()->format('d/m/Y') }}<br>Status: {{ $order->status_label }} · LUNAS</p>
<div class="box">
<strong>PENERIMA: {{ $order->recipient }}</strong>
<br>{{ $order->phone }}<br>{{ $order->fulfillment==='pickup'?'Ambil sendiri di Toko Maya':$order->address }}<br>{{ config('shop.shipping_zones.'.$order->shipping_zone.'.name','') }} {{ $order->postal_code }}<br>Kurir: {{ $order->courier??'Ambil di toko' }}<br>Resi: <strong>{{ $order->tracking_number??'Belum diterbitkan' }}</strong>
</div>@if($type==='delivery')<table>
<thead>
<tr>
<th>SKU</th>
<th>Nama Barang</th>
<th>Jumlah</th>
</tr>
</thead>
<tbody>@foreach($order->items as $item)<tr>
<td>{{ $item->sku }}</td>
<td>{{ $item->name }}</td>
<td>{{ $item->quantity }}</td>
</tr>
@endforeach
</tbody>
</table>
<table class="signatures">
<tr>
<td>Petugas Gudang<br>
<br>
<br>(________________)</td>
<td>Penerima / Kurir<br>
<br>
<br>(________________)</td>
</tr>
</table>
@endif
<p>Dokumen pemenuhan pesanan, bukan bukti pembayaran baru.</p>
</body>
</html>
