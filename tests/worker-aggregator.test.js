/**
 * Layer 2 自动化采集 — 单元测试
 *
 * 测试策略：
 * - 真实数据库连接，幂等运行
 * - 创建测试数据 → 跑聚合 → 验证 worker_profiles 更新
 * - 清理测试数据
 *
 * 运行：node tests/worker-aggregator.test.js
 */

import { pool } from '../db/connection.js'
import {
  aggregateAttendance,
  aggregateTasks,
  aggregateWorkLogs,
  aggregateGPS,
  aggregateFinance,
  runAllAggregators
} from '../services/worker-profiles-aggregator.js'

const TEST_USER_ID = 999900
let createdJobsiteId = null

async function setup() {
  // 准备测试用户
  await pool.query(
    `INSERT IGNORE INTO users (id, name, phone, role) VALUES (?, ?, ?, ?)`,
    [TEST_USER_ID, '测试工人聚合', '13800000001', 'worker']
  )
  await pool.query(
    `INSERT IGNORE INTO worker_profiles (user_id, payment_type, hourly_rate) VALUES (?, ?, ?)`,
    [TEST_USER_ID, 'piece', 30]
  )

  // 创建一个测试工地（带 GPS）
  const [r] = await pool.query(
    `INSERT INTO jobsites (code, name, gps_lat, gps_lng, gps_radius_m, status) VALUES (?, ?, ?, ?, ?, ?)`,
    ['TEST-AGG-001', '聚合测试工地', 22.1234567, 113.5432100, 500, 'active']
  )
  createdJobsiteId = r.insertId

  // 准备 attendance（带 GPS 在工地附近）
  const today = new Date().toISOString().slice(0, 10)
  await pool.query(
    `INSERT INTO attendance (user_id, date, clock_in, clock_out, status, gps_lat, gps_lng) VALUES (?, ?, '08:00:00', '18:00:00', 'normal', 22.1234567, 113.5432100)`,
    [TEST_USER_ID, today]
  )

  // 准备 tasks (3 done, 1 pending)
  for (let i = 0; i < 4; i++) {
    await pool.query(
      `INSERT INTO tasks (title, assigned_to, status, created_by) VALUES (?, ?, ?, 1)`,
      [`测试任务 ${Date.now()}-${i}`, TEST_USER_ID, i < 3 ? 'completed' : 'pending']
    )
  }

  // 准备 work_logs
  for (let i = 0; i < 5; i++) {
    await pool.query(
      `INSERT INTO work_logs (user_id, today_work, created_at) VALUES (?, '测试日志', NOW())`,
      [TEST_USER_ID]
    )
  }
}

async function cleanup() {
  await pool.query(`DELETE FROM work_logs WHERE user_id = ?`, [TEST_USER_ID])
  await pool.query(`DELETE FROM tasks WHERE assigned_to = ?`, [TEST_USER_ID])
  await pool.query(`DELETE FROM attendance WHERE user_id = ?`, [TEST_USER_ID])
  if (createdJobsiteId) await pool.query(`DELETE FROM jobsites WHERE id = ?`, [createdJobsiteId])
  await pool.query(`DELETE FROM worker_profiles WHERE user_id = ?`, [TEST_USER_ID])
  await pool.query(`DELETE FROM users WHERE id = ?`, [TEST_USER_ID])
}

let passed = 0
let failed = 0
function assert(cond, msg) {
  if (cond) {
    console.log(`  ✅ ${msg}`)
    passed++
  } else {
    console.error(`  ❌ ${msg}`)
    failed++
  }
}

async function run() {
  console.log('=== Layer 2 自动化采集 单元测试 ===\n')
  try {
    await setup()
    console.log('✅ 测试数据准备完毕\n')

    console.log('[2.1] attendance 测试')
    const r1 = await aggregateAttendance()
    assert(r1.updated >= 1, `attendance 更新数=${r1.updated}`)
    const [wp1] = await pool.query(`SELECT total_work_hours, efficiency_score FROM worker_profiles WHERE user_id = ?`, [TEST_USER_ID])
    assert(Number(wp1[0]?.total_work_hours) > 0, `total_work_hours=${wp1[0]?.total_work_hours}`)
    assert(Number(wp1[0]?.efficiency_score) > 0, `efficiency_score=${wp1[0]?.efficiency_score}`)
    console.log()

    console.log('[2.2] tasks 测试')
    const r2 = await aggregateTasks()
    assert(r2.updated >= 1, `tasks 更新数=${r2.updated}`)
    const [wp2] = await pool.query(`SELECT skill_level FROM worker_profiles WHERE user_id = ?`, [TEST_USER_ID])
    // 3/4 = 75% 完工率 → junior (>=50% 升 junior)
    assert(wp2[0]?.skill_level === 'junior', `skill_level=${wp2[0]?.skill_level} (3/4=75% 应该是 junior)`)
    console.log()

    console.log('[2.3] work_logs 测试')
    const r3 = await aggregateWorkLogs()
    assert(r3.updated >= 1, `workLogs 更新数=${r3.updated}`)
    const [wp3] = await pool.query(`SELECT impact_score FROM worker_profiles WHERE user_id = ?`, [TEST_USER_ID])
    assert(Number(wp3[0]?.impact_score) === 25, `impact_score=${wp3[0]?.impact_score} (5 logs × 5 = 25)`)
    console.log()

    console.log('[2.5] GPS 测试')
    const r5 = await aggregateGPS()
    assert(r5.updated >= 1, `GPS 匹配数=${r5.updated}`)
    const [wp5] = await pool.query(`SELECT current_jobsite_id FROM worker_profiles WHERE user_id = ?`, [TEST_USER_ID])
    assert(wp5[0]?.current_jobsite_id !== null, `current_jobsite_id=${wp5[0]?.current_jobsite_id} 期望非空(GPS 在工地附近,应匹配到)`)
    console.log()

    console.log('[2.4] finance 测试')
    const r4 = await aggregateFinance()
    assert(r4.updated >= 1, `finance 更新数=${r4.updated}`)
    const [wp4] = await pool.query(`SELECT total_pieces, monthly_salary FROM worker_profiles WHERE user_id = ?`, [TEST_USER_ID])
    assert(wp4[0]?.total_pieces === 3, `total_pieces=${wp4[0]?.total_pieces} (3 completed tasks)`)
    console.log()

    console.log('[全部聚合] runAllAggregators 测试')
    const all = await runAllAggregators()
    assert(all.attendance && all.tasks && all.workLogs && all.gps && all.finance, '5 个任务全部跑通')
    console.log()

  } catch (e) {
    console.error('❌ 测试异常:', e)
    failed++
  } finally {
    await cleanup()
    console.log(`\n=== 测试结果: ${passed} 通过, ${failed} 失败 ===`)
    await pool.end()
    process.exit(failed > 0 ? 1 : 0)
  }
}

run()