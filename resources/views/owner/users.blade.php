@extends('layouts.app')
@section('title','Manajemen Pengguna')
@section('content')
<section class="staff-hero">
<span class="eyebrow">KONTROL AKSES SISTEM</span>
<h1>Manajemen Pengguna</h1>
<p>Kelola akun dan pembagian peran operasional Toko Maya.</p>
</section>@include('components.staff-nav')
<section class="panel">
<h2>Tambah Pengguna</h2>
<form class="form-grid" method="post" action="{{ route('owner.users.store') }}">@csrf<label>Nama<input name="name" required maxlength="120">
</label>
<label>Email<input name="email" type="email" required>
</label>
<label>Kata sandi awal<input name="password" type="password" minlength="8" required autocomplete="new-password">
</label>
<label>Peran<select name="role">@foreach(['pelanggan'=>'Pelanggan','kasir'=>'Kasir','admin_gudang'=>'Admin Gudang','pemilik'=>'Pemilik'] as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>

@endforeach

</select>
</label>
<input type="hidden" name="active" value="1">
<button>Tambah Pengguna</button>
</form>
</section>
<section class="panel">
<h2>Daftar Pengguna & Hak Akses (RBAC)</h2>@foreach($users as $u)<details class="user-row">
<summary>
<strong>{{ $u->name }}</strong>
<span>{{ $u->email }}</span>
<span class="badge">{{ str_replace('_',' ',$u->role) }}</span>
<span class="badge {{ $u->active?'paid':'cancelled' }}">{{ $u->active?'Aktif':'Nonaktif' }}</span>
</summary>
<form class="form-grid" method="post" action="{{ route('owner.users.update',$u) }}">@csrf @method('PUT')<label>Nama<input name="name" value="{{ $u->name }}" required>
</label>
<label>Email<input name="email" type="email" value="{{ $u->email }}" required>
</label>
<label>Peran<select name="role">@foreach(['pelanggan'=>'Pelanggan','kasir'=>'Kasir','admin_gudang'=>'Admin Gudang','pemilik'=>'Pemilik'] as $key=>$label)<option value="{{ $key }}" @selected($u->role===$key)>{{ $label }}</option>

@endforeach

</select>
</label>
<label>Status<select name="active">
<option value="1" @selected($u->active)>Aktif</option>
<option value="0" @selected(!$u->active)>Nonaktif</option>
</select>
</label>
<label>Kata sandi baru (kosongkan jika tetap)<input name="password" type="password" minlength="8" autocomplete="new-password">
</label>
<div class="actions">
<button>Simpan Perubahan</button>
</div>
</form>
</details>

@endforeach

<div class="pagination">{{ $users->links() }}</div>
</section>


@endsection
