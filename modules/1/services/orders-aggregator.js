/**
 * 订单聚合服务 - 统一查询层
 *
 * 背景：新加坡后端有9种独立订单表（orders / mall_orders / dine_orders /
 *       takeout_orders / seckill_orders / score_orders / collage_order /
 *       hotel_orders / recharge_order），每种独立 schema。报表/对账/管理后台
 *       "全部订单" 视图需要 UNION ALL 9 次。本服务提供统一查询入口，**不改
 *       任何现有 schema**，仅作为查询层。
 *
 * 统一返回格式（每个 list 元素）：
 * {
 *   id,                  // 订单 ID
 *   order_no,            // 订单号
 *   order_type,          // 来源表标识：orders/mall/dine/takeout/seckill/score/collage/hotel/recharge
 *   order_type_label,    // 中文标签：订单/商城订单/堂食/外卖/秒杀/积分/拼团/酒店/充值
 *   customer_name,       // 客户名（统一字段）
 *   customer_phone,      // 客户手机号（统一字段）
 *   total_amount,        // 金额（统一字段）
 *   status,              // 状态（统一字段）
 *   status_label,        // 中文状态标签
 *   created_at           // 创建时间（统一字段，标准化为 DATETIME 字符串）
 * }
 *
 * 设计原则：
 * - 零侵入：不修改任何现有表 schema，不改任何现有 routes 文件
 * - 只读：本服务只提供查询，**不做任何写入/更新/删除**
 * - 性能：每个子查询带 LIMIT，全局 LIMIT 在最外层
 */

import { pool } from '../db/connection.js'

// 9种订单表 → 中文标签映射
const ORDER_TYPE_LABELS = {
  orders: '订单',
  mall: '商城订单',
  dine: '堂食',
  takeout: '外卖',
  seckill: '秒杀',
  score: '积分兑换',
  collage: '拼团',
  hotel: '酒店',
  recharge: '充值'
}

/**
 * 把不同表的时间字段（DATETIME / TIMESTAMP / INT(unix)）统一为字符串
 */
function normalizeDate(val) {
  if (val == null) return null
  // INT 时间戳（如 collage_order.createtime）
  if (typeof val === 'number' || /^\d{10}$/.test(String(val))) {
    const ts = Number(val)
    // 11-13 位是 ms，否则按 s
    const ms = ts > 1e12 ? ts : ts * 1000
    const d = new Date(ms)
    return isNaN(d.getTime()) ? String(val) : d.toISOString().slice(0, 19).replace('T', ' ')
  }
  // DATETIME / TIMESTAMP → Date 对象 → 字符串
  if (val instanceof Date) {
    return val.toISOString().slice(0, 19).replace('T', ' ')
  }
  return String(val)
}

/**
 * 字符集统一包装：latin1 表的字符串列转 utf8mb4 避免 UNION collation mismatch
 * 需要转换的表（latin1_swedish_ci）：orders / mall_orders
 *
 * 关键：CONVERT 后必须保留 AS 别名，否则 UNION 各子查询列名不一致会报错
 */
function wrapLatin1(sql, isLatin1) {
  if (!isLatin1) return sql
  return sql
    // order_no 是 SELECT 列表里的字段，必须保留 AS 别名（外层 SELECT * 要拿这一列）
    .replace(/\border_no AS order_no\b/g, `CONVERT(order_no USING utf8mb4) AS order_no`)
    .replace(/\border_no\b/g, `CONVERT(order_no USING utf8mb4)`)
    .replace(/\bmember_name AS customer_name\b/g, `CONVERT(member_name USING utf8mb4) AS customer_name`)
    .replace(/\bmember_phone AS customer_phone\b/g, `CONVERT(member_phone USING utf8mb4) AS customer_phone`)
    .replace(/\buser_name AS customer_name\b/g, `CONVERT(user_name USING utf8mb4) AS customer_name`)
    .replace(/\buser_phone AS customer_phone\b/g, `CONVERT(user_phone USING utf8mb4) AS customer_phone`)
    // 字符串字面量加 _utf8mb4 前缀（防 collation 不匹配）
    .replace(/'(orders|mall|dine|takeout|seckill|score|collage|hotel|recharge)'/g, "_utf8mb4'$1'")
}

/**
 * 状态统一映射（不同表用不同状态值，规范化）
 */
const STATUS_LABELS = {
  // 通用
  pending: '待支付', pending_pay: '待支付',
  paid: '已支付',
  shipped: '已发货',
  completed: '已完成',
  cancelled: '已取消',
  refunded: '已退款',
  // dine/takeout 特有
  ordering: '点餐中',
  confirmed: '已确认',
  serving: '上菜中',
  served: '已上菜',
  finished: '已结束',
  // 拼团特有
  forming: '组团中',
  success: '已成团',
  failed: '拼团失败'
}

function normalizeStatus(status) {
  if (!status) return ''
  return STATUS_LABELS[status] || status
}

/**
 * 9个 UNION 子查询
 *
 * 每个子查询 SELECT 出统一格式的列：
 *   id, order_no, order_type, customer_name, customer_phone,
 *   total_amount, status, created_at
 *
 * 注意：
 * - 各表 order_no 字段名不一致：collage_order 用 ordernum，其余用 order_no
 * - 各表金额字段：hotel 用 total_price，其余用 total_amount / totalprice
 * - 各表客户字段：member_* / user_* / customer_* / guest_* / mid（collage）
 * - 各表时间字段：orders.created_at / collage_order.createtime（int）/ 其余 created_at
 */
const UNION_SUBQUERIES = {
  // 主订单（管理后台订单管理）— latin1_swedish_ci
  orders: {
    sql: `
      SELECT id, CONVERT(order_no USING utf8mb4) AS order_no, _utf8mb4'orders' AS order_type,
        CONVERT(member_name USING utf8mb4) AS customer_name, CONVERT(member_phone USING utf8mb4) AS customer_phone,
        total_amount, CONVERT(status USING utf8mb4) AS status, created_at
      FROM orders
    `,
    latin1: false  // 已经在模板里处理了字符集
  },

  // 商城订单 — latin1_swedish_ci
  mall: {
    sql: `
      SELECT id, CONVERT(order_no USING utf8mb4) AS order_no, _utf8mb4'mall' AS order_type,
        CONVERT(user_name USING utf8mb4) AS customer_name, CONVERT(user_phone USING utf8mb4) AS customer_phone,
        total_amount, CONVERT(status USING utf8mb4) AS status, pay_time AS created_at
      FROM mall_orders
    `,
    latin1: false
  },

  // 堂食订单（dine_orders）— utf8mb4
  dine: {
    sql: `
      SELECT id, CONVERT(order_no USING utf8mb4) AS order_no, _utf8mb4'dine' AS order_type,
        CONVERT(customer_name USING utf8mb4) AS customer_name, CONVERT(customer_phone USING utf8mb4) AS customer_phone,
        total_amount, CONVERT(status USING utf8mb4) AS status, confirmed_at AS created_at
      FROM dine_orders
      WHERE order_type = 'dine'
    `,
    latin1: false
  },

  // 外卖订单（dine_orders 里 order_type='takeout' + 独立 takeout_orders）— utf8mb4
  takeout: {
    sql: `
      SELECT id, CONVERT(order_no USING utf8mb4) AS order_no, _utf8mb4'takeout' AS order_type,
        CONVERT(customer_name USING utf8mb4) AS customer_name, CONVERT(customer_phone USING utf8mb4) AS customer_phone,
        total_amount, CONVERT(status USING utf8mb4) AS status, created_at
      FROM takeout_orders
      UNION ALL
      SELECT id, CONVERT(order_no USING utf8mb4) AS order_no, _utf8mb4'takeout' AS order_type,
        CONVERT(customer_name USING utf8mb4) AS customer_name, CONVERT(customer_phone USING utf8mb4) AS customer_phone,
        total_amount, CONVERT(status USING utf8mb4) AS status, confirmed_at AS created_at
      FROM dine_orders
      WHERE order_type = 'takeout'
    `,
    latin1: false
  },

  // 秒杀订单 — utf8mb4
  seckill: {
    sql: `
      SELECT id, CONVERT(order_no USING utf8mb4) AS order_no, _utf8mb4'seckill' AS order_type,
        CAST(user_id AS CHAR) AS customer_name, NULL AS customer_phone,
        total_amount, CONVERT(status USING utf8mb4) AS status, created_at
      FROM seckill_orders
    `,
    latin1: false
  },

  // 积分兑换订单 — utf8mb4
  score: {
    sql: `
      SELECT id, CONVERT(order_no USING utf8mb4) AS order_no, _utf8mb4'score' AS order_type,
        CONVERT(receiver_name USING utf8mb4) AS customer_name, CONVERT(receiver_phone USING utf8mb4) AS customer_phone,
        CAST(total_score AS DECIMAL(12,2)) AS total_amount,
        _utf8mb4'pending' AS status, created_at
      FROM score_orders
    `,
    latin1: false
  },

  // 拼团订单（特殊字段名）— utf8mb4_unicode_ci
  collage: {
    sql: `
      SELECT id, CONVERT(ordernum USING utf8mb4) AS order_no, _utf8mb4'collage' AS order_type,
        CAST(mid AS CHAR) AS customer_name, NULL AS customer_phone,
        totalprice AS total_amount,
        _utf8mb4'forming' AS status, createtime AS created_at
      FROM collage_order
    `,
    latin1: false
  },

  // 酒店订单（金额字段 total_price）— utf8mb4
  hotel: {
    sql: `
      SELECT id, CONVERT(order_no USING utf8mb4) AS order_no, _utf8mb4'hotel' AS order_type,
        CONVERT(guest_name USING utf8mb4) AS customer_name, CONVERT(guest_phone USING utf8mb4) AS customer_phone,
        total_price AS total_amount, CONVERT(status USING utf8mb4) AS status, paid_at AS created_at
      FROM hotel_orders
    `,
    latin1: false
  },

  // 充值订单 — utf8mb4
  recharge: {
    sql: `
      SELECT id, CONVERT(order_no USING utf8mb4) AS order_no, _utf8mb4'recharge' AS order_type,
        CAST(user_id AS CHAR) AS customer_name, NULL AS customer_phone,
        total_amount, CONVERT(payment_status USING utf8mb4) AS status, created_at
      FROM recharge_order
    `,
    latin1: false
  }
}

/**
 * 把子查询结果格式化为统一格式
 */
function normalize(row) {
  return {
    id: row.id,
    order_no: row.order_no,
    order_type: row.order_type,
    order_type_label: ORDER_TYPE_LABELS[row.order_type] || row.order_type,
    customer_name: row.customer_name,
    customer_phone: row.customer_phone,
    total_amount: Number(row.total_amount) || 0,
    status: row.status,
    status_label: normalizeStatus(row.status),
    created_at: normalizeDate(row.created_at)
  }
}

/**
 * 列出所有订单（分页 + 筛选）
 *
 * @param {Object} options
 * @param {string} [options.order_type]  - 限定单一类型：orders/mall/dine/takeout/seckill/score/collage/hotel/recharge
 * @param {string} [options.keyword]     - 订单号/客户手机号模糊匹配
 * @param {string} [options.status]      - 状态筛选（精确匹配）
 * @param {string} [options.date_from]   - 创建时间起 (YYYY-MM-DD)
 * @param {string} [options.date_to]     - 创建时间止 (YYYY-MM-DD)
 * @param {number} [options.page=1]
 * @param {number} [options.limit=20]
 */
export async function listAllOrders(options = {}) {
  const {
    order_type, keyword, status,
    date_from, date_to,
    page = 1, limit = 20
  } = options

  // 选定的 order_type 列表
  const types = order_type && UNION_SUBQUERIES[order_type]
    ? [order_type]
    : Object.keys(UNION_SUBQUERIES)

  // 每个子查询的内部 LIMIT（防止单表过大撑爆内存）
  const innerLimit = 1000

  // 构造每个子查询的 WHERE
  function buildWhere(baseSql, t) {
    let where = []
    let params = []

    if (keyword) {
      // 各表 order_no / ordernum，phone 字段名不同
      if (t === 'collage') {
        where.push('(ordernum LIKE ? OR mid = ?)')
        params.push(`%${keyword}%`, isNaN(keyword) ? -1 : Number(keyword))
      } else if (t === 'seckill' || t === 'recharge') {
        where.push('order_no LIKE ?')
        params.push(`%${keyword}%`)
      } else if (t === 'score') {
        where.push('(order_no LIKE ? OR receiver_phone LIKE ?)')
        params.push(`%${keyword}%`, `%${keyword}%`)
      } else {
        where.push('(order_no LIKE ? OR customer_phone LIKE ? OR customer_name LIKE ?)')
        params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`)
      }
    }

    if (status) {
      if (t === 'score') {
        // score 没有 status 字段，固定为 pending，无法筛选
      } else if (t === 'collage') {
        // collage 固定 forming
      } else if (t === 'recharge') {
        where.push('payment_status = ?')
        params.push(status)
      } else {
        where.push('status = ?')
        params.push(status)
      }
    }

    // 时间范围（按 created_at / pay_time / confirmed_at / paid_at / createtime 统一处理）
    if (date_from || date_to) {
      const dateField = baseSql.match(/(\w+)\s+AS\s+created_at/i)?.[1]
                   || 'created_at'
      if (date_from) { where.push(`${dateField} >= ?`); params.push(date_from + ' 00:00:00') }
      if (date_to)   { where.push(`${dateField} <= ?`); params.push(date_to + ' 23:59:59') }
    }

    const whereSql = where.length ? ' WHERE ' + where.join(' AND ') : ''
    return { whereSql, params }
  }

  // 构造 UNION ALL（每个子查询加内部 LIMIT + latin1 转 utf8mb4）
  const unionParts = []
  const unionParams = []
  for (const t of types) {
    const entry = UNION_SUBQUERIES[t]
    const sql = wrapLatin1(entry.sql, entry.latin1)
    const { whereSql, params } = buildWhere(sql, t)
    unionParts.push(`(${sql}${whereSql} ORDER BY id DESC LIMIT ${innerLimit})`)
    unionParams.push(...params)
  }

  const unionSql = unionParts.join(' UNION ALL ')

  // 外层：总数 + 分页
  const countSql = `SELECT COUNT(*) AS total FROM (${unionSql}) AS u`
  const [countRows] = await pool.query(countSql, unionParams)
  const total = Number(countRows[0]?.total) || 0

  const offset = Math.max(0, (Number(page) - 1) * Number(limit))
  // 注意：union 内层已经按 id DESC 排，外层必须包一层 SELECT * FROM (...) AS t ORDER BY ... LIMIT
  // 但 order_by 复杂，先用 MAX(id) 模式：外层再 SELECT * FROM (subquery) LIMIT OFFSET
  const listSql = `SELECT * FROM (${unionSql}) AS u ORDER BY u.id DESC LIMIT ? OFFSET ?`
  const [rows] = await pool.query(listSql, [...unionParams, Number(limit), offset])

  return {
    list: rows.map(normalize),
    total,
    page: Number(page),
    limit: Number(limit)
  }
}

/**
 * 订单统计（按 order_type 分组）
 *
 * @param {Object} options
 * @param {string} [options.date_from]
 * @param {string} [options.date_to]
 */
export async function getOrderStats(options = {}) {
  const { date_from, date_to } = options

  // 每个子查询：COUNT + SUM(amount)
  const statsParts = []
  const allParams = []
  for (const t of Object.keys(UNION_SUBQUERIES)) {
    const entry = UNION_SUBQUERIES[t]
    const sql = wrapLatin1(entry.sql, entry.latin1)

    // 抽出日期字段（created_at 的别名来源字段）
    const dateField = sql.match(/(\w+)\s+AS\s+created_at/i)?.[1] || 'created_at'

    let where = []
    let params = []
    if (date_from) { where.push(`${dateField} >= ?`); params.push(date_from + ' 00:00:00') }
    if (date_to)   { where.push(`${dateField} <= ?`); params.push(date_to + ' 23:59:59') }
    const whereSql = where.length ? ' WHERE ' + where.join(' AND ') : ''

    // 用子查询 + 外层统计，避免改写复杂 SELECT
    // 金额字段在子查询里别名是 total_amount（hotel 用了 total_price AS total_amount）
    statsParts.push(`
      SELECT '${t}' AS order_type,
        COUNT(*) AS count,
        COALESCE(SUM(total_amount), 0) AS total_amount
      FROM (${sql}) AS sub${whereSql}
    `)
    allParams.push(...params)
  }

  const finalSql = statsParts.join(' UNION ALL ') + ' ORDER BY count DESC'
  const [rows] = await pool.query(finalSql, allParams)

  return rows.map(r => ({
    order_type: r.order_type,
    order_type_label: ORDER_TYPE_LABELS[r.order_type] || r.order_type,
    count: Number(r.count),
    total_amount: Number(r.total_amount) || 0
  }))
}

/**
 * 单订单详情（按 order_type 路由到对应表）
 */
export async function getOrderById(id, orderType) {
  if (!UNION_SUBQUERIES[orderType]) {
    throw new Error(`未知订单类型: ${orderType}`)
  }
  const entry = UNION_SUBQUERIES[orderType]
  const sql = wrapLatin1(entry.sql, entry.latin1)
  // 直接查整行（用 * 代替原 SELECT 列表）
  const tableMatch = sql.match(/FROM\s+(\w+)/i)
  if (!tableMatch) throw new Error('解析表名失败')
  const table = tableMatch[1]
  const [rows] = await pool.query(`SELECT * FROM \`${table}\` WHERE id = ? LIMIT 1`, [id])
  if (!rows.length) return null
  return { ...normalize({
    id: rows[0].id,
    order_no: rows[0].order_no || rows[0].ordernum,
    order_type: orderType,
    customer_name: rows[0].member_name || rows[0].user_name || rows[0].customer_name || rows[0].guest_name || rows[0].receiver_name,
    customer_phone: rows[0].member_phone || rows[0].user_phone || rows[0].customer_phone || rows[0].guest_phone || rows[0].receiver_phone,
    total_amount: rows[0].total_amount || rows[0].totalprice || rows[0].total_price,
    status: rows[0].status || rows[0].payment_status || 'pending',
    created_at: rows[0].created_at || rows[0].pay_time || rows[0].confirmed_at || rows[0].paid_at || rows[0].createtime
  }), raw: rows[0] }
}
