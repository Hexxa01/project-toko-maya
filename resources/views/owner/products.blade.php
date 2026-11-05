@extends('layouts.app')
@section('title','Manajemen Produk')
@section('content')
<section class="staff-hero">
<span class="eyebrow">MASTER DATA TOKO</span>
<h1>Katalog & Manajemen Produk</h1>
<p>Kelola harga, spesifikasi, kategori, dan visibilitas produk.</p>
</section>@include('components.staff-nav')
<section class="panel">
<div class="section-heading">
<h2>Daftar Produk</h2>
<a class="button" href="{{ route('owner.products.create') }}">+ Tambah Produk</a>
</div>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Produk</th>
<th>Kategori</th>
<th>Harga</th>
<th>Stok</th>
<th>Status</th>
<th>
</th>
</tr>
</thead>
<tbody>@forelse($products as $p)<tr>
<td>
<strong>{{ $p->name }}</strong>
<small>{{ $p->sku }}</small>
</td>
<td>{{ $p->category->name }}</td>
<td>Rp {{ number_format($p->price,0,',','.') }}</td>
<td>{{ $p->stock }} {{ $p->unit }}</td>
<td>
<span class="badge {{ $p->active?'paid':'cancelled' }}">{{ $p->active?'Aktif':'Nonaktif' }}</span>
</td>
<td>
<a href="{{ route('owner.products.edit',$p) }}">Edit →</a>
</td>
</tr>

@empty

<tr>
<td colspan="6">Belum ada produk. Tambahkan produk pertama Anda.</td>
</tr>

@endforelse

</tbody>
</table>
</div>
<div class="pagination">{{ $products->links() }}</div>
</section>
<section class="panel">
<h2>Kategori Produk</h2>
<div class="tags">@foreach($categories as $c)<span class="badge">{{ $c->name }}</span>

@endforeach

</div>
<form class="filters" method="post" action="{{ route('owner.categories.store') }}">@csrf<label>Nama kategori baru<input name="name" required maxlength="100">
</label>
<button>Tambah Kategori</button>
</form>
</section>


@endsection
