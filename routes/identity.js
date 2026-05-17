import { Router } from 'express'
import bcrypt from 'bcryptjs'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { generateIdentityCode, generateIdentityQRCode, verifyIdentityCode, logIdentityScan } from '../utils/identity.js'

const router = Router()

// POST /api/identity/system/generate - Generate system user identity code (admin only)
router.post('/system/generate', auth, async (req, res, next) => {
  try {
    const { userId } = req.body

    if (req.user.role !== 'admin') {
      return res.status(403).json({ code: 1, message: '仅管理员可生成身份码' })
    }

    if (!userId) {
      return res.status(400).json({ code: 1, message: '缺少用户ID' })
    }

    // Check if user exists
    const [[user]] = await pool.query('SELECT id, name, identity_code FROM users WHERE id = ?', [userId])
    if (!user) {
      return res.status(404).json({ code: 1, message: '用户不存在' })
    }

    if (user.identity_code) {
      return res.status(400).json({ code: 1, message: '该用户已有身份码' })
    }

    // Generate identity code and QR
    const identityCode = generateIdentityCode('system', userId)
    const qrPath = await generateIdentityQRCode(identityCode, 'system', userId)

    // Update user
    await pool.query(
      'UPDATE users SET identity_code = ?, identity_qr_path = ? WHERE id = ?',
      [identityCode, qrPath, userId]
    )

    res.json({
      code: 0,
      data: { identityCode, qrPath },
      message: '身份码生成成功'
    })
  } catch (err) {
    next(err)
  }
})

// DELETE /api/identity/system/:userId - Delete system user identity code (admin only)
router.delete('/system/:userId', auth, async (req, res, next) => {
  try {
    if (req.user.role !== 'admin') {
      return res.status(403).json({ code: 1, message: '仅管理员可删除身份码' })
    }

    const { userId } = req.params

    await pool.query(
      'UPDATE users SET identity_code = NULL, identity_qr_path = NULL WHERE id = ?',
      [userId]
    )

    res.json({ code: 0, message: '身份码已删除' })
  } catch (err) {
    next(err)
  }
})

// POST /api/identity/h5/generate - Generate H5 internal user identity code (admin only)
router.post('/h5/generate', auth, async (req, res, next) => {
  try {
    const { userId } = req.body

    if (req.user.role !== 'admin') {
      return res.status(403).json({ code: 1, message: '仅管理员可生成身份码' })
    }

    if (!userId) {
      return res.status(400).json({ code: 1, message: '缺少用户ID' })
    }

    // Check if H5 user exists
    const [[h5User]] = await pool.query('SELECT id, phone, identity_code, is_internal FROM h5_users WHERE id = ?', [userId])
    if (!h5User) {
      return res.status(404).json({ code: 1, message: 'H5用户不存在' })
    }

    if (h5User.identity_code) {
      return res.status(400).json({ code: 1, message: '该用户已有身份码' })
    }

    // Generate identity code and QR
    const identityCode = generateIdentityCode('h5', userId)
    const qrPath = await generateIdentityQRCode(identityCode, 'h5', userId)

    // Update H5 user and mark as internal
    await pool.query(
      'UPDATE h5_users SET identity_code = ?, is_internal = 1 WHERE id = ?',
      [identityCode, userId]
    )

    res.json({
      code: 0,
      data: { identityCode, qrPath },
      message: '身份码生成成功'
    })
  } catch (err) {
    next(err)
  }
})

// GET /api/identity/verify/:code - Verify identity code (public)
router.get('/verify/:code', async (req, res, next) => {
  try {
    const { code } = req.params

    const userInfo = await verifyIdentityCode(code, pool)

    if (!userInfo) {
      return res.status(404).json({ code: 1, message: '身份码无效' })
    }

    res.json({
      code: 0,
      data: userInfo,
      message: 'ok'
    })
  } catch (err) {
    next(err)
  }
})

// POST /api/identity/checkin - OA checkin with identity code
router.post('/checkin', async (req, res, next) => {
  try {
    const { identityCode, gpsLat, gpsLng, gpsAccuracy, deviceInfo } = req.body

    if (!identityCode) {
      return res.status(400).json({ code: 1, message: '缺少身份码' })
    }

    // Verify identity code
    const userInfo = await verifyIdentityCode(identityCode, pool)
    if (!userInfo) {
      return res.status(404).json({ code: 1, message: '身份码无效' })
    }

    // Check if user can checkin
    if (userInfo.userType === 'system') {
      const [[user]] = await pool.query('SELECT can_oa_checkin FROM users WHERE id = ?', [userInfo.userId])
      if (!user || !user.can_oa_checkin) {
        return res.status(403).json({ code: 1, message: '该用户无签到权限' })
      }
    }

    // Get IP address
    const ipAddress = req.headers['x-forwarded-for'] || req.connection.remoteAddress

    // Insert attendance record
    const [result] = await pool.query(
      `INSERT INTO attendance (user_id, clock_in, gps_lat, gps_lng, gps_accuracy, device_info, ip_address)
       VALUES (?, NOW(), ?, ?, ?, ?, ?)`,
      [userInfo.userId, gpsLat, gpsLng, gpsAccuracy, deviceInfo, ipAddress]
    )

    // Update last checkin time
    await pool.query('UPDATE users SET last_checkin_at = NOW() WHERE id = ?', [userInfo.userId])

    // Log scan
    await logIdentityScan({
      identityCode,
      userType: userInfo.userType,
      userId: userInfo.userId,
      action: 'checkin',
      location: gpsLat && gpsLng ? `${gpsLat},${gpsLng}` : null,
      ip: ipAddress,
      deviceInfo
    }, pool)

    res.json({
      code: 0,
      data: { attendanceId: result.insertId },
      message: '签到成功'
    })
  } catch (err) {
    next(err)
  }
})

// PUT /api/identity/h5/change-password - H5 user change password
router.put('/h5/change-password', auth, async (req, res, next) => {
  try {
    const { oldPassword, newPassword } = req.body

    if (!oldPassword || !newPassword) {
      return res.status(400).json({ code: 1, message: '缺少密码参数' })
    }

    // Enforce stronger password policy
    if (newPassword.length < 8) {
      return res.status(400).json({ code: 1, message: '新密码至少8位' })
    }

    if (newPassword.length > 128) {
      return res.status(400).json({ code: 1, message: '新密码不能超过128位' })
    }

    // Check password complexity
    const hasUpperCase = /[A-Z]/.test(newPassword)
    const hasLowerCase = /[a-z]/.test(newPassword)
    const hasNumber = /\d/.test(newPassword)

    if (!hasUpperCase || !hasLowerCase || !hasNumber) {
      return res.status(400).json({
        code: 1,
        message: '新密码必须包含大写字母、小写字母和数字'
      })
    }

    // This endpoint is for H5 users authenticated via h5Auth
    // We need to check if this is an H5 user context
    if (!req.user.h5UserId) {
      return res.status(400).json({ code: 1, message: '仅H5用户可使用此接口' })
    }

    const h5UserId = req.user.h5UserId

    // Get current password
    const [[h5User]] = await pool.query('SELECT password FROM h5_users WHERE id = ?', [h5UserId])
    if (!h5User) {
      return res.status(404).json({ code: 1, message: '用户不存在' })
    }

    // Verify old password
    const isValid = await bcrypt.compare(oldPassword, h5User.password)
    if (!isValid) {
      return res.status(400).json({ code: 1, message: '原密码错误' })
    }

    // Hash new password
    const hashedPassword = await bcrypt.hash(newPassword, 10)

    // Update password
    await pool.query(
      'UPDATE h5_users SET password = ?, password_updated_at = NOW() WHERE id = ?',
      [hashedPassword, h5UserId]
    )

    // Log password change
    const ipAddress = req.headers['x-forwarded-for'] || req.connection.remoteAddress
    await pool.query(
      'INSERT INTO password_change_logs (user_type, user_id, changed_by, ip) VALUES (?, ?, ?, ?)',
      ['h5', h5UserId, 'self', ipAddress]
    )

    res.json({ code: 0, message: '密码修改成功' })
  } catch (err) {
    next(err)
  }
})

// PUT /api/identity/system/change-password - System user change password
router.put('/system/change-password', auth, async (req, res, next) => {
  try {
    const { oldPassword, newPassword } = req.body

    if (!oldPassword || !newPassword) {
      return res.status(400).json({ code: 1, message: '缺少密码参数' })
    }

    // Enforce stronger password policy
    if (newPassword.length < 8) {
      return res.status(400).json({ code: 1, message: '新密码至少8位' })
    }

    if (newPassword.length > 128) {
      return res.status(400).json({ code: 1, message: '新密码不能超过128位' })
    }

    // Check password complexity
    const hasUpperCase = /[A-Z]/.test(newPassword)
    const hasLowerCase = /[a-z]/.test(newPassword)
    const hasNumber = /\d/.test(newPassword)

    if (!hasUpperCase || !hasLowerCase || !hasNumber) {
      return res.status(400).json({
        code: 1,
        message: '新密码必须包含大写字母、小写字母和数字'
      })
    }

    const userId = req.user.id

    // Get current password
    const [[user]] = await pool.query('SELECT password FROM users WHERE id = ?', [userId])
    if (!user) {
      return res.status(404).json({ code: 1, message: '用户不存在' })
    }

    // Verify old password
    const isValid = await bcrypt.compare(oldPassword, user.password)
    if (!isValid) {
      return res.status(400).json({ code: 1, message: '原密码错误' })
    }

    // Hash new password
    const hashedPassword = await bcrypt.hash(newPassword, 10)

    // Update password
    await pool.query('UPDATE users SET password = ? WHERE id = ?', [hashedPassword, userId])

    // Log password change
    const ipAddress = req.headers['x-forwarded-for'] || req.connection.remoteAddress
    await pool.query(
      'INSERT INTO password_change_logs (user_type, user_id, changed_by, ip) VALUES (?, ?, ?, ?)',
      ['system', userId, 'self', ipAddress]
    )

    res.json({ code: 0, message: '密码修改成功' })
  } catch (err) {
    next(err)
  }
})

// PUT /api/identity/h5/:userId/parent - Change H5 user parent (superadmin only)
router.put('/h5/:userId/parent', auth, async (req, res, next) => {
  try {
    if (req.user.role !== 'admin') {
      return res.status(403).json({ code: 1, message: '仅超级管理员可修改推荐关系' })
    }

    const { userId } = req.params
    const { newParentId, reason } = req.body

    // Get current parent
    const [[h5User]] = await pool.query('SELECT parent_id FROM h5_users WHERE id = ?', [userId])
    if (!h5User) {
      return res.status(404).json({ code: 1, message: 'H5用户不存在' })
    }

    const oldParentId = h5User.parent_id

    // Validate new parent exists if provided
    if (newParentId) {
      const [[newParent]] = await pool.query('SELECT id FROM h5_users WHERE id = ?', [newParentId])
      if (!newParent) {
        return res.status(400).json({ code: 1, message: '新推荐人不存在' })
      }

      // Prevent circular reference
      if (parseInt(newParentId) === parseInt(userId)) {
        return res.status(400).json({ code: 1, message: '不能将自己设为推荐人' })
      }
    }

    // Update parent
    await pool.query('UPDATE h5_users SET parent_id = ? WHERE id = ?', [newParentId || null, userId])

    // Log change
    await pool.query(
      'INSERT INTO h5_user_parent_logs (h5_user_id, old_parent_id, new_parent_id, operator_id, reason) VALUES (?, ?, ?, ?, ?)',
      [userId, oldParentId, newParentId || null, req.user.id, reason || null]
    )

    res.json({ code: 0, message: '推荐关系已更新' })
  } catch (err) {
    next(err)
  }
})

export default router
