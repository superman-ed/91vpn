<?php

namespace App\Http\Controllers;

use App\Models\ClientDownload;
use App\Models\Node;
use App\Services\PlanCatalog;

/**
 * 官网首页(门户)。游客看营销落地页,已登录进用户中心。
 *
 * [!] 落地页的价格/地区/下载【全部读真实数据】,不写死 —— 营销页和后台各说各话
 *   (页面写 ¥9、套餐改成 ¥12)是最容易伤信任的坑。
 *   · 价格 = 在售套餐(Plan::on_sale,排除加油包)
 *   · 下载 = 已启用客户端(ClientDownload::visible)
 *   · 地区 = 从启用节点名里提取(nodes 没有结构化地区字段,只能按关键词识别;
 *           识别不到时回落到一个静态清单,保证板块不空)
 */
class HomeController extends Controller
{
    /** 节点名关键词 → 规范地区名。nodes 无地区字段,只能按名字识别。 */
    private const REGION_KEYWORDS = [
        '香港' => '香港', 'HongKong' => '香港', 'Hong Kong' => '香港',
        '台湾' => '台湾', '台北' => '台湾',
        '日本' => '日本', '东京' => '日本', '大阪' => '日本',
        '新加坡' => '新加坡', '狮城' => '新加坡',
        '美国' => '美国', '洛杉矶' => '美国', '硅谷' => '美国', '圣何塞' => '美国',
        '韩国' => '韩国', '首尔' => '韩国',
        '英国' => '英国', '伦敦' => '英国', '德国' => '德国', '法国' => '法国',
        '加拿大' => '加拿大', '澳大利亚' => '澳大利亚', '澳洲' => '澳大利亚', '悉尼' => '澳大利亚',
        '越南' => '越南', '泰国' => '泰国', '马来' => '马来西亚', '菲律宾' => '菲律宾',
        '印尼' => '印尼', '印度' => '印度', '意大利' => '意大利', '西班牙' => '西班牙',
        '荷兰' => '荷兰', '土耳其' => '土耳其', '巴西' => '巴西', '阿根廷' => '阿根廷',
        '智利' => '智利', '俄罗斯' => '俄罗斯', '迪拜' => '阿联酋', '阿联酋' => '阿联酋',
    ];

    // `[!!]` 曾经这里有一个 FALLBACK_REGIONS = ['香港','日本','新加坡','美国','台湾','韩国'],
    //   在识别不到任何地区时顶上去,理由是"保证板块不空"。那是【编数据】——
    //   一台节点都没有的时候页面照样宣称覆盖 6 个地区。
    //   现在改成:识别不到就返回空,由模板把整个板块隐掉(不空着也不编)。

    public function __construct(private PlanCatalog $catalog) {}

    public function index()
    {
        if (auth()->check()) {
            return redirect('/user');
        }

        // 落地页只在官网域显示;非官网域的网页请求由 WebOnOfficialHost 中间件跳回官网,不会走到这。
        $regions = $this->regionsFromNodes();

        return view('landing', [
            // 套餐同源于 PlanCatalog:与用户商店同样的"同名归组 + 时长切换"结构
            'groups' => $this->catalog->groups(),
            'catalog' => $this->catalog,
            'downloads' => ClientDownload::visible()->get(),
            'regions' => $regions,
            'regionCount' => count($regions),
            // `[!]` 与 regionsFromNodes 同口径:没上报过的节点不算数
            'nodeCount' => Node::where('enabled', true)->where('last_heartbeat', '>', 0)->count(),
        ]);
    }

    /** @return array<int,string> 去重后的地区名 */
    private function regionsFromNodes(): array
    {
        $found = [];
        // `[!!]` 必须同时要求【曾经上报过】(last_heartbeat > 0)。只筛 enabled 时,
        //   在后台建一个节点就能让这里多出一个地区 —— 2026-09-24 实际发生:
        //   19 个 server 指向 .placeholder.invalid 的占位节点把这里从 1 个
        //   吹到了 13 个,而其中 12 个地区根本不存在。
        // `[!]` 口径是 last_heartbeat 而【不是 online】:真节点的 agent 重启时
        //   online 会短暂变 0,营销页的地区数不该跟着闪。"曾经上报过"才是
        //   "这台机器真实存在"的证据。
        foreach (Node::where('enabled', true)->where('last_heartbeat', '>', 0)->pluck('name') as $name) {
            foreach (self::REGION_KEYWORDS as $kw => $region) {
                if (mb_stripos((string) $name, $kw) !== false) {
                    $found[$region] = true;
                }
            }
        }

        return array_keys($found);
    }
}
