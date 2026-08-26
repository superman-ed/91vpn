<?php

namespace App\Services;

class CaptchaService
{
    /**
     * 生成算术验证码。返回题面，答案由调用方存入 session。
     *
     * @return array{question: string, answer: int}
     */
    public function make(): array
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        return [
            'question' => "{$a} + {$b} = ?",
            'answer' => $a + $b,
        ];
    }

    /**
     * 校验用户输入是否等于 session 中存的答案。
     */
    public function verify(?string $input, ?int $answer): bool
    {
        if ($input === null || $answer === null) {
            return false;
        }
        $input = trim($input);
        if (! ctype_digit($input)) {   // 非纯数字("7x"等)直接判错，不静默转 0/前缀数字
            return false;
        }

        return (int) $input === $answer;
    }
}
