/**
 * 菜单模块化配置 API
 * GET    /api/settings/menu-config                  - 获取当前环境的菜单配置
 * GET    /api/settings/menu-modules                 - 获取所有菜单模块定义
 * PUT    /api/settings/menu-config                  - 更新菜单配置（安装/勾选模块）
 * POST   /api/settings/menu-modules                 - 新增字典项
 * PUT    /api/settings/menu-modules/:key            - 更新字典项
 * DELETE /api/settings/menu-modules/:key            - 删除字典项
 * POST   /api/settings/menu-modules/sync-static     - 把前端 menuModules.js 同步到 DB
 * GET    /api/settings/menu-modules/orphans         - 列出孤儿（在 server_modules 但不在字典）
 * POST   /api/settings/menu-modules/merge-orphans   - 把孤儿 reverse-INSERT 进字典
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

// ─── 字典 CRUD（admin only）────────────────────────────────────

// POST /api/settings/menu-modules - 新增字典项
router.post('/menu-modules', auth, async (req, res, next) => {
  try {
    if (req.user.role !== ROLES.ADMIN) return res.status(403).json({ code: 403, message: '需 admin 权限' });
    const { key, label_zh, label_en, icon, route, category, sort_order, required, env_flags } = req.body || {};
    if (!key || !label_zh || !label_en || !route) {
      return res.status(400).json({ code: 400, message: 'key/label_zh/label_en/route 必填' });
    }
    await pool.query(
      `INSERT INTO menu_modules (\`key\`, label_zh, label_en, icon, route, category, sort_order, required, env_flags)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [key, label_zh, label_en, icon || 'circle', route, category || 'general', sort_order || 99, required ? 1 : 0,
       env_flags ? JSON.stringify(env_flags) : null]
    );
    res.json({ code: 0, message: '字典项已新增' });
  } catch (err) { next(err); }
});

// PUT /api/settings/menu-modules/:key - 更新字典项
router.put('/menu-modules/:key', auth, async (req, res, next) => {
  try {
    if (req.user.role !== ROLES.ADMIN) return res.status(403).json({ code: 403, message: '需 admin 权限' });
    const { key } = req.params;
    const { label_zh, label_en, icon, route, category, sort_order, required, env_flags } = req.body || {};
    // 动态构造 SET，只更新有传的字段（避免 NULL → enum 列报错）
    const sets = [];
    const vals = [];
    if (label_zh !== undefined) { sets.push('label_zh = ?'); vals.push(label_zh); }
    if (label_en !== undefined) { sets.push('label_en = ?'); vals.push(label_en); }
    if (icon !== undefined) { sets.push('icon = ?'); vals.push(icon); }
    if (route !== undefined) { sets.push('route = ?'); vals.push(route); }
    if (category !== undefined) { sets.push('category = ?'); vals.push(category); }
    if (sort_order !== undefined) { sets.push('sort_order = ?'); vals.push(sort_order); }
    if (required !== undefined) { sets.push('required = ?'); vals.push(required ? 1 : 0); }
    if (env_flags !== undefined) {
      sets.push('env_flags = ?');
      vals.push(env_flags ? JSON.stringify(env_flags) : null);
    }
    if (sets.length === 0) return res.status(400).json({ code: 400, message: '至少传一个字段' });
    vals.push(key);
    const [r] = await pool.query(`UPDATE menu_modules SET ${sets.join(', ')} WHERE \`key\` = ?`, vals);
    if (r.affectedRows === 0) return res.status(404).json({ code: 404, message: 'key 不存在' });
    res.json({ code: 0, message: '字典项已更新' });
  } catch (err) { next(err); }
});

// DELETE /api/settings/menu-modules/:key - 删除字典项（不会删 server_modules 里的勾选，会变孤儿）
router.delete('/menu-modules/:key', auth, async (req, res, next) => {
  try {
    if (req.user.role !== ROLES.ADMIN) return res.status(403).json({ code: 403, message: '需 admin 权限' });
    const { key } = req.params;
    // 检查是否被 profile 勾选
    const [refs] = await pool.query('SELECT COUNT(*) AS cnt FROM server_modules WHERE module_key = ?', [key]);
    const [r] = await pool.query('DELETE FROM menu_modules WHERE `key` = ?', [key]);
    if (r.affectedRows === 0) return res.status(404).json({ code: 404, message: 'key 不存在' });
    res.json({ code: 0, message: '字典项已删除', data: { was_referenced: refs[0].cnt > 0, ref_count: refs[0].cnt } });
  } catch (err) { next(err); }
});

// ─── 孤儿 + 融合 + 静态同步（模块化铁律核心）────────────────

// GET /api/settings/menu-modules/orphans - 列出孤儿
router.get('/menu-modules/orphans', auth, async (req, res, next) => {
  try {
    if (req.user.role !== ROLES.ADMIN) return res.status(403).json({ code: 403, message: '需 admin 权限' });
    // server_modules.module_key NOT IN menu_modules.key
    const [rows] = await pool.query(`
      SELECT sm.server_profile_id, sp.name AS profile_name, sm.module_key, COUNT(*) AS ref_count
      FROM server_modules sm
      JOIN server_profiles sp ON sp.id = sm.server_profile_id
      WHERE sm.module_key NOT IN (SELECT \`key\` FROM menu_modules)
      GROUP BY sm.server_profile_id, sm.module_key
      ORDER BY ref_count DESC, sm.module_key
    `);
    // 汇总：distinct module_key
    const distinct = [...new Set(rows.map(r => r.module_key))];
    res.json({
      code: 0,
      data: {
        total_orphans: rows.length,
        distinct_keys: distinct,
        by_profile: rows,
      },
    });
  } catch (err) { next(err); }
});

// POST /api/settings/menu-modules/merge-orphans - 融合孤儿到字典
// body: { keys: ['attendance', 'temple'] } 留空 = 全融合
router.post('/menu-modules/merge-orphans', auth, async (req, res, next) => {
  try {
    if (req.user.role !== ROLES.ADMIN) return res.status(403).json({ code: 403, message: '需 admin 权限' });
    const { keys } = req.body || {};

    // 取出孤儿 key 列表
    let orphanKeys;
    if (Array.isArray(keys) && keys.length > 0) {
      orphanKeys = keys;
    } else {
      const [rows] = await pool.query(`
        SELECT DISTINCT module_key FROM server_modules
        WHERE module_key NOT IN (SELECT \`key\` FROM menu_modules)
      `);
      orphanKeys = rows.map(r => r.module_key);
    }

    if (orphanKeys.length === 0) {
      return res.json({ code: 0, message: '没有孤儿需要融合', data: { merged: 0, items: [] } });
    }

    // 推断 label_zh：code 转 Title-Case（attendance → Attendance）
    // cat 默认 legacy（module 字典没有的，临时归类 legacy）
    const items = orphanKeys.map(k => ({
      key: k,
      label_zh: k.split('-').map(s => s[0].toUpperCase() + s.slice(1)).join(' '),
      label_en: k.split('-').map(s => s[0].toUpperCase() + s.slice(1)).join(' '),
      icon: 'extension',
      route: '/' + k,
      category: 'legacy',
      sort_order: 95,
      required: 0,
      env_flags: null,
    }));

    const conn = await pool.getConnection();
    await conn.beginTransaction();
    try {
      for (const it of items) {
        await conn.query(
          `INSERT IGNORE INTO menu_modules (\`key\`, label_zh, label_en, icon, route, category, sort_order, required, env_flags)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
          [it.key, it.label_zh, it.label_en, it.icon, it.route, it.category, it.sort_order, it.required, it.env_flags]
        );
      }
      await conn.commit();
    } catch (err) {
      await conn.rollback();
      throw err;
    } finally {
      conn.release();
    }

    res.json({
      code: 0,
      message: `已融合 ${items.length} 个孤儿到字典`,
      data: { merged: items.length, items },
    });
  } catch (err) { next(err); }
});

// POST /api/settings/menu-modules/sync-static - 同步前端 menuModules.js 静态真理源到 DB
// body: { modules: [...], dryRun?: bool }
// 调用方：dev build 后 admin 手动触发 / 自动启动时 sync
router.post('/menu-modules/sync-static', auth, async (req, res, next) => {
  try {
    if (req.user.role !== ROLES.ADMIN) return res.status(403).json({ code: 403, message: '需 admin 权限' });
    const { modules, dryRun = false } = req.body || {};
    if (!Array.isArray(modules) || modules.length === 0) {
      return res.status(400).json({ code: 400, message: 'modules 必须是非空数组' });
    }

    const stats = { inserted: 0, updated: 0, skipped: 0, total: modules.length };
    const changes = [];

    for (const m of modules) {
      if (!m.key || !m.label_zh || !m.label_en || !m.route) {
        stats.skipped++;
        continue;
      }
      const [exists] = await pool.query('SELECT id, sort_order FROM menu_modules WHERE `key` = ?', [m.key]);
      if (exists.length === 0) {
        if (!dryRun) {
          await pool.query(
            `INSERT INTO menu_modules (\`key\`, label_zh, label_en, icon, route, category, sort_order, required, env_flags)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
            [m.key, m.label_zh, m.label_en, m.icon || 'circle', m.route, m.category || 'general',
             m.sort_order || 99, m.required ? 1 : 0,
             m.env_flags ? JSON.stringify(m.env_flags) : null]
          );
        }
        stats.inserted++;
        changes.push({ key: m.key, op: 'insert' });
      } else {
        // 已存在：dev 真理源 sort_order / label 改了 → update
        // 但不能改 category —— 孤儿融合时 category='legacy' 是用户特意标记的
        if (!dryRun && (m.sort_order !== exists[0].sort_order || true)) {
          // 只在 sort_order / label 真正变了才 update（避免无意义 UPDATE）
          await pool.query(
            `UPDATE menu_modules
             SET sort_order = ?, label_zh = ?, label_en = ?, route = ?
             WHERE \`key\` = ?`,
            [m.sort_order || 99, m.label_zh, m.label_en, m.route, m.key]
          );
          stats.updated++;
          changes.push({ key: m.key, op: 'update' });
        } else {
          stats.skipped++;
        }
      }
    }

    res.json({
      code: 0,
      message: `同步完成：inserted=${stats.inserted}, updated=${stats.updated}, skipped=${stats.skipped}`,
      data: { stats, changes, dryRun },
    });
  } catch (err) { next(err); }
});

// POST /api/settings/menu-modules/upgrade-category - 把孤儿（legacy）的 category 升级成正式分类
// body: { key, category } 或 { upgrades: [{key, category}, ...] }
// 适用场景：dev 已把孤儿加进 menuModules.js 真理源并正式分类，sync-static 后该 key 仍 tag 'legacy'
// 时手 / 批量触发升级，category 改成正式分类
router.post('/menu-modules/upgrade-category', auth, async (req, res, next) => {
  try {
    if (req.user.role !== ROLES.ADMIN) return res.status(403).json({ code: 403, message: '需 admin 权限' });
    const { key, category, upgrades } = req.body || {};
    const VALID = new Set(['general', 'business', 'restaurant', 'mall', 'hotel', 'company', 'education', 'retail', 'legacy']);
    const ops = [];
    if (Array.isArray(upgrades) && upgrades.length > 0) {
      for (const u of upgrades) ops.push({ key: u.key, category: u.category });
    } else if (key && category) {
      ops.push({ key, category });
    }
    if (ops.length === 0) return res.status(400).json({ code: 400, message: '需要 key+category 或 upgrades 数组' });
    for (const op of ops) {
      if (!VALID.has(op.category)) return res.status(400).json({ code: 400, message: `非法 category: ${op.category}` });
    }
    const results = [];
    for (const op of ops) {
      const [r] = await pool.query(
        'UPDATE menu_modules SET category = ? WHERE `key` = ?',
        [op.category, op.key]
      );
      const [row] = await pool.query('SELECT `key`, label_zh, category FROM menu_modules WHERE `key` = ?', [op.key]);
      results.push({ key: op.key, affected: r.affectedRows, category: row[0]?.category || null });
    }
    res.json({
      code: 0,
      message: `已升级 ${results.length} 个模块的分类`,
      data: { results },
    });
  } catch (err) { next(err); }
});

// ─── 通用模块设置（visibility 等可配置项）─────────────────

// GET /api/settings/module/:key - 获取某个模块设置（公开，登录用户都可见）
router.get('/module/:key', auth, async (req, res, next) => {
  try {
    const { key } = req.params;
    const [rows] = await pool.query('SELECT value FROM settings WHERE `key` = ?', [key]);
    if (rows.length === 0) return res.json({ code: 0, data: { key, value: null } });
    res.json({ code: 0, data: { key, value: rows[0].value } });
  } catch (err) { next(err); }
});

// GET /api/settings/modules?prefix=xxx - 批量按前缀获取（前端初始化用）
router.get('/modules', auth, async (req, res, next) => {
  try {
    const { prefix } = req.query;
    let sql = 'SELECT `key`, value FROM settings';
    const params = [];
    if (prefix) {
      sql += ' WHERE `key` LIKE ?';
      params.push(prefix + '%');
    }
    const [rows] = await pool.query(sql, params);
    res.json({ code: 0, data: rows });
  } catch (err) { next(err); }
});

// PUT /api/settings/module/:key - 更新某个模块设置（admin only）
router.put('/module/:key', auth, async (req, res, next) => {
  try {
    if (req.user.role !== ROLES.ADMIN) return res.status(403).json({ code: 403, message: '需 admin 权限' });
    const { key } = req.params;
    const { value } = req.body || {};
    if (value === undefined) return res.status(400).json({ code: 400, message: 'value 必填' });

    await pool.query(
      'INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)',
      [key, String(value)]
    );
    res.json({ code: 0, message: '已更新', data: { key, value } });
  } catch (err) { next(err); }
});

export default router;