<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <!-- 顶部企业问候 -->
    <div class="bg-gradient-to-br from-purple-600 via-purple-700 to-purple-800 text-white p-6 rounded-b-3xl">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur flex items-center justify-center text-2xl">
          🏢
        </div>
        <div class="flex-1">
          <h2 class="font-bold text-lg">{{ greeting }}，{{ userName }}</h2>
          <p class="text-xs opacity-90">{{ enterpriseName }}</p>
        </div>
        <router-link to="/hqh5/enterprise-center" class="text-xs bg-white/20 px-3 py-1 rounded-full">个人中心</router-link>
      </div>
      <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="bg-white/10 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold">{{ stats.pendingApprovals }}</div>
          <div class="text-xs opacity-80">待审批</div>
        </div>
        <div class="bg-white/10 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold">{{ stats.todayAttendance }}</div>
          <div class="text-xs opacity-80">今日打卡</div>
        </div>
        <div class="bg-white/10 rounded-xl p-3 text-center">
          <div class="text-2xl font-bold">{{ stats.openButler }}</div>
          <div class="text-xs opacity-80">工单</div>
        </div>
      </div>
    </div>

    <!-- 快捷功能 -->
    <div class="mx-4 -mt-4 bg-white rounded-2xl shadow-lg p-4 grid grid-cols-4 gap-4 relative z-10">
      <router-link to="/hqh5/attendance-manage" class="flex flex-col items-center text-center hover:scale-105 transition">
        <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center mb-1">
          <span class="text-2xl">⏰</span>
        </div>
        <span class="text-xs text-gray-700">考勤打卡</span>
      </router-link>
      <router-link to="/hqh5/venue-booking" class="flex flex-col items-center text-center hover:scale-105 transition">
        <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center mb-1">
          <span class="text-2xl">🏢</span>
        </div>
        <span class="text-xs text-gray-700">会议室</span>
      </router-link>
      <router-link to="/hqh5/butler-booking" class="flex flex-col items-center text-center hover:scale-105 transition">
        <div class="w-12 h-12 rounded-xl bg-orange-100 flex items-center justify-center mb-1">
          <span class="text-2xl">🛎️</span>
        </div>
        <span class="text-xs text-gray-700">管家服务</span>
      </router-link>
      <router-link to="/hqh5/approval-list" class="flex flex-col items-center text-center hover:scale-105 transition">
        <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center mb-1">
          <span class="text-2xl">📋</span>
        </div>
        <span class="text-xs text-gray-700">申请审批</span>
      </router-link>
    </div>

    <!-- 最新通知 -->
    <div class="p-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-bold text-gray-800">最新通知</h2>
        <router-link to="/hqh5/login" class="text-sm text-purple-600">更多 →</router-link>
      </div>
      <div v-if="notifications.length === 0" class="text-center py-8 text-gray-400 bg-white rounded-xl">暂无通知</div>
      <div v-else class="space-y-2">
        <div
          v-for="n in notifications.slice(0, 3)"
          :key="n.id"
          class="bg-white rounded-xl p-3 shadow-sm flex items-start gap-3"
          :class="{'border-l-4 border-red-500': n.priority === 'high'}"
        >
          <div class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center"
               :class="{
                 'bg-red-100 text-red-600': n.type === 'urgent',
                 'bg-blue-100 text-blue-600': n.type === 'system',
                 'bg-green-100 text-green-600': n.type === 'activity',
                 'bg-purple-100 text-purple-600': n.type === 'announcement'
               }">
            {{ n.type === 'urgent' ? '🚨' : n.type === 'system' ? '⚙️' : n.type === 'activity' ? '🎯' : '📢' }}
          </div>
          <div class="flex-1 min-w-0">
            <h3 class="text-sm font-medium text-gray-800 line-clamp-1">{{ n.title }}</h3>
            <p class="text-xs text-gray-500 line-clamp-1 mt-0.5">{{ n.content }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ formatTime(n.sent_at) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- 我的申请 -->
    <div class="px-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-bold text-gray-800">我的申请</h2>
        <router-link to="/hqh5/approval-list" class="text-sm text-purple-600">全部 →</router-link>
      </div>
      <div v-if="approvals.length === 0" class="text-center py-8 text-gray-400 bg-white rounded-xl">暂无申请记录</div>
      <div v-else class="space-y-2">
        <div
          v-for="ap in approvals.slice(0, 3)"
          :key="ap.id"
          class="bg-white rounded-xl p-3 shadow-sm"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-sm font-medium text-gray-800">{{ ap.title }}</span>
            </div>
            <span
              class="text-xs px-2 py-0.5 rounded-full"
              :class="{
                'bg-yellow-100 text-yellow-700': ap.status === 'pending',
                'bg-green-100 text-green-700': ap.status === 'approved',
                'bg-red-100 text-red-700': ap.status === 'rejected'
              }"
            >{{ statusText(ap.status) }}</span>
          </div>
          <div class="text-xs text-gray-400 mt-1">{{ formatTime(ap.created_at) }} · {{ ap.user_name }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const userName = ref('员工')
const enterpriseName = ref('横琴湾区创新中心')
const notifications = ref([])
const approvals = ref([])
const stats = ref({ pendingApprovals: 0, todayAttendance: 0, openButler: 0 })

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 11) return '早上好'
  if (h < 13) return '中午好'
  if (h < 18) return '下午好'
  return '晚上好'
})

const statusText = (s) => ({ pending: '待审批', approved: '已批准', rejected: '已拒绝', cancelled: '已取消' }[s] || s)

const formatTime = (d) => {
  if (!d) return ''
  const date = new Date(d)
  const now = new Date()
  const diff = (now - date) / 1000
  if (diff < 60) return '刚刚'
  if (diff < 3600) return `${Math.floor(diff / 60)} 分钟前`
  if (diff < 86400) return `${Math.floor(diff / 3600)} 小时前`
  return date.toLocaleDateString('zh-CN')
}

onMounted(async () => {
  try {
    // 拿 dashboard overview
    const dash = await axios.get('/api/hqh5/dashboard/overview')
    if (dash.data?.data?.stats) {
      stats.value = {
        pendingApprovals: dash.data.data.stats.pending_approvals || 0,
        todayAttendance: dash.data.data.stats.today_attendance || 0,
        openButler: dash.data.data.stats.open_butler || 0
      }
    }
    // 拿通知
    const notif = await axios.get('/api/hqh5/notifications/list?user_id=1&status=sent')
    notifications.value = notif.data?.data || []
    // 拿我的申请
    const appr = await axios.get('/api/hqh5/approvals/list?user_id=1&page=1&pageSize=5')
    approvals.value = appr.data?.data || []
  } catch (e) {
    console.error('[enterprise-home] 加载失败:', e)
  }
})
</script>