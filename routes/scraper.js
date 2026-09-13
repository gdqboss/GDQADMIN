/**
 * Scrapling 抓取模块路由
 * 江小鱼 2026-09-13 加
 *
 * 端点:
 *   GET    /api/scraper/jobs        列表(分页+筛选)
 *   POST   /api/scraper/jobs        新建任务
 *   GET    /api/scraper/jobs/:id    详情
 *   PUT    /api/scraper/jobs/:id    修改
 *   DELETE /api/scraper/jobs/:id    删除
 *   POST   /api/scraper/jobs/:id/run  手动执行 → 写 scraper_results
 *   GET    /api/scraper/results     抓取结果历史(分页)
 */
import { Router } from 'express'
import { spawn } from 'child_process'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import { requirePermission, PERMISSIONS } from '../middleware/rbac.js'

const router = Router()

// runner 路径: /root/server/scraper/scraper_runner.py
// venv:        /root/sandbox/scrapling-test/.venv/bin/python
const PYTHON = '/root/sandbox/scrapling-test/.venv/bin/python'
const RUNNER = '/root/server/scraper/scraper_runner.py'
const RUN_TIMEOUT_MS = 60000  // 单次抓取最长 60s

// 4 个权限的便捷别名, 跟 rbac.js 里加的 PERMISSIONS.SCRAPER_* 一致
const p = {
  read:   PERMISSIONS.SCRAPER_READ,
  write:  PERMISSIONS.SCRAPER_WRITE,
  run:    PERMISSIONS.SCRAPER_RUN,
  del:    PERMISSIONS.SCRAPER_DELETE,
}

// ---------- 子进程调 runner ----------
function runScraper(payload) {
  return new Promise((resolve) => {
    const child = spawn(PYTHON, [RUNNER], { timeout: RUN_TIMEOUT_MS })
    let stdout = ''
    let stderr = ''
    child.stdout.on('data', (b) => { stdout += b.toString() })
    child.stderr.on('data', (b) => { stderr += b.toString() })
    child.on('error', (err) => resolve({ ok: false, error: `spawn 失败: ${err.message}` }))
    child.on('close', (code) => {
      if (code !== 0) {
        return resolve({ ok: false, error: `runner exit ${code}`, trace: stderr.slice(0, 2000) })
      }
      try {
        resolve(JSON.parse(stdout))
      } catch (e) {
        resolve({ ok: false, error: `runner 输出非 JSON: ${e.message}`, trace: stdout.slice(0, 500) })
      }
    })
    child.stdin.write(JSON.stringify(payload))
    child.stdin.end()
  })
}

// ---------- GET /api/scraper/jobs ----------
router.get('/jobs', requirePermission(p.read), async (req, res, next) => {
  try {
    const { status, keyword } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE 1=1'
    const params = [], countParams = []
    if (status) {
      where += ' AND status = ?'
      params.push(status)
      countParams.push(status)
    }
    if (keyword) {
      where += ' AND (name LIKE ? OR url LIKE ?)'
      const kw = `%${keyword}%`
      params.push(kw, kw)
      countParams.push(kw, kw)
    }

    const countSql = `SELECT COUNT(*) AS total FROM scraper_jobs ${where}`
    const [[{ total }]] = await pool.query(countSql, countParams)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(
      `SELECT * FROM scraper_jobs ${where} ORDER BY id DESC LIMIT ? OFFSET ?`,
      params
    )
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (e) { next(e) }
})

// ---------- GET /api/scraper/jobs/:id ----------
router.get('/jobs/:id(\\d+)', requirePermission(p.read), async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM scraper_jobs WHERE id = ?', [req.params.id])
    if (!rows.length) return res.status(404).json({ code: 404, message: '任务不存在' })
    res.json({ code: 0, data: rows[0], message: 'ok' })
  } catch (e) { next(e) }
})

// ---------- POST /api/scraper/jobs ----------
router.post('/jobs', requirePermission(p.write), async (req, res, next) => {
  try {
    const { name, url, selector, selector_type = 'css', fetch_mode = 'fetcher',
            headers = null, cron = null, status = 'active' } = req.body || {}

    if (!name || !url || !selector) {
      return res.status(400).json({ code: 400, message: 'name / url / selector 都必填' })
    }
    if (!['css', 'xpath', 'text'].includes(selector_type)) {
      return res.status(400).json({ code: 400, message: 'selector_type 必须是 css/xpath/text' })
    }
    if (!['fetcher', 'dynamic', 'stealthy'].includes(fetch_mode)) {
      return res.status(400).json({ code: 400, message: 'fetch_mode 必须是 fetcher/dynamic/stealthy' })
    }
    if (!['active', 'paused', 'disabled'].includes(status)) {
      return res.status(400).json({ code: 400, message: 'status 必须是 active/paused/disabled' })
    }

    const [r] = await pool.query(
      `INSERT INTO scraper_jobs
        (name, url, selector, selector_type, fetch_mode, headers, cron, status, created_by)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [name, url, selector, selector_type, fetch_mode,
       headers ? JSON.stringify(headers) : null, cron, status,
       req.user?.id || null]
    )
    res.json({ code: 0, data: { id: r.insertId }, message: 'ok' })
  } catch (e) { next(e) }
})

// ---------- PUT /api/scraper/jobs/:id ----------
router.put('/jobs/:id(\\d+)', requirePermission(p.write), async (req, res, next) => {
  try {
    const id = req.params.id
    const fields = ['name', 'url', 'selector', 'selector_type', 'fetch_mode',
                    'headers', 'cron', 'status']
    const sets = [], params = []
    for (const f of fields) {
      if (req.body && Object.prototype.hasOwnProperty.call(req.body, f)) {
        sets.push(`${f} = ?`)
        params.push(f === 'headers' && req.body[f] ? JSON.stringify(req.body[f]) : req.body[f])
      }
    }
    if (!sets.length) return res.status(400).json({ code: 400, message: '无可改字段' })
    params.push(id)
    const [r] = await pool.query(
      `UPDATE scraper_jobs SET ${sets.join(', ')} WHERE id = ?`, params
    )
    if (!r.affectedRows) return res.status(404).json({ code: 404, message: '任务不存在' })
    res.json({ code: 0, data: { affected: r.affectedRows }, message: 'ok' })
  } catch (e) { next(e) }
})

// ---------- DELETE /api/scraper/jobs/:id ----------
router.delete('/jobs/:id(\\d+)', requirePermission(p.del), async (req, res, next) => {
  try {
    const [r] = await pool.query('DELETE FROM scraper_jobs WHERE id = ?', [req.params.id])
    if (!r.affectedRows) return res.status(404).json({ code: 404, message: '任务不存在' })
    res.json({ code: 0, data: { affected: r.affectedRows }, message: 'ok' })
  } catch (e) { next(e) }
})

// ---------- POST /api/scraper/jobs/:id/run ----------
router.post('/jobs/:id(\\d+)/run', requirePermission(p.run), async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM scraper_jobs WHERE id = ?', [req.params.id])
    if (!rows.length) return res.status(404).json({ code: 404, message: '任务不存在' })
    const job = rows[0]

    const payload = {
      url: job.url,
      selector: job.selector,
      selector_type: job.selector_type,
      fetch_mode: job.fetch_mode,
      headers: job.headers || {},
    }
    const result = await runScraper(payload)

    const preview = result.ok ? (result.items || []).slice(0, 20) : null
    await pool.query(
      `INSERT INTO scraper_results
        (job_id, items_count, items_preview, elapsed_ms, http_status, final_url,
         status, error, trace, run_by)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        job.id,
        result.ok ? (result.count || 0) : 0,
        preview ? JSON.stringify(preview) : null,
        result.ok ? (result.elapsed_ms || null) : null,
        result.ok ? (result.status || null) : null,
        result.ok ? (result.url || null) : null,
        result.ok ? 'success' : 'failed',
        result.ok ? null : (result.error || 'unknown'),
        result.ok ? null : (result.trace || null).slice(0, 60000),
        req.user?.id || null,
      ]
    )

    // 更新 jobs 的 last_run_* 字段
    await pool.query(
      `UPDATE scraper_jobs SET
         last_run_at = NOW(),
         last_status = ?,
         last_error = ?
       WHERE id = ?`,
      [
        result.ok ? 'success' : 'failed',
        result.ok ? null : (result.error || '').slice(0, 500),
        job.id,
      ]
    )

    res.json({
      code: result.ok ? 0 : 500,
      data: result,
      message: result.ok ? 'ok' : '抓取失败',
    })
  } catch (e) { next(e) }
})

// ---------- GET /api/scraper/results ----------
router.get('/results', requirePermission(p.read), async (req, res, next) => {
  try {
    const { job_id, status } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE 1=1'
    const params = [], countParams = []
    if (job_id) {
      where += ' AND r.job_id = ?'
      params.push(job_id)
      countParams.push(job_id)
    }
    if (status) {
      where += ' AND r.status = ?'
      params.push(status)
      countParams.push(status)
    }

    const countSql = `SELECT COUNT(*) AS total FROM scraper_results r ${where}`
    const [[{ total }]] = await pool.query(countSql, countParams)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(
      `SELECT r.*, j.name AS job_name, j.url AS job_url
       FROM scraper_results r
       LEFT JOIN scraper_jobs j ON j.id = r.job_id
       ${where}
       ORDER BY r.id DESC LIMIT ? OFFSET ?`,
      params
    )
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (e) { next(e) }
})

export default router