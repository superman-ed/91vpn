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

        // 各客户端导入方式，每个客户端各用各的（不通用）。
        // qr_target=scheme：二维码/按钮装该客户端专属 scheme，扫码即唤起 App 自动导入。
        // qr_target=url：该客户端无 scheme(如 V2rayNG)，二维码装订阅 URL，在 App"扫码添加订阅"里识别。
        $clients = [
            [
                'key' => 'clash', 'name' => 'Clash / Verge', 'icon' => 'fas fa-bolt',
                'scheme' => 'clash://install-config?url='.urlencode($subUrl).'&name=91VPN',
                'qr_target' => 'scheme', 'tip' => '扫码/点按钮自动导入',
            ],
            [
                'key' => 'shadowrocket', 'name' => '小火箭 Shadowrocket', 'icon' => 'fas fa-rocket',
                'scheme' => 'shadowrocket://add/sub://'.base64_encode($subUrl).'?remark=91VPN',
                'qr_target' => 'scheme', 'tip' => '扫码/点按钮自动导入',
            ],
            [
                'key' => 'quantumultx', 'name' => 'Quantumult X', 'icon' => 'fas fa-atom',
                'scheme' => 'quantumult-x://add-resource?remote-resource='.urlencode('{"server_remote":["'.$subUrl.'?flag=sub, tag=91VPN"]}'),
                'qr_target' => 'scheme', 'tip' => '扫码/点按钮自动导入',
            ],
            [
                'key' => 'sing-box', 'name' => 'sing-box', 'icon' => 'fas fa-box',
                'scheme' => 'sing-box://import-remote-profile?url='.urlencode($subUrl).'#91VPN',
                'qr_target' => 'scheme', 'tip' => '扫码/点按钮自动导入',
            ],
            [
                'key' => 'v2rayng', 'name' => 'V2rayNG（安卓）', 'icon' => 'fab fa-android',
                'scheme' => null,                              // V2rayNG 无 URL scheme
                'url' => $subUrl.'?flag=v2ray',
                'qr_target' => 'url', 'tip' => '在「订阅设置→扫码添加」里扫',
            ],
        ];

        // 二维码内容：有 scheme 装 scheme，无 scheme 装订阅 URL
        foreach ($clients as &$c) {
            $c['qr'] = QrCode::dataUri($c['qr_target'] === 'scheme' ? $c['scheme'] : $c['url']);
        }
        unset($c);

        // 订阅链接（按格式，非按 App；base64 那条多客户端可读，不特指某两个 App）
        $formatLinks = [
            ['name' => 'Clash 格式（YAML）', 'url' => $subUrl.'?flag=clash'],
            ['name' => 'V2rayN 格式（base64）', 'url' => $subUrl.'?flag=v2ray'],
            ['name' => '通用 base64（多客户端可读）', 'url' => $subUrl.'?flag=sub'],
            ['name' => '自动识别（按客户端返回对应格式）', 'url' => $subUrl],
        ];

        return view('user.node', [
            'user' => $user,
            'subUrl' => $subUrl,
            'clients' => $clients,
            'formatLinks' => $formatLinks,
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
