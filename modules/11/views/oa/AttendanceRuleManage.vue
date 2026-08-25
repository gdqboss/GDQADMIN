<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">{{ $t('oa.attendanceRules') }}</h1>
      <button @click="openCreate" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center gap-1">
        <span class="material-symbols-outlined text-[18px]">add</span>
        {{ $t('oa.createRule') }}
      </button>
    </div>

    <!-- 规则列表 -->
    <div v-if="rules.length === 0" class="text-center py-16 text-gray-400">
      <span class="material-symbols-outlined text-[48px] mb-2">event_available</span>
      <p>{{ $t('oa.noRules') }}</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="rule in rules" :key="rule.id" class="border rounded-lg p-4 hover:shadow-lg transition-shadow">
        <div class="flex justify-between items-start mb-3">
          <h3 class="text-lg font-semibold text-gray-800">{{ rule.name }}</h3>
          <span :class="['px-2 py-1 text-xs rounded', rule.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800']">
            {{ rule.status === 'active' ? $t('oa.ruleActive') : $t('oa.ruleInactive') }}
          </span>
        </div>

        <!-- 星期标签 -->
        <div class="flex gap-1 mb-3">
          <span v-for="(day, idx) in weekDayLabels" :key="idx"
            :class="['w-8 h-8 rounded-full text-xs flex items-center justify-center font-medium',
              rule.weekdays?.includes(idx + 1) ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-400']">
            {{ day }}
          </span>
        </div>

        <!-- 时间段信息 -->
        <div class="text-sm text-gray-600 mb-2">
          <span class="material-symbols-outlined text-[14px] align-middle">schedule</span>
          {{ rule.start_time || '09:00' }} - {{ rule.end_time || '18:00' }}
        </div>

        <!-- 人员数 -->
        <div class="text-sm text-gray-600 mb-3">
          <span class="material-symbols-outlined text-[14px] align-middle">group</span>
          {{ rule.members?.length || 0 }} {{ $t('oa.attendanceMembers') }}
        </div>

        <!-- 人员头像列表 -->
        <div v-if="rule.members?.length" class="flex flex-wrap gap-1 mb-3">
          <span v-for="m in rule.members.slice(0, 8)" :key="m.user_id"
            class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 text-blue-700 rounded text-xs">
            {{ m.user_name || m.user_id }}
          </span>
          <span v-if="rule.members.length > 8" class="text-xs text-gray-400 self-center">+{{ rule.members.length - 8 }}</span>
        </div>

        <!-- 操作按钮 -->
        <div class="flex gap-2 pt-2 border-t border-gray-100">
          <button @click="openEdit(rule)" class="text-blue-600 hover:underline text-sm">{{ $t('common.edit') }}</button>
          <button @click="toggleStatus(rule)" class="text-orange-600 hover:underline text-sm">
            {{ rule.status === 'active' ? $t('oa.disableRule') : $t('oa.enableRule') }}
          </button>
          <button @click="deleteRule(rule)" class="text-red-500 hover:underline text-sm ml-auto">{{ $t('oa.deleteRule') }}</button>
        </div>
      </div>
    </div>

    <!-- 新建/编辑弹窗 -->
    <div v-if="showDialog" class="fixed inset-0 bg-black/30 flex items-center justify-center z-50" @click.self="closeDialog">
      <div class="bg-white rounded-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center z-10">
          <h2 class="text-xl font-bold">{{ editingRule ? $t('oa.editRule') : $t('oa.createRule') }}</h2>
          <button @click="closeDialog" class="text-gray-400 hover:text-gray-600">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="p-6 space-y-5">
          <!-- 规则名称 -->
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('oa.ruleName') }}</label>
            <input v-model="form.name" :placeholder="$t('oa.ruleNamePlaceholder')" required class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>

          <!-- 工作日勾选 -->
          <div>
            <label class="block text-sm font-medium mb-2">{{ $t('oa.workdays') }}</label>
            <div class="flex flex-wrap gap-2">
              <label v-for="(day, idx) in weekDayLabels" :key="idx"
                :class="['inline-flex items-center gap-1.5 px-4 py-2 border rounded-lg cursor-pointer transition-colors',
                  form.weekdays.includes(idx + 1) ? 'bg-blue-100 border-blue-400 text-blue-700' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50']">
                <input type="checkbox" :value="idx + 1" v-model="form.weekdays" class="hidden" />
                {{ day }}
              </label>
            </div>
          </div>

          <!-- 时间段 -->
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('oa.shiftTime') || '时间段' }}</label>
            <div class="flex items-center gap-2">
              <input v-model="form.start_time" type="time" class="flex-1 border rounded-lg px-3 py-2 text-sm" />
              <span class="text-gray-400">~</span>
              <input v-model="form.end_time" type="time" class="flex-1 border rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>

          <!-- 出勤人员 -->
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('oa.attendanceMembers') }}</label>
            <p class="text-xs text-orange-600 mb-2 flex items-center gap-1">
              <span class="material-symbols-outlined text-[14px]">info</span>
              {{ $t('oa.requiredHint') }}
            </p>

            <!-- 搜索 -->
            <input v-model="employeeSearch" :placeholder="$t('oa.searchEmployee') || '搜索员工'" class="w-full border rounded-lg px-3 py-2 text-sm mb-2" />

            <!-- 已选人员标签 -->
            <div v-if="form.member_ids.length" class="flex flex-wrap gap-1 mb-2">
              <span v-for="uid in form.member_ids" :key="uid"
                class="inline-flex items-center gap-1 px-2 py-1 bg-blue-50 text-blue-700 rounded text-xs">
                {{ getEmployeeName(uid) }}
                <button type="button" @click="removeMember(uid)" class="text-blue-400 hover:text-red-500">×</button>
              </span>
            </div>

            <!-- 员工列表 -->
            <div class="border rounded-lg max-h-48 overflow-y-auto">
              <div v-for="emp in filteredEmployees" :key="emp.id"
                class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer border-b border-gray-50 last:border-0"
                @click="toggleMember(emp.id)">
                <input type="checkbox" :checked="form.member_ids.includes(emp.id)" class="w-4 h-4 text-blue-600 rounded pointer-events-none" />
                <div class="flex-1 min-w-0">
                  <span class="text-sm">{{ emp.name }}</span>
                  <span v-if="emp.department" class="text-xs text-gray-400 ml-1">({{ emp.department }})</span>
                </div>
              </div>
              <div v-if="filteredEmployees.length === 0" class="text-center py-4 text-gray-400 text-sm">
                {{ $t('common.noData') || '暂无数据' }}
              </div>
            </div>
          </div>

          <!-- 提交 -->
          <div class="flex gap-2 justify-end pt-2">
            <button type="button" @click="closeDialog" class="px-4 py-2 border rounded-lg hover:bg-gray-50 text-sm">
              {{ $t('common.cancel') }}
            </button>
            <button type="button" @click="saveRule" :disabled="saving" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm disabled:opacity-50">
              {{ saving ? $t('common.saving') || '保存中...' : $t('oa.saveRule') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../services/api.js'

const { t } = useI18n()

const rules = ref([])
const employees = ref([])
const showDialog = ref(false)
const editingRule = ref(null)
const saving = ref(false)
const employeeSearch = ref('')

const form = ref({
  name: '',
  weekdays: [1, 2, 3, 4, 5],
  start_time: '09:00',
  end_time: '18:00',
  member_ids: []
})

const weekDayLabels = computed(() => [
  t('oa.weekMon'), t('oa.weekTue'), t('oa.weekWed'),
  t('oa.weekThu'), t('oa.weekFri'), t('oa.weekSat'), t('oa.weekSun')
])

const filteredEmployees = computed(() => {
  if (!employeeSearch.value) return employees.value
  const kw = employeeSearch.value.toLowerCase()
  return employees.value.filter(e =>
    e.name?.toLowerCase().includes(kw) || e.department?.toLowerCase().includes(kw)
  )
})

onMounted(() => {
  fetchRules()
  fetchEmployees()
})

async function fetchRules() {
  try {
    const res = await api.get('/oa/attendance-rules')
    if (res.code === 0) rules.value = res.data || []
  } catch (err) {
    console.error('Failed to fetch rules:', err)
  }
}

async function fetchEmployees() {
  try {
    const res = await api.get('/oa/employees', { params: { status: 'active', size: 1000 } })
    if (res.code === 0) {
      employees.value = res.data?.list || res.data || []
    }
  } catch {
    try {
      const res = await api.get('/users/subordinates')
      if (res.code === 0) employees.value = res.data || []
    } catch {
      try {
        const res = await api.get('/users/list')
        if (res.code === 0) employees.value = res.data?.list || res.data || []
      } catch {}
    }
  }
}

function getEmployeeName(uid) {
  const e = employees.value.find(e => e.id === uid)
  return e?.name || uid
}

function openCreate() {
  editingRule.value = null
  form.value = { name: '', weekdays: [1, 2, 3, 4, 5], start_time: '09:00', end_time: '18:00', member_ids: [] }
  employeeSearch.value = ''
  showDialog.value = true
}

function openEdit(rule) {
  editingRule.value = rule
  form.value = {
    name: rule.name,
    weekdays: rule.weekdays ? [...rule.weekdays] : [],
    start_time: rule.start_time || '09:00',
    end_time: rule.end_time || '18:00',
    member_ids: rule.members ? rule.members.map(m => m.user_id) : []
  }
  employeeSearch.value = ''
  showDialog.value = true
}

function closeDialog() {
  showDialog.value = false
  editingRule.value = null
}

function toggleMember(uid) {
  const idx = form.value.member_ids.indexOf(uid)
  if (idx >= 0) {
    form.value.member_ids.splice(idx, 1)
  } else {
    form.value.member_ids.push(uid)
  }
}

function removeMember(uid) {
  form.value.member_ids = form.value.member_ids.filter(id => id !== uid)
}

async function saveRule() {
  if (!form.value.name) { alert(t('oa.ruleName')); return }
  if (!form.value.start_time || !form.value.end_time) { alert(t('oa.shiftTime') || '请设置时间段'); return }
  if (form.value.weekdays.length === 0) { alert(t('oa.workdays')); return }

  saving.value = true
  try {
    const payload = {
      name: form.value.name,
      weekdays: form.value.weekdays,
      start_time: form.value.start_time,
      end_time: form.value.end_time,
      member_ids: form.value.member_ids
    }

    if (editingRule.value) {
      await api.put(`/oa/attendance-rules/${editingRule.value.id}`, payload)
    } else {
      await api.post('/oa/attendance-rules', payload)
    }
    closeDialog()
    fetchRules()
  } catch (err) {
    alert(err.response?.data?.message || t('common.saveFailed') || '保存失败')
  } finally {
    saving.value = false
  }
}

async function toggleStatus(rule) {
  try {
    const newStatus = rule.status === 'active' ? 'inactive' : 'active'
    await api.put(`/oa/attendance-rules/${rule.id}`, { status: newStatus })
    fetchRules()
  } catch (err) {
    alert(err.response?.data?.message || '操作失败')
  }
}

async function deleteRule(rule) {
  if (!confirm(t('oa.confirmDeleteRule'))) return
  try {
    await api.delete(`/oa/attendance-rules/${rule.id}`)
    fetchRules()
  } catch (err) {
    alert(err.response?.data?.message || '删除失败')
  }
}
</script>

<style scoped>
@media (max-width: 768px) {
  .p-6 {
    padding: 12px;
  }

  .flex.justify-between.items-center.mb-6 {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .flex.justify-between.items-center.mb-6 h1 {
    font-size: 18px;
  }

  .grid.grid-cols-1.md\:grid-cols-2.lg\:grid-cols-3 {
    grid-template-columns: 1fr;
  }

  .border.rounded-lg.p-4 {
    padding: 12px;
  }

  .text-lg.font-semibold {
    font-size: 15px;
  }

  .text-sm {
    font-size: 12px;
  }

  button.px-4.py-2 {
    padding: 8px 12px;
    font-size: 13px;
  }

  .sticky.top-0.bg-white.border-b.px-6.py-4 {
    padding: 12px 16px;
  }

  .bg-white.rounded-lg.w-full.max-w-2xl {
    width: 95%;
    max-width: none;
    margin: 0 10px;
  }

  .p-6.space-y-5 {
    padding: 16px;
  }

  .flex.gap-2.justify-end.pt-2 {
    flex-direction: column;
  }

  .flex.gap-2.justify-end.pt-2 button {
    width: 100%;
  }

  .flex.items-center.gap-2 {
    flex-direction: column;
  }

  .flex.items-center.gap-2 input {
    width: 100%;
  }

  .inline-flex.items-center.gap-1\.5.px-4.py-2 {
    padding: 6px 10px;
    font-size: 12px;
  }

  .w-8.h-8 {
    width: 28px;
    height: 28px;
    font-size: 10px;
  }

  .flex.gap-1.mb-3 {
    flex-wrap: wrap;
    gap: 4px;
  }
}
</style>
