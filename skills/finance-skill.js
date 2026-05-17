export async function handle(message, user, pool) {
  if (/收入|销售额|卖了多少/.test(message)) {
    return { reply: '💰 **本月收入**\n\n待统计\n\n功能开发中...' }
  }
  if (/支出|成本|花了多少/.test(message)) {
    return { reply: '💸 **本月支出**\n\n待统计\n\n功能开发中...' }
  }
  if (/赚钱|利润|哪个产品/.test(message)) {
    return { reply: '📊 **产品利润分析**\n\n待分析\n\n功能开发中...' }
  }
  return { reply: '💰 财务功能开发中...\n支持：收入、支出、利润分析' }
}
