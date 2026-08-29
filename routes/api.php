<?php

use App\Http\Controllers\Api\AccountApiController;
use App\Http\Controllers\Api\AnnouncementApiController;
use App\Http\Controllers\Api\AppApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\ServerApiController;
use App\Http\Controllers\Api\UserApiController;
use Illuminate\Support\Facades\Route;

// 客户端对接 API —— 无状态,Bearer api_token 认证(前缀 /api 由框架自动加)

// 公开(无需 token)
Route::post('/auth/login', [AuthApiController::class, 'login'])->middleware('throttle:10,1');   // 登录,限流防撞库
Route::get('/app/version', [AppApiController::class, 'version']);                                // 版本检查(登录前也可调)

// 需登录:Bearer api_token
Route::middleware('client.token')->group(function () {
    Route::get('/user', [UserApiController::class, 'show']);                     // 用户信息(首页/我的)
    Route::get('/servers', [ServerApiController::class, 'index']);               // 节点列表
    Route::get('/announcements', [AnnouncementApiController::class, 'index']);   // 公告
    Route::post('/checkin', [AccountApiController::class, 'checkin']);           // 每日签到
    Route::post('/account/password', [AccountApiController::class, 'updatePassword']); // 修改密码
    Route::post('/device/report', [DeviceController::class, 'report']);          // 设备上报
});
