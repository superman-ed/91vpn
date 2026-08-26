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
        'u', 'd', 'transfer_enable', 'base_transfer_enable', 'transfer_today',
        'class', 'class_expire', 'next_reset_at',
        'node_speed_limit', 'node_ip_limit',
        'money', 'ref_by', 'ref_code', 'reg_ip', 'reg_referer',
        'utm_source', 'utm_medium', 'utm_campaign', 'promo_code',
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
            'next_reset_at' => 'datetime',
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

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class)->latest();
    }

    public function unreadNotificationCount(): int
    {
        return $this->notifications()->whereNull('read_at')->count();
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

    public function aliveIps(): HasMany
    {
        return $this->hasMany(AliveIp::class);
    }

    // 当前在线设备数：最近 ONLINE_WINDOW 秒内上报的去重 IP 数
    public function onlineDevices(): int
    {
        return $this->aliveIps()
            ->where('last_seen', '>=', now()->subSeconds(AliveIp::ONLINE_WINDOW))
            ->count();
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

    // 会员时长展示文本：未开通(新用户) / 已到期 / 剩余 X 天
    public function membershipText(): string
    {
        if ($this->class <= 0) {
            return '未开通';
        }

        if ($this->class_expire === null || $this->class_expire->isPast()) {
            return '已到期';
        }

        return '剩余 '.$this->membershipDaysLeft().' 天';
    }

    // 是否等级有效(未过期)
    public function isActive(): bool
    {
        return ! $this->banned
            && $this->class_expire !== null
            && $this->class_expire->isFuture();
    }

    // 是否有生效中的付费套餐
    public function hasActivePackage(): bool
    {
        return $this->class > 0 && $this->class_expire !== null && $this->class_expire->isFuture();
    }

    // 当前生效套餐的周期（取最近一次已发货订单），无则 null
    public function currentPeriod(): ?string
    {
        return $this->orders()
            ->where('status', 'paid')
            ->whereNotNull('delivered_at')
            ->latest('delivered_at')
            ->value('period');
    }

    // 是否可“立即结束当前套餐”：生效中且当前为单月套餐（非多月）
    public function canEndCurrentPackage(): bool
    {
        return $this->hasActivePackage() && $this->currentPeriod() === 'month';
    }

    // 排队中的订单（已支付待生效）
    public function queuedOrders(): HasMany
    {
        return $this->orders()->where('status', 'queued')->orderBy('activate_at');
    }
}
