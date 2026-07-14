#!/usr/bin/env node
/**
 * inspect.mjs — 现状盘点
 * 跑完输出 H5 / mini-mp / 后端 一致性报告
 */
import fs from 'node:fs'
import path from 'node:path'

const H5_DIR = '/root/src/views/minip'
const MP_DIR = path.join(process.cwd(), 'src/pages')

console.log('\n═══ minip-mp 项目盘点 ═══\n')

// 1. H5 view 数
const h5Files = fs.readdirSync(H5_DIR).filter(f => f.endsWith('.vue'))
console.log(`📁 H5 真理源 (${H5_DIR}):  ${h5Files.length} 个 view`)

// 2. minip-mp pages 数
const mpFiles = fs.readdirSync(MP_DIR).filter(f => f.endsWith('.vue'))
console.log(`📁 minip-mp pages (${MP_DIR}): ${mpFiles.length} 个 page`)

// 3. 一致性
const h5Names = new Set(h5Files.map(f => f.replace('.vue', '')))
const mpNames = new Set(mpFiles.map(f => f.replace('.vue', '')))
const missing = [...h5Names].filter(n => !mpNames.has(n))
const extra = [...mpNames].filter(n => !h5Names.has(n))
console.log()
if (missing.length === 0 && extra.length === 0) {
  console.log('✅ 一致：所有 H5 view 都被同步到 minip-mp')
} else {
  console.log('⚠️  不一致:')
  if (missing.length) console.log('   missing in mp:', missing.join(', '))
  if (extra.length) console.log('   extra in mp:', extra.join(', '))
}

// 4. 残留 H5 专属语法扫描
const PROBLEMS = [
  ['$router.push',     /\$router\.push/],
  ['$route.',           /\$route\./],
  ['localStorage',      /localStorage/],
  ['ElMessage',         /\bElMessage/],
  ['element-plus',      /element-plus/],
  ['@/api/request',     /@\/api\/request/],
  ['<el-button',        /<el-button/],
  ['window.location',   /window\.location/],
  ['document.title',    /document\.title/],
]

console.log('\n🔍 minip-mp pages 残留 H5 语法扫描:')
const dirty = []
for (const f of mpFiles) {
  const src = fs.readFileSync(path.join(MP_DIR, f), 'utf8')
  const hits = PROBLEMS.filter(([_, p]) => p.test(src)).map(([l]) => l)
  if (hits.length) dirty.push([f, hits])
}
if (dirty.length === 0) {
  console.log('  ✅ 全部 34 个 page 干净，无残留')
} else {
  console.log(`  ❌ ${dirty.length} 个 page 残留:`)
  for (const [f, h] of dirty) console.log(`     ${f}: ${h.join(', ')}`)
}

// 5. 关键配置文件状态
console.log('\n📄 配置:')
for (const f of ['pages.json', 'manifest.json', 'App.vue', 'main.js', 'uni.scss', 'utils/api.js']) {
  const p = path.join(process.cwd(), 'src', f)
  if (fs.existsSync(p)) {
    const stat = fs.statSync(p)
    console.log(`  ✅ src/${f} (${stat.size} bytes)`)
  } else {
    console.log(`  ❌ src/${f} 缺失`)
  }
}

// 6. manifest appid
const mfst = JSON.parse(fs.readFileSync(path.join(process.cwd(), 'src/manifest.json'), 'utf8'))
const appid = mfst['mp-weixin']?.appid || ''
console.log()
if (appid.includes('PLACEHOLDER')) {
  console.log(`⚠️  manifest.appid = PLACEHOLDER — 波哥需从微信公众平台获取后替换`)
} else {
  console.log(`🔧 manifest.appid = ${appid}`)
}

console.log('\n═══ 完毕 ═══\n')
