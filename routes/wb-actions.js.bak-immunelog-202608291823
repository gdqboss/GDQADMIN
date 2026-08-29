import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission } from '../middleware/rbac.js'

const router = Router()

// POST /api/workbuddy/action/stock-count - 发起库存盘点（创建盘点任务）
// body: { warehouse_id: number, note?: string }
router.post('/action/stock-count', auth, requirePermission('workbuddy:write'), async (req, res, next) => {
  const { warehouse_id, note } = req.body || {}
  const wid = Number(warehouse_id)
  if (!wid) return res.status(400).json({ error: 'warehouse_id (number) required' })

  const connection = await pool.getConnection()
  try {
    const [[wh]] = await connection.query(`SELECT name, manager FROM warehouses WHERE id=?`, [wid])
    if (!wh) { connection.release(); return res.status(404).json({ error: 'warehouse not found' }) }

    await connection.beginTransaction()
    const [r] = await connection.query(`
      INSERT INTO stocktakes (warehouse_id, status, blind_mode, operator_id, operator_name, notes)
      VALUES (?, 'in_progress', 0, ?, ?, ?)
    `, [wid, req.user?.id || null, req.user?.name || wh.manager || null, note || null])
    await connection.commit()
    res.json({ ok: true, stocktake_id: r.insertId, warehouse_id: wid, status: 'in_progress', action: 'stock-count-created' })
  } catch (err) {
    await connection.rollback()
    next(err)
  } finally {
    connection.release()
  }
})

// GET /api/workbuddy/action/suggestions - AI 智能建议（基于当前经营数据）
router.get('/action/suggestions', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    // 低库存预警 top 5（可补货）
    const [low] = await pool.query(`
      SELECT id, sku, name, stock, alert_stock
      FROM products WHERE status='active' AND stock <= alert_stock
      ORDER BY (stock - alert_stock) ASC LIMIT 5
    `).catch(() => [[]])
    // 待审批数 + 金额
    const [[pend]] = await pool.query(`
      SELECT COUNT(*) AS c, COALESCE(SUM(amount),0) AS amt FROM approvals WHERE status='pending'
    `).catch(() => [[{ c: 0, amt: 0 }]])
    // 库存为 0 但上架的高危商品数
    const [[zeroStock]] = await pool.query(`
      SELECT COUNT(*) AS c FROM products WHERE status='active' AND stock = 0
    `).catch(() => [[{ c: 0 }]])
    // 未读财务提醒
    const [[rem]] = await pool.query(`
      SELECT COUNT(*) AS c FROM finance_reminders WHERE status IN ('pending','unread') OR status IS NULL
    `).catch(() => [[{ c: 0 }]])

    const suggestions = []
    if (Number(zeroStock.c) > 0) suggestions.push({
      type: 'inventory', priority: 'high',
      title: `${zeroStock.c} 个上架商品库存为 0`,
      detail: '可能缺货断供，建议尽快补货或下架',
      action: 'inventory/alerts',
    })
    if ((low||[]).length > 0) suggestions.push({
      type: 'inventory', priority: 'medium',
      title: `${low.length} 个商品低于预警库存`,
      detail: (low||[]).slice(0,3).map(p => `${p.name}(${p.stock}/${p.alert_stock})`).join('、'),
      action: 'inventory/alerts',
    })
    if (Number(pend.c) > 0) suggestions.push({
      type: 'approval', priority: 'medium',
      title: `${pend.c} 个审批待处理（合计 ¥${Number(pend.amt)||0}）`,
      detail: '尽快处理可避免流程阻塞',
      action: 'approvals/pending',
    })
    if (Number(rem.c) > 0) suggestions.push({
      type: 'finance', priority: 'low',
      title: `${rem.c} 条财务提醒待查看`,
      detail: '',
      action: 'finance/reminders',
    })

    res.json({ suggestions, generated_at: new Date().toISOString(), source: 'live' })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/action/priorities - 老板今日待办优先级聚合（WorkBuddy 打开即盘点）
// 汇聚: 逾期任务 / 待审工作日志 / 待审批(审批+请假+加班) / 低库存预警 / 未提交日志人数
router.get('/action/priorities', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const today = new Date().toISOString().slice(0, 10)

    // 1. 逾期任务 (tasks)
    const [[overdueTasks]] = await pool.query(`
      SELECT COUNT(*) c FROM tasks
      WHERE status IN ('pending','submitted') AND due_date IS NOT NULL AND due_date < CURDATE()
    `).catch(() => [[{ c: 0 }]])
    const [overdueTaskList] = await pool.query(`
      SELECT t.id, t.title, t.priority, t.due_date, u.name AS assignee
      FROM tasks t LEFT JOIN users u ON u.id = t.assigned_to
      WHERE t.status IN ('pending','submitted') AND t.due_date IS NOT NULL AND t.due_date < CURDATE()
      ORDER BY t.due_date ASC LIMIT 10
    `).catch(() => [[]])

    // 2. 待审工作日志 (work_logs)
    const [[pendingLogs]] = await pool.query(`
      SELECT COUNT(*) c FROM work_logs WHERE status IN ('pending','submitted')
    `).catch(() => [[{ c: 0 }]])
    const [pendingLogList] = await pool.query(`
      SELECT w.id, w.submit_date, u.name AS author
      FROM work_logs w LEFT JOIN users u ON u.id = w.user_id
      WHERE w.status IN ('pending','submitted') ORDER BY w.created_at DESC LIMIT 5
    `).catch(() => [[]])

    // 3. 待审批 (approvals + leave_records + overtime_records)
    const [[pendingApprovals]] = await pool.query(`
      SELECT COUNT(*) c, COALESCE(SUM(amount),0) amt FROM approvals WHERE status='pending'
    `).catch(() => [[{ c: 0, amt: 0 }]])
    const [[pendingLeave]] = await pool.query(`
      SELECT COUNT(*) c FROM leave_records WHERE status='pending'
    `).catch(() => [[{ c: 0 }]])
    const [[pendingOt]] = await pool.query(`
      SELECT COUNT(*) c FROM overtime_records WHERE status='pending'
    `).catch(() => [[{ c: 0 }]])

    // 4. 低库存预警 (products)
    const [[lowStock]] = await pool.query(`
      SELECT COUNT(*) c FROM products WHERE status='active' AND stock <= alert_stock
    `).catch(() => [[{ c: 0 }]])
    const [lowStockList] = await pool.query(`
      SELECT id, sku, name, stock, alert_stock FROM products
      WHERE status='active' AND stock <= alert_stock
      ORDER BY (stock - alert_stock) ASC LIMIT 5
    `).catch(() => [[]])

    // 5. 今日未提交日志人数 (users)
    const [[noLog]] = await pool.query(`
      SELECT COUNT(*) c FROM users u
      WHERE u.user_type='staff' AND u.id NOT IN (
        SELECT user_id FROM work_logs WHERE submit_date=CURDATE() OR DATE(created_at)=CURDATE()
      )
    `).catch(() => [[{ c: 0 }]])

    // 组装优先级清单 (按 severity / 血量)
    const items = []

    if (Number(overdueTasks.c) > 0) items.push({
      severity: 'critical', category: 'task', count: Number(overdueTasks.c),
      title: `${overdueTasks.c} 个任务逾期未完成`,
      detail: (overdueTaskList||[]).slice(0,3).map(t => `${t.title}(${t.assignee||'未指派'})`).join('、'),
      action: 'tasks/overdue', related: (overdueTaskList||[]).slice(0,10),
    })

    if (Number(pendingLogs.c) > 0) items.push({
      severity: 'high', category: 'log', count: Number(pendingLogs.c),
      title: `${pendingLogs.c} 条工作日志待审核`,
      detail: (pendingLogList||[]).slice(0,3).map(l => `${l.author||l.id}(${l.submit_date})`).join('、'),
      action: 'logs/pending', related: (pendingLogList||[]),
    })

    const approvalTotal = Number(pendingApprovals.c) + Number(pendingLeave.c) + Number(pendingOt.c)
    if (approvalTotal > 0) items.push({
      severity: 'high', category: 'approval', count: approvalTotal,
      title: `${approvalTotal} 个待审批（审批${pendingApprovals.c} + 请假${pendingLeave.c} + 加班${pendingOt.c}，合计 ¥${Number(pendingApprovals.amt)||0}）`,
      detail: '审批/请假/加班待处理，拖延会阻塞流程',
      action: 'approvals/pending',
    })

    if (Number(lowStock.c) > 0) items.push({
      severity: 'medium', category: 'inventory', count: Number(lowStock.c),
      title: `${lowStock.c} 个商品低于预警库存`,
      detail: (lowStockList||[]).slice(0,3).map(p => `${p.name}(${p.stock}/${p.alert_stock})`).join('、'),
      action: 'inventory/alerts', related: (lowStockList||[]),
    })

    if (Number(noLog.c) > 0) items.push({
      severity: 'low', category: 'log', count: Number(noLog.c),
      title: `${noLog.c} 名员工今日未提交工作日志`,
      detail: '可提醒员工补交日志',
      action: 'logs/summary',
    })

    res.json({
      date: today,
      total_pending: items.reduce((s, i) => s + i.count, 0),
      priorities: items,
      generated_at: new Date().toISOString(),
      source: 'live',
    })
  } catch (err) { next(err) }
})

// POST /api/workbuddy/action/cleanup-low-stock - 低库存清理（生成补货建议）
// body: { limit?: number, exclude_ids?: number[] }
// 只读分析，生成补货建议清单（不直接改库存）
router.post('/action/cleanup-low-stock', auth, requirePermission('workbuddy:write'), async (req, res, next) => {
  try {
    const limit = Math.min(Number(req.body?.limit) || 10, 50)
    // 所有低于预警库存的 active 商品，算缺货量
    const [rows] = await pool.query(`
      SELECT id, sku, name, category, unit, stock, alert_stock, turnover_days,
             purchase_price, sale_price, supplier
      FROM products
      WHERE status='active' AND alert_stock > 0 AND stock <= alert_stock
      ORDER BY (stock - alert_stock) ASC
      LIMIT ?
    `, [limit])
    // 每个缺货量 = alert_stock - stock（建议补到预警线以上）
    const suggestions = (rows||[]).map(p => {
      const deficit = Math.max(Number(p.alert_stock) - Number(p.stock), 0)
      const restockQty = Number(p.alert_stock) * 2 - Number(p.stock)  // 建议补到2倍预警线
      return {
        product_id: p.id, sku: p.sku, name: p.name, category: p.category, unit: p.unit,
        current_stock: Number(p.stock)||0, alert_stock: Number(p.alert_stock)||0,
        deficit: deficit,
        suggested_restock: Math.max(restockQty, deficit),  // 补到2倍预警线
        est_cost: Math.round(Math.max(restockQty, deficit) * (Number(p.purchase_price)||0)),
        turnover_days: p.turnover_days,
        supplier: p.supplier,
        severity: Number(p.stock) === 0 ? 'out_of_stock' : (deficit >= Number(p.alert_stock) ? 'severe' : 'low'),
      }
    })
    // 缺货额汇总
    const total_est_cost = suggestions.reduce((s, x) => s + x.est_cost, 0)
    const out_of_stock = suggestions.filter(x => x.severity === 'out_of_stock').length

    res.json({
      action: 'cleanup-low-stock',
      low_stock_products: suggestions.length,
      out_of_stock_count: out_of_stock,
      total_est_restock_cost: total_est_cost,
      suggestions,
      note: '此为只读补货建议，未实际修改库存。确认后可走采购/入库流程',
    })
  } catch (err) { next(err) }
})

export default router