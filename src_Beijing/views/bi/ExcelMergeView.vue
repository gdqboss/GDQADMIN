<template>
  <div class="merge-view">
    <div class="header">
      <h1>🔗 {{ lang === 'zh' ? '合并报告详情' : 'Merge Report Detail' }}</h1>
      <div class="header-actions">
        <button class="btn-print" @click="printReport">{{ lang === 'zh' ? '🖨️ 打印' : '🖨️ Print' }}</button>
        <button class="btn" @click="goBack">← {{ lang === 'zh' ? '返回列表' : 'Back' }}</button>
      </div>
    </div>

    <div v-if="loading" class="loading">Loading...</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <div v-else class="report">
      <!-- Merge Info -->
      <div class="report-section">
        <h2>📋 {{ lang === 'zh' ? '合并报告信息' : 'Merge Report Info' }}</h2>
        <table class="info-table">
          <tr><td>{{ lang === 'zh' ? '合并名称' : 'Merge Name' }}</td><td>{{ mergeData.name }}</td></tr>
          <tr><td>{{ lang === 'zh' ? '包含报告数' : 'Reports Count' }}</td><td>{{ mergeData.report_ids?.length || 0 }}</td></tr>
          <tr><td>{{ lang === 'zh' ? '创建时间' : 'Created' }}</td><td>{{ formatDate(mergeData.created_at) }}</td></tr>
        </table>
      </div>

      <!-- Included Reports -->
      <div class="report-section">
        <h2>📑 {{ lang === 'zh' ? '包含的报告' : 'Included Reports' }}</h2>
        <table class="data-table">
          <thead><tr><th>{{ lang === 'zh' ? '报告名称' : 'Name' }}</th><th>{{ lang === 'zh' ? '供应商' : 'Supplier' }}</th><th>{{ lang === 'zh' ? '总销量' : 'Total Qty' }}</th><th>{{ lang === 'zh' ? 'SKU数' : 'SKU' }}</th></tr></thead>
          <tbody>
            <tr v-for="r in mergeData.reports" :key="r.id">
              <td>{{ r.name }}</td><td>{{ r.supplier }}</td><td>{{ r.total_qty?.toLocaleString() }}</td><td>{{ r.unique_sku }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Core Stats -->
      <div class="report-section">
        <h2>📈 {{ lang === 'zh' ? '合并统计' : 'Combined Statistics' }}</h2>
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-value">{{ combined.totalQty?.toLocaleString() }}</div>
            <div class="stat-label">{{ lang === 'zh' ? '合并总销量' : 'Combined Total' }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">{{ combined.totalRecords?.toLocaleString() }}</div>
            <div class="stat-label">{{ lang === 'zh' ? '合并总记录' : 'Combined Records' }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">{{ combined.skuSum }}</div>
            <div class="stat-label">{{ lang === 'zh' ? 'SKU数（报告合计）' : 'SKU (Sum of Reports)' }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">{{ combined.allStores.length }}</div>
            <div class="stat-label">{{ lang === 'zh' ? '总门店数' : 'Total Stores' }}</div>
          </div>
        </div>
      </div>

      <!-- All Stores -->
      <div class="report-section" v-if="combined.allStores.length">
        <h2>🏪 {{ lang === 'zh' ? '全部门店（按销量排序）' : 'All Stores (Sorted by Qty)' }}</h2>
        <table class="data-table">
          <thead><tr><th>#</th><th>{{ lang === 'zh' ? '门店' : 'Store' }}</th><th>{{ lang === 'zh' ? '销量' : 'Qty' }}</th><th>%</th></tr></thead>
          <tbody>
            <tr v-for="(s, i) in combined.allStores" :key="i">
              <td class="rank">{{ s.rank }}</td><td>{{ s.name }}</td><td>{{ s.qty?.toLocaleString() }}</td><td>{{ s.percent }}%</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Size Distribution -->
      <div class="report-section" v-if="combined.topSizes.length">
        <h2>📐 {{ lang === 'zh' ? '尺寸销量分布' : 'Size Distribution' }}</h2>
        <table class="data-table">
          <thead><tr><th>{{ lang === 'zh' ? '尺寸' : 'Size' }}</th><th>{{ lang === 'zh' ? '销量' : 'Qty' }}</th><th>%</th></tr></thead>
          <tbody>
            <tr v-for="(s, i) in combined.topSizes" :key="i">
              <td>{{ s.name }}</td><td>{{ s.qty?.toLocaleString() }}</td><td>{{ s.percent }}%</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Top SKU -->
      <div class="report-section" v-if="combined.topSku.length">
        <h2>🔥 {{ lang === 'zh' ? 'Top 5 SKU' : 'Top 5 SKU' }}</h2>
        <table class="data-table">
          <thead><tr><th>SKU</th><th>{{ lang === 'zh' ? '描述' : 'Description' }}</th><th>{{ lang === 'zh' ? '销量' : 'Qty' }}</th></tr></thead>
          <tbody>
            <tr v-for="(s, i) in combined.topSku" :key="i">
              <td>{{ s.code }}</td><td>{{ s.desc?.substring(0, 50) }}</td><td>{{ s.qty?.toLocaleString() }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Insights -->
      <div class="report-section insights" v-if="combined.insights.length">
        <h2>💡 {{ lang === 'zh' ? '关键洞察' : 'Key Insights' }}</h2>
        <ul>
          <li v-for="(ins, i) in combined.insights" :key="i">{{ ins }}</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const loading = ref(true)
const error = ref('')
const mergeData = ref({})
const combined = ref({ totalQty: 0, totalRecords: 0, skuSum: 0, allStores: [], topSizes: [], topSku: [], insights: [] })
const lang = ref(localStorage.getItem('caimeite_locale') || 'zh')

function formatDate(date) {
  if (!date) return ''
  return new Date(date).toLocaleDateString()
}

function goBack() {
  window.location.href = '/excel-report-manage'
}

function printReport() {
  window.print()
}

async function callApi(url, options = {}) {
  const token = localStorage.getItem('caimeite_token')
  const response = await fetch(url, {
    ...options,
    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json', ...options.headers }
  })
  return response.json()
}

onMounted(async () => {
  const id = new URLSearchParams(window.location.search).get('id')
  if (!id) {
    error.value = 'No merge ID provided'
    loading.value = false
    return
  }

  try {
    const result = await callApi(`/api/excel-report/merge/${id}`)
    if (result.code === 0) {
      mergeData.value = result.data
      
      // Combine all reports data
      let totalQty = 0
      let totalRecords = 0
      let skuSum = 0
      const allStoresMap = {}
      const allSizes = {}
      const allSku = {}
      const allSkuDesc = {}
      
      for (const r of result.data.reports || []) {
        totalQty += r.total_qty || 0
        totalRecords += r.total_records || 0
        skuSum += r.unique_sku || 0
        
        // Parse JSON fields
        try {
          const stores = JSON.parse(r.top_stores || '[]')
          stores.forEach(s => {
            const key = s.name
            allStoresMap[key] = (allStoresMap[key] || 0) + s.qty
          })
        } catch {}
        
        try {
          const sizes = JSON.parse(r.size_dist || '[]')
          sizes.forEach(s => {
            const key = s.name
            allSizes[key] = (allSizes[key] || 0) + s.qty
          })
        } catch {}
        
        try {
          const skus = JSON.parse(r.top_sku || '[]')
          skus.forEach(s => {
            const key = s.code
            if (!allSku[key] || s.qty > allSku[key]) {
              allSku[key] = s.qty
              allSkuDesc[key] = s.desc
            }
          })
        } catch {}
      }
      
      // Sort all stores with ranking
      const allStoresRanked = Object.entries(allStoresMap)
        .sort((a, b) => b[1] - a[1])
        .map(([name, qty], idx) => ({ rank: idx + 1, name, qty, percent: ((qty / totalQty) * 100).toFixed(1) }))
      
      const topSizes = Object.entries(allSizes)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 10)
        .map(([name, qty]) => ({ name, qty, percent: ((qty / totalQty) * 100).toFixed(1) }))
      
      const topSku = Object.entries(allSku)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 5)
        .map(([code, qty]) => ({ code, qty, desc: allSkuDesc[code] || '' }))
      
      // Generate insights
      const insights = []
      if (topSizes[0]) {
        insights.push(`${topSizes[0].name} ${lang.value === 'zh' ? '是最爆款尺寸，占' : 'is the best seller, accounting for'} ${topSizes[0].percent}%`)
      }
      if (allStoresRanked[0]) {
        insights.push(`${allStoresRanked[0].name} ${lang.value === 'zh' ? '销量最高' : 'has the highest sales'}`)
      }
      
      combined.value = {
        totalQty,
        totalRecords,
        skuSum,
        allStores: allStoresRanked,
        topSizes,
        topSku,
        insights
      }
    } else {
      error.value = result.message || 'Failed to load merge data'
    }
  } catch (e) {
    error.value = e.message
  }
  
  loading.value = false
})
</script>

<style scoped>
.merge-view { padding: 20px; background: #f5f7fa; min-height: 100vh; max-width: 1000px; margin: 0 auto; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
.header h1 { margin: 0; font-size: 24px; }
.btn { padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 6px; cursor: pointer; }
.loading, .error { text-align: center; padding: 60px; font-size: 18px; color: #606266; }
.error { color: #f56c6c; }
.report { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); }
.report-section { margin-bottom: 40px; }
.report-section h2 { margin: 0 0 20px 0; font-size: 18px; color: #303133; padding-bottom: 10px; border-bottom: 2px solid #409eff; display: inline-block; }
.info-table { width: 100%; border-collapse: collapse; background: #fafafa; border-radius: 8px; }
.info-table td { padding: 10px 15px; border: 1px solid #eee; }
.info-table td:first-child { width: 180px; font-weight: 600; color: #606266; }
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
.stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; }
.stat-value { font-size: 28px; font-weight: bold; }
.stat-label { font-size: 13px; opacity: 0.9; margin-top: 5px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
.data-table th { background: #fafafa; font-weight: 600; color: #606266; font-size: 13px; }
.data-table .rank { font-weight: bold; color: #409eff; min-width: 40px; text-align: center; }
.insights ul { margin: 0; padding-left: 20px; }
.insights li { padding: 8px 0; color: #606266; line-height: 1.6; }

.header-actions { display: flex; gap: 10px; align-items: center; }
.btn-print { padding: 8px 16px; background: #67c23a; color: white; border: none; border-radius: 6px; cursor: pointer; }
.btn-print:hover { background: #85ce61; }

@media print {
  .header { margin-bottom: 20px; }
  .header-actions { display: none; }
  .merge-view { padding: 10px; background: white; max-width: 100%; }
  .report { box-shadow: none; padding: 10px; }
  .report-section { page-break-inside: avoid; margin-bottom: 20px; }
  .stat-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .data-table { page-break-inside: auto; }
  .data-table tr { page-break-inside: avoid; }
}
</style>
