<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    /*
    | Telegram 运维告警(节点掉线/恢复)。
    |
    | `[!]` 与站点设置里的 support_tg 【不是一回事】:那是给用户看的 t.me 链接,
    | 这里要的是 Bot Token + 接收告警的 chat_id。两者互不影响。
    |
    | 取法:跟 @BotFather 建一个 bot 拿 token;把 bot 拉进一个只有你在的群
    | (或直接私聊它),然后访问
    |   https://api.telegram.org/bot<TOKEN>/getUpdates
    | 从返回里读 chat.id(群是负数)。
    |
    | 不配则【静默不发】—— 不会让定时任务失败。
    */
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'alert_chat_id' => env('TELEGRAM_ALERT_CHAT_ID'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // dest 候选生成的排名表缓存位置。留空走 storage/app/tranco-top1m.csv。
    // `[!]` 测试会覆盖它 —— 不能让测试去写真实那份缓存。
    'dest_candidates' => [
        'ranking_path' => env('DEST_RANKING_PATH'),
    ],

];
