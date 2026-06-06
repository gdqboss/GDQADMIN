import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requireRole } from '../middleware/rbac.js'
import path from 'path'
import fs from 'fs'
import { fileURLToPath } from 'url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

const router = Router()
const TABLE = 'server_profiles'

// ============================================================
// 模块 key 别名映射：module_key (menu_modules.key) → route 文件名
// 用于处理 module_key 和 route 文件名不一致的情况
// ============================================================
const MODULE_KEY_ALIASES = {
  'ai-classroom': 'ai-class.js',
  'excel-analyzer':  null, // 特殊：映射到多个文件，在 buildModuleRouteMap 中处理
  'in-out':          'inventory.js',
  'gift-approvals':  'gift-approvals.js',
  'aftersale':       'aftersales.js',
  'referral':        'referral.js',
}

// ============================================================
// 动态构建 module → route 文件映射
// 从 index.js 的 import 语句解析，cache起来避免重复读文件
// ============================================================
let _moduleRouteMap = null
let _moduleRouteMapBuilt = false

/**
 * 从 index.js 解析：module_key → [route file paths]
 * 返回结构：{ 'orders': ['routes/orders.js'], 'finance': ['routes/finance-simple.js'], ... }
 */
async function buildModuleRouteMap() {
  if (_moduleRouteMapBuilt) return _moduleRouteMap
  _moduleRouteMapBuilt = true

  const indexPath = path.join(__dirname, '..', 'index.js')
  const content = await fs.promises.readFile(indexPath, 'utf8')

  // 解析所有 import xxx from './routes/yyy.js' 格式
  // 匹配：import SomethingRoutes from './routes/filename.js'
  const importRe = /import\s+\w+Routes?\s+from\s+['"]\.\/routes\/([^'"]+)\.js['"]/g
  const routeFileToModule = {} // route filename → module_key it belongs to

  let match
  while ((match = importRe.exec(content)) !== null) {
    const routeFile = match[1] // e.g. 'orders', 'finance-simple', 'ai-class'
    // 接下来看它被哪个 app.use('/api/xxx', ...)关联到哪个 module
    // 这里我们用 alias 表做反向映射
    routeFileToModule[routeFile] = routeFile
  }

  // 手动建立 module_key → route 文件列表的映射
  // 有些 module_key 和 route 文件名不一致，需要 alias
  _moduleRouteMap = {
    // 基础路由（始终同步，无模块化）
    '_base': [],

    // orders 模块 → orders.js
    'orders': ['routes/orders.js'],

    // products 模块 → products.js
    'products': ['routes/products.js'],

    // finance 模块 → finance-simple.js
    'finance': ['routes/finance-simple.js'],

    // dashboard 模块 → dashboard.js
    'dashboard': ['routes/dashboard.js'],

    // ai-classroom 模块 → ai-class.js
    'ai-classroom': ['routes/ai-class.js'],

    // excel-analyzer 模块 → import.js + excelReport.js + bi.js（多个文件）
    'excel-analyzer': ['routes/import.js', 'routes/excelReport.js', 'routes/bi.js'],

    // settings 模块 → settings.js + rbac/*.js
    'settings': ['routes/settings.js', 'routes/rbac/permissions.js', 'routes/rbac/menus.js', 'routes/rbac/roles.js', 'routes/rbac/userRoles.js'],

    // oa 模块 → oa.js + card.js + approvals.js
    'oa': ['routes/oa.js', 'routes/card.js', 'routes/approvals.js'],

    // tasks 模块 → tasks.js
    'tasks': ['routes/tasks.js'],

    // qrcode 模块 → qrcode.js
    'qrcode': ['routes/qrcode.js'],

    // in-out（出入库记录）模块 → inventory.js
    'in-out': ['routes/inventory.js'],

    // warehouses 模块 → warehouses.js
    'warehouses': ['routes/warehouses.js'],

    // alerts 模块 → alerts.js
    'alerts': ['routes/alerts.js'],

    // transfer 模块 → transfer.js
    'transfer': ['routes/transfer.js'],

    // returns 模块 → returns.js
    'returns': ['routes/returns.js'],

    // retail 模块 → retail.js
    'retail': ['routes/retail.js'],

    // gift-approvals 模块 → gift-approvals.js
    'gift-approvals': ['routes/gift-approvals.js'],

    // aftersale 模块 → aftersales.js
    'aftersale': ['routes/aftersales.js'],

    // reports 模块 → reports.js
    'reports': ['routes/reports.js'],

    // suppliers 模块 → suppliers.js
    'suppliers': ['routes/suppliers.js'],

    // dealers 模块 → dealers.js
    'dealers': ['routes/dealers.js'],

    // stores 模块 → stores.js + store.js
    'stores': ['routes/stores.js', 'routes/store.js'],

    // referral 模块 → referral.js
    'referral': ['routes/referral.js'],

    // auth 模块（始终加载）
    'auth': ['routes/auth.js'],
  }

  return _moduleRouteMap
}

/**
 * 根据勾选的 module_keys，返回需要同步的后端路由文件列表
 */
async function getEnabledRouteFiles(enabledModuleKeys) {
  const map = await buildModuleRouteMap()
  const files = new Set()

  // 基础文件始终同步
  files.add('routes/server-profiles.js')

  // 按勾选模块收集路由文件
  for (const key of enabledModuleKeys) {
    const routes = map[key] || []
    for (const r of routes) {
      files.add(r)
    }
  }

  return Array.from(files)
}

// ============================================================
// 后端模块验证：同步前检查目标服务器模块是否一致
// ============================================================
async function validateBackendModules(profileId, remoteAddr, sshKey, sshPort) {
  // 查询本地该 profile 勾选的模块
  const [localMods] = await pool.query(
    'SELECT module_key FROM server_modules WHERE server_profile_id = ?',
    [profileId]
  )
  const localModuleKeys = new Set(localMods.map(m => m.module_key))

  // 暂时不阻止同步，返回验证信息供管理界面参考
  return {
    localModuleKeys: Array.from(localModuleKeys),
    warning: null
  }
}

// ============================================================
// 获取远程服务器当前部署的模块信息（通过检查文件是否存在）
// ============================================================
async function getRemoteDeployedModules(remoteAddr, sshKey, sshPort, targetNginxRoot) {
  // 暂时跳过远程文件检查，返回空表示不阻止同步
  return { deployed: [], missing: [] }
}

// ============================================================
// 获取该 profile 对应的 dist 目录路径
// 前端 per-server build 输出到 /app/dist-{profile_id}/
// 前端统一 build 输出到 /app/dist/（容器内路径，宿主机 /home/gdq/dist 挂载到这里）
function getDistPath(profileId, profile) {
  return `/app/dist/`
}

// ============================================================
// 执行同步
// ============================================================
router.post('/:id/exec-sync', async (req, res) => {
  try {
    const { spawn } = await import('child_process')
    const [rows] = await pool.query(`SELECT * FROM ${TABLE} WHERE id = ?`, [req.params.id])
    if (!rows[0]) return res.status(404).json({ code: 404, message: 'Server not found' })
    const profile = rows[0]

    // 查询该服务器勾选的模块
    const [mods] = await pool.query('SELECT module_key FROM server_modules WHERE server_profile_id = ?', [req.params.id])
    const enabledModuleKeys = mods.map(m => m.module_key)

    const sshKey = profile.ssh_key_path || '/root/clawgdqshop.pem'
    const sshPort = profile.ssh_port || 22
    const remoteAddr = profile.ip
    const sshCmd = `ssh -i ${sshKey} -p ${sshPort} -o StrictHostKeyChecking=no -o PasswordAuthentication=no -o ConnectTimeout=10`

    // ============================================================
    // 同步工具函数：在宿主机上直接执行 rsync（不走容器）
    // 使用 shell: true + docker exec 联合执行
    // docker exec 进入容器后执行 ssh/rsync 命令（继承宿主机的网络）
    // ============================================================
    async function runRsyncOnHost(rsyncArgs) {
      const { spawn } = await import('child_process')
      // docker exec 让 rsync/ssh 命令使用宿主机的网络，同时访问容器内文件（挂载的 /app）
      return new Promise((resolve, reject) => {
        // 直接在宿主机 shell 执行 rsync（宿主机能访问 /home/gdq/ 和远程服务器）
        // 注意：容器内是 busybox sh，用 sh -c 而非 bash
        const child = spawn('sh', ['-c', `rsync -avz --delete ${rsyncArgs.join(' ')}`], {
          timeout: 120000,
          env: { ...process.env, RSYNC_RSH: `ssh -i ${sshKey} -p ${sshPort} -o StrictHostKeyChecking=no -o PasswordAuthentication=no -o ConnectTimeout=10` }
        })
        let err = ''
        child.stderr.on('data', d => err += d)
        child.on('close', code => {
          if (code === 0) resolve()
          else reject(new Error(err || `host rsync exit ${code}`))
        })
        child.on('error', reject)
      })
    }

    // ============================================================
    // 同步逻辑
    // ============================================================

    // Step 1: 确定源路径
    const srcPath = getDistPath(req.params.id, profile)

    // Step 2: rsync 前端
    await runRsyncOnHost([
      '-avz', '--delete',
      srcPath,
      `ubuntu@${remoteAddr}:/home/ubuntu/dist_sync/`
    ])

    // Step 3: 同步后端路由文件（按勾选模块）
    const routeFiles = await getEnabledRouteFiles(enabledModuleKeys)
    if (routeFiles.length > 0) {
        // rsync filter: 必须先 exclude 再 include（按参数顺序执行）
        // 注意：routes同步不加 --delete，因为 merge 时 sudo mv 会直接覆盖整个目录
        // 远程 server_sync/routes/ 如果有 ubuntu 无权删除的 root 子目录，--delete 会报 Permission denied
        const includeFilters = [
          '--include=routes/index.js',          // 必须最先
          '--include=routes/server-profiles.js', // 必须最先
          ...routeFiles.map(f => `--include=${f}`), // 具体模块 routes
          '--exclude=routes/*',                  // 排除其他 routes
        ]

        await runRsyncOnHost([
          '-avz',
          ...includeFilters,
          '/app/routes/',
          `ubuntu@${remoteAddr}:/home/ubuntu/server_sync/routes/`,
        ])

        // 将同步的后端文件合并到目标目录
        const mergeCmd = `sudo mkdir -p /home/ubuntu/dist_sync/server && sudo mv /home/ubuntu/server_sync/* /home/ubuntu/dist_sync/server/ 2>/dev/null || true && sudo rm -rf /home/ubuntu/server_sync`
        await new Promise((resolve, reject) => {
          const ssh = spawn('sh', ['-c',
            `ssh -i ${sshKey} -p ${sshPort} -o StrictHostKeyChecking=no ubuntu@${remoteAddr} "${mergeCmd}"`
          ], { timeout: 30000 })
          let err = ''
          ssh.stderr.on('data', d => err += d)
          ssh.on('close', code => {
            if (code !== 0) reject(new Error(err || `merge exit ${code}`))
            else resolve()
          })
          ssh.on('error', reject)
        })
      }

    // Step 4: 根据目标服务器设置正确的 nginx 根目录和网页标题
    let targetNginxRoot = '/var/www/caimeite/'
    if (profile.env === 'production' || profile.name === '北京') {
      targetNginxRoot = '/var/www/claw.gdqshop.cn/'
    }
    // 优先使用 site_name_en（英文名），否则用 site_name（中文名）
    const targetTitle = profile.site_name_en || profile.site_name || 'Caimeite'

    const mvCmd = `sudo rm -rf ${targetNginxRoot}assets ${targetNginxRoot}index.html ${targetNginxRoot}server && sudo mv /home/ubuntu/dist_sync/* ${targetNginxRoot}/ && sudo rm -rf /home/ubuntu/dist_sync && sudo sed -i "s/<title>.*<\\/title>/<title>${targetTitle}<\\/title>/" ${targetNginxRoot}index.html`

    await new Promise((resolve, reject) => {
      const mv = spawn('sh', ['-c',
        `ssh -i ${sshKey} -p ${sshPort} -o StrictHostKeyChecking=no ubuntu@${remoteAddr} "${mvCmd}"`
      ], { timeout: 30000 })
      let err = ''
      mv.stderr.on('data', d => err += d)
      mv.on('close', code => {
        if (code !== 0) reject(new Error(err || `mv exit ${code}`))
        else resolve()
      })
      mv.on('error', reject)
    })

    res.json({ code: 0, message: '同步完成', data: { routeFiles } })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// ============================================================
// 同步预览（更新为动态解析）
// ============================================================
router.post('/:id/sync', async (req, res) => {
  try {
    const [rows] = await pool.query(`SELECT * FROM ${TABLE} WHERE id = ?`, [req.params.id])
    if (!rows[0]) return res.status(404).json({ code: 404, message: 'Server not found' })
    const profile = rows[0]
    const [mods] = await pool.query('SELECT module_key FROM server_modules WHERE server_profile_id = ?', [req.params.id])
    const moduleKeys = mods.map(m => m.module_key)

    // 基础文件（全量同步）
    const baseFiles = [
      'routes/server-profiles.js',
      'routes/index.js',
    ]

    // 模块文件（按勾选，动态从 index.js 解析）
    const moduleRouteFiles = await getEnabledRouteFiles(moduleKeys)

    // 获取模块中文名（按 module_key 分组）
    const [modRows] = await pool.query('SELECT `key`, label_zh FROM menu_modules')
    const modNameMap = {}
    for (const r of modRows) modNameMap[r.key] = r.label_zh

    // 按 module_key 分组 routes 文件
    const map = await buildModuleRouteMap()
    const grouped = {}
    for (const key of moduleKeys) {
      const name = modNameMap[key] || key
      const routes = map[key] || []
      if (routes.length > 0) grouped[name] = routes
    }

    // dist 目录路径
    const distPath = getDistPath(req.params.id, profile)

    res.json({
      code: 0,
      data: {
        profile,
        moduleKeys,
        files: {
          base: baseFiles,
          modules: moduleRouteFiles
        },
        grouped,        // 按模块分组：{ '订单管理': ['routes/orders.js'], ... }
        distPath,
      }
    })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// ============================================================
// 以下是现有的 CRUD 接口（保持不变）
// ============================================================

// 列表
router.get('/', async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT sp.*,
        GROUP_CONCAT(sm.module_key) as _modules
       FROM ${TABLE} sp
       LEFT JOIN server_modules sm ON sm.server_profile_id = sp.id
       GROUP BY sp.id
       ORDER BY sp.id`
    )
    rows.forEach(r => {
      r.modules = r._modules ? r._modules.split(',') : []
      delete r._modules
    })
    res.json({ code: 0, data: rows })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// 行业模板
router.get('/industry-templates', async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT id, `key`, label_zh, label_en, description, modules, sort_order FROM industry_templates ORDER BY sort_order')
    res.json({ code: 0, data: rows })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// 可用模块（带行业分类）
router.get('/available-modules', async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT m.key as module_key, m.label_zh, m.label_en, m.category FROM menu_modules m ORDER BY m.sort_order, m.key')
    const modules = rows.map(r => ({
      module_key: r.module_key,
      name: r.label_zh && r.label_en ? `${r.label_zh} / ${r.label_en}` : (r.label_zh || r.label_en || r.module_key),
      label_zh: r.label_zh,
      label_en: r.label_en,
      category: r.category || 'main'
    }))
    res.json({ code: 0, data: modules })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// 详情
router.get('/:id', async (req, res) => {
  try {
    const [rows] = await pool.query(`SELECT * FROM ${TABLE} WHERE id = ?`, [req.params.id])
    if (!rows[0]) return res.status(404).json({ code: 404, message: 'Not found' })
    const [mods] = await pool.query('SELECT module_key FROM server_modules WHERE server_profile_id = ?', [req.params.id])
    rows[0].modules = mods.map(m => m.module_key)
    res.json({ code: 0, data: rows[0] })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// 新增
router.post('/', async (req, res) => {
  try {
    const { name, ip, ssh_port, ssh_user, ssh_key_path, ssh_auth_type, ssh_password, description, env, build_date, manager, domain, website, remark, modules, site_name_zh, site_name_en, language, currency, industry } = req.body
    const mysqlBuildDate = build_date ? new Date(build_date).toISOString().slice(0, 10) : null
    const mysqlLanguage = Array.isArray(language) ? JSON.stringify(language) : language
    const [result] = await pool.query(
      `INSERT INTO ${TABLE} (name, ip, ssh_port, ssh_user, ssh_key_path, ssh_auth_type, ssh_password, description, env, build_date, manager, domain, website, remark, site_name_zh, site_name_en, language, currency, industry)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [name, ip, ssh_port, ssh_user, ssh_key_path, ssh_auth_type, ssh_password, description, env, mysqlBuildDate, manager, domain, website, remark, site_name_zh, site_name_en, mysqlLanguage, currency, industry]
    )
    const profileId = result.insertId
    if (modules && modules.length) {
      const values = modules.map(m => [profileId, m])
      await pool.query('INSERT INTO server_modules (server_profile_id, module_key) VALUES ?', [values])
    }
    res.json({ code: 0, data: { id: profileId } })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// 更新
router.put('/:id', async (req, res) => {
  try {
    const { name, ip, ssh_port, ssh_user, ssh_key_path, ssh_auth_type, ssh_password, description, env, build_date, manager, domain, website, remark, modules, site_name_zh, site_name_en, language, currency, industry } = req.body
    console.log(`[server-profiles PUT /${req.params.id}] modules received:`, JSON.stringify(modules), 'type:', typeof modules, 'length:', modules ? modules.length : 'undefined')
    const mysqlBuildDate = build_date ? new Date(build_date).toISOString().slice(0, 10) : null
    const mysqlLanguage = Array.isArray(language) ? JSON.stringify(language) : language
    await pool.query(
      `UPDATE ${TABLE} SET name=?, ip=?, ssh_port=?, ssh_user=?, ssh_key_path=?, ssh_auth_type=?, ssh_password=?, description=?, env=?, build_date=?, manager=?, domain=?, website=?, remark=?, site_name_zh=?, site_name_en=?, language=?, currency=?, industry=? WHERE id=?`,
      [name, ip, ssh_port, ssh_user, ssh_key_path, ssh_auth_type, ssh_password, description, env, mysqlBuildDate, manager, domain, website, remark, site_name_zh, site_name_en, mysqlLanguage, currency, industry, req.params.id]
    )
    await pool.query('DELETE FROM server_modules WHERE server_profile_id=?', [req.params.id])
    if (modules && modules.length) {
      const values = modules.map(m => [parseInt(req.params.id), m])
      await pool.query('INSERT INTO server_modules (server_profile_id, module_key) VALUES ?', [values])
    }
    res.json({ code: 0, data: { id: req.params.id } })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// 删除
router.delete('/:id', async (req, res) => {
  try {
    await pool.query('DELETE FROM server_modules WHERE server_profile_id=?', [req.params.id])
    await pool.query(`DELETE FROM ${TABLE} WHERE id=?`, [req.params.id])
    res.json({ code: 0, data: {} })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

export default router
