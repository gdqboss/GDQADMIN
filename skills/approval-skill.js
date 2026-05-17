export async function handle(message, user, pool) {
  if (/待.*审批|需要.*审批|有哪些.*审批/.test(message)) {
    return { reply: '📋 **待审批列表**\n\n暂无待审批项\n\n功能开发中...' }
  }
  if (/同意|通过|批准/.test(message)) {
    return { reply: '✅ 审批已通过\n\n功能开发中...' }
  }
  return { reply: '📋 审批管理功能开发中...\n支持：待审批列表、执行审批' }
}
