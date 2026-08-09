#!/usr/bin/env bash
# sync-single-route.sh — 单文件同步后端路由到 macau (HK 共用 SGP 后端, 不需要)
#
# 用法:
#   bash sync-single-route.sh macau routes/xx.js
#   bash sync-single-route.sh macau middleware/yy.js
#
# 影响:
#   1. scp 单文件到 macau
#   2. macau pm2 restart gdq-server (或 soc-server 看实际名字)
#
# 不跑 vite build / 不 sync 前端 / 不动其它 server
# 适用场景: 改 1 个 route 处理逻辑, 改 1 个 middleware, 改 1 个 util

set -euo pipefail
SERVER="${1:?用法: bash sync-single-route.sh <server> <relative-path>}"
REL="${2:?用法: bash sync-single-route.sh <server> <relative-path>}"
SRC="/root/server/$REL"

if [[ ! -f "$SRC" ]]; then
  echo "❌ 源文件不存在: $SRC"
  exit 1
fi

echo "═══════════════════════════════════════════════════════════════════"
echo "  单文件同步: $REL → $SERVER"
echo "═══════════════════════════════════════════════════════════════════"

# md5 对比
SRC_MD5=$(md5sum "$SRC" | awk '{print $1}')
echo "SGP  $REL  md5: $SRC_MD5"

case "$SERVER" in
  macau)
    REMOTE_PATH="/opt/soc-server/$REL"
    REMOTE_MD5_BEFORE=$(ssh soc "md5sum $REMOTE_PATH 2>/dev/null | awk '{print \$1}'" || echo "missing")
    echo "macau $REL  md5: $REMOTE_MD5_BEFORE"

    if [[ "$SRC_MD5" == "$REMOTE_MD5_BEFORE" ]]; then
      echo "⏭  md5 一致, 不需要同步"
      exit 0
    fi

    # 确保远程目录存在
    ssh soc "mkdir -p \$(dirname $REMOTE_PATH)"
    scp "$SRC" "soc:$REMOTE_PATH"
    REMOTE_MD5_AFTER=$(ssh soc "md5sum $REMOTE_PATH | awk '{print \$1}'")
    echo "after  md5: $REMOTE_MD5_AFTER"

    if [[ "$SRC_MD5" != "$REMOTE_MD5_AFTER" ]]; then
      echo "❌ scp 后 md5 不一致, 请检查网络"
      exit 1
    fi

    # macau 重启
    echo "重启 macau server..."
    ssh soc "pm2 restart soc-server 2>/dev/null || pm2 restart gdq-server 2>/dev/null || echo 'no pm2, manual restart needed'"
    echo "✅ 同步 + 重启完成"
    ;;
  hk)
    echo "⚠️  HK 端共用 SGP 后端 (通过 wg 隧道打到 SGP 3200)"
    echo "   单 route 文件不需要 scp 到 HK, SGP 端 restart 即可"
    pm2 restart gdq-server
    echo "✅ SGP server 重启, HK 自动生效"
    ;;
  *)
    echo "❌ 未知 server: $SERVER (只支持 macau / hk)"
    exit 1
    ;;
esac