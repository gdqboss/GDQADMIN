/**
 * 订单聚合路由 - 提供9种订单的统一查询入口
 *
 * 挂载点：/api/order-aggregator
 * 端点：
 *   GET /list           列出所有订单（支持筛选/分页）
 *   GET /stats          订单统计（按 order_type 分组）
 *   GET /detail/:type/:id  单订单详情
 *   GET /types          支持的订单类型清单
 *
 * 权限：复用现有 requireAuth + apiLimiter（与 mall 一致：公开）
 */

import express from 'express'
import { listAllOrders, getOrderStats, getOrderById } from '../services/orders-aggregator.js'

const router = express.Router()

// 支持的订单类型清单（前端下拉用）
const SUPPORTED_TYPES = [
  { value: 'orders',   label: '订单' },
  { value: 'mall',     label: '商城订单' },
  { value: 'dine',     label: '堂食' },
  { value: 'takeout',  label: '外卖' },
  { value: 'seckill',  label: '秒杀' },
  { value: 'score',    label: '积分兑换' },
  { value: 'collage',  label: '拼团' },
  { value: 'hotel',    label: '酒店' },
  { value: 'recharge', label: '充值' }
]

/**
 * GET /list
 * Query:
 *   order_type?: orders/mall/dine/takeout/seckill/score/collage/hotel/recharge
 *   keyword?:    订单号/客户手机号模糊匹配
 *   status?:     状态精确匹配
 *   date_from?:  YYYY-MM-DD
 *   date_to?:    YYYY-MM-DD
 *   page?:       默认 1
 *   limit?:      默认 20，最大 100
 */
router.get('/list', async (req, res) => {
  try {
    const data = await listAllOrders({
      order_type: req.query.order_type,
      keyword: req.query.keyword,
      status: req.query.status,
      date_from: req.query.date_from,
      date_to: req.query.date_to,
      page: req.query.page,
      limit: req.query.limit
    })
    res.json({ code: 0, message: 'ok', data })
  } catch (err) {
    console.error('[order-aggregator.list]', err)
    res.status(500).json({ code: 500, message: err.message || '查询失败' })
  }
})

/**
 * GET /stats
 * Query: date_from?, date_to?
 */
router.get('/stats', async (req, res) => {
  try {
    const data = await getOrderStats({
      date_from: req.query.date_from,
      date_to: req.query.date_to
    })
    res.json({ code: 0, message: 'ok', data })
  } catch (err) {
    console.error('[order-aggregator.stats]', err)
    res.status(500).json({ code: 500, message: err.message || '统计失败' })
  }
})

/**
 * GET /detail/:type/:id
 */
router.get('/detail/:type/:id', async (req, res) => {
  try {
    const data = await getOrderById(req.params.id, req.params.type)
    if (!data) return res.status(404).json({ code: 404, message: '订单不存在' })
    res.json({ code: 0, message: 'ok', data })
  } catch (err) {
    console.error('[order-aggregator.detail]', err)
    res.status(500).json({ code: 500, message: err.message || '查询失败' })
  }
})

/**
 * GET /types
 * 支持的订单类型清单
 */
router.get('/types', async (req, res) => {
  res.json({ code: 0, message: 'ok', data: SUPPORTED_TYPES })
})

export default router
