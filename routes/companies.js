import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { getCompanyScope } from '../utils/company-scope.js'

const router = Router()
const TABLE = 'companies'
const PTABLE = 'company_permissions'

// 企业状态流转：pending(入驻申请) -> active / rejected；active -> suspended(停用) / left(退驻)
const VALID_STATUS = ['pending', 'active', 'suspended', 'left', 'rejected']
const VALID_PLAN = ['standard', 'pro', 'enterprise']

// [company-iso] 2026-09-12 鉴权修复：本文件原完全无鉴权（未登录可改/删任意企业）。
// 管理动作（改/删/状态/模块开关）仅孵化器超管（global）；企业成员可读自己企业信息与模块开关；
// 入驻申请（POST）登录即可。
async function requireGlobal(req, res) {
  const scope = await getCompanyScope(req)
  if (scope.kind === 'global') return scope
  res.status(403).json({ code: 403, message: '仅孵化器管理员可执行此操作' })
  return null
}

// 企业成员可读自己企业；global 可读任意
async function canReadCompany(req, res, companyId) {
  const scope = await getCompanyScope(req)
  if (scope.kind === 'global') return scope
  if ((scope.kind === 'company-manage' || scope.kind === 'company-self') && scope.companyId === Number(companyId)) return scope
  res.status(403).json({ code: 403, message: '无权查看该企业' })
  return null
}

/**
 * 企业列表（分页/搜索/状态筛选）
 * GET /api/companies?page=&pageSize=&keyword=&status=
 */
router.get('/', auth, async (req, res, next) => {
  try {
    if (!(await requireGlobal(req, res))) return
    const { page = 1, pageSize = 20, keyword = '', status = '' } = req.query
    const where = ['1=1']
    const params = []
    if (keyword) {
      where.push('(name LIKE ? OR short_name LIKE ? OR contact_phone LIKE ?)')
      params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`)
    }
    if (status && VALID_STATUS.includes(status)) { where.push('status = ?'); params.push(status) }
    const offset = (parseInt(page) - 1) * parseInt(pageSize)
    const [rows] = await pool.query(
      `SELECT * FROM ${TABLE} WHERE ${where.join(' AND ')} ORDER BY created_at DESC LIMIT ? OFFSET ?`,
      [...params, parseInt(pageSize), offset]
    )
    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) total FROM ${TABLE} WHERE ${where.join(' AND ')}`, params
    )
    res.json({ code: 0, data: { list: rows, total } })
  } catch (e) { next(e) }
})

/**
 * 新增企业（入驻登记 → 进入 pending，等超管审核）
 * POST /api/companies
 * 字段：name(必填) short_name industry contact_phone contact_email address logo_url
 *       plan max_users remark
 */
router.post('/', auth, async (req, res, next) => {
  try {
    // [company-iso] 2026-09-12 鉴权：登录即可提交入驻申请
    const {
      name, short_name, industry, contact_phone, contact_email, address,
      logo_url, plan = 'standard', max_users = 50, remark
    } = req.body
    if (!name) return res.status(400).json({ code: 400, message: '企业名称必填' })
    if (!VALID_PLAN.includes(plan)) return res.status(400).json({ code: 400, message: '无效的企业套餐' })

    const [result] = await pool.query(
      `INSERT INTO ${TABLE} (name, short_name, industry, contact_phone, contact_email, address, logo_url, plan, max_users, remark, status)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')`,
      [name, short_name || '', industry || '', contact_phone || '', contact_email || '',
       address || '', logo_url || '', plan, max_users || 50, remark || '']
    )
    res.json({ code: 0, data: { id: result.insertId }, message: '企业入驻申请已提交，待审核' })
  } catch (e) { next(e) }
})

/**
 * 企业详情（含模块开关）
 * GET /api/companies/:id
 */
router.get('/:id', auth, async (req, res, next) => {
  try {
    if (!(await canReadCompany(req, res, req.params.id))) return
    const [[row]] = await pool.query(`SELECT * FROM ${TABLE} WHERE id = ?`, [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '企业不存在' })
    const [mods] = await pool.query(`SELECT * FROM ${PTABLE} WHERE company_id = ?`, [row.id])
    res.json({ code: 0, data: { ...row, modules: mods } })
  } catch (e) { next(e) }
})

/**
 * 修改企业信息
 * PUT /api/companies/:id
 */
router.put('/:id', auth, async (req, res, next) => {
  try {
    if (!(await requireGlobal(req, res))) return
    const id = req.params.id
    const allowed = ['name', 'short_name', 'industry', 'contact_phone', 'contact_email', 'address', 'logo_url', 'plan', 'max_users', 'remark']
    const sets = []
    const params = []
    for (const k of allowed) {
      if (req.body[k] !== undefined) { sets.push(`${k} = ?`); params.push(req.body[k]) }
    }
    if (!sets.length) return res.status(400).json({ code: 400, message: '没有可更新的字段' })
    params.push(id)
    await pool.query(`UPDATE ${TABLE} SET ${sets.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, message: '已更新' })
  } catch (e) { next(e) }
})

/**
 * 变更企业状态（入驻审核通过 / 停用 / 退驻 / 拒绝）
 * PUT /api/companies/:id/status   body: { status, reason? }
 * 审核链路：pending -> active 视为入驻通过；pending -> rejected 视为拒绝（需 reason）
 */
router.put('/:id/status', auth, async (req, res, next) => {
  try {
    if (!(await requireGlobal(req, res))) return
    const { status, reason = '' } = req.body
    if (!VALID_STATUS.includes(status)) return res.status(400).json({ code: 400, message: '无效的企业状态' })
    const id = req.params.id

    const [[row]] = await pool.query(`SELECT status FROM ${TABLE} WHERE id = ?`, [id])
    if (!row) return res.status(404).json({ code: 404, message: '企业不存在' })

    // 拒绝入驻必须有原因
    if (status === 'rejected' && !reason) return res.status(400).json({ code: 400, message: '拒绝入驻必须填写原因' })

    // 状态机约束：只允许合理流转
    const allowedTrans = {
      pending: ['active', 'rejected'],
      active: ['suspended', 'left'],
      suspended: ['active', 'left'],
      left: [],
      rejected: ['active']
    }
    const trans = allowedTrans[row.status] || []
    if (!trans.includes(status)) {
      return res.status(400).json({ code: 400, message: `状态不允许从 ${row.status} 变更为 ${status}` })
    }

    await pool.query(
      `UPDATE ${TABLE} SET status = ?, remark = CASE WHEN ? <> '' THEN ? ELSE remark END, updated_at = CURRENT_TIMESTAMP WHERE id = ?`,
      [status, reason, reason, id]
    )
    res.json({ code: 0, message: status === 'active' ? '入驻审核已通过' : '企业状态已更新' })
  } catch (e) { next(e) }
})

/**
 * 删除企业（仅允许停用/拒绝状态的软删除，不允许删 active 企业以防数据丢失）
 * DELETE /api/companies/:id
 */
router.delete('/:id', auth, async (req, res, next) => {
  try {
    if (!(await requireGlobal(req, res))) return
    const [[row]] = await pool.query(`SELECT status FROM ${TABLE} WHERE id = ?`, [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '企业不存在' })
    if (row.status === 'active') return res.status(400).json({ code: 400, message: '入驻中的企业不可直接删除，请先停用' })
    await pool.query(`DELETE FROM ${TABLE} WHERE id = ?`, [req.params.id])
    res.json({ code: 0, message: '已删除' })
  } catch (e) { next(e) }
})

/**
 * 查询企业模块开关
 * GET /api/companies/:id/modules
 */
router.get('/:id/modules', auth, async (req, res, next) => {
  try {
    if (!(await canReadCompany(req, res, req.params.id))) return
    const [rows] = await pool.query(`SELECT * FROM ${PTABLE} WHERE company_id = ?`, [req.params.id])
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

/**
 * 设置企业模块开关（数组整体覆盖）
 * PUT /api/companies/:id/modules  body: { modules: [{ module_key, enabled }] }
 */
router.put('/:id/modules', auth, async (req, res, next) => {
  try {
    if (!(await requireGlobal(req, res))) return
    const id = req.params.id
    const list = req.body.modules || []
    if (!Array.isArray(list)) return res.status(400).json({ code: 400, message: 'modules 必须为数组' })
    const conn = await pool.getConnection()
    try {
      await conn.beginTransaction()
      await conn.query(`DELETE FROM ${PTABLE} WHERE company_id = ?`, [id])
      for (const m of list) {
        if (!m || !m.module_key) continue
        await conn.query(
          `INSERT INTO ${PTABLE} (company_id, module_key, enabled) VALUES (?, ?, ?)
           ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)`,
          [id, m.module_key, m.enabled === false ? 0 : 1]
        )
      }
      await conn.commit()
      res.json({ code: 0, message: '模块开关已保存' })
    } catch (e) {
      await conn.rollback()
      throw e
    } finally {
      conn.release()
    }
  } catch (e) { next(e) }
})

export default router