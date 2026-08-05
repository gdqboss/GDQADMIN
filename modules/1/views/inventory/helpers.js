/**
 * 入库出库管理 - 通用工具函数
 *
 * 抽离自 InOutList.vue，把纯函数（不依赖组件状态）抽出来便于复用和测试。
 * 涉及组件状态（activeTab / products.value 等）的函数仍然留在组件内。
 */

/**
 * 格式化日期（截取前 16 字符 "YYYY-MM-DDTHH:mm"）
 * @param {string} str
 * @returns {string}
 */
export function formatDate(str) {
  if (!str) return ''
  return String(str).slice(0, 16)
}

/**
 * 格式化日期时间为本地时区字符串 "YYYY-MM-DD HH:mm:ss"
 * ISO 时间如 "2026-06-18T05:35:35.000Z" → "2026-06-18 13:35:35"
 * @param {string} str
 * @returns {string}
 */
export function formatDateTime(str) {
  if (!str) return ''
  const d = new Date(str)
  if (isNaN(d.getTime())) return String(str)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
}

/**
 * 库存变动类型 → Badge 颜色（Tailwind 类）
 * 颜色: 入库绿 / 出库红 / 调整蓝 / 退货橙 / 调入紫 / 调出粉 / 删除灰
 * @param {string} type
 * @returns {string}
 */
export function changeTypeBadge(type) {
  return {
    inbound:      'bg-green-100 text-green-700',
    adjust:       'bg-blue-100 text-blue-700',
    outbound:     'bg-red-100 text-red-700',
    return:       'bg-orange-100 text-orange-700',
    transferIn:   'bg-purple-100 text-purple-700',
    transferOut:  'bg-pink-100 text-pink-700',
    delete:       'bg-gray-100 text-gray-600',
  }[type] || 'bg-gray-100 text-gray-600'
}

/**
 * 库存变动类型 → 中文标签
 */
export const CHANGE_TYPE_LABELS = {
  inbound:      '入库',
  outbound:     '出库',
  adjust:       '调整',
  return:       '退货',
  transferIn:   '调入',
  transferOut:  '调出',
  delete:       '删除',
}

/**
 * 根据 type 返回中文标签
 * @param {string} type
 * @returns {string}
 */
export function changeTypeLabel(type) {
  return CHANGE_TYPE_LABELS[type] || type
}

/**
 * 入库/出库/退货 记录状态 → 标签
 */
export const RECORD_STATUS_MAP = {
  pending:  { label: '待审核', class: 'bg-yellow-100 text-yellow-700' },
  approved: { label: '已审核', class: 'bg-blue-100 text-blue-700' },
  rejected: { label: '已拒绝', class: 'bg-red-100 text-red-700' },
  done:     { label: '已完成', class: 'bg-green-100 text-green-700' },
  cancelled:{ label: '已取消', class: 'bg-gray-100 text-gray-600' },
}

/**
 * 格式化 SKU 显示标签
 * 优先使用 specs JSON 解析后的规格组合，兜底用 sku_key
 * @param {Object} sku - {sku, sku_key, specs}
 * @returns {string}
 */
export function formatSkuLabel(sku) {
  if (!sku) return ''
  // 优先解析 specs JSON
  if (sku.specs) {
    try {
      const specs = typeof sku.specs === 'string' ? JSON.parse(sku.specs) : sku.specs
      const values = Object.values(specs || {})
      if (values.length) return values.join('/')
    } catch (e) {
      // specs 不是 JSON，回退
    }
  }
  // 兜底用 sku_key（如 "20-碳灰-A"）
  if (sku.sku_key) return sku.sku_key
  // 最后兜底用 sku 字段
  return sku.sku || String(sku.id || '')
}
