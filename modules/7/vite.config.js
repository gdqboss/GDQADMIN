import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
import moduleFilterPlugin from '../../vite-plugins/module-filter.js'
import materialSymbolsPreloadPlugin from '../../vite-plugins/material-symbols-preload.js'

// 自动从 server_modules DB 生成 (AGENTS.md #1 + 零硬编码)
const enabledModules = ["approvals","article","association-academic","association-activities","association-announcements","association-cards","association-downloads","association-info","association-inquiries","association-journals","association-members","association-org","banner-manage","banner-management","dashboard","finance","finance-simple","job-responsibilities","logistics","oa","rbac","reports","roles","settings","tasks","users","work-logs"]

export default defineConfig({
  plugins: [materialSymbolsPreloadPlugin(), vue(), moduleFilterPlugin(enabledModules)],
  resolve: { alias: { '@': resolve(__dirname) } },
  build: {
    outDir: '/root/server/dist-7',
    assetsDir: 'assets',
    sourcemap: false,
    minify: 'esbuild',
    base: 'auto',
    rollupOptions: {
      output: {
        manualChunks: (id) => {
          if (id.includes('/zh.js') || /\/i18n\/index\.js/.test(id)) {
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
