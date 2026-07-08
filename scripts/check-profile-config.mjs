#!/usr/bin/env node
// 彩美特模块化 — profile-config.js 校验脚本
//
// 用法:
//   node scripts/check-profile-config.mjs                    # 全量校验所有 profile
//   node scripts/check-profile-config.mjs <module_key>       # 检查单模块在 3 profile 的存在性
//   node scripts/check-profile-config.mjs --diff <p1> <p2>   # 对比 2 个 profile 差异
//
// 退出码:
//   0 = 通过
//   1 = 校验失败
//
// 检查项:
//   1. PROFILE_MODULES 每个 id 是 1/2/3（profile id 必须是已注册）
//   2. PROFILE_MODULES 每个 module_key 必须在 MODULE_FILE_MAP 里（无悬空）
//   3. MODULE_FILE_MAP / MODULE_ROUTE_MAP 的 key 必须一致（同一模块在 3 张表里都用同名）
//   4. views/ 实际文件 vs MODULE_FILE_MAP 声明的双向一致性（防漏加/漏删）
//   5. 单模块模式：<module_key> 在 PROFILE_MODULES[1/2/3] 的差异（生成报告）

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const SRC_DIR = path.resolve(__dirname, '..')
const PROFILE_CONFIG = path.join(SRC_DIR, 'modules/profile-config.js')
const VIEWS_DIR = path.join(SRC_DIR, 'views')

const args = process.argv.slice(2)
const diffMode = args[0] === '--diff' && args[1] && args[2]

// 用 module-filter 同样的 load 模式（eval ESM as CJS）
function loadProfileConfig() {
  const src = fs.readFileSync(PROFILE_CONFIG, 'utf8')
  const cjsSrc = src.replace(/export\s+const\s+([A-Za-z0-9_]+)\s*=/g, 'module.exports.$1 =')
  const fakeModule = { exports: {} }
  const fn = new Function('module', 'exports', 'path', cjsSrc)
  fn(fakeModule, fakeModule.exports, path)
  return fakeModule.exports
}

function red(s) { return `\x1b[0;31m${s}\x1b[0m` }
function green(s) { return `\x1b[0;32m${s}\x1b[0m` }
function yellow(s) { return `\x1b[1;33m${s}\x1b[0m` }
function blue(s) { return `\x1b[0;34m${s}\x1b[0m` }
function bold(s) { return `\x1b[1m${s}\x1b[0m` }

const cfg = loadProfileConfig()
const { PROFILE_MODULES, MODULE_FILE_MAP, MODULE_ROUTE_MAP } = cfg
const PROFILE_NAMES = { 1: '新加坡 (sgp)', 2: '北京 (bj)', 3: '3号仓库 (3hk)' }

let errors = 0
let warnings = 0

function fail(msg) { console.error(red('❌ ') + msg); errors++ }
function warn(msg) { console.error(yellow('⚠️  ') + msg); warnings++ }
function ok(msg) { console.log(green('✅ ') + msg) }

// ---- diff 模式 ----
if (diffMode) {
  const id1 = parseInt(args[1]), id2 = parseInt(args[2])
  const set1 = new Set(PROFILE_MODULES[id1] || [])
  const set2 = new Set(PROFILE_MODULES[id2] || [])
  if (!set1.size || !set2.size) {
    console.error(`profile ${id1} 或 ${id2} 不存在`); process.exit(1)
  }
  console.log(`\n${bold(PROFILE_NAMES[id1])} (${set1.size} 模块)  vs  ${bold(PROFILE_NAMES[id2])} (${set2.size} 模块)\n`)
  const onlyIn1 = [...set1].filter(k => !set2.has(k)).sort()
  const onlyIn2 = [...set2].filter(k => !set1.has(k)).sort()
  const common = [...set1].filter(k => set2.has(k)).sort()
  if (onlyIn1.length) {
    console.log(red(`仅 ${PROFILE_NAMES[id1]} 有 (${onlyIn1.length}):`))
    onlyIn1.forEach(k => console.log(`  - ${k}`))
    console.log()
  }
  if (onlyIn2.length) {
    console.log(yellow(`仅 ${PROFILE_NAMES[id2]} 有 (${onlyIn2.length}):`))
    onlyIn2.forEach(k => console.log(`  - ${k}`))
    console.log()
  }
  console.log(green(`共有 (${common.length}): ${common.join(', ')}`))
  process.exit(0)
}

// ---- 单模块模式 ----
if (args.length && args[0] !== '--diff') {
  const moduleKey = args[0]
  console.log(`\n${bold(`模块检查: ${moduleKey}`)}\n`)
  console.log('FILE_MAP 声明:', MODULE_FILE_MAP[moduleKey] ? green(JSON.stringify(MODULE_FILE_MAP[moduleKey])) : red('未声明'))
  console.log('ROUTE_MAP 声明:', MODULE_ROUTE_MAP[moduleKey] ? green(`${MODULE_ROUTE_MAP[moduleKey].length} 个路由`) : red('未声明'))
  console.log()
  console.log('在 3 个 profile 的存在性:')
  for (const id of [1, 2, 3]) {
    const has = (PROFILE_MODULES[id] || []).includes(moduleKey)
    console.log(`  ${PROFILE_NAMES[id]}: ${has ? green('✓ 启用') : red('✗ 未启用')}`)
  }
  if (!MODULE_FILE_MAP[moduleKey]) fail(`模块 ${moduleKey} 在 MODULE_FILE_MAP 中未声明`)
  if (!MODULE_ROUTE_MAP[moduleKey]) fail(`模块 ${moduleKey} 在 MODULE_ROUTE_MAP 中未声明`)
  process.exit(errors > 0 ? 1 : 0)
}

// ---- 全量校验 ----
console.log(blue('\n========== profile-config.js 全量校验 ==========\n'))

ok(`PROFILE_MODULES: ${Object.keys(PROFILE_MODULES).length} 个 profile`)
ok(`MODULE_FILE_MAP: ${Object.keys(MODULE_FILE_MAP).length} 个模块`)
ok(`MODULE_ROUTE_MAP: ${Object.keys(MODULE_ROUTE_MAP).length} 个模块`)

// 1. profile id 必须 1/2/3
console.log()
const validIds = new Set([1, 2, 3])
for (const id of Object.keys(PROFILE_MODULES)) {
  if (!validIds.has(parseInt(id))) fail(`PROFILE_MODULES 有未注册的 id: ${id}（只允许 1/2/3）`)
}

// 2. PROFILE_MODULES module_key 必须在 MODULE_FILE_MAP / MODULE_ROUTE_MAP（双向）
const fileMapKeys = new Set(Object.keys(MODULE_FILE_MAP))
const routeMapKeys = new Set(Object.keys(MODULE_ROUTE_MAP))

// FILE_MAP / ROUTE_MAP key 一致性
for (const k of fileMapKeys) {
  if (!routeMapKeys.has(k)) fail(`模块 "${k}" 在 MODULE_FILE_MAP 声明但 MODULE_ROUTE_MAP 缺失`)
}
for (const k of routeMapKeys) {
  if (!fileMapKeys.has(k)) fail(`模块 "${k}" 在 MODULE_ROUTE_MAP 声明但 MODULE_FILE_MAP 缺失`)
}

// PROFILE_MODULES 引用了未声明的 key
for (const id of Object.keys(PROFILE_MODULES)) {
  for (const k of PROFILE_MODULES[id]) {
    if (!fileMapKeys.has(k)) fail(`profile ${id} 引用了 MODULE_FILE_MAP 未声明的模块: "${k}"`)
  }
}

// FILE_MAP 声明了但 3 profile 都没用（孤儿）
for (const k of fileMapKeys) {
  const used = [1, 2, 3].some(id => (PROFILE_MODULES[id] || []).includes(k))
  if (!used) warn(`模块 "${k}" 在 MODULE_FILE_MAP 声明但 3 个 profile 都没启用（孤儿模块）`)
}

// 3. MODULE_FILE_MAP 声明的 views 路径必须真存在 + 反向检查（views 实际文件没被任何模块认领 = 永远进不了 dist）
console.log()
const allViewsFiles = new Set()
function scanViews(dir, prefix = '') {
  for (const f of fs.readdirSync(dir, { withFileTypes: true })) {
    const rel = (prefix ? prefix + '/' : '') + f.name
    if (f.isDirectory()) scanViews(path.join(dir, f.name), rel)
    else if (f.name.endsWith('.vue')) allViewsFiles.add(rel)
  }
}
scanViews(VIEWS_DIR)

// 把 FILE_MAP 声明的路径展开成文件集合
const claimedByModule = {} // {moduleKey: [file1, file2, ...]}
for (const [moduleKey, paths] of Object.entries(MODULE_FILE_MAP)) {
  for (const p of paths) {
    if (p.endsWith('/')) {
      // 目录
      for (const file of allViewsFiles) {
        if (file.startsWith(p)) {
          (claimedByModule[moduleKey] = claimedByModule[moduleKey] || []).push(file)
        }
      }
    } else {
      // 文件
      if (allViewsFiles.has(p)) {
        (claimedByModule[moduleKey] = claimedByModule[moduleKey] || []).push(p)
      } else {
        fail(`MODULE_FILE_MAP["${moduleKey}"] 声明了 "${p}" 但 views/${p} 不存在`)
      }
    }
  }
}

// 反向：views 实际文件（不在 components/）有没有未被任何模块认领
// 这些文件如果永远不被认领，profile build（启用模块外的）会拿不到它们
// 但全量 build 不受影响，所以这个是 warn 不是 fail
let orphanViews = []
for (const file of allViewsFiles) {
  // 跳过 router/store/components（这些不进 view chunk）
  if (file.startsWith('router/') || file.startsWith('store/') || file.includes('/components/')) continue
  const claimed = Object.values(claimedByModule).some(arr => arr && arr.includes(file))
  if (!claimed) orphanViews.push(file)
}
if (orphanViews.length) {
  warn(`${orphanViews.length} 个 views 文件没被任何模块认领（profile build 可能漏编译）: ${orphanViews.slice(0,5).join(', ')}${orphanViews.length > 5 ? ` ...还有 ${orphanViews.length - 5} 个` : ''}`)
}

// 4. 3 profile 模块数量对比（应该 1 ≥ 2 ≥ 3，因为 1 是 sgp 全量）
console.log()
const profileStats = [1, 2, 3].map(id => ({
  id, name: PROFILE_NAMES[id], count: (PROFILE_MODULES[id] || []).length
}))
for (const p of profileStats) {
  console.log(`  profile ${p.id} (${p.name}): ${p.count} 模块`)
}
const [, p2, p3] = profileStats
if (p2.count > profileStats[0].count) {
  warn(`profile 2 (bj) 模块数 ${p2.count} 比 profile 1 (sgp) ${profileStats[0].count} 还要多？异常`)
}
if (p3.count > profileStats[0].count) {
  warn(`profile 3 (3hk) 模块数 ${p3.count} 比 profile 1 (sgp) ${profileStats[0].count} 还要多？异常`)
}

// ---- 总结 ----
console.log()
console.log(blue('============================================='))
if (errors === 0 && warnings === 0) {
  ok('🎉 全部通过 — profile-config 健康')
} else {
  if (errors > 0) console.error(red(`\n${errors} 个错误，${warnings} 个警告`))
  else console.error(yellow(`\n0 个错误，${warnings} 个警告`))
}
console.log(blue('============================================='))
process.exit(errors > 0 ? 1 : 0)
