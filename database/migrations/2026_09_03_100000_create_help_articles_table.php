<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->string('category')->default('常见问题'); // 文档中心分类
            $table->string('title');
            $table->longText('content');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('published')->default(true);
            $table->timestamps();
        });
        // 默认文档见 HelpArticleSeeder(不在迁移里塞,避免测试库被污染)
    }

    public function down(): void
    {
        Schema::dropIfExists('help_articles');
    }
};
