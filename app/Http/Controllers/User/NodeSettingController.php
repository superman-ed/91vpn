<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Support\QrCode;
use Illuminate\Support\Str;

class NodeSettingController extends Controller
{
    /** GET /user/node */
    public function index()
    {
        $user = auth()->user();
        $subUrl = url('/sub/'.$user->invite_token);

        // 各客户端的一键导入 scheme（二维码里装的就是它，扫码即跳 App 自动导入）
        $clients = [
            [
                'key' => 'clash',
                'name' => 'Clash / Verge',
                'icon' => 'fas fa-bolt',
                'scheme' => 'clash://install-config?url='.urlencode($subUrl).'&name=91VPN',
            ],
            [
                'key' => 'shadowrocket',
                'name' => '小火箭 Shadowrocket',
                'icon' => 'fas fa-rocket',
                'scheme' => 'shadowrocket://add/sub://'.base64_encode($subUrl).'?remark=91VPN',
            ],
            [
                'key' => 'sing-box',
                'name' => 'sing-box',
                'icon' => 'fas fa-box',
                'scheme' => 'sing-box://import-remote-profile?url='.urlencode($subUrl).'#91VPN',
            ],
            [
                'key' => 'quantumultx',
                'name' => 'Quantumult X',
                'icon' => 'fas fa-atom',
                'scheme' => 'quantumult-x://add-resource?remote-resource='.urlencode('{"server_remote":["'.$subUrl.', tag=91VPN"]}'),
            ],
        ];

        // 给每个客户端生成"装了 scheme 的二维码"
        foreach ($clients as &$c) {
            $c['qr'] = QrCode::dataUri($c['scheme']);
        }
        unset($c);

        return view('user.node', [
            'user' => $user,
            'subUrl' => $subUrl,
            'clients' => $clients,
        ]);
    }

    /** POST /user/node/reset-sub —— 重置订阅链接 */
    public function resetSub()
    {
        auth()->user()->update(['invite_token' => Str::random(32)]);

        return back()->with('status', '订阅链接已重置，请重新导入客户端（旧链接已失效）');
    }

    /** POST /user/node/reset-passwd —— 重置连接密码（同时换 UUID） */
    public function resetPasswd()
    {
        auth()->user()->update([
            'passwd' => Str::lower(Str::random(6)),
            'uuid' => (string) Str::uuid(),
        ]);

        return back()->with('status', '连接密码与 UUID 已重置，请重新导入订阅');
    }
}
