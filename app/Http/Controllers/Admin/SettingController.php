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
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'buy_notice' => ['nullable', 'string', 'max:4000'],
        ]);

        Setting::put('buy_notice', $data['buy_notice'] ?? '');

        return redirect('/admin/settings')->with('status', '设置已保存');
    }
}
