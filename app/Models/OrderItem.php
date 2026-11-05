<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'name', 'sku', 'quantity', 'price', 'returned_quantity'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function returns()
    {
        return $this->hasMany(ProductReturn::class);
    }
}
