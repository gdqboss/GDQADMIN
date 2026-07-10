<template>
  <div class="min-h-screen bg-gray-50">
    <!-- 顶部 banner 轮播 -->
    <div class="relative h-48 overflow-hidden bg-gradient-to-r from-purple-600 to-purple-800">
      <div v-if="banners.length > 0" class="relative h-full">
        <img
          v-for="(banner, idx) in banners"
          :key="banner.id"
          :src="banner.cover_image"
          :class="['absolute inset-0 w-full h-full object-cover transition-opacity duration-700', idx === currentBanner ? 'opacity-70' : 'opacity-0']"
          :alt="banner.title"
        />
        <div class="absolute inset-0 flex items-center justify-center text-white text-center px-6">
          <div>
            <h1 class="text-3xl font-bold mb-2 drop-shadow-lg">{{ banners[currentBanner]?.title }}</h1>
            <p class="text-lg opacity-90 drop-shadow">{{ banners[currentBanner]?.subtitle }}</p>
          </div>
        </div>
        <div class="absolute bottom-3 left-0 right-0 flex justify-center gap-1.5">
          <span
            v-for="(_, idx) in banners"
            :key="idx"
            :class="['w-2 h-2 rounded-full transition-all', idx === currentBanner ? 'bg-white w-6' : 'bg-white/50']"
          ></span>
        </div>
      </div>
      <div v-else class="flex items-center justify-center h-full text-white">
        <div class="text-center">
          <h1 class="text-3xl font-bold mb-2">横琴湾区创新中心</h1>
          <p class="text-lg opacity-90">共建大湾区国际科技创新中心</p>
        </div>
      </div>
    </div>

    <!-- 快捷入口 -->
    <div class="bg-white mx-4 -mt-8 rounded-2xl shadow-lg p-4 grid grid-cols-4 gap-4 relative z-10">
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
      <router-link to="/hqh5/attendance-manage" class="flex flex-col items-center text-center hover:scale-105 transition">
        <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center mb-1">
          <span class="text-2xl">⏰</span>
        </div>
        <span class="text-xs text-gray-700">考勤</span>
      </router-link>
      <router-link to="/hqh5/approval-list" class="flex flex-col items-center text-center hover:scale-105 transition">
        <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center mb-1">
          <span class="text-2xl">📋</span>
        </div>
        <span class="text-xs text-gray-700">审批</span>
      </router-link>
    </div>

    <!-- 活动列表 -->
    <div class="p-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-bold text-gray-800">精彩活动</h2>
        <router-link to="/hqh5/login" class="text-sm text-purple-600">查看全部 →</router-link>
      </div>
      <div v-if="loading" class="text-center py-8 text-gray-400">加载中...</div>
      <div v-else-if="activities.length === 0" class="text-center py-8 text-gray-400">暂无活动</div>
      <div v-else class="space-y-3">
        <div
          v-for="act in activities"
          :key="act.id"
          class="bg-white rounded-xl shadow-sm overflow-hidden flex"
        >
          <img :src="act.cover_image" class="w-24 h-24 object-cover flex-shrink-0" :alt="act.title" />
          <div class="p-3 flex-1 min-w-0">
            <h3 class="font-medium text-gray-800 line-clamp-1">{{ act.title }}</h3>
            <p class="text-xs text-gray-500 line-clamp-1 mt-1">{{ act.subtitle }}</p>
            <div class="flex items-center gap-2 mt-2 text-xs text-gray-400">
              <span>📍 {{ act.location }}</span>
              <span>🕐 {{ formatDate(act.start_at) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 文章 -->
    <div class="p-4">
      <h2 class="text-lg font-bold text-gray-800 mb-3">最新资讯</h2>
      <div v-if="loading" class="text-center py-8 text-gray-400">加载中...</div>
      <div v-else class="space-y-3">
        <div
          v-for="art in articles"
          :key="art.id"
          class="bg-white rounded-xl shadow-sm p-4"
        >
          <div class="flex items-start gap-3">
            <img :src="art.cover_image" class="w-16 h-16 rounded-lg object-cover flex-shrink-0" :alt="art.title" />
            <div class="flex-1 min-w-0">
              <span class="inline-block px-2 py-0.5 bg-purple-100 text-purple-600 text-xs rounded">{{ art.category }}</span>
              <h3 class="font-medium text-gray-800 mt-1 line-clamp-1">{{ art.title }}</h3>
              <p class="text-xs text-gray-500 line-clamp-2 mt-1">{{ art.summary }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 底部登录引导 -->
    <div class="p-4 pb-24">
      <div class="bg-gradient-to-r from-purple-600 to-purple-700 rounded-2xl p-5 text-white text-center">
        <h3 class="text-lg font-bold mb-1">登录解锁更多功能</h3>
        <p class="text-sm opacity-90 mb-3">企业员工专享：考勤 · 审批 · 会议室 · 管家服务</p>
        <router-link to="/hqh5/login" class="inline-block bg-white text-purple-600 px-6 py-2 rounded-full font-medium text-sm">
          一键登录
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'

const banners = ref([])
const activities = ref([])
const articles = ref([])
const loading = ref(true)
const currentBanner = ref(0)
let timer = null

const formatDate = (d) => {
  if (!d) return ''
  return new Date(d).toLocaleString('zh-CN', { month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(async () => {
  try {
    const [b, a, art] = await Promise.all([
      axios.get('/api/hqh5/banners'),
      axios.get('/api/hqh5/activities?status=open&limit=5'),
      axios.get('/api/hqh5/articles?page=1&pageSize=5')
    ])
    banners.value = b.data?.data || []
    activities.value = a.data?.data || []
    articles.value = art.data?.data?.list || []
  } catch (e) {
    console.error('[guest-home] 加载失败:', e)
  } finally {
    loading.value = false
  }

  // banner 自动轮播
  timer = setInterval(() => {
    if (banners.value.length > 0) {
      currentBanner.value = (currentBanner.value + 1) % banners.value.length
    }
  }, 4000)
})

onBeforeUnmount(() => { if (timer) clearInterval(timer) })
</script>