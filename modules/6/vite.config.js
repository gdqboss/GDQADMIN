import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
import moduleFilterPlugin from '../../vite-plugins/module-filter.js'
// 自动从 server_modules DB 生成 (参照 AGENTS.md #1 + 零硬编码)
const enabledModules = ["aftersale","ai-assistant","ai-classroom","ai-knowledge-domains","ai-upload","alerts","application-review","approvals","article","association-activities","attendance","auto-ops","banner-management","banners","butler-orders","cashier","collage","coupon","dashboard","dealers","edu","excel-analyzer","finance","finance-simple","gift-approvals","h5","hotel","in-out","job-responsibilities","labor-ai","labor-ai-agent","labor-appeals","labor-hr","labor-worker","logs","mall","materials","minip","mp","oa","orders","pickup","preorder","products","qrcode","queue","rbac","referral","rental","reports","resource-match","restaurant","retail","returns","roles","score_shop","settings","stores","suppliers","takeaway","tasks","temple","theme","transfer","translations","users","warehouses","wxapp","yuyue"]

export default defineConfig({
  plugins: [vue(), moduleFilterPlugin(enabledModules)],
  resolve: { alias: { '@': resolve(__dirname) } },
  build: {
    outDir: '/root/server/dist-6-gdqadmin',
    assetsDir: 'assets',
    sourcemap: false,
    minify: 'esbuild',
    // 2026-08-26 by JXY: 改用 root base '/' (北京方式)
    //   - 旧 base='/gdqadmin/' + sync step 2b/2c/2d sed 补丁链会拼出裸 host 404
    //   - 新方案: nginx /gdqadmin/* → / 301 redirect 兼容旧链接
    base: '/',
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
