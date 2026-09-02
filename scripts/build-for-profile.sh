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
# 2026-09-02: 复制 shared/ (V2 page + composable 用)
if [ -d "$SRC_DIR/shared" ]; then
  cp -r "$SRC_DIR/shared" "$MODULE_DIR/"
fi
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
    base: '/gdqadmin/',
    // 2026-08-15 急修: base 'auto' 在 SGP/HK 部署时推断为 '/', 导致懒加载 chunk
    //   (Login-xxx.js 等路由级 split chunk) 用相对路径 './Login-xxx.js' 被浏览器
    //   解析为 '/assets/Login-xxx.js' 走 Caddy 根路径 (返回 portal HTML)
    //   → 'Failed to load module script: MIME text/html' → SPA 整个初始化失败
    //   → 表现为'新用户注册不正常 / 角色管理新增不行'
    // 改 '/gdqadmin/' 后, 懒加载 chunk 会用 '/gdqadmin/assets/Login-xxx.js' 匹配
    //   Caddyfile 的 @gdqadminAssets handler → 正常 200 application/javascript
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

# 2026-08-15 急修: Vite 5.4 的 build.base '/gdqadmin/' 在某些情况下会被 decodedBase='/' 覆盖,
#   导致产物 index.html 里 <script src> 仍是 /assets/ 根路径, 懒加载 chunk 也用相对路径
#   → Caddy 根路径 /assets/* 返回 portal HTML, 浏览器 'MIME text/html' 错
# 暴力 sed 把 /assets/ → /gdqadmin/assets/ 写进产物 HTML, 保证 dist 部署在 /gdqadmin/* 下可用
if [ "$PROFILE_ID" = "1" ] || [ "$PROFILE_ID" = "6" ] || [ "$PROFILE_ID" = "7" ]; then
  # profile 1/6/7 用 /gdqadmin/ 前缀
  # 2026-08-15 修复: base '/gdqadmin/' 时, vite 输出的 index.html 已经是 /gdqadmin/assets/
  #   sed '/assets/' → '/gdqadmin/assets/' 会变成 /gdqadmin/gdqadmin/assets/ 双重前缀
  #   改成只 sed "= "/assets/" (前面是 / 或开头, 前面不是字母数字), 跳过 /gdqadmin/assets/
  if [ -f "$DIST_DIR/index.html" ]; then
    sed -i 's|"/assets/|"/gdqadmin/assets/|g' "$DIST_DIR/index.html"
    echo "Patched index.html: \"/assets/ → \"/gdqadmin/assets/ (skip /gdqadmin/assets/)"
  fi
  # 同步修 gdqadmin/index.html (build 脚本会复制一个备份的 admin/index.html)
  if [ -f "$DIST_DIR/gdqadmin/index.html" ]; then
    sed -i 's|"/assets/|"/gdqadmin/assets/|g' "$DIST_DIR/gdqadmin/index.html"
    echo "Patched gdqadmin/index.html: same"
  fi
  # 2026-08-15: 修 vite 没处理的开发路径残留 (../node_modules/...)
  # styles/material-symbols-font-display.css 里硬编了 ../node_modules/...
  # 直接 sed 改成 /gdqadmin/assets/ 让 fallback 路径能命中
  for css in "$DIST_DIR/assets/"*.css; do
    sed -i 's|\.\./node_modules/material-symbols/[^)]*|/gdqadmin/assets/material-symbols-outlined-Bgl3Icaq.woff2|g' "$css"
  done
  echo "Patched CSS: ../node_modules/material-symbols/* → /gdqadmin/assets/..."
fi
