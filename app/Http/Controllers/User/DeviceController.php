<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AliveIp;
use App\Support\GeoIp;

class DeviceController extends Controller
{
    /**
     * GET /user/devices —— 两块内容，刻意分开：
     *
     *   ① 我的设备   来自 devices 表（客户端上报的 device_id）——【套餐上限管的是这个】
     *   ② 最近连接的 IP  来自 alive_ips（节点上报）—— 只用于发现陌生归属地
     *
     * `[!!]` 此前这一页只有 ②，标题却写「在线设备」、旁边还挂着「设备上限」——
     * 而那个上限自 e379f3a 起管的是 ①。两个不同的东西被显示成同一个：
     * 用户看到"在线 2 台 / 上限 3 台"，以为还能再加一台，实际额度算的是设备数。
     * `[D]` 一台手机 Wi-Fi 切蜂窝会在 ② 里留下两行，而在 ① 里始终是一台。
     *
     * `[!]` ② 保留而不是删掉：不带 device_id 的第三方客户端在 ① 里【不会出现】
     * （iOS/macOS 在自研客户端上线前都是这类）。只留 ① 的话，那些用户打开这一页
     * 会看到空白 —— 比看到 IP 列表更糟。
     */
    public function index()
    {
        $user = auth()->user();

        $devices = $user->devices()
            ->orderByDesc('last_seen')
            ->get()
            ->map(fn ($d) => [
                'name' => $this->label($d),
                'platform' => $d->platform ?: '—',
                'app_version' => $d->app_version ?: '—',
                'ip' => $d->ip,
                'location' => $d->ip ? (GeoIp::locate($d->ip) ?: '未知归属地') : '—',
                'last_seen' => $d->last_seen,
            ]);

        $window = now()->subSeconds(AliveIp::ONLINE_WINDOW);
        $ips = $user->aliveIps()
            ->where('last_seen', '>=', $window)
            ->with('node')
            ->orderByDesc('last_seen')
            ->get()
            ->map(fn ($a) => [
                'ip' => $a->ip,
                'location' => GeoIp::locate($a->ip) ?: '未知归属地',
                'node' => $a->node?->name ?? '—',
                'last_seen' => $a->last_seen,
            ]);

        return view('user.devices', [
            'devices' => $devices,
            'ips' => $ips,
            'limit' => (int) $user->node_ip_limit,   // 0 = 不限
        ]);
    }

    /** 设备显示名：优先"品牌 型号"，退回平台，再退回设备号前 8 位。 */
    private function label($d): string
    {
        $parts = array_filter([$d->brand, $d->model]);
        if ($parts) {
            return implode(' ', $parts);
        }

        return $d->platform ?: ('设备 '.mb_substr((string) $d->device_id, 0, 8));
    }
}
