import express from 'express';
import { pool } from '../db/connection.js';
import { auth } from '../middleware/auth.js';
import { h5Auth } from '../middleware/h5Auth.js';

const router = express.Router();

// Middleware to check both auth types (system user or H5 user)
const optionalAuth = (req, res, next) => {
  // Try system auth first
  const systemToken = req.headers.authorization?.replace('Bearer ', '');
  if (systemToken) {
    return auth(req, res, (err) => {
      if (!err && req.user) {
        return next();
      }
      // If system auth fails, try H5 auth
      return h5Auth(req, res, next);
    });
  }

  // Try H5 auth
  return h5Auth(req, res, next);
};

// POST /api/share-logs - Record share action
router.post('/', optionalAuth, async (req, res) => {
  try {
    const { share_type, share_target_id, channel, share_url } = req.body;

    // Validate required fields
    if (!share_type || !channel) {
      return res.json({
        code: 1,
        message: 'share_type and channel are required'
      });
    }

    // Validate share_type
    const validTypes = ['product', 'qrcode', 'page'];
    if (!validTypes.includes(share_type)) {
      return res.json({
        code: 1,
        message: 'Invalid share_type. Must be: product, qrcode, or page'
      });
    }

    // Validate channel
    const validChannels = ['wechat', 'whatsapp', 'link', 'poster'];
    if (!validChannels.includes(channel)) {
      return res.json({
        code: 1,
        message: 'Invalid channel. Must be: wechat, whatsapp, link, or poster'
      });
    }

    // Determine user_id or h5_user_id
    const user_id = req.user?.id || null;
    const h5_user_id = req.h5user?.id || null;

    const [result] = await pool.query(
      `INSERT INTO share_logs
       (share_type, share_target_id, channel, share_url, user_id, h5_user_id, view_count, conversion_count)
       VALUES (?, ?, ?, ?, ?, ?, 0, 0)`,
      [share_type, share_target_id, channel, share_url, user_id, h5_user_id]
    );

    res.json({
      code: 0,
      data: {
        id: result.insertId,
        share_type,
        share_target_id,
        channel,
        share_url,
        user_id,
        h5_user_id
      },
      message: 'ok'
    });
  } catch (error) {
    console.error('Error creating share log:', error);
    res.json({
      code: 1,
      message: error.message
    });
  }
});

// GET /api/share-logs - Get share logs with filters
router.get('/', auth, async (req, res) => {
  try {
    const {
      share_type,
      channel,
      date_from,
      date_to,
      user_id,
      h5_user_id,
      page = 1,
      limit = 20
    } = req.query;

    const offset = (parseInt(page) - 1) * parseInt(limit);

    let whereConditions = [];
    let params = [];

    if (share_type) {
      whereConditions.push('sl.share_type = ?');
      params.push(share_type);
    }

    if (channel) {
      whereConditions.push('sl.channel = ?');
      params.push(channel);
    }

    if (date_from) {
      whereConditions.push('sl.created_at >= ?');
      params.push(date_from);
    }

    if (date_to) {
      whereConditions.push('sl.created_at <= ?');
      params.push(date_to);
    }

    if (user_id) {
      whereConditions.push('sl.user_id = ?');
      params.push(user_id);
    }

    if (h5_user_id) {
      whereConditions.push('sl.h5_user_id = ?');
      params.push(h5_user_id);
    }

    const whereClause = whereConditions.length > 0
      ? 'WHERE ' + whereConditions.join(' AND ')
      : '';

    // Get total count
    const [countResult] = await pool.query(
      `SELECT COUNT(*) as total FROM share_logs sl ${whereClause}`,
      params
    );
    const total = countResult[0].total;

    // Get paginated data with related info
    const query = `
      SELECT
        sl.*,
        u.name as user_name,
        hu.phone as h5_user_phone,
        p.name as product_name,
        p.image_main as product_image,
        q.qr_code as qrcode_code
      FROM share_logs sl
      LEFT JOIN users u ON sl.user_id = u.id
      LEFT JOIN h5_users hu ON sl.h5_user_id = hu.id
      LEFT JOIN products p ON sl.share_type = 'product' AND sl.share_target_id = p.id
      LEFT JOIN qrcodes q ON sl.share_type = 'qrcode' AND sl.share_target_id = q.id
      ${whereClause}
      ORDER BY sl.created_at DESC
      LIMIT ? OFFSET ?
    `;

    const [rows] = await pool.query(query, [...params, parseInt(limit), offset]);

    res.json({
      code: 0,
      data: {
        list: rows,
        total,
        page: parseInt(page),
        limit: parseInt(limit),
        total_pages: Math.ceil(total / parseInt(limit))
      },
      message: 'ok'
    });
  } catch (error) {
    console.error('Error fetching share logs:', error);
    res.json({
      code: 1,
      message: error.message
    });
  }
});

// GET /api/share-logs/:id - Get share log detail
router.get('/:id', auth, async (req, res) => {
  try {
    const { id } = req.params;

    const [rows] = await pool.query(
      `SELECT
        sl.*,
        u.name as user_name,
        hu.phone as h5_user_phone,
        p.name as product_name,
        p.image_main as product_image,
        p.description as product_description,
        q.qr_code as qrcode_code,
        q.status as qrcode_status
      FROM share_logs sl
      LEFT JOIN users u ON sl.user_id = u.id
      LEFT JOIN h5_users hu ON sl.h5_user_id = hu.id
      LEFT JOIN products p ON sl.share_type = 'product' AND sl.share_target_id = p.id
      LEFT JOIN qrcodes q ON sl.share_type = 'qrcode' AND sl.share_target_id = q.id
      WHERE sl.id = ?`,
      [id]
    );

    if (rows.length === 0) {
      return res.json({
        code: 1,
        message: 'Share log not found'
      });
    }

    res.json({
      code: 0,
      data: rows[0],
      message: 'ok'
    });
  } catch (error) {
    console.error('Error fetching share log detail:', error);
    res.json({
      code: 1,
      message: error.message
    });
  }
});

// POST /api/share-logs/:id/view - Increment view count (public)
router.post('/:id/view', async (req, res) => {
  try {
    const { id } = req.params;

    const [result] = await pool.query(
      'UPDATE share_logs SET view_count = view_count + 1 WHERE id = ?',
      [id]
    );

    if (result.affectedRows === 0) {
      return res.json({
        code: 1,
        message: 'Share log not found'
      });
    }

    // Get updated count
    const [rows] = await pool.query(
      'SELECT view_count FROM share_logs WHERE id = ?',
      [id]
    );

    res.json({
      code: 0,
      data: {
        id: parseInt(id),
        view_count: rows[0].view_count
      },
      message: 'ok'
    });
  } catch (error) {
    console.error('Error incrementing view count:', error);
    res.json({
      code: 1,
      message: error.message
    });
  }
});

// POST /api/share-logs/:id/conversion - Increment conversion count (public)
router.post('/:id/conversion', async (req, res) => {
  try {
    const { id } = req.params;

    const [result] = await pool.query(
      'UPDATE share_logs SET conversion_count = conversion_count + 1 WHERE id = ?',
      [id]
    );

    if (result.affectedRows === 0) {
      return res.json({
        code: 1,
        message: 'Share log not found'
      });
    }

    // Get updated count
    const [rows] = await pool.query(
      'SELECT conversion_count FROM share_logs WHERE id = ?',
      [id]
    );

    res.json({
      code: 0,
      data: {
        id: parseInt(id),
        conversion_count: rows[0].conversion_count
      },
      message: 'ok'
    });
  } catch (error) {
    console.error('Error incrementing conversion count:', error);
    res.json({
      code: 1,
      message: error.message
    });
  }
});

// GET /api/share-logs/stats - Share statistics
router.get('/stats', auth, async (req, res) => {
  try {
    const { date_from, date_to, user_id, h5_user_id } = req.query;

    let whereConditions = [];
    let params = [];

    if (date_from) {
      whereConditions.push('created_at >= ?');
      params.push(date_from);
    }

    if (date_to) {
      whereConditions.push('created_at <= ?');
      params.push(date_to);
    }

    if (user_id) {
      whereConditions.push('user_id = ?');
      params.push(user_id);
    }

    if (h5_user_id) {
      whereConditions.push('h5_user_id = ?');
      params.push(h5_user_id);
    }

    const whereClause = whereConditions.length > 0
      ? 'WHERE ' + whereConditions.join(' AND ')
      : '';

    // Total shares
    const [totalResult] = await pool.query(
      `SELECT COUNT(*) as total_shares FROM share_logs ${whereClause}`,
      params
    );

    // By channel
    const [channelResult] = await pool.query(
      `SELECT channel, COUNT(*) as count
       FROM share_logs ${whereClause}
       GROUP BY channel`,
      params
    );

    // By type
    const [typeResult] = await pool.query(
      `SELECT share_type, COUNT(*) as count
       FROM share_logs ${whereClause}
       GROUP BY share_type`,
      params
    );

    // Total views and conversions
    const [metricsResult] = await pool.query(
      `SELECT
        SUM(view_count) as total_views,
        SUM(conversion_count) as total_conversions
       FROM share_logs ${whereClause}`,
      params
    );

    res.json({
      code: 0,
      data: {
        total_shares: totalResult[0].total_shares,
        by_channel: channelResult.reduce((acc, row) => {
          acc[row.channel] = row.count;
          return acc;
        }, {}),
        by_type: typeResult.reduce((acc, row) => {
          acc[row.share_type] = row.count;
          return acc;
        }, {}),
        total_views: metricsResult[0].total_views || 0,
        total_conversions: metricsResult[0].total_conversions || 0
      },
      message: 'ok'
    });
  } catch (error) {
    console.error('Error fetching share stats:', error);
    res.json({
      code: 1,
      message: error.message
    });
  }
});

export default router;
