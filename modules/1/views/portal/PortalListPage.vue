<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Banner -->
    <div class="bg-gradient-to-r from-amber-500 to-amber-600 text-white py-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-3xl md:text-4xl font-bold mb-3">{{ title }}</h1>
        <p v-if="subtitle" class="text-amber-100">{{ subtitle }}</p>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <!-- 搜索框 -->
      <div v-if="showSearch" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="flex gap-2">
          <input
            v-model="filter.keyword"
            @keyup.enter="search"
            :placeholder="$t('portal.search.placeholder')"
            class="flex-1 px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-amber-500"
          />
          <button @click="search" class="px-6 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600">
            {{ $t('portal.search.button') }}
          </button>
        </div>
      </div>

      <!-- 列表 -->
      <div v-if="loading" class="text-center py-20 text-gray-400">{{ $t('portal.loading') }}</div>

      <div v-else-if="list.length === 0" class="text-center py-20 text-gray-400 bg-white rounded-xl border border-gray-100">
        {{ $t('portal.list.no_data') }}
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <article
          v-for="item in list"
          :key="item.id"
          class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition cursor-pointer"
          @click="$emit('item-click', item)"
        >
          <div v-if="item.cover_image" class="aspect-video bg-gray-100 overflow-hidden">
            <img :src="item.cover_image" class="w-full h-full object-cover" :alt="item.title" />
          </div>
          <div class="p-5">
            <div class="flex items-center gap-2 mb-2">
              <span v-if="item.category" class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs rounded">{{ categoryLabel(item.category) }}</span>
              <span v-if="item.priority" class="px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded">⭐</span>
            </div>
            <h3 class="font-bold text-gray-800 mb-2 line-clamp-2 hover:text-amber-600">{{ item.title }}</h3>
            <p v-if="item.summary" class="text-sm text-gray-500 line-clamp-2 mb-3">{{ item.summary }}</p>
            <div class="flex items-center justify-between text-xs text-gray-400">
              <span>📅 {{ formatDate(item.published_at || item.created_at) }}</span>
              <span v-if="item.view_count !== undefined">👁 {{ item.view_count || 0 }}</span>
              <span v-if="item.download_count !== undefined">⬇ {{ item.download_count || 0 }}</span>
            </div>
          </div>
        </article>
      </div>

      <!-- 分页 -->
      <div v-if="total > pageSize" class="flex justify-center mt-8">
        <div class="flex gap-2">
          <button
            @click="prevPage"
            :disabled="currentPage <= 1"
            class="px-4 py-2 border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-50"
          >
            ← {{ $t('portal.list.page_prev') }}
          </button>
          <span class="px-4 py-2 text-gray-600">{{ currentPage }} / {{ totalPages }}</span>
          <button
            @click="nextPage"
            :disabled="currentPage >= totalPages"
            class="px-4 py-2 border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-50"
          >
            {{ $t('portal.list.page_next') }} →
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
// 2026-08-07 BUG FIX: 静态 import api 避免 Vite chunk resolution bug
// 原来 `await import('@/services/api.js')` 被 Vite 编译成
// `import('./index-CEyISeUP.js').then(S => S.ge)`, 但 S.ge 是 vue-i18n 工具函数
// (不是 axios 实例), 拿到 undefined.default.get() → throw → catch静默吞掉 → list=[]
// 改顶层静态 import 后, Vite 直接 inline api 实例, api.get(...) 正确
import { api } from '@/services/api.js'

const props = defineProps({
  title: String,
  subtitle: String,
  apiPath: String,            // e.g. '/association/news'
  categoryMap: Object,         // { general: '综合', ... }
  showSearch: { type: Boolean, default: true },
  extraFilter: Object,         // { status: 'published', category: 'general' }
})

const emit = defineEmits(['item-click'])

const list = ref([])
const loading = ref(false)
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(12)
const filter = ref({ keyword: '' })

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / pageSize.value)))

function categoryLabel(c) {
  return props.categoryMap?.[c] || c || ''
}

function formatDate(t) {
  if (!t) return '-'
  return new Date(t).toLocaleDateString()
}

async function load() {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      size: pageSize.value,
      ...(props.extraFilter || {})
    }
    if (filter.value.keyword) params.keyword = filter.value.keyword
    const res = await api.get(props.apiPath, { params })
    if (res.code === 0) {
      // 兼容两种 resp data 结构:
      //   1) {list,total} 包装 (interceptor 解嵌套后保留)
      //   2) 直接 array (interceptor 解嵌套后变 array)
      if (Array.isArray(res.data)) {
        list.value = res.data
        total.value = res.data.length
      } else {
        list.value = res.data.list || []
        total.value = res.data.total || 0
      }
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function search() {
  currentPage.value = 1
  load()
}

function prevPage() {
  if (currentPage.value > 1) { currentPage.value--; load() }
}
function nextPage() {
  if (currentPage.value < totalPages.value) { currentPage.value++; load() }
}

onMounted(load)
watch(() => props.apiPath, load)
</script>