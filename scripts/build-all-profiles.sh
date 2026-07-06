#!/usr/bin/env bash
# build-all-profiles.sh
#
# 一键跑 3 个 profile build：
#   bash scripts/build-all-profiles.sh           # 跑全部 3 个
#   bash scripts/build-all-profiles.sh --only 1  # 只跑 profile 1
#   bash scripts/build-all-profiles.sh --only 1,2
#   bash scripts/build-all-profiles.sh --clean   # 跑前清空 dist-{1,2,3}
#
# 产物：
#   dist/    = sgp 全集（profile 1）
#   dist-2/  = 北京（少 server_profiles）
#   dist-3/  = 3号仓库（少 server_profiles, gift-approvals, qrcode, referral, suppliers）
#
# 设计：每个 profile 独立目录，互不污染。验证命令：
#   bash scripts/build-all-profiles.sh --verify  # 列出每个 dist 的关键模块存在性

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SRC_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log()  { echo -e "${BLUE}[build-all]${NC} $*"; }
ok()   { echo -e "${GREEN}[ok]${NC} $*"; }
warn() { echo -e "${YELLOW}[warn]${NC} $*"; }
fail() { echo -e "${RED}[fail]${NC} $*"; exit 1; }

ONLY=""
CLEAN=false
VERIFY=false
for arg in "$@"; do
  case $arg in
    --only=*) ONLY="${arg#--only=}" ;;
    --clean)  CLEAN=true ;;
    --verify) VERIFY=true ;;
    -h|--help)
      sed -n '2,/^set -euo pipefail/p' "$0" | sed 's/^#//' | sed '/^set -euo pipefail$/d'
      exit 0
      ;;
    *) fail "未知参数: $arg" ;;
  esac
done

# --verify 模式：只检查 3 个 dist 的关键模块差异，不重新 build
if $VERIFY; then
  log "验证 3 个 dist 的关键模块差异..."
  for p in 1 2 3; do
    DIST="$SRC_DIR/dist-$p"
    [ "$p" = "1" ] && DIST="$SRC_DIR/dist"
    if [ ! -d "$DIST" ]; then
      warn "  profile $p: $DIST 不存在（先 build）"
      continue
    fi
    COUNT=$(ls "$DIST/assets/"*.js 2>/dev/null | wc -l)
    SIZE=$(du -sh "$DIST" | cut -f1)
    log "  profile $p: $DIST - $COUNT chunks, $SIZE"
  done
  echo
  log "关键模块存在性检查（期望 profile-1 都有，profile-2 无 server_profiles，profile-3 再少 4 个）:"
  declare -A KEY_MODULES=(
    [AftersaleManage]='aftersale (sgp/bj/3hk 都有)'
    [QrcodeManage]='qrcode (sgp/bj 有, 3hk 无)'
    [ServerProfiles]='server_profiles (sgp 有, bj/3hk 无)'
    [GiftApprovals]='gift_approvals (sgp/bj 有, 3hk 无)'
    [Suppliers]='suppliers (sgp/bj 有, 3hk 无)'
  )
  for mod in "${!KEY_MODULES[@]}"; do
    desc="${KEY_MODULES[$mod]}"
    line=""
    for p in 1 2 3; do
      DIST="$SRC_DIR/dist-$p"
      [ "$p" = "1" ] && DIST="$SRC_DIR/dist"
      if ls "$DIST/assets/${mod}-"*.js 2>/dev/null | head -1 > /dev/null; then
        line="$line profile-$p=✓ "
      else
        line="$line profile-$p=✗ "
      fi
    done
    echo "  $mod ($desc):$line"
  done
  exit 0
fi

# 默认只跑 profile 1
if [ -z "$ONLY" ]; then
  ONLY="1"
fi

# clean 模式：跑前清空 dist-{1,2,3}
if $CLEAN; then
  for p in 1 2 3; do
    if [[ ",$ONLY," == *",$p,"* ]]; then
      DIST="$SRC_DIR/dist-$p"
      [ "$p" = "1" ] && DIST="$SRC_DIR/dist"
      log "clean: $DIST"
      rm -rf "$DIST"
    fi
  done
fi

# 跑 build
for p in $(echo "$ONLY" | tr ',' ' '); do
  case $p in
    1|2|3) ;;
    *) fail "未知 profile: $p (合法: 1, 2, 3)" ;;
  esac
  log "=== Profile $p ==="
  cd "$SCRIPT_DIR"
  bash "build-for-profile.sh" "$p" 2>&1 | tail -5
  ok "Profile $p 完成"
done

ok "全部完成 ✅"
log "产物:"
for p in 1 2 3; do
  DIST="$SRC_DIR/dist-$p"
  [ "$p" = "1" ] && DIST="$SRC_DIR/dist"
  if [ -d "$DIST" ]; then
    COUNT=$(ls "$DIST/assets/"*.js 2>/dev/null | wc -l)
    SIZE=$(du -sh "$DIST" | cut -f1)
    log "  $DIST - $COUNT chunks, $SIZE"
  fi
done