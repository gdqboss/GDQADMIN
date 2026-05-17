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
