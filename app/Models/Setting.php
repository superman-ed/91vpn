<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        // 整表一次缓存成数组:未设置的键只是不在数组里 → 返回 default,
        // 不会像单键 rememberForever(null) 那样每次调用都穿透查库
        $all = Cache::rememberForever('settings:all', fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings:all');   // 设置极少变更,整体失效即可
    }
}
