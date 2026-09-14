#!/usr/bin/env bash
#
# 91vpn 的备份：数据库 + .env。
#
# `[!!]` 写这个脚本的直接原因：**线上库至今没有任何自动备份**。
# 宿主 crontab 里唯一那条备份任务备的是 relaypanel —— 而它已经并进 91vpn、
# 容器早就退出了。那条 cron 每天照跑，每天往日志里写一行
# `service "relayapp" is not running`，**没人看**。
# 一个只在失败时沉默的备份，等于没有备份。
#
# 所以这里的三条要求同等重要：
#   1. 真的备到了（校验 dump 内容，不是只看命令退出码）
#   2. 失败要吵（非零退出 + 把失败状态写回面板，让它出现在「上线自检」卡片上）
#   3. 成功也要留痕（让"多久没成功过"这个问题答得上）
#
# .env 必须一起备：里面有 APP_KEY，丢了它数据库里加密过的字段就解不开了。
# 因此产物含密钥 —— 目录与文件一律 600/700。
#
#   bash tools/backup.sh                  备份到 ~/backups/91vpn
#   bash tools/backup.sh /path/to/dir     备份到指定目录
#   bash tools/backup.sh --install-cron   装成每日自动（04:40）
#   bash tools/backup.sh --verify [文件]  把备份恢复到独立库、逐表比对行数
#
# 恢复见本文件末尾的注释。
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
COMPOSE=(docker compose -f "$ROOT/docker-compose.yml")
DB_NAME=${DB_NAME:-vpn}
KEEP_DAYS=${KEEP_DAYS:-30}
KEEP_MIN=${KEEP_MIN:-7}      # 无论多旧，至少留这么多份

if [ "${1:-}" = "--install-cron" ]; then
    LOG="$HOME/backups/91vpn-backup.log"
    LINE="40 4 * * * bash $ROOT/tools/backup.sh >> $LOG 2>&1"
    if crontab -l 2>/dev/null | grep -Fq "91vpn/tools/backup.sh"; then
        echo "已存在，跳过"
    else
        mkdir -p "$HOME/backups"
        (crontab -l 2>/dev/null; echo "$LINE") | crontab -
        echo "已装：$LINE"
    fi
    exit 0
fi

# ── --verify：把一份备份恢复到独立库，逐表比对行数 ───────────────
#
# `[!!]` 没恢复过的备份不算备份。这个模式存在的理由就是这句话 ——
# dump 文件看起来没问题、大小也对，而真正要用的时候才发现导不回去，
# 那个时刻是你最没有余地的时刻。
#
# `[!]` dump 带 --databases，头部有 `CREATE DATABASE vpn` 和 `USE \`vpn\``。
# 直接灌会【覆盖生产库】。所以先把这两类语句删掉，删完【再验一次】确实没有了，
# 然后才把库名显式写在 mysql 命令行上。两道，因为这一步错了是不可逆的。
if [ "${1:-}" = "--verify" ]; then
    FILE=${2:-$HOME/backups/91vpn/latest.tar.gz}
    [ -f "$FILE" ] || { echo "找不到 $FILE" >&2; exit 1; }
    SCRATCH=vpn_restore_check
    W=$(mktemp -d); trap 'rm -rf "$W"' EXIT
    tar -xzf "$FILE" -C "$W"
    [ -f "$W/db.sql" ] || { echo "包里没有 db.sql" >&2; exit 1; }

    sed -E '/^CREATE DATABASE/d; /^USE `/d' "$W/db.sql" > "$W/safe.sql"
    if grep -qE '^(CREATE DATABASE|USE )' "$W/safe.sql"; then
        echo "过滤后仍有库级语句，中止 —— 再灌下去可能覆盖生产库" >&2; exit 1
    fi

    "${COMPOSE[@]}" exec -T db sh -c \
        "exec mysql -uroot -p\"\$MYSQL_ROOT_PASSWORD\" -e \
         'DROP DATABASE IF EXISTS $SCRATCH; CREATE DATABASE $SCRATCH'" 2>/dev/null
    "${COMPOSE[@]}" exec -T db sh -c \
        "exec mysql -uroot -p\"\$MYSQL_ROOT_PASSWORD\" $SCRATCH" < "$W/safe.sql" 2>/dev/null \
        || { echo "恢复失败 —— 这份备份用不了" >&2; exit 1; }

    BAD=0
    for t in users orders nodes audit_logs balance_logs plans daily_traffic; do
        read -r a b < <("${COMPOSE[@]}" exec -T db sh -c \
            "exec mysql -uroot -p\"\$MYSQL_ROOT_PASSWORD\" -N -e \
             'SELECT (SELECT COUNT(*) FROM $DB_NAME.$t), (SELECT COUNT(*) FROM $SCRATCH.$t)'" 2>/dev/null)
        if [ "$a" = "$b" ]; then printf '  %-14s 生产 %-7s 恢复 %-7s OK\n' "$t" "$a" "$b"
        else printf '  %-14s 生产 %-7s 恢复 %-7s 不一致\n' "$t" "$a" "$b"; BAD=1; fi
    done
    "${COMPOSE[@]}" exec -T db sh -c \
        "exec mysql -uroot -p\"\$MYSQL_ROOT_PASSWORD\" -e 'DROP DATABASE $SCRATCH'" 2>/dev/null
    [ "$BAD" = 0 ] && echo "$(basename "$FILE") 可恢复" || { echo "这份备份对不上" >&2; exit 1; }
    exit 0
fi

DEST=${1:-$HOME/backups/91vpn}
mkdir -p "$DEST"; chmod 700 "$DEST"
STAMP=$(date +%Y%m%d-%H%M%S)
WORK=$(mktemp -d); chmod 700 "$WORK"

# 把结果写回面板。成功失败都写 —— "从来没成功过"和"昨天开始失败"要分得开。
record() { # $1=ok|fail  $2=说明
    "${COMPOSE[@]}" exec -T app php artisan backup:record \
        --status="$1" --detail="$2" >/dev/null 2>&1 || \
        echo "[warn] 状态没写回面板（app 容器不可达？）——备份本身的结果见上一行" >&2
}

fail() {
    echo "[$(date +%F' '%T)] 备份失败：$1" >&2
    record fail "$1"
    rm -rf "$WORK"
    exit 1
}
trap 'fail "脚本异常退出（第 $LINENO 行）"' ERR

# ── 1. 数据库 ────────────────────────────────────────────────────
# `[!]` 密码不出容器:用 db 容器自己的环境变量,宿主 shell 与 ps 里都看不到它。
# --single-transaction 让 InnoDB 在一致快照上导出,不锁表(线上要能随时跑)。
"${COMPOSE[@]}" exec -T db sh -c \
    "exec mysqldump -uroot -p\"\$MYSQL_ROOT_PASSWORD\" \
        --single-transaction --quick --routines --events --no-tablespaces \
        --databases $DB_NAME" > "$WORK/db.sql" 2>"$WORK/db.err" \
    || fail "mysqldump 退出非零：$(tail -2 "$WORK/db.err" | tr '\n' ' ')"

# `[!!]` 只看退出码是不够的 —— 连不上、权限不足、库名写错,都可能得到一个
# 语法完整但【没有数据】的文件。校验三件事:
#   有 CREATE TABLE、关键表在里面、尾部有 mysqldump 自己写的完成标记。
# 少了第三条就区分不出"导到一半被掐断"。
SIZE=$(wc -c < "$WORK/db.sql")
[ "$SIZE" -gt 10240 ] || fail "dump 只有 ${SIZE} 字节，不像是完整的库"
grep -q "CREATE TABLE" "$WORK/db.sql" || fail "dump 里没有任何 CREATE TABLE"
for t in users orders nodes audit_logs; do
    grep -q "CREATE TABLE \`$t\`" "$WORK/db.sql" || fail "dump 里缺表 $t"
done
tail -5 "$WORK/db.sql" | grep -q "Dump completed" \
    || fail "dump 尾部没有完成标记 —— 多半是导到一半被掐断了"

# ── 2. .env（含 APP_KEY，没有它加密字段解不开）──────────────────
[ -f "$ROOT/.env" ] || fail "找不到 .env"
cp "$ROOT/.env" "$WORK/env"

# ── 3. 打包 ──────────────────────────────────────────────────────
OUT="$DEST/91vpn-$STAMP.tar.gz"
tar -czf "$OUT.tmp" -C "$WORK" db.sql env    # 先写 .tmp
chmod 600 "$OUT.tmp"
mv "$OUT.tmp" "$OUT"                          # 原子替换:半截文件不会被当成一份备份
ln -sfn "$(basename "$OUT")" "$DEST/latest.tar.gz"
rm -rf "$WORK"

# ── 4. 轮转 ──────────────────────────────────────────────────────
# `[!]` 先按份数保底再按天数删 —— 只按天数的话,停机超过保留期回来一看,
# 会把仅存的几份一起删掉。
mapfile -t ALL < <(ls -1t "$DEST"/91vpn-*.tar.gz 2>/dev/null || true)
if [ "${#ALL[@]}" -gt "$KEEP_MIN" ]; then
    for f in "${ALL[@]:$KEEP_MIN}"; do
        [ -n "$(find "$f" -mtime +"$KEEP_DAYS" 2>/dev/null)" ] && rm -f "$f"
    done
fi

HUMAN=$(du -h "$OUT" | cut -f1)
echo "[$(date +%F' '%T)] 备份完成：$OUT（$HUMAN，共 $(ls -1 "$DEST"/91vpn-*.tar.gz | wc -l) 份）"
record ok "$(basename "$OUT") $HUMAN"

# ── 恢复 ────────────────────────────────────────────────────────
# tar -xzf 91vpn-<stamp>.tar.gz -C /tmp/restore
# docker compose exec -T db sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD"' \
#     < /tmp/restore/db.sql
# cp /tmp/restore/env .env && docker compose restart app scheduler
#
# `[!]` dump 带 --databases,所以它自己会 CREATE DATABASE / USE,
# 不需要先手工建库;但也意味着它会【覆盖同名库】—— 恢复前先确认你要覆盖。
