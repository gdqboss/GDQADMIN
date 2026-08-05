import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
import moduleFilterPlugin from '../../vite-plugins/module-filter.js'

const enabledModules = ["aftersale","ai-classroom","alerts","dashboard","dealers","excel-analyzer","finance","gift-approvals","in-out","job-responsibilities","oa","orders","products","qrcode","referral","reports","retail","returns","roles","server_profiles","settings","stores","suppliers","tasks","transfer","users","warehouses"]

export default defineConfig({
  plugins: [vue(), moduleFilterPlugin(enabledModules)],
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
          if (id.includes('i18n/') || id.includes('/zh.js') || id.includes('/en.js') || id.includes('/ms.js')) {
            return 'i18n'
          }
        }
      }
    }
  }
})
