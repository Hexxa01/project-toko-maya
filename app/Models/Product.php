<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['category_id', 'sku', 'name', 'description', 'price', 'stock', 'minimum_stock', 'weight', 'unit', 'brand', 'material', 'specification', 'warranty', 'image', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/'.$this->image) : asset('images/tool.svg');
    }
}
