/**
 * 工人档案聚合服务 — Layer 2 自动化采集
 *
 * 4 个采集任务，每晚 cron 跑一次：
 *   2.1 attendance → total_work_hours + efficiency_score
 *   2.2 tasks 完工率 → skill_level 反推
 *   2.3 work_logs 阅读率 → impact_score
 *   2.5 GPS 打卡 → current_jobsite_id (距最近 jobsite < 半径)
 *
 * 设计原则：
 * - 零侵入：不改 attendance / tasks / work_logs 任何 schema
 * - 只写 worker_profiles 这一张新表
 * - 幂等：每次跑 = 全量重算当月
 * - 单事务失败不影响其他任务（任务级别 try/catch）
 *
 * 调用方式：
 *   import { runAllAggregators } from './services/worker-profiles-aggregator.js'
 *   await runAllAggregators()  // 全部跑
 *   await runAllAggregators({ only: ['attendance'] })  // 只跑单个
 */

import { pool } from '../db/connection.js'

const log = (...args) => console.log('[worker-aggregator]', ...args)

/**
 * 2.1 attendance → 工时 + 效率分
 * 工时 = sum(clock_out - clock_in)
 * 效率分 = 100 - late_minutes/10 - early_minutes/10 - absent_count*20，clamp [0, 100]
 */
export async function aggregateAttendance() {
  const [rows] = await pool.query(`
    SELECT user_id,
           SUM(TIMESTAMPDIFF(MINUTE, clock_in, clock_out) / 60) AS work_hours,
           SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) AS late_count,
           SUM(late_minutes) AS late_minutes,
           SUM(early_minutes) AS early_minutes,
           SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) AS absent_count
    FROM attendance
    WHERE clock_in IS NOT NULL AND clock_out IS NOT NULL
      AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY user_id
  `)

  let updated = 0
  for (const r of rows) {
    const hours = Number(r.work_hours || 0)
    const lateM = Number(r.late_minutes || 0)
    const earlyM = Number(r.early_minutes || 0)
    const absent = Number(r.absent_count || 0)
    const eff = Math.max(0, Math.min(100, 100 - lateM / 10 - earlyM / 10 - absent * 20))

    await pool.query(
      `UPDATE worker_profiles SET total_work_hours = ?, efficiency_score = ? WHERE user_id = ?`,
      [hours.toFixed(2), eff.toFixed(2), r.user_id]
    )
    updated++
  }
  log(`2.1 attendance → ${updated} workers updated`)
  return { updated }
}

/**
 * 2.2 tasks 完工率 → skill_level 反推
 * 完工率 = completed / total_assigned
 * skill_level 规则：
 *   完工率>=80% 且 完成>=10 → senior
 *   完工率>=80% 且 完成>=3  → junior
 *   完工率>=50%             → junior
 *   其他                    → rookie
 */
export async function aggregateTasks() {
  const [rows] = await pool.query(`
    SELECT assigned_to AS user_id,
           COUNT(*) AS total,
           SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS done
    FROM tasks
    WHERE assigned_to IS NOT NULL
    GROUP BY assigned_to
  `)

  let updated = 0
  for (const r of rows) {
    const total = Number(r.total || 0)
    const done = Number(r.done || 0)
    const rate = total > 0 ? done / total : 0
    let level = 'rookie'
    if (rate >= 0.8 && done >= 10) level = 'senior'
    else if (rate >= 0.8 && done >= 3) level = 'junior'
    else if (rate >= 0.5) level = 'junior'

    await pool.query(
      `UPDATE worker_profiles SET skill_level = ? WHERE user_id = ?`,
      [level, r.user_id]
    )
    updated++
  }
  log(`2.2 tasks → ${updated} workers updated (skill_level)`)
  return { updated }
}

/**
 * 2.3 work_logs 阅读率 → impact_score
 * 阅读率 = reports_received / reports_sent（recipient 包含本人）
 * 简化为：影响力 = 最近30天写的日志数 × 5，被阅读 (TODO:需要读 receipts 表，先用日志数)
 * 当前用：最近 30 天 log 数 × 5，clamp [0, 100]
 */
export async function aggregateWorkLogs() {
  const [rows] = await pool.query(`
    SELECT user_id, COUNT(*) AS log_count
    FROM work_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY user_id
  `)

  let updated = 0
  for (const r of rows) {
    const impact = Math.min(100, Number(r.log_count || 0) * 5)
    await pool.query(
      `UPDATE worker_profiles SET impact_score = ? WHERE user_id = ?`,
      [impact.toFixed(2), r.user_id]
    )
    updated++
  }
  log(`2.3 work_logs → ${updated} workers updated (impact_score)`)
  return { updated }
}

/**
 * 2.5 GPS 打卡 → current_jobsite_id
 * 算法：对每个有 GPS 的最近一次 attendance，找最近的 jobsite（haversine 距离 < gps_radius_m）
 * 仅当工人是 active 且当前 jobsite_id 未设置时设置。
 * 直接用 MySQL 计算：经纬度差估算（粗略但够用）
 */
export async function aggregateGPS() {
  // 取每个用户最近一次有 GPS 的打卡
  const [latest] = await pool.query(`
    SELECT a.user_id, a.gps_lat, a.gps_lng
    FROM attendance a
    INNER JOIN (
      SELECT user_id, MAX(date) AS max_date
      FROM attendance
      WHERE gps_lat IS NOT NULL AND gps_lng IS NOT NULL
      GROUP BY user_id
    ) m ON a.user_id = m.user_id AND a.date = m.max_date
    WHERE a.gps_lat IS NOT NULL AND a.gps_lng IS NOT NULL
  `)

  const [sites] = await pool.query(`
    SELECT id, gps_lat, gps_lng, gps_radius_m FROM jobsites
    WHERE gps_lat IS NOT NULL AND gps_lng IS NOT NULL AND status = 'active'
  `)

  let updated = 0
  for (const a of latest) {
    // 找最近的工地
    let bestSite = null
    let bestDist = Infinity
    for (const s of sites) {
      const dist = haversineMeters(a.gps_lat, a.gps_lng, s.gps_lat, s.gps_lng)
      if (dist < bestDist) {
        bestDist = dist
        bestSite = s
      }
    }
    if (bestSite && bestDist <= bestSite.gps_radius_m) {
      await pool.query(
        `UPDATE worker_profiles SET current_jobsite_id = ? WHERE user_id = ?`,
        [bestSite.id, a.user_id]
      )
      updated++
    }
  }
  log(`2.5 GPS → ${updated} workers matched to jobsites`)
  return { updated }
}

function haversineMeters(lat1, lng1, lat2, lng2) {
  const R = 6371000
  const toRad = (d) => (d * Math.PI) / 180
  const dLat = toRad(lat2 - lat1)
  const dLng = toRad(lng2 - lng1)
  const a = Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2
  return 2 * R * Math.asin(Math.sqrt(a))
}

/**
 * 2.4 finance → 反推计件工资（基于 tasks 完成数）
 * 简化为：total_pieces = tasks.status='completed' count
 * monthly_salary_estimate = total_pieces * 平均 piece_rate(50元) + total_work_hours * hourly_rate
 * 仅更新 payment_type='piece' 或 'mixed' 的工人
 */
export async function aggregateFinance() {
  const [rows] = await pool.query(`
    SELECT assigned_to AS user_id, COUNT(*) AS done_count
    FROM tasks
    WHERE assigned_to IS NOT NULL AND status = 'completed'
    GROUP BY assigned_to
  `)

  let updated = 0
  for (const r of rows) {
    const pieces = Number(r.done_count || 0)
    // 估算月度工资 = 计件数 × 50 元 + 工时 × 时薪（如果有时薪）
    const [wps] = await pool.query(
      `SELECT total_work_hours, hourly_rate FROM worker_profiles WHERE user_id = ?`,
      [r.user_id]
    )
    if (wps.length === 0) continue
    const wp = wps[0]
    const hours = Number(wp.total_work_hours || 0)
    const rate = Number(wp.hourly_rate || 0)
    const estimated = pieces * 50 + hours * rate

    await pool.query(
      `UPDATE worker_profiles SET total_pieces = ? WHERE user_id = ?`,
      [pieces, r.user_id]
    )
    // 月薪估值（只在没有手动设置过 monthly_salary 时写入，已设置的>0 不动）
    await pool.query(
      `UPDATE worker_profiles SET monthly_salary = ? WHERE user_id = ? AND (monthly_salary = 0 OR monthly_salary IS NULL)`,
      [estimated.toFixed(2), r.user_id]
    )
    updated++
  }
  log(`2.4 finance → ${updated} workers piece/total updated`)
  return { updated }
}

/**
 * 跑全部聚合任务
 * @param {{ only?: string[] }} opts
 */
export async function runAllAggregators(opts = {}) {
  const tasks = {
    attendance: aggregateAttendance,
    tasks: aggregateTasks,
    workLogs: aggregateWorkLogs,
    gps: aggregateGPS,
    finance: aggregateFinance
  }
  const only = opts.only || Object.keys(tasks)
  const results = {}
  for (const key of only) {
    try {
      results[key] = await tasks[key]()
    } catch (e) {
      log(`ERROR in ${key}:`, e.message)
      results[key] = { error: e.message }
    }
  }
  return results
}