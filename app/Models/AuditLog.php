<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /**
     * 由【定时任务】写入的动作。action => 给人看的名字。
     *
     * `[!!]` 存在的理由有两个，缺一不可：
     *
     *  1. 渲染时要分得清 admin_id=null 的两种来源 —— 自动任务，和
     *     【管理员被删掉】。此前一律显示「系统」，而当时根本没有自动任务写审计，
     *     所以那个标签实际在说的是后者，字面意思却相反。
     *
     *  2. 动作名沿用 user./order. 前缀（而不是另起一个 system. 组），
     *     这样按"用户"或"订单"筛的时候，人工与自动的记录会【排在同一条时间线上】。
     *     出事时要看的就是这条线，不是两张表。人工/自动的区分交给 ?src= 筛选。
     *
     * 新增动作必须登记在这里 —— system_audit() 对未登记的动作直接抛错，
     * 同 AdminAccess::capFor()。漏登记会让它在页面上显示成"管理员被删了"。
     */
    public const SYSTEM_ACTIONS = [
        'user.traffic_reset' => '流量重置',
        'order.auto_activate' => '自动发货',
        'order.auto_cancel' => '自动关单',
        'order.reconciled' => '对账补发',
        'recharge.reconciled' => '充值补到账',
    ];

    /** 这条记录是不是自动任务产生的（而不是"操作人已被删除"）。 */
    public function isSystem(): bool
    {
        return $this->admin_id === null && array_key_exists($this->action, self::SYSTEM_ACTIONS);
    }

    protected $fillable = ['admin_id', 'action', 'description', 'target_type', 'target_id', 'ip'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
