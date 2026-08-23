<?php

use App\Support\GeoIp;

it('resolves china ipv4 to chinese location', function () {
    if (! file_exists(storage_path('app/ip2region/ip2region_v4.xdb'))) {
        $this->markTestSkipped('ip2region v4 库不存在');
    }
    expect(GeoIp::locate('202.96.128.86'))->toContain('中国');
});

it('resolves ipv6 to a location', function () {
    if (! file_exists(storage_path('app/ip2region/ip2region_v6.xdb'))) {
        $this->markTestSkipped('ip2region v6 库不存在');
    }
    expect(GeoIp::locate('240e:45c:7e60:378:f198:1c51:e425:a0e'))->toContain('中国');
});

it('returns empty for invalid ip gracefully', function () {
    expect(GeoIp::locate(''))->toBe('');
    expect(GeoIp::locate(null))->toBe('');
});
