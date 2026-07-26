import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requireRole } from '../middleware/rbac.js'
import path from 'path'
import fs from 'fs'
import { fileURLToPath } from 'url'
import { createRequire } from 'module'
import { spawn } from 'child_process'
import net from 'net'

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
  'ai-classroom':       'ai-class.js',
  'labor-ai-agent':     'labor-ai-agent.js',
  'ai-knowledge-domains': null,
  'excel-analyzer':     null,
  'in-out':             'inventory.js',
  'gift-approvals':     'gift-approvals.js',
  'aftersale':          'aftersales.js',
  'referral':           'referral.js',
  'labor-ai':           'labor-ai.js',  // 2026-07-13: 鼓励/成长/知识库/健康 (复用既有表)
  'smart-studio':       'smart-studio.js',  // 2026-07-17: 私聊模块,只在新加坡 profile 1 启用
  'ai-assistant':       'ai-assistant.js',  // 2026-07-18: 横琴湾 AI 助手迁移（原 /root/backend/src/services/ai/assistant.js）,只 profile 1
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
    // === Labor / SmartBiz 模块 (2026-07-12 新增) ===
    'labor-worker': ['routes/labor-worker.js'],  // 4 个 router: worker/jobsite/dispatch/eval 都在同一文件
    'labor-hr': ['routes/labor-hr.js'],
    'labor-appeals': ['routes/labor-appeals.js'],
    'labor-ai': ['routes/labor-ai.js'],  // 2026-07-13: 鼓励/成长/知识库/健康
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
// 铁律 2026-07-21: **only when 波哥 says "同步到 X"** — 绝对禁止任何 cron / agent 私自触发
// AGENTS.md "跨服务器一致性铁律": 波哥没明确说同步 → 绝对不动目标服务器
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
  // profile 路径规则（按 profile.domain 或 profile.name 匹配）:
  //   domain=hatch.gdqshop.cn  → /var/www/hatch/                  (HK 横琴港澳科技孵化器)
  //   domain=claw.gdqshop.cn   → /var/www/claw.gdqshop.cn/        (北京彩美特)
  //   env=production / name=北京 → /var/www/claw.gdqshop.cn/      (兼容老规则)
  //   其它                       → /var/www/caimeite/              (默认 SGP/Bangkok 等)
  let targetNginx = '/var/www/caimeite/';
  if (profile.domain === 'hatch.gdqshop.cn' || profile.name === '横琴港澳科技孵化器') {
    targetNginx = '/var/www/hatch/';
  } else if (profile.domain === 'claw.gdqshop.cn' || profile.env === 'production' || profile.name === '北京') {
    targetNginx = '/var/www/claw.gdqshop.cn/';
  }
  const useSudo = profile.env === 'production' || profile.name === '北京' || profile.name === '横琴港澳科技孵化器';
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

// 端点类型字典（必须在 /:id 路由之前）
router.get('/endpoint-types', async (req, res) => {
  try {
    const [rows] = await pool.query(
      'SELECT id, type_key, label_zh, label_en, icon, description, requires_primary, sort_order FROM endpoint_types ORDER BY sort_order'
    )
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
    // 完整 38 字段白名单(2026-07-26 补强:写入 schema 全字段,之前 18 个丢失: mysql_*, http_port, backend_port, web_server, db_*, redis_*, deployment_*, last_*, auto_sync, os_*, cpu_*, ram_*, disk_*, user_*, order_*, data_isolation, notes, frontend_type, dist_path, target_nginx, target_domain, is_source, site_logo, wx_pub_*, pem_content)
    const b = req.body || {}
    const fields = [
      'name','ip','ssh_port','ssh_user','ssh_key_path','ssh_auth_type','ssh_password',
      'description','env','build_date','manager','domain',
      // 站点信息
      'site_name_zh','site_name_en','site_logo','language','currency','industry',
      'website','wechat_appid','wx_pub_account','wx_pub_email','wx_pub_secret',
      // 部署路径 / 资源
      'http_port','backend_port','web_server',
      'frontend_type','dist_path','target_nginx','target_domain','is_source',
      'pem_content','remark',
      // DB / 缓存
      'mysql_host','mysql_port','mysql_db','mysql_user','mysql_password',
      'db_engine','db_version','redis_host','redis_port','ssh_tunnel_use',
      // 部署状态
      'deployment_mode','last_deploy_at','last_health_at','last_sync_from','auto_sync',
      // 资源
      'os_version','cpu_cores','ram_total_mb','disk_total_gb',
      // 业务
      'user_count','order_count','data_isolation','notes'
    ]
    // 只接受白名单字段
    const cols = fields.filter(f => b[f] !== undefined)
    const vals = cols.map(f => {
      let v = b[f]
      if (f === 'build_date' && v) v = new Date(v).toISOString().slice(0, 10)
      if (f === 'language' && Array.isArray(v)) v = JSON.stringify(v)
      return v
    })
    const placeholders = cols.map(() => '?').join(',')
    const [result] = await pool.query(
      `INSERT INTO ${TABLE} (${cols.join(',')}) VALUES (${placeholders})`,
      vals
    )
    const profileId = result.insertId
    const modules = b.modules
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
    const b = req.body || {}
    // 同 POST 的白名单
    const fields = [
      'name','ip','ssh_port','ssh_user','ssh_key_path','ssh_auth_type','ssh_password',
      'description','env','build_date','manager','domain',
      'site_name_zh','site_name_en','site_logo','language','currency','industry',
      'website','wechat_appid','wx_pub_account','wx_pub_email','wx_pub_secret',
      'http_port','backend_port','web_server',
      'frontend_type','dist_path','target_nginx','target_domain','is_source',
      'pem_content','remark',
      'mysql_host','mysql_port','mysql_db','mysql_user','mysql_password',
      'db_engine','db_version','redis_host','redis_port','ssh_tunnel_use',
      'deployment_mode','last_deploy_at','last_health_at','last_sync_from','auto_sync',
      'os_version','cpu_cores','ram_total_mb','disk_total_gb',
      'user_count','order_count','data_isolation','notes'
    ]
    const cols = fields.filter(f => b[f] !== undefined)
    if (cols.length === 0) {
      // 没字段要更新,只处理 modules
      await handleModulesUpdate(req.params.id, b.modules)
      return res.json({ code: 0, data: { id: req.params.id } })
    }
    const vals = cols.map(f => {
      let v = b[f]
      if (f === 'build_date' && v) v = new Date(v).toISOString().slice(0, 10)
      if (f === 'language' && Array.isArray(v)) v = JSON.stringify(v)
      return v
    })
    const setClause = cols.map(f => `${f}=?`).join(',')
    await pool.query(
      `UPDATE ${TABLE} SET ${setClause} WHERE id=?`,
      [...vals, req.params.id]
    )
    await handleModulesUpdate(req.params.id, b.modules)
    res.json({ code: 0, data: { id: req.params.id } })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

// 抽出:同步 modules 子表
async function handleModulesUpdate(profileId, modules) {
  if (!Array.isArray(modules)) return
  await pool.query('DELETE FROM server_modules WHERE server_profile_id=?', [profileId])
  if (modules.length) {
    const values = modules.map(m => [parseInt(profileId), m])
    await pool.query('INSERT INTO server_modules (server_profile_id, module_key) VALUES ?', [values])
  }
}

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

// ═══════════════════════════════════════════════════════════════════════════════
//  连接地址配置（每台服务器可填多个不同类型地址：H5/小程序前端/后端/支付/CDN 等）
// ═══════════════════════════════════════════════════════════════════════════════

// GET /api/server-profiles/:id/endpoints — 列出某台服务器的所有连接地址
router.get('/:id/endpoints', async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, server_profile_id, endpoint_type, label, url, is_primary, env, sort_order, description, extra, created_at, updated_at
       FROM server_endpoints WHERE server_profile_id = ? ORDER BY sort_order, id`,
      [req.params.id]
    )
    const parsed = rows.map(r => ({
      ...r,
      is_primary: !!r.is_primary,
      extra: r.extra ? (typeof r.extra === 'string' ? JSON.parse(r.extra) : r.extra) : null
    }))
    res.json({ code: 0, data: parsed })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

// POST /api/server-profiles/:id/endpoints — 新增一个连接地址
router.post('/:id/endpoints', async (req, res) => {
  try {
    const { endpoint_type, label, url, is_primary = 0, env = 'production', sort_order = 99, description = null, extra = null } = req.body
    if (!endpoint_type || !label || !url) {
      return res.status(400).json({ code: 400, message: 'endpoint_type / label / url 不能为空' })
    }
    // 如果标记为主地址，先把同类型已有主地址降级
    if (Number(is_primary) === 1) {
      await pool.query(
        'UPDATE server_endpoints SET is_primary = 0 WHERE server_profile_id = ? AND endpoint_type = ?',
        [req.params.id, endpoint_type]
      )
    }
    const extraJson = extra ? JSON.stringify(extra) : null
    const [r] = await pool.query(
      `INSERT INTO server_endpoints (server_profile_id, endpoint_type, label, url, is_primary, env, sort_order, description, extra)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [req.params.id, endpoint_type, label, url, Number(is_primary) ? 1 : 0, env, sort_order, description, extraJson]
    )
    res.json({ code: 0, data: { id: r.insertId } })
  } catch (err) {
    if (err.code === 'ER_DUP_ENTRY') return res.status(400).json({ code: 400, message: '同类型已有主地址，请先取消原有主地址或设为非主' })
    res.status(500).json({ code: 500, message: err.message })
  }
})

// PUT /api/server-profiles/:id/endpoints/:endpointId — 更新一个连接地址
router.put('/:id/endpoints/:endpointId', async (req, res) => {
  try {
    const { endpoint_type, label, url, is_primary = 0, env = 'production', sort_order = 99, description = null, extra = null } = req.body
    if (!endpoint_type || !label || !url) {
      return res.status(400).json({ code: 400, message: 'endpoint_type / label / url 不能为空' })
    }
    if (Number(is_primary) === 1) {
      await pool.query(
        'UPDATE server_endpoints SET is_primary = 0 WHERE server_profile_id = ? AND endpoint_type = ? AND id <> ?',
        [req.params.id, endpoint_type, req.params.endpointId]
      )
    }
    const extraJson = extra ? JSON.stringify(extra) : null
    await pool.query(
      `UPDATE server_endpoints SET endpoint_type=?, label=?, url=?, is_primary=?, env=?, sort_order=?, description=?, extra=?
       WHERE id = ? AND server_profile_id = ?`,
      [endpoint_type, label, url, Number(is_primary) ? 1 : 0, env, sort_order, description, extraJson, req.params.endpointId, req.params.id]
    )
    res.json({ code: 0, data: { id: Number(req.params.endpointId) } })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

// DELETE /api/server-profiles/:id/endpoints/:endpointId — 删除一个连接地址
router.delete('/:id/endpoints/:endpointId', async (req, res) => {
  try {
    await pool.query(
      'DELETE FROM server_endpoints WHERE id = ? AND server_profile_id = ?',
      [req.params.endpointId, req.params.id]
    )
    res.json({ code: 0, data: {} })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

// POST /api/server-profiles/:id/endpoints/reset-primary — 一键把某类型主地址重置
router.post('/:id/endpoints/reset-primary', async (req, res) => {
  try {
    const { endpoint_type, endpoint_id } = req.body
    if (!endpoint_type || !endpoint_id) return res.status(400).json({ code: 400, message: '缺少 endpoint_type 或 endpoint_id' })
    await pool.query('UPDATE server_endpoints SET is_primary = 0 WHERE server_profile_id = ? AND endpoint_type = ?', [req.params.id, endpoint_type])
    await pool.query('UPDATE server_endpoints SET is_primary = 1 WHERE id = ? AND server_profile_id = ?', [endpoint_id, req.params.id])
    res.json({ code: 0, data: {} })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

// ============================================================
// 健康检查 (2026-07-26 补强)
// 不依赖外部 SSH,只用本地 net socket + child_process nc 探测
// 每个 profile 的检测项从 server_profiles 配置读取
// ============================================================

// 单项 TCP 端口探测 (本地 node, 0 依赖)
function tcpProbe(host, port, timeoutMs = 4000) {
  return new Promise((resolve) => {
    const start = Date.now()
    const socket = new net.Socket()
    let done = false
    const finish = (status, message) => {
      if (done) return
      done = true
      try { socket.destroy() } catch (_) {}
      resolve({ status, message, latency_ms: Date.now() - start })
    }
    socket.setTimeout(timeoutMs)
    socket.on('connect', () => finish('ok', `connected ${host}:${port}`))
    socket.on('timeout', () => finish('fail', `timeout after ${timeoutMs}ms`))
    socket.on('error', (e) => finish('fail', e.code || e.message))
    try {
      socket.connect(port, host)
    } catch (e) {
      finish('fail', e.message)
    }
  })
}

// SSH/TCP 探针 (用 nc 命令, 3 秒超时)
function ncProbe(host, port, timeoutMs = 4000) {
  return new Promise((resolve) => {
    const start = Date.now()
    const child = spawn('nc', ['-zv', '-w', '3', host, String(port)], { timeout: timeoutMs })
    let stderr = ''
    child.stderr.on('data', d => { stderr += d.toString() })
    child.on('error', (e) => resolve({ status: 'fail', message: 'nc not found or spawn error: ' + e.message, latency_ms: Date.now() - start }))
    child.on('close', (code) => {
      const latency = Date.now() - start
      // nc -zv 退出 0 = 通; 非 0 = 不通 (但有些 nc 退出 0 即使 fail)
      if (code === 0 || stderr.includes('open') || stderr.includes('succeeded')) {
        resolve({ status: 'ok', message: stderr.trim().slice(-100) || 'port open', latency_ms: latency })
      } else {
        resolve({ status: 'fail', message: stderr.trim().slice(-200) || `nc exit ${code}`, latency_ms: latency })
      }
    })
  })
}

// 单次健康检查: 返回 6 项结果 + 写 server_health_log
router.get('/:id/health', requireRole('admin'), async (req, res) => {
  try {
    const id = req.params.id
    const [rows] = await pool.query('SELECT * FROM server_profiles WHERE id = ?', [id])
    if (!rows[0]) return res.status(404).json({ code: 404, message: 'Profile not found' })
    const p = rows[0]
    const host = p.ip
    const checks = []

    // 1) SSH 端口
    if (p.ssh_port) {
      const r = await ncProbe(host, p.ssh_port)
      checks.push({ check_type: 'ssh', ...r })
    } else {
      checks.push({ check_type: 'ssh', status: 'skip', message: 'no ssh_port configured', latency_ms: 0 })
    }

    // 2) HTTP 端口 (nginx/caddy 外部端口)
    if (p.http_port && p.http_port !== 3000 /* 默认值 */) {
      const r = await ncProbe(host, p.http_port)
      checks.push({ check_type: 'http', ...r })
    } else {
      checks.push({ check_type: 'http', status: 'skip', message: 'no http_port configured', latency_ms: 0 })
    }

    // 3) 后端 API 端口
    if (p.backend_port) {
      const r = await ncProbe(host, p.backend_port)
      checks.push({ check_type: 'api', ...r })
    } else {
      checks.push({ check_type: 'api', status: 'skip', message: 'no backend_port configured', latency_ms: 0 })
    }

    // 4) MySQL 端口
    // 独立部署的 profile (HK 等), mysql 仅 bind 127.0.0.1,远程 nc 探测必失败
    // 但这是安全默认,不算 fail — 标记 'skip' 让前端知道"独立部署,DB 不暴露"
    const dbIsLocalOnly = !p.mysql_host || p.mysql_host === 'localhost' || p.mysql_host === '127.0.0.1'
    const dbHost = dbIsLocalOnly ? host : p.mysql_host
    const dbPort = p.mysql_port || 3306
    if (p.db_engine && p.db_engine !== 'none') {
      const r = await ncProbe(dbHost, dbPort)
      // 独立部署:DB 远程探测失败但不是真问题,标记为 skip
      if (r.status === 'fail' && dbIsLocalOnly && p.deployment_mode === 'independent') {
        checks.push({ check_type: 'db', status: 'skip', message: '独立部署, DB 仅本地 bind (安全默认,非故障)', latency_ms: r.latency_ms })
      } else {
        checks.push({ check_type: 'db', ...r })
      }
    } else {
      checks.push({ check_type: 'db', status: 'skip', message: 'no db configured', latency_ms: 0 })
    }

    // 5) Redis 端口 (同上,独立部署 redis 本机不暴露)
    if (p.redis_host) {
      const redisIsLocal = p.redis_host === 'localhost' || p.redis_host === '127.0.0.1'
      const redisHost = redisIsLocal ? host : p.redis_host
      const redisPort = p.redis_port || 6379
      const r = await ncProbe(redisHost, redisPort)
      if (r.status === 'fail' && redisIsLocal && p.deployment_mode === 'independent') {
        checks.push({ check_type: 'redis', status: 'skip', message: '独立部署, Redis 仅本地 bind', latency_ms: r.latency_ms })
      } else {
        checks.push({ check_type: 'redis', ...r })
      }
    } else {
      checks.push({ check_type: 'redis', status: 'skip', message: 'no redis configured', latency_ms: 0 })
    }

    // 6) 本机 API health 端点 (只测 SGP 本机: id=1, 因为只有 SGP 在 127.0.0.1 上有 Node)
    if (id === '1' && p.backend_port) {
      try {
        const start = Date.now()
        const ctrl = new AbortController()
        const timer = setTimeout(() => ctrl.abort(), 4000)
        const r = await fetch(`http://127.0.0.1:${p.backend_port}/api/health`, { signal: ctrl.signal }).catch(() => null)
        clearTimeout(timer)
        if (r && (r.status === 200 || r.status === 404)) {
          checks.push({ check_type: 'tcp', status: 'ok', message: `/api/health HTTP ${r.status}`, latency_ms: Date.now() - start })
        } else {
          checks.push({ check_type: 'tcp', status: 'fail', message: 'fetch failed', latency_ms: Date.now() - start })
        }
      } catch (e) {
        checks.push({ check_type: 'tcp', status: 'fail', message: e.message, latency_ms: 0 })
      }
    } else {
      checks.push({ check_type: 'tcp', status: 'skip', message: 'remote server - use nc only', latency_ms: 0 })
    }

    // 写日志
    try {
      const values = checks.map(c => [id, c.check_type, c.status, c.latency_ms || null, (c.message || '').slice(0, 500)])
      await pool.query(
        'INSERT INTO server_health_log (server_profile_id, check_type, status, latency_ms, message) VALUES ?',
        [values]
      )
      // 更新 profile.last_health_at (任意 ok 即视为健康)
      const anyOk = checks.some(c => c.status === 'ok')
      if (anyOk) {
        await pool.query('UPDATE server_profiles SET last_health_at = NOW() WHERE id = ?', [id])
      }
    } catch (logErr) {
      console.error('[health log]', logErr.message)
    }

    res.json({ code: 0, data: { profile_id: id, checks, checked_at: new Date().toISOString() } })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

// 历史健康日志
router.get('/:id/health-log', requireRole('admin'), async (req, res) => {
  try {
    const id = req.params.id
    const limit = Math.min(parseInt(req.query.limit || '20'), 100)
    const [rows] = await pool.query(
      `SELECT id, check_type, status, latency_ms, message, checked_at
       FROM server_health_log WHERE server_profile_id = ? ORDER BY id DESC LIMIT ?`,
      [id, limit]
    )
    res.json({ code: 0, data: rows })
  } catch (err) { res.status(500).json({ code: 500, message: err.message }) }
})

export default router
