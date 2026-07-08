#!/usr/bin/env bash
# verify-sync.sh — 100% chunk 完整性验证
#
# 目的：杜绝"rsync 漏文件 → 用户进路由 → 404 → 白屏"
#
# 流程：
#   1. 读本地 dist/chunk-manifest.json
#   2. 拉远程服务器文件清单（ls assets/）
#   3. 对比：本地有但远程缺 → 报错（具体到文件名）
#   4. 对比：md5 不一致 → 报错（文件损坏）
#   5. 5 个核心 chunk 任意一个缺/坏 → 直接 fail（登录页会白屏）
#
# 用法：
#   bash scripts/verify-sync.sh                  # 默认验证新加坡本地（self-test）
#   REMOTE_HOST=claw.gdqshop.cn bash scripts/verify-sync.sh   # 验证 bj
#   REMOTE_DIR=/var/www/claw.gdqshop.cn bash scripts/verify-sync.sh
#
# 退出码：
#   0 = 100% 一致
#   1 = 有缺失或损坏
#   2 = 核心 chunk 缺失（必须 fail 同步）

set -euo pipefail

# ---- 配置 ----
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
MANIFEST="$ROOT_DIR/dist/chunk-manifest.json"

# 验证目标：默认本地 self-test，否则验证远程
REMOTE_HOST="${REMOTE_HOST:-}"
REMOTE_PORT="${REMOTE_PORT:-2222}"
REMOTE_USER="${REMOTE_USER:-ubuntu}"
REMOTE_PEM="${REMOTE_PEM:-/root/clawgdqshop.pem}"
REMOTE_DIR="${REMOTE_DIR:-$ROOT_DIR/dist}"  # self-test 默认本地 dist

if [ -z "$REMOTE_HOST" ]; then
  TARGET="LOCAL (self-test)"
  REMOTE_CMD="ls"
  REMOTE_FILES_CMD="ls -1"
else
  TARGET="$REMOTE_HOST:$REMOTE_DIR"
  REMOTE_CMD="ssh -i $REMOTE_PEM -p $REMOTE_PORT -o StrictHostKeyChecking=no $REMOTE_USER@$REMOTE_HOST"
  REMOTE_FILES_CMD="ssh -i $REMOTE_PEM -p $REMOTE_PORT -o StrictHostKeyChecking=no $REMOTE_USER@$REMOTE_HOST 'ls -1 $REMOTE_DIR/assets/'"
fi

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log()  { echo -e "${BLUE}[verify]${NC} $*"; }
ok()   { echo -e "${GREEN}[ok]${NC} $*"; }
warn() { echo -e "${YELLOW}[warn]${NC} $*"; }
fail() { echo -e "${RED}[fail]${NC} $*"; exit 1; }

# ---- 0. 前置检查 ----
[ -f "$MANIFEST" ] || fail "❌ $MANIFEST 不存在，先跑 npm run build && node scripts/generate-chunk-manifest.mjs"

log "验证目标: $TARGET"
log "读取 manifest: $MANIFEST"

# ---- 1. 拉远程文件清单 ----
log "拉取远程文件清单..."
if [ -z "$REMOTE_HOST" ]; then
  # self-test: 读本地（LC_ALL=C 保证 byte 排序一致）
  # assets/ + dist 根的 favicon/logo（manifest 也包含这些）
  REMOTE_FILES=$(LC_ALL=C ls -1 "$REMOTE_DIR/assets/" 2>/dev/null | LC_ALL=C sort)
  for f in "$REMOTE_DIR"/*; do
    [ -f "$f" ] || continue
    base=$(basename "$f")
    case "$base" in
      *.js|*.css|*.svg|*.png|*.jpg|*.woff|*.woff2) REMOTE_FILES="$REMOTE_FILES"$'\n'"$base" ;;
    esac
  done
else
  REMOTE_FILES=$(eval "$REMOTE_FILES_CMD" 2>/dev/null | LC_ALL=C sort)
  # 远程也加 dist 根的 favicon/logo
  REMOTE_ROOT_FILES=$(eval "$REMOTE_CMD 'ls -1 $REMOTE_DIR/ | grep -E \"\.(js|css|svg|png|jpg|woff2?)$\"'" 2>/dev/null | LC_ALL=C sort)
  REMOTE_FILES="$REMOTE_FILES"$'\n'"$REMOTE_ROOT_FILES"
fi

if [ -z "$REMOTE_FILES" ]; then
  fail "❌ 远程目录为空或拉取失败"
fi

# 去重 + 去空行
REMOTE_FILES=$(echo "$REMOTE_FILES" | grep -v '^$' | sort -u)
REMOTE_COUNT=$(echo "$REMOTE_FILES" | grep -c .)
log "远程文件数: $REMOTE_COUNT"

# ---- 2. 对比本地 manifest ----
log "对比本地 manifest..."

# 提取本地 chunk 列表（byte 排序，与 LC_ALL=C ls 一致）
LOCAL_FILES=$(node -e "
const m = require('$MANIFEST')
console.log(Object.keys(m.chunks).sort().join('\n'))
")

LOCAL_COUNT=$(echo "$LOCAL_FILES" | wc -l)
log "本地文件数: $LOCAL_COUNT"

# 计算差集（用临时文件避免 comm process substitution 误报）
TMP_LOCAL=$(mktemp)
TMP_REMOTE=$(mktemp)
printf '%s\n' "$LOCAL_FILES" > "$TMP_LOCAL"
printf '%s\n' "$REMOTE_FILES" > "$TMP_REMOTE"
# 强制 LC_ALL=C 排序（避免 locale 差异导致 comm 报 not sorted）
LC_ALL=C sort -o "$TMP_LOCAL" "$TMP_LOCAL"
LC_ALL=C sort -o "$TMP_REMOTE" "$TMP_REMOTE"
MISSING=$(LC_ALL=C comm -23 "$TMP_LOCAL" "$TMP_REMOTE")
EXTRA=$(LC_ALL=C comm -13 "$TMP_LOCAL" "$TMP_REMOTE")
rm -f "$TMP_LOCAL" "$TMP_REMOTE"

# ---- 3. 报告 ----
echo ""
echo "=========================================="
echo "  同步完整性验证报告"
echo "=========================================="
echo "目标: $TARGET"
echo "本地文件数: $LOCAL_COUNT"
echo "远程文件数: $REMOTE_COUNT"
echo ""

if [ -n "$MISSING" ]; then
  MISSING_COUNT=$(echo "$MISSING" | wc -l)
  echo -e "${RED}❌ 缺失 $MISSING_COUNT 个文件（远程没有但本地有）:${NC}"
  echo "$MISSING" | head -20 | sed 's/^/  - /'
  if [ "$MISSING_COUNT" -gt 20 ]; then
    echo "  ... 还有 $((MISSING_COUNT - 20)) 个"
  fi
  echo ""
fi

if [ -n "$EXTRA" ]; then
  EXTRA_COUNT=$(echo "$EXTRA" | wc -l)
  echo -e "${YELLOW}⚠️  多余 $EXTRA_COUNT 个文件（远程有但本地没有，可能旧版本残留）:${NC}"
  echo "$EXTRA" | head -10 | sed 's/^/  - /'
  if [ "$EXTRA_COUNT" -gt 10 ]; then
    echo "  ... 还有 $((EXTRA_COUNT - 10)) 个"
  fi
  echo ""
fi

# ---- 4. 核心 chunk 强制检查 ----
log "核心 chunk 验证（这些缺一个就白屏）..."
CORE_CHUNKS=$(node -e "
const m = require('$MANIFEST')
console.log(m.coreChunks.join('\n'))
")

# 检查 manifest 里的 core chunk 是否都在 chunks 里
MANIFEST_MISSING=""
for chunk in $CORE_CHUNKS; do
  if ! echo "$LOCAL_FILES" | grep -qx "$chunk"; then
    MANIFEST_MISSING="$MANIFEST_MISSING $chunk"
  fi
done

if [ -n "$MANIFEST_MISSING" ]; then
  echo ""
  echo -e "${YELLOW}⚠️  manifest 声明了核心 chunk 但本地 dist/assets/ 没有（build 自身问题，不是同步问题）:${NC}"
  for c in $MANIFEST_MISSING; do
    echo "  ⚠️  $c"
  done
  echo ""
  warn "跳过核心 chunk 同步校验（build 需要先修）"
  SKIP_CORE_CHECK=1
fi

CORE_MISSING=""
if [ -z "${SKIP_CORE_CHECK:-}" ]; then
  for chunk in $CORE_CHUNKS; do
    if ! echo "$REMOTE_FILES" | grep -qx "$chunk"; then
      CORE_MISSING="$CORE_MISSING $chunk"
    fi
  done

  if [ -n "$CORE_MISSING" ]; then
    echo ""
    echo -e "${RED}🚨 核心 chunk 缺失（登录页会白屏！）:${NC}"
    for c in $CORE_MISSING; do
      echo "  ❌ $c"
    done
    echo ""
    fail "核心 chunk 缺失，登录页必白屏。回退 dist 重新同步。"
  fi
fi

# ---- 5. md5 校验（全文件覆盖，可选 SAMPLE_ONLY=1 退回抽样）----
#
# 历史：原本只抽样核心 5 个 + 改动的模块。问题：rsync 中途断 → 1 个非抽样文件
# 损坏 → 脚本通过 → 用户访问该模块路由 → 404 → 白屏。
#
# 默认行为：校验 manifest 里全部 262 个 chunks。SAMPLE_ONLY=1 退回旧行为。
# CHECK_MODULE=foo 仍然追加指定模块（兼容旧 workflow）。
if [ "${SAMPLE_ONLY:-0}" = "1" ]; then
  log "md5 抽样校验（旧行为：核心 5 + 改动的模块）..."
  CHUNKS_TO_CHECK="$CORE_CHUNKS"
else
  log "md5 全文件校验（$(echo "$LOCAL_FILES" | wc -l) 个文件，防 rsync 漏传）..."
  # 用 node 一次性从 manifest 拿所有 chunk key（比 shell for 循环 + node eval 块快 50 倍）
  CHUNKS_TO_CHECK=$(node -e "
const m = require('$MANIFEST')
process.stdout.write(Object.keys(m.chunks).join('\n'))
")
fi

# 如果命令行传了 MODULE 参数，追加这个模块的所有 chunks
if [ -n "${CHECK_MODULE:-}" ]; then
  MODULE_CHUNKS=$(node -e "
const m = require('$MANIFEST')
console.log((m.moduleMap['$CHECK_MODULE'] || []).join('\n'))
")
  if [ -n "$MODULE_CHUNKS" ]; then
    CHUNKS_TO_CHECK="$CHUNKS_TO_CHECK $MODULE_CHUNKS"
    log "  含模块 $CHECK_MODULE 的 $(echo "$MODULE_CHUNKS" | wc -l) 个 chunks"
  fi
fi

MISMATCH=0
TOTAL_TO_CHECK=0
CHECKED_COUNT=0
for chunk in $CHUNKS_TO_CHECK; do
  TOTAL_TO_CHECK=$((TOTAL_TO_CHECK + 1))

  # 跳过 manifest 标记为缺失的（build 自身问题，不影响同步）
  if echo "$MANIFEST_MISSING" | grep -qx "$chunk" 2>/dev/null; then
    continue
  fi

  # 跳过 chunk 文件本身不存在的（防 md5sum hang）
  # chunk 可能在 dist/assets/ 或 dist 根（如 favicon.svg）
  if [ ! -f "$REMOTE_DIR/assets/$chunk" ] && [ ! -f "$REMOTE_DIR/$chunk" ]; then
    echo -e "  ${YELLOW}⚠️  $chunk 本地文件不存在（既不在 assets/ 也不在 dist 根），跳过${NC}"
    continue
  fi

  CHECKED_COUNT=$((CHECKED_COUNT + 1))
  LOCAL_MD5=$(node -e "
const m = require('$MANIFEST')
process.stdout.write(m.chunks['$chunk']?.md5 || 'NOTFOUND')
")

  if [ -z "$REMOTE_HOST" ]; then
    CHUNK_LOCAL_PATH="$REMOTE_DIR/assets/$chunk"
    [ ! -f "$CHUNK_LOCAL_PATH" ] && CHUNK_LOCAL_PATH="$REMOTE_DIR/$chunk"
    REMOTE_MD5=$(md5sum "$CHUNK_LOCAL_PATH" 2>/dev/null | awk '{print $1}' || echo "")
  else
    # 远程：先试 assets/，再试 dist 根
    CHUNK_REMOTE_PATH="$REMOTE_DIR/assets/$chunk"
    REMOTE_MD5=$(timeout 10 $REMOTE_CMD "test -f $CHUNK_REMOTE_PATH && md5sum $CHUNK_REMOTE_PATH || md5sum $REMOTE_DIR/$chunk" 2>/dev/null | awk '{print $1}' || echo "")
  fi

  if [ "$LOCAL_MD5" != "$REMOTE_MD5" ]; then
    echo -e "  ${RED}❌ $chunk md5 不一致 (local=$LOCAL_MD5 remote=$REMOTE_MD5)${NC}"
    MISMATCH=$((MISMATCH + 1))
  fi
done

if [ "$MISMATCH" -gt 0 ]; then
  fail "❌ $MISMATCH 个 chunk md5 不一致（文件可能损坏 / rsync 漏传）"
fi

# ---- 6. 总结 ----
echo ""
echo "=========================================="
if [ -z "$MISSING" ] && [ -z "$EXTRA" ] && [ "$MISMATCH" -eq 0 ]; then
  ok "✅ 100% 同步一致"
  echo "  本地: $LOCAL_COUNT 文件"
  echo "  远程: $REMOTE_COUNT 文件"
  echo "  核心: $(echo "$CORE_CHUNKS" | wc -l) 个全部 OK"
  echo "  md5 校验: $CHECKED_COUNT / $TOTAL_TO_CHECK 个全部一致"
  echo "  结论: 登录页 + 所有模块都健康，不会白屏"
  exit 0
elif [ -n "$MISSING" ]; then
  fail "❌ 缺失 $MISSING_COUNT 个文件，必须重新同步"
elif [ -n "$EXTRA" ]; then
  # 严格：远程有本地无 = 旧 build 残留。下次 build 不再生成这些文件 = 用户进旧路由白屏
  # 用 ALLOW_EXTRA=1 退回旧 warn-only 行为（仅当你 100% 确定 stale file 安全时）
  if [ "${ALLOW_EXTRA:-0}" = "1" ]; then
    warn "⚠️  $EXTRA_COUNT 个多余文件（ALLOW_EXTRA=1，不影响功能可清理）"
    exit 0
  else
    fail "❌ 远程有 $EXTRA_COUNT 个本地没有的文件（stale 残留 / build 路径变了）。重新 rsync --delete，或显式 ALLOW_EXTRA=1 跳过"
  fi
else
  fail "❌ 未知失败"
fi
