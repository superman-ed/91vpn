<?php

use App\Http\Controllers\Admin\AcquisitionController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\ClientDownloadController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\CrashLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DeviceStatController;
use App\Http\Controllers\Admin\DocsController;
use App\Http\Controllers\Admin\EmailLogController;
use App\Http\Controllers\Admin\EntryDomainController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\HealthController;
use App\Http\Controllers\Admin\HelpArticleController as AdminHelpArticleController;
use App\Http\Controllers\Admin\LoginLogController;
use App\Http\Controllers\Admin\NodeController;
use App\Http\Controllers\Admin\NodeController as AdminNodeController;
use App\Http\Controllers\Admin\OnlineUserController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\PromoController;
use App\Http\Controllers\Admin\RebateController;
use App\Http\Controllers\Admin\RelayDeployController;
use App\Http\Controllers\Admin\RelayMonitorController;
use App\Http\Controllers\Admin\RelayOnlineIpController;
use App\Http\Controllers\Admin\RelayRuleController;
use App\Http\Controllers\Admin\RelayWizardController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\TopologyController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\ModMu\ForwardController as ModMuForwardController;
use App\Http\Controllers\Api\ModMu\UserController as ModMuUserController;
use App\Http\Controllers\Api\SubController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\User\AccountController;
use App\Http\Controllers\User\CheckinController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\DeviceController as UserDeviceController;
use App\Http\Controllers\User\DownloadController;
use App\Http\Controllers\User\InviteController;
use App\Http\Controllers\User\NodeSettingController;
use App\Http\Controllers\User\NotificationController;
use App\Http\Controllers\User\ServerListController;
use App\Http\Controllers\User\ShopController;
use App\Http\Controllers\User\SubscribeLogController;
use App\Http\Controllers\User\TicketController as UserTicketController;
use App\Http\Controllers\User\TrafficLogController;
use App\Http\Controllers\User\WalletController;
use Illuminate\Support\Facades\Route;

// 官网首页(门户):游客看营销落地页(价格/地区/下载读真实数据),已登录用户进用户中心。
Route::get('/', [HomeController::class, 'index']);

// 条款页:正文读站点设置(后台 → 站点设置 → 法务条款);为空则显示"整理中"
Route::get('/terms', fn () => view('legal', ['title' => '服务条款', 'content' => setting('terms_content', '')]))->name('terms');
Route::get('/privacy', fn () => view('legal', ['title' => '隐私政策', 'content' => setting('privacy_content', '')]))->name('privacy');
Route::get('/refund', fn () => view('legal', ['title' => '退款政策', 'content' => setting('refund_content', '')]))->name('refund');

// 公开帮助中心(游客可看已发布文章),官网下载/FAQ 链到这里
Route::get('/help', [HelpController::class, 'index'])->name('help');
Route::get('/help/{article}', [HelpController::class, 'show'])->name('help.show');

// SEO:robots.txt / sitemap.xml(动态取 config('app.url'),域名切换自动正确)
Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);

// 支付网关回调（易支付，机器对机器，无需登录）
Route::match(['get', 'post'], '/pay/epay/notify', [PaymentController::class, 'notify']);
Route::match(['get', 'post'], '/pay/epay/return', [PaymentController::class, 'epayReturn']);

// 认证（游客）
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');          // 登录限流,防撞库
    // 账户体系去邮箱:无邮箱验证码/邮箱找回;忘记密码走在线客服人工重置
});

// 用户中心（占位，M2 实现）
Route::middleware('auth')->group(function () {
    Route::get('/user', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/user/checkin', [CheckinController::class, 'store'])->name('checkin');
    Route::get('/user/node', [NodeSettingController::class, 'index'])->name('user.node');
    Route::post('/user/node/reset-sub', [NodeSettingController::class, 'resetSub']);
    Route::post('/user/node/reset-passwd', [NodeSettingController::class, 'resetPasswd']);
    Route::get('/user/devices', [UserDeviceController::class, 'index'])->name('user.devices');
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
    Route::post('/user/ticket/{ticket}/close', [UserTicketController::class, 'close']);
    Route::get('/user/shop', [ShopController::class, 'index'])->name('user.shop');
    Route::post('/user/order/create', [ShopController::class, 'createOrder']);
    Route::get('/user/order/{order}', [ShopController::class, 'checkout'])->name('user.checkout');
    Route::post('/user/order/{order}/coupon', [ShopController::class, 'applyCoupon']);
    Route::post('/user/order/{order}/pay', [ShopController::class, 'pay']);
    Route::post('/user/order/{order}/cancel', [ShopController::class, 'cancelOrder']);
    Route::post('/user/subscription/end', [ShopController::class, 'endSubscription']);
    Route::post('/user/order/{order}/mock-pay', [ShopController::class, 'mockPay']);
    Route::get('/user/messages', [NotificationController::class, 'index'])->name('user.messages');
    Route::post('/user/messages/read-all', [NotificationController::class, 'readAll']);
    Route::post('/user/messages/{notification}/read', [NotificationController::class, 'read']);
    Route::get('/user/wallet', [WalletController::class, 'index'])->name('user.wallet');
    Route::post('/user/wallet/recharge', [WalletController::class, 'recharge']);
    Route::post('/user/order/{order}/pay-balance', [WalletController::class, 'payBalance']);
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

// 订阅下发（公开，客户端凭 token 拉取，不需登录）
Route::get('/sub/{token}', [SubController::class, 'show'])->name('sub');

// 客户端 API(设备上报、登录、用户信息等)已迁至 routes/api.php

// 节点对接 WebAPI（节点后端调用，node.secret 鉴权）
Route::middleware('node.secret')->prefix('mod_mu')->group(function () {
    Route::get('/users', [ModMuUserController::class, 'index']);
    Route::post('/users/traffic', [ModMuUserController::class, 'addTraffic']);
    Route::post('/users/aliveip', [ModMuUserController::class, 'aliveIp']);
    Route::post('/users/detectlog', [ModMuUserController::class, 'detectLog']);   // 审计违规上报(空实现)
    Route::get('/func/ping', [ModMuUserController::class, 'ping']);
    Route::get('/func/detect_rules', [ModMuUserController::class, 'detectRules']); // 审计规则(空=不审计)
    Route::get('/nodes/{node}/info', [ModMuUserController::class, 'nodeInfo']);    // 节点配置拉取(soga/XrayR 开机拉)
    Route::post('/nodes/{node}/info', [ModMuUserController::class, 'nodeHeartbeat']); // 节点状态/心跳上报(soga 实测走这里)
    Route::post('/nodes/{node}/dest_scan', [ModMuUserController::class, 'destScan']);  // dest 候选筛查结果回报(本项目扩展)

    // 中转节点的规则面（ADR-008：从 relaypanel 并入）。
    // [!] /routes 对非中转角色返回 404 —— agent 把 404 解读为"本部署没有中转功能"
    // 并停止轮询,这正是落地节点该有的行为,不是错误。
    Route::get('/nodes/{node}/routes', [ModMuForwardController::class, 'routes']);
    Route::post('/nodes/{node}/rules/traffic', [ModMuForwardController::class, 'ruleTraffic']);
    Route::post('/nodes/{node}/rules/aliveip', [ModMuForwardController::class, 'ruleAliveIp']);
    Route::post('/nodes/{node}/rules/status', [ModMuForwardController::class, 'ruleStatus']);
    Route::post('/nodes/{node}/rules/sync', [ModMuForwardController::class, 'ruleSync']);
});

// 管理后台
// [!] 后台还受 Middleware\AdminHost 保护(挂在 web 组最前面,不在这里):
// 配了 ADMIN_HOST 后,只有管理专用主机名能进 /admin/*,其余一律 404。
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::resource('nodes', AdminNodeController::class)->except('show')->names('admin.nodes');
    // 一键诊断:向外探端口,故限流(见 NodeController::diagnose)
    Route::get('nodes/{node}/diagnose', [NodeController::class, 'diagnose'])
        ->middleware('throttle:20,1')->name('admin.nodes.diagnose');
    Route::post('nodes/{node}/regenerate-secret', [AdminNodeController::class, 'regenerateSecret'])->name('admin.nodes.regenerate-secret');
    // dest 候选生成:纯计算 + 被动 DNS,不连第三方。首次会下 10MB 排名表,故限流。
    Route::post('nodes/{node}/dest-candidates', [AdminNodeController::class, 'destCandidates'])
        ->middleware('throttle:10,1')->name('admin.nodes.dest-candidates');
    // 中转（ADR-008：从 relaypanel 并入）
    Route::get('rules', [RelayRuleController::class, 'index'])->name('admin.rules.index');
    // 中转链路向导：常见形态一次配好，并自动让落地收 PROXY 头（成对的那一步）
    Route::get('rules/wizard', [RelayWizardController::class, 'create'])->name('admin.rules.wizard');
    Route::post('rules/wizard', [RelayWizardController::class, 'store']);
    Route::get('rules/create', [RelayRuleController::class, 'create'])->name('admin.rules.create');
    Route::post('rules', [RelayRuleController::class, 'store'])->name('admin.rules.store');
    Route::get('rules/{rule}/edit', [RelayRuleController::class, 'edit'])->name('admin.rules.edit');
    Route::put('rules/{rule}', [RelayRuleController::class, 'update'])->name('admin.rules.update');
    Route::delete('rules/{rule}', [RelayRuleController::class, 'destroy'])->name('admin.rules.destroy');
    // 把写死的出站地址转成落地节点引用（下发内容不变，面板从此认得回来）
    Route::post('rules/{rule}/adopt-targets', [RelayRuleController::class, 'adoptTargets']);
    Route::post('rules/{rule}/regenerate-cred', [RelayRuleController::class, 'regenerateCred']);
    Route::post('rules/{rule}/reality-keypair', [RelayRuleController::class, 'realityKeypair']);
    Route::post('nodes/{node}/deploy', [RelayDeployController::class, 'start']);
    Route::get('nodes/{node}/deploy/{run}', [RelayDeployController::class, 'log']);
    // 落地部署的 91vpn 身份(面板即 91vpn,自动带入;secret 按需取不洒进列表页)
    Route::get('nodes/{node}/deploy-identity', [RelayDeployController::class, 'identity']);
    // 入口域名池：给中转挂稳定域名,订阅发域名不发裸 IP;IP 被墙只改 A 记录、客户端无感。
    Route::get('entry-domains', [EntryDomainController::class, 'index'])->name('admin.entry-domains.index');
    Route::post('entry-domains', [EntryDomainController::class, 'store'])->name('admin.entry-domains.store');
    Route::post('entry-domains/{entryDomain}/activate', [EntryDomainController::class, 'activate'])->name('admin.entry-domains.activate');
    Route::post('entry-domains/{entryDomain}/block', [EntryDomainController::class, 'block'])->name('admin.entry-domains.block');
    Route::post('entry-domains/{entryDomain}/rotate', [EntryDomainController::class, 'rotate'])->name('admin.entry-domains.rotate');
    Route::delete('entry-domains/{entryDomain}', [EntryDomainController::class, 'destroy'])->name('admin.entry-domains.destroy');

    // 拓扑：按【路径】看 —— 哪条路存在、断在哪一段、用户实际拿得到哪几条
    Route::get('topology', [TopologyController::class, 'index'])->name('admin.topology');
    Route::get('relay/monitor', [RelayMonitorController::class, 'index'])->name('admin.relay.monitor');
    Route::get('relay/online-ip', [RelayOnlineIpController::class, 'index'])->name('admin.relay.online-ip');

    Route::resource('plans', AdminPlanController::class)->except('show')->names('admin.plans');
    Route::post('plans/{plan}/toggle-sale', [AdminPlanController::class, 'toggleSale'])->name('admin.plans.toggle-sale');
    Route::post('plans/{plan}/move', [AdminPlanController::class, 'move'])->name('admin.plans.move');
    // 内容：客户端下载 / 首页 Banner —— 改文案和链接不该找开发
    Route::resource('downloads', ClientDownloadController::class)
        ->except('show')->names('admin.downloads')->parameters(['downloads' => 'download']);
    Route::resource('banners', BannerController::class)
        ->except('show')->names('admin.banners');
    Route::resource('announcements', AdminAnnouncementController::class)->except('show')->names('admin.announcements');
    Route::resource('help', AdminHelpArticleController::class)->except('show')->names('admin.help')->parameters(['help' => 'help']);
    Route::get('users/export', [AdminUserController::class, 'export'])->name('admin.users.export');
    Route::get('users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('admin.users.edit');
    Route::put('users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::post('users/{user}/toggle-ban', [AdminUserController::class, 'toggleBan'])->name('admin.users.toggle-ban');
    Route::post('users/{user}/reset-traffic', [AdminUserController::class, 'resetTraffic'])->name('admin.users.reset-traffic');
    Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('admin.users.reset-password');
    Route::get('users/{user}/grant', [AdminUserController::class, 'grant'])->name('admin.users.grant');
    Route::post('users/{user}/grant', [AdminUserController::class, 'doGrant']);
    Route::get('admins', [AdminController::class, 'index'])->name('admin.admins.index');
    Route::get('admins/create', [AdminController::class, 'create']);
    Route::post('admins', [AdminController::class, 'store']);
    Route::post('admins/{user}/role', [AdminController::class, 'updateRole']);
    Route::delete('admins/{user}', [AdminController::class, 'destroy']);
    // 管理员自助改密
    Route::get('account', [App\Http\Controllers\Admin\AccountController::class, 'edit'])->name('admin.account');
    Route::post('account/password', [App\Http\Controllers\Admin\AccountController::class, 'updatePassword'])->name('admin.account.password');
    Route::get('finance/export', [FinanceController::class, 'export'])->name('admin.finance.export');
    Route::get('finance', [FinanceController::class, 'index'])->name('admin.finance.index');
    Route::get('rebates', [RebateController::class, 'index'])->name('admin.rebates.index');
    Route::get('promo', [PromoController::class, 'index'])->name('admin.promo.index');
    Route::post('promo', [PromoController::class, 'store'])->name('admin.promo.store');
    Route::get('promo/{channel}', [PromoController::class, 'show'])->name('admin.promo.show');
    Route::put('promo/{channel}', [PromoController::class, 'update'])->name('admin.promo.update');
    Route::delete('promo/{channel}', [PromoController::class, 'destroy'])->name('admin.promo.destroy');
    Route::get('online', [OnlineUserController::class, 'index'])->name('admin.online.index');
    Route::get('system/login-logs', [LoginLogController::class, 'index'])->name('admin.system.login-logs');
    Route::get('system/devices', [DeviceStatController::class, 'index'])->name('admin.system.devices');
    Route::get('system/crashes', [CrashLogController::class, 'index'])->name('admin.system.crashes');
    Route::get('system/crashes/{fingerprint}', [CrashLogController::class, 'show'])->name('admin.system.crashes.show');
    Route::get('system/acquisition', [AcquisitionController::class, 'index'])->name('admin.system.acquisition');
    // 技术文档（sogacore/docs/guide 的副本，php artisan docs:sync 同步）
    Route::get('docs/{slug?}', [DocsController::class, 'index'])
        ->name('admin.docs');
    Route::get('system/audit', [AuditLogController::class, 'index'])->name('admin.system.audit');
    Route::get('system/emails', [EmailLogController::class, 'index'])->name('admin.system.emails');
    Route::get('system/health', [HealthController::class, 'index'])->name('admin.system.health');
    Route::get('notifications', [App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('admin.notifications.index');
    Route::post('notifications', [App\Http\Controllers\Admin\NotificationController::class, 'store'])->name('admin.notifications.store');
    Route::put('notifications/{batch}', [App\Http\Controllers\Admin\NotificationController::class, 'update'])->name('admin.notifications.update');
    Route::delete('notifications/{batch}', [App\Http\Controllers\Admin\NotificationController::class, 'destroy'])->name('admin.notifications.destroy');
    Route::get('orders/export', [AdminOrderController::class, 'export'])->name('admin.orders.export');
    Route::get('orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
    Route::post('orders/{order}/mark-paid', [AdminOrderController::class, 'markPaid'])->name('admin.orders.mark-paid');
    Route::post('orders/{order}/refund', [OrderController::class, 'refund']);
    Route::post('orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->name('admin.orders.cancel');
    Route::get('tickets', [AdminTicketController::class, 'index'])->name('admin.tickets.index');
    Route::get('tickets/{ticket}', [AdminTicketController::class, 'show'])->name('admin.tickets.show');
    Route::post('tickets/{ticket}/reply', [AdminTicketController::class, 'reply']);
    Route::post('tickets/{ticket}/close', [AdminTicketController::class, 'close']);
    Route::post('tickets/{ticket}/reopen', [AdminTicketController::class, 'reopen']);
    Route::get('coupons', [AdminCouponController::class, 'index'])->name('admin.coupons.index');
    Route::get('coupons/create', [AdminCouponController::class, 'create']);
    Route::post('coupons/batch', [AdminCouponController::class, 'batchStore'])->name('admin.coupons.batch');
    Route::post('coupons', [AdminCouponController::class, 'store']);
    Route::get('coupons/{coupon}/edit', [AdminCouponController::class, 'edit']);
    Route::put('coupons/{coupon}', [AdminCouponController::class, 'update']);
    Route::delete('coupons/{coupon}', [AdminCouponController::class, 'destroy']);
    Route::get('settings', [AdminSettingController::class, 'edit'])->name('admin.settings.edit');
    Route::put('settings', [AdminSettingController::class, 'update']);
    Route::post('settings/test-email', [AdminSettingController::class, 'testEmail']);
    Route::post('settings/test-gateway', [AdminSettingController::class, 'testGateway']);
});
