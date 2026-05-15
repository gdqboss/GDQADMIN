<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../services/api.js'

const { t } = useI18n()

const loading = ref(false)
const roles = ref([])
const selectedRole = ref(null)
const responsibilities = ref([])
const showEditModal = ref(false)
const editForm = ref({
  id: null,
  role: '',
  category: 'duty',
  title: '',
  description: '',
  details: []
})

const categories = computed(() => [
  { value: 'duty', label: t('settings.dutyCategory'), icon: 'task', color: 'primary' },
  { value: 'authority', label: t('settings.authorityCategory'), icon: 'verified_user', color: 'success' },
  { value: 'kpi', label: 'KPI', icon: 'analytics', color: 'warning' }
])

const roleOptions = computed(() => [
  { value: 'chairman', label: t('settings.chairmanRole') },
  { value: 'board', label: t('settings.boardRole') },
  { value: 'admin_dept', label: t('settings.adminDeptRole') },
  { value: 'marketing_dept', label: t('settings.marketingDeptRole') },
  { value: 'tech_dept', label: t('settings.techDeptRole') },
  { value: 'finance_dept', label: t('settings.financeDeptRole') },
  { value: 'admin', label: t('settings.adminRole') },
  { value: 'manager', label: t('settings.managerRole') },
  { value: 'operator', label: t('settings.operatorRole') }
])

const filteredResponsibilities = computed(() => {
  if (!selectedRole.value) return responsibilities.value
  return responsibilities.value.filter(r => r.role === selectedRole.value)
})

const loadRoles = async () => {
  try {
    const res = await api.get('/users/roles')
    if (res.code === 0) {
      roles.value = res.data || []
    }
  } catch (err) {
    console.error('Failed to load roles:', err)
  }
}

const loadResponsibilities = async () => {
  try {
    loading.value = true
    const res = await api.get('/job-responsibilities')
    if (res.code === 0) {
      responsibilities.value = res.data || []
    }
  } catch (err) {
    console.error('Failed to load responsibilities:', err)
  } finally {
    loading.value = false
  }
}

const handleCreate = () => {
  editForm.value = {
    id: null,
    role: selectedRole.value || 'admin',
    category: 'duty',
    title: '',
    description: '',
    details: []
  }
  showEditModal.value = true
}

const handleEdit = (item) => {
  editForm.value = {
    id: item.id,
    role: item.role,
    category: item.category,
    title: item.title,
    description: item.description,
    details: item.details ? JSON.parse(item.details) : []
  }
  showEditModal.value = true
}

const handleDelete = async (id) => {
  if (!confirm(t('settings.confirmDeleteRespMsg'))) return
  try {
    loading.value = true
    const res = await api.delete(`/job-responsibilities/${id}`)
    if (res.code === 0) {
      await loadResponsibilities()
    }
  } catch (err) {
    console.error('Failed to delete responsibility:', err)
    alert(t('settings.deleteFailed'))
  } finally {
    loading.value = false
  }
}

const addDetail = () => {
  editForm.value.details.push('')
}

const removeDetail = (index) => {
  editForm.value.details.splice(index, 1)
}

const saveResponsibility = async () => {
  if (!editForm.value.title) {
    alert(t('settings.pleaseEnterTitleMsg'))
    return
  }
  try {
    loading.value = true
    const data = {
      ...editForm.value,
      details: JSON.stringify(editForm.value.details.filter(d => d.trim()))
    }
    if (editForm.value.id) {
      await api.put(`/job-responsibilities/${editForm.value.id}`, data)
    } else {
      await api.post('/job-responsibilities', data)
    }
    showEditModal.value = false
    await loadResponsibilities()
  } catch (err) {
    console.error('Failed to save responsibility:', err)
    alert(t('settings.saveFailed'))
  } finally {
    loading.value = false
  }
}

const getCategoryInfo = (category) => {
  return categories.value.find(c => c.value === category) || categories.value[0]
}

const getRoleLabel = (role) => {
  const opt = roleOptions.value.find(r => r.value === role)
  return opt ? opt.label : role
}

onMounted(async () => {
  await loadRoles()
  await loadResponsibilities()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex justify-between items-center">
      <h2 class="text-2xl font-bold text-text-primary">{{ $t('settings.respManageTitle') }}</h2>
      <button
        @click="handleCreate"
        class="flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-hover"
      >
        <span class="material-symbols-outlined text-lg">add</span>
        {{ $t('settings.addRespBtn') }}
      </button>
    </div>

    <!-- 角色筛选 -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
      <div class="flex items-center gap-4">
        <label class="text-sm font-medium text-text-primary">{{ $t('settings.filterRole') }}</label>
        <div class="flex gap-2">
          <button
            @click="selectedRole = null"
            :class="[
              'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
              selectedRole === null
                ? 'bg-primary text-white'
                : 'bg-gray-100 text-text-secondary hover:bg-gray-200'
            ]"
          >
            {{ $t('settings.allFilter') }}
          </button>
          <button
            v-for="role in roleOptions"
            :key="role.value"
            @click="selectedRole = role.value"
            :class="[
              'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
              selectedRole === role.value
                ? 'bg-primary text-white'
                : 'bg-gray-100 text-text-secondary hover:bg-gray-200'
            ]"
          >
            {{ role.label }}
          </button>
        </div>
      </div>
    </div>

    <!-- 权责列表 -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card">
      <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-bold text-text-primary">{{ $t('settings.respList') }}</h3>
      </div>
      <div v-if="loading" class="p-8 text-center text-text-secondary">{{ $t('common.loading') }}</div>
      <div v-else-if="filteredResponsibilities.length === 0" class="p-8 text-center text-text-secondary">
        {{ $t('settings.noRespData') }}
      </div>
      <div v-else class="p-6 space-y-4">
        <div
          v-for="item in filteredResponsibilities"
          :key="item.id"
          class="border border-gray-200 rounded-lg p-4 hover:border-primary/50 hover:bg-primary/5 transition-all"
        >
          <div class="flex items-start justify-between mb-3">
            <div class="flex items-start gap-3 flex-1">
              <span
                :class="[
                  'material-symbols-outlined text-2xl shrink-0',
                  `text-${getCategoryInfo(item.category).color}`
                ]"
              >
                {{ getCategoryInfo(item.category).icon }}
              </span>
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-1">
                  <h4 class="font-medium text-text-primary">{{ item.title }}</h4>
                  <span
                    :class="[
                      'px-2 py-0.5 rounded text-xs',
                      `bg-${getCategoryInfo(item.category).color}/10 text-${getCategoryInfo(item.category).color}`
                    ]"
                  >
                    {{ getCategoryInfo(item.category).label }}
                  </span>
                  <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-text-secondary">
                    {{ getRoleLabel(item.role) }}
                  </span>
                </div>
                <p v-if="item.description" class="text-sm text-text-secondary mb-2">{{ item.description }}</p>
                <ul v-if="item.details" class="space-y-1">
                  <li
                    v-for="(detail, idx) in JSON.parse(item.details)"
                    :key="idx"
                    class="text-sm text-text-secondary flex items-start gap-2"
                  >
                    <span class="material-symbols-outlined text-xs mt-0.5">chevron_right</span>
                    <span>{{ detail }}</span>
                  </li>
                </ul>
              </div>
            </div>
            <div class="flex gap-2 ml-4">
              <button
                @click="handleEdit(item)"
                class="text-primary hover:bg-primary/10 p-2 rounded transition-colors"
                :title="$t('common.edit')"
              >
                <span class="material-symbols-outlined text-lg">edit</span>
              </button>
              <button
                @click="handleDelete(item.id)"
                class="text-danger hover:bg-danger/10 p-2 rounded transition-colors"
                :title="$t('common.delete')"
              >
                <span class="material-symbols-outlined text-lg">delete</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 编辑弹窗 -->
    <div v-if="showEditModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
          <h3 class="text-lg font-bold text-text-primary">{{ editForm.id ? $t('settings.editRespTitle') : $t('settings.addRespBtn') }}</h3>
          <button @click="showEditModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('settings.roleLabelField') }}</label>
              <select
                v-model="editForm.role"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
              >
                <option v-for="role in roleOptions" :key="role.value" :value="role.value">{{ role.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('settings.categoryField') }}</label>
              <select
                v-model="editForm.category"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
              >
                <option v-for="cat in categories" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('settings.titleField') }}</label>
            <input
              v-model="editForm.title"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
              :placeholder="$t('settings.enterTitlePlaceholder')"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('settings.descField') }}</label>
            <textarea
              v-model="editForm.description"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
              :placeholder="$t('settings.enterDescPlaceholder')"
            ></textarea>
          </div>
          <div>
            <div class="flex justify-between items-center mb-2">
              <label class="block text-sm font-medium text-text-primary">{{ $t('settings.specificDuties') }}</label>
              <button
                @click="addDetail"
                class="flex items-center gap-1 text-primary hover:underline text-sm"
              >
                <span class="material-symbols-outlined text-sm">add</span>
                {{ $t('settings.addDetailBtn') }}
              </button>
            </div>
            <div class="space-y-2">
              <div
                v-for="(detail, index) in editForm.details"
                :key="index"
                class="flex items-center gap-2"
              >
                <input
                  v-model="editForm.details[index]"
                  type="text"
                  class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                  :placeholder="$t('settings.enterDutyContent')"
                />
                <button
                  @click="removeDetail(index)"
                  class="text-danger hover:bg-danger/10 p-2 rounded transition-colors"
                >
                  <span class="material-symbols-outlined text-lg">delete</span>
                </button>
              </div>
            </div>
          </div>
          <div class="flex justify-end gap-2 pt-4">
            <button
              @click="showEditModal = false"
              class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
            >
              {{ $t('common.cancel') }}
            </button>
            <button
              @click="saveResponsibility"
              :disabled="loading"
              class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-hover disabled:opacity-50"
            >
              {{ $t('common.save') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
