#!/usr/bin/env node
/**
 * sync-h5-source.mjs
 * 把 H5 view 同步到 uni-app minip-mp，同源单事实源
 *
 * 干的事：
 * 1. 遍历 /root/src/views/minip/*.vue
 * 2. 转译 H5 独有语法到 uni-app
 * 3. 写到 src/pages/<name>.vue
 * 4. 自动生成 pages.json + manifest.json
 */
import fs from 'node:fs'
import path from 'node:path'

const H5_DIR = '/root/src/views/minip'
const OUT_DIR = '/root/src/apps/minip-mp/src/pages'
const SRC_DIR = '/root/src/apps/minip-mp/src'

// 转译规则
const TRANSFORMS = [
  // 路由链接
  { from: /<router-link\s+to="([^"]+)"([^>]*)>/g, to: '<navigator url="$1"$2>' },
  { from: /<\/router-link>/g, to: '</navigator>' },
  // 路由跳转 JS（页面内）
  { from: /\$router\.push\(['"]([^'"]+)['"]\)/g, to: "uni.navigateTo({ url: '$1' })" },
  { from: /\$router\.back\(\)/g, to: 'uni.navigateBack()' },
  // API 路径
  { from: /from\s+['"]@\/api\/request['"]/g, to: "from '@/utils/api'" },
  // localStorage -> uni.setStorageSync
  { from: /localStorage\.getItem\(['"]([^'"]+)['"]\)/g, to: "uni.getStorageSync('$1')" },
  { from: /localStorage\.setItem\(['"]([^'"]+)['"]\s*,\s*([^)]+)\)/g, to: "uni.setStorageSync('$1', $2)" },
  { from: /localStorage\.removeItem\(['"]([^'"]+)['"]\)/g, to: "uni.removeStorageSync('$1')" },
  // ElMessage -> uni.showToast (uni-app 内置 API)
  { from: /ElMessage\.success\(([^)]+)\)/g, to: "uni.showToast({ title: $1, icon: 'success' })" },
  { from: /ElMessage\.error\(([^)]+)\)/g, to: "uni.showToast({ title: $1, icon: 'none' })" },
  { from: /ElMessage\.warning\(([^)]+)\)/g, to: "uni.showToast({ title: $1, icon: 'none' })" },
  { from: /ElMessage\.info\(([^)]+)\)/g, to: "uni.showToast({ title: $1, icon: 'none' })" },
  // alert -> uni.showModal
  { from: /alert\(([^)]+)\)/g, to: "uni.showModal({ content: $1, showCancel: false })" },
]

function translate(content) {
  for (const t of TRANSFORMS) {
    content = content.replace(t.from, t.to)
  }
  return content
}

function extractTitle(content, filename) {
  let m = content.match(/MinipLayout[^>]*title=["']([^"']+)["']/)
  if (m) return m[1]
  return filename.replace('.vue', '')
}

// 主流程
fs.mkdirSync(OUT_DIR, { recursive: true })

const files = fs.readdirSync(H5_DIR).filter(f => f.endsWith('.vue')).sort()
const pages = []

for (const f of files) {
  const name = f.replace('.vue', '')
  const src = fs.readFileSync(path.join(H5_DIR, f), 'utf8')
  let clean = translate(src)
  // 自动修正：清掉文件中重复的加载中块
  clean = clean.replace(/(<div\s+v-if="loading"\s+class="empty">加载中…<\/div>\s*){2,}/g, '<div v-if="loading" class="empty">加载中…</div>\n      ')
  clean = clean.replace(/v-if="!list\.length"/g, 'v-if="!list.length && !loading"')
  clean = clean.replace(/v-if="!?\w+\.length"\s+class="empty"\s+v-if="!loading"/g, 'v-if="!$1.length && !loading" class="empty"')
  const dest = path.join(OUT_DIR, f)
  fs.writeFileSync(dest, clean)
  const title = extractTitle(src, f)
  pages.push({ path: `pages/${name}/${name}`, name, title, source: f })
  console.log(`✅ ${f} → ${name}/${name}.vue (title="${title}")`)
}

// 生成 pages.json
const pagesJson = {
easycom: { autoscan: true, custom: {} },
pages: pages.map(p => ({
  path: `pages/${p.name}`,
  style: { navigationBarTitleText: p.title, enablePullDownRefresh: false }
}))
}
fs.writeFileSync(path.join(SRC_DIR, 'pages.json'), JSON.stringify(pagesJson, null, 2))
console.log(`\n✅ pages.json: ${pages.length} pages`)

// 生成 manifest.json（appid 是占位，真发布时替换）
const manifest = {
  name: '彩美特小程序',
  appid: 'PLACEHOLDER_REPLACE_WITH_REAL_APPID',
  description: '企业服务一站式平台',
  versionName: '1.0.0',
  versionCode: '100',
  transformPx: false,
  h5: {
    router: { mode: 'hash', base: '/minip/' },
    title: '彩美特小程序'
  },
  'mp-weixin': {
    appid: 'PLACEHOLDER_REPLACE_WITH_REAL_APPID',
    setting: { urlCheck: false, minified: true },
    usingComponents: {}
  }
}
fs.writeFileSync(path.join(SRC_DIR, 'manifest.json'), JSON.stringify(manifest, null, 2))
console.log(`✅ manifest.json (appid=PLACEHOLDER)`)

console.log(`\n=== 同步完成：${pages.length} 个 view ===`)
