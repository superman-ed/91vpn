#!/usr/bin/env python3
"""
91VPN 节点上报 agent —— 把节点上代理内核统计的每用户流量，按面板 mod_mu 契约上报。

鉴权:节点密钥走请求头 `X-Node-Secret`(不放 query,避免落进访问日志/反代日志);
     node_id 仍走 query(非机密)。

数据流:
  1) GET  {panel}/mod_mu/users?node_id=   头:X-Node-Secret → 拉可服务用户名单(id/uuid)
  2) 从代理内核读每用户增量流量(uplink/downlink 字节)
  3) POST {panel}/mod_mu/users/traffic?node_id=  头:X-Node-Secret  body={"data":[{user_id,u,d}]}

内核约定:生成 inbound 配置时，把每个 vmess client 的 email 设为「用户 id」
(纯数字字符串)。这样内核统计项 `user>>>{id}>>>traffic>>>uplink|downlink`
能直接映射回面板 user_id。

数据源:
  --source xray  用 `xray api statsquery -reset` 读 Xray/V2Ray Stats(增量,读后清零)
  --source mock  造随机小流量(联调用:验证 agent→面板链路，不需要真内核)

仅依赖 Python 3 标准库。部署:crontab 每分钟，或 --interval 常驻 + systemd。
"""
import argparse
import json
import os
import random
import subprocess
import sys
import time
import urllib.parse
import urllib.request


def log(msg):
    print(f"[{time.strftime('%H:%M:%S')}] {msg}", flush=True)


def http_json(url, body=None, timeout=15, extra_headers=None):
    data = json.dumps(body).encode() if body is not None else None
    headers = {"Content-Type": "application/json"} if body is not None else {}
    if extra_headers:
        headers.update(extra_headers)
    req = urllib.request.Request(url, data=data, headers=headers, method="POST" if body is not None else "GET")
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return json.loads(resp.read().decode())


def api_url(cfg, path):
    # 只把 node_id 放进 query;密钥改走 X-Node-Secret 头,避免落进访问日志
    qs = urllib.parse.urlencode({"node_id": cfg.node_id})
    return f"{cfg.panel.rstrip('/')}/mod_mu/{path}?{qs}"


def auth_headers(cfg):
    return {"X-Node-Secret": cfg.secret}


def fetch_users(cfg):
    r = http_json(api_url(cfg, "users"), extra_headers=auth_headers(cfg))
    users = r.get("users", []) if isinstance(r, dict) else []
    return {int(u["id"]): u for u in users}


# ---- 数据源：Xray/V2Ray Stats ----
def collect_xray(cfg, known_ids):
    """调用 `xray api statsquery -reset`，解析 user>>>{id}>>>traffic>>>uplink|downlink（增量）。"""
    try:
        out = subprocess.check_output(
            [cfg.xray_bin, "api", "statsquery", f"--server={cfg.xray_api}", "-reset", "user>>>"],
            stderr=subprocess.STDOUT, timeout=20,
        )
    except (subprocess.CalledProcessError, subprocess.TimeoutExpired, FileNotFoundError) as e:
        log(f"读取 xray stats 失败: {e}")
        return {}

    stats = json.loads(out.decode() or "{}").get("stat", []) or []
    acc = {}  # user_id -> [u, d]
    for s in stats:
        # name 形如: user>>>123>>>traffic>>>uplink
        parts = s.get("name", "").split(">>>")
        if len(parts) != 4 or parts[0] != "user" or parts[2] != "traffic":
            continue
        try:
            uid = int(parts[1])
        except ValueError:
            continue
        if uid not in known_ids:
            continue
        val = int(s.get("value", 0) or 0)
        slot = acc.setdefault(uid, [0, 0])
        if parts[3] == "uplink":
            slot[0] += val
        elif parts[3] == "downlink":
            slot[1] += val
    return acc


# ---- 数据源：mock（联调）----
def collect_mock(cfg, known_ids):
    acc = {}
    for uid in list(known_ids)[: cfg.mock_users]:
        acc[uid] = [random.randint(1_000_000, 50_000_000), random.randint(5_000_000, 300_000_000)]
    return acc


# ---- backlog：POST 失败的增量落地暂存，防止内核已清零 → 数据永久丢失 ----
def load_backlog(cfg):
    try:
        with open(cfg.backlog, "r") as f:
            return {int(k): v for k, v in json.load(f).items()}
    except (FileNotFoundError, ValueError):
        return {}


def save_backlog(cfg, acc):
    try:
        with open(cfg.backlog, "w") as f:
            json.dump({str(k): v for k, v in acc.items()}, f)
    except OSError as e:
        log(f"backlog 落地失败(下次内核增量仍会保留在内核侧计数): {e}")


def merge(a, b):
    out = {uid: list(ud) for uid, ud in a.items()}
    for uid, ud in b.items():
        slot = out.setdefault(uid, [0, 0])
        slot[0] += ud[0]
        slot[1] += ud[1]
    return out


def report(cfg, acc):
    data = [{"user_id": uid, "u": ud[0], "d": ud[1]} for uid, ud in acc.items() if ud[0] or ud[1]]
    if not data:
        return True
    if cfg.dry_run:
        log(f"[dry-run] 将上报 {len(data)} 条: {data[:3]}{' ...' if len(data) > 3 else ''}")
        return True
    r = http_json(api_url(cfg, "users/traffic"), {"data": data}, extra_headers=auth_headers(cfg))
    ok = isinstance(r, dict) and r.get("ret") == 1
    log(f"{'已上报' if ok else '上报未确认'} {len(data)} 条，面板回执: {r}")
    return ok


def tick(cfg):
    users = fetch_users(cfg)
    if not users:
        log("用户名单为空(检查 node_id/secret/面板地址)")
        return
    collector = collect_mock if cfg.source == "mock" else collect_xray
    fresh = collector(cfg, set(users.keys()))       # 本轮从内核读到的增量(已清零)
    backlog = load_backlog(cfg)                       # 上轮 POST 失败暂存的增量
    acc = merge(backlog, fresh)

    try:
        ok = report(cfg, acc)
    except Exception as e:
        ok = False
        log(f"上报异常: {e}")

    if ok:
        if backlog:
            log(f"backlog 已随本轮补报清空({len(backlog)} 条)")
        save_backlog(cfg, {})                         # 成功 → 清空
    else:
        save_backlog(cfg, acc)                        # 失败 → 落地，下轮合并重报，绝不丢
        log(f"本轮上报失败，{len(acc)} 条增量已暂存 backlog，下轮重试")


def main():
    p = argparse.ArgumentParser(description="91VPN 节点流量上报 agent")
    p.add_argument("--panel", default=os.getenv("PANEL_URL", "http://127.0.0.1:8088"))
    p.add_argument("--node-id", type=int, default=int(os.getenv("NODE_ID", "1")))
    p.add_argument("--secret", default=os.getenv("NODE_SECRET", ""))
    p.add_argument("--source", choices=["xray", "mock"], default=os.getenv("SOURCE", "xray"))
    p.add_argument("--xray-bin", default=os.getenv("XRAY_BIN", "xray"))
    p.add_argument("--xray-api", default=os.getenv("XRAY_API", "127.0.0.1:10085"))
    p.add_argument("--interval", type=int, default=int(os.getenv("INTERVAL", "60")), help="常驻上报间隔秒；0=只跑一次")
    p.add_argument("--jitter", type=float, default=float(os.getenv("JITTER", "10")), help="每轮额外随机抖动秒(多节点错峰)")
    p.add_argument("--mock-users", type=int, default=5)
    p.add_argument("--backlog", default=os.getenv("BACKLOG", "/tmp/vpn-agent-backlog.json"), help="上报失败暂存文件(防丢增量)")
    p.add_argument("--dry-run", action="store_true", help="只打印不真正 POST")
    cfg = p.parse_args()

    if not cfg.secret:
        sys.exit("缺少 --secret(节点密钥)")

    log(f"agent 启动 node_id={cfg.node_id} source={cfg.source} panel={cfg.panel} interval={cfg.interval}s")
    while True:
        try:
            tick(cfg)
        except Exception as e:  # 单次失败不退出，下个周期重试
            log(f"本轮异常: {e}")
        if cfg.interval <= 0:
            break
        # 错峰抖动：多节点避免整点同时打面板
        time.sleep(cfg.interval + random.uniform(0, cfg.jitter))


if __name__ == "__main__":
    main()
