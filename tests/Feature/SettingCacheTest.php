<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

it('returns default for an unset key without caching null forever', function () {
    Cache::flush();
    expect(Setting::get('nope_missing', 'fallback'))->toBe('fallback');
    // 之前 rememberForever(null) 会让未命中键每次穿透查库;现在整表缓存,再取仍是 default
    expect(Setting::get('nope_missing', 'fallback'))->toBe('fallback');
});

it('reflects put() immediately by invalidating the settings cache', function () {
    Cache::flush();
    expect(Setting::get('site_x'))->toBeNull();       // 预热缓存(此时无该键)

    Setting::put('site_x', 'hello');
    expect(Setting::get('site_x'))->toBe('hello');    // put 后立即可见,不吃旧缓存

    Setting::put('site_x', 'world');
    expect(Setting::get('site_x'))->toBe('world');
});
