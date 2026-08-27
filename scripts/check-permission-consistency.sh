#!/usr/bin/env bash
# check-permission-consistency.sh — 波哥 2026-08-27 P2 CI 门禁
# 跑法: bash scripts/check-permission-consistency.sh
# 退出码: 0 全过, 1 有问题
set -e
cd "$(dirname "$0")/.."

echo "==================== RBAC 一致性 CI 检查 ===================="
node scripts/check-permission-consistency.mjs
RC=$?
echo "================================================================"
if [ $RC -ne 0 ]; then
  echo "❌ CI 失败 (exit=$RC)"
  exit 1
fi
echo "✅ CI 通过"
exit 0
