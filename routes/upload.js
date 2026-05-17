import express from 'express'
import sharp from 'sharp'
import fs from 'fs'
import { upload, uploadTask } from '../middleware/upload.js'
import { pool } from '../db/connection.js'

const router = express.Router()
const uploadDir = '/home/gdq/server/uploads/products'

// 确保上传目录存在
if (!fs.existsSync(uploadDir)) {
  fs.mkdirSync(uploadDir, { recursive: true })
}

// 通用函数：保存图片到图片库
async function saveToImageLibrary(url, userId, category = 'other', filename = null, size = null) {
  try {
    await pool.query(
      'INSERT INTO images (user_id, category, url, filename, size) VALUES (?, ?, ?, ?, ?)',
      [userId || null, category, url, filename || null, size || null]
    )
  } catch (e) {
    console.error('保存到图片库失败:', e)
  }
}

// POST /api/upload/product-image - 带压缩
router.post('/product-image', upload.single('file'), async (req, res) => {
  if (!req.file) return res.status(400).json({ code: 400, message: '未收到文件' })
  
  const userId = req.user?.id
  const filePath = uploadDir + '/' + req.file.filename
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
  // 自动保存到图片库
  await saveToImageLibrary(url, userId, 'product', req.file.originalname, req.file.size)
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
  const fileUrl = '/uploads/products/' + req.file.filename
  
  // 自动保存到图片库
  await saveToImageLibrary(fileUrl, userId, category, req.file.originalname, req.file.size)
  
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
  const fileUrl = '/uploads/products/' + req.file.filename
  
  // 自动保存到图片库
  await saveToImageLibrary(fileUrl, userId, category, req.file.originalname, req.file.size)
  
  res.json({
    code: 0,
    data: { url: fileUrl },
    message: '上传成功'
  })
})

export default router
