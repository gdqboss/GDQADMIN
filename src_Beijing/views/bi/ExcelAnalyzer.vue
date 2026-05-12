<template>
  <div class="excel-analyzer">
    <div class="header">
      <h1>📊 Excel Analyzer</h1>
      <div class="lang-toggle">
        <button @click="setLang('zh')" :class="{ active: lang === 'zh' }">中文</button>
        <button @click="setLang('en')" :class="{ active: lang === 'en' }">EN</button>
      </div>
    </div>

    <!-- Upload Area -->
    <div class="upload-area" v-if="!hasData">
      <div class="upload-box" @dragover.prevent @drop.prevent="handleDrop" @click="triggerUpload">
        <div class="upload-icon">📁</div>
        <div class="upload-text">{{ lang === 'zh' ? '拖拽Excel文件到此处' : 'Drag & drop Excel file here' }}</div>
        <div class="upload-text">{{ lang === 'zh' ? '或点击选择文件' : 'or click to select' }}</div>
        <div class="upload-hint">.xlsx, .xls</div>
      </div>
      <input ref="fileInputRef" type="file" accept=".xlsx,.xls" style="display:none" @change="handleFileSelect" />
    </div>

    <!-- Results -->
    <div v-if="hasData" class="report">
      <!-- File Info -->
      <div class="report-section">
        <h2>📋 {{ lang === 'zh' ? '文件信息' : 'File Info' }}</h2>
        <table class="info-table">
          <tr><td>{{ lang === 'zh' ? '文件名' : 'File Name' }}</td><td>{{ fileName }}</td></tr>
          <tr><td>{{ lang === 'zh' ? '供应商' : 'Supplier' }}</td><td>{{ report.supplier }}</td></tr>
          <tr><td>{{ lang === 'zh' ? '品牌' : 'Brand' }}</td><td>{{ report.brand }}</td></tr>
          <tr><td>{{ lang === 'zh' ? '时间范围' : 'Date Range' }}</td><td>{{ report.dateRange }}</td></tr>
        </table>
      </div>

      <!-- Core Stats -->
      <div class="report-section">
        <h2>📈 {{ lang === 'zh' ? '核心数据' : 'Core Data' }}</h2>
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-value">{{ report.totalQty?.toLocaleString() }}</div>
            <div class="stat-label">{{ lang === 'zh' ? '总销量' : 'Total Qty' }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">{{ report.totalRecords?.toLocaleString() }}</div>
            <div class="stat-label">{{ lang === 'zh' ? '总记录' : 'Records' }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">{{ report.uniqueSKU?.toLocaleString() }}</div>
            <div class="stat-label">{{ lang === 'zh' ? 'SKU数' : 'SKU Count' }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">{{ report.uniqueStores }}</div>
            <div class="stat-label">{{ lang === 'zh' ? '门店数' : 'Stores' }}</div>
          </div>
        </div>
      </div>

      <!-- Store Distribution -->
      <div class="report-section" v-if="report.allStores?.length">
        <h2>🏪 {{ lang === 'zh' ? '门店销量排行' : 'Store Sales Ranking' }}</h2>
        <table class="data-table">
          <thead><tr><th>#</th><th>{{ lang === 'zh' ? '门店' : 'Store' }}</th><th>{{ lang === 'zh' ? '销量' : 'Qty' }}</th><th>%</th></tr></thead>
          <tbody>
            <tr v-for="(s, i) in report.allStores" :key="i">
              <td class="rank">{{ s.rank }}</td>
              <td>{{ s.name }}</td><td>{{ s.qty?.toLocaleString() }}</td><td>{{ s.percent }}%</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Size Distribution -->
      <div class="report-section" v-if="report.sizeDistribution?.length">
        <h2>📐 {{ lang === 'zh' ? '尺寸分布' : 'Size Distribution' }}</h2>
        <table class="data-table">
          <thead><tr><th>#</th><th>{{ lang === 'zh' ? '尺寸' : 'Size' }}</th><th>{{ lang === 'zh' ? '销量' : 'Qty' }}</th><th>%</th></tr></thead>
          <tbody>
            <tr v-for="(s, i) in report.sizeDistribution" :key="i">
              <td class="rank">{{ i + 1 }}</td><td>{{ s.name }}</td><td>{{ s.qty?.toLocaleString() }}</td><td>{{ s.percent }}%</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Color Distribution -->
      <div class="report-section" v-if="report.colorDistribution?.length">
        <h2>🎨 {{ lang === 'zh' ? '颜色分布' : 'Color Distribution' }}</h2>
        <table class="data-table">
          <thead><tr><th>#</th><th>{{ lang === 'zh' ? '颜色' : 'Color' }}</th><th>{{ lang === 'zh' ? '销量' : 'Qty' }}</th><th>%</th></tr></thead>
          <tbody>
            <tr v-for="(c, i) in report.colorDistribution" :key="i">
              <td class="rank">{{ i + 1 }}</td><td>{{ c.name }} ({{ c.code }})</td><td>{{ c.qty?.toLocaleString() }}</td><td>{{ c.percent }}%</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- SKU+Size+Color Combo Analysis -->
      <div class="report-section" v-if="report.comboAnalysis?.top?.length">
        <h2>📊 SKU×尺寸×颜色 {{ lang === 'zh' ? '组合分析' : 'Combo Analysis' }}</h2>
        
        <!-- Top 10 -->
        <div class="combo-section">
          <h3>🔺 {{ lang === 'zh' ? '销量前10' : 'Top 10' }}</h3>
          <table class="data-table">
            <thead><tr><th>#</th><th>SKU</th><th>{{ lang === 'zh' ? '尺寸' : 'Size' }}</th><th>{{ lang === 'zh' ? '颜色' : 'Color' }}</th><th>{{ lang === 'zh' ? '销量' : 'Qty' }}</th></tr></thead>
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
          <h3>🔻 {{ lang === 'zh' ? '销量尾10' : 'Bottom 10' }}</h3>
          <table class="data-table">
            <thead><tr><th>#</th><th>SKU</th><th>{{ lang === 'zh' ? '尺寸' : 'Size' }}</th><th>{{ lang === 'zh' ? '颜色' : 'Color' }}</th><th>{{ lang === 'zh' ? '销量' : 'Qty' }}</th></tr></thead>
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
      <div class="report-section insights" v-if="report.insights?.length">
        <h2>💡 {{ lang === 'zh' ? '关键洞察' : 'Key Insights' }}</h2>
        <ul>
          <li v-for="(ins, i) in report.insights" :key="i">{{ ins }}</li>
        </ul>
      </div>

      <!-- Actions -->
      <div class="actions">
        <button class="btn" @click="reset">{{ lang === 'zh' ? '上传新文件' : 'Upload New' }}</button>
        <button class="btn btn-save" @click="saveReport">{{ lang === 'zh' ? '保存报告' : 'Save Report' }}</button>
      </div>
      <div class="save-success" v-if="showSaveSuccess">
        ✅ {{ lang === 'zh' ? '报告已保存' : 'Report saved!' }}
        <button @click="goManage">{{ lang === 'zh' ? '查看报告' : 'View Reports' }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import * as XLSX from 'xlsx'

const fileInputRef = ref(null)
const hasData = ref(false)
const fileName = ref('')
const report = ref({})
const showSaveSuccess = ref(false)
const lang = ref(localStorage.getItem('caimeite_locale') || 'zh')

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
  return colorNames[code]?.[lang.value] || code
}

function setLang(l) {
  lang.value = l
  localStorage.setItem('caimeite_locale', l)
}

function triggerUpload() {
  fileInputRef.value?.click()
}

function handleFileSelect(e) {
  const file = e.target.files[0]
  if (file) analyzeFile(file)
}

function handleDrop(e) {
  const file = e.dataTransfer.files[0]
  if (file) analyzeFile(file)
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
  const reader = new FileReader()
  reader.onload = (e) => {
    const data = new Uint8Array(e.target.result)
    const wb = XLSX.read(data, { type: 'array' })
    const ws = wb.Sheets[wb.SheetNames[0]]
    const jsonData = XLSX.utils.sheet_to_json(ws, { header: 1 })
    
    const headers = jsonData[0].map((h, i) => ({ name: String(h || '').trim(), index: i }))
    const idxDoc = headers.findIndex(h => /document title/i.test(h.name))
    const idxVendor = headers.findIndex(h => /vendor name|supplier/i.test(h.name))
    const idxBrand = headers.findIndex(h => /^brand$/i.test(h.name))
    // 智能识别门店列
    const allRows = jsonData.slice(1).filter(r => r.length > 5)
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
      if (store) storeMap[store] = (storeMap[store] || 0) + qty
      
      // Track per-store SKU data
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
      
      // 颜色提取 - 从SKU或描述中提取颜色代码
      const colorMatch = sku.match(colorRegex) || desc.match(colorRegex)
      if (colorMatch) {
        const colorCode = colorMatch[1].toUpperCase()
        colorMap[colorCode] = (colorMap[colorCode] || 0) + qty
      
      // SKU+尺寸+颜色组合 - 放宽条件
      const sizeMatch2 = desc.match(/(\d{2})\s*[A-Z]{2,}$/) || (sku && sku.match(/(\d{2})\s*[A-Z]{2,}$/))
      const colorMatch2 = sku.match(colorRegex) || desc.match(colorRegex)
      if (sizeMatch2 || colorMatch2) {
        const sizeName = sizeMatch2 ? (sizeMatch2[1] + '"') : '-'
        const colorCode = colorMatch2 ? colorMatch2[1].toUpperCase() : '-'
        const comboKey = `${sku}_${sizeName}_${colorCode}`
        if (!comboMap[comboKey]) comboMap[comboKey] = { sku, size: sizeName, color: colorCode, colorName: getColorName(colorCode), qty: 0, desc }
        comboMap[comboKey].qty += qty
      }
      }
      
      if (sku) {
        skuMap[sku] = (skuMap[sku] || 0) + qty
        if (!skuDescMap[sku]) skuDescMap[sku] = desc
      }
      
      if (!supplier && idxVendor !== -1) supplier = String(row[idxVendor] || '').trim()
      if (!brand && idxBrand !== -1) brand = String(row[idxBrand] || '').trim()
      if (!dateRange && idxDate !== -1) dateRange = String(row[idxDate] || '').trim()
    })
    
    const sortedStores = Object.entries(storeMap).sort((a, b) => b[1] - a[1])
    const sortedSizes = Object.entries(sizeMap).sort((a, b) => b[1] - a[1]).slice(0, 15)
    const sortedColors = Object.entries(colorMap).sort((a, b) => b[1] - a[1]).slice(0, 15)
    const sortedSKU = Object.entries(skuMap).map(([code, qty]) => ({ code, qty, desc: skuDescMap[code] || '' })).sort((a, b) => b.qty - a.qty).slice(0, 10)
    
    // SKU+尺寸+颜色组合分析
    const comboList = Object.values(comboMap).filter(c => c.qty > 0).sort((a, b) => b.qty - a.qty)
    const topCombos = comboList.slice(0, 10)
    const bottomCombos = comboList.length > 10 ? comboList.slice(-10).reverse() : []
    
    const insights = []
    if (sortedSizes[0]) insights.push(`${sortedSizes[0][0]} is the best seller (size)`)
    if (sortedColors[0]) insights.push(`${getColorName(sortedColors[0][0])} is the best seller (color)`)
    if (sortedStores.length >= 2) insights.push(`${sortedStores[0][0]} + ${sortedStores[1][0]} contribute most sales`)
    
    // Build per-store SKU data (exclude qty=0)
    const storeSkus = {}
    Object.entries(storeSkuMap).forEach(([store, skus]) => {
      const storeName = store.replace('SM STORE - ', '').replace('SM STORE ', '')
      storeSkus[storeName] = Object.entries(skus)
        .filter(([_, info]) => info.qty > 0)
        .map(([code, info]) => ({ code, qty: info.qty, desc: info.desc }))
        .sort((a, b) => b.qty - a.qty)
    })
    
    report.value = {
      supplier, brand, dateRange,
      totalQty,
      totalRecords: rows.length,
      uniqueSKU: Object.keys(skuMap).length,
      uniqueStores: Object.keys(storeMap).length,
      allStores: sortedStores.map(([name, qty], idx) => ({ rank: idx + 1, name: name.replace('SM STORE - ', '').replace('SM STORE ', ''), qty, percent: ((qty / totalQty) * 100).toFixed(1) })),
      sizeDistribution: sortedSizes.map(([name, qty]) => ({ name, qty, percent: ((qty / totalQty) * 100).toFixed(1) })),
      colorDistribution: sortedColors.map(([code, qty]) => ({ code, name: getColorName(code), qty, percent: ((qty / totalQty) * 100).toFixed(1) })),
      comboAnalysis: { top: topCombos, bottom: bottomCombos },
      topSKU: sortedSKU,
      storeSkus,
      insights
    }
    hasData.value = true
  }
  reader.readAsArrayBuffer(file)
}

function reset() {
  hasData.value = false
  fileName.value = ''
  report.value = {}
  if (fileInputRef.value) fileInputRef.value.value = ''
}

function saveReport() {
  const data = {
    name: report.value.supplier + ' - ' + report.value.dateRange,
    file_name: fileName.value,
    supplier: report.value.supplier,
    brand: report.value.brand,
    date_range: report.value.dateRange,
    total_qty: report.value.totalQty,
    total_records: report.value.totalRecords,
    unique_sku: report.value.uniqueSKU,
    unique_stores: report.value.uniqueStores,
    top_stores: report.value.allStores,
    size_dist: report.value.sizeDistribution,
    color_dist: report.value.colorDistribution || [],
    top_sku: report.value.topSKU,
    store_skus: report.value.storeSkus || {},
    insights: report.value.insights,
    combo_analysis: report.value.comboAnalysis
  }
  
  const token = localStorage.getItem('caimeite_token')
  fetch('/api/excel-report/save', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
    body: JSON.stringify(data)
  })
  .then(res => res.json())
  .then(result => {
    if (result.code === 0) showSaveSuccess.value = true
    else alert(result.message || 'Error')
  })
  .catch(err => { console.error(err); alert('Error') })
}

function goManage() {
  window.location.href = '/excel-report-manage'
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
</style>
