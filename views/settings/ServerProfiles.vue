<script setup>
import { ref, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import PageHeader from '../../components/PageHeader.vue'
import { serverProfileApi, menuApi, serverEndpointApi } from '../../services/api.js'
import { useI18n } from 'vue-i18n'
import { menuModules as staticMenuModules } from '../../constants/menuModules.js'

const { t } = useI18n()

// ─── State ───────────────────────────────────────────────────────────────────
const loading = ref(false)
const profiles = ref([])
const availableModules = ref([])
const industryTemplates = ref([])
const dialogVisible = ref(false)
const dialogTitle = ref('')
const isEditing = ref(false)
const editingId = ref(null)
const moduleSearch = ref('')

// 语言选项
const languageOptions = [
  { value: 'zh', label: '中文 (zh)' },
  { value: 'en', label: 'English (en)' },
  { value: 'ms', label: 'Bahasa Melayu (ms)' },
]

// 货币选项
const currencyOptions = [
  { value: 'CNY', label: '人民币 (CNY)' },
  { value: 'MYR', label: '令吉 (MYR)' },
  { value: 'SGD', label: '新币 (SGD)' },
  { value: 'USD', label: '美元 (USD)' },
  { value: 'HKD', label: '港币 (HKD)' },
  { value: 'EUR', label: '欧元 (EUR)' },
]

// 行业选项
const industryOptions = [
  { value: 'ecommerce', label: '电商' },
  { value: 'sales', label: '销售CRM' },
  { value: 'education', label: '教育' },
  { value: 'company', label: '企业管理' },
  { value: 'realestate', label: '房地产' },
  { value: 'restaurant', label: '餐饮' },
  { value: 'hotel', label: '酒店' },
]

// 行业分类中文名
const industryNameMap = {
  general: '通用基础',
  business: '业务模块',
  restaurant: '餐饮专用',
  hotel: '酒店专用',
  mall: '商城专用',
  company: '企业管理',
  education: 'AI课堂',
}

// 表单
const form = ref({
  name: '', ip: '', ssh_port: 22, ssh_user: 'ubuntu',
  ssh_key_path: '/root/clawgdqshop.pem', description: '', env: 'production',
  build_date: '', manager: '', domain: '', pem_content: '', website: '',
  site_logo: '',
  modules: [], site_name_zh: '', site_name_en: '', language: [], currency: '', industry: '',
  mysql_host: '', mysql_db: '', mysql_user: '', mysql_password: '', mysql_port: 3306,
  endpoints: [], // 连接地址（H5/后台/API/小程序前端 等）
})

// ─── Endpoints（连接地址）inline 编辑 ────────────────────────────────────────
const endpointTypes = [
  { value: 'admin_frontend', label: '管理后台前端', placeholder: 'https://admin.example.com' },
  { value: 'api_backend', label: 'API 后端', placeholder: 'https://api.example.com' },
  { value: 'h5_frontend', label: 'H5 客户端', placeholder: 'https://h5.example.com' },
  { value: 'minip_frontend', label: '小程序前端', placeholder: 'https://minip.example.com' },
  { value: 'shop_h5', label: '商城 H5', placeholder: 'https://shop.example.com' },
  { value: 'oa_h5', label: 'OA H5', placeholder: 'https://oa.example.com' },
  { value: 'wechat_minip', label: '微信小程序', placeholder: 'https://mp-weixin.example.com' },
  { value: 'custom', label: '自定义', placeholder: 'https://...' },
]

async function loadProfileEndpoints(profileId) {
  if (!profileId) return
  try {
    const res = await serverEndpointApi.listByProfile(profileId)
    const list = res.data?.data || res.data || []
    form.value.endpoints = list.map(r => ({
      id: r.id,
      endpoint_type: r.endpoint_type || '',
      label: r.label || '',
      url: r.url || '',
      env: r.env || 'production',
      is_primary: !!r.is_primary,
      is_active: r.is_active === 0 ? false : true,
      sort_order: r.sort_order || 100,
      description: r.description || ''
    }))
  } catch (e) {
    ElMessage.error('加载连接地址失败: ' + e.message)
    form.value.endpoints = []
  }
}

function addEndpoint() {
  if (!form.value.endpoints) form.value.endpoints = []
  form.value.endpoints.push({
    id: null,
    endpoint_type: 'admin_frontend',
    label: '',
    url: '',
    env: 'production',
    is_primary: false,
    is_active: true,
    sort_order: 100,
    description: ''
  })
}

function removeEndpoint(idx) {
  form.value.endpoints.splice(idx, 1)
}

function endpointTypeLabel(type) {
  const opt = endpointTypes.find(t => t.value === type)
  return opt ? opt.label : type
}

function endpointTypePlaceholder(type) {
  const opt = endpointTypes.find(t => t.value === type)
  return opt ? opt.placeholder : 'https://...'
}

// 同步状态
const syncing = ref(false)
const siteLogoTesting = ref(false)
const syncResult = ref(null)
const syncDialogVisible = ref(false)
const syncLoading = ref(false)

// 进度条状态
const syncProgress = ref({ percent: 0, label: '', step: 0, logs: [] })

// 模块管理
const profileModules = ref([])
const profileModulesLoading = ref(false)
const moduleOpLoading = ref(false)
const newModuleKey = ref('')
const bulkModuleKeys = ref([])

// ─── Load ───────────────────────────────────────────────────────────────────
async function loadProfiles() {
  loading.value = true
  try {
    const res = await serverProfileApi.list()
    if (res.code === 0) profiles.value = res.data
  } catch (e) {
    ElMessage.error(t('common.loadFailed') + ': ' + e.message)
  } finally {
    loading.value = false
  }
}

async function loadAvailableModules() {
  try {
    const res = await serverProfileApi.getAvailableModules()
    if (res.code === 0) availableModules.value = res.data
  } catch (e) { /* ignore */ }
}

async function loadIndustryTemplates() {
  try {
    const res = await serverProfileApi.getIndustryTemplates()
    if (res.code === 0) industryTemplates.value = res.data
  } catch (e) { /* ignore */ }
}

// ─── Dialog ─────────────────────────────────────────────────────────────────
function openAdd() {
  dialogTitle.value = t('serverProfiles.addServer')
  isEditing.value = false
  editingId.value = null
  form.value = {
    name: '', ip: '', ssh_port: 22, ssh_user: 'ubuntu',
    ssh_key_path: '/root/clawgdqshop.pem', description: '', env: 'production',
    build_date: '', manager: '', domain: '', pem_content: '', website: '',
    site_logo: '',
    modules: [], site_name_zh: '', site_name_en: '', language: [], currency: '', industry: '',
    mysql_host: '', mysql_db: '', mysql_user: '', mysql_password: '', mysql_port: 3306,
    endpoints: [],
  }
  dialogVisible.value = true
}

async function openEdit(row) {
  dialogTitle.value = t('serverProfiles.editServer')
  isEditing.value = true
  editingId.value = row.id
  // Parse language
  let langArray = row.language
  if (typeof langArray === 'string' && langArray.startsWith('[')) {
    try { langArray = JSON.parse(langArray) } catch { langArray = [] }
  } else if (typeof langArray === 'string') {
    langArray = langArray.split(',').map(l => l.trim()).filter(Boolean)
  }
  if (!Array.isArray(langArray)) langArray = []
  form.value = {
    name: row.name, ip: row.ip, ssh_port: row.ssh_port || 22,
    ssh_user: row.ssh_user || 'ubuntu', ssh_key_path: row.ssh_key_path || '/root/clawgdqshop.pem',
    description: row.description || '', env: row.env || 'production',
    build_date: row.build_date || '', manager: row.manager || '',
    domain: row.domain || '', pem_content: row.pem_content || '',
    website: row.website || '', site_logo: row.site_logo || '',
    modules: [...(row.modules || [])],
    site_name_zh: row.site_name_zh || '', site_name_en: row.site_name_en || '',
    language: langArray, currency: row.currency || '', industry: row.industry || '',
    mysql_host: row.mysql_host || '', mysql_db: row.mysql_db || '', mysql_user: row.mysql_user || '',
    mysql_password: row.mysql_password || '', mysql_port: row.mysql_port || 3306,
    endpoints: [], // 下面 loadProfileEndpoints 加载
  }
  newModuleKey.value = ''
  bulkModuleKeys.value = []
  dialogVisible.value = true
  await Promise.all([loadProfileModules(row.id), loadAvailableModules(), loadOrphans(), loadProfileEndpoints(row.id)])
}

async function submitForm() {
  if (!form.value.name || !form.value.ip) {
    ElMessage.warning(t('serverProfiles.nameIpRequired'))
    return
  }
  try {
    // 1. 先保存 profile 主体（不含 endpoints）
    const payload = { ...form.value }
    const endpointsPayload = payload.endpoints || []
    delete payload.endpoints
    let savedId = editingId.value
    if (isEditing.value) {
      await serverProfileApi.update(editingId.value, payload)
    } else {
      const res = await serverProfileApi.create(payload)
      savedId = res.data?.data?.id || editingId.value
    }
    // 2. diff endpoints：新增 / 修改 / 删除
    if (savedId) {
      await syncProfileEndpoints(savedId, endpointsPayload)
    }
    dialogVisible.value = false
    await loadProfiles()
    ElMessage.success(t('common.saveSuccess'))
  } catch (e) {
    ElMessage.error(t('common.saveFailed') + ': ' + e.message)
  }
}

async function syncProfileEndpoints(profileId, newList) {
  try {
    // 取服务端当前 endpoints
    const res = await serverEndpointApi.listByProfile(profileId)
    const oldList = res.data?.data || res.data || []
    const oldById = new Map(oldList.map(e => [e.id, e]))
    const newById = new Map()
    // 找出要新增 / 更新
    for (const ep of newList) {
      const payload = {
        server_profile_id: profileId,
        endpoint_type: ep.endpoint_type,
        label: ep.label || ep.endpoint_type,
        url: ep.url,
        env: ep.env || 'production',
        is_primary: ep.is_primary ? 1 : 0,
        is_active: ep.is_active ? 1 : 0,
        sort_order: ep.sort_order || 100,
        description: ep.description || null
      }
      if (ep.id && oldById.has(ep.id)) {
        // 更新
        await serverEndpointApi.update(ep.id, payload)
        newById.set(ep.id, true)
      } else if (ep.url) {
        // 新增（必须有 url）
        const created = await serverEndpointApi.create(payload)
        const createdId = created.data?.data?.id || created.data?.id
        if (createdId) newById.set(createdId, true)
      }
    }
    // 找出要删除的（旧的 id 不在新列表里）
    for (const oldEp of oldList) {
      if (!newList.some(n => n.id === oldEp.id)) {
        await serverEndpointApi.remove(oldEp.id)
      }
    }
  } catch (e) {
    console.error('syncProfileEndpoints error:', e)
    throw e
  }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(
      t('serverProfiles.confirmDelete').replace('{name}', row.name),
      t('common.confirm'),
      { type: 'warning' }
    )
    await serverProfileApi.remove(row.id)
    await loadProfiles()
    ElMessage.success(t('common.deleteSuccess'))
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message)
  }
}

// ─── Industry Templates ──────────────────────────────────────────────────────
function applyTemplate(template) {
  // Parse modules JSON string/array
  let mods = template.modules
  if (typeof mods === 'string') {
    try { mods = JSON.parse(mods) } catch { mods = [] }
  }
  // Merge with existing selections (avoid duplicates)
  const existing = new Set(form.value.modules)
  ;(mods || []).forEach(m => existing.add(m))
  form.value.modules = Array.from(existing)
  form.value.industry = template.key
  ElMessage.success(`已应用「${template.label_zh}」模板，` + form.value.modules.length + ' 个模块已勾选')
}

function clearModules() {
  form.value.modules = []
  form.value.industry = ''
}

function toggleModule(key) {
  const idx = form.value.modules.indexOf(key)
  if (idx >= 0) {
    form.value.modules.splice(idx, 1)
  } else {
    form.value.modules.push(key)
  }
}

// ─── Module display helpers ──────────────────────────────────────────────────
const moduleCategoryMap = computed(() => {
  const cats = {}
  availableModules.value.forEach(m => {
    const cat = m.category || 'main'
    if (!cats[cat]) cats[cat] = []
    cats[cat].push(m)
  })
  return cats
})

const filteredModules = computed(() => {
  if (!moduleSearch.value.trim()) return null
  const q = moduleSearch.value.toLowerCase()
  return availableModules.value.filter(m =>
    m.label_zh?.toLowerCase().includes(q) ||
    m.label_en?.toLowerCase().includes(q) ||
    m.module_key.toLowerCase().includes(q)
  )
})

const categoryOrder = ['general', 'business', 'restaurant', 'hotel', 'mall', 'company', 'education']

function getCategoryName(cat) {
  return industryNameMap[cat] || cat
}

function isModuleSelected(key) {
  return form.value.modules.includes(key)
}

function getModuleCount(modules) {
  if (!Array.isArray(modules)) return 0
  // 只统计在 availableModules（menu_modules）里有的，过滤孤儿
  if (!availableModules.value || !availableModules.value.length) return modules.length
  const validKeys = new Set(availableModules.value.map(m => m.module_key))
  return modules.filter(k => validKeys.has(k)).length
}
function getOrphanModuleCount(modules) {
  if (!Array.isArray(modules) || !availableModules.value || !availableModules.value.length) return 0
  const validKeys = new Set(availableModules.value.map(m => m.module_key))
  return modules.filter(k => !validKeys.has(k)).length
}

// ─── Sync ────────────────────────────────────────────────────────────────────
async function testSiteLogo() {
  const url = form.value.site_logo
  if (!url) { ElMessage.warning('请先输入Logo URL'); return }
  siteLogoTesting.value = true
  try {
    const img = new Image()
    img.onload = () => ElMessage.success('Logo可访问')
    img.onerror = () => ElMessage.error('Logo无法加载，请检查URL')
    img.src = url
  } finally {
    setTimeout(() => { siteLogoTesting.value = false }, 3000)
  }
}

async function handleSync(row) {
  try {
    await ElMessageBox.confirm(
      t('serverProfiles.confirmSync').replace('{name}', row.name),
      t('common.confirm'),
      { type: 'warning' }
    )
  } catch (e) { return }

  syncing.value = true
  syncResult.value = null
  syncDialogVisible.value = true
  try {
    const res = await serverProfileApi.sync(row.id)
    if (res.code === 0) {
      syncResult.value = res.data
      ElMessage.success(t('serverProfiles.syncPreviewReady'))
    } else {
      ElMessage.error(res.message || t('serverProfiles.syncFailed'))
      syncDialogVisible.value = false
    }
  } catch (e) {
    ElMessage.error(e.message)
    syncDialogVisible.value = false
  } finally {
    syncing.value = false
  }
}

async function confirmSync() {
  if (!syncResult.value) return
  syncLoading.value = true
  syncProgress.value = { percent: 0, label: '正在连接服务器...', step: 0, logs: [], status: 'running' }

  const token = localStorage.getItem('caimeite_token') || ''
  const profileId = syncResult.value.profile.id

  try {
    // Step 1: 启动同步，立即获取 taskId
    const startRes = await fetch('/api/server-profiles/' + profileId + '/exec-sync', {
      method: 'POST',
      headers: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json',
      }
    })
    const startData = await startRes.json()
    if (!startData.data?.taskId) throw new Error('启动同步失败: ' + JSON.stringify(startData))
    const taskId = startData.data.taskId
    syncProgress.value.label = '同步已启动，等待服务器响应...'

    // Step 2: 每2秒轮询进度
    const poll = async () => {
      while (true) {
        await new Promise(r => setTimeout(r, 2000))
        const progRes = await fetch('/api/server-profiles/' + profileId + '/sync-progress/' + taskId, {
          headers: { 'Authorization': 'Bearer ' + token }
        })
        const progData = await progRes.json()
        const p = progData.data
        if (!p || p.status === 'not_found') {
          //任务还没创建，等一下
          continue
        }
        syncProgress.value.percent = p.percent
        syncProgress.value.label = p.label
        syncProgress.value.step = p.step
        if (p.logs) syncProgress.value.logs = p.logs

        if (p.status === 'done') {
          syncLoading.value = false
          syncDialogVisible.value = false
          ElMessage.success('同步完成')
          return
        } else if (p.status === 'error') {
          ElMessage.error('同步失败: ' + p.label)
          syncLoading.value = false
          return
        }
        // running，继续轮询
      }
    }
    poll()
  } catch (err) {
    ElMessage.error('同步连接失败: ' + err.message)
    syncLoading.value = false
  }
}

function getEnvLabel(env) {
  const map = { production: t('serverProfiles.envProduction'), staging: t('serverProfiles.envStaging'), development: t('serverProfiles.envDev') }
  return map[env] || env
}

function getEnvTagType(env) {
  const map = { production: 'danger', staging: 'warning', development: 'success' }
  return map[env] || 'info'
}

function displayLanguages(lang) {
  if (!lang) return '-'
  let langs = lang
  if (typeof langs === 'string') {
    if (langs.startsWith('[')) {
      try { langs = JSON.parse(langs) } catch { langs = [] }
    } else {
      langs = langs.split(',').map(l => l.trim()).filter(Boolean)
    }
  }
  if (!Array.isArray(langs)) return '-'
  const displayMap = { zh: '中文', en: 'English', ms: 'Bahasa Melayu' }
  return langs.map(l => displayMap[l] || l).join(', ')
}

function getIndustryLabel(ind) {
  return industryNameMap[ind] || ind || '-'
}

// ─── Module Management ───────────────────────────────────────────────────────
async function loadProfileModules(profileId) {
  if (!profileId) return
  profileModulesLoading.value = true
  try {
    const res = await serverProfileApi.getModules(profileId)
    if (res.code === 0) profileModules.value = res.data
  } catch (e) {
    ElMessage.error('加载模块失败: ' + e.message)
  } finally {
    profileModulesLoading.value = false
  }
}

async function handleAddModule() {
  if (!newModuleKey.value) return
  if (!editingId.value) {
    ElMessage.warning('请先保存服务器配置后再添加模块')
    return
  }
  moduleOpLoading.value = true
  try {
    await serverProfileApi.addModule(editingId.value, newModuleKey.value)
    await loadProfileModules(editingId.value)
    newModuleKey.value = ''
    ElMessage.success('模块添加成功')
  } catch (e) {
    ElMessage.error('添加模块失败: ' + e.message)
  } finally {
    moduleOpLoading.value = false
  }
}

async function handleRemoveModule(moduleKey) {
  try {
    await ElMessageBox.confirm('确认移除模块「' + moduleKey + '」？', t('common.confirm'), { type: 'warning' })
  } catch (e) { return }
  if (!editingId.value) {
    ElMessage.warning('请先保存服务器配置后再操作模块')
    return
  }
  moduleOpLoading.value = true
  try {
    await serverProfileApi.removeModule(editingId.value, moduleKey)
    await loadProfileModules(editingId.value)
    ElMessage.success('模块已移除')
  } catch (e) {
    ElMessage.error('移除模块失败: ' + e.message)
  } finally {
    moduleOpLoading.value = false
  }
}

async function handleBulkSyncModules() {
  if (!bulkModuleKeys.value.length) return
  if (!editingId.value) {
    ElMessage.warning('请先保存服务器配置后再批量同步模块')
    return
  }
  moduleOpLoading.value = true
  try {
    await serverProfileApi.syncModules(editingId.value, bulkModuleKeys.value)
    await loadProfileModules(editingId.value)
    bulkModuleKeys.value = []
    ElMessage.success('批量同步成功')
  } catch (e) {
    ElMessage.error('批量同步失败: ' + e.message)
  } finally {
    moduleOpLoading.value = false
  }
}

onMounted(() => {
  loadProfiles()
  loadAvailableModules()
  loadIndustryTemplates()
  loadDictStats()
  loadOrphans()
})

// ─── 模块字典管理（模块化铁律核心） ──────────────────────────────────────────
const dictPanelOpen = ref(true)  // 默认展开
const dictLoading = ref(false)
const dictTableData = ref([])  // 字典列表
const orphanListVisible = ref(false)
const orphanByProfile = ref([])  // 孤儿按 profile
const orphanCount = computed(() => orphanByProfile.value.length)
const categoryOptions = ['general', 'business', 'restaurant', 'mall', 'hotel', 'company', 'education', 'legacy']

// 当前 profile 在编辑中的孤儿（orphanByProfile 过滤 + profileModules 二次核对）
// availableModules 字段兼容：menu_modules API 用 'key'，server-profiles/available-modules 用 'module_key'
const currentProfileOrphans = computed(() => {
  if (!editingId.value) return []
  const dictKeys = new Set(availableModules.value.map(m => m.module_key || m.key))
  return profileModules.value
    .filter(m => !dictKeys.has(m.module_key))
    .map(m => ({ module_key: m.module_key, label_zh: m.label_zh }))
})
function isCurrentOrphan(moduleKey) {
  const dictKeys = new Set(availableModules.value.map(m => m.module_key || m.key))
  return !dictKeys.has(moduleKey)
}
async function mergeCurrentOrphans() {
  const keys = currentProfileOrphans.value.map(o => o.module_key)
  if (keys.length === 0) return
  try {
    const res = await menuApi.mergeOrphans(keys)
    if (res.code === 0) {
      ElMessage.success(res.message || `已回填 ${keys.length} 个孤儿`)
      await loadDictStats()
      await loadOrphans()
      await loadAvailableModules()
      // 关键：重算 currentProfileOrphans，让 banner 消失
      await loadProfileModules(editingId.value)
      dialogVisible.value = false
    } else {
      ElMessage.error(res.message || '回填失败')
    }
  } catch (e) {
    ElMessage.error('回填失败: ' + (e.message || JSON.stringify(e)))
  }
}
async function removeCurrentOrphans() {
  const keys = currentProfileOrphans.value.map(o => o.module_key)
  if (keys.length === 0) return
  if (!confirm(`确定从当前 profile 移除 ${keys.length} 个孤儿勾选？\n\n${keys.join(', ')}\n\n（字典不动，只清勾选）`)) return
  // 复用 profileModules 接口：逐个调 handleRemoveModule
  moduleOpLoading.value = true
  let removed = 0
  try {
    for (const k of keys) {
      try {
        await serverProfileApi.removeModule(editingId.value, k)
        removed++
      } catch (e) {
        console.warn(`removeModule(${k}) failed:`, e.message)
      }
    }
    ElMessage.success(`已移除 ${removed}/${keys.length} 个孤儿勾选`)
    await loadProfileModules(editingId.value)
    await loadOrphans()
  } catch (e) {
    ElMessage.error('移除孤儿失败: ' + (e.message || JSON.stringify(e)))
  } finally {
    moduleOpLoading.value = false
  }
}

const dictDialogVisible = ref(false)
const dictDialogMode = ref('add')  // 'add' | 'edit'
const dictForm = ref({
  key: '', label_zh: '', label_en: '', icon: 'extension',
  route: '', category: 'general', sort_order: 99, required: false,
})

const syncStaticDialogVisible = ref(false)

async function loadDictStats() {
  dictLoading.value = true
  try {
    const res = await menuApi.getMenuModules()
    if (res.code === 0) {
      dictTableData.value = res.data
      availableModules.value = res.data  // 同步给表格用
    }
  } catch (e) {
    console.error('loadDictStats:', e)
  } finally {
    dictLoading.value = false
  }
}

async function loadOrphans() {
  try {
    const res = await menuApi.getOrphans()
    if (res.code === 0) {
      orphanByProfile.value = res.data.by_profile
      orphanListVisible.value = res.data.total_orphans > 0
    }
  } catch (e) {
    console.error('loadOrphans:', e)
  }
}

async function mergeAllOrphans() {
  if (!confirm(`确认融合全部 ${orphanCount.value} 个孤儿到字典？`)) return
  try {
    const res = await menuApi.mergeOrphans()
    if (res.code === 0) {
      ElMessage.success(res.message)
      await loadDictStats()
      await loadOrphans()
      await loadAvailableModules()
    } else {
      ElMessage.error(res.message)
    }
  } catch (e) {
    ElMessage.error('融合失败: ' + (e.message || JSON.stringify(e)))
  }
}

async function mergeSingleOrphan(key) {
  if (!confirm(`确认把孤儿 "${key}" 融合到字典？`)) return
  try {
    const res = await menuApi.mergeOrphans([key])
    if (res.code === 0) {
      ElMessage.success(res.message)
      await loadDictStats()
      await loadOrphans()
      await loadAvailableModules()
    } else {
      ElMessage.error(res.message)
    }
  } catch (e) {
    ElMessage.error('融合失败: ' + (e.message || JSON.stringify(e)))
  }
}

function openAddDictDialog() {
  dictDialogMode.value = 'add'
  dictForm.value = {
    key: '', label_zh: '', label_en: '', icon: 'extension',
    route: '', category: 'general', sort_order: 99, required: false,
  }
  dictDialogVisible.value = true
}

function openEditDictDialog(row) {
  dictDialogMode.value = 'edit'
  dictForm.value = { ...row, required: !!row.required }
  dictDialogVisible.value = true
}

async function saveDictItem() {
  const f = dictForm.value
  if (!f.key || !f.label_zh || !f.label_en || !f.route) {
    ElMessage.error('Key / 中文名 / 英文名 / 路由 必填')
    return
  }
  try {
    const res = dictDialogMode.value === 'add'
      ? await menuApi.createModule(f)
      : await menuApi.updateModule(f.key, f)
    if (res.code === 0) {
      ElMessage.success(res.message || '保存成功')
      dictDialogVisible.value = false
      await loadDictStats()
      await loadAvailableModules()
    } else {
      ElMessage.error(res.message || '保存失败')
    }
  } catch (e) {
    ElMessage.error('保存失败: ' + (e.message || JSON.stringify(e)))
  }
}

async function deleteDictItem(row) {
  const cnt = orphanByProfile.value.filter(o => o.module_key === row.key).length
  const msg = cnt > 0
    ? `字典项 "${row.key}" 被 ${cnt} 个 profile 引用，删除后这些 profile 的勾选会变成孤儿。确定删除？`
    : `确认删除字典项 "${row.key}"？`
  if (!confirm(msg)) return
  try {
    const res = await menuApi.deleteModule(row.key)
    if (res.code === 0) {
      ElMessage.success(res.message || '删除成功')
      if (res.data?.was_referenced) {
        ElMessage.warning(`"${row.key}" 之前被 ${res.data.ref_count} 个 profile 引用，已变孤儿，可一键融合`)
      }
      await loadDictStats()
      await loadOrphans()
      await loadAvailableModules()
    } else {
      ElMessage.error(res.message || '删除失败')
    }
  } catch (e) {
    ElMessage.error('删除失败: ' + (e.message || JSON.stringify(e)))
  }
}

const UPGRADE_CATEGORIES = [
  { value: 'general', label: '通用' },
  { value: 'business', label: '业务' },
  { value: 'restaurant', label: '餐饮' },
  { value: 'mall', label: '商城' },
  { value: 'retail', label: '零售' },
  { value: 'hotel', label: '酒店' },
  { value: 'company', label: '公司' },
  { value: 'education', label: '教育' },
]

async function upgradeCategory(row) {
  const optionsHtml = UPGRADE_CATEGORIES.map(c => `<option value="${c.value}">${c.label} (${c.value})</option>`).join('')
  const inputHtml = `<select id="upgrade-cat-select" class="el-input__inner" style="width:100%;height:32px;line-height:32px;padding:0 12px;border:1px solid #dcdfe6;border-radius:4px;">${optionsHtml}</select>`
  try {
    const { value } = await ElMessageBox({
      title: `升级 "${row.key}" 的分类`,
      message: () => {
        // 该对话框将在 created 时插入 select
        return inputHtml
      },
      showCancelButton: true,
      confirmButtonText: '升级',
      cancelButtonText: '取消',
      dangerouslyUseHTMLString: true,
      beforeClose: (action, instance, done) => {
        if (action === 'confirm') {
          const sel = document.getElementById('upgrade-cat-select')
          const cat = sel?.value
          if (!cat) { ElMessage.warning('请选择分类'); return }
          instance.confirmButtonLoading = true
          menuApi.upgradeCategory(row.key, cat).then(res => {
            instance.confirmButtonLoading = false
            if (res.code === 0) {
              ElMessage.success(res.message || `已升级 ${row.key} → ${cat}`)
              done()
            } else {
              ElMessage.error(res.message || '升级失败')
              done()
            }
          }).catch(e => {
            instance.confirmButtonLoading = false
            ElMessage.error('升级失败: ' + (e.message || JSON.stringify(e)))
            done()
          })
        } else {
          done()
        }
      }
    })
    if (value === 'confirm') {
      await loadDictStats()
      await loadOrphans()
      await loadAvailableModules()
    }
  } catch (e) {
    if (e !== 'cancel' && e !== 'close') console.error('upgradeCategory:', e)
  }
}

function openSyncStaticDialog() {
  syncStaticDialogVisible.value = true
}

async function executeSyncStatic(dryRun) {
  try {
    const res = await menuApi.syncStatic(staticMenuModules, !dryRun)
    if (res.code === 0) {
      const s = res.data.stats
      ElMessage.success(`[${dryRun ? '预览' : '执行'}] inserted=${s.inserted}, updated=${s.updated}, skipped=${s.skipped}`)
      if (dryRun) {
        console.log('同步预览 changes:', res.data.changes)
      } else {
        syncStaticDialogVisible.value = false
        await loadDictStats()
        await loadAvailableModules()
      }
    } else {
      ElMessage.error(res.message || '同步失败')
    }
  } catch (e) {
    ElMessage.error('同步失败: ' + (e.message || JSON.stringify(e)))
  }
}

</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader :title="$t('serverProfiles.title')" :subtitle="$t('serverProfiles.subtitle')" />

    <!-- 模块字典管理 + 孤儿融合（模块化铁律核心） -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-4">
      <div class="p-4 border-b border-gray-100 flex justify-between items-center cursor-pointer" @click="dictPanelOpen = !dictPanelOpen">
        <div class="flex items-center gap-3">
          <span class="text-base font-medium text-gray-700">🧬 模块字典管理</span>
          <el-tag size="small" type="info">字典 {{ availableModules.length }} 条</el-tag>
          <el-tag size="small" :type="orphanCount > 0 ? 'warning' : 'success'">
            孤儿 {{ orphanCount }} 个
          </el-tag>
        </div>
        <div class="flex items-center gap-2">
          <button @click.stop="loadDictStats" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded hover:bg-gray-50">🔄 刷新</button>
          <span class="text-gray-400 text-xs">{{ dictPanelOpen ? '收起 ▲' : '展开 ▼' }}</span>
        </div>
      </div>

      <div v-show="dictPanelOpen" class="p-4 space-y-3">
        <!-- 操作按钮行 -->
        <div class="flex flex-wrap gap-2 pb-3 border-b border-gray-100">
          <button @click="openSyncStaticDialog" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 flex items-center gap-1">
            🔄 同步静态到字典
          </button>
          <button @click="loadOrphans" class="px-4 py-2 bg-amber-500 text-white rounded-lg text-sm hover:bg-amber-600 flex items-center gap-1">
            📜 查看孤儿 ({{ orphanCount }})
          </button>
          <button v-if="orphanCount > 0" @click="mergeAllOrphans" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 flex items-center gap-1">
            🧩 一键融合全部孤儿
          </button>
          <button @click="openAddDictDialog" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700 flex items-center gap-1">
            ➕ 新增字典项
          </button>
        </div>

        <!-- 字典表格 -->
        <el-table :data="dictTableData" v-loading="dictLoading" stripe max-height="320" class="w-full">
          <el-table-column prop="key" label="Key" width="140">
            <template #default="{ row }">
              <span class="font-mono text-xs text-gray-700">{{ row.key }}</span>
              <el-tag v-if="row.category === 'legacy'" size="small" type="warning" class="ml-1">孤儿融合</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="label_zh" label="中文名" width="120" />
          <el-table-column prop="label_en" label="英文名" width="120" />
          <el-table-column prop="icon" label="图标" width="80">
            <template #default="{ row }">
              <span class="font-mono text-xs">{{ row.icon }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="route" label="路由" width="120">
            <template #default="{ row }">
              <span class="font-mono text-xs text-blue-600">{{ row.route }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="category" label="分类" width="100">
            <template #default="{ row }">
              <el-tag size="small" :type="(!row.category || row.category === 'main' || row.category === '') ? 'info' : (row.category === 'legacy' ? 'warning' : 'success')">{{ row.category || '未分类' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="sort_order" label="排序" width="70" />
          <el-table-column label="操作" width="180" fixed="right">
            <template #default="{ row }">
              <button @click="openEditDictDialog(row)" class="text-xs text-blue-600 hover:text-blue-800 mr-2">✏️ 编辑</button>
              <button v-if="row.category === 'legacy'" @click="upgradeCategory(row)" class="text-xs text-amber-600 hover:text-amber-800 mr-2">🆙 升级</button>
              <button @click="deleteDictItem(row)" class="text-xs text-red-600 hover:text-red-800">🗑️ 删除</button>
            </template>
          </el-table-column>
        </el-table>

        <!-- 孤儿列表（展开显示） -->
        <div v-if="orphanListVisible" class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
          <div class="flex justify-between items-center mb-2">
            <span class="text-sm font-medium text-amber-700">📜 孤儿清单（profile 勾了但字典里没定义的模块）</span>
            <button @click="orphanListVisible = false" class="text-xs text-gray-500 hover:text-gray-700">收起</button>
          </div>
          <div v-if="orphanByProfile.length === 0" class="text-sm text-green-600 py-2">✅ 没有孤儿，字典与 profile 100% 同步</div>
          <table v-else class="w-full text-xs">
            <thead><tr class="text-amber-800">
              <th class="text-left p-1">module_key</th>
              <th class="text-left p-1">profile</th>
              <th class="text-left p-1">引用数</th>
              <th class="text-left p-1">操作</th>
            </tr></thead>
            <tbody>
              <tr v-for="o in orphanByProfile" :key="o.server_profile_id + '-' + o.module_key" class="border-t border-amber-100">
                <td class="p-1 font-mono text-amber-900">{{ o.module_key }}</td>
                <td class="p-1 text-gray-700">{{ o.profile_name }}</td>
                <td class="p-1 text-gray-500">{{ o.ref_count }}</td>
                <td class="p-1">
                  <button @click="mergeSingleOrphan(o.module_key)" class="text-blue-600 hover:text-blue-800">🧩 融合这一个</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- 字典项编辑弹窗（新增/编辑共用） -->
    <el-dialog v-model="dictDialogVisible" :title="dictDialogMode === 'add' ? '新增字典项' : '编辑字典项'" width="600px">
      <el-form :model="dictForm" label-width="100px">
        <el-form-item label="Key" required>
          <el-input v-model="dictForm.key" :disabled="dictDialogMode === 'edit'" placeholder="英文短横线，如 attendance-correction" />
        </el-form-item>
        <el-form-item label="中文名" required>
          <el-input v-model="dictForm.label_zh" />
        </el-form-item>
        <el-form-item label="英文名" required>
          <el-input v-model="dictForm.label_en" />
        </el-form-item>
        <el-form-item label="路由" required>
          <el-input v-model="dictForm.route" placeholder="/module-path" />
        </el-form-item>
        <el-form-item label="图标">
          <el-input v-model="dictForm.icon" placeholder="element-plus icon name" />
        </el-form-item>
        <el-form-item label="分类">
          <el-select v-model="dictForm.category" class="w-full">
            <el-option v-for="c in categoryOptions" :key="c" :label="c" :value="c" />
          </el-select>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="dictForm.sort_order" :min="1" :max="999" />
        </el-form-item>
        <el-form-item label="必装">
          <el-switch v-model="dictForm.required" />
        </el-form-item>
      </el-form>
      <template #footer>
        <button @click="dictDialogVisible = false" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded">取消</button>
        <button @click="saveDictItem" class="px-4 py-2 text-sm bg-blue-600 text-white rounded ml-2">保存</button>
      </template>
    </el-dialog>

    <!-- 同步静态到字典 弹窗 -->
    <el-dialog v-model="syncStaticDialogVisible" title="🔄 同步前端 menuModules.js 静态真理源到 DB" width="500px">
      <div class="text-sm text-gray-700 leading-7">
        <p>前端真理源共 <b>{{ staticMenuModules.length }}</b> 条模块定义。</p>
        <p>DB 当前字典共 <b>{{ availableModules.length }}</b> 条。</p>
        <p class="mt-3 text-amber-700">⚠️ 同步策略：</p>
        <ul class="text-xs text-gray-600 list-disc ml-5 space-y-1">
          <li>DB 没有的 key → INSERT 新增</li>
          <li>DB 有的 key → 自动按真理源的 sort_order / label 更新</li>
          <li>DB 有但真理源删了的 → <b>不会自动删除</b>（安全原则）</li>
        </ul>
      </div>
      <template #footer>
        <button @click="syncStaticDialogVisible = false" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded">取消</button>
        <button @click="executeSyncStatic(false)" class="px-4 py-2 text-sm bg-blue-500 text-white rounded ml-2">预览</button>
        <button @click="executeSyncStatic(true)" class="px-4 py-2 text-sm bg-blue-700 text-white rounded ml-2">实际同步</button>
      </template>
    </el-dialog>

    <!-- 列表 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
      <div class="p-4 border-b border-gray-100 flex justify-between items-center">
        <span class="text-sm text-gray-500">{{ $t('serverProfiles.totalServers', { n: profiles.length }) }}</span>
        <button @click="openAdd" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 flex items-center gap-1">
          <span class="text-base">+</span> {{ $t('serverProfiles.addServer') }}
        </button>
      </div>

      <el-table :data="profiles" v-loading="loading" stripe class="w-full">
        <el-table-column :label="$t('serverProfiles.colName')" prop="name" min-width="120" />
        <el-table-column :label="$t('serverProfiles.colIp')" prop="ip" min-width="140">
          <template #default="{ row }">
            <span class="font-mono text-sm">{{ row.ip }}:{{ row.ssh_port || 22 }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="$t('serverProfiles.colEnv')" prop="env" width="100">
          <template #default="{ row }">
            <el-tag :type="getEnvTagType(row.env)" size="small">{{ getEnvLabel(row.env) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="$t('serverProfiles.colIndustry')" width="90">
          <template #default="{ row }">
            <span class="text-sm">{{ getIndustryLabel(row.industry) }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="$t('serverProfiles.colModules')" width="120">
          <template #default="{ row }">
            <div class="flex items-center gap-1">
              <span class="text-blue-600 font-medium">{{ getModuleCount(row.modules) }}</span>
              <span class="text-gray-400 text-xs">/ {{ availableModules.length }}</span>
              <el-tooltip v-if="getOrphanModuleCount(row.modules) > 0" placement="top" :content="'数据库里有 ' + getOrphanModuleCount(row.modules) + ' 个不在菜单模块里的孤儿模块'" >
                <span class="text-xs text-orange-500 ml-1">({{ getOrphanModuleCount(row.modules) }} 孤儿)</span>
              </el-tooltip>
            </div>
          </template>
        </el-table-column>
        <el-table-column :label="$t('serverProfiles.colLang')" min-width="120">
          <template #default="{ row }">
            <span class="text-sm text-gray-700">{{ displayLanguages(row.language) }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="$t('serverProfiles.colCurrency')" prop="currency" width="80">
          <template #default="{ row }">
            <span v-if="row.currency" class="px-1.5 py-0.5 bg-gray-100 rounded text-xs font-mono">{{ row.currency }}</span>
            <span v-else class="text-gray-400">-</span>
          </template>
        </el-table-column>
        <el-table-column :label="$t('serverProfiles.colSiteZh')" prop="site_name_zh" width="110">
          <template #default="{ row }">
            <span class="text-sm">{{ row.site_name_zh || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="$t('common.actions')" width="200" fixed="right">
          <template #default="{ row }">
            <div class="flex gap-2">
              <button @click="openEdit(row)" class="text-blue-600 hover:text-blue-800 text-sm">{{ $t('common.edit') }}</button>
              <button @click="handleSync(row)" class="text-green-600 hover:text-green-800 text-sm">{{ $t('serverProfiles.sync') }}</button>
              <button @click="handleDelete(row)" class="text-red-500 hover:text-red-700 text-sm">{{ $t('common.delete') }}</button>
            </div>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- 新增/编辑弹窗 -->
    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="800px" :close-on-click-modal="false" destroy-on-close>
      <div class="space-y-5 max-h-[70vh] overflow-y-auto pr-2">

        <!-- 基本信息 -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">基本信息</div>
          <el-form :model="form" label-width="120px" class="grid grid-cols-2 gap-x-4">
            <el-form-item :label="$t('serverProfiles.formName')" class="col-span-2 md:col-span-1">
              <el-input v-model="form.name" :placeholder="$t('serverProfiles.formNamePlaceholder')" />
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.formIp')" class="col-span-2 md:col-span-1">
              <el-input v-model="form.ip" placeholder="81.70.199.64" />
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.formSSHPort')" class="col-span-2 md:col-span-1">
              <el-input-number v-model="form.ssh_port" :min="1" :max="65535" controls-position="right" class="w-full" />
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.formSSHUser')" class="col-span-2 md:col-span-1">
              <el-input v-model="form.ssh_user" />
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.formSSHKey')" class="col-span-2">
              <el-input v-model="form.ssh_key_path" />
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.formEnv')" class="col-span-2 md:col-span-1">
              <el-select v-model="form.env" class="w-full">
                <el-option value="production" :label="$t('serverProfiles.envProduction')" />
                <el-option value="staging" :label="$t('serverProfiles.envStaging')" />
                <el-option value="development" :label="$t('serverProfiles.envDev')" />
              </el-select>
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.formDescription')" class="col-span-2">
              <el-input v-model="form.description" type="textarea" :rows="2" />
            </el-form-item>
          </el-form>
        </div>

        <!-- 部署信息 -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">部署信息</div>
          <el-form :model="form" label-width="120px" class="grid grid-cols-2 gap-x-4">
            <el-form-item :label="$t('serverProfiles.formBuildDate')" class="col-span-2 md:col-span-1">
              <el-date-picker v-model="form.build_date" type="date" value-format="YYYY-MM-DD" placeholder="选择日期" class="w-full" />
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.formManager')" class="col-span-2 md:col-span-1">
              <el-input v-model="form.manager" :placeholder="$t('serverProfiles.formManagerPlaceholder')" />
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.formDomain')" class="col-span-2 md:col-span-1">
              <el-input v-model="form.domain" placeholder="wecom.gdqshop.cn" />
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.formWebsite')" class="col-span-2 md:col-span-1">
              <el-input v-model="form.website" placeholder="https://wecom.gdqshop.cn" />
            </el-form-item>
            <!-- 网站Logo -->
            <el-form-item :label="$t('serverProfiles.formSiteLogo')" class="col-span-2">
              <div class="flex items-center gap-3">
                <el-input v-model="form.site_logo" placeholder="https://example.com/logo.png" clearable class="flex-1" />
                <el-button size="small" @click="testSiteLogo" :loading="siteLogoTesting">检测</el-button>
                <img v-if="form.site_logo" :src="form.site_logo" class="h-8 w-auto object-contain border rounded" @error="form.site_logo = ''" />
              </div>
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.formWechatAppid')" class="col-span-2 md:col-span-1">
              <el-input v-model="form.wechat_appid" placeholder="wx90a47bdbe0bf89cb" clearable />
            </el-form-item>
          </el-form>
        </div>

        <!-- 站点信息 -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">站点信息</div>
          <el-form :model="form" label-width="120px" class="grid grid-cols-2 gap-x-4">
            <el-form-item :label="$t('serverProfiles.formSiteNameZh')" class="col-span-2 md:col-span-1">
              <el-input v-model="form.site_name_zh" placeholder="彩美特" />
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.formSiteNameEn')" class="col-span-2 md:col-span-1">
              <el-input v-model="form.site_name_en" placeholder="TRAVELMATE" />
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.formLanguage')" class="col-span-2 md:col-span-1">
              <el-select v-model="form.language" multiple filterable allow-create default-first-option placeholder="选择语言（可多选）" class="w-full">
                <el-option v-for="opt in languageOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.formCurrency')" class="col-span-2 md:col-span-1">
              <el-select v-model="form.currency" placeholder="选择货币" class="w-full">
                <el-option v-for="opt in currencyOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.formIndustry')" class="col-span-2 md:col-span-1">
              <el-select v-model="form.industry" placeholder="选择行业（可选）" class="w-full" clearable>
                <el-option v-for="opt in industryOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-form-item>
          </el-form>
        </div>

        <!-- 数据库配置 -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">数据库配置</div>
          <el-form :model="form" label-width="120px" class="grid grid-cols-2 gap-x-4">
            <el-form-item :label="$t('serverProfiles.mysqlHost')" class="col-span-2 md:col-span-1">
              <el-input v-model="form.mysql_host" placeholder="localhost 或 IP地址" />
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.mysqlPort')" class="col-span-2 md:col-span-1">
              <el-input-number v-model="form.mysql_port" :min="1" :max="65535" controls-position="right" class="w-full" />
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.mysqlDb')" class="col-span-2 md:col-span-1">
              <el-input v-model="form.mysql_db" placeholder="gdq" />
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.mysqlUser')" class="col-span-2 md:col-span-1">
              <el-input v-model="form.mysql_user" placeholder="root / gdq" />
            </el-form-item>
            <el-form-item :label="$t('serverProfiles.mysqlPassword')" class="col-span-2">
              <el-input v-model="form.mysql_password" type="password" show-password placeholder="数据库密码" />
            </el-form-item>
          </el-form>
        </div>

        <!-- SSH密钥 -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">SSH密钥</div>
          <el-form-item :label="$t('serverProfiles.formPem')">
            <el-input v-model="form.pem_content" type="textarea" :rows="3" placeholder="-----BEGIN RSA PRIVATE KEY-----" class="font-mono text-xs" />
          </el-form-item>
        </div>

        <!-- 连接地址（endpoints）— 各端访问入口 -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="flex items-center justify-between mb-3">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">连接地址（endpoints）</div>
            <button type="button" @click="addEndpoint" class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">+ 新增地址</button>
          </div>
          <div class="text-xs text-gray-500 mb-3">每个目标服务器对外暴露的访问入口（H5 客户端 / 管理后台 / API 后端 / 小程序前端），保存后通过 /api/public-settings 下发给前端各端</div>

          <div v-if="!form.endpoints || form.endpoints.length === 0" class="text-center py-4 text-xs text-gray-400 border border-dashed border-gray-200 rounded">
            暂无连接地址，点击右上「+ 新增地址」添加
          </div>

          <div v-else class="space-y-2">
            <div
              v-for="(ep, idx) in form.endpoints"
              :key="idx"
              class="bg-white border border-gray-200 rounded-lg p-3 space-y-2"
            >
              <div class="grid grid-cols-12 gap-2 items-center">
                <!-- 类型 -->
                <div class="col-span-12 md:col-span-3">
                  <el-select v-model="ep.endpoint_type" size="small" class="w-full">
                    <el-option v-for="t in endpointTypes" :key="t.value" :label="t.label" :value="t.value" />
                  </el-select>
                </div>
                <!-- URL -->
                <div class="col-span-12 md:col-span-5">
                  <el-input v-model="ep.url" :placeholder="endpointTypePlaceholder(ep.endpoint_type)" size="small" />
                </div>
                <!-- 显示名 -->
                <div class="col-span-12 md:col-span-2">
                  <el-input v-model="ep.label" placeholder="显示名（可选）" size="small" />
                </div>
                <!-- 删除按钮 -->
                <div class="col-span-12 md:col-span-2 flex items-center justify-end gap-1">
                  <el-tag v-if="ep.id" size="small" type="info">#{{ ep.id }}</el-tag>
                  <button type="button" @click="removeEndpoint(idx)" class="px-2 py-0.5 text-xs text-red-600 border border-red-200 rounded hover:bg-red-50">删除</button>
                </div>
              </div>
              <div class="grid grid-cols-12 gap-2 items-center text-xs">
                <div class="col-span-6 md:col-span-2">
                  <el-select v-model="ep.env" size="small" class="w-full">
                    <el-option label="生产" value="production" />
                    <el-option label="预发" value="staging" />
                    <el-option label="开发" value="development" />
                    <el-option label="测试" value="testing" />
                  </el-select>
                </div>
                <div class="col-span-6 md:col-span-2 flex items-center gap-1">
                  <el-switch v-model="ep.is_primary" />
                  <span class="text-gray-500">主用</span>
                </div>
                <div class="col-span-6 md:col-span-2 flex items-center gap-1">
                  <el-switch v-model="ep.is_active" />
                  <span class="text-gray-500">启用</span>
                </div>
                <div class="col-span-6 md:col-span-2 flex items-center gap-1">
                  <span class="text-gray-500">排序</span>
                  <el-input-number v-model="ep.sort_order" :min="0" :max="9999" controls-position="right" size="small" class="flex-1" />
                </div>
                <div class="col-span-12 md:col-span-4">
                  <el-input v-model="ep.description" placeholder="说明（可选）" size="small" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 模块管理（独立Tab） -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">模块管理</div>

          <!-- ⚠ 孤儿模块警告 banner -->
          <div
            v-if="currentProfileOrphans.length > 0"
            class="mb-3 p-3 bg-amber-50 border border-amber-300 rounded-lg"
          >
            <div class="flex items-start gap-2">
              <span class="text-amber-600 text-lg leading-none">⚠</span>
              <div class="flex-1">
                <div class="text-sm font-medium text-amber-800">
                  当前 profile 有 {{ currentProfileOrphans.length }} 个孤儿模块（字典里没定义）
                </div>
                <div class="text-xs text-amber-700 mt-1">
                  <span
                    v-for="(o, idx) in currentProfileOrphans"
                    :key="o.module_key"
                    class="font-mono"
                  >{{ o.module_key }}{{ idx < currentProfileOrphans.length - 1 ? ', ' : '' }}</span>
                </div>
                <div class="mt-2 flex gap-2">
                  <button
                    @click="mergeCurrentOrphans"
                    class="px-3 py-1 text-xs bg-amber-600 text-white rounded hover:bg-amber-700"
                    title="把孤儿反向 INSERT 到 menu_modules 字典表（标 category='legacy'）"
                  >🧩 一键回填字典</button>
                  <button
                    @click="removeCurrentOrphans"
                    class="px-3 py-1 text-xs bg-white border border-amber-300 text-amber-800 rounded hover:bg-amber-100"
                    title="从当前 profile 移除孤儿勾选（保留字典不动）"
                  >🗑 一键移除</button>
                </div>
              </div>
            </div>
          </div>

          <!-- 已有模块列表 -->
          <div class="mb-3">
            <div class="text-xs text-gray-500 mb-2">当前已启用的模块（{{ profileModules.length }} 个）</div>
            <div v-if="profileModulesLoading" class="text-sm text-gray-400 py-2">加载中...</div>
            <div v-else-if="profileModules.length === 0" class="text-sm text-gray-400 py-2">暂无已启用的模块</div>
            <div v-else class="flex flex-wrap gap-2 mb-3">
              <span
                v-for="mod in profileModules"
                :key="mod.module_key"
                class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded border"
                :class="isCurrentOrphan(mod.module_key) ? 'bg-amber-50 border-amber-300 text-amber-800' : 'bg-blue-50 border-blue-200 text-blue-700'"
              >
                <span v-if="isCurrentOrphan(mod.module_key)" title="孤儿模块（字典里没定义）">⚠</span>
                {{ mod.label_zh || mod.module_key }}
                <span v-if="isCurrentOrphan(mod.module_key)" class="font-mono text-amber-600">({{ mod.module_key }})</span>
                <button
                  @click="handleRemoveModule(mod.module_key)"
                  class="ml-1 hover:text-red-500 font-bold leading-none"
                  :class="isCurrentOrphan(mod.module_key) ? 'text-amber-500' : 'text-blue-400'"
                  title="移除"
                >×</button>
              </span>
            </div>
          </div>

          <!-- 新增单个模块 &批量同步 -->
          <div class="flex flex-col gap-3">
            <!-- 新增单个模块 -->
            <div class="flex items-center gap-2">
              <el-select
                v-model="newModuleKey"
                placeholder="选择要添加的模块"
                filterable
                size="small"
                class="flex-1"
              >
                <el-option
                  v-for="m in availableModules"
                  :key="m.module_key"
                  :label="(m.label_zh || m.module_key) + ' (' + m.module_key + ')'"
                  :value="m.module_key"
                  :disabled="profileModules.some(pm => pm.module_key === m.module_key)"
                />
              </el-select>
              <button
                @click="handleAddModule"
                :disabled="!newModuleKey || moduleOpLoading"
                class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
              >新增</button>
            </div>

            <!-- 批量同步 -->
            <div class="flex items-center gap-2">
              <el-select
                v-model="bulkModuleKeys"
                multiple
                filterable
                placeholder="选择模块后批量同步到服务器"
                size="small"
                class="flex-1"
              >
                <el-option
                  v-for="m in availableModules"
                  :key="m.module_key"
                  :label="(m.label_zh || m.module_key) + ' (' + m.module_key + ')'"
                  :value="m.module_key"
                />
              </el-select>
              <button
                @click="handleBulkSyncModules"
                :disabled="!bulkModuleKeys.length || moduleOpLoading"
                class="px-3 py-1.5 text-sm bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50"
              >批量同步</button>
            </div>
          </div>
        </div>

        <!-- 模块勾选：行业模板 + 精细调整 -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">模块配置</div>

          <!-- 行业模板快捷选择 -->
          <div class="mb-4">
            <div class="text-xs text-gray-500 mb-2">快捷模板（一键勾选整套模块，可叠加）</div>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="tpl in industryTemplates"
                :key="tpl.key"
                @click="applyTemplate(tpl)"
                class="px-3 py-1.5 text-sm border border-blue-200 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors"
              >
                {{ tpl.label_zh }}
              </button>
              <button
                @click="clearModules"
                class="px-3 py-1.5 text-sm border border-gray-200 bg-white text-gray-500 rounded-lg hover:bg-gray-50 transition-colors"
              >
                清空
              </button>
            </div>
          </div>

          <!-- 搜索 -->
          <div class="mb-3">
            <el-input
              v-model="moduleSearch"
              placeholder="搜索模块（输入名称过滤）..."
              clearable
              size="small"
              class="max-w-xs"
            >
              <template #prefix>
                <span class="text-gray-400 text-sm">🔍</span>
              </template>
            </el-input>
          </div>

          <!-- 搜索结果模式 -->
          <div v-if="filteredModules" class="mb-2">
            <div class="text-xs text-gray-500 mb-2">搜索结果（{{ filteredModules.length }} 个）</div>
            <div class="flex flex-wrap gap-1">
              <button
                v-for="mod in filteredModules"
                :key="mod.module_key"
                @click="toggleModule(mod.module_key)"
                class="px-2 py-1 text-xs rounded border transition-colors"
                :class="isModuleSelected(mod.module_key)
                  ? 'bg-blue-600 text-white border-blue-600'
                  : 'bg-white text-gray-700 border-gray-200 hover:border-blue-400'"
              >
                {{ mod.label_zh || mod.module_key }}
              </button>
            </div>
          </div>

          <!-- 按行业分类展示 -->
          <div v-else>
            <div
              v-for="cat in categoryOrder"
              :key="cat"
              v-show="moduleCategoryMap[cat]?.length"
              class="mb-3"
            >
              <div class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">
                {{ getCategoryName(cat) }}
              </div>
              <div class="flex flex-wrap gap-1">
                <button
                  v-for="mod in moduleCategoryMap[cat]"
                  :key="mod.module_key"
                  @click="toggleModule(mod.module_key)"
                  class="px-2 py-1 text-xs rounded border transition-colors"
                  :class="isModuleSelected(mod.module_key)
                    ? 'bg-blue-600 text-white border-blue-600'
                    : 'bg-white text-gray-700 border-gray-200 hover:border-blue-400'"
                >
                  {{ mod.label_zh || mod.module_key }}
                </button>
              </div>
            </div>
          </div>

          <!-- 已选模块汇总 -->
          <div class="mt-3 pt-3 border-t border-gray-200">
            <div class="text-xs text-gray-500 mb-1.5">
              已选模块（{{ form.modules.length }}）{{ form.industry ? '| 行业：' + getIndustryLabel(form.industry) : '' }}
            </div>
            <div class="flex flex-wrap gap-1">
              <span
                v-for="key in form.modules"
                :key="key"
                class="px-2 py-0.5 text-xs bg-blue-100 text-blue-700 rounded"
              >
                {{ availableModules.find(m => m.module_key === key)?.label_zh || key }}
              </span>
              <span v-if="!form.modules.length" class="text-xs text-gray-400">未选择任何模块</span>
            </div>
          </div>
        </div>

      </div>
      <template #footer>
        <button @click="dialogVisible = false" class="px-4 py-2 text-gray-600 hover:text-gray-800 mr-2">{{ $t('common.cancel') }}</button>
        <button @click="submitForm" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">{{ $t('common.save') }}</button>
      </template>
    </el-dialog>
    <!-- 同步预览弹窗 -->
    <el-dialog v-model="syncDialogVisible" :title="$t('serverProfiles.syncPreview')" width="700px">
      <div v-if="syncing" class="text-center py-8 text-gray-500">
        <div class="mb-2">{{ $t('serverProfiles.syncing') }}</div>
      </div>

      <!-- 同步进度条（确认同步后显示） -->
      <div v-else-if="syncLoading" class="py-4">
        <div class="mb-3 text-center font-medium text-blue-700">{{ syncProgress.label || '正在同步...' }}</div>
        <div class="relative h-3 bg-gray-200 rounded-full overflow-hidden mb-2">
          <div class="absolute left-0 top-0 h-full bg-blue-600 rounded-full transition-all duration-300"
               :style="{ width: syncProgress.percent + '%' }"></div>
        </div>
        <div class="flex justify-between text-xs text-gray-400 mb-4">
          <span>0%</span>
          <span>{{ syncProgress.percent }}%</span>
          <span>100%</span>
        </div>
        <!-- 日志输出 -->
        <div v-if="syncProgress.logs.length > 0" class="bg-gray-900 text-green-400 rounded-lg p-3 text-xs font-mono max-h-32 overflow-y-auto">
          <div v-for="(log, i) in syncProgress.logs" :key="i" class="leading-relaxed">{{ log }}</div>
        </div>
      </div>

      <div v-else-if="syncResult">
        <div class="mb-4 p-3 bg-blue-50 rounded-lg">
          <div class="font-medium text-blue-800">{{ syncResult.profile.name }} ({{ syncResult.profile.ip }})</div>
          <div class="text-sm text-blue-600 mt-1">{{ $t('serverProfiles.syncWillUpdate') }} {{ syncResult.moduleKeys.length }} {{ $t('serverProfiles.modules') }}</div>
        </div>
        <div class="mb-3 font-medium text-sm text-gray-700">{{ $t('serverProfiles.baseFiles') }}</div>
        <div class="flex flex-wrap gap-2 mb-4">
          <span v-for="f in syncResult.files.base" :key="f" class="px-2 py-1 bg-gray-100 rounded text-xs font-mono">{{ f }}</span>
        </div>
        <div class="mb-3 font-medium text-sm text-gray-700">{{ $t('serverProfiles.moduleFiles') }}（按模块分组）</div>
        <div class="max-h-64 overflow-y-auto">
          <div v-for="(files, modName) in syncResult.grouped" :key="modName" class="mb-3">
            <div class="text-xs font-medium text-green-700 mb-1">{{ modName }}</div>
            <div class="flex flex-wrap gap-1">
              <span v-for="f in files" :key="f" class="px-1.5 py-0.5 bg-green-50 border border-green-200 rounded text-xs font-mono text-gray-600">{{ f }}</span>
            </div>
          </div>
          <div v-if="Object.keys(syncResult.grouped).length === 0" class="text-sm text-gray-400 text-center py-4">无模块文件</div>
        </div>
        <div class="mt-4 text-xs text-gray-400">{{ $t('serverProfiles.syncCommandHint') }}</div>
      </div>
      <template #footer>
        <button @click="syncDialogVisible = false" class="px-4 py-2 text-gray-600 hover:text-gray-800">{{ $t('common.cancel') }}</button>
        <button v-if="syncResult && !syncLoading" @click="confirmSync" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
          {{ $t('serverProfiles.confirmSyncBtn') || '确认同步' }}
        </button>
      </template>
    </el-dialog>
  </div>
</template>
