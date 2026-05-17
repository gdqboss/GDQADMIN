import jwt from 'jsonwebtoken'

// CRITICAL: JWT_SECRET must be set in environment
if (!process.env.JWT_SECRET) {
  throw new Error('JWT_SECRET environment variable is required for H5 authentication')
}

const JWT_SECRET = process.env.JWT_SECRET

/**
 * H5 user authentication middleware
 * Verifies JWT token from Authorization header
 */
export function h5Auth(req, res, next) {
  const token = req.headers['authorization']?.replace('Bearer ', '')

  if (!token) {
    return res.status(401).json({ code: 401, message: '未登录' })
  }

  try {
    req.h5user = jwt.verify(token, JWT_SECRET)
    next()
  } catch (err) {
    console.error('H5 JWT verification failed:', err.message)
    return res.status(401).json({ code: 401, message: 'Token 无效或已过期' })
  }
}
