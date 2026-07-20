import express from 'express';
import { pool } from '../db/connection.js';
import { auth } from '../middleware/auth.js';
import { PERMISSIONS, ROLES, requirePermission } from '../middleware/rbac.js';
import { checkPerm } from '../utils/permission.js';

const router = express.Router();

// 全局工具函数
function safeParse(str, defaultVal = {}) {
  if (!str) return defaultVal;
  try {
    return typeof str === 'object' ? str : JSON.parse(str);
  } catch (e) {
    return defaultVal;
  }
}

// All routes require authentication
router.use(auth);

// ==================== Template Management ====================

// GET /api/work-logs/templates - Get all templates
router.get('/templates', async (req, res, next) => {
  try {
    const { status } = req.query;

    let sql = 'SELECT * FROM work_log_templates WHERE 1=1';
    const params = [];

    if (status) {
      sql += ' AND status = ?';
      params.push(status);
    }

    sql += ' ORDER BY is_default DESC, id ASC';

    const [templates] = await pool.query(sql, params);

    res.json({
      code: 0,
      data: templates,
      message: 'ok'
    });
  } catch (err) {
    next(err);
  }
});

// Validate template fields structure
function validateTemplateFields(fields) {
  if (!Array.isArray(fields)) return false

  const validTypes = [
    'text', 'number', 'date', 'time', 'time_range', 'datetime', 'recipients', 'target_user',
    'textarea', 'select', 'checkbox', 'radio',
    'location', 'image', 'participants', 'rating', 'complainants'
  ]

  for (const field of fields) {
    if (!field.label || !field.type) return false
    if (!validTypes.includes(field.type)) return false

    // select/radio/checkbox must have options
    if (['select', 'radio', 'checkbox'].includes(field.type)) {
      if (field.options && !Array.isArray(field.options)) return false
    }

    // Validate optional feature flags
    if (field.enableLocation !== undefined && typeof field.enableLocation !== 'boolean') return false
    if (field.enableImages !== undefined && typeof field.enableImages !== 'boolean') return false
    if (field.enableParticipants !== undefined && typeof field.enableParticipants !== 'boolean') return false
  }

  return true
}

// POST /api/work-logs/templates - Create template
router.post('/templates', requirePermission(PERMISSIONS.WORK_LOG_TEMPLATE_MANAGE), async (req, res, next) => {
  try {
    const { name, fields, is_default = false, status = 'active', log_type = 'work', description = '' } = req.body;

    if (!name || !fields) {
      return res.status(400).json({
        code: 400,
        message: 'Name and fields are required'
      });
    }

    if (!validateTemplateFields(fields)) {
      return res.status(400).json({
        code: 400,
        message: 'Invalid fields structure'
      });
    }

    const [result] = await pool.query(
      `INSERT INTO work_log_templates (name, creator_id, fields, is_default, status, log_type, description)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [name, req.user.id, JSON.stringify(fields), is_default, status, log_type, description]
    );

    res.json({
      code: 0,
      data: { id: result.insertId },
      message: 'Template created successfully'
    });
  } catch (err) {
    next(err);
  }
});

// PUT /api/work-logs/templates/:id - Update template
router.put('/templates/:id', requirePermission(PERMISSIONS.WORK_LOG_TEMPLATE_MANAGE), async (req, res, next) => {
  try {
    const { id } = req.params;
    const { name, fields, is_default, status, log_type, description } = req.body;

    const updates = [];
    const params = [];

    if (name !== undefined) {
      updates.push('name = ?');
      params.push(name);
    }
    if (fields !== undefined) {
      if (!validateTemplateFields(fields)) {
        return res.status(400).json({
          code: 400,
          message: 'Invalid fields structure'
        });
      }
      updates.push('fields = ?');
      params.push(JSON.stringify(fields));
    }
    if (is_default !== undefined) {
      updates.push('is_default = ?');
      params.push(is_default);
    }
    if (status !== undefined) {
      updates.push('status = ?');
      params.push(status);
    }
    if (log_type !== undefined) {
      updates.push('log_type = ?');
      params.push(log_type);
    }
    if (description !== undefined) {
      updates.push('description = ?');
      params.push(description);
    }

    if (updates.length === 0) {
      return res.status(400).json({
        code: 400,
        message: 'No fields to update'
      });
    }

    params.push(id);

    const [result] = await pool.query(
      `UPDATE work_log_templates SET ${updates.join(', ')} WHERE id = ?`,
      params
    );

    if (result.affectedRows === 0) {
      return res.status(404).json({
        code: 404,
        message: 'Template not found'
      });
    }

    res.json({
      code: 0,
      message: 'Template updated successfully'
    });
  } catch (err) {
    next(err);
  }
});

// DELETE /api/work-logs/templates/:id - Delete template
router.delete('/templates/:id', requirePermission(PERMISSIONS.WORK_LOG_TEMPLATE_MANAGE), async (req, res, next) => {
  try {
    const { id } = req.params;

    const [result] = await pool.query(
      'DELETE FROM work_log_templates WHERE id = ?',
      [id]
    );

    if (result.affectedRows === 0) {
      return res.status(404).json({
        code: 404,
        message: 'Template not found'
      });
    }

    res.json({
      code: 0,
      message: 'Template deleted successfully'
    });
  } catch (err) {
    next(err);
  }
});

// POST /api/work-logs/templates/init-defaults - Initialize preset templates (admin only)
router.post('/templates/init-defaults', async (req, res, next) => {
  try {
    if (!(await checkPerm(req, 'work_log:write'))) {
      return res.status(403).json({ code: 403, message: 'Admin permission required' })
    }

    const defaults = [
      {
        name: '日报模板',
        log_type: 'work',
        description: '每日工作汇报',
        fields: [
          { name: 'title', label: '标题', type: 'text', required: true },
          { name: 'submit_date', label: '日期', type: 'date', required: true },
          { name: 'today_work', label: '今日工作内容', type: 'textarea', required: true },
          { name: 'tomorrow_plan', label: '明日计划', type: 'textarea', required: false },
          { name: 'participants', label: '参与人', type: 'participants', required: false },
          { name: 'recipients', label: '收件人', type: 'recipients', required: false }
        ]
      },
      {
        name: '周报模板',
        log_type: 'work',
        description: '每周工作总结',
        fields: [
          { name: 'title', label: '标题', type: 'text', required: true },
          { name: 'week_summary', label: '本周工作总结', type: 'textarea', required: true },
          { name: 'next_week_plan', label: '下周计划', type: 'textarea', required: true },
          { name: 'completion', label: '完成度', type: 'rating', maxRating: 5, required: false },
          { name: 'participants', label: '参与人', type: 'participants', required: false },
          { name: 'recipients', label: '收件人', type: 'recipients', required: false }
        ]
      },
      {
        name: '拜访记录模板',
        log_type: 'work',
        description: '客户拜访记录',
        fields: [
          { name: 'customer', label: '客户名称', type: 'text', required: true },
          { name: 'visit_time', label: '拜访时间', type: 'time_range', required: true },
          { name: 'location', label: '拜访地点', type: 'location', required: false },
          { name: 'content', label: '拜访内容', type: 'textarea', required: true },
          { name: 'photos', label: '现场拍照', type: 'image', maxCount: 9, required: false },
          { name: 'participants', label: '参与人', type: 'participants', required: false },
          { name: 'recipients', label: '收件人', type: 'recipients', required: false }
        ]
      }
    ]

    let created = 0
    for (const tpl of defaults) {
      // 检查是否已存在同名模板
      const [[existing]] = await pool.query(
        'SELECT id FROM work_log_templates WHERE name = ?', [tpl.name]
      )
      if (!existing) {
        await pool.query(
          `INSERT INTO work_log_templates (name, creator_id, fields, is_default, status, log_type, description)
           VALUES (?, ?, ?, 1, 'active', ?, ?)`,
          [tpl.name, req.user.id, JSON.stringify(tpl.fields), tpl.log_type, tpl.description]
        )
        created++
      }
    }

    res.json({ code: 0, data: { created }, message: `${created} default templates created` })
  } catch (err) { next(err) }
})

// ==================== Work Logs ====================

// POST /api/work-logs - Submit work log
router.post('/', async (req, res, next) => {
  try {
    const { template_id, content, recipients, attachments, status = 'submitted', location, gps_lat, gps_lng, participants, date, submit_date } = req.body;

    if (!template_id || !content) {
      return res.status(400).json({
        code: 400,
        message: 'Template ID and content are required'
      });
    }

    // 检查重复提交 - 5分钟内相同内容的日志
    // 规范化content为字符串进行比较
    let contentStr;
    if (typeof content === 'object') {
      // 按key排序后序列化，避免key顺序不同导致比较失败
      const sorted = {};
      Object.keys(content).sort().forEach(k => sorted[k] = content[k]);
      contentStr = JSON.stringify(sorted);
    } else {
      contentStr = content;
    }
    
    const [recentLogs] = await pool.query(
      `SELECT id, created_at, content FROM work_logs 
       WHERE user_id = ? AND template_id = ? 
       AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
       ORDER BY created_at DESC LIMIT 1`,
      [req.user.id, template_id]
    );
    
    if (recentLogs.length > 0) {
      let recentContentStr;
      if (typeof recentLogs[0].content === 'string') {
        try {
          const parsed = JSON.parse(recentLogs[0].content);
          const sorted = {};
          Object.keys(parsed).sort().forEach(k => sorted[k] = parsed[k]);
          recentContentStr = JSON.stringify(sorted);
        } catch {
          recentContentStr = recentLogs[0].content;
        }
      } else {
        const sorted = {};
        Object.keys(recentLogs[0].content).sort().forEach(k => sorted[k] = recentLogs[0].content[k]);
        recentContentStr = JSON.stringify(sorted);
      }
      if (contentStr === recentContentStr) {
        return res.status(429).json({
          code: 429,
          message: '请勿重复提交内容相同的日志，请稍后再试或修改内容后提交'
        });
      }
    }

    // Verify template exists
    const [templates] = await pool.query(
      'SELECT id FROM work_log_templates WHERE id = ?',
      [template_id]
    );

    if (templates.length === 0) {
      return res.status(404).json({
        code: 404,
        message: 'Template not found'
      });
    }

    const [result] = await pool.query(
      `INSERT INTO work_logs (user_id, template_id, submit_date, content, recipients, attachments, status, location, gps_lat, gps_lng, participants)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        req.user.id,
        template_id,
        submit_date || date || new Date().toISOString().split('T')[0],
        typeof content === "string" ? content : JSON.stringify(content),
        JSON.stringify(recipients || []),
        JSON.stringify(attachments || []),
        status,
        location || null,
        gps_lat || null,
        gps_lng || null,
        JSON.stringify(participants || [])
      ]
    );

    res.json({
      code: 0,
      data: { id: result.insertId },
      message: 'Work log created successfully'
    });
  } catch (err) {
    next(err);
  }
});

// GET /api/work-logs - Get work logs
router.get('/', async (req, res, next) => {
  try {
    // Admin/Manager默认看全部日志，普通人看自己的
    const isAdmin = [ROLES.ADMIN, ROLES.MANAGER, ROLES.DIRECTOR].includes(req.user.role);
    
    const {
      type,
      status,
      date_from,
      date_to,
      page = 1,
      limit = 20
    } = req.query;
    
    // 默认类型：根据角色决定
    const logType = type || (isAdmin ? 'all' : 'mine');

    const offset = (parseInt(page) - 1) * parseInt(limit);

    let sql = `
      SELECT
        wl.*,
        u.name as creator_name,
        u.avatar as creator_avatar,
        u.department as creator_department,
        wlt.name as template_name,
        (SELECT COUNT(*) FROM work_log_interactions WHERE log_id = wl.id AND type = 'like') as like_count,
        (SELECT COUNT(*) FROM work_log_interactions WHERE log_id = wl.id AND type = 'dislike') as dislike_count,
        (SELECT COUNT(*) FROM work_log_interactions WHERE log_id = wl.id AND type = 'forward') as forward_count,
        (SELECT COUNT(*) FROM work_log_interactions WHERE log_id = wl.id AND type = 'comment') as comment_count,
        EXISTS(SELECT 1 FROM work_log_interactions WHERE log_id = wl.id AND user_id = ? AND type = 'like') as liked_by_me,
        EXISTS(SELECT 1 FROM work_log_interactions WHERE log_id = wl.id AND user_id = ? AND type = 'dislike') as disliked_by_me
      FROM work_logs wl
      LEFT JOIN users u ON wl.user_id = u.id
      LEFT JOIN work_log_templates wlt ON wl.template_id = wlt.id
      WHERE 1=1
    `;
    const params = [req.user.id, req.user.id];

    // Filter by type
    if (logType === 'mine') {
      sql += ' AND wl.user_id = ?';
      params.push(req.user.id);
    } else if (logType === 'received') {
      sql += ' AND JSON_CONTAINS(wl.recipients, ?)';
      params.push(JSON.stringify(req.user.id));
    } else if (logType === 'all') {
      // Admin can see all logs - no additional filter
    }

    // Filter by status
    if (status) {
      sql += ' AND wl.status = ?';
      params.push(status);
    }

    // Filter by date range
    if (date_from) {
      sql += ' AND DATE(wl.created_at) >= ?';
      params.push(date_from);
    }
    if (date_to) {
      sql += ' AND DATE(wl.created_at) <= ?';
      params.push(date_to);
    }

    // Get total count - build a separate count query
    let countSql = `SELECT COUNT(*) as total FROM work_logs wl WHERE 1=1`;
    const countParams = [];
    
    if (logType === 'mine') {
      countSql += ' AND wl.user_id = ?';
      countParams.push(req.user.id);
    } else if (logType === 'received') {
      countSql += ' AND JSON_CONTAINS(wl.recipients, ?)';
      countParams.push(JSON.stringify(req.user.id));
    }
    // 'all' has no additional filter
    
    if (status) {
      countSql += ' AND wl.status = ?';
      countParams.push(status);
    }
    if (date_from) {
      countSql += ' AND DATE(wl.created_at) >= ?';
      countParams.push(date_from);
    }
    if (date_to) {
      countSql += ' AND DATE(wl.created_at) <= ?';
      countParams.push(date_to);
    }
    
    const [countResult] = await pool.query(countSql, countParams);
    const total = countResult?.[0]?.total ?? 0;

    // Get paginated results
    sql += ' ORDER BY wl.created_at DESC LIMIT ? OFFSET ?';
    params.push(parseInt(limit), offset);

    const [logs] = await pool.query(sql, params);

    const parsedLogs = logs.map(log => ({
      ...log,
      content: safeParse(log.content),
      recipients: safeParse(log.recipients),
      attachments: safeParse(log.attachments),
      // 兜底：JOIN 不到时用 user_id 当显示名（修 2026-07-16 "看不出谁写的"）
      creator_name: log.creator_name || `用户#${log.user_id}`,
      creator_department: log.creator_department || ''
    }));

    res.json({
      code: 0,
      data: {
        logs: parsedLogs,
        total,
        page: parseInt(page),
        limit: parseInt(limit)
      },
      message: 'ok'
    });
  } catch (err) {
    next(err);
  }
});

// GET /api/work-logs/:id - Get log detail
router.get('/:id', async (req, res, next) => {
  try {
    const { id } = req.params;

    const [logs] = await pool.query(
      `SELECT
        wl.*,
        u.name as creator_name,
        u.avatar as creator_avatar,
        u.department as creator_department,
        wlt.name as template_name,
        wlt.fields as template_fields
      FROM work_logs wl
      LEFT JOIN users u ON wl.user_id = u.id
      LEFT JOIN work_log_templates wlt ON wl.template_id = wlt.id
      WHERE wl.id = ?`,
      [id]
    );

    if (logs.length === 0) {
      return res.status(404).json({
        code: 404,
        message: 'Work log not found'
      });
    }

    const log = logs[0];
    // 兜底：JOIN miss 时显示"用户#user_id"（修 2026-07-16 BJ 详情缺作者）
    log.creator_name = log.creator_name || `用户#${log.user_id}`;
    log.creator_department = log.creator_department || '';

    // Check permission: creator or recipient or admin
    const isAdmin = [ROLES.ADMIN, ROLES.MANAGER, ROLES.DIRECTOR].includes(req.user.role);
    const recipients = safeParse(log.recipients);
    if (!isAdmin && log.user_id !== req.user.id && !recipients.includes(req.user.id)) {
      return res.status(403).json({
        code: 403,
        message: 'Access denied'
      });
    }

    // Parse JSON fields
    log.content = safeParse(log.content);
    log.recipients = recipients;
    log.attachments = safeParse(log.attachments);
    log.participants = safeParse(log.participants || '[]');
    log.template_fields = safeParse(log.template_fields);

    // Get participant names
    if (log.participants.length > 0) {
      const [participantUsers] = await pool.query(
        `SELECT id, name FROM users WHERE id IN (?)`,
        [log.participants]
      );
      log.participant_names = participantUsers.map(u => u.name);
    } else {
      log.participant_names = [];
    }

    res.json({
      code: 0,
      data: log,
      message: 'ok'
    });
  } catch (err) {
    next(err);
  }
});

// PUT /api/work-logs/:id - Update log (only draft logs, only by creator)
router.put('/:id', async (req, res, next) => {
  try {
    const { id } = req.params;
    const { content, recipients, attachments, status } = req.body;

    // Check if log exists and is owned by user
    const [logs] = await pool.query(
      'SELECT user_id, status FROM work_logs WHERE id = ?',
      [id]
    );

    if (logs.length === 0) {
      return res.status(404).json({
        code: 404,
        message: 'Work log not found'
      });
    }

    const log = logs[0];

    // Check if log has interactions (replies/likes/dislikes/forwards)
    // If yes, only admin can edit
    const [interactions] = await pool.query(
      `SELECT COUNT(*) as cnt FROM work_log_interactions
       WHERE log_id = ? AND type IN ('comment','like','dislike','forward')`,
      [id]
    );
    if (interactions[0].cnt > 0 && !(await checkPerm(req, 'work_log:write'))) {
      return res.status(403).json({
        code: 403,
        message: '此日志已有互动，仅管理员可编辑'
      });
    }

    if (log.user_id !== req.user.id) {
      return res.status(403).json({
        code: 403,
        message: 'Only creator can update work log'
      });
    }

    // Allow updating all logs (submitted or draft), only creator can edit
    const updates = [];
    const params = [];

    if (content !== undefined) {
      updates.push('content = ?');
      params.push(JSON.stringify(content));
    }
    if (recipients !== undefined) {
      updates.push('recipients = ?');
      params.push(JSON.stringify(recipients));
    }
    if (attachments !== undefined) {
      updates.push('attachments = ?');
      params.push(JSON.stringify(attachments));
    }
    if (status !== undefined) {
      updates.push('status = ?');
      params.push(status);
    }

    if (updates.length === 0) {
      return res.status(400).json({
        code: 400,
        message: 'No fields to update'
      });
    }

    params.push(id);

    await pool.query(
      `UPDATE work_logs SET ${updates.join(', ')} WHERE id = ?`,
      params
    );

    res.json({
      code: 0,
      message: 'Work log updated successfully'
    });
  } catch (err) {
    next(err);
  }
});

// DELETE /api/work-logs/:id - Delete log (only by creator)
router.delete('/:id', async (req, res, next) => {
  try {
    const { id } = req.params;

    // Check if log exists and is owned by user
    const [logs] = await pool.query(
      'SELECT user_id FROM work_logs WHERE id = ?',
      [id]
    );

    if (logs.length === 0) {
      return res.status(404).json({
        code: 404,
        message: 'Work log not found'
      });
    }

    // Check if log has interactions - if yes, only admin can delete
    const [interactions] = await pool.query(
      `SELECT COUNT(*) as cnt FROM work_log_interactions
       WHERE log_id = ? AND type IN ('comment','like','dislike','forward')`,
      [id]
    );
    if (interactions[0].cnt > 0 && req.user.role !== ROLES.ADMIN) {
      return res.status(403).json({
        code: 403,
        message: '此日志已有互动，仅管理员可删除'
      });
    }

    if (logs[0].user_id !== req.user.id && req.user.role !== ROLES.ADMIN) {
      return res.status(403).json({
        code: 403,
        message: '仅创建者或管理员可删除'
      });
    }

    await pool.query('DELETE FROM work_logs WHERE id = ?', [id]);

    res.json({
      code: 0,
      message: 'Work log deleted successfully'
    });
  } catch (err) {
    next(err);
  }
});

// ==================== Interactions ====================

// POST /api/work-logs/:id/read - Mark as read
router.post('/:id/read', async (req, res, next) => {
  try {
    const { id } = req.params;

    // Check if log exists and user is recipient
    const [logs] = await pool.query(
      'SELECT recipients FROM work_logs WHERE id = ?',
      [id]
    );

    if (logs.length === 0) {
      return res.status(404).json({
        code: 404,
        message: 'Work log not found'
      });
    }

    const recipients = safeParse(logs[0].recipients);
    if (!recipients.includes(req.user.id)) {
      return res.status(403).json({
        code: 403,
        message: 'Only recipients can mark as read'
      });
    }

    // Check if already marked as read
    const [existing] = await pool.query(
      'SELECT id FROM work_log_interactions WHERE log_id = ? AND user_id = ? AND type = ?',
      [id, req.user.id, 'read']
    );

    if (existing.length > 0) {
      return res.json({
        code: 0,
        message: 'Already marked as read'
      });
    }

    await pool.query(
      'INSERT INTO work_log_interactions (log_id, user_id, type) VALUES (?, ?, ?)',
      [id, req.user.id, 'read']
    );

    res.json({
      code: 0,
      message: 'Marked as read'
    });
  } catch (err) {
    next(err);
  }
});

// POST /api/work-logs/:id/comment - Add comment
router.post('/:id/comment', async (req, res, next) => {
  try {
    const { id } = req.params;
    const { content } = req.body;

    if (!content) {
      return res.status(400).json({
        code: 400,
        message: 'Content is required'
      });
    }

    // Check if log exists and user has access
    const [logs] = await pool.query(
      'SELECT user_id, recipients FROM work_logs WHERE id = ?',
      [id]
    );

    if (logs.length === 0) {
      return res.status(404).json({
        code: 404,
        message: 'Work log not found'
      });
    }

    const log = logs[0];
    const recipients = safeParse(log.recipients);

    if (log.user_id !== req.user.id && !recipients.includes(req.user.id)) {
      return res.status(403).json({
        code: 403,
        message: 'Access denied'
      });
    }

    const [result] = await pool.query(
      'INSERT INTO work_log_interactions (log_id, user_id, type, content) VALUES (?, ?, ?, ?)',
      [id, req.user.id, 'comment', content]
    );

    res.json({
      code: 0,
      data: { id: result.insertId },
      message: 'Comment added successfully'
    });
  } catch (err) {
    if (err.code === 'ER_DUP_ENTRY') {
      return res.status(400).json({
        code: 400,
        message: '您已经评论过此日志，每条日志只能评论一次'
      });
    }
    next(err);
  }
});

// POST /api/work-logs/:id/like - Like/unlike toggle
router.post('/:id/like', async (req, res, next) => {
  try {
    const { id } = req.params;

    // Check if log exists and user has access
    const [logs] = await pool.query(
      'SELECT user_id, recipients FROM work_logs WHERE id = ?',
      [id]
    );

    if (logs.length === 0) {
      return res.status(404).json({
        code: 404,
        message: 'Work log not found'
      });
    }

    const log = logs[0];
    const recipients = safeParse(log.recipients);

    if (log.user_id !== req.user.id && !recipients.includes(req.user.id)) {
      return res.status(403).json({
        code: 403,
        message: 'Access denied'
      });
    }

    // Check if already liked
    const [existing] = await pool.query(
      'SELECT id FROM work_log_interactions WHERE log_id = ? AND user_id = ? AND type = ?',
      [id, req.user.id, 'like']
    );

    if (existing.length > 0) {
      // Unlike
      await pool.query(
        'DELETE FROM work_log_interactions WHERE id = ?',
        [existing[0].id]
      );

      return res.json({
        code: 0,
        data: { liked: false },
        message: 'Unliked'
      });
    } else {
      // Like
      await pool.query(
        'INSERT INTO work_log_interactions (log_id, user_id, type) VALUES (?, ?, ?)',
        [id, req.user.id, 'like']
      );

      return res.json({
        code: 0,
        data: { liked: true },
        message: 'Liked'
      });
    }
  } catch (err) {
    next(err);
  }
});

// GET /api/work-logs/:id/interactions - Get all interactions for a log
// GET /api/work-logs/:id/interactions - 拉评论/点赞/反对/转发列表
// 修 2026-07-17 BJ "评论回复不见了" — 互动信息是公司内部业务,
// 所有登录用户都能查看(对齐"无权限不显示"原则 — 评论对全员可见)
// 但仍用 requirePermission('work_log:read') 兜底,防止无权限用户偷看
router.get('/:id/interactions', requirePermission('work_log:read'), async (req, res, next) => {
  try {
    const { id } = req.params;

    // Check if log exists
    const [logs] = await pool.query(
      'SELECT id, user_id, recipients FROM work_logs WHERE id = ?',
      [id]
    );

    if (logs.length === 0) {
      return res.status(404).json({
        code: 404,
        message: 'Work log not found'
      });
    }

    // 修 2026-07-17 BJ "评论回复不见了" — 互动信息(评论/点赞)是公司内部业务信息,
    // 不是私密数据,所有登录用户都应该能查看(对齐"无权限不显示,出现即可用"原则 — 评论对全员可见)
    // 仍保留 work_log:read 权限检查作为安全网(中间件 requirePermission 在 mount 时已加)
    const [interactions] = await pool.query(
      `SELECT
        wli.*,
        u.name
      FROM work_log_interactions wli
      LEFT JOIN users u ON wli.user_id = u.id
      WHERE wli.log_id = ?
      ORDER BY wli.created_at ASC`,
      [id]
    );

    res.json({
      code: 0,
      data: interactions,
      message: 'ok'
    });
  } catch (err) {
    next(err);
  }
});

// POST /api/work-logs/:id/dislike - Dislike/unlike toggle
router.post('/:id/dislike', async (req, res, next) => {
  try {
    const { id } = req.params;

    const [logs] = await pool.query(
      'SELECT user_id, recipients FROM work_logs WHERE id = ?',
      [id]
    );

    if (logs.length === 0) {
      return res.status(404).json({ code: 404, message: 'Work log not found' });
    }

    const log = logs[0];
    const recipients = safeParse(log.recipients);
    if (log.user_id !== req.user.id && !recipients.includes(req.user.id)) {
      return res.status(403).json({ code: 403, message: 'Access denied' });
    }

    const [existing] = await pool.query(
      'SELECT id FROM work_log_interactions WHERE log_id = ? AND user_id = ? AND type = ?',
      [id, req.user.id, 'dislike']
    );

    if (existing.length > 0) {
      await pool.query('DELETE FROM work_log_interactions WHERE id = ?', [existing[0].id]);
      return res.json({ code: 0, data: { disliked: false }, message: 'Undisliked' });
    } else {
      await pool.query(
        'INSERT INTO work_log_interactions (log_id, user_id, type) VALUES (?, ?, ?)',
        [id, req.user.id, 'dislike']
      );
      return res.json({ code: 0, data: { disliked: true }, message: 'Disliked' });
    }
  } catch (err) {
    next(err);
  }
});

// POST /api/work-logs/:id/forward - Forward log to others
router.post('/:id/forward', async (req, res, next) => {
  try {
    const { id } = req.params;

    const [logs] = await pool.query(
      'SELECT user_id, recipients FROM work_logs WHERE id = ?',
      [id]
    );

    if (logs.length === 0) {
      return res.status(404).json({ code: 404, message: 'Work log not found' });
    }

    const log = logs[0];
    const recipients = safeParse(log.recipients);
    if (log.user_id !== req.user.id && !recipients.includes(req.user.id)) {
      return res.status(403).json({ code: 403, message: 'Access denied' });
    }

    // Record forward interaction
    const [existing] = await pool.query(
      'SELECT id FROM work_log_interactions WHERE log_id = ? AND user_id = ? AND type = ?',
      [id, req.user.id, 'forward']
    );

    if (existing.length > 0) {
      return res.json({ code: 0, data: { forwarded: true }, message: 'Already forwarded' });
    }

    await pool.query(
      'INSERT INTO work_log_interactions (log_id, user_id, type) VALUES (?, ?, ?)',
      [id, req.user.id, 'forward']
    );

    res.json({ code: 0, data: { forwarded: true }, message: 'Forwarded' });
  } catch (err) {
    next(err);
  }
});

export default router;
