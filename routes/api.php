<?php

use App\Http\Controllers\Api\AccountApiController;
use App\Http\Controllers\Api\AnnouncementApiController;
use App\Http\Controllers\Api\AppApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\CrashController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\HelpApiController;
use App\Http\Controllers\Api\InviteApiController;
use App\Http\Controllers\Api\MessageApiController;
use App\Http\Controllers\Api\NodeApiController;
use App\Http\Controllers\Api\ServerApiController;
use App\Http\Controllers\Api\ShopApiController;
use App\Http\Controllers\Api\TicketApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\WalletApiController;
use Illuminate\Support\Facades\Route;

// 客户端对接 API —— 无状态,Bearer api_token 认证(前缀 /api 由框架自动加)

// 面板间内部只读(供 relaypanel 配对校验;共享 token 鉴权,内部调,不给客户端)
Route::get('/internal/relay/accept-proxy', [\App\Http\Controllers\Api\Internal\RelayController::class, 'acceptProxy'])->middleware('throttle:60,1');

// 公开(无需 token)
Route::post('/auth/login', [AuthApiController::class, 'login'])->middleware('throttle:10,1');       // 登录(账户名+密码),限流防撞库
Route::post('/auth/register', [AuthApiController::class, 'register'])->middleware('throttle:10,1');  // 注册(账户名+密码,无邮箱)
Route::get('/app/version', [AppApiController::class, 'version']);                                    // 版本检查(登录前也可调)
Route::get('/app/config', [AppApiController::class, 'config']);                                       // 运行时配置(在线客服 Crisp 等)
Route::get('/plans', [ShopApiController::class, 'index']);                                            // 套餐目录(公开:游客未登录也可浏览,购买时才要求登录)
Route::get('/help', [HelpApiController::class, 'index']);                                             // 帮助中心/文档(公开:游客也可看)
Route::post('/crash', [CrashController::class, 'report'])->middleware('throttle:30,1');               // 崩溃上报(公开:游客也可能崩溃;token 可选;限流防刷)

// 需登录:Bearer api_token
Route::middleware('client.token')->group(function () {
    Route::get('/user', [UserApiController::class, 'show']);                     // 用户信息(首页/我的)
    Route::get('/servers', [ServerApiController::class, 'index']);               // 节点列表
    Route::get('/announcements', [AnnouncementApiController::class, 'index']);   // 公告
    Route::post('/checkin', [AccountApiController::class, 'checkin']);           // 每日签到
    Route::post('/account/password', [AccountApiController::class, 'updatePassword']); // 修改密码
    Route::post('/account/profile', [AccountApiController::class, 'updateProfile']);   // 修改昵称
    Route::post('/device/report', [DeviceController::class, 'report']);          // 设备上报
    Route::get('/devices', [DeviceController::class, 'index']);                   // 设备列表(我的→在线设备)
    Route::delete('/devices/{id}', [DeviceController::class, 'destroy']);         // 下线/移除一台设备

    // 商店 / 下单 / 支付
    Route::get('/orders', [ShopApiController::class, 'orders']);                  // 订单历史
    Route::post('/subscription/end', [ShopApiController::class, 'endSubscription']); // 立即结束当前套餐
    Route::post('/order/create', [ShopApiController::class, 'create']);           // 下单
    Route::get('/order/{order}', [ShopApiController::class, 'show']);             // 收银台信息
    Route::post('/order/{order}/coupon', [ShopApiController::class, 'coupon']);   // 应用/移除优惠码
    Route::post('/order/{order}/pay', [ShopApiController::class, 'pay']);         // 支付(余额/在线/0元)
    Route::post('/order/{order}/cancel', [ShopApiController::class, 'cancel']);   // 取消

    // 钱包
    Route::get('/wallet', [WalletApiController::class, 'index']);                 // 余额+流水
    Route::post('/wallet/recharge', [WalletApiController::class, 'recharge']);    // 充值

    // 工单
    Route::get('/tickets', [TicketApiController::class, 'index']);                // 列表
    Route::post('/tickets', [TicketApiController::class, 'store']);               // 新建
    Route::get('/tickets/{ticket}', [TicketApiController::class, 'show']);        // 详情
    Route::post('/tickets/{ticket}/reply', [TicketApiController::class, 'reply']); // 回复
    Route::post('/tickets/{ticket}/close', [TicketApiController::class, 'close']); // 结单

    // 连接凭证 / 用量
    Route::get('/node', [NodeApiController::class, 'show']);                          // 订阅链接/UUID/密码
    Route::post('/node/reset-sub', [NodeApiController::class, 'resetSub']);           // 重置订阅链接
    Route::post('/node/reset-credential', [NodeApiController::class, 'resetCredential']); // 重置UUID+密码
    Route::get('/traffic', [NodeApiController::class, 'traffic']);                    // 每日流量
    Route::get('/subscribe-log', [NodeApiController::class, 'subscribeLog']);         // 订阅拉取记录

    // 站内信
    Route::get('/messages', [MessageApiController::class, 'index']);                  // 列表+未读数
    Route::post('/messages/read-all', [MessageApiController::class, 'readAll']);      // 全部已读
    Route::post('/messages/{notification}/read', [MessageApiController::class, 'read']); // 单条已读

    // 邀请返利
    Route::get('/invite', [InviteApiController::class, 'index']);                     // 推广码+下线明细
});
