<?php

namespace App\Services;

/**
 * REALITY 的密钥与 short_id 生成。
 *
 * [!!] 必须与 xray 的 `xray x25519` 逐字节一致，否则现象是**静默失败**：
 * 节点起得来、端口通、证书也对，只有持凭据的客户端连不上，
 * 而日志里什么都没有。所以有一个跨语言的对拍测试
 * （sogacore/agent/internal/core/xray/realitykey_contract_test.go）。
 *
 * 推导取自 xray-core main/commands/all/curve25519.go：
 *   32 字节随机 → clamp（cr.yp.to/ecdh.html）→ X25519 基点乘 → base64.RawURLEncoding
 */
class Reality
{
    /** @return array{private_key: string, public_key: string} */
    public static function keypair(?string $privateKeyB64 = null): array
    {
        if ($privateKeyB64 !== null && $privateKeyB64 !== '') {
            $sk = self::b64urlDecode($privateKeyB64);
            if (strlen($sk) !== 32) {
                throw new \InvalidArgumentException('X25519 私钥必须是 32 字节');
            }
        } else {
            $sk = random_bytes(32);
        }

        // clamp。xray 明确做了这一步（"just to make sure printing the real
        // private key"）—— 不 clamp 的话我们存的私钥与 xray 实际使用的
        // 不是同一个值，人拿去 `xray x25519 -i` 复核会对不上。
        $sk[0] = chr(ord($sk[0]) & 248);
        $sk[31] = chr(ord($sk[31]) & 127);
        $sk[31] = chr(ord($sk[31]) | 64);

        $pk = sodium_crypto_scalarmult_base($sk);

        return [
            'private_key' => self::b64urlEncode($sk),
            'public_key' => self::b64urlEncode($pk),
        ];
    }

    /**
     * short_id：偶数长度的十六进制，最长 16 个字符（8 字节）。
     *
     * [!] xray 要求偶数长度 —— 它按十六进制解成字节。奇数长度会被拒。
     */
    public static function shortId(int $bytes = 4): string
    {
        return bin2hex(random_bytes(max(1, min(8, $bytes))));
    }

    /** xray 用的是 base64.RawURLEncoding：URL 字母表、无填充。 */
    private static function b64urlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'), true);
    }
}
