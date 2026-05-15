<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../stores/user'

import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import Pagination from '../../components/Pagination.vue'
import api from '../../services/api.js'

const { t } = useI18n()
const userStore = useUserStore()

const isAdmin = computed(() => userStore.userRole === 'admin')

// ─── State ──────────────────────────────────────────────────────────────────────
const records = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = 50

const filterChannel = ref('')
const filterDateStart = ref('')
const filterDateEnd = ref('')
const filterKeyword = ref('')

// ─── Stats ──────────────────────────────────────────────────────────────────────
const stats = ref({ total: 0, scan: 0, manual: 0 })

// ─── Fetch ───────────────────────────────────────────────────────────────────────
async function fetchRecords() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize }
    if (filterChannel.value) params.channel = filterChannel.value
    if (filterDateStart.value) params.date_start = filterDateStart.value
    if (filterDateEnd.value) params.date_end = filterDateEnd.value
    if (filterKeyword.value) params.keyword = filterKeyword.value
    const res = await api.get('/retail-records', { params })
    if (res.code === 0) {
      records.value = res.data.list
      total.value = res.data.total
    }
  } finally { loading.value = false }
}

async function fetchStats() {
  const res = await api.get('/retail-records', { params: { size: 1 } })
  if (res.code === 0) stats.value.total = res.data.total
  const s = await api.get('/retail-records', { params: { channel: 'scan', size: 1 } })
  if (s.code === 0) stats.value.scan = s.data.total
  const m = await api.get('/retail-records', { params: { channel: 'manual', size: 1 } })
  if (m.code === 0) stats.value.manual = m.data.total
}

watch([filterChannel, filterDateStart, filterDateEnd, filterKeyword], () => {
  currentPage.value = 1
  fetchRecords()
})
watch(currentPage, fetchRecords)

onMounted(() => { fetchRecords(); fetchStats() })

// ─── Export ──────────────────────────────────────────────────────────────────────
async function exportExcel() {
  const params = {}
  if (filterChannel.value) params.channel = filterChannel.value
  if (filterDateStart.value) params.date_start = filterDateStart.value
  if (filterDateEnd.value) params.date_end = filterDateEnd.value
  // Use backend export endpoint
  const q = new URLSearchParams(params).toString()
  const TOKEN = localStorage.getItem('caimeite_token')
  const res = await fetch(`/api/retail-records/export?${q}`, {
    headers: { Authorization: `Bearer ${TOKEN}` }
  })
  const blob = await res.blob()
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `retail_records_${new Date().toISOString().slice(0, 10)}.xlsx`
  a.click()
  URL.revokeObjectURL(url)
}

// ─── Helpers ─────────────────────────────────────────────────────────────────────
function formatTime(dt) {
  if (!dt) return '-'
  const loc = document.documentElement.lang === 'zh' ? 'zh-CN' : 'en-US'
  return new Date(dt).toLocaleString(loc, { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

// ─── Actions ─────────────────────────────────────────────────────────────────────
async function handleRevoke(record) {
  if (record.type === 'gift') {
    alert(t('retail.giftCannotRevoke'))
    return
  }
  if (!confirm(t('retail.confirmRevoke', { name: record.product_name }))) return

  try {
    const res = await api.put(`/retail-records/${record.id}/revoke`)
    if (res.code === 0) {
      alert(t('retail.revokeSuccess'))
      fetchRecords()
      fetchStats()
    } else {
      alert(res.message || t('retail.revokeFailed'))
    }
  } catch (e) {
    alert(e.message || t('retail.revokeFailed'))
  }
}

async function handleDelete(record) {
  if (!confirm(t('retail.confirmDeleteSale', { type: record.type === 'gift' ? t('retail.typeGift') : t('retail.typeSale'), name: record.product_name }))) return

  try {
    const res = await api.delete(`/retail-records/${record.id}`)
    if (res.code === 0) {
      alert(t('retail.deleteSuccess'))
      fetchRecords()
      fetchStats()
    } else {
      alert(res.message || t('retail.deleteFailed'))
    }
  } catch (e) {
    alert(e.message || t('retail.deleteFailed'))
  }
}
</script>

<template>
  <div>
    <PageHeader :title="$t('retail.title')" :subtitle="$t('retail.subtitle')" />

    <!-- Stats cards -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 flex items-center gap-3">
        <div class="size-10 rounded-lg bg-primary/10 flex items-center justify-center">
          <span class="material-symbols-outlined text-primary text-[20px]">receipt_long</span>
        </div>
        <div>
          <p class="text-xs text-text-secondary">{{ $t('retail.totalRecords') }}</p>
          <p class="text-xl font-bold text-text-primary">{{ stats.total }}</p>
        </div>
      </div>
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 flex items-center gap-3">
        <div class="size-10 rounded-lg bg-green-50 flex items-center justify-center">
          <span class="material-symbols-outlined text-green-600 text-[20px]">qr_code_scanner</span>
        </div>
        <div>
          <p class="text-xs text-text-secondary">{{ $t('retail.scanSales') }}</p>
          <p class="text-xl font-bold text-text-primary">{{ stats.scan }}</p>
        </div>
      </div>
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 flex items-center gap-3">
        <div class="size-10 rounded-lg bg-orange-50 flex items-center justify-center">
          <span class="material-symbols-outlined text-orange-600 text-[20px]">edit_note</span>
        </div>
        <div>
          <p class="text-xs text-text-secondary">{{ $t('retail.manualSales') }}</p>
          <p class="text-xl font-bold text-text-primary">{{ stats.manual }}</p>
        </div>
      </div>
    </div>

    <!-- Filters + Export -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 mb-6">
      <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center bg-gray-50 rounded-lg px-3 py-2 border border-transparent focus-within:border-primary focus-within:bg-white transition-all min-w-[180px]">
          <span class="material-symbols-outlined text-text-secondary text-[18px]">search</span>
          <input v-model="filterKeyword" type="text" :placeholder="$t('retail.searchPlaceholder')" class="bg-transparent border-none focus:ring-0 focus:outline-none text-sm w-full ml-2" />
        </div>
        <select v-model="filterChannel" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('retail.allChannels') }}</option>
          <option value="scan">{{ $t('retail.channelScan') }}</option>
          <option value="manual">{{ $t('retail.channelManual') }}</option>
        </select>
        <input v-model="filterDateStart" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <span class="text-text-secondary text-sm">—</span>
        <input v-model="filterDateEnd" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <button @click="filterChannel=''; filterDateStart=''; filterDateEnd=''; filterKeyword=''" class="text-sm text-text-secondary hover:text-text-primary border border-gray-200 rounded-lg px-3 py-2">
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
              <th class="px-4 py-3 font-medium">{{ $t('retail.time') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('retail.product') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('retail.qrCode') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('retail.channel') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('retail.typeCol') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('retail.operator') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('retail.buyer') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('retail.price') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('retail.note') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('common.action') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="loading">
              <td colspan="10" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('retail.loading') }}</td>
            </tr>
            <tr v-else-if="records.length === 0">
              <td colspan="10" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('retail.noRecords') }}</td>
            </tr>
            <tr v-for="r in records" :key="r.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">{{ formatTime(r.created_at) }}</td>
              <td class="px-4 py-3">
                <p class="font-medium text-text-primary">{{ r.product_name || '-' }}</p>
                <p v-if="r.product_spec" class="text-xs text-text-secondary">{{ r.product_spec }}</p>
              </td>
              <td class="px-4 py-3 font-mono text-xs text-primary">{{ r.qr_code || '-' }}</td>
              <td class="px-4 py-3 text-center">
                <StatusTag
                  :type="r.channel === 'scan' ? 'success' : 'warning'"
                  :text="r.channel === 'scan' ? $t('retail.channelScan') : $t('retail.channelManual')"
                />
              </td>
              <td class="px-4 py-3 text-center">
                <StatusTag
                  :type="r.type === 'gift' ? 'warning' : 'info'"
                  :text="r.type === 'gift' ? $t('retail.typeGift') : $t('retail.typeSale')"
                />
                <StatusTag
                  v-if="r.gift_status"
                  :type="r.gift_status === 'approved' ? 'success' : r.gift_status === 'rejected' ? 'danger' : 'warning'"
                  :text="r.gift_status === 'approved' ? $t('retail.giftStatusApproved') : r.gift_status === 'rejected' ? $t('retail.giftStatusRejected') : $t('retail.giftStatusPending')"
                  class="ml-1"
                />
              </td>
              <td class="px-4 py-3 text-text-secondary text-sm">{{ r.sold_by_name || r.h5_user_name || '-' }}</td>
              <td class="px-4 py-3 text-text-secondary text-sm">
                <span v-if="r.buyer_name || r.buyer_phone || r.h5_user_phone">
                  {{ r.buyer_name || r.h5_user_name || '' }}
                  <span v-if="r.buyer_phone || r.h5_user_phone" class="text-xs block">{{ r.buyer_phone || r.h5_user_phone }}</span>
                </span>
                <span v-else>-</span>
              </td>
              <td class="px-4 py-3 text-right text-text-primary">
                <span v-if="r.type === 'gift'" class="text-orange-500 text-sm">{{ $t('retail.giftLabel') }}</span>
                <span v-else>{{ r.sale_price ? '¥' + Number(r.sale_price).toFixed(2) : '-' }}</span>
              </td>
              <td class="px-4 py-3 text-text-secondary text-xs max-w-[120px] truncate" :title="r.note">{{ r.note || '-' }}</td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button
                    v-if="r.type === 'sale'"
                    @click="handleRevoke(r)"
                    class="text-xs px-2 py-1 text-warning hover:text-orange-700 font-medium"
                  >
                    {{ $t('retail.revoke') }}
                  </button>
                  <button
                    v-if="isAdmin"
                    @click="handleDelete(r)"
                    class="text-xs px-2 py-1 text-danger hover:text-red-700 font-medium"
                  >
                    {{ $t('common.delete') }}
                  </button>
                  <span v-if="r.type === 'gift' && !isAdmin" class="text-xs text-text-secondary">-</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-gray-100">
        <Pagination :total="total" :page="currentPage" :pageSize="pageSize" @update:page="currentPage = $event" />
      </div>
    </div>
  </div>
</template>
