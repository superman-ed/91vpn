<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\NodeController as AdminNodeController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Api\ModMu\UserController as ModMuUserController;
use App\Http\Controllers\Api\SubController;
use App\Http\Controllers\Auth\EmailCodeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\User\AnnouncementController;
use App\Http\Controllers\User\CheckinController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\NodeSettingController;
use App\Http\Controllers\User\ShopController;
use App\Http\Controllers\User\WalletController;
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
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/password/forgot', [PasswordController::class, 'forgot'])->name('password.forgot');
    Route::post('/password/send', [PasswordController::class, 'send']);
    Route::post('/password/reset', [PasswordController::class, 'reset']);
});

// 用户中心（占位，M2 实现）
Route::middleware('auth')->group(function () {
    Route::get('/user', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/user/checkin', [CheckinController::class, 'store'])->name('checkin');
    Route::get('/user/node', [NodeSettingController::class, 'index'])->name('user.node');
    Route::post('/user/node/reset-sub', [NodeSettingController::class, 'resetSub']);
    Route::post('/user/node/reset-passwd', [NodeSettingController::class, 'resetPasswd']);
    Route::get('/user/announcement', [AnnouncementController::class, 'index'])->name('user.announcement');
    Route::get('/user/shop', [ShopController::class, 'index'])->name('user.shop');
    Route::post('/user/order/create', [ShopController::class, 'createOrder']);
    Route::post('/user/order/{order}/mock-pay', [ShopController::class, 'mockPay']);
    Route::get('/user/wallet', [WalletController::class, 'index'])->name('user.wallet');
    Route::post('/user/wallet/recharge', [WalletController::class, 'recharge']);
    Route::post('/user/order/{order}/pay-balance', [WalletController::class, 'payBalance']);
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

// 订阅下发（公开，客户端凭 token 拉取，不需登录）
Route::get('/sub/{token}', [SubController::class, 'show'])->name('sub');

// 节点对接 WebAPI（节点后端调用，node.secret 鉴权）
Route::middleware('node.secret')->prefix('mod_mu')->group(function () {
    Route::get('/users', [ModMuUserController::class, 'index']);
    Route::post('/users/traffic', [ModMuUserController::class, 'addTraffic']);
    Route::get('/func/ping', [ModMuUserController::class, 'ping']);
});

// 管理后台
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::resource('nodes', AdminNodeController::class)->except('show')->names('admin.nodes');
    Route::resource('plans', AdminPlanController::class)->except('show')->names('admin.plans');
    Route::resource('announcements', AdminAnnouncementController::class)->except('show')->names('admin.announcements');
    Route::get('users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('admin.users.edit');
    Route::put('users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::post('users/{user}/toggle-ban', [AdminUserController::class, 'toggleBan'])->name('admin.users.toggle-ban');
    Route::get('orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
});
