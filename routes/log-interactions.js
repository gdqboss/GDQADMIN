import express from 'express';
import { pool } from '../db/connection.js';
import { auth } from '../middleware/auth.js';

const router = express.Router();

// 标记日志为已读
router.post('/read', auth, async (req, res, next) => {
  try {
    const { log_id } = req.body;
    if (!log_id) return res.status(400).json({ code: 400, message: 'log_id required' });

    await pool.query(
      'INSERT IGNORE INTO work_log_reads (work_log_id, user_id, is_read, read_at) VALUES (?, ?, 1, NOW())',
      [log_id, req.user.id]
    );

    res.json({ code: 0, message: 'ok' });
  } catch (err) { next(err); }
});

// 获取日志已阅人员列表
router.get('/readers/:log_id', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT r.user_id, u.name, r.read_at FROM work_log_reads r JOIN users u ON r.user_id = u.id WHERE r.work_log_id = ? ORDER BY r.read_at DESC',
      [req.params.log_id]
    );
    res.json({ code: 0, data: rows });
  } catch (err) { next(err); }
});

// 添加评论
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

// 获取日志评论列表
router.get('/comments/:log_id', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT c.*, u.name as user_name,
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

// 点赞/取消点赞
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

// 获取日志点赞数
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

// 转发日志给其他人
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
      // content已经是JSON对象，直接使用
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

export default router;

// 获取用户徽章数量
router.get('/badges', auth, async (req, res, next) => {
  try {
    const today = new Date().toISOString().slice(0, 10)
    const userId = req.user.id
    
    // 1. 考勤徽章 - 检查今日是否已签到
    const [[att]] = await pool.query(
      'SELECT COUNT(*) as count FROM attendance WHERE user_id = ? AND date = ?',
      [userId, today]
    )
    const attendanceBadge = att.count > 0 ? 0 : 1  // 未签到则显示徽章
    
    // 2. 日志徽章 - 未读的收到的日志 + 未读的评论点赞
    const [unreadLogs] = await pool.query(
      'SELECT COUNT(*) as count FROM work_logs WHERE FIND_IN_SET(?, recipients) > 0 AND submit_date >= ?',
      [userId, today]
    )
    const [[unreadComments]] = await pool.query(
      'SELECT COUNT(*) as count FROM work_log_comments WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
      [userId]
    )
    const logBadge = (unreadLogs[0]?.count || 0) + (unreadComments[0]?.count || 0)
    
    // 3. 任务徽章 - 待处理任务数
    const [[tasks]] = await pool.query(
      'SELECT COUNT(*) as count FROM tasks WHERE assignee_id = ? AND status IN (pending, in_progress)',
      [userId]
    )
    const taskBadge = tasks.count || 0
    
    res.json({ code: 0, data: { attendanceBadge, logBadge, taskBadge } })
  } catch (err) { next(err) }
});
