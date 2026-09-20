<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * REALITY dest 候选生成 —— 把「选 dest」从手工活变成面板上的一个按钮。
 *
 * `[!!]` 本服务【只做纯计算 + 被动 DNS】，一个字节都不往第三方发。
 * TLS/h2/X25519 那四关刻意不在这里测，交给【节点】：
 *   · 可达性与延迟是"节点到候选"的关系，面板机房的视角给不出答案
 *     （sogacore compatibility/dest-scan.md §1）；
 *   · 面板主动连几百个第三方 443，自己就成了一台可被指挥的扫描器 ——
 *     正是节点侧那套护栏（固定 443、≤30 候选、5s/60s 超时）要防的事。
 * 所以这里只负责把 100 万条缩到几十条，及格线由节点判。
 *
 * `[!!]` 不内置固定清单：所有人用同一份，那份清单本身就成了特征。
 * 每次从大数据集【随机采样】，两次跑出来的也不一样。
 *
 * `[!]` 数据源用访问量排名，不用证书透明度日志。踩过：CT log 收录的是
 * "所有签过证书的主机名"，捞出来一堆 uat/内部系统 —— 那种当 dest 比热门站
 * 更糟（不自然、且随时会关）。要的是真实的、有人访问的公开网站。
 *
 * 对照实现：sogacore tools/dest-candidates.sh（判据一致，两者可互相印证）。
 */
class DestCandidates
{
    /** Top 5000 是所有推荐列表都有的靶子；长尾多是死站或内部系统。取中段。 */
    public const RANK_MIN = 20000;

    public const RANK_MAX = 300000;

    /** 母域全球排名低于此值 = 全球品牌的地区站（yahoo.com 59、tripadvisor.com 589…）。 */
    public const BRAND_RANK = 10000;

    /** 被动 DNS 的查询次数上限 —— 同步请求里不能无限查。 */
    public const DNS_BUDGET = 150;

    private const URL = 'https://tranco-list.eu/top-1m.csv.zip';

    private const CACHE_DAYS = 7;

    private const CDN_CNAME = '/cloudflare|akamai|akadns|edgekey|edgesuite|cloudfront|fastly|azureedge|azurefd|incapdns|imperva|cdntip|qcloud|alicdn|wscdn|chinanetcenter|lxdns|cdngslb|llnwd|stackpath|bunnycdn|gcorelabs|cdn77|keycdn/i';

    /** Cloudflare 等常见 CDN 的 IP 前缀（CNAME 藏不住时的兜底）。 */
    private const CDN_IP = '/^(104\.1[6-9]\.|104\.2[0-7]\.|172\.6[4-9]\.|172\.7[01]\.|162\.159\.|188\.114\.|198\.41\.|173\.245\.|103\.21\.24|103\.22\.20|141\.101\.)/';

    /**
     * 排名表缓存位置。
     *
     * `[!]` 可配置(config/services.php 的 dest_candidates.ranking_path):
     * 测试要塞一份小的假表进来,不能去碰真实那份 —— 真实那份一旦被测试覆盖,
     * 面板上的按钮就开始返回假数据,而没有任何迹象。
     */
    public function rankingPath(): string
    {
        return (string) config('services.dest_candidates.ranking_path')
            ?: storage_path('app/tranco-top1m.csv');
    }

    public function rankingFresh(): bool
    {
        $p = $this->rankingPath();

        return is_file($p) && filesize($p) > 1_000_000
            && filemtime($p) > now()->subDays(self::CACHE_DAYS)->timestamp;
    }

    /**
     * 下载并缓存排名表（约 10MB）。
     *
     * `[!]` 首次调用较慢。调用方要自己放宽执行时限 —— 默认的 30 秒不够。
     */
    public function ensureRanking(): void
    {
        if ($this->rankingFresh()) {
            return;
        }
        $zip = tempnam(sys_get_temp_dir(), 'tranco').'.zip';
        $raw = @file_get_contents(self::URL, false, stream_context_create([
            'http' => ['timeout' => 180, 'user_agent' => 'Mozilla/5.0'],
        ]));
        if ($raw === false || strlen($raw) < 1_000_000) {
            @unlink($zip);
            throw new \RuntimeException('排名表下载失败（网络不通或对方限流）');
        }
        file_put_contents($zip, $raw);

        $za = new \ZipArchive;
        if ($za->open($zip) !== true) {
            @unlink($zip);
            throw new \RuntimeException('排名表压缩包打不开');
        }
        $inner = $za->getNameIndex(0);
        $csv = $za->getFromName($inner);
        $za->close();
        @unlink($zip);
        if ($csv === false) {
            throw new \RuntimeException('排名表压缩包里没有 CSV');
        }

        // `[!!]` 这份 CSV 是 CRLF。不去掉 \r，后面按 "\n" 切出来的每行尾部都带 \r，
        // 于是 str_ends_with($host, '.hk') 永远为假 —— 现象是"一个都没筛出来"，
        // 看着像数据源里没有，其实是行尾格式。踩过一次。
        file_put_contents($this->rankingPath(), str_replace("\r", '', $csv));
    }

    /**
     * 生成候选。
     *
     * @return array{candidates: list<string>, stats: array<string,int>}
     */
    public function generate(string $tld = '.hk', int $count = 25): array
    {
        $this->ensureRanking();

        $kept = $this->shortlist($tld);
        $stats = ['after_brand' => count($kept)];

        // `[!!]` 先打散再查 DNS：固定顺序会让每次、每个人都拿到同一批，
        // 那就又变回"共享清单"了。随机是这个功能的一部分，不是省事。
        shuffle($kept);

        $out = [];
        $seenIp = [];
        $looked = 0;
        foreach ($kept as $host) {
            if (count($out) >= $count || $looked >= self::DNS_BUDGET) {
                break;
            }
            $looked++;
            $ip = $this->resolveNonCdn($host);
            if ($ip === null || isset($seenIp[$ip])) {
                continue;   // 同 IP 只留一个：多个主机名指向同一台服务器
            }
            $seenIp[$ip] = true;
            $out[] = $host;
        }
        $stats['dns_lookups'] = $looked;
        $stats['candidates'] = count($out);

        return ['candidates' => $out, 'stats' => $stats];
    }

    /**
     * 纯筛选：排名表 → 目标 TLD 的中段 → 剔掉政府与全球品牌。
     *
     * `[!]` 这一段【不碰网络】，故可离线测。DNS 与随机采样在 generate() 里，
     * 两者分开是为了让判据本身能被断言 —— 混在一起就只能靠跑真实网络来验。
     *
     * @return list<string>
     */
    public function shortlist(string $tld): array
    {
        [$tier, $brands] = $this->scanRanking($tld);

        return $this->dropGovAndBrands($tier, $tld, $brands);
    }

    /**
     * 单遍扫过排名表，同时取两样东西。
     *
     * `[!!]` 不要把整张表读进数组：100 万行会吃掉上百 MB。这里只留
     * 目标 TLD 的那几百条 + 排名前 BRAND_RANK 的域名集合（1 万条，很小）。
     */
    private function scanRanking(string $tld): array
    {
        $tier = [];
        $brands = [];
        $fh = fopen($this->rankingPath(), 'r');
        if ($fh === false) {
            throw new \RuntimeException('排名表读不开');
        }
        while (($line = fgets($fh)) !== false) {
            $c = strpos($line, ',');
            if ($c === false) {
                continue;
            }
            $rank = (int) substr($line, 0, $c);
            $host = rtrim(substr($line, $c + 1), "\r\n");
            if ($host === '') {
                continue;
            }
            if ($rank < self::BRAND_RANK) {
                $brands[$host] = true;
            }
            if ($rank >= self::RANK_MIN && $rank < self::RANK_MAX && str_ends_with($host, $tld)) {
                $tier[] = $host;
            }
        }
        fclose($fh);

        return [$tier, $brands];
    }

    /**
     * 剔掉政府，以及全球品牌的地区站。
     *
     * `[D]` 判据：X.com.hk 的母域 X.com 若全球排名很靠前，它就是全球品牌的地区站
     * （yahoo.com 59 / tripadvisor.com 589 / underarmour.com 6389）。这类不适合：
     * 太有名，且它们的流量本就不会从一台小 VPS 出来。
     *
     * `[!!]` 这一招【抓不到银行保险】—— 反直觉但确凿：金融机构的全球母域排名
     * 反而很低（dbs.com 11755 / zurich.com 24017 / aia.com 206540），因为客户
     * 都去本地站。放宽阈值又会误伤正常候选（xtom.com 排 40228）。
     * `[D]` 政府过滤只按 .gov 这个 TLD，漏掉不在该 TLD 下的公营机构 ——
     * 实跑里漏出过香港邮政、香港电台。这两类只能靠人扫一眼，UI 上要写明。
     *
     * @param  array<string,true>  $brands
     * @return list<string>
     */
    private function dropGovAndBrands(array $tier, string $tld, array $brands): array
    {
        $out = [];
        foreach ($tier as $host) {
            if (str_ends_with($host, '.gov'.$tld) || $host === 'gov'.$tld) {
                continue;
            }
            $base = substr($host, 0, -strlen($tld));
            $base = rtrim($base, '.');
            $base = preg_replace('/\.(com|net|org|edu)$/', '', $base);
            $isBrand = false;
            foreach (['com', 'net', 'org'] as $t) {
                if (isset($brands[$base.'.'.$t])) {
                    $isBrand = true;
                    break;
                }
            }
            if (! $isBrand) {
                $out[] = $host;
            }
        }

        return $out;
    }

    /**
     * 被动 DNS：解析得到且不在 CDN 后面时返回 IP，否则 null。
     *
     * `[!]` 只查 DNS，不连对方服务器。CDN 的 dest 不适合：TLS 在众多边缘终结，
     * 指纹是共享的。节点侧的扫描也会判这一关，这里先筛是为了省节点那一轮。
     */
    private function resolveNonCdn(string $host): ?string
    {
        $rec = @dns_get_record($host, DNS_A | DNS_CNAME);
        if (! is_array($rec) || $rec === []) {
            return null;
        }
        foreach ($rec as $r) {
            $target = $r['target'] ?? '';
            if ($target !== '' && preg_match(self::CDN_CNAME, $target)) {
                return null;
            }
        }
        foreach ($rec as $r) {
            $ip = $r['ip'] ?? null;
            if ($ip === null) {
                continue;
            }
            if (preg_match(self::CDN_IP, $ip)) {
                return null;
            }

            return $ip;
        }

        return null;
    }
}
