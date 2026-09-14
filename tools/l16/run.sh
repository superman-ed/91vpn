#!/usr/bin/env bash
# L-16 并发实验：签到 与 加油包到账 撞在一起，谁吃掉谁。
#
# `[!!]` 必须是【真并发】—— 两个独立进程、各自的数据库连接。
#   之前的 P21-B2 是在单进程里拿一个陈旧模型模拟交错，
#   那证明的是"如果读写之间隔了一次变更会怎样",
#   而不是"这件事在真实并发下真的会发生"。
#
# 两格（同一段代码的两个分支，并发安全性不同）：
#   [D]-1 非会员：签到写【绝对值】min(读到的值 + 奖励, 封顶) → 吞掉加油包
#   [D]-2 会员：  签到走 SQL 自增 transfer_enable + 奖励      → 加油包还在
#
#   bash tools/l16/run.sh
set -euo pipefail
DIR="$(cd "$(dirname "$0")/../.." && pwd)"
C=${REPRO_CONTAINER:-91vpn-app-1}
PASS=0; FAIL=0

run1() {  # $1=本地脚本 $2=容器内文件名 其余=额外 -e
  local script=$1 name=$2; shift 2
  local tmp; tmp=$(mktemp /tmp/l16-XXXXXX.php)
  cat "$DIR/tools/repro-guard.php" > "$tmp"
  sed '1{/^<?php[[:space:]]*$/d;}' "$script" >> "$tmp"
  docker cp "$tmp" "$C:/tmp/$name" >/dev/null
  rm -f "$tmp"
  docker exec -e XDG_CONFIG_HOME=/tmp -e HOME=/tmp -e DB_DATABASE=vpn_test "$@" \
    "$C" php artisan tinker "/tmp/$name"
}

judge() {  # $1=期望 $2=实得 $3=说明
  if [ "$1" = "$2" ]; then printf '  OK  %s\n' "$3"; PASS=$((PASS+1))
  else printf '  BAD %s（期望 %s，实得 %s）\n' "$3" "$1" "$2"; FAIL=$((FAIL+1)); fi
}

echo "=== 造数据 ==="
OUT=$(run1 "$DIR/tools/l16/seed.php" l16-seed.php 2>&1)
echo "$OUT" | grep -oE 'FREE=[0-9]+ MEMBER=[0-9]+ PACK=[0-9]+' > /tmp/l16-ids
# shellcheck disable=SC2046
export $(cat /tmp/l16-ids)
cat /tmp/l16-ids

# `[!]` 过程信息一律走 stderr —— 本函数的 stdout 被 $( ) 捕获,
# 打在 stdout 上的数字会被整个吞掉,而那正是要给人看的证据。
race() {  # $1=用户 id  $2=标签
  local uid=$1 label=$2 barrier
  barrier=$(python3 -c 'import time;print(time.time()+6)')
  echo >&2
  echo "=== $label ===" >&2
  run1 "$DIR/tools/l16/checkin.php" "l16-ci-$uid.php" \
    -e "BARRIER=$barrier" -e "UID=$uid" > /tmp/l16-a.out 2>&1 &
  local pa=$!
  run1 "$DIR/tools/l16/pack.php" "l16-pk-$uid.php" \
    -e "BARRIER=$barrier" -e "UID=$uid" -e "PACK=$PACK" > /tmp/l16-b.out 2>&1 &
  local pb=$!
  wait $pa || true
  wait $pb || true
  echo "  签到进程: $(grep -cE '^done$' /tmp/l16-a.out || true) done / $(grep -c '^err' /tmp/l16-a.out || true) err" >&2
  echo "  加油包进程: $(grep -cE '^done$' /tmp/l16-b.out || true) done / $(grep -c '^err' /tmp/l16-b.out || true) err" >&2
  run1 "$DIR/tools/l16/check.php" "l16-ck-$uid.php" -e "UID=$uid" 2>&1 \
    | grep -E "最终配额|应当|VERDICT" > /tmp/l16-check.out
  sed 's/^/  /' /tmp/l16-check.out >&2
  grep -o 'VERDICT=[A-Z]*' /tmp/l16-check.out | tail -1 | cut -d= -f2
}

V=$(race "$FREE" "[D]-1 非会员：签到写绝对值" | tail -1)
judge LOST "$V" "加油包被签到覆盖掉了 —— 非会员分支不安全"

V=$(race "$MEMBER" "[D]-2 会员：签到走 SQL 自增" | tail -1)
judge KEPT "$V" "加油包还在 —— 会员分支安全（对照组）"

printf '\n通过 %d / 失败 %d\n' "$PASS" "$FAIL"
[ $FAIL = 0 ]
