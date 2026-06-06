<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import Pagination from '../../components/Pagination.vue'
import api from '../../services/api.js'

const { t } = useI18n()

// ─── State ──────────────────────────────────────────────────────────────────────
const records = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = 20

// Filters
const filterDateStart = ref('')
const filterDateEnd = ref('')
const filterType = ref('')
const filterChannel = ref('')

// Stats
const stats = ref({
  totalShares: 0,
  totalViews: 0,
  totalConversions: 0,
  conversionRate: 0,
  byChannel: {},
  byType: {},
  topPerformers: []
})

// Detail Modal
const showDetailModal = ref(false)
const selectedLog = ref(null)

// ─── Fetch ────────────────────────────────────────────────────────────────────
async function fetchRecords() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize }
    if (filterDateStart.value) params.date_start = filterDateStart.value
    if (filterDateEnd.value) params.date_end = filterDateEnd.value
    if (filterType.value) params.type = filterType.value
    if (filterChannel.value) params.channel = filterChannel.value

    const res = await api.get('/share-logs', { params })
    if (res.code === 0) {
      records.value = res.data.list
      total.value = res.data.total
    }
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const params = {}
    if (filterDateStart.value) params.date_start = filterDateStart.value
    if (filterDateEnd.value) params.date_end = filterDateEnd.value
    if (filterType.value) params.type = filterType.value
    if (filterChannel.value) params.channel = filterChannel.value

    const res = await api.get('/share-logs/stats', { params })
    if (res.code === 0) {
      stats.value = res.data
    }
  } catch (err) {
    console.error('Failed to fetch stats:', err)
  }
}

watch([filterDateStart, filterDateEnd, filterType, filterChannel], () => {
  currentPage.value = 1
  fetchRecords()
  fetchStats()
})
watch(currentPage, fetchRecords)

onMounted(() => {
  // Set default date range to last 30 days
  const end = new Date()
  const start = new Date()
  start.setDate(start.getDate() - 30)
  filterDateEnd.value = end.toISOString().split('T')[0]
  filterDateStart.value = start.toISOString().split('T')[0]

  fetchRecords()
  fetchStats()
})

// ─── Detail Modal ──────────────────────────────────────────────────────────────
function openDetail(log) {
  selectedLog.value = log
  showDetailModal.value = true
}

function closeDetail() {
  showDetailModal.value = false
  selectedLog.value = null
}

// ─── Export ────────────────────────────────────────────────────────────────────
async function exportExcel() {
  const params = {}
  if (filterDateStart.value) params.date_start = filterDateStart.value
  if (filterDateEnd.value) params.date_end = filterDateEnd.value
  if (filterType.value) params.type = filterType.value
  if (filterChannel.value) params.channel = filterChannel.value

  const q = new URLSearchParams(params).toString()
  const TOKEN = localStorage.getItem('gdq_token')
  const res = await fetch(`/api/share-logs/export?${q}`, {
    headers: { Authorization: `Bearer ${TOKEN}` }
  })
  const blob = await res.blob()
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `share-logs_${new Date().toISOString().split('T')[0]}.xlsx`
  a.click()
  URL.revokeObjectURL(url)
}

// ─── Helpers ───────────────────────────────────────────────────────────────────
function formatTime(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleString('zh-CN', {
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatDate(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleDateString('zh-CN')
}

function getTypeLabel(type) {
  const labels = {
    product: t('sharelog.typeProduct'),
    qrcode: t('sharelog.typeQrcode'),
    page: t('sharelog.typePage')
  }
  return labels[type] || type
}

function getChannelLabel(channel) {
  const labels = {
    wechat: t('sharelog.channelWechat'),
    whatsapp: t('sharelog.channelWhatsapp'),
    link: t('sharelog.channelLink'),
    poster: t('sharelog.channelPoster')
  }
  return labels[channel] || channel
}

function getChannelIcon(channel) {
  const icons = {
    wechat: 'chat',
    whatsapp: 'forum',
    link: 'link',
    poster: 'image'
  }
  return icons[channel] || 'share'
}

function getChannelColor(channel) {
  const colors = {
    wechat: 'text-green-600 bg-green-50',
    whatsapp: 'text-emerald-600 bg-emerald-50',
    link: 'text-blue-600 bg-blue-50',
    poster: 'text-purple-600 bg-purple-50'
  }
  return colors[channel] || 'text-gray-600 bg-gray-50'
}

function calculateConversionRate(views, conversions) {
  if (!views || views === 0) return '0%'
  return ((conversions / views) * 100).toFixed(1) + '%'
}

// ─── Chart Helpers ─────────────────────────────────────────────────────────────
const channelChartData = computed(() => {
  const data = stats.value.byChannel || {}
  const total = Object.values(data).reduce((sum, val) => sum + val, 0)
  return Object.entries(data).map(([channel, count]) => ({
    channel,
    count,
    percentage: total > 0 ? ((count / total) * 100).toFixed(1) : 0,
    color: getChannelChartColor(channel)
  }))
})

const typeChartData = computed(() => {
  const data = stats.value.byType || {}
  const maxCount = Math.max(...Object.values(data), 1)
  return Object.entries(data).map(([type, count]) => ({
    type,
    count,
    percentage: ((count / maxCount) * 100).toFixed(1)
  }))
})

function getChannelChartColor(channel) {
  const colors = {
    wechat: '#10b981',
    whatsapp: '#059669',
    link: '#3b82f6',
    poster: '#8b5cf6'
  }
  return colors[channel] || '#6b7280'
}
</script>

<template>
  <div>
    <PageHeader :title="$t('sharelog.title')" :subtitle="$t('sharelog.subtitle')" />

    <!-- Statistics Cards -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 flex items-center gap-3">
        <div class="size-10 rounded-lg bg-primary/10 flex items-center justify-center">
          <span class="material-symbols-outlined text-primary text-[20px]">share</span>
        </div>
        <div>
          <p class="text-xs text-text-secondary">{{ $t('sharelog.totalShares') }}</p>
          <p class="text-xl font-bold text-text-primary">{{ stats.totalShares }}</p>
        </div>
      </div>
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 flex items-center gap-3">
        <div class="size-10 rounded-lg bg-blue-50 flex items-center justify-center">
          <span class="material-symbols-outlined text-blue-600 text-[20px]">visibility</span>
        </div>
        <div>
          <p class="text-xs text-text-secondary">{{ $t('sharelog.totalViews') }}</p>
          <p class="text-xl font-bold text-text-primary">{{ stats.totalViews }}</p>
        </div>
      </div>
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 flex items-center gap-3">
        <div class="size-10 rounded-lg bg-green-50 flex items-center justify-center">
          <span class="material-symbols-outlined text-green-600 text-[20px]">check_circle</span>
        </div>
        <div>
          <p class="text-xs text-text-secondary">{{ $t('sharelog.totalConversions') }}</p>
          <p class="text-xl font-bold text-text-primary">{{ stats.totalConversions }}</p>
        </div>
      </div>
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 flex items-center gap-3">
        <div class="size-10 rounded-lg bg-orange-50 flex items-center justify-center">
          <span class="material-symbols-outlined text-orange-600 text-[20px]">trending_up</span>
        </div>
        <div>
          <p class="text-xs text-text-secondary">{{ $t('sharelog.conversionRate') }}</p>
          <p class="text-xl font-bold text-text-primary">{{ stats.conversionRate }}%</p>
        </div>
      </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-2 gap-4 mb-6">
      <!-- Channel Distribution -->
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-5">
        <h3 class="text-sm font-bold text-text-primary mb-4 flex items-center gap-2">
          <span class="material-symbols-outlined text-[18px]">pie_chart</span>
          {{ $t('sharelog.sharesByChannel') }}
        </h3>
        <div class="space-y-3">
          <div v-for="item in channelChartData" :key="item.channel" class="flex items-center gap-3">
            <div class="flex-1">
              <div class="flex items-center justify-between mb-1">
                <span class="text-sm text-text-primary">{{ getChannelLabel(item.channel) }}</span>
                <span class="text-xs text-text-secondary">{{ item.count }} ({{ item.percentage }}%)</span>
              </div>
              <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-300"
                  :style="{ width: item.percentage + '%', backgroundColor: item.color }"
                ></div>
              </div>
            </div>
          </div>
          <p v-if="channelChartData.length === 0" class="text-sm text-text-secondary text-center py-4">
            {{ $t('common.noData') }}
          </p>
        </div>
      </div>

      <!-- Type Distribution -->
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-5">
        <h3 class="text-sm font-bold text-text-primary mb-4 flex items-center gap-2">
          <span class="material-symbols-outlined text-[18px]">bar_chart</span>
          {{ $t('sharelog.sharesByType') }}
        </h3>
        <div class="space-y-3">
          <div v-for="item in typeChartData" :key="item.type" class="flex items-center gap-3">
            <div class="flex-1">
              <div class="flex items-center justify-between mb-1">
                <span class="text-sm text-text-primary">{{ getTypeLabel(item.type) }}</span>
                <span class="text-xs text-text-secondary">{{ item.count }}</span>
              </div>
              <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div
                  class="h-full bg-primary rounded-full transition-all duration-300"
                  :style="{ width: item.percentage + '%' }"
                ></div>
              </div>
            </div>
          </div>
          <p v-if="typeChartData.length === 0" class="text-sm text-text-secondary text-center py-4">
            {{ $t('common.noData') }}
          </p>
        </div>
      </div>
    </div>

    <!-- Top Performers -->
    <div v-if="stats.topPerformers && stats.topPerformers.length > 0" class="bg-white rounded-lg border border-gray-100 shadow-card p-5 mb-6">
      <h3 class="text-sm font-bold text-text-primary mb-4 flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">emoji_events</span>
        {{ $t('sharelog.topPerformers') }}
      </h3>
      <div class="grid grid-cols-3 gap-4">
        <div v-for="(item, idx) in stats.topPerformers.slice(0, 3)" :key="idx"
             class="border border-gray-100 rounded-lg p-4 hover:border-primary/30 transition-colors">
          <div class="flex items-start gap-3">
            <div :class="[
              'size-8 rounded-full flex items-center justify-center text-sm font-bold',
              idx === 0 ? 'bg-yellow-100 text-yellow-700' :
              idx === 1 ? 'bg-gray-100 text-gray-700' :
              'bg-orange-100 text-orange-700'
            ]">
              {{ idx + 1 }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-text-primary truncate">{{ item.target_name }}</p>
              <p class="text-xs text-text-secondary">{{ getTypeLabel(item.type) }}</p>
              <div class="flex items-center gap-3 mt-2 text-xs">
                <span class="text-text-secondary">{{ item.views }} {{ $t('sharelog.views') }}</span>
                <span class="text-green-600 font-medium">{{ calculateConversionRate(item.views, item.conversions) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters + Export -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 mb-6">
      <div class="flex flex-wrap items-center gap-3">
        <select v-model="filterType" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('sharelog.allTypes') }}</option>
          <option value="product">{{ $t('sharelog.typeProduct') }}</option>
          <option value="qrcode">{{ $t('sharelog.typeQrcode') }}</option>
          <option value="page">{{ $t('sharelog.typePage') }}</option>
        </select>
        <select v-model="filterChannel" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('sharelog.allChannels') }}</option>
          <option value="wechat">{{ $t('sharelog.channelWechat') }}</option>
          <option value="whatsapp">{{ $t('sharelog.channelWhatsapp') }}</option>
          <option value="link">{{ $t('sharelog.channelLink') }}</option>
          <option value="poster">{{ $t('sharelog.channelPoster') }}</option>
        </select>
        <input v-model="filterDateStart" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <span class="text-text-secondary text-sm">—</span>
        <input v-model="filterDateEnd" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <button @click="filterType=''; filterChannel=''; filterDateStart=''; filterDateEnd=''" class="text-sm text-text-secondary hover:text-text-primary border border-gray-200 rounded-lg px-3 py-2">
          {{ $t('common.reset') }}
        </button>
        <div class="ml-auto">
          <button @click="exportExcel" class="flex items-center gap-1.5 border border-gray-200 text-text-secondary hover:text-text-primary hover:border-gray-300 px-3 py-2 rounded-lg text-sm transition-colors">
            <span class="material-symbols-outlined text-[16px]">download</span>
            {{ $t('common.export') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">{{ $t('sharelog.date') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('sharelog.type') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('sharelog.target') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('sharelog.channel') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('sharelog.views') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('sharelog.conversions') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('sharelog.conversionRate') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('common.action') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="loading">
              <td colspan="8" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('common.loading') }}</td>
            </tr>
            <tr v-else-if="records.length === 0">
              <td colspan="8" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('sharelog.noRecords') }}</td>
            </tr>
            <tr v-for="r in records" :key="r.id" class="hover:bg-gray-50 transition-colors cursor-pointer" @click="openDetail(r)">
              <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">{{ formatTime(r.created_at) }}</td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 text-text-primary rounded text-xs">
                  {{ getTypeLabel(r.type) }}
                </span>
              </td>
              <td class="px-4 py-3">
                <p class="font-medium text-text-primary">{{ r.target_name || '-' }}</p>
                <p v-if="r.target_spec" class="text-xs text-text-secondary">{{ r.target_spec }}</p>
              </td>
              <td class="px-4 py-3 text-center">
                <div :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium', getChannelColor(r.channel)]">
                  <span class="material-symbols-outlined text-[14px]">{{ getChannelIcon(r.channel) }}</span>
                  {{ getChannelLabel(r.channel) }}
                </div>
              </td>
              <td class="px-4 py-3 text-right text-text-primary font-medium">{{ r.views || 0 }}</td>
              <td class="px-4 py-3 text-right text-text-primary font-medium">{{ r.conversions || 0 }}</td>
              <td class="px-4 py-3 text-right">
                <span :class="[
                  'font-medium',
                  r.views > 0 && (r.conversions / r.views) >= 0.1 ? 'text-green-600' : 'text-text-secondary'
                ]">
                  {{ calculateConversionRate(r.views, r.conversions) }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <button @click.stop="openDetail(r)" class="text-primary hover:text-primary-hover text-xs font-medium">
                  {{ $t('common.view') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-gray-100">
        <Pagination :total="total" :page="currentPage" :pageSize="pageSize" @update:page="currentPage = $event" />
      </div>
    </div>

    <!-- Detail Modal -->
    <Teleport to="body">
      <div v-if="showDetailModal && selectedLog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/30" @click="closeDetail"></div>
        <div class="relative w-full max-w-2xl bg-white rounded-lg shadow-xl flex flex-col max-h-[90vh]">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b shrink-0">
            <h3 class="text-lg font-bold text-text-primary">{{ $t('sharelog.detailTitle') }}</h3>
            <button @click="closeDetail" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <!-- Content -->
          <div class="flex-1 overflow-y-auto p-6 space-y-5">
            <!-- Share Information -->
            <div class="bg-gray-50 rounded-lg p-4 space-y-3">
              <p class="text-xs font-medium text-text-secondary uppercase tracking-wider">{{ $t('sharelog.shareInfo') }}</p>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <p class="text-xs text-text-secondary mb-1">{{ $t('sharelog.date') }}</p>
                  <p class="text-sm font-medium text-text-primary">{{ formatDate(selectedLog.created_at) }}</p>
                </div>
                <div>
                  <p class="text-xs text-text-secondary mb-1">{{ $t('sharelog.type') }}</p>
                  <p class="text-sm font-medium text-text-primary">{{ getTypeLabel(selectedLog.type) }}</p>
                </div>
                <div>
                  <p class="text-xs text-text-secondary mb-1">{{ $t('sharelog.target') }}</p>
                  <p class="text-sm font-medium text-text-primary">{{ selectedLog.target_name || '-' }}</p>
                  <p v-if="selectedLog.target_spec" class="text-xs text-text-secondary">{{ selectedLog.target_spec }}</p>
                </div>
                <div>
                  <p class="text-xs text-text-secondary mb-1">{{ $t('sharelog.channel') }}</p>
                  <div :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium', getChannelColor(selectedLog.channel)]">
                    <span class="material-symbols-outlined text-[14px]">{{ getChannelIcon(selectedLog.channel) }}</span>
                    {{ getChannelLabel(selectedLog.channel) }}
                  </div>
                </div>
              </div>
              <div v-if="selectedLog.share_url">
                <p class="text-xs text-text-secondary mb-1">{{ $t('sharelog.shareUrl') }}</p>
                <div class="flex items-center gap-2 bg-white rounded border border-gray-200 px-3 py-2">
                  <span class="material-symbols-outlined text-text-secondary text-[16px]">link</span>
                  <p class="text-xs text-text-primary font-mono flex-1 truncate">{{ selectedLog.share_url }}</p>
                  <button @click="navigator.clipboard.writeText(selectedLog.share_url)" class="text-primary hover:text-primary-hover text-xs">
                    {{ $t('common.copy') }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Performance Metrics -->
            <div>
              <p class="text-sm font-medium text-text-primary mb-3">{{ $t('sharelog.performanceMetrics') }}</p>
              <div class="grid grid-cols-3 gap-4">
                <div class="bg-blue-50 rounded-lg p-4 text-center">
                  <p class="text-xs text-blue-600 mb-1">{{ $t('sharelog.views') }}</p>
                  <p class="text-2xl font-bold text-blue-700">{{ selectedLog.views || 0 }}</p>
                </div>
                <div class="bg-green-50 rounded-lg p-4 text-center">
                  <p class="text-xs text-green-600 mb-1">{{ $t('sharelog.conversions') }}</p>
                  <p class="text-2xl font-bold text-green-700">{{ selectedLog.conversions || 0 }}</p>
                </div>
                <div class="bg-orange-50 rounded-lg p-4 text-center">
                  <p class="text-xs text-orange-600 mb-1">{{ $t('sharelog.conversionRate') }}</p>
                  <p class="text-2xl font-bold text-orange-700">
                    {{ calculateConversionRate(selectedLog.views, selectedLog.conversions) }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Sharer Info -->
            <div v-if="selectedLog.sharer_name || selectedLog.sharer_phone">
              <p class="text-sm font-medium text-text-primary mb-2">{{ $t('sharelog.sharerInfo') }}</p>
              <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-sm text-text-primary">{{ selectedLog.sharer_name || $t('common.anonymous') }}</p>
                <p v-if="selectedLog.sharer_phone" class="text-xs text-text-secondary">{{ selectedLog.sharer_phone }}</p>
              </div>
            </div>

            <!-- Additional Info -->
            <div v-if="selectedLog.note" class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
              <p class="text-xs font-medium text-yellow-800 mb-1">{{ $t('common.remark') }}</p>
              <p class="text-sm text-yellow-900">{{ selectedLog.note }}</p>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t shrink-0 flex justify-end">
            <button @click="closeDetail" class="px-4 py-2 bg-gray-100 text-text-primary rounded-lg text-sm font-medium hover:bg-gray-200">
              {{ $t('common.close') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@media (max-width: 768px) {
  /* Statistics Cards */
  .grid.grid-cols-4 {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
  }


  /* Charts Section */
  .grid.grid-cols-2 {
    grid-template-columns: 1fr;
  }


  /* Top Performers */
  .grid.grid-cols-3 {
    grid-template-columns: 1fr;
  }

  /* Filters */
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-4.mb-6 .flex.flex-wrap.items-center.gap-3 {
    gap: 0.5rem;
  }

  /* Table */
  table {
    font-size: 0.75rem;
  }
  table th,
  table td {
    padding: 0.5rem 0.25rem;
  }

  /* Stats Card text */
  .text-xl {
    font-size: 1.125rem;
  }
  .text-xs {
    font-size: 0.625rem;
  }

  /* Charts */
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-5 {
    padding: 0.75rem;
  }

  /* Modal */
  .fixed.inset-0.z-50.flex.items-center.justify-center.p-4 {
    padding: 0.5rem;
  }
  .fixed.inset-0.z-50.flex.items-center.justify-center.p-4 .bg-white.rounded-lg.shadow-xl {
    max-width: 100%;
    max-height: 95vh;
  }
  .fixed.inset-0.z-50.flex.items-center.justify-center.p-4 .px-6.py-4 {
    padding: 0.75rem;
  }
  .fixed.inset-0.z-50.flex.items-center.justify-center.p-4 .p-6 {
    padding: 0.75rem;
  }
  .fixed.inset-0.z-50.flex.items-center.justify-center.p-4 .grid.grid-cols-2.gap-4 {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }
  .fixed.inset-0.z-50.flex.items-center.justify-center.p-4 .grid.grid-cols-3.gap-4 {
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
  }
  .fixed.inset-0.z-50.flex.items-center.justify-center.p-4 .bg-blue-50.rounded-lg.p-4,
  .fixed.inset-0.z-50.flex.items-center.justify-center.p-4 .bg-green-50.rounded-lg.p-4,
  .fixed.inset-0.z-50.flex.items-center.justify-center.p-4 .bg-orange-50.rounded-lg.p-4 {
    padding: 0.5rem;
  }
  .fixed.inset-0.z-50.flex.items-center.justify-center.p-4 .text-2xl {
    font-size: 1.25rem;
  }
}
</style>

