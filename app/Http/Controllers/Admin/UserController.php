<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q');
        $users = User::when($q, fn ($query) => $query->where('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"))
            ->orderByDesc('id')->paginate(30)->withQueryString();

        return view('admin.users.index', ['users' => $users, 'q' => $q]);
    }

    public function edit(User $user)
    {
        return view('admin.users.form', ['user' => $user]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'class' => ['required', 'integer', 'min:0', 'max:9'],
            'transfer_enable_gb' => ['required', 'numeric', 'min:0'],
            'class_expire' => ['nullable', 'date'],
            'node_speed_limit' => ['nullable', 'integer', 'min:0'],
            'node_ip_limit' => ['nullable', 'integer', 'min:0'],
            'money' => ['nullable', 'numeric'],
        ]);

        $user->update([
            'class' => $data['class'],
            'transfer_enable' => (int) round($data['transfer_enable_gb'] * (1024 ** 3)),
            'class_expire' => $data['class_expire'] ?? $user->class_expire,
            'node_speed_limit' => $data['node_speed_limit'] ?? 0,
            'node_ip_limit' => $data['node_ip_limit'] ?? 0,
            'money' => $data['money'] ?? $user->money,
        ]);

        return redirect('/admin/users')->with('status', "已更新用户 {$user->email}");
    }

    public function toggleBan(User $user)
    {
        $user->update(['banned' => ! $user->banned]);

        return back()->with('status', $user->banned ? '已封禁' : '已解封');
    }
}
