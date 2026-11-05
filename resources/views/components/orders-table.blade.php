<div class="table-wrap">
<table>
<thead>
<tr>
<th>Nomor Pesanan</th>
<th>Pelanggan</th>
<th>Metode</th>
<th>Total</th>
<th>Status</th>
<th>
</th>
</tr>
</thead>
<tbody>@forelse($orders as $o)<tr>
<td>
<strong>{{ $o->code }}</strong>
<small>{{ $o->created_at->format('d M Y H:i') }}</small>
</td>
<td>{{ $o->recipient }}</td>
<td>{{ $o->fulfillment==='pickup'?'Ambil di toko':'Ekspedisi' }}<small>{{ $o->payment_method==='midtrans'?'Digital':ucfirst($o->payment_method) }} · {{ $o->payment_status==='paid'?'Lunas':'Belum lunas' }}</small>
</td>
<td>Rp {{ number_format($o->total,0,',','.') }}</td>
<td>
<span class="badge {{ $o->status }}">{{ $o->status_label }}</span>
</td>
<td>
<a href="{{ route('orders.show',$o) }}">Detail →</a>
</td>
</tr>

@empty

<tr>
<td colspan="6" class="empty">Belum ada pesanan pada daftar ini.</td>
</tr>

@endforelse

</tbody>
</table>
</div>
@if($orders instanceof \Illuminate\Contracts\Pagination\Paginator)<div class="pagination">{{ $orders->links() }}</div>

@endif
