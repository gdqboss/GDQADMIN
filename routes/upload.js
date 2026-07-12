import express from 'express'
import sharp from 'sharp'
import fs from 'fs'
import crypto from 'crypto'
import { upload, uploadTask } from '../middleware/upload.js'
import { pool } from '../db/connection.js'

const router = express.Router()
const uploadDir = '/home/gdq/server/uploads/products'

// 确保上传目录存在
if (!fs.existsSync(uploadDir)) {
  fs.mkdirSync(uploadDir, { recursive: true })
}

// 通用函数：计算文件 sha256
function fileSha256(filePath) {
  return new Promise((resolve, reject) => {
    const hash = crypto.createHash('sha256')
    const stream = fs.createReadStream(filePath)
    stream.on('data', d => hash.update(d))
    stream.on('end', () => resolve(hash.digest('hex')))
    stream.on('error', reject)
  })
}

// 通用函数：保存图片到图片库（含去重）
async function saveToImageLibrary(url, userId, category = 'other', filename = null, size = null, contentHash = null) {
  try {
    await pool.query(
      'INSERT IGNORE INTO images (user_id, category, url, content_hash, filename, size) VALUES (?, ?, ?, ?, ?, ?)',
      [userId || null, category, url, contentHash || null, filename || null, size || null]
    )
  } catch (e) {
    console.error('保存到图片库失败:', e)
  }
}

// 通用函数：上传前去重，返回 { isDuplicate, existingUrl } 或 null
async function checkImageDuplicate(filePath, category) {
  try {
    const hash = await fileSha256(filePath)
    const [rows] = await pool.query(
      'SELECT url FROM images WHERE content_hash = ? AND category = ? LIMIT 1',
      [hash, category]
    )
    if (rows.length > 0) {
      return { isDuplicate: true, existingUrl: rows[0].url, contentHash: hash }
    }
    return { isDuplicate: false, existingUrl: null, contentHash: hash }
  } catch (e) {
    console.error('查重失败:', e)
    return { isDuplicate: false, existingUrl: null, contentHash: null }
  }
}

// GET /api/upload/images - 获取当前用户的已上传图片列表
router.get('/images', async (req, res) => {
  try {
    const page = parseInt(req.query.page) || 1
    const limit = parseInt(req.query.limit) || 30
    const offset = (page - 1) * limit
    const category = req.query.category || null

    let where = 'WHERE user_id = ?'
    const params = [req.user.id]
    if (category) {
      where += ' AND category = ?'
      params.push(category)
    }

    const [rows] = await pool.query(
      `SELECT id, category, url, filename, size, created_at FROM images ${where} ORDER BY created_at DESC LIMIT ? OFFSET ?`,
      [...params, limit, offset]
    )
    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM images ${where}`, params
    )

    res.json({ code: 0, data: { list: rows, total, page, limit } })
  } catch (e) {
    console.error('获取图片列表失败:', e)
    res.status(500).json({ code: 500, message: '获取图片列表失败' })
  }
})

// POST /api/upload/product-image - 带压缩
router.post('/product-image', upload.single('file'), async (req, res) => {
  if (!req.file) return res.status(400).json({ code: 400, message: '未收到文件' })
  
  const userId = req.user?.id
  const filePath = uploadDir + '/' + req.file.filename

  // 先去重
  const dup = await checkImageDuplicate(filePath, 'product')
  if (dup?.isDuplicate) {
    // 存在相同图片，删除刚上传的文件，返回已有 URL
    try { fs.unlinkSync(filePath) } catch (e) {}
    return res.json({ code: 0, data: { url: dup.existingUrl }, message: 'ok (dup)' })
  }

  try {
    await sharp(filePath)
      .resize(1280, null, { withoutEnlargement: true })
      .jpeg({ quality: 80 })
      .toFile(filePath.replace(/\.[^.]+$/, '_compressed.jpg'))
    
    fs.unlinkSync(filePath)
    const newPath = filePath.replace(/\.[^.]+$/, '.jpg')
    fs.renameSync(filePath.replace(/\.[^.]+$/, '_compressed.jpg'), newPath)
    req.file.filename = req.file.filename.replace(/\.[^.]+$/, '.jpg')
  } catch(e) {
    console.error('压缩失败:', e)
  }
  
  const url = '/uploads/products/' + req.file.filename
  // 自动保存到图片库（含 hash）
  await saveToImageLibrary(url, userId, 'product', req.file.originalname, req.file.size, dup?.contentHash)
  res.json({ code: 0, data: { url }, message: 'ok' })
})

// POST /api/upload/task-attachment
router.post('/task-attachment', uploadTask.single('file'), async (req, res) => {
  try {
    if (!req.file) {
      return res.status(400).json({ code: 400, message: '未上传文件' })
    }
    const fileUrl = '/uploads/tasks/' + req.file.filename
    res.json({ code: 0, data: { url: fileUrl }, message: 'ok' })
  } catch (error) {
    console.error('Upload error:', error)
    res.status(500).json({ code: 500, message: '上传失败' })
  }
})

// POST /api/upload - 通用图片上传
router.post('/', upload.single('file'), async (req, res) => {
  if (!req.file) {
    return res.status(400).json({ code: 400, message: '未收到文件' })
  }
  
  const userId = req.user?.id
  const category = req.body.category || 'other'
  const filePath = uploadDir + '/' + req.file.filename

  // 先去重
  const dup = await checkImageDuplicate(filePath, category)
  if (dup?.isDuplicate) {
    try { fs.unlinkSync(filePath) } catch (e) {}
    return res.json({ code: 0, data: { url: dup.existingUrl }, message: 'ok (dup)' })
  }

  const fileUrl = '/uploads/products/' + req.file.filename
  
  // 自动保存到图片库（含 hash）
  await saveToImageLibrary(fileUrl, userId, category, req.file.originalname, req.file.size, dup?.contentHash)
  
  res.json({
    code: 0,
    data: { url: fileUrl },
    message: '上传成功'
  })
})

// POST /api/upload/image - 通用图片上传
router.post('/image', upload.single('file'), async (req, res) => {
  if (!req.file) {
    return res.status(400).json({ code: 400, message: '未收到文件' })
  }
  
  const userId = req.user?.id
  const category = req.body.category || 'other'
  const filePath = uploadDir + '/' + req.file.filename

  // 先去重
  const dup = await checkImageDuplicate(filePath, category)
  if (dup?.isDuplicate) {
    try { fs.unlinkSync(filePath) } catch (e) {}
    return res.json({ code: 0, data: { url: dup.existingUrl }, message: 'ok (dup)' })
  }

  const fileUrl = '/uploads/products/' + req.file.filename
  
  // 自动保存到图片库（含 hash）
  await saveToImageLibrary(fileUrl, userId, category, req.file.originalname, req.file.size, dup?.contentHash)
  
  res.json({
    code: 0,
    data: { url: fileUrl },
    message: '上传成功'
  })
})

export default router
