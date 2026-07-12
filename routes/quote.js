/**
 * 传媒广告物料租赁报价下单系统 - 报价引擎
 *
 * 核心功能:
 *   - 三客户类型价格体系 (gov/biz/peer)
 *   - 自动双报价 (税前/税后)
 *   - 多级折扣权限 (按 req.user.role 校验)
 *   - 报价格式模板 (3套)
 *   - 全程审计留痕
 *
 * 模块路由: GET/POST /api/quote/* (全部需要 auth)
 */
import express from 'express'
import { pool } from '../db/connection.js'

const router = express.Router()

// ============== 折扣权限配置（按需求"以后去角色管理配"先写默认值） ==============
const DISCOUNT_TIERS = {
    member:     { min: 1.00, max: 1.00 }, // 无权改价
    supervisor: { min: 0.90, max: 1.00 }, // 主管：最多9折
    boss:       { min: 0.50, max: 1.00 }, // 总经理：最低5折
    admin:      { min: 0.00, max: 1.00 }, // 超管：无下限
    warehouse:  { min: 1.00, max: 1.00 }, // 仓库员：无权
    shopkeeper: { min: 1.00, max: 1.00 },
    experience: { min: 1.00, max: 1.00 }
}

const CUSTOMER_TYPE_LABELS = {
    gov:  '政府客户',
    biz:  '个人商业客户',
    peer: '同行合作客户'
}

const ACTIVITY_TYPE_LABELS = {
    gov_event:  '政府活动',
    biz_event:  '商业活动',
    wedding:    '婚礼',
    party:      '晚会',
    exhibition: '展会'
}

const STATUS_LABELS = {
    draft: '草稿',
    pending_approval: '待审核',
    quoted: '已报价',
    confirmed: '客户已确认',
    signed: '已签约',
    completed: '已完工',
    archived: '已归档',
    cancelled: '已取消'
}

// ============== 工具函数 ==============

function genOrderNo() {
    const d = new Date()
    const ymd = `${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}${String(d.getDate()).padStart(2, '0')}`
    const rand = Math.floor(Math.random() * 9999).toString().padStart(4, '0')
    return `QT${ymd}${rand}`
}

async function logAudit(conn, quoteId, user, action, before, after, diff, ip) {
    await conn.query(
        `INSERT INTO quote_audit_logs (quote_id, user_id, user_name, user_role, action, before_value, after_value, diff_summary, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
            quoteId, user?.id || null, user?.name || null, user?.role || null,
            action,
            before ? JSON.stringify(before) : null,
            after  ? JSON.stringify(after)  : null,
            diff   || null, ip || null
        ]
    )
}

function getPriceForCustomerType(priceMap, customerType) {
    // priceMap = { gov, biz, peer, sale_price_fallback }
    if (!priceMap) return 0
    return Number(priceMap[customerType] || priceMap.sale_price || priceMap.biz || 0)
}

// ============== 路由 ==============

// 1) 配置信息（前端下拉用）
router.get('/config', async (req, res, next) => {
    try {
        res.json({
            ok: true,
            data: {
                customer_types: Object.entries(CUSTOMER_TYPE_LABELS).map(([k, v]) => ({ value: k, label: v })),
                activity_types: Object.entries(ACTIVITY_TYPE_LABELS).map(([k, v]) => ({ value: k, label: v })),
                status_labels:  Object.entries(STATUS_LABELS).map(([k, v]) => ({ value: k, label: v })),
                discount_tiers: DISCOUNT_TIERS,
                templates: [
                    { value: 'gov',  label: '政府正式版（红头规范）' },
                    { value: 'biz',  label: '商业明细版' },
                    { value: 'peer', label: '同行极简版' }
                ]
            }
        })
    } catch (e) { next(e) }
})

// 2) 报价单列表（按 customer_type 板块过滤）
router.get('/list', async (req, res, next) => {
    try {
        const { customer_type, status, page = 1, page_size = 20, keyword } = req.query
        const offset = (Number(page) - 1) * Number(page_size)
        const conds = ['1=1']
        const args  = []
        if (customer_type) { conds.push('q.customer_type = ?'); args.push(customer_type) }
        if (status)        { conds.push('q.status = ?');        args.push(status) }
        if (keyword)       { conds.push('(q.order_no LIKE ? OR q.customer_name LIKE ?)'); args.push(`%${keyword}%`, `%${keyword}%`) }

        const where = conds.join(' AND ')
        const [list] = await pool.query(
            `SELECT q.*, u.name AS creator_name
             FROM quote_orders q
             LEFT JOIN users u ON q.created_by = u.id
             WHERE ${where}
             ORDER BY q.id DESC
             LIMIT ? OFFSET ?`,
            [...args, Number(page_size), offset]
        )
        const [cnt] = await pool.query(`SELECT COUNT(*) AS total FROM quote_orders q WHERE ${where}`, args)
        res.json({ ok: true, data: { list, total: cnt[0].total, page: Number(page), page_size: Number(page_size) } })
    } catch (e) { next(e) }
})

// 3) 报价单详情（含明细 + 审计）
router.get('/:id', async (req, res, next) => {
    try {
        const id = Number(req.params.id)
        const [orders] = await pool.query(`SELECT q.*, u.name AS creator_name, r.name AS reviewer_name
            FROM quote_orders q
            LEFT JOIN users u ON q.created_by = u.id
            LEFT JOIN users r ON q.reviewed_by = r.id
            WHERE q.id = ?`, [id])
        if (!orders.length) return res.status(404).json({ ok: false, error: '报价单不存在' })

        const [items] = await pool.query(`SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order, id`, [id])
        const [logs]  = await pool.query(`SELECT * FROM quote_audit_logs WHERE quote_id = ? ORDER BY id DESC`, [id])

        res.json({ ok: true, data: { order: orders[0], items, logs } })
    } catch (e) { next(e) }
})

// 4) 创建报价单
router.post('/create', async (req, res, next) => {
    const conn = await pool.getConnection()
    try {
        await conn.beginTransaction()
        const user = req.user
        const {
            customer_type, customer_name, customer_phone, customer_id,
            activity_type, activity_time_start, activity_time_end,
            setup_time, teardown_time, activity_location, remark,
            items = [], template_type, discount_rate = 1.0
        } = req.body || {}

        if (!customer_type) { await conn.rollback(); return res.status(400).json({ ok: false, error: '请选择客户类型' }) }
        if (!Array.isArray(items) || !items.length) { await conn.rollback(); return res.status(400).json({ ok: false, error: '报价明细不能为空' }) }

        // 校验折扣权限
        const role = user?.role || 'member'
        const tier = DISCOUNT_TIERS[role] || DISCOUNT_TIERS.member
        const dr = Number(discount_rate)
        if (dr < tier.min || dr > tier.max) {
            await conn.rollback()
            return res.status(403).json({ ok: false, error: `${role} 角色折扣权限 ${tier.min}-${tier.max}，你提交了 ${dr}` })
        }

        // 计算金额
        let subtotal = 0
        const enrichedItems = []
        for (let i = 0; i < items.length; i++) {
            const it = items[i]
            const days = Number(it.days || 1)
            const qty  = Number(it.qty  || 1)
            // 价格优先级: 提交过来的 unit_price > customer_pricing_tiers 查 > products.sale_price
            let unitPrice = Number(it.unit_price || 0)
            if (!unitPrice && it.product_id) {
                const [tierRows] = await conn.query(
                    `SELECT price, tax_rate, unit_day_price FROM customer_pricing_tiers
                     WHERE product_id = ? AND customer_type = ? AND is_active = 1`,
                    [it.product_id, customer_type]
                )
                if (tierRows.length) {
                    unitPrice = Number(tierRows[0].unit_day_price || tierRows[0].price || 0)
                } else {
                    const [p] = await conn.query(`SELECT sale_price FROM products WHERE id = ?`, [it.product_id])
                    unitPrice = Number(p[0]?.sale_price || 0)
                }
            }
            const lineSub = +(unitPrice * qty * days).toFixed(2)
            subtotal += lineSub
            enrichedItems.push({
                product_id:   it.product_id   || null,
                product_sku:  it.product_sku  || null,
                product_name: it.product_name || '未命名物料',
                category:     it.category     || null,
                item_type:    it.item_type    || 'equipment',
                qty, days,
                unit_price:   unitPrice,
                unit_day_price: it.unit_day_price || null,
                subtotal:     lineSub,
                spec:         it.spec         ? JSON.stringify(it.spec) : null,
                image_url:    it.image_url    || null,
                sort_order:   i
            })
        }

        const discount_amount  = +(subtotal * (1 - dr)).toFixed(2)
        const pre_tax_total    = +subtotal.toFixed(2)
        // 税率从客户类型定价表取，否则用请求里的，再否则 0.06
        let taxRate = 0.06
        if (items[0]?.product_id) {
            const [t] = await conn.query(
                `SELECT tax_rate FROM customer_pricing_tiers WHERE product_id = ? AND customer_type = ? AND is_active = 1 LIMIT 1`,
                [items[0].product_id, customer_type]
            )
            if (t[0]?.tax_rate != null) taxRate = Number(t[0].tax_rate)
        }
        const discountedTotal = +(pre_tax_total * dr).toFixed(2)
        const tax_amount      = +(discountedTotal * taxRate).toFixed(2)
        const post_tax_total  = +(discountedTotal + tax_amount).toFixed(2)

        const orderNo = genOrderNo()
        const [r] = await conn.query(
            `INSERT INTO quote_orders
             (order_no, customer_id, customer_name, customer_type, customer_phone,
              activity_type, activity_time_start, activity_time_end,
              setup_time, teardown_time, activity_location, remark,
              subtotal, discount_rate, discount_amount, pre_tax_total,
              tax_rate, tax_amount, post_tax_total, final_amount,
              template_type, status, created_by, created_by_role)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?)`,
            [
                orderNo, customer_id || null, customer_name || null, customer_type, customer_phone || null,
                activity_type || null, activity_time_start || null, activity_time_end || null,
                setup_time || null, teardown_time || null, activity_location || null, remark || null,
                pre_tax_total, dr, discount_amount, pre_tax_total,
                taxRate, tax_amount, post_tax_total, discountedTotal,
                template_type || customer_type,
                user?.id || null, role
            ]
        )
        const quoteId = r.insertId

        // 插入明细
        for (const it of enrichedItems) {
            await conn.query(
                `INSERT INTO quote_items (quote_id, product_id, product_sku, product_name, category, item_type,
                 qty, days, unit_price, unit_day_price, subtotal, spec, image_url, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
                [quoteId, it.product_id, it.product_sku, it.product_name, it.category, it.item_type,
                 it.qty, it.days, it.unit_price, it.unit_day_price, it.subtotal, it.spec, it.image_url, it.sort_order]
            )
        }

        await logAudit(conn, quoteId, user, 'created', null, { order_no: orderNo, total: pre_tax_total }, '创建报价单', req.ip)
        await conn.commit()
        res.json({ ok: true, data: { id: quoteId, order_no: orderNo } })
    } catch (e) {
        await conn.rollback()
        next(e)
    } finally {
        conn.release()
    }
})

// 5) 更新报价单（草稿/待审核状态可改）
router.put('/:id', async (req, res, next) => {
    const conn = await pool.getConnection()
    try {
        await conn.beginTransaction()
        const id = Number(req.params.id)
        const user = req.user
        const [orders] = await conn.query(`SELECT * FROM quote_orders WHERE id = ?`, [id])
        if (!orders.length) { await conn.rollback(); return res.status(404).json({ ok: false, error: '报价单不存在' }) }
        const before = orders[0]
        if (!['draft', 'pending_approval'].includes(before.status)) {
            await conn.rollback()
            return res.status(400).json({ ok: false, error: `当前状态 ${before.status} 不可修改` })
        }

        const role = user?.role || 'member'
        const tier = DISCOUNT_TIERS[role] || DISCOUNT_TIERS.member
        const { discount_rate, customer_name, customer_phone, activity_location, remark, status, items } = req.body || {}
        if (discount_rate != null) {
            const dr = Number(discount_rate)
            if (dr < tier.min || dr > tier.max) {
                await conn.rollback()
                return res.status(403).json({ ok: false, error: `${role} 折扣权限 ${tier.min}-${tier.max}` })
            }
            const diffAmt = +(before.pre_tax_total * (1 - dr)).toFixed(2)
            const final   = +(before.pre_tax_total * dr).toFixed(2)
            const taxAmt  = +(final * Number(before.tax_rate)).toFixed(2)
            await conn.query(
                `UPDATE quote_orders SET discount_rate=?, discount_amount=?, final_amount=?, tax_amount=?, post_tax_total=? WHERE id=?`,
                [dr, diffAmt, final, taxAmt, +(final + taxAmt).toFixed(2), id]
            )
            await logAudit(conn, id, user, 'discount_changed', { discount_rate: before.discount_rate }, { discount_rate: dr }, `折扣 ${before.discount_rate} → ${dr}`, req.ip)
        }
        // 基础字段
        const upd = []
        const args = []
        if (customer_name)     { upd.push('customer_name = ?');     args.push(customer_name) }
        if (customer_phone)    { upd.push('customer_phone = ?');    args.push(customer_phone) }
        if (activity_location) { upd.push('activity_location = ?'); args.push(activity_location) }
        if (remark != null)    { upd.push('remark = ?');            args.push(remark) }
        if (status)            { upd.push('status = ?');            args.push(status) }
        if (upd.length) {
            args.push(id)
            await conn.query(`UPDATE quote_orders SET ${upd.join(', ')} WHERE id = ?`, args)
        }

        // 重算明细（如有 items 重新提交）
        if (Array.isArray(items) && items.length) {
            await conn.query(`DELETE FROM quote_items WHERE quote_id = ?`, [id])
            let subtotal = 0
            for (let i = 0; i < items.length; i++) {
                const it = items[i]
                const days = Number(it.days || 1)
                const qty  = Number(it.qty  || 1)
                const unitPrice = Number(it.unit_price || 0)
                const lineSub = +(unitPrice * qty * days).toFixed(2)
                subtotal += lineSub
                await conn.query(
                    `INSERT INTO quote_items (quote_id, product_id, product_sku, product_name, category, item_type,
                     qty, days, unit_price, unit_day_price, subtotal, spec, image_url, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
                    [id, it.product_id || null, it.product_sku || null, it.product_name, it.category || null, it.item_type || 'equipment',
                     qty, days, unitPrice, it.unit_day_price || null, lineSub,
                     it.spec ? JSON.stringify(it.spec) : null, it.image_url || null, i]
                )
            }
            const dr = discount_rate != null ? Number(discount_rate) : Number(before.discount_rate)
            const taxRate = Number(before.tax_rate)
            const final   = +(subtotal * dr).toFixed(2)
            const taxAmt  = +(final * taxRate).toFixed(2)
            await conn.query(
                `UPDATE quote_orders SET subtotal=?, pre_tax_total=?, discount_amount=?, final_amount=?, tax_amount=?, post_tax_total=? WHERE id=?`,
                [subtotal, subtotal, +(subtotal * (1 - dr)).toFixed(2), final, taxAmt, +(final + taxAmt).toFixed(2), id]
            )
        }

        await logAudit(conn, id, user, 'updated', before, req.body, '更新报价单', req.ip)
        await conn.commit()
        res.json({ ok: true })
    } catch (e) {
        await conn.rollback()
        next(e)
    } finally {
        conn.release()
    }
})

// 6) 提交审核
router.post('/:id/submit-review', async (req, res, next) => {
    try {
        const id = Number(req.params.id)
        const user = req.user
        const [orders] = await pool.query(`SELECT * FROM quote_orders WHERE id = ?`, [id])
        if (!orders.length) return res.status(404).json({ ok: false, error: '报价单不存在' })
        if (orders[0].status !== 'draft') return res.status(400).json({ ok: false, error: '仅草稿状态可提交审核' })
        await pool.query(`UPDATE quote_orders SET status = 'pending_approval' WHERE id = ?`, [id])
        const conn = await pool.getConnection()
        try {
            await logAudit(conn, id, user, 'status_changed', { status: 'draft' }, { status: 'pending_approval' }, '提交审核', req.ip)
        } finally { conn.release() }
        res.json({ ok: true })
    } catch (e) { next(e) }
})

// 7) 审核通过 → 已报价
router.post('/:id/approve', async (req, res, next) => {
    try {
        const id = Number(req.params.id)
        const user = req.user
        const role = user?.role || 'member'
        if (!['supervisor', 'boss', 'admin'].includes(role)) {
            return res.status(403).json({ ok: false, error: `${role} 无审核权` })
        }
        const { note } = req.body || {}
        await pool.query(
            `UPDATE quote_orders SET status = 'quoted', reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?`,
            [user.id, note || null, id]
        )
        const conn = await pool.getConnection()
        try {
            await logAudit(conn, id, user, 'status_changed', { status: 'pending_approval' }, { status: 'quoted', note: note || '' }, '审核通过', req.ip)
        } finally { conn.release() }
        res.json({ ok: true })
    } catch (e) { next(e) }
})

// 8) 状态推进（quoted → confirmed → signed → completed → archived）
router.post('/:id/status', async (req, res, next) => {
    try {
        const id = Number(req.params.id)
        const user = req.user
        const { status } = req.body || {}
        const allowed = ['confirmed', 'signed', 'completed', 'archived', 'cancelled']
        if (!allowed.includes(status)) return res.status(400).json({ ok: false, error: '非法状态' })

        const [orders] = await pool.query(`SELECT status FROM quote_orders WHERE id = ?`, [id])
        if (!orders.length) return res.status(404).json({ ok: false, error: '报价单不存在' })
        const before = orders[0].status

        // 状态流转合法性
        const flow = {
            quoted: ['confirmed', 'cancelled'],
            confirmed: ['signed', 'cancelled'],
            signed: ['completed'],
            completed: ['archived']
        }
        if (flow[before] && !flow[before].includes(status)) {
            return res.status(400).json({ ok: false, error: `${before} 不能直接 → ${status}` })
        }

        await pool.query(`UPDATE quote_orders SET status = ? WHERE id = ?`, [status, id])

        // 已签约 → 锁库存
        if (status === 'signed') {
            const [items] = await pool.query(`SELECT * FROM quote_items WHERE quote_id = ?`, [id])
            for (const it of items) {
                if (it.product_id) {
                    await pool.query(
                        `INSERT INTO rental_stock_locks (product_id, qty, lock_start, lock_end, quote_id, status, created_by)
                         VALUES (?, ?, (SELECT activity_time_start FROM quote_orders WHERE id=?),
                                 (SELECT activity_time_end FROM quote_orders WHERE id=?),
                                 ?, 'locked', ?)`,
                        [it.product_id, it.qty, id, id, id, user.id]
                    )
                }
            }
        }

        const conn = await pool.getConnection()
        try {
            await logAudit(conn, id, user, 'status_changed', { status: before }, { status }, `状态 ${before} → ${status}`, req.ip)
        } finally { conn.release() }
        res.json({ ok: true })
    } catch (e) { next(e) }
})

// 9) 库存冲突检查
router.get('/check-availability', async (req, res, next) => {
    try {
        const { product_id, start, end, qty = 1 } = req.query
        if (!product_id || !start || !end) return res.status(400).json({ ok: false, error: '缺少参数' })

        const [stocks] = await pool.query(
            `SELECT product_id, name, stock FROM products WHERE id = ?`, [product_id]
        )
        if (!stocks.length) return res.status(404).json({ ok: false, error: '物料不存在' })

        const [locks] = await pool.query(
            `SELECT COALESCE(SUM(qty), 0) AS locked_qty FROM rental_stock_locks
             WHERE product_id = ? AND status = 'locked'
               AND NOT (lock_end < ? OR lock_start > ?)`,
            [product_id, start, end]
        )
        const locked = Number(locks[0].locked_qty || 0)
        const total  = Number(stocks[0].stock || 0)
        const available = total - locked
        res.json({
            ok: true,
            data: {
                product_id, total_stock: total, locked_qty: locked,
                available_qty: available, requested: Number(qty),
                available: available >= Number(qty)
            }
        })
    } catch (e) { next(e) }
})

// 10) 报价单导出 Excel 台账
router.get('/export/excel', async (req, res, next) => {
    try {
        const { customer_type, status, start_date, end_date } = req.query
        const conds = ['1=1']
        const args  = []
        if (customer_type) { conds.push('q.customer_type = ?'); args.push(customer_type) }
        if (status)        { conds.push('q.status = ?');        args.push(status) }
        if (start_date)    { conds.push('q.created_at >= ?');   args.push(start_date) }
        if (end_date)      { conds.push('q.created_at <= ?');   args.push(end_date) }
        const where = conds.join(' AND ')

        const [rows] = await pool.query(
            `SELECT q.order_no, q.customer_name, q.customer_type, q.customer_phone,
                    q.activity_location, q.activity_time_start, q.activity_time_end,
                    q.pre_tax_total, q.discount_rate, q.discount_amount, q.tax_rate,
                    q.tax_amount, q.post_tax_total, q.final_amount, q.status,
                    q.created_at, u.name AS creator_name
             FROM quote_orders q
             LEFT JOIN users u ON q.created_by = u.id
             WHERE ${where}
             ORDER BY q.id DESC`,
            args
        )

        // 简易 CSV (Excel 友好) — 用 BOM 头防乱码
        const headers = ['报价单号', '客户名称', '客户类型', '联系电话', '活动地点', '活动开始', '活动结束',
                         '税前金额', '折扣率', '折扣金额', '税率', '税额', '税后金额', '成交价', '状态', '创建时间', '操作员']
        const typeMap = { gov: '政府', biz: '个人', peer: '同行' }
        const statusMap = { draft: '草稿', pending_approval: '待审核', quoted: '已报价', confirmed: '已确认', signed: '已签约', completed: '已完工', archived: '已归档', cancelled: '已取消' }
        const lines = [headers.join(',')]
        for (const r of rows) {
            lines.push([
                r.order_no, r.customer_name, typeMap[r.customer_type] || r.customer_type, r.customer_phone,
                r.activity_location, r.activity_time_start, r.activity_time_end,
                r.pre_tax_total, r.discount_rate, r.discount_amount, r.tax_rate,
                r.tax_amount, r.post_tax_total, r.final_amount,
                statusMap[r.status] || r.status, r.created_at, r.creator_name
            ].map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(','))
        }
        const csv = '\ufeff' + lines.join('\n')
        const filename = `报价台账_${new Date().toISOString().slice(0, 10)}.csv`
        res.setHeader('Content-Type', 'text/csv; charset=utf-8')
        res.setHeader('Content-Disposition', `attachment; filename="${filename}"`)
        res.send(csv)

        // 留痕
        const conn = await pool.getConnection()
        try { await logAudit(conn, 0, req.user, 'exported', null, { filter: req.query, count: rows.length }, `导出Excel 共${rows.length}条`, req.ip) } finally { conn.release() }
    } catch (e) { next(e) }
})

// 11) 客户定价表 CRUD（运营后台用）
router.get('/pricing-tiers', async (req, res, next) => {
    try {
        const { customer_type, product_id } = req.query
        const conds = ['1=1']
        const args = []
        if (customer_type) { conds.push('cpt.customer_type = ?'); args.push(customer_type) }
        if (product_id)    { conds.push('cpt.product_id = ?');    args.push(product_id) }
        const [rows] = await pool.query(
            `SELECT cpt.*, p.name AS product_name, p.sku, p.category, p.sale_price, p.stock
             FROM customer_pricing_tiers cpt
             LEFT JOIN products p ON cpt.product_id = p.id
             WHERE ${conds.join(' AND ')}
             ORDER BY p.category, cpt.product_id`,
            args
        )
        res.json({ ok: true, data: rows })
    } catch (e) { next(e) }
})

router.post('/pricing-tiers/upsert', async (req, res, next) => {
    try {
        const { product_id, customer_type, price, tax_rate, unit_day_price, is_active = 1 } = req.body || {}
        if (!product_id || !customer_type) return res.status(400).json({ ok: false, error: '缺少 product_id 或 customer_type' })
        await pool.query(
            `INSERT INTO customer_pricing_tiers (product_id, customer_type, price, tax_rate, unit_day_price, is_active)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE price=VALUES(price), tax_rate=VALUES(tax_rate),
                                     unit_day_price=VALUES(unit_day_price), is_active=VALUES(is_active)`,
            [product_id, customer_type, price || 0, tax_rate || 0.06, unit_day_price || null, is_active ? 1 : 0]
        )
        res.json({ ok: true })
    } catch (e) { next(e) }
})

export default router