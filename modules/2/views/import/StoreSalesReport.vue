<template>
  <div class="store-sales-report">
    <!-- 顶部标题 -->
    <div class="header">
      <div class="header-left">
        <button v-if="currentStore" class="btn-back" @click="backToList">← 返回列表</button>
        <h1>🏪 {{ currentStore ? currentStore.store_name + ' 销售明细' : $t('storeSales.title') }}</h1>
      </div>
      <div class="header-actions">
        <select v-if="!currentStore" v-model="sortBy" @change="loadStoreSales" class="sort-select">
          <option value="total_qty">{{ $t('storeSales.sortByQty') || '按销量' }}</option>
          <option value="total_amount">{{ $t('storeSales.sortByAmount') || '按销售额' }}</option>
          <option value="sku_count">{{ $t('storeSales.sortBySku') || '按SKU数' }}</option>
          <option value="model_count">{{ $t('storeSales.sortByModel') || '按型号数' }}</option>
        </select>
        <button v-if="!currentStore" class="btn" @click="showChart = !showChart">
          {{ showChart ? $t('storeSales.hideChart') : $t('storeSales.showChart') }}
        </button>
      </div>
    </div>

    <!-- 聚合统计 -->
    <div v-if="!currentStore && overall" class="stats-grid">
      <div class="stat-card">
        <div class="stat-value">{{ overall.store_count }}</div>
        <div class="stat-label">{{ $t('storeSales.totalStores') }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ Number(overall.grand_qty).toLocaleString() }}</div>
        <div class="stat-label">{{ $t('storeSales.totalQty') }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">¥{{ Number(overall.grand_amount).toLocaleString() }}</div>
        <div class="stat-label">{{ $t('storeSales.totalAmount') }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ overall.grand_models }}</div>
        <div class="stat-label">{{ $t('storeSales.totalModels') }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ overall.grand_skus }}</div>
        <div class="stat-label">{{ $t('storeSales.totalSkus') }}</div>
      </div>
    </div>

    <!-- 门店列表 -->
    <div v-if="!currentStore" class="report-section">
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ $t('storeSales.storeCode') }}</th>
            <th>{{ $t('storeSales.totalQty') }}</th>
            <th>{{ $t('storeSales.totalAmount') }}</th>
            <th>{{ $t('storeSales.modelCount') }}</th>
            <th>{{ $t('storeSales.skuCount') }}</th>
            <th>{{ $t('storeSales.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="store in storeList" :key="store.store_code" class="store-row">
            <td class="store-name-cell">
              <span class="store-code">{{ store.store_code }}</span>
              <span class="store-name">{{ store.store_name }}</span>
            </td>
            <td class="num highlight">{{ Number(store.total_qty).toLocaleString() }}</td>
            <td class="num">¥{{ Number(store.total_amount).toLocaleString() }}</td>
            <td class="num">{{ store.model_count }}</td>
            <td class="num">{{ store.sku_count }}</td>
            <td>
              <button class="btn-small" @click="viewStoreDetail(store)">{{ $t('storeSales.viewDetail') }}</button>
            </td>
          </tr>
          <tr v-if="storeList.length === 0">
            <td colspan="6" class="empty-cell">{{ $t('storeSales.noData') || '暂无数据' }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 门店销售明细 -->
    <div v-if="currentStore">
      <!-- 门店聚合卡 -->
      <div class="store-summary">
        <div class="store-info">
          <h2>{{ currentStore.store_name }}</h2>
          <span class="store-code-badge">{{ currentStore.store_code }}</span>
        </div>
        <div class="store-stats">
          <div class="stat-item">
            <div class="stat-value">{{ Number(currentStore.total_qty).toLocaleString() }}</div>
            <div class="stat-label">{{ $t('storeSales.totalQty') }}</div>
          </div>
          <div class="stat-item">
            <div class="stat-value">¥{{ Number(currentStore.total_amount).toLocaleString() }}</div>
            <div class="stat-label">{{ $t('storeSales.totalAmount') }}</div>
          </div>
          <div class="stat-item">
            <div class="stat-value">{{ currentStore.model_count }}</div>
            <div class="stat-label">{{ $t('storeSales.modelCount') }}</div>
          </div>
          <div class="stat-item">
            <div class="stat-value">{{ currentStore.sku_count }}</div>
            <div class="stat-label">{{ $t('storeSales.skuCount') }}</div>
          </div>
        </div>
      </div>

      <!-- 型号汇总 -->
      <div class="report-section">
        <h3>📦 {{ $t('storeSales.modelSummary') }}</h3>
        <div class="model-grid" v-if="byModel.length">
          <div v-for="(m, i) in byModel" :key="i" class="model-card"
               :class="{ 'has-image': !!m._img }"
               @click="viewModelSkus(m)">
            <div class="model-rank">#{{ i + 1 }}</div>
            <img v-if="m._img" :src="m._img" class="model-thumb" alt=""
                 @error="m._img = null" />
            <div v-if="!m._img" class="model-thumb-placeholder">📦</div>
            <div class="model-info">
              <div class="model-name" :title="m.model">{{ m.model || '-' }}</div>
              <div class="model-meta">
                <span>{{ m.sku_count }} SKU</span>
                <span>¥{{ Number(m.total_amount).toLocaleString() }}</span>
              </div>
            </div>
            <div class="model-qty">{{ Number(m.total_qty).toLocaleString() }}{{ $t('storeSales.pcs') || '件' }}</div>
          </div>
        </div>
        <div v-else class="empty-section">{{ $t('storeSales.noData') }}</div>
      </div>

      <!-- SKU汇总（可点击型号过滤 or 多SKU码展开） -->
      <div class="report-section">
        <div class="section-header">
          <h3>🏷️ {{ $t('storeSales.skuSummary') }}</h3>
          <div class="section-actions" v-if="currentModelFilter">
            <span class="filter-tag">{{ $t('storeSales.model') }}: <strong>{{ currentModelFilter }}</strong></span>
            <button class="btn-small" @click="clearModelFilter">{{ $t('common.clear') || '清除' }}</button>
          </div>
        </div>
        <div class="sku-list" v-if="filteredSku.length">
          <div v-for="(s, i) in filteredSku" :key="i" class="sku-item"
               @click="expandSkuDetail(s)">
            <img v-if="s._img" :src="s._img" class="sku-thumb" alt=""
                 @error="s._img = null" />
            <div v-if="!s._img" class="sku-thumb-placeholder">🏷️</div>
            <span class="sku-rank">{{ i + 1 }}</span>
            <span class="sku-val">{{ s.sku }}</span>
            <span class="sku-name">{{ s.product_name || '-' }}</span>
            <span class="sku-model">{{ s.model || '-' }}</span>
            <span class="sku-color">{{ getColorDisplay(s.color) || '-' }}</span>
            <span class="sku-size">{{ s.size || '-' }}</span>
            <span class="sku-qty">{{ Number(s.total_qty).toLocaleString() }}{{ $t('storeSales.pcs') || '件' }}</span>
            <span class="sku-amount">¥{{ Number(s.total_amount).toLocaleString() }}</span>
          </div>
        </div>
        <div v-else class="empty-section">{{ $t('storeSales.noData') }}</div>
      </div>

      <!-- SKU展开详情（多门店/多情况/图片） -->
      <div class="report-section" v-if="expandedSku">
        <h3>🔍 {{ $t('storeSales.skuDetail') || 'SKU 明细' }}: {{ expandedSku.sku }}</h3>
        <div class="sku-detail">
          <img v-if="expandedSku._img" :src="expandedSku._img" class="sku-detail-img" alt=""
               @error="expandedSku._img = null" />
          <div class="sku-detail-info">
            <div class="detail-row"><span class="label">{{ $t('storeSales.sku') || 'SKU' }}:</span> <span class="val">{{ expandedSku.sku }}</span></div>
            <div class="detail-row"><span class="label">{{ $t('storeSales.model') || '型号' }}:</span> <span class="val">{{ expandedSku.model }}</span></div>
            <div class="detail-row"><span class="label">{{ $t('storeSales.productName') || '商品名称' }}:</span> <span class="val">{{ expandedSku.product_name }}</span></div>
            <div class="detail-row"><span class="label">{{ $t('storeSales.color') || '颜色' }}:</span> <span class="val">{{ getColorDisplay(expandedSku.color) }}</span></div>
            <div class="detail-row"><span class="label">{{ $t('storeSales.size') || '尺码' }}:</span> <span class="val">{{ expandedSku.size }}</span></div>
            <div class="detail-row"><span class="label">{{ $t('storeSales.totalQty') || '总销量' }}:</span> <span class="val highlight">{{ Number(expandedSku.total_qty).toLocaleString() }}{{ $t('storeSales.pcs') || '件' }}</span></div>
            <div class="detail-row"><span class="label">{{ $t('storeSales.totalAmount') || '总销售额' }}:</span> <span class="val amount">¥{{ Number(expandedSku.total_amount).toLocaleString() }}</span></div>
            <div class="detail-row"><span class="label">{{ $t('storeSales.storeCount') || '涉及门店' }}:</span> <span class="val">{{ expandedSku.store_count }}{{ $t('storeSales.stores') || '个' }}</span></div>
          </div>
        </div>
        <button class="btn-small" style="margin-top:12px" @click="expandedSku = null">{{ $t('common.close') || '关闭' }}</button>
      </div>

      <!-- 原始明细 -->
      <div class="report-section">
        <h3>📋 {{ $t('storeSales.rawItems') || '原始明细' }}</h3>
        <div class="filter-bar">
          <select v-model="itemSortBy" @change="loadStoreItems(1)" class="sort-select">
            <option value="quantity">{{ $t('storeSales.sortByQty') || '按销量' }}</option>
            <option value="amount">{{ $t('storeSales.sortByAmount') || '按金额' }}</option>
            <option value="sku">{{ $t('storeSales.sortBySku') || '按SKU' }}</option>
            <option value="model">{{ $t('storeSales.sortByModel') || '按型号' }}</option>
            <option value="color">{{ $t('storeSales.sortByColor') || '按颜色' }}</option>
            <option value="size">{{ $t('storeSales.sortBySize') || '按尺码' }}</option>
          </select>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>SKU</th>
              <th>{{ $t('storeSales.productName') }}</th>
              <th>{{ $t('storeSales.model') }}</th>
              <th>{{ $t('storeSales.color') }}</th>
              <th>{{ $t('storeSales.size') }}</th>
              <th>{{ $t('storeSales.quantity') }}</th>
              <th>{{ $t('storeSales.unitPrice') }}</th>
              <th>{{ $t('storeSales.amount') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td class="sku-cell">{{ item.sku }}</td>
              <td>{{ item.product_name || '-' }}</td>
              <td>{{ item.model || '-' }}</td>
              <td>{{ getColorDisplay(item.color) || '-' }}</td>
              <td>{{ item.size || '-' }}</td>
              <td class="num">{{ item.quantity }}</td>
              <td class="num">¥{{ item.unit_price?.toLocaleString() }}</td>
              <td class="num amount-cell">¥{{ item.amount?.toLocaleString() }}</td>
            </tr>
            <tr v-if="items.length === 0">
              <td colspan="8" class="empty-cell">{{ $t('storeSales.noData') || '暂无数据' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 分页 -->
      <div class="pagination" v-if="totalItems > pageSize">
        <button class="btn-small" :disabled="itemPage <= 1" @click="loadStoreItems(itemPage - 1)">{{ $t('common.prev') }}</button>
        <span>{{ itemPage }} / {{ Math.ceil(totalItems / pageSize) }}</span>
        <button class="btn-small" :disabled="itemPage >= Math.ceil(totalItems / pageSize)" @click="loadStoreItems(itemPage + 1)">{{ $t('common.next') }}</button>
      </div>
    </div>

    <!-- 列表分页 -->
    <div class="pagination" v-if="!currentStore && totalStores > pageSize">
      <button class="btn-small" :disabled="page <= 1" @click="loadStoreSales(page - 1)">{{ $t('common.prev') }}</button>
      <span>{{ page }} / {{ Math.ceil(totalStores / pageSize) }}</span>
      <button class="btn-small" :disabled="page >= Math.ceil(totalStores / pageSize)" @click="loadStoreSales(page + 1)">{{ $t('common.next') }}</button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import i18n from '@/i18n'
import api from '@/services/api.js'

const { t, locale } = i18n.global

const storeList = ref([])
const overall = ref(null)
const currentStore = ref(null)
const byModel = ref([])
const bySku = ref([])
const items = ref([])
const page = ref(1)
const pageSize = 50
const totalStores = ref(0)
const sortBy = ref('total_qty')

// 明细页
const itemPage = ref(1)
const pageSizeItems = 50
const totalItems = ref(0)
const itemSortBy = ref('quantity')

// 展开/过滤
const currentModelFilter = ref('')
const expandedSku = ref(null)

const showChart = ref(false)

// 型号点击 → 过滤SKU列表
function viewModelSkus(m) {
  currentModelFilter.value = m.model
  expandedSku.value = null
}

function clearModelFilter() {
  currentModelFilter.value = ''
}

// SKU点击 → 展开详情
function expandSkuDetail(s) {
  expandedSku.value = s
}

const filteredSku = computed(() => {
  if (!currentModelFilter.value) return bySku.value
  return bySku.value.filter(s => s.model === currentModelFilter.value)
})

async function loadStoreSales(p = 1) {
  try {
    const res = await api.get('/excel-report/store-sales', {
      params: { page: p, pageSize, sort_by: sortBy.value }
    })
    if (res.code === 0) {
      storeList.value = res.data || []
      totalStores.value = res.total
      page.value = res.page
      if (res.overall) overall.value = res.overall
    }
  } catch (e) {
    console.error('[StoreSales] load error:', e)
  }
}

async function viewStoreDetail(store) {
  currentStore.value = store
  itemPage.value = 1
  await Promise.all([loadStoreItems(1), loadStoreAgg()])
}

async function loadStoreItems(p = 1) {
  try {
    const res = await api.get(`/excel-report/store-sales/${currentStore.value.store_code}/items`, {
      params: { page: p, pageSize: pageSizeItems, sort_by: itemSortBy.value }
    })
    if (res.code === 0) {
      items.value = res.data || []
      totalItems.value = res.total
      itemPage.value = res.page
    }
  } catch (e) {
    console.error('[StoreSales] load items error:', e)
  }
}

async function loadStoreAgg() {
  try {
    const res = await api.get('/excel-report/store-sales/model-sku', {
      params: { store_code: currentStore.value.store_code }
    })
    if (res.code === 0 && res.data) {
      byModel.value = (res.data.storeModel || []).map(m => ({ ...m, _img: m.image_url || null }))
      bySku.value = (res.data.modelSku || []).map(s => ({ ...s, _img: s.image_url || null }))
    }
  } catch (e) {
    console.error('[StoreSales] load agg error:', e)
  }
}

function backToList() {
  currentStore.value = null
  byModel.value = []
  bySku.value = []
  items.value = []
  currentModelFilter.value = ''
  expandedSku.value = null
}

const COLOR_DISPLAY = {
  RGOLD: 'ROSE GOLD', DGRAY: 'DARK GRAY', IGRAY: 'IRON GRAY',
  DBLUE: 'DARK BLUE', AGREEN: 'ARMY GREEN', BRONZE: 'BRONZE',
  NAVY: 'NAVY', BURGUNDY: 'BURGUNDY', BEIGE: 'BEIGE',
  MILITARY: 'MILITARY', KHAKI: 'KHAKI', VIOLET: 'VIOLET',
};

function getColorDisplay(color) {
  if (!color) return null
  return COLOR_DISPLAY[color] || color
}

onMounted(() => {
  loadStoreSales()
})
</script>

<style scoped>
.store-sales-report { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
.header-left { display: flex; align-items: center; gap: 16px; }
.header-left h1 { margin: 0; font-size: 20px; }
.header-actions { display: flex; gap: 10px; align-items: center; }
.stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); text-align: center; }
.stat-value { font-size: 24px; font-weight: bold; color: #409eff; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.store-summary { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
.store-info { display: flex; align-items: center; gap: 12px; }
.store-info h2 { margin: 0; font-size: 20px; }
.store-code-badge { background: #409eff; color: white; padding: 2px 10px; border-radius: 4px; font-size: 12px; }
.store-stats { display: flex; gap: 40px; }
.stat-item { text-align: center; }
.stat-item .stat-value { font-size: 22px; font-weight: bold; color: #303133; }
.stat-item .stat-label { font-size: 12px; color: #909399; margin-top: 4px; }
.report-section { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; overflow-x: auto; }
.report-section h3 { margin: 0 0 16px; font-size: 16px; color: #303133; }
.data-table { width: 100%; border-collapse: collapse; min-width: 800px; }
.data-table th, .data-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #ebeef5; font-size: 13px; }
.data-table th { background: #fafafa; font-weight: 600; color: #606266; }
.data-table tr:hover { background: #f5f7fa; }
.store-row:hover { cursor: pointer; }
.store-name-cell { display: flex; flex-direction: column; gap: 2px; }
.store-code { font-size: 11px; color: #909399; }
.store-name { font-weight: 600; color: #303133; }
.num { text-align: right; font-family: monospace; }
.highlight { font-weight: bold; color: #409eff; }
.amount-cell { color: #e6a23c; font-weight: 600; }
.sku-cell { font-family: monospace; font-size: 12px; color: #409eff; }
.empty-cell { text-align: center; color: #909399; padding: 40px; }
.empty-section { text-align: center; color: #909399; padding: 20px; font-size: 14px; }
.model-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
.model-card { background: #f5f7fa; border-radius: 8px; padding: 12px; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: background 0.2s; }
.model-card:hover { background: #e3f2fd; }
.model-card.has-image { border: 2px solid #409eff; }
.model-rank { font-size: 12px; color: #909399; width: 24px; }
.model-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 4px; flex-shrink: 0; }
.model-thumb-placeholder { width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; background: #e3f2fd; border-radius: 4px; font-size: 20px; flex-shrink: 0; }
.model-info { flex: 1; min-width: 0; }
.model-name { font-size: 13px; font-weight: 600; color: #303133; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.model-meta { display: flex; gap: 8px; font-size: 11px; color: #909399; margin-top: 2px; }
.model-qty { font-size: 14px; font-weight: bold; color: #409eff; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
.section-actions { display: flex; align-items: center; gap: 8px; }
.filter-tag { background: #ecf5ff; color: #409eff; padding: 4px 10px; border-radius: 4px; font-size: 13px; }
.sku-list { display: flex; flex-direction: column; gap: 4px; }
.sku-item { display: flex; align-items: center; gap: 8px; padding: 8px; border-bottom: 1px solid #f0f0f0; font-size: 12px; cursor: pointer; transition: background 0.15s; border-radius: 4px; }
.sku-item:hover { background: #f5f7fa; }
.sku-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; flex-shrink: 0; }
.sku-thumb-placeholder { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: #f5f7fa; border-radius: 4px; font-size: 18px; flex-shrink: 0; }
.sku-rank { width: 24px; color: #909399; font-size: 11px; }
.sku-val { font-family: monospace; color: #409eff; width: 80px; }
.sku-name { flex: 1; color: #303133; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sku-model { color: #606266; width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sku-color { color: #909399; width: 80px; }
.sku-size { color: #909399; width: 50px; text-align: center; }
.sku-qty { color: #409eff; font-weight: bold; width: 60px; text-align: right; }
.sku-amount { color: #e6a23c; width: 80px; text-align: right; }
.sku-detail { display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap; padding: 8px 0; }
.sku-detail-img { width: 160px; height: 160px; object-fit: cover; border-radius: 8px; border: 1px solid #ebeef5; }
.sku-detail-info { flex: 1; min-width: 280px; display: flex; flex-direction: column; gap: 8px; }
.detail-row { display: flex; gap: 8px; font-size: 14px; }
.detail-row .label { color: #909399; width: 100px; flex-shrink: 0; }
.detail-row .val { color: #303133; font-weight: 500; }
.detail-row .val.highlight { color: #409eff; font-size: 16px; font-weight: bold; }
.detail-row .val.amount { color: #e6a23c; font-size: 16px; font-weight: bold; }
.filter-bar { display: flex; gap: 10px; margin-bottom: 16px; }
.sort-select { padding: 8px 12px; border: 1px solid #dcdfe6; border-radius: 4px; font-size: 14px; }
.pagination { display: flex; justify-content: center; align-items: center; gap: 16px; margin-top: 20px; }
.btn-back { padding: 8px 16px; background: #909399; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
.btn { padding: 8px 16px; background: #409eff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
.btn:hover { background: #66b1ff; }
.btn-small { padding: 6px 12px; background: #409eff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
.btn-small:hover { background: #66b1ff; }
</style>