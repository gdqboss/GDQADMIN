#!/usr/bin/env bash
# sync-single-dist-file.sh — 单文件同步前端 dist 到 HK / SGP
#
# 用法:
#   bash sync-single-dist-file.sh hk minip/assets/index-AbCdEf.js
#   bash sync-single-dist-file.sh sgp assets/index-AbCdEf.js
#
# 适用场景:
#   - 微调 CSS (颜色 / 字号)
#   - 微调 HTML 文案 (前提: 没被 vue-i18n runtime 渲染)
#   - 改字体 woff2
#   - 改 favicon / logo
#
# 不适用:
#   - 改 .vue 源文件 → 必须 vite build
#   - 改路由 → 必须重新 chunk
#   - 改 i18n 字典 → 必须 vite build
#   - 加新依赖 → 必须 npm install + vite build
#
# 警告:
#   - 下次 vite build 会覆盖这个改动
#   - 只适合临时 hot-fix / 调试 / A/B 测试

set -euo pipefail
SERVER="${1:?用法: bash sync-single-dist-file.sh <server> <rel-path>}"
REL="${2:?用法: bash sync-single-dist-file.sh <server> <rel-path>}"

echo "═══════════════════════════════════════════════════════════════════"
echo "  单 dist 文件同步: $REL → $SERVER"
echo "═══════════════════════════════════════════════════════════════════"
echo "⚠️  注意: 下次 vite build 会覆盖此改动, 仅用于临时 hot-fix"

# 源 dist 路径
case "$SERVER" in
  sgp)
    SRC="/home/gdq/dist/$REL"
    if [[ ! -f "$SRC" ]]; then
      echo "❌ 源文件不存在: $SRC"
      exit 1
    fi
    SRC_MD5=$(md5sum "$SRC" | awk '{print $1}')
    echo "SGP  $REL  md5: $SRC_MD5  (本地源, 无需同步)"
    echo "⏭  已在 SGP, 不需要同步"
    exit 0
    ;;
  hk)
    SRC="/root/server/dist-1/$REL"
    # 也允许从 SGP /home/gdq/dist 取
    [[ ! -f "$SRC" ]] && SRC="/home/gdq/dist/$REL"

    if [[ ! -f "$SRC" ]]; then
      echo "❌ 源文件不存在: $SRC"
      exit 1
    fi

    # HK 用哪个 dist?  根据 REL 前缀判断
    case "$REL" in
      minip/*) REMOTE_PATH="/var/www/hatch/$REL" ;;
      admin/*) REMOTE_PATH="/var/www/hatch/$REL" ;;
      labor/*) REMOTE_PATH="/var/www/hatch/$REL" ;;
      portal/*) REMOTE_PATH="/var/www/hatch/$REL" ;;
      fonts/*) REMOTE_PATH="/var/www/hatch/$REL" ;;
      assets/*|index.html|module-manifest.json) REMOTE_PATH="/var/www/hatch/$REL" ;;
      *)
        echo "❌ 无法判断 HK dist 归属路径: $REL"
        echo "   例: minip/.../xxx.js → /var/www/hatch/minip/.../xxx.js"
        exit 1
        ;;
    esac

    SRC_MD5=$(md5sum "$SRC" | awk '{print $1}')
    REMOTE_MD5_BEFORE=$(ssh hk-incubator "md5sum $REMOTE_PATH 2>/dev/null | awk '{print \$1}'" || echo "missing")
    echo "SGP  $SRC"
    echo "  md5: $SRC_MD5"
    echo "HK   $REMOTE_PATH"
    echo "  md5: $REMOTE_MD5_BEFORE"

    if [[ "$SRC_MD5" == "$REMOTE_MD5_BEFORE" ]]; then
      echo "⏭  md5 一致, 不需要同步"
      exit 0
    fi

    # 确保远程目录
    ssh hk-incubator "mkdir -p \$(dirname $REMOTE_PATH)"
    scp "$SRC" "hk-incubator:$REMOTE_PATH"
    REMOTE_MD5_AFTER=$(ssh hk-incubator "md5sum $REMOTE_PATH | awk '{print \$1}'")
    echo "after md5: $REMOTE_MD5_AFTER"

    if [[ "$SRC_MD5" != "$REMOTE_MD5_AFTER" ]]; then
      echo "❌ scp 后 md5 不一致, 请检查网络"
      exit 1
    fi
    echo "✅ 同步完成 (HK 4 应用 dist: minip/admin/labor/portal)"
    ;;
  macau)
    echo "⚠️  macau 用自己的 vite build 产物 (独立后端 + 独立前端)"
    echo "   单 dist 文件 sync 风险高: macau 端的 chunk hash 跟 SGP 不一样"
    echo "   推荐走全量: bash sync-sgp-dist.sh 1 (但这其实是 SGP 的)"
    echo "   实际场景: 改 macau 端走 sync-soc-server.sh 全量"
    exit 1
    ;;
  *)
    echo "❌ 未知 server: $SERVER (只支持 sgp / hk / macau)"
    exit 1
    ;;
esac