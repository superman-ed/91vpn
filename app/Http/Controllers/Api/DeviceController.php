<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DeviceLimitException;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\DeviceService;
use Illuminate\Http\Request;

/**
 * 自研客户端设备信息上报（框架先行，等自研客户端完成后对接）。
 * 认证：Authorization: Bearer {user.api_token}（不受 Web 端 IP 绑定，与真站客户端一致）。
 */
class DeviceController extends Controller
{
    /** GET /api/devices —— 当前用户的设备清单(按最后在线倒序);可带 ?device_id= 标记本机 */
    public function index(Request $request)
    {
        $current = (string) $request->query('device_id', '');

        $list = $request->user()->devices()
            ->orderByDesc('last_seen')
            ->get()
            ->map(fn (Device $d) => [
                'id' => $d->id,
                'device_id' => $d->device_id,
                'platform' => $d->platform,
                'brand' => $d->brand,
                'model' => $d->model,
                'os_version' => $d->os_version,
                'app_version' => $d->app_version,
                'last_seen' => $d->last_seen?->toDateTimeString(),
                'is_current' => $current !== '' && $d->device_id === $current,
            ]);

        return response()->json(['ret' => 1, 'data' => $list]);
    }

    /** DELETE /api/devices/{id} —— 下线/移除本账号名下的一台设备(仅能删自己的) */
    public function destroy(Request $request, int $id)
    {
        $deleted = $request->user()->devices()->whereKey($id)->delete();

        return response()->json(['ret' => $deleted ? 1 : 0]);
    }

    /** POST /api/device/report */
    public function report(Request $request, DeviceService $devices)
    {
        $user = $request->user();   // 由 client.token 中间件注入

        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:128'],
            'platform' => ['nullable', 'string', 'max:16'],
            'brand' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:128'],
            'os_version' => ['nullable', 'string', 'max:32'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'promo_code' => ['nullable', 'string', 'max:64'],   // 渠道包首启带上来的推广码
        ]);

        // 渠道包归因回填：用户尚未归因 + 推广码有效时才写入（首次来源不被覆盖）
        if (! empty($data['promo_code']) && empty($user->promo_code)) {
            $code = strtoupper($data['promo_code']);
            if (\App\Models\PromoChannel::where('code', $code)->where('enabled', true)->exists()) {
                $user->update(['promo_code' => $code]);
            }
        }

        // 走统一准入:登记/刷新设备 + 按套餐设备数卡上限(超限且无陈旧设备可回收则拒绝)
        try {
            $device = $devices->admit($user, $data['device_id'], [
                'platform' => $data['platform'] ?? '',
                'brand' => $data['brand'] ?? '',
                'model' => $data['model'] ?? '',
                'os_version' => $data['os_version'] ?? '',
                'app_version' => $data['app_version'] ?? '',
                'ip' => $request->ip() ?? '',
            ]);
        } catch (DeviceLimitException $e) {
            return response()->json(['ret' => 0, 'msg' => $e->getMessage()], 403);
        }

        return response()->json(['ret' => 1, 'device' => $device->id]);
    }
}
