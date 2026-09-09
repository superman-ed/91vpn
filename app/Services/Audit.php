<?php

namespace App\Services;

/**
 * 中转侧（规则/出站）的操作审计。
 *
 * [!] 本类由 relaypanel 搬入（ADR-008），但**落点改了**：relaypanel 有自己的
 * audit_logs（带 admin_name / target_name / changes JSON 列）和自己的 /audit 页。
 * 并进 91vpn 后不能再开第二条审计链 —— 后台已经有 audit_logs 和
 * system/audit 页，两张表就意味着查事故时要翻两个地方，而人只会翻一个。
 * 所以这里保留 diff + 脱敏，写入沿用 91vpn 的 audit() helper。
 *
 * [!!] 搬控制器时【漏了这个类】：RelayRuleController 一直在 `use App\Services\Audit`，
 * 而 91vpn 里没有 —— 规则的增/改/删/换凭据/生成密钥对全都 Class not found 500。
 * 测试只覆盖了列表页能打开，没覆盖写入，所以 481 个测试全绿也没发现。
 *
 * [!!] 记的是**变更内容**，不只是"某某被修改了"。这立刻带来一个问题：规则里有
 * REALITY 私钥、节点间凭据。这些绝不能进日志 —— 日志读者范围比配置页广，
 * 而且常被整表导出、贴进工单。见 SENSITIVE 与 redact()。
 */
class Audit
{
    /**
     * 字段名里出现这些片段就脱敏。
     *
     * [!] 用**片段匹配**而不是精确列名：字段会增加（今天是 inbound_cred，
     * 明天可能是 out_cred_v2），精确列表迟早漏掉一个，而漏掉的那次不会有
     * 任何报错 —— 密钥就那么写进去了。宁可多脱敏一些无关字段。
     */
    private const SENSITIVE = [
        'secret', 'key', 'password', 'passwd', 'token', 'cred', 'uuid', 'private',
    ];

    /** 记一条。$before / $after 传变更前后的属性数组。 */
    public static function log(
        string $action,
        string $targetType = '',
        ?int $targetId = null,
        string $targetName = '',
        array $before = [],
        array $after = [],
    ): void {
        self::write($action, $targetType, $targetId, $targetName, self::diff($before, $after));
    }

    /**
     * 变更内容已经算好时用这个。
     *
     * [!] 有这个入口是因为某些改动（比如整体替换出站）没法用 before/after
     * 属性数组表达 —— 逐条 diff 会因为 id 变了而全是噪声。
     */
    public static function logChanges(
        string $action,
        string $targetType,
        ?int $targetId,
        string $targetName,
        array $changes,
    ): void {
        self::write($action, $targetType, $targetId, $targetName, $changes);
    }

    private static function write(
        string $action, string $targetType, ?int $targetId,
        string $targetName, array $changes,
    ): void {
        // 91vpn 的 audit_logs 没有 changes JSON 列，description 是 varchar(500)。
        // 把变更渲染成一行人话塞进 description —— 信息量不如 JSON 列，但胜在
        // 与其它后台操作在同一条时间线上；真需要结构化 diff 再加列不迟。
        // 91vpn 的 audit_logs 没有 changes JSON 列，description 是 varchar(500)。
        // 把变更渲染成一行人话塞进 description —— 信息量不如 JSON 列，但胜在
        // 与其它后台操作在同一条时间线上；真需要结构化 diff 再加列不迟。
        //
        // [!] 不走 audit() helper：它用 class_basename($target) 定 target_type，
        // 而中转这边传的是 ('rule', 12) 这样的类型+id，没有模型可传。
        // 硬凑一个匿名类进去，target_type 会变成 "Audit.php:130$0@anonymous" 那种东西。
        \App\Models\AuditLog::create([
            'admin_id' => auth()->id(),
            'action' => $action,
            'description' => \Illuminate\Support\Str::limit(
                $targetName.($changes ? '：'.self::render($changes) : ''), 490, ''
            ),
            'target_type' => $targetType !== '' ? $targetType : null,
            'target_id' => $targetId,
            'ip' => request()?->ip() ?? '',
        ]);
    }

    /** 把 diff 渲染成 "端口 1234→5678；备注 (空)→x" 这样的一行。 */
    private static function render(array $changes): string
    {
        $parts = [];
        foreach ($changes as $k => $c) {
            $from = self::scalar($c['from'] ?? null);
            $to = self::scalar($c['to'] ?? null);
            $parts[] = "{$k} {$from}→{$to}";
        }

        return implode('；', $parts);
    }

    private static function scalar(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '(空)';
        }
        if (is_bool($v)) {
            return $v ? '是' : '否';
        }
        if (is_array($v)) {
            return \Illuminate\Support\Str::limit((string) json_encode($v, JSON_UNESCAPED_UNICODE), 60, '…');
        }

        return \Illuminate\Support\Str::limit((string) $v, 60, '…');
    }

    /**
     * 算出变更的字段，并脱敏。
     *
     * [!] 只记**变了的**字段。全量记的话，改一个端口会产生三十行噪声，
     * 而真正要看的那一行淹没在里面。
     */
    public static function diff(array $before, array $after): array
    {
        $out = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $k) {
            if (in_array($k, ['updated_at', 'created_at'], true)) {
                continue;
            }
            $b = $before[$k] ?? null;
            $a = $after[$k] ?? null;
            if (self::same($b, $a)) {
                continue;
            }
            $out[$k] = ['from' => self::redact($k, $b), 'to' => self::redact($k, $a)];
        }

        return $out;
    }

    /**
     * [!!] 脱敏。字段名命中 SENSITIVE 就只保留"有没有值 / 变没变"，绝不记内容。
     *
     * 嵌套结构（inbound_opts 里的 reality.private_key）要递归处理 —— 只看顶层
     * 字段名的话，inbound_opts 这个名字本身不敏感，里面的私钥就会原样写进日志。
     */
    public static function redact(string $key, mixed $v): mixed
    {
        if (self::isSensitive($key)) {
            return self::mask($v);
        }
        if (is_array($v)) {
            $out = [];
            foreach ($v as $k => $item) {
                $out[$k] = is_string($k) ? self::redact($k, $item) : self::redact($key, $item);
            }

            return $out;
        }
        if (is_string($v) && self::looksLikeJson($v)) {
            $decoded = json_decode($v, true);
            if (is_array($decoded)) {
                return self::redact($key, $decoded);
            }
        }

        return $v;
    }

    private static function isSensitive(string $key): bool
    {
        $k = strtolower($key);
        foreach (self::SENSITIVE as $frag) {
            if (str_contains($k, $frag)) {
                return true;
            }
        }

        return false;
    }

    /** 只表达"有没有"，不表达是什么。 */
    private static function mask(mixed $v): string
    {
        if ($v === null || $v === '' || $v === []) {
            return '(空)';
        }

        return '(已隐藏)';
    }

    private static function looksLikeJson(string $s): bool
    {
        $s = ltrim($s);

        return $s !== '' && ($s[0] === '{' || $s[0] === '[');
    }

    /** 松散比较：表单来的都是字符串，而模型里可能是 int/bool。 */
    private static function same(mixed $a, mixed $b): bool
    {
        if (is_array($a) || is_array($b)) {
            return json_encode($a) === json_encode($b);
        }
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }

        return (string) $a === (string) $b;
    }
}
