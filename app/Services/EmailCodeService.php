<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailCodeService
{
    private const TTL_MINUTES = 5;

    private function key(string $email): string
    {
        return 'email_code:'.strtolower(trim($email));
    }

    /**
     * 生成 6 位邮箱验证码，存入缓存并发送。
     * 已配置 SMTP → 真发邮件；否则回退记日志（开发环境）。
     */
    public function send(string $email): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->key($email), $code, now()->addMinutes(self::TTL_MINUTES));

        $subject = '【91VPN】邮箱验证码';
        if (smtp_configured()) {
            try {
                $this->mail($email, $code);
                $this->record($email, $subject, 'sent');
            } catch (\Throwable $e) {
                Log::warning("[邮箱验证码] 发送失败 {$email}: {$e->getMessage()}");
                $this->record($email, $subject, 'failed', $e->getMessage());
            }
        } else {
            Log::info("[邮箱验证码] {$email} => {$code}（未配置 SMTP，仅记录）");
            $this->record($email, $subject, 'logged');
        }

        return $code;
    }

    /** 后台「发送测试邮件」：用当前 SMTP 配置真发一封,失败抛异常由调用方捕获 */
    public function sendTest(string $to): void
    {
        $this->configureMailer();
        $from = setting('smtp_from') ?: setting('smtp_username');
        $fromName = setting('smtp_from_name', '91VPN');
        $body = "这是一封来自 91VPN 后台的测试邮件。\n\n若你收到它,说明当前 SMTP 配置可以正常发信。\n发送时间：".now()->format('Y-m-d H:i:s');

        try {
            Mail::mailer('smtp')->raw($body, function ($m) use ($to, $from, $fromName) {
                $m->to($to)->from($from, $fromName)->subject('【91VPN】SMTP 测试邮件');
            });
            $this->record($to, '【91VPN】SMTP 测试邮件', 'sent');
        } catch (\Throwable $e) {
            $this->record($to, '【91VPN】SMTP 测试邮件', 'failed', $e->getMessage());
            throw $e;
        }
    }

    /** 把后台 SMTP 设置写进 mailer 运行时配置 */
    private function configureMailer(): void
    {
        Config::set('mail.mailers.smtp', array_merge(config('mail.mailers.smtp', []), [
            'transport' => 'smtp',
            'host' => setting('smtp_host'),
            'port' => (int) setting('smtp_port', '465'),
            'encryption' => setting('smtp_encryption', 'ssl') ?: null,
            'username' => setting('smtp_username'),
            'password' => setting('smtp_password'),
        ]));
    }

    /** 用后台配置的 SMTP 发送验证码邮件 */
    private function mail(string $email, string $code): void
    {
        $this->configureMailer();

        $from = setting('smtp_from') ?: setting('smtp_username');
        $fromName = setting('smtp_from_name', '91VPN');
        $body = "您的验证码是：{$code}\n\n验证码 {$this->ttlMinutes()} 分钟内有效，请勿泄露给他人。\n如非本人操作请忽略此邮件。";

        Mail::mailer('smtp')->raw($body, function ($m) use ($email, $from, $fromName) {
            $m->to($email)->from($from, $fromName)->subject('【91VPN】邮箱验证码');
        });
    }

    private function ttlMinutes(): int
    {
        return self::TTL_MINUTES;
    }

    /** 记录一条邮件发送日志(供后台「邮件记录」排查) */
    private function record(string $email, string $subject, string $status, ?string $error = null): void
    {
        \App\Models\EmailLog::create([
            'to_email' => $email,
            'type' => 'code',
            'subject' => $subject,
            'status' => $status,
            'error' => $error,
        ]);
    }

    /** 代查当前有效验证码（实时读缓存，过期/已用则 null）。仅供后台客服代查，不落库。 */
    public function peek(string $email): ?string
    {
        return Cache::get($this->key($email));
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
            Cache::forget($this->failKey($email));

            return true;
        }
        // 失败锁定：连续错 5 次即作废该码，阻断暴力撞码
        $fails = (int) Cache::get($this->failKey($email), 0) + 1;
        Cache::put($this->failKey($email), $fails, now()->addMinutes(self::TTL_MINUTES));
        if ($fails >= 5) {
            Cache::forget($this->key($email));   // 作废,须重新申请
        }

        return false;
    }

    private function failKey(string $email): string
    {
        return 'email_code_fail:'.strtolower(trim($email));
    }
}
