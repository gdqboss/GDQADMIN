<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">{{ $t('oa.attendanceSummary') }}</h1>
      <button @click="exportData" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
        {{ $t('common.export') }}
      </button>
    </div>

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">{{ $t('common.startDate') }}</label>
        <input v-model="filters.start_date" type="date" class="w-full border rounded px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">{{ $t('common.endDate') }}</label>
        <input v-model="filters.end_date" type="date" class="w-full border rounded px-3 py-2" />
      </div>
      <div v-if="userStore.canAccess('attendance:manage')">
        <label class="block text-sm font-medium mb-1">{{ $t('common.department') }}</label>
        <select v-model="filters.department" class="w-full border rounded px-3 py-2">
          <option value="">{{ $t('common.all') }}</option>
          <option v-for="dept in departments" :key="dept" :value="dept">{{ dept }}</option>
        </select>
      </div>
      <div class="flex items-end">
        <button @click="fetchSummary" class="w-full px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
          {{ $t('common.search') }}
        </button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="text-sm text-blue-600 mb-1">{{ $t('oa.totalEmployees') }}</div>
        <div class="text-2xl font-bold text-blue-700">{{ summary.length }}</div>
      </div>
      <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="text-sm text-green-600 mb-1">{{ $t('oa.avgAttendanceRate') }}</div>
        <div class="text-2xl font-bold text-green-700">{{ avgAttendanceRate }}%</div>
      </div>
      <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
        <div class="text-sm text-orange-600 mb-1">{{ $t('oa.totalLate') }}</div>
        <div class="text-2xl font-bold text-orange-700">{{ totalLate }}</div>
      </div>
      <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
        <div class="text-sm text-purple-600 mb-1">{{ $t('oa.totalOvertime') }}</div>
        <div class="text-2xl font-bold text-purple-700">{{ totalOvertime }}h</div>
      </div>
    </div>

    <!-- Summary Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-semibold">{{ $t('common.employee') }}</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">{{ $t('common.department') }}</th>
            <th class="px-4 py-3 text-center text-sm font-semibold">{{ $t('oa.totalDays') }}</th>
            <th class="px-4 py-3 text-center text-sm font-semibold">{{ $t('oa.normalDays') }}</th>
            <th class="px-4 py-3 text-center text-sm font-semibold">{{ $t('oa.lateDays') }}</th>
            <th class="px-4 py-3 text-center text-sm font-semibold">{{ $t('oa.earlyDays') }}</th>
            <th class="px-4 py-3 text-center text-sm font-semibold">{{ $t('oa.absentDays') }}</th>
            <th class="px-4 py-3 text-center text-sm font-semibold">{{ $t('oa.overtimeHours') }}</th>
            <th class="px-4 py-3 text-center text-sm font-semibold">{{ $t('oa.attendanceRate') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="item in summary" :key="item.user_id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-sm">{{ item.user_name }}</td>
            <td class="px-4 py-3 text-sm">{{ item.department }}</td>
            <td class="px-4 py-3 text-sm text-center">{{ item.total_days }}</td>
            <td class="px-4 py-3 text-sm text-center">
              <span class="px-2 py-1 bg-green-100 text-green-800 rounded">{{ item.normal_days }}</span>
            </td>
            <td class="px-4 py-3 text-sm text-center">
              <span v-if="item.late_days > 0" class="px-2 py-1 bg-orange-100 text-orange-800 rounded">
                {{ item.late_days }}
              </span>
              <span v-else class="text-gray-400">0</span>
            </td>
            <td class="px-4 py-3 text-sm text-center">
              <span v-if="item.early_days > 0" class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded">
                {{ item.early_days }}
              </span>
              <span v-else class="text-gray-400">0</span>
            </td>
            <td class="px-4 py-3 text-sm text-center">
              <span v-if="item.absent_days > 0" class="px-2 py-1 bg-red-100 text-red-800 rounded">
                {{ item.absent_days }}
              </span>
              <span v-else class="text-gray-400">0</span>
            </td>
            <td class="px-4 py-3 text-sm text-center">{{ item.total_overtime || 0 }}h</td>
            <td class="px-4 py-3 text-sm text-center">
              <span class="font-semibold"
                    :class="{
                      'text-green-600': item.attendance_rate >= 95,
                      'text-orange-600': item.attendance_rate >= 80 && item.attendance_rate < 95,
                      'text-red-600': item.attendance_rate < 80
                    }">
                {{ item.attendance_rate }}%
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../stores/user'
import api from '../../services/api.js'

const { t } = useI18n()
const userStore = useUserStore()
const summary = ref([])
const departments = ref([])
const filters = ref({
  start_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
  end_date: new Date().toISOString().slice(0, 10),
  department: ''
})

const avgAttendanceRate = computed(() => {
  if (summary.value.length === 0) return '0.00'
  const total = summary.value.reduce((sum, item) => sum + parseFloat(item.attendance_rate), 0)
  return (total / summary.value.length).toFixed(2)
})

const totalLate = computed(() => {
  return summary.value.reduce((sum, item) => sum + item.late_days, 0)
})

const totalOvertime = computed(() => {
  return summary.value.reduce((sum, item) => sum + (item.total_overtime || 0), 0).toFixed(2)
})

onMounted(() => {
  fetchDepartments()
  fetchSummary()
})

async function fetchDepartments() {
  try {
    const res = await api.get('/oa/employees', { params: { size: 1000 } })
    if (res.code === 0) {
      departments.value = [...new Set(res.data.list.map(e => e.department).filter(Boolean))]
    }
  } catch (err) {
    console.error('Failed to fetch departments:', err)
  }
}

async function fetchSummary() {
  try {
    const params = { ...filters.value }

    // If not admin, only show current user's records
    if (!userStore.canAccess('attendance:manage')) {
      params.user_id = userStore.user?.id
    }

    const res = await api.get('/oa/attendance/summary', { params })
    if (res.code === 0) {
      summary.value = res.data
    }
  } catch (err) {
    console.error('Failed to fetch summary:', err)
  }
}

function exportData() {
  // Simple CSV export
  const headers = [t('oa.exportEmployee'), t('oa.exportDepartment'), t('oa.exportTotalDays'), t('oa.exportNormal'), t('oa.exportLate'), t('oa.exportEarly'), t('oa.exportAbsent'), t('oa.exportOvertimeH'), t('oa.exportAttendanceRate')]
  const rows = summary.value.map(item => [
    item.user_name,
    item.department,
    item.total_days,
    item.normal_days,
    item.late_days,
    item.early_days,
    item.absent_days,
    item.total_overtime || 0,
    item.attendance_rate + '%'
  ])

  const csv = [headers, ...rows].map(row => row.join(',')).join('\n')
  const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `attendance_summary_${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
}
</script>

<style scoped>
/* 基础样式 */
.p-6 {
  padding: 1.5rem;
}

.text-2xl {
  font-size: 1.5rem;
}

.mb-6 {
  margin-bottom: 1.5rem;
}

.gap-4 {
  gap: 1rem;
}

.bg-white.rounded-lg.shadow.overflow-hidden {
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}

table {
  border-collapse: collapse;
}

th, td {
  padding: 0.75rem 1rem;
}

/* 手机适配 */
@media (max-width: 768px) {
  .p-6 {
    padding: 1rem;
  }

  .text-2xl {
    font-size: 1.25rem;
  }

  .flex.justify-between.items-center.mb-6 {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }

  .flex.justify-between.items-center.mb-6 button {
    width: 100%;
  }

  .mb-6.grid.grid-cols-1.md\:grid-cols-4 {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }

  .grid.grid-cols-1.md\:grid-cols-4.gap-4.mb-6 {
    grid-template-columns: 1fr;
  }

  .bg-blue-50, .bg-green-50, .bg-orange-50, .bg-purple-50 {
    padding: 0.75rem;
  }

  .text-2xl {
    font-size: 1.125rem;
  }

  .bg-white.rounded-lg.shadow.overflow-hidden {
    overflow-x: auto;
  }

  table {
    font-size: 0.75rem;
    min-width: 600px;
  }

  th, td {
    padding: 0.5rem;
  }

  .px-4.py-3 {
    padding: 0.5rem;
  }

  .px-2.py-1 {
    padding: 0.125rem 0.375rem;
    font-size: 0.7rem;
  }

  button {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
  }
}
</style>
