#!/bin/bash
# build-macau.sh — SGP build macau (profile 7) 中医协会前端
# 波哥 2026-08-09 立: 源码必须在 SGP, 消费端 (macau) 不存源码只存产物
#
# 关键原则 (AGENTS.md #1 + 2026-08-12 零硬编码铁律):
# - module_key 从 server_modules DB 读, 不动 profile-config.js
# - 1 套 SGP 源码 + 8 套 DB 勾选 = N 客户
# - 加新模块 = views/<m>/_meta.js + routes/<m>.js + rbac_permissions + server_modules INSERT
# - 加新客户 = server_profiles + server_modules 勾选, 0 代码改动
# - router 用 SGP 全集, moduleFilterPlugin 过滤

set -e
PROFILE_ID=7
SRC_DIR="/root/server"
MODULE_DIR="$SRC_DIR/modules/$PROFILE_ID"
DIST_DIR="$SRC_DIR/dist-$PROFILE_ID"
DB_USER="gdq"
DB_PASS="Re78g0A1XcNmr1T8"
DB="gdq"

echo "=== Build macau (profile $PROFILE_ID) ==="
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

# 2. 生成 modules/7/ 目录
echo
echo "--- 2. Setting up $MODULE_DIR ---"
rm -rf "$MODULE_DIR"
mkdir -p "$MODULE_DIR"

# 3. 拷贝所有 SGP 共享文件 (views/components/layouts/stores/services/i18n/utils/constants/mock/api/styles/public)
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

# 4. 生成 modules/7/vite.config.js (enabledModules 从 DB 拉)
echo "--- 4. Writing vite.config.js ---"
cat > "$MODULE_DIR/vite.config.js" << VITEEOF
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
import moduleFilterPlugin from '../../vite-plugins/module-filter.js'
// 自动从 server_modules DB 生成 (AGENTS.md #1 + 零硬编码)
const enabledModules = [$MODULES_STR]

export default defineConfig({
  plugins: [vue(), moduleFilterPlugin(enabledModules)],
  resolve: { alias: { '@': resolve(__dirname) } },
  build: {
    outDir: '$DIST_DIR',
    assetsDir: 'assets',
    sourcemap: false,
    minify: 'esbuild',
    base: 'auto',
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


echo
echo "=== Build Complete ==="
echo "Profile $PROFILE_ID dist: $DIST_DIR ($(ls "$DIST_DIR/assets/"*.js 2>/dev/null | wc -l) chunks, $(du -sh "$DIST_DIR" | cut -f1))"