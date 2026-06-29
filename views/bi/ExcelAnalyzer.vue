<template>
  <div class="excel-analyzer">
    <div class="header">
      <h1>📊 {{ $t('excelAnalyzer.pageTitle') }}</h1>
    </div>

    <!-- Upload Area -->
    <div class="upload-area" v-if="!hasData">
      <div class="upload-box" @dragover.prevent @drop.prevent="handleDrop" @click="triggerUpload">
        <div class="upload-icon">📁</div>
        <div class="upload-text">{{ $t('excelAnalyzer.dragDropFile') }}</div>
        <div class="upload-text">{{ $t('excelAnalyzer.orClickSelect') }}</div>
        <div class="upload-hint">.xlsx, .xls</div>
      </div>
      <input ref="fileInputRef" type="file" accept=".xlsx,.xls" multiple style="display:none" @change="handleFileSelect" />
    </div>

    <!-- Filter Controls for APPOLLOS -->
    <div class="filter-section" v-if="hasData && report.dataType === 'APPOLLOS'">
      <h3>🔍 {{ $t('excelAnalyzer.dataFilter') }}</h3>
      <div class="filter-row">
        <div class="filter-item">
          <label>{{ $t('excelAnalyzer.sclassCategory') }}</label>
          <select v-model="filterSclass">
            <option value="">{{ $t('excelAnalyzer.all') }}</option>
            <option v-for="s in sclassList" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
        <div class="filter-item">
          <label>{{ $t('excelAnalyzer.priceRange') }}</label>
          <select v-model="filterPrice">
            <option value="">{{ $t('excelAnalyzer.all') }}</option>
            <option value="0-5000">'0-5000'</option>
            <option value="5000-8000">'5000-8000'</option>
            <option value="8000-10000">'8000-10000'</option>
            <option value="10000+">'10000+'</option>
          </select>
        </div>
        <div class="filter-item">
          <label>{{ $t('excelAnalyzer.keywordSearch') }}</label>
          <input type="text" v-model="filterKeyword" :placeholder="$t('excelAnalyzer.inputKeywordPlaceholder')">
        </div>
        <div class="filter-item filter-buttons">
          <button class="btn-small" @click="applyFilter">{{ $t('excelAnalyzer.applyFilter') }}</button>
          <button class="btn-small" @click="resetFilter">{{ $t('excelAnalyzer.reset') }}</button>
        </div>
      </div>
      <div class="filter-stats">
        {{ $t('excelAnalyzer.currentFilterResults') }}: {{ filteredItems.length }} / {{ report.items?.length || 0 }} {{ $t('excelAnalyzer.items') }}
      </div>
    </div>

    <!-- Results -->
    <div v-if="hasData" class="report">
      <!-- File Info -->
      <div class="report-section">
        <h2>📋 {{ $t('excelAnalyzer.fileInfo') }}</h2>
        <table class="info-table">
          <tr><td>{{ $t('excelAnalyzer.fileName') }}</td><td>{{ fileName }}</td></tr>
          <tr><td>{{ $t('excelAnalyzer.supplier') }}</td><td>{{ report.supplier }}</td></tr>
          <tr><td>{{ $t('excelAnalyzer.brand') }}</td><td>{{ report.brand }}</td></tr>
          <tr><td>{{ $t('excelAnalyzer.dateRange') }}</td><td>{{ report.dateRange }}</td></tr>
        </table>
      </div>

      <!-- Core Stats -->
      <div class="report-section">
        <h2>📈 {{ $t('excelAnalyzer.coreData') }}</h2>
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-value">{{ report.totalQty?.toLocaleString() }}</div>
            <div class="stat-label">{{ $t('excelAnalyzer.totalQty') }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">{{ report.totalRecords?.toLocaleString() }}</div>
            <div class="stat-label">{{ $t('excelAnalyzer.totalRecords') }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">{{ report.uniqueSKU?.toLocaleString() }}</div>
            <div class="stat-label">{{ $t('excelAnalyzer.skuCount') }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">{{ report.uniqueStores }}</div>
            <div class="stat-label">{{ $t('excelAnalyzer.storeCount') }}</div>
          </div>
        </div>
      </div>

      <!-- APPOLLOS: SCLASS Distribution -->
      <div class="report-section" v-if="(report.dataType === 'APPOLLOS' || report.dataType === 'APPOLLOS_MULTI') && report.sclassDist?.length">
        <h2>🏷️ {{ $t('excelAnalyzer.sclassDistribution') }}</h2>
        <table class="data-table">
          <thead><tr><th>#</th><th>SCLASS</th><th>{{ $t('excelAnalyzer.productCount') }}</th><th>%</th></tr></thead>
          <tbody>
            <tr v-for="(s, i) in report.sclassDist" :key="i">
              <td class="rank">{{ i + 1 }}</td>
              <td>{{ s.name }}</td>
              <td>{{ s.count?.toLocaleString() }}</td>
              <td>{{ s.percent }}%</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Multi-File Comparison -->
      <div class="report-section" v-if="report.dataType === 'APPOLLOS_MULTI' && report.fileComparison?.length">
        <h2>📊 {{ $t('excelAnalyzer.multiFileComparison') }}</h2>
        <div v-for="(fc, fi) in report.fileComparison" :key="fi" class="file-comparison">
          <h3>📁 {{ fc.source }} <span class="file-total">({{ fc.total }} products)</span></h3>
          <table class="data-table">
            <thead><tr><th>#</th><th>SCLASS</th><th>{{ $t('excelAnalyzer.count') }}</th><th>%</th></tr></thead>
            <tbody>
              <tr v-for="(s, si) in fc.sclassBreakdown.slice(0, 15)" :key="si">
                <td class="rank">{{ si + 1 }}</td>
                <td>{{ s.name }}</td>
                <td>{{ s.count?.toLocaleString() }}</td>
                <td>{{ s.percent }}%</td>
              </tr>
            </tbody>
          </table>
          <div v-if="fc.sclassBreakdown.length > 15" class="more-info">
            +{{ fc.sclassBreakdown.length - 15 }} {{ $t('excelAnalyzer.moreCategories') }}
          </div>
        </div>
      </div>

      <!-- APPOLLOS: Price Distribution -->
      <div class="report-section" v-if="(report.dataType === 'APPOLLOS' || report.dataType === 'APPOLLOS_MULTI') && report.priceDist?.length">
        <h2>💰 {{ $t('excelAnalyzer.priceDistribution') }}</h2>
        <table class="data-table">
          <thead><tr><th>#</th><th>{{ $t('excelAnalyzer.priceRange') }}</th><th>{{ $t('excelAnalyzer.productCount') }}</th><th>%</th></tr></thead>
          <tbody>
            <tr v-for="(p, i) in report.priceDist" :key="i">
              <td class="rank">{{ i + 1 }}</td>
              <td>{{ p.name }}</td>
              <td>{{ p.count?.toLocaleString() }}</td>
              <td>{{ p.percent }}%</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- APPOLLOS: Items Table -->
      <div class="report-section" v-if="(report.dataType === 'APPOLLOS' || report.dataType === 'APPOLLOS_MULTI') && filteredItems.length">
        <h2>📦 {{ $t('excelAnalyzer.productDetails') }} ({{ filteredItems.length }})</h2>
        <table class="data-table">
          <thead><tr><th>SKU</th><th>{{ $t('excelAnalyzer.image') }}</th><th>{{ $t('excelAnalyzer.productName') }}</th><th>{{ $t('excelAnalyzer.description') }}</th><th>SCLASS</th><th>{{ $t('excelAnalyzer.price') }}</th><th v-if="report.isMultiFile">{{ $t('excelAnalyzer.source') }}</th></tr></thead>
          <tbody>
            <tr v-for="(item, i) in filteredItems.slice(0, 500)" :key="i">
              <td>{{ item.sku }}</td>
              <td>
                <img v-if="getItemImage(item)" :src="getItemImage(item)" alt="" class="w-10 h-10 object-cover rounded border border-gray-100" />
                <div v-else class="w-10 h-10 rounded border border-gray-100 bg-gray-50 flex items-center justify-center">
                  <span class="material-symbols-outlined text-gray-300 text-[16px]">image</span>
                </div>
              </td>
              <td>{{ getItemName(item) }}</td>
              <td>{{ item.description }}</td>
              <td>{{ item.sclass }}</td>
              <td>{{ item.price?.toLocaleString() }}</td>
              <td v-if="report.isMultiFile" class="source-tag">{{ item._sourceFile }}</td>
            </tr>
          </tbody>
        </table>
        <div v-if="filteredItems.length > 500" class="more-info">
          {{ $t('excelAnalyzer.showingFirstOf') }} {{ filteredItems.length }}
        </div>
      </div>

      <!-- Store Distribution -->
      <div class="report-section" v-if="report.allStores?.length">
        <h2>🏪 {{ $t('excelAnalyzer.storeAnalysis') }} ({{ report.uniqueStores || 0 }})</h2>

        <!-- 搜索栏：SM 原图 Filter by SKU / Filter by Store / Stock # -->
        <div class="store-filter-bar">
          <input v-model="storeFilter.sku" :placeholder="$t('excelAnalyzer.filterBySku')" class="filter-input" @keyup.enter="applyStoreFilter" />
          <input v-model="storeFilter.store" :placeholder="$t('excelAnalyzer.filterByStore')" class="filter-input" @keyup.enter="applyStoreFilter" />
          <input v-model="storeFilter.stockNum" :placeholder="$t('excelAnalyzer.filterByStockNum')" class="filter-input" @keyup.enter="applyStoreFilter" />
          <button class="filter-btn primary" @click="applyStoreFilter">{{ $t('excelAnalyzer.apply') }}</button>
          <button class="filter-btn" @click="clearStoreFilter">{{ $t('excelAnalyzer.clear') }}</button>
          <button class="filter-btn export" @click="exportStoreCsv">📥 {{ $t('excelAnalyzer.exportCsv') }}</button>
          <button class="filter-btn print" @click="printCurrentStores">🖨️ {{ $t('excelAnalyzer.printCurrentBranch') }}</button>
          <button class="filter-btn print" @click="printWholeMonthReport">🖨️ {{ $t('excelAnalyzer.printWholeMonth') }}</button>
          <button class="filter-btn" @click="hideStoreChart = !hideStoreChart">📊 {{ hideStoreChart ? $t('excelAnalyzer.showChart') : $t('excelAnalyzer.hideChart') }}</button>
        </div>

        <table class="data-table">
          <thead><tr><th>#</th><th>{{ $t('excelAnalyzer.store') }}</th><th>{{ $t('excelAnalyzer.items') }}</th><th>{{ $t('excelAnalyzer.salesQty') }}</th><th>{{ $t('excelAnalyzer.salesAmount') }}</th><th>%</th><th>{{ $t('excelAnalyzer.action') }}</th></tr></thead>
          <tbody>
            <template v-for="(s, i) in filteredStores" :key="s.name">
              <tr>
                <td class="rank">{{ i + 1 }}</td>
                <td>{{ s.name }}</td>
                <td>{{ s.items?.toLocaleString() }}</td>
                <td>{{ s.qty?.toLocaleString() }}</td>
                <td>¥{{ (s.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</td>
                <td>{{ s.percent }}%</td>
                <td>
                  <button class="filter-btn small" @click="toggleStoreExpand(s.name)">
                    {{ expandedStores.has(s.name) ? $t('excelAnalyzer.collapse') : $t('excelAnalyzer.expand') }}
                  </button>
                </td>
              </tr>
              <tr v-if="expandedStores.has(s.name)" class="store-detail-row">
                <td colspan="7">
                  <table class="nested-table">
                    <thead><tr><th>SKU #</th><th>{{ $t('excelAnalyzer.description') }}</th><th>{{ $t('excelAnalyzer.qty') }}</th></tr></thead>
                    <tbody>
                      <tr v-for="(sd, sku) in getStoreSkuDetail(s.name)" :key="sku">
                        <td>{{ sku }}</td>
                        <td>{{ sd.desc }}</td>
                        <td>{{ sd.qty?.toLocaleString() }}</td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Size Distribution -->
      <div class="report-section" v-if="report.sizeDistribution?.length">
        <h2>📐 {{ $t('excelAnalyzer.sizeDistribution') }}</h2>
        <table class="data-table">
          <thead><tr><th>#</th><th>{{ $t('excelAnalyzer.size') }}</th><th>{{ $t('excelAnalyzer.qty') }}</th><th>%</th></tr></thead>
          <tbody>
            <tr v-for="(s, i) in report.sizeDistribution" :key="i">
              <td class="rank">{{ i + 1 }}</td><td>{{ s.name }}</td><td>{{ s.qty?.toLocaleString() }}</td><td>{{ s.percent }}%</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Color Distribution -->
      <div class="report-section" v-if="report.colorDistribution?.length">
        <h2>🎨 {{ $t('excelAnalyzer.colorDistribution') }}</h2>
        <table class="data-table">
          <thead><tr><th>#</th><th>{{ $t('excelAnalyzer.color') }}</th><th>{{ $t('excelAnalyzer.qty') }}</th><th>%</th></tr></thead>
          <tbody>
            <tr v-for="(c, i) in report.colorDistribution" :key="i">
              <td class="rank">{{ i + 1 }}</td><td>{{ c.name }} ({{ c.code }})</td><td>{{ c.qty?.toLocaleString() }}</td><td>{{ c.percent }}%</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- SKU+Size+Color Combo Analysis -->
      <div class="report-section" v-if="report.comboAnalysis?.top?.length">
        <h2>📊 SKU×尺寸×颜色 {{ $t('excelAnalyzer.comboAnalysis') }}</h2>
        
        <!-- Top 10 -->
        <div class="combo-section">
          <h3>🔺 {{ $t('excelAnalyzer.top10') }}</h3>
          <table class="data-table">
            <thead><tr><th>#</th><th>SKU</th><th>{{ $t('excelAnalyzer.size') }}</th><th>{{ $t('excelAnalyzer.color') }}</th><th>{{ $t('excelAnalyzer.qty') }}</th></tr></thead>
            <tbody>
              <tr v-for="(c, i) in report.comboAnalysis.top" :key="i">
                <td class="rank">{{ i + 1 }}</td>
                <td>{{ c.sku }}</td>
                <td>{{ c.size }}</td>
                <td>{{ c.colorName }} ({{ c.color }})</td>
                <td class="qty-high">{{ c.qty?.toLocaleString() }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <!-- Bottom 10 -->
        <div class="combo-section" v-if="report.comboAnalysis.bottom?.length">
          <h3>🔻 {{ $t('excelAnalyzer.bottom10') }}</h3>
          <table class="data-table">
            <thead><tr><th>#</th><th>SKU</th><th>{{ $t('excelAnalyzer.size') }}</th><th>{{ $t('excelAnalyzer.color') }}</th><th>{{ $t('excelAnalyzer.qty') }}</th></tr></thead>
            <tbody>
              <tr v-for="(c, i) in report.comboAnalysis.bottom" :key="i">
                <td class="rank">{{ report.comboAnalysis.top.length + i + 1 }}</td>
                <td>{{ c.sku }}</td>
                <td>{{ c.size }}</td>
                <td>{{ c.colorName }} ({{ c.color }})</td>
                <td class="qty-low">{{ c.qty?.toLocaleString() }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Insights -->
      <div class="report-section insights" v-if="localizedInsights.length">
        <h2>💡 {{ $t('excelAnalyzer.keyInsights') }}</h2>
        <ul>
          <li v-for="(ins, i) in localizedInsights" :key="i">{{ ins }}</li>
        </ul>
      </div>

      <!-- Actions -->
      <div class="actions">
        <button class="btn" @click="reset">{{ $t('excelAnalyzer.uploadNew') }}</button>
        <button class="btn btn-save" @click="saveReport">{{ $t('excelAnalyzer.saveReport') }}</button>
      </div>
      <div class="save-success" v-if="showSaveSuccess">
        ✅ {{ $t('excelAnalyzer.reportSaved') }}
        <button @click="goManage">{{ $t('excelAnalyzer.viewReport') }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import i18n from '@/i18n'
import api from '../../services/api.js'

const fileInputRef = ref(null)
const hasData = ref(false)
const fileName = ref('')
const report = ref({})
const showSaveSuccess = ref(false)
const uploadedFile = ref(null) // 存储已上传的原始File对象，供saveReport使用

// 商品SKU对照表：sku -> { name, image_main }
const productMap = ref({})

// 型号->图片映射：从商品名称提取6位型号，映射到image_main
// 例如 "VOYAGER HC LUGG 555165A 28 BLK" -> extract "555165A" -> image
const modelImageMap = ref({})

// 加载商品SKU对照表
async function loadProductMap() {
  try {
    const res = await api.get('/products', { params: { size: 10000 } })
    if (res.code === 0) {
      const list = res.data.list || res.data || []
      const map = {}
      const modelMap = {}
      list.forEach(p => {
        if (p.sku) map[String(p.sku)] = p
        // 从商品名称提取6位型号建立图片映射
        if (p.image_main) {
          const m = String(p.name || '').match(/\d{6}[A-Z]/)
          if (m) modelMap[m[0]] = p.image_main
        }
      })
      productMap.value = map
      modelImageMap.value = modelMap
    }
  } catch (e) {
    console.error('[ExcelAnalyzer] 加载商品表失败', e)
  }
}

// 从描述文本提取6位型号
function extractModel(desc) {
  if (!desc) return null
  const m = String(desc).match(/\d{6}[A-Z]/)
  return m ? m[0] : null
}

// 获取商品图片：优先按SKU精确匹配，其次按描述中的6位型号模糊匹配
function getItemImage(item) {
  // 1. 先按SKU精确查（适合日期格式SKU的商品）
  if (productMap.value[item.sku]?.image_main) return productMap.value[item.sku].image_main
  // 2. 按描述中的型号查（如 "VOYAGER HARDCASE LUGGAGE 551563A 28 BRONZE" -> 提取 "551563A"）
  const model = extractModel(item.description)
  if (model && modelImageMap.value[model]) return modelImageMap.value[model]
  return null
}

// 获取商品名称：优先按SKU精确查，其次按描述型号模糊匹配
function getItemName(item) {
  if (productMap.value[item.sku]?.name) return productMap.value[item.sku].name
  const model = extractModel(item.description)
  if (model) {
    // 找型号相同的所有商品，返回第一个
    const found = Object.values(productMap.value).find(p => {
      const pm = String(p.name || '').match(/\d{6}[A-Z]/)
      return pm && pm[0] === model
    })
    if (found) return found.name
  }
  return '-'
}

onMounted(loadProductMap)

// 使用 i18n 管理语言
const { t, locale } = i18n.global

// APPOLLOS筛选相关
const filterSclass = ref('')
const filterPrice = ref('')
const filterKeyword = ref('')
const filteredItems = ref([])
const sclassList = ref([])

// SM 原图 1.A/1.B 门店销售排名 筛选（SKU / Store / Stock #）
const storeFilter = ref({ sku: '', store: '', stockNum: '' })
const expandedStores = ref(new Set())
const hideStoreChart = ref(false)
const filteredStores = computed(() => {
  const list = report.value.allStores || []
  const skuKw = storeFilter.value.sku.trim().toLowerCase()
  const storeKw = storeFilter.value.store.trim().toLowerCase()
  const stockKw = storeFilter.value.stockNum.trim().toLowerCase()
  return list.filter(s => {
    if (storeKw && !s.name.toLowerCase().includes(storeKw)) return false
    if (skuKw || stockKw) {
      const detail = report.value.storeSkus?.[s.name] || {}
      const skus = Object.keys(detail)
      const skuMatch = skuKw ? skus.some(k => k.toLowerCase().includes(skuKw)) : true
      const stockMatch = stockKw ? skus.some(k => k.toLowerCase().includes(stockKw)) : true
      if (!skuMatch || !stockMatch) return false
    }
    return true
  })
})
function toggleStoreExpand(name) {
  if (expandedStores.value.has(name)) expandedStores.value.delete(name)
  else expandedStores.value.add(name)
  // 触发响应式
  expandedStores.value = new Set(expandedStores.value)
}
function applyStoreFilter() { /* computed 自动响应，no-op 但保留按钮交互 */ }
function clearStoreFilter() {
  storeFilter.value = { sku: '', store: '', stockNum: '' }
}
function getStoreSkuDetail(name) {
  return report.value.storeSkus?.[name] || {}
}
function exportStoreCsv() {
  const rows = [['#', 'Store', 'Items', 'Sales Qty', 'Sales Amount', 'Percent']]
  filteredStores.value.forEach((s, i) => {
    rows.push([i + 1, s.name, s.items, s.qty, s.amount || 0, s.percent + '%'])
  })
  const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `store_sales_${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

// 打印当前筛选的门店报告（按当前 Filter by SKU/Store/Stock # 结果）
function printCurrentStores() {
  const totalStores = filteredStores.value.length
  const totalQty = filteredStores.value.reduce((sum, s) => sum + (s.qty || 0), 0)
  const totalAmount = filteredStores.value.reduce((sum, s) => sum + (s.amount || 0), 0)
  const date = new Date().toLocaleString()
  const filterDesc = [
    storeFilter.value.sku && `SKU: ${storeFilter.value.sku}`,
    storeFilter.value.store && `Store: ${storeFilter.value.store}`,
    storeFilter.value.stockNum && `Stock#: ${storeFilter.value.stockNum}`
  ].filter(Boolean).join(' | ') || 'No filter'

  const rows = filteredStores.value.map((s, i) => `
    <tr>
      <td>${i + 1}</td>
      <td>${s.name}</td>
      <td style="text-align:right">${s.items}</td>
      <td style="text-align:right">${s.qty}</td>
      <td style="text-align:right">¥${(s.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
      <td style="text-align:right">${s.percent}%</td>
    </tr>`).join('')

  const html = `
    <!DOCTYPE html><html><head><meta charset="UTF-8"><title>Store Report</title>
    <style>
      body { font-family: Arial, sans-serif; padding: 20px; }
      h1 { margin: 0 0 8px; font-size: 22px; }
      .meta { color: #555; font-size: 12px; margin-bottom: 16px; }
      table { width: 100%; border-collapse: collapse; font-size: 13px; }
      th, td { border: 1px solid #ccc; padding: 6px 10px; }
      th { background: #f4f4f4; }
      .summary { margin-top: 16px; font-size: 13px; color: #333; }
    </style></head><body>
    <h1>Store Sales Report (${totalStores} stores)</h1>
    <div class="meta">
      File: ${fileName.value || '-'} | Supplier: ${report.value.supplier || '-'} | Brand: ${report.value.brand || '-'} | Date Range: ${report.value.dateRange || '-'} | Filter: ${filterDesc} | Printed: ${date}
    </div>
    <table>
      <thead><tr><th>#</th><th>Store</th><th>Items</th><th>Sales Qty</th><th>Sales Amount</th><th>%</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>
    <div class="summary">
      <strong>Summary:</strong> ${totalStores} stores, ${totalQty} pcs total, ¥${totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} total
    </div>
    </body></html>`
  openPrintWindow(html)
}

// 打印整月完整报告（全部门店 + 全部 SKU 明细）
function printWholeMonthReport() {
  const totalStores = report.value.allStores?.length || 0
  const totalQty = report.value.allStores?.reduce((sum, s) => sum + (s.qty || 0), 0) || 0
  const totalAmount = report.value.allStores?.reduce((sum, s) => sum + (s.amount || 0), 0) || 0
  const date = new Date().toLocaleString()

  // 门店汇总
  const storeRows = (report.value.allStores || []).map((s, i) => `
    <tr>
      <td>${i + 1}</td>
      <td>${s.name}</td>
      <td style="text-align:right">${s.items}</td>
      <td style="text-align:right">${s.qty}</td>
      <td style="text-align:right">¥${(s.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
      <td style="text-align:right">${s.percent}%</td>
    </tr>`).join('')

  // SKU 明细（前 50，按门店分组）
  let detailRows = ''
  const storeSkus = report.value.storeSkus || {}
  Object.entries(storeSkus).forEach(([store, skus]) => {
    detailRows += `<tr style="background:#f9f9f9"><td colspan="4" style="font-weight:bold">${store}</td></tr>`
    Object.entries(skus).slice(0, 50).forEach(([sku, sd]) => {
      detailRows += `<tr><td>${sku}</td><td>${sd.desc || ''}</td><td style="text-align:right">${sd.qty}</td><td></td></tr>`
    })
  })

  const html = `
    <!DOCTYPE html><html><head><meta charset="UTF-8"><title>Whole Month Report</title>
    <style>
      body { font-family: Arial, sans-serif; padding: 20px; }
      h1 { margin: 0 0 8px; font-size: 22px; page-break-before: avoid; }
      h2 { margin: 16px 0 8px; font-size: 16px; page-break-after: avoid; }
      .meta { color: #555; font-size: 12px; margin-bottom: 16px; }
      table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 12px; }
      th, td { border: 1px solid #ccc; padding: 4px 8px; }
      th { background: #f4f4f4; }
      .summary { margin-top: 16px; font-size: 13px; color: #333; }
      @media print { .page-break { page-break-before: always; } }
    </style></head><body>
    <h1>Whole Month Sales Report</h1>
    <div class="meta">
      File: ${fileName.value || '-'} | Supplier: ${report.value.supplier || '-'} | Brand: ${report.value.brand || '-'} | Date Range: ${report.value.dateRange || '-'} | Printed: ${date}
    </div>

    <h2>Store Summary (${totalStores} stores)</h2>
    <table>
      <thead><tr><th>#</th><th>Store</th><th>Items</th><th>Sales Qty</th><th>Sales Amount</th><th>%</th></tr></thead>
      <tbody>${storeRows}</tbody>
    </table>
    <div class="summary">
      <strong>Monthly Summary:</strong> ${totalStores} stores, ${totalQty} pcs total, ¥${totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} total
    </div>

    <h2 class="page-break">SKU Detail by Store</h2>
    <table>
      <thead><tr><th>SKU #</th><th>Description</th><th>Qty</th><th></th></tr></thead>
      <tbody>${detailRows || '<tr><td colspan="4" style="text-align:center;color:#999">No detail data</td></tr>'}</tbody>
    </table>
    </body></html>`
  openPrintWindow(html)
}

// 打开新窗口打印（避免 SPA 路由干扰）
function openPrintWindow(html) {
  const win = window.open('', '_blank', 'width=900,height=700')
  if (!win) {
    alert(locale.value === 'zh' ? '请允许弹窗以打印' : 'Please allow popups to print')
    return
  }
  win.document.write(html)
  win.document.close()
  win.focus()
  // 等待资源加载后触发打印
  setTimeout(() => { win.print() }, 300)
}

// 筛选computed
const filteredReport = computed(() => {
  if (!report.value.items || report.value.items.length === 0) return report.value
  return report.value
})

function applyFilter() {
  if (!report.value.items) return
  let items = [...report.value.items]
  
  // 按SCLASS筛选
  if (filterSclass.value) {
    items = items.filter(i => i.sclass == filterSclass.value)
  }
  
  // 按价格区间筛选
  if (filterPrice.value) {
    const [min, max] = filterPrice.value.split('-').map(v => v === '+' ? Infinity : parseInt(v))
    items = items.filter(i => {
      const p = i.price || 0
      if (filterPrice.value.endsWith('+')) return p >= min
      return p >= min && p <= max
    })
  }
  
  // 按关键词筛选
  if (filterKeyword.value) {
    const kw = filterKeyword.value.toLowerCase()
    items = items.filter(i => (i.description || '').toLowerCase().includes(kw) || (i.sku || '').toLowerCase().includes(kw))
  }
  
  filteredItems.value = items
}

function resetFilter() {
  filterSclass.value = ''
  filterPrice.value = ''
  filterKeyword.value = ''
  filteredItems.value = report.value.items || []
}

function getSclassListFromItems(items) {
  if (!items || items.length === 0) return []
  const sclases = [...new Set(items.map(i => i.sclass).filter(Boolean))]
  return sclases.sort((a, b) => a - b)
}

function getSclassList() {
  return getSclassListFromItems(report.value.items)
}

const colorNames = {
  'BLK': { zh: '黑色', en: 'Black' },
  'DGRY': { zh: '深灰', en: 'Dark Grey' },
  'GRY': { zh: '灰色', en: 'Grey' },
  'RGLD': { zh: '玫瑰金', en: 'Rose Gold' },
  'GRN': { zh: '绿色', en: 'Green' },
  'SIL': { zh: '银色', en: 'Silver' },
  'WHT': { zh: '白色', en: 'White' },
  'RED': { zh: '红色', en: 'Red' },
  'BLU': { zh: '蓝色', en: 'Blue' },
  'ORG': { zh: '橙色', en: 'Orange' },
  'YLW': { zh: '黄色', en: 'Yellow' },
  'PK': { zh: '粉色', en: 'Pink' },
  'BRN': { zh: '棕色', en: 'Brown' },
  'NVY': { zh: '藏青', en: 'Navy' },
  'KHA': { zh: '卡其', en: 'Khaki' },
  'LBL': { zh: '浅蓝', en: 'Light Blue' },
  'DBR': { zh: '深棕', en: 'Dark Brown' },
  'BGR': { zh: '浅绿', en: 'Light Green' },
  'MIR': { zh: '镜面', en: 'Mirror' },
  'GLD': { zh: '金色', en: 'Gold' }
}

// 颜色代码检测正则（从SKU或描述末尾提取）
const colorCodes = Object.keys(colorNames).join('|')
const colorRegex = new RegExp(`(${colorCodes})\\s*$`, 'i')

function getColorName(code) {
  return colorNames[code]?.[locale.value] || code
}

// 语言相关的computed - 实时响应语言切换
const localizedInsights = computed(() => {
  if (!report.value.insights) return []
  return report.value.insights.map(ins => {
    if (typeof ins === 'string') return ins
    if (ins.type === 'merge_count') {
      return `${ins.fileCount} ${locale.value === 'zh' ? '个文件合并' : 'files merged'}`
    }
    if (ins.type === 'product_count') {
      return `${ins.count} ${locale.value === 'zh' ? '个商品' : 'products total'}`
    }
    if (ins.type === 'category_count') {
      return `${ins.count} ${locale.value === 'zh' ? '个SCLASS分类' : 'SCLASS categories'}`
    }
    if (ins.type === 'best_size') {
      return `${ins.name} ${locale.value === 'zh' ? '是最畅销尺寸' : 'is the best seller (size)'}`
    }
    if (ins.type === 'best_color') {
      return `${getColorName(ins.name)} ${locale.value === 'zh' ? '是最畅销颜色' : 'is the best seller (color)'}`
    }
    if (ins.type === 'top_stores') {
      return `${ins.names} ${locale.value === 'zh' ? '贡献最多销量' : 'contribute most sales'}`
    }
    if (ins.type === 'store_count') {
      return `${ins.count} ${locale.value === 'zh' ? '个门店' : 'stores'}`
    }
    if (ins.type === 'sku_count') {
      return `${ins.count} ${locale.value === 'zh' ? '个SKU' : 'SKUs'}`
    }
    return ins.text || JSON.stringify(ins)
  })
})

function setLang(l) {
  locale.value = l
  localStorage.setItem('caimeite_locale', l)
}

function triggerUpload() {
  fileInputRef.value?.click()
}

async function handleFileSelect(e) {
  const files = Array.from(e.target.files)
  if (files.length === 0) return
  // 确保商品SKU对照表已加载（用于 Sales Amount 计算：productMap[sku].price）
  if (Object.keys(productMap.value).length === 0) {
    await loadProductMap()
  }
  if (files.length === 1) {
    uploadedFile.value = files[0] // 保存原始File对象供saveReport使用
    analyzeFile(files[0])
  } else {
    // 多文件：同类型文件合并分析
    const fileNames = files.map(f => f.name).join(', ')
    if (files.every(f => f.name.endsWith('.xlsx') || f.name.endsWith('.xls'))) {
      // 批量分析（仅支持同类型文件）
      analyzeMultipleFiles(files)
    } else {
      // 有非Excel文件，只分析第一个
      analyzeFile(files[0])
    }
  }
}

function analyzeMultipleFiles(files) {
  console.log('[ExcelAnalyzer] Processing ' + files.length + ' files')
  
  if (files.length === 0) return
  
  // 分析所有文件，收集数据
  Promise.all(files.map(f => analyzeFileAsync(f)))
    .then(results => {
      const validResults = results.filter(r => r && r.items && r.items.length > 0)
      
      if (validResults.length === 0) {
        alert(locale.value === 'zh' ? '没有找到有效数据' : 'No valid data found')
        return
      }
      
      if (validResults.length === 1) {
        // 单个文件，直接使用
        uploadedFile.value = files[0] // 多文件时保存第一个文件供saveReport使用
        report.value = validResults[0]
        initFilterData()
        return
      }
      
      // 多文件合并分析
      mergeMultipleReports(validResults)
    })
    .catch(err => {
      console.error('[ExcelAnalyzer] Multi-file error:', err)
      // fallback: 分析第一个
      if (files[0]) analyzeFile(files[0])
    })
}

function handleDrop(e) {
  const files = Array.from(e.dataTransfer.files)
  if (files.length <= 1) {
    const file = files[0]
    if (file) {
      uploadedFile.value = file // 保存原始File对象供saveReport使用
      analyzeFile(file)
    }
  } else {
    // 多个文件
    analyzeMultipleFiles(e.dataTransfer.files)
  }
}

// 异步分析单个文件
async function analyzeFileAsync(file) {
  return new Promise((resolve) => {
    const reader = new FileReader()
    reader.onload = async (e) => {
      try {
        const data = new Uint8Array(e.target.result)
        const XLSX = await import('xlsx')
        const workbook = XLSX.read(data, { type: 'array', cellDates: true })
        const sheetName = workbook.SheetNames[0]
        const jsonData = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], { header: 1 })
        // APPOLLOS格式: 找实际数据行(含最多非空值的行作为表头)
        let headerRow = jsonData[0] || []
        let maxCols = (headerRow || []).filter(v => v != null).length
        for (let i = 1; i < Math.min(jsonData.length, 10); i++) {
          const row = jsonData[i] || []
          const nonNull = row.filter(v => v != null).length
          if (nonNull > maxCols) {
            headerRow = row
            maxCols = nonNull
          }
        }
        const headers = (headerRow || []).map((name, idx) => ({ name: String(name || '').trim(), index: idx }))

        // 检测是否为APPOLLOS格式
        const idxSclass = headers.findIndex(h => /^sclass$/i.test(h.name))

        if (idxSclass !== -1) {
          const result = analyzeAppollosFileData(jsonData, headers, idxSclass, file.name)
          resolve(result)
        } else {
          resolve(null)
        }
      } catch (err) {
        console.error('[ExcelAnalyzer] File error:', file.name, err)
        resolve(null)
      }
    }
    reader.onerror = () => resolve(null)
    reader.readAsArrayBuffer(file)
  })
}

// 合并多个报告
function mergeMultipleReports(reports) {
  const langVal = locale.value
  
  // 合并所有items
  let allItems = []
  let fileSources = []
  
  reports.forEach((r, idx) => {
    if (r.items) {
      // 标记来源文件
      const taggedItems = r.items.map(item => ({
        ...item,
        _sourceFile: r.supplier || 'File ' + (idx + 1)
      }))
      allItems.push(...taggedItems)
      fileSources.push(r.supplier || 'File ' + (idx + 1))
    }
  })
  
  // 统计SCLASS分布（跨文件）
  const sclassMap = {}
  const fileSclassMap = {} // 每个文件的SCLASS分布
  
  allItems.forEach(item => {
    if (item.sclass) {
      sclassMap[item.sclass] = (sclassMap[item.sclass] || 0) + 1
      
      // 按来源文件统计
      const src = item._sourceFile
      if (!fileSclassMap[src]) fileSclassMap[src] = {}
      fileSclassMap[src][item.sclass] = (fileSclassMap[src][item.sclass] || 0) + 1
    }
  })
  
  // 价格统计
  let priceMin = Infinity, priceMax = 0, totalPrice = 0
  allItems.forEach(item => {
    if (item.price > 0) {
      priceMin = Math.min(priceMin, item.price)
      priceMax = Math.max(priceMax, item.price)
      totalPrice += item.price
    }
  })
  
  // SCLASS分布
  const sclassDist = Object.entries(sclassMap)
    .map(([name, count]) => ({ name, count, percent: ((count / allItems.length) * 100).toFixed(1) }))
    .sort((a, b) => b.count - a.count)
  
  // 价格分布
  const priceRanges = { '0-5000': 0, '5000-8000': 0, '8000-10000': 0, '10000+': 0 }
  allItems.forEach(i => {
    if (i.price <= 5000) priceRanges['0-5000']++
    else if (i.price <= 8000) priceRanges['5000-8000']++
    else if (i.price <= 10000) priceRanges['8000-10000']++
    else priceRanges['10000+']++
  })
  const priceDist = Object.entries(priceRanges)
    .map(([name, count]) => ({ name, count, percent: ((count / allItems.length) * 100).toFixed(1) }))
    .filter(p => p.count > 0)
  
  // 每文件SCLASS对比
  const fileComparison = fileSources.map(src => {
    const fData = fileSclassMap[src] || {}
    const total = Object.values(fData).reduce((a, b) => a + b, 0)
    return {
      source: src,
      total: total,
      sclassBreakdown: Object.entries(fData)
        .map(([name, count]) => ({ name, count, percent: ((count / total) * 100).toFixed(1) }))
        .sort((a, b) => b.count - a.count)
    }
  })
  
  report.value = {
    dataType: 'APPOLLOS_MULTI',
    supplier: fileSources.join(' vs '),
    brand: reports[0]?.brand || '',
    dateRange: fileSources.length + ' files',
    totalQty: allItems.length,
    totalRecords: allItems.length,
    uniqueSKU: new Set(allItems.map(i => i.sku)).size,
    uniqueStores: '-',
    allStores: [],
    sizeDistribution: [],
    colorDistribution: [],
    comboAnalysis: { top: [], bottom: [] },
    topSKU: [],
    storeSkus: {},
    insights: [
      { type: 'merge_count', fileCount: fileSources.length },
      { type: 'product_count', count: allItems.length },
      { type: 'category_count', count: Object.keys(sclassMap).length }
    ],
    items: allItems,
    sclassDist,
    priceDist,
    priceMin: priceMin === Infinity ? 0 : priceMin,
    priceMax,
    priceAvg: allItems.length > 0 ? Math.round(totalPrice / allItems.length) : 0,
    // 多文件特有
    fileSources,
    fileComparison,
    isMultiFile: true
  }
  
  // 初始化筛选
  sclassList.value = getSclassList()
  filteredItems.value = allItems
  hasData.value = true
}

// APPOLLOS格式分析（返回数据而非直接设置report）
function analyzeAppollosFileData(jsonData, headers, idxSclass, fileNameVal) {
  const idxSKU = headers.findIndex(h => h.name === 'SKU')
  const idxDesc = headers.findIndex(h => /description/i.test(h.name))
  const idxPrice = headers.findIndex(h => h.name === 'PRICE')
  
  const dataRows = jsonData.slice(3).filter(r => r && r.length > 5 && r[0])
  
  let items = []
  let supplier = '', brand = '', totalPrice = 0
  const sclassMap = {}, skuMap = {}
  
  dataRows.forEach(row => {
    // APPOLLOS has two SKU columns: col 0 = Excel row number (skip), col 10 = real UPC/SKU
    const sku = String(row[10] || row[0] || '').trim()
    const desc = String(idxDesc !== -1 ? row[idxDesc] : row[7] || '').trim()
    const price = parseFloat(idxPrice !== -1 ? row[idxPrice] : row[8]) || 0
    const sclass = String(idxSclass !== -1 ? row[idxSclass] : row[6] || '').trim()
    const dept = String(row[3] || '').trim()
    const upc = String(row[9] || '').trim()
    
    if (!sku || sku === 'undefined') return
    
    items.push({ sku, description: desc, price, sclass, dept, upc })
    if (price > 0) totalPrice += price
    if (sclass) sclassMap[sclass] = (sclassMap[sclass] || 0) + 1
    if (sku) skuMap[sku] = (skuMap[sku] || 0) + 1
  })
  
  if (fileNameVal) {
    const match = fileNameVal.match(/SM\s*-\s*([^\-]+)/i)
    if (match) supplier = match[1].trim()
  }
  
  const firstDesc = items[0]?.description || ''
  const brandMatch = firstDesc.match(/^([A-Z]+)\s+HARDCASE/i)
  if (brandMatch) brand = brandMatch[1]
  
  const sclassDist = Object.entries(sclassMap)
    .map(([name, count]) => ({ name, count, percent: ((count / items.length) * 100).toFixed(1) }))
    .sort((a, b) => b.count - a.count)
  
  return {
    dataType: 'APPOLLOS',
    supplier,
    brand,
    dateRange: '',
    totalQty: items.length,
    totalRecords: items.length,
    uniqueSKU: Object.keys(skuMap).length,
    items,
    sclassDist,
    priceMin: items.length > 0 ? Math.min(...items.filter(i => i.price > 0).map(i => i.price)) || 0 : 0,
    priceMax: items.length > 0 ? Math.max(...items.filter(i => i.price > 0).map(i => i.price)) || 0 : 0,
    priceAvg: items.length > 0 ? Math.round(totalPrice / items.length) : 0
  }
}

// 智能识别门店列 - 分析每列内容特征
    function findStoreColumn(headers, rows) {
      // 门店列名关键词
      const storeKeywords = ['store', 'shop', 'branch', 'branch name', 'store name', 'shop name', 
                            '门店', '店铺', '店面', '分店', '商店', 'name', 'location']
      // 排除的关键词（日期等）
      const excludeKeywords = ['date', 'week', 'month', 'period', 'range', '日期', '周', '期间']
      
      let bestIdx = -1
      let bestScore = 0
      
      headers.forEach((header, idx) => {
        const hName = header.name.toLowerCase()
        let score = 0
        
        // 列名匹配加分
        const isExclude = excludeKeywords.some(k => hName.includes(k))
        if (isExclude) return // 跳过排除词
        
        const isStoreKeyword = storeKeywords.some(k => hName.includes(k))
        if (isStoreKeyword) score += 50
        
        // 分析该列内容
        const values = rows.map(r => String(r[idx] || '').trim()).filter(v => v.length > 0)
        if (values.length === 0) return
        
        // 计算重复度（门店名会重复）
        const uniqueValues = new Set(values)
        const repeatRatio = 1 - (uniqueValues.size / values.length)
        score += repeatRatio * 40
        
        // 文本长度适中（5-40字符）加分
        const avgLen = values.reduce((sum, v) => sum + v.length, 0) / values.length
        if (avgLen >= 3 && avgLen <= 50) score += 10
        
        // 不是纯数字加分
        const isNumeric = values.every(v => /^\d+(\.\d+)?$/.test(v))
        if (!isNumeric) score += 20
        
        // 不是日期格式加分
        const isDateFormat = values.some(v => /\d{4}[-/]\d{1,2}[-/]\d{1,2}/.test(v) || /\d{1,2}[-/]\d{1,2}[-/]\d{2,4}/.test(v))
        if (!isDateFormat) score += 15
        
        // 唯一值数量适中（2-200个）加分（太少可能是特殊分类，太多可能是明细）
        if (uniqueValues.size >= 2 && uniqueValues.size <= 200) score += 10
        
        // SM STORE特征加分
        const hasSMStore = values.some(v => v.toUpperCase().includes('SM STORE') || v.includes('SM'))
        if (hasSMStore) score += 25
        
        console.log(`列${idx} "${header.name}" 得分: ${score.toFixed(1)} (重复度:${repeatRatio.toFixed(2)}, 平均长度:${avgLen.toFixed(1)}, 值数量:${uniqueValues.size})`)
        
        if (score > bestScore) {
          bestScore = score
          bestIdx = idx
        }
      })
      
      console.log(`智能选择门店列: 索引${bestIdx}, 得分${bestScore.toFixed(1)}`)
      return bestIdx
    }
    
    function analyzeFile(file) {
  fileName.value = file.name
  hasData.value = false
  report.value = {}
  const reader = new FileReader()
  reader.onload = async (e) => {
    try {
      const data = new Uint8Array(e.target.result)
      const XLSX = await import('xlsx')
      const wb = XLSX.read(data, { type: 'array', cellDates: true })
      const ws = wb.Sheets[wb.SheetNames[0]]
      const jsonData = XLSX.utils.sheet_to_json(ws, { header: 1 })
      
      // APPOLLOS格式: 找实际数据行(含最多非空值的行作为表头)
      let headerRow = jsonData[0] || []
      let maxCols = (headerRow || []).filter(v => v != null).length
      for (let i = 1; i < Math.min(jsonData.length, 10); i++) {
        const row = jsonData[i] || []
        const nonNull = row.filter(v => v != null).length
        if (nonNull > maxCols) {
          headerRow = row
          maxCols = nonNull
        }
      }
      const headers = (headerRow || []).map((h, i) => ({ name: String(h || '').trim(), index: i }))
      
      // 检测是否为APPOLLOS格式（有SCLASS列）
      const idxSclass = headers.findIndex(h => /^sclass$/i.test(h.name))
      
      let result
      if (idxSclass !== -1) {
        // APPOLLOS格式解析
        result = analyzeAppollosFileData(jsonData, headers, idxSclass, fileName.value)
        // 初始化筛选数据
        sclassList.value = getSclassListFromItems(result.items)
        filteredItems.value = result.items || []
      } else {
        // 通用SM格式解析
        result = analyzeSMFile(jsonData, headers, fileName.value)
      }
      
      report.value = result
      hasData.value = true
      showSaveSuccess.value = false
    } catch (err) {
      console.error('[ExcelAnalyzer] analyzeFile error:', err.message, err.stack)
      alert(locale.value === 'zh' ? '文件解析失败: ' + err.message : 'File parse error: ' + err.message)
    }
  }
  reader.onerror = () => alert(locale.value === 'zh' ? '读取文件失败' : 'Failed to read file')
  reader.readAsArrayBuffer(file)
}

// SM通用格式分析
function analyzeSMFile(jsonData, headers, fileNameVal) {
  const idxDoc = headers.findIndex(h => /^document$/i.test(h.name))
  const allRows = jsonData.slice(1)
  const idxBranch = findStoreColumn(headers, allRows)
  const idxDate = headers.findIndex(h => /^date$/i.test(h.name))
  const idxSKU = headers.findIndex(h => /^sku$/i.test(h.name))
  const idxDesc = headers.findIndex(h => /description|desc/i.test(h.name))
  const idxQty = headers.findIndex(h => /^qty$|quantity/i.test(h.name))
  
  const rows = allRows.filter(r => {
    const doc = idxDoc !== -1 ? r[idxDoc] : r[0]
    return String(doc || '').toLowerCase().includes('sales')
  })
  
  let totalQty = 0
  const storeMap = {}, sizeMap = {}, colorMap = {}, skuMap = {}, skuDescMap = {}, storeSkuMap = {}, comboMap = {}
  let supplier = '', brand = '', dateRange = ''

  rows.forEach(row => {
    const qty = parseFloat(idxQty !== -1 ? row[idxQty] : row[12]) || 0
    const store = String(idxBranch !== -1 ? row[idxBranch] : row[6] || '').trim()
    const desc = String(idxDesc !== -1 ? row[idxDesc] : row[9] || '').trim()
    const sku = String(idxSKU !== -1 ? row[idxSKU] : row[8] || '').trim()

    totalQty += qty
    if (store) {
      if (!storeMap[store]) storeMap[store] = { qty: 0, items: new Set(), amount: 0 }
      // 优先用 Excel 自身金额列（若有），否则从商品库 productMap 查 SKU 单价 × 数量
      const unitPrice = productMap.value[sku]?.price || 0
      const rowAmount = unitPrice * qty
      storeMap[store].qty += qty
      storeMap[store].amount += rowAmount
      if (sku) storeMap[store].items.add(sku)
    }

    if (store && sku && qty > 0) {
      if (!storeSkuMap[store]) storeSkuMap[store] = {}
      if (!storeSkuMap[store][sku]) storeSkuMap[store][sku] = { qty: 0, desc }
      storeSkuMap[store][sku].qty += qty
    }
    
    const sizeMatch = desc.match(/(\d{2})\s*[A-Z]{2,}$/)
    if (sizeMatch) {
      const sizeName = sizeMatch[1] + '"'
      sizeMap[sizeName] = (sizeMap[sizeName] || 0) + qty
    }
    
    const colorMatch = sku.match(colorRegex) || desc.match(colorRegex)
    if (colorMatch) {
      const colorCode = colorMatch[1].toUpperCase()
      colorMap[colorCode] = (colorMap[colorCode] || 0) + qty
    }
    
    if (sku) {
      skuMap[sku] = (skuMap[sku] || 0) + qty
      if (desc) skuDescMap[sku] = desc
    }
    
    if (!brand && desc) {
      const m = desc.match(/^([A-Z]+)\s+HARDCASE/i)
      if (m) brand = m[1]
    }
    
    if (!dateRange && idxDate !== -1) {
      const d = row[idxDate]
      if (d) dateRange = String(d).slice(0, 10)
    }
  })
  
  const allStores = Object.entries(storeMap)
    .map(([name, v]) => ({
      name,
      qty: v.qty,
      items: v.items.size,
      amount: v.amount,
      percent: totalQty ? ((v.qty / totalQty) * 100).toFixed(1) : '0.0'
    }))
    .sort((a, b) => b.qty - a.qty)
  
  const topSKU = Object.entries(skuMap)
    .map(([sku, qty]) => ({ sku, qty, desc: skuDescMap[sku] || '', percent: ((qty / totalQty) * 100).toFixed(1) }))
    .sort((a, b) => b.qty - a.qty).slice(0, 20)
  
  const sizeDist = Object.entries(sizeMap)
    .map(([name, count]) => ({ name, count, percent: ((count / totalQty) * 100).toFixed(1) }))
    .sort((a, b) => b.count - a.count)
  
  const colorDist = Object.entries(colorMap)
    .map(([name, count]) => ({ name, nameZh: getColorName(name), count, percent: ((count / totalQty) * 100).toFixed(1) }))
    .sort((a, b) => b.count - a.count)
  
  if (fileNameVal) {
    const m = fileNameVal.match(/SM\s*-\s*([^\-]+)/i)
    if (m) supplier = m[1].trim()
  }
  
  return {
    dataType: 'SM',
    supplier,
    brand,
    dateRange,
    totalQty,
    totalRecords: rows.length,
    uniqueSKU: Object.keys(skuMap).length,
    uniqueStores: allStores.length,
    allStores,
    sizeDistribution: sizeDist,
    colorDistribution: colorDist,
    comboAnalysis: { top: topSKU.slice(0, 10), bottom: topSKU.slice(-5) },
    topSKU,
    storeSkus: storeSkuMap,
    insights: [
      { type: 'store_count', count: allStores.length },
      { type: 'sku_count', count: topSKU.length }
    ]
  }
}



function reset() {
  hasData.value = false
  fileName.value = ''
  report.value = {}
  uploadedFile.value = null
  if (fileInputRef.value) fileInputRef.value.value = ''
}

function saveReport() {
  const file = uploadedFile.value || fileInputRef.value?.files?.[0]
  if (!file) {
    alert(locale.value === 'zh' ? '请先上传文件' : 'Please upload a file first')
    return
  }
  
  const token = localStorage.getItem('caimeite_token')
  const formData = new FormData()
  formData.append('file', file)
  formData.append('file_type', report.value.dataType === 'APPOLLOS' || report.value.dataType === 'APPOLLOS_MULTI' ? 'APPOLLOS' : 'SM')
  formData.append('supplier', report.value.supplier || '')
  formData.append('brand', report.value.brand || '')
  formData.append('date_range', report.value.dateRange || '')
  formData.append('total_qty', report.value.totalQty || 0)
  formData.append('total_records', report.value.totalRecords || 0)
  formData.append('unique_sku', report.value.uniqueSKU || 0)
  formData.append('unique_stores', report.value.uniqueStores || 0)
  
  fetch('/api/import/upload', {
    method: 'POST',
    headers: { 'Authorization': 'Bearer ' + token },
    body: formData
  })
  .then(res => res.json())
  .then(result => {
    if (result.success) {
      showSaveSuccess.value = true
      // 提示用户去重信息
      if (result.deduplicated > 0) {
        const msg = locale.value === 'zh'
          ? `已自动去重 ${result.deduplicated} 条相同文件的旧记录`
          : `Auto-deduplicated ${result.deduplicated} old record(s) of the same file`
        console.log('[DEDUP]', msg)
      }
    } else {
      alert(result.message || (locale.value === 'zh' ? '保存失败' : 'Save failed'))
    }
  })
  .catch(err => { console.error(err); alert(locale.value === 'zh' ? '保存失败' : 'Save failed') })
}

function goManage() {
  window.location.href = '/#/excel-report-manage'
}
</script>

<style scoped>
.excel-analyzer { padding: 20px; background: #f5f7fa; min-height: 100vh; max-width: 900px; margin: 0 auto; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
.header h1 { margin: 0; font-size: 24px; }
.lang-toggle { display: flex; gap: 5px; }
.lang-toggle button { padding: 6px 12px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer; }
.lang-toggle button.active { background: #409eff; color: white; }
.upload-area { display: flex; justify-content: center; padding: 60px 20px; }
.upload-box { width: 400px; padding: 60px 40px; border: 2px dashed #ddd; border-radius: 16px; text-align: center; cursor: pointer; background: white; }
.upload-box:hover { border-color: #409eff; }
.upload-icon { font-size: 64px; margin-bottom: 20px; }
.upload-text { font-size: 16px; color: #606266; margin-bottom: 8px; }
.upload-hint { font-size: 12px; color: #909399; margin-top: 16px; }
.report { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); }
.report-section { margin-bottom: 40px; }
.report-section h2 { margin: 0 0 20px 0; font-size: 18px; color: #303133; padding-bottom: 10px; border-bottom: 2px solid #409eff; }
.info-table { width: 100%; border-collapse: collapse; }
.info-table td { padding: 10px 15px; border: 1px solid #eee; }
.info-table td:first-child { width: 140px; background: #fafafa; font-weight: 600; color: #606266; }
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
.stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; }
.stat-value { font-size: 28px; font-weight: bold; }
.stat-label { font-size: 13px; opacity: 0.9; margin-top: 5px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
.data-table th { background: #fafafa; font-weight: 600; color: #606266; font-size: 13px; }
.data-table .rank { font-weight: bold; color: #409eff; min-width: 40px; text-align: center; }
.insights ul { margin: 0; padding-left: 20px; }
.insights li { padding: 8px 0; color: #606266; }
.actions { display: flex; gap: 15px; justify-content: center; padding-top: 20px; border-top: 1px solid #eee; }
.btn { padding: 12px 30px; border: 1px solid #ddd; background: white; border-radius: 8px; cursor: pointer; }
.btn-save { background: #67c23a; color: white; border-color: #67c23a; }
.save-success { margin-top: 15px; padding: 12px 15px; background: #f0f9eb; border-radius: 8px; color: #67c23a; text-align: center; }
.save-success button { margin-left: 10px; padding: 4px 12px; background: #67c23a; color: white; border: none; border-radius: 4px; cursor: pointer; }
.combo-section { margin-top: 25px; }
.combo-section h3 { margin: 15px 0 10px 0; font-size: 16px; color: #606266; }
.qty-high { color: #67c23a; font-weight: bold; }
.qty-low { color: #f56c6c; }
.filter-section { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); }
.filter-section h3 { margin: 0 0 15px 0; font-size: 16px; color: #303133; }
.filter-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
.filter-item { display: flex; flex-direction: column; gap: 5px; }
.filter-item label { font-size: 13px; color: #606266; font-weight: 600; }
.filter-item select, .filter-item input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; min-width: 150px; }
.filter-item.filter-buttons { flex-direction: row; }
.filter-stats { margin-top: 10px; font-size: 13px; color: #606266; }
.btn-small { padding: 6px 12px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer; font-size: 13px; }
.btn-small:hover { background: #f5f5f5; }

@media (max-width: 768px) {
  .excel-analyzer { padding: 12px; }
  .header { flex-direction: column; align-items: flex-start; gap: 10px; margin-bottom: 20px; }
  .header h1 { font-size: 20px; }
  .upload-area { padding: 30px 15px; }
  .upload-box { width: 100%; padding: 40px 20px; }
  .report { padding: 15px; border-radius: 8px; }
  .report-section { margin-bottom: 25px; }
  .report-section h2 { font-size: 16px; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
  .stat-card { padding: 15px; }
  .stat-value { font-size: 22px; }
  .stat-label { font-size: 12px; }
  .filter-section { padding: 15px; }
  .filter-row { flex-direction: column; gap: 10px; }
  .filter-item select, .filter-item input { min-width: 100%; width: 100%; font-size: 14px; }
  .filter-item.filter-buttons { flex-direction: row; gap: 10px; }
  .actions { flex-direction: column; gap: 10px; }
  .btn { width: 100%; padding: 12px 20px; font-size: 15px; }
  .data-table { font-size: 13px; overflow-x: auto; display: block; }
  .data-table th, .data-table td { padding: 8px 10px; white-space: nowrap; }
  .info-table { display: block; overflow-x: auto; }
  .save-success button { display: block; width: 100%; margin: 10px 0 0 0; }
}

/* SM 原图 门店销售排名 搜索栏 + 折叠样式 */
.store-filter-bar {
  display: flex; flex-wrap: wrap; gap: 10px;
  margin: 12px 0 16px 0;
  padding: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  align-items: center;
}
.store-filter-bar .filter-input {
  flex: 1; min-width: 180px;
  padding: 8px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 14px;
  background: #fff;
}
.store-filter-bar .filter-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
.store-filter-bar .filter-btn {
  padding: 8px 16px; border-radius: 6px;
  border: 1px solid #cbd5e1; background: #fff;
  color: #334155; cursor: pointer; font-size: 14px;
  transition: all 0.15s;
}
.store-filter-bar .filter-btn:hover { background: #f1f5f9; }
.store-filter-bar .filter-btn.primary { background: #3b82f6; color: #fff; border-color: #3b82f6; }
.store-filter-bar .filter-btn.primary:hover { background: #2563eb; }
.store-filter-bar .filter-btn.export { background: #10b981; color: #fff; border-color: #10b981; }
.store-filter-bar .filter-btn.export:hover { background: #059669; }
.store-filter-bar .filter-btn.print { background: #6366f1; color: #fff; border-color: #6366f1; }
.store-filter-bar .filter-btn.print:hover { background: #4f46e5; }
.store-filter-bar .filter-btn.small { padding: 4px 10px; font-size: 12px; }
.store-detail-row { background: #f8fafc; }
.store-detail-row .nested-table { width: 100%; font-size: 13px; }
.store-detail-row .nested-table th { background: #e2e8f0; color: #475569; }
.store-detail-row .nested-table td { padding: 6px 10px; }
</style>
