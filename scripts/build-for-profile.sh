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
# 同步读 modules 列表（从 build-profile-router.js 拿的 modules）
MODULES_STR=$(node -e "import('./modules/profile-config.js').then(m => console.log(JSON.stringify(m.PROFILE_MODULES[$PROFILE_ID])))" --input-type=module 2>/dev/null || \
  node --input-type=module -e "import('file://$SRC_DIR/modules/profile-config.js').then(m => console.log(JSON.stringify(m.PROFILE_MODULES[$PROFILE_ID])))")
if [ -z "$MODULES_STR" ]; then
  echo "ERROR: 无法读取 modules 列表"
  exit 1
fi
echo "Enabled modules: $MODULES_STR"
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
cp -r "$SRC_DIR/api" "$MODULE_DIR/"
# 2026-08-06: copy styles/ (main.js 引用 ./styles/material-symbols-font-display.css)
if [ -d "$SRC_DIR/styles" ]; then
  cp -r "$SRC_DIR/styles" "$MODULE_DIR/"
fi
cp "$SRC_DIR/App.vue" "$MODULE_DIR/"
cp "$SRC_DIR/main.js" "$MODULE_DIR/"
cp "$SRC_DIR/index.html" "$MODULE_DIR/"
cp "$SRC_DIR/style.css" "$MODULE_DIR/"
cp "$SRC_DIR/postcss.config.js" "$MODULE_DIR/"
cp "$SRC_DIR/tailwind.config.js" "$MODULE_DIR/"
# 复制 public/ 目录（logo.jpg 等静态资源）
if [ -d "$SRC_DIR/public" ]; then
  cp -r "$SRC_DIR/public" "$MODULE_DIR/"
fi
cat > "$MODULE_DIR/vite.config.js" << VITEEOF
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
import moduleFilterPlugin from '../../vite-plugins/module-filter.js'
// 2026-08-06: 自动注入 Material Symbols woff2 preload link (手机 FOIT 修复)
import materialSymbolsPreloadPlugin from '../../vite-plugins/material-symbols-preload.js'

const enabledModules = $MODULES_STR

export default defineConfig({
  plugins: [materialSymbolsPreloadPlugin(), vue(), moduleFilterPlugin(enabledModules)],
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
          // 2026-08-06 修复懒加载: zh.js 静态 import → 独立 chunk (zh 必加载)
          //                          en/ms/zh-HK 动态 import → 各自分离 chunk (按需下载)
          // 之前全打成一捆 (i18n-Xxx.js 477KB), 初次打开就下载所有语言, 违反"按需加载"架构
          if (id.includes('/zh.js') || /\/i18n\/index\.js/.test(id)) {
            return 'i18n-zh'  // zh 默认语言, 主 bundle 必带
          }
          if (id.includes('/en.js')) {
            return 'i18n-en'  // en 独立 chunk, 用户切 en 时才下载
          }
          if (id.includes('/ms.js')) {
            return 'i18n-ms'  // ms 独立 chunk, 用户切 ms 时才下载
          }
          if (id.includes('/zh-HK.js')) {
            return 'i18n-zh-HK'  // zh-HK 独立 chunk, profile 6/7 用户切时下载
          }
          // 其它 i18n 辅助文件 (utils 等) 进 i18n-zh 即可
          if (id.includes('i18n/')) {
            return 'i18n-zh'
          }
        }
      }
    }
  }
})
VITEEOF
echo "Running Vite build for profile $PROFILE_ID..."
cd "$MODULE_DIR" && node ../../node_modules/vite/bin/vite.js build --emptyOutDir

# 2026-08-06 BUG FIX: 自动注入 woff2 preload link 到 dist/index.html
# 找到实际 hash 后替换 MATERIALSYMBOLSWOFF2 占位符
WOFF2_FILE=$(ls "$DIST_DIR/assets/"material-symbols-outlined-*.woff2 2>/dev/null | head -1 | xargs -I{} basename {})
if [ -n "$WOFF2_FILE" ]; then
  sed -i "s|MATERIALSYMBOLSWOFF2|/assets/$WOFF2_FILE|" "$DIST_DIR/index.html"
  echo "Material Symbols preload: /assets/$WOFF2_FILE"
else
  echo "WARNING: material-symbols-outlined woff2 not found in $DIST_DIR/assets/"
fi

echo ""
echo "=== Build Complete ==="
echo "Profile $PROFILE_ID dist: $DIST_DIR ($(ls "$DIST_DIR/assets/"*.js 2>/dev/null | wc -l) chunks, $(du -sh "$DIST_DIR" | cut -f1))"
