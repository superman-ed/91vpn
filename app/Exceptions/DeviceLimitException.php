<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 账号活跃设备数已达套餐上限,且无可回收的陈旧设备。
 * 继承 RuntimeException —— SubController 已统一 catch(RuntimeException)→403,复用同一出口。
 */
class DeviceLimitException extends RuntimeException
{
    public function __construct(public int $limit)
    {
        parent::__construct("设备数已达上限({$limit} 台),请在客户端「我的设备」中移除一台后再连接。");
    }
}
