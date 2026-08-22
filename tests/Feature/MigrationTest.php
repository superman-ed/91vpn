<?php

use Illuminate\Support\Facades\Schema;

it('migrates all 8 core tables', function () {
    $tables = ['users', 'plans', 'orders', 'nodes', 'balance_logs', 'invite_codes', 'announcements', 'traffic_logs'];
    foreach ($tables as $t) {
        expect(Schema::hasTable($t))->toBeTrue("表 {$t} 应存在");
    }
});

it('user table has connection and billing fields', function () {
    $cols = ['uuid', 'passwd', 'u', 'd', 'transfer_enable', 'class', 'class_expire',
        'node_speed_limit', 'node_ip_limit', 'money', 'ref_by', 'invite_token', 'api_token', 'is_admin', 'banned'];
    foreach ($cols as $c) {
        expect(Schema::hasColumn('users', $c))->toBeTrue("users.{$c} 应存在");
    }
});

it('node table supports relay架构 fields', function () {
    foreach (['server', 'port', 'type', 'net', 'traffic_rate', 'node_class', 'secret'] as $c) {
        expect(Schema::hasColumn('nodes', $c))->toBeTrue("nodes.{$c} 应存在");
    }
});

it('plan table has billing dimensions', function () {
    foreach (['transfer_gb', 'class', 'speed_limit', 'ip_limit', 'duration_days', 'price'] as $c) {
        expect(Schema::hasColumn('plans', $c))->toBeTrue("plans.{$c} 应存在");
    }
});
