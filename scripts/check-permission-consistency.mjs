// check-permission-consistency.mjs — RBAC 一致性 4 项检查 (波哥 2026-08-27 P2)
// 1) 字典 value 是否都在 DB
// 2) DB perm 是否都在字典 (warn, 不阻塞)
// 3) 后端 P.XXX 引用是否都能解析 (硬阻塞)
// 4) 前端 canAccess 引用是否都在 DB (硬阻塞)

import { PERMISSIONS } from '../middleware/rbac.js'
import { execSync } from 'child_process'
import { readFileSync, readdirSync, statSync } from 'fs'
import { join } from 'path'

const DB = {
  host: 'localhost',
  user: 'gdq',
  password: 'Re78g0A1XcNmr1T8',
  database: 'gdq',
  socketPath: '/run/mysqld/mysqld.sock',
  connectionLimit: 1
}

let errors = 0
function fail(name, list, info='') {
  errors++
  console.log(`  ✗ ${name} (${list.length}${info?' — '+info:''})`)
  for (const x of list.slice(0, 20)) console.log(`      ${x}`)
  if (list.length > 20) console.log(`      ... +${list.length-20} more`)
}
function ok(name, detail='') {
  console.log(`  ✅ ${name}${detail?' ('+detail+')':''}`)
}
function warn(name, detail='') {
  console.log(`  ⚠ ${name}${detail?' ('+detail+')':''}`)
}

// ===== 拉数据 =====
const mysql = await import('mysql2/promise')
const pool = mysql.default.createPool(DB)
const [rows] = await pool.query('SELECT name FROM rbac_permissions')
const dbPerms = new Set(rows.map(r => r.name).sort())
await pool.end()

const dictVals = Object.values(PERMISSIONS).sort()
const dictSet = new Set(dictVals)

console.log('')
console.log('【1】字典 → DB 缺失 (字典里有但 DB 里没的行, 硬阻塞)')
const dictNotInDb = dictVals.filter(v => !dbPerms.has(v))
if (dictNotInDb.length) fail('字典有 DB 没有', dictNotInDb, '角色配置勾不上')
else ok('字典所有 value 都在 DB', `${dictVals.length} 个`)

console.log('')
console.log('【2】DB → 字典缺失 (DB 有但字典没定义, warn)')
const dbNotInDict = [...dbPerms].filter(p => !dictSet.has(p))
if (dbNotInDict.length) warn('DB 有但字典没定义', `${dbNotInDict.length} 个 — 应纳入字典`)
else ok('DB 全部权限都在字典', `${dbPerms.size} 个`)

console.log('')
console.log('【3】后端 P.XXX 引用解析 (硬阻塞)')
// 扫 routes/ middleware/ 所有 .js
function walk(dir, out=[]) {
  for (const f of readdirSync(dir)) {
    const p = join(dir, f)
    const st = statSync(p)
    if (st.isDirectory()) { if (f !== 'node_modules' && !f.startsWith('.')) walk(p, out) }
    else if (p.endsWith('.js')) out.push(p)
  }
  return out
}
function walkVue(dir, out=[]) {
  for (const f of readdirSync(dir)) {
    const p = join(dir, f)
    const st = statSync(p)
    if (st.isDirectory()) { if (f !== 'node_modules' && !f.startsWith('.')) walkVue(p, out) }
    else if (p.endsWith('.vue')) out.push(p)
  }
  return out
}
const allFiles = [...walk('routes'), ...walk('middleware')]
const symSet = new Set()
for (const f of allFiles) {
  const s = readFileSync(f, 'utf8')
  for (const m of s.matchAll(/\bP\.([A-Z_]+)\b/g)) symSet.add(m[1])
}
const definedKeys = new Set(Object.keys(PERMISSIONS))
const undefSyms = [...symSet].filter(s => !definedKeys.has(s))
if (undefSyms.length) {
  // 同时验证字面 requirePermission('xxx:read') 也能解析
  const undefFiles = []
  for (const f of allFiles) {
    const s = readFileSync(f, 'utf8')
    for (const m of s.matchAll(/requirePermission\s*\(\s*(?:PERMISSIONS\.)?([A-Z_]+|'[^']+')\s*\)/g)) {
      const v = m[1]
      if (definedKeys.has(v)) continue  // 字面字符串, 不是 sym, skip
      if (v.startsWith("'")) continue   // 字面 'xxx:read' — 不强校验（防误伤）
      if (undefSyms.includes(v)) undefFiles.push(`${f} uses P.${v}`)
    }
  }
  fail('未定义的 P.XXX 符号', undefSyms.map(s=>`P.${s}`), `${undefFiles.length} 处使用`)
} else ok('所有 P.XXX 后端引用都解析', `${symSet.size} 个符号`)

console.log('')
console.log('【4】前端 canAccess 引用 DB 缺失 (硬阻塞)')
const frontRoots = ['/root/src', '/root/server/views']
const frontPerms = new Set()
for (const root of frontRoots) {
  try {
    // walk 只收 .js, 这里再扩 .vue
    const files = [...walk(root), ...walkVue(root)].filter(f => /\.(vue|js)$/.test(f))
    for (const f of files) {
      const s = readFileSync(f, 'utf8')
      for (const m of s.matchAll(/canAccess\s*\(\s*['"]([a-z_][a-z0-9_:\-]*)['"]\s*\)/g)) {
        frontPerms.add(m[1])
      }
    }
  } catch (e) { /* 目录可能不存在 */ }
}
const frontNotInDb = [...frontPerms].filter(p => !dbPerms.has(p))
if (frontNotInDb.length) fail('前端 canAccess 用到但 DB 没有', frontNotInDb, '前端永远 canAccess=false')
else ok('前端 canAccess 全部权限在 DB', `${frontPerms.size} 个`)

console.log('')
console.log(`错误数: ${errors}`)
process.exit(errors === 0 ? 0 : 1)
