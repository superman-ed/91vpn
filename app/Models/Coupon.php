<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = ['code', 'type', 'value', 'max_use', 'used', 'expires_at', 'enabled'];

    protected $casts = ['value' => 'decimal:2', 'expires_at' => 'datetime', 'enabled' => 'boolean'];

    /** 是否可用 */
    public function isUsable(): bool
    {
        if (! $this->enabled) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->max_use >= 0 && $this->used >= $this->max_use) {
            return false;
        }
        return true;
    }

    /** 对原价计算折后价（不低于0） */
    public function apply(float $amount): float
    {
        $discounted = $this->type === 'percent'
            ? $amount * (1 - $this->value / 100)
            : $amount - (float) $this->value;

        return max(0, round($discounted, 2));
    }
}
