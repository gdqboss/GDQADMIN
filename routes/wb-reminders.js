import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission } from '../middleware/rbac.js'

const router = Router()

// ============================================================
// GET /api/workbuddy/reminders/center — WorkBuddy 提醒中心
//
// 波哥 2026-08-29 立: 出勤/日志不允许自动填写(只允许真人手动), 但允许提醒。
// 目的不是"钉死员工", 而是"提醒员工把工作做得更好"。
//
// 本接口【只聚合查询, 不写任何数据】— 完全符合反造假铁律。
// 按当前登录用户的角色返回"该他/她操心"的提醒:
//
//   【员工视角】自己该做的事:
//     - 我的逾期任务 (assigned_to=我, due<今天, 未完成)
//     - 我今日待完成/今日到期的任务
//     - 我刚被派的新任务 (is_new=1)
//     - 我今晚还没填工作日志
//
//   【主管/上级视角】下级的东西等我看:
//     - 下级发来的工作日志待读 (作者=我递推下级, reviewed_at 为空)
//     - 待我审批的 审批/请假/加班
//     - 我下级逾期的任务
//
//   【叠加】低库存 / 财务提醒 (有相关权限时)
// ============================================================
router.get('/reminders/center', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const uid = req.user.id
    const today = new Date().toISOString().slice(0, 10)

    const items = []

    // ===================== 通用: 我的直接/递归下级(包含自己) =====================
    let subordinateIds = [uid]
    try {
      const [subs] = await pool.query(`
        WITH RECURSIVE subordinate_tree AS (
          SELECT id FROM users WHERE supervisor_id = ?
          UNION ALL
          SELECT u.id FROM users u
          INNER JOIN subordinate_tree st ON u.supervisor_id = st.id
        )
        SELECT id FROM subordinate_tree
      `, [uid])
      subordinateIds = subs.map(s => s.id)
      if (!subordinateIds.includes(uid)) subordinateIds.push(uid)
    } catch (e) { console.error('[wb-reminders] 下级递归失败:', e?.message) }

    // ===================== 1. 我的逾期任务 (员工视角) =====================
    const [[overdueMine]] = await pool.query(`
      SELECT COUNT(*) c FROM tasks
      WHERE assigned_to = ? AND status IN ('pending','submitted')
        AND due_date IS NOT NULL AND due_date < CURDATE()
    `, [uid]).catch(e => { console.error('[wb-reminders] 查询兜底:', e?.message); return [[{ c: 0 }]] })
    if (Number(overdueMine.c) > 0) {
      const [list] = await pool.query(`
        SELECT t.id, t.title, t.due_date, t.priority
        FROM tasks t WHERE t.assigned_to = ? AND t.status IN ('pending','submitted')
          AND t.due_date IS NOT NULL AND t.due_date < CURDATE()
        ORDER BY t.due_date ASC LIMIT 5
      `, [uid]).catch(e => { console.error('[wb-reminders] 查询兜底:', e?.message); return [[]] })
      items.push({
        audience: 'self', severity: 'critical', category: 'task', count: Number(overdueMine.c),
        title: `${overdueMine.c} 个任务逾期未完成，抽空处理一下`,
        detail: (list||[]).slice(0,3).map(t => `${t.title}（${t.due_date ? String(t.due_date).slice(5,10) : '无期限'}）`).join('、'),
        action: 'tasks/overdue', related: (list||[]).slice(0,5),
      })
    }

    // ===================== 2. 我今日要处理的任务 (今天到期/待办) =====================
    const [[todayMine]] = await pool.query(`
      SELECT COUNT(*) c FROM tasks
      WHERE assigned_to = ? AND status = 'pending'
        AND (due_date IS NULL OR DATE(due_date) = CURDATE())
    `, [uid]).catch(e => { console.error('[wb-reminders] 查询兜底:', e?.message); return [[{ c: 0 }]] })
    if (Number(todayMine.c) > 0 && Number(overdueMine.c) === 0) {
      const [list] = await pool.query(`
        SELECT t.id, t.title, t.priority,
               ub.name AS from_who
        FROM tasks t LEFT JOIN users ub ON ub.id = t.assigned_by
        WHERE t.assigned_to = ? AND t.status = 'pending'
          AND (t.due_date IS NULL OR DATE(t.due_date) = CURDATE())
        ORDER BY (t.priority='high') DESC, t.id DESC LIMIT 5
      `, [uid]).catch(e => { console.error('[wb-reminders] 查询兜底:', e?.message); return [[]] })
      items.push({
        audience: 'self', severity: 'medium', category: 'task', count: Number(todayMine.c),
        title: `你今天有 ${todayMine.c} 项待办任务`,
        detail: (list||[]).slice(0,3).map(t => `${t.title}${t.from_who ? `（${t.from_who}派）`:''}`).join('、'),
        action: 'tasks/pending', related: (list||[]).slice(0,5),
      })
    }

    // ===================== 3. 我今晚还没填工作日志 =====================
    const [[myLogToday]] = await pool.query(`
      SELECT COUNT(*) c FROM work_logs
      WHERE user_id = ? AND (submit_date = CURDATE() OR DATE(created_at) = CURDATE())
    `, [uid]).catch(e => { console.error('[wb-reminders] 查询兜底:', e?.message); return [[{ c: 0 }]] })
    if (Number(myLogToday.c) === 0) {
      items.push({
        audience: 'self', severity: 'low', category: 'log', count: 1,
        title: '今天的日志还没填，收工前写一下今天做了什么吧',
        detail: '养成小习惯，日积月累就是成长记录',
        action: 'logs/submit', related: [],
      })
    }

    // ===================== 4. 下级发来的日志待读 (主管视角) =====================
    // 只统计真正的下级(排除自己), reviewed_at 为空 = 未读
    const subOnly4 = subordinateIds.filter(id => id !== uid)
    if (subOnly4.length > 0) {
      const ph = subOnly4.map(() => '?').join(',')
      const [[unreadSubLogs]] = await pool.query(`
        SELECT COUNT(*) c FROM work_logs w
        WHERE w.reviewed_at IS NULL
          AND w.status IN ('submitted','approved')
          AND w.user_id IN (${ph})
      `, subOnly4).catch(e => { console.error('[wb-reminders] 查询兜底:', e?.message); return [[{ c: 0 }]] })
      if (Number(unreadSubLogs.c) > 0) {
        const [list] = await pool.query(`
          SELECT w.id, w.submit_date, w.status,
                 u.name AS author
          FROM work_logs w LEFT JOIN users u ON u.id = w.user_id
          WHERE w.reviewed_at IS NULL AND w.status IN ('submitted','approved')
            AND w.user_id IN (${ph})
          ORDER BY w.created_at DESC LIMIT 8
        `, subOnly4).catch(e => { console.error('[wb-reminders] 查询兜底:', e?.message); return [[]] })
        items.push({
          audience: 'supervisor', severity: 'high', category: 'log', count: Number(unreadSubLogs.c),
          title: `${unreadSubLogs.c} 份下级工作日志待你阅读`,
          detail: (list||[]).slice(0,5).map(l => `${l.author}（${String(l.submit_date||'').slice(5,10)}）`).join('、'),
          action: 'logs/pending', related: (list||[]).slice(0,5),
        })
      }
    }

    // ===================== 5. 待我审批 (审批/请假/加班) =====================
    const [[myApprovals]] = await pool.query(`
      SELECT COUNT(*) c FROM approvals WHERE status='pending' AND approved_by = ?
    `, [uid]).catch(e => { console.error('[wb-reminders] 查询兜底:', e?.message); return [[{ c: 0 }]] })
    const [[myLeave]] = await pool.query(`
      SELECT COUNT(*) c FROM leave_records WHERE status='pending' AND (reviewer_id = ? OR ? IN (SELECT supervisor_id FROM users WHERE id=leave_records.user_id))
    `, [uid, uid]).catch(e => { console.error('[wb-reminders] 查询兜底:', e?.message); return [[{ c: 0 }]] })
    const [[myOt]] = await pool.query(`
      SELECT COUNT(*) c FROM overtime_records WHERE status='pending' AND (reviewer_id = ? OR ? IN (SELECT supervisor_id FROM users WHERE id=overtime_records.user_id))
    `, [uid, uid]).catch(e => { console.error('[wb-reminders] 查询兜底:', e?.message); return [[{ c: 0 }]] })
    const approvalTotal = Number(myApprovals.c) + Number(myLeave.c) + Number(myOt.c)
    if (approvalTotal > 0) {
      items.push({
        audience: 'supervisor', severity: 'high', category: 'approval', count: approvalTotal,
        title: `${approvalTotal} 项待你审批（审批${myApprovals.c} + 请假${myLeave.c} + 加班${myOt.c}）`,
        detail: '拖延会阻塞流程，尽快处理',
        action: 'approvals/pending', related: [],
      })
    }

    // ===================== 6. 我下级逾期的任务 (主管视角) =====================
    if (subordinateIds.length > 1) {
      const subOnly = subordinateIds.filter(id => id !== uid)
      if (subOnly.length > 0) {
        const ph2 = subOnly.map(() => '?').join(',')
        const [[subOverdue]] = await pool.query(`
          SELECT COUNT(*) c FROM tasks
          WHERE assigned_to IN (${ph2}) AND status IN ('pending','submitted')
            AND due_date IS NOT NULL AND due_date < CURDATE()
        `, subOnly).catch(e => { console.error('[wb-reminders] 查询兜底:', e?.message); return [[{ c: 0 }]] })
        if (Number(subOverdue.c) > 0) {
          items.push({
            audience: 'supervisor', severity: 'medium', category: 'task', count: Number(subOverdue.c),
            title: `${subOverdue.c} 个下级任务逾期未完成`,
            detail: '可以提醒相关负责人补一下进度',
            action: 'tasks/overdue', related: [],
          })
        }
      }
    }

    // ===================== 7. 低库存预警 (权限内) =====================
    const [[lowStock]] = await pool.query(`
      SELECT COUNT(*) c FROM products WHERE status='active' AND stock <= alert_stock
    `).catch(e => { console.error('[wb-reminders] 查询兜底:', e?.message); return [[{ c: 0 }]] })
    if (Number(lowStock.c) > 0) {
      items.push({
        audience: 'all', severity: 'medium', category: 'inventory', count: Number(lowStock.c),
        title: `${lowStock.c} 个商品库存低于预警线`,
        detail: '要不要安排补货？',
        action: 'inventory/alerts', related: [],
      })
    }

    // 排序: critical 优先
    const sevOrder = { critical: 0, high: 1, medium: 2, low: 3 }
    items.sort((a, b) => (sevOrder[a.severity] ?? 9) - (sevOrder[b.severity] ?? 9))

    res.json({
      date: today,
      user_id: uid,
      total: items.length,
      unread_count: items.reduce((s, i) => s + i.count, 0),
      reminders: items,
      generated_at: new Date().toISOString(),
      source: 'live',
    })
  } catch (err) { next(err) }
})

export default router
