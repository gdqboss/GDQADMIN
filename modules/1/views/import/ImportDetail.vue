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
          <option value="product_name">{{ $t('importDetail.sortByName') }}</option>
          <option value="color">{{ $t('importDetail.sortByColor') }}</option>
          <option value="size">{{ $t('importDetail.sortBySize') }}</option>
          <option value="sku">{{ $t('importDetail.sortBySku') }}</option>
          <option value="store_name">{{ $t('importDetail.sortByStore') }}</option>
          <option value="sale_date">{{ $t('importDetail.sortByDate') }}</option>
        </select>
        <select v-model="sortOrder" @change="loadItems" class="sort-select">
          <option value="DESC">{{ $t('importDetail.desc') }}</option>
          <option value="ASC">{{ $t('importDetail.asc') }}</option>
        </select>
        <button class="btn" @click="exportCSV">{{ $t('importDetail.exportCSV') }}</button>
        <button class="btn btn-toggle" @click="showChart = !showChart">{{ showChart ? $t('importDetail.hideChart') : $t('importDetail.showChart') }}</button>
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
      <button class="btn-small btn-gray" @click="clearFilters">{{ $t('importDetail.clearFilter') }}</button>
    </div>

    <!-- Charts Section -->
    <div v-if="showChart && summary" class="charts-section">
      
      <!-- 门店分析：TOP + BOTTOM -->
      <div class="chart-card">
        <h3>🏪 {{ $t('importDetail.storeAnalysis') || '门店分析' }}</h3>
        <div class="chart-tabs">
          <button :class="{active: storeTab === 'top'}" @click="storeTab = 'top'">{{ $t('importDetail.hotTop') || '热销 TOP' }}</button>
          <button :class="{active: storeTab === 'bottom'}" @click="storeTab = 'bottom'">{{ $t('importDetail.coldBottom') || '滞销 BOTTOM' }}</button>
        </div>
        <div class="chart-placeholder">
          <div v-for="(s, i) in (storeTab === 'top' ? storeHot : storeCold)" :key="i" class="bar-item">
            <span class="bar-rank">{{ i + 1 }}</span>
            <span class="bar-label" :title="s.store_name || s.store_code">{{ s.store_name || s.store_code || '-' }}</span>
            <div class="bar-wrap">
              <div class="bar" :class="storeTab === 'top' ? 'bar-green' : 'bar-red'" 
                   :style="{ width: getBarWidth(s, storeTab === 'top' ? storeHot : storeCold, 'qty') + '%' }"></div>
            </div>
            <span class="bar-value">{{ Number(s.qty).toLocaleString() }}{{ $t('importDetail.pcs') || '件' }}</span>
            <button class="btn-xs" @click="loadStoreSummary(s.store_code)">📊</button>
          </div>
          <div v-if="(storeTab === 'top' ? storeHot : storeCold).length === 0" class="empty-chart">{{ $t('importDetail.noData') || '暂无数据' }}</div>
        </div>
      </div>

      <!-- 商品分析：TOP + BOTTOM -->
      <div class="chart-card">
        <h3>📦 {{ $t('importDetail.productAnalysis') || '商品分析' }}</h3>
        <div class="chart-tabs">
          <button :class="{active: productTab === 'top'}" @click="productTab = 'top'">{{ $t('importDetail.hotTop') || '爆款 TOP' }}</button>
          <button :class="{active: productTab === 'bottom'}" @click="productTab = 'bottom'">{{ $t('importDetail.coldBottom') || '滞销 BOTTOM' }}</button>
        </div>
        <div class="chart-placeholder">
          <div v-for="(p, i) in (productTab === 'top' ? productHot : productCold)" :key="i" class="bar-item">
            <span class="bar-rank">{{ i + 1 }}</span>
            <span class="bar-label" :title="(p.product_name || p.sku) + ' | ' + p.model">{{ p.model ? p.model.substring(0, 12) : (p.sku || '-') }}</span>
            <span class="bar-sku">{{ p.sku }}</span>
            <div class="bar-wrap">
              <div class="bar" :class="productTab === 'top' ? 'bar-green' : 'bar-red'"
                   :style="{ width: getBarWidth(p, productTab === 'top' ? productHot : productCold, 'qty') + '%' }"></div>
            </div>
            <span class="bar-value">{{ Number(p.qty).toLocaleString() }}{{ $t('importDetail.pcs') || '件' }}</span>
          </div>
          <div v-if="(productTab === 'top' ? productHot : productCold).length === 0" class="empty-chart">{{ $t('importDetail.noData') || '暂无数据' }}</div>
        </div>
      </div>

      <!-- 颜色分析 -->
      <div class="chart-card">
        <h3>🎨 {{ $t('importDetail.colorAnalysis') || '颜色销量分析' }}</h3>
        <div class="chart-placeholder">
          <div v-for="(c, i) in summary.byColor?.slice(0, 12)" :key="i" class="bar-item">
            <span class="bar-rank">{{ i + 1 }}</span>
            <span class="bar-label color-label" :title="getColorDisplay({color: c.color})">{{ getColorDisplay({color: c.color}) || '-' }}</span>
            <div class="bar-wrap">
              <div class="bar bar-purple" :style="{ width: getBarWidth(c, summary.byColor, 'qty') + '%' }"></div>
            </div>
            <span class="bar-value">{{ Number(c.qty).toLocaleString() }}{{ $t('importDetail.pcs') || '件' }}</span>
          </div>
          <div v-if="!summary.byColor?.length" class="empty-chart">{{ $t('importDetail.noData') || '暂无颜色数据' }}</div>
        </div>
      </div>

      <!-- 尺码分析 -->
      <div class="chart-card">
        <h3>📏 {{ $t('importDetail.sizeAnalysis') || '尺码销量分析' }}</h3>
        <div class="chart-placeholder">
          <div v-for="(s, i) in summary.bySize?.slice(0, 12)" :key="i" class="bar-item">
            <span class="bar-rank">{{ i + 1 }}</span>
            <span class="bar-label size-label">{{ s.size || '-' }}</span>
            <div class="bar-wrap">
              <div class="bar bar-orange" :style="{ width: getBarWidth(s, summary.bySize, 'qty') + '%' }"></div>
            </div>
            <span class="bar-value">{{ Number(s.qty).toLocaleString() }}{{ $t('importDetail.pcs') || '件' }}</span>
          </div>
          <div v-if="!summary.bySize?.length" class="empty-chart">{{ $t('importDetail.noData') || '暂无尺码数据' }}</div>
        </div>
      </div>

      <!-- 颜色×尺码 热销组合 -->
      <div class="chart-card chart-wide">
        <h3>🔥 {{ $t('importDetail.colorSizeCombo') || '颜色×尺码 热销组合' }}</h3>
        <div class="matrix-grid">
          <div v-for="(cs, i) in summary.byColorSize?.slice(0, 20)" :key="i" class="matrix-item" :title="`${getColorDisplay({color: cs.color})} + ${cs.size}`">
            <span class="matrix-rank">#{{ i + 1 }}</span>
            <span class="matrix-color">{{ getColorDisplay({color: cs.color })?.substring(0, 8) }}</span>
            <span class="matrix-size">{{ cs.size }}</span>
            <span class="matrix-qty">{{ Number(cs.qty).toLocaleString() }}件</span>
          </div>
          <div v-if="!summary.byColorSize?.length" class="empty-chart">暂无组合数据</div>
        </div>
      </div>

<!-- 型号总销量 TOP20 + BOTTOM20 -->
      <div class="chart-card chart-wide">
        <h3>🏆 {{ $t('importDetail.modelAnalysis') || '型号分析' }}</h3>
        <div class="chart-tabs">
          <button :class="{active: modelTab === 'top'}" @click="modelTab = 'top'">{{ $t('importDetail.hotTop') || '爆款 TOP' }}</button>
          <button :class="{active: modelTab === 'bottom'}" @click="modelTab = 'bottom'">{{ $t('importDetail.coldBottom') || '滞销 BOTTOM' }}</button>
        </div>
        <div class="chart-placeholder">
          <div v-for="(m, i) in (modelTab === 'top' ? topModels : bottomModels)" :key="i" class="bar-item">
            <span class="bar-rank">{{ i + 1 }}</span>
            <span class="bar-label" :title="m.model">{{ m.model ? m.model.substring(0, 16) : '-' }}</span>
            <span class="bar-sku">{{ m.sku_count }}{{ $t('importDetail.skuCount') || 'SKU' }}</span>
            <div class="bar-wrap">
              <div class="bar" :class="modelTab === 'top' ? 'bar-green' : 'bar-red'" :style="{ width: getBarWidth(m, modelTab === 'top' ? topModels : bottomModels, 'qty') + '%' }"></div>
            </div>
            <span class="bar-value">{{ Number(m.qty).toLocaleString() }}{{ $t('importDetail.pcs') || '件' }}</span>
          </div>
          <div v-if="(modelTab === 'top' ? topModels : bottomModels).length === 0" class="empty-chart">{{ $t('importDetail.noData') || '暂无数据' }}</div>
        </div>
      </div>
    </div>

    <!-- 门店下钻详情面板 -->
    <div v-if="storeDetail" class="store-detail-panel">
      <div class="store-detail-header">
        <div class="store-detail-info">
          <h2>🏪 {{ storeDetail.store?.store_name || storeDetail.store?.store_code }}</h2>
          <span class="store-code-tag">{{ storeDetail.store?.store_code }}</span>
        </div>
        <div class="store-detail-stats">
          <span class="stat-chip">销量 <strong>{{ Number(storeDetail.store?.total_qty || 0).toLocaleString() }}</strong> 件</span>
          <span class="stat-chip">销售额 <strong>¥{{ Number(storeDetail.store?.total_amount || 0).toLocaleString() }}</strong></span>
          <span class="stat-chip">型号 <strong>{{ storeDetail.store?.model_count }}</strong> 个</span>
          <span class="stat-chip">SKU <strong>{{ storeDetail.store?.sku_count }}</strong> 个</span>
        </div>
        <button class="btn-small btn-gray" @click="storeDetail = null">✕ 关闭</button>
      </div>

      <!-- 型号分布 -->
      <div class="report-section">
        <h3>📦 型号分布</h3>
        <div class="model-bar-list">
          <div v-for="(m, i) in storeDetail.byModel" :key="i" class="model-bar-item">
            <span class="model-rank">#{{ i + 1 }}</span>
            <span class="model-name" :title="m.model">{{ m.model || '-' }}</span>
            <span class="model-skus">{{ m.sku_count }} SKU</span>
            <div class="bar-wrap" style="max-width: 300px;">
              <div class="bar bar-green" :style="{ width: getBarWidth(m, storeDetail.byModel, 'qty') + '%' }"></div>
            </div>
            <span class="model-qty">{{ Number(m.qty).toLocaleString() }}件</span>
          </div>
          <div v-if="!storeDetail.byModel?.length" class="empty-section">暂无型号数据</div>
        </div>
      </div>

      <!-- 颜色×尺码 矩阵 -->
      <div class="report-section">
        <h3>🔥 颜色 × 尺码 热销组合</h3>
        <div class="matrix-grid">
          <div v-for="(cs, i) in storeDetail.byColorSize" :key="i" class="matrix-item"
               :title="`${getColorDisplay({color: cs.color})} + ${cs.size}`">
            <span class="matrix-rank">#{{ i + 1 }}</span>
            <span class="matrix-color">{{ getColorDisplay({color: cs.color})?.substring(0, 8) || '-' }}</span>
            <span class="matrix-size">{{ cs.size }}</span>
            <span class="matrix-qty">{{ Number(cs.qty).toLocaleString() }}件</span>
          </div>
          <div v-if="!storeDetail.byColorSize?.length" class="empty-section">暂无组合数据</div>
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
import { ref, computed, onMounted } from 'vue'
import i18n from '@/i18n'
import api from '@/services/api.js'

const { t, locale } = i18n.global

const recordId = window.location.hash.split('/import-detail/').pop()
const record = ref(null)
const items = ref([])
const summary = ref(null)
const storeDetail = ref(null) // 门店下钻数据
const page = ref(1)
const pageSize = 50
const total = ref(0)
const sortBy = ref('quantity')
const sortOrder = ref('DESC')
const filterSku = ref('')
const filterStore = ref('')
const showChart = ref(true)
const storeTab = ref('top')
const productTab = ref('top')
const modelTab = ref('top')

// 型号热销/滞销
const topModels = computed(() => summary.value?.topModels || [])
const bottomModels = computed(() => summary.value?.bottomModels || [])

// 门店热销/滞销
const storeHot = computed(() => summary.value?.byStore?.slice(0, 20) || [])
const storeCold = computed(() => {
  const arr = summary.value?.byStore || []
  return arr.slice(-20).reverse()
})

// 商品热销/滞销
const productHot = computed(() => summary.value?.topProducts || [])
const productCold = computed(() => summary.value?.bottomProducts || [])

function getBarWidth(item, arr, field = 'qty') {
  if (!arr || !arr.length) return 0
  const max = Math.max(...arr.map(a => Number(a[field] || 0)))
  if (!max) return 0
  return Math.round((Number(item[field] || 0) / max) * 100)
}

function formatDateShort(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return `${d.getMonth() + 1}/${d.getDate()}`
}

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

async function loadStoreSummary(storeCode) {
  try {
    const res = await api.get(`/import/records/${recordId}/summary/by-store/${storeCode}`)
    if (res.success) storeDetail.value = res
  } catch (e) {
    console.error('[ImportDetail] load store summary error:', e)
  }
}

function clearFilters() {
  filterSku.value = ''
  filterStore.value = ''
  loadItems()
}

const COLOR_DISPLAY = {
  RGOLD: 'ROSE GOLD', DGRAY: 'DARK GRAY', IGRAY: 'IRON GRAY',
  DBLUE: 'DARK BLUE', AGREEN: 'ARMY GREEN', BRONZE: 'BRONZE',
  NAVY: 'NAVY', BURGUNDY: 'BURGUNDY', BEIGE: 'BEIGE',
  MILITARY: 'MILITARY', KHAKI: 'KHAKI', VIOLET: 'VIOLET',
};

function getColorDisplay(item) {
  if (!item.color) return null
  return COLOR_DISPLAY[item.color] || item.color
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
.header-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); text-align: center; }
.stat-value { font-size: 24px; font-weight: bold; color: #409eff; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.filter-bar { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
.filter-input { padding: 8px 12px; border: 1px solid #dcdfe6; border-radius: 4px; font-size: 14px; min-width: 150px; }
.sort-select { padding: 8px 12px; border: 1px solid #dcdfe6; border-radius: 4px; font-size: 14px; }

.charts-section { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.chart-card { background: white; border-radius: 8px; padding: 16px; }
.chart-wide { grid-column: span 2; }
.chart-card h3 { margin: 0 0 12px; font-size: 15px; color: #303133; }

.chart-tabs { display: flex; gap: 8px; margin-bottom: 12px; }
.chart-tabs button { padding: 4px 12px; border: 1px solid #dcdfe6; border-radius: 4px; background: white; cursor: pointer; font-size: 12px; }
.chart-tabs button.active { background: #409eff; color: white; border-color: #409eff; }

.chart-placeholder { display: flex; flex-direction: column; gap: 6px; }
.bar-item { display: flex; align-items: center; gap: 6px; }
.bar-rank { width: 18px; font-size: 11px; color: #909399; text-align: center; }
.bar-label { width: 50px; font-size: 11px; color: #606266; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bar-sku { width: 70px; font-size: 10px; color: #409eff; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bar-label.color-label { width: 60px; }
.bar-label.size-label { width: 40px; text-align: center; }
.bar-wrap { flex: 1; height: 14px; background: #f0f2f5; border-radius: 3px; overflow: hidden; }
.bar { height: 100%; border-radius: 3px; transition: width 0.3s; min-width: 2px; }
.bar-green { background: linear-gradient(90deg, #67c23a, #95d475); }
.bar-red { background: linear-gradient(90deg, #f56c6c, #f89898); }
.bar-purple { background: linear-gradient(90deg, #9c27b0, #ce93d8); }
.bar-orange { background: linear-gradient(90deg, #ff9800, #ffb74d); }
.bar-value { width: 60px; text-align: right; font-size: 11px; color: #606266; }
.empty-chart { text-align: center; color: #909399; padding: 20px; font-size: 13px; }

.matrix-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
.matrix-item { background: #f5f7fa; border-radius: 6px; padding: 8px; text-align: center; }
.matrix-rank { display: block; font-size: 10px; color: #909399; }
.matrix-color { display: block; font-size: 12px; color: #303133; font-weight: 600; }
.matrix-size { display: block; font-size: 14px; color: #606266; margin: 2px 0; }
.matrix-qty { display: block; font-size: 11px; color: #67c23a; font-weight: bold; }

.trend-bars { display: flex; align-items: flex-end; gap: 6px; height: 100px; }
.trend-item { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; }
.trend-bar-wrap { width: 100%; height: 80px; display: flex; align-items: flex-end; }
.trend-bar { width: 100%; background: linear-gradient(180deg, #409eff, #66b1ff); border-radius: 2px 2px 0 0; min-height: 2px; }
.trend-label { font-size: 10px; color: #909399; margin-top: 4px; }
.trend-qty { font-size: 9px; color: #606266; }

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
.btn-toggle { background: #67c23a; }
.btn-toggle:hover { background: #85ce61; }
.btn-small { padding: 6px 12px; background: #409eff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
.btn-small:hover { background: #66b1ff; }
.btn-gray { background: #909399; }
.btn-gray:hover { background: #a6a9ad; }
.btn-xs { padding: 2px 6px; background: #409eff; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px; margin-left: 4px; }
.btn-xs:hover { background: #66b1ff; }

/* 门店下钻面板 */
.store-detail-panel { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; border: 2px solid #409eff; }
.store-detail-header { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; background: #ecf5ff; border-radius: 8px; padding: 16px; }
.store-detail-info { display: flex; align-items: center; gap: 10px; }
.store-detail-info h2 { margin: 0; font-size: 18px; }
.store-code-tag { background: #409eff; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
.store-detail-stats { display: flex; gap: 16px; flex-wrap: wrap; flex: 1; }
.stat-chip { background: white; padding: 6px 14px; border-radius: 20px; font-size: 13px; color: #606266; border: 1px solid #dcdfe6; }
.stat-chip strong { color: #409eff; font-size: 15px; }
.model-bar-list { display: flex; flex-direction: column; gap: 8px; }
.model-bar-item { display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: #f5f7fa; border-radius: 6px; }
.model-rank { width: 28px; color: #909399; font-size: 12px; font-weight: 600; }
.model-name { width: 120px; font-size: 13px; font-weight: 600; color: #303133; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.model-skus { width: 60px; font-size: 11px; color: #909399; }
.model-qty { width: 60px; text-align: right; font-size: 13px; color: #67c23a; font-weight: bold; }
.empty-section { text-align: center; color: #909399; padding: 24px; font-size: 13px; }
</style>