<?php

namespace Database\Seeders;

use App\Models\Announcement;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        Announcement::updateOrCreate(
            ['title' => '欢迎使用 91VPN'],
            ['content' => "感谢注册！\n1. 购买套餐后在「节点设置」获取订阅链接\n2. 导入 Clash / v2rayN 即可使用\n3. 有问题请查看公告或联系客服", 'sort' => 10, 'published' => true]
        );
    }
}
