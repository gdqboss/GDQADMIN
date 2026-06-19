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
          manualChunks: (id) => {
            // 手动分包：i18n 翻译文件 + 扫码页 + 客服组件打包在一起，方便浏览器缓存校验
            if (id.includes('i18n/') || id.includes('/zh.js') || id.includes('/en.js') || id.includes('/ms.js') || id.includes('ScanPage')) {
              return 'i18n'
            }
          }
        }
      }
    }
  }
})
