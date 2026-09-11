#!/usr/bin/env bash
# 把 sogacore 的使用文档同步进面板，供后台「技术文档」页渲染。
#
# [!!] 文档的**唯一事实来源是 sogacore/docs/guide/**，这里是一份副本。
#   要改文档就改那边再同步回来 —— 直接改副本,下次同步会被覆盖,
#   而且改动不会进 sogacore 的版本历史。
#
# [!!] 为什么必须是副本、而不是让面板去读那个目录:面板跑在容器里,
#   sogacore 仓库不在它的挂载内;换一台机器部署时那个目录更不会存在。
#
# [!] 副本必然会过期 —— 所以把【来源提交】记进 .source.json,页面上直接显示
#   "同步自 <hash>"。这不能防止过期,但能让过期【看得见】:
#   静默过期的文档比没有文档更坏,因为人会照着它做。
#
# [!] 同步产物【要提交进版本库】。一度想把它 gitignore 掉(避免两份文档),
#   但那样:① 面板部署到没有 sogacore 的机器上时,文档页永远是空的;
#   ② 文档页的测试依赖这些文件存在,干净 clone 会全红。
#   一份带"同步自 <hash>"标记的旧副本,好过一个空页面。
#
#   bash deploy/sync-docs.sh [sogacore 仓库路径]
set -eu
SRC_REPO="${1:-/home/dev/web/sogacore}"
SRC="$SRC_REPO/docs/guide"
DST="$(cd "$(dirname "$0")/.." && pwd)/resources/docs"

[ -d "$SRC" ] || { echo "找不到文档源目录：$SRC" >&2; exit 1; }

mkdir -p "$DST"
# 先清旧副本：源里删掉的文件不该留在面板上继续被人读到。
rm -f "$DST"/*.md
cp "$SRC"/*.md "$DST"/

REV="$(git -C "$SRC_REPO" rev-parse --short HEAD 2>/dev/null || echo unknown)"
cat > "$DST/.source.json" <<JSON
{
  "source": "sogacore/docs/guide",
  "commit": "$REV",
  "synced_at": "$(date '+%Y-%m-%d %H:%M:%S')"
}
JSON

echo "已同步 $(ls -1 "$DST"/*.md | wc -l) 篇（来源提交 $REV）→ $DST"
