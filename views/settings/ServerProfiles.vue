<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import PageHeader from '../../components/PageHeader.vue'
import { serverProfileApi } from '../../services/api.js'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// ─── State ───────────────────────────────────────────────────────────────────
const loading = ref(false)
const profiles = ref([])
const availableModules = ref([])
const dialogVisible = ref(false)
const dialogTitle = ref('')
const isEditing = ref(false)
const editingId = ref(null)

// 表单
const form = ref({
  name: '',
  ip: '',
  ssh_port: 22,
  ssh_user: 'ubuntu',
  ssh_key_path: '/root/clawgdqshop.pem',
  description: '',
  env: 'production',
  build_date: '',
  manager: '',
  domain: '',
  pem_content: '',
  website: '',
  modules: []
})

// 同步状态
const syncing = ref(false)
const syncResult = ref(null)
const syncDialogVisible = ref(false)
const syncLoading = ref(false)

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

// ─── Dialog ─────────────────────────────────────────────────────────────────
function openAdd() {
  dialogTitle.value = t('serverProfiles.addServer')
  isEditing.value = false
  editingId.value = null
  form.value = {
    name: '', ip: '', ssh_port: 22, ssh_user: 'ubuntu',
    ssh_key_path: '/root/clawgdqshop.pem', description: '', env: 'production',
    build_date: '', manager: '', domain: '', pem_content: '', website: '', modules: []
  }
  dialogVisible.value = true
}

function openEdit(row) {
  dialogTitle.value = t('serverProfiles.editServer')
  isEditing.value = true
  editingId.value = row.id
  form.value = {
    name: row.name,
    ip: row.ip,
    ssh_port: row.ssh_port || 22,
    ssh_user: row.ssh_user || 'ubuntu',
    ssh_key_path: row.ssh_key_path || '/root/clawgdqshop.pem',
    description: row.description || '',
    env: row.env || 'production',
    build_date: row.build_date || '',
    manager: row.manager || '',
    domain: row.domain || '',
    pem_content: row.pem_content || '',
    website: row.website || '',
    modules: [...(row.modules || [])]
  }
  dialogVisible.value = true
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
      ElMessage.error(res.msg)
    }
  } catch (e) {
    ElMessage.error(e.message)
  } finally {
    syncing.value = false
  }
}

async function confirmSync() {
  if (!syncResult.value) return
  const profile = syncResult.value.profile
  syncLoading.value = true
  try {
    const sshKey = profile.ssh_key_path || '/root/clawgdqshop.pem'
    const sshPort = profile.ssh_port || 22
    const remoteAddr = profile.ip

    // 调用后端执行 rsync 同步
    const res = await serverProfileApi.execSync(profile.id)
    if (res.code === 0) {
      ElMessage.success('同步完成')
      syncDialogVisible.value = false
    } else {
      ElMessage.error(res.message || '同步失败')
    }
  } catch (e) {
    ElMessage.error(e.message)
  } finally {
    syncLoading.value = false
  }
}

function getModuleCount(modules) {
  return Array.isArray(modules) ? modules.length : 0
}

function getEnvLabel(env) {
  const map = { production: t('serverProfiles.envProduction'), staging: t('serverProfiles.envStaging'), development: t('serverProfiles.envDev') }
  return map[env] || env
}

function getEnvTagType(env) {
  const map = { production: 'danger', staging: 'warning', development: 'success' }
  return map[env] || 'info'
}

// 模块按分类分组
function getModulesByCategory() {
  const cats = {}
  availableModules.value.forEach(m => {
    const cat = m.category || 'other'
    if (!cats[cat]) cats[cat] = []
    cats[cat].push(m)
  })
  return cats
}

onMounted(() => {
  loadProfiles()
  loadAvailableModules()
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
        <el-table-column :label="$t('serverProfiles.colEnv')" prop="env" width="120">
          <template #default="{ row }">
            <el-tag :type="getEnvTagType(row.env)" size="small">{{ getEnvLabel(row.env) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="$t('serverProfiles.colModules')" width="120">
          <template #default="{ row }">
            <span class="text-blue-600 font-medium">{{ getModuleCount(row.modules) }}</span> / {{ availableModules.length }}
          </template>
        </el-table-column>
        <el-table-column :label="$t('serverProfiles.colSSHUser')" prop="ssh_user" width="100" />
        <el-table-column :label="$t('serverProfiles.colDescription')" prop="description" min-width="160" show-overflow-tooltip />
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
    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="640px" :close-on-click-modal="false">
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
        <el-form-item :label="$t('serverProfiles.formBuildDate')" class="col-span-2 md:col-span-1">
          <el-date-picker v-model="form.build_date" type="date" value-format="YYYY-MM-DD" placeholder="选择日期" class="w-full" />
        </el-form-item>
        <el-form-item :label="$t('serverProfiles.formManager')" class="col-span-2 md:col-span-1">
          <el-input v-model="form.manager" :placeholder="$t('serverProfiles.formManagerPlaceholder')" />
        </el-form-item>
        <el-form-item :label="$t('serverProfiles.formDomain')" class="col-span-2 md:col-span-1">
          <el-input v-model="form.domain" placeholder="wecom.gdqshop.cn" />
        </el-form-item>
        <el-form-item :label="$t('serverProfiles.formPem')" class="col-span-2">
          <el-input v-model="form.pem_content" type="textarea" :rows="4" placeholder="-----BEGIN RSA PRIVATE KEY-----" class="font-mono text-xs" />
        </el-form-item>

        <!-- 模块勾选 -->
        <el-form-item :label="$t('serverProfiles.formModules')" class="col-span-2">
          <div class="border border-gray-200 rounded-lg p-3 max-h-64 overflow-y-auto w-full">
            <template v-for="(mods, cat) in getModulesByCategory()" :key="cat">
              <div class="font-semibold text-xs text-gray-500 uppercase mt-2 mb-1 first:mt-0">{{ cat }}</div>
              <div class="grid grid-cols-2 gap-1">
                <label v-for="mod in mods" :key="mod.module_key" class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 rounded px-2 py-1">
                  <input type="checkbox" :value="mod.module_key" v-model="form.modules" class="rounded" />
                  <span class="text-sm">{{ mod.name }}</span>
                </label>
              </div>
            </template>
          </div>
          <div class="text-xs text-gray-400 mt-1">{{ $t('serverProfiles.modulesSelected', { n: form.modules.length }) }}</div>
        </el-form-item>
      </el-form>
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
      <div v-else-if="syncResult">
        <div class="mb-4 p-3 bg-blue-50 rounded-lg">
          <div class="font-medium text-blue-800">{{ syncResult.profile.name }} ({{ syncResult.profile.ip }})</div>
          <div class="text-sm text-blue-600 mt-1">{{ $t('serverProfiles.syncWillUpdate') }} {{ syncResult.moduleKeys.length }} {{ $t('serverProfiles.modules') }}</div>
        </div>
        <div class="mb-3 font-medium text-sm text-gray-700">{{ $t('serverProfiles.baseFiles') }}</div>
        <div class="flex flex-wrap gap-2 mb-4">
          <span v-for="f in syncResult.files.base" :key="f" class="px-2 py-1 bg-gray-100 rounded text-xs font-mono">{{ f }}</span>
        </div>
        <div class="mb-3 font-medium text-sm text-gray-700">{{ $t('serverProfiles.moduleFiles') }}</div>
        <div class="flex flex-wrap gap-2 max-h-48 overflow-y-auto">
          <span v-for="f in syncResult.files.modules" :key="f" class="px-2 py-1 bg-green-50 border border-green-200 rounded text-xs font-mono">{{ f }}.js</span>
        </div>
        <div class="mt-4 text-xs text-gray-400">{{ $t('serverProfiles.syncCommandHint') }}</div>
      </div>
      <template #footer>
        <button @click="syncDialogVisible = false" class="px-4 py-2 text-gray-600 hover:text-gray-800">{{ $t('common.cancel') }}</button>
        <button v-if="syncResult" @click="confirmSync" :disabled="syncLoading" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50">
          {{ syncLoading ? '同步中...' : ($t('serverProfiles.confirmSyncBtn') || '确认同步') }}
        </button>
      </template>
    </el-dialog>
  </div>
</template>
