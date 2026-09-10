#!/usr/bin/env bash
#
# 把 91vpn 面板挂到一条 Cloudflare named tunnel 上，装成 systemd 服务。
#
# 用法（需要 root）：
#   sudo bash deploy/install-tunnel.sh /path/to/token.txt
#
# token 从【文件】读，不走命令行也不走环境变量：
#   --token 会让 token 出现在进程命令行里，本机任何用户 ps 一下就能看到，
#   拿到它等于拿到这条隧道的控制权。--token-file 没有这个问题。
set -euo pipefail

TOKEN_SRC="${1:?用法: sudo bash deploy/install-tunnel.sh <token 文件>}"
[ -s "$TOKEN_SRC" ] || { echo "token 文件是空的或不存在: $TOKEN_SRC" >&2; exit 1; }
# `[!!]` 找 cloudflared，并确保它在【系统位置】。
#
# 常见情形：它被装在某个用户的 ~/.local/bin 下，root 的 PATH 里没有，
# 于是这里报"找不到"。就算加上绝对路径也不该直接用 ——
# 服务以 cfpanel 身份运行，而那个路径在别人的家目录里：
# 家目录可能不可遍历（服务起不来），更要紧的是那个用户能随时替换掉
# 二进制，等于把服务的执行权交给了他。
#
# 所以：找到之后复制一份到 /usr/local/bin，root 属主。
BIN="$(command -v cloudflared 2>/dev/null || true)"
if [ -z "$BIN" ]; then
    for c in /usr/local/bin/cloudflared /usr/bin/cloudflared /home/*/.local/bin/cloudflared; do
        [ -x "$c" ] && { BIN="$c"; break; }
    done
fi
[ -n "$BIN" ] || { echo "找不到 cloudflared。装一个：https://pkg.cloudflare.com/" >&2; exit 1; }

case "$BIN" in
    /usr/local/bin/*|/usr/bin/*|/bin/*|/usr/sbin/*|/sbin/*) ;;
    *)
        echo "cloudflared 在 $BIN（非系统位置），复制到 /usr/local/bin"
        install -m 0755 -o root -g root "$BIN" /usr/local/bin/cloudflared
        BIN=/usr/local/bin/cloudflared
        ;;
esac
echo "使用 $BIN（$("$BIN" --version 2>&1 | head -1)）"

# 专用系统用户。不用 DynamicUser：它的 uid 每次启动都变，没法给
# token 文件一个稳定的属主，只能把文件放宽到所有人可读 —— 那就白费了
# "不把 token 放进命令行"这件事，本机任何用户 cat 一下就拿到了。
id -u cfpanel >/dev/null 2>&1 || useradd --system --no-create-home --shell /usr/sbin/nologin cfpanel

install -d -m 0750 -o root -g cfpanel /etc/cloudflared
install -m 0640 -o root -g cfpanel /dev/null /etc/cloudflared/91vpn.token
tr -d ' \t\r\n' < "$TOKEN_SRC" > /etc/cloudflared/91vpn.token
chown root:cfpanel /etc/cloudflared/91vpn.token
chmod 0640 /etc/cloudflared/91vpn.token

# `[!!]` 从粘贴的内容里【提取】token，而不是要求对方粘得刚刚好。
#
# Cloudflare 后台给你复制的就是整条安装命令
# （`cloudflared service install eyJ...`），粘全条是最自然的操作。
# 早先这里是"不以 eyJ 开头就报错退出"，结果是让人反复重试 ——
# 把可预见的输入形态当成用户的错误，是脚本的问题不是人的问题。
#
# 连接器 token 是一段 base64url 的 JSON，恒以 eyJ 开头，且不含空格，
# 所以从任意文本里把它切出来是可靠的。
extracted=$(grep -oE 'eyJ[A-Za-z0-9_=-]{50,}' /etc/cloudflared/91vpn.token | head -1 || true)
if [ -n "$extracted" ]; then
  printf '%s' "$extracted" > /etc/cloudflared/91vpn.token
  # `[!!]` 必须是 0640 + cfpanel 组，不是 0600 ——
  # 服务以 cfpanel 身份运行，0600 意味着它读不到自己的 token，
  # 表现为 "Failed to read token file: permission denied"。
  chown root:cfpanel /etc/cloudflared/91vpn.token
  chmod 0640 /etc/cloudflared/91vpn.token
else
  echo "在 token 文件里找不到连接器 token。" >&2
  echo "它是一段以 eyJ 开头的长字符串；把 Cloudflare 给你的那条命令整条粘进来也行。" >&2
  exit 1
fi

cat > /etc/systemd/system/cloudflared-91vpn.service <<UNIT
[Unit]
Description=Cloudflare Tunnel for the 91vpn panel
After=network-online.target
Wants=network-online.target

[Service]
Type=notify
# [!] 用 --token-file 而不是 --token：后者会把 token 暴露在进程命令行里。
ExecStart=$BIN --no-autoupdate tunnel run --token-file /etc/cloudflared/91vpn.token
Restart=always
RestartSec=5
# 隧道只需要出网和读那一个文件，其余一律收紧。
User=cfpanel
Group=cfpanel
NoNewPrivileges=yes
PrivateTmp=yes
ProtectSystem=strict
ProtectHome=yes
ReadOnlyPaths=/etc/cloudflared
CapabilityBoundingSet=
RestrictAddressFamilies=AF_INET AF_INET6 AF_UNIX

[Install]
WantedBy=multi-user.target
UNIT

# `[!!]` 装之前先确认服务用户【真的读得到】token。
#
# 加这一步是因为踩过：前面设好了 0640:cfpanel，后面一句 chmod 600 又把它
# 改回 root-only，而脚本一路"成功"，直到 systemd 起不来才发现。
# 权限这种东西不能靠"我设过了"，要靠"以那个身份试一次"。
if ! sudo -u cfpanel test -r /etc/cloudflared/91vpn.token; then
    echo "cfpanel 读不到 token 文件 —— 权限设错了：" >&2
    ls -l /etc/cloudflared/91vpn.token >&2
    exit 1
fi
echo "已确认 cfpanel 可读 token"

systemctl daemon-reload
systemctl enable --now cloudflared-91vpn.service
sleep 4
systemctl --no-pager --lines=12 status cloudflared-91vpn.service || true

echo
echo "装好了。接下来："
echo "  1. Zero Trust 后台确认 Public Hostname 指向 HTTP 127.0.0.1:8088"
echo "  2. [!!] Access 只能套在 /admin 上 —— 这个面板同时服务【用户】和【节点】,"
echo "         整站套 Access 会把节点心跳(/mod_mu/*)和用户订阅(/sub/*)全挡掉"
echo "  3. 改 .env 的 APP_URL,然后 php artisan config:clear"
echo "  4. 逐台更新节点 agent.conf 的 webapi_url 并重启 agent(不然节点会失联)"

# 清理上一条隧道的残留:它在 Cloudflare 那边已经删了,token 也就作废了,
# 留着一个 enabled 却起不来的服务只会在每次开机时刷错误日志。
if systemctl list-unit-files 2>/dev/null | grep -q '^cloudflared-relaypanel\.service'; then
    echo
    echo "==> 发现旧的 cloudflared-relaypanel 服务,停用并清理(隧道已在 CF 删除,token 作废)"
    systemctl disable --now cloudflared-relaypanel.service >/dev/null 2>&1 || true
    rm -f /etc/systemd/system/cloudflared-relaypanel.service /etc/cloudflared/relaypanel.token
    systemctl daemon-reload
    echo "    已清理"
fi
