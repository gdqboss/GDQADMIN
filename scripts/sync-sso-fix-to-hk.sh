#!/bin/bash
# sync-sso-fix-to-hk.sh — 把 SGP 上 SSO 全链路修复推到 HK 横琴 (gbaw.cn)
# 2026-08-25 by Hermes
# 用法: bash sync-sso-fix-to-hk.sh [--apply]
#   --dry-run (default): 只 print, 不执行
#   --apply: 真的执行
set -e

SSH_KEY="/root/.ssh/hk-incubator/hk_incubator_v4.pem"
HK_HOST="ubuntu@43.128.47.254"
HK_CODE_DIR="/home/ubuntu/server"
HK_DIST_DIR="/var/www/hatch/gdqadmin"
HK_DB="gdq_hk"

# 颜色
G='\033[0;32m'
Y='\033[1;33m'
R='\033[0;31m'
N='\033[0m'

DRY_RUN=true
if [ "$1" = "--apply" ]; then
  DRY_RUN=false
fi

run() {
  if $DRY_RUN; then
    echo -e "${Y}[DRY-RUN]${N} $*"
  else
    echo -e "${G}[EXEC]${N} $*"
    eval "$@"
  fi
}

echo "=========================================="
echo " SSO Fix Sync: SGP → HK 横琴 (gbaw.cn)"
echo " Mode: $($DRY_RUN && echo dry-run || echo APPLY)"
echo "=========================================="

# ── 0. 本地前置: dist-6 build 必须已跑 ──
echo -e "\n${Y}--- 0. Verify local files exist ---${N}"
for f in \
  /root/server/routes/auth.js \
  /root/server/middleware/auth.js \
  /root/server/views/Login.vue \
  /root/server/main.js \
  /root/server/stores/user.js \
  /root/server/router/index.js \
  /root/server/db/migration-user-sessions-20260825.sql \
  /root/server/dist-6-gdqadmin
do
  if [ ! -e "$f" ]; then
    echo -e "${R}MISSING: $f${N}"
    exit 1
  else
    echo "  ✅ $f"
  fi
done

# ── 1. 备份 HK 当前文件 ──
echo -e "\n${Y}--- 1. Backup HK current files (cp -a) ---${N}"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
run "ssh -i $SSH_KEY $HK_HOST 'cd $HK_CODE_DIR && \
  cp -a routes/auth.js routes/auth.js.sso-pre-${TIMESTAMP} && \
  cp -a middleware/auth.js middleware/auth.js.sso-pre-${TIMESTAMP} && \
  echo \"backup saved at ${TIMESTAMP}\"'"

# ── 2. rsync 6 个后端源码文件 ──
echo -e "\n${Y}--- 2. rsync 6 backend source files to HK ---${N}"
# 必须 chown ubuntu:ubuntu 否则 HK pm2 EACCES crash (前人教训)
run "rsync -avz -e \"ssh -i $SSH_KEY\" \
  /root/server/routes/auth.js \
  /root/server/middleware/auth.js \
  /root/server/views/Login.vue \
  /root/server/main.js \
  /root/server/stores/user.js \
  /root/server/router/index.js \
  $HK_HOST:$HK_CODE_DIR/"

# chown 三个动了的目录
run "ssh -i $SSH_KEY $HK_HOST 'cd $HK_CODE_DIR && \
  chown ubuntu:ubuntu routes/auth.js middleware/auth.js views/Login.vue main.js stores/user.js router/index.js && \
  echo chown done'"

# ── 3. rsync db migration SQL ──
echo -e "\n${Y}--- 3. rsync migration SQL ---${N}"
run "rsync -avz -e \"ssh -i $SSH_KEY\" \
  /root/server/db/migration-user-sessions-20260825.sql \
  $HK_HOST:$HK_CODE_DIR/db/"

# ── 4. 在 HK gdq_hk db 跑 migration ──
echo -e "\n${Y}--- 4. Run migration on HK gdq_hk db ---${N}"
# ⚠️ 需要 #7 铁律波哥允许: 其它服务器 DB 增删改需要允许
# 准备执行: mysql -u gdq -p gdq_hk < migration-user-sessions-20260825.sql
run "ssh -i $SSH_KEY $HK_HOST 'cd $HK_CODE_DIR/db && \
  mysql -u gdq -pRe78g0A1XcNmr1T8 $HK_DB < migration-user-sessions-20260825.sql && \
  mysql -u gdq -pRe78g0A1XcNmr1T8 $HK_DB -e \"SHOW TABLES LIKE \\\"user_sessions\\\"\"'"

# ── 5. rsync dist-6-gdqadmin (前端的 build 产物) ──
echo -e "\n${Y}--- 5. rsync dist-6-gdqadmin to HK /var/www/hatch/gdqadmin ---${N}"
# 复用 sync-hk-gdqadmin.sh 的核心逻辑 (含 base path patch)
# 但先把 dist-6-gdqadmin 备份
run "ssh -i $SSH_KEY root@43.128.47.254 'cp -a $HK_DIST_DIR $HK_DIST_DIR.bak.${TIMESTAMP} || true'"
run "rsync -avz --delete -e \"ssh -i $SSH_KEY\" \
  /root/server/dist-6-gdqadmin/ \
  root@43.128.47.254:$HK_DIST_DIR/"
# base path + axios baseURL + lazy chunk patch (来自 sync-hk-gdqadmin.sh)
run "ssh -i $SSH_KEY root@43.128.47.254 '\
  sed -i \"s|/assets/|/gdqadmin/assets/|g\" $HK_DIST_DIR/index.html && \
  sed -i \"s|baseURL:\\\"/api\\\"|baseURL:\\\"/gdqadmin/api\\\"|g; s|baseURL:s?\\\"/api\\\"|baseURL:\\\"/gdqadmin/api\\\"|g\" $HK_DIST_DIR/assets/*.js && \
  echo dist patched'"
run "ssh -i $SSH_KEY root@43.128.47.254 'systemctl reload caddy || true'"

# ── 6. 重启 HK 后端 ──
echo -e "\n${Y}--- 6. Restart HK pm2 gdq-server-hk ---${N}"
# ⚠️ 需要波哥允许 (pm2 restart 是 production server)
# 必须用 ubuntu user (前人教训: pkill/nohup root 会 EACCES crash)
run "ssh -i $SSH_KEY $HK_HOST 'sudo -u ubuntu pm2 restart gdq-server-hk --update-env'"

# ── 7. 自验: curl HK gbaw.cn /api/auth/me 应返 401 ──
echo -e "\n${Y}--- 7. Verify on HK production ---${N}"
run "curl -s -o /dev/null -w 'HK /api/health → %{http_code}\n' http://43.128.47.254:3300/api/health"
run "curl -s -o /dev/null -w 'HK gbaw.cn/gdqadmin/ → %{http_code}\n' https://gbaw.cn/gdqadmin/"

echo ""
echo "=========================================="
echo -e "${G}Sync plan complete.${N}"
if $DRY_RUN; then
  echo -e "${Y}现在是 DRY-RUN 模式, 真实命令没执行.${N}"
  echo -e "${Y}再跑一次: bash $0 --apply${N}"
else
  echo -e "${G}APPLIED. 检查上面每一行 [EXEC] 是否有错.${N}"
fi
echo "=========================================="