<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * 自研客户端设备信息上报（框架先行，等自研客户端完成后对接）。
 * 认证：Authorization: Bearer {user.api_token}（不受 Web 端 IP 绑定，与真站客户端一致）。
 */
class DeviceController extends Controller
{
    /** POST /api/device/report */
    public function report(Request $request)
    {
        $token = $this->bearer($request);
        $user = $token ? User::where('api_token', $token)->first() : null;
        if (! $user) {
            return response()->json(['ret' => 0, 'msg' => 'unauthorized'], 401);
        }

        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:128'],
            'platform' => ['nullable', 'string', 'max:16'],
            'brand' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:128'],
            'os_version' => ['nullable', 'string', 'max:32'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        $device = Device::updateOrCreate(
            ['user_id' => $user->id, 'device_id' => $data['device_id']],
            [
                'platform' => strtolower($data['platform'] ?? ''),
                'brand' => $data['brand'] ?? '',
                'model' => $data['model'] ?? '',
                'os_version' => $data['os_version'] ?? '',
                'app_version' => $data['app_version'] ?? '',
                'ip' => $request->ip() ?? '',
                'last_seen' => now(),
            ],
        );

        return response()->json(['ret' => 1, 'device' => $device->id]);
    }

    private function bearer(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');
        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }

        return $request->input('token') ?: null;   // 兼容 body 传 token
    }
}
