<script setup>
import { ref, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import PageHeader from '../../components/PageHeader.vue'
import { serverProfileApi } from '../../services/api.js'
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
  ecommerce: '电商', sales: '销售CRM', education: '教育',
  company: '企业管理', realestate: '房地产', restaurant: '餐饮', hotel: '酒店',
  main: '基础', partner: '合作伙伴'
}

// 表单
const form = ref({
  name: '', ip: '', ssh_port: 22, ssh_user: 'ubuntu',
  ssh_key_path: '/root/clawgdqshop.pem', description: '', env: 'production',
  build_date: '', manager: '', domain: '', pem_content: '', website: '',
  modules: [], site_name_zh: '', site_name_en: '', language: [], currency: '', industry: '',
})

// 同步状态
const syncing = ref(false)
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
    modules: [], site_name_zh: '', site_name_en: '', language: [], currency: '', industry: '',
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
    website: row.website || '', modules: [...(row.modules || [])],
    site_name_zh: row.site_name_zh || '', site_name_en: row.site_name_en || '',
    language: langArray, currency: row.currency || '', industry: row.industry || '',
  }
  newModuleKey.value = ''
  bulkModuleKeys.value = []
  dialogVisible.value = true
  await loadProfileModules(row.id)
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

const categoryOrder = ['ecommerce', 'restaurant', 'hotel', 'sales', 'education', 'company', 'main', 'partner']

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
        <el-table-column :label="$t('serverProfiles.colModules')" width="90">
          <template #default="{ row }">
            <span class="text-blue-600 font-medium">{{ getModuleCount(row.modules) }}</span> / {{ availableModules.length }}
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

        <!-- SSH密钥 -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">SSH密钥</div>
          <el-form-item :label="$t('serverProfiles.formPem')">
            <el-input v-model="form.pem_content" type="textarea" :rows="3" placeholder="-----BEGIN RSA PRIVATE KEY-----" class="font-mono text-xs" />
          </el-form-item>
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
