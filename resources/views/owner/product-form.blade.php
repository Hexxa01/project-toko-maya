@extends('layouts.app')
@section('title',$product->exists?'Edit Produk':'Tambah Produk')
@section('content')
<div class="page-heading">
<span class="eyebrow">MASTER DATA KATALOG</span>
<h1>{{ $product->exists?'Edit Produk':'Tambah Produk Baru' }}</h1>
<p>Lengkapi informasi barang sebelum ditampilkan pada katalog.</p>
</div>@include('components.staff-nav')
<form class="panel form-grid" method="post" enctype="multipart/form-data" action="{{ $product->exists?route('owner.products.update',$product):route('owner.products.store') }}">@csrf @if($product->exists)@method('PUT')

@endif


@foreach(['name'=>'Nama Produk','sku'=>'SKU / Kode Barang','price'=>'Harga Satuan (Rp)','unit'=>'Satuan','weight'=>'Berat (gram)','minimum_stock'=>'Batas Minimum Stok','brand'=>'Merek','material'=>'Material','specification'=>'Ukuran / Tipe','warranty'=>'Garansi'] as $key=>$label)<label>{{ $label }}<input name="{{ $key }}" type="{{ in_array($key,['price','weight','minimum_stock'])?'number':'text' }}" value="{{ old($key,$product->$key??match($key){'weight'=>1000,'minimum_stock'=>5,'unit'=>'pcs',default=>''}) }}" @required(in_array($key,['name','sku','price','unit','weight','minimum_stock'])) @if(in_array($key,['price','weight'])) min="1" @elseif($key==='minimum_stock') min="0"

@endif

>
</label>

@endforeach


<label>Kategori<select name="category_id" required>
<option value="">Pilih kategori</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id',$product->category_id)===$c->id || old('category_id')==$c->id)>{{ $c->name }}</option>

@endforeach

</select>
</label>
<label>Status katalog<select name="active">
<option value="1" @selected(old('active',$product->active??true))>Aktif</option>
<option value="0" @selected(!old('active',$product->active??true))>Nonaktif</option>
</select>
</label>
<label class="span-2">Deskripsi Produk<textarea name="description" rows="5" maxlength="5000" required>{{ old('description',$product->description) }}</textarea>
</label>
<label>Foto Produk (JPG / PNG / WebP, maks. 3 MB)<input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
</label>
<div class="notice">Stok awal produk baru adalah 0. Gunakan menu Gudang untuk menambah persediaan agar perubahan tercatat.</div>
<div class="actions span-2">
<button>Simpan Produk</button>
<a class="button outline" href="{{ route('owner.products') }}">Batal</a>
</div>
</form>


@endsection
