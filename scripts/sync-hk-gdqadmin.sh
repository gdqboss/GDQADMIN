#!/bin/bash
# sync-hk-gdqadmin.sh   SGP  HK gdqadmin  HK
# 2026-08-23 by JXY
# 2026-08-26 by JXY: 北京方式 — 删 step 2b/2c/2d sed 补丁链
#   - 旧补丁链 (base='/gdqadmin/' + sed patch index.html + axios baseURL + lazy chunk path)
#     已被根 base='/' 取代,Vite build 输出本身就是绝对 '/assets/...'
#   - nginx /gdqadmin/* 301 → /* 兼容旧书签
#   - HK gdqadmin 现在跟北京一样 = root base '/' 部署

set -e

SRC_DIR="/root/server/dist-6-gdqadmin"
HK_HOST="root@43.128.47.254"
HK_DIR="/var/www/hatch/gdqadmin"
SSH_KEY="/root/.ssh/hk-incubator/hk_incubator_v4.pem"

if [ ! -d "$SRC_DIR" ]; then
  echo "ERROR: $SRC_DIR not found — run scripts/build-hk-gdqadmin.sh first"
  exit 1
fi

echo "=== Sync HK gdqadmin (北京方式 root base) ==="
echo "Source: $SRC_DIR"
echo "Target: $HK_HOST:$HK_DIR"

echo "--- 1. Backup current HK gdqadmin ---"
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$HK_HOST" \
  "[ -d $HK_DIR ] && cp -a $HK_DIR ${HK_DIR}.bak.$(date +%Y%m%d-%H%M%S) || echo 'no existing dir'"

echo "--- 2. rsync dist to HK (root base, 无 sed 补丁) ---"
rsync -avz --delete -e "ssh -i $SSH_KEY -o StrictHostKeyChecking=no" "$SRC_DIR/" "$HK_HOST:$HK_DIR/"

# 2026-08-26 by JXY: 旧 step 2b/2c/2d sed 补丁链 (base patch / axios baseURL / lazy chunk 绝对路径)
#   全部废弃。原因: build 出来的产物 base='/' 已自带正确绝对路径,
#   sed 改相对路径= 制造 bug (裸 host 404 根因)。
#   nginx /gdqadmin/* 301 → /* 已经处理旧链接兼容。

echo "--- 3. nginx /gdqadmin/* 301 → /* (旧链接兼容) ---"
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$HK_HOST" \
  "grep -q 'location ~* ^/gdqadmin/' /etc/caddy/Caddyfile /etc/nginx/conf.d/*.conf 2>/dev/null && echo '301 redirect 已配置' || echo '⚠️  WARNING: nginx 301 redirect 未配置, 请手动加'"

echo "--- 4. Reload Caddy/nginx ---"
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$HK_HOST" \
  "(systemctl reload caddy 2>/dev/null && echo 'caddy reload OK') || (nginx -t && nginx -s reload && echo 'nginx reload OK') || echo 'reload 失败, 请手动 reload'"

echo "=== Sync Complete ==="