#!/usr/bin/env bash
# sync-sgp-to-bj.sh
# 把 /root/src/dist/ 同步到北京 claw.gdqshop.cn (81.70.199.64:2222)
#
# 流程：
#   1. 读本地 dist/ 的 md5 快照
#   2. dry-run 模式：只显示会传输哪些文件 + 大小，不动 bj
#   3. 实际同步：rsync --delete + chown root + nginx reload
#   4. 同步后远程 curl 验证不白屏
#
# sudo 密码从 ~/.sync-bj-pass 读（不要传命令行 / 写脚本）：
#   echo 'your_pass' > ~/.sync-bj-pass && chmod 600 ~/.sync-bj-pass
#
# 用法：
#   bash scripts/sync-sgp-to-bj.sh              # dry-run（默认安全）
#   bash scripts/sync-sgp-to-bj.sh --push       # 实际推送
#   bash scripts/sync-sgp-to-bj.sh --push --skip-build  # 不重建，直接推
#   bash scripts/sync-sgp-to-bj.sh --verify     # 只验证 bj 当前状态

set -euo pipefail

# ---- 配置 ----
SRC_DIR="/root/src"
DIST_DIR="$SRC_DIR/dist"
BJ_HOST="81.70.199.64"
BJ_PORT="2222"
BJ_USER="ubuntu"
BJ_PEM="/root/clawgdqshop.pem"
BJ_REMOTE_DIR="/var/www/claw.gdqshop.cn"
BJ_NGINX_DOMAIN="claw.gdqshop.cn"
BJ_PASS_FILE="$HOME/.sync-bj-pass"

# ---- 颜色输出 ----
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log()  { echo -e "${BLUE}[sync]${NC} $*"; }
ok()   { echo -e "${GREEN}[ok]${NC} $*"; }
warn() { echo -e "${YELLOW}[warn]${NC} $*"; }
fail() { echo -e "${RED}[fail]${NC} $*"; exit 1; }

# ---- 参数解析 ----
PUSH=false
SKIP_BUILD=false
VERIFY_ONLY=false
for arg in "$@"; do
  case $arg in
    --push) PUSH=true ;;
    --skip-build) SKIP_BUILD=true ;;
    --verify) VERIFY_ONLY=true ;;
    -h|--help)
      sed -n '2,/^set -euo pipefail/p' "$0" | sed 's/^#//' | sed '/^set -euo pipefail$/d'
      exit 0
      ;;
    *) fail "未知参数: $arg (用 --help 看用法)" ;;
  esac
done

# ---- 0. 仅验证 bj 当前状态 ----
if $VERIFY_ONLY; then
  log "验证 bj 当前状态..."
  REMOTE_STATUS=$(curl -sk --max-time 10 "https://$BJ_NGINX_DOMAIN/" -o /dev/null -w "%{http_code}")
  if [ "$REMOTE_STATUS" = "200" ]; then
    ok "bj 主页 200 OK"
    REMOTE_ENTRY=$(curl -sk --max-time 10 "https://$BJ_NGINX_DOMAIN/" | grep -oE 'index-[A-Za-z0-9_-]+\.js' | head -1)
    log "当前 entry: $REMOTE_ENTRY"
  else
    fail "bj 主页返回 $REMOTE_STATUS"
  fi
  exit 0
fi

# ---- 1. 预检查 ----
log "预检查..."
[ -d "$DIST_DIR" ] || fail "dist 目录不存在: $DIST_DIR (先 npm run build)"
[ -f "$BJ_PEM" ]   || fail "SSH key 不存在: $BJ_PEM"
command -v expect >/dev/null || fail "expect 未安装 (apt install expect)"
command -v rsync  >/dev/null || fail "rsync 未安装"
command -v ssh    >/dev/null || fail "ssh 未安装"

# ---- 2. 构建（除非 --skip-build） ----
if ! $SKIP_BUILD; then
  log "构建 sgp dist (npm run build)..."
  cd "$SRC_DIR"
  npm run build 2>&1 | tail -20
  ok "构建完成"
fi

# ---- 3. 收集本地 dist 快照 ----
log "收集本地 dist 快照..."
LOCAL_FILES=$(find "$DIST_DIR" -type f -printf "%P\n" | sort)
LOCAL_COUNT=$(echo "$LOCAL_FILES" | wc -l)
LOCAL_SIZE=$(du -sh "$DIST_DIR" | cut -f1)
LOCAL_HASH=$(find "$DIST_DIR" -type f -exec md5sum {} \; | md5sum | cut -d' ' -f1)
ENTRY=$(grep -oE 'index-[A-Za-z0-9_-]+\.js' "$DIST_DIR/index.html" | head -1)
[ -n "$ENTRY" ] || fail "无法从 dist/index.html 解析 entry chunk"
log "  - 文件数: $LOCAL_COUNT"
log "  - 总大小: $LOCAL_SIZE"
log "  - 整体 hash: $LOCAL_HASH"
log "  - entry: $ENTRY"

# ---- 4. dry-run: 显示要推送的内容（不改 bj） ----
if ! $PUSH; then
  echo
  warn "============ DRY-RUN 模式 (不实际推送) ============"
  warn "推送命令预览："
  echo "  rsync -avz --delete \\"
  echo "    -e 'ssh -i $BJ_PEM -p $BJ_PORT -o StrictHostKeyChecking=no' \\"
  echo "    $DIST_DIR/ ${BJ_USER}@${BJ_HOST}:${BJ_REMOTE_DIR}/"
  echo
  warn "  然后远程执行："
  echo "    sudo chown -R root:root $BJ_REMOTE_DIR/assets"
  echo "    sudo nginx -t && sudo nginx -s reload"
  echo
  warn "  最后 curl 验证: https://$BJ_NGINX_DOMAIN/"
  echo
  warn "确认要推送？加上 --push 参数："
  warn "  bash $0 --push"
  exit 0
fi

# ---- 5. 实际推送前再检查密码文件（仅 push 模式需要） ----
[ -f "$BJ_PASS_FILE" ] || fail "sudo 密码文件不存在: $BJ_PASS_FILE (echo 'pass' > $BJ_PASS_FILE && chmod 600 $BJ_PASS_FILE)"
[ "$(stat -c '%a' "$BJ_PASS_FILE")" = "600" ] || fail "密码文件权限不是 600: $(stat -c '%a' "$BJ_PASS_FILE") (chmod 600 $BJ_PASS_FILE)"
BJ_SUDO_PASS=$(cat "$BJ_PASS_FILE")

# ---- 6. 实际推送：rsync ----
log "推送 dist 到 bj (rsync --delete)..."
cd "$DIST_DIR"
rsync -avz --delete \
  -e "ssh -i $BJ_PEM -p $BJ_PORT -o StrictHostKeyChecking=no -o ConnectTimeout=30" \
  ./ "${BJ_USER}@${BJ_HOST}:${BJ_REMOTE_DIR}/" 2>&1 | tail -20
ok "rsync 完成"

# ---- 7. 远程 chown + nginx reload（用 expect 输密码） ----
log "远程 chown + nginx reload (expect)..."
expect << EXPECT_EOF
set timeout 30
spawn ssh -i "$BJ_PEM" -p $BJ_PORT -o StrictHostKeyChecking=no ${BJ_USER}@${BJ_HOST}

expect {
  "\\\$ " {
    send "sudo chown -R root:root $BJ_REMOTE_DIR/assets && sudo nginx -t && sudo nginx -s reload && echo SYNC_RELOAD_OK\r"
  }
  timeout { send_user "\n[sync] SSH 进入超时\n"; exit 1 }
}

expect {
  "SYNC_RELOAD_OK" { send_user "\n[sync] nginx reload 成功\n" }
  "\\[sudo\\] password" {
    send "$BJ_SUDO_PASS\r"
    exp_continue
  }
  "password" {
    send "$BJ_SUDO_PASS\r"
    exp_continue
  }
  timeout { send_user "\n[sync] sudo 超时\n"; exit 1 }
}

expect "\\\$ "
send "exit\r"
expect eof
EXPECT_EOF
ok "远程 reload 完成"

# ---- 8. 验证 bj 不白屏 ----
log "验证 bj..."
sleep 2
REMOTE_STATUS=$(curl -sk --max-time 10 "https://$BJ_NGINX_DOMAIN/" -o /dev/null -w "%{http_code}")
REMOTE_ENTRY=$(curl -sk --max-time 10 "https://$BJ_NGINX_DOMAIN/" | grep -oE 'index-[A-Za-z0-9_-]+\.js' | head -1)
REMOTE_CHUNK_STATUS=$(curl -sk --max-time 10 "https://$BJ_NGINX_DOMAIN/assets/$REMOTE_ENTRY" -o /dev/null -w "%{http_code}")

if [ "$REMOTE_STATUS" != "200" ]; then
  fail "bj 主页返回 $REMOTE_STATUS (期望 200)"
fi
if [ "$REMOTE_CHUNK_STATUS" != "200" ]; then
  fail "bj entry chunk $REMOTE_ENTRY 返回 $REMOTE_CHUNK_STATUS (期望 200)"
fi
if [ "$REMOTE_ENTRY" != "$ENTRY" ]; then
  fail "bj entry hash 不一致: sgp=$ENTRY bj=$REMOTE_ENTRY (白屏风险！)"
fi

ok "bj 主页 200 OK + entry chunk 200 OK + hash 一致 ($REMOTE_ENTRY)"
ok "同步完成 ✅"
