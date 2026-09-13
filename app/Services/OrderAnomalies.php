<?php

namespace App\Services;

use App\Models\Order;

/**
 * 订单异常：钱和货对不上的几种形态。
 *
 * `[!!]` 判据取自【真实流程不会产生的组合】，不是泛泛的"已付未发货"。
 * 读 BillingService 得到的不变量：
 *
 *     status=paid    ⇒ 一定有 delivered_at
 *     status=queued  ⇒ 一定有 activate_at，且到点后由定时任务转成 paid
 *     有 paid_at     ⇒ status 不可能还是 pending（同一个事务里写的）
 *
 * 所以每一条告警都对应一个【被打破的不变量】，而不是一个看起来可疑的状态。
 * 排队中的订单不是异常 —— 当前套餐没到期就该排队，把它算进去等于天天误报。
 *
 * `[!]` 2026-09-13 差点在这里报出一个假故障：库里有 49 条 status=paid 而
 * delivered_at 为空的订单，看着像"钱扣了没到账"。查下来全是 mock 直接插库的
 * （有 paid_at 却没有 activate_at —— 真实流程绝不会产生这种组合），
 * 走过 completeOrder 的那 3 条都正常。**先查数据来源，再下结论。**
 */
class OrderAnomalies
{
    /** 排队订单过了激活时刻多久算卡住。定时任务每 10 分钟跑一次，给足余量。 */
    private const STUCK_GRACE_MIN = 30;

    /**
     * @return array<int,array{key:string,level:string,count:int,title:string,detail:string,link:string}>
     */
    public function check(): array
    {
        $out = [];

        // 一、status=paid 却没有 delivered_at —— 不变量被打破。
        // 正常流程里这两者在同一个 update 里写,出现就说明有人绕过了 BillingService。
        if ($n = Order::where('status', 'paid')->whereNull('delivered_at')->count()) {
            $out[] = $this->x('paid_undelivered', 'bad', $n, '已付款但没有发货记录',
                '正常流程里「标记已付」和「发货时间」是同一次写入 —— '
                .'出现这种订单说明有人绕过了结算服务（直接改库、或旧数据）。'
                .'先确认这些用户的套餐到底生效了没有，再决定补发还是只补记录。');
        }

        // 二、排队订单过了激活时刻还没转正 —— 激活任务没跑或跑失败了。
        // `[!]` 这一条是最可能真实发生的:orders:activate-due 每 10 分钟一次,
        // 它静默失败的话,用户会在套餐到期后【突然没得用】,而订单显示一切正常。
        $stuck = Order::where('status', 'queued')
            ->whereNotNull('activate_at')
            ->where('activate_at', '<', now()->subMinutes(self::STUCK_GRACE_MIN))
            ->count();
        if ($stuck) {
            $out[] = $this->x('queued_stuck', 'bad', $stuck, '排队订单过了激活时刻还没生效',
                '这些订单该在 '.self::STUCK_GRACE_MIN.' 分钟前就自动转正了。'
                .'多半是 orders:activate-due 没在跑 —— 用户会在上一个套餐到期后突然没得用，'
                .'而订单页显示一切正常。先看定时任务，再手动激活。');
        }

        // 三、记了付款却仍是待支付 —— 同一个事务里写的两个字段不一致。
        if ($n = Order::where('status', 'pending')->whereNotNull('paid_at')->count()) {
            $out[] = $this->x('pending_with_paid_at', 'bad', $n, '记了付款时间却仍是待支付',
                '这两个字段在同一个事务里写，不该不一致。出现即说明有写入绕过了事务。');
        }

        // 四、长期待支付 —— 不是不变量被破坏,是【可能网关已扣款而回调丢了】。
        // 对账任务会主动查单,这里只是让人看得见数量。
        $old = Order::where('status', 'pending')->where('created_at', '<', now()->subDay())->count();
        if ($old) {
            $out[] = $this->x('stale_pending', 'warn', $old, '超过一天仍未支付',
                '多数是用户放弃了。但网关已扣款而回调丢失也长这样 —— '
                .'payment:reconcile 每 5 分钟会主动查单补发货，这里只是让数量可见。');
        }

        return $out;
    }

    private function x(string $key, string $level, int $count, string $title, string $detail): array
    {
        return [
            'key' => $key, 'level' => $level, 'count' => $count,
            'title' => $title, 'detail' => $detail,
            'link' => '/admin/orders?anomaly='.$key,
        ];
    }

    /** 按 key 取出对应的订单查询，供订单页筛选。 */
    public static function scope(string $key): ?\Illuminate\Database\Eloquent\Builder
    {
        return match ($key) {
            'paid_undelivered' => Order::where('status', 'paid')->whereNull('delivered_at'),
            'queued_stuck' => Order::where('status', 'queued')->whereNotNull('activate_at')
                ->where('activate_at', '<', now()->subMinutes(self::STUCK_GRACE_MIN)),
            'pending_with_paid_at' => Order::where('status', 'pending')->whereNotNull('paid_at'),
            'stale_pending' => Order::where('status', 'pending')->where('created_at', '<', now()->subDay()),
            default => null,
        };
    }
}
