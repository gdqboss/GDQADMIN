// crypto-util.js — AES-256-GCM 加密工具 (2026-09-18 江小鱼立)
//
// 设计: AES-256-GCM 加密 (authenticated encryption)
//       密钥从 .env SECRET_ENCRYPTION_KEY 读 (32 bytes hex = 64 chars)
//       每文档独立 IV (12 bytes), 跟密文一起存
//
// 用法:
//   import { encrypt, decrypt } from './crypto-util.js'
//   const { ciphertext, iv, tag } = encrypt('机密内容')
//   const plain = decrypt(ciphertext, iv, tag)
//
// 安全注意:
//   - 密钥不能进 DB / 不能进 git
//   - .env SECRET_ENCRYPTION_KEY 必须生成: openssl rand -hex 32
//   - 每文档独立 IV (防相同原文 → 相同密文)
//   - GCM tag 保证完整性 (防篡改)

import crypto from 'crypto'

const ALGO = 'aes-256-gcm'
const IV_LENGTH = 12  // GCM 推荐 12 bytes

function getKey() {
  const hex = process.env.SECRET_ENCRYPTION_KEY
  if (!hex) {
    throw new Error('SECRET_ENCRYPTION_KEY not set in .env (run: openssl rand -hex 32)')
  }
  const key = Buffer.from(hex, 'hex')
  if (key.length !== 32) {
    throw new Error(`SECRET_ENCRYPTION_KEY must be 32 bytes (got ${key.length})`)
  }
  return key
}

/**
 * 加密 → 返回 base64 密文 + iv + tag
 */
export function encrypt(plaintext) {
  const key = getKey()
  const iv = crypto.randomBytes(IV_LENGTH)
  const cipher = crypto.createCipheriv(ALGO, key, iv)
  const enc = Buffer.concat([cipher.update(plaintext, 'utf8'), cipher.final()])
  const tag = cipher.getAuthTag()
  return {
    ciphertext: enc.toString('base64'),
    iv: iv.toString('hex'),
    tag: tag.toString('hex'),
  }
}

/**
 * 解密 → 返原文
 * throw if tag 校验失败 (被篡改)
 */
export function decrypt(ciphertextB64, ivHex, tagHex) {
  const key = getKey()
  const iv = Buffer.from(ivHex, 'hex')
  const tag = Buffer.from(tagHex, 'hex')
  const ciphertext = Buffer.from(ciphertextB64, 'base64')
  const decipher = crypto.createDecipheriv(ALGO, key, iv)
  decipher.setAuthTag(tag)
  const dec = Buffer.concat([decipher.update(ciphertext), decipher.final()])
  return dec.toString('utf8')
}

/**
 * SHA-256 hash — 用于完整性校验
 */
export function sha256(text) {
  return crypto.createHash('sha256').update(text, 'utf8').digest('hex')
}