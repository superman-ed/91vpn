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
        'is_admin', 'banned', 'last_check_in',
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

    // 是否等级有效(未过期)
    public function isActive(): bool
    {
        return ! $this->banned
            && $this->class_expire !== null
            && $this->class_expire->isFuture();
    }
}
