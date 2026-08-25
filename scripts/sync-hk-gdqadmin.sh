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

# 2026-08-25: axios baseURL patch
# dist 出来的 JS 里 axios.create({baseURL:"/api"}) 写死,HK SPA 挂在 /gdqadmin/ 子路径
# 必须改成 /gdqadmin/api,否则前端请求落到主域 /api/* → 主站后端没有 phone/password 路由 → 白屏
# (index.html sed 改不到 JS bundle 内的字符串,必须独立一步)
echo "--- 2c. Patch axios baseURL in all JS chunks ---"
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$HK_HOST" \
  "sed -i 's|baseURL:\"/api\"|baseURL:\"/gdqadmin/api\"|g; s|baseURL:s?\"/api\"|baseURL:\"/gdqadmin/api\"|g' $HK_DIR/assets/*.js && grep -c 'gdqadmin/api' $HK_DIR/assets/index-*.js | head -3"

# 2026-08-25: lazy chunk path patch (Vite 5 关键陷阱)
# Vite build 出来的 chunk map 用的是相对路径 '","assets/X-Y.js","'(无前导 /)
# 浏览器解析时基于 entry 的 base path,entry 在 /gdqadmin/assets/ 下 → 应该解析成 /gdqadmin/assets/
# **但**网络抓包看:浏览器先发 /assets/X-Y.js(404)再发 /gdqadmin/assets/X-Y.js(200)双倍请求
# 第一次的 404 chunk 被 vue-error-handler 捕获 → 触发 "加载失败" dialog
# 解法:把所有 lazy chunk 引用强制改成绝对路径 "/gdqadmin/assets/X-Y.js"
# 必须同时匹配:["assets/ (数组首项) , ","assets/ (中间/结尾项)
echo "--- 2d. Patch lazy chunk paths to absolute /gdqadmin/assets/ ---"
# 用 python 代替 sed — 避免嵌套引号 + 反斜杠 escape 把 sed 模式搞废
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$HK_HOST" "python3 - << 'PYEOF'
import os, glob
fixed = remaining = 0
for path in glob.glob('$HK_DIR/assets/*.js'):
    with open(path) as f: src = f.read()
    new = src.replace('[\"assets/', '[\"/gdqadmin/assets/').replace(',\"assets/', ',\"/gdqadmin/assets/')
    if new != src:
        with open(path, 'w') as f: f.write(new)
        fixed += 1
# 验证
import re
with open('$HK_DIR/assets/index-CUOwwhEt.js') as f:
    content = f.read()
remaining = len(re.findall(r'[\"\\[,]assets/[A-Za-z0-9_-]+\\.[a-z]+\"', content))
fixed_count = len(re.findall(r'\"/gdqadmin/assets/[A-Za-z0-9_-]+\\.[a-z]+\"', content))
print(f'  files_changed={fixed} unprefixed_remaining={remaining} fixed={fixed_count}')
PYEOF"

echo "--- 3. Reload Caddy ---"
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$HK_HOST" "systemctl reload caddy || true"

echo "=== Sync Complete ==="
