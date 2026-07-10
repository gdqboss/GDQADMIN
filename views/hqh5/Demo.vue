<template>
  <div class="min-h-screen bg-gradient-to-br from-purple-50 to-indigo-50 p-4">
    <div class="max-w-4xl mx-auto">
      <!-- 顶部 -->
      <div class="text-center mb-8 pt-8">
        <div class="text-5xl mb-3">🌉</div>
        <h1 class="text-3xl font-bold text-gray-800 mb-2">横琴湾区创新中心</h1>
        <p class="text-sm text-gray-600">企业服务一站式移动平台 · 客户演示地图</p>
      </div>

      <!-- 11 个页面入口 -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <router-link
          v-for="page in pages"
          :key="page.path"
          :to="page.path"
          class="bg-white rounded-2xl shadow-md hover:shadow-xl transition p-5 group"
        >
          <div class="flex items-start gap-3">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl group-hover:scale-110 transition" :class="page.bgClass">
              {{ page.icon }}
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="text-base font-bold text-gray-800 group-hover:text-purple-600">{{ page.label }}</h3>
              <p class="text-xs text-gray-500 mt-1">{{ page.desc }}</p>
              <span class="text-xs text-purple-600 mt-2 inline-block">{{ page.tag }}</span>
            </div>
          </div>
        </router-link>
      </div>

      <!-- 系统信息 -->
      <div class="mt-8 bg-white rounded-2xl shadow-md p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-3">📊 系统状态</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div class="bg-green-50 rounded-lg p-3">
            <div class="text-xs text-gray-500">后端 API</div>
            <div class="text-sm font-bold text-green-600">✓ 运行中</div>
          </div>
          <div class="bg-blue-50 rounded-lg p-3">
            <div class="text-xs text-gray-500">数据库</div>
            <div class="text-sm font-bold text-blue-600">11 张表</div>
          </div>
          <div class="bg-purple-50 rounded-lg p-3">
            <div class="text-xs text-gray-500">演示数据</div>
            <div class="text-sm font-bold text-purple-600">已注入</div>
          </div>
          <div class="bg-orange-50 rounded-lg p-3">
            <div class="text-xs text-gray-500">端口</div>
            <div class="text-sm font-bold text-orange-600">3200</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const pages = [
  { path: '/hqh5/guest-home', label: '游客首页', desc: 'banner + 活动 + 资讯', icon: '🏠', bgClass: 'bg-purple-100', tag: '公开' },
  { path: '/hqh5/login', label: '登录页', desc: '4 角色一键演示', icon: '🔐', bgClass: 'bg-indigo-100', tag: '演示' },
  { path: '/hqh5/enterprise-home', label: '企业首页', desc: '个人数据 + 通知 + 申请', icon: '🏢', bgClass: 'bg-blue-100', tag: '员工' },
  { path: '/hqh5/attendance-manage', label: '考勤管理', desc: '打卡 + 月度统计', icon: '⏰', bgClass: 'bg-green-100', tag: '员工' },
  { path: '/hqh5/approval-list', label: '申请审批', desc: '请假 / 加班 / 出差 / 报销', icon: '📋', bgClass: 'bg-orange-100', tag: '全员' },
  { path: '/hqh5/venue-booking', label: '会议室预约', desc: '5 个会议室 + 冲突检测', icon: '🏛️', bgClass: 'bg-cyan-100', tag: '员工' },
  { path: '/hqh5/butler-booking', label: '管家服务', desc: 'IT/快递/搬运/保洁/停车', icon: '🛎️', bgClass: 'bg-pink-100', tag: '员工' },
  { path: '/hqh5/enterprise-center', label: '个人中心', desc: '9 大功能菜单', icon: '👤', bgClass: 'bg-yellow-100', tag: '员工' },
  { path: '/hqh5/article-publish', label: '文章发布', desc: '8 篇文章 + 分类管理', icon: '📝', bgClass: 'bg-teal-100', tag: '后台' },
  { path: '/hqh5/notification-push', label: '通知推送', desc: '5 条已发送通知', icon: '🔔', bgClass: 'bg-red-100', tag: '后台' },
  { path: '/hqh5/backend-dashboard', label: '后台看板', desc: '7 维真数据聚合', icon: '📊', bgClass: 'bg-violet-100', tag: '后台' },
  { path: '/hqh5/booking-success', label: '预约成功', desc: '会议室 / 管家成功状态', icon: '✅', bgClass: 'bg-emerald-100', tag: '状态' }
]
</script>