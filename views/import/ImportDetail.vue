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
    <div v-if="showChart && summary" class="charts-wrap">

      <!-- 门店分析：独占一行（矩阵风格） -->
      <div class="store-section">
        <div class="chart-card chart-full">
          <div class="store-section-header">
            <h3>🏪 {{ $t('importDetail.storeAnalysis') }}<span class="text-gray">({{ allStores.length }})</span></h3>
            <div class="store-section-actions">
              <button class="btn-xs btn-print" @click="printStores">{{ $t('importDetail.printStores') }}</button>
              <button class="btn-xs btn-toggle-all" @click="toggleAllRecords">
                {{ expandedAllRecords ? $t('importDetail.collapse') : $t('importDetail.expandAllRecords') }}
              </button>
            </div>
          </div>
          <div v-if="expandedAllRecords" class="all-records-wrap">
            <div v-if="allRecordsGroups.length" class="multi-matrix-list">
              <div v-for="grp in allRecordsGroups" :key="grp.model" class="multi-matrix-card">
                <div class="multi-matrix-header">
                  <img v-if="grp.image" :src="grp.image" class="model-thumb-sm" alt="" />
                  <span class="model-label">{{ $t('importDetail.model') }}: <strong>{{ grp.model }}</strong></span>
                  <span class="model-stat">{{ grp.rows.length }} {{ $t('importDetail.pcs') || '行' }}</span>
                </div>
                <div class="sku-matrix-wrap" v-if="grp.matrix.colors.length">
                  <table class="sku-matrix">
                    <thead>
                      <tr>
                        <th class="color-col">{{ $t('importDetail.colorSizeMatrix') }}</th>
                        <th v-for="sz in grp.matrix.sizes" :key="sz" class="num size-col">{{ sz }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="c in grp.matrix.colors" :key="c">
                        <td class="color-cell">{{ getColorDisplay({color: c}) || c }}</td>
                        <td v-for="sz in grp.matrix.sizes" :key="sz" class="num cell-data">
                          <div v-for="cell in (grp.matrix.cells[c]?.[sz] || [])" :key="cell.sku" class="cell-sku">
                            <span class="sku-num">{{ cell.sku }}</span>
                            <span class="sku-qty">{{ cell.qty }}{{ $t('importDetail.pcs') }}</span>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else class="empty-cell">{{ $t('importDetail.noSkuData') }}</div>
              </div>
            </div>
            <div v-else class="empty-cell">{{ $t('importDetail.noItems') || 'No data' }}</div>
            <div v-if="total > pageSize" class="pagination mini-pagination">
              <button class="btn-small" :disabled="page <= 1" @click="loadItems(page - 1)">‹</button>
              <span>{{ page }} / {{ Math.ceil(total / pageSize) }}</span>
              <button class="btn-small" :disabled="page >= Math.ceil(total / pageSize)" @click="loadItems(page + 1)">›</button>
            </div>
          </div>
          <div class="chart-placeholder">
          <div class="matrix-table-wrap store-wrap">
          <table v-if="allStores.length > 0" class="matrix-table store-matrix">
            <thead>
              <tr>
                <th>{{ $t('importDetail.rank') }}</th>
                <th>{{ $t('importDetail.store') }}</th>
                <th class="num">{{ $t('importDetail.detailCount') }}</th>
                <th class="num">{{ $t('importDetail.salesQty') }}</th>
                <th class="num">{{ $t('importDetail.salesAmountCol') }}</th>
                <th>{{ $t('importDetail.operation') }}</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(s, i) in allStores" :key="s.store_code">
              <tr :class="{ expanded: expandedStore === s.store_code }">
                <td>{{ i + 1 }}</td>
                <td class="store-name-cell" :title="s.store_name || s.store_code">{{ s.store_name || s.store_code || '-' }}</td>
                <td class="num">{{ s.item_count ?? '-' }}</td>
                <td class="num qty-cell">{{ Number(s.qty).toLocaleString() }}</td>
                <td class="num amount-cell">{{ s.amount ? '¥' + Number(s.amount).toLocaleString() : '-' }}</td>
                <td>
                  <div class="store-row-actions">
                    <button class="btn-xs" @click="toggleStore(s.store_code)">{{ expandedStore === s.store_code ? $t('importDetail.collapse') : $t('importDetail.expand') }}</button>
                    <button class="btn-xs btn-toggle-store-detail" @click="toggleStoreDetail(s.store_code)">{{ expandedStoreDetail === s.store_code ? $t('importDetail.collapseDetail') : $t('importDetail.expandDetail') }}</button>
                  </div>
                </td>
              </tr>
              <tr v-if="expandedStore === s.store_code" class="expand-row">
                <td colspan="6">
                  <div v-if="storeDetail && storeDetail.store?.store_code === s.store_code" class="store-expand-inner">
                    <!-- 门店汇总 chips -->
                    <div class="store-expand-stats">
                      <span class="stat-chip">{{ $t('importDetail.salesQty') }} <strong>{{ Number(storeDetail.store?.total_qty || 0).toLocaleString() }}</strong> {{ $t('importDetail.pcs') }}</span>
                      <span class="stat-chip">{{ $t('importDetail.salesAmount') }} <strong>¥{{ Number(storeDetail.store?.total_amount || 0).toLocaleString() }}</strong></span>
                      <span class="stat-chip">{{ $t('importDetail.modelCount') }} <strong>{{ storeDetail.store?.model_count }}</strong></span>
                      <span class="stat-chip">{{ $t('importDetail.skuCount') }} <strong>{{ storeDetail.store?.sku_count }}</strong></span>
                    </div>
                    <!-- 型号分布矩阵 -->
                    <div class="report-section sub-section">
                      <h4>📦 {{ $t('importDetail.modelDistribution') }} ({{ storeDetail.byModel?.length || 0 }})</h4>
                      <div class="matrix-table-wrap">
                      <table v-if="storeDetail.byModel?.length > 0" class="matrix-table model-matrix">
                        <thead>
                          <tr>
                            <th>#</th>
                            <th>{{ $t('importDetail.image') }}</th>
                            <th>{{ $t('importDetail.model') }}</th>
                            <th class="num">{{ $t('importDetail.skuCount') }}</th>
                            <th class="num">{{ $t('importDetail.quantity') }}</th>
                            <th class="num">{{ $t('importDetail.amount') }}</th>
                            <th>{{ $t('importDetail.operation') }}</th>
                          </tr>
                        </thead>
                        <tbody>
                          <template v-for="(m, mi) in storeDetail.byModel" :key="m.model">
                          <tr :class="{ expanded: expandedModel === m.model }">
                            <td>{{ mi + 1 }}</td>
                            <td>
                              <img v-if="m.image_url" :src="m.image_url" class="model-thumb" alt="" />
                              <div v-else class="model-thumb-placeholder">📦</div>
                            </td>
                            <td :title="m.model">{{ (m.model || '-').substring(0, 20) }}</td>
                            <td class="num">{{ m.sku_count }}</td>
                            <td class="num qty-cell">{{ Number(m.qty).toLocaleString() }}</td>
                            <td class="num amount-cell">{{ m.amount ? '¥' + Number(m.amount).toLocaleString() : '-' }}</td>
                            <td><button class="btn-xs" @click="toggleModel(m.model)">{{ expandedModel === m.model ? $t('importDetail.collapse') : $t('importDetail.matrix') }}</button></td>
                          </tr>
                          <!-- SKU 颜色×尺码 真矩阵 -->
                          <tr v-if="expandedModel === m.model && matrixForModel.colors.length > 0" class="expand-row">
                            <td colspan="7">
                              <div class="sku-matrix-wrap">
                                <div class="sku-matrix-layout">
                                  <!-- 左侧：型号大图 -->
                                  <div class="model-image-cell">
                                    <img v-if="matrixForModel.modelImage" :src="matrixForModel.modelImage" class="model-image-large" alt="" />
                                    <div v-else class="model-image-placeholder">📦</div>
                                    <div class="model-image-label">{{ $t('importDetail.modelImageLabel') }}</div>
                                  </div>
                                  <!-- 右侧：颜色×尺码矩阵 -->
                                  <div class="sku-matrix-right">
                                    <table class="matrix-table sku-matrix">
                                      <thead>
                                        <tr>
                                          <th class="color-col">{{ $t('importDetail.colorSizeMatrix') }}</th>
                                          <th v-for="sz in matrixForModel.sizes" :key="sz" class="num size-col">{{ sz }}</th>
                                        </tr>
                                      </thead>
                                      <tbody>
                                        <tr v-for="c in matrixForModel.colors" :key="c">
                                          <td class="color-cell">{{ c }}</td>
                                          <td v-for="sz in matrixForModel.sizes" :key="sz" class="num cell-data">
                                            <div v-for="item in (matrixForModel.cells[c]?.[sz] || [])" :key="item.sku" class="cell-sku">
                                              <span class="sku-num">{{ item.sku }}</span>
                                              <span class="sku-qty">/{{ item.qty }}PCS</span>
                                            </div>
                                          </td>
                                        </tr>
                                      </tbody>
                                    </table>
                                  </div>
                                </div>
                              </div>
                            </td>
                          </tr>
                          <tr v-if="expandedModel === m.model && matrixForModel.colors.length === 0" class="expand-row">
                            <td colspan="7" class="empty-cell">{{ $t('importDetail.noSkuData') }}</td>
                          </tr>
                          </template>
                        </tbody>
                      </table>
                      </div>
                      <div v-if="!(storeDetail.byModel?.length > 0)" class="empty-cell">{{ $t('importDetail.noModelDataMatrix') }}</div>
                    </div>

                    <!-- ②门店明细 — 该店所有 SKU（按型号分组矩阵显示） -->
                    <div v-if="expandedStoreDetail === s.store_code" class="report-section sub-section store-detail-section">
                      <h4>📋 {{ $t('importDetail.storeDetail') }} ({{ storeDetail.bySku?.length || 0 }} SKU · {{ storeDetailGroups.length }} {{ $t('importDetail.model') }})</h4>
                      <div v-if="storeDetailGroups.length" class="multi-matrix-list">
                        <div v-for="grp in storeDetailGroups" :key="grp.model" class="multi-matrix-card">
                          <div class="multi-matrix-header">
                            <img v-if="grp.image" :src="grp.image" class="model-thumb-sm" alt="" />
                            <span class="model-label">{{ $t('importDetail.model') }}: <strong>{{ grp.model }}</strong></span>
                            <span class="model-stat">{{ grp.rows.length }} SKU</span>
                          </div>
                          <div class="sku-matrix-wrap" v-if="grp.matrix.colors.length">
                            <table class="sku-matrix">
                              <thead>
                                <tr>
                                  <th class="color-col">{{ $t('importDetail.colorSizeMatrix') }}</th>
                                  <th v-for="sz in grp.matrix.sizes" :key="sz" class="num size-col">{{ sz }}</th>
                                </tr>
                              </thead>
                              <tbody>
                                <tr v-for="c in grp.matrix.colors" :key="c">
                                  <td class="color-cell">{{ getColorDisplay({color: c}) || c }}</td>
                                  <td v-for="sz in grp.matrix.sizes" :key="sz" class="num cell-data">
                                    <div v-for="cell in (grp.matrix.cells[c]?.[sz] || [])" :key="cell.sku" class="cell-sku">
                                      <span class="sku-num">{{ cell.sku }}</span>
                                      <span class="sku-qty">{{ cell.qty }}{{ $t('importDetail.pcs') }}</span>
                                    </div>
                                  </td>
                                </tr>
                              </tbody>
                            </table>
                          </div>
                          <div v-else class="empty-cell">{{ $t('importDetail.noSkuData') }}</div>
                        </div>
                      </div>
                      <div v-else class="empty-cell">{{ $t('importDetail.noData') }}</div>
                      <div class="store-detail-footer">
                        <button class="btn-xs btn-toggle-store-detail" @click="toggleStoreDetail(s.store_code)">{{ $t('importDetail.collapseDetail') }}</button>
                      </div>
                    </div>
                  </div>
                  <div v-else class="empty-cell">{{ $t('importDetail.noData') || 'Loading...' }}</div>
                </td>
              </tr>
              </template>
            </tbody>
          </table>
          </div>
          <div v-if="!(allStores.length > 0)" class="empty-chart">{{ $t('importDetail.noData') }}</div>
          </div>
        </div>
      </div>

      <!-- 全局分析区 -->
      <div class="global-section">
        <div class="global-grid">
          <!-- 商品分析 -->
          <div class="chart-card">
            <h3>📦 {{ $t('importDetail.productAnalysis') }} <span class="tag-global">Global</span></h3>
            <div class="chart-tabs">
              <button :class="{active: productTab === 'top'}" @click="productTab = 'top'">{{ $t('importDetail.hotTop') }}</button>
              <button :class="{active: productTab === 'bottom'}" @click="productTab = 'bottom'">{{ $t('importDetail.coldBottom') }}</button>
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
                <span class="bar-value">{{ Number(p.qty).toLocaleString() }} {{ $t('importDetail.pcs') }}</span>
              </div>
              <div v-if="(productTab === 'top' ? productHot : productCold).length === 0" class="empty-chart">{{ $t('importDetail.noData') }}</div>
            </div>
          </div>

          <!-- 颜色分析 -->
          <div class="chart-card">
            <h3>🎨 {{ $t('importDetail.colorAnalysis') }} <span class="tag-global">Global</span></h3>
            <div class="chart-placeholder">
              <div v-for="(c, i) in summary.byColor?.slice(0, 12)" :key="i" class="bar-item">
                <span class="bar-rank">{{ i + 1 }}</span>
                <span class="bar-label color-label" :title="getColorDisplay({color: c.color})">{{ getColorDisplay({color: c.color}) || '-' }}</span>
                <div class="bar-wrap">
                  <div class="bar bar-purple" :style="{ width: getBarWidth(c, summary.byColor, 'qty') + '%' }"></div>
                </div>
                <span class="bar-value">{{ Number(c.qty).toLocaleString() }} {{ $t('importDetail.pcs') }}</span>
              </div>
              <div v-if="!summary.byColor?.length" class="empty-chart">{{ $t('importDetail.noData') }}</div>
            </div>
          </div>

          <!-- 尺码分析 -->
          <div class="chart-card">
            <h3>📏 {{ $t('importDetail.sizeAnalysis') }} <span class="tag-global">Global</span></h3>
            <div class="chart-placeholder">
              <div v-for="(s, i) in summary.bySize?.slice(0, 12)" :key="i" class="bar-item">
                <span class="bar-rank">{{ i + 1 }}</span>
                <span class="bar-label size-label">{{ s.size || '-' }}</span>
                <div class="bar-wrap">
                  <div class="bar bar-orange" :style="{ width: getBarWidth(s, summary.bySize, 'qty') + '%' }"></div>
                </div>
                <span class="bar-value">{{ Number(s.qty).toLocaleString() }} {{ $t('importDetail.pcs') }}</span>
              </div>
              <div v-if="!summary.bySize?.length" class="empty-chart">{{ $t('importDetail.noData') }}</div>
            </div>
          </div>
        </div>

        <!-- 型号分析 + 颜色×尺码 宽卡片 -->
        <div class="global-wide">
          <!-- 型号总销量 -->
          <div class="chart-card chart-wide">
            <h3>🏆 {{ $t('importDetail.modelAnalysis') }} <span class="tag-global">Global</span></h3>
            <div class="chart-tabs">
              <button :class="{active: modelTab === 'top'}" @click="modelTab = 'top'">{{ $t('importDetail.hotTop') }}</button>
              <button :class="{active: modelTab === 'bottom'}" @click="modelTab = 'bottom'">{{ $t('importDetail.coldBottom') }}</button>
            </div>
            <div class="chart-placeholder">
              <div v-for="(m, i) in (modelTab === 'top' ? topModels : bottomModels)" :key="i" class="bar-item">
                <span class="bar-rank">{{ i + 1 }}</span>
                <span class="bar-label" :title="m.model">{{ m.model ? m.model.substring(0, 16) : '-' }}</span>
                <span class="bar-sku">{{ m.sku_count }}{{ $t('importDetail.skuCount') }}</span>
                <div class="bar-wrap">
                  <div class="bar" :class="modelTab === 'top' ? 'bar-green' : 'bar-red'" :style="{ width: getBarWidth(m, modelTab === 'top' ? topModels : bottomModels, 'qty') + '%' }"></div>
                </div>
                <span class="bar-value">{{ Number(m.qty).toLocaleString() }} {{ $t('importDetail.pcs') }}</span>
              </div>
              <div v-if="(modelTab === 'top' ? topModels : bottomModels).length === 0" class="empty-chart">{{ $t('importDetail.noData') }}</div>
            </div>
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
import { ref, computed, onMounted } from 'vue'
import i18n from '@/i18n'
import api from '@/services/api.js'

const { t, locale } = i18n.global

const recordId = window.location.hash.split('/import-detail/').pop()
const record = ref(null)
const items = ref([])
const summary = ref(null)
const storeDetail = ref(null)
const page = ref(1)
const pageSize = 50
const total = ref(0)
const sortBy = ref('quantity')
const sortOrder = ref('DESC')
const filterSku = ref('')
const filterStore = ref('')
const showChart = ref(true)
const productTab = ref('top')
const modelTab = ref('top')

// 型号热销/滞销
const topModels = computed(() => summary.value?.topModels || [])
const bottomModels = computed(() => summary.value?.bottomModels || [])

// 全部门店，按销量降序
const allStores = computed(() => {
  const arr = summary.value?.byStore || []
  return [...arr].sort((a, b) => Number(b.qty || 0) - Number(a.qty || 0))
})

const expandedStore = ref(null)
const expandedModel = ref(null)
const expandedStoreDetail = ref(null)
const expandedAllRecords = ref(false)
const modelSkus = ref([])

async function toggleStore(storeCode) {
  if (expandedStore.value === storeCode) {
    expandedStore.value = null
    return
  }
  expandedStore.value = storeCode
  expandedModel.value = null
  expandedStoreDetail.value = null
  modelSkus.value = []
  storeDetail.value = null
  await loadStoreSummary(storeCode)
}

async function toggleStoreDetail(storeCode) {
  // 必须先展开 store（否则没 storeDetail 数据）
  if (expandedStore.value !== storeCode) {
    await toggleStore(storeCode)
  }
  expandedStoreDetail.value = expandedStoreDetail.value === storeCode ? null : storeCode
}

function toggleAllRecords() {
  expandedAllRecords.value = !expandedAllRecords.value
  if (expandedAllRecords.value && items.value.length === 0) loadItems(1)
}

// 🖨 打印整张门店分析表 — 把 allStores 一页打出来
async function printStores() {
  if (!allStores.value.length) return
  // 进浏览器打印前给个标题（让打印页眉更友好）
  const title = `门店销售分析 · ${record.value?.file_name || recordId} · ${new Date().toLocaleString()}`
  const rows = allStores.value.map((s, i) => `
    <tr>
      <td style="text-align:center">${i + 1}</td>
      <td>${(s.store_name || s.store_code || '-').replace(/[<>&]/g, c => ({ '<':'&lt;','>':'&gt;','&':'&amp;'}[c]))}</td>
      <td style="text-align:center">${s.item_count ?? '-'}</td>
      <td style="text-align:right">${Number(s.qty || 0).toLocaleString()}</td>
      <td style="text-align:right">${s.amount ? '¥' + Number(s.amount).toLocaleString() : '-'}</td>
    </tr>`).join('')
  const totalQty = allStores.value.reduce((sum, s) => sum + Number(s.qty || 0), 0)
  const totalAmt = allStores.value.reduce((sum, s) => sum + Number(s.amount || 0), 0)
  const html = `<!doctype html><html><head><meta charset="utf-8"><title>${title}</title>
    <style>
      body { font-family: 'Microsoft YaHei','Helvetica',Arial,sans-serif; padding: 16px; color:#222; }
      h1 { font-size: 16px; margin: 0 0 4px; }
      .meta { font-size: 12px; color:#666; margin-bottom: 12px; }
      table { border-collapse: collapse; width: 100%; font-size: 12px; }
      th, td { border: 1px solid #999; padding: 4px 8px; }
      th { background: #f0f2f5; }
      tfoot td { font-weight: bold; background: #fafbfc; }
    </style>
1.html>
  <body>
    <h1>🏪 门店销售分析</h1>
    <div class="meta">${title} · 门店数: <strong>${allStores.value.length}</strong> · 总销量: <strong>${totalQty.toLocaleString()}</strong> PCS · 总销售额: <strong>¥${totalAmt.toLocaleString()}</strong></div>
    <table>
      <thead>
        <tr><th>#</th><th>门店</th><th>明细数</th><th style="text-align:right">销量(PCS)</th><th style="text-align:right">销售额</th></tr>
      </thead>
      <tbody>${rows}</tbody>
      <tfoot>
        <tr><td colspan="2" style="text-align:center">合计 ${allStores.value.length} 店</td>
            <td style="text-align:center">${allStores.value.reduce((a, s) => a + Number(s.item_count || 0), 0).toLocaleString()}</td>
            <td style="text-align:right">${totalQty.toLocaleString()}</td>
            <td style="text-align:right">¥${totalAmt.toLocaleString()}</td></tr>
      </tfoot>
    </table>
  </body>
  </html>`.replace('1.html>', '</head>')

  const w = window.open('', '_blank')
  if (!w) { alert('弹窗被浏览器拦截，请允许本站弹出窗口后再点打印'); return }

  // 分段拼装避免模板字符串里套标签出错
  const docStart = '<!doctype html><html><head><meta charset="utf-8"><title>' + title + '</title>'
  const styleBlock = '<style>' +
    "body{font-family:'Microsoft YaHei','Helvetica',Arial,sans-serif;padding:16px;color:#222}" +
    'h1{font-size:16px;margin:0 0 4px}' +
    '.meta{font-size:12px;color:#666;margin-bottom:12px}' +
    'table{border-collapse:collapse;width:100%;font-size:12px}' +
    'th,td{border:1px solid #999;padding:4px 8px}' +
    'th{background:#f0f2f5}' +
    'tfoot td{font-weight:bold;background:#fafbfc}' +
    '</style></head><body>'
  const bodyEnd = '</body></html>'

  w.document.open()
  w.document.write(docStart + styleBlock
    + '<h1>🏪 门店销售分析</h1>'
    + '<div class="meta">' + title + ' · 门店数: <strong>' + allStores.value.length
    + '</strong> · 总销量: <strong>' + totalQty.toLocaleString()
    + '</strong> PCS · 总销售额: <strong>¥' + totalAmt.toLocaleString() + '</strong></div>'
    + '<table><thead><tr><th>#</th><th>门店</th><th>明细数</th>'
    + '<th style="text-align:right">销量(PCS)</th><th style="text-align:right">销售额</th></tr></thead>'
    + '<tbody>' + rows + '</tbody>'
    + '<tfoot><tr><td colspan="2" style="text-align:center">合计 ' + allStores.value.length + ' 店</td>'
    + '<td style="text-align:center">' + allStores.value.reduce((a, s) => a + Number(s.item_count || 0), 0).toLocaleString() + '</td>'
    + '<td style="text-align:right">' + totalQty.toLocaleString() + '</td>'
    + '<td style="text-align:right">¥' + totalAmt.toLocaleString() + '</td></tr></tfoot>'
    + '</table>' + bodyEnd)
  w.document.close()
  setTimeout(() => { w.focus(); w.print() }, 250)
}

// 当前展开型号的 颜色×尺码 矩阵（行=颜色，列=尺码，格=Sku编号/数量）
const matrixForModel = computed(() => buildMatrix(modelSkus.value))

// 通用矩阵构建函数 — rows = [{sku, color, size, total_qty|qty, image_url, ...}]
// 返回 { sizes[], colors[], cells[color][size] = [{sku, qty}], colorImageMap, modelImage }
function buildMatrix(rows) {
  const arr = rows || []
  if (arr.length === 0) return { sizes: [], colors: [], cells: {}, colorImageMap: {}, modelImage: '' }
  const colorSet = new Set()
  const sizeSet = new Set()
  const colorImageMap = {}
  let modelImage = ''
  for (const s of arr) {
    const c = (s.color || '').toString().trim() || '-'
    const sz = (s.size || '').toString().trim()
    if (sz) sizeSet.add(sz)
    colorSet.add(c)
    if (s.image_url && !colorImageMap[c]) colorImageMap[c] = s.image_url
    if (s.image_url && !modelImage) modelImage = s.image_url
  }
  const sizes = Array.from(sizeSet).sort((a, b) => {
    const na = parseFloat(a), nb = parseFloat(b)
    if (!isNaN(na) && !isNaN(nb)) return na - nb
    return a.localeCompare(b)
  })
  const colors = Array.from(colorSet).sort((a, b) => a.localeCompare(b))
  const cells = {}
  for (const c of colors) {
    cells[c] = {}
    for (const sz of sizes) cells[c][sz] = []
  }
  for (const s of arr) {
    const c = (s.color || '').toString().trim() || '-'
    const sz = (s.size || '').toString().trim()
    if (!sz) continue
    if (!cells[c][sz]) cells[c][sz] = []
    cells[c][sz].push({ sku: s.sku, qty: Number(s.total_qty || s.qty || 0) })
  }
  return { sizes, colors, cells, colorImageMap, modelImage }
}

// 按 model 分组：{ '555485M': [rows...], '598841M': [rows...] }
function groupByModel(rows) {
  const map = {}
  for (const r of rows || []) {
    const m = (r.model || '-').toString().trim() || '-'
    if (!map[m]) map[m] = []
    map[m].push(r)
  }
  return Object.keys(map).sort().map(model => ({
    model,
    image: map[model].find(r => r.image_url)?.image_url || '',
    rows: map[model],
    matrix: buildMatrix(map[model])
  }))
}

// 门店明细：按 model 分组后的所有矩阵
const storeDetailGroups = computed(() => groupByModel(storeDetail.value?.bySku))

// 全记录明细：按 model 分组后的所有矩阵
const allRecordsGroups = computed(() => groupByModel(items.value))

function toggleModel(model) {
  if (expandedModel.value === model) {
    expandedModel.value = null
    modelSkus.value = []
  } else {
    expandedModel.value = model
    modelSkus.value = storeDetail.value?.bySku?.filter(s => s.model === model) || []
  }
}

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
    storeDetail.value = res
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
  const headers = ['SKU', 'Product Name', 'Model', 'Store', 'Qty', 'Unit Price', 'Amount', 'Color', 'Size', 'Date', 'Image']
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

.charts-wrap { display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px; }
.store-section { width: 100%; }
.chart-full { width: 100%; max-width: 1280px; margin: 0 auto; }
.global-section { display: flex; flex-direction: column; gap: 16px; }
.global-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.global-wide { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.tag-global { display: inline-block; background: #e6a7ff; color: #7030a0; font-size: 11px; padding: 1px 6px; border-radius: 10px; margin-left: 6px; vertical-align: middle; font-weight: 600; }
.chart-card h3 { margin: 0 0 12px; font-size: 15px; color: #303133; }

.chart-tabs { display: flex; gap: 8px; margin-bottom: 12px; }
.chart-tabs button { padding: 4px 12px; border: 1px solid #dcdfe6; border-radius: 4px; background: white; cursor: pointer; font-size: 12px; }
.chart-tabs button.active { background: #409eff; color: white; border-color: #409eff; }

.chart-placeholder { display: flex; flex-direction: column; gap: 6px; }
.bar-item { display: flex; align-items: center; gap: 6px; }
.bar-rank { width: 18px; font-size: 11px; color: #909399; text-align: center; }
.bar-label { min-width: 120px; max-width: 240px; font-size: 12px; color: #606266; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex-shrink: 0; }
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

.store-row-wrap { display: flex; flex-direction: column; }
.store-main-row { cursor: pointer; border-radius: 6px; transition: background 0.2s; }
.store-main-row:hover { background: #f5f7fa; }
.store-main-row.expanded { background: #ecf5ff; }
.store-name { min-width: 220px; max-width: 400px; font-size: 12px; color: #303133; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex-shrink: 0; text-align: left; }
.expand-icon { font-size: 10px; color: #909399; width: 16px; }
.store-expand-detail { padding: 12px 0 8px 28px; }
.store-expand-inner { background: #fafafa; border-radius: 8px; padding: 12px; }
.store-expand-stats { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
.sub-section { background: white; border-radius: 6px; padding: 12px; margin-bottom: 10px; }
.sub-section h4 { margin: 0 0 10px; font-size: 13px; color: #606266; }
.sub-section:last-child { margin-bottom: 0; }
.text-gray { color: #909399; font-weight: normal; font-size: 13px; margin-left: 8px; }
.store-section-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 8px; }
.store-section-header h3 { margin: 0; font-size: 15px; color: #303133; }
.store-section-actions { display: flex; gap: 8px; }
.store-row-actions { display: flex; gap: 6px; flex-wrap: wrap; }
.btn-toggle-all, .btn-toggle-store-detail, .btn-print { background: #ecf5ff; color: #1890ff; border-color: #91d5ff; }
.btn-print { background: #fff7e6; color: #d48806; border-color: #ffd591; }
.store-detail-section { margin-top: 12px; padding-top: 12px; border-top: 1px dashed #dcdfe6; }
.store-detail-section h4 { margin: 0 0 10px; font-size: 13px; color: #606266; }
.store-detail-footer { margin-top: 8px; text-align: right; }

/* 多矩阵列表（按 model 分组的若干个 SKU 矩阵） */
.multi-matrix-list { display: flex; flex-direction: column; gap: 12px; margin-top: 8px; }
.multi-matrix-card { background: #fafbfc; border: 1px solid #ebeef5; border-radius: 6px; padding: 10px 12px; }
.multi-matrix-header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px dashed #dcdfe6; }
.multi-matrix-header .model-thumb-sm { width: 28px; height: 28px; object-fit: cover; border-radius: 3px; border: 1px solid #ebeef5; }
.multi-matrix-header .model-label { font-size: 13px; color: #303133; }
.multi-matrix-header .model-label strong { color: #1890ff; font-family: monospace; }
.multi-matrix-header .model-stat { font-size: 11px; color: #909399; margin-left: auto; }

.all-records-wrap { margin-bottom: 16px; max-height: none; overflow: visible; }
.mini-pagination { justify-content: flex-end; padding-top: 8px; }

/* Model bar with image */
.model-bar-item { display: flex; align-items: center; gap: 6px; position: relative; }
.model-thumb { width: 36px; height: 36px; object-fit: cover; border-radius: 4px; border: 1px solid #ebeef5; flex-shrink: 0; }
.model-thumb-placeholder { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; background: #f5f7fa; border-radius: 4px; font-size: 16px; flex-shrink: 0; }
.btn-expand-model { padding: 2px 8px; background: #409eff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; flex-shrink: 0; }
.model-sku-detail { background: #f0f2f5; border-radius: 8px; margin-top: 8px; padding: 8px 12px; }
.sku-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.sku-table th { background: #e4e7ed; color: #606266; font-weight: 600; padding: 6px 8px; text-align: left; font-size: 11px; text-transform: uppercase; }
.sku-table td { padding: 6px 8px; border-bottom: 1px solid #ebeef5; }
.sku-table tr:last-child td { border-bottom: none; }
.sku-thumb { width: 32px; height: 32px; object-fit: cover; border-radius: 4px; }
.sku-thumb-placeholder { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: #f5f7fa; border-radius: 4px; font-size: 14px; }
.sku-table .sku-code { font-family: monospace; color: #409eff; font-size: 11px; }
.sku-table .qty { text-align: right; color: #67c23a; font-weight: 600; }

.sku-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #ebeef5; }
.sku-thumb-placeholder { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: #f5f7fa; border-radius: 4px; font-size: 16px; }
.sku-code { font-family: monospace; font-size: 11px; color: #409eff; overflow: hidden; text-overflow: ellipsis; }
.sku-detail-row .qty { color: #67c23a; font-weight: 600; }

/* ============ 矩阵表格（门店/型号/SKU）紧凑版 ============ */
.matrix-table { width: 100%; border-collapse: collapse; font-size: 12px; background: white; table-layout: auto; }
.matrix-table th, .matrix-table td { padding: 3px 4px; border: 1px solid #ebeef5; text-align: center; vertical-align: middle; line-height: 1.35; }
.matrix-table th { background: #f5f7fa; font-weight: 600; color: #303133; white-space: nowrap; }
.matrix-table tbody tr:hover { background: #f5f7fa; }
.matrix-table tbody tr.expanded { background: #ecf5ff; }
.matrix-table .num { text-align: center; font-variant-numeric: tabular-nums; }
.matrix-table .qty-cell { color: #67c23a; font-weight: 600; }
.matrix-table .amount-cell { color: #e6a23c; font-weight: 600; }
.matrix-table .expand-row td { background: #fafbfc; padding: 4px 6px; }
.matrix-table .empty-cell { text-align: center; color: #909399; padding: 12px; }
.matrix-table .store-name-cell { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.matrix-table.store-matrix { table-layout: fixed; width: 100%; }
.matrix-table.store-matrix th:nth-child(1), .matrix-table.store-matrix td:nth-child(1) { width: 32px; text-align: center; }
.matrix-table.store-matrix th:nth-child(2), .matrix-table.store-matrix td:nth-child(2) { width: 160px; min-width: 160px; max-width: 160px; text-align: center; }
.matrix-table.store-matrix th:nth-child(3), .matrix-table.store-matrix td:nth-child(3) { width: 56px; text-align: center; }
.matrix-table.store-matrix th:nth-child(4), .matrix-table.store-matrix td:nth-child(4) { width: 64px; text-align: center; }
.matrix-table.store-matrix th:nth-child(5), .matrix-table.store-matrix td:nth-child(5) { width: 88px; text-align: center; }
.matrix-table.store-matrix th:nth-child(6), .matrix-table.store-matrix td:nth-child(6) { width: 52px; text-align: center; padding: 2px; }

/* 型号分布表：限高+内部滚动，避免撑爆卡片 */
.matrix-table.model-matrix thead { position: sticky; top: 0; z-index: 1; }
.matrix-table.model-matrix { table-layout: fixed; width: 100%; }
.matrix-table.model-matrix th:nth-child(1), .matrix-table.model-matrix td:nth-child(1) { width: 32px; text-align: center; }
.matrix-table.model-matrix th:nth-child(2), .matrix-table.model-matrix td:nth-child(2) { width: 48px; text-align: center; }
.matrix-table.model-matrix th:nth-child(3), .matrix-table.model-matrix td:nth-child(3) { width: 200px; min-width: 200px; max-width: 200px; text-align: center; }
.matrix-table.model-matrix th:nth-child(4), .matrix-table.model-matrix td:nth-child(4) { width: 56px; text-align: center; }
.matrix-table.model-matrix th:nth-child(5), .matrix-table.model-matrix td:nth-child(5) { width: 64px; text-align: center; }
.matrix-table.model-matrix th:nth-child(6), .matrix-table.model-matrix td:nth-child(6) { width: 88px; text-align: center; }
.matrix-table.model-matrix th:nth-child(7), .matrix-table.model-matrix td:nth-child(7) { width: 52px; text-align: center; padding: 2px; }
.matrix-table.model-matrix td.model-thumb, .matrix-table.model-matrix td.model-thumb-placeholder { padding: 1px; }
.matrix-table.model-matrix .model-thumb { width: 32px; height: 32px; }
.matrix-table.model-matrix .model-thumb-placeholder { width: 32px; height: 32px; font-size: 16px; }
/* 通用 — 加大，避免多行内容卡死 */
.matrix-table-wrap { max-height: 720px; overflow-y: auto; overflow-x: auto; border: 1px solid #ebeef5; border-radius: 4px; width: max-content; max-width: 100%; }
.matrix-table-wrap .matrix-table.model-matrix { border: none; }
.matrix-table-wrap .matrix-table.model-matrix th, .matrix-table-wrap .matrix-table.model-matrix td { border-left: none; border-right: none; }
.matrix-table-wrap .matrix-table.model-matrix thead th { border-top: none; }

/* 门店排名表：全展开，老板要看全 46 行不再卡死 */
.matrix-table-wrap.store-wrap { max-height: none; overflow-y: visible; overflow-x: auto; width: 100%; max-width: 100%; border: none; }

/* 门店明细（展开行的内层） */
.store-detail-section .matrix-table-wrap { max-height: 600px; }
/* 全记录明细（46 店的全部行，按页 50 条） */
.all-records-wrap { max-height: 600px; }

/* SKU 颜色×尺码 矩阵 — 紧凑版 */
.sku-matrix-wrap { background: white; border-radius: 4px; padding: 4px; overflow-x: auto; border: 1px solid #dcdfe6; width: max-content; max-width: 100%; }
.sku-matrix-layout { display: flex; gap: 6px; align-items: flex-start; }
.sku-matrix-right { flex: 0 0 auto; min-width: 0; }
.sku-matrix { border-collapse: collapse; table-layout: fixed; width: auto; }
.sku-matrix th, .sku-matrix td { border: 1px solid #dcdfe6 !important; }
.sku-matrix .size-col { background: #f0f2f5; width: 60px !important; min-width: 60px; max-width: 60px; padding: 3px 2px !important; font-size: 11px; font-weight: 600; text-align: center; }
.sku-matrix tbody tr:hover { background: #f5f7fa; }
.sku-matrix .empty-qty { color: #c0c4cc; }
.sku-matrix tfoot td { background: #f0f2f5; border-top: 2px solid #dcdfe6; }

/* 左侧型号大图 — 缩到 64×64，和缩略图比例协调 */
.model-image-cell { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 2px; background: #fafbfc; border-radius: 4px; min-width: 72px; flex-shrink: 0; }
.model-image-large { width: 64px; height: 64px; object-fit: cover; border-radius: 4px; border: 1px solid #ebeef5; }
.model-image-placeholder { width: 64px; height: 64px; display: flex; align-items: center; justify-content: center; background: #f5f7fa; border-radius: 4px; font-size: 24px; }
.model-image-label { font-size: 9px; color: #909399; font-weight: 700; letter-spacing: 1px; }

/* 颜色列 — 紧凑，固定宽 90px（颜色名最长 ~JGRN-BRZ）*/
.sku-matrix .color-col { background: #f0f2f5; font-size: 11px; color: #606266; width: 90px !important; min-width: 90px; max-width: 90px; padding: 3px 2px !important; font-weight: 600; text-align: center; }
.sku-matrix .color-cell { background: #fafbfc; font-size: 11px; color: #303133; font-weight: 600; width: 90px !important; min-width: 90px; max-width: 90px; padding: 3px 2px !important; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sku-matrix .cell-data { padding: 3px 2px !important; vertical-align: middle; width: 60px !important; min-width: 60px; max-width: 60px; text-align: center; }
.sku-matrix .cell-sku { display: flex; flex-direction: column; align-items: center; gap: 8px; line-height: 1; width: auto; min-width: 0; }
.sku-matrix .sku-num { font-family: monospace; font-size: 10px; color: #409eff; border-bottom: 1px solid #409eff; padding: 0 2px 1px; text-align: center; font-weight: 600; }
.sku-matrix .sku-qty { font-size: 10px; color: #67c23a; font-weight: 600; text-align: center; }
</style>