#!/bin/bash
echo "=== 彩美特多服务器构建验证 ==="
echo ""

# 显示各 profile 的模块数量
for profile in 1 2 3; do
  dist_dir="/root/src/dist-${profile}"
  if [ -d "$dist_dir" ]; then
    files=$(find "$dist_dir" -name "*.js" | wc -l)
    chunks=$(ls "$dist_dir/assets/"*.js 2>/dev/null | wc -l)
    echo "Profile ${profile}: $files JS files, $chunks chunks"
  else
    echo "Profile ${profile}: 未构建"
  fi
done

echo ""
echo "=== 同步状态 ==="
# 新加坡（本地）
echo "新加坡: /home/gdq/dist/ ($(ls /home/gdq/dist/assets/*.js 2>/dev/null | wc -l) chunks)"

# 北京（通过 SSH）
# BJ_IP="81.70.199.64"
# BJ_KEY="/root/clawgdqshop.pem"
# BJ_CHUNKS=$(ssh -i $BJ_KEY -p 2222 ubuntu@$BJ_IP "ls /home/gdq/dist/assets/*.js 2>/dev/null | wc -l" 2>/dev/null || echo "连接失败")
# echo "北京: /home/gdq/dist/ ($BJ_CHUNKS chunks)"

# 3号仓库
# HK_IP="43.160.238.201"
# HK_KEY="/root/PEMS/3hck.pem"
# HK_CHUNKS=$(ssh -i $HK_KEY ubuntu@$HK_IP "ls /var/www/caimeite/dist/assets/*.js 2>/dev/null | wc -l" 2>/dev/null || echo "连接失败")
# echo "3号仓库: /var/www/caimeite/dist/ ($HK_CHUNKS chunks)"
