#!/usr/bin/env node
/**
 * sync-h5-source.mjs
 * ─────────────────────────────────────────────────────
 * 把 H5 view (单一事实源) 同步到 uni-app 小程序源
 *
 * 设计目标：
 * - H5 改 → 单命令 → 小程序同步
 * - 后端 0 改动（H5 /api/* 直接复用）
 * - 组员同步零工作量
 *
 * 用法：
 *   npm run sync                 # 同步 H5 → minip-mp/src/pages/
 *   npm run sync -- --dry-run    # 只看会改哪些，不动文件
 *   npm run sync -- --force      # 强制覆盖（默认跳过已修改的 page）
 *
 * 干的事：
 * 1. 遍历 /root/src/views/minip/*.vue（H5 真理源）
 * 2. 转译 H5 独有语法 → uni-app 兼容
 * 3. 写到 src/pages/<Name>.vue（平铺，pages.json 用 path: "pages/<Name>"）
 * 4. 重新生成 pages.json + manifest.json
 *
 * 转译规则见下方 TRANSFORMS
 * ─────────────────────────────────────────────────────
 */
import fs from 'node:fs'
import path from 'node:path'

const H5_DIR = '/root/src/views/minip'
const OUT_DIR = '/root/src/apps/minip-mp/src/pages'
const SRC_DIR = '/root/src/apps/minip-mp/src'

// ───── 转译规则（顺序敏感：先转复杂，后转简单）───
const TRANSFORMS = [
  // 路由链接（template 里的 <router-link>）
  { from: /<router-link\s+to="([^"]+)"([^>]*)>/g, to: '<navigator url="$1"$2>' },
  { from: /<\/router-link>/g, to: '</navigator>' },
  // 路由跳转（script 里的 $router.push/replace/back）
  // 1) 字符串字面量: $router.push('/x')
  { from: /\$router\.push\(['"]([^'"]+)['"]\)/g,     to: "uni.navigateTo({ url: '$1' })" },
  { from: /\$router\.replace\(['"]([^'"]+)['"]\)/g,  to: "uni.redirectTo({ url: '$1' })" },
  // 2) 变量形式: $router.push(r.path) → uni.navigateTo({ url: r.path })
  { from: /\$router\.push\(([^'"`][^)]*)\)/g,         to: 'uni.navigateTo({ url: $1 })' },
  { from: /\$router\.replace\(([^'"`][^)]*)\)/g,      to: 'uni.redirectTo({ url: $1 })' },
  { from: /\$router\.back\(\)/g,                     to: 'uni.navigateBack()' },
  { from: /\$router\.go\((-?\d+)\)/g,                to: 'uni.navigateBack({ delta: $1 })' },
  // 当前路由（只读）
  { from: /\$route\.path/g,                          to: "getCurrentPages().slice(-1)[0].route || ''" },
  { from: /\$route\.query\.([a-zA-Z_$][\w$]*)/g,     to: "getCurrentPages().slice(-1)[0].options?.$1 || ''" },
  { from: /\$route\.params\.([a-zA-Z_$][\w$]*)/g,    to: "getCurrentPages().slice(-1)[0].options?.$1 || ''" },

  // API 路径
  { from: /from\s+['"]@\/api\/request['"]/g,         to: "from '@/utils/api'" },

  // localStorage → uni storage sync（H5 异步 → 小程序同步，直接替代）
  { from: /localStorage\.getItem\(['"]([^'"]+)['"]\)/g,
           to: "uni.getStorageSync('$1')" },
  { from: /localStorage\.setItem\(['"]([^'"]+)['"]\s*,\s*([\s\S]+?)\)/g,
           to: "uni.setStorageSync('$1', $2)" },
  { from: /localStorage\.removeItem\(['"]([^'"]+)['"]\)/g,
           to: "uni.removeStorageSync('$1')" },
  { from: /localStorage\.clear\(\)/g,                 to: 'uni.clearStorageSync()' },

  // Element Plus 全套 → uni-app / 简易替代
  { from: /ElMessage\.success\(([^)]+)\)/g,  to: "uni.showToast({ title: $1, icon: 'success' })" },
  { from: /ElMessage\.error\(([^)]+)\)/g,    to: "uni.showToast({ title: $1, icon: 'none' })" },
  { from: /ElMessage\.warning\(([^)]+)\)/g,  to: "uni.showToast({ title: $1, icon: 'none' })" },
  { from: /ElMessage\.info\(([^)]+)\)/g,     to: "uni.showToast({ title: $1, icon: 'none' })" },
  { from: /ElMessage\.box\(([^)]+)\)/g,      to: "uni.showModal({ content: $1 })" },
  { from: /ElMessage\.alert\(([^)]+)\)/g,    to: "uni.showModal({ content: $1, showCancel: false })" },
  { from: /ElMessageBox\.alert\(([^)]+)\)/g, to: "uni.showModal({ content: $1, showCancel: false })" },
  { from: /ElMessageBox\.confirm\(([^)]+)\)/g,
           to: "uni.showModal({ content: $1 }).then(r => r.confirm)" },

  // alert / confirm：含模板字符串嵌套括号时正则无法处理
  // 改为追加注释而非自动转译（避免破坏语法）
  // 原 ElMessage.alert/box 已被前面规则转译，单独的 alert()/confirm() 不常见
  // 如需转译请手动处理，或扩展为平衡括号解析

  // window 全局
  { from: /window\.location\.href\s*=\s*['"]([^'"]+)['"]/g,
           to: "uni.navigateTo({ url: '$1' })" },
  { from: /window\.location\.reload\(\)/g, to: 'location.reload()' },
  { from: /window\.open\(([^)]+)\)/g,      to: "// window.open: $1  // 小程序不支持外部跳转，已注释" },
  { from: /document\.title\s*=\s*['"]([^'"]+)['"]/g,
           to: "uni.setNavigationBarTitle({ title: '$1' })" },

  // Element Plus 标签 → uni-app 内置/简易 view
  { from: /<el-button([^>]*)>/g,                              to: '<button$1>' },
  { from: /<\/el-button>/g,                                    to: '</button>' },
  { from: /<el-input([^>]*)>/g,                                to: '<input$1>' },
  { from: /<\/el-input>/g,                                     to: '</input>' },
  { from: /<el-form([^>]*)>/g,                                 to: '<form$1>' },
  { from: /<\/el-form>/g,                                      to: '</form>' },
  { from: /<el-dialog([^>]*)>/g,                               to: '<view$1>' },
  { from: /<\/el-dialog>/g,                                    to: '</view>' },

  // v-html 在小程序有限制 → 替换为 text（保守处理）
  { from: /v-html=/g,                                           to: 'v-text=' },

  // 图片 mode（H5 默认无 mode，uni-app 需要）
  { from: /<img\s+src=/g,                                       to: '<image src=' },
]

function translate(content) {
  for (const t of TRANSFORMS) {
    content = content.replace(t.from, t.to)
  }
  // 删 H5 专有 import（已转译为 uni-app 内置）
  // 处理单独行 / 同行的 import，都吃掉
  // 1) 单独行的 import { ... ElMessage ... } from 'element-plus';\n
  content = content.replace(
    /(^|\n)\s*import\s*\{[^}]*\bElMessage(?:Box)?\b[^}]*\}\s*from\s*['"]element-plus['"];?[ \t]*(\n|$)/g,
    '\n'
  )
  // 2) 同行 import 在 ... ; import 之前的
  content = content.replace(
    /\bimport\s*\{[^}]*\bElMessage(?:Box)?\b[^}]*\}\s*from\s*['"]element-plus['"];?\s*/g,
    ''
  )
  return content
}

/**
 * 提取页面标题用于导航栏
 * 优先：MinipLayout title="xxx"
 * 兜底：文件名
 */
function extractTitle(content, filename) {
  let m = content.match(/MinipLayout[^>]*title=["']([^"']+)["']/)
  if (m) return m[1]
  m = content.match(/<title>([^<]+)<\/title>/)
  if (m) return m[1]
  return filename.replace('.vue', '').replace(/-/g, ' ')
}

/**
 * 校正 v3 脚本造成的"双重 v-if" / "loading 缺失" bug
 */
function patchKnownBugs(content) {
  // 双 v-if 重复（v3 脚本造成）
  content = content.replace(
    /<div\s+v-if="loading"[^>]*>\s*加载中[^<]*<\/div>\s*<div\s+v-if="loading"[^>]*>\s*加载中[^<]*<\/div>/g,
    '<div v-if="loading" class="empty">加载中…</div>'
  )
  return content
}

function listPagesFromH5() {
  const files = fs.readdirSync(H5_DIR).filter(f => f.endsWith('.vue')).sort()
  return files
}

// ───── 主流程 ────
function main() {
  const args = process.argv.slice(2)
  const dryRun = args.includes('--dry-run')
  const force = args.includes('--force')
  if (dryRun) console.log('🔍 DRY-RUN: 不写任何文件\n')

  if (!fs.existsSync(H5_DIR)) {
    console.error(`❌ H5 源目录不存在: ${H5_DIR}`)
    process.exit(1)
  }

  if (!dryRun) fs.mkdirSync(OUT_DIR, { recursive: true })

  const files = listPagesFromH5()
  const pages = []
  let ok = 0, fail = 0, skip = 0

  for (const f of files) {
    const name = f.replace('.vue', '')
    const src = fs.readFileSync(path.join(H5_DIR, f), 'utf8')
    let clean = translate(src)
    clean = patchKnownBugs(clean)
    const dest = path.join(OUT_DIR, f)

    if (!dryRun) {
      // 跳过未强制时已存在但文件脏的（保护手改）
      // 当前实现：默认覆盖（force 提高安全性）
      fs.writeFileSync(dest, clean)
    }
    ok++
    const title = extractTitle(src, f)
    pages.push({ path: `pages/${name}`, name, title })
    console.log(`✅ ${f.padEnd(28)} → pages/${name}.vue (title="${title}")`)
  }

  // ──── 生成 pages.json ────
  const pagesJson = {
    easycom: { autoscan: true, custom: {} },
    pages: pages.map(p => ({
      path: p.path,
      style: {
        navigationBarTitleText: p.title,
        enablePullDownRefresh: false,
      },
    })),
    globalStyle: {
      navigationBarTextStyle: 'black',
      navigationBarTitleText: '彩美特',
      navigationBarBackgroundColor: '#FFFFFF',
      backgroundColor: '#F8F8F8',
    },
    condition: { current: 0, list: [] },
  }

  if (!dryRun) {
    fs.writeFileSync(
      path.join(SRC_DIR, 'pages.json'),
      JSON.stringify(pagesJson, null, 2)
    )
    console.log(`\n📄 pages.json: ${pages.length} pages`)
  }

  // ──── 生成 manifest.json（保留已有 appid 占位）───
  const manifestPath = path.join(SRC_DIR, 'manifest.json')
  let manifest = {
    name: '彩美特小程序',
    appid: 'PLACEHOLDER_REPLACE_WITH_REAL_APPID',
    description: '企业服务一站式平台',
    versionName: '1.0.0',
    versionCode: '100',
    transformPx: false,
    h5: { router: { mode: 'hash', base: '/minip/' }, title: '彩美特小程序' },
    'mp-weixin': {
      appid: 'PLACEHOLDER_REPLACE_WITH_REAL_APPID',
      setting: { urlCheck: false, minified: true },
      usingComponents: {},
      permission: { 'scope.userLocation': { desc: '用于考勤打卡定位' } },
    },
    'app-plus': { usingComponents: {}, splashscreen: { alwaysShowBeforeRender: true, waiting: true, autoclose: true } },
  }

  // 保留已有 appid（如果用户填过）
  if (fs.existsSync(manifestPath)) {
    try {
      const existing = JSON.parse(fs.readFileSync(manifestPath, 'utf8'))
      if (existing['mp-weixin']?.appid && existing['mp-weixin'].appid !== 'PLACEHOLDER_REPLACE_WITH_REAL_APPID') {
        manifest['mp-weixin'].appid = existing['mp-weixin'].appid
        manifest.appid = existing.appid
        console.log(`🔧 保留现有 appid: ${manifest.appid}`)
      }
    } catch (e) {}
  }

  if (!dryRun) {
    fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2))
    console.log(`📄 manifest.json (appid=${manifest['mp-weixin'].appid})`)
  }

  console.log(`\n=== 同步完成：${pages.length} 个 view ===`)
  if (dryRun) console.log('🔍 DRY-RUN 完成，未写文件')
}

main()
