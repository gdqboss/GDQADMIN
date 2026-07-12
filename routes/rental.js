/**
 * 传媒广告物料租赁报价下单系统 - 租赁订单 + 库存防冲突
 *
 * 核心:
 *   - 客户提交询价（小程序端）
 *   - 时间段库存锁（防冲突）
 *   - 询价单查询/取消
 */
import express from 'express'
import jwt from 'jsonwebtoken'
import { pool } from '../db/connection.js'

const router = express.Router()

// 软解析 token（不强制登录，匿名也可提交询价）
function softAuthUserId(req) {
  try {
    const h = req.headers.authorization
    if (!h || !h.startsWith('Bearer ')) return null
    const decoded = jwt.verify(h.split(' ')[1], process.env.JWT_SECRET)
    console.log('[softAuthUserId] decoded:', JSON.stringify(decoded))
    return decoded?.id || null
  } catch (e) {
    console.log('[softAuthUserId ERR]', e.message)
    return null
  }
}

// 拿用户角色（用于审计 created_by_role）
async function getUserRoleForAudit(conn, userId) {
  try {
    const [rows] = await conn.query('SELECT role, user_type FROM users WHERE id = ?', [userId])
    if (!rows.length) return 'unknown'
    const u = rows[0]
    return u.user_type === 'customer' ? `customer:${u.role}` : u.role
  } catch { return 'unknown' }
}

// ============== 1. 询价下单（小程序客户可调） ==============
router.post('/inquiry', async (req, res, next) => {
    const conn = await pool.getConnection()
    try {
        await conn.beginTransaction()
        const {
            customer_name, customer_phone, customer_type = 'biz',
            activity_type, activity_time_start, activity_time_end,
            setup_time, teardown_time, activity_location, remark,
            items = []
        } = req.body || {}

        // 已登录用户自动 bind user_id 到报价单（关键：从 token 解 user_id）
        const loggedInUserId = softAuthUserId(req)
        console.log('[rental/inquiry DEBUG] loggedInUserId:', loggedInUserId, 'authHeader:', (req.headers.authorization || '').slice(0, 40))

        if (!customer_name || !customer_phone) { await conn.rollback(); return res.status(400).json({ ok: false, error: '请填姓名和电话' }) }
        if (!activity_time_start || !activity_time_end) { await conn.rollback(); return res.status(400).json({ ok: false, error: '请填活动时间' }) }
        if (!Array.isArray(items) || !items.length) { await conn.rollback(); return res.status(400).json({ ok: false, error: '至少选 1 件物料' }) }

        // 库存冲突检查
        for (const it of items) {
            if (!it.product_id) continue
            const [stocks] = await conn.query(`SELECT stock, name FROM products WHERE id = ? AND status = 'active'`, [it.product_id])
            if (!stocks.length) { await conn.rollback(); return res.status(400).json({ ok: false, error: `物料 #${it.product_id} 已下架` }) }
            const [locks] = await conn.query(
                `SELECT COALESCE(SUM(qty), 0) AS locked_qty FROM rental_stock_locks
                 WHERE product_id = ? AND status = 'locked'
                   AND NOT (lock_end < ? OR lock_start > ?)`,
                [it.product_id, activity_time_start, activity_time_end]
            )
            const available = Number(stocks[0].stock || 0) - Number(locks[0].locked_qty || 0)
            if (available < Number(it.qty || 1)) {
                await conn.rollback()
                return res.status(409).json({ ok: false, error: `物料「${stocks[0].name}」库存不足（剩余 ${available} 件）` })
            }
        }

        // 询价单 = 草稿状态的报价单
        const orderNo = 'INQ' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + Math.floor(Math.random() * 9999).toString().padStart(4, '0')
        let subtotal = 0
        for (const it of items) {
            const days = Number(it.days || 1)
            const qty  = Number(it.qty  || 1)
            let unitPrice = Number(it.unit_price || 0)
            if (!unitPrice && it.product_id) {
                const [tier] = await conn.query(
                    `SELECT price, unit_day_price FROM customer_pricing_tiers WHERE product_id = ? AND customer_type = ? AND is_active = 1`,
                    [it.product_id, customer_type]
                )
                if (tier[0]) unitPrice = Number(tier[0].unit_day_price || tier[0].price || 0)
                if (!unitPrice) {
                    const [p] = await conn.query(`SELECT sale_price FROM products WHERE id = ?`, [it.product_id])
                    unitPrice = Number(p[0]?.sale_price || 0)
                }
            }
            subtotal += +(unitPrice * qty * days).toFixed(2)
        }

        const [r] = await conn.query(
                    `INSERT INTO quote_orders
                     (order_no, customer_id, customer_name, customer_type, customer_phone,
                      activity_type, activity_time_start, activity_time_end,
                      setup_time, teardown_time, activity_location, remark,
                      subtotal, pre_tax_total, status, template_type, created_by, created_by_role)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_approval', ?, ?, ?)`,
                    [orderNo, loggedInUserId, customer_name, customer_type, customer_phone,
                     activity_type || null, activity_time_start, activity_time_end,
                     setup_time || null, teardown_time || null, activity_location || null, remark || null,
                     subtotal, subtotal, customer_type,
                     loggedInUserId, loggedInUserId ? (await getUserRoleForAudit(conn, loggedInUserId)) : 'guest']
        )
        const quoteId = r.insertId

        for (let i = 0; i < items.length; i++) {
            const it = items[i]
            const days = Number(it.days || 1)
            const qty  = Number(it.qty  || 1)
            let unitPrice = Number(it.unit_price || 0)
            if (!unitPrice && it.product_id) {
                const [tier] = await conn.query(
                    `SELECT price, unit_day_price FROM customer_pricing_tiers WHERE product_id = ? AND customer_type = ? AND is_active = 1`,
                    [it.product_id, customer_type]
                )
                if (tier[0]) unitPrice = Number(tier[0].unit_day_price || tier[0].price || 0)
                if (!unitPrice) {
                    const [p] = await conn.query(`SELECT sale_price FROM products WHERE id = ?`, [it.product_id])
                    unitPrice = Number(p[0]?.sale_price || 0)
                }
            }
            const lineSub = +(unitPrice * qty * days).toFixed(2)
            await conn.query(
                `INSERT INTO quote_items (quote_id, product_id, product_sku, product_name, category, item_type,
                 qty, days, unit_price, unit_day_price, subtotal, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
                [quoteId, it.product_id || null, it.product_sku || null, it.product_name || '物料', it.category || null,
                 it.item_type || 'equipment', qty, days, unitPrice, it.unit_day_price || null, lineSub, i]
            )
        }

        await conn.query(
            `INSERT INTO quote_audit_logs (quote_id, action, diff_summary, ip_address) VALUES (?, 'created', '小程序客户询价下单', ?)`,
            [quoteId, req.ip]
        )

        await conn.commit()
        res.json({ ok: true, data: { id: quoteId, order_no: orderNo, subtotal } })
    } catch (e) {
        await conn.rollback()
        next(e)
    } finally {
        conn.release()
    }
})

// ============== 2. 库存状态总览（仓库端用） ==============
router.get('/inventory/overview', async (req, res, next) => {
    try {
        const { category, keyword } = req.query
        const conds = ["p.status = 'active'"]
        const args = []
        if (category) { conds.push('p.category = ?'); args.push(category) }
        if (keyword)  { conds.push('(p.name LIKE ? OR p.sku LIKE ?)'); args.push(`%${keyword}%`, `%${keyword}%`) }
        const where = conds.join(' AND ')
        const [rows] = await pool.query(
            `SELECT p.id, p.sku, p.name, p.category, p.spec, p.unit, p.stock,
                    p.image_main, p.publish_status, p.turnover_days,
                    COALESCE(SUM(CASE WHEN rsl.status = 'locked' AND NOT (rsl.lock_end < NOW()) THEN rsl.qty ELSE 0 END), 0) AS locked_qty,
                    (p.stock - COALESCE(SUM(CASE WHEN rsl.status = 'locked' AND NOT (rsl.lock_end < NOW()) THEN rsl.qty ELSE 0 END), 0)) AS available_qty
             FROM products p
             LEFT JOIN rental_stock_locks rsl ON rsl.product_id = p.id
             WHERE ${where}
             GROUP BY p.id
             ORDER BY p.category, p.id`,
            args
        )
        res.json({ ok: true, data: rows })
    } catch (e) { next(e) }
})

// ============== 3. 物料上下架（仓库员专属） ==============
router.post('/inventory/:id/toggle', async (req, res, next) => {
    try {
        const id = Number(req.params.id)
        const { publish_status } = req.body || {}
        const role = req.user?.role
        if (!['warehouse', 'admin', 'boss'].includes(role)) {
            return res.status(403).json({ ok: false, error: '仅仓库员/超管/总经理可上下架' })
        }
        if (!['draft', 'published', 'unpublished'].includes(publish_status)) {
            return res.status(400).json({ ok: false, error: '非法状态' })
        }
        await pool.query(`UPDATE products SET publish_status = ? WHERE id = ?`, [publish_status, id])
        res.json({ ok: true })
    } catch (e) { next(e) }
})

// ============== 4. 询价单列表（按状态/客户类型过滤） ==============
router.get('/inquiries', async (req, res, next) => {
    try {
        const { status, customer_type, page = 1, page_size = 20 } = req.query
        const conds = ['1=1']
        const args = []
        if (status)        { conds.push('q.status = ?');        args.push(status) }
        if (customer_type) { conds.push('q.customer_type = ?'); args.push(customer_type) }
        const where = conds.join(' AND ')
        const offset = (Number(page) - 1) * Number(page_size)
        const [list] = await pool.query(
            `SELECT q.id, q.order_no, q.customer_name, q.customer_type, q.customer_phone,
                    q.activity_time_start, q.activity_time_end, q.activity_location,
                    q.pre_tax_total, q.status, q.created_at
             FROM quote_orders q WHERE ${where}
             ORDER BY q.id DESC LIMIT ? OFFSET ?`,
            [...args, Number(page_size), offset]
        )
        const [cnt] = await pool.query(`SELECT COUNT(*) AS total FROM quote_orders q WHERE ${where}`, args)
        res.json({ ok: true, data: { list, total: cnt[0].total } })
    } catch (e) { next(e) }
})

// ============== 5. 库存锁详情（按 quote_id 查锁了哪些设备） ==============
router.get('/locks/:quote_id', async (req, res, next) => {
    try {
        const [rows] = await pool.query(
            `SELECT rsl.*, p.name AS product_name, p.sku
             FROM rental_stock_locks rsl
             LEFT JOIN products p ON rsl.product_id = p.id
             WHERE rsl.quote_id = ?`,
            [req.params.quote_id]
        )
        res.json({ ok: true, data: rows })
    } catch (e) { next(e) }
})

// ============== 5A. 报价模板列表（用于渲染/切换） ==============
router.get('/quote-templates', async (req, res, next) => {
    try {
        const [rows] = await pool.query(`SELECT * FROM rental_quote_templates WHERE is_active = 1 ORDER BY template_key`)
        res.json({ ok: true, data: rows })
    } catch (e) { next(e) }
})

// ============== 5B. 客户端浏览设备(只返回 available 状态 + 有客户类型定价的) ==============
router.get('/public/products', async (req, res, next) => {
    try {
        const { customer_type = 'biz', category } = req.query
        const conds = [`p.publish_status = 'published'`, `(p.rental_status IS NULL OR p.rental_status = 'available')`]
        const args = [customer_type]
        if (category) { conds.push('p.category = ?'); args.push(category) }
        const [rows] = await pool.query(
            `SELECT p.id, p.sku, p.name, p.category, p.spec, p.unit, p.image_main, p.image_url_2, p.image_url_3,
                    COALESCE(cpt.price, p.sale_price) AS effective_price,
                    COALESCE(cpt.unit_day_price, p.sale_price) AS unit_day_price,
                    COALESCE(cpt.tax_rate, 0.06) AS tax_rate,
                    p.stock,
                    (p.stock - COALESCE((SELECT SUM(qty) FROM rental_stock_locks WHERE product_id = p.id AND status = 'locked' AND NOT (lock_end < NOW())), 0)) AS available_qty
             FROM products p
             LEFT JOIN customer_pricing_tiers cpt ON cpt.product_id = p.id AND cpt.customer_type = ? AND cpt.is_active = 1
             WHERE ${conds.join(' AND ')}
             ORDER BY p.category, p.id
             LIMIT 500`, args)
        res.json({ ok: true, data: rows, customer_type })
    } catch (e) { next(e) }
})

// ============== 5C. 后台产品总览(含价格3 套 + 库存 + 状态) ==============
router.get('/admin/products', async (req, res, next) => {
    try {
        const { category, rental_status, keyword } = req.query
        const conds = [`1=1`]
        const args = []
        if (category) { conds.push('p.category = ?'); args.push(category) }
        if (rental_status) { conds.push('p.rental_status = ?'); args.push(rental_status) }
        if (keyword) { conds.push('(p.name LIKE ? OR p.sku LIKE ?)'); args.push(`%${keyword}%`, `%${keyword}%`) }
        const [rows] = await pool.query(
            `SELECT p.id, p.sku, p.name, p.category, p.spec, p.unit, p.image_main,
                    p.stock, p.publish_status, p.rental_status, p.repair_note,
                    MAX(CASE WHEN cpt.customer_type = 'gov' THEN cpt.price END) AS gov_price,
                    MAX(CASE WHEN cpt.customer_type = 'biz' THEN cpt.price END) AS biz_price,
                    MAX(CASE WHEN cpt.customer_type = 'peer' THEN cpt.price END) AS peer_price,
                    (p.stock - COALESCE((SELECT SUM(qty) FROM rental_stock_locks WHERE product_id = p.id AND status = 'locked' AND NOT (lock_end < NOW())), 0)) AS available_qty
             FROM products p
             LEFT JOIN customer_pricing_tiers cpt ON cpt.product_id = p.id AND cpt.is_active = 1
             WHERE ${conds.join(' AND ')}
             GROUP BY p.id
             ORDER BY p.rental_status, p.category, p.id`, args)
        res.json({ ok: true, data: rows })
    } catch (e) { next(e) }
})

// ============== 5D. 三类客户价格设置(后台CRUD) ==============
router.post('/pricing', async (req, res, next) => {
    try {
        const role = req.user?.role
        if (!['admin', 'boss', '总经理', 'manager', '主管'].includes(role)) {
            return res.status(403).json({ ok: false, error: '仅主管以上可设置价格' })
        }
        const { product_id, customer_type, price, unit_day_price, tax_rate } = req.body || {}
        if (!['gov', 'biz', 'peer'].includes(customer_type)) return res.status(400).json({ ok: false, error: '客户类型非法' })
        if (!(Number(price) >= 0)) return res.status(400).json({ ok: false, error: '价格非法' })
        await pool.query(
            `INSERT INTO customer_pricing_tiers (product_id, customer_type, price, unit_day_price, tax_rate)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE price = VALUES(price), unit_day_price = VALUES(unit_day_price), tax_rate = VALUES(tax_rate)`,
            [product_id, customer_type, price, unit_day_price || null, tax_rate ?? 0.06]
        )
        res.json({ ok: true })
    } catch (e) { next(e) }
})

// ============== 6. 折扣应用（主管/总经理操作） ==============
router.post('/:id/apply-discount', async (req, res, next) => {
    const conn = await pool.getConnection()
    try {
        await conn.beginTransaction()
        const id = Number(req.params.id)
        const { discount_rate, note } = req.body || {}
        const role = req.user?.role
        const dr = Number(discount_rate)
        if (!(dr > 0 && dr <= 1)) { await conn.rollback(); return res.status(400).json({ ok: false, error: '折扣率必须在 0-1 之间' }) }
        // 多级折扣权限: 文员/staff=0折扣, 主管≤0.85, 总经理≤0.5, 超管不受限
        const caps = { staff: 1.0, '文员': 1.0, manager: 0.85, '主管': 0.85, boss: 0.5, '总经理': 0.5, admin: 0 }
        const cap = caps[role]
        if (cap === undefined) { await conn.rollback(); return res.status(403).json({ ok: false, error: '角色无折扣权限' }) }
        if (cap > 0 && dr < cap) {
            await conn.rollback()
            return res.status(403).json({ ok: false, error: `当前角色最低折扣 ${(cap * 100).toFixed(0)}%,需总经理审批` })
        }
        const [rows] = await conn.query(`SELECT subtotal, pre_tax_total, discount_rate FROM quote_orders WHERE id = ?`, [id])
        if (!rows.length) { await conn.rollback(); return res.status(404).json({ ok: false, error: '订单不存在' }) }
        const before = rows[0]
        const subtotal = Number(before.subtotal)
        const discountAmount = +(subtotal * (1 - dr)).toFixed(2)
        const preTax = +(subtotal * dr).toFixed(2)
        await conn.query(
            `UPDATE quote_orders SET discount_rate = ?, discount_amount = ?, pre_tax_total = ?, final_amount = ? WHERE id = ?`,
            [dr, discountAmount, preTax, preTax, id]
        )
        await conn.query(
            `INSERT INTO quote_audit_logs (quote_id, user_id, user_role, action, before_value, after_value, diff_summary, ip_address)
             VALUES (?, ?, ?, 'discount_applied', ?, ?, ?, ?)`,
            [id, req.user?.id, role,
             JSON.stringify({ discount_rate: before.discount_rate }),
             JSON.stringify({ discount_rate: dr, discount_amount: discountAmount }),
             `${req.user?.name || '操作员'} 应用折扣 ${(dr * 100).toFixed(1)}%${note ? ' / ' + note : ''}`,
             req.ip]
        )
        await conn.commit()
        res.json({ ok: true, data: { discount_amount: discountAmount, pre_tax_total: preTax } })
    } catch (e) { await conn.rollback(); next(e) } finally { conn.release() }
})

// ============== 7. 改价单条物料（总经理专属，超管也OK） ==============
router.put('/items/:itemId/price', async (req, res, next) => {
    try {
        const itemId = Number(req.params.itemId)
        const { new_price, reason } = req.body || {}
        const role = req.user?.role
        if (!['boss', '总经理', 'admin'].includes(role)) {
            return res.status(403).json({ ok: false, error: '仅总经理/超管可改价' })
        }
        const np = Number(new_price)
        if (!(np >= 0)) return res.status(400).json({ ok: false, error: '价格非法' })
        const [items] = await pool.query(`SELECT * FROM quote_items WHERE id = ?`, [itemId])
        if (!items.length) return res.status(404).json({ ok: false, error: '条目不存在' })
        const before = items[0]
        const oldPrice = Number(before.unit_price)
        const qty = Number(before.qty || 1)
        const days = Number(before.days || 1)
        const lineSub = +(np * qty * days).toFixed(2)
        await pool.query(`UPDATE quote_items SET unit_price = ?, subtotal = ? WHERE id = ?`, [np, lineSub, itemId])
        // 联动更新主单 subtotal
        await pool.query(
            `UPDATE quote_orders SET subtotal = (
                SELECT SUM(subtotal) FROM quote_items WHERE quote_id = ?
             ), pre_tax_total = subtotal * (1 - IFNULL(discount_rate,0)), final_amount = pre_tax_total WHERE id = ?`,
            [before.quote_id, before.quote_id]
        )
        await pool.query(
            `INSERT INTO quote_audit_logs (quote_id, user_id, user_role, action, before_value, after_value, diff_summary, ip_address)
             VALUES (?, ?, ?, 'price_changed', ?, ?, ?, ?)`,
            [before.quote_id, req.user?.id, role,
             JSON.stringify({ item_id: itemId, old_price: oldPrice }),
             JSON.stringify({ item_id: itemId, new_price: np }),
             `改价 ${before.product_name}: ¥${oldPrice} → ¥${np}${reason ? ' / ' + reason : ''}`,
             req.ip]
        )
        res.json({ ok: true, data: { new_subtotal: lineSub } })
    } catch (e) { next(e) }
})

// ============== 8. 报价审批（总经理/超管专属） ==============
router.post('/:id/audit', async (req, res, next) => {
    try {
        const id = Number(req.params.id)
        const { approve, note } = req.body || {}
        const role = req.user?.role
        if (!['boss', '总经理', 'admin'].includes(role)) {
            return res.status(403).json({ ok: false, error: '仅总经理/超管可审批' })
        }
        const [rows] = await pool.query(`SELECT status FROM quote_orders WHERE id = ?`, [id])
        if (!rows.length) return res.status(404).json({ ok: false, error: '订单不存在' })
        if (!['pending_approval', 'draft'].includes(rows[0].status)) {
            return res.status(400).json({ ok: false, error: `当前状态 ${rows[0].status} 不可审批` })
        }
        const newStatus = approve ? 'quoted' : 'draft'
        await pool.query(
            `UPDATE quote_orders SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?`,
            [newStatus, req.user?.id, note || null, id]
        )
        await pool.query(
            `INSERT INTO quote_audit_logs (quote_id, user_id, user_role, action, before_value, after_value, diff_summary, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
            [id, req.user?.id, role, approve ? 'approved' : 'rejected',
             JSON.stringify({ status: rows[0].status }), JSON.stringify({ status: newStatus }),
             `${approve ? '✅批准' : '❌驳回'}${note ? ' / ' + note : ''}`, req.ip]
        )
        res.json({ ok: true, data: { status: newStatus } })
    } catch (e) { next(e) }
})

// ============== 9. 状态机推进 ==============
const STATUS_FLOW = {
    draft: ['pending_approval', 'cancelled'],
    pending_approval: ['quoted', 'draft', 'cancelled'],
    quoted: ['confirmed', 'cancelled'],
    confirmed: ['signed', 'cancelled'],
    signed: ['completed'],
    completed: ['archived'],
    archived: [],
    cancelled: []
}
router.put('/:id/status', async (req, res, next) => {
    try {
        const id = Number(req.params.id)
        const { status: nextStatus } = req.body || {}
        const [rows] = await pool.query(`SELECT status FROM quote_orders WHERE id = ?`, [id])
        if (!rows.length) return res.status(404).json({ ok: false, error: '订单不存在' })
        const cur = rows[0].status
        const allowed = STATUS_FLOW[cur] || []
        if (!allowed.includes(nextStatus)) {
            return res.status(400).json({ ok: false, error: `不可从 ${cur} 跳到 ${nextStatus}` })
        }
        await pool.query(`UPDATE quote_orders SET status = ? WHERE id = ?`, [nextStatus, id])
        if (nextStatus === 'confirmed') {
            // 客户确认 → 锁定库存消耗(从 locked → consumed)
            await pool.query(
                `UPDATE rental_stock_locks SET status = 'consumed' WHERE quote_id = ? AND status = 'locked'`,
                [id]
            )
        }
        if (nextStatus === 'cancelled') {
            // 取消 → 释放库存锁
            await pool.query(
                `UPDATE rental_stock_locks SET status = 'released' WHERE quote_id = ? AND status = 'locked'`,
                [id]
            )
        }
        await pool.query(
            `INSERT INTO quote_audit_logs (quote_id, user_id, user_role, action, before_value, after_value, diff_summary, ip_address)
             VALUES (?, ?, ?, 'status_changed', ?, ?, ?, ?)`,
            [id, req.user?.id, req.user?.role, JSON.stringify({ status: cur }), JSON.stringify({ status: nextStatus }),
             `状态: ${cur} → ${nextStatus}`, req.ip]
        )
        res.json({ ok: true, data: { from: cur, to: nextStatus } })
    } catch (e) { next(e) }
})

// ============== 10. 报价单 HTML 渲染(三套模板) ==============
router.get('/:id/quote-html', async (req, res, next) => {
    try {
        const id = Number(req.params.id)
        const templateKey = (req.query.template || 'biz').toString()
        const [orders] = await pool.query(`SELECT * FROM quote_orders WHERE id = ?`, [id])
        if (!orders.length) return res.status(404).json({ ok: false, error: '订单不存在' })
        const order = orders[0]
        const [items] = await pool.query(`SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order, id`, [id])
        const [tpl] = await pool.query(`SELECT * FROM rental_quote_templates WHERE template_key = ? AND is_active = 1`, [templateKey])
        const t = tpl[0] || { header_html: '', footer_html: '', body_style: 'detail', name: '默认' }
        const taxRate = Number(order.tax_rate || 0)
        const preTax = Number(order.pre_tax_total || 0)
        const taxAmount = +(preTax * taxRate).toFixed(2)
        const postTax = +(preTax + taxAmount).toFixed(2)
        // 渲染 body(按 body_style)
        const itemRows = items.map((it, i) => {
            if (t.body_style === 'minimal') return `<tr><td>${it.product_name}</td><td>¥${Number(it.subtotal).toFixed(0)}</td></tr>`
            return `<tr><td>${i + 1}</td><td>${it.product_name}</td><td>${it.spec || '-'}</td><td>${it.qty}</td><td>${it.days}</td><td>¥${Number(it.unit_price).toFixed(2)}</td><td>¥${Number(it.subtotal).toFixed(2)}</td></tr>`
        }).join('')
        const headerHtml = (t.header_html || '')
            .replace(/{customer_name}/g, order.customer_name || '')
            .replace(/{activity_time}/g, `${(order.activity_time_start || '').toString().slice(0, 10)}`)
            .replace(/{logo}/g, `<img src="${t.logo_url || ''}" style="height:40px;">`)
            .replace(/{order_no}/g, order.order_no)
        const isGov = templateKey === 'gov'
        const colSpan = t.body_style === 'minimal' ? 1 : 5
        const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>报价单 ${order.order_no}</title>
<style>
  body { font-family: "Microsoft YaHei", sans-serif; padding: ${isGov ? '40px' : '20px'}; color: #333; }
  .gov-header { border-bottom: 3px double #c00; padding-bottom: 10px; margin-bottom: 20px; }
  .red-title { font-size: 28px; font-weight: bold; color: #c00; text-align: center; letter-spacing: 8px; }
  table { width: 100%; border-collapse: collapse; margin: 16px 0; }
  th { background: ${isGov ? '#c00' : '#f5f5f5'}; color: ${isGov ? '#fff' : '#333'}; padding: 10px; border: 1px solid #ddd; text-align: left; }
  td { padding: 8px; border: 1px solid #ddd; }
  .totals { margin-top: 20px; text-align: right; }
  .totals div { padding: 4px 0; }
  .totals .final { font-size: 22px; color: #c00; font-weight: bold; }
  .footer { margin-top: 40px; padding-top: 20px; border-top: 1px dashed #999; font-size: 12px; color: #666; text-align: center; }
  .biz-footer, .peer-footer, .gov-footer { margin-top: 40px; padding-top: 16px; border-top: 1px dashed #999; font-size: 12px; color: #666; text-align: center; }
  @media print { .no-print { display: none; } }
</style></head><body>
${headerHtml}
<table>
  <thead><tr><th>序号</th><th>物料/服务</th><th>规格</th><th>数量</th><th>天数</th><th>单价</th><th>小计</th></tr></thead>
  <tbody>${itemRows || '<tr><td colspan="7" style="text-align:center;">暂无明细</td></tr>'}</tbody>
</table>
<div class="totals">
  <div>税前合计：¥${preTax.toFixed(2)}</div>
  ${taxRate > 0 ? `<div>税额 (${(taxRate * 100).toFixed(1)}%)：¥${taxAmount.toFixed(2)}</div><div>税后合计：¥${postTax.toFixed(2)}</div>` : ''}
  ${Number(order.discount_rate || 1) < 1 ? `<div>折扣 (${((1 - Number(order.discount_rate)) * 100).toFixed(1)}%)：-¥${Number(order.discount_amount || 0).toFixed(2)}</div>` : ''}
  <div class="final">最终报价：¥${postTax.toFixed(2)}</div>
</div>
<div style="margin-top:30px;">${(t.footer_html || '').replace(/{customer_name}/g, order.customer_name || '')}</div>
<div class="no-print" style="text-align:center;margin-top:20px;">
  <button onclick="window.print()" style="padding:10px 24px;background:#c00;color:#fff;border:none;border-radius:4px;cursor:pointer;">🖨️ 打印 / 另存为 PDF</button>
</div>
</body></html>`
        res.set('Content-Type', 'text/html; charset=utf-8')
        res.send(html)
    } catch (e) { next(e) }
})

// ============== 11. 报价单 Excel 台账导出 ==============
router.get('/export-excel', async (req, res, next) => {
    try {
        const ExcelJS = (await import('exceljs')).default
        const { status, customer_type, date_from, date_to } = req.query
        const conds = ['1=1']
        const args = []
        if (status) { conds.push('q.status = ?'); args.push(status) }
        if (customer_type) { conds.push('q.customer_type = ?'); args.push(customer_type) }
        if (date_from) { conds.push('q.created_at >= ?'); args.push(date_from) }
        if (date_to) { conds.push('q.created_at <= ?'); args.push(date_to) }
        const [list] = await pool.query(
            `SELECT q.*, u.name AS staff_name FROM quote_orders q LEFT JOIN users u ON u.id = q.created_by
             WHERE ${conds.join(' AND ')} ORDER BY q.id DESC LIMIT 10000`, args)
        const wb = new ExcelJS.Workbook()
        const ws = wb.addWorksheet('租赁报价台账')
        ws.columns = [
            { header: '订单号', key: 'order_no', width: 20 },
            { header: '客户类型', key: 'customer_type', width: 12 },
            { header: '客户姓名', key: 'customer_name', width: 16 },
            { header: '客户电话', key: 'customer_phone', width: 14 },
            { header: '活动类型', key: 'activity_type', width: 14 },
            { header: '活动时间', key: 'activity_time', width: 24 },
            { header: '活动地点', key: 'activity_location', width: 30 },
            { header: '税前金额', key: 'pre_tax_total', width: 12 },
            { header: '折扣比例', key: 'discount_rate', width: 10 },
            { header: '折扣金额', key: 'discount_amount', width: 12 },
            { header: '税率', key: 'tax_rate', width: 8 },
            { header: '税后金额', key: 'post_tax_total', width: 12 },
            { header: '最终成交', key: 'final_amount', width: 12 },
            { header: '订单状态', key: 'status', width: 12 },
            { header: '操作员', key: 'staff_name', width: 12 },
            { header: '报价模板', key: 'template_type', width: 12 },
            { header: '下单时间', key: 'created_at', width: 18 }
        ]
        list.forEach(r => ws.addRow({
            order_no: r.order_no,
            customer_type: { gov: '政府', biz: '个人', peer: '同行' }[r.customer_type] || r.customer_type,
            customer_name: r.customer_name,
            customer_phone: r.customer_phone,
            activity_type: r.activity_type,
            activity_time: `${(r.activity_time_start || '').toString().slice(0, 16)} ~ ${(r.activity_time_end || '').toString().slice(0, 16)}`,
            activity_location: r.activity_location,
            pre_tax_total: Number(r.pre_tax_total || 0).toFixed(2),
            discount_rate: ((1 - Number(r.discount_rate || 1)) * 100).toFixed(1) + '%',
            discount_amount: Number(r.discount_amount || 0).toFixed(2),
            tax_rate: ((Number(r.tax_rate || 0)) * 100).toFixed(1) + '%',
            post_tax_total: Number(r.post_tax_total || 0).toFixed(2),
            final_amount: Number(r.final_amount || 0).toFixed(2),
            status: r.status,
            staff_name: r.staff_name || '-',
            template_type: { gov: '政府', biz: '商业', peer: '同行' }[r.template_type] || '-',
            created_at: r.created_at
        }))
        const buf = await wb.xlsx.writeBuffer()
        const ts = new Date().toISOString().slice(0, 10)
        res.set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        res.set('Content-Disposition', `attachment; filename="rental_quotes_${ts}.xlsx"`)
        res.send(buf)
    } catch (e) { next(e) }
})

// ============== 12. 仓库5状态切换（仓库员专属） ==============
const RENTAL_STATUS = ['available', 'leased', 'repairing', 'pending', 'recycled']
router.put('/:productId/rental-status', async (req, res, next) => {
    try {
        const pid = Number(req.params.productId)
        const { new_status, reason, note } = req.body || {}
        const role = req.user?.role
        if (!['warehouse', 'admin', 'boss', '仓库', '仓库员'].includes(role)) {
            return res.status(403).json({ ok: false, error: '仅仓库员/超管可改状态' })
        }
        if (!RENTAL_STATUS.includes(new_status)) {
            return res.status(400).json({ ok: false, error: '非法状态' })
        }
        const conn = await pool.getConnection()
        try {
            await conn.beginTransaction()
            const [before] = await conn.query(`SELECT rental_status FROM products WHERE id = ?`, [pid])
            if (!before.length) { await conn.rollback(); return res.status(404).json({ ok: false, error: '产品不存在' }) }
            const old = before[0].rental_status || 'available'
            await conn.query(`UPDATE products SET rental_status = ?, repair_note = ? WHERE id = ?`, [new_status, note || null, pid])
            await conn.query(
                `INSERT INTO rental_stock_status_logs (product_id, old_status, new_status, reason, note, operator_id, operator_role)
                 VALUES (?, ?, ?, ?, ?, ?, ?)`,
                [pid, old, new_status, reason || null, note || null, req.user?.id, role]
            )
            await conn.commit()
            res.json({ ok: true, data: { old, new: new_status } })
        } catch (e) { await conn.rollback(); throw e } finally { conn.release() }
    } catch (e) { next(e) }
})

// ============== 13. 列表查询 + 详情（含items+审计日志） ==============
router.get('/detail/:id', async (req, res, next) => {
    try {
        const id = Number(req.params.id)
        const [orders] = await pool.query(`SELECT q.*, u.name AS staff_name FROM quote_orders q LEFT JOIN users u ON u.id = q.created_by WHERE q.id = ?`, [id])
        if (!orders.length) return res.status(404).json({ ok: false, error: '订单不存在' })
        const [items] = await pool.query(`SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order, id`, [id])
        const [logs] = await pool.query(`SELECT * FROM quote_audit_logs WHERE quote_id = ? ORDER BY id DESC`, [id])
        const [locks] = await pool.query(`SELECT rsl.*, p.name AS product_name FROM rental_stock_locks rsl LEFT JOIN products p ON p.id = rsl.product_id WHERE rsl.quote_id = ?`, [id])
        res.json({ ok: true, data: { ...orders[0], items, logs, locks } })
    } catch (e) { next(e) }
})

// ============== 14. 询价单取消（客户/操作员） ==============
router.post('/:id/cancel', async (req, res, next) => {
    try {
        const id = Number(req.params.id)
        const [orders] = await pool.query(`SELECT status FROM quote_orders WHERE id = ?`, [id])
        if (!orders.length) return res.status(404).json({ ok: false, error: '订单不存在' })
        if (['completed', 'archived', 'cancelled'].includes(orders[0].status)) {
            return res.status(400).json({ ok: false, error: '当前状态不可取消' })
        }
        await pool.query(`UPDATE quote_orders SET status = 'cancelled' WHERE id = ?`, [id])
        await pool.query(`UPDATE rental_stock_locks SET status = 'released' WHERE quote_id = ? AND status = 'locked'`, [id])
        const conn = await pool.getConnection()
        try {
            await conn.query(
                `INSERT INTO quote_audit_logs (quote_id, user_id, user_role, action, before_value, after_value, diff_summary, ip_address)
                 VALUES (?, ?, ?, 'status_changed', ?, ?, '订单取消', ?)`,
                [id, req.user?.id, req.user?.role, JSON.stringify({ status: orders[0].status }), JSON.stringify({ status: 'cancelled' }), req.ip]
            )
        } finally { conn.release() }
        res.json({ ok: true })
    } catch (e) { next(e) }
})

// 仓库日志(留痕查询)
router.get('/inv-log/:product_id', async (req, res, next) => {
    try {
        const pid = parseInt(req.params.product_id)
        const [rows] = await pool.query(
            `SELECT id, product_id, action, before_value, after_value, diff_summary, operator_name, created_at
             FROM rental_stock_inventory_logs
             WHERE product_id = ?
             ORDER BY id DESC LIMIT 50`, [pid])
        res.json({ ok: true, data: { logs: rows } })
    } catch (e) { next(e) }
})

// 状态变更日志(留痕查询)
router.get('/status-log/:quote_id', async (req, res, next) => {
    try {
        const qid = parseInt(req.params.quote_id)
        const [rows] = await pool.query(
            `SELECT id, quote_id, action, from_status, to_status, diff_summary, operator_name, created_at
             FROM rental_status_logs
             WHERE quote_id = ?
             ORDER BY id DESC LIMIT 50`, [qid])
        res.json({ ok: true, data: { logs: rows } })
    } catch (e) { next(e) }
})

export default router