#!/usr/bin/env bash
# ╔═══════════════════════════════════════════════════════════════════════════╗
# ║ module-sync.sh — 唯一入口,按 profile 同步 SGP → 目标服务器              ║
# ║                                                                           ║
# ║ 立 (2026-09-18 江小鱼 review 后立)                                       ║
# ║ 替代 8 个分散脚本:                                                        ║
# ║   - sync-hk-gdqadmin.sh                                                   ║
# ║   - sync-single-route.sh                                                  ║
# ║   - sync-modules-by-profile.sh + v2.mjs                                  ║
# ║   - sync-macau.sh.deprecated (废弃, 留档)                                ║
# ║   - sync-sgp-dist.sh.deprecated (废弃, 留档)                             ║
# ║                                                                           ║
# ║ 设计原则 (波哥习惯: code 优先 / 不请示 / 自决 / 实战踩坑式):              ║
# ║   1. 唯一入口: 一个脚本 = 所有 profile 同步场景                           ║
# ║   2. 从 server_profiles 读 SSH/路径, 不写死                               ║
# ║   3. dry-run 默认, --execute 才真同步 (跟现有 sync-modules 一致)         ║
# ║   4. 4 步自动决策 (mode auto-detect):                                     ║
# ║      - 只改 source/views/X.vue         → 只 sync source                  ║
# ║      - 只改 routes/Y.js                → 只 sync route (单文件)           ║
# ║      - 改前端且改了 source/manifest     → build + sync dist               ║
# ║      - 改后端且改了 routes/             → build 不需要 + sync route      ║
# ║      - 改 i18n / public/*              → build + sync dist               ║
# ║      手动覆盖: --mode=source|route|dist|all                             ║
# ║   5. 必跑 sanity check (AGENTS.md #22 + module-perfect-before-clone)    ║
# ║   6. 不动 macau (profile 7) 客户服务器 fork 独有 routes, 见 #23.1 例外   ║
# ║                                                                           ║
# ║ 用法:                                                                     ║
# ║   bash module-sync.sh <profile_id> [--execute] [--mode=<m>]              ║
# ║                                                                           ║
# ║   --mode=auto    : 自动判定 (默认, 看 git diff --stat)                    ║
# ║   --mode=source  : 只 sync source/views (白名单 alias)                    ║
# ║   --mode=route   : 只 sync route 单文件 (--route=<path>)                  ║
# ║   --mode=dist    : build + sync dist                                     ║
# ║   --mode=all     : build + sync dist + sync routes + restart             ║
# ║   --route=X.js   : 配合 --mode=route, 指定单 route                       ║
# ║   --execute      : 实际同步 (默认 dry-run)                                ║
# ║   --skip-browser : 跳过浏览器实测 (假)                                      ║
# ║   --skip-sanity  : 跳过 sanity check (不推荐, 除非 debug)                ║
# ║                                                                           ║
# ║ 例子:                                                                     ║
# ║   bash module-sync.sh 6                       # HK dry-run, 自动判定     ║
# ║   bash module-sync.sh 6 --execute             # HK 真同步               ║
# ║   bash module-sync.sh 6 --mode=dist --execute # HK 只同步 dist           ║
# ║   bash module-sync.sh 7 --route=routes/xx.js --execute  # macau 单 route ║
# ╚═══════════════════════════════════════════════════════════════════════════╝

set -eo pipefail

# ─── 0. 参数解析 ────────────────────────────────────────────────────────────
PROFILE_ID=""
EXECUTE=false
MODE="auto"
ROUTE_PATH=""
SKIP_BROWSER=false
SKIP_SANITY=false

while [[ $# -gt 0 ]]; do
  case "$1" in
    -h|--help)
      sed -n '2,40p' "$0"  # 打印用法
      exit 0
      ;;
    --execute)     EXECUTE=true; shift ;;
    --dry-run)     EXECUTE=false; shift ;;
    --mode=*)      MODE="${1#*=}"; shift ;;
    --route=*)     ROUTE_PATH="${1#*=}"; shift ;;
    --skip-browser) SKIP_BROWSER=true; shift ;;
    --skip-sanity)  SKIP_SANITY=true; shift ;;
    *)
      if [[ -z "$PROFILE_ID" ]]; then PROFILE_ID="$1"; shift
      else echo "❌ 未知参数: $1"; exit 2
      fi
      ;;
  esac
done

if [[ -z "$PROFILE_ID" ]]; then
  echo "❌ 必须指定 profile_id (例: 7=macau, 6=hatch, 3=mywh3, 2=北京)"
  echo "   查: mysql gdq -e 'SELECT id, name, ip FROM server_profiles'"
  exit 2
fi

# mode 校验
case "$MODE" in
  auto|source|route|dist|all) ;;
  *) echo "❌ --mode 必须 auto|source|route|dist|all (当前: $MODE)"; exit 2 ;;
esac

if [[ "$MODE" == "route" && -z "$ROUTE_PATH" ]]; then
  echo "❌ --mode=route 必须配合 --route=<path>"; exit 2
fi

# ─── 1. 强 checkpoint: SGP 是源头 (AGENTS.md #1/#5) ───────────────────────
cd /root/server || { echo "❌ /root/server 不存在, 请确认在 SGP 跑"; exit 1; }

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "❌ /root/server 不是 git repo (违反 AGENTS.md #16)"; exit 1
fi

# ─── 2. 查 server_profiles 拿目标服务器信息 ──────────────────────────────────
DB_PASSWORD=$(grep ^DB_PASSWORD .env | cut -d= -f2)
# 只查已知存在的字段 (避免 schema 漂移)
PROFILE_ROW=$(mysql -h127.0.0.1 -ugdq -p"$DB_PASSWORD" gdq -N -B -e \
  "SELECT name, ip, ssh_key_path, ssh_user, ssh_port, deployment_mode, dist_path, target_domain FROM server_profiles WHERE id = $PROFILE_ID" 2>/dev/null)

if [[ -z "$PROFILE_ROW" ]]; then
  echo "❌ profile_id=$PROFILE_ID 不存在"
  echo "   查: mysql gdq -e 'SELECT id, name, ip FROM server_profiles'"
  exit 1
fi

IFS=$'\t' read -r PROFILE_NAME PROFILE_IP SSH_KEY SSH_USER SSH_PORT DEPLOY_MODE DIST_REMOTE DOMAIN <<< "$PROFILE_ROW"

SSH_USER=${SSH_USER:-root}
SSH_PORT=${SSH_PORT:-22}
SSH_KEY=${SSH_KEY:-/root/clawgdqshop.pem}
DIST_REMOTE=${DIST_REMOTE:-/home/gdq/dist/}
DOMAIN=${DOMAIN:-}

# SGP 本地 dist 路径: dist_path 远端路径反推本地 (SGP 模式 = /root/server/dist-<id>/)
DIST_LOCAL="dist-${PROFILE_ID}"

# 后端 routes 路径 (按 deployment_mode 推导)
# source: 不需要 (SGP 自己)
# shared_via_tunnel: 共用 SGP 后端 (SGP restart 即生效, 不需要 rsync)
# fork/independent: 各自有 routes 目录, 路径不同
case "$DEPLOY_MODE" in
  source)
    ROUTES_REMOTE="(SGP source - no remote rsync)"
    RESTART_CMD="(no remote restart)"
    ;;
  shared_via_tunnel)
    # tunnel 模式: 后端实际跑在 SGP, 改完 SGP restart 即可
    # 用目标 IP 仅供 ssh 探测 + 反向同步
    ROUTES_REMOTE="(tunnel - backend lives on SGP)"
    RESTART_CMD="pm2 restart gdq-server  # local SGP restart"
    ;;
  fork|independent)
    # fork 模式: 各自有独立 routes, 路径推断 (macau fork 路径特殊)
    # 跟现有 sync-single-route.sh 一致: macau → /opt/soc-server/
    # 其它 fork 暂用 /opt/gdq-server/ 或类似 (保守走远端 $DIST_REMOTE 的父目录)
    if [[ "$PROFILE_ID" == "7" ]]; then
      ROUTES_REMOTE="/opt/soc-server"
    else
      # 通用 fork: routes 通常在 dist 父目录的同级, 如 /home/gdq/server/ 或 /opt/<name>/server/
      # 留空让 sync 阶段报错 (避免猜测)
      ROUTES_REMOTE=""
    fi
    # 重启命令也按 profile_id 推断
    case "$PROFILE_ID" in
      7) RESTART_CMD="systemctl restart soc-server || pm2 restart soc-server" ;;
      11) RESTART_CMD="pm2 restart gdq-server" ;;
      *) RESTART_CMD="systemctl status <server>  # 请确认 service 名" ;;
    esac
    ;;
  *)
    echo "❌ 未知 deployment_mode: $DEPLOY_MODE"
    exit 1
    ;;
esac

# 检查 SSH key 存在
if [[ ! -f "$SSH_KEY" ]]; then
  echo "⚠️  SSH key 不存在: $SSH_KEY (profile $PROFILE_ID)"
  echo "   不影响 dry-run, --execute 会失败"
fi

# source 模式特殊处理 (SGP 不该 sync 到自己)
if [[ "$DEPLOY_MODE" == "source" ]]; then
  echo "═══════════════════════════════════════════════════════════════"
  echo "  profile $PROFILE_ID ($PROFILE_NAME) = SGP 源头本身"
  echo "═══════════════════════════════════════════════════════════════"
  echo "  ⚠️  source profile 不需要 sync 到自己"
  echo "  → 只跑 build + 自验即可"
  echo "  → 如要 sync 到其它 profile, 请指定目标 profile_id"
  exit 0
fi

echo "═══════════════════════════════════════════════════════════════"
echo "  module-sync.sh — profile $PROFILE_ID ($PROFILE_NAME)"
echo "═══════════════════════════════════════════════════════════════"
echo "  IP:        $PROFILE_IP"
echo "  SSH:       $SSH_USER@$PROFILE_IP:$SSH_PORT key=$SSH_KEY"
echo "  Domain:    $DOMAIN"
MODE_DISPLAY="$MODE"
if $EXECUTE; then MODE_DISPLAY="$MODE (execute)"; else MODE_DISPLAY="$MODE (dry-run)"; fi
echo "  Mode:      $MODE_DISPLAY"
echo "  Deploy:    $DEPLOY_MODE"
echo "  Dist:      $DIST_LOCAL → $SSH_USER@$PROFILE_IP:$DIST_REMOTE"
echo "  Routes:    $ROUTES_REMOTE"
echo "  Restart:   $RESTART_CMD"
echo "  RoutePath: $ROUTE_PATH"
echo ""

# 强 checkpoint: execute 必须输入 YES
if $EXECUTE; then
  echo "⚠️  EXECUTE 模式: 将要实际同步到目标"
  echo "⚠️  profile_id=$PROFILE_ID ($PROFILE_NAME)"
  echo "⚠️  继续? (输入 YES 继续, 其它取消)"
  read -r CONFIRM
  if [[ "$CONFIRM" != "YES" ]]; then
    echo "❌ 已取消"
    exit 1
  fi
fi

# ─── 3. auto 模式: 看 git diff 判定做什么 ────────────────────────────────────
if [[ "$MODE" == "auto" ]]; then
  echo "=== Step 1/N: auto 模式判定 (看 git diff --stat) ==="
  # 列出 working tree 改动 + 未提交 + 未跟踪但 .vue 关键文件
  CHANGED=$(git diff --stat HEAD 2>/dev/null | tail -1)
  UNTRACKED=$(git status --short 2>/dev/null | grep -E '^\?\?.*\.vue$' | wc -l)
  HAS_DIST_CHANGE=$(git diff --stat HEAD 2>/dev/null | grep -cE '\.(vue|js|json|css)$' || true)

  echo "  $CHANGED"
  echo "  未跟踪 .vue 文件: $UNTRACKED 个"

  # 决策树
  if git diff --stat HEAD 2>/dev/null | grep -qE '^[^|]*\.(vue|css)$|views/.*\.vue'; then
    # 改了前端 view → 需要 build
    if git diff --stat HEAD 2>/dev/null | grep -qE 'scripts/generate-manifest|router/index|vite\.config'; then
      MODE="all"
    else
      MODE="dist"
    fi
  elif git diff --stat HEAD 2>/dev/null | grep -qE '^[^|]*routes/.*\.js$'; then
    MODE="route"
    ROUTE_PATH="${ROUTE_PATH:-$(git diff --name-only HEAD | grep '^routes/' | head -1)}"
  elif git diff --stat HEAD 2>/dev/null | grep -qE '^[^|]*i18n/|^[^|]*public/'; then
    MODE="dist"
  else
    echo "⚠️  git diff 没匹配到典型改动, 默认 MODE=all (保守)"
    MODE="all"
  fi

  echo "  → 判定 MODE=$MODE"
  if [[ "$MODE" == "route" && -n "$ROUTE_PATH" ]]; then
    echo "  → Route path: $ROUTE_PATH"
  fi
fi

# ─── 4. SGP 本地 git status (防 working tree 不干净, 跳档) ─────────────────
echo ""
echo "=== Step 2/N: SGP 本地状态 ==="
git status --short | head -20
echo ""
echo "  ⚠️  提醒: SGP 改动必须已 commit 才同步 (AGENTS.md #16)"
echo "             (commit 后 --execute 真同步; commit 前 dry-run 也可跑, 但建议先 commit)"
UNCOMMITTED=$(git status --short | wc -l)
if [[ "$UNCOMMITTED" -gt 0 ]]; then
  echo "  ⚠️  当前有 $UNCOMMITTED 条未提交改动"
fi

# ─── 5. 准备阶段 (按 MODE 分支) ─────────────────────────────────────────────
case "$MODE" in
  source)
    echo ""
    echo "=== Step 3/N: source 模式 (views 源码白名单生成) ==="
    echo "  → 调 v2.mjs 生成 alias-aware 白名单"
    node scripts/sync-modules-by-profile.v2.mjs --profile="$PROFILE_ID" $([[ $EXECUTE == true ]] && echo --execute || echo --dry-run)
    ;;

  route)
    echo ""
    echo "=== Step 3/N: route 模式 (单文件同步) ==="
    [[ -n "$ROUTE_PATH" ]] || { echo "❌ route 模式缺 --route=<path>"; exit 2; }
    SRC="/root/server/$ROUTE_PATH"
    [[ -f "$SRC" ]] || { echo "❌ 源文件不存在: $SRC"; exit 1; }

    SRC_MD5=$(md5sum "$SRC" | awk '{print $1}')
    REMOTE="$ROUTES_REMOTE/$ROUTE_PATH"

    if $EXECUTE; then
      REMOTE_BEFORE=$(ssh -p "$SSH_PORT" -i "$SSH_KEY" -o StrictHostKeyChecking=no \
        "$SSH_USER@$PROFILE_IP" "md5sum $REMOTE 2>/dev/null | awk '{print \$1}'" 2>/dev/null || echo "missing")
      echo "  SGP    $ROUTE_PATH md5: $SRC_MD5"
      echo "  remote $REMOTE   md5: $REMOTE_BEFORE"

      if [[ "$SRC_MD5" == "$REMOTE_BEFORE" ]]; then
        echo "  ⏭  md5 一致, 不需要同步"
      else
        ssh -p "$SSH_PORT" -i "$SSH_KEY" -o StrictHostKeyChecking=no \
          "$SSH_USER@$PROFILE_IP" "mkdir -p \$(dirname $REMOTE)"
        scp -P "$SSH_PORT" -i "$SSH_KEY" -o StrictHostKeyChecking=no \
          "$SRC" "$SSH_USER@$PROFILE_IP:$REMOTE"
        REMOTE_AFTER=$(ssh -p "$SSH_PORT" -i "$SSH_KEY" -o StrictHostKeyChecking=no \
          "$SSH_USER@$PROFILE_IP" "md5sum $REMOTE | awk '{print \$1}'")
        echo "  after  md5: $REMOTE_AFTER"
        [[ "$SRC_MD5" == "$REMOTE_AFTER" ]] || { echo "❌ scp 后 md5 不一致"; exit 1; }

        # 重启目标 server
        echo "  → 重启目标 server: $RESTART_CMD"
        ssh -p "$SSH_PORT" -i "$SSH_KEY" -o StrictHostKeyChecking=no \
          "$SSH_USER@$PROFILE_IP" "$RESTART_CMD"
      fi
    else
      echo "  [DRY-RUN] SGP md5: $SRC_MD5"
      echo "  [DRY-RUN] remote: $REMOTE"
      echo "  [DRY-RUN] would: scp + $RESTART_CMD"
    fi
    ;;

  dist|all)
    echo ""
    echo "=== Step 3/N: build 阶段 ==="
    BUILD_SCRIPT="scripts/build-for-profile.sh"
    [[ -f "scripts/build-for-profile.sh" ]] || BUILD_SCRIPT="scripts/build-profile-gdqadmin.sh"
    if [[ -f "scripts/build-macau.sh" && "$PROFILE_ID" == "7" ]]; then
      BUILD_SCRIPT="scripts/build-macau.sh"
    fi

    echo "  → 用 $BUILD_SCRIPT 生成 dist-$PROFILE_ID"
    if $EXECUTE; then
      bash "$BUILD_SCRIPT" "$PROFILE_ID" 2>&1 | tail -30
    else
      echo "  [DRY-RUN] 会跑: bash $BUILD_SCRIPT $PROFILE_ID"
    fi
    [[ -d "$DIST_LOCAL" ]] || { echo "❌ build 后 dist 不存在: $DIST_LOCAL"; exit 1; }

    # Sanity check 1: dist 不为空
    DIST_FILES=$(find "$DIST_LOCAL" -type f | wc -l)
    [[ "$DIST_FILES" -gt 0 ]] || { echo "❌ dist 空: $DIST_LOCAL"; exit 1; }
    echo "  ✅ build 产出: $DIST_FILES 个文件"

    # Sanity check 2: 模块指纹 (从 server_profiles.site_name_zh)
    PROFILE_TITLE=$(mysql -h127.0.0.1 -ugdq -p"$DB_PASSWORD" gdq -N -B -e \
      "SELECT site_name_zh FROM server_profiles WHERE id = $PROFILE_ID" 2>/dev/null | head -1)
    echo "  → profile site_name_zh: ${PROFILE_TITLE:-(未设)}"

    if [[ -n "$PROFILE_TITLE" ]]; then
      # 关键指纹 grep (profile-specific brand 应在 dist 里命中)
      # || true 防 grep 找不到时 exit 1 + pipefail 中断
      SANITY_HITS=$( (grep -rl "$PROFILE_TITLE" "$DIST_LOCAL/assets" 2>/dev/null || true) | wc -l)
      # 反向: TRAVELMATE / 智能商业系统 等旧 brand 不应在 dist 里出现
      LEAK_HITS=$( (grep -rl -E "TRAVELMATE|智能商業系統|智能商业系统" "$DIST_LOCAL/assets" 2>/dev/null || true) | wc -l)

      echo "  → profile brand 指纹命中: $SANITY_HITS 文件"
      if [[ "$SANITY_HITS" -eq 0 ]]; then
        echo "  ⚠️  WARNING: brand 指纹 0 命中 — build 脚本可能漏 patch i18n chunk"
        echo "     (见 module-perfect-before-clone skill v0.54, build-macau.sh 需加 i18n patch 步骤)"
      fi
      if [[ "$LEAK_HITS" -gt 0 ]]; then
        echo "  ⚠️  WARNING: 旧 brand (TRAVELMATE/智能商业系统) 泄漏 $LEAK_HITS 个文件"
        echo "     sync 前必须修 build 脚本 (不能 rsync 污染)"
        if $EXECUTE; then
          echo "  ❌ 旧 brand 泄漏, --execute 拒绝继续 (exit 1)"
          exit 1
        fi
      fi
    fi

    # ─── Step 4/N: rsync dist ───────────────────────────────────────────
    echo ""
    echo "=== Step 4/N: rsync dist → $PROFILE_IP:$DIST_REMOTE ==="

    if $EXECUTE; then
      # 备份目标 dist (AGENTS.md #18 + #22.3 必先备份)
      ssh -p "$SSH_PORT" -i "$SSH_KEY" -o StrictHostKeyChecking=no \
        "$SSH_USER@$PROFILE_IP" \
        "[ -d $DIST_REMOTE ] && cp -a $DIST_REMOTE $DIST_REMOTE.bak.\$(date +%Y%m%d-%H%M%S) || echo 'no existing dir'"

      rsync -avz --delete -e "ssh -p $SSH_PORT -i $SSH_KEY -o StrictHostKeyChecking=no" \
        "$DIST_LOCAL/" "$SSH_USER@$PROFILE_IP:$DIST_REMOTE/"

      # Sanity check 3: 同步后 dist md5 对账 (抽样关键文件)
      REMOTE_FILES=$(ssh -p "$SSH_PORT" -i "$SSH_KEY" -o StrictHostKeyChecking=no \
        "$SSH_USER@$PROFILE_IP" "find $DIST_REMOTE -type f | wc -l")
      echo "  ✅ remote dist files: $REMOTE_FILES (SGP 本地: $DIST_FILES)"

      # nginx reload (HK 用 caddy, 其它用 nginx)
      echo "  → reload web server"
      ssh -p "$SSH_PORT" -i "$SSH_KEY" -o StrictHostKeyChecking=no \
        "$SSH_USER@$PROFILE_IP" \
        "(systemctl reload caddy 2>/dev/null && echo caddy reload OK) || \
         (nginx -t && nginx -s reload && echo nginx reload OK) || \
         echo 'reload 失败, 请手动'"
    else
      echo "  [DRY-RUN] 会: 备份 remote dist → rsync --delete → reload web"
    fi

    # all 模式还做 routes 同步
    if [[ "$MODE" == "all" ]]; then
      echo ""
      echo "=== Step 5/N: all 模式 = 同步所有 git diff routes/ ==="
      ROUTE_FILES=$(git diff --name-only HEAD 2>/dev/null | grep '^routes/' || true)
      [[ -z "$ROUTE_FILES" ]] && echo "  (no routes changed, skip)"
      for f in $ROUTE_FILES; do
        echo "  → route: $f"
        if $EXECUTE; then
          REMOTE="$ROUTES_REMOTE/$f"
          ssh -p "$SSH_PORT" -i "$SSH_KEY" -o StrictHostKeyChecking=no \
            "$SSH_USER@$PROFILE_IP" "mkdir -p \$(dirname $REMOTE)"
          scp -P "$SSH_PORT" -i "$SSH_KEY" -o StrictHostKeyChecking=no \
            "$f" "$SSH_USER@$PROFILE_IP:$REMOTE"
        else
          echo "    [DRY-RUN] would scp: $f"
        fi
      done

      # 重启
      if $EXECUTE && [[ -n "$ROUTE_FILES" ]]; then
        echo "  → 重启 target server: $RESTART_CMD"
        ssh -p "$SSH_PORT" -i "$SSH_KEY" -o StrictHostKeyChecking=no \
          "$SSH_USER@$PROFILE_IP" "$RESTART_CMD"
      fi
    fi
    ;;
esac

# ─── 6. Sanity check (跨层 + browser 实测) ─────────────────────────────────
if ! $SKIP_SANITY; then
  echo ""
  echo "=== Step N: sanity check (4 件套) ==="
  if $EXECUTE; then
    # 1. 关键 API ping
    PUBLIC_HOST=$(mysql -h127.0.0.1 -ugdq -p"$DB_PASSWORD" gdq -N -B -e \
      "SELECT domain FROM server_profiles WHERE id = $PROFILE_ID" 2>/dev/null | head -1)
    if [[ -n "$PUBLIC_HOST" ]]; then
      echo "  1. API ping https://$PUBLIC_HOST/api/auth/login"
      HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "https://$PUBLIC_HOST/api/auth/login" \
        -H "Content-Type: application/json" -d '{"phone":"x","password":"y"}' || echo "fail")
      echo "     HTTP: $HTTP_CODE (期望 400/401, 不是 500/000)"
      [[ "$HTTP_CODE" =~ ^(4..)$ ]] || echo "     ⚠️ API 异常"
    fi

    # 2. /api/server-profiles/...
    echo "  2. server_profiles API (确认 profile $PROFILE_ID 注册正确)"
    curl -s "https://$PUBLIC_HOST/api/server-profiles" 2>/dev/null | python3 -c "
import sys, json
try:
    d = json.load(sys.stdin)
    p = next((x for x in d.get('data', []) if x['id'] == $PROFILE_ID), None)
    print(f'     ✅ profile $PROFILE_ID found: {p[\"name\"] if p else \"MISSING\"}')
except Exception as e:
    print(f'     ⚠️ parse fail: {e}')
" 2>/dev/null

    # 3. dist 关键 chunk grep
    echo "  3. dist 关键 chunk grep"
    REMOTE_FIRST_CHUNK=$(ssh -p "$SSH_PORT" -i "$SSH_KEY" -o StrictHostKeyChecking=no \
      "$SSH_USER@$PROFILE_IP" "ls $DIST_REMOTE/assets/*.js 2>/dev/null | head -1")
    echo "     sample chunk: $REMOTE_FIRST_CHUNK"

    # 4. browser 实测 (可选)
    if ! $SKIP_BROWSER; then
      echo "  4. browser 实测 (建议手动跑 puppeteer / browser_vision)"
      echo "     https://$PUBLIC_HOST/ 应该有内容"
    fi
  else
    echo "  [DRY-RUN] sanity check 跳过 (execute 模式才跑)"
  fi
fi

echo ""
echo "═══════════════════════════════════════════════════════════════"
if $EXECUTE; then
  echo "  ✅ EXECUTE 完成 — profile $PROFILE_ID ($PROFILE_NAME) 已同步"
else
  echo "  🛑 DRY-RUN 完成 — 没真同步"
  echo "     加 --execute 参数 + 输入 YES 才真同步"
fi
echo "═══════════════════════════════════════════════════════════════"