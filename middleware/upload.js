import multer from 'multer'
import path from 'path'
import { fileURLToPath } from 'url'
import fs from 'fs'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const uploadDir = path.join(__dirname, '../uploads/products')
const aftersaleUploadDir = path.join(__dirname, '../uploads/aftersale')
const invoiceUploadDir = path.join(__dirname, '../uploads/invoices')
const taskUploadDir = path.join(__dirname, '../uploads/tasks')
if (!fs.existsSync(uploadDir)) fs.mkdirSync(uploadDir, { recursive: true })
if (!fs.existsSync(aftersaleUploadDir)) fs.mkdirSync(aftersaleUploadDir, { recursive: true })
if (!fs.existsSync(invoiceUploadDir)) fs.mkdirSync(invoiceUploadDir, { recursive: true })
if (!fs.existsSync(taskUploadDir)) fs.mkdirSync(taskUploadDir, { recursive: true })

const storage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, uploadDir),
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase()
    cb(null, `${Date.now()}-${Math.random().toString(36).slice(2)}${ext}`)
  },
})

const fileFilter = (req, file, cb) => {
  const allowed = ['.jpg', '.jpeg', '.png', '.webp']
  const ext = path.extname(file.originalname).toLowerCase()
  if (allowed.includes(ext)) cb(null, true)
  else cb(new Error('只允许上传 jpg/png/webp 格式'), false)
}

export const upload = multer({
  storage,
  fileFilter,
  limits: { fileSize: 5 * 1024 * 1024 }, // 5MB
})

// After-sale image upload configuration
const aftersaleStorage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, aftersaleUploadDir),
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase()
    cb(null, `${Date.now()}-${Math.random().toString(36).slice(2)}${ext}`)
  },
})

export const uploadAftersale = multer({
  storage: aftersaleStorage,
  fileFilter,
  limits: { fileSize: 5 * 1024 * 1024 }, // 5MB
})

// Invoice image upload configuration
const invoiceStorage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, invoiceUploadDir),
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase()
    cb(null, `${Date.now()}-${Math.random().toString(36).slice(2)}${ext}`)
  },
})

const invoiceFileFilter = (req, file, cb) => {
  const allowed = ['.jpg', '.jpeg', '.png', '.pdf']
  const ext = path.extname(file.originalname).toLowerCase()
  if (allowed.includes(ext)) cb(null, true)
  else cb(new Error('只允许上传 jpg/png/pdf 格式'), false)
}

export const uploadInvoice = multer({
  storage: invoiceStorage,
  fileFilter: invoiceFileFilter,
  limits: { fileSize: 10 * 1024 * 1024 }, // 10MB
})

// Feedback image upload configuration
const feedbackUploadDir = path.join(__dirname, '../uploads/feedback')
if (!fs.existsSync(feedbackUploadDir)) fs.mkdirSync(feedbackUploadDir, { recursive: true })

const feedbackStorage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, feedbackUploadDir),
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase()
    cb(null, `${Date.now()}-${Math.random().toString(36).slice(2)}${ext}`)
  },
})

export const uploadFeedback = multer({
  storage: feedbackStorage,
  fileFilter,
  limits: { fileSize: 5 * 1024 * 1024 }, // 5MB
})

// Task attachment upload configuration
const taskStorage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, taskUploadDir),
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase()
    cb(null, `${Date.now()}-${Math.random().toString(36).slice(2)}${ext}`)
  },
})

const taskFileFilter = (req, file, cb) => {
  const allowed = ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.heic', '.heif']
  const ext = path.extname(file.originalname).toLowerCase()
  if (allowed.includes(ext) || file.mimetype.startsWith('image/')) cb(null, true)
  else cb(new Error('只允许上传图片格式'), false)
}

export const uploadTask = multer({
  storage: taskStorage,
  fileFilter: taskFileFilter,
  limits: { fileSize: 5 * 1024 * 1024 }, // 5MB
})

// ============================================================
// LISA AI 上传(2026-07-13 新增)
// 支持: 图片(摄像头/截图) + 文档(txt/md/pdf/docx/xlsx/csv/json) + CAD(dwg/dxf)
// 单文件最大: 50MB(放宽,因 CAD 经常几十兆)
// 复用: 不新建 task — 直接落 ai_class_uploads 表(共用)
// ============================================================
const lisaUploadDir = path.join(__dirname, '../uploads/labor-ai')
if (!fs.existsSync(lisaUploadDir)) fs.mkdirSync(lisaUploadDir, { recursive: true })

const lisaStorage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, lisaUploadDir),
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase()
    const ts = Date.now()
    const rnd = Math.random().toString(36).slice(2, 8)
    cb(null, `${ts}-${rnd}${ext}`)
  },
})

const LISA_ALLOWED_EXT = [
  '.jpg', '.jpeg', '.png', '.webp', '.gif', '.bmp',  // 图片
  '.pdf', '.txt', '.md', '.csv', '.json',           // 文本/PDF
  '.docx', '.xlsx', '.doc', '.xls',                 // Office
  '.dwg', '.dxf',                                    // CAD
  '.zip',
]
const LISA_ALLOWED_MIME_PREFIX = ['image/']

function lisaFileFilter(req, file, cb) {
  const ext = path.extname(file.originalname).toLowerCase()
  if (LISA_ALLOWED_EXT.includes(ext) || LISA_ALLOWED_MIME_PREFIX.some(p => file.mimetype?.startsWith(p))) {
    cb(null, true)
  } else {
    cb(new Error(`不支持的文件类型: ${ext || file.mimetype};允许: ${LISA_ALLOWED_EXT.join(',')}`), false)
  }
}

export const uploadLisa = multer({
  storage: lisaStorage,
  fileFilter: lisaFileFilter,
  limits: { fileSize: 50 * 1024 * 1024 }, // 50MB
})
