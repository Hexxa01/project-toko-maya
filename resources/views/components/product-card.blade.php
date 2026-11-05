<article class="product-card">
<a href="{{ route('products.show',$product) }}" class="product-image">
<img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy">@if(!$product->image)<span class="image-label">Ilustrasi produk</span>

@endif

</a>
<div class="product-body">
<span class="eyebrow">{{ $product->category->name }}</span>
<a class="product-name" href="{{ route('products.show',$product) }}">{{ $product->name }}</a>
<div class="product-meta">
<strong class="price">Rp {{ number_format($product->price,0,',','.') }}</strong>
<span>{{ $product->stock }} {{ $product->unit }}</span>
</div>
<form method="post" action="{{ route('cart.add',$product) }}">@csrf<input type="hidden" name="quantity" value="1">
<button class="outline full" @disabled($product->stock<1)>{{ $product->stock>0?'+ Keranjang':'Stok habis' }}</button>
</form>
</div>
</article>
