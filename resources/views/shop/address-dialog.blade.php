<dialog id="address-book" aria-labelledby="address-title">
<div class="section-heading">
<h2 id="address-title">Pilih Alamat Pengiriman</h2>
<button type="button" class="outline small" data-close-dialog>Tutup</button>
</div>@forelse($addresses as $a)<article class="queue-card">
<strong>{{ $a->label }}</strong> @if($a->is_default)<span class="badge paid">Utama</span>
@endif
<p>{{ $a->recipient }} · {{ $a->phone }}<br>{{ $a->address }}<br>{{ config('shop.shipping_zones.'.$a->zone.'.name') }} {{ $a->postal_code }}</p>
<button type="button" class="small" data-select-address="{{ json_encode($a->only(['id','recipient','phone','address','zone','postal_code'])) }}">Gunakan Alamat Ini</button>
</article>
@empty
<p class="muted">Simpan alamat pertama Anda di bawah.</p>
@endforelse
<details>
<summary>+ Tambah Alamat Baru</summary>
<form method="post" action="{{ route('addresses.store') }}" class="form-stack">@csrf @include('shop.address-fields',['a'=>null])<button>Simpan Alamat</button>
</form>
</details>
<a href="{{ route('addresses.index') }}">Kelola semua alamat →</a>
</dialog>
