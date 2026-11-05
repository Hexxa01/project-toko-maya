@extends('layouts.app')
@section('title','Buku Alamat')
@section('content')
<div class="page-heading">
<span class="eyebrow">AKUN PELANGGAN</span>
<h1>Buku Alamat Pengiriman</h1>
<p>Simpan alamat agar checkout berikutnya lebih cepat.</p>
</div>
<div class="two-col">
<section class="panel">
<h2>Alamat Tersimpan</h2>@forelse($addresses as $a)<article class="queue-card">
<strong>{{ $a->label }}</strong> @if($a->is_default)<span class="badge paid">Utama</span>
@endif
<p>{{ $a->recipient }} · {{ $a->phone }}<br>{{ $a->address }}<br>{{ config('shop.shipping_zones.'.$a->zone.'.name') }} {{ $a->postal_code }}</p>
<details>
<summary>Edit alamat</summary>
<form method="post" action="{{ route('addresses.update',$a) }}" class="form-stack">@csrf @method('PUT')@include('shop.address-fields',['a'=>$a])<button>Simpan Perubahan</button>
</form>
</details>
<form method="post" action="{{ route('addresses.destroy',$a) }}" data-confirm="Hapus alamat tersimpan ini?">@csrf @method('DELETE')<button class="danger outline small">Hapus Alamat</button>
</form>
</article>
@empty
<p class="muted">Belum ada alamat tersimpan.</p>
@endforelse
</section>
<section class="panel">
<h2>Tambah Alamat Baru</h2>
<form method="post" action="{{ route('addresses.store') }}" class="form-stack">@csrf @include('shop.address-fields',['a'=>null])<button>Simpan Alamat</button>
</form>
</section>
</div>
<a href="{{ route('checkout') }}">← Kembali ke Checkout</a>

@endsection
