<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings.edit', [
            'buyNotice' => implode("\n", buy_notice_lines()),
            'epayUrl' => setting('epay_url', ''),
            'epayPid' => setting('epay_pid', ''),
            'epayKey' => setting('epay_key', ''),
            'rebateRate' => rebate_rate(),
            'signupBonus' => signup_bonus(),
            'freeTrafficCapGb' => free_traffic_cap_gb(),
            'smtpHost' => setting('smtp_host', ''),
            'smtpPort' => setting('smtp_port', '465'),
            'smtpEncryption' => setting('smtp_encryption', 'ssl'),
            'smtpUsername' => setting('smtp_username', ''),
            'smtpPassword' => setting('smtp_password', ''),
            'smtpFrom' => setting('smtp_from', ''),
            'smtpFromName' => setting('smtp_from_name', '91VPN'),
            'supportTg' => setting('support_tg', ''),
            'supportGroup' => setting('support_group', ''),
            'supportEmail' => setting('support_email', ''),
            'supportQq' => setting('support_qq', ''),
            'supportHours' => setting('support_hours', ''),
            'crispWebsiteId' => setting('crisp_website_id', ''),
            'crispBindIdentity' => setting('crisp_bind_identity', '0') === '1',
            'supportWidget' => setting('support_widget', ''),
            'termsContent' => setting('terms_content', ''),
            'privacyContent' => setting('privacy_content', ''),
            'refundContent' => setting('refund_content', ''),
            'ogImage' => setting('og_image', ''),
            'trackingCode' => setting('tracking_code', ''),
            'apiHosts' => setting('api_hosts', ''),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'buy_notice' => ['nullable', 'string', 'max:4000'],
            'epay_url' => ['nullable', 'string', 'max:255'],
            'epay_pid' => ['nullable', 'string', 'max:64'],
            'epay_key' => ['nullable', 'string', 'max:128'],
            'rebate_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'signup_bonus' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'free_traffic_cap_gb' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', 'in:ssl,tls,none'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_from' => ['nullable', 'string', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:64'],
            'support_tg' => ['nullable', 'string', 'max:255'],
            'support_group' => ['nullable', 'string', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_qq' => ['nullable', 'string', 'max:64'],
            'support_hours' => ['nullable', 'string', 'max:128'],
            'crisp_website_id' => ['nullable', 'string', 'regex:/^[0-9a-f-]{36}$/i', 'max:36'],
            'support_widget' => ['nullable', 'string', 'max:8000'],
            'terms_content' => ['nullable', 'string', 'max:20000'],
            'privacy_content' => ['nullable', 'string', 'max:20000'],
            'refund_content' => ['nullable', 'string', 'max:20000'],
            'og_image' => ['nullable', 'url', 'max:500'],
            'tracking_code' => ['nullable', 'string', 'max:20000'],
            'api_hosts' => ['nullable', 'string', 'max:4000'],
        ]);

        // `[!!]` 两项【原样注入到公开页的脚本】只允许超级管理员改。
        //
        //   tracking_code   注入 落地页/登录页/注册页/帮助/条款
        //   support_widget  注入 登录页/注册页
        //   [D] 2026-09-24 实测过注入范围,两者都覆盖【登录页】。
        //
        // `[!!]` 而这两项原本只要 system.manage —— `infra`(运维) 角色就有。
        //   能在登录页跑任意 JS = 能捕获任何登录者的口令,【包括超级管理员】。
        //   也就是 infra 实际上等价于 super。
        //   这违反 AdminAccess 自己写下的原则:"能在界面上改权限的人,就能给自己
        //   加权限 —— 那么角色划分只剩装饰"。能在认证页注入 JS 与之等价。
        //
        // `[!]` 只在【值真的变了】时才拦:非超管保存其它设置时,表单会把这两项的
        //   原值一起提交,那不该被拒。做法与 UserController 的 original_money 同源。
        $isSuper = (auth()->user()->admin_role ?? null) === 'super';
        if (! $isSuper) {
            $blocked = [];
            foreach (['tracking_code' => '统计/追踪代码', 'support_widget' => '客服挂件代码'] as $key => $label) {
                if ((string) ($data[$key] ?? '') !== (string) setting($key, '')) {
                    $blocked[$key] = "「{$label}」只有超级管理员能改 —— 它是原样注入到登录页的脚本，"
                        .'能改它就等于能拿到任何人的登录口令';
                }
            }
            if ($blocked !== []) {
                return back()->withErrors($blocked)->withInput();
            }
            // 没改动 → 沿用原值，不让下面的 put 把它覆盖成表单里可能被篡改的内容
            $data['tracking_code'] = setting('tracking_code', '');
            $data['support_widget'] = setting('support_widget', '');
        }

        Setting::put('buy_notice', $data['buy_notice'] ?? '');
        Setting::put('epay_url', $data['epay_url'] ?? '');
        Setting::put('epay_pid', $data['epay_pid'] ?? '');
        Setting::put('epay_key', $data['epay_key'] ?? '');
        Setting::put('rebate_rate', (string) ($data['rebate_rate'] ?? '2.5'));
        Setting::put('signup_bonus', (string) ($data['signup_bonus'] ?? '1'));
        Setting::put('free_traffic_cap_gb', (string) ($data['free_traffic_cap_gb'] ?? '2'));
        Setting::put('smtp_host', $data['smtp_host'] ?? '');
        Setting::put('smtp_port', (string) ($data['smtp_port'] ?? '465'));
        Setting::put('smtp_encryption', ($data['smtp_encryption'] ?? 'ssl') === 'none' ? '' : ($data['smtp_encryption'] ?? 'ssl'));
        Setting::put('smtp_username', $data['smtp_username'] ?? '');
        Setting::put('smtp_password', $data['smtp_password'] ?? '');
        Setting::put('smtp_from', $data['smtp_from'] ?? '');
        Setting::put('smtp_from_name', $data['smtp_from_name'] ?? '91VPN');
        Setting::put('support_tg', $data['support_tg'] ?? '');
        Setting::put('support_group', $data['support_group'] ?? '');
        Setting::put('support_email', $data['support_email'] ?? '');
        Setting::put('support_qq', $data['support_qq'] ?? '');
        Setting::put('support_hours', $data['support_hours'] ?? '');
        Setting::put('crisp_website_id', $data['crisp_website_id'] ?? '');
        Setting::put('crisp_bind_identity', $request->boolean('crisp_bind_identity') ? '1' : '0');
        Setting::put('support_widget', $data['support_widget'] ?? '');
        Setting::put('terms_content', $data['terms_content'] ?? '');
        Setting::put('privacy_content', $data['privacy_content'] ?? '');
        Setting::put('refund_content', $data['refund_content'] ?? '');
        Setting::put('og_image', $data['og_image'] ?? '');
        Setting::put('tracking_code', $data['tracking_code'] ?? '');
        Setting::put('api_hosts', $data['api_hosts'] ?? '');

        audit('setting.update', '更新站点设置');

        return redirect('/admin/settings')->with('status', '设置已保存');
    }

    /** POST /admin/settings/test-email —— 用当前 SMTP 配置试发一封,验证连通性 */
    public function testEmail(Request $request, \App\Services\EmailCodeService $emailCode)
    {
        $data = $request->validate(['test_email' => ['required', 'email']]);

        if (! smtp_configured()) {
            return back()->with('status', '⚠ 尚未配置 SMTP,请先填写并保存邮件设置。');
        }
        try {
            $emailCode->sendTest($data['test_email']);
            audit('setting.test-email', "发送 SMTP 测试邮件至 {$data['test_email']}");

            return back()->with('status', "✅ 测试邮件已发送至 {$data['test_email']},请查收(含垃圾箱)。");
        } catch (\Throwable $e) {
            return back()->with('status', '❌ 发送失败：'.\Illuminate\Support\Str::limit($e->getMessage(), 160));
        }
    }

    /** POST /admin/settings/test-gateway —— 易支付网关配置自检 */
    public function testGateway(\App\Services\EpayService $epay)
    {
        if (! $epay->configured()) {
            return back()->with('status', '⚠ 网关未配置完整,请填写 网关地址 / 商户PID / 商户密钥 后保存。');
        }

        $host = parse_url($epay->url(), PHP_URL_HOST);
        $reachable = false;
        try {
            // 只探连通性,不发起真实交易
            $reachable = \Illuminate\Support\Facades\Http::timeout(8)->get($epay->url())->status() > 0;
        } catch (\Throwable $e) {
            $reachable = false;
        }

        return back()->with('status', $reachable
            ? "✅ 网关配置完整,{$host} 可访问(PID：{$epay->pid()})。真实交易结果仍以回调为准。"
            : "⚠ 网关参数已配置,但 {$host} 当前无法访问,请检查网关地址或服务器出网。");
    }
}
