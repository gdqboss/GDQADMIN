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
  { name: t('oa.writeLog'), icon: 'edit_note', route: '/oa/work-logs', color: 'bg-green-500' },
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
const workLogs = ref([])

onMounted(async () => {
  loadDashboard()
  try {
    const [att, lv, logs] = await Promise.allSettled([
      api.get('/oa/attendance'),
      api.get('/oa/leave'),
      api.get('/oa/work-logs'),
    ])

    if (att.status === 'fulfilled' && att.value.code === 0) {
      attendanceRecords.value = att.value.data?.list || att.value.data || []
    }
    if (lv.status === 'fulfilled' && lv.value.code === 0) {
      leaveRecords.value = lv.value.data?.list || lv.value.data || []
    }
    if (logs.status === 'fulfilled' && logs.value.code === 0) {
      workLogs.value = logs.value.data?.list || logs.value.data || []
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

const selectedLogUser = ref('')
const filteredLogs = computed(() => {
  if (!selectedLogUser.value) return workLogs.value
  return workLogs.value.filter(l => l.user === selectedLogUser.value)
})
const logUsers = computed(() => [...new Set(workLogs.value.map(l => l.user))])

// ─── 写日志（内嵌弹窗） ───
const showWriteLog = ref(false)
const submitting = ref(false)
const templates = ref([])
const selectedTemplateId = ref(null)
const formData = ref({
  date: new Date().toISOString().split('T')[0],
  participants: [],
  recipients: []
})
const formContent = ref({})
const showParticipantModal = ref(false)
const availableUsers = ref([])
const tempParticipants = ref([])

const currentFields = computed(() => {
  if (!selectedTemplateId.value) return []
  const tpl = templates.value.find(t => t.id === selectedTemplateId.value)
  if (!tpl || !tpl.fields) return []
  try {
    const fields = typeof tpl.fields === 'string' ? JSON.parse(tpl.fields) : tpl.fields
    return Array.isArray(fields) ? fields : []
  } catch {
    return []
  }
})

async function loadTemplates() {
  try {
    const res = await api.get('/oa/work-log-templates')
    if (res.code === 0) templates.value = res.data || []
  } catch (err) {
    console.error('Failed to load templates:', err)
  }
}

async function loadAvailableUsers() {
  try {
    const res = await api.get('/users/subordinates')
    if (res.code === 0) availableUsers.value = res.data || []
  } catch {
    try {
      const res = await api.get('/users/list')
      if (res.code === 0) availableUsers.value = res.data || []
    } catch {}
  }
}

function openWriteLog() {
  selectedTemplateId.value = null
  formContent.value = {}
  formData.value = {
    date: new Date().toISOString().split('T')[0],
    participants: [userStore.userId],
    recipients: []
  }
  showWriteLog.value = true
}

function onTemplateChange() {
  formContent.value = {}
  for (const field of currentFields.value) {
    if (field.type === 'checkbox' || field.type === 'image') {
      formContent.value[field.name] = []
    }
  }
}

async function submitLog() {
  if (!formData.value.date) {
    alert(t('oa.dateLabel') || '请选择日期')
    return
  }
  if (currentFields.value.length > 0) {
    for (const field of currentFields.value) {
      if (field.required && !formContent.value[field.name]) {
        alert(`请填写「${field.label}」`)
        return
      }
    }
  } else {
    if (!formContent.value.title) {
      alert(t('oa.logTitleLabel') || '请输入标题')
      return
    }
  }
  submitting.value = true
  try {
    const payload = {
      template_id: selectedTemplateId.value,
      date: formData.value.date,
      content: formContent.value,
      participants: formData.value.participants,
      recipients: formData.value.recipients
    }
    const res = await api.post('/oa/work-logs', payload)
    if (res.code === 0) {
      showWriteLog.value = false
      // reload work logs
      try {
        const logsRes = await api.get('/oa/work-logs')
        if (logsRes.code === 0) workLogs.value = logsRes.data?.list || logsRes.data || []
      } catch {}
    } else {
      alert(res.message || t('oa.submitFailed') || '提交失败')
    }
  } catch (err) {
    alert(err.message || t('oa.submitFailed') || '提交失败')
  } finally {
    submitting.value = false
  }
}

function openParticipantPicker() {
  const existing = formData.value.participants || []
  tempParticipants.value = existing.length === 0 && userStore.userId ? [userStore.userId] : [...existing]
  showParticipantModal.value = true
}

function confirmParticipants() {
  formData.value.participants = [...tempParticipants.value]
  showParticipantModal.value = false
}

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

async function handleImageUpload(event, fieldName) {
  const files = Array.from(event.target.files)
  if (!files.length) return
  if (!formContent.value[fieldName]) formContent.value[fieldName] = []
  for (const file of files) {
    const fd = new FormData()
    fd.append('file', file)
    try {
      const res = await api.post('/upload', fd)
      if (res.code === 0) {
        formContent.value[fieldName].push(res.data.url)
      }
    } catch {}
  }
  event.target.value = ''
}

function removeImage(fieldName, index) {
  formContent.value[fieldName].splice(index, 1)
}

onMounted(() => {
  loadTemplates()
  loadAvailableUsers()
})
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

      <!-- Work Logs -->
      <div v-if="activeTab === 'logs'" class="p-6">
        <div class="flex justify-between items-center mb-4">
          <div class="flex items-center gap-3">
            <h3 class="font-bold text-text-primary">{{ $t('oa.workLogs') }}</h3>
            <select v-model="selectedLogUser" class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option value="">{{ $t('oa.allMembers') }}</option>
              <option v-for="u in logUsers" :key="u" :value="u">{{ u }}</option>
            </select>
          </div>
          <button @click="openWriteLog" class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">edit_note</span>
            {{ $t('oa.writeLog') }}
          </button>
        </div>
        <div class="space-y-4">
          <div v-if="filteredLogs.length === 0" class="text-center py-16 text-gray-400">
            <span class="material-symbols-outlined text-[48px] mb-2">edit_note</span>
            <p>{{ $t('oa.noLogs') || '暂无工作日志' }}</p>
          </div>
          <div v-for="log in filteredLogs" :key="log.id" class="border border-gray-100 rounded-lg p-4 hover:shadow-card-hover transition-shadow">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-2">
                <div class="size-8 rounded-full bg-primary flex items-center justify-center text-white text-xs font-bold">{{ log.user.charAt(0) }}</div>
                <span class="font-medium text-text-primary text-sm">{{ log.user }}</span>
                <span class="text-xs text-text-secondary">{{ formatDate(log.date) }}</span>
              </div>
              <span class="text-xs text-text-secondary">{{ $t('oa.reportTo') }}: {{ log.report_to }}</span>
            </div>
            <div class="space-y-3 text-sm">
              <div>
                <p class="text-xs font-medium text-primary mb-1">{{ $t('oa.todayCompleted') }}</p>
                <p class="text-text-primary whitespace-pre-line leading-relaxed">{{ log.today_work }}</p>
              </div>
              <div>
                <p class="text-xs font-medium text-success mb-1">{{ $t('oa.tomorrowPlan') }}</p>
                <p class="text-text-primary whitespace-pre-line leading-relaxed">{{ log.tomorrow_plan }}</p>
              </div>
              <div v-if="log.issues">
                <p class="text-xs font-medium text-warning mb-1">{{ $t('oa.issues') }}</p>
                <p class="text-text-primary whitespace-pre-line leading-relaxed">{{ log.issues }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 写日志弹窗 -->
    <div v-if="showWriteLog" class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center" @click.self="showWriteLog = false">
      <div class="bg-white rounded-t-2xl sm:rounded-xl w-full sm:max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-4 py-3 flex justify-between items-center z-10">
          <h3 class="font-bold">{{ $t('oa.writeLog') }}</h3>
          <button type="button" @click="showWriteLog = false" class="text-gray-400">✕</button>
        </div>
        <div class="p-4 space-y-4">
          <!-- 模板选择 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('oa.selectTemplate') || '选择模板' }}</label>
            <select v-model="selectedTemplateId" @change="onTemplateChange" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
              <option :value="null">{{ $t('oa.noTemplate') || '不使用模板' }}</option>
              <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id">{{ tpl.name }}</option>
            </select>
          </div>

          <!-- 日期 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('oa.dateLabel') || '日期' }} <span class="text-red-500">*</span></label>
            <div class="relative">
              <div @click="$refs.dateInput.showPicker?.()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm cursor-pointer flex justify-between items-center">
                <span>{{ formatDate(formData.date) || $t('oa.dateLabel') }}</span>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
              </div>
              <input ref="dateInput" v-model="formData.date" type="date" class="absolute inset-0 opacity-0 cursor-pointer" />
            </div>
          </div>

          <!-- 动态模板字段 -->
          <template v-if="currentFields.length > 0">
            <div v-for="field in currentFields" :key="field.name">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ field.label }}
                <span v-if="field.required" class="text-red-500">*</span>
              </label>
              <input v-if="field.type === 'text'" v-model="formContent[field.name]" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
              <input v-else-if="field.type === 'number'" v-model.number="formContent[field.name]" type="number" :min="field.min" :max="field.max" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
              <textarea v-else-if="field.type === 'textarea'" v-model="formContent[field.name]" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none"></textarea>
              <input v-else-if="field.type === 'date'" v-model="formContent[field.name]" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
              <input v-else-if="field.type === 'time'" v-model="formContent[field.name]" type="time" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
              <div v-else-if="field.type === 'time_range'" class="flex gap-2 items-center">
                <input v-model="formContent[field.name + '_start']" type="time" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                <span class="text-gray-400">~</span>
                <input v-model="formContent[field.name + '_end']" type="time" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm" />
              </div>
              <select v-else-if="field.type === 'select'" v-model="formContent[field.name]" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">请选择</option>
                <option v-for="opt in (field.options || [])" :key="opt" :value="opt">{{ opt }}</option>
              </select>
              <div v-else-if="field.type === 'radio'" class="flex flex-wrap gap-3">
                <label v-for="opt in (field.options || [])" :key="opt" class="flex items-center gap-1 text-sm">
                  <input type="radio" v-model="formContent[field.name]" :value="opt" class="w-4 h-4" />
                  {{ opt }}
                </label>
              </div>
              <div v-else-if="field.type === 'checkbox'" class="flex flex-wrap gap-3">
                <label v-for="opt in (field.options || [])" :key="opt" class="flex items-center gap-1 text-sm">
                  <input type="checkbox" :value="opt" v-model="formContent[field.name]" class="w-4 h-4" />
                  {{ opt }}
                </label>
              </div>
              <div v-else-if="field.type === 'image'">
                <div class="grid grid-cols-4 gap-2">
                  <div v-for="(img, idx) in (formContent[field.name] || [])" :key="idx" class="relative aspect-square">
                    <img :src="img" class="w-full h-full object-cover rounded" />
                    <button type="button" @click="removeImage(field.name, idx)" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center">×</button>
                  </div>
                  <label v-if="!formContent[field.name] || formContent[field.name].length < (field.maxCount || 9)" class="aspect-square border-2 border-dashed border-gray-200 rounded-lg flex items-center justify-center cursor-pointer hover:border-blue-400">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <input type="file" accept="image/*" multiple @change="handleImageUpload($event, field.name)" class="hidden" />
                  </label>
                </div>
              </div>
              <button v-else-if="field.type === 'participants'" type="button" @click="openParticipantPicker" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-left flex justify-between items-center">
                <span>{{ formData.participants.length ? `已选 ${formData.participants.length} 人` : '选择参与人' }}</span>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
              <div v-else-if="field.type === 'rating'" class="flex gap-1">
                <button v-for="star in (field.maxRating || 5)" :key="star" type="button" @click="formContent[field.name] = star" :class="['text-2xl', star <= (formContent[field.name] || 0) ? 'text-yellow-400' : 'text-gray-300']">★</button>
              </div>
              <input v-else v-model="formContent[field.name]" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
          </template>

          <!-- 无模板时的简单表单 -->
          <template v-if="currentFields.length === 0">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('oa.logTitleLabel') || '标题' }} <span class="text-red-500">*</span></label>
              <input v-model="formContent.title" type="text" :placeholder="$t('oa.titlePlaceholder') || '请输入标题'" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('oa.logContentLabel') || '内容' }} <span class="text-red-500">*</span></label>
              <textarea v-model="formContent.content" rows="4" :placeholder="$t('oa.contentPlaceholder') || '请输入内容'" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('oa.selectRecipients') || '参与人' }}</label>
              <button type="button" @click="openParticipantPicker" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-left flex justify-between items-center">
                <span>{{ formData.participants.length ? `已选 ${formData.participants.length} 人` : '选择参与人' }}</span>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
            </div>
          </template>

          <!-- 提交按钮 -->
          <button type="button" @click="submitLog" :disabled="submitting" class="w-full bg-blue-600 text-white py-3 rounded-lg font-medium disabled:opacity-50">
            {{ submitting ? $t('common.submitting') || '提交中...' : $t('common.submit') || '提交' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 参与人选择弹窗 -->
    <div v-if="showParticipantModal" class="fixed inset-0 bg-black/50 z-[60] flex items-end sm:items-center justify-center" @click.self="showParticipantModal = false">
      <div class="bg-white rounded-t-2xl sm:rounded-xl w-full sm:max-w-md max-h-[70vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-4 py-3 flex justify-between items-center z-10">
          <h3 class="font-bold text-sm">{{ $t('oa.selectRecipients') || '选择参与人' }}</h3>
          <button type="button" @click="showParticipantModal = false" class="text-gray-400">✕</button>
        </div>
        <div class="p-4">
          <div v-if="availableUsers.length === 0" class="text-center py-8 text-gray-400 text-sm">暂无可选人员</div>
          <div
            v-for="user in availableUsers"
            :key="user.id"
            class="flex items-center gap-3 py-3 border-b border-gray-50"
            :style="{ paddingLeft: (user.level || 0) * 16 + 'px' }"
          >
            <input type="checkbox" :value="user.id" v-model="tempParticipants" class="w-4 h-4 text-blue-600 rounded" />
            <div class="flex-1 min-w-0">
              <span class="text-sm">{{ user.name }}</span>
              <span v-if="user.department" class="text-xs text-gray-400 ml-1">({{ user.department }})</span>
            </div>
          </div>
        </div>
        <div class="sticky bottom-0 bg-white border-t px-4 py-3">
          <button type="button" @click="confirmParticipants" class="w-full py-3 bg-blue-600 text-white rounded-lg font-medium text-sm">
            {{ $t('common.confirm') || '确认' }}（{{ $t('oa.selectedCount') || '已选' }} {{ tempParticipants.length }}）
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
