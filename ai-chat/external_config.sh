#!/bin/bash
# 外部助手接入配置脚本
# 在其他OpenClaw服务器上运行此脚本进行配置

echo "======================================"
echo "  彩美特 AI 助手接入配置"
echo "======================================"

# 读取配置
read -p "请输入你的名字(例如: 江小鱼): " ASSISTANT_NAME
read -p "请输入主人ID(默认1): " OWNER_ID
OWNER_ID=${OWNER_ID:-1}

# 获取服务器配置
SERVER_URL="https://bot.gdqshop.cn"

# 注册助手
echo ""
echo "正在注册助手..."

RESPONSE=$(curl -s -X POST "$SERVER_URL/ai-chat/api/external.php?action=register" \
  -d "assistant_name=$ASSISTANT_NAME" \
  -d "owner_id=$OWNER_ID" \
  -d "endpoint=")

echo "$RESPONSE"

if echo "$RESPONSE" | grep -q '"code":1'; then
    SECRET=$(echo "$RESPONSE" | grep -o '"secret_key":"[^"]*"' | cut -d'"' -f4)
    
    echo ""
    echo "======================================"
    echo "  配置成功！"
    echo "======================================"
    echo ""
    echo "请保存以下配置信息："
    echo ""
    echo "API地址: $SERVER_URL/ai-chat/api/external.php"
    echo "密钥: $SECRET"
    echo ""
    echo "下一步："
    echo "1. 在你的OpenClaw中配置定时任务"
    echo "2. 每次对话后调用 API 保存消息"
    echo ""
else
    echo "注册失败，请重试"
fi
