<script setup>
import { ref, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import PageHeader from '../../components/PageHeader.vue'
import { serverProfileApi, serverEndpointApi } from '../../services/api.js'
import { useI18n } from 'vue-i18n'

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
})

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
    // 2026-07-26 补强
    http_port: null, backend_port: null,
    web_server: 'nginx', db_engine: 'mysql', db_version: '',
    redis_host: '', redis_port: 6379, ssh_tunnel_use: 'none',
    deployment_mode: 'fork', last_deploy_at: '', last_sync_from: null,
    os_version: '', cpu_cores: null, ram_total_mb: null, disk_total_gb: null,
    user_count: 0, order_count: 0, data_isolation: 'partial', notes: '',
  }
  dialogVisible.value = true
  // 新建模式没 profile id, endpoint 等保存后再加载
  endpointList.value = []
  loadEndpointTypes()
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
    // 2026-07-26 补强
    http_port: row.http_port, backend_port: row.backend_port,
    web_server: row.web_server || 'nginx',
    db_engine: row.db_engine || 'mysql', db_version: row.db_version || '',
    redis_host: row.redis_host || '', redis_port: row.redis_port || 6379,
    ssh_tunnel_use: row.ssh_tunnel_use || 'none',
    deployment_mode: row.deployment_mode || 'fork',
    last_deploy_at: row.last_deploy_at || '', last_sync_from: row.last_sync_from || null,
    os_version: row.os_version || '', cpu_cores: row.cpu_cores || null,
    ram_total_mb: row.ram_total_mb || null, disk_total_gb: row.disk_total_gb || null,
    user_count: row.user_count || 0, order_count: row.order_count || 0,
    data_isolation: row.data_isolation || 'partial',
    notes: row.notes || '',
  }
  newModuleKey.value = ''
  bulkModuleKeys.value = []
  dialogVisible.value = true
  await loadProfileModules(row.id)
  await loadEndpoints(row.id)
  await loadEndpointTypes()
}

async function submitForm() {
  if (!form.value.name || !form.value.ip) {
    ElMessage.warning(t('serverProfiles.nameIpRequired'))
    return
  }
  try {
    if (isEditing.value) {
      await serverProfileApi.update(editingId.value, form.value)
    } else {
      await serverProfileApi.create(form.value)
    }
    dialogVisible.value = false
    await loadProfiles()
    ElMessage.success(t('common.saveSuccess'))
  } catch (e) {
    ElMessage.error(t('common.saveFailed') + ': ' + e.message)
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
  return Array.isArray(modules) ? modules.length : 0
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

// ─── Health Check (2026-07-26 补强) ───────────────────────────────────────────
const healthChecking = ref(false)
const healthLogLoading = ref(false)
const healthResult = ref(null)
const healthLog = ref([])

async function runHealthCheck() {
  if (!editingId.value) {
    ElMessage.warning('请先选中一个 profile')
    return
  }
  const token = localStorage.getItem('caimeite_token') || ''
  healthChecking.value = true
  healthResult.value = null
  try {
    const res = await fetch(`/api/server-profiles/${editingId.value}/health`, {
      headers: { 'Authorization': 'Bearer ' + token }
    })
    const json = await res.json()
    if (json.code === 0) {
      healthResult.value = json.data
    } else {
      ElMessage.error(json.message || '健康检查失败')
    }
  } catch (err) {
    ElMessage.error('健康检查异常: ' + err.message)
  } finally {
    healthChecking.value = false
  }
}

async function loadHealthLog() {
  if (!editingId.value) return
  const token = localStorage.getItem('caimeite_token') || ''
  healthLogLoading.value = true
  try {
    const res = await fetch(`/api/server-profiles/${editingId.value}/health-log?limit=20`, {
      headers: { 'Authorization': 'Bearer ' + token }
    })
    const json = await res.json()
    if (json.code === 0) {
      healthLog.value = json.data
      if (json.data.length === 0) ElMessage.info('暂无历史检查记录')
    }
  } catch (err) {
    ElMessage.error('加载历史失败: ' + err.message)
  } finally {
    healthLogLoading.value = false
  }
}

// ─── Endpoint Management (2026-07-26 补强) ────────────────────────────────────
const endpointList = ref([])
const endpointLoading = ref(false)
const endpointTypes = ref([]) // 端点类型字典
const endpointDialogVisible = ref(false)
const endpointEditingId = ref(null)
const endpointForm = ref({
  endpoint_type: 'admin_backend',
  label: '',
  url: '',
  is_primary: 0,
  is_active: 1,
  env: 'production',
  sort_order: 99,
  description: ''
})

async function loadEndpointTypes() {
  if (endpointTypes.value.length > 0) return
  try {
    const res = await serverProfileApi.getEndpointTypes()
    endpointTypes.value = res || []
  } catch (e) {
    console.error('[loadEndpointTypes]', e.message)
  }
}

function endpointTypeLabel(typeKey) {
  const t = endpointTypes.value.find(x => x.type_key === typeKey)
  return t ? t.label_zh : typeKey
}

async function loadEndpoints(profileId) {
  if (!profileId) {
    endpointList.value = []
    return
  }
  endpointLoading.value = true
  try {
    const list = await serverEndpointApi.listByProfile(profileId)
    endpointList.value = list || []
  } catch (e) {
    ElMessage.error('加载端点失败: ' + e.message)
    endpointList.value = []
  } finally {
    endpointLoading.value = false
  }
}

function openEndpointAdd() {
  if (!editingId.value) {
    ElMessage.warning('请先保存 profile')
    return
  }
  endpointEditingId.value = null
  endpointForm.value = {
    endpoint_type: endpointTypes.value[0]?.type_key || 'admin_backend',
    label: '', url: '',
    is_primary: 0, is_active: 1,
    env: 'production', sort_order: 99,
    description: ''
  }
  endpointDialogVisible.value = true
}

function openEndpointEdit(row) {
  endpointEditingId.value = row.id
  endpointForm.value = {
    endpoint_type: row.endpoint_type,
    label: row.label,
    url: row.url,
    is_primary: row.is_primary,
    is_active: row.is_active,
    env: row.env,
    sort_order: row.sort_order,
    description: row.description || ''
  }
  endpointDialogVisible.value = true
}

async function submitEndpoint() {
  if (!editingId.value) return
  if (!endpointForm.value.label || !endpointForm.value.url) {
    ElMessage.warning('请填写名称和 URL')
    return
  }
  try {
    if (endpointEditingId.value) {
      await serverEndpointApi.update(editingId.value, endpointEditingId.value, endpointForm.value)
      ElMessage.success('端点已更新')
    } else {
      await serverEndpointApi.create(editingId.value, endpointForm.value)
      ElMessage.success('端点已新增')
    }
    endpointDialogVisible.value = false
    await loadEndpoints(editingId.value)
  } catch (e) {
    ElMessage.error('保存失败: ' + e.message)
  }
}

async function deleteEndpoint(row) {
  if (!editingId.value) return
  try {
    await ElMessageBox.confirm(`确定删除端点 "${row.label}"?`, '确认', { type: 'warning' })
    await serverEndpointApi.remove(editingId.value, row.id)
    ElMessage.success('已删除')
    await loadEndpoints(editingId.value)
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('删除失败: ' + e.message)
  }
}

async function setEndpointPrimary(row) {
  if (!editingId.value) return
  try {
    await serverEndpointApi.resetPrimary(editingId.value, row.endpoint_type, row.id)
    ElMessage.success('已设为主端点')
    await loadEndpoints(editingId.value)
  } catch (e) {
    ElMessage.error('设置失败: ' + e.message)
  }
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
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader :title="$t('serverProfiles.title')" :subtitle="$t('serverProfiles.subtitle')" />

    <!-- 列表 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
      <div class="p-4 border-b border-gray-100 flex justify-between items-center">
        <span class="text-sm text-gray-500">{{ $t('serverProfiles.totalServers', { n: profiles.length }) }}</span>
        <button @click="openAdd" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 flex items-center gap-1">
          <span class="text-base">+</span> {{ $t('serverProfiles.addServer') }}
        </button>
      </div>

      <el-table :data="profiles" v-loading="loading" stripe class="w-full">
        <el-table-column :label="$t('serverProfiles.colName')" min-width="120">
          <template #default="scope">
            <span>{{ scope?.row?.name || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="$t('serverProfiles.colIp')" min-width="140">
          <template #default="scope">
            <span class="font-mono text-sm">{{ scope?.row?.ip || '-' }}:{{ scope?.row?.ssh_port || 22 }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="$t('serverProfiles.colEnv')" width="100">
          <template #default="scope">
            <el-tag :type="getEnvTagType(scope?.row?.env)" size="small">{{ getEnvLabel(scope?.row?.env) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="$t('serverProfiles.colIndustry')" width="90">
          <template #default="scope">
            <span class="text-sm">{{ getIndustryLabel(scope?.row?.industry) }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="$t('serverProfiles.colModules')" width="90">
          <template #default="scope">
            <span class="text-blue-600 font-medium">{{ getModuleCount(scope?.row?.modules) }}</span> / {{ availableModules.length }}
          </template>
        </el-table-column>
        <el-table-column :label="$t('serverProfiles.colLang')" min-width="120">
          <template #default="scope">
            <span class="text-sm text-gray-700">{{ displayLanguages(scope?.row?.language) }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="$t('serverProfiles.colCurrency')" width="80">
          <template #default="scope">
            <span v-if="scope?.row?.currency" class="px-1.5 py-0.5 bg-gray-100 rounded text-xs font-mono">{{ scope.row.currency }}</span>
            <span v-else class="text-gray-400">-</span>
          </template>
        </el-table-column>
        <el-table-column :label="$t('serverProfiles.colSiteZh')" width="110">
          <template #default="scope">
            <span class="text-sm">{{ scope?.row?.site_name_zh || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="$t('common.actions')" width="200" fixed="right">
          <template #default="scope">
            <div class="flex gap-2">
              <button @click="openEdit(scope?.row)" class="text-blue-600 hover:text-blue-800 text-sm">{{ $t('common.edit') }}</button>
              <button @click="handleSync(scope?.row)" class="text-green-600 hover:text-green-800 text-sm">{{ $t('serverProfiles.sync') }}</button>
              <button @click="handleDelete(scope?.row)" class="text-red-500 hover:text-red-700 text-sm">{{ $t('common.delete') }}</button>
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

        <!-- 基础设施 (2026-07-26 补强: web_server / backend_port / db / redis) -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">基础设施</div>
          <el-form :model="form" label-width="120px" class="grid grid-cols-2 gap-x-4">
            <el-form-item label="Web 服务器" class="col-span-2 md:col-span-1">
              <el-select v-model="form.web_server" class="w-full" clearable>
                <el-option value="nginx" label="nginx" />
                <el-option value="caddy" label="caddy (auto SSL)" />
                <el-option value="openresty" label="openresty" />
                <el-option value="apache" label="apache" />
                <el-option value="none" label="none" />
              </el-select>
            </el-form-item>
            <el-form-item label="HTTP 端口" class="col-span-2 md:col-span-1">
              <el-input-number v-model="form.http_port" :min="1" :max="65535" controls-position="right" class="w-full" placeholder="80/443" />
            </el-form-item>
            <el-form-item label="Node 后端端口" class="col-span-2 md:col-span-1">
              <el-input-number v-model="form.backend_port" :min="1" :max="65535" controls-position="right" class="w-full" placeholder="SGP=3200, HK=3300" />
            </el-form-item>
            <el-form-item label="通信通道" class="col-span-2 md:col-span-1">
              <el-select v-model="form.ssh_tunnel_use" class="w-full" clearable>
                <el-option value="none" label="无 (直连)" />
                <el-option value="wireguard" label="WireGuard (HK 之前)" />
                <el-option value="ssh_tunnel" label="SSH 隧道" />
                <el-option value="vpn" label="VPN" />
              </el-select>
            </el-form-item>
            <el-form-item label="DB 引擎" class="col-span-2 md:col-span-1">
              <el-select v-model="form.db_engine" class="w-full" clearable>
                <el-option value="mysql" label="MySQL" />
                <el-option value="mariadb" label="MariaDB (HK=SGP)" />
                <el-option value="postgres" label="PostgreSQL" />
                <el-option value="sqlite" label="SQLite" />
                <el-option value="none" label="none" />
              </el-select>
            </el-form-item>
            <el-form-item label="DB 版本" class="col-span-2 md:col-span-1">
              <el-input v-model="form.db_version" placeholder="10.11.14 / 8.0.36" />
            </el-form-item>
            <el-form-item label="Redis 地址" class="col-span-2 md:col-span-1">
              <el-input v-model="form.redis_host" placeholder="127.0.0.1 (空=未启用)" />
            </el-form-item>
            <el-form-item label="Redis 端口" class="col-span-2 md:col-span-1">
              <el-input-number v-model="form.redis_port" :min="1" :max="65535" controls-position="right" class="w-full" placeholder="6379" />
            </el-form-item>
          </el-form>
        </div>

        <!-- 部署状态 + 资源 (2026-07-26 补强) -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">部署状态 / 资源</div>
          <el-form :model="form" label-width="120px" class="grid grid-cols-2 gap-x-4">
            <el-form-item label="部署模式" class="col-span-2 md:col-span-1">
              <el-select v-model="form.deployment_mode" class="w-full" clearable>
                <el-option value="source" label="source (唯一源头)" />
                <el-option value="fork" label="fork (复制)" />
                <el-option value="independent" label="independent (独立)" />
                <el-option value="shared_via_tunnel" label="shared_via_tunnel (隧道共享)" />
              </el-select>
            </el-form-item>
            <el-form-item label="数据隔离" class="col-span-2 md:col-span-1">
              <el-select v-model="form.data_isolation" class="w-full" clearable>
                <el-option value="full" label="full (零数据交集, HK)" />
                <el-option value="partial" label="partial (部分共享)" />
                <el-option value="none" label="none (共享 SGP)" />
              </el-select>
            </el-form-item>
            <el-form-item label="OS 版本" class="col-span-2 md:col-span-1">
              <el-input v-model="form.os_version" placeholder="Ubuntu 24.04.4 LTS" />
            </el-form-item>
            <el-form-item label="CPU 核心" class="col-span-2 md:col-span-1">
              <el-input-number v-model="form.cpu_cores" :min="1" :max="128" controls-position="right" class="w-full" />
            </el-form-item>
            <el-form-item label="RAM (MB)" class="col-span-2 md:col-span-1">
              <el-input-number v-model="form.ram_total_mb" :min="256" controls-position="right" class="w-full" placeholder="3600" />
            </el-form-item>
            <el-form-item label="Disk (GB)" class="col-span-2 md:col-span-1">
              <el-input-number v-model="form.disk_total_gb" :min="1" controls-position="right" class="w-full" placeholder="55" />
            </el-form-item>
            <el-form-item label="上次部署" class="col-span-2 md:col-span-1">
              <el-date-picker v-model="form.last_deploy_at" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" placeholder="2026-07-26 12:00:00" class="w-full" />
            </el-form-item>
            <el-form-item label="上次同步源" class="col-span-2 md:col-span-1">
              <el-input-number v-model="form.last_sync_from" :min="1" controls-position="right" class="w-full" placeholder="profile_id (如 1)" />
            </el-form-item>
          </el-form>
        </div>

        <!-- 业务运营 / 备注 (2026-07-26 补强) -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">业务 / 备注</div>
          <el-form :model="form" label-width="120px" class="grid grid-cols-2 gap-x-4">
            <el-form-item label="用户数" class="col-span-2 md:col-span-1">
              <el-input-number v-model="form.user_count" :min="0" controls-position="right" class="w-full" />
            </el-form-item>
            <el-form-item label="订单数" class="col-span-2 md:col-span-1">
              <el-input-number v-model="form.order_count" :min="0" controls-position="right" class="w-full" />
            </el-form-item>
            <el-form-item label="运维备注" class="col-span-2">
              <el-input v-model="form.notes" type="textarea" :rows="4" placeholder="运维备注: 部署历史/特殊配置/坑/未解事项" />
            </el-form-item>
          </el-form>
        </div>

        <!-- 健康检查 (2026-07-26 补强:实时探针) -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center justify-between">
            <span>健康检查 (实时探针)</span>
            <div class="flex gap-2">
              <button @click="runHealthCheck" :disabled="healthChecking" class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50">
                {{ healthChecking ? '检查中...' : '立即检查' }}
              </button>
              <button @click="loadHealthLog" :disabled="healthLogLoading" class="px-3 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700 disabled:opacity-50">
                {{ healthLogLoading ? '加载...' : '查看历史' }}
              </button>
            </div>
          </div>
          <div v-if="healthResult" class="space-y-2">
            <div v-for="check in healthResult.checks" :key="check.check_type"
              :class="['flex items-center justify-between p-2 rounded border text-sm',
                       check.status === 'ok' ? 'bg-green-50 border-green-200' :
                       check.status === 'warn' ? 'bg-yellow-50 border-yellow-200' :
                       check.status === 'skip' ? 'bg-gray-50 border-gray-200' :
                       'bg-red-50 border-red-200']">
              <div class="flex items-center gap-2">
                <span :class="['inline-block w-2 h-2 rounded-full',
                              check.status === 'ok' ? 'bg-green-500' :
                              check.status === 'warn' ? 'bg-yellow-500' :
                              check.status === 'skip' ? 'bg-gray-400' :
                              'bg-red-500']"></span>
                <span class="font-mono text-xs uppercase">{{ check.check_type }}</span>
                <span class="text-gray-600">{{ check.message }}</span>
              </div>
              <span v-if="check.latency_ms > 0" class="text-xs text-gray-500 font-mono">{{ check.latency_ms }}ms</span>
            </div>
            <div class="text-xs text-gray-400">检查时间: {{ healthResult.checked_at }}</div>
          </div>
          <div v-else-if="healthLog.length > 0" class="space-y-1 max-h-48 overflow-y-auto">
            <div v-for="log in healthLog" :key="log.id" class="flex items-center justify-between text-xs py-1 px-2 hover:bg-gray-50">
              <div class="flex items-center gap-2">
                <span :class="['inline-block w-1.5 h-1.5 rounded-full',
                              log.status === 'ok' ? 'bg-green-500' :
                              log.status === 'warn' ? 'bg-yellow-500' :
                              log.status === 'skip' ? 'bg-gray-400' :
                              'bg-red-500']"></span>
                <span class="font-mono">{{ log.check_type }}</span>
                <span class="text-gray-500">{{ log.message?.slice(0, 60) }}</span>
              </div>
              <span class="text-gray-400 font-mono">{{ log.checked_at }}</span>
            </div>
          </div>
          <div v-else class="text-xs text-gray-400 py-2">未检查, 点击"立即检查"开始</div>
        </div>

        <!-- 端点管理 (2026-07-26 补强: server_endpoints 子表 CRUD UI) -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center justify-between">
            <span>应用入口 (端点 URL)</span>
            <button @click="openEndpointAdd" class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">新增端点</button>
          </div>
          <el-table :data="endpointList" v-loading="endpointLoading" stripe size="small" class="w-full">
            <el-table-column prop="endpoint_type" label="类型" width="120">
              <template #default="{ row }">
                <span class="text-xs px-1.5 py-0.5 bg-blue-50 text-blue-700 rounded">{{ endpointTypeLabel(row.endpoint_type) }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="label" label="名称" min-width="120" />
            <el-table-column prop="url" label="URL" min-width="180">
              <template #default="{ row }">
                <a :href="row.url" target="_blank" class="text-blue-600 hover:underline text-xs font-mono truncate block max-w-xs">{{ row.url }}</a>
              </template>
            </el-table-column>
            <el-table-column label="主" width="50">
              <template #default="{ row }">
                <span v-if="row.is_primary" class="text-green-600 text-xs">✓</span>
                <button v-else @click="setEndpointPrimary(row)" class="text-xs text-gray-400 hover:text-blue-600">设主</button>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="100">
              <template #default="{ row }">
                <button @click="openEndpointEdit(row)" class="text-xs text-blue-600 hover:underline mr-2">编辑</button>
                <button @click="deleteEndpoint(row)" class="text-xs text-red-600 hover:underline">删除</button>
              </template>
            </el-table-column>
          </el-table>
          <div v-if="endpointList.length === 0 && !endpointLoading" class="text-xs text-gray-400 py-2 text-center">
            暂无端点,点击"新增端点"添加 URL
          </div>
        </div>

        <!-- 模块管理（独立Tab） -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">模块管理</div>

          <!-- 已有模块列表 -->
          <div class="mb-3">
            <div class="text-xs text-gray-500 mb-2">当前已启用的模块（{{ profileModules.length }} 个）</div>
            <div v-if="profileModulesLoading" class="text-sm text-gray-400 py-2">加载中...</div>
            <div v-else-if="profileModules.length === 0" class="text-sm text-gray-400 py-2">暂无已启用的模块</div>
            <div v-else class="flex flex-wrap gap-2 mb-3">
              <span
                v-for="mod in profileModules"
                :key="mod.module_key"
                class="inline-flex items-center gap-1 px-2 py-1 text-xs bg-blue-50 border border-blue-200 text-blue-700 rounded"
              >
                {{ mod.label_zh || mod.module_key }}
                <button
                  @click="handleRemoveModule(mod.module_key)"
                  class="ml-1 text-blue-400 hover:text-red-500 font-bold leading-none"
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

    <!-- 端点编辑子弹窗 (2026-07-26 补强) -->
    <el-dialog v-model="endpointDialogVisible" :title="endpointEditingId ? '编辑端点' : '新增端点'" width="500px" :close-on-click-modal="false">
      <el-form :model="endpointForm" label-width="100px">
        <el-form-item label="类型" required>
          <el-select v-model="endpointForm.endpoint_type" class="w-full">
            <el-option v-for="t in endpointTypes" :key="t.type_key" :label="t.label_zh" :value="t.type_key" />
          </el-select>
        </el-form-item>
        <el-form-item label="名称" required>
          <el-input v-model="endpointForm.label" placeholder="HK 主站后台 /admin" />
        </el-form-item>
        <el-form-item label="URL" required>
          <el-input v-model="endpointForm.url" placeholder="https://hatch.gdqshop.cn/admin" />
        </el-form-item>
        <el-form-item label="是否主">
          <el-switch v-model="endpointForm.is_primary" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="启用">
          <el-switch v-model="endpointForm.is_active" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="环境">
          <el-select v-model="endpointForm.env" class="w-full">
            <el-option value="production" label="production" />
            <el-option value="staging" label="staging" />
            <el-option value="development" label="development" />
          </el-select>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="endpointForm.sort_order" :min="0" controls-position="right" class="w-full" />
        </el-form-item>
        <el-form-item label="说明">
          <el-input v-model="endpointForm.description" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <button @click="endpointDialogVisible = false" class="px-4 py-2 border rounded-lg mr-2">取消</button>
        <button @click="submitEndpoint" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">保存</button>
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
