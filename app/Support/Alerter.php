<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 运维告警发送（Telegram Bot）。
 *
 * `[!!]` 它的第一条纪律是【永不抛异常、永不阻塞调用方】。
 * 调用它的是 health:sample —— 一个每 5 分钟跑一次、负责维护存活区段的定时任务。
 * 告警发不出去是小事;因为告警发不出去而让采样整轮失败、区段记不上,
 * 那是把一个通知问题升级成了数据问题。所以这里 try/catch 兜住一切并只记日志。
 *
 * `[!]` 没配 token/chat_id 时【静默不发】并返回 false。不报错是刻意的:
 * 没配告警不该让定时任务红,但 send() 的返回值让调用方能自己决定要不要记一笔。
 *
 * `[!!]` 不走队列。QUEUE_CONNECTION=redis 但 compose 里【没有 queue worker】
 * (scheduler 只跑 schedule:work)—— 派发的任务会永远躺在 Redis 里不执行。
 * 这里是定时任务上下文、不在用户请求路径上,同步发即可。
 *
 * `[!!]` 消息里【不许出现】节点 secret / api key / 订阅 token / 用户凭据。
 * Telegram 的聊天记录不受我们控制,且会被转发。
 */
class Alerter
{
    /** 发送超时（秒）。宁可发不出去，也不要拖住定时任务。 */
    public const TIMEOUT = 5;

    /** Telegram 单条消息上限 4096 字符，留出余量。 */
    public const MAX_LEN = 3500;

    public function configured(): bool
    {
        return $this->token() !== '' && $this->chatId() !== '';
    }

    /**
     * 发一条告警。
     *
     * @return bool 真的发出去了才返回 true（未配置、失败都返回 false）
     */
    public function send(string $text): bool
    {
        $text = trim($text);
        if ($text === '' || ! $this->configured()) {
            return false;
        }

        if (mb_strlen($text) > self::MAX_LEN) {
            $text = mb_substr($text, 0, self::MAX_LEN)."\n…（已截断）";
        }

        try {
            $res = Http::timeout(self::TIMEOUT)
                ->asJson()
                ->post("https://api.telegram.org/bot{$this->token()}/sendMessage", [
                    'chat_id' => $this->chatId(),
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ]);

            if ($res->successful()) {
                return true;
            }

            // `[!]` 不把响应体原样记进日志 —— 失败响应里可能回显 chat_id。
            Log::warning('告警发送失败', ['status' => $res->status()]);

            return false;
        } catch (\Throwable $e) {
            Log::warning('告警发送异常', ['error' => class_basename($e).': '.$e->getMessage()]);

            return false;
        }
    }

    private function token(): string
    {
        return trim((string) config('services.telegram.bot_token'));
    }

    private function chatId(): string
    {
        return trim((string) config('services.telegram.alert_chat_id'));
    }
}
