<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <!-- 頂部歡迎區 -->
    <section class="bg-gradient-to-br from-amber-500 to-amber-600 text-white px-4 py-8">
      <div class="max-w-md mx-auto flex items-center gap-4">
        <div class="w-16 h-16 rounded-full bg-white/20 backdrop-blur flex items-center justify-center text-3xl">
          {{ avatar }}
        </div>
        <div>
          <h1 class="text-xl font-bold">{{ name }}</h1>
          <p class="text-amber-100 text-sm mt-1">{{ membershipStatus }}</p>
        </div>
      </div>
    </section>

    <!-- 快捷入口 -->
    <div class="max-w-md mx-auto px-4 -mt-4">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 grid grid-cols-4 gap-2 p-4">
        <router-link v-for="entry in entries" :key="entry.path"
          :to="entry.path"
          class="flex flex-col items-center py-2 hover:bg-amber-50 rounded-lg transition">
          <span class="text-2xl mb-1">{{ entry.icon }}</span>
          <span class="text-xs text-gray-600">{{ entry.label }}</span>
        </router-link>
      </div>
    </div>

    <!-- 我的資訊 -->
    <div class="max-w-md mx-auto px-4 mt-4">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 divide-y">
        <router-link to="/portal/members" class="flex items-center justify-between p-4 hover:bg-gray-50">
          <span class="flex items-center gap-3"><span>👥</span><span>會員通訊錄</span></span>
          <span class="text-gray-400">›</span>
        </router-link>
        <router-link to="/portal/downloads" class="flex items-center justify-between p-4 hover:bg-gray-50">
          <span class="flex items-center gap-3"><span>📥</span><span>我的下載</span></span>
          <span class="text-gray-400">›</span>
        </router-link>
        <router-link to="/portal/org" class="flex items-center justify-between p-4 hover:bg-gray-50">
          <span class="flex items-center gap-3"><span>🏢</span><span>組織架構</span></span>
          <span class="text-gray-400">›</span>
        </router-link>
        <router-link to="/portal/contact" class="flex items-center justify-between p-4 hover:bg-gray-50">
          <span class="flex items-center gap-3"><span>📞</span><span>聯絡我們</span></span>
          <span class="text-gray-400">›</span>
        </router-link>
      </div>
    </div>

    <div class="max-w-md mx-auto px-4 mt-6 text-center text-xs text-gray-400">
      學會 Portal v1.0 · Powered by SmartBiz
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const avatar = ref('👤')
const name = ref('訪客')
const membershipStatus = ref('點擊登入享更多服務')

// TODO: 接 /api/association/members/me (登入態)
onMounted(() => {
  const token = localStorage.getItem('token') || localStorage.getItem('gdq_token')
  if (token) {
    // 異步載入真實會員資料
    import('@/services/api.js').then(async ({ api }) => {
      try {
        const r = await api.get('/association/members/me')
        if (r.code === 0 && r.data) {
          name.value = r.data.name_zh_tw || r.data.name_zh || '會員'
          membershipStatus.value = r.data.membership_level || '普通會員'
          avatar.value = '🌟'
        }
      } catch (e) {
        // 未登入或 API 失敗保持訪客態
      }
    })
  }
})

const entries = [
  { path: '/portal/news',     icon: '📰', label: '資訊' },
  { path: '/portal/academic', icon: '🎓', label: '學術' },
  { path: '/portal/activities', icon: '📅', label: '活動' },
  { path: '/portal/journals', icon: '📚', label: '期刊' },
]
</script>