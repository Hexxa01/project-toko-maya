@foreach($products as $p)<label class="pos-product">
<img src="{{ $p->image_url }}" alt="{{ $p->name }}">
<strong>{{ $p->name }}</strong>
<span class="price">Rp {{ number_format($p->price,0,',','.') }}</span>
<small>Stok {{ $p->stock }} {{ $p->unit }}</small>
<span>Jumlah</span>
<input type="number" name="items[{{ $p->id }}]" min="0" max="{{ min($p->stock,999) }}" value="{{ old('items.'.$p->id,0) }}" data-price="{{ $p->price }}">
</label>

@endforeach
