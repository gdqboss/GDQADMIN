// P0 + P1 验证脚本 — 2026-08-27
// 直接导入 middleware,绕过 HTTP,逐项验证
import { applyPermAliases, PERM_ALIASES, PERMISSIONS, ROLES } from '../middleware/rbac.js'
import { getUserPermissions } from '../middleware/rbac.js'

let pass = 0, fail = 0
function assert(name, cond, info='') {
  if (cond) { pass++; console.log('  ✅', name) }
  else { fail++; console.log('  ❌', name, info) }
}

console.log('========== Step 1: PERMISSIONS 字典完整性 ==========')
const dictVals = Object.values(PERMISSIONS)
console.log('  字典常量总数:', dictVals.length)
assert('字典 ≥ 250 个 (补 151 后)', dictVals.length >= 250, `actual: ${dictVals.length}`)

console.log('\n========== Step 2: alias 兼容层 ==========')
const t1 = applyPermAliases(['worklog:read'])
assert('worklog:read → 扩展 work_log:read', t1.includes('work_log:read'), JSON.stringify(t1))
assert('worklog:read → 保留 worklog:read', t1.includes('worklog:read'))

const t2 = applyPermAliases(['worklog:write'])
assert('worklog:write → 扩展 work_log:write', t2.includes('work_log:write'))

const t3 = applyPermAliases(['product:read'])
assert('product:read → 扩展 products:read', t3.includes('products:read'), JSON.stringify(t3))

const t4 = applyPermAliases(['work_log:read'])
assert('work_log:read → 不重复扩展', t4.length === 1)

const t5 = applyPermAliases(['task:read'])
assert('无 alias 的保持原样', t5.length === 1 && t5[0] === 'task:read')

console.log('\n========== Step 3: 关键 P0 死引用都活过来 ==========')
const p0 = applyPermAliases(['worklog:read','worklog:write','product:read','product:write'])
for (const x of ['work_log:read','work_log:write','products:read','products:write']) {
  assert(`历史名字能取到 ${x}`, p0.includes(x))
}

console.log('\n========== Step 4: 字典 value 都是规范 name 形式 ==========')
let bad = 0
for (const v of dictVals) {
  // 规范: lowercase, 字母数字下划线冒号点
  if (!/^[a-z][a-z0-9_:\-\.]*$/.test(v)) { bad++; console.log('  ⚠ 非规范:', v) }
}
assert('全部 270 个字典 value 字符合法', bad === 0, `bad count: ${bad}`)

console.log('\n========== Step 5: P0 物流模块所需 perm 都在字典 ==========')
const logisticsNeeded = ['express_read','express_write','freight_read','freight_write','channel_read','channel_write']
for (const x of logisticsNeeded) {
  assert(`物流 ${x} 有常量`, dictVals.includes(x))
}

console.log('\n========== Step 6: getUserPermissions 集成 ==========')
const perms = await getUserPermissions(1, 'admin')  // 假设 admin user_id=1
assert('admin 拿到权限数 > 200', perms.length > 200, `actual: ${perms.length}`)
assert('admin 含 work_log:read', perms.includes('work_log:read'))
assert('admin 含 worklog:read (alias 源)', perms.includes('worklog:read'))
assert('admin 含 products:read', perms.includes('products:read'))
assert('admin 含 express_write (P0 物流)', perms.includes('express_write'))

console.log(`\n========== 结果 ==========`)
console.log(`通过 ${pass}, 失败 ${fail}`)
process.exit(fail === 0 ? 0 : 1)
