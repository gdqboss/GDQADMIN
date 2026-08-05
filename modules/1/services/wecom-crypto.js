/**
 * WeCom callback signature verification and message encryption/decryption
 * Uses Node.js built-in crypto module only.
 */

import crypto from 'crypto'

/**
 * Verify WeCom callback signature (SHA1)
 * @param {string} token - Callback token configured in WeCom admin
 * @param {string} timestamp
 * @param {string} nonce
 * @param {string} encrypt - Encrypted message body
 * @param {string} msgSignature - Signature to verify against
 * @returns {boolean}
 */
export function verifySignature(token, timestamp, nonce, encrypt, msgSignature) {
  const parts = [token, timestamp, nonce, encrypt].sort()
  const sha1 = crypto.createHash('sha1').update(parts.join('')).digest('hex')
  return sha1 === msgSignature
}

/**
 * Decode the EncodingAESKey from WeCom (Base64 -> 32-byte key)
 * WeCom's EncodingAESKey is a 43-char Base64 string that decodes to 32 bytes
 */
function decodeAESKey(encodingAESKey) {
  return Buffer.from(encodingAESKey + '=', 'base64')
}

/**
 * Decrypt a WeCom encrypted message
 * @param {string} encodingAESKey - The AES key from WeCom admin console
 * @param {string} encrypted - Base64-encoded encrypted message
 * @returns {{ message: string, corpId: string }}
 */
export function decryptMessage(encodingAESKey, encrypted) {
  const key = decodeAESKey(encodingAESKey)
  const iv = key.subarray(0, 16)

  const decipher = crypto.createDecipheriv('aes-256-cbc', key, iv)
  decipher.setAutoPadding(false)

  const decrypted = Buffer.concat([
    decipher.update(Buffer.from(encrypted, 'base64')),
    decipher.final()
  ])

  // Remove PKCS#7 padding
  const padLen = decrypted[decrypted.length - 1]
  const content = decrypted.subarray(0, decrypted.length - padLen)

  // Format: 16 bytes random + 4 bytes msg length (big-endian) + message + corpId
  const msgLen = content.readUInt32BE(16)
  const message = content.subarray(20, 20 + msgLen).toString('utf8')
  const corpId = content.subarray(20 + msgLen).toString('utf8')

  return { message, corpId }
}

/**
 * Encrypt a reply message for WeCom
 * @param {string} encodingAESKey
 * @param {string} corpId
 * @param {string} replyMsg - Plain text reply
 * @returns {string} Base64-encoded encrypted message
 */
export function encryptMessage(encodingAESKey, corpId, replyMsg) {
  const key = decodeAESKey(encodingAESKey)
  const iv = key.subarray(0, 16)

  const random = crypto.randomBytes(16)
  const msgBuf = Buffer.from(replyMsg, 'utf8')
  const corpBuf = Buffer.from(corpId, 'utf8')

  const msgLenBuf = Buffer.alloc(4)
  msgLenBuf.writeUInt32BE(msgBuf.length, 0)

  const plaintext = Buffer.concat([random, msgLenBuf, msgBuf, corpBuf])

  // PKCS#7 padding to 32-byte block size
  const blockSize = 32
  const padLen = blockSize - (plaintext.length % blockSize)
  const padding = Buffer.alloc(padLen, padLen)
  const padded = Buffer.concat([plaintext, padding])

  const cipher = crypto.createCipheriv('aes-256-cbc', key, iv)
  cipher.setAutoPadding(false)

  const encrypted = Buffer.concat([cipher.update(padded), cipher.final()])
  return encrypted.toString('base64')
}

/**
 * Generate a response signature
 */
export function generateSignature(token, timestamp, nonce, encrypt) {
  const parts = [token, timestamp, nonce, encrypt].sort()
  return crypto.createHash('sha1').update(parts.join('')).digest('hex')
}

/**
 * Simple XML field extraction (avoids needing an XML parser library)
 * @param {string} xml - XML string
 * @param {string} tag - Tag name to extract
 * @returns {string|null}
 */
export function extractXmlField(xml, tag) {
  // Handle CDATA: <Tag><![CDATA[value]]></Tag>
  const cdataRegex = new RegExp(`<${tag}>[\\s]*<!\\[CDATA\\[([\\s\\S]*?)\\]\\]>[\\s]*</${tag}>`)
  const cdataMatch = xml.match(cdataRegex)
  if (cdataMatch) return cdataMatch[1]

  // Handle plain text: <Tag>value</Tag>
  const plainRegex = new RegExp(`<${tag}>([\\s\\S]*?)</${tag}>`)
  const plainMatch = xml.match(plainRegex)
  if (plainMatch) return plainMatch[1]

  return null
}
