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

export default router