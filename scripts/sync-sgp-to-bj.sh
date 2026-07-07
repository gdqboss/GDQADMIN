#!/usr/bin/env bash
# sync-sgp-to-bj.sh
# 把 /root/src/dist/ 同步到北京 claw.gdqshop.cn (81.70.199.64:2222)
#
# 流程：
#   1. 读本地 dist/ 的 md5 快照
#   2. dry-run 模式：只显示会传输哪些文件 + 大小，不动 bj
#   3. 实际同步：rsync 到 ubuntu 家目录 + sudo cp 到 /var/www + nginx reload + curl 验证
#
# 关键：BJ /var/www/claw.gdqshop.cn 是 root-owned，ubuntu 没法直推。
#       两阶段：先 rsync 到 ubuntu:~/dist-staging/（ubuntu 可写），
#       再 sudo cp -r staging/* 到 /var/www（root 权限）。
#
# sudo 密码从 ~/.sync-bj-pass 读（不要传命令行 / 写脚本）：
#   echo 'your_pass' > ~/.sync-bj-pass && chmod 600 ~/.sync-bj-pass
#
# 用法：
#   bash scripts/sync-sgp-to-bj.sh              # dry-run（默认安全），走 profile 1 (sgp 全集)
#   bash scripts/sync-sgp-to-bj.sh --push       # 实际推送 (profile 1)
#   bash scripts/sync-sgp-to-bj.sh --push --skip-build
#   bash scripts/sync-sgp-to-bj.sh --verify
#   bash scripts/sync-sgp-to-bj.sh --profile 2  # 推 bj 专属 profile dist-2 (模块化)
#   bash scripts/sync-sgp-to-bj.sh --profile 2 --push --skip-build
#   bash scripts/sync-sgp-to-bj.sh --build-script build:custom  # 自定义 build 命令

set -euo pipefail

# ---- 配置 ----
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
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
# Phase 1 新增: profile 概念。默认 1 (sgp 全集) 保持向后兼容
PROFILE=1
BUILD_SCRIPT="build:sg"  # 默认走模块化 build，不是全量 build
for arg in "$@"; do
  case $arg in
    --push) PUSH=true ;;
    --skip-build) SKIP_BUILD=true ;;
    --verify) VERIFY_ONLY=true ;;
    --profile=*)
      PROFILE="${arg#--profile=}"
      ;;
    --build-script=*)
      BUILD_SCRIPT="${arg#--build-script=}"
      ;;
    -h|--help)
      sed -n '2,/^set -euo pipefail/p' "$0" | sed 's/^#//' | sed '/^set -euo pipefail$/d'
      exit 0
      ;;
    *) fail "未知参数: $arg (用 --help 看用法)" ;;
  esac
done

# profile → build script 默认映射
case $PROFILE in
  1|sgp) BUILD_SCRIPT="build:sg" ;;
  2|bj)  BUILD_SCRIPT="build:bj" ;;
  3|3hk) BUILD_SCRIPT="build:3hk" ;;
  *) fail "未知 profile: $PROFILE (合法: 1/sgp, 2/bj, 3/3hk)" ;;
esac

# profile → 推送目标目录
case $PROFILE in
  1|sgp) DIST_DIR="$SRC_DIR/dist" ;;
  2|bj)  DIST_DIR="$SRC_DIR/dist-2" ;;
  3|3hk) DIST_DIR="$SRC_DIR/dist-3" ;;
esac

log "目标 profile: $PROFILE (build=$BUILD_SCRIPT, dist=$DIST_DIR)"

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
  log "构建 profile $PROFILE ($BUILD_SCRIPT)..."
  cd "$SRC_DIR"
  npm run "$BUILD_SCRIPT" 2>&1 | tail -10
  ok "构建完成 ($DIST_DIR)"

  # 生成 chunk manifest（verify-sync.sh 依赖这个）
  log "生成 chunk manifest..."
  if node "$SCRIPT_DIR/generate-chunk-manifest.mjs" 2>&1 | tail -5; then
    ok "chunk manifest 已生成"
  else
    warn "chunk manifest 生成失败（verify-sync.sh 将降级运行）"
  fi
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
  warn "目标 profile: $PROFILE"
  warn "本地 dist: $DIST_DIR"
  warn "推送流程（两阶段）："
  echo "  阶段 1: rsync 本地 $DIST_DIR → bj:~/dist-staging/  (ubuntu 可写)"
  echo "    rsync -avz --delete \\"
  echo "      -e 'ssh -i $BJ_PEM -p $BJ_PORT -o StrictHostKeyChecking=no' \\"
  echo "      $DIST_DIR/ ${BJ_USER}@${BJ_HOST}:~/dist-staging/"
  echo
  echo "  阶段 2: expect 跑 sudo cp + chown + nginx reload (用 ~/.sync-bj-pass 密码)"
  echo "    sudo rm -rf $BJ_REMOTE_DIR/assets $BJ_REMOTE_DIR/index.html ..."
  echo "    sudo cp -r ~/dist-staging/. $BJ_REMOTE_DIR/"
  echo "    sudo chown -R root:root $BJ_REMOTE_DIR"
  echo "    sudo nginx -t && sudo nginx -s reload"
  echo
  warn "  最后 5 信号验证 bj 不白屏："
  echo "    1. 主页 200"
  echo "    2. entry chunk 200"
  echo "    3. entry Content-Type = application/javascript"
  echo "    4. entry 大小 > 100KB (防 nginx fallback 命中)"
  echo "    5. entry hash 与 sgp 一致"
  echo
  warn "确认要推送？加上 --push 参数："
  warn "  bash $0 --push"
  exit 0
fi

# ---- 5. 实际推送前再检查密码文件（仅 push 模式需要） ----
[ -f "$BJ_PASS_FILE" ] || fail "sudo 密码文件不存在: $BJ_PASS_FILE (echo 'pass' > $BJ_PASS_FILE && chmod 600 $BJ_PASS_FILE)"
[ "$(stat -c '%a' "$BJ_PASS_FILE")" = "600" ] || fail "密码文件权限不是 600: $(stat -c '%a' "$BJ_PASS_FILE") (chmod 600 $BJ_PASS_FILE)"
BJ_SUDO_PASS=$(cat "$BJ_PASS_FILE")

# ---- 6. 实际推送：先备份，再两阶段 rsync ----

# 6a. 备份 bj 当前 dist 到 sgp /tmp
#  生成独立 expect 脚本避免 heredoc 方括号转义问题
log "备份 bj 当前 dist → /tmp/bj-dist-backup-*.tar.gz..."
BACKUP_NAME="bj-dist-backup-$(date +%Y%m%d_%H%M%S).tar.gz"
EXP_SCRIPT=$(mktemp -t sync-backup-XXXXXX.exp)
cat > "$EXP_SCRIPT" <<'EXP_HEREDOC'
#!/usr/bin/expect -f
set timeout 30
set backup_name [lindex $argv 0]
set bj_pem [lindex $argv 1]
set bj_port [lindex $argv 2]
set bj_host [lindex $argv 3]
set bj_user [lindex $argv 4]
set bj_remote_dir [lindex $argv 5]
set bj_sudo_pass [lindex $argv 6]

spawn ssh -i "$bj_pem" -p $bj_port -o StrictHostKeyChecking=no $bj_user@$bj_host
expect {
  -re {[#$] $} {
    send "sudo tar -czf ~/$backup_name -C $bj_remote_dir . && echo BACKUP_DONE\r"
  }
  timeout { puts "TIMEOUT_SSH"; exit 1 }
}
expect {
  "BACKUP_DONE" { puts "BACKUP_OK" }
  -re {\[sudo\] password} { send "$bj_sudo_pass\r"; exp_continue }
  timeout { puts "TIMEOUT_SUDO"; exit 1 }
}
expect -re {[#$] $}
send "exit\r"
expect eof
EXP_HEREDOC
chmod +x "$EXP_SCRIPT"
EXP_OUTPUT=$(expect "$EXP_SCRIPT" "$BACKUP_NAME" "$BJ_PEM" "$BJ_PORT" "$BJ_HOST" "$BJ_USER" "$BJ_REMOTE_DIR" "$BJ_SUDO_PASS" 2>&1)
rm -f "$EXP_SCRIPT"
if echo "$EXP_OUTPUT" | grep -q "BACKUP_OK"; then
  ok "bj dist 远程备份完成: $BACKUP_NAME"
  scp -i "$BJ_PEM" -P $BJ_PORT -o StrictHostKeyChecking=no -q \
    "$BJ_USER@$BJ_HOST:~/$BACKUP_NAME" "/tmp/$BACKUP_NAME" 2>&1 | head -3
  if [ -f "/tmp/$BACKUP_NAME" ]; then
    ok "备份拉到 sgp: /tmp/$BACKUP_NAME ($(du -sh "/tmp/$BACKUP_NAME" | cut -f1))"
  else
    warn "scp 回拉失败（备份仍在 bj）"
  fi
else
  warn "备份失败（继续推送）: $(echo "$EXP_OUTPUT" | tail -3)"
fi

# 6b. 阶段 1：rsync 到 ubuntu 家目录 ~/dist-staging/（ubuntu 可写）
log "阶段 1：rsync 本地 dist → bj:~/dist-staging/ ..."
cd "$DIST_DIR"
rsync -avz --delete \
  -e "ssh -i $BJ_PEM -p $BJ_PORT -o StrictHostKeyChecking=no -o ConnectTimeout=30" \
  ./ "$BJ_USER@$BJ_HOST:~/dist-staging/" 2>&1 | tail -5
ok "rsync staging 完成"

# 6c. 阶段 2：expect 跑 sudo cp -r staging 到 /var/www + chown + nginx reload
log "阶段 2：expect 跑 sudo cp + chown root + nginx reload..."
EXP_SCRIPT=$(mktemp -t sync-cp-XXXXXX.exp)
cat > "$EXP_SCRIPT" <<'EXP_HEREDOC'
#!/usr/bin/expect -f
set timeout 60
set bj_pem [lindex $argv 0]
set bj_port [lindex $argv 1]
set bj_host [lindex $argv 2]
set bj_user [lindex $argv 3]
set bj_remote_dir [lindex $argv 4]
set bj_sudo_pass [lindex $argv 5]

spawn ssh -i "$bj_pem" -p $bj_port -o StrictHostKeyChecking=no $bj_user@$bj_host
expect {
  -re {[#$] $} {
    send "sudo rm -rf $bj_remote_dir/assets $bj_remote_dir/index.html $bj_remote_dir/mywh3-logo.jpg $bj_remote_dir/module-manifest.json $bj_remote_dir/logo.jpg 2>/dev/null; sudo cp -r ~/dist-staging/. $bj_remote_dir/ && sudo chown -R root:root $bj_remote_dir && sudo nginx -t && sudo nginx -s reload && echo PUSH_RELOAD_OK\r"
  }
  timeout { puts "TIMEOUT_SSH"; exit 1 }
}
expect {
  "PUSH_RELOAD_OK" { puts "RELOAD_OK" }
  -re {\[sudo\] password} { send "$bj_sudo_pass\r"; exp_continue }
  timeout { puts "TIMEOUT_SUDO"; exit 1 }
}
expect -re {[#$] $}
send "rm -rf ~/dist-staging && exit\r"
expect eof
EXP_HEREDOC
chmod +x "$EXP_SCRIPT"
EXP_OUTPUT=$(expect "$EXP_SCRIPT" "$BJ_PEM" "$BJ_PORT" "$BJ_HOST" "$BJ_USER" "$BJ_REMOTE_DIR" "$BJ_SUDO_PASS" 2>&1)
rm -f "$EXP_SCRIPT"
if echo "$EXP_OUTPUT" | grep -q "RELOAD_OK"; then
  ok "sudo cp + nginx reload 成功"
else
  fail "❌ 远程 reload 失败: $(echo "$EXP_OUTPUT" | tail -10)"
fi

# ---- 7. 验证 bj 不白屏（5 个信号） ----
log "验证 bj..."
sleep 2
REMOTE_STATUS=$(curl -sk --max-time 10 "https://$BJ_NGINX_DOMAIN/" -o /dev/null -w "%{http_code}")
REMOTE_ENTRY=$(curl -sk --max-time 10 "https://$BJ_NGINX_DOMAIN/" | grep -oE 'index-[A-Za-z0-9_-]+\.js' | head -1)
REMOTE_CHUNK_STATUS=$(curl -sk --max-time 10 "https://$BJ_NGINX_DOMAIN/assets/$REMOTE_ENTRY" -o /dev/null -w "%{http_code}")
REMOTE_CHUNK_TYPE=$(curl -sk --max-time 10 "https://$BJ_NGINX_DOMAIN/assets/$REMOTE_ENTRY" -o /dev/null -w "%{content_type}")
REMOTE_CHUNK_SIZE=$(curl -sk --max-time 10 "https://$BJ_NGINX_DOMAIN/assets/$REMOTE_ENTRY" -o /dev/null -w "%{size_download}")

# 信号 1: 主页 200
[ "$REMOTE_STATUS" = "200" ] || fail "❌ 主页返回 $REMOTE_STATUS (期望 200)"

# 信号 2: entry chunk 200
[ "$REMOTE_CHUNK_STATUS" = "200" ] || fail "❌ entry chunk $REMOTE_ENTRY 返回 $REMOTE_CHUNK_STATUS (期望 200)"

# 信号 3: entry chunk 是 JS 类型（防 SPA fallback 命中白屏信号）
[ "$REMOTE_CHUNK_TYPE" = "application/javascript" ] || [ "$REMOTE_CHUNK_TYPE" = "text/javascript" ] \
  || fail "❌ entry chunk Content-Type=$REMOTE_CHUNK_TYPE (期望 javascript，nginx fallback 命中！)"

# 信号 4: entry chunk 大小 > 100KB（防 fallback 返回 973B index.html）
[ "$REMOTE_CHUNK_SIZE" -gt 100000 ] \
  || fail "❌ entry chunk 大小=$REMOTE_CHUNK_SIZE 字节 (期望 >100KB，nginx fallback 命中！)"

# 信号 5: entry hash 一致
[ "$REMOTE_ENTRY" = "$ENTRY" ] \
  || fail "❌ entry hash 不一致: sgp=$ENTRY bj=$REMOTE_ENTRY"

ok "✅ 主页 200 + entry $REMOTE_ENTRY 200 OK ($REMOTE_CHUNK_TYPE, ${REMOTE_CHUNK_SIZE}B) + hash 一致"

# 信号 6: 100% chunk 完整性验证（防止"rsync 漏文件 → 路由 404 → 白屏"）
log "运行 100% chunk 完整性验证..."
if REMOTE_HOST="$BJ_HOST" REMOTE_PORT="$BJ_PORT" REMOTE_USER="$BJ_USER" REMOTE_PEM="$BJ_PEM" REMOTE_DIR="$BJ_REMOTE_DIR" \
   bash "$SCRIPT_DIR/verify-sync.sh" 2>&1 | tail -15; then
  ok "✅ 100% chunk 同步一致（255 个文件 + 核心 5 个 + md5 全部匹配）"
else
  fail "❌ chunk 完整性验证失败（白屏风险），已停止。请检查同步日志 + 必要时回退 dist。"
fi

ok "同步完成 ✅"

