/**
 * SKU 标签格式化工具
 * 规则：纯值组合（颜色/尺寸），用 / 分隔，不带属性名前缀
 * 例：{ specs: '{"尺寸":"20","颜色":"枪灰"}' } → "20/枪灰"
 */

/**
 * 从 sku.specs JSON 字符串解析规格组合
 * @param {Object} sku
 * @returns {string}
 */
export function formatSkuLabel(sku) {
  if (!sku) return ''
  // 优先用 sku.specs（JSON 字符串）
  if (sku.specs) {
    try {
      const obj = typeof sku.specs === 'string' ? JSON.parse(sku.specs) : sku.specs
      const values = Object.values(obj || {}).filter(Boolean)
      if (values.length > 0) return values.join('/')
    } catch {
      // ignore
    }
  }
  // 兜底：sku.sku_code 或 sku.key
  if (sku.sku_code) return sku.sku_code
  if (sku.key) return sku.key
  // 最后兜底：color + size
  if (sku.color && sku.size) return sku.color + '/' + sku.size
  return sku.color || sku.size || `#${sku.id || ''}`
}

/**
 * 解析 SKU specs 对象
 */
export function parseSkuSpecs(sku) {
  if (!sku || !sku.specs) return {}
  try {
    return typeof sku.specs === 'string' ? JSON.parse(sku.specs) : sku.specs
  } catch {
    return {}
  }
}

/**
 * 提取 SKU 的颜色
 */
export function getSkuColor(sku) {
  const specs = parseSkuSpecs(sku)
  return specs['颜色'] || specs['color'] || sku.color || null
}

/**
 * 提取 SKU 的尺寸
 */
export function getSkuSize(sku) {
  const specs = parseSkuSpecs(sku)
  return specs['尺寸'] || specs['size'] || sku.size || null
}