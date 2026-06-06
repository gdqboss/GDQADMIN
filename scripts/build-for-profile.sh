#!/bin/bash
PROFILE_ID=$1
if [ -z "$PROFILE_ID" ]; then
  echo "Usage: $0 <profile_id> (1=新加坡, 2=北京, 3=3号仓库)"
  exit 1
fi
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SRC_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
MODULE_DIR="$SRC_DIR/modules/$PROFILE_ID"
DIST_DIR="$SRC_DIR/dist-$PROFILE_ID"
echo "Building profile $PROFILE_ID..."
echo "Building router..."
node "$SCRIPT_DIR/build-profile-router.js" "$PROFILE_ID"
echo "Copying shared files to $MODULE_DIR..."
mkdir -p "$MODULE_DIR"
# 复制 views 目录（Vite 需要能找到 .vue 文件供动态 import）
mkdir -p "$MODULE_DIR/views"
cp -r "$SRC_DIR/views"/* "$MODULE_DIR/views/"
# 不要 rm -rf $MODULE_DIR，保留 router/index.js（由 build-profile-router.js 生成）
cp -r "$SRC_DIR/components" "$MODULE_DIR/"
cp -r "$SRC_DIR/layouts" "$MODULE_DIR/"
cp -r "$SRC_DIR/stores" "$MODULE_DIR/"
cp -r "$SRC_DIR/services" "$MODULE_DIR/"
cp -r "$SRC_DIR/i18n" "$MODULE_DIR/"
cp -r "$SRC_DIR/utils" "$MODULE_DIR/"
cp -r "$SRC_DIR/constants" "$MODULE_DIR/"
cp -r "$SRC_DIR/mock" "$MODULE_DIR/"
cp "$SRC_DIR/App.vue" "$MODULE_DIR/"
cp "$SRC_DIR/main.js" "$MODULE_DIR/"
cp "$SRC_DIR/index.html" "$MODULE_DIR/"
cp "$SRC_DIR/style.css" "$MODULE_DIR/"
cp "$SRC_DIR/postcss.config.js" "$MODULE_DIR/"
cp "$SRC_DIR/tailwind.config.js" "$MODULE_DIR/"
cat > "$MODULE_DIR/vite.config.js" << VITEEOF
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
export default defineConfig({
  plugins: [vue()],
  resolve: { alias: { '@': resolve(__dirname) } },
  build: { outDir: '$DIST_DIR', assetsDir: 'assets', sourcemap: false, minify: 'esbuild', base: 'auto' }
})
VITEEOF
echo "Running Vite build for profile $PROFILE_ID..."
cd "$MODULE_DIR" && node ../../node_modules/vite/bin/vite.js build --emptyOutDir 2>&1 | tail -5
echo ""
echo "=== Build Complete ==="
echo "Profile $PROFILE_ID dist: $DIST_DIR ($(ls "$DIST_DIR/assets/"*.js 2>/dev/null | wc -l) chunks, $(du -sh "$DIST_DIR" | cut -f1))"
