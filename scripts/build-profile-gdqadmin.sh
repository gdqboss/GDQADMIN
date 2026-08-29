#!/bin/bash
# build-profile-gdqadmin.sh — SGP build 任意 profile 的 gdqadmin SPA
# 2026-08-23 by JXY
# 用法: bash scripts/build-profile-gdqadmin.sh <profile_id>
#
# 关键原则:
# - module_key 从 server_modules DB 读，不动 profile-config.js
# - 1 套 SGP 源码 + N 套 DB 勾选 = N 客户

set -e
PROFILE_ID=$1
if [ -z "$PROFILE_ID" ]; then
  echo "Usage: $0 <profile_id>"
  echo "Example: $0 6   # HK 横琴"
  echo "Example: $0 7   # macau"
  echo "Example: $0 11  # 海丰大道庵"
  exit 1
fi

SRC_DIR="/root/server"
MODULE_DIR="$SRC_DIR/modules/$PROFILE_ID"
DIST_DIR="$SRC_DIR/dist-$PROFILE_ID-gdqadmin"
DB_USER="gdq"
DB_PASS="Re78g0A1XcNmr1T8"
DB="gdq"

echo "=== Build gdqadmin for profile $PROFILE_ID ==="
echo "SGP source: $SRC_DIR"
echo "Module dir: $MODULE_DIR"
echo "Dist dir:   $DIST_DIR"
echo

# 1. 从 server_modules DB 拉 module_key 列表
echo "--- 1. Reading module_key from server_modules profile $PROFILE_ID ---"
MODULES_RAW=$(mysql -u$DB_USER -p"$DB_PASS" $DB -N -B -e \
  "SELECT module_key FROM server_modules WHERE server_profile_id = $PROFILE_ID ORDER BY module_key;" \
  2>/dev/null)

if [ -z "$MODULES_RAW" ]; then
  echo "ERROR: 无法从 server_modules 读 profile $PROFILE_ID modules"
  exit 1
fi
MODULES_STR=$(echo "$MODULES_RAW" | awk '{printf "\"%s\",", $1}' | sed 's/,$//')
MODULE_COUNT=$(echo "$MODULES_RAW" | wc -l)
echo "Enabled modules ($MODULE_COUNT):"
echo "$MODULES_RAW" | sed 's/^/  - /'

# 2. 生成 modules/<profile_id>/ 目录
echo
echo "--- 2. Setting up $MODULE_DIR ---"
rm -rf "$MODULE_DIR"
mkdir -p "$MODULE_DIR"

# 3. 拷贝所有 SGP 共享文件
echo "--- 3. Copying shared files ---"
mkdir -p "$MODULE_DIR/views"
cp -r "$SRC_DIR/views"/* "$MODULE_DIR/views/"
for dir in components layouts stores services i18n utils constants mock api styles public router; do
  if [ -d "$SRC_DIR/$dir" ]; then
    cp -r "$SRC_DIR/$dir" "$MODULE_DIR/"
  fi
done
for f in App.vue index.html main.js style.css postcss.config.js tailwind.config.js; do
  if [ -f "$SRC_DIR/$f" ]; then
    cp "$SRC_DIR/$f" "$MODULE_DIR/"
  fi
done

# 4. 生成 modules/<profile_id>/vite.config.js
echo "--- 4. Writing vite.config.js ---"
cat > "$MODULE_DIR/vite.config.js" << VITEEOF
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
import moduleFilterPlugin from '../../vite-plugins/module-filter.js'
// 自动从 server_modules DB 生成 (参照 AGENTS.md #1 + 零硬编码)
const enabledModules = [$MODULES_STR]

export default defineConfig({
  plugins: [vue(), moduleFilterPlugin(enabledModules)],
  resolve: { alias: { '@': resolve(__dirname) } },
  build: {
    outDir: '$DIST_DIR',
    assetsDir: 'assets',
    sourcemap: false,
    minify: 'esbuild',
    base: '/gdqadmin/',
    rollupOptions: {
      output: {
        manualChunks: (id) => {
          if (id.includes('/zh.js') || /\\/i18n\\/index\\.js/.test(id)) {
            return 'i18n-zh'
          }
          if (id.includes('/en.js')) return 'i18n-en'
          if (id.includes('/zh-HK.js')) return 'i18n-zh-HK'
          if (id.includes('i18n/')) return 'i18n-zh'
        }
      }
    }
  }
})
VITEEOF

# 5. 跑 vite build
echo "--- 5. Running Vite build for profile $PROFILE_ID ---"
cd "$MODULE_DIR" && node ../../node_modules/vite/bin/vite.js build --emptyOutDir

# 6. 专用 patch: 改 title + favicon
echo
echo "--- 6. Patching dist-$PROFILE_ID/index.html ---"
PROFILE_TITLE_ZH=$(mysql -u$DB_USER -p"$DB_PASS" $DB -N -B -e \
  "SELECT site_name_zh FROM server_profiles WHERE id = $PROFILE_ID;" 2>/dev/null)
PROFILE_TITLE_EN=$(mysql -u$DB_USER -p"$DB_PASS" $DB -N -B -e \
  "SELECT site_name_en FROM server_profiles WHERE id = $PROFILE_ID;" 2>/dev/null)
PROFILE_SITE_LOGO=$(mysql -u$DB_USER -p"$DB_PASS" $DB -N -B -e \
  "SELECT site_logo FROM server_profiles WHERE id = $PROFILE_ID;" 2>/dev/null)
echo "site_name_zh: $PROFILE_TITLE_ZH"
echo "site_name_en: $PROFILE_TITLE_EN"
echo "site_logo:    $PROFILE_SITE_LOGO"

python3 << PYEOF
import re
fp = "$DIST_DIR/index.html"
with open(fp, 'r', encoding='utf-8') as f:
    html = f.read()
orig = html

zh_title = """$PROFILE_TITLE_ZH""".strip()
if zh_title:
    html = re.sub(r'<title>[^<]*</title>', f'<title>{zh_title}</title>', html, count=1)

logo = """$PROFILE_SITE_LOGO""".strip()
if logo and logo.lower().endswith(('.png', '.webp', '.jpg', '.jpeg')):
    html = re.sub(
        r'<link rel="icon"[^>]*>',
        f'<link rel="icon" type="image/png" href="{logo}" />',
        html, count=1)
    html = re.sub(
        r'<link rel="apple-touch-icon"[^>]*>',
        f'<link rel="apple-touch-icon" href="{logo}" />',
        html, count=1)
    print(f"  favicon -> {logo}")
else:
    print("  favicon: keep original (no site_logo set or non-image)")

if html != orig:
    with open(fp, 'w', encoding='utf-8') as f:
        f.write(html)
    print(f"  title -> {zh_title}")
    print(f"  patched: {fp}")
else:
    print("  no changes")
PYEOF

echo
echo "=== Build Complete ==="
echo "Profile $PROFILE_ID dist: $DIST_DIR ($(ls \"$DIST_DIR/assets/\"*.js 2>/dev/null | wc -l) chunks, $(du -sh \"$DIST_DIR\" | cut -f1))"
