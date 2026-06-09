import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requireRole } from '../middleware/rbac.js'
import path from 'path'
import fs from 'fs'
import { fileURLToPath } from 'url'
import { createRequire } from 'module'

const require = createRequire(import.meta.url)
const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

const router = Router()
const TABLE = 'server_profiles'
const activeChildren = new Map()

// ============================================================
// 模块 key 别名映射
// ============================================================
const MODULE_KEY_ALIASES = {
  'ai-classroom': 'ai-class.js',
  'excel-analyzer':  null,
  'in-out':          'inventory.js',
  'gift-approvals':  'gift-approvals.js',
  'aftersale':       'aftersales.js',
  'referral':        'referral.js',
}

// ============================================================
// 动态构建 module → route 文件映射
// ============================================================
let _moduleRouteMap = null
let _moduleRouteMapBuilt = false

async function buildModuleRouteMap() {
  if (_moduleRouteMapBuilt) return _moduleRouteMap
  _moduleRouteMapBuilt = true

  _moduleRouteMap = {
    '_base': [],
    'orders': ['routes/orders.js'],
    'products': ['routes/products.js'],
    'finance': ['routes/finance-simple.js'],
    'dashboard': ['routes/dashboard.js'],
    'ai-classroom': ['routes/ai-class.js'],
    'excel-analyzer': ['routes/import.js', 'routes/excelReport.js', 'routes/bi.js'],
    'settings': ['routes/settings.js', 'routes/rbac/permissions.js', 'routes/rbac/menus.js', 'routes/rbac/roles.js', 'routes/rbac/userRoles.js'],
    'oa': ['routes/oa.js', 'routes/card.js', 'routes/approvals.js'],
    'tasks': ['routes/tasks.js'],
    'qrcode': ['routes/qrcode.js'],
    'in-out': ['routes/inventory.js'],
    'warehouses': ['routes/warehouses.js'],
    'alerts': ['routes/alerts.js'],
    'transfer': ['routes/transfer.js'],
    'returns': ['routes/returns.js'],
    'retail': ['routes/retail.js'],
    'gift-approvals': ['routes/gift-approvals.js'],
    'aftersale': ['routes/aftersales.js'],
    'reports': ['routes/reports.js'],
    'suppliers': ['routes/suppliers.js'],
    'dealers': ['routes/dealers.js'],
    'stores': ['routes/stores.js', 'routes/store.js'],
    'referral': ['routes/referral.js'],
    'users': ['routes/users.js'],
    'roles': ['routes/rbac/roles.js'],
    'job-responsibilities': ['routes/responsibilities.js'],
    'server_profiles': ['routes/server-profiles.js'],
    'auth': ['routes/auth.js'],
  }
  return _moduleRouteMap
}

async function getEnabledRouteFiles(enabledModuleKeys) {
  const map = await buildModuleRouteMap()
  const files = new Set()
  files.add('routes/server-profiles.js')
  for (const key of enabledModuleKeys) {
    const routes = map[key] || []
    for (const r of routes) files.add(r)
  }
  return Array.from(files)
}

function getDistPath(profileId, profile) {
  return `/app/dist/`
}

// ============================================================
// 执行同步（独立进程模式）
// 所有同步逻辑在 child 进程运行，通过文件与主进程通信
// 主 Express handler 只负责：启动 child → 立即返回 taskId → 每5秒轮询文件状态
// ============================================================
const syncProgressDir = '/tmp/sync-progress'
const syncWorkDir = '/tmp/sync-work'
;[syncProgressDir, syncWorkDir].forEach(d => {
  try { fs.mkdirSync(d, { recursive: true }) } catch (_) {}
})

// POST /:id/exec-sync — 立即返回 taskId，子进程跑同步
router.post('/:id/exec-sync', requireRole('admin'), async (req, res) => {
  const { spawn } = await import('child_process')
  const crypto = await import('crypto')
  const taskId = crypto.randomUUID()
  const profileId = req.params.id

  // 立即返回 taskId
  res.json({ code: 0, data: { taskId } })

  // 写初始状态文件
  const stateFile = syncProgressDir + '/' + taskId + '.json'
  fs.writeFileSync(stateFile, JSON.stringify({ status: 'starting', step: 0, label: '准备开始...', percent: 0, logs: [] }))

  // 启动独立子进程跑完整同步逻辑
  // 用临时文件而非 --eval（避免 stdin 管道问题）
  const scriptFile = syncWorkDir + '/sync-' + taskId + '.mjs'
  const syncScript = `
import fs from 'fs';
import { pool as mysqlPool } from '/app/db/connection.js';
import { spawn } from 'child_process';

const taskId = '${taskId}';
const profileId = '${profileId}';
const stateFile = '${stateFile}';

function writeState(data) {
  fs.writeFileSync(stateFile, JSON.stringify({ taskId, ...data }));
}
function writeLog(line) {
  try {
    const s = JSON.parse(fs.readFileSync(stateFile, 'utf8'));
    s.logs = s.logs || [];
    s.logs.push(line);
    fs.writeFileSync(stateFile, JSON.stringify(s));
  } catch(_){}
}

writeState({ status: 'running', step: 1, label: '正在同步前端文件...', percent: 5 });

(async () => {
try {
  const [rows] = await mysqlPool.query('SELECT * FROM server_profiles WHERE id = ?', [profileId]);
  if (!rows[0]) { writeState({ status: 'error', label: 'Profile不存在', percent: 0 }); return; }
  const profile = rows[0];

  const [mods] = await mysqlPool.query('SELECT module_key FROM server_modules WHERE server_profile_id = ?', [profileId]);
  const enabledModuleKeys = mods.map(m => m.module_key);

  const sshKey  = profile.ssh_key_path || '/root/clawgdqshop.pem';
  const sshPort = profile.ssh_port || 22;
  const remoteAddr = profile.ip;
  const sshUser = profile.ssh_user || 'ubuntu';
  const sshPass = profile.ssh_password || '';
  const usePassword = !!sshPass;
  const sshConnStr = usePassword
    ? "sshpass -p '" + sshPass + "' -- ssh -p " + sshPort + " -o StrictHostKeyChecking=no -o ConnectTimeout=15"
    : 'ssh -i ' + sshKey + ' -p ' + sshPort + ' -o StrictHostKeyChecking=no -o ConnectTimeout=15';

  async function runSSH(cmd) {
    return new Promise((resolve, reject) => {
      const sshBase = (usePassword ? "sshpass -p '" + sshPass + "' -- ssh" : 'ssh') + ' -p ' + sshPort + ' -o StrictHostKeyChecking=no' + (usePassword ? '' : ' -i ' + sshKey);
      const fullCmd = sshBase + ' ' + sshUser + '@' + remoteAddr + ' "' + cmd.replace(/"/g, '\\"') + '"';
      const child = spawn('sh', ['-c', fullCmd], { timeout: 60000 });
      let err = '';
      child.stderr.on('data', d => err += d);
      child.on('close', code => code === 0 ? resolve() : reject(new Error(err || 'ssh exit ' + code)));
      child.on('error', reject);
    });
  }

  const srcPath = '/app/dist/';
  const remoteDir = '/home/' + sshUser + '/dist_sync';
  writeState({ status: 'running', step: 1, label: '正在同步前端文件...', percent: 10 });

  // Step 1: mkdir
  writeState({ status: 'running', step: 1, label: '正在创建目标目录...', percent: 5 });
  await runSSH('mkdir -p ' + remoteDir + '/server/routes');

  // Step 2: tar+SSH 同步 dist
  writeState({ status: 'running', step: 2, label: '正在同步前端文件...', percent: 15 });
  await new Promise((resolve, reject) => {
    // tar 通过 SSH 管道传输，不用 rsync 协议，避免 SSH 握手挂住
    const sshBase = (usePassword ? "sshpass -p '" + sshPass + "' -- ssh" : 'ssh') + ' -p ' + sshPort + ' -o StrictHostKeyChecking=no' + (usePassword ? '' : ' -i ' + sshKey);
    const tarCmd = 'cd ' + srcPath + ' && tar cf - . | ' + sshBase + ' ' + sshUser + '@' + remoteAddr + ' "mkdir -p ' + remoteDir + ' && cd ' + remoteDir + ' && tar xf -"';
    const child = spawn('sh', ['-c', tarCmd], { timeout: 600000 });
    child.on('close', code => code === 0 ? resolve() : reject(new Error('[dist tar] exit ' + code)));
    child.on('error', reject);
  });
  writeState({ status: 'running', step: 3, label: '前端文件同步完成', percent: 40 });

  // Step 3: tar+SSH 同步 routes
  writeState({ status: 'running', step: 4, label: '正在同步后端路由...', percent: 45 });
  await new Promise((resolve, reject) => {
    const sshBase = (usePassword ? "sshpass -p '" + sshPass + "' -- ssh" : 'ssh') + ' -p ' + sshPort + ' -o StrictHostKeyChecking=no' + (usePassword ? '' : ' -i ' + sshKey);
    const tarCmd = 'cd /app && tar cf - routes | ' + sshBase + ' ' + sshUser + '@' + remoteAddr + ' "mkdir -p ' + remoteDir + '/server && cd ' + remoteDir + '/server && tar xf -"';
    const child = spawn('sh', ['-c', tarCmd], { timeout: 300000 });
    child.on('close', code => code === 0 ? resolve() : reject(new Error('[routes tar] exit ' + code)));
    child.on('error', reject);
  });
  writeState({ status: 'running', step: 5, label: '后端路由同步完成', percent: 65 });

  // Step 4: MySQL sync — 同步模块配置
  writeState({ status: 'running', step: 6, label: '正在同步模块配置...', percent: 70 });
  const remoteDb = profile.mysql_db || 'gdq';
  const remoteDbUser = profile.mysql_user || 'root';
  const remoteDbPass = profile.mysql_password || '';
  const mysqlViaSSH = (sql) => {
    const mysqlCmd = remoteDbPass
      ? 'mysql -u' + remoteDbUser + ' -p' + remoteDbPass + ' ' + remoteDb + ' -e "' + sql + '"'
      : 'mysql -u' + remoteDbUser + ' ' + remoteDb + ' -e "' + sql + '"';
    return new Promise((resolve, reject) => {
      const fullCmd = (usePassword ? "sshpass -p '" + sshPass + "' -- " : '') + 'ssh -p ' + sshPort + ' -o StrictHostKeyChecking=no ' + sshUser + '@' + remoteAddr + ' "' + mysqlCmd.replace(/"/g, '\\\\"') + '"';
      const child = spawn('sh', ['-c', fullCmd], { timeout: 15000 });
      let out = '', err = '';
      child.stdout.on('data', d => out += d);
      child.stderr.on('data', d => err += d);
      child.on('close', code => code === 0 ? resolve(out.trim()) : reject(new Error('[MySQL] exit ' + code + ': ' + (err || out))));
      child.on('error', reject);
    });
  };
  let toAdd = [], toDelete = [];
  try {
    const remoteStr = await mysqlViaSSH('SELECT module_key FROM server_modules WHERE server_profile_id = ' + profileId);
    const remote = remoteStr ? remoteStr.split('\\n').map(s => s.trim()).filter(Boolean) : [];
    const localSet = new Set(enabledModuleKeys);
    const remoteSet = new Set(remote);
    toDelete = remote.filter(m => !localSet.has(m));
    toAdd = enabledModuleKeys.filter(m => !remoteSet.has(m));
    if (toDelete.length > 0) {
      const keys = toDelete.map(m => "'" + m + "'").join(',');
      await mysqlViaSSH('DELETE FROM server_modules WHERE server_profile_id = ' + profileId + ' AND module_key IN (' + keys + ')');
    }
    if (toAdd.length > 0) {
      const vals = toAdd.map(m => '(' + profileId + ", '" + m + "')").join(', ');
      await mysqlViaSSH('INSERT INTO server_modules (server_profile_id, module_key) VALUES ' + vals);
    }
  } catch (me) { writeLog('[MySQL跳过] ' + me.message); }
  writeState({ status: 'running', step: 7, label: '模块配置同步完成', percent: 85 });

  // Step 5: mv to nginx root
  let targetNginx = '/var/www/caimeite/';
  if (profile.env === 'production' || profile.name === '北京') targetNginx = '/var/www/claw.gdqshop.cn/';
  const useSudo = profile.env === 'production' || profile.name === '北京';
  const mvCmd = (useSudo ? 'sudo ' : '') + 'cp -rf ' + remoteDir + '/* ' + targetNginx + '/ && ' + (useSudo ? 'sudo ' : '') + 'rm -rf ' + remoteDir + ' 2>/dev/null; true';
  await runSSH(mvCmd);

  writeState({ status: 'done', step: 8, label: '部署完成', percent: 100, moduleSync: { toAdd, toDelete } });

} catch (err) {
  writeState({ status: 'error', step: 0, label: err.message, percent: 0 });
}
})();
`
  fs.writeFileSync(scriptFile, syncScript)

  // 保持引用防止GC回收
  const child = spawn('node', [scriptFile], {
    stdio: ['ignore', 'pipe', 'pipe'],
    cwd: '/app',
    timeout: 0
  })
  child.stdout.on('data', () => {})
  child.stderr.on('data', () => {})
  child.on('error', () => {})
  activeChildren.set(taskId, child)
  child.on('close', () => { activeChildren.delete(taskId) })
})

// GET /:id/sync-progress/:taskId — 轮询查进度
router.get('/:id/sync-progress/:taskId', requireRole('admin'), async (req, res) => {
  const { taskId } = req.params
  const stateFile = syncProgressDir + '/' + taskId + '.json'
  try {
    const raw = fs.readFileSync(stateFile, 'utf8')
    const state = JSON.parse(raw)
    res.json({ code: 0, data: state })
  } catch (_) {
    res.json({ code: 0, data: { status: 'not_found' } })
  }
})

// ============================================================
// 同步预览
// ============================================================
router.post('/:id/sync', async (req, res) => {
  try {
    const [rows] = await pool.query(`SELECT * FROM ${TABLE} WHERE id = ?`, [req.params.id])
    if (!rows[0]) return res.status(404).json({ code: 404, message: 'Server not found' })
    const profile = rows[0]
    const [mods] = await pool.query('SELECT module_key FROM server_modules WHERE server_profile_id = ?', [req.params.id])
    const moduleKeys = mods.map(m => m.module_key)
    const baseFiles = ['routes/server-profiles.js', 'routes/index.js']
    const moduleRouteFiles = await getEnabledRouteFiles(moduleKeys)
    const [modRows] = await pool.query('SELECT `key`, label_zh FROM menu_modules')
    const modNameMap = {}
    for (const r of modRows) modNameMap[r.key] = r.label_zh
    const map = await buildModuleRouteMap()
    const grouped = {}
    for (const key of moduleKeys) {
      const name = modNameMap[key] || key
      const routes = map[key] || []
      if (routes.length > 0) grouped[name] = routes
    }
    const distPath = getDistPath(req.params.id, profile)
    res.json({ code: 0, data: { profile, moduleKeys, files: { base: baseFiles, modules: moduleRouteFiles }, grouped, distPath } })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// 列表
router.get('/', async (req, res) => {
  try {
    const [rows] = await pool.query(`SELECT sp.*, GROUP_CONCAT(sm.module_key) as _modules FROM ${TABLE} sp LEFT JOIN server_modules sm ON sm.server_profile_id = sp.id GROUP BY sp.id ORDER BY sp.id`)
    rows.forEach(r => { r.modules = r._modules ? r._modules.split(',') : []; delete r._modules })
    res.json({ code: 0, data: rows })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

// 行业模板
router.get('/industry-templates', async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT id, `key`, label_zh, label_en, description, modules, sort_order FROM industry_templates ORDER BY sort_order')
    res.json({ code: 0, data: rows })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

// 可用模块
router.get('/available-modules', async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT m.key as module_key, m.label_zh, m.label_en, m.category FROM menu_modules m ORDER BY m.sort_order, m.key')
    const modules = rows.map(r => ({
      module_key: r.module_key,
      name: r.label_zh && r.label_en ? r.label_zh + ' / ' + r.label_en : (r.label_zh || r.label_en || r.module_key),
      label_zh: r.label_zh, label_en: r.label_en, category: r.category || 'main'
    }))
    res.json({ code: 0, data: modules })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

// 详情
router.get('/:id', async (req, res) => {
  try {
    const [rows] = await pool.query(`SELECT * FROM ${TABLE} WHERE id = ?`, [req.params.id])
    if (!rows[0]) return res.status(404).json({ code: 404, message: 'Not found' })
    const [mods] = await pool.query('SELECT module_key FROM server_modules WHERE server_profile_id = ?', [req.params.id])
    rows[0].modules = mods.map(m => m.module_key)
    res.json({ code: 0, data: rows[0] })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

// 新增
router.post('/', async (req, res) => {
  try {
    const { name, ip, ssh_port, ssh_user, ssh_key_path, ssh_auth_type, ssh_password, description, env, build_date, manager, domain, website, remark, modules, site_name_zh, site_name_en, language, currency, industry, wechat_appid } = req.body
    const mysqlBuildDate = build_date ? new Date(build_date).toISOString().slice(0, 10) : null
    const mysqlLanguage = Array.isArray(language) ? JSON.stringify(language) : language
    const [result] = await pool.query(
      `INSERT INTO ${TABLE} (name, ip, ssh_port, ssh_user, ssh_key_path, ssh_auth_type, ssh_password, description, env, build_date, manager, domain, website, remark, site_name_zh, site_name_en, language, currency, industry, wechat_appid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [name, ip, ssh_port, ssh_user, ssh_key_path, ssh_auth_type, ssh_password, description, env, mysqlBuildDate, manager, domain, website, remark, site_name_zh, site_name_en, mysqlLanguage, currency, industry, wechat_appid]
    )
    const profileId = result.insertId
    if (modules && modules.length) {
      const values = modules.map(m => [profileId, m])
      await pool.query('INSERT INTO server_modules (server_profile_id, module_key) VALUES ?', [values])
    }
    res.json({ code: 0, data: { id: profileId } })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

// 更新
router.put('/:id', async (req, res) => {
  try {
    const { name, ip, ssh_port, ssh_user, ssh_key_path, ssh_auth_type, ssh_password, description, env, build_date, manager, domain, website, remark, modules, site_name_zh, site_name_en, language, currency, industry, wechat_appid } = req.body
    const mysqlBuildDate = build_date ? new Date(build_date).toISOString().slice(0, 10) : null
    const mysqlLanguage = Array.isArray(language) ? JSON.stringify(language) : language
    await pool.query(
      `UPDATE ${TABLE} SET name=?, ip=?, ssh_port=?, ssh_user=?, ssh_key_path=?, ssh_auth_type=?, ssh_password=?, description=?, env=?, build_date=?, manager=?, domain=?, website=?, remark=?, site_name_zh=?, site_name_en=?, language=?, currency=?, industry=?, wechat_appid=? WHERE id=?`,
      [name, ip, ssh_port, ssh_user, ssh_key_path, ssh_auth_type, ssh_password, description, env, mysqlBuildDate, manager, domain, website, remark, site_name_zh, site_name_en, mysqlLanguage, currency, industry, wechat_appid, req.params.id]
    )
    await pool.query('DELETE FROM server_modules WHERE server_profile_id=?', [req.params.id])
    if (modules && modules.length) {
      const values = modules.map(m => [parseInt(req.params.id), m])
      await pool.query('INSERT INTO server_modules (server_profile_id, module_key) VALUES ?', [values])
    }
    res.json({ code: 0, data: { id: req.params.id } })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

// 删除
router.delete('/:id', async (req, res) => {
  try {
    await pool.query('DELETE FROM server_modules WHERE server_profile_id=?', [req.params.id])
    await pool.query(`DELETE FROM ${TABLE} WHERE id=?`, [req.params.id])
    res.json({ code: 0, data: {} })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

// ============================================================
// 模块 CRUD
// ============================================================

// GET /:id/modules — 查询该配置文件的所有模块
router.get('/:id/modules', async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT sm.*, mm.label_zh, mm.label_en, mm.category
       FROM server_modules sm
       LEFT JOIN menu_modules mm ON sm.module_key = mm.key
       WHERE sm.server_profile_id = ?`,
      [req.params.id]
    )
    res.json({ code: 0, data: rows })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

// POST /:id/modules — 增单个模块
router.post('/:id/modules', async (req, res) => {
  try {
    const { module_key } = req.body
    if (!module_key) return res.status(400).json({ code: 400, message: 'module_key is required' })
    // 避免重复
    const [existing] = await pool.query(
      'SELECT id FROM server_modules WHERE server_profile_id = ? AND module_key = ?',
      [req.params.id, module_key]
    )
    if (existing.length > 0) {
      return res.json({ code: 0, data: { id: existing[0].id }, message: 'already exists' })
    }
    const [result] = await pool.query(
      'INSERT INTO server_modules (server_profile_id, module_key) VALUES (?, ?)',
      [req.params.id, module_key]
    )
    res.json({ code: 0, data: { id: result.insertId, module_key } })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

// PUT /:id/modules — 批量同步模块（替换整个列表）
router.put('/:id/modules', async (req, res) => {
  try {
    const { modules } = req.body
    if (!Array.isArray(modules)) return res.status(400).json({ code: 400, message: 'modules must be an array' })
    await pool.query('DELETE FROM server_modules WHERE server_profile_id = ?', [req.params.id])
    if (modules.length > 0) {
      const values = modules.map(m => [parseInt(req.params.id), m])
      await pool.query('INSERT INTO server_modules (server_profile_id, module_key) VALUES ?', [values])
    }
    res.json({ code: 0, data: { modules } })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

// DELETE /:id/modules/:module_key — 删单个模块
router.delete('/:id/modules/:module_key', async (req, res) => {
  try {
    await pool.query(
      'DELETE FROM server_modules WHERE server_profile_id = ? AND module_key = ?',
      [req.params.id, req.params.module_key]
    )
    res.json({ code: 0, data: {} })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

export default router
