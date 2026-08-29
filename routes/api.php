<?php

use App\Http\Controllers\Api\AccountApiController;
use App\Http\Controllers\Api\AnnouncementApiController;
use App\Http\Controllers\Api\AppApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\DeviceController;
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

// 公开(无需 token)
Route::post('/auth/login', [AuthApiController::class, 'login'])->middleware('throttle:10,1');       // 登录,限流防撞库
Route::post('/auth/register', [AuthApiController::class, 'register'])->middleware('throttle:10,1');  // 注册(邮箱验证码)
Route::post('/auth/send-code', [AuthApiController::class, 'sendCode'])->middleware('throttle:5,1');  // 注册发码,限流防轰炸
Route::post('/auth/forgot', [AuthApiController::class, 'forgot'])->middleware('throttle:5,1');       // 找回发码,限流
Route::post('/auth/reset', [AuthApiController::class, 'reset'])->middleware('throttle:10,1');        // 找回校验重置,限流防撞码
Route::get('/app/version', [AppApiController::class, 'version']);                                    // 版本检查(登录前也可调)

// 需登录:Bearer api_token
Route::middleware('client.token')->group(function () {
    Route::get('/user', [UserApiController::class, 'show']);                     // 用户信息(首页/我的)
    Route::get('/servers', [ServerApiController::class, 'index']);               // 节点列表
    Route::get('/announcements', [AnnouncementApiController::class, 'index']);   // 公告
    Route::post('/checkin', [AccountApiController::class, 'checkin']);           // 每日签到
    Route::post('/account/password', [AccountApiController::class, 'updatePassword']); // 修改密码
    Route::post('/device/report', [DeviceController::class, 'report']);          // 设备上报

    // 商店 / 下单 / 支付
    Route::get('/plans', [ShopApiController::class, 'index']);                    // 套餐目录
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
