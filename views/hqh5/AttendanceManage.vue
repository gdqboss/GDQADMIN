<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <!-- 顶部打卡区 -->
    <div class="bg-gradient-to-br from-green-500 via-green-600 to-green-700 text-white p-6 text-center">
      <div class="text-5xl font-bold mb-1">{{ currentTime }}</div>
      <div class="text-sm opacity-90">{{ currentDate }}</div>
    </div>

    <!-- 打卡按钮 -->
    <div class="mx-4 -mt-8 bg-white rounded-2xl shadow-lg p-6 relative z-10">
      <div v-if="todayRecord" class="mb-4 text-center">
        <p class="text-sm text-gray-500 mb-3">
          今日已打卡
          <span class="text-green-600 font-medium">上班 {{ todayRecord.clock_in }}</span>
          <span v-if="todayRecord.clock_out" class="ml-2 text-orange-600 font-medium">下班 {{ todayRecord.clock_out }}</span>
        </p>
      </div>
      <div class="flex justify-center gap-6">
        <button
          @click="handleClock('in')"
          :disabled="loading || todayRecord?.clock_in"
          class="w-28 h-28 rounded-full text-white shadow-xl active:scale-95 transition-transform flex flex-col items-center justify-center disabled:cursor-not-allowed"
          :class="todayRecord?.clock_in ? 'bg-gray-300' : 'bg-gradient-to-br from-green-500 to-green-600'"
        >
          <div class="text-xl font-bold">{{ todayRecord?.clock_in || '上班' }}</div>
          <div class="text-xs opacity-80 mt-1">{{ todayRecord?.clock_in ? '已打卡' : '点击打卡' }}</div>
        </button>
        <button
          @click="handleClock('out')"
          :disabled="loading || !todayRecord?.clock_in || todayRecord?.clock_out"
          class="w-28 h-28 rounded-full text-white shadow-xl active:scale-95 transition-transform flex flex-col items-center justify-center disabled:cursor-not-allowed"
          :class="(!todayRecord?.clock_in || todayRecord?.clock_out) ? 'bg-gray-300' : 'bg-gradient-to-br from-orange-500 to-orange-600'"
        >
          <div class="text-xl font-bold">{{ todayRecord?.clock_out || '下班' }}</div>
          <div class="text-xs opacity-80 mt-1">{{ todayRecord?.clock_out ? '已打卡' : '点击打卡' }}</div>
        </button>
      </div>
    </div>

    <!-- 月度统计 -->
    <div class="p-4">
      <h2 class="text-lg font-bold text-gray-800 mb-3">本月考勤 ({{ currentMonth }})</h2>
      <div class="grid grid-cols-4 gap-3 mb-4">
        <div class="bg-white rounded-xl p-3 text-center shadow-sm">
          <div class="text-2xl font-bold text-green-600">{{ monthStats.normal }}</div>
          <div class="text-xs text-gray-500 mt-1">正常</div>
        </div>
        <div class="bg-white rounded-xl p-3 text-center shadow-sm">
          <div class="text-2xl font-bold text-orange-600">{{ monthStats.late }}</div>
          <div class="text-xs text-gray-500 mt-1">迟到</div>
        </div>
        <div class="bg-white rounded-xl p-3 text-center shadow-sm">
          <div class="text-2xl font-bold text-blue-600">{{ monthStats.leave }}</div>
          <div class="text-xs text-gray-500 mt-1">请假</div>
        </div>
        <div class="bg-white rounded-xl p-3 text-center shadow-sm">
          <div class="text-2xl font-bold text-gray-600">{{ monthStats.absent }}</div>
          <div class="text-xs text-gray-500 mt-1">缺勤</div>
        </div>
      </div>
    </div>

    <!-- 考勤记录 -->
    <div class="px-4">
      <h2 class="text-lg font-bold text-gray-800 mb-3">考勤记录</h2>
      <div v-if="records.length === 0" class="text-center py-8 text-gray-400 bg-white rounded-xl">本月暂无记录</div>
      <div v-else class="space-y-2">
        <div
          v-for="r in records"
          :key="r.id"
          class="bg-white rounded-xl p-3 shadow-sm flex items-center justify-between"
        >
          <div>
            <div class="text-sm font-medium text-gray-800">{{ formatDate(r.date) }}</div>
            <div class="text-xs text-gray-400 mt-1">
              <span>上班 {{ r.clock_in || '--' }}</span>
              <span class="mx-2">·</span>
              <span>下班 {{ r.clock_out || '--' }}</span>
            </div>
          </div>
          <span
            class="text-xs px-2 py-1 rounded-full"
            :class="{
              'bg-green-100 text-green-700': r.status === 'normal',
              'bg-orange-100 text-orange-700': r.status === 'late',
              'bg-blue-100 text-blue-700': r.status === 'leave',
              'bg-red-100 text-red-700': r.status === 'absent'
            }"
          >{{ statusText(r.status) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'

const currentTime = ref('')
const currentDate = ref('')
const records = ref([])
const todayRecord = ref(null)
const loading = ref(false)
let timer = null

const currentMonth = computed(() => {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
})

const monthStats = computed(() => {
  const s = { normal: 0, late: 0, leave: 0, absent: 0 }
  for (const r of records.value) {
    if (s[r.status] !== undefined) s[r.status]++
  }
  return s
})

const statusText = (s) => ({ normal: '正常', late: '迟到', leave: '请假', absent: '缺勤', early: '早退' }[s] || s)

const formatDate = (d) => {
  if (!d) return ''
  return new Date(d).toLocaleDateString('zh-CN', { month: 'long', day: 'numeric', weekday: 'short' })
}

const updateTime = () => {
  const now = new Date()
  currentTime.value = now.toTimeString().slice(0, 8)
  currentDate.value = now.toLocaleDateString('zh-CN', { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' })
}

const handleClock = async (type) => {
  loading.value = true
  try {
    const res = await axios.post('/api/hqh5/attendance/clock', {
      user_id: 1,
      user_name: '江清波',
      type: 'work',
      location: 'A栋 305'
    })
    if (res.data?.code === 0) {
      const { ElMessage } = await import('element-plus')
      ElMessage.success(type === 'in' ? '上班打卡成功' : '下班打卡成功')
      await loadData()
    }
  } catch (e) {
    console.error('[clock] 失败:', e)
    const { ElMessage } = await import('element-plus')
    ElMessage.error('打卡失败，请重试')
  } finally {
    loading.value = false
  }
}

const loadData = async () => {
  try {
    const res = await axios.get(`/api/hqh5/attendance/list?user_id=1&month=${currentMonth.value}`)
    records.value = res.data?.data || []
    const today = new Date().toISOString().slice(0, 10)
    todayRecord.value = records.value.find(r => r.date && r.date.slice(0, 10) === today) || null
  } catch (e) {
    console.error('[loadData] 失败:', e)
  }
}

onMounted(() => {
  updateTime()
  timer = setInterval(updateTime, 1000)
  loadData()
})

onBeforeUnmount(() => { if (timer) clearInterval(timer) })
</script>