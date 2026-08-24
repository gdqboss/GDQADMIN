#!/bin/bash
# ╔═══════════════════════════════════════════════════════════════════════════╗
# ║ sync-modules-by-profile.sh — 一键按白名单精准同步 (2026-08-25 B 拍板立)  ║
# ║                                                                           ║
# ║ 替代已 deprecated 的 sync-macau.sh / sync-sgp-dist.sh                    ║
# ║ 实现 AGENTS.md #21 反模式红线 (永久禁止 第 1 条: 禁止全量覆盖)          ║
# ║                                                                           ║
# ║ 用法:                                                                     ║
# ║   bash scripts/sync-modules-by-profile.sh <profile_id> [--execute]        ║
# ║                                                                           ║
# ║ 默认 dry-run, 必须显式 --execute 才真同步                                ║
# ╚═══════════════════════════════════════════════════════════════════════════╝

set -eo pipefail

PROFILE_ID=""
EXECUTE=false
ARGS=()

while [[ $# -gt 0 ]]; do
  case "$1" in
    -h|--help)
      echo "用法: bash sync-modules-by-profile.sh <profile_id> [--execute]"
      echo "  --execute:  实际 rsync (默认 dry-run)"
      echo "  --dry-run:  只生成报告 + whitelist (默认)"
      echo ""
      echo "profile_id: 7=macau, 6=hatch, 3=mywh3, 2=北京, 4=上海, 11=寺庙"
      echo "查 profile_id: mysql gdq -e 'SELECT id, name FROM server_profiles'"
      exit 0
      ;;
    --execute) EXECUTE=true; shift ;;
    --dry-run) EXECUTE=false; shift ;;
    *)
      if [[ -z "$PROFILE_ID" ]]; then
        PROFILE_ID="$1"
      else
        echo "❌ 未知参数: $1"; exit 2
      fi
      shift
      ;;
  esac
done

if [[ -z "$PROFILE_ID" ]]; then
  echo "❌ 必须指定 profile_id (例: 7=macau, 6=hatch, 3=mywh3, 2=北京)"
  echo "   查 profile_id: mysql gdq -e 'SELECT id, name FROM server_profiles'"
  exit 2
fi

# 强制 checkpoint: dry-run 不需要确认, execute 要
if $EXECUTE; then
  echo "⚠️  EXECUTE 模式: 将要实际 rsync 到目标服务器"
  echo "⚠️  profile_id=$PROFILE_ID"
  echo "⚠️  继续? (输入 YES 继续, 其它取消)"
  read -r CONFIRM
  if [[ "$CONFIRM" != "YES" ]]; then
    echo "❌ 已取消"
    exit 1
  fi
fi

cd /root/server

echo "=== Step 1/3: 生成白名单报告 (调 mock 脚本) ==="
MOCK_FLAGS="--profile=$PROFILE_ID"
if $EXECUTE; then
  MOCK_FLAGS="$MOCK_FLAGS --execute"
else
  MOCK_FLAGS="$MOCK_FLAGS --dry-run"
fi
node scripts/sync-modules-by-profile.v2.mjs "$MOCK_FLAGS"

echo ""
echo "=== Step 2/3: 取目标 SSH 信息 (从 server_profiles 查 ip + ssh_key) ==="
TARGET_INFO=$(mysql -h127.0.0.1 -ugdq -p"$DB_PASSWORD" gdq -N -B -e \
  "SELECT ip, ssh_key_path FROM server_profiles WHERE id = $PROFILE_ID" 2>/dev/null || true)

if [[ -z "$TARGET_INFO" ]]; then
  echo "⚠️  查不到 ssh_key_path, 请确认 profile $PROFILE_ID 有 ssh_key_path 字段"
  echo "   (mock 脚本走源码层 rsync 不需要 SSH, 仅当后续要推源码到目标时才需要)"
fi

echo ""
echo "=== Step 3/3: 总结 ==="
if $EXECUTE; then
  echo "✅ EXECUTE 完成 — 见上面 mock 脚本输出 (含 rsync 命令)"
  echo "⏭️  下一步: 人工评估命名映射 (DB module_key vs 源码 views/ 目录名)"
  echo "            差异已在 mock 脚本 '真相层检查' 段列出"
else
  echo "🛑 DRY-RUN 完成 — 没真同步"
  echo "   加 --execute 参数 + 输入 YES 才真同步"
fi