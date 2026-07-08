#!/bin/bash
# 离线 E2E：profile build 后审计 (不动生产)
# 跑法: bash scripts/e2e-profile-build.sh [profile_id]
#   profile_id: 1 (sgp, 全开) | 2 (bj) | 3 (3hk, 最多禁用) — 默认 3
#
# 不依赖浏览器, 用 curl + grep + JSON 报告断言:
#   1. disabled module 的 chunk 文件不存在
#   2. entry chunk 没引用 disabled module
#   3. enabled module 的入口 chunk 文件存在
#   4. index.html 引用 entry chunk 存在
#   5. 跑 preview server 抓 index.html 能 200
#
# 风险: 只读本地 dist-<id>, 不动任何线上资源

set -e

PROFILE_ID="${1:-3}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SRC_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
DIST_DIR="$SRC_DIR/dist-$PROFILE_ID"
REPORT="$DIST_DIR/.module-filter-report.json"

echo "=== E2E Profile Build Audit (profile $PROFILE_ID) ==="
echo "  dist: $DIST_DIR"
echo "  report: $REPORT"
echo ""

# 0. 必备: dist + report 必须存在
if [ ! -d "$DIST_DIR" ]; then
  echo "FAIL: $DIST_DIR 不存在, 请先跑: bash scripts/build-for-profile.sh $PROFILE_ID build"
  exit 1
fi
if [ ! -f "$REPORT" ]; then
  echo "FAIL: $REPORT 不存在, 请重跑 build 并加 MODULE_FILTER_JSON_REPORT=1"
  echo "  例如: MODULE_FILTER_JSON_REPORT=1 bash scripts/build-for-profile.sh $PROFILE_ID build"
  exit 1
fi

PASS=0
FAIL=0

ok() { echo "  ✅ $1"; PASS=$((PASS+1)); }
ko() { echo "  ❌ $1"; FAIL=$((FAIL+1)); }

# 1. 解析 JSON 报告 (无 jq 也能跑, 用 node)
DISABLED_MODULES=$(node -e "const r=require('$REPORT');console.log(r.profile.disabledModules.join(' '))")
DISABLED_PREFIXES=$(node -e "const r=require('$REPORT');console.log(r.profile.disabledPrefixes.map(d=>d.prefix).join(' '))")
FILTERED_CHUNK_COUNT=$(node -e "const r=require('$REPORT');console.log(r.entry.filteredRefs.total)")
REMOVED_FILE_COUNT=$(node -e "const r=require('$REPORT');console.log(r.entry.removedFiles.total)")

echo "Disabled modules: $DISABLED_MODULES"
echo "Disabled prefixes: $DISABLED_PREFIXES"
echo "Filtered refs in entry: $FILTERED_CHUNK_COUNT"
echo "Removed files: $REMOVED_FILE_COUNT"
echo ""

# 2. 解析 entry chunk name
ENTRY_NAME=$(node -e "const r=require('$REPORT');console.log(r.entry.name)")
ENTRY_PATH="$DIST_DIR/assets/$ENTRY_NAME"
echo "Entry chunk: $ENTRY_NAME"
echo ""

# 检查 1: index.html 存在 + 引用 entry chunk
echo "[1] index.html 引用 entry chunk"
if grep -q "$ENTRY_NAME" "$DIST_DIR/index.html"; then
  ok "index.html 引用 $ENTRY_NAME"
else
  ko "index.html 没引用 $ENTRY_NAME (entry 改写后没回写 index.html)"
fi

# 检查 2: entry chunk 文件存在
echo "[2] entry chunk 文件存在"
if [ -f "$ENTRY_PATH" ]; then
  ok "$ENTRY_NAME 存在 ($(du -h "$ENTRY_PATH" | cut -f1))"
else
  ko "$ENTRY_NAME 不存在"
fi

# 检查 3: disabled module 的 chunk 文件**不存在** (被 module-filter 删了)
# 用 glob + nullglob 替代 ls|head 避免 pipeline 吞 exit code
echo "[3] disabled module 的 chunk 文件已删"
shopt -s nullglob
for prefix in $DISABLED_PREFIXES; do
  files=("$DIST_DIR/assets/$prefix"-*.js)
  if [ ${#files[@]} -gt 0 ]; then
    ko "$prefix-*.js 还在 dist 里 (没被删干净!)"
    printf '      %s\n' "${files[@]}"
  else
    ok "$prefix-*.js 已删"
  fi
done
shopt -u nullglob

# 检查 4: entry chunk 里**没引用** disabled module
echo "[4] entry chunk 没引用 disabled module"
ENTRY_REFS=$(grep -oE "[\"'\\./]($DISABLED_PREFIXES)[A-Za-z0-9_-]*[\"']" "$ENTRY_PATH" 2>/dev/null | sort -u || true)
if [ -z "$ENTRY_REFS" ]; then
  ok "entry chunk 不引用任何 disabled module"
else
  ko "entry chunk 仍引用 disabled module:"
  echo "$ENTRY_REFS" | sed 's/^/      /'
fi

# 检查 5: 启动 preview server, curl 抓 index.html 看 200
echo "[5] preview server 抓 index.html 200"
PORT=4173
# 先确认端口干净 (之前跑可能没 kill 干净)
pkill -f "serve.*--listen.*$PORT" 2>/dev/null || true
pkill -f "vite preview.*$PORT" 2>/dev/null || true
sleep 1
# 用 npx serve (纯静态, 不像 vite preview 会被当前目录 vite.config 影响)
# vite preview --outDir 在 npx 下不可靠 (会读 cwd 的 vite.config outDir)
# serve: positional [directory] 必传, --listen 是 uri 不是 -l
npx serve --listen tcp://127.0.0.1:$PORT "$DIST_DIR" > /tmp/vite-preview-$PROFILE_ID.log 2>&1 &
PREVIEW_PID=$!
# 注册 cleanup: 杀整个进程组 (npx -> node -> serve)
trap "kill -- -$PREVIEW_PID 2>/dev/null; pkill -P $PREVIEW_PID 2>/dev/null; wait $PREVIEW_PID 2>/dev/null" EXIT
# 等 vite 启动
for i in 1 2 3 4 5 6 7 8 9 10; do
  if curl -sf "http://127.0.0.1:$PORT/" -o /dev/null 2>/dev/null; then
    break
  fi
  sleep 0.5
done
HTTP_CODE=$(curl -s -o /tmp/preview-index-$PROFILE_ID.html -w "%{http_code}" "http://127.0.0.1:$PORT/" 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
  ok "preview server 200 (curl 抓到 index.html, $(wc -c < /tmp/preview-index-$PROFILE_ID.html) bytes)"
  # 抓到的 index.html 应该跟本地 dist/index.html 一致
  if diff -q "$DIST_DIR/index.html" /tmp/preview-index-$PROFILE_ID.html > /dev/null 2>&1; then
    ok "preview 返回的 index.html 跟 dist 本地一致"
  else
    ko "preview 返回的 index.html 跟 dist 本地不一致"
  fi
else
  ko "preview server HTTP $HTTP_CODE (查看 /tmp/vite-preview-$PROFILE_ID.log)"
fi
# 抓 entry chunk 也要 200
ENTRY_HTTP=$(curl -s -o /tmp/preview-entry-$PROFILE_ID.js -w "%{http_code}" "http://127.0.0.1:$PORT/assets/$ENTRY_NAME" 2>/dev/null || echo "000")
if [ "$ENTRY_HTTP" = "200" ]; then
  ok "preview server 抓到 entry chunk 200 ($(wc -c < /tmp/preview-entry-$PROFILE_ID.js) bytes)"
else
  ko "preview server 抓 entry chunk HTTP $ENTRY_HTTP"
fi
kill -- -$PREVIEW_PID 2>/dev/null || true
pkill -P $PREVIEW_PID 2>/dev/null || true
kill $PREVIEW_PID 2>/dev/null || true
wait $PREVIEW_PID 2>/dev/null || true

# 总结
echo ""
echo "=== Summary ==="
echo "  PASS: $PASS"
echo "  FAIL: $FAIL"
if [ $FAIL -eq 0 ]; then
  echo "  🟢 ALL OK"
  exit 0
else
  echo "  🔴 $FAIL 个失败"
  exit 1
fi
