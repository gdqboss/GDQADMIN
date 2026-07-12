#!/usr/bin/env node
/**
 * deploy.mjs — 彩美特模块化部署工具
 *
 * 流程:
 *   1. 读本地 server_profiles 列出所有目标服务器
 *   2. 选一台目标
 *   3. SSH 测连通 + 查目标 server_modules
 *   4. 对比本地 vs 目标的模块差异
 *   5. 确认后 PUT /:id/modules 覆盖目标模块列表
 *
 * 用法:
 *   node scripts/deploy.mjs                    # 交互式选目标
 *   node scripts/deploy.mjs --profile 2        # 选 profile 2
 *   node scripts/deploy.mjs --diff-only        # 只看差异不同步
 *
 * ⚠️ 本地 server_modules 是基准，目标 server_modules 被覆盖为本地值
 *    如需 "增量补齐而非覆盖"，请用 sync-modules.mjs
 */
import { pool } from '../db/connection.js'
import { spawn } from 'child_process'

// ============================================================
// CLI args
// ============================================================
const args = process.argv.slice(2)
const PROFILE_ID_FLAG = args.includes('--profile') ? parseInt(args[args.indexOf('--profile') + 1]) : null
const DIFF_ONLY = args.includes('--diff-only')

// ============================================================
// 本地模块列表（从本地 DB 读 — 当前服务器实际启用的）
// ============================================================
async function getLocalModules() {
  // 当前 server 是新加坡 profile_id=1（按需求取自己）
  const [rows] = await pool.query(
    `SELECT module_key FROM server_modules WHERE server_profile_id = 1 ORDER BY module_key`
  )
  return rows.map(r => r.module_key)
}

// ============================================================
// 列所有目标 profile（排除自己）
// ============================================================
async function listProfiles() {
  const [rows] = await pool.query(
    `SELECT id, name, ip, ssh_port, ssh_user, ssh_key_path, ssh_auth_type, ssh_password, env
     FROM server_profiles WHERE id != 1 ORDER BY id`
  )
  return rows
}

// ============================================================
// 本地目标状态（目标服务器管理界面在新加坡 server_modules WHERE server_profile_id=目标.id 的行）
// 也就是"目标服务器选了哪些模块" - 波哥铁律：选了啥就同步啥，不强加
// ============================================================
async function getLocalTargetModules(profileId) {
  const [rows] = await pool.query(
    `SELECT module_key FROM server_modules WHERE server_profile_id = ? ORDER BY module_key`,
    [profileId]
  )
  return rows.map(r => r.module_key)
}

// ============================================================
// 目标模块列表 — 远程 curl + 本地解析（避免嵌套引号解析问题）
// ============================================================
async function getRemoteModules(profile) {
  // 目标服务器后端 :3000 暴露 GET /api/public-settings，返回 data.modules 数组
  // 把原始 JSON 拉回本地再解析（避免 ssh + bash 嵌套引号问题）
  // 上海/部分服务器跑 3200 端口，按 profile.http_port 优先（默认 3000）
  const port = profile.http_port || 3000
  const sshCmd = buildSshCmd(profile, `curl -s http://localhost:${port}/api/public-settings`)
  return new Promise((resolve, reject) => {
    const p = spawn('bash', ['-c', sshCmd])
    let out = '', err = ''
    p.stdout.on('data', d => out += d.toString())
    p.stderr.on('data', d => err += d.toString())
    p.on('close', code => {
      if (code !== 0) return reject(new Error(`SSH exit ${code}: ${err.slice(0,200)}`))
      try {
        const data = JSON.parse(out)
        const modules = (data.data?.modules || []).slice().sort()
        resolve(modules)
      } catch (e) {
        reject(new Error(`JSON parse failed: ${e.message}\n原始输出: ${out.slice(0,200)}`))
      }
    })
  })
}

// ============================================================
// SSH 命令构造
// ============================================================
function buildSshCmd(profile, remoteCmd) {
  const port = profile.ssh_port || 22
  const user = profile.ssh_user
  const host = profile.ip
  let auth = ''
  // 优先用密码（如果有），否则 key
  if (profile.ssh_password) {
    auth = `sshpass -p '${profile.ssh_password}' ssh -o StrictHostKeyChecking=no -p ${port}`
  } else {
    const key = profile.ssh_key_path || '/root/clawgdqshop.pem'
    auth = `ssh -i ${key} -o StrictHostKeyChecking=no -p ${port}`
  }
  return `${auth} ${user}@${host} "${remoteCmd.replace(/"/g, '\\"')}"`
}

// ============================================================
// SSH 连通性测试
// ============================================================
async function testSsh(profile) {
  return new Promise((resolve) => {
    const cmd = buildSshCmd(profile, `echo ok`)
    const p = spawn('bash', ['-c', cmd])
    let out = ''
    p.stdout.on('data', d => out += d.toString())
    p.on('close', code => resolve({ ok: code === 0 && out.trim() === 'ok', output: out.trim() }))
    setTimeout(() => { p.kill('SIGTERM'); resolve({ ok: false, output: 'timeout' }) }, 15000)
  })
}

// ============================================================
// 差异对比
// ============================================================
function diffModules(local, remote) {
  const localSet = new Set(local)
  const remoteSet = new Set(remote)
  const onlyLocal = [...localSet].filter(x => !remoteSet.has(x)).sort()
  const onlyRemote = [...remoteSet].filter(x => !localSet.has(x)).sort()
  const common = [...localSet].filter(x => remoteSet.has(x)).sort()
  return { onlyLocal, onlyRemote, common, same: onlyLocal.length === 0 && onlyRemote.length === 0 }
}

// ============================================================
// 选择目标（交互式）
// ============================================================
async function selectProfile(profiles) {
  if (PROFILE_ID_FLAG) {
    const p = profiles.find(p => p.id === PROFILE_ID_FLAG)
    if (!p) throw new Error(`Profile ${PROFILE_ID_FLAG} 不存在`)
    return p
  }
  console.log('\n可用目标服务器：')
  profiles.forEach((p, i) => {
    console.log(`  [${p.id}] ${p.name}  (${p.ip}:${p.ssh_port}, ${p.env})`)
  })
  console.log('\n用法: --profile <id> 跳过交互')
  process.exit(0)
}

// ============================================================
// 主流程
// ============================================================
async function main() {
  console.log('🚀 彩美特模块化部署工具\n')

  const profiles = await listProfiles()
  if (profiles.length === 0) {
    console.log('⚠️  无目标服务器（只有本地 profile_id=1）')
    await pool.end()
    return
  }

  const profile = await selectProfile(profiles)
  console.log(`\n→ 目标: ${profile.name} (${profile.ip}:${profile.ssh_port})`)
  console.log(`  认证: ${profile.ssh_auth_type}${profile.ssh_auth_type === 'password' ? ' (有密码)' : ` (key: ${profile.ssh_key_path})`}`)

  // SSH 测试
  const t = await testSsh(profile)
  if (!t.ok) {
    console.error(`❌ SSH 连不上: ${t.output.slice(0, 100)}`)
    await pool.end()
    process.exit(1)
  }
  console.log(`✅ SSH 通`)

  // 拉模块对比
  // "本地目标状态" = 新加坡 server_modules WHERE server_profile_id=目标.id
  //   也就是"目标服务器管理"界面勾选了什么（波哥铁律：选了啥就同步啥，不强加）
  // "远程实际" = 目标服务器自己的 server_modules 表
  const local = await getLocalTargetModules(profile.id)
  let remote
  try {
    remote = await getRemoteModules(profile)
  } catch (e) {
    console.error(`❌ 读远程模块失败: ${e.message}`)
    console.log('   提示: 目标服务器可能没装 mysql client，或密码不通')
    await pool.end()
    process.exit(1)
  }

  const d = diffModules(local, remote)
  console.log(`\n模块对比（按"目标服务器管理"勾选状态）:`)
  console.log(`  目标状态 [profile_id=${profile.id}] (${local.length}): ${local.join(', ') || '(空)'}`)
  console.log(`  远程实际 (${remote.length}): ${remote.join(', ') || '(空)'}`)
  console.log(`  共有: ${d.common.length}`)
  console.log(`  目标有/远程无 (待新增): ${d.onlyLocal.length} 个`)
  console.log(`  远程有/目标无 (待删除): ${d.onlyRemote.length} 个`)

  if (d.same) {
    console.log('\n✅ 双方模块一致，无需同步')
    await pool.end()
    return
  }

  if (d.onlyLocal.length) {
    console.log(`  + 新增: ${d.onlyLocal.join(', ')}`)
  }
  if (d.onlyRemote.length) {
    console.log(`  - 删除: ${d.onlyRemote.join(', ')}`)
  }

  if (DIFF_ONLY) {
    console.log('\n(--diff-only 模式，不执行同步)')
    await pool.end()
    return
  }

  // 同步模式: 写本地 server_profiles 让前端 PUT 调用直接覆盖
  console.log('\n⚠️  本脚本只生成同步报告，实际同步请：')
  console.log('   1) 在前端「目标服务器管理」页面选模块')
  console.log('   2) 调 PUT /api/server-profiles/:id/modules 接口')
  console.log('   3) 或直接 SQL UPDATE server_modules')
  console.log('\nSQL 预览 (在目标服务器执行):')
  console.log(`  DELETE FROM server_modules;`)
  if (local.length) {
    const vals = local.map(m => `('${m}')`).join(',')
    console.log(`  INSERT INTO server_modules (module_key) VALUES ${vals};`)
  }

  await pool.end()
}

main().catch(e => {
  console.error('💥 异常:', e.message)
  pool.end().catch(()=>{})
  process.exit(1)
})
