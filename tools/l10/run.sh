#!/usr/bin/env bash
# L-10 并发实验：两个进程同时激活同一笔排队订单。
#
# `[!!]` 顺序调两次本来就安全（查询条件 status=queued 已不匹配），
#   所以单进程测不出这条缺陷。审计指出的可达路径是【并发】：
#   两轮调度重叠，或调度器与用户点「立即结束当前套餐」撞上。
#   必须两个独立进程、各自的数据库连接。
#
#   bash tools/l10/run.sh
set -euo pipefail
DIR="$(cd "$(dirname "$0")/../.." && pwd)"
C=${REPRO_CONTAINER:-91vpn-app-1}

run1() {  # $1=本地脚本 $2=容器内文件名 其余=额外 -e
  local script=$1 name=$2; shift 2
  local tmp; tmp=$(mktemp /tmp/l10-XXXXXX.php)
  cat "$DIR/tools/repro-guard.php" > "$tmp"
  sed '1{/^<?php[[:space:]]*$/d;}' "$script" >> "$tmp"
  docker cp "$tmp" "$C:/tmp/$name" >/dev/null
  rm -f "$tmp"
  docker exec -e XDG_CONFIG_HOME=/tmp -e HOME=/tmp -e DB_DATABASE=vpn_test "$@" \
    "$C" php artisan tinker "/tmp/$name"
}

echo "=== 造数据 ==="
OUT=$(run1 "$DIR/tools/l10/seed.php" l10-seed.php 2>&1)
echo "$OUT" | grep -oE 'L10UID=[0-9]+ ORDER=[0-9]+' > /tmp/l10-ids
# shellcheck disable=SC2046
export $(cat /tmp/l10-ids)
cat /tmp/l10-ids
echo "$OUT" | grep '起点' || true

BARRIER=$(python3 -c 'import time;print(time.time()+6)')
echo
echo "=== 两个进程同时 activate 同一笔订单 ==="
run1 "$DIR/tools/l10/activate.php" l10-a.php -e "BARRIER=$BARRIER" -e "ORDER=$ORDER" > /tmp/l10-a.out 2>&1 &
PA=$!
run1 "$DIR/tools/l10/activate.php" l10-b.php -e "BARRIER=$BARRIER" -e "ORDER=$ORDER" > /tmp/l10-b.out 2>&1 &
PB=$!
wait $PA || true
wait $PB || true
for f in a b; do
  printf '  进程%s: %s\n' "${f^^}" "$(grep -E '^(delivered|skipped|err.*)$' /tmp/l10-$f.out | head -1)"
done

run1 "$DIR/tools/l10/check.php" l10-ck.php -e "L10UID=$L10UID" -e "ORDER=$ORDER" 2>&1 \
  | grep -E "剩余天数|订单状态|VERDICT" > /tmp/l10-check.out
sed 's/^/  /' /tmp/l10-check.out
V=$(grep -o 'VERDICT=[A-Z]*' /tmp/l10-check.out | tail -1 | cut -d= -f2)

echo
if [ "$V" = ONCE ]; then
  echo "  OK  并发两次只发一次货"
  exit 0
else
  echo "  BAD 发了两次货 —— 锁内复查没起作用"
  exit 1
fi
