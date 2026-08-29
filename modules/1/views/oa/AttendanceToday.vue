<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">{{ $t('oa.todayAllAttendance') }}</h1>
      <div class="flex gap-2">
        <button @click="exportData" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
          {{ $t('common.export') }}
        </button>
        <button @click="fetchAll" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
          {{ $t('common.refresh') }}
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-5 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">{{ $t('oa.scheduleDate') }}</label>
        <input v-model="filters.date" type="date" class="w-full border rounded px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">{{ $t('common.department') }}</label>
        <select v-model="filters.department" class="w-full border rounded px-3 py-2">
          <option value="">{{ $t('common.all') }}</option>
          <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">{{ $t('oa.workerCategory') }}</label>
        <select v-model="filters.worker_category" class="w-full border rounded px-3 py-2">
          <option value="">{{ $t('common.all') }}</option>
          <option value="engineering">{{ workerCategoryLabel('engineering') }}</option>
          <option value="office">{{ workerCategoryLabel('office') }}</option>
          <option value="both">{{ workerCategoryLabel('both') }}</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">{{ $t('common.status') }}</label>
        <select v-model="filters.status" class="w-full border rounded px-3 py-2">
          <option value="">{{ $t('common.all') }}</option>
          <option value="normal">{{ statusLabel('normal', false) }}</option>
          <option value="late">{{ statusLabel('late', false) }}</option>
          <option value="early">{{ statusLabel('early', false) }}</option>
          <option value="absent">{{ statusLabel('absent', false) }}</option>
          <option value="leave">{{ statusLabel('leave', false) }}</option>
          <option value="silent">{{ $t('oa.silentMode') }}</option>
        </select>
      </div>
      <div class="flex items-end">
        <button @click="fetchAll" class="w-full px-4 py-2 bg-gray-700 text-white rounded hover:bg-gray-800">
          {{ $t('common.search') }}
        </button>
      </div>
    </div>

    <!-- 4 Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="text-sm text-blue-600 mb-1">{{ $t('oa.todayShouldAttend') }}</div>
        <div class="text-3xl font-bold text-blue-700">{{ stats.should_attend || 0 }}</div>
      </div>
      <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="text-sm text-green-600 mb-1">{{ $t('oa.todayAttended') }}</div>
        <div class="text-3xl font-bold text-green-700">{{ stats.checked_in || 0 }}</div>
        <div class="text-xs text-green-600 mt-1">{{ attendanceRate }}%</div>
      </div>
      <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
        <div class="text-sm text-orange-600 mb-1">{{ $t('oa.todayLateOrEarly') }}</div>
        <div class="text-3xl font-bold text-orange-700">{{ Number(stats.late_count||0) + Number(stats.early_leave_count||0) }}</div>
        <div class="text-xs text-orange-600 mt-1">
          {{ $t('oa.lateLabel') }} {{ stats.late_count || 0 }} · {{ $t('oa.earlyLabel') }} {{ stats.early_leave_count || 0 }}
        </div>
      </div>
      <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="text-sm text-red-600 mb-1">{{ $t('oa.todayAbsent') }}</div>
        <div class="text-3xl font-bold text-red-700">{{ stats.absent_count || 0 }}</div>
      </div>
    </div>

    <!-- Detail Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-semibold">{{ $t('common.employee') }}</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">{{ $t('common.department') }}</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">{{ $t('oa.workerCategory') }}</th>
            <th class="px-4 py-3 text-center text-sm font-semibold">{{ $t('oa.scheduledIn') }}</th>
            <th class="px-4 py-3 text-center text-sm font-semibold">{{ $t('oa.clockInText') }}</th>
            <th class="px-4 py-3 text-center text-sm font-semibold">{{ $t('oa.scheduledOut') }}</th>
            <th class="px-4 py-3 text-center text-sm font-semibold">{{ $t('oa.clockOutText') }}</th>
            <th class="px-4 py-3 text-center text-sm font-semibold">{{ $t('common.status') }}</th>
            <th class="px-4 py-3 text-center text-sm font-semibold">{{ $t('oa.lateMinutes') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="r in filteredList" :key="r.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-sm">{{ r.user_name }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ r.department || '-' }}</td>
            <td class="px-4 py-3 text-sm">
              <span class="px-2 py-0.5 rounded text-xs" :class="workerCategoryClass(r.worker_category)">
                {{ workerCategoryLabel(r.worker_category) }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-center text-gray-500">{{ r.scheduled_in || '-' }}</td>
            <td class="px-4 py-3 text-sm text-center font-medium">{{ r.clock_in || '-' }}</td>
            <td class="px-4 py-3 text-sm text-center text-gray-500">{{ r.scheduled_out || '-' }}</td>
            <td class="px-4 py-3 text-sm text-center font-medium">{{ r.clock_out || '-' }}</td>
            <td class="px-4 py-3 text-sm text-center">
              <span class="px-2 py-1 rounded text-xs" :class="statusClass(r.status, r.silent)">
                {{ statusLabel(r.status, r.silent) }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-center">
              <span v-if="r.late_minutes > 0" class="text-orange-600">+{{ r.late_minutes }}m</span>
              <span v-else-if="r.early_minutes > 0" class="text-yellow-600">-{{ r.early_minutes }}m</span>
              <span v-else class="text-gray-300">-</span>
            </td>
          </tr>
          <tr v-if="filteredList.length === 0">
            <td colspan="9" class="px-4 py-6 text-center text-gray-400">
              {{ $t('oa.todayNoRecords') }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="filteredList.length > 0" class="mt-3 text-xs text-gray-500 text-right">
      {{ $t('oa.todayRecordsCount', { count: filteredList.length }) }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../services/api.js'

const { t } = useI18n()

const todayList = ref([])
const stats = ref({})
const departments = ref([])

const filters = ref({
  date: new Date().toISOString().slice(0, 10),
  department: '',
  worker_category: '',
  status: ''
})

// 工种显示 + badge 颜色
function workerCategoryLabel(c) {
  return { engineering: '工程', office: '办公', both: '两者' }[c] || '办公'
}
function workerCategoryClass(c) {
  return {
    engineering: 'bg-purple-100 text-purple-700',
    office: 'bg-blue-100 text-blue-700',
    both: 'bg-gray-100 text-gray-700'
  }[c] || 'bg-gray-100 text-gray-700'
}

// 状态映射
function statusClass(status, silent) {
  if (silent) return 'bg-gray-100 text-gray-500'
  return {
    normal: 'bg-green-100 text-green-700',
    late: 'bg-orange-100 text-orange-700',
    early: 'bg-yellow-100 text-yellow-700',
    absent: 'bg-red-100 text-red-700',
    leave: 'bg-blue-100 text-blue-700'
  }[status] || 'bg-gray-100 text-gray-600'
}
function statusLabel(status, silent) {
  if (silent) return t('oa.silentMode')
  return {
    normal: '正常',
    late: '迟到',
    early: '早退',
    absent: '缺勤',
    leave: '请假'
  }[status] || '-'
}

// 前端过滤 (server 已返回该日全部,client 再筛部门/工种/状态)
const filteredList = computed(() => {
  return todayList.value.filter(r => {
    if (filters.value.department && r.department !== filters.value.department) return false
    if (filters.value.worker_category && r.worker_category !== filters.value.worker_category) return false
    if (filters.value.status) {
      if (filters.value.status === 'silent') {
        if (!r.silent) return false
      } else if (r.status !== filters.value.status || r.silent) return false
    }
    return true
  })
})

const attendanceRate = computed(() => {
  const should = Number(stats.value.should_attend || 0)
  if (!should) return '0.00'
  return ((Number(stats.value.checked_in || 0) / should) * 100).toFixed(2)
})

onMounted(() => {
  fetchDepartments()
  fetchAll()
})

async function fetchDepartments() {
  try {
    const res = await api.get('/oa/employees', { params: { size: 1000 } })
    if (res.code === 0) {
      departments.value = [...new Set((res.data.list || []).map(e => e.department).filter(Boolean))]
    }
  } catch (err) {
    console.error('fetchDepartments:', err)
  }
}

async function fetchAll() {
  await Promise.all([fetchList(), fetchStats()])
}

async function fetchList() {
  try {
    const res = await api.get('/oa/attendance', {
      params: { date: filters.value.date, size: 500, page: 1 }
    })
    if (res.code === 0) {
      todayList.value = (res.data.list || []).map(r => ({
        ...r,
        silent: r.silent === true || (r.abnormal_reason && r.abnormal_reason.startsWith('non-required'))
      }))
    }
  } catch (err) {
    console.error('fetchList:', err)
  }
}

async function fetchStats() {
  try {
    // 后端 today-summary 永远是今天(写死今天),日期筛选只影响 list,不重算 stats
    const res = await api.get('/oa/attendance/today-summary')
    if (res.code === 0) {
      stats.value = res.data || {}
    }
  } catch (err) {
    console.error('fetchStats:', err)
  }
}

function exportData() {
  const headers = [
    t('oa.exportEmployee'), t('oa.exportDepartment'), t('oa.workerCategory'),
    t('oa.scheduledIn'), t('oa.clockInText'), t('oa.scheduledOut'), t('oa.clockOutText'),
    t('common.status'), t('oa.lateMinutes'), t('oa.earlyMinutes'), t('oa.overtimeHours')
  ]
  const rows = filteredList.value.map(r => [
    r.user_name || '',
    r.department || '',
    workerCategoryLabel(r.worker_category),
    r.scheduled_in || '',
    r.clock_in || '',
    r.scheduled_out || '',
    r.clock_out || '',
    statusLabel(r.status, r.silent),
    r.late_minutes || 0,
    r.early_minutes || 0,
    r.overtime_hours || 0
  ])
  const csv = [headers, ...rows].map(row => row.join(',')).join('\n')
  const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `attendance_today_${filters.value.date}.csv`
  link.click()
}
</script>

<style scoped>
.p-6 { padding: 1.5rem; }
.mb-6 { margin-bottom: 1.5rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-3 { margin-bottom: 0.75rem; }
.mb-1 { margin-bottom: 0.25rem; }
.mt-3 { margin-top: 0.75rem; }
.mt-1 { margin-top: 0.25rem; }
.gap-4 { gap: 1rem; }
.gap-2 { gap: 0.5rem; }

.bg-white.rounded-lg.shadow.overflow-hidden {
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}

table { border-collapse: collapse; }
th, td { padding: 0.75rem 1rem; }

/* 手机适配 */
@media (max-width: 768px) {
  .p-6 { padding: 1rem; }
  .text-2xl { font-size: 1.25rem; }
  .flex.justify-between.items-center.mb-6 { flex-direction: column; align-items: flex-start; gap: 1rem; }
  .flex.justify-between.items-center.mb-6 button { width: 100%; }
  .mb-6.grid.grid-cols-1.md\:grid-cols-5 { grid-template-columns: 1fr; gap: 0.75rem; }
  .grid.grid-cols-2.md:grid-cols-4.gap-4.mb-6 { grid-template-columns: 1fr; }
  .bg-blue-50, .bg-green-50, .bg-orange-50, .bg-red-50 { padding: 0.75rem; }
  .text-3xl { font-size: 1.5rem; }
  .bg-white.rounded-lg.shadow.overflow-hidden { overflow-x: auto; }
  table { font-size: 0.75rem; min-width: 720px; }
  th, td { padding: 0.5rem; }
  .px-4.py-3 { padding: 0.5rem; }
  .px-2.py-1 { padding: 0.125rem 0.375rem; font-size: 0.7rem; }
  button { padding: 0.5rem 1rem; font-size: 0.875rem; }
}
</style>