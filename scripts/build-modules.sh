#!/bin/bash
# 彩美特模块批量重建脚本 - Windows LLM驱动
# 循环运行，直到所有模块完成

LLM_URL="http://100.74.233.52:1234/v1/chat/completions"
PROJECT="/root/caimeite-refactor"
LOG="/root/caimeite-refactor/scripts/build-log.txt"

call_llm() {
  local prompt="$1"
  curl -s -X POST "$LLM_URL" \
    -H "Content-Type: application/json" \
    -d "{\"model\":\"qwen3.5-9B\",\"messages\":[{\"role\":\"user\",\"content\":$(echo "$prompt" | jq -Rs .)}],\"max_tokens\":360}" \
    --max-time 280 2>/dev/null
}

gen_module() {
  local module="$1"
  local desc="$2"
  local api_path="$3"
  
  echo "[$(date)] 生成: $module" | tee -a "$LOG"
  
  # 读取参考组件
  local ref="/root/caimeite-refactor/src/modules/products/components/ProductList.vue"
  
  # 构建prompt
  local prompt="参考 $ref，写一个Vue3组件: $module。使用Element Plus el-table + 分页，API路径 $api_path。用<script setup>语法。只需返回<template><script setup>和<style>部分，不要解释。组件保存到 $PROJECT/src/$module/index.vue"
  
  # 调用LLM
  local response=$(call_llm "$prompt")
  local content=$(echo "$response" | jq -r '.choices[0].message.content // empty' 2>/dev/null)
  
  if [ -n "$content" ]; then
    # 清理LLM思考过程标记
    content=$(echo "$content" | sed 's/<think>.*<\/think>//gs' | tr -d '\n')
    
    mkdir -p "$(dirname "$PROJECT/src/$module/index.vue")"
    echo "<template>" > "$PROJECT/src/$module/index.vue.tmp"
    echo "$content" >> "$PROJECT/src/$module/index.vue.tmp"
    echo "</template>" >> "$PROJECT/src/$module/index.vue.tmp"
    mv "$PROJECT/src/$module/index.vue.tmp" "$PROJECT/src/$module/index.vue"
    echo "[$(date)] 完成: $module" | tee -a "$LOG"
    return 0
  else
    echo "[$(date)] 失败: $module - $response" | tee -a "$LOG"
    return 1
  fi
}

# 批次定义
BATCH_A=(
  "dealers:经销商列表:/api/dealers"
  "stores:门店列表:/api/stores"
  "suppliers:供应商列表:/api/suppliers"
  "employees:员工名录:/api/employees"
  "finance/dashboard:财务概览:/api/finance/dashboard"
  "finance/receivable:应收账款:/api/finance/receivable"
  "finance/payable:应付账款:/api/finance/payable"
  "finance/cashflow:现金流:/api/finance/cashflow"
  "finance/profit:利润分析:/api/finance/profit"
  "finance/invoice:发票管理:/api/finance/invoice"
)

BATCH_B=(
  "sales/revenues:销售收入:/api/sales/revenues"
  "sales/retail:零售记录:/api/sales/retail"
  "sales/returns:退货列表:/api/sales/returns"
  "stock/alerts:库存预警:/api/stock/alerts"
  "stock/inout:出入库记录:/api/stock/inout"
  "stock/costs:采购成本:/api/stock/costs"
  "customer/statement:客户对账:/api/customer/statement"
  "customer/feedback:客户反馈:/api/customer/feedback"
)

BATCH_C_1=(
  "attendance/manage:考勤管理:/api/attendance/manage"
  "attendance/summary:考勤汇总:/api/attendance/summary"
  "attendance/rule:考勤规则:/api/attendance/rule"
  "shift:班次管理:/api/shift"
  "schedule:排班日历:/api/schedule"
  "leave:请假管理:/api/leave"
  "approval/list:审批列表:/api/approval/list"
  "approval/create:创建审批:/api/approval/create"
)

BATCH_C_2=(
  "approval/detail:审批详情:/api/approval/detail"
  "approval/manage:审批管理:/api/approval/manage"
  "approval/settings:审批设置:/api/approval/settings"
  "report/center:报表中心:/api/report/center"
  "report/excel:Excel报表:/api/report/excel"
  "report/bi:BI看板:/api/report/bi"
  "system:系统设置:/api/system"
  "image:图片库:/api/image"
)

# 合并所有批次
ALL_BATCHES=("${BATCH_A[@]}" "${BATCH_C_1[@]}" "${BATCH_C_2[@]}" "${BATCH_B[@]}")

# 读取已完成的模块
DONE_FILE="/root/caimeite-refactor/scripts/done-modules.txt"
touch "$DONE_FILE"

for item in "${ALL_BATCHES[@]}"; do
  module=$(echo "$item" | cut -d: -f1)
  desc=$(echo "$item" | cut -d: -f2)
  api=$(echo "$item" | cut -d: -f3)
  
  # 检查是否已完成
  if grep -q "^$module$" "$DONE_FILE" 2>/dev/null; then
    echo "[$(date)] 跳过(已完成): $module"
    continue
  fi
  
  # 生成模块
  if gen_module "$module" "$desc" "$api"; then
    echo "$module" >> "$DONE_FILE"
  fi
  
  # 每完成3个模块验证一次build
  done_count=$(wc -l < "$DONE_FILE")
  if [ $((done_count % 3)) -eq 0 ]; then
    cd "$PROJECT" && npm run build >> "$LOG" 2>&1
    echo "[$(date)] Build验证完成" | tee -a "$LOG"
  fi
  
  sleep 2
done

# 最终build
cd "$PROJECT" && npm run build >> "$LOG" 2>&1
echo "[$(date)] 全部完成！最终Build完成" | tee -a "$LOG"

# Git提交
cd "$PROJECT" && git add -A && git commit -m "feat: 批量重建模块 ($(wc -l < $DONE_FILE)个)" && git push >> "$LOG" 2>&1
echo "[$(date)] Git推送完成" | tee -a "$LOG"
