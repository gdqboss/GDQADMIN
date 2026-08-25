import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
import moduleFilterPlugin from '../../vite-plugins/module-filter.js'
// 自动从 server_modules DB 生成 (参照 AGENTS.md #1 + 零硬编码)
const enabledModules = ["aftersale","ai-assistant","ai-classroom","ai-knowledge-domains","ai-upload","alerts","approvals","article","association-academic","association-activities","association-announcements","association-cards","association-downloads","association-info","association-inquiries","association-journals","association-members","association-org","attendance","auto-ops","banner-manage","banner-management","banners","cashier","collage","coupon","dashboard","dealers","diypage","edu","excel-analyzer","finance","finance-simple","gift-approvals","h5","home","hotel","hqh5","in-out","job-responsibilities","kefu","labor-ai","labor-ai-agent","labor-appeals","labor-hr","labor-worker","logistics","logs","mall","materials","minip","mp","oa","online-order","orders","pickup","portal-clone","preorder","products","queue","rbac","referral","rental","reports","restaurant","retail","returns","roles","score_shop","server_profiles","settings","stores","suppliers","takeaway","tasks","temple","theme","transfer","translations","users","warehouses","work-logs","wxapp","yuyue"]

export default defineConfig({
  plugins: [vue(), moduleFilterPlugin(enabledModules)],
  resolve: { alias: { '@': resolve(__dirname) } },
  build: {
    outDir: '/root/server/dist-11',
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
