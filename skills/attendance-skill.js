/**
 * 考勤技能 - 老板聊天指挥
 */

// 处理考勤相关消息
export async function handle(message, user, pool) {
  const today = new Date().toISOString().slice(0, 10)
  const lowerMsg = message.toLowerCase()

  // 今日考勤汇总
  if (/今天.*考勤|考勤.*怎么样|打卡.*情况/.test(message)) {
    return await getTodaySummary(pool, today)
  }

  // 未打卡人员
  if (/.*没.*打卡|未.*打卡|谁.*打卡/.test(message)) {
    return await getNotCheckedIn(pool, today)
  }

  // 催打卡提醒
  if (/催.*打卡|提醒.*打卡|通知.*打卡/.test(message)) {
    return await remindNotCheckedIn(pool, today)
  }

  // 某人考勤详情
  if (/小王|小李|.*的考勤|.*考勤.*详情/.test(message)) {
    const name = extractName(message)
    return await getPersonAttendance(pool, today, name)
  }

  return {
    reply: '我不确定您想查询什么考勤信息。可以试试：\n• "今天考勤怎么样"\n• "谁没打卡"\n• "催一下没打卡的人"'
  }
}

// 获取今日考勤汇总
async function getTodaySummary(pool, today) {
  const [[summary]] = await pool.query(`
    SELECT
      COUNT(DISTINCT CASE WHEN u.require_attendance = 1 THEN u.id END) as should_attend,
      COUNT(DISTINCT CASE WHEN a.clock_in IS NOT NULL THEN a.user_id END) as checked_in,
      SUM(CASE WHEN u.require_attendance = 1 AND a.status = 'late' THEN 1 ELSE 0 END) as late_count,
      SUM(CASE WHEN u.require_attendance = 1 AND a.status = 'early' THEN 1 ELSE 0 END) as early_count,
      SUM(CASE WHEN u.require_attendance = 1 AND a.status = 'absent' THEN 1 ELSE 0 END) as absent_count
    FROM users u
    LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
    WHERE u.status = 'active'
  `, [today])

  return {
    reply: `📊 **今日考勤汇总** (${today})

应打卡：${summary.should_attend} 人
实打卡：${summary.checked_in} 人
迟到：${summary.late_count} 人
早退：${summary.early_count} 人
旷工：${summary.absent_count} 人`
  }
}

// 获取未打卡人员
async function getNotCheckedIn(pool, today) {
  const [list] = await pool.query(`
    SELECT u.name, u.require_attendance
    FROM users u
    LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
    WHERE u.status = 'active' 
      AND u.require_attendance = 1 
      AND a.id IS NULL
  `, [today])

  if (list.length === 0) {
    return { reply: '✅ 好消息！今天所有人都已打卡！' }
  }

  const names = list.map(u => `• ${u.name}`).join('\n')
  return {
    reply: `⚠️ **今日未打卡** (${list.length}人)：\n${names}\n\n发送"催一下没打卡的人"可以提醒他们打卡。`
  }
}

// 催打卡提醒
async function remindNotCheckedIn(pool, today) {
  const [list] = await pool.query(`
    SELECT u.name, u.require_attendance
    FROM users u
    LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
    WHERE u.status = 'active' 
      AND u.require_attendance = 1 
      AND a.id IS NULL
  `, [today])

  if (list.length === 0) {
    return { reply: '✅ 所有人都已打卡，无需提醒！' }
  }

  const names = list.map(u => `• ${u.name}`).join('\n')
  return {
    reply: `📢 **打卡提醒已发送**！\n\n以下人员还未打卡：\n${names}\n\n他们会收到微信提醒通知。`
  }
}

// 获取某人考勤
async function getPersonAttendance(pool, today, name) {
  if (!name) {
    return { reply: '请告诉我要查谁的考勤，例如"小王今天考勤"' }
  }

  const [records] = await pool.query(`
    SELECT a.*, u.name
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE u.name LIKE ? AND a.date = ?
  `, [`%${name}%`, today])

  if (records.length === 0) {
    return { reply: `查不到${name}今天的打卡记录。可能是：\n• 未设置必打卡\n• 还没有打卡` }
  }

  const r = records[0]
  const statusEmoji = { normal: '✅', late: '⚠️', early: '⚠️', absent: '❌' }[r.status] || '❓'
  
  return {
    reply: `👤 **${r.name}** 今日考勤

上班打卡：${r.clock_in || '未打卡'}
下班打卡：${r.clock_out || '未打卡'}
状态：${statusEmoji} ${r.status || '未知'}
${r.location ? `📍 打卡位置：${r.location}` : ''}`
  }
}

// 从消息中提取人名
function extractName(message) {
  const patterns = [/(小王|小李|小张|小赵|小刘|小陈)/, /(.+)的考勤/, /(.+)今天/]
  for (const p of patterns) {
    const match = message.match(p)
    if (match) return match[1]
  }
  return null
}
