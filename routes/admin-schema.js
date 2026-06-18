/**
 * 系统 Schema 管理 API
 *
 * 用途：开发者/AI 课堂管理员查看当前 DB 的完整结构（表+字段+权限）
 * 数据源：services/system-schema-generator.js（5分钟缓存）
 *
 * 权限：仅 admin 角色可访问（敏感的系统结构信息）
 */

import express from 'express'
import { auth } from '../middleware/auth.js'
import { requireRole, ROLES } from '../middleware/rbac.js'
import {
  generateSystemSchema,
  schemaToPrompt,
  clearSchemaCache,
  categorizeTable
} from '../services/system-schema-generator.js'

const router = express.Router()

/**
 * GET /api/admin/schema/export
 *
 * Query params:
 *   ?category=商品           只返回某分类的表
 *   ?format=json|markdown    返回格式（默认 json）
 *   ?force=1                 强制刷新缓存
 */
router.get('/export', auth, requireRole(ROLES.ADMIN), async (req, res) => {
  try {
    const { category, format = 'json', force } = req.query
    const data = await generateSystemSchema(force === '1' || force === 'true')

    if (format === 'markdown' || format === 'prompt') {
      const promptFragment = schemaToPrompt(data)
      if (category) {
        const re = new RegExp(`### ${category}[\\s\\S]*?(?=\\n### |$)`)
        const match = promptFragment.match(re)
        return res.json({
          code: 0,
          data: {
            category,
            fragment: match ? match[0] : `（未找到分类：${category}）`,
            length: match ? match[0].length : 0
          }
        })
      }
      return res.json({
        code: 0,
        data: {
          full_prompt: promptFragment,
          length: promptFragment.length,
          table_count: data.table_count,
          permission_count: data.permission_count
        }
      })
    }

    if (category) {
      const filtered = {}
      for (const [tname, tinfo] of Object.entries(data.tables)) {
        if (categorizeTable(tname) === category) {
          filtered[tname] = tinfo
        }
      }
      return res.json({
        code: 0,
        data: {
          category,
          tables: filtered,
          count: Object.keys(filtered).length
        }
      })
    }

    res.json({ code: 0, data })
  } catch (e) {
    console.error('schema export failed:', e)
    res.status(500).json({ code: -1, message: '导出失败: ' + e.message })
  }
})

/**
 * GET /api/admin/schema/categories
 * 返回所有表分类 + 每分类表数量（前端导航用）
 */
router.get('/categories', auth, requireRole(ROLES.ADMIN), async (req, res) => {
  try {
    const data = await generateSystemSchema()
    const counts = {}
    for (const tname of Object.keys(data.tables)) {
      const cat = categorizeTable(tname)
      counts[cat] = (counts[cat] || 0) + 1
    }
    res.json({
      code: 0,
      data: {
        categories: Object.entries(counts)
          .map(([name, count]) => ({ name, count }))
          .sort((a, b) => b.count - a.count),
        total_tables: data.table_count,
        total_permissions: data.permission_count,
        generated_at: data.generated_at,
        cache_age_seconds: Math.floor((Date.now() - new Date(data.generated_at).getTime()) / 1000)
      }
    })
  } catch (e) {
    console.error('schema categories failed:', e)
    res.status(500).json({ code: -1, message: e.message })
  }
})

/**
 * POST /api/admin/schema/refresh
 * 强制刷新缓存（DB 结构变更后调用）
 */
router.post('/refresh', auth, requireRole(ROLES.ADMIN), (req, res) => {
  clearSchemaCache()
  res.json({ code: 0, message: 'Schema 缓存已清空，下次访问将重新生成' })
})

export default router
