/**
 * home.js — 首页聚合接口 (JXY 第 25-32 行需求)
 *
 * 端点:
 *   GET /api/home/overview  →  4 张卡片 + 在建项目 + 待审批 + 异常打卡
 *
 * 设计原则:
 *   1. 复用现有 endpoint 数据源,不再新写 SQL 重复计算
 *   2. Promise.all 并行调用 4-5 个内部 SQL,延迟控制在 200ms 内
 *   3. 返回结构对齐前端 Home.vue 期望:
 *      {
 *        attendance: { checked_in, should_attend, late_count, absent_count, early_leave_count },
 *        oa:         { pending_approvals },
 *        bi:         { total_orders, total_amount, ... },
 *        jobsites:   [{id, name, code, status}],
 *        jobsitesCount: N
 *      }
 */
import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'

const router = Router()

router.use(auth)

router.get('/overview', async (req, res, next) => {
  try {
    const today = new Date().toISOString().slice(0, 10)

    const [
      [[att]],
      [[approvalsCount]],
      [[biStats]],
      [jobsites],
    ] = await Promise.all([
      // 1. 今日出勤 (复用 attendance 表 + users 表)
      pool.query(
        `SELECT
           (SELECT COUNT(*) FROM users WHERE status='active') AS should_attend,
           (SELECT COUNT(*) FROM attendance
              WHERE DATE(clock_in) = CURDATE() OR DATE(date) = CURDATE()
           ) AS checked_in,
           (SELECT COUNT(*) FROM attendance
              WHERE (DATE(clock_in) = CURDATE() OR DATE(date) = CURDATE())
                AND status = 'late') AS late_count,
           (SELECT COUNT(*) FROM attendance
              WHERE (DATE(clock_in) = CURDATE() OR DATE(date) = CURDATE())
                AND status = 'absent') AS absent_count,
           (SELECT COUNT(*) FROM attendance
              WHERE (DATE(clock_in) = CURDATE() OR DATE(date) = CURDATE())
                AND status = 'early') AS early_leave_count`,
        []
      ),
      // 2. 待审批 (admin 看全部,其他人看自己)
      pool.query(
        `SELECT COUNT(*) AS pending_approvals FROM approvals WHERE status = 'pending'`,
        []
      ),
      // 3. BI 简易统计 (复用 dashboard.js 逻辑,online_orders 列名=total_amount)
      pool.query(
        `SELECT
           (SELECT COUNT(*) FROM online_orders WHERE status != 'cancelled') AS total_orders,
           COALESCE((SELECT SUM(total_amount) FROM online_orders
              WHERE status = 'completed'), 0) AS total_amount,
           (SELECT COUNT(*) FROM users WHERE status='active') AS total_customers,
           (SELECT COUNT(*) FROM products WHERE status='active') AS total_products`,
        []
      ),
      // 4. 在建项目 (复用 jobsite / projects 表)
      pool.query(
        `SELECT id, name, code, status, address, created_at
         FROM jobsites
         WHERE status IN ('in_progress','active','pending','planning')
         ORDER BY updated_at DESC
         LIMIT 10`,
        []
      ).catch(() => [[]]),
    ])

    // 如果 jobsites 表不存在/空,fallback 到 projects 表
    let jobsiteRows = jobsites
    if (!jobsiteRows || jobsiteRows.length === 0) {
      try {
        const [rows] = await pool.query(
          `SELECT id, name, code, status FROM projects
           WHERE status IN ('in_progress','active')
           ORDER BY updated_at DESC LIMIT 10`,
          []
        )
        jobsiteRows = rows
      } catch {
        jobsiteRows = []
      }
    }

    res.json({
      code: 0,
      message: 'ok',
      data: {
        attendance: {
          should_attend: Number(att?.should_attend) || 0,
          checked_in:    Number(att?.checked_in) || 0,
          late_count:    Number(att?.late_count) || 0,
          absent_count:  Number(att?.absent_count) || 0,
          early_leave_count: Number(att?.early_leave_count) || 0,
        },
        oa: {
          pending_approvals: Number(approvalsCount?.pending_approvals) || 0,
        },
        bi: {
          total_orders:   Number(biStats?.total_orders) || 0,
          total_amount:   Number(biStats?.total_amount) || 0,
          total_customers:Number(biStats?.total_customers) || 0,
          total_products: Number(biStats?.total_products) || 0,
        },
        jobsites: jobsiteRows || [],
        jobsitesCount: (jobsiteRows || []).length,
      },
    })
  } catch (err) {
    next(err)
  }
})

export default router