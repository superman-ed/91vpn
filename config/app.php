<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
     * 管理后台专用主机名（方案 A）。配了之后 /admin/* 只能从这个主机名进,
     * 其余主机名一律 404 —— 因为用户面那个主机名【没有 Cloudflare Access】,
     * 而两个主机名指向同一个应用。留空 = 不限制(本地开发/CI)。
     */
    'admin_host' => env('ADMIN_HOST'),

    /*
    |--------------------------------------------------------------------------
    | Subscription Base URL
    |--------------------------------------------------------------------------
    |
    | 订阅链接的域名。留空 = 跟随 APP_URL（现状，向后兼容）。
    |
    | `[!!]` 分开的理由:订阅 URL 是【每个用户的客户端每天都要访问】的东西,
    | 而它此前和面板同域 —— 面板域名一旦被封或被污染,用户不只是打不开网页,
    | 是【连订阅也拉不了】:换不了节点、加不了新设备。
    |
    | `[!!]` 而订阅 URL 一旦发出去就【收不回来】—— 它嵌在每个人的客户端配置里。
    | 所以这件事必须在没有用户的时候定下来,晚一天成本就高一天。
    |
    | `[!]` 放在 env 而不是后台设置里,是刻意的:订阅是用户唯一的入口,
    | 填错一个字符就是全员连不上。让它需要一次部署动作,而不是后台一次手滑。
    |
    | 只填到域名,不带路径,例如 https://sub.example.com
    */
    'sub_url_base' => env('SUB_URL_BASE'),

    /*
    |--------------------------------------------------------------------------
    | 节点回连面板的地址（NODE_API_URL）
    |--------------------------------------------------------------------------
    |
    | 每台节点机器要定时回连面板:拉用户名单、上报流量。这个地址在部署那台
    | 机器时被写进它的 agent.conf(键 webapi_url),此后就固定在那台机器上。
    |
    | `[!!]` 它【不该】跟着 APP_URL 走。APP_URL 现在是官网品牌域,那个域要投
    | 广告、做 SEO —— 它的存在意义就是被所有人找到,包括封网的人,所以它是
    | 手上最容易被封的一个。而节点回连用的地址不做任何推广,没人会主动去找。
    | 把两者绑在一起,等于把仓库专线号码印在广告牌上。
    |
    | `[!!]` 封了之后的表现很难发现:节点不会停,它继续按【上次拿到的用户名单】
    | 服务(fail-open,见 LAUNCH-CHECKLIST L-11)—— 新买的用户连不上、已过期的
    | 还能用、流量完全不计,而且【没有任何告警】。面板挂了你立刻知道;
    | 节点回连不上,可能好几天都不知道。
    |
    | 不填则回落到 APP_URL(保持旧行为,不会因为少一个变量就装不上节点)。
    | 只填到域名,不带路径,例如 https://app.example.com
    */
    'node_api_url' => env('NODE_API_URL'),

    /*
    |--------------------------------------------------------------------------
    | Mock Pay(模拟支付,仅供本地开发)
    |--------------------------------------------------------------------------
    |
    | `POST /user/order/{order}/mock-pay` 不走真支付直接发货,给本机开发用。
    | `[!!]` 独立开关,默认【关】—— 即使 APP_ENV=local 也必须显式 MOCK_PAY_ENABLED=true
    | 才可用。否则任何登录用户直接 POST 就能白嫖套餐(审计 U-1)。生产恒不可用。
    */
    'mock_pay_enabled' => env('MOCK_PAY_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
