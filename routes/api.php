<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\UserApiController;
use Illuminate\Support\Facades\Route;

// 客户端对接 API —— 无状态,Bearer api_token 认证(前缀 /api 由框架自动加)

// 登录(无需 token),限流防撞库
Route::post('/auth/login', [AuthApiController::class, 'login'])->middleware('throttle:10,1');

// 需登录:Bearer api_token
Route::middleware('client.token')->group(function () {
    Route::get('/user', [UserApiController::class, 'show']);              // 用户信息(首页/我的)
    Route::post('/device/report', [DeviceController::class, 'report']);   // 设备上报
});
