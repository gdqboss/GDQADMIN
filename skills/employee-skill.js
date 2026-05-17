export async function handle(message, user, pool) {
  if (/员工列表|有哪些员工|员工.*多少/.test(message)) {
    return { reply: '👤 **员工列表**\n\n待查询\n\n功能开发中...' }
  }
  if (/新增|添加|招聘/.test(message)) {
    return { reply: '✅ **新增员工**\n\n请告诉我：姓名、电话、职位\n\n功能开发中...' }
  }
  return { reply: '👤 员工管理功能开发中...\n支持：员工列表、新增、离职' }
}
