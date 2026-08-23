<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'uuid', 'passwd',
        'u', 'd', 'transfer_enable', 'transfer_today',
        'class', 'class_expire',
        'node_speed_limit', 'node_ip_limit',
        'money', 'ref_by', 'ref_code',
        'invite_token', 'api_token',
        'is_admin', 'banned', 'last_check_in', 'last_used_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'passwd', 'api_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'class_expire' => 'datetime',
            'last_used_at' => 'datetime',
            'money' => 'decimal:2',
            'is_admin' => 'boolean',
            'banned' => 'boolean',
        ];
    }

    // 关系
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function balanceLogs(): HasMany
    {
        return $this->hasMany(BalanceLog::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ref_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'ref_by');
    }

    public function paybacks(): HasMany
    {
        return $this->hasMany(Payback::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function loginLogs(): HasMany
    {
        return $this->hasMany(LoginLog::class);
    }

    // 已用总流量(字节)
    public function usedTraffic(): int
    {
        return (int) ($this->u + $this->d);
    }

    // 是否流量耗尽
    public function isTrafficExhausted(): bool
    {
        return $this->usedTraffic() >= $this->transfer_enable;
    }

    // 今天是否已签到
    public function checkedInToday(): bool
    {
        return $this->last_check_in > 0
            && \Illuminate\Support\Carbon::createFromTimestamp($this->last_check_in)->isToday();
    }

    // 流量已用百分比(0-100)
    public function usagePercent(): int
    {
        if ($this->transfer_enable <= 0) {
            return 0;
        }

        return (int) min(100, round($this->usedTraffic() / $this->transfer_enable * 100));
    }

    // 当前在线设备数（占位）：mod_mu 尚未上报 alive IP，接入前恒为 0
    public function onlineDevices(): int
    {
        return 0;
    }

    // 上次使用时间展示文本（m-d H:i），从未使用返回“—”
    public function lastUsedText(): string
    {
        return $this->last_used_at ? $this->last_used_at->format('m-d H:i') : '—';
    }

    // 会员剩余时长(天)，未开通或已过期返回 0
    public function membershipDaysLeft(): int
    {
        if ($this->class <= 0 || $this->class_expire === null || $this->class_expire->isPast()) {
            return 0;
        }

        return (int) ceil(now()->diffInDays($this->class_expire, true));
    }

    // 会员时长展示文本
    public function membershipText(): string
    {
        $days = $this->membershipDaysLeft();

        return $days > 0 ? "剩余 {$days} 天" : '未开通';
    }

    // 是否等级有效(未过期)
    public function isActive(): bool
    {
        return ! $this->banned
            && $this->class_expire !== null
            && $this->class_expire->isFuture();
    }
}
