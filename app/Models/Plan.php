<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name', 'price', 'period', 'transfer_gb', 'class',
        'speed_limit', 'ip_limit', 'duration_days', 'sort', 'on_sale', 'stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'on_sale' => 'boolean',
    ];
}
