import express from 'express';
import { pool } from '../db/connection.js';
import { auth } from '../middleware/auth.js';

const router = express.Router();

// =====================================================================
// 配置：4 类日志 → settings key + 源表 + 收件人字段提取
// (zero-hardcode: 不在代码里硬编 log type 名, 通过配置驱动)
// =====================================================================
const LOG_TYPE_CONFIG = {
  work_log: {
    settingKey: 'work_log:read_visibility',
    sourceTable: 'work_logs',
    creatorField: 'user_id',
    recipientsField: 'recipients',         // JSON array of user_ids
    fallbackRecipientsField: null
  },
  visit_log: {
    settingKey: 'visit_log:read_visibility',
    sourceTable: 'visit_logs',
    creatorField: 'user_id',
    recipientsField: null,                 // 无收件人 = 默认仅上级 + 创建者
    fallbackRecipientsField: null
  },
  share_log: {
    settingKey: 'share_log:read_visibility',
    sourceTable: 'share_logs',
    creatorField: 'user_id',
    recipientsField: null,
    fallbackRecipientsField: null
  },
  feedback: {
    settingKey: 'feedback:read_visibility',
    sourceTable: 'feedback_records',
    creatorField: 'user_id',
    recipientsField: 'recipients',
    fallbackRecipientsField: 'target_user_id'  // 被投诉人也算收件人
  }
};

// =====================================================================
// 工具：解析 JSON 字段（DB 里 longtext, 可能是 string/object/null）
// =====================================================================
function parseJsonField(val, defaultVal = []) {
  if (val == null) return defaultVal;
  if (typeof val === 'object') return val;
  try {
    const parsed = JSON.parse(val);
    return Array.isArray(parsed) ? parsed : defaultVal;
  } catch {
    return defaultVal;
  }
}

// =====================================================================
// 工具：取一条日志的可见人员集合 (creator + recipients + fallback)
// =====================================================================
async function getLogVisibilityPeople(logType, logId) {
  const cfg = LOG_TYPE_CONFIG[logType];
  if (!cfg) return { creator: null, recipients: [], source: null };

  const fields = [cfg.creatorField];
  if (cfg.recipientsField) fields.push(cfg.recipientsField);
  if (cfg.fallbackRecipientsField) fields.push(cfg.fallbackRecipientsField);

  const [rows] = await pool.query(
    `SELECT ${fields.join(', ')} FROM ${cfg.sourceTable} WHERE id = ?`,
    [logId]
  );
  if (rows.length === 0) return { creator: null, recipients: [], source: null };

  const row = rows[0];
  const creator = row[cfg.creatorField];

  const recipientSet = new Set();
  if (cfg.recipientsField && row[cfg.recipientsField]) {
    parseJsonField(row[cfg.recipientsField], []).forEach(id => recipientSet.add(Number(id)));
  }
  if (cfg.fallbackRecipientsField && row[cfg.fallbackRecipientsField]) {
    recipientSet.add(Number(row[cfg.fallbackRecipientsField]));
  }

  return {
    creator,
    recipients: [...recipientSet].filter(id => id != null),
    source: row
  };
}

// =====================================================================
// 工具：递归查询某 user 的所有上级链（直到 admin 或 supervisor_id=NULL）
// 用 MySQL 8 递归 CTE，循环上限 20 防呆
// 返回 Set<number>
// =====================================================================
async function getSupervisorChain(userId) {
  // 返回 userId 的整条正向上级链（userId → supervisor → ... → 直到 supervisor_id=NULL）
  // 注意：调用方需自行判断 userRole==='admin' 放行，这里不参与 admin 特殊化
  const chain = new Set();
  const [[user]] = await pool.query('SELECT id, supervisor_id FROM users WHERE id = ?', [userId]);
  if (!user) return chain;

  try {
    const [rows] = await pool.query(
      `WITH RECURSIVE sup_chain (id, supervisor_id, depth) AS (
        SELECT id, supervisor_id, 1 FROM users WHERE id = ?
        UNION ALL
        SELECT u.id, u.supervisor_id, sc.depth + 1
        FROM users u
        INNER JOIN sup_chain sc ON u.id = sc.supervisor_id
        WHERE sc.depth < 20
      )
      SELECT DISTINCT id FROM sup_chain WHERE id != ?`,
      [userId, userId]
    );
    rows.forEach(r => chain.add(r.id));
  } catch (e) {
    // MySQL < 8 不支持 CTE，降级为应用层递归
    let current = userId;
    let depth = 0;
    while (current && depth < 20) {
      const [[row]] = await pool.query('SELECT supervisor_id FROM users WHERE id = ?', [current]);
      if (!row || !row.supervisor_id) break;
      chain.add(row.supervisor_id);
      current = row.supervisor_id;
      depth++;
    }
  }
  return chain;
}

// =====================================================================
// 工具：判断 user 是否在 visibility setting 允许的范围内
// visibility 值: 'supervisor_only' | 'recipients_only' | 'supervisor_and_recipients' | 'all'
// =====================================================================
async function isUserAllowedToRead({ userId, userRole, logType, logId }) {
  // admin 例外: 直接放行
  if (userRole === 'admin') return true;

  const cfg = LOG_TYPE_CONFIG[logType];
  if (!cfg) return false;

  // 1. 读 visibility setting（带默认 fallback）
  const [[setting]] = await pool.query(
    'SELECT value FROM settings WHERE `key` = ?',
    [cfg.settingKey]
  );
  const visibility = setting?.value || 'supervisor_and_recipients';

  if (visibility === 'all') return true;

  // 2. 取日志创建者 + 收件人
  const { creator, recipients } = await getLogVisibilityPeople(logType, logId);
  if (creator == null) return false; // 日志不存在

  // 3. 创建者永远可以读自己的
  if (Number(creator) === Number(userId)) return true;

  // 4. 收件人判断
  const isRecipient = recipients.includes(Number(userId));

  // 5. 上级链判断（递归查 supervisor_id 链直到 admin）
  const supervisorChain = await getSupervisorChain(userId);
  const isSupervisor = supervisorChain.has(Number(creator)); // 创建者在我的上级链里 = 我是创建者的下级 = 我是收件人的上级

  switch (visibility) {
    case 'supervisor_only':
      return isSupervisor;
    case 'recipients_only':
      return isRecipient;
    case 'supervisor_and_recipients':
      return isSupervisor || isRecipient;
    default:
      return isSupervisor || isRecipient;
  }
}

// =====================================================================
// POST /api/log-interactions/read
// 标记一条日志为已读（钉钉模式：UNIQUE 去重，重复阅读不记）
// =====================================================================
router.post('/read', auth, async (req, res, next) => {
  try {
    const { log_type, log_id } = req.body;
    if (!log_type || !log_id) {
      return res.status(400).json({ code: 400, message: 'log_type and log_id required' });
    }
    if (!LOG_TYPE_CONFIG[log_type]) {
      return res.status(400).json({ code: 400, message: `invalid log_type: ${log_type}` });
    }

    // visibility 检查
    const allowed = await isUserAllowedToRead({
      userId: req.user.id,
      userRole: req.user.role,
      logType: log_type,
      logId: log_id
    });
    if (!allowed) {
      return res.status(403).json({
        code: 403,
        message: '当前日志阅读可见范围不允许您记录阅读'
      });
    }

    // 写入 log_reads（INSERT IGNORE 走 UNIQUE 去重）
    await pool.query(
      'INSERT IGNORE INTO log_reads (log_type, log_id, user_id, read_at) VALUES (?, ?, ?, NOW())',
      [log_type, log_id, req.user.id]
    );

    res.json({ code: 0, message: 'ok' });
  } catch (err) { next(err); }
});

// =====================================================================
// GET /api/log-interactions/readers/:log_type/:log_id
// 获取某条日志的已阅人员列表（带头像）
// =====================================================================
router.get('/readers/:log_type/:log_id', auth, async (req, res, next) => {
  try {
    const { log_type, log_id } = req.params;
    if (!LOG_TYPE_CONFIG[log_type]) {
      return res.status(400).json({ code: 400, message: `invalid log_type: ${log_type}` });
    }

    // 鉴权：用户必须能读到这条日志（visibility 范围内）
    const allowed = await isUserAllowedToRead({
      userId: req.user.id,
      userRole: req.user.role,
      logType: log_type,
      logId: log_id
    });
    if (!allowed) {
      return res.status(403).json({
        code: 403,
        message: '当前日志阅读可见范围不允许您查看已阅人员'
      });
    }

    const [rows] = await pool.query(
      `SELECT r.user_id, u.name, u.avatar, r.read_at
       FROM log_reads r
       JOIN users u ON r.user_id = u.id
       WHERE r.log_type = ? AND r.log_id = ?
       ORDER BY r.read_at DESC`,
      [log_type, log_id]
    );

    res.json({ code: 0, data: rows });
  } catch (err) { next(err); }
});

// =====================================================================
// POST /api/log-interactions/comments
// 添加评论（兼容旧 API，行为不变）
// =====================================================================
router.post('/comments', auth, async (req, res, next) => {
  try {
    const { log_id, parent_id = 0, content } = req.body;
    if (!log_id || !content) return res.status(400).json({ code: 400, message: 'log_id and content required' });

    const [result] = await pool.query(
      'INSERT INTO work_log_comments (log_id, user_id, parent_id, content) VALUES (?, ?, ?, ?)',
      [log_id, req.user.id, parent_id, content]
    );

    const [rows] = await pool.query(
      'SELECT c.*, u.name as user_name FROM work_log_comments c JOIN users u ON c.user_id = u.id WHERE c.id = ?',
      [result.insertId]
    );

    res.json({ code: 0, data: rows[0] });
  } catch (err) { next(err); }
});

// =====================================================================
// GET /api/log-interactions/comments/:log_id
// 获取日志评论列表（兼容旧 API）
// =====================================================================
router.get('/comments/:log_id', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT c.*, u.name as user_name, u.avatar,
       (SELECT COUNT(*) FROM work_log_likes WHERE target_type='comment' AND target_id=c.id) as like_count,
       (SELECT COUNT(*) FROM work_log_likes WHERE target_type='comment' AND target_id=c.id AND user_id=?) as liked
       FROM work_log_comments c
       JOIN users u ON c.user_id = u.id
       WHERE c.log_id = ?
       ORDER BY c.created_at ASC`,
      [req.user.id, req.params.log_id]
    );
    res.json({ code: 0, data: rows });
  } catch (err) { next(err); }
});

// =====================================================================
// POST /api/log-interactions/like
// 点赞/取消点赞（兼容旧 API）
// =====================================================================
router.post('/like', auth, async (req, res, next) => {
  try {
    const { target_type, target_id } = req.body;
    if (!target_type || !target_id) return res.status(400).json({ code: 400, message: 'target_type and target_id required' });

    const [[exist]] = await pool.query(
      'SELECT id FROM work_log_likes WHERE user_id=? AND target_type=? AND target_id=?',
      [req.user.id, target_type, target_id]
    );

    if (exist) {
      await pool.query(
        'DELETE FROM work_log_likes WHERE user_id=? AND target_type=? AND target_id=?',
        [req.user.id, target_type, target_id]
      );
      res.json({ code: 0, action: 'unliked' });
    } else {
      await pool.query(
        'INSERT INTO work_log_likes (user_id, target_type, target_id) VALUES (?, ?, ?)',
        [req.user.id, target_type, target_id]
      );
      res.json({ code: 0, action: 'liked' });
    }
  } catch (err) { next(err); }
});

// =====================================================================
// GET /api/log-interactions/likes/:target_type/:target_id
// 获取点赞数（兼容旧 API）
// =====================================================================
router.get('/likes/:target_type/:target_id', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT COUNT(*) as count FROM work_log_likes WHERE target_type=? AND target_id=?',
      [req.params.target_type, req.params.target_id]
    );
    const [[liked]] = await pool.query(
      'SELECT COUNT(*) as count FROM work_log_likes WHERE target_type=? AND target_id=? AND user_id=?',
      [req.params.target_type, req.params.target_id, req.user.id]
    );
    res.json({ code: 0, data: { count: rows[0].count, liked: liked.count > 0 } });
  } catch (err) { next(err); }
});

// =====================================================================
// POST /api/log-interactions/forward
// 转发日志给其他人（兼容旧 API）
// =====================================================================
router.post('/forward', auth, async (req, res, next) => {
  try {
    const { log_id, recipient_ids } = req.body;
    if (!log_id || !recipient_ids || !recipient_ids.length) {
      return res.status(400).json({ code: 400, message: 'log_id and recipient_ids required' });
    }

    const [logs] = await pool.query('SELECT * FROM work_logs WHERE id = ?', [log_id]);
    if (logs.length === 0) {
      return res.status(404).json({ code: 404, message: 'Log not found' });
    }
    const originalLog = logs[0];

    const results = [];
    for (const userId of recipient_ids) {
      const content = typeof originalLog.content === 'string' ? originalLog.content : JSON.stringify(originalLog.content);
      const participants = originalLog.participants ? (typeof originalLog.participants === 'string' ? originalLog.participants : JSON.stringify(originalLog.participants)) : null;
      const attachments = originalLog.attachments ? (typeof originalLog.attachments === 'string' ? originalLog.attachments : JSON.stringify(originalLog.attachments)) : null;

      const [result] = await pool.query(
        `INSERT INTO work_logs (user_id, log_type, template_id, submit_date, content, recipients, participants, location, gps_lat, gps_lng, attachments, status, forwarded_from, forwarded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [userId, originalLog.log_type, originalLog.template_id, originalLog.submit_date, content, JSON.stringify([userId]), participants, originalLog.location, originalLog.gps_lat, originalLog.gps_lng, attachments, 'received', log_id, req.user.id]
      );
      results.push(result.insertId);
    }

    res.json({ code: 0, data: { created: results.length } });
  } catch (err) { next(err); }
});

// =====================================================================
// GET /api/log-interactions/badges
// 获取用户徽章数量（兼容旧 API）
// =====================================================================
router.get('/badges', auth, async (req, res, next) => {
  try {
    const today = new Date().toISOString().slice(0, 10);
    const userId = req.user.id;

    const [[att]] = await pool.query(
      'SELECT COUNT(*) as count FROM attendance WHERE user_id = ? AND date = ?',
      [userId, today]
    );
    const attendanceBadge = att.count > 0 ? 0 : 1;

    const [unreadLogs] = await pool.query(
      'SELECT COUNT(*) as count FROM work_logs WHERE FIND_IN_SET(?, recipients) > 0 AND submit_date >= ?',
      [userId, today]
    );
    const [[unreadComments]] = await pool.query(
      'SELECT COUNT(*) as count FROM work_log_comments WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
      [userId]
    );
    const logBadge = (unreadLogs[0]?.count || 0) + (unreadComments[0]?.count || 0);

    const [[tasks]] = await pool.query(
      'SELECT COUNT(*) as count FROM tasks WHERE assignee_id = ? AND status IN (pending, in_progress)',
      [userId]
    );
    const taskBadge = tasks.count || 0;

    res.json({ code: 0, data: { attendanceBadge, logBadge, taskBadge } });
  } catch (err) { next(err); }
});

export default router;