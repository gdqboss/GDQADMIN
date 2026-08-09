#!/bin/bash
# 2026-08-06 BUG FIX: sync SGP build dist 到 /home/gdq/dist/, 但不删 minip/labor 子目录
# 之前的 rsync -a --delete 会把 /home/gdq/dist/minip/ /home/gdq/dist/labor/ 等
# 独立部署的前端 dist 目录一起删掉, 导致 /minip 500
#
# 用法: bash scripts/sync-sgp-dist.sh [PROFILE_ID]
#   默认 PROFILE_ID=1 (wecom / gdqadmin)
#   sync /root/server/dist-$PROFILE_ID/ 的内容到 /home/gdq/dist/
#   保留 /home/gdq/dist/minip/ 等独立部署的子目录

set -e
PROFILE_ID=${1:-1}
SRC_DIR="/root/server"
DIST_SRC="$SRC_DIR/dist-$PROFILE_ID"
DIST_DST="/home/gdq/dist"

if [ ! -d "$DIST_SRC" ]; then
  echo "ERROR: $DIST_SRC 不存在, 先跑 scripts/build-for-profile.sh $PROFILE_ID"
  exit 1
fi

if [ ! -d "$DIST_DST" ]; then
  echo "ERROR: $DIST_DST 不存在"
  exit 1
fi

echo "Syncing $DIST_SRC -> $DIST_DST (excluding minip/labor 等独立前端 dist)..."

# 关键: rsync --exclude 保留独立前端的子目录
# AGENTS.md 提到的独立 dist:
#   - minip (小程序 H5)
#   - labor (SmartBiz Labor)
#   - h5-shop (历史 H5)
#   - finance (历史财务)
# 我们用 --exclude 排除这些子目录, 让它们在 sync 时不被覆盖
rsync -a \
  --exclude='minip/' \
  --exclude='labor/' \
  --exclude='h5-shop/' \
  --exclude='finance/' \
  --exclude='chat/' \
  "$DIST_SRC/" "$DIST_DST/"

echo "Sync complete:"
ls -la "$DIST_DST" | head -10
echo ""
echo "保留的独立前端 dist:"
for sub in minip labor h5-shop finance; do
  if [ -d "$DIST_DST/$sub" ]; then
    echo "  ✓ $sub/ ($(du -sh "$DIST_DST/$sub" | cut -f1))"
  fi
done