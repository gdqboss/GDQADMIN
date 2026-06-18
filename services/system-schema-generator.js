/**
 * 系统能力描述生成器
 *
 * 自动扫描 DB schema（表结构 + RBAC permissions），生成 AI 课堂 System Prompt
 * 用的"系统能力描述"片段。让 AI 不靠猜，能准确知道系统有哪些表/字段/权限。
 *
 * 使用方式：
 *   import { generateSystemSchema, schemaToPrompt } from './system-schema-generator.js'
 *   const schemaData = await generateSystemSchema()
 *   const promptFragment = schemaToPrompt(schemaData)
 *
 * 缓存策略：5 分钟 TTL。表结构变化不频繁，没必要每次查 DB。
 */

import { pool } from '../db/connection.js'

// 缓存（5 分钟 TTL）
let cachedSchema = null
let cachedAt = 0
const TTL_MS = 5 * 60 * 1000

// 哪些表要包含到 prompt（排除敏感内部表）
const EXCLUDED_TABLES = [
  // RBAC 内部表（提示用户权限名就够了）
  /^rbac_/i,
  // AI 课堂自身（避免递归）
  /^ai_class_/i,
  // 临时/缓存
  /^temp_/i,
  /^cache_/i,
  // migrations
  /^migrations?$/i,
  /^knex_migrations?/i,
]

export const TABLE_CATEGORIES = [
  '商品', '订单', '用户', '库存', '渠道', '财务', 'OA',
  '审批', '商城', '酒店', '内容', '二维码', '报表',
  '系统配置', '日志', '其他'
] // 导出分类常量给 routes/admin-schema.js 复用

// 表分组（让 prompt 更易读）
export function categorizeTable(name) {
  if (/_log(s)?$/i.test(name)) return '日志'
  if (/^(orders?|mall_orders?|dine|takeout|seckill|score|collage|hotel|recharge)/i.test(name)) return '订单'
  if (/^(product|sku|category|material|brand)/i.test(name)) return '商品'
  if (/^(user|member|customer|address)/i.test(name)) return '用户'
  if (/^(warehouse|inbound|outbound|return|stock|inventory|alert)/i.test(name)) return '库存'
  if (/^(store|dealer|supplier)/i.test(name)) return '渠道'
  if (/^(finance|payment|invoice|recharge)/i.test(name)) return '财务'
  if (/^(oa|attendance|worklog|task|responsibilit|visit)/i.test(name)) return 'OA'
  if (/^(gift|approval|retail)/i.test(name)) return '审批'
  if (/^(mall|product_spu)/i.test(name)) return '商城'
  if (/^(hotel|yuyue|restaurant)/i.test(name)) return '酒店'
  if (/^(article|news|notice|message)/i.test(name)) return '内容'
  if (/^(settings|menu|module|server)/i.test(name)) return '系统配置'
  if (/^(qrcode|code|scan)/i.test(name)) return '二维码'
  if (/^(report|bi|stat|dashboard)/i.test(name)) return '报表'
  return '其他'
}

/**
 * 扫描所有表的 schema
 */
async function scanTableSchemas() {
  const [tables] = await pool.query(
    `SELECT TABLE_NAME, TABLE_COMMENT
     FROM information_schema.tables
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_TYPE = 'BASE TABLE'
     ORDER BY TABLE_NAME`
  )

  const result = {}
  for (const t of tables) {
    const name = t.TABLE_NAME
    // 跳过排除列表
    if (EXCLUDED_TABLES.some(re => re.test(name))) continue

    const [cols] = await pool.query(
      `SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_COMMENT, COLUMN_DEFAULT
       FROM information_schema.columns
       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
       ORDER BY ORDINAL_POSITION`,
      [name]
    )

    result[name] = {
      comment: t.TABLE_COMMENT || '',
      columns: cols.map(c => ({
        name: c.COLUMN_NAME,
        type: c.COLUMN_TYPE,
        nullable: c.IS_NULLABLE === 'YES',
        key: c.COLUMN_KEY,        // PRI / UNI / MUL
        comment: c.COLUMN_COMMENT || '',
      }))
    }
  }
  return result
}

/**
 * 扫描 RBAC permissions
 */
async function scanRbacPermissions() {
  try {
    const [rows] = await pool.query(
      `SELECT name, category, description
       FROM rbac_permissions
       ORDER BY category, name`
    )
    return rows.map(r => ({
      name: r.name,
      category: r.category || 'general',
      description: r.description || ''
    }))
  } catch (e) {
    // rbac_permissions 表可能不存在（schema 未迁移）
    return []
  }
}

/**
 * 主入口：生成完整 schema 数据（带缓存）
 */
export async function generateSystemSchema(force = false) {
  const now = Date.now()
  if (!force && cachedSchema && (now - cachedAt) < TTL_MS) {
    return cachedSchema
  }

  const [tables, permissions] = await Promise.all([
    scanTableSchemas(),
    scanRbacPermissions()
  ])

  cachedSchema = {
    tables,
    permissions,
    generated_at: new Date().toISOString(),
    table_count: Object.keys(tables).length,
    permission_count: permissions.length
  }
  cachedAt = now
  return cachedSchema
}

/**
 * 把 schema 数据转为 System Prompt 片段
 *
 * 输出格式（人类可读）：
 *   ## 系统数据库结构（共 N 张表）
 *   ### 商品
 *   - **products**: id (int) [PK] | sku (varchar) [UNI] | name (varchar) | category_id (int)
 *   - **product_skus**: id (int) [PK] | product_id (int) | sku_key (varchar) | specs (text)
 *   ### 订单
 *   ...
 *
 *   ## 系统权限（共 M 个）
 *   - inventory:read [库存] 查看库存数据
 *   - inventory:write [库存] 修改库存
 *   ...
 */
export function schemaToPrompt(schemaData) {
  const { tables, permissions, table_count, permission_count } = schemaData

  // 按分组组织表
  const grouped = {}
  for (const [tname, tinfo] of Object.entries(tables)) {
    const cat = categorizeTable(tname)
    if (!grouped[cat]) grouped[cat] = []
    grouped[cat].push([tname, tinfo])
  }

  // 按分类顺序输出
  const catOrder = ['商品', '订单', '用户', '库存', '渠道', '财务', 'OA', '审批', '商城', '酒店', '内容', '二维码', '报表', '系统配置', '日志', '其他']

  let out = `\n## 系统数据库结构（共 ${table_count} 张表，自动生成于 ${schemaData.generated_at}）\n`
  out += '> 表名/字段名必须以下面为准，凭印象猜会导致 SQL 报错。\n\n'

  for (const cat of catOrder) {
    if (!grouped[cat] || !grouped[cat].length) continue
    out += `### ${cat}\n`
    for (const [tname, tinfo] of grouped[cat]) {
      const cols = tinfo.columns.map(c => {
        const keyMark = c.key === 'PRI' ? '[PK]' : c.key === 'UNI' ? '[UNI]' : ''
        const nullable = c.nullable ? '' : '*'
        return `${nullable}${c.name}(${c.type})${keyMark ? ' ' + keyMark : ''}${c.comment ? '//' + c.comment : ''}`
      }).join(' | ')
      out += `- **${tname}**: ${cols}\n`
    }
    out += '\n'
  }

  // 权限列表
  if (permissions.length) {
    out += `\n## 系统权限（共 ${permission_count} 个，用户可能拥有其中部分）\n`
    const permGrouped = {}
    for (const p of permissions) {
      const c = p.category || 'general'
      if (!permGrouped[c]) permGrouped[c] = []
      permGrouped[c].push(p)
    }
    for (const [cat, perms] of Object.entries(permGrouped)) {
      out += `- [${cat}] ${perms.map(p => p.name + (p.description ? `（${p.description}）` : '')).join('、')}\n`
    }
    out += '\n'
  }

  out += '\n## SQL 查询规则\n'
  out += '- 字符串字段含中文时，用 _utf8mb4"中文" 前缀防 collation 错误\n'
  out += '- LIKE 查询必须传 %keyword% 形式\n'
  out += '- 金额字段注意命名差异：orders.total_amount / hotel_orders.total_price / collage_order.totalprice\n'
  out += '- 时间字段差异：DATETIME（created_at）/ TIMESTAMP（paid_at）/ INT unix 时间戳（createtime）\n'
  out += '- 找不到字段时先查 SHOW COLUMNS FROM table，禁止凭印象写\n'

  return out
}

/**
 * 清空缓存（测试 / 紧急刷新用）
 */
export function clearSchemaCache() {
  cachedSchema = null
  cachedAt = 0
}
