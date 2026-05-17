import QRCode from 'qrcode'
import { randomBytes } from 'crypto'
import { promises as fs } from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

/**
 * Generate unique identity code
 * @param {string} userType - 'system' or 'h5'
 * @param {number} userId - User ID
 * @returns {string} 32-character identity code
 */
export function generateIdentityCode(userType, userId) {
  if (!userType || !userId) {
    throw new Error('userType and userId are required')
  }

  const prefix = userType === 'h5' ? 'H5U' : 'SYS'
  const timestamp = Date.now().toString(36).toUpperCase()
  const userIdHex = userId.toString(36).toUpperCase().padStart(4, '0')
  const random = randomBytes(6).toString('hex').toUpperCase()

  // Format: PREFIX-TIMESTAMP-USERID-RANDOM (exactly 32 chars)
  const code = `${prefix}-${timestamp}-${userIdHex}-${random}`

  // Pad or trim to exactly 32 characters
  if (code.length < 32) {
    return code.padEnd(32, '0')
  }
  return code.substring(0, 32)
}

/**
 * Generate QR code image for identity code
 * @param {string} identityCode - The identity code to encode
 * @param {string} userType - 'system' or 'h5'
 * @param {number} userId - User ID
 * @returns {Promise<string>} Relative path to saved QR code image
 */
export async function generateIdentityQRCode(identityCode, userType, userId) {
  if (!identityCode || !userType || !userId) {
    throw new Error('identityCode, userType, and userId are required')
  }

  try {
    const uploadsDir = path.join(__dirname, '../uploads/identity')

    // Ensure directory exists
    await fs.mkdir(uploadsDir, { recursive: true })

    // Generate filename: identity_{userType}_{userId}_{timestamp}.png
    const timestamp = Date.now()
    const filename = `identity_${userType}_${userId}_${timestamp}.png`
    const filepath = path.join(uploadsDir, filename)

    // Generate QR code with high error correction
    await QRCode.toFile(filepath, identityCode, {
      errorCorrectionLevel: 'H',
      type: 'png',
      width: 300,
      margin: 2
    })

    // Return relative path from server root
    return `/uploads/identity/${filename}`
  } catch (error) {
    throw new Error(`Failed to generate QR code: ${error.message}`)
  }
}

/**
 * Verify identity code exists in database
 * @param {string} identityCode - The identity code to verify
 * @param {object} pool - MySQL connection pool
 * @returns {Promise<object|null>} User info if found, null otherwise
 */
export async function verifyIdentityCode(identityCode, pool) {
  if (!identityCode || !pool) {
    throw new Error('identityCode and pool are required')
  }

  try {
    // Check system users table
    const [systemUsers] = await pool.query(
      'SELECT id, name, email, role, department, "system" as user_type FROM users WHERE identity_code = ? AND status = "active"',
      [identityCode]
    )

    if (systemUsers.length > 0) {
      return systemUsers[0]
    }

    // Check H5 users table
    const [h5Users] = await pool.query(
      'SELECT id, phone, nickname, "h5" as user_type FROM h5_users WHERE identity_code = ? AND status = "active"',
      [identityCode]
    )

    if (h5Users.length > 0) {
      return h5Users[0]
    }

    return null
  } catch (error) {
    throw new Error(`Failed to verify identity code: ${error.message}`)
  }
}

/**
 * Log identity scan event
 * @param {object} params - Scan parameters
 * @param {string} params.identityCode - The scanned identity code
 * @param {string} params.scannerType - 'system' or 'h5' or 'anonymous'
 * @param {number} params.scannerId - ID of the scanner (optional)
 * @param {string} params.scanLocation - Location description (optional)
 * @param {string} params.scanIp - IP address (optional)
 * @param {object} pool - MySQL connection pool
 * @returns {Promise<number>} Insert ID of the log record
 */
export async function logIdentityScan(params, pool) {
  if (!params || !params.identityCode || !pool) {
    throw new Error('params.identityCode and pool are required')
  }

  const {
    identityCode,
    scannerType = 'anonymous',
    scannerId = null,
    scanLocation = null,
    scanIp = null
  } = params

  try {
    const [result] = await pool.query(
      `INSERT INTO identity_scan_logs
       (identity_code, scanner_type, scanner_id, scan_location, scan_ip, scanned_at)
       VALUES (?, ?, ?, ?, ?, NOW())`,
      [identityCode, scannerType, scannerId, scanLocation, scanIp]
    )

    return result.insertId
  } catch (error) {
    throw new Error(`Failed to log identity scan: ${error.message}`)
  }
}
