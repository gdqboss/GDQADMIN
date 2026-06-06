<script setup>
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../services/api.js'

const { t } = useI18n()

const loading = ref(true)
const responsibilities = ref([])
const jobLevels = ref([])
const departments = ref([])
const flatDepartments = ref([])

function flattenDepartments(depts, result = [], level = 0) {
  for (const dept of depts) {
    result.push({
      ...dept,
      displayName: '　'.repeat(level) + dept.name,
      _level: level
    })
    if (dept.children && dept.children.length > 0) {
      flattenDepartments(dept.children, result, level + 1)
    }
  }
  return result
}

// 筛选条件
const filterJobLevel = ref('')
const filterDepartment = ref('')
const filterCategory = ref('')

// 编辑对话框
const showDialog = ref(false)
const editMode = ref('create') // 'create' | 'edit'
const currentItem = ref({
  id: null,
  job_level_id: '',
  department_id: null,
  title: '',
  description: '',
  category: 'duty',
  sort_order: 0
})

const categories = computed(() => [
  { value: 'duty', label: t('settings.dutyCategory'), color: 'primary' },
  { value: 'authority', label: t('settings.authorityCategory'), color: 'success' },
  { value: 'kpi', label: t('settings.kpiCategory'), color: 'warning' }
])

const filteredData = computed(() => {
  let result = responsibilities.value

  if (filterJobLevel.value) {
    result = result.filter(item => item.job_level_id == filterJobLevel.value)
  }

  if (filterDepartment.value) {
    result = result.filter(item =>
      item.department_id == filterDepartment.value || !item.department_id
    )
  }

  if (filterCategory.value) {
    result = result.filter(item => item.category === filterCategory.value)
  }

  return result
})

async function loadData() {
  loading.value = true
  try {
    const [respData, levelsData, deptsData] = await Promise.all([
      api.get('/job-responsibilities'),
      api.get('/oa/job-levels'),
      api.get('/oa/departments')
    ])

    if (respData.code === 0) responsibilities.value = respData.data
    if (levelsData.code === 0) jobLevels.value = levelsData.data
    if (deptsData.code === 0) {
      departments.value = deptsData.data
      flatDepartments.value = flattenDepartments(deptsData.data)
    }
  } catch (err) {
    console.error('加载失败:', err)
  } finally {
    loading.value = false
  }
}

function openCreateDialog() {
  editMode.value = 'create'
  currentItem.value = {
    id: null,
    job_level_id: '',
    department_id: null,
    title: '',
    description: '',
    category: 'duty',
    sort_order: 0
  }
  showDialog.value = true
}

function openEditDialog(item) {
  editMode.value = 'edit'
  currentItem.value = { ...item }
  showDialog.value = true
}

async function saveItem() {
  try {
    if (!currentItem.value.job_level_id || !currentItem.value.title) {
      alert(t('settings.fillLevelAndTitle'))
      return
    }

    if (editMode.value === 'create') {
      const res = await api.post('/job-responsibilities', currentItem.value)
      if (res.code === 0) {
        alert(t('settings.createSuccess'))
        showDialog.value = false
        loadData()
      }
    } else {
      const res = await api.put(`/job-responsibilities/${currentItem.value.id}`, currentItem.value)
      if (res.code === 0) {
        alert(t('settings.updateSuccess'))
        showDialog.value = false
        loadData()
      }
    }
  } catch (err) {
    alert(err.message || t('settings.operationFailed'))
  }
}

async function deleteItem(id) {
  if (!confirm(t('settings.confirmDeleteResp'))) return

  try {
    const res = await api.delete(`/job-responsibilities/${id}`)
    if (res.code === 0) {
      alert(t('settings.deleteSuccess'))
      loadData()
    }
  } catch (err) {
    alert(err.message || t('settings.deleteFailed'))
  }
}

function getCategoryLabel(category) {
  return categories.value.find(c => c.value === category)?.label || category
}

function getCategoryColor(category) {
  return categories.value.find(c => c.value === category)?.color || 'gray'
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-text-primary">{{ $t('settings.jobRespTitle') }}</h1>
        <p class="text-sm text-text-secondary mt-1">{{ $t('settings.jobRespSubtitle') }}</p>
      </div>
      <button @click="openCreateDialog" class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
        <span class="material-symbols-outlined text-[20px]">add</span>
        {{ $t('settings.addResp') }}
      </button>
    </div>

    <!-- 筛选条件 -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-sm p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs text-text-secondary mb-1.5">{{ $t('settings.jobLevel') }}</label>
          <select v-model="filterJobLevel" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none">
            <option value="">{{ $t('settings.allJobLevels') }}</option>
            <option v-for="level in jobLevels" :key="level.id" :value="level.id">{{ level.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-text-secondary mb-1.5">{{ $t('settings.departmentLabel') }}</label>
          <select v-model="filterDepartment" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none">
            <option value="">{{ $t('settings.allDepts') }}</option>
            <option v-for="dept in flatDepartments" :key="dept.id" :value="dept.id">{{ dept.displayName }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-text-secondary mb-1.5">{{ $t('settings.categoryLabel') }}</label>
          <select v-model="filterCategory" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none">
            <option value="">{{ $t('settings.allCategories') }}</option>
            <option v-for="cat in categories" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- 数据列表 -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-sm overflow-hidden">
      <div v-if="loading" class="flex justify-center py-12">
        <div class="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
      </div>

      <div v-else-if="filteredData.length === 0" class="text-center py-12 text-text-secondary">
        <span class="material-symbols-outlined text-5xl text-gray-300">description</span>
        <p class="mt-3">{{ $t('common.noData') }}</p>
      </div>

      <table v-else class="w-full">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('settings.jobLevel') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('settings.departmentLabel') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('settings.categoryLabel') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('settings.titleCol2') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('settings.descriptionCol') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('settings.sortOrder') }}</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-text-secondary uppercase">{{ $t('settings.actionCol') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="item in filteredData" :key="item.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-sm text-text-primary font-medium">{{ item.job_level_name }}</td>
            <td class="px-4 py-3 text-sm text-text-secondary">{{ item.department_name || $t('settings.generalDept') }}</td>
            <td class="px-4 py-3">
              <span :class="[
                'px-2 py-1 rounded text-xs font-medium',
                `bg-${getCategoryColor(item.category)}/10 text-${getCategoryColor(item.category)}`
              ]">
                {{ getCategoryLabel(item.category) }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-text-primary">{{ item.title }}</td>
            <td class="px-4 py-3 text-sm text-text-secondary max-w-xs truncate">{{ item.description }}</td>
            <td class="px-4 py-3 text-sm text-text-secondary">{{ item.sort_order }}</td>
            <td class="px-4 py-3 text-right">
              <button @click="openEditDialog(item)" class="text-primary hover:text-primary-hover text-sm mr-3">{{ $t('common.edit') }}</button>
              <button @click="deleteItem(item.id)" class="text-danger hover:text-danger/80 text-sm">{{ $t('common.delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 编辑对话框 -->
    <div v-if="showDialog" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
          <h3 class="text-lg font-bold text-text-primary">{{ editMode === 'create' ? $t('settings.addRespTitle') : $t('settings.editRespTitle') }}</h3>
          <button @click="showDialog = false" class="text-gray-400 hover:text-gray-600">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="p-6 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1.5">{{ $t('settings.jobLevel') }} <span class="text-danger">*</span></label>
              <select v-model="currentItem.job_level_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option value="">{{ $t('settings.allJobLevels') }}</option>
                <option v-for="level in jobLevels" :key="level.id" :value="level.id">{{ level.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1.5">{{ $t('settings.departmentLabel') }} <span class="text-text-secondary text-xs">{{ $t('settings.optionalDept') }}</span></label>
              <select v-model="currentItem.department_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option :value="null">{{ $t('settings.generalAllDepts') }}</option>
                <option v-for="dept in flatDepartments" :key="dept.id" :value="dept.id">{{ dept.displayName }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1.5">{{ $t('settings.categoryLabel') }} <span class="text-danger">*</span></label>
              <select v-model="currentItem.category" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="cat in categories" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1.5">{{ $t('settings.sortOrder') }}</label>
              <input v-model.number="currentItem.sort_order" type="number" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-text-primary mb-1.5">{{ $t('settings.titleCol2') }} <span class="text-danger">*</span></label>
            <input v-model="currentItem.title" type="text" :placeholder="$t('settings.titlePlaceholder2')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none" />
          </div>

          <div>
            <label class="block text-sm font-medium text-text-primary mb-1.5">{{ $t('settings.descriptionCol') }}</label>
            <textarea v-model="currentItem.description" rows="4" :placeholder="$t('settings.descPlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none resize-none"></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 p-6 border-t border-gray-100">
          <button @click="showDialog = false" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-text-secondary hover:bg-gray-50">
            {{ $t('common.cancel') }}
          </button>
          <button @click="saveItem" class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium">
            {{ $t('common.save') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@media (max-width: 768px) {
  /* 标题区域 */
  .mb-6.flex.items-center.justify-between {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .mb-6.flex.items-center.justify-between button {
    width: 100%;
    justify-content: center;
  }

  h1.text-2xl {
    font-size: 1.25rem;
  }

  p.text-sm {
    font-size: 0.8rem;
  }

  /* 筛选区域 */
  .bg-white.rounded-lg.border.border-gray-100.shadow-sm.p-4.mb-6 {
    padding: 12px;
  }

  .grid.grid-cols-1.md\:grid-cols-3.gap-4 {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  /* 表格区域 */
  .bg-white.rounded-lg.border.border-gray-100.shadow-sm.overflow-hidden {
    overflow-x: auto;
  }

  table.w-full {
    min-width: 700px;
  }

  th, td {
    padding: 8px !important;
    font-size: 0.75rem;
  }

  th.text-xs {
    font-size: 0.65rem;
  }

  td.text-sm {
    font-size: 0.75rem;
  }

  td.max-w-xs.truncate {
    max-width: 100px;
  }

  /* 对话框 */
  .fixed.inset-0.bg-black\/50 {
    padding: 8px;
  }

  .bg-white.rounded-xl.shadow-xl.w-full.max-w-2xl {
    max-width: calc(100vw - 16px);
    max-height: 85vh;
  }

  .p-6.space-y-4 {
    padding: 12px;
    gap: 12px;
  }

  .grid.grid-cols-2.gap-4 {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .p-6.border-t.border-gray-100 {
    padding: 12px;
    flex-direction: column;
  }

  .flex.items-center.justify-end.gap-3 button {
    flex: 1;
    padding: 10px 16px;
  }

  .text-lg.font-bold {
    font-size: 1rem;
  }
}
</style>
