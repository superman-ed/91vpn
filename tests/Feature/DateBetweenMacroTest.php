<?php

use App\Models\User;

it('dateBetween macro filters by the given column and range', function () {
    $old = User::factory()->create();
    $old->forceFill(['created_at' => '2026-01-01 12:00:00'])->save();
    $mid = User::factory()->create();
    $mid->forceFill(['created_at' => '2026-06-15 12:00:00'])->save();
    $new = User::factory()->create();
    $new->forceFill(['created_at' => '2026-08-20 12:00:00'])->save();

    // from+to 双边界
    expect(User::query()->dateBetween('2026-06-01', '2026-06-30')->pluck('id')->all())
        ->toEqual([$mid->id]);

    // 仅 from
    expect(User::query()->dateBetween('2026-06-01', null)->pluck('id')->sort()->values()->all())
        ->toEqual(collect([$mid->id, $new->id])->sort()->values()->all());

    // from/to 均为空 → 不过滤(返回全部)
    expect(User::query()->dateBetween(null, null)->count())->toBe(3);

    // 自定义列
    expect(User::query()->dateBetween('2026-08-01', null, 'created_at')->pluck('id')->all())
        ->toEqual([$new->id]);
});
