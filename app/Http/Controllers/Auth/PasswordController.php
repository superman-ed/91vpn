<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    private function key(string $email): string
    {
        return 'reset_code:'.strtolower(trim($email));
    }

    /** GET /password/forgot */
    public function forgot()
    {
        return view('auth.forgot');
    }

    /** POST /password/send —— 发送重置验证码 */
    public function send(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        // 无论邮箱是否存在都返回成功（不泄露账号是否注册）
        if (User::where('email', $data['email'])->exists()) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put($this->key($data['email']), $code, now()->addMinutes(15));
            Log::info("[重置密码验证码] {$data['email']} => {$code}（第一版仅记录）");
        }

        return response()->json(['ok' => true, 'message' => '若邮箱已注册，验证码已发送']);
    }

    /** POST /password/reset —— 用验证码重置密码 */
    public function reset(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $stored = Cache::get($this->key($data['email']));
        if ($stored === null || ! hash_equals($stored, $data['code'])) {
            throw ValidationException::withMessages(['code' => '验证码错误或已过期']);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            throw ValidationException::withMessages(['email' => '账号不存在']);
        }

        $user->update(['password' => Hash::make($data['password'])]);
        Cache::forget($this->key($data['email']));

        return redirect('/login')->with('status', '密码已重置，请用新密码登录');
    }
}
