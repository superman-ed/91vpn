<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmailCodeService
{
    private const TTL_MINUTES = 5;

    private function key(string $email): string
    {
        return 'email_code:'.strtolower(trim($email));
    }

    /**
     * 生成 6 位邮箱验证码，存入缓存并（第一版）打到日志，返回验证码。
     */
    public function send(string $email): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->key($email), $code, now()->addMinutes(self::TTL_MINUTES));
        Log::info("[邮箱验证码] {$email} => {$code}（第一版仅记录，未真发邮件）");
        return $code;
    }

    /**
     * 校验验证码；成功后作废（一次性）。
     */
    public function verify(string $email, ?string $code): bool
    {
        if ($code === null) {
            return false;
        }
        $stored = Cache::get($this->key($email));
        if ($stored !== null && hash_equals($stored, $code)) {
            Cache::forget($this->key($email));
            return true;
        }
        return false;
    }
}
