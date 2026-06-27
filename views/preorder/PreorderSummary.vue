<template>
  <div class="p-6 max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">📊 订货单汇总表</h1>

    <el-card class="mb-4">
      <el-form :inline="true" :model="filter" @submit.prevent>
        <el-form-item label="产品">
          <el-select v-model="filter.product_id" placeholder="选择产品" filterable style="width: 360px" @change="loadSummary">
            <el-option v-for="p in products" :key="p.id" :label="`${p.name} | ${p.sku}`" :value="p.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="开始日期">
          <el-date-picker v-model="filter.date_start" type="date" placeholder="开始" value-format="YYYY-MM-DD" @change="loadSummary" />
        </el-form-item>
        <el-form-item label="结束日期">
          <el-date-picker v-model="filter.date_end" type="date" placeholder="结束" value-format="YYYY-MM-DD" @change="loadSummary" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadSummary">刷新</el-button>
          <el-button type="success" :disabled="!data.stores.length" @click="exportExcel">📥 导出 Excel</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card v-if="!filter.product_id">
      <el-empty description="请先选择产品查看汇总" />
    </el-card>

    <template v-else>
      <el-card class="mb-4">
        <h3 class="text-lg font-semibold mb-3">📋 UPCOMING STOCKS AVAILABLE ADVISORY</h3>
        <el-descriptions :column="3" border>
          <el-descriptions-item label="总数量 (TOTAL QTY)">{{ data.totals.total_qty }} PCS</el-descriptions-item>
          <el-descriptions-item label="总箱数 (TOTAL BOX)">{{ data.totals.total_box }}</el-descriptions-item>
          <el-descriptions-item label="下单门店数">{{ data.totals.store_count }} / {{ data.stores.length }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="balanced ? 'success' : 'warning'">{{ balanced ? '✓ BALANCED' : '⚠ 仍有门店未下单' }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="生成时间">{{ formatDate(new Date()) }}</el-descriptions-item>
          <el-descriptions-item label="导出批次">{{ batchNo }}</el-descriptions-item>
        </el-descriptions>
      </el-card>

      <el-card>
        <h3 class="text-lg font-semibold mb-3">🏪 ARC DISTRIBUTION GUIDE（各门店分货量）</h3>
        <el-table :data="data.stores" stripe border>
          <el-table-column type="index" label="#" width="60" />
          <el-table-column prop="store_name" label="门店名称" min-width="160" />
          <el-table-column prop="store_code" label="门店编码" width="140" />
          <el-table-column label="数量 (PCS)" width="120" align="center">
            <template #default="{ row }">
              <el-input-number v-model="row.quantity" :min="0" size="small" style="width: 100px" @change="recalc" />
            </template>
          </el-table-column>
          <el-table-column label="箱数" width="100" align="center">
            <template #default="{ row }">
              <el-input-number v-model="row.box_qty" :min="0" size="small" style="width: 80px" @change="recalc" />
            </template>
          </el-table-column>
          <el-table-column label="原始订单" min-width="200">
            <template #default="{ row }">
              <div v-if="row.orders.length" class="text-xs">
                <div v-for="o in row.orders" :key="o.order_no">{{ o.order_no }} × {{ o.qty }}</div>
              </div>
              <span v-else class="text-gray-400 text-xs">— 未下单 —</span>
            </template>
          </el-table-column>
        </el-table>

        <div class="mt-4 text-right">
          <el-tag size="large" effect="dark">合计: {{ data.totals.total_qty }} PCS / {{ data.totals.total_box }} BOX</el-tag>
        </div>
      </el-card>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import * as XLSX from 'xlsx'
import api from '../../services/api.js'

const filter = ref({ product_id: null, date_start: null, date_end: null })
const products = ref([])
const data = ref({ stores: [], totals: { total_qty: 0, total_box: 0, store_count: 0 } })

const balanced = computed(() => {
  if (!data.value.stores.length) return false
  return data.value.totals.store_count === data.value.stores.length
})

const batchNo = computed(() => {
  const d = new Date()
  return `BATCH-${d.getFullYear()}${String(d.getMonth()+1).padStart(2,'0')}${String(d.getDate()).padStart(2,'0')}-${String(d.getHours()).padStart(2,'0')}${String(d.getMinutes()).padStart(2,'0')}`
})

async function loadProducts() {
  try {
    const r = await api.get('/preorder/products')
    products.value = r.data?.data || []
  } catch (e) { ElMessage.error('产品加载失败: ' + e.message) }
}

async function loadSummary() {
  if (!filter.value.product_id) return
  try {
    const r = await api.get('/preorder/aggregate', { params: filter.value })
    data.value = r.data?.data || { stores: [], totals: { total_qty: 0, total_box: 0, store_count: 0 } }
  } catch (e) { ElMessage.error('汇总加载失败: ' + e.message) }
}

function recalc() {
  const totals = { total_qty: 0, total_box: 0, store_count: 0 }
  for (const s of data.value.stores) {
    totals.total_qty += Number(s.quantity || 0)
    totals.total_box += Number(s.box_qty || 0)
    if (Number(s.quantity || 0) > 0) totals.store_count += 1
  }
  data.value.totals = totals
}

function formatDate(d) { if (!d) return ''; return new Date(d).toLocaleString('zh-CN') }

function exportExcel() {
  const product = products.value.find(p => p.id === filter.value.product_id)
  const productName = product?.name || 'product'

  // Sheet 1: UPCOMING STOCKS AVAILABLE ADVISORY
  const summaryRows = [
    ['UPCOMING STOCKS AVAILABLE ADVISORY'],
    [],
    ['STOCKS ORDER FROM', 'DUNHILL'],
    ['PRODUCT', productName],
    ['STOCK #', product?.sku || ''],
    [],
    ['TOTAL QTY', data.value.totals.total_qty],
    ['TOTAL BOX', data.value.totals.total_box],
    ['TOTAL STORES', data.value.totals.store_count],
    ['BATCH NO', batchNo.value],
    ['GENERATED AT', formatDate(new Date())],
    [],
    ['REMARK', balanced.value ? 'BALANCED' : 'NOT BALANCED']
  ]

  // Sheet 2: ARC DISTRIBUTION GUIDE
  const distRows = [
    ['ARC DISTRIBUTION GUIDE'],
    [],
    ['#', 'STORE NAME', 'STORE CODE', 'QTY (PCS)', 'BOX', 'STATUS', 'ORDERS']
  ]
  data.value.stores.forEach((s, i) => {
    distRows.push([
      i + 1,
      s.store_name,
      s.store_code || '',
      s.quantity,
      s.box_qty,
      s.quantity > 0 ? 'OK' : 'PENDING',
      s.orders.map(o => `${o.order_no}×${o.qty}`).join(', ')
    ])
  })
  // totals row
  distRows.push([])
  distRows.push(['TOTAL', '', '', data.value.totals.total_qty, data.value.totals.total_box, '', ''])

  const wb = XLSX.utils.book_new()
  const ws1 = XLSX.utils.aoa_to_sheet(summaryRows)
  ws1['!cols'] = [{ wch: 28 }, { wch: 32 }]
  XLSX.utils.book_append_sheet(wb, ws1, 'UPCOMING STOCKS')

  const ws2 = XLSX.utils.aoa_to_sheet(distRows)
  ws2['!cols'] = [{ wch: 5 }, { wch: 24 }, { wch: 18 }, { wch: 12 }, { wch: 10 }, { wch: 12 }, { wch: 40 }]
  XLSX.utils.book_append_sheet(wb, ws2, 'ARC DISTRIBUTION')

  XLSX.writeFile(wb, `preorder-summary-${batchNo.value}.xlsx`)
  ElMessage.success('Excel 已导出')
}

onMounted(async () => {
  await loadProducts()
})
</script>
