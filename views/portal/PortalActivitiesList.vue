<template>
  <div class="min-h-screen bg-gray-50">
    <div class="bg-gradient-to-r from-amber-500 to-amber-600 text-white py-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-3xl md:text-4xl font-bold mb-3">{{ $t('portal.nav.activities') }}</h1>
        <p class="text-amber-100">{{ $t('portal.sections.upcoming_activities') }}</p>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div v-if="loading" class="text-center py-20 text-gray-400">{{ $t('portal.loading') }}</div>

      <div v-else-if="list.length === 0" class="text-center py-20 text-gray-400 bg-white rounded-xl">
        {{ $t('portal.list.no_data') }}
      </div>

      <div v-else class="space-y-4">
        <article
          v-for="a in list"
          :key="a.id"
          class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition cursor-pointer"
          @click="$router.push(`/portal/activities/${a.id}`)"
        >
          <div class="flex flex-col md:flex-row md:items-start gap-4">
            <!-- 时间块 -->
            <div class="md:w-32 flex-shrink-0">
              <div class="bg-amber-50 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-amber-600">{{ formatDay(a.start_time) }}</div>
                <div class="text-sm text-gray-600 mt-1">{{ formatMonth(a.start_time) }}</div>
              </div>
            </div>

            <div class="flex-1">
              <div class="flex items-center gap-2 mb-2">
                <span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs rounded">{{ categoryLabel(a.category) }}</span>
                <span v-if="a.status === 'open'" class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded">报名中</span>
                <span v-else-if="a.status === 'closed'" class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">已截止</span>
                <span v-else-if="a.status === 'finished'" class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded">已结束</span>
              </div>
              <h3 class="text-xl font-bold text-gray-800 mb-2 hover:text-amber-600">{{ a.title }}</h3>
              <p v-if="a.description" class="text-sm text-gray-500 mb-3 line-clamp-2">{{ a.description }}</p>
              <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-600">
                <span>📍 {{ a.location }}</span>
                <span>⏰ {{ formatTime(a.start_time) }} - {{ formatTime(a.end_time) }}</span>
                <span>👥 {{ a.current_participants }} / {{ a.max_participants || '∞' }}</span>
                <span class="font-medium" :class="a.fee > 0 ? 'text-amber-600' : 'text-green-600'">
                  {{ a.fee > 0 ? a.fee + ' 元' : '免費' }}
                </span>
              </div>
            </div>
          </div>
        </article>
      </div>

      <div v-if="total > pageSize" class="flex justify-center mt-8">
        <div class="flex gap-2">
          <button @click="prevPage" :disabled="currentPage <= 1" class="px-4 py-2 border border-gray-200 rounded-lg disabled:opacity-50">
            ← {{ $t('portal.list.page_prev') }}
          </button>
          <span class="px-4 py-2 text-gray-600">{{ currentPage }} / {{ totalPages }}</span>
          <button @click="nextPage" :disabled="currentPage >= totalPages" class="px-4 py-2 border border-gray-200 rounded-lg disabled:opacity-50">
            {{ $t('portal.list.page_next') }} →
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const list = ref([])
const loading = ref(false)
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(10)
const totalPages = computed(() => Math.max(1, Math.ceil(total.value / pageSize.value)))

function categoryLabel(c) {
  return { general: '綜合', seminar: '講座', training: '培訓', annual: '年會' }[c] || c || '活動'
}

function formatDay(t) { return t ? new Date(t).getDate() : '?' }
function formatMonth(t) { return t ? new Date(t).toLocaleDateString('zh-CN', { year: 'numeric', month: 'long' }) : '' }
function formatTime(t) { return t ? new Date(t).toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit', hour12: false }) : '' }

async function load() {
  loading.value = true
  try {
    const { api } = await import('@/services/api.js')
    const r = await api.get('/association/activities', { params: { page: currentPage.value, size: pageSize.value } })
    if (r.code === 0) {
      // 兼容 axios interceptor 解嵌套后的两种结构
      if (Array.isArray(r.data)) {
        list.value = r.data
        total.value = r.data.length
      } else {
        list.value = r.data.list || []
        total.value = r.data.total || 0
      }
    }
  } catch (e) {} finally { loading.value = false }
}

function prevPage() { if (currentPage.value > 1) { currentPage.value--; load() } }
function nextPage() { if (currentPage.value < totalPages.value) { currentPage.value++; load() } }

// 直接在 setup 末尾同步触发 (避免 onMounted/watch 在 Vite mangle 后失效)
load()
</script>