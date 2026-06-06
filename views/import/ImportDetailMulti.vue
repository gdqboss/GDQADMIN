<template>
  <div class="import-detail-multi">
    <div class="header">
      <div class="header-left">
        <button class="btn-back" @click="$router.push('/import-records')">← {{ $t('importMulti.back') || '返回' }}</button>
        <h1>📊 {{ $t('importMulti.pageTitle') || '多选门店分析' }}</h1>
      </div>
      <div class="header-actions">
        <span class="record-count">{{ recordIds.length }} {{ $t('importMulti.recordsSelected') || '份记录' }}</span>
        <button class="btn btn-toggle" @click="showChart = !showChart">
          {{ showChart ? $t('importMulti.hideChart') : $t('importMulti.showChart') }}
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <span>{{ $t('importMulti.loading') || '加载中...' }}</span>
    </div>

    <div v-else-if="data">
      <!-- Overall Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value">{{ data.overall?.total_items?.toLocaleString() }}</div>
          <div class="stat-label">{{ $t('importMulti.totalItems') || '总条数' }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ data.overall?.total_qty?.toLocaleString() }}</div>
          <div class="stat-label">{{ $t('importMulti.totalQty') || '总销量' }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">¥{{ Number(data.overall?.total_amount || 0).toLocaleString() }}</div>
          <div class="stat-label">{{ $t('importMulti.totalAmount') || '总销售额' }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ data.overall?.unique_stores }}</div>
          <div class="stat-label">{{ $t('importMulti.totalStores') || '门店数' }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ data.overall?.unique_skus }}</div>
          <div class="stat-label">{{ $t('importMulti.totalSkus') || 'SKU数' }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ data.overall?.unique_models }}</div>
          <div class="stat-label">{{ $t('importMulti.totalModels') || '型号数' }}</div>
        </div>
      </div>

      <!-- Charts -->
      <div v-if="showChart" class="charts-section">
        <!-- 门店销售排名 -->
        <div class="chart-card chart-wide">
          <h3>🏪 {{ $t('importMulti.storeRanking') || '门店销售排名' }}</h3>
          <div class="chart-placeholder">
            <div v-for="(s, i) in data.byStore" :key="i" class="bar-item" @click="selectStore(s)" :class="{ 'bar-active': selectedStore?.store_code === s.store_code }">
              <span class="bar-rank">{{ i + 1 }}</span>
              <span class="bar-label" :title="s.store_name || s.store_code">{{ (s.store_name || s.store_code || '-').substring(0, 12) }}</span>
              <div class="bar-wrap">
                <div class="bar bar-green" :style="{ width: getBarWidth(s, data.byStore, 'total_amount') + '%' }"></div>
              </div>
              <span class="bar-value">¥{{ Number(s.total_amount).toLocaleString() }}</span>
              <span class="bar-qty">{{ Number(s.total_qty).toLocaleString() }}{{ $t('importMulti.pcs') || '件' }}</span>
            </div>
          </div>
        </div>

        <!-- 颜色×尺码热销组合 -->
        <div class="chart-card chart-wide">
          <h3>🔥 {{ $t('importMulti.colorSizeCombo') || '颜色×尺码 热销组合' }}</h3>
          <div class="matrix-grid">
            <div v-for="(cs, i) in data.byColorSize" :key="i" class="matrix-item">
              <span class="matrix-rank">#{{ i + 1 }}</span>
              <span class="matrix-color">{{ getColorDisplay({ color: cs.color })?.substring(0, 8) || '-' }}</span>
              <span class="matrix-size">{{ cs.size }}</span>
              <span class="matrix-qty">{{ Number(cs.qty).toLocaleString() }}{{ $t('importMulti.pcs') || '件' }}</span>
            </div>
            <div v-if="!data.byColorSize?.length" class="empty-chart">{{ $t('importMulti.noData') || '暂无数据' }}</div>
          </div>
        </div>
      </div>

      <!-- Store Detail: Click a store to see its models and SKUs -->
      <div v-if="selectedStore" class="store-detail">
        <div class="store-detail-header">
          <h2>🏪 {{ selectedStore.store_name || selectedStore.store_code }}</h2>
          <div class="store-stats">
            <span>销量: {{ Number(selectedStore.total_qty).toLocaleString() }}{{ $t('importMulti.pcs') || '件' }}</span>
            <span>销售额: ¥{{ Number(selectedStore.total_amount).toLocaleString() }}</span>
            <span>型号: {{ selectedStore.model_count }} 个</span>
            <span>SKU: {{ selectedStore.sku_count }} 个</span>
          </div>
          <button class="btn-small btn-gray" @click="selectedStore = null">{{ $t('importMulti.closeStore') || '关闭' }}</button>
        </div>

        <!-- Models in this store -->
        <div class="model-section">
          <h3>📦 {{ $t('importMulti.storeModels') || '门店型号' }}</h3>
          <table class="data-table">
            <thead>
              <tr>
                <th>{{ $t('importMulti.model') || '型号' }}</th>
                <th>{{ $t('importMulti.qty') || '销量' }}</th>
                <th>{{ $t('importMulti.amount') || '销售额' }}</th>
                <th>{{ $t('importMulti.skuCount') || 'SKU数' }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in storeModels" :key="m.model" @click="selectModel(m)" class="clickable-row" :class="{ 'row-active': selectedModel?.model === m.model }">
                <td class="model-cell">{{ m.model || '-' }}</td>
                <td class="num">{{ Number(m.qty).toLocaleString() }}</td>
                <td class="num">¥{{ Number(m.amount).toLocaleString() }}</td>
                <td class="num">{{ m.sku_count }}</td>
              </tr>
              <tr v-if="storeModels.length === 0">
                <td colspan="4" class="empty-cell">{{ $t('importMulti.noModelData') || '暂无型号数据' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- SKUs for selected model in this store -->
        <div v-if="selectedModel" class="sku-section">
          <h3>🏷️ {{ selectedModel.model }} — SKU 明细</h3>
          <table class="data-table">
            <thead>
              <tr>
                <th>SKU</th>
                <th>{{ $t('importMulti.color') || '颜色' }}</th>
                <th>{{ $t('importMulti.size') || '尺码' }}</th>
                <th>{{ $t('importMulti.qty') || '销量' }}</th>
                <th>{{ $t('importMulti.amount') || '销售额' }}</th>
                <th>{{ $t('importMulti.orderCount') || '单数' }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="sku in modelSkus" :key="sku.sku">
                <td class="sku-cell">{{ sku.sku }}</td>
                <td>{{ getColorDisplay({ color: sku.color }) || '-' }}</td>
                <td>{{ sku.size || '-' }}</td>
                <td class="num">{{ Number(sku.total_qty).toLocaleString() }}</td>
                <td class="num">¥{{ Number(sku.total_amount).toLocaleString() }}</td>
                <td class="num">{{ sku.order_count }}</td>
              </tr>
              <tr v-if="modelSkus.length === 0">
                <td colspan="6" class="empty-cell">{{ $t('importMulti.noSkuData') || '暂无SKU数据' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Store List (default view) -->
      <div v-else class="store-list">
        <h2>🏪 {{ $t('importMulti.allStores') || '所有门店' }}</h2>
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ $t('importMulti.storeCode') || '门店编号' }}</th>
              <th>{{ $t('importMulti.storeName') || '门店名称' }}</th>
              <th>{{ $t('importMulti.totalQty') || '总销量' }}</th>
              <th>{{ $t('importMulti.totalAmount') || '总销售额' }}</th>
              <th>{{ $t('importMulti.modelCount') || '型号数' }}</th>
              <th>{{ $t('importMulti.skuCount') || 'SKU数' }}</th>
              <th>{{ $t('importMulti.action') || '操作' }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(s, i) in data.byStore" :key="s.store_code" class="clickable-row" @click="selectStore(s)">
              <td class="num">{{ i + 1 }}</td>
              <td>{{ s.store_code || '-' }}</td>
              <td>{{ s.store_name || '-' }}</td>
              <td class="num">{{ Number(s.total_qty).toLocaleString() }}</td>
              <td class="num amount-cell">¥{{ Number(s.total_amount).toLocaleString() }}</td>
              <td class="num">{{ s.model_count }}</td>
              <td class="num">{{ s.sku_count }}</td>
              <td><button class="btn-small" @click.stop="selectStore(s)">{{ $t('importMulti.viewModels') || '查看型号' }}</button></td>
            </tr>
            <tr v-if="data.byStore.length === 0">
              <td colspan="8" class="empty-cell">{{ $t('importMulti.noStoreData') || '暂无门店数据' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import i18n from '@/i18n'
import api from '@/services/api.js'

const { t, locale } = i18n.global

const recordIds = window.location.hash.split('/import-detail-multi/').pop().split(',').map(Number)
const data = ref(null)
const loading = ref(true)
const showChart = ref(true)
const selectedStore = ref(null)
const selectedModel = ref(null)

const storeModels = computed(() => {
  if (!selectedStore.value || !data.value) return []
  return data.value.byModel.filter(m => m.store_code === selectedStore.value.store_code)
})

const modelSkus = computed(() => {
  if (!selectedModel.value || !data.value) return []
  return data.value.bySku.filter(s =>
    s.store_code === selectedStore.value.store_code &&
    s.model === selectedModel.value.model
  )
})

function getBarWidth(item, arr, field = 'total_amount') {
  if (!arr || !arr.length) return 0
  const max = Math.max(...arr.map(a => Number(a[field] || 0)))
  if (!max) return 0
  return Math.round((Number(item[field] || 0) / max) * 100)
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

function selectStore(store) {
  selectedStore.value = store
  selectedModel.value = null
}

function selectModel(model) {
  selectedModel.value = model
}

async function loadData() {
  loading.value = true
  try {
    const res = await api.post('/import/multi-analysis', { record_ids: recordIds })
    if (res.success) {
      data.value = res
    }
  } catch (e) {
    console.error('[ImportDetailMulti] load error:', e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.import-detail-multi { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
.header-left { display: flex; align-items: center; gap: 16px; }
.header-left h1 { margin: 0; font-size: 20px; }
.header-actions { display: flex; gap: 10px; align-items: center; }
.record-count { font-size: 13px; color: #909399; background: white; padding: 6px 12px; border-radius: 4px; }
.loading-state { display: flex; align-items: center; gap: 12px; justify-content: center; padding: 60px; color: #909399; }
.spinner { width: 24px; height: 24px; border: 3px solid #e4e7ed; border-top-color: #409eff; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.stats-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card { background: white; padding: 16px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); text-align: center; }
.stat-value { font-size: 22px; font-weight: bold; color: #409eff; }
.stat-label { font-size: 12px; color: #909399; margin-top: 4px; }

.charts-section { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 24px; }
.chart-card { background: white; border-radius: 8px; padding: 16px; }
.chart-wide { grid-column: span 2; }
.chart-card h3 { margin: 0 0 12px; font-size: 15px; color: #303133; }
.chart-placeholder { display: flex; flex-direction: column; gap: 6px; }
.bar-item { display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 4px 8px; border-radius: 4px; }
.bar-item:hover { background: #f5f7fa; }
.bar-active { background: #ecf5ff; border: 1px solid #409eff; }
.bar-rank { width: 20px; font-size: 12px; color: #909399; text-align: center; }
.bar-label { width: 80px; font-size: 13px; color: #606266; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bar-wrap { flex: 1; height: 16px; background: #f0f2f5; border-radius: 3px; overflow: hidden; }
.bar { height: 100%; border-radius: 3px; transition: width 0.3s; min-width: 2px; }
.bar-green { background: linear-gradient(90deg, #67c23a, #95d475); }
.bar-value { width: 100px; text-align: right; font-size: 13px; color: #606266; }
.bar-qty { width: 80px; text-align: right; font-size: 12px; color: #909399; }
.empty-chart { text-align: center; color: #909399; padding: 20px; font-size: 13px; }

.matrix-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
.matrix-item { background: #f5f7fa; border-radius: 6px; padding: 10px; text-align: center; }
.matrix-rank { display: block; font-size: 10px; color: #909399; }
.matrix-color { display: block; font-size: 12px; color: #303133; font-weight: 600; }
.matrix-size { display: block; font-size: 16px; color: #606266; margin: 2px 0; }
.matrix-qty { display: block; font-size: 11px; color: #67c23a; font-weight: bold; }

.store-detail { background: white; border-radius: 8px; padding: 20px; }
.store-detail-header { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
.store-detail-header h2 { margin: 0; font-size: 18px; }
.store-stats { display: flex; gap: 16px; font-size: 13px; color: #606266; }
.model-section, .sku-section { margin-top: 20px; }
.model-section h3, .sku-section h3 { margin: 0 0 12px; font-size: 16px; color: #303133; }

.store-list { background: white; border-radius: 8px; padding: 20px; }
.store-list h2 { margin: 0 0 16px; font-size: 18px; }

.report-section, .data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 10px 8px; text-align: left; border-bottom: 1px solid #ebeef5; font-size: 13px; white-space: nowrap; }
.data-table th { background: #fafafa; font-weight: 600; color: #606266; position: sticky; top: 0; }
.data-table tr:hover { background: #f5f7fa; }
.clickable-row { cursor: pointer; }
.row-active { background: #ecf5ff; }
.model-cell { font-family: monospace; font-weight: 600; color: #303133; }
.sku-cell { font-family: monospace; font-size: 12px; color: #409eff; }
.num { text-align: right; font-family: monospace; }
.amount-cell { color: #e6a23c; font-weight: 600; }
.empty-cell { text-align: center; color: #909399; padding: 30px; }
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
</style>