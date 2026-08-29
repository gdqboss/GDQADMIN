// utils/audit.js — 审计日志工具
// 2026-08-12 立
import { pool } from '../db/connection.js'

export async function writeAuditLog(req, action, tableName, recordId, oldValue, newValue) {
  try {
    const profileId = req.user?.server_profile_id || 1
    await pool.execute(
      `INSERT INTO audit_logs
       (server_profile_id, user_id, username, action, table_name, record_id, old_value, new_value, ip_address, user_agent)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        profileId,
        req.user?.id || null,
        req.user?.username || null,
        action,
        tableName,
        recordId,
        oldValue ? JSON.stringify(oldValue) : null,
        newValue ? JSON.stringify(newValue) : null,
        req.ip || req.headers['x-forwarded-for'] || null,
        (req.headers['user-agent'] || '').substring(0, 500)
      ]
    )
  } catch (err) {
    console.error('[audit] write error:', err.message)
    // 审计失败不应阻塞主流程
  }
}