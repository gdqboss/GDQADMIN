<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Banner -->
    <div class="bg-gradient-to-r from-amber-500 to-amber-600 text-white py-12">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-sm text-amber-100 mb-2">
          <router-link to="/portal" class="hover:text-white">{{ $t('portal.nav.home') }}</router-link>
          <span class="mx-2">/</span>
          <router-link :to="backPath" class="hover:text-white">{{ backLabel }}</router-link>
        </div>
        <h1 class="text-3xl font-bold">{{ item.title }}</h1>
        <div class="flex flex-wrap items-center gap-4 mt-4 text-sm text-amber-100">
          <span v-if="item.published_at">📅 {{ formatDate(item.published_at) }}</span>
          <span v-if="item.author_name">✍ {{ item.author_name }}</span>
          <span v-if="item.journal_name">📖 {{ item.journal_name }}</span>
          <span v-if="item.doi">DOI: {{ item.doi }}</span>
          <span v-if="item.view_count !== undefined">👁 {{ item.view_count }}</span>
        </div>
      </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <article class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <div v-if="item.cover_image" class="mb-6">
          <img :src="item.cover_image" class="w-full rounded-lg" :alt="item.title" />
        </div>

        <div class="prose prose-slate max-w-none text-gray-700 leading-relaxed whitespace-pre-wrap">{{ item.content }}</div>

        <!-- 下载按钮 -->
        <div v-if="item.pdf_url" class="mt-8 pt-6 border-t border-gray-100 flex flex-wrap gap-3">
          <button @click="downloadFile" class="px-6 py-2.5 bg-amber-500 text-white rounded-lg hover:bg-amber-600 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            {{ $t('portal.detail.download_pdf') }}
          </button>
          <span class="text-sm text-gray-500 self-center">{{ $t('portal.detail.download_count', { n: item.download_count || 0 }) }}</span>
        </div>

        <!-- 文件下载（非 PDF）-->
        <div v-else-if="item.file_url" class="mt-8 pt-6 border-t border-gray-100">
          <button @click="downloadFile" class="px-6 py-2.5 bg-amber-500 text-white rounded-lg hover:bg-amber-600">
            {{ $t('portal.list.download') }} {{ item.file_name || '' }}
          </button>
        </div>
      </article>

      <div class="mt-8 text-center">
        <button @click="$router.push(backPath)" class="px-6 py-2 border border-gray-200 rounded-lg hover:bg-gray-50">
          ← {{ $t('portal.detail.back') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const props = defineProps({
  apiPath: String,           // e.g. '/association/announcements'
  backPath: String,           // e.g. '/portal/news'
  backLabel: String,          // e.g. $t('portal.nav.news')
  downloadEndpoint: String,    // e.g. '/association/journals/{id}/download'
})

const route = useRoute()
const router = useRouter()
const item = ref({})

async function load() {
  const { api } = await import('@/services/api.js')
  const res = await api.get(`${props.apiPath}/${route.params.id}`)
  if (res.code === 0) item.value = res.data || {}
}

function formatDate(t) {
  if (!t) return ''
  return new Date(t).toLocaleDateString()
}

async function downloadFile() {
  if (!item.value.id) return
  const { api } = await import('@/services/api.js')
  const endpoint = props.downloadEndpoint.replace('{id}', item.value.id)
  const res = await api.post(endpoint)
  if (res.code === 0 && res.data?.file_url) {
    window.open(res.data.file_url, '_blank')
  } else if (res.data?.pdf_url) {
    window.open(res.data.pdf_url, '_blank')
  }
  load() // refresh download count
}

onMounted(load)
</script>