<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <!-- 顶部 -->
    <div class="bg-gradient-to-br from-purple-700 via-purple-800 to-indigo-900 text-white p-6">
      <h1 class="text-2xl font-bold mb-1">后台看板</h1>
      <p class="text-sm opacity-90">横琴湾区运营数据</p>
      <p class="text-xs opacity-70 mt-1">更新时间：{{ formatTime(data.timestamp) }}</p>
    </div>

    <!-- 核心指标 -->
    <div class="p-4">
      <div class="grid grid-cols-2 gap-3">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-4 text-white shadow-md">
          <div class="text-3xl font-bold">{{ data.stats?.enterprises || 0 }}</div>
          <div class="text-xs opacity-90 mt-1">入驻企业</div>
        </div>
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-4 text-white shadow-md">
          <div class="text-3xl font-bold">{{ data.stats?.today_attendance || 0 }}</div>
          <div class="text-xs opacity-90 mt-1">今日打卡</div>
        </div>
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-4 text-white shadow-md">
          <div class="text-3xl font-bold">{{ data.stats?.pending_approvals || 0 }}</div>
          <div class="text-xs opacity-90 mt-1">待审批</div>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-4 text-white shadow-md">
          <div class="text-3xl font-bold">{{ data.stats?.active_activities || 0 }}</div>
          <div class="text-xs opacity-90 mt-1">进行中活动</div>
        </div>
      </div>
    </div>

    <!-- 详细数据 -->
    <div class="px-4">
      <h2 class="text-base font-bold text-gray-800 mb-3">业务详情</h2>
      <div class="grid grid-cols-2 gap-3">
        <div class="bg-white rounded-xl p-4 shadow-sm">
          <div class="text-xs text-gray-500">今日会议室预约</div>
          <div class="text-2xl font-bold text-purple-600 mt-1">{{ data.stats?.today_venues || 0 }}</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
          <div class="text-xs text-gray-500">未处理工单</div>
          <div class="text-2xl font-bold text-orange-600 mt-1">{{ data.stats?.open_butler || 0 }}</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
          <div class="text-xs text-gray-500">已发布文章</div>
          <div class="text-2xl font-bold text-blue-600 mt-1">{{ data.stats?.articles || 0 }}</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
          <div class="text-xs text-gray-500">已发送通知</div>
          <div class="text-2xl font-bold text-green-600 mt-1">{{ data.stats?.sent_notifications || 0 }}</div>
        </div>
      </div>
    </div>

    <!-- 工单类型分布 -->
    <div class="p-4">
      <h2 class="text-base font-bold text-gray-800 mb-3">近 30 天工单类型</h2>
      <div class="bg-white rounded-xl p-4 shadow-sm">
        <div v-if="!data.butler_by_type || data.butler_by_type.length === 0" class="text-center py-8 text-gray-400">
          暂无数据
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="bt in data.butler_by_type"
            :key="bt.type"
            class="flex items-center gap-3"
          >
            <div class="w-16 text-sm text-gray-700">{{ typeLabel(bt.type) }}</div>
            <div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
              <div
                class="h-full rounded-full flex items-center justify-end pr-2 text-xs text-white font-medium transition-all"
                :class="typeColor(bt.type)"
                :style="{ width: Math.max(10, (bt.count / maxButler) * 100) + '%' }"
              >
                {{ bt.count }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 一周考勤 -->
    <div class="px-4 pb-4">
      <h2 class="text-base font-bold text-gray-800 mb-3">近 7 天考勤</h2>
      <div class="bg-white rounded-xl p-4 shadow-sm">
        <div v-if="!data.weekly_attendance || data.weekly_attendance.length === 0" class="text-center py-8 text-gray-400">
          暂无数据
        </div>
        <div v-else class="flex items-end justify-around h-32 gap-2">
          <div v-for="wa in data.weekly_attendance" :key="wa.day" class="flex flex-col items-center flex-1">
            <div
              class="w-full bg-gradient-to-t from-purple-500 to-purple-300 rounded-t transition-all"
              :style="{ height: Math.max(10, (wa.count / maxAttendance) * 100) + '%' }"
            ></div>
            <div class="text-xs text-gray-500 mt-2">{{ wa.day }}</div>
            <div class="text-xs text-gray-700 font-medium">{{ wa.count }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const data = ref({})

const maxButler = computed(() => {
  const arr = data.value?.butler_by_type || []
  return Math.max(1, ...arr.map(x => x.count))
})

const maxAttendance = computed(() => {
  const arr = data.value?.weekly_attendance || []
  return Math.max(1, ...arr.map(x => x.count))
})

const typeLabel = (t) => ({ it: 'IT 支持', express: '快递代收', moving: '搬运协助', cleaning: '保洁', parking: '停车' }[t] || t)
const typeColor = (t) => ({
  it: 'bg-gradient-to-r from-blue-500 to-blue-400',
  express: 'bg-gradient-to-r from-orange-500 to-orange-400',
  moving: 'bg-gradient-to-r from-purple-500 to-purple-400',
  cleaning: 'bg-gradient-to-r from-green-500 to-green-400',
  parking: 'bg-gradient-to-r from-pink-500 to-pink-400'
}[t] || 'bg-gradient-to-r from-gray-500 to-gray-400')

const formatTime = (d) => d ? new Date(d).toLocaleString('zh-CN') : ''

onMounted(async () => {
  try {
    const res = await axios.get('/api/hqh5/dashboard/overview')
    data.value = res.data?.data || {}
  } catch (e) {
    console.error(e)
  }
})
</script>