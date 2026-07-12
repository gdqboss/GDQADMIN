/**
 * Rental 公开端点（游客无需登录可调用，登录用户 token 可选携带）
 *
 * 路由：/api/rental-public/*
 *   - GET  /products         客户端浏览设备（按 customer_type 出对应价）
 *   - POST /inquiry          客户端提交询价（无需 token）
 *
 * 价格策略：
 *   - 游客（无 token） → 强制返回政府含税价（最高档），不暴露 peer 同行价
 *   - 登录用户（带 token） → 根据 users.customer_type 返回真实档位（peer/biz/gov/normal）
 *   - token 失败/无效 → 降级为游客策略，不报错
 */
import express from 'express'
import jwt from 'jsonwebtoken'
import { pool } from '../db/connection.js'

const router = express.Router()

// 软解析 token：登录用户能看到真实档位，匿名游客返回 null（按 gov 锁价）
function softResolveUser(req) {
  try {
    const h = req.headers.authorization
    if (!h || !h.startsWith('Bearer ')) return null
    const decoded = jwt.verify(h.split(' ')[1], process.env.JWT_SECRET)
    if (!decoded?.id) return null
    return { id: decoded.id, customer_type: decoded.customer_type || null, member_level: decoded.member_level || 1, member_label: decoded.member_label || null, user_type: decoded.user_type || 'staff' }
  } catch { return null }
}

// 按用户身份选 customer_type（不传 gov，匿名 → gov）
function pickCustomerType(softUser) {
  if (!softUser || softUser.user_type === 'staff') return 'gov'  // 员工也用 gov（最高价，安全）
  const ct = softUser.customer_type
  // 顾客：normal/biz/peer/gov 都允许
  if (['gov', 'biz', 'peer', 'normal'].includes(ct)) return ct
  return 'gov'  // 兜底
}

// ============== 1. 客户端浏览设备 ==============
// 价格策略:
//   - 游客（无 token）→ 强制 gov（最高档），不暴露 peer 同行价
//   - 登录用户（带 token）→ 按 users.customer_type 返回真实档位
router.get('/products', async (req, res, next) => {
    try {
        const softUser = softResolveUser(req)
        const customer_type = pickCustomerType(softUser)
        const isLocked = !softUser  // 游客价被锁定为 gov
        const conds = [`p.publish_status = 'published'`, `(p.rental_status IS NULL OR p.rental_status = 'available')`]
        const args = [customer_type]
        const { category } = req.query
        if (category) { conds.push('p.category = ?'); args.push(category) }
        // 三段位价格:your_price(自身档) + anchor_price(最高档 gov 作锚定) + base_price(员工采购价 sale_price)
        const [rows] = await pool.query(
            `SELECT p.id, p.sku, p.name, p.category, p.spec, p.unit, p.image_main, p.image_url_2, p.image_url_3,
                    COALESCE(cpt.price, p.sale_price) AS effective_price,
                    COALESCE(cpt.unit_day_price, p.sale_price) AS unit_day_price,
                    COALESCE(cpt.tax_rate, 0.06) AS tax_rate,
                    p.sale_price AS base_price,
                    p.stock,
                    (p.stock - COALESCE((SELECT SUM(qty) FROM rental_stock_locks WHERE product_id = p.id AND status = 'locked' AND NOT (lock_end < NOW())), 0)) AS available_qty
             FROM products p
             LEFT JOIN customer_pricing_tiers cpt ON cpt.product_id = p.id AND cpt.customer_type = ? AND cpt.is_active = 1
             WHERE ${conds.join(' AND ')}
             ORDER BY p.category, p.id
             LIMIT 500`, args)
        // 再查 gov 档作 anchor_price (最高档作锚定)
        const productIds = rows.map(r => r.id)
        let govPriceMap = {}
        if (productIds.length) {
          const [govRows] = await pool.query(
            `SELECT product_id, price, unit_day_price FROM customer_pricing_tiers
             WHERE product_id IN (?) AND customer_type = 'gov' AND is_active = 1`, [productIds])
          for (const g of govRows) govPriceMap[g.product_id] = Number(g.price || g.unit_day_price || 0)
          console.log('[rental-public/products] gov-rows:', govRows.length, 'of', productIds.length, 'first:', govRows[0])
        }
        // 装入三段位
        const enriched = rows.map(r => {
          const your = Number(r.effective_price || 0)
          const anchor = govPriceMap[r.id] || Number(r.base_price || 0) * 1.3
          return {
            ...r,
            your_price: your,
            anchor_price: anchor,
            base_price: Number(r.base_price || 0)
          }
        })
        res.json({
            ok: true,
            data: enriched,
            customer_type,
            price_locked: isLocked,
            locked_reason: isLocked ? '请登录后查看您的专属价格' : null,
            user_type: softUser?.user_type || 'guest',
            member_level: softUser?.member_level || 0,
            member_label: softUser?.member_label || null
        })
    } catch (e) { next(e) }
})

// ============== 2. 客户端提交询价（无需 token,登录后自动 bind user_id） ==============
// 价格策略:
//   - 游客 → 强制 gov（最高档）算价 + 不绑 user_id
//   - 登录用户 → 按 users.customer_type 算价 + 绑 user_id 到订单
router.post('/inquiry', async (req, res, next) => {
    const conn = await pool.getConnection()
    try {
        await conn.beginTransaction()
        const {
            customer_name, customer_phone,
            activity_type, activity_time_start, activity_time_end,
            setup_time, teardown_time, activity_location, remark,
            items = []
        } = req.body || {}

        const softUser = softResolveUser(req)
        const customer_type = pickCustomerType(softUser)
        const loggedInUserId = softUser?.id || null
        // 拿登录用户角色用于审计
        let createdByRole = 'guest'
        if (loggedInUserId) {
          try {
            const [ur] = await conn.query('SELECT role, user_type FROM users WHERE id = ?', [loggedInUserId])
            if (ur[0]) createdByRole = ur[0].user_type === 'customer' ? `customer:${ur[0].role}` : ur[0].role
          } catch {}
        }

        if (!customer_name || !customer_phone) {
            await conn.rollback()
            return res.status(400).json({ ok: false, error: '请填姓名和电话' })
        }
        if (!activity_time_start || !activity_time_end) {
            await conn.rollback()
            return res.status(400).json({ ok: false, error: '请填活动时间' })
        }
        if (!Array.isArray(items) || !items.length) {
            await conn.rollback()
            return res.status(400).json({ ok: false, error: '至少选 1 件物料' })
        }

        // 库存冲突检查
        for (const it of items) {
            if (!it.product_id) continue
            const [stocks] = await conn.query(`SELECT stock, name FROM products WHERE id = ? AND status = 'active'`, [it.product_id])
            if (!stocks.length) {
                await conn.rollback()
                return res.status(400).json({ ok: false, error: `物料 #${it.product_id} 已下架` })
            }
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

        const orderNo = 'INQ' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + Math.floor(Math.random() * 9999).toString().padStart(4, '0')
        let subtotal = 0
        for (const it of items) {
            const days = Number(it.days || 1)
            const qty = Number(it.qty || 1)
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
             loggedInUserId, createdByRole]
        )
        const quoteId = r.insertId

        for (let i = 0; i < items.length; i++) {
            const it = items[i]
            const days = Number(it.days || 1)
            const qty = Number(it.qty || 1)
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
            `INSERT INTO quote_audit_logs (quote_id, user_id, user_role, action, diff_summary, ip_address)
             VALUES (?, ?, ?, 'created', ?, ?)`,
            [quoteId, loggedInUserId, createdByRole,
             loggedInUserId ? `登录用户(${createdByRole})询价下单, customer_type=${customer_type}` : '游客匿名询价下单,按gov锁价',
             req.ip]
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

export default router
