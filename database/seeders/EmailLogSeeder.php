<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmailLogSeeder extends Seeder
{
    /** 造邮件发送记录 mock 数据（演示用），覆盖成功/失败/仅记录三种状态 */
    public function run(): void
    {
        $subject = '【91VPN】邮箱验证码';
        $mailboxes = [
            'alice@gmail.com', 'bob@qq.com', 'carol@163.com', 'dave@outlook.com',
            'eve@foxmail.com', 'frank@gmail.com', 'grace@126.com', 'henry@hotmail.com',
            'ivy@qq.com', 'jack@gmail.com', 'kate@163.com', 'leo@icloud.com',
        ];
        $failReasons = [
            '550 5.1.1 Recipient address rejected: User unknown',
            'Connection could not be established with host smtpdm.aliyun.com: Connection timed out',
            '535 Authentication failed: invalid username or password',
            '451 4.7.1 Too many messages this session, rate limited',
            '554 5.7.1 Message rejected as spam',
        ];

        $rows = [];
        for ($i = 0; $i < 40; $i++) {
            // 80% 成功、13% 失败、7% 仅记录
            $r = random_int(1, 100);
            $status = $r <= 80 ? 'sent' : ($r <= 93 ? 'failed' : 'logged');
            $ts = now()->subDays(random_int(0, 6))->subMinutes(random_int(0, 1439));
            $rows[] = [
                'to_email' => $mailboxes[array_rand($mailboxes)],
                'type' => 'code',
                'subject' => $subject,
                'status' => $status,
                'error' => $status === 'failed' ? $failReasons[array_rand($failReasons)] : null,
                'created_at' => $ts,
                'updated_at' => $ts,
            ];
        }

        DB::table('email_logs')->insert($rows);
    }
}
