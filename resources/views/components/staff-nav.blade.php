<nav class="tabs" aria-label="Menu staf">
@if(auth()->user()->role==='pemilik')<a class="{{ request()->routeIs('owner.index')?'active':'' }}" href="{{ route('owner.index') }}">Dashboard & Laporan</a>
<a class="{{ request()->routeIs('owner.products*')?'active':'' }}" href="{{ route('owner.products') }}">Manajemen Produk</a>
<a class="{{ request()->routeIs('owner.users*')?'active':'' }}" href="{{ route('owner.users') }}">Manajemen Pengguna</a>

@endif


@if(in_array(auth()->user()->role,['pemilik','admin_gudang']))<a class="{{ request()->routeIs('warehouse.*')?'active':'' }}" href="{{ route('warehouse.index') }}">Gudang & Pengiriman</a>

@endif


@if(in_array(auth()->user()->role,['pemilik','kasir']))<a class="{{ request()->routeIs('cashier.*')?'active':'' }}" href="{{ route('cashier.index') }}">Kasir & Transaksi</a>

@endif


</nav>
