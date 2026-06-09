/**
 * 菜单模块化配置 API
 * GET  /api/settings/menu-config        - 获取当前环境的菜单配置
 * GET  /api/settings/menu-modules     - 获取所有菜单模块定义
 * PUT  /api/settings/menu-config      - 更新菜单配置（安装/勾选模块）
 */
import express from 'express';
import { pool } from '../db/connection.js';
import { auth } from '../middleware/auth.js';
import { ROLES } from '../middleware/rbac.js';

const router = express.Router();

// 当前环境标识（从环境变量读取，默认singapore）
const ENV = process.env.APP_ENV || 'singapore';

// GET /api/settings/menu-config - 获取当前环境+角色的菜单配置
router.get('/menu-config', auth, async (req, res, next) => {
  try {
    const { role = ROLES.ADMIN, user_id } = req.query;
    const userRole = req.user.role || role;

    // 先查menu_modules表有没有数据（判断是否已初始化）
    const [modules] = await pool.query('SELECT COUNT(*) as cnt FROM menu_modules');
    if (modules[0].cnt === 0) {
      // 未初始化，返回空（前端降级到硬编码）
      return res.json({ code: 0, data: [] });
    }

    // 查配置：优先查用户个人配置，没有则查role默认配置，都没有则查全部模块的默认排序
    let sql = `
      SELECT mc.menu_key, mc.visible, mc.position, mm.label_zh, mm.label_en,
             mm.icon, mm.route, mm.category, mm.required
      FROM menu_config mc
      JOIN menu_modules mm ON mm.\`key\` = mc.menu_key
      WHERE mc.env = ? AND mc.role = ? AND mc.user_id IS NULL
    `;
    const params = [ENV, userRole];

    if (user_id) {
      // 有个人配置就优先用个人配置
      const [personal] = await pool.query(sql + ' AND mc.user_id = ?', [...params, user_id]);
      if (personal.length > 0) {
        return res.json({ code: 0, data: personal });
      }
    }

    const [rows] = await pool.query(sql, params);

    // 如果role配置为空（新建环境），自动拉全量模块的默认排序
    if (rows.length === 0) {
      const [all] = await pool.query(
        `SELECT \`key\` as menu_key, sort_order as position, label_zh, label_en, icon, route, category, required
         FROM menu_modules WHERE env_flags IS NULL OR JSON_CONTAINS(env_flags, ?)`, 
        [JSON.stringify(ENV)]
      );
      return res.json({ code: 0, data: all.map(m => ({ ...m, visible: true })) });
    }

    res.json({ code: 0, data: rows });
  } catch (err) { next(err) }
});

// GET /api/settings/menu-modules - 获取所有模块定义（安装界面用）
router.get('/menu-modules', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, \`key\`, label_zh, label_en, icon, route, category, sort_order, required, env_flags
       FROM menu_modules ORDER BY category, sort_order`
    );
    res.json({ code: 0, data: rows });
  } catch (err) { next(err) }
});

// PUT /api/settings/menu-config - 批量更新菜单配置（安装勾选）
router.put('/menu-config', auth, async (req, res, next) => {
  try {
    const { selections } = req.body; // Array<{ menu_key: string, visible: boolean, position: number }>
    if (!Array.isArray(selections)) {
      return res.status(400).json({ code: 400, message: 'selections必须是数组' });
    }

    const conn = await pool.getConnection();
    await conn.beginTransaction();
    try {
      for (const sel of selections) {
        await conn.query(
          `INSERT INTO menu_config (env, role, menu_key, visible, position)
           VALUES (?, '${ROLES.ADMIN}', ?, ?, ?)
           ON DUPLICATE KEY UPDATE visible=VALUES(visible), position=VALUES(position), updated_at=NOW()`,
          [ENV, sel.menu_key, sel.visible ? 1 : 0, sel.position || 99]
        );
      }
      await conn.commit();
      conn.release();
      res.json({ code: 0, message: '菜单配置已更新' });
    } catch (err) {
      await conn.rollback();
      conn.release();
      throw err;
    }
  } catch (err) { next(err) }
});

export default router;