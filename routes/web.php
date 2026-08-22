<?php

use App\Http\Controllers\Auth\EmailCodeController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect(Auth::check() ? '/user' : '/login');
});

// 认证（游客）
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::post('/auth/send', [EmailCodeController::class, 'send']);
});

// 用户中心（占位，M2 实现）
Route::middleware('auth')->group(function () {
    Route::get('/user', function () {
        return 'dashboard placeholder - user: '.Auth::user()->email;
    })->name('dashboard');
});
