export async function handle(message, user, pool) {
  if (/系统.*状态|还正常吗/.test(message)) {
    return { reply: '✅ **系统状态**\n\n运行正常\n\n功能开发中...' }
  }
  if (/考勤规则|上班时间/.test(message)) {
    return { reply: '⏰ **考勤规则**\n\n上班时间：09:00\n下班时间：18:00\n\n功能开发中...' }
  }
  return { reply: '⚙️ 系统设置功能开发中...\n支持：考勤规则、系统状态、数据导出' }
}
