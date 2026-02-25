#!/bin/bash
# 助手接入配置脚本
# 在其他OpenClaw服务器上运行

echo "======================================"
echo "  彩美特助手接入配置"
echo "======================================"

read -p "助手名称（如：江小鱼2号）: " NAME
read -p "你的用户名: " USERNAME
read -p "助手描述: " DESC

echo ""
echo "正在注册..."

RESPONSE=$(curl -s -X POST "https://bot.gdqshop.cn/api/commune/assistant_connect.php?action=register" \
  -d "assistant_name=$NAME" \
  -d "owner_username=$USERNAME" \
  -d "description=$DESC")

echo "$RESPONSE"

if echo "$RESPONSE" | grep -q '"code":1'; then
    SECRET=$(echo "$RESPONSE" | grep -o '"secret_key":"[^"]*"' | cut -d'"' -f4)
    echo ""
    echo "======================================"
    echo "  配置成功！"
    echo "======================================"
    echo ""
    echo "保存以下配置："
    echo ""
    echo "API地址: https://bot.gdqshop.cn/api/commune/assistant_connect.php"
    echo "密钥: $SECRET"
    echo ""
else
    echo "注册失败，请检查用户名是否正确"
fi
