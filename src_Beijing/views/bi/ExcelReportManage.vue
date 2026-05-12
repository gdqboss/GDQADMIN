<template>
  <div class="manage">
    <div class="header">
      <h1>📊 {{ lang === 'zh' ? '报告管理' : 'Report Management' }}</h1>
      <div class="header-actions">
        <button class="btn" @click="goAnalyzer">{{ lang === 'zh' ? '📈 新建分析' : '📈 New Analysis' }}</button>
        <button class="btn btn-primary" @click="showMergeModal = true">{{ lang === 'zh' ? '🔗 合并报告' : '🔗 Merge Reports' }}</button>
      </div>
    </div>

    <div class="lang-toggle">
      <button @click="setLang('zh')" :class="{ active: lang === 'zh' }">中文</button>
      <button @click="setLang('en')" :class="{ active: lang === 'en' }">EN</button>
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <button :class="{ active: activeTab === 'reports' }" @click="activeTab = 'reports'; loadReports()">
        📋 {{ lang === 'zh' ? '分析报告' : 'Reports' }}
      </button>
      <button :class="{ active: activeTab === 'merges' }" @click="activeTab = 'merges'; loadMerges()">
        🔗 {{ lang === 'zh' ? '合并记录' : 'Merges' }}
      </button>
    </div>

    <!-- Report List Tab -->
    <div v-if="activeTab === 'reports'">
      <div class="report-list" v-if="reports.length > 0">
        <table class="data-table">
          <thead>
            <tr>
              <th width="40"><input type="checkbox" v-model="selectAll"></th>
              <th>{{ lang === 'zh' ? '报告名称' : 'Name' }}</th>
              <th>{{ lang === 'zh' ? '供应商' : 'Supplier' }}</th>
              <th>{{ lang === 'zh' ? '总销量' : 'Total Qty' }}</th>
              <th>{{ lang === 'zh' ? 'SKU数' : 'SKU' }}</th>
              <th>{{ lang === 'zh' ? '创建时间' : 'Created' }}</th>
              <th width="180">{{ lang === 'zh' ? '操作' : 'Actions' }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in reports" :key="r.id">
              <td><input type="checkbox" :value="r.id" v-model="selectedIds"></td>
              <td>{{ r.name }}</td>
              <td>{{ r.supplier }}</td>
              <td>{{ r.total_qty?.toLocaleString() }}</td>
              <td>{{ r.unique_sku }}</td>
              <td>{{ formatDate(r.created_at) }}</td>
              <td>
                <button class="btn-small" @click="viewReport(r)">{{ lang === 'zh' ? '查看' : 'View' }}</button>
                <button class="btn-small danger" @click="deleteReport(r.id)">{{ lang === 'zh' ? '删除' : 'Delete' }}</button>
              </td>
            </tr>
          </tbody>
        </table>
        <div class="batch-actions" v-if="selectedIds.length > 0">
          <span>{{ lang === 'zh' ? '已选择' : 'Selected' }}: {{ selectedIds.length }}</span>
          <button class="btn-small danger" @click="batchDelete">{{ lang === 'zh' ? '批量删除' : 'Batch Delete' }}</button>
        </div>
      </div>
      <div class="empty-state" v-else>
        <div class="empty-icon">📊</div>
        <div class="empty-text">{{ lang === 'zh' ? '暂无报告' : 'No reports yet' }}</div>
        <button class="btn btn-primary" @click="goAnalyzer">{{ lang === 'zh' ? '去分析' : 'Go to Analysis' }}</button>
      </div>
    </div>

    <!-- Merge List Tab -->
    <div v-if="activeTab === 'merges'">
      <div class="report-list" v-if="mergeList.length > 0">
        <table class="data-table">
          <thead>
            <tr>
              <th>{{ lang === 'zh' ? '合并名称' : 'Merge Name' }}</th>
              <th>{{ lang === 'zh' ? '包含报告数' : 'Reports' }}</th>
              <th>{{ lang === 'zh' ? '创建时间' : 'Created' }}</th>
              <th>{{ lang === 'zh' ? '操作' : 'Actions' }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in mergeList" :key="m.id">
              <td>{{ m.name }}</td>
              <td>{{ m.report_ids?.length || 0 }}</td>
              <td>{{ formatDate(m.created_at) }}</td>
              <td>
                <button class="btn-small" @click="viewMerge(m)">{{ lang === 'zh' ? '查看完整分析' : 'View Full Analysis' }}</button>
                <button class="btn-small danger" @click="deleteMerge(m.id)">{{ lang === 'zh' ? '删除' : 'Delete' }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="empty-state" v-else>
        <div class="empty-icon">🔗</div>
        <div class="empty-text">{{ lang === 'zh' ? '暂无合并记录' : 'No merge records' }}</div>
      </div>
    </div>

    <!-- View Report Modal -->
    <div class="modal" v-if="viewModal">
      <div class="modal-content full">
        <div class="modal-header">
          <h3>📊 {{ currentReport.name }}</h3>
          <div class="modal-header-actions">
            <button class="btn-print" @click="printReport">{{ lang === 'zh' ? '🖨️ 打印' : '🖨️ Print' }}</button>
            <button class="close" @click="viewModal = false">×</button>
          </div>
        </div>
        <div class="modal-body" v-if="currentReport.id">
          <!-- File Info -->
          <div class="report-section">
            <h2>📋 {{ lang === 'zh' ? '文件信息' : 'File Info' }}</h2>
            <table class="info-table">
              <tr><td>{{ lang === 'zh' ? '文件名' : 'File Name' }}</td><td>{{ currentReport.file_name }}</td></tr>
              <tr><td>{{ lang === 'zh' ? '供应商' : 'Supplier' }}</td><td>{{ currentReport.supplier }}</td></tr>
              <tr><td>{{ lang === 'zh' ? '品牌' : 'Brand' }}</td><td>{{ currentReport.brand }}</td></tr>
              <tr><td>{{ lang === 'zh' ? '时间范围' : 'Date Range' }}</td><td>{{ currentReport.date_range }}</td></tr>
            </table>
          </div>

          <!-- Core Stats -->
          <div class="report-section">
            <h2>📈 {{ lang === 'zh' ? '核心数据' : 'Core Data' }}</h2>
            <div class="stats-grid">
              <div class="stat-card">
                <div class="stat-value">{{ currentReport.total_qty?.toLocaleString() }}</div>
                <div class="stat-label">{{ lang === 'zh' ? '总销量' : 'Total Qty' }}</div>
              </div>
              <div class="stat-card">
                <div class="stat-value">{{ currentReport.total_records?.toLocaleString() }}</div>
                <div class="stat-label">{{ lang === 'zh' ? '总记录' : 'Records' }}</div>
              </div>
              <div class="stat-card">
                <div class="stat-value">{{ currentReport.unique_sku }}</div>
                <div class="stat-label">{{ lang === 'zh' ? '唯一SKU' : 'Unique SKU' }}</div>
              </div>
              <div class="stat-card">
                <div class="stat-value">{{ currentReport.unique_stores }}</div>
                <div class="stat-label">{{ lang === 'zh' ? '销售门店' : 'Stores' }}</div>
              </div>
            </div>
          </div>

          <!-- All Stores (Expandable) -->
          <div class="report-section" v-if="parsedStores.length">
            <h2>🏪 {{ lang === 'zh' ? '门店销量排行（点击查看SKU详情）' : 'Store Sales Ranking (Click to view SKU details)' }}</h2>
            <table class="data-table">
              <thead><tr><th style="width:50px">#</th><th>{{ lang === 'zh' ? '门店' : 'Store' }}</th><th style="width:100px">{{ lang === 'zh' ? '销量' : 'Qty' }}</th><th style="width:70px">%</th><th style="width:90px">{{ lang === 'zh' ? '操作' : 'Action' }}</th></tr></thead>
              <tbody>
                <template v-for="(s, i) in parsedStores" :key="s.id || i">
                  <tr class="store-row" :class="{ expanded: expandedStore === s.name }" @click="toggleStore(s.name)">
                    <td class="rank">{{ s.rank }}</td>
                    <td class="store-name">
                      <span class="expand-icon">{{ expandedStore === s.name ? '▼' : '▶' }}</span>
                      {{ s.name }}
                    </td>
                    <td>{{ s.qty?.toLocaleString() }}</td>
                    <td>{{ s.percent }}%</td>
                    <td>
                      <button class="btn-small" @click.stop="toggleStore(s.name)">
                        {{ expandedStore === s.name ? (lang === 'zh' ? '收起' : 'Collapse') : (lang === 'zh' ? '查看SKU' : 'View SKU') }}
                      </button>
                    </td>
                  </tr>
                  <!-- SKU Detail Row -->
                  <tr v-if="expandedStore === s.name" class="sku-detail-row">
                    <td colspan="5">
                      <div class="sku-detail-box">
                        <h4>📦 {{ s.name }} - {{ lang === 'zh' ? '热销SKU排名' : 'Top SKU Ranking' }}</h4>
                        <table class="sku-table" v-if="parsedStoreSkus[s.name]?.length">
                          <thead>
                            <tr><th style="width:50px">#</th><th>SKU</th><th>{{ lang === 'zh' ? '商品名称' : 'Description' }}</th><th style="width:100px">{{ lang === 'zh' ? '销量' : 'Qty' }}</th></tr>
                          </thead>
                          <tbody>
                            <tr v-for="(sku, idx) in parsedStoreSkus[s.name]" :key="sku.code" :class="{ 'top-sku': idx < 3 }">
                              <td class="rank">{{ idx + 1 }}</td>
                              <td class="sku-code">{{ sku.code }}</td>
                              <td class="sku-desc">{{ sku.desc || '-' }}</td>
                              <td>{{ sku.qty?.toLocaleString() }}</td>
                            </tr>
                          </tbody>
                        </table>
                        <div v-else class="no-sku-data">{{ lang === 'zh' ? '暂无SKU数据' : 'No SKU data available' }}</div>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <!-- Size Distribution -->
          <div class="report-section" v-if="parsedSizes.length">
            <h2>📐 {{ lang === 'zh' ? '尺寸分布' : 'Size Distribution' }}</h2>
            <table class="data-table">
              <thead><tr><th>{{ lang === 'zh' ? '尺寸' : 'Size' }}</th><th>{{ lang === 'zh' ? '销量' : 'Qty' }}</th><th>%</th></tr></thead>
              <tbody>
                <tr v-for="(s, i) in parsedSizes" :key="i">
                  <td>{{ s.name }}</td><td>{{ s.qty?.toLocaleString() }}</td><td>{{ s.percent }}%</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Color Distribution -->
          <div class="report-section" v-if="parsedColors.length">
            <h2>🎨 {{ lang === 'zh' ? '颜色分布' : 'Color Distribution' }}</h2>
            <table class="data-table">
              <thead><tr><th>{{ lang === 'zh' ? '颜色代码' : 'Color Code' }}</th><th>{{ lang === 'zh' ? '颜色' : 'Color' }}</th><th>{{ lang === 'zh' ? '销量' : 'Qty' }}</th></tr></thead>
              <tbody>
                <tr v-for="(c, i) in parsedColors" :key="i">
                  <td>{{ c.code }}</td><td>{{ c.name }}</td><td>{{ c.qty?.toLocaleString() }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Top SKU -->
          <div class="report-section" v-if="parsedSkus.length">
            <h2>🔥 {{ lang === 'zh' ? 'Top 10 SKU' : 'Top 10 SKU' }}</h2>
            <table class="data-table">
              <thead><tr><th>SKU</th><th>{{ lang === 'zh' ? '描述' : 'Description' }}</th><th>{{ lang === 'zh' ? '销量' : 'Qty' }}</th></tr></thead>
              <tbody>
                <tr v-for="(s, i) in parsedSkus" :key="i">
                  <td>{{ s.code }}</td><td>{{ s.desc?.substring(0, 40) }}</td><td>{{ s.qty?.toLocaleString() }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Combo Analysis: Top 10 -->
          <div class="report-section" v-if="parsedCombo.top?.length">
            <h2>📊 SKU×尺寸×颜色 {{ lang === 'zh' ? '销量前10' : 'Top 10 Combos' }}</h2>
            <table class="data-table">
              <thead><tr><th>#</th><th>SKU</th><th>{{ lang === 'zh' ? '尺寸' : 'Size' }}</th><th>{{ lang === 'zh' ? '颜色' : 'Color' }}</th><th>{{ lang === 'zh' ? '销量' : 'Qty' }}</th></tr></thead>
              <tbody>
                <tr v-for="(c, i) in parsedCombo.top" :key="i">
                  <td class="rank">{{ i + 1 }}</td>
                  <td>{{ c.sku }}</td>
                  <td>{{ c.size }}</td>
                  <td>{{ c.colorName }} ({{ c.color }})</td>
                  <td class="qty-high">{{ c.qty?.toLocaleString() }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Combo Analysis: Bottom 10 -->
          <div class="report-section" v-if="parsedCombo.bottom?.length">
            <h2>📊 SKU×尺寸×颜色 {{ lang === 'zh' ? '销量尾10' : 'Bottom 10 Combos' }}</h2>
            <table class="data-table">
              <thead><tr><th>#</th><th>SKU</th><th>{{ lang === 'zh' ? '尺寸' : 'Size' }}</th><th>{{ lang === 'zh' ? '颜色' : 'Color' }}</th><th>{{ lang === 'zh' ? '销量' : 'Qty' }}</th></tr></thead>
              <tbody>
                <tr v-for="(c, i) in parsedCombo.bottom" :key="i">
                  <td class="rank">{{ parsedCombo.top.length + i + 1 }}</td>
                  <td>{{ c.sku }}</td>
                  <td>{{ c.size }}</td>
                  <td>{{ c.colorName }} ({{ c.color }})</td>
                  <td class="qty-low">{{ c.qty?.toLocaleString() }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Insights -->
          <div class="report-section insights" v-if="parsedInsights.length">
            <h2>💡 {{ lang === 'zh' ? '关键洞察' : 'Key Insights' }}</h2>
            <ul>
              <li v-for="(ins, i) in parsedInsights" :key="i">{{ ins }}</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Merge Modal -->
    <div class="modal" v-if="showMergeModal">
      <div class="modal-content small">
        <div class="modal-header">
          <h3>🔗 {{ lang === 'zh' ? '合并报告' : 'Merge Reports' }}</h3>
          <button class="close" @click="showMergeModal = false">×</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>{{ lang === 'zh' ? '合并报告名称' : 'Merge Name' }}</label>
            <input type="text" v-model="mergeName" :placeholder="lang === 'zh' ? '例如：Q1合并报告' : 'e.g., Q1 Combined Report'">
          </div>
          <div class="form-group">
            <label>{{ lang === 'zh' ? '选择报告' : 'Select Reports' }} ({{ mergeSelectIds.length }})</label>
            <div class="checkbox-list">
              <label v-for="r in reports" :key="r.id">
                <input type="checkbox" :value="r.id" v-model="mergeSelectIds">
                {{ r.name }} ({{ r.total_qty?.toLocaleString() }})
              </label>
            </div>
          </div>
          <div class="form-actions">
            <button class="btn" @click="showMergeModal = false">{{ lang === 'zh' ? '取消' : 'Cancel' }}</button>
            <button class="btn btn-primary" @click="doMerge" :disabled="mergeSelectIds.length < 2">{{ lang === 'zh' ? '执行合并' : 'Merge' }}</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const reports = ref([])
const selectedIds = ref([])
const mergeSelectIds = ref([])
const page = ref(1)
const total = ref(0)
const viewModal = ref(false)
const showMergeModal = ref(false)
const activeTab = ref('reports')
const currentReport = ref({})
const mergeName = ref('')
const expandedStore = ref(null)
const mergeList = ref([])
const lang = ref(localStorage.getItem('caimeite_locale') || 'zh')

const selectAll = computed({
  get: () => reports.value.length > 0 && selectedIds.value.length === reports.value.length,
  set: (val) => { selectedIds.value = val ? reports.value.map(r => r.id) : [] }
})

// Parsed data for view modal
const parsedStores = computed(() => {
  try {
    const raw = currentReport.value.top_stores
    if (!raw) return []
    const stores = typeof raw === 'string' ? JSON.parse(raw) : raw
    if (!Array.isArray(stores)) return []
    // 如果已有rank字段直接返回，否则添加
    if (stores.length > 0 && stores[0].rank !== undefined) return stores
    return stores.map((s, idx) => ({ ...s, rank: idx + 1 }))
  } catch (e) { 
    console.error('parsedStores error:', e)
    return [] 
  }
})
const parsedSizes = computed(() => {
  try {
    const raw = currentReport.value.size_dist
    if (!raw) return []
    const data = typeof raw === 'string' ? JSON.parse(raw) : raw
    return Array.isArray(data) ? data : []
  } catch (e) { console.error('parsedSizes error:', e); return [] }
})
const parsedColors = computed(() => {
  try {
    const raw = currentReport.value.color_dist
    if (!raw) return []
    const data = typeof raw === 'string' ? JSON.parse(raw) : raw
    return Array.isArray(data) ? data : []
  } catch (e) { console.error('parsedColors error:', e); return [] }
})
const parsedSkus = computed(() => {
  try {
    const raw = currentReport.value.top_sku
    if (!raw) return []
    const data = typeof raw === 'string' ? JSON.parse(raw) : raw
    return Array.isArray(data) ? data : []
  } catch (e) { console.error('parsedSkus error:', e); return [] }
})
const parsedCombo = computed(() => {
  try {
    const raw = currentReport.value.combo_analysis
    if (!raw) return { top: [], bottom: [] }
    const data = typeof raw === 'string' ? JSON.parse(raw) : raw
    return data || { top: [], bottom: [] }
  } catch (e) { console.error('parsedCombo error:', e); return { top: [], bottom: [] } }
})

const parsedInsights = computed(() => {
  try {
    const raw = currentReport.value.insights
    if (!raw) return []
    const data = typeof raw === 'string' ? JSON.parse(raw) : raw
    return Array.isArray(data) ? data : []
  } catch (e) { console.error('parsedInsights error:', e); return [] }
})

// Parse store_skus: { "StoreName": [{ code, qty, desc }] }
const parsedStoreSkus = computed(() => {
  try {
    const raw = currentReport.value.store_skus
    if (!raw) return {}
    const data = typeof raw === 'string' ? JSON.parse(raw) : raw
    return typeof data === 'object' ? data : {}
  } catch (e) { console.error('parsedStoreSkus error:', e); return {} }
})

function setLang(l) {
  lang.value = l
  localStorage.setItem('caimeite_locale', l)
}

function formatDate(date) {
  if (!date) return ''
  return new Date(date).toLocaleDateString()
}

function toggleStore(storeName) {
  expandedStore.value = expandedStore.value === storeName ? null : storeName
}

async function callApi(url, options = {}) {
  const token = localStorage.getItem('caimeite_token')
  const response = await fetch(url, {
    ...options,
    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json', ...options.headers }
  })
  return response.json()
}

async function loadReports() {
  const result = await callApi(`/api/excel-report/list?page=${page.value}&pageSize=50`)
  if (result.code === 0) {
    reports.value = result.data
    total.value = result.total
  }
}

async function loadMerges() {
  const result = await callApi('/api/excel-report/merge/list')
  if (result.code === 0) {
    mergeList.value = result.data
  }
}

function goAnalyzer() {
  window.location.href = '/excel-analyzer'
}

async function viewReport(r) {
  const result = await callApi(`/api/excel-report/${r.id}`)
  if (result.code === 0) {
    currentReport.value = result.data
    viewModal.value = true
  }
}

async function deleteReport(id) {
  if (!confirm(lang.value === 'zh' ? '确定删除？' : 'Confirm delete?')) return
  const result = await callApi(`/api/excel-report/${id}`, { method: 'DELETE' })
  if (result.code === 0) loadReports()
}

async function deleteMerge(id) {
  if (!confirm(lang.value === 'zh' ? '确定删除？' : 'Confirm delete?')) return
  const result = await callApi(`/api/excel-report/merge/${id}`, { method: 'DELETE' })
  if (result.code === 0) loadMerges()
}

async function batchDelete() {
  if (!confirm(lang.value === 'zh' ? `删除${selectedIds.value.length}条？` : `Delete ${selectedIds.value.length}?`)) return
  const result = await callApi('/api/excel-report/batch-delete', { method: 'POST', body: JSON.stringify({ ids: selectedIds.value }) })
  if (result.code === 0) { selectedIds.value = []; loadReports() }
}

async function doMerge() {
  if (mergeSelectIds.value.length < 2) { alert(lang.value === 'zh' ? '至少选2个' : 'Select at least 2'); return }
  if (!mergeName.value) { alert(lang.value === 'zh' ? '输入名称' : 'Enter name'); return }
  
  let totalQty = 0
  const selectedReports = reports.value.filter(r => mergeSelectIds.value.includes(r.id))
  selectedReports.forEach(r => totalQty += r.total_qty || 0)
  
  const result = await callApi('/api/excel-report/merge-save', {
    method: 'POST',
    body: JSON.stringify({
      name: mergeName.value,
      report_ids: mergeSelectIds.value,
      merged_data: { reports: selectedReports, total_qty: totalQty }
    })
  })
  
  if (result.code === 0) {
    showMergeModal.value = false
    mergeName.value = ''
    mergeSelectIds.value = []
    activeTab.value = 'merges'
    loadMerges()
    alert(lang.value === 'zh' ? '合并成功！' : 'Merge saved!')
  }
}

async function viewMerge(m) {
  // 跳转到合并详情页面
  window.location.href = `/excel-merge-view?id=${m.id}`
}

async function escapeHtml(text) {
  if (!text) return ''
  const div = document.createElement('div')
  div.textContent = text
  return div.innerHTML
}

function printReport() {
  // 打开新窗口
  const printWindow = window.open('', '_blank')
  if (!printWindow) {
    alert(lang.value === 'zh' ? '请允许弹出窗口' : 'Please allow popups')
    return
  }
  
  // 获取弹窗内容
  const modalBody = document.querySelector('.modal-body')
  if (!modalBody) {
    alert(lang.value === 'zh' ? '无法获取内容' : 'Cannot get content')
    return
  }
  
  // 复制弹窗内容到新窗口
  const content = modalBody.innerHTML
  const langText = lang.value === 'zh' ? '打印预览' : 'Print Preview'
  
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <title>${langText}</title>
      <style>
        body { font-family: Arial, sans-serif; padding: 20px; color: #333; font-size: 14px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 13px; }
        th { background: #f5f5f5; font-weight: 600; }
        .rank { font-weight: bold; color: #409eff; text-align: center; }
        .store-name { font-weight: 600; }
        @media print { body { padding: 0; } }
      </style>
    </head>
    <body>
      ${content}
      <script>window.onload = function() { window.print(); }<\/script>
    </body>
    </html>
  `)
  printWindow.document.close()
}


onMounted(() => { loadReports() })
</script>

<style scoped>
.manage { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.header h1 { margin: 0; font-size: 24px; }
.header-actions { display: flex; gap: 10px; }
.lang-toggle { display: flex; gap: 5px; margin-bottom: 20px; }
.lang-toggle button { padding: 6px 12px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer; }
.lang-toggle button.active { background: #409eff; color: white; border-color: #409eff; }
.tabs { display: flex; gap: 5px; margin-bottom: 20px; }
.tabs button { padding: 10px 20px; border: 1px solid #ddd; background: white; border-radius: 8px; cursor: pointer; }
.tabs button.active { background: #409eff; color: white; border-color: #409eff; }
.btn { padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 6px; cursor: pointer; }
.btn-primary { background: #409eff; color: white; border-color: #409eff; }
.btn-small { padding: 4px 8px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer; font-size: 12px; margin-right: 4px; }
.btn-small.danger { color: #f56c6c; border-color: #f56c6c; }
.data-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; margin-bottom: 20px; }
.data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
.data-table th { background: #fafafa; font-weight: 600; color: #606266; }
.data-table .rank { font-weight: bold; color: #409eff; min-width: 40px; text-align: center; }
.batch-actions { margin-top: 15px; padding: 10px; background: #f0f9eb; border-radius: 6px; display: flex; gap: 15px; align-items: center; }
.empty-state { text-align: center; padding: 80px 20px; background: white; border-radius: 12px; }
.empty-icon { font-size: 64px; margin-bottom: 20px; }
.empty-text { color: #909399; margin-bottom: 20px; }
.modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; overflow-y: auto; }
.modal-content { background: white; border-radius: 12px; width: 95%; max-width: 900px; max-height: 90vh; overflow-y: auto; margin: 20px; }
.modal-content.full { max-width: 950px; }
.modal-content.small { max-width: 500px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid #eee; position: sticky; top: 0; background: white; z-index: 1; }
.modal-header h3 { margin: 0; }
.close { background: none; border: none; font-size: 24px; cursor: pointer; color: #909399; }
.modal-body { padding: 20px; }
.report-section { margin-bottom: 30px; }
.report-section h2 { margin: 0 0 20px 0; font-size: 18px; color: #303133; padding-bottom: 10px; border-bottom: 2px solid #409eff; }
.info-table { width: 100%; border-collapse: collapse; background: #fafafa; border-radius: 8px; }
.info-table td { padding: 10px 15px; border: 1px solid #eee; }
.info-table td:first-child { width: 140px; font-weight: 600; color: #606266; }
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
.stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; }
.stat-value { font-size: 28px; font-weight: bold; }
.stat-label { font-size: 13px; opacity: 0.9; margin-top: 5px; }
.insights ul { margin: 0; padding-left: 20px; }
.insights li { padding: 8px 0; color: #606266; line-height: 1.6; }

/* 打印样式 */
.modal-header-actions { display: flex; gap: 10px; align-items: center; }
.btn-print { padding: 6px 12px; background: #67c23a; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
.btn-print:hover { background: #85ce61; }

@media print {
  .manage { padding: 0; background: white; }
  .header, .lang-toggle, .tabs, .batch-actions, .empty-state, .modal-header-actions, .close, .btn-print { display: none !important; }
  .modal { position: static !important; background: none !important; overflow: visible !important; }
  .modal-content { width: 100% !important; max-width: 100% !important; max-height: none !important; box-shadow: none !important; border-radius: 0 !important; overflow: visible !important; }
  .modal-body { padding: 10px; overflow: visible !important; max-height: none !important; }
  .report-section { page-break-inside: avoid; margin-bottom: 15px; }
  .stats-grid { grid-template-columns: repeat(4, 1fr); gap: 10px; }
  .stat-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .data-table { page-break-inside: auto; width: 100% !important; }
  .data-table tbody { display: block; max-height: none !important; overflow: visible !important; }
  .data-table tr { page-break-inside: avoid; }
  body { overflow: visible !important; }
}
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #606266; }
.form-group input[type="text"] { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; }
.checkbox-list { max-height: 200px; overflow-y: auto; border: 1px solid #eee; border-radius: 6px; padding: 10px; }
.checkbox-list label { display: block; padding: 5px 0; cursor: pointer; }
.form-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

/* Store expandable rows */
.store-row { cursor: pointer; transition: background 0.2s; }
.store-row:hover { background: #f5f7fa; }
.store-row.expanded { background: #ecf5ff; }
.store-name { font-weight: 600; }
.expand-icon { margin-right: 8px; color: #409eff; font-size: 12px; }
.sku-detail-row td { padding: 0; background: #fafafa; }
.sku-detail-box { padding: 15px 20px; }
.sku-detail-box h4 { margin: 0 0 15px 0; color: #303133; font-size: 15px; }
.sku-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; }
.sku-table th, .sku-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 13px; }
.sku-table th { background: #f5f7fa; font-weight: 600; color: #606266; }
.sku-table .top-sku { background: #fff9e6; }
.sku-table .top-sku td:first-child { color: #ff6b00; font-weight: bold; }
.sku-code { font-family: monospace; color: #409eff; }
.sku-desc { color: #606266; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.no-sku-data { text-align: center; padding: 20px; color: #909399; }
.rank { font-weight: bold; color: #409eff; text-align: center; }
.qty-high { color: #67c23a; font-weight: bold; }
.qty-low { color: #f56c6c; }
</style>
