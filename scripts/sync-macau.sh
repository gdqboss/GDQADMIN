#!/bin/bash
# sync-macau.sh — SGP build → rsync 到 macau 101.33.32.177
# 波哥 2026-08-09 立: 源码必须在 SGP, 消费端只拉产物
#
# 用法: bash sync-macau.sh
#
# 流程:
# 1. 跑 build-macau.sh 生成 dist-7
# 2. rsync dist-7 → macau /opt/soc-server/dist-1/
# 3. 备份 macau 老 dist (按 AGENTS.md #18 nginx backup 原则)
# 4. macau nginx reload (按 AGENTS.md #14 必先 nginx -t)
# 5. curl 验证 macau 新版生效

set -e
PROFILE_ID=7
DIST_LOCAL="/root/server/dist-$PROFILE_ID"
MACAU_HOST="101.33.32.177"
MACAU_USER="root"
MACAU_KEY="/root/.ssh/hk-incubator/hk_incubator_v4.pem"
MACAU_DIST="/opt/soc-server/dist-1"
MACAU_NGINX="/etc/nginx/conf.d/aippmcm.com.conf"

echo "=== Sync macau (SGP dist-$PROFILE_ID → macau $MACAU_DIST) ==="

# 1. 确认 SGP dist-7 存在
if [ ! -d "$DIST_LOCAL" ]; then
  echo "ERROR: $DIST_LOCAL 不存在, 请先跑 build-macau.sh"
  exit 1
fi
echo "Source: $DIST_LOCAL ($(du -sh $DIST_LOCAL | cut -f1), $(ls $DIST_LOCAL/assets/*.js | wc -l) chunks)"

# 2. SSH 确认连接 + macau dist 状态
echo
echo "--- 2. SSH test + macau dist status ---"
ssh -o ConnectTimeout=8 -i $MACAU_KEY $MACAU_USER@$MACAU_HOST '
echo "macau dist-1 现状:"
ls -la /opt/soc-server/dist-1/ 2>&1 | head -5
echo "---"
echo "macau 后端运行状态:"
ps -ef | grep -E "node.*soc-server|node.*server" | grep -v grep | head -3
echo "---"
echo "macau nginx 状态:"
systemctl is-active nginx 2>&1
'

# 3. macau 备份老 dist (按 AGENTS.md #18 backup 必须挪走)
echo
echo "--- 3. Backup macau old dist ---"
BACKUP_NAME="dist-1.bak.pre-sync-$(date +%Y%m%d-%H%M%S)"
ssh -o ConnectTimeout=8 -i $MACAU_KEY $MACAU_USER@$MACAU_HOST "
  if [ -d $MACAU_DIST ]; then
    mv $MACAU_DIST /opt/soc-server/$BACKUP_NAME
    echo \"老 dist 备份为: $BACKUP_NAME\"
    ls -la /opt/soc-server/$BACKUP_NAME/ 2>&1 | head -3
  else
    echo \"macau $MACAU_DIST 不存在, 无需备份\"
  fi
"

# 4. rsync SGP dist-7 → macau dist-1/
echo
echo "--- 4. rsync SGP → macau ---"
rsync -avz --delete \
  -e "ssh -o ConnectTimeout=8 -i $MACAU_KEY" \
  $DIST_LOCAL/ \
  $MACAU_USER@$MACAU_HOST:$MACAU_DIST/ 2>&1 | tail -10

# 5. macau nginx -t + reload (按 AGENTS.md #14)
echo
echo "--- 5. macau nginx -t + reload ---"
ssh -o ConnectTimeout=8 -i $MACAU_KEY $MACAU_USER@$MACAU_HOST '
nginx -t 2>&1 | tail -3
echo "---"
nginx -s reload 2>&1
echo "nginx reload 完成"
'

# 6. 验证 (curl 看 mtime + 静态资源)
echo
echo "--- 6. curl 验证 ---"
sleep 2
curl -sI https://aippmcm.com/ 2>&1 | head -10
echo "---"
echo "macau 静态资源 mtime (应该是新):"
ssh -o ConnectTimeout=8 -i $MACAU_KEY $MACAU_USER@$MACAU_HOST "stat -c '%y %n' $MACAU_DIST/index.html $MACAU_DIST/assets/*.woff2 2>&1 | head -3"

echo
echo "=== Sync Complete ==="
echo "回退 (如果新版本有问题):"
echo "  ssh -i $MACAU_KEY $MACAU_USER@$MACAU_HOST \"mv $MACAU_DIST /opt/soc-server/dist-1.bad && mv /opt/soc-server/$BACKUP_NAME $MACAU_DIST && nginx -s reload\""