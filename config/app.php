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
