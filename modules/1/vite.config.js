import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
export default defineConfig({
  plugins: [vue()],
  resolve: { alias: { '@': resolve(__dirname) } },
  build: { outDir: '/root/src/dist-1', assetsDir: 'assets', sourcemap: false, minify: 'esbuild', base: 'auto' }
})
