<?php

namespace App\Support;

use App\Models\User;

class ClientLinks
{
    /**
     * 构造某用户的订阅链接、各客户端导入 scheme/URL 与二维码、各格式链接。
     *
     * @return array{subUrl:string, clashScheme:string, clients:array, formatLinks:array}
     */
    public static function for(User $user): array
    {
        $subUrl = url('/sub/'.$user->invite_token);
        $enc = urlencode($subUrl);

        $clients = [
            [
                'key' => 'clash', 'name' => 'Clash / Verge', 'icon' => 'fas fa-bolt',
                'scheme' => 'clash://install-config?url='.$enc.'&name=91VPN',
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
                'scheme' => 'sing-box://import-remote-profile?url='.$enc.'#91VPN',
                'qr_target' => 'scheme', 'tip' => '扫码/点按钮自动导入',
            ],
            [
                'key' => 'v2rayng', 'name' => 'V2rayNG（安卓）', 'icon' => 'fab fa-android',
                'scheme' => null, 'url' => $subUrl.'?flag=v2ray',
                'qr_target' => 'url', 'tip' => '在「订阅设置→扫码添加」里扫',
            ],
        ];

        foreach ($clients as &$c) {
            $c['qr'] = QrCode::dataUri($c['qr_target'] === 'scheme' ? $c['scheme'] : $c['url']);
        }
        unset($c);

        $formatLinks = [
            ['name' => 'Clash 格式（YAML）', 'url' => $subUrl.'?flag=clash'],
            ['name' => 'V2rayN 格式（base64）', 'url' => $subUrl.'?flag=v2ray'],
            ['name' => '通用 base64（多客户端可读）', 'url' => $subUrl.'?flag=sub'],
            ['name' => '自动识别（按客户端返回对应格式）', 'url' => $subUrl],
        ];

        return [
            'subUrl' => $subUrl,
            'clashScheme' => 'clash://install-config?url='.$enc.'&name=91VPN',
            'clients' => $clients,
            'formatLinks' => $formatLinks,
        ];
    }
}
