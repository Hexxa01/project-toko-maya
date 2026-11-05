@extends('layouts.app')
@section('title','Sistem Kasir')
@section('content')
<section class="staff-hero">
<span class="eyebrow">POINT OF SALE</span>
<h1>Sistem Kasir & Transaksi Toko</h1>
<p>Transaksi fisik, pengambilan pesanan, dan pengembalian barang dalam satu tempat.</p>
</section>@include('components.staff-nav')
<nav class="tabs">
<a href="#pos">Transaksi Fisik (POS)</a>
<a href="#pickup">Ambil di Toko & Riwayat POS</a>
<a href="#online">Transaksi Online Masuk</a>
<a href="#retur">Pengembalian Barang</a>
</nav>
<section id="pos">
<div class="section-heading">
<h2>Transaksi Fisik (POS Kasir)</h2>
<form class="search" method="get" data-pos-search="{{ route('cashier.search') }}">
<input name="q" value="{{ request('q') }}" placeholder="Cari nama / kode produk" aria-label="Cari nama / kode produk">
<button>Cari</button>
</form>
</div>
<form class="pos-grid" method="post" action="{{ route('cashier.pos') }}" data-pos data-user-id="{{ auth()->id() }}">@csrf<input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
<div>
<div class="pos-products">@include('staff.pos-products')
</div>
<p class="muted">Pilihan barang tetap tersimpan selama pencarian dan pergantian halaman di tab browser ini.</p>
<div class="pagination">{{ $products->links() }}</div>
</div>
<aside class="panel summary form-stack">
<h2>Struk Belanja Kasir</h2>
<label>Nama pelanggan<input name="recipient" value="{{ old('recipient','Pelanggan Walk-in') }}" maxlength="120" required>
</label>
<div data-pos-lines class="muted">Belum ada produk dipilih.</div>
<div class="sum-row total">
<span>Total</span>
<strong data-pos-total>Rp 0</strong>
</div>
<label>Metode pembayaran<select name="payment_method">
<option value="cash" @selected(old('payment_method')==='cash')>Tunai</option>
<option value="debit" @selected(old('payment_method')==='debit')>Debit / EDC (terverifikasi kasir)</option>
<option value="midtrans" @disabled(!config('shop.midtrans.server_key'))>QRIS / Digital via Midtrans</option>
</select>
</label>
<label>Uang diterima (tunai)<input type="number" name="cash_received" value="{{ old('cash_received') }}" min="0" data-cash>
</label>
<label>Referensi transaksi EDC (debit)<input name="payment_reference" maxlength="100" value="{{ old('payment_reference') }}">
</label>
<div class="sum-row">
<span>Kembalian tunai</span>
<strong data-change>Rp 0</strong>
</div>
<p class="muted">Debit hanya boleh divalidasi setelah transaksi EDC berhasil. Pembayaran digital menunggu konfirmasi gateway.</p>
<button>Proses Transaksi →</button>
</aside>
</form>
</section>
<section class="panel" id="pickup">
<div class="section-heading">
<h2>Pengambilan Barang & Riwayat POS</h2>
<form class="actions">
<label class="sr-only" for="code">Kode pesanan</label>
<input name="code" id="code" value="{{ request('code') }}" placeholder="Kode pesanan lengkap">
<button class="small">Cari Pesanan</button>
<a href="{{ route('cashier.index') }}#pickup">Reset</a>
</form>
</div>
<p class="muted">Buka detail pesanan untuk menerima tunai, mencetak struk, atau memvalidasi penyerahan barang yang sudah siap.</p>@include('components.orders-table')</section>
<section class="panel" id="online">
<h2>Monitoring Transaksi Online via Ekspedisi</h2>@include('components.orders-table',['orders'=>$online])</section>
<section class="panel" id="retur">
<h2>Validasi Pengembalian Barang</h2>
<p>Hanya pesanan selesai dan lunas yang dapat diretur. Barang cacat tidak dikembalikan ke stok layak jual.</p>
<form method="post" action="{{ route('cashier.returns') }}" class="form-grid">@csrf<label>Nomor pesanan<input name="code" required maxlength="80">
</label>
<label>SKU barang (lihat detail pesanan)<input name="sku" required maxlength="80">
</label>
<label>Jumlah retur<input name="quantity" type="number" min="1" max="999" required>
</label>
<label>Kondisi barang<select name="restock">
<option value="0">Rusak / karantina (tidak menambah stok jual)</option>
<option value="1">Layak jual (kembalikan ke stok)</option>
</select>
</label>
<label class="span-2">Alasan retur<textarea name="reason" required maxlength="1000">
</textarea>
</label>
<button>Validasi & Catat Retur</button>
</form>
<h3>Catatan Pengembalian Barang</h3>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Nomor Retur</th>
<th>Pesanan / Produk</th>
<th>Jumlah</th>
<th>Stok</th>
<th>Dana Retur</th>
</tr>
</thead>
<tbody>@forelse($returns as $ret)<tr>
<td>{{ $ret->code }}</td>
<td>{{ $ret->item->order->code }}<small>{{ $ret->item->name }}</small>
</td>
<td>{{ $ret->quantity }}</td>
<td>{{ $ret->restock?'Dikembalikan':'Karantina' }}</td>
<td>{{ $ret->refund_status==='paid'?'Diselesaikan':'Menunggu pemilik' }}</td>
</tr>

@empty

<tr>
<td colspan="5">Belum ada retur.</td>
</tr>

@endforelse

</tbody>
</table>
</div>
</section>


@endsection
