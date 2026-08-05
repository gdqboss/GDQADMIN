import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
import moduleFilterPlugin from './vite-plugins/module-filter.js'

/**
 * Vite 配置
 *
 * 关键：module-filter 插件实现"按模块构建"——
 *   VITE_ENABLED_MODULES=products,in-out,users  vite build
 *   → 只在 dist 中保留启用模块的 chunks，删除未启用的孤立文件
 *
 * 不传 VITE_ENABLED_MODULES = 全量构建（dev/新加坡默认）
 */
export default defineConfig(({ mode }) => {
  // 读环境变量：VITE_ENABLED_MODULES=products,in-out,users
  const enabledModules = process.env.VITE_ENABLED_MODULES
    ? process.env.VITE_ENABLED_MODULES.split(',').map(s => s.trim()).filter(Boolean)
    : null

  if (enabledModules) {
    console.log('[vite.config] 模块化构建模式：启用 ' + enabledModules.length + ' 个模块')
    console.log('[vite.config] 模块列表：' + enabledModules.join(', '))
  } else {
    console.log('[vite.config] 全量构建模式（不启用模块过滤）')
  }

  return {
    plugins: [
      vue(),
      moduleFilterPlugin(enabledModules)
    ],
    resolve: {
      alias: {
        '@': resolve(__dirname)
      }
    },
    server: {
      port: 5173,
      proxy: {
        '/api': {
          target: 'http://localhost:3200',
          changeOrigin: true
        },
        '/uploads': {
          target: 'http://localhost:3200',
          changeOrigin: true
        }
      }
    },
    build: {
      outDir: 'dist',
      assetsDir: 'assets',
      emptyOutDir: true,
      sourcemap: false,
      minify: 'esbuild',
      base: 'auto',
      rollupOptions: {
        output: {
          // 2026-08-06 修复: 删掉 i18n 强制合并
          //   之前: zh.js + en.js + ms.js 强制进 'i18n' chunk → 一个 477KB bundle 三语言混在一起
          //   修复: 让 vite 按 dynamic import 自动拆 chunk
          //   - zh.js 静态 import (在 i18n/index.js 顶部) → 进主 bundle
          //   - en.js / ms.js 动态 import (await import(`./${locale}.js`)) → 独立 chunk, 用户切换时才下载
          // 2026-08-06 江小鱼加: vendor split (解決 macau /gdqadmin 首次加載慢)
          //   把 vue / pinia / vue-router / axios / element-plus / icons / i18n 拆到獨立 vendor chunk
          //   → 瀏覽器並行下載 + 緩存命中, main bundle 從 1.19MB → ~300KB
          manualChunks(id) {
            if (!id.includes('node_modules')) return undefined
            if (id.includes('@element-plus/icons-vue')) return 'vendor-icons'
            if (id.includes('element-plus')) return 'vendor-element-plus'
            if (id.includes('vue-demi') || id.match(/\/vue\//)) return 'vendor-vue'
            if (id.includes('pinia')) return 'vendor-pinia'
            if (id.includes('vue-router')) return 'vendor-router'
            if (id.includes('axios')) return 'vendor-axios'
            if (id.includes('vue-i18n') || id.includes('/i18n/')) return 'vendor-i18n'
            if (id.includes('@vueuse') || id.includes('echarts') || id.includes('xlsx')) return 'vendor-misc'
            return 'vendor-misc'
          }
        }
      }
    }
  }
})
