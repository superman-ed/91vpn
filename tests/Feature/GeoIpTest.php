<?php

use App\Support\GeoIp;

it('resolves china ip to chinese location', function () {
    // 数据文件存在才测（CI 无文件则跳过）
    if (! file_exists(storage_path('app/qqwry.dat'))) {
        $this->markTestSkipped('qqwry.dat 不存在');
    }
    $loc = GeoIp::locate('223.5.5.5');
    expect($loc)->toContain('中国');
});

it('returns empty for invalid ip gracefully', function () {
    expect(GeoIp::locate(''))->toBe('');
    expect(GeoIp::locate(null))->toBe('');
});
