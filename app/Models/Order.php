<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'customer_user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_city',
        'customer_address',
        'customer_note',
        'items',
        'subtotal',
        'delivery_fee',
        'total',
        'status',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }
}
