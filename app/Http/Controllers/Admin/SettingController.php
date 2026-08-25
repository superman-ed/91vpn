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
            'smtpHost' => setting('smtp_host', ''),
            'smtpPort' => setting('smtp_port', '465'),
            'smtpEncryption' => setting('smtp_encryption', 'ssl'),
            'smtpUsername' => setting('smtp_username', ''),
            'smtpPassword' => setting('smtp_password', ''),
            'smtpFrom' => setting('smtp_from', ''),
            'smtpFromName' => setting('smtp_from_name', '91VPN'),
            'supportTg' => setting('support_tg', ''),
            'supportGroup' => setting('support_group', ''),
            'supportHours' => setting('support_hours', ''),
            'crispWebsiteId' => setting('crisp_website_id', ''),
            'crispBindIdentity' => setting('crisp_bind_identity', '0') === '1',
            'supportWidget' => setting('support_widget', ''),
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
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', 'in:ssl,tls,none'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_from' => ['nullable', 'string', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:64'],
            'support_tg' => ['nullable', 'string', 'max:255'],
            'support_group' => ['nullable', 'string', 'max:255'],
            'support_hours' => ['nullable', 'string', 'max:128'],
            'crisp_website_id' => ['nullable', 'string', 'regex:/^[0-9a-f-]{36}$/i', 'max:36'],
            'support_widget' => ['nullable', 'string', 'max:8000'],
        ]);

        Setting::put('buy_notice', $data['buy_notice'] ?? '');
        Setting::put('epay_url', $data['epay_url'] ?? '');
        Setting::put('epay_pid', $data['epay_pid'] ?? '');
        Setting::put('epay_key', $data['epay_key'] ?? '');
        Setting::put('rebate_rate', (string) ($data['rebate_rate'] ?? '2.5'));
        Setting::put('signup_bonus', (string) ($data['signup_bonus'] ?? '1'));
        Setting::put('smtp_host', $data['smtp_host'] ?? '');
        Setting::put('smtp_port', (string) ($data['smtp_port'] ?? '465'));
        Setting::put('smtp_encryption', ($data['smtp_encryption'] ?? 'ssl') === 'none' ? '' : ($data['smtp_encryption'] ?? 'ssl'));
        Setting::put('smtp_username', $data['smtp_username'] ?? '');
        Setting::put('smtp_password', $data['smtp_password'] ?? '');
        Setting::put('smtp_from', $data['smtp_from'] ?? '');
        Setting::put('smtp_from_name', $data['smtp_from_name'] ?? '91VPN');
        Setting::put('support_tg', $data['support_tg'] ?? '');
        Setting::put('support_group', $data['support_group'] ?? '');
        Setting::put('support_hours', $data['support_hours'] ?? '');
        Setting::put('crisp_website_id', $data['crisp_website_id'] ?? '');
        Setting::put('crisp_bind_identity', $request->boolean('crisp_bind_identity') ? '1' : '0');
        Setting::put('support_widget', $data['support_widget'] ?? '');

        return redirect('/admin/settings')->with('status', '设置已保存');
    }
}
