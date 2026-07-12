// 微信小程序 JWT 中间件
const { verifyToken } = require('../utils/jwt');

module.exports = async (req, res, next) => {
  const auth = req.headers.authorization;
  if (!auth || !auth.startsWith('Bearer ')) {
    return res.status(401).json({ code: 401, message: '未授权' });
  }
  const token = auth.slice(7);
  try {
    const decoded = verifyToken(token);
    if (decoded.type !== 'mp') {
      return res.status(401).json({ code: 401, message: '非法访问' });
    }
    // 附加openid到req.user
    req.user = { id: decoded.id, role: decoded.role, openid: decoded.openid };
    next();
  } catch (e) {
    return res.status(401).json({ code: 401, message: 'token无效' });
  }
};
