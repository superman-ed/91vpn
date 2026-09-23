<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * 按设备的认证 token(Level A)。登录带 device_id 时发/取;下线时删除 → 该设备被登出。
 * 与 devices 表用 (user_id, device_id) 关联,但职责分离:此表管认证,devices 管限额+机型。
 */
class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'device_id', 'token'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** 发/取某设备的 token:已存在则复用(登录不轮换,避免其它已登录实例被顶掉),否则新建 */
    public static function issue(User $user, string $deviceId): string
    {
        $row = static::firstOrCreate(
            ['user_id' => $user->id, 'device_id' => $deviceId],
            ['token' => Str::random(60)],
        );

        return $row->token;
    }
}
