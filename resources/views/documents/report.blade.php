<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan Penjualan Toko Maya</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#182a48}h1{font-size:20px;margin:0 0 5px}h2{font-size:13px}table{width:100%;border-collapse:collapse}td,th{padding:7px;border-bottom:1px solid #dce3ef;text-align:left}th{background:#ecf1ff}thead{display:table-header-group}.right{text-align:right}.muted{color:#617189}.total{margin-top:20px;font-size:13px}</style>
</head>
<body>
<h1>TOKO MAYA</h1>
<p>{{ config('shop.address') }}</p>
<h2>LAPORAN PENJUALAN RESMI</h2>
<p>Periode pembayaran: {{ $from??'Awal pencatatan' }} s.d. {{ $to??'Hari ini' }}<br>Dicetak: {{ now()->format('d M Y H:i') }} WIB</p>
<table>
<thead>
<tr>
<th>Invoice / Lunas</th>
<th>Pelanggan</th>
<th>Kanal</th>
<th>Status</th>
<th class="right">Subtotal</th>
<th class="right">Ongkir</th>
<th class="right">Total (Rp)</th>
</tr>
</thead>
<tbody>@forelse($orders as $o)<tr>
<td>{{ $o->code }}<br>{{ $o->paid_at->format('d/m/Y H:i') }}</td>
<td>{{ $o->recipient }}</td>
<td>{{ $o->channel }}</td>
<td>{{ $o->status_label }}</td>
<td class="right">{{ number_format($o->subtotal,0,',','.') }}</td>
<td class="right">{{ number_format($o->shipping_cost,0,',','.') }}</td>
<td class="right">{{ number_format($o->total,0,',','.') }}</td>
</tr>
@empty
<tr>
<td colspan="7">Tidak ada transaksi lunas dalam periode ini.</td>
</tr>
@endforelse
</tbody>
</table>
<p class="total">Jumlah transaksi: {{ $orders->count() }}<br>Total penerimaan bruto: <strong>Rp {{ number_format($orders->sum('total'),0,',','.') }}</strong>
</p>
<p class="muted">Termasuk ongkir, sebelum pengembalian dana retur. Bukan laporan laba rugi.</p>
</body>
</html>
