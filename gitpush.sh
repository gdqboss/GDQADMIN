#!/bin/bash
# 自动提交并推送到GitHub

cd /www/wwwroot/gdqshop.cn

# 添加所有更改
git add -A

# 检查是否有更改
if git diff --cached --quiet; then
    echo "没有需要提交的更改"
    exit 0
fi

# 提交更改
git commit -m "$(date '+%Y-%m-%d %H:%M:%S') 更新"

# 推送到GitHub
git push origin main

echo "已推送到 GitHub"
