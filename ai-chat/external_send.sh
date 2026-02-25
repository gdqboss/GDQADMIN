#!/bin/bash
# 发送消息到彩美特服务器
# 用法: ./external_send.sh "secret_key" "消息内容" [user|assistant]

SECRET=${1:-}
CONTENT=${2:-}
ROLE=${3:-user}

if [ -z "$SECRET" ] || [ -z "$CONTENT" ]; then
    echo "用法: $0 <secret_key> <消息内容> [user|assistant]"
    exit 1
fi

curl -s -X POST "https://bot.gdqshop.cn/ai-chat/api/external.php?action=send_message" \
  -d "secret=$SECRET" \
  -d "content=$CONTENT" \
  -d "role=$ROLE"
