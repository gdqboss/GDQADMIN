<template>
  <div class="import-detail">
    <div class="header">
      <div class="header-left">
        <button class="btn-back" @click="$router.push('/import-records')">← {{ $t('importDetail.back') }}</button>
        <h1>📊 {{ $t('importDetail.pageTitle') }}: {{ record?.file_name }}</h1>
      </div>
      <div class="header-actions">
        <select v-model="sortBy" @change="loadItems" class="sort-select">
          <option value="id">{{ $t('importDetail.sortById') }}</option>
          <option value="amount">{{ $t('importDetail.sortByAmount') }}</option>
          <option value="quantity">{{ $t('importDetail.sortByQty') }}</option>
          <option value="sku">{{ $t('importDetail.sortBySku') }}</option>
          <option value="store_name">{{ $t('importDetail.sortByStore') }}</option>
          <option value="sale_date">{{ $t('importDetail.sortByDate') }}</option>
        </select>
        <select v-model="sortOrder" @change="loadItems" class="sort-select">
          <option value="DESC">{{ $t('importDetail.desc') }}</option>
          <option value="ASC">{{ $t('importDetail.asc') }}</option>
        </select>
        <button class="btn" @click="exportCSV">{{ $t('importDetail.exportCSV') }}</button>
        <button class="btn" @click="showChart = !showChart">{{ showChart ? $t('importDetail.hideChart') : $t('importDetail.showChart') }}</button>
      </div>
    </div>

    <!-- Summary Stats -->
    <div class="stats-grid" v-if="summary">
      <div class="stat-card">
        <div class="stat-value">{{ summary.overall?.total_items?.toLocaleString() }}</div>
        <div class="stat-label">{{ $t('importDetail.totalItems') }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ summary.overall?.unique_skus?.toLocaleString() }}</div>
        <div class="stat-label">{{ $t('importDetail.uniqueSkus') }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">¥{{ summary.overall?.total_amount?.toLocaleString() }}</div>
        <div class="stat-label">{{ $t('importDetail.totalAmount') }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ summary.overall?.unique_stores }}</div>
        <div class="stat-label">{{ $t('importDetail.uniqueStores') }}</div>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <input type="text" v-model="filterSku" :placeholder="$t('importDetail.filterBySku')" class="filter-input" @keyup.enter="loadItems" />
      <input type="text" v-model="filterStore" :placeholder="$t('importDetail.filterByStore')" class="filter-input" @keyup.enter="loadItems" />
      <button class="btn-small" @click="loadItems">{{ $t('importDetail.applyFilter') }}</button>
      <button class="btn-small" @click="clearFilters">{{ $t('importDetail.clearFilter') }}</button>
    </div>

    <!-- Charts -->
    <div v-if="showChart && summary" class="charts-section">
      <!-- Store Distribution -->
      <div class="chart-card" v-if="summary.byStore?.length">
        <h3>🏪 {{ $t('importDetail.storeDistribution') }}</h3>
        <div class="chart-placeholder">
          <div v-for="(s, i) in summary.byStore.slice(0, 10)" :key="i" class="bar-item">
            <span class="bar-label">{{ s.store_name || s.store_code }}</span>
            <div class="bar-wrap">
              <div class="bar" :style="{ width: (s.amount / (summary.byStore[0]?.amount || 1) * 100) + '%' }"></div>
            </div>
            <span class="bar-value">¥{{ s.amount?.toLocaleString() }}</span>
          </div>
        </div>
      </div>
      <!-- Product Top 10 -->
      <div class="chart-card" v-if="summary.byProduct?.length">
        <h3>📦 {{ $t('importDetail.topProducts') }}</h3>
        <div class="chart-placeholder">
          <div v-for="(p, i) in summary.byProduct.slice(0, 10)" :key="i" class="bar-item">
            <span class="bar-label">{{ p.model || p.sku }}</span>
            <div class="bar-wrap">
              <div class="bar bar-blue" :style="{ width: (p.amount / (summary.byProduct[0]?.amount || 1) * 100) + '%' }"></div>
            </div>
            <span class="bar-value">¥{{ p.amount?.toLocaleString() }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Items Table -->
    <div class="report-section">
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ $t('importDetail.image') }}</th>
            <th>SKU</th>
            <th>{{ $t('importDetail.productName') }}</th>
            <th>{{ $t('importDetail.model') }}</th>
            <th>{{ $t('importDetail.store') }}</th>
            <th>{{ $t('importDetail.quantity') }}</th>
            <th>{{ $t('importDetail.unitPrice') }}</th>
            <th>{{ $t('importDetail.amount') }}</th>
            <th>{{ $t('importDetail.color') }}</th>
            <th>{{ $t('importDetail.size') }}</th>
            <th>{{ $t('importDetail.saleDate') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td>
              <img v-if="item.image_url" :src="item.image_url" alt="" class="product-img" />
              <div v-else class="product-img-placeholder">📦</div>
            </td>
            <td class="sku-cell">{{ item.sku }}</td>
            <td>{{ item.product_name || '-' }}</td>
            <td>{{ item.model || '-' }}</td>
            <td>{{ item.store_name || item.store_code || '-' }}</td>
            <td class="num">{{ item.quantity }}</td>
            <td class="num">¥{{ item.unit_price?.toLocaleString() }}</td>
            <td class="num amount-cell">¥{{ item.amount?.toLocaleString() }}</td>
            <td>{{ getColorDisplay(item) || '-' }}</td>
            <td>{{ item.size || '-' }}</td>
            <td>{{ item.sale_date || '-' }}</td>
          </tr>
          <tr v-if="items.length === 0">
            <td colspan="11" class="empty-cell">{{ $t('importDetail.noItems') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="total > pageSize">
      <button class="btn-small" :disabled="page <= 1" @click="loadItems(page - 1)">{{ $t('common.prev') }}</button>
      <span>{{ page }} / {{ Math.ceil(total / pageSize) }}</span>
      <button class="btn-small" :disabled="page >= Math.ceil(total / pageSize)" @click="loadItems(page + 1)">{{ $t('common.next') }}</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import i18n from '@/i18n'
import api from '@/services/api.js'

const { t, locale } = i18n.global

const recordId = window.location.hash.split('/import-detail/').pop()
const record = ref(null)
const items = ref([])
const summary = ref(null)
const page = ref(1)
const pageSize = 50
const total = ref(0)
const sortBy = ref('id')
const sortOrder = ref('DESC')
const filterSku = ref('')
const filterStore = ref('')
const showChart = ref(true)

async function loadRecord() {
  try {
    const res = await api.get(`/import/records/${recordId}`)
    if (res.success) record.value = res.record
  } catch (e) {
    console.error('[ImportDetail] load record error:', e)
  }
}

async function loadItems(p = 1) {
  try {
    const params = {
      page: p, page_size: pageSize, sort_by: sortBy.value, sort_order: sortOrder.value
    }
    if (filterSku.value) params.sku = filterSku.value
    if (filterStore.value) params.store_code = filterStore.value
    const res = await api.get(`/import/records/${recordId}/items`, { params })
    if (res.success) {
      items.value = res.items || []
      total.value = res.total
      page.value = res.page
    }
  } catch (e) {
    console.error('[ImportDetail] load items error:', e)
  }
}

async function loadSummary() {
  try {
    const res = await api.get(`/import/records/${recordId}/summary`)
    if (res.success) summary.value = res.summary
  } catch (e) {
    console.error('[ImportDetail] load summary error:', e)
  }
}

function clearFilters() {
  filterSku.value = ''
  filterStore.value = ''
  loadItems()
}

const COLOR_DISPLAY = {
  RGOLD: 'ROSE GOLD', DGRAY: 'DARK GRAY', IGRAY: 'IRON GRAY',
  DBLUE: 'DARK BLUE', AGREEN: 'ARMY GREEN',
};

function getColorDisplay(item) {
  if (!item.color) return null;
  return COLOR_DISPLAY[item.color] || item.color;
}

function exportCSV() {
  const headers = ['SKU', '商品名称', '型号', '门店', '数量', '单价', '金额', '颜色', '尺寸', '日期', '图片']
  const rows = items.value.map(item => [
    item.sku, item.product_name, item.model, item.store_name || item.store_code,
    item.quantity, item.unit_price, item.amount, item.color, item.size, item.sale_date, item.image_url || ''
  ])
  const csvContent = [headers, ...rows].map(r => r.map(v => `"${(v || '').toString().replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${record.value?.file_name || 'import'}_${Date.now()}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(() => {
  loadRecord()
  loadItems()
  loadSummary()
})
</script>

<style scoped>
.import-detail { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
.header-left { display: flex; align-items: center; gap: 16px; }
.header-left h1 { margin: 0; font-size: 20px; }
.header-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); text-align: center; }
.stat-value { font-size: 24px; font-weight: bold; color: #409eff; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.filter-bar { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.filter-input { padding: 8px 12px; border: 1px solid #dcdfe6; border-radius: 4px; font-size: 14px; min-width: 160px; }
.sort-select { padding: 8px 12px; border: 1px solid #dcdfe6; border-radius: 4px; font-size: 14px; }
.charts-section { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
.chart-card { background: white; border-radius: 8px; padding: 20px; }
.chart-card h3 { margin: 0 0 16px; font-size: 16px; }
.chart-placeholder { display: flex; flex-direction: column; gap: 8px; }
.bar-item { display: flex; align-items: center; gap: 8px; }
.bar-label { width: 80px; font-size: 12px; color: #606266; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bar-wrap { flex: 1; height: 16px; background: #f0f2f5; border-radius: 4px; overflow: hidden; }
.bar { height: 100%; background: linear-gradient(90deg, #67c23a, #95d475); border-radius: 4px; transition: width 0.3s; min-width: 2px; }
.bar-blue { background: linear-gradient(90deg, #409eff, #66b1ff); }
.bar-value { width: 90px; text-align: right; font-size: 12px; color: #606266; }
.report-section { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; min-width: 900px; }
.data-table th, .data-table td { padding: 10px 8px; text-align: left; border-bottom: 1px solid #ebeef5; font-size: 13px; white-space: nowrap; }
.data-table th { background: #fafafa; font-weight: 600; color: #606266; position: sticky; top: 0; }
.data-table tr:hover { background: #f5f7fa; }
.product-img { width: 48px; height: 48px; object-fit: cover; border-radius: 4px; border: 1px solid #ebeef5; }
.product-img-placeholder { width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; background: #f5f7fa; border-radius: 4px; font-size: 20px; }
.sku-cell { font-family: monospace; font-size: 12px; color: #409eff; }
.num { text-align: right; font-family: monospace; }
.amount-cell { color: #e6a23c; font-weight: 600; }
.empty-cell { text-align: center; color: #909399; padding: 40px; }
.pagination { display: flex; justify-content: center; align-items: center; gap: 16px; margin-top: 20px; }
.btn-back { padding: 8px 16px; background: #909399; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
.btn { padding: 8px 16px; background: #409eff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
.btn:hover { background: #66b1ff; }
.btn-small { padding: 6px 12px; background: #409eff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
.btn-small:hover { background: #66b1ff; }
</style>