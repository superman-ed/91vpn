<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Node;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    /** 定时任务元数据：signature => [显示名, 预期间隔秒, 频率文案] */
    private const TASKS = [
        'alive-ips:prune' => ['清理过期在线IP', 300, '每5分钟'],
        'payment:reconcile' => ['支付对账', 300, '每5分钟'],
        'orders:activate-due' => ['激活排队订单', 600, '每10分钟'],
        'orders:expire-pending' => ['关闭超时订单', 600, '每10分钟'],
        'stats:snapshot' => ['在线/日活快照', 600, '每10分钟'],
        'traffic:reset-daily' => ['每日流量清零', 86400, '每天00:00'],
        'traffic:reset-monthly' => ['月度流量重置', 86400, '每天00:05'],
        'notify:expiry' => ['到期提醒站内信', 86400, '每天09:00'],
    ];

    /** GET /admin/system/health */
    public function index()
    {
        return view('admin.system.health', [
            'tasks' => $this->tasks(),
            'services' => $this->services(),
            'nodes' => $this->nodes(),
            'env' => $this->env(),
        ]);
    }

    private function tasks(): array
    {
        $rows = [];
        foreach (self::TASKS as $sig => [$name, $interval, $freq]) {
            $hb = Cache::get("task_hb:{$sig}");
            $at = $hb['at'] ?? null;
            $ago = $at ? now()->timestamp - $at : null;
            // 超过预期间隔 2.5 倍视为异常；从未运行=未知
            $status = $at === null ? 'unknown' : (($ago > $interval * 2.5) ? 'bad' : (($hb['ok'] ?? true) ? 'ok' : 'bad'));
            $rows[] = [
                'name' => $name, 'sig' => $sig, 'freq' => $freq,
                'last' => $at ? \Illuminate\Support\Carbon::createFromTimestamp($at) : null,
                'status' => $status,
            ];
        }

        return $rows;
    }

    private function services(): array
    {
        $out = [];

        // MySQL
        $out[] = $this->probe('MySQL 数据库', function () {
            $t = microtime(true);
            DB::select('select 1');

            return round((microtime(true) - $t) * 1000).' 毫秒';
        });

        // Redis
        $out[] = $this->probe('Redis', function () {
            $t = microtime(true);
            Redis::connection()->ping();

            return round((microtime(true) - $t) * 1000).' 毫秒';
        });

        // 队列积压
        $out[] = $this->probe('队列(redis)', function () {
            $len = (int) Redis::connection()->llen('queues:default');

            return $len.' 待处理'.($len > 100 ? ' ⚠ 积压' : '');
        });

        return $out;
    }

    private function probe(string $name, \Closure $fn): array
    {
        try {
            return ['name' => $name, 'ok' => true, 'detail' => $fn()];
        } catch (\Throwable $e) {
            return ['name' => $name, 'ok' => false, 'detail' => \Illuminate\Support\Str::limit($e->getMessage(), 80)];
        }
    }

    /** 节点心跳在线判定窗口（秒） */
    private const NODE_ONLINE_WINDOW = 180;

    private function nodes(): array
    {
        $now = now()->timestamp;

        return Node::orderBy('sort')->get()->map(function ($n) use ($now) {
            $ago = $n->last_heartbeat > 0 ? $now - $n->last_heartbeat : null;

            return [
                'name' => $n->name,
                'last' => $n->last_heartbeat > 0 ? \Illuminate\Support\Carbon::createFromTimestamp($n->last_heartbeat) : null,
                'online' => $ago !== null && $ago < self::NODE_ONLINE_WINDOW,
            ];
        })->all();
    }

    private function env(): array
    {
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());
        $usedPct = ($free && $total) ? round(($total - $free) / $total * 100) : null;

        return [
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'env' => app()->environment(),
            'debug' => config('app.debug') ? '开(⚠上线应关)' : '关',
            'cache' => config('cache.default'),
            'queue' => config('queue.default'),
            'disk_free' => $free ? human_bytes($free) : '—',
            'disk_used_pct' => $usedPct,
        ];
    }
}
