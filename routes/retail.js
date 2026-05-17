import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import * as XLSX from 'xlsx'

const router = Router()

// GET /api/retail-records — 分页列表，支持筛选
router.get('/', async (req, res, next) => {
  try {
    const { channel, product_id, category_id, date_start, date_end, keyword } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE 1=1'
    const params = [], countParams = []

    if (channel) { where += ' AND r.channel = ?'; params.push(channel); countParams.push(channel) }
    if (product_id) { where += ' AND r.product_id = ?'; params.push(product_id); countParams.push(product_id) }
    if (category_id) { where += ' AND p.category_id = ?'; params.push(category_id); countParams.push(category_id) }
    if (date_start) { where += ' AND DATE(r.created_at) >= ?'; params.push(date_start); countParams.push(date_start) }
    if (date_end) { where += ' AND DATE(r.created_at) <= ?'; params.push(date_end); countParams.push(date_end) }
    if (keyword) { where += ' AND (p.name LIKE ? OR q.code LIKE ? OR r.buyer_name LIKE ?)'; const kw = `%${keyword}%`; params.push(kw, kw, kw); countParams.push(kw, kw, kw) }

    const sql = `
      SELECT r.*,
        p.name as product_name, p.spec as product_spec, p.image_main,
        q.code as qr_code, q.service_user_id,
        u.name as sold_by_name,
        h.name as h5_user_name, h.phone as h5_user_phone,
        svc.name as service_user_name, svc.phone as service_user_phone
      FROM retail_records r
      LEFT JOIN products p ON r.product_id = p.id
      LEFT JOIN qrcodes q ON r.qrcode_id = q.id
      LEFT JOIN users u ON r.sold_by = u.id
      LEFT JOIN h5_users h ON r.h5_user_id = h.id
      LEFT JOIN users svc ON q.service_user_id = svc.id
      ${where}
      ORDER BY r.created_at DESC
      LIMIT ? OFFSET ?
    `
    const countSql = `SELECT COUNT(*) as total FROM retail_records r LEFT JOIN products p ON r.product_id = p.id LEFT JOIN qrcodes q ON r.qrcode_id = q.id ${where}`

    params.push(size, (page - 1) * size)
    const [[{ total }]] = await pool.query(countSql, countParams)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/retail-records — 创建零售记录（销售/赠送）
router.post('/', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const allowedRoles = ['salesperson', 'cashier', 'admin', 'superadmin', 'manager', 'operator']
    if (!allowedRoles.includes(req.user.role)) {
      return res.status(403).json({ code: 403, message: '无权限创建零售记录' })
    }

    const { qrcode_id, product_id, channel = 'manual', type = 'sale', buyer_name, buyer_phone, sale_price, note, approver_id } = req.body
    if (!product_id) return res.status(400).json({ code: 400, message: '商品ID必填' })
    if (!['sale', 'gift'].includes(type)) return res.status(400).json({ code: 400, message: 'type 必须是 sale 或 gift' })

    // 赠送必须填写客户姓名、电话、审批人
    if (type === 'gift') {
      if (!buyer_name || !buyer_name.trim()) return res.status(400).json({ code: 400, message: '赠送必须填写客户姓名' })
      if (!buyer_phone || !buyer_phone.trim()) return res.status(400).json({ code: 400, message: '赠送必须填写客户电话' })
      if (!approver_id) return res.status(400).json({ code: 400, message: '赠送必须选择审批人' })

      // 检查是否有未解决的拒绝
      const [[{ rejCnt }]] = await pool.query(
        "SELECT COUNT(*) as rejCnt FROM gift_approvals WHERE requested_by = ? AND status = 'rejected'",
        [req.user.id]
      )
      if (rejCnt > 0) {
        return res.status(403).json({ code: 403, message: '您有未解决的赠送拒绝记录，暂时无法发起赠送' })
      }

      // 防重：同一 qrcode 不允许有两个 pending 赠送
      if (qrcode_id) {
        const [[{ pendCnt }]] = await pool.query(
          "SELECT COUNT(*) as pendCnt FROM retail_records WHERE qrcode_id = ? AND type = 'gift' AND gift_status = 'pending'",
          [qrcode_id]
        )
        if (pendCnt > 0) {
          return res.status(400).json({ code: 400, message: '该二维码已有待审批的赠送申请' })
        }
      }
    }

    await conn.beginTransaction()

    const [[product]] = await conn.query('SELECT purchase_price, sale_price FROM products WHERE id = ?', [product_id])
    if (!product) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '商品不存在' })
    }

    let finalPrice = 0
    if (type === 'sale') {
      finalPrice = sale_price !== undefined ? sale_price : product.sale_price
      // 销售价格不得低于成本价（admin也不行）
      if (parseFloat(finalPrice) < parseFloat(product.purchase_price)) {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: `售价不能低于成本价（¥${product.purchase_price}）` })
      }
    }
    // 赠送时售价为0

    // Auto-bind customer service
    let service_user_id = null
    if (qrcode_id) {
      const [[qr]] = await conn.query('SELECT store_id FROM qrcodes WHERE id = ?', [qrcode_id])
      if (qr?.store_id) {
        const [[store]] = await conn.query('SELECT service_user_id FROM stores WHERE id = ?', [qr.store_id])
        service_user_id = store?.service_user_id || null
      }
    }
    if (!service_user_id) {
      const [[defaultService]] = await conn.query("SELECT id FROM users WHERE role = 'service' LIMIT 1")
      service_user_id = defaultService?.id || null
    }

    const giftStatus = type === 'gift' ? 'pending' : null

    const [result] = await conn.query(
      'INSERT INTO retail_records (qrcode_id, product_id, channel, type, gift_status, sold_by, buyer_name, buyer_phone, sale_price, note) VALUES (?,?,?,?,?,?,?,?,?,?)',
      [qrcode_id || null, product_id, channel, type, giftStatus, req.user.id, buyer_name || null, buyer_phone || null, finalPrice, note || null]
    )

    // 赠送：创建审批记录，不更新 qrcode 状态（待审批通过后更新）
    if (type === 'gift') {
      await conn.query(
        'INSERT INTO gift_approvals (retail_record_id, requested_by, approver_id) VALUES (?,?,?)',
        [result.insertId, req.user.id, approver_id]
      )
    }

    // Update qrcode status to sold (only for sale, not gift)
    if (qrcode_id && type !== 'gift') {
      await conn.query(
        'UPDATE qrcodes SET service_user_id = COALESCE(?, service_user_id), buyer_phone = COALESCE(?, buyer_phone), status = ? WHERE id = ?',
        [service_user_id, buyer_phone || null, 'sold', qrcode_id]
      )
    }

    await conn.commit()
    res.json({ code: 0, data: { id: result.insertId }, message: type === 'sale' ? '销售成功' : '赠送申请已提交，等待审批' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// PUT /api/retail-records/:id/buyer — 已售商品补填客户信息
router.put('/:id/buyer', async (req, res, next) => {
  try {
    const { buyer_name, buyer_phone } = req.body
    if (!buyer_name || !buyer_phone) {
      return res.status(400).json({ code: 400, message: '姓名和电话必填' })
    }
    const [[record]] = await pool.query('SELECT id, qrcode_id, buyer_name, buyer_phone FROM retail_records WHERE id = ?', [req.params.id])
    if (!record) return res.status(404).json({ code: 404, message: '记录不存在' })

    // 已有信息的不允许普通员工修改
    if (record.buyer_name && record.buyer_phone && req.user.role !== 'admin') {
      return res.status(400).json({ code: 400, message: '该记录已有客户信息，无法修改' })
    }

    await pool.query('UPDATE retail_records SET buyer_name = ?, buyer_phone = ? WHERE id = ?', [buyer_name, buyer_phone, req.params.id])

    // 同步更新二维码上的 buyer_phone
    if (record.qrcode_id) {
      await pool.query('UPDATE qrcodes SET buyer_phone = ? WHERE id = ?', [buyer_phone, record.qrcode_id])
    }

    res.json({ code: 0, data: null, message: '客户信息更新成功' })
  } catch (err) { next(err) }
})

// GET /api/retail-records/export — Excel导出（最多5000条）
router.get('/export', async (req, res, next) => {
  try {
    const { channel, product_id, date_start, date_end } = req.query
    let where = 'WHERE 1=1'
    const params = []
    if (channel) { where += ' AND r.channel = ?'; params.push(channel) }
    if (product_id) { where += ' AND r.product_id = ?'; params.push(product_id) }
    if (date_start) { where += ' AND DATE(r.created_at) >= ?'; params.push(date_start) }
    if (date_end) { where += ' AND DATE(r.created_at) <= ?'; params.push(date_end) }

    const [rows] = await pool.query(`
      SELECT r.created_at, p.name as product_name, p.spec, q.code as qr_code,
        r.channel, u.name as sold_by_name, h.name as h5_name, h.phone as h5_phone,
        r.buyer_name, r.buyer_phone, r.sale_price, r.note
      FROM retail_records r
      LEFT JOIN products p ON r.product_id = p.id
      LEFT JOIN qrcodes q ON r.qrcode_id = q.id
      LEFT JOIN users u ON r.sold_by = u.id
      LEFT JOIN h5_users h ON r.h5_user_id = h.id
      ${where}
      ORDER BY r.created_at DESC
      LIMIT 5000
    `, params)

    const data = rows.map(r => ({
      '时间': r.created_at ? new Date(r.created_at).toLocaleString('zh-CN') : '',
      '商品名称': r.product_name || '',
      '规格': r.spec || '',
      '二维码': r.qr_code || '',
      '渠道': r.channel === 'scan' ? '扫码' : '手动',
      '操作人': r.sold_by_name || r.h5_name || '',
      '买家姓名': r.buyer_name || '',
      '买家手机': r.buyer_phone || r.h5_phone || '',
      '售价': r.sale_price || '',
      '备注': r.note || ''
    }))

    const ws = XLSX.utils.json_to_sheet(data)
    const wb = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(wb, ws, '零售记录')
    const buf = XLSX.write(wb, { type: 'buffer', bookType: 'xlsx' })
    res.setHeader('Content-Disposition', `attachment; filename="retail-${Date.now()}.xlsx"`)
    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
    res.send(buf)
  } catch (err) { next(err) }
})

// PUT /api/retail-records/:id/revoke — 撤回销售记录（仅限销售类型，赠送不可撤回）
router.put('/:id/revoke', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    // 查询记录
    const [[record]] = await conn.query(
      'SELECT * FROM retail_records WHERE id = ?',
      [req.params.id]
    )
    if (!record) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '记录不存在' })
    }

    // 只能撤回销售类型，赠送类型不可撤回
    if (record.type === 'gift') {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '赠送记录不可撤回，请使用赠送审批功能' })
    }

    // 恢复二维码状态为 bindProduct
    if (record.qrcode_id) {
      await conn.query(
        'UPDATE qrcodes SET status = ? WHERE id = ?',
        ['bindProduct', record.qrcode_id]
      )
    }

    // 删除零售记录
    await conn.query('DELETE FROM retail_records WHERE id = ?', [req.params.id])

    await conn.commit()
    res.json({ code: 0, message: '撤回成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// DELETE /api/retail-records/:id — 删除销售记录（管理员权限）
router.delete('/:id', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    // 查询记录
    const [[record]] = await conn.query(
      'SELECT * FROM retail_records WHERE id = ?',
      [req.params.id]
    )
    if (!record) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '记录不存在' })
    }

    // 如果是赠送且有审批记录，先删除审批记录
    if (record.type === 'gift') {
      await conn.query('DELETE FROM gift_approvals WHERE retail_record_id = ?', [req.params.id])
    }

    // 恢复二维码状态为 bindProduct
    if (record.qrcode_id) {
      await conn.query(
        'UPDATE qrcodes SET status = ? WHERE id = ?',
        ['bindProduct', record.qrcode_id]
      )
    }

    // 删除零售记录
    await conn.query('DELETE FROM retail_records WHERE id = ?', [req.params.id])

    await conn.commit()
    res.json({ code: 0, message: '删除成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

export default router
