#!/usr/bin/env bash
# L-12 并发实验：充值返利的幂等到底靠哪一层。
#
# `[!!]` 必须是【真并发】—— 两个独立进程、各自的数据库连接。
#   在单进程里连调两次测出来的是"顺序重放",那是另一件事:
#   顺序重放会被状态复查挡住,而真并发考验的是行锁。
#   混为一谈会得出"已经安全了"的错误结论。
#
# 两格：
#   [D]-1 现有入口 creditRecharge(行锁 + 状态复查) 并发两次 → 应当只返一次
#   [D]-2 一个忘了加锁的新调用方 applyRecharge  并发两次 → 会返两次
#
#   bash tools/l12/run.sh
set -euo pipefail
DIR="$(cd "$(dirname "$0")/../.." && pwd)"
C=${REPRO_CONTAINER:-91vpn-app-1}

# 跑一个脚本（测试库）。$1=本地脚本 $2=容器内文件名 其余=额外 -e
run1() {
  local script=$1 name=$2; shift 2
  local tmp; tmp=$(mktemp /tmp/l12-XXXXXX.php)
  cat "$DIR/tools/repro-guard.php" > "$tmp"
  sed '1{/^<?php[[:space:]]*$/d;}' "$script" >> "$tmp"
  docker cp "$tmp" "$C:/tmp/$name" >/dev/null
  rm -f "$tmp"
  docker exec -e XDG_CONFIG_HOME=/tmp -e HOME=/tmp -e DB_DATABASE=vpn_test "$@" \
    "$C" php artisan tinker "/tmp/$name"
}

seed() {
  local out; out=$(run1 "$DIR/tools/l12/seed.php" l12-seed.php 2>&1)
  echo "$out" | sed -n 's/^\(INVITER=.*\)$/\1/p' > /tmp/l12-ids
  echo "$out" | grep -E "INVITER=|返利比例" || true
  # shellcheck disable=SC2046
  export $(cat /tmp/l12-ids)
}

# 返回 ONCE / DOUBLE
check() {
  run1 "$DIR/tools/l12/check.php" l12-check.php -e "INVITER=$INVITER" -e "DOWNLINE=$DOWNLINE" \
    2>&1 | grep -E "余额|paybacks|流水|VERDICT" | tee /tmp/l12-check.out
  grep -o 'VERDICT=[A-Z]*' /tmp/l12-check.out | tail -1 | cut -d= -f2
}

PASS=0; FAIL=0
judge() {  # $1=期望 ONCE/DOUBLE  $2=实得  $3=说明
  if [ "$1" = "$2" ]; then printf '  OK  %s\n' "$3"; PASS=$((PASS+1))
  else printf '  BAD %s（期望 %s，实得 %s）\n' "$3" "$1" "$2"; FAIL=$((FAIL+1)); fi
}

parallel_two() {  # $1=脚本 $2=容器文件前缀 其余=额外 -e
  local script=$1 pre=$2; shift 2
  local barrier; barrier=$(python3 -c 'import time;print(time.time()+6)')
  run1 "$script" "$pre-a.php" -e "BARRIER=$barrier" "$@" > /tmp/l12-a.out 2>&1 &
  local pa=$!
  run1 "$script" "$pre-b.php" -e "BARRIER=$barrier" "$@" > /tmp/l12-b.out 2>&1 &
  local pb=$!
  wait $pa || true
  wait $pb || true
  echo "  进程A: $(grep -cE '^done$' /tmp/l12-a.out || true) done / $(grep -c '^err' /tmp/l12-a.out || true) err"
  echo "  进程B: $(grep -cE '^done$' /tmp/l12-b.out || true) done / $(grep -c '^err' /tmp/l12-b.out || true) err"
}

echo "=== [D]-1 现有入口 creditRecharge：行锁 + 状态复查 ==="
seed
parallel_two "$DIR/tools/l12/credit.php" l12-credit -e "RECHARGE=$RECHARGE"
V=$(check | tail -1)
judge ONCE "$V" "并发两次只到账一次 —— 行锁 + 状态复查确实挡住了"

echo
echo "=== [D]-2 一个忘了加锁的新调用方：直接 applyRecharge ==="
seed
parallel_two "$DIR/tools/l12/apply.php" l12-apply -e "DOWNLINE=$DOWNLINE"
V=$(check | tail -1)
judge DOUBLE "$V" "并发两次到账两次 —— 返利没有自己的第二道防线"

printf '\n通过 %d / 失败 %d\n' "$PASS" "$FAIL"
[ $FAIL = 0 ]
