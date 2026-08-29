<template>
  <div class="min-h-screen bg-gray-50">
    <div class="bg-gradient-to-r from-amber-500 to-amber-600 text-white py-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-3xl md:text-4xl font-bold mb-3">{{ $t('portal.nav.members') }}</h1>
        <p class="text-amber-100">{{ $t('portal.sections.members_gallery') }}</p>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div v-if="loading" class="text-center py-20 text-gray-400">{{ $t('portal.loading') }}</div>
      <div v-else-if="list.length === 0" class="text-center py-20 text-gray-400 bg-white rounded-xl">
        {{ $t('portal.list.no_data') }}
      </div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <article v-for="m in list" :key="m.id" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition">
          <div class="aspect-square bg-gray-100 overflow-hidden">
            <img v-if="m.avatar" :src="m.avatar" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full flex items-center justify-center text-5xl text-gray-300">👤</div>
          </div>
          <div class="p-4">
            <div class="flex items-center gap-2 mb-2">
              <h3 class="font-bold text-gray-800">{{ m.name }}</h3>
              <span v-if="m.card_level" class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs rounded">{{ m.card_level }}</span>
            </div>
            <p v-if="m.title" class="text-sm text-gray-600 mb-1">{{ m.title }}</p>
            <p v-if="m.company" class="text-xs text-gray-400">🏢 {{ m.company }}</p>
            <p v-if="m.industry" class="text-xs text-gray-400 mt-1">🏷 {{ m.industry }}</p>
            <p v-if="m.bio" class="text-xs text-gray-500 mt-2 line-clamp-2">{{ m.bio }}</p>
          </div>
        </article>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
// 2026-08-07 BUG FIX: 同 PortalListPage, 静态 import api 避免 Vite chunk bug
import { api } from '@/services/api.js'
const list = ref([])
const loading = ref(false)

onMounted(async () => {
  loading.value = true
  try {
    const r = await api.get('/association/cards')
    if (r.code === 0) {
      // 兼容 interceptor 解嵌套后的两种结构
      if (Array.isArray(r.data)) list.value = r.data
      else list.value = r.data.list || []
    }
  } catch (e) {} finally { loading.value = false }
})
</script>