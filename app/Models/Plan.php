<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name', 'price', 'period', 'transfer_gb', 'reset_type', 'is_data_pack', 'class',
        'speed_limit', 'ip_limit', 'duration_days', 'sort', 'on_sale', 'stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'on_sale' => 'boolean',
        'is_data_pack' => 'boolean',
    ];

    /** 是否按月(每30天)重置流量；none 为总量型不重置 */
    public function resetsMonthly(): bool
    {
        return $this->reset_type !== 'none';
    }
}
