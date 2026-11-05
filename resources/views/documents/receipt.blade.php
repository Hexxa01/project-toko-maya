<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Struk {{ $order->code }}</title>
<style>@page{size:{{ $width }}mm 200mm;margin:0}*{box-sizing:border-box}body{font:11px/1.4 monospace;margin:0;padding:3mm;width:{{ $width }}mm;color:black;background:white;overflow-wrap:anywhere}h1{font-size:17px;margin:0}p{margin:5px 0}.center{text-align:center}.line{border-bottom:1px dashed black;margin:8px 0}table{width:100%;border-collapse:collapse;font-size:10px}td{vertical-align:top;padding:3px 0}.right{text-align:right}nav{font:12px sans-serif;margin-bottom:15px}button,a{margin:5px 0;display:inline-block}@media print{nav{display:none}}</style>@vite('resources/js/documents.js')</head>
<body>
<nav>
<button data-print>Cetak Struk</button>
<a href="{{ route('orders.receipt',[$order,'width'=>58]) }}">58 mm</a> · <a href="{{ route('orders.receipt',[$order,'width'=>80]) }}">80 mm</a>
<p>Pilih kertas {{ $width }} mm, skala 100%, nonaktifkan header/footer browser.</p>
</nav>
<div class="center">
<h1>TOKO MAYA</h1>
<p>{{ config('shop.address') }}</p>
</div>
<div class="line">
</div>
<p>{{ $order->code }}<br>{{ $order->created_at->format('d/m/Y H:i') }} WIB<br>Kasir: {{ $order->cashier?->name??'Online' }}<br>{{ $order->recipient }}</p>
<p>
<strong>{{ $order->payment_status==='paid'?'LUNAS':'BELUM LUNAS' }}</strong> · {{ strtoupper($order->payment_method) }}</p>
<div class="line">
</div>
<table>@foreach($order->items as $item)<tr>
<td colspan="2">{{ $item->name }}</td>
</tr>
<tr>
<td>{{ $item->quantity }} × {{ number_format($item->price,0,',','.') }}</td>
<td class="right">{{ number_format($item->quantity*$item->price,0,',','.') }}</td>
</tr>
@endforeach
<tr>
<td>Subtotal</td>
<td class="right">{{ number_format($order->subtotal,0,',','.') }}</td>
</tr>
<tr>
<td>Ongkir</td>
<td class="right">{{ number_format($order->shipping_cost,0,',','.') }}</td>
</tr>
<tr>
<td>
<strong>TOTAL</strong>
</td>
<td class="right">
<strong>{{ number_format($order->total,0,',','.') }}</strong>
</td>
</tr>@if($order->cash_received!==null)<tr>
<td>Tunai</td>
<td class="right">{{ number_format($order->cash_received,0,',','.') }}</td>
</tr>
<tr>
<td>Kembalian</td>
<td class="right">{{ number_format($order->cash_received-$order->total,0,',','.') }}</td>
</tr>
@endif
</table>
<div class="line">
</div>
<p class="center">Terima kasih telah berbelanja.<br>Simpan struk sebagai bukti pembelian.</p>
</body>
</html>
