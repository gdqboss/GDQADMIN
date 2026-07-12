import { Router } from 'express'
import express from 'express';
const router = Router();
import { createHash } from 'crypto'
import axios from 'axios';
import jwt from 'jsonwebtoken'

// ── 微信小程序配置 ──────────────────────────────────────────────────
const WX_MP_APPID = process.env.WX_APPID || 'wx2947d27b4da69b1e';
const WX_MP_SECRET = process.env.WX_MP_SECRET || '';

// ── 中间件 ──────────────────────────────────────────────────────────
const mpAuth = async (req, res, next) => {
  const auth = req.headers.authorization;
  if (!auth || !auth.startsWith('Bearer ')) {
    return res.status(401).json({ code: 401, message: '未授权' });
  }
  const token = auth.slice(7);
  try {
    const decoded = jwt.verify(token, process.env.JWT_SECRET);
    if (decoded.type !== 'mp') {
      return res.status(401).json({ code: 401, message: '非法访问' });
    }
    req.user = { id: decoded.id, role: decoded.role, openid: decoded.openid };
    next();
  } catch (e) {
    return res.status(401).json({ code: 401, message: 'token无效' });
  }
};

// ── 工具函数 ────────────────────────────────────────────────────────
function sendError(res, code, msg) {
  return res.json({ code, message: msg || 'error' });
}

function getWxMpToken() {
  return axios.get(`https://api.weixin.qq.com/cgi-bin/token`, {
    params: { grant_type: 'client_credential', appid: WX_MP_APPID, secret: WX_MP_SECRET },
    timeout: 10000
  }).then(r => r.data);
}

// ══════════════════════════════════════════════════════════════════════
// 1. 小程序登录 (code 换 openid)
// ══════════════════════════════════════════════════════════════════════
router.post('/login', async (req, res, next) => {
  try {
    const { code, encryptedData, iv, userInfo } = req.body;
    if (!code) return sendError(res, 400, 'code不能为空');

    // 用code换openid
    const r = await axios.get(`https://api.weixin.qq.com/sns/jscode2session`, {
      params: {
        grant_type: 'authorization_code',
        appid: WX_MP_APPID,
        secret: WX_MP_SECRET,
        js_code: code
      },
      timeout: 10000
    });
    const { openid, session_key, unionid, errcode, errmsg } = r.data;
    if (errcode) return sendError(res, 400, errmsg || '微信登录失败');

    // 查询或创建用户
    let users = await req.db.query(
      'SELECT * FROM users WHERE wx_openid = ? LIMIT 1',
      [openid]
    );
    let user;
    if (users.length === 0) {
      // 自动注册
      const name = userInfo?.nickName || '微信用户';
      const avatar = userInfo?.avatarUrl || '';
      await req.db.query(
        `INSERT INTO users (name, phone, wx_openid, wx_unionid, auth_type, role, created_at) VALUES (?, ?, ?, ?, 'wx_mp', 'member', NOW())`,
        [name, '', openid, unionid || null]
      );
      const ins = await req.db.query('SELECT * FROM users WHERE wx_openid = ? LIMIT 1', [openid]);
      user = ins[0];
    } else {
      user = users[0];
    }

    // 生成token
    const token = jwt.sign({ id: user.id, role: user.role || 'member', type: 'mp' }, process.env.JWT_SECRET, { expiresIn: '9999d' });
    const { password, ...safeUser } = user;

    res.json({
      code: 0,
      message: 'success',
      data: {
        token,
        openid,
        user: safeUser
      }
    });
  } catch (err) {
    next(err);
  }
});

// ══════════════════════════════════════════════════════════════════════
// 2. 获取手机号 (微信新版)
// ══════════════════════════════════════════════════════════════════════
router.post('/get-phone', mpAuth, async (req, res, next) => {
  try {
    const { code } = req.body;
    if (!code) return sendError(res, 400, 'code不能为空');

    const tokenData = await getWxMpToken();
    if (!tokenData.access_token) return sendError(res, 500, '获取access_token失败');

    const pr = await axios.post(
      `https://api.weixin.qq.com/wxa/business/getuserphonenumber?access_token=${tokenData.access_token}`,
      { code },
      { timeout: 10000 }
    );
    const { errcode, errmsg, phone_info } = pr.data;
    if (errcode) return sendError(res, errcode, errmsg || '获取手机号失败');

    const phone = phone_info?.phoneNumber;
    if (!phone) return sendError(res, 400, '未获取到手机号');

    // 绑定手机号到用户
    await req.db.query('UPDATE users SET phone = ?, wx_openid = COALESCE(wx_openid, ?) WHERE id = ?',
      [phone, req.user.openid || null, req.user.id]);

    res.json({ code: 0, message: 'success', data: { phone } });
  } catch (err) {
    next(err);
  }
});

// ══════════════════════════════════════════════════════════════════════
// 3. 获取用户信息
// ══════════════════════════════════════════════════════════════════════
router.get('/user', mpAuth, async (req, res, next) => {
  try {
    const users = await req.db.query('SELECT id, name, phone, wx_openid, wx_unionid, role, avatar, score, level, created_at FROM users WHERE id = ? LIMIT 1', [req.user.id]);
    if (!users.length) return sendError(res, 404, '用户不存在');
    res.json({ code: 0, message: 'success', data: users[0] });
  } catch (err) {
    next(err);
  }
});

// ══════════════════════════════════════════════════════════════════════
// 4. 更新用户信息
// ══════════════════════════════════════════════════════════════════════
router.put('/user', mpAuth, async (req, res, next) => {
  try {
    const { name, avatar } = req.body;
    const updates = [];
    const vals = [];
    if (name !== undefined) { updates.push('name = ?'); vals.push(name); }
    if (avatar !== undefined) { updates.push('avatar = ?'); vals.push(avatar); }
    if (!updates.length) return sendError(res, 400, '无更新内容');
    vals.push(req.user.id);
    await req.db.query(`UPDATE users SET ${updates.join(', ')} WHERE id = ?`, vals);
    res.json({ code: 0, message: 'success' });
  } catch (err) {
    next(err);
  }
});

// ══════════════════════════════════════════════════════════════════════
// 5. 收货地址 CRUD (小程序端)
// ══════════════════════════════════════════════════════════════════════
router.get('/addresses', mpAuth, async (req, res, next) => {
  try {
    const rows = await req.db.query(
      'SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC',
      [req.user.id]
    );
    res.json({ code: 0, message: 'success', data: rows });
  } catch (err) {
    next(err);
  }
});

router.post('/addresses', mpAuth, async (req, res, next) => {
  try {
    const { consignee, phone, province, city, district, detail, is_default } = req.body;
    if (!consignee || !phone) return sendError(res, 400, '收货人和电话必填');
    if (is_default) {
      await req.db.query('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [req.user.id]);
    }
    const r = await req.db.query(
      `INSERT INTO addresses (user_id, consignee, phone, province, city, district, detail, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [req.user.id, consignee, phone, province || '', city || '', district || '', detail || '', is_default ? 1 : 0]
    );
    res.json({ code: 0, message: 'success', data: { id: r.insertId } });
  } catch (err) {
    next(err);
  }
});

router.put('/addresses/:id', mpAuth, async (req, res, next) => {
  try {
    const { consignee, phone, province, city, district, detail, is_default } = req.body;
    if (is_default) {
      await req.db.query('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [req.user.id]);
    }
    await req.db.query(
      `UPDATE addresses SET consignee=?, phone=?, province=?, city=?, district=?, detail=?, is_default=? WHERE id=? AND user_id=?`,
      [consignee, phone, province||'', city||'', district||'', detail||'', is_default?1:0, req.params.id, req.user.id]
    );
    res.json({ code: 0, message: 'success' });
  } catch (err) {
    next(err);
  }
});

router.delete('/addresses/:id', mpAuth, async (req, res, next) => {
  try {
    await req.db.query('DELETE FROM addresses WHERE id=? AND user_id=?', [req.params.id, req.user.id]);
    res.json({ code: 0, message: 'success' });
  } catch (err) {
    next(err);
  }
});

// ══════════════════════════════════════════════════════════════════════
// 6. 收藏商品
// ══════════════════════════════════════════════════════════════════════
router.get('/favorites', mpAuth, async (req, res, next) => {
  try {
    const rows = await req.db.query(
      `SELECT f.id, f.created_at, p.id as product_id, p.name, p.sale_price, p.image_url
       FROM favorites f JOIN products p ON f.product_id = p.id
       WHERE f.user_id = ? ORDER BY f.created_at DESC`,
      [req.user.id]
    );
    res.json({ code: 0, message: 'success', data: rows });
  } catch (err) {
    next(err);
  }
});

router.post('/favorites/:productId', mpAuth, async (req, res, next) => {
  try {
    // 查重
    const ex = await req.db.query('SELECT id FROM favorites WHERE user_id=? AND product_id=?', [req.user.id, req.params.productId]);
    if (ex.length) return res.json({ code: 0, message: '已收藏' });
    await req.db.query('INSERT INTO favorites (user_id, product_id) VALUES (?, ?)', [req.user.id, req.params.productId]);
    res.json({ code: 0, message: 'success' });
  } catch (err) {
    next(err);
  }
});

router.delete('/favorites/:productId', mpAuth, async (req, res, next) => {
  try {
    await req.db.query('DELETE FROM favorites WHERE user_id=? AND product_id=?', [req.user.id, req.params.productId]);
    res.json({ code: 0, message: 'success' });
  } catch (err) {
    next(err);
  }
});

// ══════════════════════════════════════════════════════════════════════
// 7. 微信分享卡 (获取加密签名)
// ══════════════════════════════════════════════════════════════════════
router.get('/share-config', mpAuth, async (req, res, next) => {
  try {
    const tokenData = await getWxMpToken();
    if (!tokenData.access_token) return sendError(res, 500, '获取token失败');

    const ticket = await axios.get(
      `https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=${tokenData.access_token}&type=jsapi`,
      { timeout: 10000 }
    );
    const { ticket: jsapi_ticket } = ticket.data;

    const url = req.query.url || '';
    const timestamp = Math.floor(Date.now() / 1000);
    const noncestr = Math.random().toString(36).slice(2);
    const str = `jsapi_ticket=${jsapi_ticket}&noncestr=${noncestr}&timestamp=${timestamp}&url=${url}`;
    const signature = createHash('sha1').update(str).digest('hex');

    res.json({
      code: 0, message: 'success', data: {
        appId: WX_MP_APPID,
        timestamp,
        nonceStr: noncestr,
        signature
      }
    });
  } catch (err) {
    next(err);
  }
});

// ══════════════════════════════════════════════════════════════════════
// 8. 会员等级信息
// ══════════════════════════════════════════════════════════════════════
router.get('/member-info', mpAuth, async (req, res, next) => {
  try {
    const users = await req.db.query(
      'SELECT id, name, phone, score, level, created_at FROM users WHERE id = ? LIMIT 1',
      [req.user.id]
    );
    if (!users.length) return sendError(res, 404, '用户不存在');

    const levels = await req.db.query('SELECT * FROM member_level ORDER BY level ASC');
    const currentLevel = levels.find(l => l.level === users[0].level) || levels[0] || {};
    const nextLevel = levels.find(l => l.level > users[0].level) || null;

    res.json({
      code: 0, message: 'success', data: {
        user: users[0],
        currentLevel,
        nextLevel,
        levels
      }
    });
  } catch (err) {
    next(err);
  }
});

// ══════════════════════════════════════════════════════════════════════
// 9. 签到
// ══════════════════════════════════════════════════════════════════════
router.post('/checkin', mpAuth, async (req, res, next) => {
  try {
    const today = new Date().toISOString().slice(0, 10);
    const ex = await req.db.query(
      'SELECT id FROM checkin_logs WHERE user_id = ? AND DATE(created_at) = ? LIMIT 1',
      [req.user.id, today]
    );
    if (ex.length) return sendError(res, 400, '今日已签到');

    const score = 5; // 每次签到5积分
    await req.db.query(
      'UPDATE users SET score = score + ? WHERE id = ?',
      [score, req.user.id]
    );
    await req.db.query(
      'INSERT INTO checkin_logs (user_id, score, created_at) VALUES (?, ?, NOW())',
      [req.user.id, score]
    );

    res.json({ code: 0, message: '签到成功', data: { score } });
  } catch (err) {
    next(err);
  }
});

// ══════════════════════════════════════════════════════════════════════
// 10. 秒杀当前活动
// ══════════════════════════════════════════════════════════════════════
router.get('/seckill/active', async (req, res, next) => {
  try {
    const now = new Date();
    const activities = await req.db.query(
      `SELECT * FROM seckill_activities WHERE status = 'active' AND start_time <= ? AND end_time >= ? LIMIT 1`,
      [now, now]
    );
    if (!activities.length) return res.json({ code: 0, message: 'success', data: null });

    const activity = activities[0];
    const products = await req.db.query(
      `SELECT sp.*, p.name as product_name, p.image_url, p.original_price
       FROM seckill_products sp
       JOIN products p ON sp.product_id = p.id
       WHERE sp.activity_id = ?`,
      [activity.id]
    );

    res.json({ code: 0, message: 'success', data: { activity, products } });
  } catch (err) {
    next(err);
  }
});

// ══════════════════════════════════════════════════════════════════════
// 11. 拼团列表
// ══════════════════════════════════════════════════════════════════════
router.get('/collage/groups', async (req, res, next) => {
  try {
    const rows = await req.db.query(
      `SELECT cg.*, p.name as product_name, p.image_url, p.collage_price, p.original_price
       FROM collage_groups cg
       JOIN products p ON cg.product_id = p.id
       WHERE cg.status = 'open' AND cg.expire_time > NOW()
       ORDER BY cg.created_at DESC LIMIT 20`
    );
    res.json({ code: 0, message: 'success', data: rows });
  } catch (err) {
    next(err);
  }
});

// ══════════════════════════════════════════════════════════════════════
// 12. 首页数据 (聚合接口)
// ══════════════════════════════════════════════════════════════════════
router.get('/home-data', mpAuth, async (req, res, next) => {
  try {
    const banners = await req.db.query(
      "SELECT * FROM banners WHERE status='active' ORDER BY sort ASC LIMIT 5"
    );
    const categories = await req.db.query(
      "SELECT * FROM categories WHERE is_active=1 ORDER BY sort ASC LIMIT 20"
    );
    const newProducts = await req.db.query(
      "SELECT id, name, sale_price, image_url FROM products WHERE is_active=1 ORDER BY created_at DESC LIMIT 10"
    );
    const seckill = await req.db.query(
      `SELECT sp.*, p.name as product_name, p.image_url, p.original_price
       FROM seckill_products sp JOIN products p ON sp.product_id = p.id
       JOIN seckill_activities sa ON sp.activity_id = sa.id
       WHERE sa.status='active' AND sa.start_time <= NOW() AND sa.end_time >= NOW()
       LIMIT 5`
    );

    res.json({
      code: 0, message: 'success', data: { banners, categories, newProducts, seckill }
    });
  } catch (err) {
    next(err);
  }
});

export default router;
