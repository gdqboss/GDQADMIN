#!/usr/bin/env node
/**
 * generate-chunk-manifest.mjs
 *
 * Build 完跑一次，扫描 dist/assets/ 生成 module-chunk 映射：
 *   哪些 chunks 属于哪个模块（按 chunk name 前缀）
 *   哪些 chunks 是核心（entry/i18n/scan）必须存在
 *
 * 输出：dist/chunk-manifest.json
 *   {
 *     buildHash: "2026-08-12T...",
 *     totalChunks: 255,
 *     jsChunks: 176,
 *     cssChunks: 78,
 *     coreChunks: ["index-tUix1F9X.js", "i18n-CRmHwMls.js", "scan-BFFvFGTg.js", "index-BIpq9a-z.css"],
 *     moduleMap: {
 *       "Dashboard": ["Dashboard-XXX.js", "Dashboard-XXX.css"],
 *       "AftersaleManage": ["AftersaleManage-XXX.js"],
 *       "InOutList": ["InOutList-XXX.js", "InOutList-XXX.css"],
 *       ...
 *     },
 *     chunks: {
 *       "Dashboard-XXX.js": { size: 12345, module: "Dashboard", type: "js" },
 *       ...
 *     }
 *   }
 *
 * 用途：
 *   1. sync-sgp-to-bj.sh 用它来验证 100% chunk 都同步过去
 *   2. 更新模块前对比 moduleMap 看影响范围
 *   3. 出问题能秒级定位"哪些 chunks 缺失"
 */

import fs from 'fs'
import path from 'path'
import crypto from 'crypto'

const DIST_DIR = path.resolve(process.argv[2] || 'dist')
const ASSETS_DIR = path.join(DIST_DIR, 'assets')
const MANIFEST_PATH = path.join(DIST_DIR, 'chunk-manifest.json')
const INDEX_HTML = path.join(DIST_DIR, 'index.html')

if (!fs.existsSync(ASSETS_DIR)) {
  console.error('❌ dist/assets/ 不存在，请先 npm run build')
  process.exit(1)
}

// 1. 扫所有 chunks（assets 目录 + 根目录的 favicon/logo）
//    Vite 把 public/ 的非 js/css 资源原样拷到 dist 根（如 favicon.svg）
//    这些也要进 manifest 验证 + 同步
//    chunks key 保持原文件名（无 assets/ 前缀），路径在 stat/read 时按前缀决定
const ASSET_EXTS = /\.(js|css|svg|png|jpg|woff2?)$/
const allFiles = []
for (const f of fs.readdirSync(ASSETS_DIR)) {
  if (ASSET_EXTS.test(f)) allFiles.push(f)
}
if (fs.existsSync(DIST_DIR)) {
  for (const f of fs.readdirSync(DIST_DIR)) {
    if (ASSET_EXTS.test(f) && !f.endsWith('.html')) allFiles.push(f)
  }
}
const jsChunks = allFiles.filter(f => f.endsWith('.js'))
const cssChunks = allFiles.filter(f => f.endsWith('.css'))

// 2. 解析 index.html 找核心 chunks
const indexHtml = fs.readFileSync(INDEX_HTML, 'utf8')
const coreChunks = new Set()
const coreRegex = /(?:src|href)=["']\/assets\/([^"']+)["']/g
let m
while ((m = coreRegex.exec(indexHtml)) !== null) {
  coreChunks.add(m[1])
}

// 3. 提取 chunk 模块归属（去掉 -HASH 后缀）
//    Dashboard-AbCdEf.js → Dashboard
//    AccountsPayable-DSKzpGLP.js → AccountsPayable
const HASH_RE = /^[A-Za-z0-9]+-([A-Za-z0-9_-]{6,})\.(js|css)$/
const moduleMap = {}
const chunks = {}

for (const file of allFiles) {
  // file 可能是 'X.js' (assets 内) 或 'favicon.svg' (dist 根)
  // 先尝试 assets，没有再试 dist 根
  let fullPath = path.join(ASSETS_DIR, file)
  if (!fs.existsSync(fullPath)) fullPath = path.join(DIST_DIR, file)
  const stats = fs.statSync(fullPath)
  const ext = file.endsWith('.js') ? 'js' : (file.endsWith('.css') ? 'css' : 'asset')
  const hashMatch = file.match(HASH_RE)

  // 找模块名（去掉 -HASH.ext 部分）
  let moduleName = 'shared'
  if (hashMatch) {
    const lastDash = file.lastIndexOf('-')
    moduleName = file.substring(0, lastDash)
  } else {
    moduleName = file.includes('-') ? file.split('-')[0] : 'shared'
  }

  if (!moduleMap[moduleName]) moduleMap[moduleName] = []
  moduleMap[moduleName].push(file)

  chunks[file] = {
    size: stats.size,
    module: moduleName,
    type: ext,
    isCore: coreChunks.has(file),
    md5: crypto.createHash('md5').update(fs.readFileSync(fullPath)).digest('hex')
  }
}

const manifest = {
  buildHash: new Date().toISOString(),
  totalChunks: allFiles.length,
  jsChunks: jsChunks.length,
  cssChunks: cssChunks.length,
  totalSize: allFiles.reduce((sum, f) => {
    let fp = path.join(ASSETS_DIR, f)
    if (!fs.existsSync(fp)) fp = path.join(DIST_DIR, f)
    return sum + fs.statSync(fp).size
  }, 0),
  coreChunks: [...coreChunks].map(c => c.startsWith('assets/') ? c.replace(/^assets\//, '') : c).sort(),
  moduleMap,
  chunks
}

fs.writeFileSync(MANIFEST_PATH, JSON.stringify(manifest, null, 2))

console.log(`✅ chunk-manifest.json generated`)
console.log(`   total: ${manifest.totalChunks} (${manifest.jsChunks} js + ${manifest.cssChunks} css)`)
console.log(`   total size: ${(manifest.totalSize / 1024 / 1024).toFixed(2)} MB`)
console.log(`   core chunks: ${manifest.coreChunks.length}`)
console.log(`   modules: ${Object.keys(moduleMap).length}`)
console.log(`   output: ${MANIFEST_PATH}`)
