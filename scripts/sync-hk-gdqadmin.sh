#!/bin/bash
# sync-hk-gdqadmin.sh   SGP  HK gdqadmin  HK 
# 2026-08-23 by JXY
set -e

SRC_DIR="/root/server/dist-6-gdqadmin"
HK_HOST="root@43.128.47.254"
HK_DIR="/var/www/hatch/gdqadmin"
SSH_KEY="/root/.ssh/hk-incubator/hk_incubator_v4.pem"

if [ ! -d "$SRC_DIR" ]; then
  echo "ERROR: $SRC_DIR  build-hk-gdqadmin.sh"
  exit 1
fi

echo "=== Sync HK gdqadmin ==="
echo "Source: $SRC_DIR"
echo "Target: $HK_HOST:$HK_DIR"

echo "--- 1. Backup current HK gdqadmin ---"
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$HK_HOST" \
  "[ -d $HK_DIR ] && cp -a $HK_DIR ${HK_DIR}.bak.\$(date +%Y%m%d-%H%M%S) || echo 'no existing dir'"

echo "--- 2. rsync dist to HK ---"
rsync -avz --delete -e "ssh -i $SSH_KEY -o StrictHostKeyChecking=no" "$SRC_DIR/" "$HK_HOST:$HK_DIR/"

# 2026-08-25: base path patch
# build 出来的 index.html 里 script/link 是 /assets/...,但 SPA 挂在 /gdqadmin/ 子路径下
# 必须改成 /gdqadmin/assets/... 否则浏览器会去 https://域名/assets/ 加载 → 白屏
# (此前 HK admin/index.html 是手工 sed 过的旧版,所以一直没暴露这个坑)
echo "--- 2b. Patch base path in HK gdqadmin/index.html ---"
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$HK_HOST" \
  "sed -i 's|/assets/|/gdqadmin/assets/|g' $HK_DIR/index.html && grep -c 'gdqadmin/assets' $HK_DIR/index.html"

echo "--- 3. Reload Caddy ---"
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$HK_HOST" "systemctl reload caddy || true"

echo "=== Sync Complete ==="
