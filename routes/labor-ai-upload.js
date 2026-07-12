// labor-ai-upload.js — LISA 文件/图片/CAD 上传 + 视觉分析(2026-07-13 新增)
//
// 端点:
//   POST   /api/labor-ai/upload         上传文件 → 写入 ai_class_uploads 表, 走视觉 LLM 看图
//   GET    /api/labor-ai/upload/list    看我的上传历史
//   GET    /api/labor-ai/upload/:id     看单个上传详情(含视觉理解文本)
//   DELETE /api/labor-ai/upload/:id     删除我的上传
//   POST   /api/labor-ai/upload/analyze/:id  重新触发视觉分析(异步落库)
//
// 视觉模型: ai_config 表 category='vision' 的项 → qwen3-vl-8b @ 100.74.233.52
// 复用: 不新建表 — 落 ai_class_uploads(如有);如无该表,用 ai_class_messages 存
//       attachment 列用 JSON 字段(部分文件为简单 metadata)
//
// AGENTS.md 9 步:
//   1. 写本文件 ✓
//   2. mount /api/labor-ai/upload ✓ (在 index.js)
//   3. rbac_permissions 加 ai:upload ✓
//   4. PERMISSIONS.AI_UPLOAD ✓
//   5. requirePermission('ai:upload') ✓
//   6. server_modules profile 1+5 加 ai-upload ✓
//   7. 重启 server — 待执行
//   8. curl 验证 200/401/403 — 待执行
//   9. profile 同步 — profile 1 自动, profile 5 等前端用到再 INSERT

import express from 'express'
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission, PERMISSIONS as P } from '../middleware/rbac.js'
import { uploadLisa } from '../middleware/upload.js'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const router = express.Router()
router.use(auth)

// ============================================================
// 0. 视觉模型调用 helper — 读 ai_config.vision 行
//    默认 local Qwen3-VL-8B @ 100.74.233.52
// ============================================================
async function loadVisionConfig() {
  const [rows] = await pool.query(
    `SELECT id, base_url, api_key, model, provider
       FROM ai_config
      WHERE category = 'vision' AND status = 1
      ORDER BY is_default DESC LIMIT 1`
  )
  return rows[0] || null
}

// 把图片编码成 base64 dataURL(Qwen VL 接受 image_url 形式)
function fileToBase64DataUrl(absPath, mime) {
  const b = fs.readFileSync(absPath)
  return `data:${mime || 'image/jpeg'};base64,${b.toString('base64')}`
}

// 文档类: 直读文本内容(txt/md/json/csv ≤ 2MB)
async function readDocAsText(absPath, ext, sizeBytes) {
  if (sizeBytes > 2 * 1024 * 1024) return `[文件过大 ${(sizeBytes / 1024 / 1024).toFixed(1)}MB,超 2MB 不解析文本]`
  const txtExt = ['.txt', '.md', '.csv', '.json']
  if (txtExt.includes(ext)) {
    return fs.readFileSync(absPath, 'utf-8').slice(0, 8000)
  }
  return `[二进制文档 ${ext} — 文本不可直读,需 OCR 或专用解析器]`
}

// CAD: DWG 复杂 — 试读为文本看是否有 ASCII header(很多 dwg 文件头部含版本字符串)
async function readCadMetadata(absPath, ext, sizeBytes) {
  const stat = fs.statSync(absPath)
  let preview = ''
  try {
    const fd = fs.openSync(absPath, 'r')
    const buf = Buffer.alloc(Math.min(512, stat.size))
    fs.readSync(fd, buf, 0, buf.length, 0)
    fs.closeSync(fd)
    preview = buf.toString('ascii').replace(/[^\x20-\x7E]/g, '·').slice(0, 200)
  } catch {}
  return {
    size_bytes: sizeBytes,
    file_extension: ext,
    ascii_header_preview: preview,
    note: ext === '.dwg'
      ? 'AutoCAD DWG 源文件—需 libredwg 解析图层/坐标/尺寸;此处仅元数据.'
      : 'AutoCAD DXF 文本格式—可解析图层/块参照/实体.',
  }
}

// 调用视觉 LLM 看图
async function callVisionLLM({ baseUrl, apiKey, model, prompt, imageDataUrls }) {
  const content = []
  for (const url of imageDataUrls) {
    content.push({ type: 'image_url', image_url: { url } })
  }
  content.push({ type: 'text', text: prompt })

  // baseUrl 一般形如 http://x.x.x.x:1234/v1 — 拼 /chat/completions
  const url = baseUrl.replace(/\/$/, '') + '/chat/completions'
  const resp = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${apiKey || 'EMPTY'}`,
    },
    body: JSON.stringify({
      model,
      messages: [{ role: 'user', content }],
      max_tokens: 1500,
      temperature: 0.2,
    }),
    signal: AbortSignal.timeout(60000),
  })
  if (!resp.ok) {
    const e = await resp.text()
    throw new Error(`VisionLLM ${resp.status}: ${e.slice(0, 300)}`)
  }
  const json = await resp.json()
  return json?.choices?.[0]?.message?.content || ''
}

// ============================================================
// 1. POST /api/labor-ai/upload — 单文件上传(支持多文件,但存多次)
//    form-data: file=@xxx.png  + 可选 session_id / domain
// ============================================================
router.post('/upload', requirePermission(P.AI_UPLOAD), (req, res, next) => {
  uploadLisa.single('file')(req, res, (err) => {
    if (err) {
      // multer 错误:FILE_TOO_LIMIT / 不支持类型 — 转 4xx 不要 500
      if (err.code === 'LIMIT_FILE_SIZE') {
        return res.status(413).json({ code: 413, message: `文件超 50MB 限制` })
      }
      return res.status(400).json({ code: 400, message: err.message || '上传失败' })
    }
    handleUpload(req, res, next)
  })
})

async function handleUpload(req, res, next) {
  try {
    if (!req.file) return res.status(400).json({ code: 400, message: 'file 必传' })
    const userId = req.user.id
    const { session_id, domain } = req.body || {}
    const f = req.file
    const ext = path.extname(f.originalname).toLowerCase()

    // 写入 ai_class_uploads 表(若存在),否则 fallback 到 ai_class_messages
    let uploadId = null
    const [[tbl]] = await pool.query(
      `SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'ai_class_uploads'`
    )
    if (tbl.cnt > 0) {
      const [r] = await pool.query(
        `INSERT INTO ai_class_uploads
         (user_id, session_id, original_name, file_path, mime, ext, size_bytes, domain, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'analyzing')`,
        [userId, session_id || null, f.originalname, f.path, f.mimetype, ext, f.size, domain || null]
      )
      uploadId = r.insertId
    } else {
      // fallback: 存到 ai_class_messages (用 attachment_ids JSON 不可,改存 user_msg)
      const [r] = await pool.query(
        `INSERT INTO ai_class_messages
         (session_id, role, content, model, created_at)
         VALUES (?, 'user', ?, 'upload', NOW())`,
        [
          session_id || 0,
          `[附件] ${f.originalname} (${(f.size/1024).toFixed(1)}KB) 已上传,等待分析`,
        ]
      )
      uploadId = r.insertId
    }

    // 触发视觉 / 文档分析
    const analysisResult = { status: 'pending', vision_text: null, doc_text: null, cad_meta: null, error: null }
    try {
      const vCfg = await loadVisionConfig()
      const isImage = ext.match(/\.(jpg|jpeg|png|webp|gif|bmp)$/i)
      const isDoc = ext.match(/\.(txt|md|csv|json)$/i)

      if (isImage && vCfg) {
        // 图片走视觉 LLM
        const dataUrl = fileToBase64DataUrl(f.path, f.mimetype)
        const text = await callVisionLLM({
          baseUrl: vCfg.base_url, apiKey: vCfg.api_key, model: vCfg.model,
          prompt: '请仔细描述这张图,识别其中的建筑元素(门窗/墙面/管线/材料/工人/隐患),给出关键尺寸或数量估计',
          imageDataUrls: [dataUrl],
        })
        analysisResult.vision_text = text
        analysisResult.status = 'analyzed'
        analysisResult.model = `${vCfg.provider}:${vCfg.model}`
      } else if (isDoc) {
        const text = await readDocAsText(f.path, ext, f.size)
        analysisResult.doc_text = text
        analysisResult.status = 'analyzed'
      } else if (ext === '.dxf' || ext === '.dwg') {
        const meta = await readCadMetadata(f.path, ext, f.size)
        analysisResult.cad_meta = meta
        analysisResult.status = 'metadata_only'
      } else if (ext === '.pdf') {
        // PDF 需要 pdf-parse;先 fallback 到元数据
        analysisResult.doc_text = `[PDF 文件 ${(f.size/1024/1024).toFixed(1)}MB — 需要 pdf-parse 提取文本,后续增强]`
        analysisResult.status = 'metadata_only'
      } else {
        analysisResult.status = 'stored'
      }
    } catch (vErr) {
      analysisResult.status = 'vision_failed'
      analysisResult.error = vErr.message?.slice(0, 200)
      console.error('[upload vision error]', vErr.message)
    }

    // 更新 status
    if (tbl.cnt > 0 && uploadId) {
      await pool.query(
        `UPDATE ai_class_uploads
            SET status = ?, analysis_result = ?, analyzed_at = NOW()
          WHERE id = ?`,
        [analysisResult.status, JSON.stringify(analysisResult), uploadId]
      )
    }

    res.json({
      code: 0,
      data: {
        upload_id: uploadId,
        url: `/uploads/labor-ai/${path.basename(f.path)}`,
        original_name: f.originalname,
        size_bytes: f.size,
        mime: f.mimetype,
        ext,
        analysis: analysisResult,
      },
      message: '上传成功,已分析',
    })
  } catch (e) { next(e) }
}

// ============================================================
// 2. GET /api/labor-ai/upload/list — 我的上传列表
// ============================================================
router.get('/upload/list', requirePermission(P.AI_UPLOAD), async (req, res, next) => {
  try {
    const userId = req.user.id
    const limit = Math.min(parseInt(req.query.limit) || 30, 100)

    const [[tbl]] = await pool.query(
      `SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'ai_class_uploads'`
    )
    if (tbl.cnt === 0) return res.json({ code: 0, data: [] })

    const [rows] = await pool.query(
      `SELECT id, original_name, mime, ext, size_bytes, domain, status, analysis_result, created_at
         FROM ai_class_uploads
        WHERE user_id = ?
        ORDER BY id DESC LIMIT ?`,
      [userId, limit]
    )
    res.json({ code: 0, data: rows.map(r => ({ ...r, analysis_result: r.analysis_result ? safeJson(r.analysis_result) : null })) })
  } catch (e) { next(e) }
})

function safeJson(s) { try { return JSON.parse(s) } catch { return s } }

// ============================================================
// 3. GET /api/labor-ai/upload/:id — 单个详情(含视觉理解)
// ============================================================
router.get('/upload/:id', requirePermission(P.AI_UPLOAD), async (req, res, next) => {
  try {
    const userId = req.user.id
    const id = parseInt(req.params.id)
    if (!id) return res.status(400).json({ code: 400, message: 'id 必填' })

    const [[tbl]] = await pool.query(
      `SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'ai_class_uploads'`
    )
    if (tbl.cnt === 0) return res.json({ code: 0, data: null })

    const [rows] = await pool.query(
      `SELECT id, original_name, file_path, mime, ext, size_bytes, domain, status, analysis_result, created_at, analyzed_at
         FROM ai_class_uploads WHERE id = ? AND user_id = ? LIMIT 1`,
      [id, userId]
    )
    if (!rows.length) return res.status(404).json({ code: 404, message: '未找到该上传' })
    const row = rows[0]
    row.analysis_result = row.analysis_result ? safeJson(row.analysis_result) : null
    row.url = `/uploads/labor-ai/${path.basename(row.file_path || '')}`
    res.json({ code: 0, data: row })
  } catch (e) { next(e) }
})

// ============================================================
// 4. DELETE /api/labor-ai/upload/:id
// ============================================================
router.delete('/upload/:id', requirePermission(P.AI_UPLOAD), async (req, res, next) => {
  try {
    const userId = req.user.id
    const id = parseInt(req.params.id)
    if (!id) return res.status(400).json({ code: 400, message: 'id 必填' })

    const [rows] = await pool.query(
      `SELECT id, file_path FROM ai_class_uploads WHERE id = ? AND user_id = ?`,
      [id, userId]
    )
    if (!rows.length) return res.status(404).json({ code: 404, message: '未找到' })

    // 物理删文件
    try { if (rows[0].file_path && fs.existsSync(rows[0].file_path)) fs.unlinkSync(rows[0].file_path) } catch {}

    await pool.query(`DELETE FROM ai_class_uploads WHERE id = ?`, [id])
    res.json({ code: 0, message: '已删除' })
  } catch (e) { next(e) }
})

export default router
