<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 一键部署的运行记录 —— 一次 SSH 装机 = 一行。
 *
 * [!!] 这里【绝不存 SSH 私钥/密码】。凭据是一次性的：运维在部署表单里
 * 粘一次，经 stdin 交给后台进程，用完随进程消失（方案 A + 用完即焚）。
 * 表里只留"装到哪台、结果如何、日志"这些可追溯的东西。
 *
 * [!] host_key 是首次连上时对端的 SSH 主机指纹（TOFU）。存下来，下次
 * 部署同一台若指纹变了就告警 —— 中间人/被换机的信号。
 */
/**
 * [!] 本文件由 relaypanel 原样搬入（ADR-008：中转面板并回 91vpn）。
 * 注释保持原样 —— 那是三边（本表 / 下发 JSON / agent 的 Go 结构体）
 * 冻结过的共识，不要在这里自行增删字段名。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deploy_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('node_id')->index();          // 部署的目标节点（本面板 nodes 表）
            $t->string('status', 16)->default('pending')->index(); // pending|running|ok|failed
            $t->string('ssh_host');                     // 连到哪（IP/域名，仅记录，不含凭据）
            $t->unsignedInteger('ssh_port')->default(22);
            $t->string('ssh_user', 64)->default('root');
            $t->string('host_key', 255)->nullable();    // 对端主机指纹（TOFU 固定）
            $t->longText('log')->nullable();            // 实时追加的部署输出
            $t->string('reason', 255)->nullable();      // 失败原因摘要（成功为空）
            $t->string('agent_version', 64)->nullable();// 装上的 agent 版本（成功时回填）
            $t->unsignedBigInteger('created_by')->nullable(); // 哪个管理员发起的
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deploy_runs');
    }
};
