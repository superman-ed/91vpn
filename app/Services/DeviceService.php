<?php

namespace App\Services;

use App\Exceptions\DeviceLimitException;
use App\Models\Device;
use App\Models\User;

/**
 * 按设备(device_id)统计并卡套餐设备数上限 —— 应用层控制(仅对携带 device_id 的自研客户端生效)。
 * 判据是客户端上报的 device_id,不是 IP:多设备共出口 IP 也各自算清,单设备换 IP 也仍算一台。
 *
 * 注意:这是应用层限制,不是协议层。绕开官方客户端(把订阅导入第三方客户端)不带 device_id 时
 * 无法在此统计,那部分仍由节点侧在线 IP 计数兜底。真正防绕过需按设备发 UUID(另一件事)。
 */
class DeviceService
{
    /** 超过此天数未上报的设备视为陈旧,新设备入场时可自动回收其名额(解重装/换机后的锁死) */
    public const STALE_DAYS = 15;

    /**
     * 设备准入:已知设备刷新放行;未满额则登记新设备;满额则尝试回收陈旧设备,否则抛 DeviceLimitException。
     *
     * @param  array<string,mixed>  $meta  platform/brand/model/os_version/app_version/ip(空值不覆盖已有)
     */
    public function admit(User $user, string $deviceId, array $meta = []): Device
    {
        $clean = $this->cleanMeta($meta);

        $existing = $user->devices()->where('device_id', $deviceId)->first();
        if ($existing) {
            $existing->update($clean + ['last_seen' => now()]);

            return $existing;
        }

        $limit = (int) $user->node_ip_limit;   // 0 = 不限
        if ($limit > 0 && $user->devices()->count() >= $limit) {
            // 满额:回收最久没动静、且已超陈旧窗口的一台;没有可回收的才真正拒绝
            $stale = $user->devices()
                ->where('last_seen', '<', now()->subDays(self::STALE_DAYS))
                ->orderBy('last_seen')
                ->first();

            if (! $stale) {
                throw new DeviceLimitException($limit);
            }
            $stale->delete();
        }

        return $user->devices()->create(['device_id' => $deviceId] + $clean + ['last_seen' => now()]);
    }

    /** 只保留允许写入且非空的字段;platform 归一化小写(空值不覆盖已有 meta) */
    private function cleanMeta(array $meta): array
    {
        $out = [];
        foreach (['platform', 'brand', 'model', 'os_version', 'app_version', 'ip'] as $k) {
            $v = $meta[$k] ?? null;
            if ($v !== null && $v !== '') {
                $out[$k] = $k === 'platform' ? strtolower((string) $v) : (string) $v;
            }
        }

        return $out;
    }
}
