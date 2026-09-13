<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'plan_id', 'coupon_id', 'order_no', 'trade_no', 'amount', 'status', 'period', 'pay_method', 'refunded_at', 'refund_amount', 'refund_reason',
        'paid_at', 'activate_at', 'delivered_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_no)) {
                $order->order_no = now()->format('YmdHis').str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            }
        });
    }

    protected $casts = [
        'refunded_at' => 'datetime',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'activate_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
