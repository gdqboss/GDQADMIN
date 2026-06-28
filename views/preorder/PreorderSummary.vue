<template>
  <div class="p-6 max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">📊 产品预订</h1>

    <el-tabs v-model="activeTab" class="mb-4">
      <!-- Tab 1: 订货单汇总（原有功能） -->
      <el-tab-pane label="📋 订货单汇总" name="summary">
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

        <el-empty v-if="!filter.product_id" description="请先选择产品查看汇总" />

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
      </el-tab-pane>

      <!-- Tab 2: 供应商确认 (DRF) — 仓管/店长协作确认到货 -->
      <el-tab-pane v-if="canShowDrf" label="🚚 供应商确认 (DRF)" name="drf">
        <el-card class="mb-4">
          <div class="flex items-center justify-between mb-3">
            <div>
              <h3 class="text-lg font-semibold">STORE-SUPPLIER VALIDATED DELIVERY RECEIPT</h3>
              <p class="text-xs text-gray-500 mt-1">
                两步确认流程：仓管验收填实收数 → 推送店长终审
              </p>
            </div>
            <div class="flex gap-2 items-center">
              <el-tag v-if="canInitDrf" type="info" size="small">从已审批订单初始化</el-tag>
              <el-tag>待仓管：{{ drfStats.warehouse_visible }}</el-tag>
              <el-tag type="warning">待店长：{{ drfStats.pending_shopkeeper }}</el-tag>
              <el-tag type="success">已完成：{{ drfStats.done }}</el-tag>
            </div>
          </div>

          <!-- 仓管：从源订单初始化 DRF 行（如果有 init 权限） -->
          <div v-if="canInitDrf && sourceOrders.length" class="mb-4 p-3 bg-blue-50 rounded">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm font-medium">📦 已审批订单（{{ sourceOrders.length }} 个待初始化）</span>
              <el-button size="small" type="primary" @click="initDrf" :disabled="!selectedOrderIds.length">
                初始化 DRF（{{ selectedOrderIds.length }}）
              </el-button>
            </div>
            <el-table :data="sourceOrders" border size="small" max-height="240"
                      @selection-change="rows => selectedOrderIds = rows.map(r => r.order_item_id)">
              <el-table-column type="selection" width="48" />
              <el-table-column prop="order_no" label="订单号" min-width="180" />
              <el-table-column prop="store_name" label="门店" min-width="140" />
              <el-table-column prop="product_name" label="产品" min-width="160" />
              <el-table-column prop="quantity" label="预订数" width="80" align="center" />
              <el-table-column label="DRF 状态" width="100" align="center">
                <template #default="{ row }">
                  <el-tag v-if="row.drf_id" :type="statusTagType(row.drf_status)" size="small">
                    {{ statusLabel(row.drf_status) }}
                  </el-tag>
                  <span v-else class="text-gray-400 text-xs">未生成</span>
                </template>
              </el-table-column>
            </el-table>
          </div>

          <!-- 主 DRF 列表（仓管 + 店长看到的内容不同） -->
          <el-table :data="drfList" border stripe>
            <el-table-column prop="order_no" label="订单号" min-width="180" />
            <el-table-column prop="store_name" label="门店" min-width="140" />
            <el-table-column label="产品 / SKU" min-width="200">
              <template #default="{ row }">
                <div>{{ row.product_name }}</div>
                <div class="text-xs text-gray-500">{{ formatSkuLabel(row) }}</div>
              </template>
            </el-table-column>
            <el-table-column prop="expected_qty" label="预订数" width="80" align="center" />
            <el-table-column label="期望到货" width="120" align="center">
              <template #default="{ row }">{{ row.expected_date || '—' }}</template>
            </el-table-column>
            <el-table-column label="实收数 (PCS)" width="120" align="center">
              <template #default="{ row }">
                <el-input-number v-if="canWarehouseEdit(row)"
                                 v-model="row.actual_qty" :min="0" size="small"
                                 style="width: 100px"
                                 @change="v => row.discrepancy_rate = calcDiscrepancy(row)" />
                <span v-else>{{ row.actual_qty }}</span>
              </template>
            </el-table-column>
            <el-table-column label="实收箱数" width="100" align="center">
              <template #default="{ row }">
                <el-input-number v-if="canWarehouseEdit(row)"
                                 v-model="row.actual_boxes" :min="0" size="small"
                                 style="width: 80px" />
                <span v-else>{{ row.actual_boxes }}</span>
              </template>
            </el-table-column>
            <el-table-column label="差异率" width="90" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.discrepancy_rate > 5" type="danger" size="small">
                  {{ row.discrepancy_rate }}%
                </el-tag>
                <el-tag v-else-if="row.discrepancy_rate > 0" type="warning" size="small">
                  {{ row.discrepancy_rate }}%
                </el-tag>
                <el-tag v-else-if="row.actual_qty > 0" type="success" size="small">OK</el-tag>
                <span v-else class="text-gray-400">—</span>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="120" align="center">
              <template #default="{ row }">
                <el-tag :type="statusTagType(row.status)" size="small">
                  {{ statusLabel(row.status) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="140" align="center" fixed="right">
              <template #default="{ row }">
                <el-button v-if="canWarehouseEdit(row)" size="small" type="primary"
                           @click="warehouseConfirm(row)">
                  仓管确认
                </el-button>
                <el-button v-else-if="canShopkeeperEdit(row)" size="small" type="success"
                           @click="shopkeeperConfirm(row)">
                  店长终审
                </el-button>
                <span v-else class="text-xs text-gray-400">
                  {{ row.warehouse_confirmer || '' }}
                  <span v-if="row.shopkeeper_confirmer">→ {{ row.shopkeeper_confirmer }}</span>
                </span>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import * as XLSX from 'xlsx'
import api from '../../services/api.js'
import { useUserStore } from '../../stores/user.js'

const userStore = useUserStore()
const activeTab = ref('summary')

// 权限判断
const canConfirmWarehouse = computed(() => userStore.canAccess('preorder:confirm_warehouse'))
const canConfirmShopkeeper = computed(() => userStore.canAccess('preorder:confirm_shopkeeper'))
const canShowDrf = computed(() => canConfirmWarehouse.value || canConfirmShopkeeper.value)
const canInitDrf = computed(() => canConfirmWarehouse.value)

// ============ Tab 1: 订货单汇总 ============
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

// ============ Tab 2: DRF 供应商确认 ============
const drfList = ref([])
const sourceOrders = ref([])
const selectedOrderIds = ref([])

const drfStats = computed(() => {
  const stats = { warehouse_visible: 0, pending_shopkeeper: 0, done: 0 }
  for (const r of drfList.value) {
    if (r.status === 'pending') stats.warehouse_visible++
    else if (r.status === 'warehouse_confirmed') stats.pending_shopkeeper++
    else if (r.status === 'shopkeeper_confirmed') stats.done++
  }
  return stats
})

function statusLabel(s) {
  return { pending: '待仓管', warehouse_confirmed: '待店长', shopkeeper_confirmed: '已完成' }[s] || s
}
function statusTagType(s) {
  return { pending: 'info', warehouse_confirmed: 'warning', shopkeeper_confirmed: 'success' }[s] || ''
}
function formatSkuLabel(row) {
  if (!row.sku_code) return ''
  try {
    if (row.sku_specs) {
      const specs = typeof row.sku_specs === 'string' ? JSON.parse(row.sku_specs) : row.sku_specs
      const vals = Object.values(specs || {}).filter(Boolean)
      if (vals.length) return `${row.sku_code} - ${vals.join('/')}`
    }
  } catch {}
  return row.sku_code
}
function calcDiscrepancy(row) {
  const expected = Number(row.expected_qty) || 0
  const actual = Number(row.actual_qty) || 0
  if (!expected) return 0
  return Math.round(Math.abs(actual - expected) / expected * 10000) / 100
}
function canWarehouseEdit(row) {
  return canConfirmWarehouse.value && row.status === 'pending'
}
function canShopkeeperEdit(row) {
  return canConfirmShopkeeper.value && row.status === 'warehouse_confirmed'
}

async function loadDrfList() {
  try {
    const r = await api.get('/preorder/drf/list')
    drfList.value = r.data?.data || []
  } catch (e) { ElMessage.error('DRF 加载失败: ' + e.message) }
}
async function loadSourceOrders() {
  if (!canInitDrf.value) { sourceOrders.value = []; return }
  try {
    const r = await api.get('/preorder/drf/source-orders')
    sourceOrders.value = (r.data?.data || []).filter(o => !o.drf_id)
  } catch (e) { ElMessage.error('源订单加载失败: ' + e.message) }
}

async function initDrf() {
  if (!selectedOrderIds.value.length) return
  try {
    const r = await api.post('/preorder/drf/init', { order_item_ids: selectedOrderIds.value })
    const { created, skipped } = r.data?.data || {}
    ElMessage.success(`DRF 行已初始化：新建 ${created} 条，跳过 ${skipped} 条`)
    selectedOrderIds.value = []
    await loadSourceOrders()
    await loadDrfList()
  } catch (e) { ElMessage.error('初始化失败: ' + e.message) }
}

async function warehouseConfirm(row) {
  if (row.actual_qty === null || row.actual_qty === undefined) {
    return ElMessage.warning('请填写实收数')
  }
  try {
    await ElMessageBox.confirm(
      `仓管确认：${row.product_name} 实收 ${row.actual_qty} PCS / ${row.actual_boxes} 箱？`,
      '仓管确认', { type: 'warning' }
    )
  } catch { return }
  try {
    await api.post('/preorder/drf/warehouse-confirm', {
      drf_id: row.id,
      dr_no: row.dr_no || null,
      actual_qty: row.actual_qty,
      actual_boxes: row.actual_boxes || 0,
      remark: row.remark || null
    })
    ElMessage.success('仓管确认完成，已推给店长')
    await loadDrfList()
  } catch (e) { ElMessage.error('确认失败: ' + (e.response?.data?.message || e.message)) }
}

async function shopkeeperConfirm(row) {
  try {
    await ElMessageBox.confirm(
      `店长终审：${row.product_name} 实收 ${row.actual_qty} PCS（仓管已确认）？`,
      '店长终审', { type: 'success' }
    )
  } catch { return }
  try {
    await api.post('/preorder/drf/shopkeeper-confirm', {
      drf_id: row.id,
      remark: 'OK'
    })
    ElMessage.success('店长确认完成')
    await loadDrfList()
  } catch (e) { ElMessage.error('确认失败: ' + (e.response?.data?.message || e.message)) }
}

onMounted(async () => {
  await loadProducts()
  // DRF Tab 始终加载（不论 canShowDrf，admin 看全部；店长看自己门店；后端会按角色过滤）
  // 用户角色对应权限由后端 RBAC 控制，前端只是 UI 层
  await loadDrfList()
  await loadSourceOrders()
})
</script>
