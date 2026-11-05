<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReturn extends Model
{
    protected $fillable = ['code', 'order_item_id', 'user_id', 'quantity', 'reason', 'restock', 'refund_amount', 'refund_status', 'refund_reference'];

    public function item()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    protected function casts(): array
    {
        return ['restock' => 'boolean'];
    }
}
