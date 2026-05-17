export async function handle(message, user, pool) {
  if (/库存|还有多少|仓库/.test(message)) {
    return { reply: '📦 **库存查询**\n\n请告诉我具体要查什么产品\n\n功能开发中...' }
  }
  if (/补货|预警|要进货/.test(message)) {
    return { reply: '⚠️ **库存预警**\n\n暂无预警\n\n功能开发中...' }
  }
  return { reply: '📦 库存功能开发中...\n支持：查库存、预警、补货' }
}
