#!/usr/bin/env node
/**
 * 模块化部署辅助工具 — 对比新加坡(本地)与目标服务器的 server_modules 差异
 *
 * 用法:
 *   node scripts/diff-server-modules.mjs <server_profile_id>
 *
 * 输出:
 *   - 目标服务器有但本地没有的模块（建议删除）
 *   - 本地有但目标没有的模块（建议新增）
 *   - 双方都有的模块
 *
 * ⚠️ 此工具仅生成报告，不直接同步。等波哥确认后再用 server_profiles API 操作。
 */

import { pool } from '../db/connection.js'

const targetId = parseInt(process.argv[2])
if (!targetId) {
  console.error('用法: node scripts/diff-server-modules.mjs <server_profile_id>')
  process.exit(1)
}

try {
  // 1. 目标服务器信息
  const [profiles] = await pool.query(
    'SELECT id, name, ip, domain FROM server_profiles WHERE id = ?',
    [targetId]
  )
  if (profiles.length === 0) {
    console.error(`❌ server_profile_id=${targetId} 不存在`)
    process.exit(1)
  }
  const target = profiles[0]
  console.log(`\n📡 目标服务器: ${target.name} (${target.ip || 'no ip'}${target.domain ? ' / ' + target.domain : ''})`)

  // 2. 拿本地(新加坡, profile_id=1)的模块
  const [localRows] = await pool.query(
    'SELECT module_key FROM server_modules WHERE server_profile_id = 1 ORDER BY module_key'
  )
  const localMods = new Set(localRows.map(r => r.module_key))

  // 3. 拿目标的模块
  const [targetRows] = await pool.query(
    'SELECT module_key FROM server_modules WHERE server_profile_id = ? ORDER BY module_key',
    [targetId]
  )
  const targetMods = new Set(targetRows.map(r => r.module_key))

  console.log(`📦 新加坡模块数: ${localMods.size}`)
  console.log(`📦 目标模块数: ${targetMods.size}\n`)

  // 4. 差异计算
  const onlyInLocal = [...localMods].filter(m => !targetMods.has(m)).sort()
  const onlyInTarget = [...targetMods].filter(m => !localMods.has(m)).sort()
  const inBoth = [...localMods].filter(m => targetMods.has(m)).sort()

  console.log(`✅ 共同模块 (${inBoth.length}):`)
  inBoth.forEach(m => console.log(`   - ${m}`))

  console.log(`\n➕ 本地有但目标缺 (${onlyInLocal.length}) — 建议新增:`)
  onlyInLocal.forEach(m => console.log(`   + ${m}`))

  console.log(`\n➖ 目标有但本地缺 (${onlyInTarget.length}) — 建议删除:`)
  onlyInTarget.forEach(m => console.log(`   - ${m}`))

  console.log(`\n⚠️  本工具只生成报告，不直接同步。`)
  console.log(`    同步操作走 POST/PUT /api/server-profiles/modules API，等波哥确认后手动触发。`)
} catch (e) {
  console.error('❌ 查询失败:', e.message)
  process.exit(1)
} finally {
  await pool.end()
}
