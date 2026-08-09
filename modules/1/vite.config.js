import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
import moduleFilterPlugin from '../../vite-plugins/module-filter.js'
// 2026-08-06: 自动注入 Material Symbols woff2 preload link (手机 FOIT 修复)
import materialSymbolsPreloadPlugin from '../../vite-plugins/material-symbols-preload.js'

const enabledModules = ["aftersale","ai-classroom","alerts","dashboard","dealers","excel-analyzer","finance","gift-approvals","in-out","job-responsibilities","oa","orders","products","qrcode","referral","reports","retail","returns","roles","server_profiles","settings","stores","suppliers","tasks","transfer","users","warehouses"]

export default defineConfig({
  plugins: [materialSymbolsPreloadPlugin(), vue(), moduleFilterPlugin(enabledModules)],
  resolve: { alias: { '@': resolve(__dirname) } },
  build: {
    outDir: '/root/server/dist-1',
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
