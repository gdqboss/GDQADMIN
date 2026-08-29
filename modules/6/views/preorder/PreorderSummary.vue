<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import PageHeader from '../../components/PageHeader.vue'
import Pagination from '../../components/Pagination.vue'
import api from '../../services/api.js'
import * as XLSX from 'xlsx'

const { t } = useI18n()
const router = useRouter()

const activeTab = ref('summary')

// 权限（admin 默认 true）
const canConfirmWarehouse = ref(true)
const canConfirmShopkeeper = ref(true)
const canConfirm = computed(() => canConfirmWarehouse.value || canConfirmShopkeeper.value)

// 汇总查询条件
const filter = reactive({ product_id: null, date_start: null, date_end: null })
const products = ref([])
const aggregateData = reactive({
  stores: [],
  totals: { total_qty: 0, total_box: 0, store_count: 0 }
})

const allStoresOrdered = computed(() => {
  return aggregateData.stores.length
    ? aggregateData.totals.store_count === aggregateData.stores.length
    : false
})

const batchNo = computed(() => {
  const d = new Date()
  const pad = (n) => String(n).padStart(2, '0')
  return `BATCH-${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}-${pad(d.getHours())}${pad(d.getMinutes())}`
})

async function loadProducts() {
  try {
    const res = await api.get('/preorder/products')
    products.value = res.data || []
  } catch (e) {
    ElMessage.error('产品加载失败: ' + e.message)
  }
}

async function loadAggregate() {
  if (!filter.product_id) return
  try {
    const res = await api.get('/preorder/aggregate', { params: filter })
    const data = res.data || { stores: [], totals: { total_qty: 0, total_box: 0, store_count: 0 } }
    aggregateData.stores = data.stores || []
    aggregateData.totals = data.totals || { total_qty: 0, total_box: 0, store_count: 0 }
  } catch (e) {
    ElMessage.error('汇总加载失败: ' + e.message)
  }
}

function recalcTotals() {
  const totals = { total_qty: 0, total_box: 0, store_count: 0 }
  for (const s of aggregateData.stores) {
    totals.total_qty += Number(s.quantity || 0)
    totals.total_box += Number(s.box_qty || 0)
    if (Number(s.quantity || 0) > 0) totals.store_count += 1
  }
  aggregateData.totals = totals
}

function fmtDate(d) {
  return d ? new Date(d).toLocaleString('zh-CN') : ''
}

function exportExcel() {
  const product = products.value.find(p => p.id === filter.product_id)
  const productName = product?.name || 'product'
  const header = [
    ['UPCOMING STOCKS AVAILABLE ADVISORY'],
    [],
    ['STOCKS ORDER FROM', 'DUNHILL'],
    ['PRODUCT', productName],
    ['STOCK #', product?.sku || ''],
    [],
    ['TOTAL QTY', aggregateData.totals.total_qty],
    ['TOTAL BOX', aggregateData.totals.total_box],
    ['TOTAL STORES', aggregateData.totals.store_count],
    ['BATCH NO', batchNo.value],
    ['GENERATED AT', fmtDate(new Date())],
    [],
    ['REMARK', allStoresOrdered.value ? 'BALANCED' : 'NOT BALANCED']
  ]
  const body = [
    ['ARC DISTRIBUTION GUIDE'],
    [],
    ['#', 'STORE NAME', 'STORE CODE', 'QTY (PCS)', 'BOX', 'STATUS', 'ORDERS']
  ]
  aggregateData.stores.forEach((s, idx) => {
    body.push([
      idx + 1, s.store_name, s.store_code || '',
      s.quantity, s.box_qty,
      s.quantity > 0 ? 'OK' : 'PENDING',
      (s.orders || []).map(o => `${o.order_no}×${o.qty}`).join(', ')
    ])
  })
  body.push([])
  body.push(['TOTAL', '', '', aggregateData.totals.total_qty, aggregateData.totals.total_box, '', ''])

  const wb = XLSX.utils.book_new()
  const ws1 = XLSX.utils.aoa_to_sheet(header)
  ws1['!cols'] = [{ wch: 28 }, { wch: 32 }]
  XLSX.utils.book_append_sheet(wb, ws1, 'UPCOMING STOCKS')
  const ws2 = XLSX.utils.aoa_to_sheet(body)
  ws2['!cols'] = [{ wch: 5 }, { wch: 24 }, { wch: 18 }, { wch: 12 }, { wch: 10 }, { wch: 12 }, { wch: 40 }]
  XLSX.utils.book_append_sheet(wb, ws2, 'ARC DISTRIBUTION')
  XLSX.writeFile(wb, `preorder-summary-${batchNo.value}.xlsx`)
  ElMessage.success('Excel 已导出')
}

// DRF 状态管理
const drfList = ref([])
const sourceOrders = ref([])
const selectedSourceIds = ref([])
const pendingDrfs = computed(() => drfList.value.filter(d => d.status === 'pending'))

const asnDialog = ref(false)
const asnForm = reactive({
  drf_ids: [],
  warehouse_name: 'DC1',
  docking_bay: '',
  delivery_time: null,
  driver_name: '',
  helper_count: 0,
  vehicle_type: '',
  plate_no: '',
  remark: ''
})

const cartonDialog = ref(false)
const cartonForm = reactive({
  drf_id: null,
  product_name: '',
  expected_qty: 0,
  total_boxes: 2,
  weight_kg: null,
  dimensions: ''
})

function openAsnDialog() {
  asnForm.drf_ids = []
  asnDialog.value = true
}

function openCartonDialog(row) {
  cartonForm.drf_id = row.id
  cartonForm.product_name = row.product_name
  cartonForm.expected_qty = row.expected_qty
  cartonForm.total_boxes = Math.max(1, Math.ceil(row.expected_qty / 3))
  cartonForm.weight_kg = null
  cartonForm.dimensions = ''
  cartonDialog.value = true
}

async function submitAsn() {
  try {
    const res = await api.post('/preorder/asn/create', asnForm)
    ElMessage.success(`ASN 已创建：${res.data.asn_no}（${res.data.total_vdrs} 个 DRF）`)
    asnDialog.value = false
    await loadDrfs()
  } catch (e) {
    ElMessage.error('创建失败: ' + (e.response?.data?.message || e.message))
  }
}

async function submitCarton() {
  try {
    const res = await api.post('/preorder/cartons/generate', cartonForm)
    ElMessage.success(`已生成 ${res.data.created} 个箱唛`)
    cartonDialog.value = false
    await loadDrfs()
  } catch (e) {
    ElMessage.error('生成失败: ' + (e.response?.data?.message || e.message))
  }
}

function printCarton(id) {
  router.push(`/preorder/carton-print/${id}`)
}

function gotoScan() {
  router.push('/preorder/scan')
}

const drfStats = computed(() => {
  const s = { warehouse_visible: 0, pending_shopkeeper: 0, done: 0 }
  for (const d of drfList.value) {
    if (d.status === 'pending') s.warehouse_visible++
    else if (d.status === 'warehouse_confirmed') s.pending_shopkeeper++
    else if (d.status === 'shopkeeper_confirmed') s.done++
  }
  return s
})

function statusLabel(s) {
  return { pending: '待仓管', warehouse_confirmed: '待店长', shopkeeper_confirmed: '已完成' }[s] || s
}
function statusType(s) {
  return { pending: 'info', warehouse_confirmed: 'warning', shopkeeper_confirmed: 'success' }[s] || ''
}
function skuDisplay(row) {
  if (!row.sku_code) return ''
  try {
    const specs = typeof row.sku_specs === 'string' ? JSON.parse(row.sku_specs) : row.sku_specs
    const vals = Object.values(specs || {}).filter(Boolean)
    if (vals.length) return `${row.sku_code} - ${vals.join('/')}`
  } catch {}
  return row.sku_code
}
function calcDiscrepancy(row) {
  const expected = Number(row.expected_qty) || 0
  const actual = Number(row.actual_qty) || 0
  return expected ? Math.round(Math.abs(actual - expected) / expected * 10000) / 100 : 0
}

const canWarehouseConfirm = (row) => canConfirmWarehouse.value && row.status === 'pending'
const canShopkeeperConfirm = (row) => canConfirmShopkeeper.value && row.status === 'warehouse_confirmed'

async function loadDrfs() {
  try {
    const res = await api.get('/preorder/drf/list')
    const rows = res.data || []
    await Promise.all(rows.map(async d => {
      try {
        const cr = await api.get(`/preorder/cartons/list?drf_id=${d.id}`)
        d.has_cartons = (cr.data || []).length > 0
      } catch { d.has_cartons = false }
    }))
    drfList.value = rows
  } catch (e) {
    ElMessage.error('DRF 加载失败: ' + e.message)
  }
}

async function loadSourceOrders() {
  if (!canConfirmWarehouse.value) { sourceOrders.value = []; return }
  try {
    const res = await api.get('/preorder/drf/source-orders')
    sourceOrders.value = (res.data || []).filter(o => !o.drf_id)
  } catch (e) {
    ElMessage.error('源订单加载失败: ' + e.message)
  }
}

async function initDrfs() {
  if (!selectedSourceIds.value.length) return
  try {
    const res = await api.post('/preorder/drf/init', { order_item_ids: selectedSourceIds.value })
    const { created, skipped } = res.data || {}
    ElMessage.success(`DRF 行已初始化：新建 ${created} 条，跳过 ${skipped} 条`)
    selectedSourceIds.value = []
    await loadSourceOrders()
    await loadDrfs()
  } catch (e) {
    ElMessage.error('初始化失败: ' + e.message)
  }
}

async function warehouseConfirm(row) {
  if (row.actual_qty === null || row.actual_qty === undefined) {
    ElMessage.warning('请填写实收数')
    return
  }
  try {
    await ElMessageBox.confirm(
      `仓管确认：${row.product_name} 实收 ${row.actual_qty} PCS / ${row.actual_boxes} 箱？`,
      '仓管确认',
      { type: 'warning' }
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
    await loadDrfs()
  } catch (e) {
    ElMessage.error('确认失败: ' + (e.response?.data?.message || e.message))
  }
}

async function shopkeeperConfirm(row) {
  try {
    await ElMessageBox.confirm(
      `店长终审：${row.product_name} 实收 ${row.actual_qty} PCS（仓管已确认）？`,
      '店长终审',
      { type: 'success' }
    )
  } catch { return }
  try {
    await api.post('/preorder/drf/shopkeeper-confirm', { drf_id: row.id, remark: 'OK' })
    ElMessage.success('店长确认完成')
    await loadDrfs()
  } catch (e) {
    ElMessage.error('确认失败: ' + (e.response?.data?.message || e.message))
  }
}

onMounted(async () => {
  await loadProducts()
  await loadDrfs()
  await loadSourceOrders()
})
</script>

<template>
  <div class="p-6 max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">📊 产品预订</h1>

    <el-tabs v-model="activeTab" class="mb-4">
      <!-- 汇总 Tab -->
      <el-tab-pane label="📋 订货单汇总" name="summary">
        <el-card class="mb-4">
          <el-form :inline="true" :model="filter" @submit.prevent>
            <el-form-item label="产品">
              <el-select v-model="filter.product_id" placeholder="选择产品" filterable style="width: 360px" @change="loadAggregate">
                <el-option v-for="p in products" :key="p.id" :label="`${p.name} | ${p.sku}`" :value="p.id" />
              </el-select>
            </el-form-item>
            <el-form-item label="开始日期">
              <el-date-picker v-model="filter.date_start" type="date" placeholder="开始" value-format="YYYY-MM-DD" @change="loadAggregate" />
            </el-form-item>
            <el-form-item label="结束日期">
              <el-date-picker v-model="filter.date_end" type="date" placeholder="结束" value-format="YYYY-MM-DD" @change="loadAggregate" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="loadAggregate">刷新</el-button>
              <el-button type="success" :disabled="!aggregateData.stores.length" @click="exportExcel">📥 导出 Excel</el-button>
            </el-form-item>
          </el-form>
        </el-card>

        <template v-if="filter.product_id">
          <el-card class="mb-4">
            <h3 class="text-lg font-semibold mb-3">📋 UPCOMING STOCKS AVAILABLE ADVISORY</h3>
            <el-descriptions :column="3" border>
              <el-descriptions-item label="总数量 (TOTAL QTY)">{{ aggregateData.totals.total_qty }} PCS</el-descriptions-item>
              <el-descriptions-item label="总箱数 (TOTAL BOX)">{{ aggregateData.totals.total_box }}</el-descriptions-item>
              <el-descriptions-item label="下单门店数">{{ aggregateData.totals.store_count }} / {{ aggregateData.stores.length }}</el-descriptions-item>
              <el-descriptions-item label="状态">
                <el-tag :type="allStoresOrdered ? 'success' : 'warning'">
                  {{ allStoresOrdered ? '✓ BALANCED' : '⚠ 仍有门店未下单' }}
                </el-tag>
              </el-descriptions-item>
              <el-descriptions-item label="生成时间">{{ fmtDate(new Date()) }}</el-descriptions-item>
              <el-descriptions-item label="导出批次">{{ batchNo }}</el-descriptions-item>
            </el-descriptions>
          </el-card>

          <el-card>
            <h3 class="text-lg font-semibold mb-3">🏪 ARC DISTRIBUTION GUIDE（各门店分货量）</h3>
            <el-table :data="aggregateData.stores" stripe border>
              <el-table-column type="index" label="#" width="60" />
              <el-table-column prop="store_name" label="门店名称" min-width="160" />
              <el-table-column prop="store_code" label="门店编码" width="140" />
              <el-table-column label="数量 (PCS)" width="120" align="center">
                <template #default="{ row }">
                  <el-input-number v-model="row.quantity" :min="0" size="small" style="width: 100px" @change="recalcTotals" />
                </template>
              </el-table-column>
              <el-table-column label="箱数" width="100" align="center">
                <template #default="{ row }">
                  <el-input-number v-model="row.box_qty" :min="0" size="small" style="width: 80px" @change="recalcTotals" />
                </template>
              </el-table-column>
              <el-table-column label="原始订单" min-width="200">
                <template #default="{ row }">
                  <div v-if="(row.orders || []).length" class="text-xs">
                    <div v-for="o in row.orders" :key="o.order_no">{{ o.order_no }} × {{ o.qty }}</div>
                  </div>
                  <span v-else class="text-gray-400 text-xs">— 未下单 —</span>
                </template>
              </el-table-column>
            </el-table>
            <div class="mt-4 text-right">
              <el-tag size="large" effect="dark">
                合计: {{ aggregateData.totals.total_qty }} PCS / {{ aggregateData.totals.total_box }} BOX
              </el-tag>
            </div>
          </el-card>
        </template>
        <el-empty v-else description="请先选择产品查看汇总" />
      </el-tab-pane>

      <!-- DRF 供应商确认 Tab -->
      <el-tab-pane v-if="canConfirm" label="🚚 供应商确认 (DRF)" name="drf">
        <el-card class="mb-4">
          <div class="flex items-center justify-between mb-3">
            <div>
              <h3 class="text-lg font-semibold">STORE-SUPPLIER VALIDATED DELIVERY RECEIPT</h3>
              <p class="text-xs text-gray-500 mt-1">两步确认流程：仓管验收填实收数 → 推送店长终审</p>
            </div>
            <div class="flex gap-2 items-center">
              <el-button v-if="canConfirmWarehouse" type="primary" size="small" :disabled="!pendingDrfs.length" @click="openAsnDialog">
                📋 创建 ASN（{{ pendingDrfs.length }}）
              </el-button>
              <el-button v-if="canConfirmWarehouse" type="success" size="small" @click="gotoScan">📷 扫码收货</el-button>
              <el-tag>待仓管：{{ drfStats.warehouse_visible }}</el-tag>
              <el-tag type="warning">待店长：{{ drfStats.pending_shopkeeper }}</el-tag>
              <el-tag type="success">已完成：{{ drfStats.done }}</el-tag>
            </div>
          </div>

          <div v-if="canConfirmWarehouse && sourceOrders.length" class="mb-4 p-3 bg-blue-50 rounded">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm font-medium">📦 已审批订单（{{ sourceOrders.length }} 个待初始化）</span>
              <el-button size="small" type="primary" :disabled="!selectedSourceIds.length" @click="initDrfs">
                初始化 DRF（{{ selectedSourceIds.length }}）
              </el-button>
            </div>
            <el-table :data="sourceOrders" border size="small" max-height="240"
              @selection-change="rows => selectedSourceIds = rows.map(r => r.order_item_id)">
              <el-table-column type="selection" width="48" />
              <el-table-column prop="order_no" label="订单号" min-width="180" />
              <el-table-column prop="store_name" label="门店" min-width="140" />
              <el-table-column prop="supplier_name" label="供应商" min-width="160">
                <template #default="{ row }">
                  <span v-if="row.supplier_name">{{ row.supplier_name }}</span>
                  <span v-else class="text-orange-500 text-xs">⚠ 未关联</span>
                </template>
              </el-table-column>
              <el-table-column prop="product_name" label="产品" min-width="160" />
              <el-table-column prop="quantity" label="预订数" width="80" align="center" />
              <el-table-column label="DRF 状态" width="100" align="center">
                <template #default="{ row }">
                  <el-tag v-if="row.drf_id" :type="statusType(row.drf_status)" size="small">{{ statusLabel(row.drf_status) }}</el-tag>
                  <span v-else class="text-gray-400 text-xs">未生成</span>
                </template>
              </el-table-column>
            </el-table>
          </div>

          <el-table :data="drfList" border stripe>
            <el-table-column prop="order_no" label="订单号" min-width="160" />
            <el-table-column label="供应商 (VENDOR)" min-width="180">
              <template #default="{ row }">
                <div>{{ row.supplier_name || '—' }}</div>
                <div v-if="row.vendor_code" class="text-xs text-gray-500">CODE: {{ row.vendor_code }}</div>
              </template>
            </el-table-column>
            <el-table-column prop="store_name" label="门店" min-width="120" />
            <el-table-column label="产品 / SKU" min-width="180">
              <template #default="{ row }">
                <div>{{ row.product_name }}</div>
                <div class="text-xs text-gray-500">{{ skuDisplay(row) }}</div>
              </template>
            </el-table-column>
            <el-table-column prop="expected_qty" label="预订" width="70" align="center" />
            <el-table-column label="DRF 申请" width="80" align="center">
              <template #default="{ row }">{{ row.applied_qty ?? '—' }}</template>
            </el-table-column>
            <el-table-column label="期望到货" width="110" align="center">
              <template #default="{ row }">{{ row.expected_date || '—' }}</template>
            </el-table-column>
            <el-table-column label="实收 (PCS)" width="110" align="center">
              <template #default="{ row }">
                <el-input-number v-if="canWarehouseConfirm(row)" v-model="row.actual_qty" :min="0" size="small" style="width: 90px"
                  @change="row.discrepancy_rate = calcDiscrepancy(row)" />
                <span v-else>{{ row.actual_qty }}</span>
              </template>
            </el-table-column>
            <el-table-column label="箱数" width="90" align="center">
              <template #default="{ row }">
                <el-input-number v-if="canWarehouseConfirm(row)" v-model="row.actual_boxes" :min="0" size="small" style="width: 70px" />
                <span v-else>{{ row.actual_boxes }}</span>
              </template>
            </el-table-column>
            <el-table-column label="差异" width="80" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.discrepancy_rate > 5" type="danger" size="small">{{ row.discrepancy_rate }}% </el-tag>
                <el-tag v-else-if="row.discrepancy_rate > 0" type="warning" size="small">{{ row.discrepancy_rate }}% </el-tag>
                <el-tag v-else-if="row.actual_qty > 0" type="success" size="small">OK</el-tag>
                <span v-else class="text-gray-400">—</span>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="110" align="center">
              <template #default="{ row }">
                <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="280" align="center" fixed="right">
              <template #default="{ row }">
                <div class="flex gap-1 flex-wrap justify-center">
                  <el-button v-if="canWarehouseConfirm(row)" size="small" type="primary" @click="warehouseConfirm(row)">仓管确认</el-button>
                  <el-button v-else-if="canShopkeeperConfirm(row)" size="small" type="success" @click="shopkeeperConfirm(row)">店长终审</el-button>
                  <span v-else class="text-xs text-gray-400">
                    {{ row.warehouse_confirmer || '' }}
                    <span v-if="row.shopkeeper_confirmer">→ {{ row.shopkeeper_confirmer }}</span>
                  </span>
                  <el-button v-if="canWarehouseConfirm(row) && !row.has_cartons" size="small" type="warning" @click="openCartonDialog(row)">生成箱唛</el-button>
                  <el-button v-if="row.has_cartons" size="small" type="info" @click="printCarton(row.id)">打印箱唛</el-button>
                </div>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>
    </el-tabs>

    <!-- 创建 ASN 弹窗 -->
    <el-dialog v-model="asnDialog" title="📋 创建 ASN 送货单" width="640px">
      <el-form :model="asnForm" label-width="120px">
        <el-form-item label="选择 DRF">
          <el-table :data="pendingDrfs" border max-height="220"
            @selection-change="rows => asnForm.drf_ids = rows.map(r => r.id)">
            <el-table-column type="selection" width="48" />
            <el-table-column prop="order_no" label="订单" min-width="160" />
            <el-table-column prop="store_name" label="门店" min-width="120" />
            <el-table-column prop="expected_qty" label="件数" width="70" align="center" />
          </el-table>
          <div class="text-xs text-gray-500 mt-1">已选 {{ asnForm.drf_ids.length }} 个 DRF</div>
        </el-form-item>
        <el-form-item label="仓库"><el-input v-model="asnForm.warehouse_name" placeholder="例 DC1" /></el-form-item>
        <el-form-item label="月台"><el-input v-model="asnForm.docking_bay" placeholder="例 Bay 4" /></el-form-item>
        <el-form-item label="到货时间">
          <el-date-picker v-model="asnForm.delivery_time" type="datetime" placeholder="选择日期时间" value-format="YYYY-MM-DD HH:mm:ss" />
        </el-form-item>
        <el-form-item label="司机"><el-input v-model="asnForm.driver_name" /></el-form-item>
        <el-form-item label="帮手数"><el-input-number v-model="asnForm.helper_count" :min="0" /></el-form-item>
        <el-form-item label="车型"><el-input v-model="asnForm.vehicle_type" placeholder="例 6-WHEELER FWD VAN" /></el-form-item>
        <el-form-item label="车牌号"><el-input v-model="asnForm.plate_no" /></el-form-item>
        <el-form-item label="备注"><el-input v-model="asnForm.remark" type="textarea" :rows="2" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="asnDialog = false">取消</el-button>
        <el-button type="primary" :disabled="!asnForm.drf_ids.length" @click="submitAsn">创建</el-button>
      </template>
    </el-dialog>

    <!-- 生成箱唛弹窗 -->
    <el-dialog v-model="cartonDialog" title="📦 生成箱唛" width="480px">
      <p class="mb-2 text-sm">DRF #{{ cartonForm.drf_id }} · {{ cartonForm.product_name }}</p>
      <p class="mb-4 text-xs text-gray-500">件数：{{ cartonForm.expected_qty }}（系统自动均分到每箱）</p>
      <el-form :model="cartonForm" label-width="100px">
        <el-form-item label="总箱数"><el-input-number v-model="cartonForm.total_boxes" :min="1" :max="100" /></el-form-item>
        <el-form-item label="重量 (kg)"><el-input-number v-model="cartonForm.weight_kg" :min="0" :precision="2" /></el-form-item>
        <el-form-item label="尺寸 (cm)"><el-input v-model="cartonForm.dimensions" placeholder="60x40x30" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="cartonDialog = false">取消</el-button>
        <el-button type="primary" @click="submitCarton">生成</el-button>
      </template>
    </el-dialog>
  </div>
</template>