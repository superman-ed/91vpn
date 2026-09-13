<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 两张内容表：客户端下载、首页 Banner。
 *
 * `[!!]` 存在的理由只有一条：**改文案和链接不该找开发**。
 * 下载链接此前硬编码在 User\DownloadController 里，四个平台的 url 全是 null ——
 * 想放个安装包上去就要改代码、跑一次部署。
 *
 * `[!]` 图片只存 URL，【不做上传】。上传意味着存储、权限、清理、体积限制、
 * 以及一条新的对外攻击面，而当前的需求只是"换一张图" ——
 * 把图放到任何图床/对象存储再贴地址就够了。真需要上传时再加，不难。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_downloads', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 32);          // Windows / macOS / Android / iOS / …
            $t->string('label', 64);             // 显示名，可与 platform 不同
            $t->string('icon', 64)->default('fas fa-download');
            // `[!]` url 可空 = "即将推出"。这是个【有意义的状态】，不是缺数据：
            // 页面照常列出该平台并显示为未开放，而不是把它整个藏掉。
            $t->string('url', 512)->nullable();
            $t->string('version', 32)->nullable();
            $t->string('note', 255)->nullable();
            $t->integer('sort')->default(0);
            $t->boolean('enabled')->default(true);
            $t->timestamps();
        });

        Schema::create('banners', function (Blueprint $t) {
            $t->id();
            $t->string('title', 128);
            $t->string('image_url', 512)->nullable();
            $t->string('link', 512)->nullable();
            $t->string('text', 255)->nullable();   // 没有图时显示的文字条
            $t->integer('sort')->default(0);
            $t->boolean('enabled')->default(true);
            // `[!!]` 起止时间:没有它的话,一条过期的活动会一直挂在首页 ——
            // 而"忘了下架"是运营最常见的失误,且没有任何现象提醒。
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamps();
        });

        // `[!]` 把现有硬编码的四个平台原样搬进来,url 仍为空 ——
        // 上线当天的页面表现【一字不变】,只是从此可以在后台改。
        $now = now();
        \DB::table('client_downloads')->insert([
            ['platform' => 'Windows', 'label' => '91VPN For Windows', 'icon' => 'fab fa-windows', 'sort' => 1, 'enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            ['platform' => 'macOS', 'label' => '91VPN For macOS', 'icon' => 'fab fa-apple', 'sort' => 2, 'enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            ['platform' => 'Android', 'label' => '91VPN For Android', 'icon' => 'fab fa-android', 'sort' => 3, 'enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            ['platform' => 'iOS', 'label' => '91VPN For iOS', 'icon' => 'fab fa-app-store-ios', 'sort' => 4, 'enabled' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
        Schema::dropIfExists('client_downloads');
    }
};
