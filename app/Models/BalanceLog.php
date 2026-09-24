<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceLog extends Model
{
    /**
     * 流水类型 → 中文名。**全项目唯一来源。**
     *
     * `[!!]` 2026-09-24 加 refund 类型时踩过一次:改了财务页的 Blade,却漏掉
     * FinanceController 里【另一份一模一样的清单】—— 结果是新类型在筛选里被
     * 无视(in_array 不中就当没筛)、在导出里显示成原始英文 refund、计数里没有它。
     * 而这三个症状都【不报错】,只是安静地不对。
     *
     * 所以清单放这里一份,控制器与视图都引它。加类型时只改这一处。
     */
    public const TYPE_NAME = [
        'recharge' => '充值',
        'consume' => '消费',
        'rebate' => '返佣',
        'bonus' => '注册奖励',
        'adjust' => '调账',
        'refund' => '退款',
    ];

    /** 可筛选的类型键。 */
    public static function types(): array
    {
        return array_keys(self::TYPE_NAME);
    }

    protected $fillable = ['user_id', 'amount', 'type', 'order_id', 'trade_no', 'balance_after', 'remark'];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
