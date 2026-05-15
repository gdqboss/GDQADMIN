<template>
    <!-- 顶部时间显示 -->
  <div class="min-h-screen bg-gray-100 pb-20">
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6 text-center">
      <div class="text-5xl font-bold mb-1">{{ currentTime }}</div>
      <div class="text-lg opacity-90">{{ currentDate }}</div>
    </div>

    <!-- 打卡按钮 -->
    <div class="p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 text-center">
        <!-- GPS状态 -->
        <div v-if="gpsLoading" class="mb-4 flex items-center justify-center gap-2 text-gray-500">
          <div class="animate-spin rounded-full h-5 w-5 border-2 border-blue-600 border-t-transparent"></div>
          <span>{{ $t('oa.locating') }}</span>
        </div>
        <div v-else-if="gpsData" class="mb-4 text-green-600 flex items-center justify-center gap-1">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
          <span class="text-sm">{{ gpsData.address }}</span>
        </div>
        <div v-else-if="gpsError" class="mb-4 text-red-500 text-sm">
          {{ gpsError }}
        </div>
        <div v-else class="mb-4 text-gray-400 text-sm">
          {{ $t('oa.clickToGetLocation') }}
        </div>

        <!-- 打卡按钮 -->
        <div class="flex justify-center gap-8">
          <div class="flex flex-col items-center">
            <button @click="handleClock('in')" :disabled="loading || todayRecord?.clock_in"
              :class="[
                'w-28 h-28 rounded-full text-white shadow-xl active:scale-95 transition-transform',
                todayRecord?.clock_in
                  ? 'bg-gradient-to-br from-gray-300 to-gray-400 cursor-not-allowed'
                  : 'bg-gradient-to-br from-green-500 to-green-600'
              ]">
              <div class="flex flex-col items-center justify-center h-full">
                <div class="text-2xl font-bold">{{ todayRecord?.clock_in || $t('oa.clockInText') }}</div>
                <div class="text-xs opacity-80">{{ todayRecord?.clock_in ? $t('oa.signedIn') : $t('oa.signIn') }}</div>
              </div>
            </button>
            <div class="mt-2 text-xs" :class="todayRecord?.clock_in ? 'text-green-600 font-medium' : 'text-gray-500'">
              {{ todayRecord?.clock_in ? ($t('oa.signedIn') + ' ✓') : $t('oa.clockInHint') }}
            </div>
          </div>
          <div class="flex flex-col items-center">
            <button @click="handleClock('out')" :disabled="loading || todayRecord?.clock_out"
              :class="[
                'w-28 h-28 rounded-full text-white shadow-xl active:scale-95 transition-transform',
                todayRecord?.clock_out
                  ? 'bg-gradient-to-br from-gray-300 to-gray-400 cursor-not-allowed'
                  : 'bg-gradient-to-br from-blue-500 to-blue-600'
              ]">
              <div class="flex flex-col items-center justify-center h-full">
                <div class="text-2xl font-bold">{{ todayRecord?.clock_out || $t('oa.clockOutText') }}</div>
                <div class="text-xs opacity-80">{{ todayRecord?.clock_out ? $t('oa.signedOut') : $t('oa.signOut') }}</div>
              </div>
            </button>
            <div class="mt-2 text-xs" :class="todayRecord?.clock_out ? 'text-green-600 font-medium' : 'text-gray-500'">
              {{ todayRecord?.clock_out ? ($t('oa.signedOut') + ' ✓') : $t('oa.clockOutHint') }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 统计卡片 -->
    <div class="p-4">
      <div class="grid grid-cols-4 gap-2">
        <div class="bg-white rounded-lg shadow p-3 text-center">
          <div class="text-xs text-gray-500">{{ $t('oa.attendanceStat') }}</div>
          <div class="text-xl font-bold text-green-600">{{ monthStats.normalDays }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
          <div class="text-xs text-gray-500">{{ $t('oa.lateStat') }}</div>
          <div class="text-xl font-bold text-orange-500">{{ monthStats.lateDays }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
          <div class="text-xs text-gray-500">{{ $t('oa.earlyStat') }}</div>
          <div class="text-xl font-bold text-blue-500">{{ monthStats.earlyDays }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
          <div class="text-xs text-gray-500">{{ $t('oa.abnormalStat') }}</div>
          <div class="text-xl font-bold text-red-500">{{ monthStats.absentDays }}</div>
        </div>
      </div>
    </div>

    <!-- 今日记录 -->
    <div v-if="todayRecord" class="px-4">
      <div class="bg-white rounded-xl shadow p-4">
        <div class="font-medium text-gray-800 mb-2">{{ $t('oa.todayClockRecord') }}</div>
        <div class="flex justify-between text-sm">
          <div>
            <span class="text-gray-500">{{ $t('oa.clockInTimeLabel') }}</span>
            <span class="font-medium">{{ todayRecord.clock_in || '-' }}</span>
          </div>
          <div>
            <span class="text-gray-500">{{ $t('oa.clockOutTimeLabel') }}</span>
            <span class="font-medium">{{ todayRecord.clock_out || '-' }}</span>
          </div>
          <div>
            <span :class="getStatusClass(todayRecord.status)" class="px-2 py-0.5 text-xs rounded-full">{{ getStatusText(todayRecord.status) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 历史记录 -->
    <div class="px-4 mt-4">
      <div class="flex items-center justify-between mb-2">
        <div class="font-medium text-gray-800">{{ $t('oa.clockRecords') }}</div>
        <button @click="showFilter = !showFilter" class="text-blue-600 text-sm">{{ $t('oa.filterBtn') }}</button>
      </div>

      <!-- 筛选 -->
      <div v-if="showFilter" class="bg-white rounded-xl shadow p-3 mb-2">
        <div class="flex gap-2">
          <input v-model="filters.start_date" type="date" class="flex-1 border rounded-lg px-2 py-1 text-sm" />
          <input v-model="filters.end_date" type="date" class="flex-1 border rounded-lg px-2 py-1 text-sm" />
          <button @click="loadRecords" class="bg-blue-600 text-white px-3 py-1 rounded-lg text-sm">{{ $t('oa.queryBtn') }}</button>
        </div>
      </div>

      <!-- 记录列表 -->
      <div class="space-y-2">
        <div v-for="record in records.list" :key="record.id" class="bg-white rounded-xl shadow p-3 flex items-center justify-between">
          <div>
            <div class="font-medium text-gray-800">{{ record.date?.split('T')[0] }}</div>
            <div class="text-xs text-gray-500">{{ record.clock_in || '-' }} - {{ record.clock_out || '-' }}</div>
          </div>
          <span :class="getStatusClass(record.status)" class="px-3 py-1 text-xs rounded-full">{{ getStatusText(record.status) }}</span>
        </div>
        <div v-if="records.list.length === 0" class="text-center text-gray-400 py-8">{{ $t('oa.noRecords') }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../stores/user'
import api from '../../services/api'
import { getCurrentPosition } from '../../utils/geolocation'

const { t } = useI18n()
useUserStore()
const userStore = useUserStore()
const loading = ref(false)
const gpsLoading = ref(false)
const gpsError = ref('')
const gpsData = ref(null)
const records = ref({ list: [], total: 0 })
const filters = ref({ start_date: '', end_date: '', status: '' })
const currentTime = ref('')
const currentDate = ref('')
const monthStats = ref({ normalDays: 0, lateDays: 0, earlyDays: 0, absentDays: 0 })
const showFilter = ref(false)

let timeInterval = null

const todayRecord = ref(null)

async function loadTodayRecord() {
  try {
    const res = await api.get('/oa/attendance/my-today')
    if (res.code === 0) todayRecord.value = res.data
  } catch (err) {
    console.error('Failed to load today record:', err)
  }
}

// 上班后超过1小时可打卡（或有上班记录）
// 允许下班打卡（后端会验证）
const canClockOut = computed(() => {
  // 已下班就不能再打
  if (todayRecord.value?.clock_out) return false
  // 有上班记录就允许
  return !!todayRecord.value?.clock_in
})

onMounted(() => {
  loadTodayRecord()
  loadRecords()
  loadMonthStats()
  updateTime()
  timeInterval = setInterval(updateTime, 1000)
})

onUnmounted(() => {
  if (timeInterval) clearInterval(timeInterval)
})

function updateTime() {
  const now = new Date()
  currentTime.value = now.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
  currentDate.value = now.toLocaleDateString('zh-CN', { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' })
}

async function handleClock(type) {
  loading.value = true
  try {
    const res = await api.post('/oa/attendance/clock', { type })
    if (res.code === 0) {
      await loadTodayRecord()
      await loadRecords()
      await loadMonthStats()
      alert(t('oa.clockSuccess'))
    } else {
      alert(res.message || t('oa.clockFailed'))
    }
  } catch (err) {
    const msg = err.response?.data?.message || err.message || t('oa.clockFailed')
    alert(msg)
  } finally {
    loading.value = false
  }
}

async function loadRecords() {
  try {
    const params = { user_id: userStore.userId, ...filters.value }
    const res = await api.get('/oa/attendance', { params })
    if (res.code === 0) {
      records.value = res.data
    }
    showFilter.value = false
  } catch (err) {
    console.error('Failed to load records:', err)
  }
}

async function loadMonthStats() {
  try {
    const now = new Date()
    const year = now.getFullYear()
    const month = String(now.getMonth() + 1).padStart(2, '0')
    const params = {
      user_id: userStore.userId,
      start_date: `${year}-${month}-01`,
      end_date: `${year}-${month}-${new Date(year, now.getMonth() + 1, 0).getDate()}`
    }
    const res = await api.get('/oa/attendance', { params })
    if (res.code === 0) {
      const list = res.data.list || []
      monthStats.value = {
        normalDays: list.filter(r => r.status === 'normal').length,
        lateDays: list.filter(r => r.status === 'late').length,
        earlyDays: list.filter(r => r.status === 'early').length,
        absentDays: list.filter(r => r.status === 'absent').length
      }
    }
  } catch (err) {
    console.error('Failed to load stats:', err)
  }
}

function getStatusClass(status) {
  const classes = {
    normal: 'bg-green-100 text-green-700',
    late: 'bg-orange-100 text-orange-700',
    early: 'bg-blue-100 text-blue-700',
    absent: 'bg-red-100 text-red-700'
  }
  return classes[status] || 'bg-gray-100 text-gray-700'
}

function getStatusText(status) {
  const texts = {
    normal: t('oa.statusNormal'),
    late: t('oa.statusLate'),
    early: t('oa.statusEarly'),
    absent: t('oa.statusAbsent')
  }
  return texts[status] || status
}
</script>
