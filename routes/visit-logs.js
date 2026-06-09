import express from 'express';
import { pool } from '../db/connection.js';
import { auth } from '../middleware/auth.js';
import { ROLES } from '../middleware/rbac.js';

const router = express.Router();

// All routes require authentication
router.use(auth);

// POST /api/visit-logs - Create visit log
router.post('/', async (req, res) => {
  const conn = await pool.getConnection();
  try {
    const {
      customer_type,
      customer_id,
      visit_date,
      purpose,
      content,
      result,
      next_plan,
      photos,
      location,
      gps_lat,
      gps_lng
    } = req.body;

    // Validate required fields
    if (!customer_type || !customer_id || !visit_date) {
      return res.json({
        code: 1,
        message: 'customer_type, customer_id, and visit_date are required'
      });
    }

    // Validate customer_type
    const validTypes = ['supplier', 'dealer', 'store'];
    if (!validTypes.includes(customer_type)) {
      return res.json({
        code: 1,
        message: 'customer_type must be one of: supplier, dealer, store'
      });
    }

    // Validate customer exists
    let tableName;
    if (customer_type === 'supplier') tableName = 'suppliers';
    else if (customer_type === 'dealer') tableName = 'dealers';
    else tableName = 'stores';

    const [customerRows] = await conn.query(
      `SELECT id FROM ${tableName} WHERE id = ?`,
      [customer_id]
    );

    if (customerRows.length === 0) {
      return res.json({
        code: 1,
        message: `${customer_type} with id ${customer_id} not found`
      });
    }

    // Insert visit log
    const [result_insert] = await conn.query(
      `INSERT INTO visit_logs
       (user_id, customer_type, customer_id, visit_date, purpose, content, result, next_plan, photos, location, gps_lat, gps_lng, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())`,
      [
        req.user.id,
        customer_type,
        customer_id,
        visit_date,
        purpose || null,
        content || null,
        result || null,
        next_plan || null,
        photos ? JSON.stringify(photos) : null,
        location || null,
        gps_lat || null,
        gps_lng || null
      ]
    );

    res.json({
      code: 0,
      data: { id: result_insert.insertId },
      message: 'ok'
    });
  } catch (error) {
    console.error('Error creating visit log:', error);
    res.json({
      code: 1,
      message: error.message
    });
  } finally {
    conn.release();
  }
});

// GET /api/visit-logs - Get visit logs with filters
router.get('/', async (req, res) => {
  const conn = await pool.getConnection();
  try {
    const {
      customer_type,
      customer_id,
      date_from,
      date_to,
      user_id,
      page = 1,
      limit = 20
    } = req.query;

    const offset = (parseInt(page) - 1) * parseInt(limit);

    // Build WHERE clause
    const conditions = [];
    const params = [];

    if (customer_type) {
      conditions.push('vl.customer_type = ?');
      params.push(customer_type);
    }

    if (customer_id) {
      conditions.push('vl.customer_id = ?');
      params.push(customer_id);
    }

    if (date_from) {
      conditions.push('vl.visit_date >= ?');
      params.push(date_from);
    }

    if (date_to) {
      conditions.push('vl.visit_date <= ?');
      params.push(date_to);
    }

    if (user_id) {
      conditions.push('vl.user_id = ?');
      params.push(user_id);
    }

    const whereClause = conditions.length > 0 ? 'WHERE ' + conditions.join(' AND ') : '';

    // Get total count
    const [countRows] = await conn.query(
      `SELECT COUNT(*) as total FROM visit_logs vl ${whereClause}`,
      params
    );
    const total = countRows[0].total;

    // Get visit logs with customer names
    const [rows] = await conn.query(
      `SELECT
        vl.*,
        u.name as user_name,
        CASE
          WHEN vl.customer_type = 'supplier' THEN s.name
          WHEN vl.customer_type = 'dealer' THEN d.name
          WHEN vl.customer_type = 'store' THEN st.name
        END as customer_name,
        CASE
          WHEN vl.customer_type = 'supplier' THEN s.contact
          WHEN vl.customer_type = 'dealer' THEN d.contact
          WHEN vl.customer_type = 'store' THEN st.contact
        END as customer_contact
      FROM visit_logs vl
      LEFT JOIN users u ON vl.user_id = u.id
      LEFT JOIN suppliers s ON vl.customer_type = 'supplier' AND vl.customer_id = s.id
      LEFT JOIN dealers d ON vl.customer_type = 'dealer' AND vl.customer_id = d.id
      LEFT JOIN stores st ON vl.customer_type = 'store' AND vl.customer_id = st.id
      ${whereClause}
      ORDER BY vl.visit_date DESC, vl.created_at DESC
      LIMIT ? OFFSET ?`,
      [...params, parseInt(limit), offset]
    );

    // Parse photos JSON
    const data = rows.map(row => ({
      ...row,
      photos: row.photos ? JSON.parse(row.photos) : []
    }));

    res.json({
      code: 0,
      data: {
        list: data,
        total,
        page: parseInt(page),
        limit: parseInt(limit)
      },
      message: 'ok'
    });
  } catch (error) {
    console.error('Error getting visit logs:', error);
    res.json({
      code: 1,
      message: error.message
    });
  } finally {
    conn.release();
  }
});

// GET /api/visit-logs/stats - Visit statistics
router.get('/stats', async (req, res) => {
  const conn = await pool.getConnection();
  try {
    const { date_from, date_to, user_id } = req.query;

    const conditions = [];
    const params = [];

    if (date_from) {
      conditions.push('visit_date >= ?');
      params.push(date_from);
    }

    if (date_to) {
      conditions.push('visit_date <= ?');
      params.push(date_to);
    }

    if (user_id) {
      conditions.push('user_id = ?');
      params.push(user_id);
    }

    const whereClause = conditions.length > 0 ? 'WHERE ' + conditions.join(' AND ') : '';

    // Total visits
    const [totalRows] = await conn.query(
      `SELECT COUNT(*) as total FROM visit_logs ${whereClause}`,
      params
    );

    // By customer type
    const [byTypeRows] = await conn.query(
      `SELECT customer_type, COUNT(*) as count
       FROM visit_logs ${whereClause}
       GROUP BY customer_type`,
      params
    );

    // By user
    const [byUserRows] = await conn.query(
      `SELECT vl.user_id, u.name, COUNT(*) as count
       FROM visit_logs vl
       LEFT JOIN users u ON vl.user_id = u.id
       ${whereClause}
       GROUP BY vl.user_id, u.name
       ORDER BY count DESC`,
      params
    );

    res.json({
      code: 0,
      data: {
        total: totalRows[0].total,
        by_customer_type: byTypeRows,
        by_user: byUserRows
      },
      message: 'ok'
    });
  } catch (error) {
    console.error('Error getting visit stats:', error);
    res.json({
      code: 1,
      message: error.message
    });
  } finally {
    conn.release();
  }
});

// GET /api/visit-logs/:id - Get visit log detail
router.get('/:id', async (req, res) => {
  const conn = await pool.getConnection();
  try {
    const { id } = req.params;

    const [rows] = await conn.query(
      `SELECT
        vl.*,
        u.name as user_name,
        CASE
          WHEN vl.customer_type = 'supplier' THEN s.name
          WHEN vl.customer_type = 'dealer' THEN d.name
          WHEN vl.customer_type = 'store' THEN st.name
        END as customer_name,
        CASE
          WHEN vl.customer_type = 'supplier' THEN s.contact
          WHEN vl.customer_type = 'dealer' THEN d.contact
          WHEN vl.customer_type = 'store' THEN st.contact
        END as customer_contact,
        CASE
          WHEN vl.customer_type = 'supplier' THEN s.phone
          WHEN vl.customer_type = 'dealer' THEN d.phone
          WHEN vl.customer_type = 'store' THEN st.phone
        END as customer_phone,
        CASE
          WHEN vl.customer_type = 'supplier' THEN s.address
          WHEN vl.customer_type = 'dealer' THEN d.address
          WHEN vl.customer_type = 'store' THEN st.address
        END as customer_address
      FROM visit_logs vl
      LEFT JOIN users u ON vl.user_id = u.id
      LEFT JOIN suppliers s ON vl.customer_type = 'supplier' AND vl.customer_id = s.id
      LEFT JOIN dealers d ON vl.customer_type = 'dealer' AND vl.customer_id = d.id
      LEFT JOIN stores st ON vl.customer_type = 'store' AND vl.customer_id = st.id
      WHERE vl.id = ?`,
      [id]
    );

    if (rows.length === 0) {
      return res.json({
        code: 1,
        message: 'Visit log not found'
      });
    }

    const data = {
      ...rows[0],
      photos: rows[0].photos ? JSON.parse(rows[0].photos) : []
    };

    res.json({
      code: 0,
      data,
      message: 'ok'
    });
  } catch (error) {
    console.error('Error getting visit log detail:', error);
    res.json({
      code: 1,
      message: error.message
    });
  } finally {
    conn.release();
  }
});

// PUT /api/visit-logs/:id - Update visit log (only by creator)
router.put('/:id', async (req, res) => {
  const conn = await pool.getConnection();
  try {
    const { id } = req.params;
    const {
      customer_type,
      customer_id,
      visit_date,
      purpose,
      content,
      result,
      next_plan,
      photos,
      location,
      gps_lat,
      gps_lng
    } = req.body;

    // Check if visit log exists and user is creator
    const [existingRows] = await conn.query(
      'SELECT user_id FROM visit_logs WHERE id = ?',
      [id]
    );

    if (existingRows.length === 0) {
      return res.json({
        code: 1,
        message: 'Visit log not found'
      });
    }

    if (existingRows[0].user_id !== req.user.id) {
      return res.json({
        code: 1,
        message: 'Only the creator can update this visit log'
      });
    }

    // If customer info is being updated, validate customer exists
    if (customer_type && customer_id) {
      const validTypes = ['supplier', 'dealer', 'store'];
      if (!validTypes.includes(customer_type)) {
        return res.json({
          code: 1,
          message: 'customer_type must be one of: supplier, dealer, store'
        });
      }

      let tableName;
      if (customer_type === 'supplier') tableName = 'suppliers';
      else if (customer_type === 'dealer') tableName = 'dealers';
      else tableName = 'stores';

      const [customerRows] = await conn.query(
        `SELECT id FROM ${tableName} WHERE id = ?`,
        [customer_id]
      );

      if (customerRows.length === 0) {
        return res.json({
          code: 1,
          message: `${customer_type} with id ${customer_id} not found`
        });
      }
    }

    // Build update query
    const updates = [];
    const params = [];

    if (customer_type !== undefined) {
      updates.push('customer_type = ?');
      params.push(customer_type);
    }
    if (customer_id !== undefined) {
      updates.push('customer_id = ?');
      params.push(customer_id);
    }
    if (visit_date !== undefined) {
      updates.push('visit_date = ?');
      params.push(visit_date);
    }
    if (purpose !== undefined) {
      updates.push('purpose = ?');
      params.push(purpose);
    }
    if (content !== undefined) {
      updates.push('content = ?');
      params.push(content);
    }
    if (result !== undefined) {
      updates.push('result = ?');
      params.push(result);
    }
    if (next_plan !== undefined) {
      updates.push('next_plan = ?');
      params.push(next_plan);
    }
    if (photos !== undefined) {
      updates.push('photos = ?');
      params.push(photos ? JSON.stringify(photos) : null);
    }
    if (location !== undefined) {
      updates.push('location = ?');
      params.push(location);
    }
    if (gps_lat !== undefined) {
      updates.push('gps_lat = ?');
      params.push(gps_lat);
    }
    if (gps_lng !== undefined) {
      updates.push('gps_lng = ?');
      params.push(gps_lng);
    }

    if (updates.length === 0) {
      return res.json({
        code: 1,
        message: 'No fields to update'
      });
    }

    updates.push('updated_at = NOW()');
    params.push(id);

    await conn.query(
      `UPDATE visit_logs SET ${updates.join(', ')} WHERE id = ?`,
      params
    );

    res.json({
      code: 0,
      data: { id },
      message: 'ok'
    });
  } catch (error) {
    console.error('Error updating visit log:', error);
    res.json({
      code: 1,
      message: error.message
    });
  } finally {
    conn.release();
  }
});

// DELETE /api/visit-logs/:id - Delete visit log (only by creator or admin)
router.delete('/:id', async (req, res) => {
  const conn = await pool.getConnection();
  try {
    const { id } = req.params;

    // Check if visit log exists
    const [existingRows] = await conn.query(
      'SELECT user_id FROM visit_logs WHERE id = ?',
      [id]
    );

    if (existingRows.length === 0) {
      return res.json({
        code: 1,
        message: 'Visit log not found'
      });
    }

    // Check if user is creator or admin
    const [userRows] = await conn.query(
      'SELECT role_id FROM users WHERE id = ?',
      [req.user.id]
    );

    const [roleRows] = await conn.query(
      'SELECT name FROM roles WHERE id = ?',
      [userRows[0].role_id]
    );

    const isAdmin = roleRows.length > 0 && roleRows[0].name === ROLES.ADMIN;
    const isCreator = existingRows[0].user_id === req.user.id;

    if (!isAdmin && !isCreator) {
      return res.json({
        code: 1,
        message: 'Only the creator or admin can delete this visit log'
      });
    }

    await conn.query('DELETE FROM visit_logs WHERE id = ?', [id]);

    res.json({
      code: 0,
      data: { id },
      message: 'ok'
    });
  } catch (error) {
    console.error('Error deleting visit log:', error);
    res.json({
      code: 1,
      message: error.message
    });
  } finally {
    conn.release();
  }
});

export default router;
