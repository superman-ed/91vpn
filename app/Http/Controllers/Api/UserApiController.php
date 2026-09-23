<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserApiController extends Controller
{
    /** GET /api/user —— 客户端拉取当前用户信息(首页/我的用) */
    public function show(Request $request)
    {
        return response()->json(['ret' => 1, 'data' => self::payload($request->user())]);
    }

    /** 统一的用户信息序列化(登录与 /api/user 共用) */
    public static function payload(User $u): array
    {
        $used = (int) $u->u + (int) $u->d;

        return [
            'id' => $u->id,
            'username' => $u->username,
            'email' => $u->email,
            'name' => $u->name,
            // 等级 / 到期
            'class' => (int) $u->class,
            'class_name' => class_name($u->class),
            'class_expire' => $u->class_expire?->toDateTimeString(),
            'is_active' => $u->isActive(),
            // 流量(字节)
            'transfer_enable' => (int) $u->transfer_enable,
            'transfer_used' => $used,
            'transfer_remaining' => max(0, (int) $u->transfer_enable - $used),
            'traffic_exhausted' => $u->isTrafficExhausted(),
            // 余额
            'money' => (float) $u->money,
            // 订阅/邀请凭证
            'uuid' => $u->uuid,
            'sub_token' => $u->invite_token,   // 客户端用它拼订阅 URL: /sub/{sub_token}
            'ref_code' => $u->ref_code,
            // 设备:限额已按设备(device_id),故客户端这个数用已注册设备数(与「我的设备」列表同口径),
            // 不用 onlineDevices() 的在线 IP —— 否则未连节点时恒为 0、与限额/设备列表对不上。
            // (web 仪表盘/admin 仍用 onlineDevices() 的在线 IP 口径,各自不同视角,互不影响。)
            'device_limit' => (int) $u->node_ip_limit,   // 0 = 不限
            'online_devices' => $u->devices()->count(),
        ];
    }
}
