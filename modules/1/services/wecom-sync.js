/**
 * WeCom data sync service
 * Handles syncing attendance, approvals, and contacts from WeCom to local DB.
 */

import { pool } from '../db/connection.js'
import * as wecom from './wecom.js'

/**
 * Format a Date or timestamp to YYYY-MM-DD in +08:00 timezone
 */
function toDateCN(ts) {
  return new Date(ts).toLocaleString('sv-SE', { timeZone: 'Asia/Shanghai' }).slice(0, 10)
}

/**
 * Format a timestamp to HH:MM:SS in +08:00 timezone
 */
function toTimeCN(ts) {
  return new Date(ts).toLocaleString('sv-SE', { timeZone: 'Asia/Shanghai' }).slice(11, 19)
}

/**
 * Log a sync operation (best-effort, never throws)
 */
async function logSync(type, status, recordsSynced, errorMessage, startedAt) {
  try {
    await pool.query(
      `INSERT INTO wecom_sync_log (type, status, records_synced, error_message, started_at, finished_at)
       VALUES (?, ?, ?, ?, ?, NOW())`,
      [type, status, recordsSynced, errorMessage, startedAt]
    )
  } catch (logErr) {
    console.error('[wecom-sync] Failed to write sync log:', logErr.message)
  }
}

/**
 * Sync attendance data from WeCom
 * @param {string} startDate - YYYY-MM-DD
 * @param {string} endDate - YYYY-MM-DD
 */
export async function syncAttendance(startDate, endDate) {
  const startedAt = new Date()
  let synced = 0

  try {
    // Get all WeCom user IDs that are linked to local users
    const [contacts] = await pool.query(
      'SELECT wecom_userid, user_id FROM wecom_contacts WHERE user_id IS NOT NULL AND status = 1'
    )
    if (contacts.length === 0) {
      await logSync('attendance', 'success', 0, null, startedAt)
      return { synced: 0 }
    }

    const startTime = Math.floor(new Date(startDate + 'T00:00:00+08:00').getTime() / 1000)
    const endTime = Math.floor(new Date(endDate + 'T23:59:59+08:00').getTime() / 1000)

    // Build userid -> user_id mapping
    const userMap = new Map()
    for (const c of contacts) {
      userMap.set(c.wecom_userid, c.user_id)
    }

    const userIds = contacts.map(c => c.wecom_userid)

    // Process in batches of 100
    for (let i = 0; i < userIds.length; i += 100) {
      const batch = userIds.slice(i, i + 100)
      const checkinData = await wecom.getCheckinData(batch, startTime, endTime)

      for (const record of checkinData) {
        const userId = userMap.get(record.userid)
        if (!userId) continue

        const date = toDateCN(record.checkin_time * 1000)
        const time = toTimeCN(record.checkin_time * 1000)
        const isClockIn = record.checkin_type === '上班打卡'

        if (isClockIn) {
          await pool.query(
            `INSERT INTO attendance (user_id, date, clock_in, status, location, wecom_synced)
             VALUES (?, ?, ?, 'normal', ?, TRUE)
             ON DUPLICATE KEY UPDATE clock_in = VALUES(clock_in), location = VALUES(location), wecom_synced = TRUE`,
            [userId, date, time, record.location_detail || '']
          )
        } else {
          await pool.query(
            `INSERT INTO attendance (user_id, date, clock_out, status, location, wecom_synced)
             VALUES (?, ?, ?, 'normal', ?, TRUE)
             ON DUPLICATE KEY UPDATE clock_out = VALUES(clock_out), wecom_synced = TRUE`,
            [userId, date, time, record.location_detail || '']
          )
        }
        synced++
      }
    }

    // Update attendance status based on clock_in/clock_out times
    await pool.query(
      `UPDATE attendance SET status = CASE
         WHEN clock_in IS NULL AND clock_out IS NULL THEN 'absent'
         WHEN clock_in > '09:00:00' THEN 'late'
         WHEN clock_out < '18:00:00' AND clock_out IS NOT NULL THEN 'early'
         ELSE 'normal'
       END
       WHERE date BETWEEN ? AND ? AND wecom_synced = TRUE`,
      [startDate, endDate]
    )

    await logSync('attendance', 'success', synced, null, startedAt)
    return { synced }
  } catch (err) {
    await logSync('attendance', 'failed', synced, err.message, startedAt)
    throw err
  }
}

/**
 * Sync approval records from WeCom
 * @param {string} startDate - YYYY-MM-DD
 * @param {string} endDate - YYYY-MM-DD
 */
export async function syncApprovals(startDate, endDate) {
  const startedAt = new Date()
  let synced = 0

  try {
    const startTime = Math.floor(new Date(startDate + 'T00:00:00+08:00').getTime() / 1000)
    const endTime = Math.floor(new Date(endDate + 'T23:59:59+08:00').getTime() / 1000)

    const spNos = await wecom.getApprovalList(startTime, endTime)

    for (const spNo of spNos) {
      const detail = await wecom.getApprovalDetail(spNo)
      if (!detail) continue

      // Map WeCom status to readable status
      const statusMap = { 1: 'pending', 2: 'approved', 3: 'rejected', 4: 'cancelled', 6: 'revoked' }
      const spStatus = statusMap[detail.sp_status] || 'pending'

      await pool.query(
        `INSERT INTO wecom_approvals (sp_no, sp_name, sp_status, apply_time, applicant_userid, data_json, synced_at)
         VALUES (?, ?, ?, FROM_UNIXTIME(?), ?, ?, NOW())
         ON DUPLICATE KEY UPDATE sp_status = VALUES(sp_status), data_json = VALUES(data_json), synced_at = NOW()`,
        [
          spNo,
          detail.sp_name || '',
          spStatus,
          detail.apply_time,
          detail.applyer?.userid || '',
          JSON.stringify(detail.apply_data || {})
        ]
      )
      synced++

      // If this is a leave approval, sync to leave_records
      if (detail.sp_name === '请假' && spStatus === 'approved') {
        await syncLeaveFromApproval(detail)
      }
    }

    await logSync('approvals', 'success', synced, null, startedAt)
    return { synced }
  } catch (err) {
    await logSync('approvals', 'failed', synced, err.message, startedAt)
    throw err
  }
}

/**
 * Sync a leave approval to leave_records
 */
async function syncLeaveFromApproval(detail) {
  const applicantUserId = detail.applyer?.userid
  if (!applicantUserId) return

  // Find local user by WeCom userid
  const [contacts] = await pool.query(
    'SELECT user_id FROM wecom_contacts WHERE wecom_userid = ? AND user_id IS NOT NULL',
    [applicantUserId]
  )
  if (contacts.length === 0) return

  const userId = contacts[0].user_id

  // Extract leave details from apply_data
  const contents = detail.apply_data?.contents || []
  let leaveType = '事假'
  let startDate = null
  let endDate = null
  let reason = ''
  let days = 1

  for (const item of contents) {
    if (item.control === 'Vacation') {
      const vacation = item.value?.vacation
      if (vacation) {
        leaveType = vacation.selector?.value?.[0]?.text || leaveType
        const dateRange = vacation.attendance?.date_range
        if (dateRange) {
          startDate = toDateCN(dateRange.new_begin * 1000)
          endDate = toDateCN(dateRange.new_end * 1000)
          days = dateRange.new_duration / 86400 || 1
        }
      }
    }
    if (item.control === 'Textarea') {
      reason = item.value?.text || ''
    }
  }

  if (!startDate || !endDate) return

  await pool.query(
    `INSERT INTO leave_records (user_id, type, start_date, end_date, days, reason, status, approver, created_at)
     VALUES (?, ?, ?, ?, ?, ?, 'approved', '企微审批', NOW())
     ON DUPLICATE KEY UPDATE status = 'approved'`,
    [userId, leaveType, startDate, endDate, days, reason]
  )
}

/**
 * Sync contacts (departments + users) from WeCom
 */
export async function syncContacts() {
  const startedAt = new Date()
  let synced = 0

  try {
    // Sync departments
    const departments = await wecom.getDepartmentList()
    for (const dept of departments) {
      await pool.query(
        `INSERT INTO wecom_departments (wecom_dept_id, name, parentid, dept_order, synced_at)
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE name = VALUES(name), parentid = VALUES(parentid), dept_order = VALUES(dept_order), synced_at = NOW()`,
        [dept.id, dept.name, dept.parentid || 0, dept.order || 0]
      )
      synced++
    }

    // Sync users from each department
    const seenUserIds = new Set()
    for (const dept of departments) {
      const users = await wecom.getUserListByDept(dept.id)
      for (const user of users) {
        if (seenUserIds.has(user.userid)) continue
        seenUserIds.add(user.userid)

        await pool.query(
          `INSERT INTO wecom_contacts (wecom_userid, name, department_ids, position, mobile, email, status, synced_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
           ON DUPLICATE KEY UPDATE name = VALUES(name), department_ids = VALUES(department_ids),
             position = VALUES(position), mobile = VALUES(mobile), email = VALUES(email),
             status = VALUES(status), synced_at = NOW()`,
          [
            user.userid,
            user.name,
            JSON.stringify(user.department || []),
            user.position || '',
            user.mobile || '',
            user.email || '',
            user.status || 1
          ]
        )
        synced++
      }
    }

    // Auto-link contacts to local users by email
    await pool.query(
      `UPDATE wecom_contacts wc
       INNER JOIN users u ON LOWER(wc.email) = LOWER(u.email)
       SET wc.user_id = u.id
       WHERE wc.user_id IS NULL AND wc.email != ''`
    )

    await logSync('contacts', 'success', synced, null, startedAt)
    return { synced }
  } catch (err) {
    await logSync('contacts', 'failed', synced, err.message, startedAt)
    throw err
  }
}
