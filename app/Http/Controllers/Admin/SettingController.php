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
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'buy_notice' => ['nullable', 'string', 'max:4000'],
            'epay_url' => ['nullable', 'string', 'max:255'],
            'epay_pid' => ['nullable', 'string', 'max:64'],
            'epay_key' => ['nullable', 'string', 'max:128'],
        ]);

        Setting::put('buy_notice', $data['buy_notice'] ?? '');
        Setting::put('epay_url', $data['epay_url'] ?? '');
        Setting::put('epay_pid', $data['epay_pid'] ?? '');
        Setting::put('epay_key', $data['epay_key'] ?? '');

        return redirect('/admin/settings')->with('status', '设置已保存');
    }
}
