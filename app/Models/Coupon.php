<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = ['code', 'note', 'type', 'value', 'max_use', 'used', 'expires_at', 'enabled', 'show_on_checkout'];

    protected $casts = ['value' => 'decimal:2', 'expires_at' => 'datetime', 'enabled' => 'boolean', 'show_on_checkout' => 'boolean'];

    /** 收银台可展示的券：启用 + 勾选展示 + 有文案 + 当前可用 */
    public static function checkoutVisible()
    {
        return static::query()
            ->where('enabled', true)
            ->where('show_on_checkout', true)
            ->whereNotNull('note')
            ->latest()
            ->get()
            ->filter->isUsable()
            ->values();
    }

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
