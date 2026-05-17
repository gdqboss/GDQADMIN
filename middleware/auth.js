import jwt from 'jsonwebtoken'

export function auth(req, res, next) {
  const header = req.headers.authorization
  if (!header || !header.startsWith('Bearer ')) {
    return res.status(401).json({ code: 401, message: '未登录或 token 缺失' })
  }
  try {
    const decoded = jwt.verify(header.split(' ')[1], process.env.JWT_SECRET)
    req.user = decoded
    next()
  } catch {
    return res.status(401).json({ code: 401, message: 'token 无效或已过期' })
  }
}
