<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useUserStore } from '../../stores/user'
import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import ClockInButton from '../../components/ClockInButton.vue'
import api from '../../services/api.js'

const { t, locale } = useI18n()
const router = useRouter()
const userStore = useUserStore()
const activeTab = ref('home')
const dashboardStats = ref({
  pending_approvals: 0,
  my_approvals: 0,
  attendance: { clock_in: null, clock_out: null },
  work_logs: 0
})

const tabs = computed(() => [
  { key: 'home', label: t('oa.center'), icon: 'home' },
  { key: 'attendance', label: t('oa.attendance'), icon: 'schedule' },
  { key: 'leave', label: t('oa.leave'), icon: 'event_busy' },
  // { key: 'logs', label: t('oa.workLog'), icon: 'edit_note' },
])

const quickActions = computed(() => [
  { name: t('dashboard.quickAttendance'), icon: 'schedule', route: '/oa/attendance', color: 'bg-blue-500' },
  { name: t('oa.writeLog'), icon: 'edit_note', route: '/logs/work-logs', color: 'bg-green-500' },
  { name: t('oa.initiateApproval'), icon: 'approval', route: '/oa/approvals', color: 'bg-purple-500' },
  { name: t('oa.directory'), icon: 'contacts', route: '/oa/directory', color: 'bg-orange-500' },
  { name: t('oa.shiftManage'), icon: 'calendar_month', route: '/oa/schedule', color: 'bg-pink-500' },
  { name: t('oa.attendanceSummary'), icon: 'bar_chart', route: '/oa/attendance-summary', color: 'bg-indigo-500' },
  { name: t('oa.attendanceRules'), icon: 'event_available', route: '/oa/attendance-rules', color: 'bg-teal-500' },
])

const statusMap = computed(() => ({
  normal: { type: 'success', text: t('oa.normalAttendance') },
  late: { type: 'warning', text: t('dashboard.late') },
  early: { type: 'warning', text: t('dashboard.earlyLeave') },
  absent: { type: 'danger', text: t('oa.absent') },
}))

const leaveStatusMap = computed(() => ({
  pending: { type: 'warning', text: t('approval.pending') },
  approved: { type: 'success', text: t('approval.passed') },
  rejected: { type: 'danger', text: t('approval.rejected') },
}))

const attendanceRecords = ref([])
const leaveRecords = ref([])

onMounted(async () => {
  loadDashboard()
  try {
    const [att, lv] = await Promise.allSettled([
      api.get('/oa/attendance'),
      api.get('/oa/leave'),
    ])

    if (att.status === 'fulfilled' && att.value.code === 0) {
      attendanceRecords.value = att.value.data?.list || att.value.data || []
    }
    if (lv.status === 'fulfilled' && lv.value.code === 0) {
      leaveRecords.value = lv.value.data?.list || lv.value.data || []
    }
  } catch (err) {
    console.error('Failed to load OA data:', err)
  }
})

async function loadDashboard() {
  try {
    const res = await api.get('/oa/dashboard', { params: { user_id: userStore.userId } })
    if (res.code === 0) {
      dashboardStats.value = res.data
    }
  } catch (err) {
    console.error('Failed to load dashboard:', err)
  }
}

function navigateTo(route) {
  router.push(route)
}

function handleClockSuccess() {
  alert(t('oa.clockSuccess'))
  loadDashboard()
}

const todayRecords = computed(() => attendanceRecords.value.filter(r => r.date === '2026-02-20'))

const attendanceStats = computed(() => ({
  total: todayRecords.value.length,
  normal: todayRecords.value.filter(r => r.status === 'normal').length,
  abnormal: todayRecords.value.filter(r => r.status !== 'normal').length,
  absent: todayRecords.value.filter(r => r.status === 'absent').length,
}))

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  const lang = locale.value || 'zh'
  if (lang.startsWith('zh')) {
    return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日`
  }
  const months = ['January','February','March','April','May','June','July','August','September','October','November','December']
  return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`
}
</script>

<template>
  <div>
    <PageHeader :title="$t('oa.title')" :subtitle="$t('oa.subtitle')" />

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
        <div class="flex items-center gap-3">
          <div class="size-10 rounded-lg bg-primary/10 flex items-center justify-center">
            <span class="material-symbols-outlined text-primary">group</span>
          </div>
          <div>
            <p class="text-2xl font-bold text-text-primary">{{ attendanceStats.total }}</p>
            <p class="text-xs text-text-secondary">{{ $t('oa.todayAttendance') }}</p>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
        <div class="flex items-center gap-3">
          <div class="size-10 rounded-lg bg-success/10 flex items-center justify-center">
            <span class="material-symbols-outlined text-success">check_circle</span>
          </div>
          <div>
            <p class="text-2xl font-bold text-success">{{ attendanceStats.normal }}</p>
            <p class="text-xs text-text-secondary">{{ $t('oa.normalAttendance') }}</p>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
        <div class="flex items-center gap-3">
          <div class="size-10 rounded-lg bg-warning/10 flex items-center justify-center">
            <span class="material-symbols-outlined text-warning">warning</span>
          </div>
          <div>
            <p class="text-2xl font-bold text-warning">{{ attendanceStats.abnormal }}</p>
            <p class="text-xs text-text-secondary">{{ $t('oa.abnormal') }}</p>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
        <div class="flex items-center gap-3">
          <div class="size-10 rounded-lg bg-danger/10 flex items-center justify-center">
            <span class="material-symbols-outlined text-danger">cancel</span>
          </div>
          <div>
            <p class="text-2xl font-bold text-danger">{{ attendanceStats.absent }}</p>
            <p class="text-xs text-text-secondary">{{ $t('oa.absent') }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <div class="flex border-b border-gray-100 overflow-x-auto">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          :class="[
            'flex items-center gap-2 px-6 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap',
            activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-text-secondary hover:text-text-primary'
          ]"
        >
          <span class="material-symbols-outlined text-[18px]">{{ tab.icon }}</span>
          {{ tab.label }}
        </button>
      </div>

      <!-- Home Dashboard -->
      <div v-if="activeTab === 'home'" class="p-6">
        <!-- Quick Clock In -->
        <div class="mb-6 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-6 text-white">
          <h3 class="text-lg font-semibold mb-4">{{ $t('oa.todayClock') }}</h3>
          <div class="flex items-center gap-4 mb-4">
            <div class="flex-1">
              <p class="text-sm opacity-90">{{ $t('oa.clockInLabel') }}: {{ dashboardStats.attendance.clock_in || $t('oa.notClocked') }}</p>
              <p class="text-sm opacity-90">{{ $t('oa.clockOutLabel') }}: {{ dashboardStats.attendance.clock_out || $t('oa.notClocked') }}</p>
            </div>
            <ClockInButton
              :type="dashboardStats.attendance.clock_in ? 'out' : 'in'"
              :disabled="dashboardStats.attendance.clock_in && dashboardStats.attendance.clock_out"
              @success="handleClockSuccess"
            />
          </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-2xl font-bold text-orange-600">{{ dashboardStats.pending_approvals }}</p>
                <p class="text-sm text-gray-600">{{ $t('oa.pendingMyApproval') }}</p>
              </div>
              <span class="material-symbols-outlined text-4xl text-orange-400">pending_actions</span>
            </div>
          </div>
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-2xl font-bold text-blue-600">{{ dashboardStats.my_approvals }}</p>
                <p class="text-sm text-gray-600">{{ $t('oa.myApplications') }}</p>
              </div>
              <span class="material-symbols-outlined text-4xl text-blue-400">description</span>
            </div>
          </div>
          <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-2xl font-bold text-green-600">{{ dashboardStats.work_logs }}</p>
                <p class="text-sm text-gray-600">{{ $t('oa.monthlyLogs') }}</p>
              </div>
              <span class="material-symbols-outlined text-4xl text-green-400">edit_note</span>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div>
          <h3 class="font-semibold mb-3">{{ $t('oa.quickEntry') }}</h3>
          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <button
              v-for="action in quickActions"
              :key="action.name"
              @click="navigateTo(action.route)"
              class="flex flex-col items-center gap-2 p-4 bg-white border rounded-lg hover:shadow-md transition-all"
            >
              <div :class="[action.color, 'w-12 h-12 rounded-full flex items-center justify-center text-white']">
                <span class="material-symbols-outlined">{{ action.icon }}</span>
              </div>
              <span class="text-sm font-medium">{{ action.name }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Attendance -->
      <div v-if="activeTab === 'attendance'" class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">{{ $t('common.name') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('inout.date') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('oa.clockIn') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('oa.clockOut') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('oa.location') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('common.status') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('common.action') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="r in attendanceRecords" :key="r.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 font-medium text-text-primary">{{ r.user }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ r.date }}</td>
              <td class="px-4 py-3" :class="r.clock_in ? (r.status === 'late' ? 'text-warning font-medium' : 'text-text-primary') : 'text-danger'">{{ r.clock_in || $t('oa.notClocked') }}</td>
              <td class="px-4 py-3" :class="r.clock_out ? (r.status === 'early' ? 'text-warning font-medium' : 'text-text-primary') : 'text-danger'">{{ r.clock_out || $t('oa.notClocked') }}</td>
              <td class="px-4 py-3 text-text-secondary text-xs">{{ r.location || '-' }}</td>
              <td class="px-4 py-3 text-center"><StatusTag :type="statusMap[r.status].type" :text="statusMap[r.status].text" /></td>
              <td class="px-4 py-3 text-right">
                <button v-if="r.status !== 'normal'" class="text-primary hover:text-primary-hover text-xs font-medium">{{ $t('oa.makeUpRequest') }}</button>
                <span v-else class="text-text-secondary text-xs">-</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Leave -->
      <div v-if="activeTab === 'leave'" class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="font-bold text-text-primary">{{ $t('oa.leaveRecords') }}</h3>
          <button class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">add</span>
            {{ $t('oa.submitRequest') }}
          </button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
              <tr>
                <th class="px-4 py-3 font-medium">{{ $t('common.name') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('oa.type') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('oa.startDate') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('oa.endDate') }}</th>
                <th class="px-4 py-3 font-medium text-center">{{ $t('oa.days') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('oa.reason') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('oa.approver') }}</th>
                <th class="px-4 py-3 font-medium text-center">{{ $t('common.status') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="l in leaveRecords" :key="l.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 font-medium text-text-primary">{{ l.user }}</td>
                <td class="px-4 py-3"><StatusTag type="primary" :text="l.type" /></td>
                <td class="px-4 py-3 text-text-secondary">{{ l.start_date }}</td>
                <td class="px-4 py-3 text-text-secondary">{{ l.end_date }}</td>
                <td class="px-4 py-3 text-center font-medium">{{ l.days }}</td>
                <td class="px-4 py-3 text-text-secondary text-xs">{{ l.reason }}</td>
                <td class="px-4 py-3 text-text-primary">{{ l.approver }}</td>
                <td class="px-4 py-3 text-center"><StatusTag :type="leaveStatusMap[l.status].type" :text="leaveStatusMap[l.status].text" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
