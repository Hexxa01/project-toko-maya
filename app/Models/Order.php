<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['snap_token', 'shipping_zone', 'postal_code', 'verified_at', 'verified_by', 'request_key', 'code', 'user_id', 'cashier_id', 'channel', 'fulfillment', 'status', 'payment_status', 'payment_method', 'subtotal', 'shipping_cost', 'total', 'cash_received', 'recipient', 'phone', 'address', 'courier', 'tracking_number', 'payment_url', 'payment_reference', 'stock_released', 'expires_at', 'paid_at', 'completed_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'expires_at' => 'datetime', 'paid_at' => 'datetime', 'completed_at' => 'datetime', 'stock_released' => 'boolean'];
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function getStatusLabelAttribute()
    {
        return ['awaiting_payment' => 'Menunggu pembayaran', 'processing' => 'Disiapkan gudang', 'ready' => 'Siap diambil', 'shipped' => 'Dikirim', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'][$this->status] ?? $this->status;
    }
}
