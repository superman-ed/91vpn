<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\NodeController as AdminNodeController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\ModMu\UserController as ModMuUserController;
use App\Http\Controllers\Api\SubController;
use App\Http\Controllers\Auth\EmailCodeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\User\AccountController;
use App\Http\Controllers\User\DownloadController;
use App\Http\Controllers\User\SubscribeLogController;
use App\Http\Controllers\User\ServerListController;
use App\Http\Controllers\User\TrafficLogController;
use App\Http\Controllers\User\CheckinController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\InviteController;
use App\Http\Controllers\User\TicketController as UserTicketController;
use App\Http\Controllers\User\NodeSettingController;
use App\Http\Controllers\User\ShopController;
use App\Http\Controllers\User\WalletController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect(Auth::check() ? '/user' : '/login');
});

// 支付网关回调（易支付，机器对机器，无需登录）
Route::match(['get', 'post'], '/pay/epay/notify', [App\Http\Controllers\PaymentController::class, 'notify']);
Route::match(['get', 'post'], '/pay/epay/return', [App\Http\Controllers\PaymentController::class, 'epayReturn']);

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
    Route::get('/user/account', [AccountController::class, 'index'])->name('user.account');
    Route::post('/user/account/password', [AccountController::class, 'updatePassword']);
    Route::post('/user/account/profile', [AccountController::class, 'updateProfile']);
    Route::get('/user/servers', [ServerListController::class, 'index'])->name('user.servers');
    Route::get('/user/traffic', [TrafficLogController::class, 'index'])->name('user.traffic');
    Route::get('/user/downloads', [DownloadController::class, 'index'])->name('user.downloads');
    Route::get('/user/subscribe-log', [SubscribeLogController::class, 'index'])->name('user.subscribe-log');
    Route::get('/user/invite', [InviteController::class, 'index'])->name('user.invite');
    Route::get('/user/ticket', [UserTicketController::class, 'index'])->name('user.ticket');
    Route::get('/user/ticket/create', [UserTicketController::class, 'create']);
    Route::post('/user/ticket', [UserTicketController::class, 'store']);
    Route::get('/user/ticket/{ticket}', [UserTicketController::class, 'show']);
    Route::post('/user/ticket/{ticket}/reply', [UserTicketController::class, 'reply']);
    Route::get('/user/shop', [ShopController::class, 'index'])->name('user.shop');
    Route::post('/user/order/create', [ShopController::class, 'createOrder']);
    Route::get('/user/order/{order}', [ShopController::class, 'checkout'])->name('user.checkout');
    Route::post('/user/order/{order}/coupon', [ShopController::class, 'applyCoupon']);
    Route::post('/user/order/{order}/pay', [ShopController::class, 'pay']);
    Route::post('/user/order/{order}/cancel', [ShopController::class, 'cancelOrder']);
    Route::post('/user/subscription/end', [ShopController::class, 'endSubscription']);
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
    Route::post('/users/aliveip', [ModMuUserController::class, 'aliveIp']);
    Route::get('/func/ping', [ModMuUserController::class, 'ping']);
});

// 管理后台
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::resource('nodes', AdminNodeController::class)->except('show')->names('admin.nodes');
    Route::post('nodes/{node}/regenerate-secret', [AdminNodeController::class, 'regenerateSecret'])->name('admin.nodes.regenerate-secret');
    Route::resource('plans', AdminPlanController::class)->except('show')->names('admin.plans');
    Route::resource('announcements', AdminAnnouncementController::class)->except('show')->names('admin.announcements');
    Route::get('users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('admin.users.edit');
    Route::put('users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::post('users/{user}/toggle-ban', [AdminUserController::class, 'toggleBan'])->name('admin.users.toggle-ban');
    Route::post('users/{user}/reset-traffic', [AdminUserController::class, 'resetTraffic'])->name('admin.users.reset-traffic');
    Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('admin.users.reset-password');
    Route::get('users/{user}/grant', [AdminUserController::class, 'grant'])->name('admin.users.grant');
    Route::post('users/{user}/grant', [AdminUserController::class, 'doGrant']);
    Route::get('admins', [App\Http\Controllers\Admin\AdminController::class, 'index'])->name('admin.admins.index');
    Route::get('admins/create', [App\Http\Controllers\Admin\AdminController::class, 'create']);
    Route::post('admins', [App\Http\Controllers\Admin\AdminController::class, 'store']);
    Route::delete('admins/{user}', [App\Http\Controllers\Admin\AdminController::class, 'destroy']);
    Route::get('finance', [App\Http\Controllers\Admin\FinanceController::class, 'index'])->name('admin.finance.index');
    Route::get('rebates', [App\Http\Controllers\Admin\RebateController::class, 'index'])->name('admin.rebates.index');
    Route::get('online', [App\Http\Controllers\Admin\OnlineUserController::class, 'index'])->name('admin.online.index');
    Route::get('system/login-logs', [App\Http\Controllers\Admin\LoginLogController::class, 'index'])->name('admin.system.login-logs');
    Route::get('system/devices', [App\Http\Controllers\Admin\DeviceStatController::class, 'index'])->name('admin.system.devices');
    Route::get('system/acquisition', [App\Http\Controllers\Admin\AcquisitionController::class, 'index'])->name('admin.system.acquisition');
    Route::get('system/audit', [App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('admin.system.audit');
    Route::get('orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
    Route::post('orders/{order}/mark-paid', [AdminOrderController::class, 'markPaid'])->name('admin.orders.mark-paid');
    Route::post('orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->name('admin.orders.cancel');
    Route::get('tickets', [AdminTicketController::class, 'index'])->name('admin.tickets.index');
    Route::get('tickets/{ticket}', [AdminTicketController::class, 'show'])->name('admin.tickets.show');
    Route::post('tickets/{ticket}/reply', [AdminTicketController::class, 'reply']);
    Route::post('tickets/{ticket}/close', [AdminTicketController::class, 'close']);
    Route::get('coupons', [AdminCouponController::class, 'index'])->name('admin.coupons.index');
    Route::get('coupons/create', [AdminCouponController::class, 'create']);
    Route::post('coupons', [AdminCouponController::class, 'store']);
    Route::get('coupons/{coupon}/edit', [AdminCouponController::class, 'edit']);
    Route::put('coupons/{coupon}', [AdminCouponController::class, 'update']);
    Route::delete('coupons/{coupon}', [AdminCouponController::class, 'destroy']);
    Route::get('settings', [AdminSettingController::class, 'edit'])->name('admin.settings.edit');
    Route::put('settings', [AdminSettingController::class, 'update']);
});
