<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <!-- 顶部 -->
    <div class="bg-white p-4 sticky top-0 z-10 shadow-sm">
      <h1 class="text-lg font-bold text-gray-800">企业中心</h1>
    </div>

    <!-- 用户卡片 -->
    <div class="bg-gradient-to-br from-purple-600 to-purple-800 text-white p-6 m-4 rounded-2xl shadow-lg">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-16 h-16 rounded-full bg-white/20 backdrop-blur flex items-center justify-center text-3xl">
          👤
        </div>
        <div class="flex-1">
          <h2 class="text-lg font-bold">{{ user.name }}</h2>
          <p class="text-xs opacity-90">{{ user.enterprise }}</p>
        </div>
      </div>
      <div class="grid grid-cols-3 gap-3 mt-4">
        <div class="bg-white/10 rounded-xl p-3 text-center">
          <div class="text-xl font-bold">{{ stats.attendance }}</div>
          <div class="text-xs opacity-80 mt-1">本月出勤</div>
        </div>
        <div class="bg-white/10 rounded-xl p-3 text-center">
          <div class="text-xl font-bold">{{ stats.approvals }}</div>
          <div class="text-xs opacity-80 mt-1">本月申请</div>
        </div>
        <div class="bg-white/10 rounded-xl p-3 text-center">
          <div class="text-xl font-bold">{{ stats.bookings }}</div>
          <div class="text-xs opacity-80 mt-1">本月预约</div>
        </div>
      </div>
    </div>

    <!-- 功能菜单 -->
    <div class="mx-4 bg-white rounded-2xl shadow-sm overflow-hidden">
      <div
        v-for="(item, idx) in menus"
        :key="idx"
        @click="handleMenu(item)"
        :class="['flex items-center gap-3 p-4 cursor-pointer active:bg-gray-50 transition',
                 idx > 0 ? 'border-t border-gray-100' : '']"
      >
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl" :class="item.bgClass">
          {{ item.icon }}
        </div>
        <div class="flex-1">
          <h3 class="text-sm font-medium text-gray-800">{{ item.label }}</h3>
          <p class="text-xs text-gray-400">{{ item.desc }}</p>
        </div>
        <span class="text-gray-400">›</span>
      </div>
    </div>

    <!-- 退出登录 -->
    <div class="p-4">
      <button @click="logout" class="w-full bg-white border border-red-200 text-red-500 py-3 rounded-xl font-medium">
        退出登录
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'

const router = useRouter()
const user = ref({ name: '江清波', enterprise: '横琴湾区创新中心' })
const stats = ref({ attendance: 12, approvals: 3, bookings: 5 })

const menus = [
  { icon: '👤', label: '个人资料', desc: '查看和编辑个人信息', bgClass: 'bg-blue-100', action: () => ElMessage.info('个人资料页面开发中') },
  { icon: '🔒', label: '账号安全', desc: '修改密码 / 绑定手机', bgClass: 'bg-green-100', action: () => ElMessage.info('账号安全页面开发中') },
  { icon: '📋', label: '我的申请', desc: '查看所有审批历史', bgClass: 'bg-purple-100', path: '/hqh5/approval-list' },
  { icon: '🏢', label: '会议室预约', desc: '查看我的预约', bgClass: 'bg-orange-100', path: '/hqh5/venue-booking' },
  { icon: '🛎️', label: '管家服务', desc: '查看我的工单', bgClass: 'bg-pink-100', path: '/hqh5/butler-booking' },
  { icon: '⏰', label: '考勤记录', desc: '查看月度考勤', bgClass: 'bg-cyan-100', path: '/hqh5/attendance-manage' },
  { icon: '🔔', label: '消息通知', desc: '系统通知和提醒', bgClass: 'bg-yellow-100', action: () => ElMessage.info('消息通知开发中') },
  { icon: '❓', label: '帮助反馈', desc: '使用指南和问题反馈', bgClass: 'bg-indigo-100', action: () => ElMessage.info('帮助反馈开发中') },
  { icon: 'ℹ️', label: '关于', desc: '横琴湾区 H5 v1.0', bgClass: 'bg-gray-100', action: () => ElMessage.info('横琴湾区 H5 v1.0\n彩美特团队开发') }
]

const handleMenu = (item) => {
  if (item.path) {
    router.push(item.path)
  } else if (item.action) {
    item.action()
  }
}

const logout = async () => {
  try {
    await ElMessageBox.confirm('确定要退出登录吗？', '提示', { type: 'warning' })
    router.push('/hqh5/login')
  } catch {}
}

onMounted(async () => {
  try {
    const [a, ap] = await Promise.all([
      axios.get('/api/hqh5/attendance/list?user_id=1'),
      axios.get('/api/hqh5/approvals/list?user_id=1')
    ])
    stats.value.attendance = (a.data?.data || []).length
    stats.value.approvals = (ap.data?.data || []).length
  } catch (e) {
    console.error(e)
  }
})
</script>