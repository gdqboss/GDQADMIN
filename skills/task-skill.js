/**
 * 任务管理技能
 */
export async function handle(message, user, pool) {
  const lowerMsg = message.toLowerCase()
  
  // 派任务
  if (/派.*任务|给小王.*任务|给小李.*任务/.test(message)) {
    return {
      reply: `✅ **任务已派发**

任务内容：${message.match(/派.*任务[：:](.+)/)?.[1] || '待定'}
执行人：待指定
截止时间：待定

功能开发中...`
    }
  }
  
  // 查进度
  if (/任务.*完成|.*进度/.test(message)) {
    return { reply: '🔄 任务进度查询功能开发中...' }
  }
  
  // 催任务
  if (/催.*任务|提醒.*任务/.test(message)) {
    return { reply: '📢 任务提醒功能开发中...' }
  }
  
  return { reply: '✅ 任务管理功能开发中...\n支持：派任务、查进度、催任务' }
}
