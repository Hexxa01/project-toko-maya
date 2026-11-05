@foreach(['label'=>'Label (Rumah / Kantor)','recipient'=>'Nama penerima','phone'=>'Nomor telepon','address'=>'Alamat lengkap','postal_code'=>'Kode pos'] as $field=>$label)
<label>{{ $label }}<input name="{{ $field }}" value="{{ old($field,$a?->$field) }}" required maxlength="{{ match($field){'label'=>60,'recipient'=>120,'phone'=>25,'postal_code'=>5,default=>1000} }}" @if($field==='postal_code') pattern="[0-9]{5}" inputmode="numeric"
@endif
>
</label>

@endforeach

<label>Wilayah pengiriman<select name="zone" required>@foreach(config('shop.shipping_zones') as $key=>$zone)<option value="{{ $key }}" @selected(old('zone',$a?->zone)===$key)>{{ $zone['name'] }}</option>
@endforeach
</select>
</label>
<label class="check">
<input type="checkbox" name="is_default" value="1" @checked($a?->is_default)> Jadikan alamat utama</label>
