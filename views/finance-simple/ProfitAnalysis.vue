<script setup>
import { ref, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'

const { t } = useI18n()

const loading = ref(false)
const dimension = ref('product')
const dateStart = ref('')
const dateEnd = ref('')
const filterStore = ref('')
const filterProduct = ref('')

const analysisData = ref([])
const stores = ref([])
const products = ref([])
const exporting = ref(false)

async function fetchAnalysis() {
  loading.value = true
  try {
    const params = { dimension: dimension.value }
    if (dateStart.value) params.date_start = dateStart.value
    if (dateEnd.value) params.date_end = dateEnd.value
    if (filterStore.value) params.store_id = filterStore.value
    if (filterProduct.value) params.product_id = filterProduct.value
    const res = await api.get('/finance-simple/profit-analysis', { params })
    if (res.code === 0) {
      analysisData.value = res.data
    }
  } finally { loading.value = false }
}

async function fetchStores() {
  const res = await api.get('/stores')
  if (res.code === 0) stores.value = res.data
}

async function fetchProducts() {
  const res = await api.get('/products', { params: { size: 1000 } })
  if (res.code === 0) products.value = res.data.list
}

watch([dimension, dateStart, dateEnd, filterStore, filterProduct], fetchAnalysis)

onMounted(() => {
  fetchAnalysis()
  fetchStores()
  fetchProducts()
})

function formatMoney(val) {
  return '¥' + Number(val || 0).toFixed(2)
}

function formatDate(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleDateString('zh-CN')
}

function getProfitMargin(revenue, profit) {
  if (revenue === 0) return '0.00'
  return ((profit / revenue) * 100).toFixed(2)
}

async function exportExcel() {
  exporting.value = true
  try {
    const params = { dimension: dimension.value }
    if (dateStart.value) params.date_start = dateStart.value
    if (dateEnd.value) params.date_end = dateEnd.value
    if (filterStore.value) params.store_id = filterStore.value
    if (filterProduct.value) params.product_id = filterProduct.value

    const res = await api.get('/finance-simple/export/profit-analysis', { params, responseType: 'blob' })

    const url = window.URL.createObjectURL(new Blob([res]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `${t('financeSimple.profitAnalysisExportFilename')}_${new Date().toISOString().slice(0, 10)}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (err) {
    alert(err.message || t('financeSimple.exportFailed'))
  } finally {
    exporting.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader :title="$t('financeSimple.profitAnalysis')" :subtitle="$t('financeSimple.profitAnalysisSubtitle')" />

    <!-- Filters -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 mb-6">
      <div class="flex flex-wrap items-center gap-3 mb-4">
        <span class="text-sm font-medium text-text-primary">{{ $t('financeSimple.analysisDimension') }}:</span>
        <div class="flex gap-2">
          <button @click="dimension = 'product'" :class="dimension === 'product' ? 'bg-primary text-white' : 'bg-gray-100 text-text-secondary'" class="px-4 py-2 rounded-lg text-sm transition-colors">
            {{ $t('financeSimple.byProduct') }}
          </button>
          <button @click="dimension = 'store'" :class="dimension === 'store' ? 'bg-primary text-white' : 'bg-gray-100 text-text-secondary'" class="px-4 py-2 rounded-lg text-sm transition-colors">
            {{ $t('financeSimple.byStore') }}
          </button>
          <button @click="dimension = 'date'" :class="dimension === 'date' ? 'bg-primary text-white' : 'bg-gray-100 text-text-secondary'" class="px-4 py-2 rounded-lg text-sm transition-colors">
            {{ $t('financeSimple.byDate') }}
          </button>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <select v-if="dimension !== 'store'" v-model="filterStore" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('financeSimple.allStores') }}</option>
          <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <select v-if="dimension !== 'product'" v-model="filterProduct" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('financeSimple.allProducts') }}</option>
          <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <input v-model="dateStart" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <span class="text-text-secondary">—</span>
        <input v-model="dateEnd" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <button @click="dateStart=''; dateEnd=''; filterStore=''; filterProduct=''" class="text-sm text-text-secondary hover:text-text-primary border border-gray-200 rounded-lg px-3 py-2">
          {{ $t('common.reset') }}
        </button>
        <button @click="exportExcel" :disabled="exporting" class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors ml-auto">
          <span class="material-symbols-outlined text-sm">download</span>
          {{ exporting ? $t('common.exporting') : $t('common.exportExcel') }}
        </button>
      </div>
    </div>

    <!-- Analysis Table -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">
                <span v-if="dimension === 'product'">{{ $t('financeSimple.product') }}</span>
                <span v-else-if="dimension === 'store'">{{ $t('financeSimple.store') }}</span>
                <span v-else>{{ $t('financeSimple.date') }}</span>
              </th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.salesCount') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.totalQuantity') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.totalRevenue') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.totalCost') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.grossProfit') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.profitMargin') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.avgSalePrice') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="loading">
              <td colspan="8" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('common.loading') }}</td>
            </tr>
            <tr v-else-if="analysisData.length === 0">
              <td colspan="8" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('common.noData') }}</td>
            </tr>
            <tr v-for="(item, idx) in analysisData" :key="idx" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3">
                <div v-if="dimension === 'product'">
                  <p class="font-medium text-text-primary">{{ item.product_name }}</p>
                  <p v-if="item.product_spec" class="text-xs text-text-secondary">{{ item.product_spec }}</p>
                </div>
                <div v-else-if="dimension === 'store'">
                  <p class="font-medium text-text-primary">{{ item.store_name || '-' }}</p>
                </div>
                <div v-else>
                  <p class="font-medium text-text-primary">{{ formatDate(item.sale_date) }}</p>
                </div>
              </td>
              <td class="px-4 py-3 text-right text-text-primary">{{ item.sales_count }}</td>
              <td class="px-4 py-3 text-right text-text-primary">{{ item.total_quantity }}</td>
              <td class="px-4 py-3 text-right text-text-primary">{{ formatMoney(item.total_revenue) }}</td>
              <td class="px-4 py-3 text-right text-text-secondary">{{ formatMoney(item.total_cost) }}</td>
              <td class="px-4 py-3 text-right font-semibold" :class="item.gross_profit >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ formatMoney(item.gross_profit) }}
              </td>
              <td class="px-4 py-3 text-right font-medium" :class="item.gross_profit >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ getProfitMargin(item.total_revenue, item.gross_profit) }}%
              </td>
              <td class="px-4 py-3 text-right text-text-secondary">{{ formatMoney(item.avg_sale_price) }}</td>
            </tr>
          </tbody>
          <tfoot v-if="analysisData.length > 0" class="bg-gray-50 font-semibold">
            <tr>
              <td class="px-4 py-3 text-text-primary">{{ $t('common.total') }}</td>
              <td class="px-4 py-3 text-right text-text-primary">{{ analysisData.reduce((sum, item) => sum + item.sales_count, 0) }}</td>
              <td class="px-4 py-3 text-right text-text-primary">{{ analysisData.reduce((sum, item) => sum + item.total_quantity, 0) }}</td>
              <td class="px-4 py-3 text-right text-text-primary">{{ formatMoney(analysisData.reduce((sum, item) => sum + Number(item.total_revenue), 0)) }}</td>
              <td class="px-4 py-3 text-right text-text-secondary">{{ formatMoney(analysisData.reduce((sum, item) => sum + Number(item.total_cost), 0)) }}</td>
              <td class="px-4 py-3 text-right" :class="analysisData.reduce((sum, item) => sum + Number(item.gross_profit), 0) >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ formatMoney(analysisData.reduce((sum, item) => sum + Number(item.gross_profit), 0)) }}
              </td>
              <td class="px-4 py-3 text-right" :class="analysisData.reduce((sum, item) => sum + Number(item.gross_profit), 0) >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ getProfitMargin(analysisData.reduce((sum, item) => sum + Number(item.total_revenue), 0), analysisData.reduce((sum, item) => sum + Number(item.gross_profit), 0)) }}%
              </td>
              <td class="px-4 py-3"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</template>
