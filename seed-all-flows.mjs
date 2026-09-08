#!/usr/bin/env node
/**
 * OA 全量流程入库脚本（2026-09-08 · AutoClaw）
 * 输入: /home/ubuntu/oa-all-flows.json（从 oa-flows.js 只读导出，61 流程）
 * 规则:
 *   - 已存在的 code 跳过（leave/reimburse/seal/vehicle 4 条 P0 已入库）
 *   - 有预置图模型（6条）→ 原样入库 + resubmitPolicy:restart
 *   - 无预置 → 按业务语义生成默认链（3 档：单级/两级/金额网关），全部 resubmitPolicy:restart
 * 分类: category 沿用前端 13 大类 id
 * 幂等: 可重复执行（跳过已存在）
 */
import fs from 'fs'
import mysql from 'mysql2/promise'
import 'dotenv/config'

const SRC = JSON.parse(fs.readFileSync('/home/ubuntu/oa-all-flows.json', 'utf8'))

// ── 默认链生成（语义分档）──
// 单级审批: 直属上级
function chain1(name = '直属上级', role = 'direct_supervisor') {
  return {
    nodes: [
      { id: 'n1', type: 'start', name: '申请人', role: 'applicant' },
      { id: 'n2', type: 'approve', name, role, timeout: 24, rejectTo: 'n1' },
      { id: 'n3', type: 'end', name: '归档' }
    ],
    edges: [ { from: 'n1', to: 'n2' }, { from: 'n2', to: 'n3' } ]
  }
}
// 两级: 直属上级 → 部门负责人
function chain2(a, aRole, b, bRole) {
  return {
    nodes: [
      { id: 'n1', type: 'start', name: '申请人', role: 'applicant' },
      { id: 'n2', type: 'approve', name: a, role: aRole, timeout: 24, rejectTo: 'n1' },
      { id: 'n3', type: 'approve', name: b, role: bRole, timeout: 48, rejectTo: 'n1' },
      { id: 'n4', type: 'end', name: '归档' }
    ],
    edges: [ { from: 'n1', to: 'n2' }, { from: 'n2', to: 'n3' }, { from: 'n3', to: 'n4' } ]
  }
}
// 金额网关: amount ≤X 直属上级 / >X 部门负责人 → 财务
function chainAmount(threshold) {
  return {
    nodes: [
      { id: 'n1', type: 'start', name: '申请人', role: 'applicant' },
      { id: 'n2', type: 'gate', name: '金额网关', gateType: 'amount',
        gateConfig: { field: 'amount', branches: [
          { cond: `<=${threshold}`, nextNodeId: 'n3' },
          { cond: `>${threshold}`, nextNodeId: 'n4' } ] } },
      { id: 'n3', type: 'approve', name: '直属上级', role: 'direct_supervisor', timeout: 24, rejectTo: 'n1' },
      { id: 'n4', type: 'approve', name: '部门负责人', role: 'dept_head', timeout: 24, rejectTo: 'n1' },
      { id: 'n5', type: 'approve', name: '财务复核', role: 'finance', timeout: 24, rejectTo: 'n1' },
      { id: 'n6', type: 'end', name: '归档' }
    ],
    edges: [
      { from: 'n1', to: 'n2' },
      { from: 'n2', to: 'n3', branch: `<=${threshold}` },
      { from: 'n2', to: 'n4', branch: `>${threshold}` },
      { from: 'n3', to: 'n6' }, { from: 'n4', to: 'n5' }, { from: 'n5', to: 'n6' }
    ]
  }
}

// 无预置流程的默认链分档（依据前端语义：涉及金额走网关，人事关键节点两级，日常事务单级）
const DEFAULT_CHAINS = {
  travel: () => chainAmount(5000),        // 出差含预算
  onboard: () => chain2('直属上级', 'direct_supervisor', 'HR', 'hr'),
  regular: () => chain2('直属上级', 'direct_supervisor', 'HR', 'hr'),
  resign: () => chain2('直属上级', 'direct_supervisor', 'HR', 'hr'),
  transfer: () => chain2('直属上级', 'direct_supervisor', 'HR', 'hr'),
  meeting: () => chain1('行政确认', 'hr'),
  stationery: () => chain1('行政确认', 'hr'),
  visitor: () => chain1('行政确认', 'hr'),
  payment: () => chainAmount(50000),      // 付款大额
  loan: () => chainAmount(10000),
  purchase: () => chainAmount(10000),
  budget: () => chain2('直属上级', 'direct_supervisor', '财务复核', 'finance'),
  contract: () => chain2('直属上级', 'direct_supervisor', '法务审核', 'hr'),
  'contract-change': () => chain2('直属上级', 'direct_supervisor', '法务审核', 'hr'),
  'contract-terminate': () => chain2('直属上级', 'direct_supervisor', '法务审核', 'hr'),
  'project-init': () => chain2('直属上级', 'direct_supervisor', '总经办', 'gm'),
  'project-change': () => chain1('直属上级', 'direct_supervisor'),
  'project-accept': () => chain2('直属上级', 'direct_supervisor', '总经办', 'gm'),
  'project-close': () => chain1('直属上级', 'direct_supervisor'),
  'it-ticket': () => chain1('IT 处理', 'hr'),
  'account-perm': () => chain1('IT 安全审核', 'hr'),
  device: () => chain1('IT/行政确认', 'hr'),
  vpn: () => chain1('IT 安全审核', 'hr'),
  software: () => chain1('IT 安全审核', 'hr'),
  recruit: () => chain2('直属上级', 'direct_supervisor', 'HR', 'hr'),
  offer: () => chain2('HR', 'hr', '总经办', 'gm'),
  headcount: () => chain2('直属上级', 'direct_supervisor', '总经办', 'gm'),
  invoice: () => chain1('财务审核', 'finance_fallback'),
  certificate: () => chain1('行政确认', 'hr'),
  receipt: () => chain1('行政确认', 'hr'),
  discount: () => chainAmount(5000),
  refund: () => chainAmount(5000),
  credit: () => chain2('直属上级', 'direct_supervisor', '财务复核', 'finance_fallback'),
  release: () => chain2('直属上级', 'direct_supervisor', '总经办', 'gm'),
  sre: () => chain1('技术负责人', 'dept_head_fallback'),
  requirement: () => chain1('直属上级', 'direct_supervisor'),
  incident: () => chain2('直属上级', 'direct_supervisor', '总经办', 'gm'),
  'tech-review': () => chain1('技术负责人', 'dept_head_fallback'),
  quality: () => chain1('直属上级', 'direct_supervisor'),
  safety: () => chain2('直属上级', 'direct_supervisor', '总经办', 'gm'),
  'prod-order': () => chain1('生产负责人', 'dept_head_fallback'),
  supplier: () => chain2('采购负责人', 'dept_head_fallback', '财务复核', 'finance_fallback'),
  'equip-repair': () => chain1('设备管理员', 'hr'),
  publish: () => chain2('直属上级', 'direct_supervisor', '总经办', 'gm'),
  event: () => chain1('直属上级', 'direct_supervisor'),
  material: () => chainAmount(5000),
  media: () => chainAmount(10000),
  copyright: () => chainAmount(5000),
  graduation: () => chain2('服务专员', 'hr', '总经办', 'gm'),
  service: () => chain1('服务专员', 'hr'),
  subsidy: () => chain2('服务专员', 'hr', '总经办', 'gm'),
  space: () => chain1('行政确认', 'hr'),
  activity: () => chain1('直属上级', 'direct_supervisor')
}
// 兼容角色降级：finance_fallback/dept_head_fallback 语义 = 现库无人时任务置空（引擎 §5.9 已支持 warn 不阻断）
// 这里直接用 finance/dept_head，解析不到人会置空待指派，与交接文档一致。
for (const k of Object.keys(DEFAULT_CHAINS)) {
  DEFAULT_CHAINS[k] = (() => { const fn = DEFAULT_CHAINS[k]; return () => {
    const f = fn()
    const s = JSON.stringify(f).replace(/finance_fallback/g, 'finance').replace(/dept_head_fallback/g, 'dept_head')
    return JSON.parse(s)
  } })()
}

const EXISTING_SKIP = new Set(['leave', 'reimburse', 'seal', 'vehicle'])

const conn = await mysql.createConnection({
  host: process.env.DB_HOST || '127.0.0.1',
  port: Number(process.env.DB_PORT || 3306),
  user: process.env.DB_USER,
  password: process.env.DB_PASSWORD,
  database: process.env.DB_NAME,
  charset: 'utf8mb4'
})

let created = 0, skipped = 0, failed = 0
for (const f of SRC) {
  if (EXISTING_SKIP.has(f.code)) { skipped++; continue }
  const [[dup]] = await conn.query('SELECT id FROM workflow_definitions WHERE code = ? AND tenant_id = 1', [f.code])
  if (dup) { skipped++; continue }
  const flowConfig = f.flow
    ? { ...f.flow, resubmitPolicy: 'restart' }
    : { ...(DEFAULT_CHAINS[f.code] ? DEFAULT_CHAINS[f.code]() : chain1()), resubmitPolicy: 'restart' }
  try {
    await conn.query(
      `INSERT INTO workflow_definitions (tenant_id, name, code, category, description, flow_config, form_config, version, is_active, created_by)
       VALUES (1, ?, ?, ?, ?, ?, ?, 1, 1, 9)`,
      [f.name, f.code, f.category,
       `${f.name}（前端 oa-flows.js 对齐 · ${f.flow ? '预置图模型' : '默认链'}）`,
       JSON.stringify(flowConfig),
       JSON.stringify(f.fields || [])])
    created++
    console.log(`+ ${f.code} (${f.name}) [${f.category}] ${f.flow ? 'preset' : 'default-chain'}`)
  } catch (e) {
    failed++
    console.error(`FAIL ${f.code}: ${e.message}`)
  }
}
console.log(`\ncreated=${created} skipped=${skipped} failed=${failed}`)
const [[{ total }]] = await conn.query('SELECT COUNT(*) total FROM workflow_definitions WHERE tenant_id = 1')
console.log('tenant1 definitions total:', total)
await conn.end()
