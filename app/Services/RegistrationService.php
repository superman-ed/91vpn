<?php

namespace App\Services;

use App\Models\BalanceLog;
use App\Models\InviteCode;
use App\Models\PromoChannel;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * 新用户创建的唯一权威路径:邀请码归因 + 受邀奖励 + 各类令牌生成。
 * 网页版(RegisterController)与客户端 API(AuthApiController)共用,避免逻辑分叉。
 * 调用方负责前置校验(算术/邮箱验证码、邮箱唯一性)。
 */
class RegistrationService
{
    /**
     * @param  array{name:string,email:string,password:string,invite_code?:?string}  $data  password 为明文
     * @param  array{ip?:?string,referer?:?string,utm?:array{source?:?string,medium?:?string,campaign?:?string},promo?:?string}  $ctx  归因上下文(API 场景可全空)
     *
     * @throws ValidationException 邀请码无效时(错误键 invite_code)
     */
    public function register(array $data, array $ctx = []): User
    {
        [$invite, $refByUserId] = $this->resolveInvite($data['invite_code'] ?? null);

        $utm = $ctx['utm'] ?? [];
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'uuid' => (string) Str::uuid(),
            'passwd' => Str::lower(Str::random(6)),
            'transfer_enable' => 0,
            'class' => 0,
            'class_expire' => now(),
            'ref_code' => Str::upper(Str::random(8)),
            'invite_token' => Str::random(32),
            'api_token' => Str::random(60),
            'ref_by' => $refByUserId,
            'reg_ip' => $ctx['ip'] ?? null,
            'reg_referer' => Str::limit((string) ($ctx['referer'] ?? ''), 490, ''),
            'utm_source' => $utm['source'] ?? null,
            'utm_medium' => $utm['medium'] ?? null,
            'utm_campaign' => $utm['campaign'] ?? null,
            'promo_code' => $this->validPromoCode($ctx['promo'] ?? null),
        ]);

        if ($invite) {
            $invite->update(['used_by' => $user->id]);
        }

        // 受邀注册奖励:通过邀请注册即得初始资金(后台可配,默认 1 元)
        if ($refByUserId && signup_bonus() > 0) {
            $bonus = signup_bonus();
            $user->increment('money', $bonus);
            BalanceLog::create([
                'user_id' => $user->id,
                'amount' => $bonus,
                'type' => 'bonus',
                'balance_after' => $user->fresh()->money,
                'remark' => '邀请注册奖励',
            ]);
        }

        return $user;
    }

    /**
     * 解析邀请码:支持一次性邀请码 或 用户永久推广码(ref_code)。
     *
     * @return array{0: ?InviteCode, 1: ?int}  [一次性码模型(需回写 used_by)|null, 归因用户ID|null]
     *
     * @throws ValidationException
     */
    private function resolveInvite(?string $code): array
    {
        if (empty($code)) {
            return [null, null];
        }

        $invite = InviteCode::where('code', $code)->whereNull('used_by')->first();
        if ($invite) {
            return [$invite, $invite->user_id];
        }

        $inviter = User::where('ref_code', $code)->first();
        if (! $inviter) {
            throw ValidationException::withMessages(['invite_code' => '邀请码无效或已被使用']);
        }

        return [null, $inviter->id];
    }

    /** 仅当推广码存在且启用时才归因,否则忽略 */
    private function validPromoCode(?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        return PromoChannel::where('code', $code)->where('enabled', true)->exists() ? $code : null;
    }
}
