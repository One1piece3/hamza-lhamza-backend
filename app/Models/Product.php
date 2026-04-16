<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'image',
        'category',
        'stock',
        'size',
        'color',
        'is_featured',
    ];

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
}