export async function handle(message, user, pool) {
  if (/客户|大客户|有哪些客户/.test(message)) {
    return { reply: '👥 **客户列表**\n\n待查询\n\n功能开发中...' }
  }
  if (/流失|哪些客户.*没了/.test(message)) {
    return { reply: '⚠️ **客户流失预警**\n\n暂无流失\n\n功能开发中...' }
  }
  return { reply: '👥 客户功能开发中...\n支持：客户列表、跟进、流失预警' }
}
