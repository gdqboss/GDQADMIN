<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import QrcodeDetailModal from '../../components/QrcodeDetailModal.vue'
import Pagination from '../../components/Pagination.vue'
import { useQrcodeStore } from '../../stores/qrcode.js'
import { useUserStore } from '../../stores/user.js'
import api from '../../services/api.js'

const { t } = useI18n()
const store = useQrcodeStore()
const userStore = useUserStore()
const canDelete = computed(() => userStore.canAccess('qrcode_delete'))
const canSelect = computed(() => true)
const products = ref([])

// ─── Pagination ─────────────────────────────────────────────────────────────────
const qrPage = ref(1)
const qrPageSize = 50

function loadQrcodes() {
  const params = { page: qrPage.value, size: qrPageSize }
  if (qrSearch.value) params.keyword = qrSearch.value
  if (qrStatusFilter.value) params.status = qrStatusFilter.value
  if (qrCategoryFilter.value) params.category = qrCategoryFilter.value
  if (qrDateStart.value) params.date_start = qrDateStart.value
  if (qrDateEnd.value) params.date_end = qrDateEnd.value
  store.fetchQrcodes(params)
}

onMounted(async () => {
  loadQrcodes()
  store.fetchAfterSale()
  store.fetchHierarchy()
  store.fetchCommissions()
  try {
    const res = await api.get('/products')
    if (res.code === 0) products.value = res.data.list || res.data
  } catch (e) { /* ignore */ }
})

const activeTab = ref('qrcodes')
const tabs = computed(() => [
  { key: 'qrcodes', label: t('qrcode.qrManagement'), icon: 'qr_code_2' },
  { key: 'scan', label: t('qrcode.scanRecords'), icon: 'qr_code_scanner' },
  { key: 'afterSale', label: t('qrcode.afterSaleManagement'), icon: 'support_agent' },
  { key: 'hierarchy', label: t('qrcode.hierarchy'), icon: 'account_tree' },
])

// QR code tab
const qrSearch = ref('')
const qrStatusFilter = ref('')
const qrCategoryFilter = ref('')
const qrDateStart = ref('')
const qrDateEnd = ref('')

watch([qrSearch, qrStatusFilter, qrCategoryFilter, qrDateStart, qrDateEnd], () => {
  qrPage.value = 1
  loadQrcodes()
})
watch(qrPage, loadQrcodes)

const filteredQrcodes = computed(() => store.qrcodes)

// Unique product categories for filter dropdown
const availableCategories = computed(() => {
  const cats = new Set(store.qrcodes.map(q => q.product_category).filter(Boolean))
  return [...cats].sort()
})

// Generate dialog
const showGenerate = ref(false)
const generateCount = ref(10)
function handleGenerate() {
  if (generateCount.value < 1 || generateCount.value > 1000) return
  store.generateBatch(generateCount.value)
  showGenerate.value = false
  generateCount.value = 10
}

// ═══ 决策 3: 盘点对话框 ═══
const showStocktake = ref(false)
const stocktakeWarehouseId = ref(1)
const stocktakeScannedIds = ref([])
const stocktakeResult = ref(null)
const stocktakeLoading = ref(false)
const stocktakeBlindMode = ref(false)

function openStocktake() {
  showStocktake.value = true
  stocktakeScannedIds.value = []
  stocktakeResult.value = null
}

function addScannedId(id) {
  const numId = parseInt(id)
  if (numId && !stocktakeScannedIds.value.includes(numId)) {
    stocktakeScannedIds.value.push(numId)
  }
}

async function runStocktake() {
  if (stocktakeScannedIds.value.length === 0) {
    alert('请先扫码或输入二维码 ID')
    return
  }
  stocktakeLoading.value = true
  try {
    const res = await fetch('/api/stocktake', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${localStorage.getItem('caimeite_token')}` },
      body: JSON.stringify({
        warehouse_id: stocktakeWarehouseId.value,
        qrcode_ids: stocktakeScannedIds.value,
        blind_mode: stocktakeBlindMode.value
      })
    })
    const data = await res.json()
    if (data.code === 0) {
      stocktakeResult.value = data.data
    } else {
      alert(`盘点失败: ${data.message}`)
    }
  } catch (e) {
    alert(`盘点异常: ${e.message}`)
  } finally {
    stocktakeLoading.value = false
  }
}

async function runReconcile() {
  if (!confirm('对账将根据 qrcodes 表重新计算 warehouse_stock，确认执行？')) return
  stocktakeLoading.value = true
  try {
    const res = await fetch('/api/reconcile', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${localStorage.getItem('caimeite_token')}` },
      body: JSON.stringify({})
    })
    const data = await res.json()
    if (data.code === 0) {
      alert(`对账完成: 同步 ${data.data.synced} 行，新建 ${data.data.created} 行`)
      stocktakeResult.value = null
      stocktakeScannedIds.value = []
    } else {
      alert(`对账失败: ${data.message}`)
    }
  } catch (e) {
    alert(`对账异常: ${e.message}`)
  } finally {
    stocktakeLoading.value = false
  }
}

// ═══ 决策 5: 重新绑定对话框 ═══
const showRebind = ref(false)
const rebindQrcode = ref(null)
const rebindProductId = ref(null)
const rebindSkuId = ref(null)
const rebindWarehouseId = ref(null)
const rebindLoading = ref(false)
const rebindResult = ref(null)

function openRebind() {
  if (selectedIds.value.length !== 1) return
  rebindQrcode.value = store.qrcodes.find(q => q.id === selectedIds.value[0])
  rebindProductId.value = rebindQrcode.value?.product_id || null
  rebindSkuId.value = rebindQrcode.value?.sku_id || null
  rebindWarehouseId.value = rebindQrcode.value?.warehouse_id || null
  rebindResult.value = null
  showRebind.value = true
}

async function runRebind() {
  rebindLoading.value = true
  try {
    const res = await fetch(`/api/qrcodes/rebind/${rebindQrcode.value.id}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${localStorage.getItem('caimeite_token')}` },
      body: JSON.stringify({
        product_id: rebindProductId.value,
        sku_id: rebindSkuId.value,
        warehouse_id: rebindWarehouseId.value
      })
    })
    const data = await res.json()
    if (data.code === 0) {
      rebindResult.value = data.data
      // 刷新列表
      await store.fetchList()
    } else {
      alert(`重新绑定失败: ${data.message}`)
    }
  } catch (e) {
    alert(`重新绑定异常: ${e.message}`)
  } finally {
    rebindLoading.value = false
  }
}

// Selection for batch operations
const selectedIds = ref([])
const allSelected = computed(() =>
  filteredQrcodes.value.length > 0 && filteredQrcodes.value.every(q => selectedIds.value.includes(q.id))
)
const someSelected = computed(() =>
  selectedIds.value.length > 0 && !allSelected.value
)
function toggleSelectAll() {
  if (allSelected.value) {
    selectedIds.value = []
  } else {
    selectedIds.value = filteredQrcodes.value.map(q => q.id)
  }
}
function toggleSelect(id) {
  const idx = selectedIds.value.indexOf(id)
  if (idx >= 0) selectedIds.value.splice(idx, 1)
  else selectedIds.value.push(id)
}
function toggleRow(checked, id) {
  const idx = selectedIds.value.indexOf(id)
  if (checked && idx < 0) selectedIds.value.push(id)
  else if (!checked && idx >= 0) selectedIds.value.splice(idx, 1)
}
async function handleDelete(qr) {
  if (!confirm(t('qrcode.confirmDeleteQr', { code: qr.code }))) return
  try {
    await store.deleteQrcodes([qr.id])
    selectedIds.value = selectedIds.value.filter(id => id !== qr.id)
  } catch (e) {
    alert(t('qrcode.deleteFailed') + (e?.message || t('qrcode.unknownError')))
  }
}
async function handleBatchDelete() {
  if (!selectedIds.value.length) return
  if (!confirm(t('qrcode.confirmBatchDeleteMsg', { count: selectedIds.value.length }))) return
  try {
    await store.deleteQrcodes([...selectedIds.value])
    selectedIds.value = []
  } catch (e) {
    alert(t('qrcode.batchDeleteFailed') + (e?.message || t('qrcode.unknownError')))
  }
}

// Bind dialog
const showBind = ref(false)
const bindTarget = ref(null)
const bindProductId = ref('')
const bindSkuId = ref('')
const bindMode = ref('single')  // 'single' | 'batch'
const bindBatchQuantity = ref(1)
const selectedProductSkus = ref([])
const detailQrcodeId = ref(null)
const previewImage = ref(null)

// 监听商品选择，加载该商品的 SKU
watch(bindProductId, async (newVal) => {
  bindSkuId.value = ''
  selectedProductSkus.value = []

  if (!newVal) return

  try {
    const res = await api.get(`/products/${newVal}/specs`)
    if (res.code === 0 && res.data?.skus?.length) {
      selectedProductSkus.value = res.data.skus
    }
  } catch (e) {
    console.error('加载SKU失败', e)
  }
})

function downloadQr(qr) {
  const a = document.createElement('a')
  a.href = qr.image_url
  a.download = `${qr.code}.png`
  a.click()
}

// ═══ 扫码销售弹窗 ═══
const showSell = ref(false)
const sellTarget = ref(null)
const sellQuantity = ref(1)
const sellBuyer = ref('')
const sellSalesPerson = ref('')
const sellLoading = ref(false)

function openSellDialog(qr) {
  sellTarget.value = qr
  // 批码默认 1，单码固定 1
  sellQuantity.value = 1
  sellBuyer.value = ''
  sellSalesPerson.value = userStore.user?.name || ''
  showSell.value = true
}

async function handleConfirmSell() {
  if (!sellTarget.value) return
  const qty = parseInt(sellQuantity.value)
  if (!qty || qty < 1) {
    alert('销售数量必须 >= 1')
    return
  }
  // 批码校验
  if (sellTarget.value.batch_mode === 'batch' && qty > (sellTarget.value.remaining_qty || 0)) {
    alert(`批码剩余 ${sellTarget.value.remaining_qty}，不够 ${qty}`)
    return
  }
  sellLoading.value = true
  const res = await store.sell(sellTarget.value.id, {
    quantity: qty,
    buyer: sellBuyer.value || null,
    sales_person: sellSalesPerson.value || null
  })
  sellLoading.value = false
  if (res.code === 0) {
    showSell.value = false
  } else {
    alert('销售失败：' + (res.message || '未知错误'))
  }
}

// ═══ 批码调整弹窗 ═══
const showAdjust = ref(false)
const adjustTarget = ref(null)
const adjustDelta = ref(0)
const adjustReason = ref('')
const adjustLoading = ref(false)

function openAdjustDialog(qr) {
  adjustTarget.value = qr
  adjustDelta.value = 0
  adjustReason.value = ''
  showAdjust.value = true
}

async function handleConfirmAdjust() {
  if (!adjustTarget.value) return
  const d = parseInt(adjustDelta.value)
  if (!d) {
    alert('调整数量不能为 0')
    return
  }
  adjustLoading.value = true
  const res = await store.adjustBatch(adjustTarget.value.id, d, adjustReason.value)
  adjustLoading.value = false
  if (res.code === 0) {
    showAdjust.value = false
  } else {
    alert('调整失败：' + (res.message || '未知错误'))
  }
}

function openBind(qr) {
  bindTarget.value = qr
  bindProductId.value = ''
  bindSkuId.value = ''
  bindMode.value = 'single'
  bindBatchQuantity.value = 1
  selectedProductSkus.value = []
  showBind.value = true
}

function handleBind() {
  if (!bindProductId.value || !bindTarget.value) return
  // 多规格商品必须选具体 SKU（和入库一样）
  if (selectedProductSkus.value.length > 0 && !bindSkuId.value) {
    alert(t('qrcode.selectSkuRequired') || '请选择具体 SKU 规格')
    return
  }
  // 批码模式必须有 SKU
  if (bindMode.value === 'batch') {
    if (!bindSkuId.value) {
      alert(t('qrcode.selectSkuRequired') || '请选择具体 SKU 规格')
      return
    }
    const qty = parseInt(bindBatchQuantity.value)
    if (!qty || qty < 1) {
      alert(t('qrcode.batchQuantityMin') || '批码数量必须 >= 1')
      return
    }
  }
  store.bindProduct(
    bindTarget.value.id,
    Number(bindProductId.value),
    bindSkuId.value ? Number(bindSkuId.value) : null,
    { mode: bindMode.value, batch_quantity: parseInt(bindBatchQuantity.value) }
  )
  showBind.value = false
}

function formatSkuLabel(sku) {
  const specs = typeof sku.specs === 'string' ? JSON.parse(sku.specs) : sku.specs
  const specStr = Object.entries(specs || {}).map(([k, v]) => `${k}:${v}`).join(' / ')
  return `${sku.sku} ${specStr ? `(${specStr})` : ''}`
}

function formatSkuSpecs(specs) {
  const obj = typeof specs === 'string' ? JSON.parse(specs) : specs
  return Object.entries(obj || {}).map(([k, v]) => `${k}:${v}`).join(' / ')
}

// Scan log tab
const scanSearch = ref('')
const filteredScans = computed(() => {
  if (!scanSearch.value) return store.scanLogs
  const q = scanSearch.value.toLowerCase()
  return store.scanLogs.filter(r => r.code.toLowerCase().includes(q) || r.scanner.toLowerCase().includes(q) || r.role.includes(q))
})

// After sale tab
const afterSaleStatusMap = computed(() => ({
  processing: { type: 'warning', text: t('qrcode.processingStatus') },
  resolved: { type: 'success', text: t('qrcode.resolvedStatus') },
  rejected: { type: 'danger', text: t('qrcode.rejectedStatus') },
}))

// ─── A4 Print ────────────────────────────────────────────────────────────────
const showPrint = ref(false)
const printLayout = ref(localStorage.getItem('caimeite_print_layout') || '12') // 默认12个/页，记住用户偏好
const printNotes = ref({}) // { qrId: 'custom note' }
const batchNoteText = ref('') // 批量填充的备注内容

// 监听布局变化，保存到localStorage
watch(printLayout, (newVal) => {
  localStorage.setItem('caimeite_print_layout', newVal)
})

const printList = computed(() =>
  selectedIds.value.length
    ? filteredQrcodes.value.filter(q => selectedIds.value.includes(q.id))
    : filteredQrcodes.value
)

// 预计打印页数
const estimatedPages = computed(() => {
  const total = printList.value.length
  const perPage = parseInt(printLayout.value)
  return Math.ceil(total / perPage)
})

const layoutConfig = computed(() => {
  const configs = {
    '1': { cols: 1, rows: 1, size: '150mm', fontSize: '14pt', gap: '0mm', label: t('qrcode.layoutXLShort'), showAll: true },
    '2': { cols: 1, rows: 2, size: '120mm', fontSize: '12pt', gap: '10mm', label: t('qrcode.layoutL2Short'), showAll: true },
    '4': { cols: 2, rows: 2, size: '80mm', fontSize: '10pt', gap: '10mm', label: t('qrcode.layoutL4Short'), showAll: true },
    '6': { cols: 2, rows: 3, size: '65mm', fontSize: '9pt', gap: '8mm', label: t('qrcode.layoutM6Short'), showAll: true },
    '8': { cols: 2, rows: 4, size: '55mm', fontSize: '8pt', gap: '8mm', label: t('qrcode.layoutM8Short'), showAll: true },
    '12': { cols: 3, rows: 4, size: '45mm', fontSize: '7pt', gap: '6mm', label: t('qrcode.layoutMS12Short'), showAll: true },
    '20': { cols: 4, rows: 5, size: '35mm', fontSize: '6pt', gap: '5mm', label: t('qrcode.layoutS20Short'), showAll: false },
    '24': { cols: 4, rows: 6, size: '32mm', fontSize: '6pt', gap: '4mm', label: t('qrcode.layoutSS24Short'), showAll: false },
    '35': { cols: 5, rows: 7, size: '26mm', fontSize: '5pt', gap: '3mm', label: t('qrcode.layoutMini35Short'), showAll: false },
    '48': { cols: 6, rows: 8, size: '22mm', fontSize: '5pt', gap: '3mm', label: t('qrcode.layoutMicro48Short'), showAll: false }
  }
  return configs[printLayout.value]
})

// 一键填充备注
function fillAllNotes() {
  if (!batchNoteText.value.trim()) {
    alert(t('qrcode.fillNoteEmpty'))
    return
  }
  printList.value.forEach(qr => {
    printNotes.value[qr.id] = batchNoteText.value
  })
}

// 清空所有备注
function clearAllNotes() {
  if (!confirm(t('qrcode.confirmClearNotes'))) return
  printNotes.value = {}
  batchNoteText.value = ''
}

function handlePrint() {
  const printWindow = window.open('', '_blank')
  if (!printWindow) {
    alert(t('qrcode.allowPopup'))
    return
  }

  const cfg = layoutConfig.value
  const qrItems = printList.value.map(qr => {
    const note = printNotes.value[qr.id] || ''

    // 根据布局大小决定显示内容
    const showProductName = cfg.showAll && qr.product_name
    const showNote = cfg.showAll && note

    // 动态字体大小
    const codeFontSize = cfg.fontSize
    const infoFontSize = parseInt(cfg.fontSize) >= 7 ? '6pt' : '5pt'

    return `
      <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; border:1px solid #e5e7eb; border-radius:4px; padding:3mm; box-sizing:border-box; break-inside:avoid; page-break-inside:avoid;">
        <img src="/uploads/qrcodes/${qr.code}.png" alt="${qr.code}" style="width:${cfg.size}; height:${cfg.size}; object-fit:contain; display:block;" />
        <p style="margin-top:2mm; font-size:${codeFontSize}; font-family:monospace; text-align:center; word-break:break-all; color:#374151;">${qr.code}</p>
        ${showProductName ? `<p style="margin-top:1mm; font-size:${infoFontSize}; text-align:center; color:#6b7280; word-break:break-all;">${qr.product_name}</p>` : ''}
        ${showNote ? `<p style="margin-top:1mm; font-size:${infoFontSize}; text-align:center; color:#374151; word-break:break-all;">${note}</p>` : ''}
      </div>
    `
  }).join('')

  printWindow.document.write(`<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>${t('qrcode.printTitle')}</title>
  <style>
    @page { size: A4; margin: 10mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, 'PingFang SC', 'Microsoft YaHei', sans-serif; }
  </style>
</head>
<body>
  <div style="display:grid; grid-template-columns:repeat(${cfg.cols}, 1fr); gap:${cfg.gap};">
    ${qrItems}
  </div>
</body>
</html>`)
  printWindow.document.close()

  // 等待图片加载完成后打印
  const images = printWindow.document.querySelectorAll('img')
  let loaded = 0
  const total = images.length

  function tryPrint() {
    loaded++
    if (loaded >= total) {
      setTimeout(() => {
        printWindow.print()
        printWindow.close()
      }, 200)
    }
  }

  if (total === 0) {
    printWindow.print()
    printWindow.close()
  } else {
    images.forEach(img => {
      if (img.complete) tryPrint()
      else {
        img.onload = tryPrint
        img.onerror = tryPrint
      }
    })
  }
}

// ─── Excel Export ─────────────────────────────────────────────────────────────
async function exportExcel() {
  const XLSX = await import('xlsx')
  const list = selectedIds.value.length
    ? filteredQrcodes.value.filter(q => selectedIds.value.includes(q.id))
    : filteredQrcodes.value
  const rows = list.map((q, i) => ({
    [t('qrcode.serialNo')]: i + 1,
    [t('qrcode.codeCol')]: q.code,
    [t('qrcode.imageUrlCol')]: `${window.location.origin}/uploads/qrcodes/${q.code}.png`,
    [t('qrcode.productNameCol')]: q.product_name || '',
    [t('qrcode.statusCol')]: q.status,
    [t('qrcode.generateTime')]: q.created_at?.slice(0, 16) || '',
  }))
  const ws = XLSX.utils.json_to_sheet(rows)
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, t('qrcode.qrListSheet'))
  XLSX.writeFile(wb, `qrcodes-${Date.now()}.xlsx`)
}

// ─── Batch Edit ───────────────────────────────────────────────────────────────
const showBatchEdit = ref(false)
const batchEditForm = ref({
  inbound_by: '',
  outbound_by: '',
  warehouse: '',
  supplier_id: '',
  dealer_id: '',
  store_id: '',
  sales_person: '',
  after_sale_contact: '',
})
const batchEditLoading = ref(false)

function openBatchEdit() {
  batchEditForm.value = {
    inbound_by: '',
    outbound_by: '',
    warehouse: '',
    supplier_id: '',
    dealer_id: '',
    store_id: '',
    sales_person: '',
    after_sale_contact: '',
  }
  showBatchEdit.value = true
}

async function handleBatchEdit() {
  if (!selectedIds.value.length) return
  // Build payload: only non-empty fields
  const payload = {}
  for (const [k, v] of Object.entries(batchEditForm.value)) {
    if (v !== '' && v !== null && v !== undefined) payload[k] = v
  }
  if (Object.keys(payload).length === 0) {
    alert(t('qrcode.fillAtLeastOne'))
    return
  }
  batchEditLoading.value = true
  try {
    const promises = selectedIds.value.map(id =>
      api.put(`/qrcodes/${id}`, payload).catch(() => null)
    )
    await Promise.all(promises)
    showBatchEdit.value = false
    await loadQrcodes()
  } catch (e) {
    alert(t('qrcode.batchEditFailed') + (e?.message || t('qrcode.unknownError')))
  } finally {
    batchEditLoading.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader :title="$t('qrcode.title')" :subtitle="$t('qrcode.subtitle')" />

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-6">
      <div v-for="(item, i) in [
        { label: $t('qrcode.totalCount'), value: store.stats.total, icon: 'qr_code_2', color: 'primary' },
        { label: $t('qrcode.unusedCount'), value: store.stats.unused, icon: 'hourglass_empty', color: 'info' },
        { label: $t('qrcode.boundCount'), value: store.stats.bound, icon: 'link', color: 'primary' },
        { label: $t('qrcode.inStockCount'), value: store.stats.inStock, icon: 'inventory_2', color: 'primary' },
        { label: $t('qrcode.outStockCount'), value: store.stats.outStock, icon: 'output', color: 'warning' },
        { label: $t('qrcode.shippedCount'), value: store.stats.shipped, icon: 'local_shipping', color: 'warning' },
        { label: $t('qrcode.soldCount'), value: store.stats.sold, icon: 'shopping_cart', color: 'success' },
        { label: $t('qrcode.activatedCount'), value: store.stats.activated, icon: 'verified', color: 'success' },
        { label: $t('qrcode.afterSaleCount'), value: store.stats.afterSale, icon: 'support_agent', color: 'danger' },
        { label: $t('qrcode.returnedCount'), value: store.stats.returned, icon: 'assignment_return', color: 'danger' },
        { label: $t('qrcode.totalScans'), value: store.stats.totalScans, icon: 'qr_code_scanner', color: 'primary' },
      ]" :key="i" class="bg-white rounded-lg border border-gray-100 shadow-card p-3">
        <div class="flex items-center gap-2">
          <div :class="[`size-8 rounded-lg bg-${item.color}/10 flex items-center justify-center`]">
            <span :class="[`material-symbols-outlined text-${item.color} text-[18px]`]">{{ item.icon }}</span>
          </div>
          <div>
            <p class="text-lg font-bold text-text-primary">{{ item.value }}</p>
            <p class="text-[10px] text-text-secondary">{{ item.label }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <div class="flex border-b border-gray-100 overflow-x-auto">
        <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
          :class="['flex items-center gap-2 px-6 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap',
            activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-text-secondary hover:text-text-primary']">
          <span class="material-symbols-outlined text-[18px]">{{ tab.icon }}</span>
          {{ tab.label }}
        </button>
      </div>

      <!-- QR Code Management Tab -->
      <div v-if="activeTab === 'qrcodes'">
        <div class="flex flex-wrap items-center gap-3 p-4 border-b border-gray-100">
          <div class="relative flex-1 min-w-[200px]">
            <span class="material-symbols-outlined text-[18px] text-text-secondary absolute left-3 top-1/2 -translate-y-1/2">search</span>
            <input v-model="qrSearch" type="text" :placeholder="$t('qrcode.searchPlaceholder')" class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <select v-model="qrStatusFilter" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
            <option value="">{{ $t('qrcode.allStatus') }}</option>
            <option v-for="(v, k) in store.qrcodeStatusMap" :key="k" :value="k">{{ v.label }}</option>
          </select>
          <select v-if="availableCategories.length" v-model="qrCategoryFilter" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
            <option value="">{{ $t('qrcode.allCategories') }}</option>
            <option v-for="cat in availableCategories" :key="cat" :value="cat">{{ cat }}</option>
          </select>
          <div class="flex items-center gap-1.5">
            <input v-model="qrDateStart" type="date" class="border border-gray-200 rounded-lg px-2 py-2 text-xs focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" :title="$t('common.startDate')" />
            <span class="text-text-secondary text-xs">{{ $t('qrcode.dateRangeTo') }}</span>
            <input v-model="qrDateEnd" type="date" class="border border-gray-200 rounded-lg px-2 py-2 text-xs focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" :title="$t('common.endDate')" />
          </div>

          <!-- Batch Edit button (when items selected) -->
          <button v-if="selectedIds.length > 0" @click="openBatchEdit"
            class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">edit_note</span>
            {{ $t('qrcode.batchEdit') }} ({{ selectedIds.length }})
          </button>

          <!-- Batch Delete button -->
          <button v-if="canDelete && selectedIds.length > 0" @click="handleBatchDelete"
            class="flex items-center gap-2 bg-danger hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">delete</span>
            {{ $t('qrcode.batchDelete') }} ({{ selectedIds.length }})
          </button>

          <!-- Print button -->
          <button @click="showPrint = true"
            class="flex items-center gap-2 bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">print</span>
            {{ $t('qrcode.print') }}{{ selectedIds.length > 0 ? ` (${selectedIds.length})` : '' }}
          </button>

          <!-- Export Excel button -->
          <button @click="exportExcel"
            class="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">table_view</span>
            {{ $t('qrcode.exportExcelBtn') }}{{ selectedIds.length > 0 ? ` (${selectedIds.length})` : '' }}
          </button>

          <!-- 决策 3: 盘点 -->
          <button @click="openStocktake"
            class="flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">inventory_2</span>
            {{ $t('qrcode.stocktake') }}
          </button>

          <!-- 决策 5: 重新绑定 -->
          <button v-if="selectedIds.length === 1" @click="openRebind"
            class="flex items-center gap-2 bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">restart_alt</span>
            {{ $t('qrcode.rebind') }}
          </button>

          <button @click="showGenerate = true" class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">add</span>
            {{ $t('qrcode.batchGenerate') }}
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
              <tr>
                <th v-if="canSelect" class="px-4 py-3 w-10">
                  <input type="checkbox" :checked="allSelected" :indeterminate="someSelected"
                    @change="toggleSelectAll" class="rounded border-gray-300 text-primary cursor-pointer" />
                </th>
                <th class="px-4 py-3 font-medium">{{ $t('qrcode.codeNumber') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('qrcode.productSku') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('qrcode.warehouse') }}</th>
                <th class="px-4 py-3 font-medium text-center">{{ $t('qrcode.scanCount') }}</th>
                <th class="px-4 py-3 font-medium text-center">{{ $t('common.status') }}</th>
                <th class="px-4 py-3 font-medium text-center">{{ $t('qrcode.batchMode') || '模式' }}</th>
                <th class="px-4 py-3 font-medium text-center">{{ $t('qrcode.remaining') || '剩余' }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('common.createdAt') }}</th>
                <th class="px-4 py-3 font-medium text-right">{{ $t('common.action') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="qr in filteredQrcodes" :key="qr.id"
                :class="['hover:bg-gray-50 transition-colors', selectedIds.includes(qr.id) ? 'bg-primary/5' : '']">
                <td class="px-4 py-3">
                  <input type="checkbox" :checked="selectedIds.includes(qr.id)"
                    @click="toggleRow(!selectedIds.includes(qr.id), qr.id)" class="rounded border-gray-300 text-primary cursor-pointer" />
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <img v-if="qr.image_url" :src="qr.image_url" @click="previewImage = qr" class="w-10 h-10 rounded cursor-pointer border border-gray-100 hover:border-primary transition-colors" :alt="qr.code" />
                    <span v-else class="material-symbols-outlined text-primary text-[18px]">qr_code_2</span>
                    <span class="font-mono text-xs text-primary font-medium">{{ qr.code }}</span>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <template v-if="qr.product_name">
                    <p class="text-text-primary text-sm font-medium">{{ qr.product_name }}</p>
                    <p class="text-text-secondary text-xs font-mono">{{ qr.sku }}</p>
                    <div v-if="qr.sku_code" class="text-xs text-primary mt-0.5">
                      SKU: {{ qr.sku_code }}
                      <span v-if="qr.sku_specs" class="ml-1 text-text-secondary">
                        ({{ formatSkuSpecs(qr.sku_specs) }})
                      </span>
                    </div>
                  </template>
                  <span v-else class="text-text-secondary text-xs">{{ $t('qrcode.unbound') }}</span>
                </td>
                <td class="px-4 py-3 text-text-secondary text-xs">{{ qr.warehouse || '-' }}</td>
                <td class="px-4 py-3 text-center font-medium">{{ qr.scan_count }}</td>
                <td class="px-4 py-3 text-center"><StatusTag :type="store.qrcodeStatusMap[qr.status]?.type" :text="store.qrcodeStatusMap[qr.status]?.label" /></td>
                <td class="px-4 py-3 text-center text-xs">
                  <span v-if="qr.batch_mode === 'batch'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-purple-50 text-purple-700 font-medium">
                    <span class="material-symbols-outlined text-[12px]">inventory_2</span>
                    批 {{ qr.batch_quantity }}
                  </span>
                  <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-medium">
                    <span class="material-symbols-outlined text-[12px]">looks_one</span>
                    单
                  </span>
                </td>
                <td class="px-4 py-3 text-center text-xs">
                  <span v-if="qr.batch_mode === 'batch'">
                    <span :class="(qr.remaining_qty || 0) === 0 ? 'text-red-500 font-medium' : (qr.remaining_qty || 0) <= 5 ? 'text-orange-500 font-medium' : 'text-text-primary'">
                      {{ qr.remaining_qty ?? '-' }} / {{ qr.batch_quantity }}
                    </span>
                  </span>
                  <span v-else class="text-text-secondary">-</span>
                </td>
                <td class="px-4 py-3 text-text-secondary text-xs">{{ qr.created_at }}</td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button v-if="qr.status === 'unused'" @click="openBind(qr)" class="text-primary hover:text-primary-hover text-xs font-medium">{{ $t('qrcode.bindProduct') }}</button>
                    <button v-if="qr.status === 'bindProduct'" @click="store.updateStatus(qr.id, 'inStock')" class="text-primary hover:text-primary-hover text-xs font-medium">{{ $t('qrcode.confirmInStock') }}</button>
                    <button v-if="qr.status === 'inStock'" @click="store.updateStatus(qr.id, 'outStock')" class="text-warning hover:text-warning text-xs font-medium">{{ $t('qrcode.confirmOutStock') }}</button>
                    <button v-if="qr.status === 'outStock'" @click="store.updateStatus(qr.id, 'shipped')" class="text-warning hover:text-warning text-xs font-medium">{{ $t('qrcode.confirmShipped') }}</button>
                    <!-- 批码扫码销售：单码可以走原 shipped→sold，批码走 sell 接口扣减 -->
                    <button v-if="['inStock', 'shipped', 'bindProduct'].includes(qr.status)" @click="openSellDialog(qr)" class="text-success hover:text-success text-xs font-medium">
                      <span class="material-symbols-outlined text-[14px] align-middle">shopping_cart_checkout</span>
                      {{ $t('qrcode.scanSell') || '扫码销售' }}
                    </button>
                    <!-- 批码：调整个数 -->
                    <button v-if="qr.batch_mode === 'batch' && ['inStock', 'shipped', 'sold', 'sold_out'].includes(qr.status)" @click="openAdjustDialog(qr)" class="text-primary hover:text-primary-hover text-xs font-medium">
                      <span class="material-symbols-outlined text-[14px] align-middle">tune</span>
                      {{ $t('qrcode.adjustBatch') || '调整个数' }}
                    </button>
                    <button v-if="qr.status === 'returned'" @click="store.updateStatus(qr.id, 'inStock')" class="text-primary hover:text-primary-hover text-xs font-medium">{{ $t('qrcode.returnToInStock') }}</button>
                    <!-- Undo / revert buttons -->
                    <button v-if="qr.status === 'bindProduct'" @click="store.updateStatus(qr.id, 'unused')" class="text-text-secondary hover:text-text-primary text-xs" :title="$t('qrcode.revertToUnused')">
                      <span class="material-symbols-outlined text-[15px]">link_off</span>
                    </button>
                    <button v-if="qr.status === 'inStock'" @click="store.updateStatus(qr.id, 'bindProduct')" class="text-text-secondary hover:text-warning text-xs" :title="$t('qrcode.revertToBind')">
                      <span class="material-symbols-outlined text-[15px]">undo</span>
                    </button>
                    <button v-if="qr.status === 'outStock'" @click="store.updateStatus(qr.id, 'inStock')" class="text-text-secondary hover:text-warning text-xs" :title="$t('qrcode.revertToInStock')">
                      <span class="material-symbols-outlined text-[15px]">undo</span>
                    </button>
                    <button v-if="qr.image_url" @click="downloadQr(qr)" class="text-text-secondary hover:text-text-primary text-xs" :title="$t('common.download')">
                      <span class="material-symbols-outlined text-[16px]">download</span>
                    </button>
                    <button @click="detailQrcodeId = qr.id" class="text-text-secondary hover:text-text-primary text-xs">{{ $t('qrcode.detail') }}</button>
                    <button v-if="canDelete" @click="handleDelete(qr)"
                      class="text-danger hover:text-red-600 text-xs" :title="$t('common.delete')">
                      <span class="material-symbols-outlined text-[16px]">delete</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100">
          <Pagination :total="store.qrTotal" :page="qrPage" :pageSize="qrPageSize" @update:page="qrPage = $event" />
        </div>
      </div>

      <!-- Scan Logs Tab -->
      <div v-if="activeTab === 'scan'">
        <div class="p-4 border-b border-gray-100">
          <div class="relative max-w-md">
            <span class="material-symbols-outlined text-[18px] text-text-secondary absolute left-3 top-1/2 -translate-y-1/2">search</span>
            <input v-model="scanSearch" type="text" :placeholder="$t('qrcode.searchPlaceholder')" class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
              <tr>
                <th class="px-4 py-3 font-medium">{{ $t('qrcode.codeNumber') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('qrcode.scannerName') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('qrcode.role') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('qrcode.scanAction') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('qrcode.scanTime') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('qrcode.scanLocation') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="log in filteredScans" :key="log.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 font-mono text-xs text-primary font-medium">{{ log.code }}</td>
                <td class="px-4 py-3 text-text-primary font-medium">{{ log.scanner }}</td>
                <td class="px-4 py-3"><StatusTag :type="log.role === '管理员' ? 'danger' : log.role === '购买者' ? 'success' : log.role === '普通用户' ? 'info' : 'primary'" :text="log.role" /></td>
                <td class="px-4 py-3 text-text-secondary">{{ log.action }}</td>
                <td class="px-4 py-3 text-text-secondary text-xs">{{ log.time }}</td>
                <td class="px-4 py-3 text-text-secondary text-xs">{{ log.location }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- After Sale Tab -->
      <div v-if="activeTab === 'afterSale'" class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">{{ $t('qrcode.codeNumber') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('qrcode.productSku') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('qrcode.buyer') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('qrcode.issueDesc') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('qrcode.handlerCol') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('qrcode.handlerNote') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('common.status') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('qrcode.submitTime') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="r in store.afterSaleRecords" :key="r.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 font-mono text-xs text-primary font-medium">{{ r.code }}</td>
              <td class="px-4 py-3 text-text-primary">{{ r.product_name }}</td>
              <td class="px-4 py-3 text-text-primary">{{ r.buyer }}</td>
              <td class="px-4 py-3 text-text-secondary text-xs max-w-[200px] truncate">{{ r.issue }}</td>
              <td class="px-4 py-3 text-text-primary">{{ r.handler }}</td>
              <td class="px-4 py-3 text-text-secondary text-xs max-w-[200px] truncate">{{ r.handler_note }}</td>
              <td class="px-4 py-3 text-center"><StatusTag :type="afterSaleStatusMap[r.status]?.type" :text="afterSaleStatusMap[r.status]?.text" /></td>
              <td class="px-4 py-3 text-text-secondary text-xs">{{ r.created_at }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Hierarchy & Commission Tab -->
      <div v-if="activeTab === 'hierarchy'" class="p-6">
        <!-- Commission Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div class="border border-gray-100 rounded-lg p-4">
            <p class="text-xs text-text-secondary mb-1">{{ $t('qrcode.commissionTotal') }}</p>
            <p class="text-2xl font-bold text-primary">¥{{ store.totalCommission.toFixed(2) }}</p>
          </div>
          <div class="border border-gray-100 rounded-lg p-4">
            <p class="text-xs text-text-secondary mb-1">{{ $t('qrcode.commissionPending') }}</p>
            <p class="text-2xl font-bold text-warning">¥{{ store.pendingCommission.toFixed(2) }}</p>
          </div>
          <div class="border border-gray-100 rounded-lg p-4">
            <p class="text-xs text-text-secondary mb-1">{{ $t('qrcode.commissionSettled') }}</p>
            <p class="text-2xl font-bold text-success">¥{{ (store.totalCommission - store.pendingCommission).toFixed(2) }}</p>
          </div>
        </div>

        <!-- User Hierarchy -->
        <h4 class="font-bold text-text-primary mb-3 flex items-center gap-2">
          <span class="material-symbols-outlined text-primary text-[18px]">account_tree</span>
          {{ $t('qrcode.userHierarchy') }}
        </h4>
        <div class="overflow-x-auto mb-6">
          <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
              <tr>
                <th class="px-4 py-3 font-medium">{{ $t('qrcode.userCol') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('qrcode.userRole') }}</th>
                <th class="px-4 py-3 font-medium text-center">{{ $t('qrcode.level') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('qrcode.parentCol') }}</th>
                <th class="px-4 py-3 font-medium text-right">{{ $t('qrcode.totalCommission') }}</th>
                <th class="px-4 py-3 font-medium text-right">{{ $t('qrcode.totalPoints') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="u in store.userHierarchy" :key="u.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <div class="size-7 rounded-full bg-primary flex items-center justify-center text-white text-xs font-bold">{{ u.name.charAt(0) }}</div>
                    <span class="font-medium text-text-primary">{{ u.name }}</span>
                  </div>
                </td>
                <td class="px-4 py-3"><StatusTag :type="u.role === 'VIP' ? 'warning' : u.role === '销售员' ? 'primary' : 'info'" :text="u.role" /></td>
                <td class="px-4 py-3 text-center">
                  <span :class="['inline-flex items-center justify-center size-6 rounded-full text-xs font-bold', u.level === 0 ? 'bg-warning/10 text-warning' : 'bg-gray-100 text-text-secondary']">{{ u.level }}</span>
                </td>
                <td class="px-4 py-3 text-text-secondary">{{ u.parent || '—' }}</td>
                <td class="px-4 py-3 text-right font-medium" :class="u.total_commission > 0 ? 'text-primary' : 'text-text-secondary'">¥{{ u.total_commission.toFixed(2) }}</td>
                <td class="px-4 py-3 text-right text-text-primary">{{ u.total_points }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Commission Records -->
        <h4 class="font-bold text-text-primary mb-3 flex items-center gap-2">
          <span class="material-symbols-outlined text-primary text-[18px]">payments</span>
          {{ $t('qrcode.commissionRecords') }}
        </h4>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
              <tr>
                <th class="px-4 py-3 font-medium">{{ $t('qrcode.codeNumber') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('qrcode.productSku') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('qrcode.buyer') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('qrcode.beneficiary') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('qrcode.commType') }}</th>
                <th class="px-4 py-3 font-medium text-right">{{ $t('qrcode.commAmount') }}</th>
                <th class="px-4 py-3 font-medium text-center">{{ $t('common.status') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('common.createdAt') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="c in store.commissionRecords" :key="c.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 font-mono text-xs text-primary">{{ c.qrcode_code }}</td>
                <td class="px-4 py-3 text-text-primary">{{ c.product_name }}</td>
                <td class="px-4 py-3 text-text-primary">{{ c.buyer }}</td>
                <td class="px-4 py-3 font-medium text-text-primary">{{ c.beneficiary }}</td>
                <td class="px-4 py-3"><StatusTag :type="c.type === '层级佣金' ? 'warning' : 'primary'" :text="c.type" /></td>
                <td class="px-4 py-3 text-right font-bold text-primary">¥{{ c.amount.toFixed(2) }}</td>
                <td class="px-4 py-3 text-center"><StatusTag :type="c.status === 'settled' ? 'success' : 'warning'" :text="c.status === 'settled' ? $t('qrcode.commissionSettled') : $t('qrcode.commissionPending')" /></td>
                <td class="px-4 py-3 text-text-secondary text-xs">{{ c.created_at }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Generate Dialog -->
    <div v-if="showGenerate" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showGenerate = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="font-bold text-text-primary text-lg mb-4 flex items-center gap-2">
          <span class="material-symbols-outlined text-primary">qr_code_2</span>
          {{ $t('qrcode.generateDialog') }}
        </h3>
        <div class="mb-4">
          <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('qrcode.generateCountLabel') }}</label>
          <input v-model.number="generateCount" type="number" min="1" max="1000" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          <p class="text-xs text-text-secondary mt-1">{{ $t('qrcode.generateHint') }}</p>
        </div>
        <div class="flex justify-end gap-3">
          <button @click="showGenerate = false" class="px-4 py-2 rounded-lg text-sm font-medium text-text-secondary hover:bg-gray-100 transition-colors">{{ $t('common.cancel') }}</button>
          <button @click="handleGenerate" class="px-4 py-2 rounded-lg text-sm font-medium bg-primary hover:bg-primary-hover text-white transition-colors">{{ $t('qrcode.confirmGenerate') }}</button>
        </div>
      </div>
    </div>

    <!-- Bind Dialog -->
    <div v-if="showBind" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showBind = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="font-bold text-text-primary text-lg mb-4 flex items-center gap-2">
          <span class="material-symbols-outlined text-primary">link</span>
          {{ $t('qrcode.bindDialog') }}
        </h3>
        <div class="mb-3 p-3 bg-gray-50 rounded-lg">
          <p class="text-xs text-text-secondary">{{ $t('qrcode.codeNumber') }}</p>
          <p class="font-mono text-sm text-primary font-medium">{{ bindTarget?.code }}</p>
        </div>

        <!-- 绑定模式：单个 / 一批 -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('qrcode.bindMode') || '绑定模式' }}</label>
          <div class="flex gap-2">
            <button
              type="button"
              @click="bindMode = 'single'"
              :class="['flex-1 py-2 px-3 rounded-lg text-sm font-medium transition-colors border',
                bindMode === 'single' ? 'bg-primary text-white border-primary' : 'bg-white text-text-secondary border-gray-200 hover:border-primary']"
            >
              <span class="material-symbols-outlined text-base align-middle">looks_one</span>
              {{ $t('qrcode.bindSingle') || '绑单个' }}
            </button>
            <button
              type="button"
              @click="bindMode = 'batch'"
              :class="['flex-1 py-2 px-3 rounded-lg text-sm font-medium transition-colors border',
                bindMode === 'batch' ? 'bg-primary text-white border-primary' : 'bg-white text-text-secondary border-gray-200 hover:border-primary']"
            >
              <span class="material-symbols-outlined text-base align-middle">inventory_2</span>
              {{ $t('qrcode.bindBatch') || '绑一批' }}
            </button>
          </div>
          <p class="text-xs text-text-secondary mt-1">
            <span v-if="bindMode === 'single'">1 个二维码对应 1 件实物</span>
            <span v-else>1 个二维码代表该 SKU 的一批货，可单件多次销售</span>
          </p>
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('qrcode.productSku') }}</label>
          <select v-model="bindProductId" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
            <option value="">{{ $t('qrcode.selectProductPlaceholder') }}</option>
            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
          </select>
        </div>
        <!-- SKU 选择（仅当商品有多规格时显示，多规格必选具体 SKU） -->
        <div v-if="selectedProductSkus.length > 0" class="mb-4">
          <label class="block text-sm font-medium text-text-primary mb-1">
            {{ $t('qrcode.selectSpec') }} <span class="text-red-500">*</span>
          </label>
          <select v-model="bindSkuId" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
            <option value="" disabled>{{ $t('qrcode.selectSpecPlaceholder') }}</option>
            <option v-for="sku in selectedProductSkus" :key="sku.id" :value="sku.id">
              {{ formatSkuLabel(sku) }}
            </option>
          </select>
        </div>
        <!-- 批码数量输入 -->
        <div v-if="bindMode === 'batch' && bindSkuId" class="mb-4">
          <label class="block text-sm font-medium text-text-primary mb-1">
            {{ $t('qrcode.batchQuantity') || '批码数量' }} <span class="text-red-500">*</span>
          </label>
          <input
            v-model.number="bindBatchQuantity"
            type="number"
            min="1"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
            :placeholder="$t('qrcode.batchQuantityPlaceholder') || '例如 50'"
          />
          <p class="text-xs text-text-secondary mt-1">这批货实际有多少件，后续扫码销售时扣减</p>
        </div>
        <div class="flex justify-end gap-3">
          <button @click="showBind = false" class="px-4 py-2 rounded-lg text-sm font-medium text-text-secondary hover:bg-gray-100 transition-colors">{{ $t('common.cancel') }}</button>
          <button @click="handleBind" :disabled="!bindProductId" :class="['px-4 py-2 rounded-lg text-sm font-medium transition-colors', bindProductId ? 'bg-primary hover:bg-primary-hover text-white' : 'bg-gray-100 text-text-secondary cursor-not-allowed']">{{ $t('qrcode.confirmBind') }}</button>
        </div>
      </div>
    </div>

    <!-- Sell Dialog -->
    <div v-if="showSell" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showSell = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="font-bold text-text-primary text-lg mb-4 flex items-center gap-2">
          <span class="material-symbols-outlined text-success">shopping_cart_checkout</span>
          {{ $t('qrcode.scanSell') || '扫码销售' }}
        </h3>
        <div class="mb-3 p-3 bg-gray-50 rounded-lg space-y-1">
          <p class="text-xs text-text-secondary">{{ $t('qrcode.codeNumber') }}: <span class="font-mono text-primary">{{ sellTarget?.code }}</span></p>
          <p v-if="sellTarget?.product_name" class="text-xs text-text-secondary">{{ $t('qrcode.productSku') }}: <span class="text-text-primary">{{ sellTarget?.product_name }}</span></p>
          <p v-if="sellTarget?.batch_mode === 'batch'" class="text-xs text-text-secondary">
            剩余可售: <span class="text-primary font-medium">{{ sellTarget?.remaining_qty }} / {{ sellTarget?.batch_quantity }}</span>
          </p>
        </div>
        <!-- 批码可调数量，单码固定 1 -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-text-primary mb-1">销售数量 <span class="text-red-500">*</span></label>
          <input
            v-model.number="sellQuantity"
            type="number"
            min="1"
            :max="sellTarget?.batch_mode === 'batch' ? sellTarget?.remaining_qty : 1"
            :disabled="sellTarget?.batch_mode === 'single'"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none disabled:bg-gray-50 disabled:text-text-secondary"
          />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-text-primary mb-1">购买人</label>
          <input v-model="sellBuyer" type="text" placeholder="选填"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-text-primary mb-1">销售员</label>
          <input v-model="sellSalesPerson" type="text"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        </div>
        <div class="flex justify-end gap-3">
          <button @click="showSell = false" class="px-4 py-2 rounded-lg text-sm font-medium text-text-secondary hover:bg-gray-100 transition-colors">{{ $t('common.cancel') }}</button>
          <button @click="handleConfirmSell" :disabled="sellLoading" class="px-4 py-2 rounded-lg text-sm font-medium bg-success text-white hover:bg-success-hover transition-colors disabled:opacity-50">
            {{ sellLoading ? '处理中...' : '确认销售' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Adjust Batch Dialog -->
    <div v-if="showAdjust" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showAdjust = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="font-bold text-text-primary text-lg mb-4 flex items-center gap-2">
          <span class="material-symbols-outlined text-primary">tune</span>
          {{ $t('qrcode.adjustBatch') || '调整个数' }}
        </h3>
        <div class="mb-3 p-3 bg-gray-50 rounded-lg space-y-1">
          <p class="text-xs text-text-secondary">{{ $t('qrcode.codeNumber') }}: <span class="font-mono text-primary">{{ adjustTarget?.code }}</span></p>
          <p class="text-xs text-text-secondary">
            当前剩余: <span class="text-text-primary font-medium">{{ adjustTarget?.remaining_qty }} / {{ adjustTarget?.batch_quantity }}</span>
          </p>
          <p class="text-xs text-text-secondary">当前状态: <span class="text-text-primary font-medium">{{ adjustTarget?.status }}</span></p>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-text-primary mb-1">
            调整数量 <span class="text-red-500">*</span>
            <span class="text-xs text-text-secondary ml-2">(正数=增加, 负数=减少)</span>
          </label>
          <input
            v-model.number="adjustDelta"
            type="number"
            :min="-(adjustTarget?.remaining_qty || 0)"
            :max="(adjustTarget?.batch_quantity || 0) - (adjustTarget?.remaining_qty || 0)"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
          />
          <p class="text-xs text-text-secondary mt-1">
            调整后剩余: <span class="text-primary font-medium">{{ (adjustTarget?.remaining_qty || 0) + (parseInt(adjustDelta) || 0) }}</span>
          </p>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-text-primary mb-1">调整原因</label>
          <input v-model="adjustReason" type="text" placeholder="例如：退货回库 / 实物盘点差异"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        </div>
        <div class="flex justify-end gap-3">
          <button @click="showAdjust = false" class="px-4 py-2 rounded-lg text-sm font-medium text-text-secondary hover:bg-gray-100 transition-colors">{{ $t('common.cancel') }}</button>
          <button @click="handleConfirmAdjust" :disabled="adjustLoading" class="px-4 py-2 rounded-lg text-sm font-medium bg-primary text-white hover:bg-primary-hover transition-colors disabled:opacity-50">
            {{ adjustLoading ? '处理中...' : '确认调整' }}
          </button>
        </div>
      </div>
    </div>

    <!-- QR Code Detail Modal -->
    <QrcodeDetailModal
      :qrcode-id="detailQrcodeId"
      @close="detailQrcodeId = null"
      @updated="loadQrcodes()"
    />

    <!-- QR Image Zoom Preview -->
    <div v-if="previewImage" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" @click="previewImage = null">
      <div class="bg-white rounded-xl shadow-2xl p-6 flex flex-col items-center gap-4" @click.stop>
        <img :src="previewImage.image_url" class="w-48 h-48 rounded" :alt="previewImage.code" />
        <p class="font-mono text-sm text-primary font-medium">{{ previewImage.code }}</p>
        <div class="flex gap-3">
          <button @click="downloadQr(previewImage); previewImage = null" class="flex items-center gap-1 px-4 py-2 bg-primary text-white rounded-lg text-sm">
            <span class="material-symbols-outlined text-[16px]">download</span>{{ $t('common.download') }}
          </button>
          <button @click="previewImage = null" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-text-secondary hover:bg-gray-50">{{ $t('common.close') }}</button>
        </div>
      </div>
    </div>

    <!-- A4 Print Preview Modal -->
    <div v-if="showPrint" class="fixed inset-0 bg-black/60 flex items-start justify-center z-50 overflow-auto py-6" @click.self="showPrint = false">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl mx-4">
        <!-- Modal header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-100">
          <h3 class="font-bold text-text-primary text-lg flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">print</span>
            {{ $t('qrcode.printPreview') }}
            <span class="text-sm font-normal text-text-secondary ml-2">{{ $t('qrcode.totalQrcodesCount', { count: printList.length }) }} · {{ $t('qrcode.estimatedPages', { count: estimatedPages }) }}</span>
          </h3>
          <div class="flex items-center gap-3">
            <!-- Layout selector - 改为下拉选择 -->
            <div class="flex items-center gap-2">
              <label class="text-sm text-text-secondary">{{ $t('qrcode.printLayout') }}</label>
              <select v-model="printLayout" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                <option value="1">{{ $t('qrcode.layoutXL') }}</option>
                <option value="2">{{ $t('qrcode.layoutL2') }}</option>
                <option value="4">{{ $t('qrcode.layoutL4') }}</option>
                <option value="6">{{ $t('qrcode.layoutM6') }}</option>
                <option value="8">{{ $t('qrcode.layoutM8') }}</option>
                <option value="12">{{ $t('qrcode.layoutMS12') }}</option>
                <option value="20">{{ $t('qrcode.layoutS20') }}</option>
                <option value="24">{{ $t('qrcode.layoutSS24') }}</option>
                <option value="35">{{ $t('qrcode.layoutMini35') }}</option>
                <option value="48">{{ $t('qrcode.layoutMicro48') }}</option>
              </select>
            </div>
            <button @click="handlePrint" class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
              <span class="material-symbols-outlined text-[18px]">print</span>
              {{ $t('qrcode.print') }}
            </button>
            <button @click="showPrint = false" class="px-4 py-2 border bgray-200 rounded-lg text-sm text-text-secondary hover:bg-gray-50">{{ $t('common.close') }}</button>
          </div>
        </div>

        <!-- 一键填充备注区域 -->
        <div class="p-4 bg-blue-50 border-b border-blue-100">
          <div class="flex items-center gap-3">
            <label class="text-sm font-medium text-text-primary whitespace-nowrap">{{ $t('qrcode.batchNote') }}</label>
            <input
              v-model="batchNoteText"
              type="text"
              :placeholder="$t('qrcode.batchNotePlaceholder')"
              class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
            />
            <button
              @click="fillAllNotes"
              class="flex items-center gap-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition-colors whitespace-nowrap"
            >
              <span class="material-symbols-outlined text-[18px]">content_copy</span>
              {{ $t('qrcode.fillAll') }}
            </button>
            <button
              @click="clearAllNotes"
              class="flex items-center gap-1 px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg text-sm font-medium transition-colors whitespace-nowrap"
            >
              <span class="material-symbols-outlined text-[18px]">clear_all</span>
              {{ $t('qrcode.clearAll') }}
            </button>
          </div>
          <p class="text-xs text-blue-600 mt-2">
            {{ $t('qrcode.batchNoteTip') }}
          </p>
        </div>

        <!-- A4 page preview -->
        <div class="p-4 bg-gray-100 max-h-[70vh] overflow-auto">
          <div id="print-area" class="bg-white" style="width:210mm; min-height:297mm; margin:0 auto; box-sizing:border-box; padding:10mm;">
            <div :style="{
              display: 'grid',
              gridTemplateColumns: `repeat(${layoutConfig.cols}, 1fr)`,
              gap: layoutConfig.gap
            }">
              <div
                v-for="qr in printList"
                :key="qr.id"
                :style="{
                  display: 'flex',
                  flexDirection: 'column',
                  alignItems: 'center',
                  justifyContent: 'center',
                  border: '1px solid #e5e7eb',
                  borderRadius: '4px',
                  padding: '3mm',
                  boxSizing: 'border-box',
                  breakInside: 'avoid',
                  pageBreakInside: 'avoid'
                }"
              >
                <!-- QR Code Image -->
                <img
                  :src="`/uploads/qrcodes/${qr.code}.png`"
                  :alt="qr.code"
                  :style="{
                    width: layoutConfig.size,
                    height: layoutConfig.size,
                    objectFit: 'contain',
                    display: 'block'
                  }"
                />

                <!-- QR Code -->
                <p :style="{
                  marginTop: '2mm',
                  fontSize: layoutConfig.fontSize,
                  fontFamily: 'monospace',
                  textAlign: 'center',
                  wordBreak: 'break-all',
                  color: '#374151'
                }">{{ qr.code }}</p>

                <!-- Product Name -->
                <p v-if="qr.product_name" :style="{
                  marginTop: '1mm',
                  fontSize: printLayout === '36' ? '5pt' : '6pt',
                  textAlign: 'center',
                  color: '#6b7280',
                  wordBreak: 'break-all'
                }">{{ qr.product_name }}</p>

                <!-- Custom Note Input (only in preview) -->
                <input
                  v-model="printNotes[qr.id]"
                  type="text"
                  :placeholder="$t('qrcode.notePlaceholder')"
                  class="w-full mt-1 px-2 py-1 text-xs border border-gray-200 rounded focus:border-primary focus:outline-none"
                  :style="{ fontSize: printLayout === '36' ? '10px' : '11px' }"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Batch Edit Modal -->
    <div v-if="showBatchEdit" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showBatchEdit = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
          <h3 class="font-bold text-text-primary text-lg flex items-center gap-2">
            <span class="material-symbols-outlined text-indigo-600">edit_note</span>
            {{ $t('qrcode.batchEditTitle') }}
            <span class="text-sm font-normal text-text-secondary ml-1">{{ $t('qrcode.selectedCount', { count: selectedIds.length }) }}</span>
          </h3>
          <button @click="showBatchEdit = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-5 space-y-4">
          <p class="text-xs text-text-secondary bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
            {{ $t('qrcode.batchEditHint') }}
          </p>

          <!-- Warehouse fields -->
          <div class="border border-gray-100 rounded-lg p-4 space-y-3">
            <p class="text-xs font-semibold text-text-secondary uppercase tracking-wide">{{ $t('qrcode.warehouseInfo') }}</p>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('qrcode.inboundByLabel') }}</label>
              <input v-model="batchEditForm.inbound_by" type="text" :placeholder="$t('qrcode.leaveEmptyNoChange')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('qrcode.outboundByLabel') }}</label>
              <input v-model="batchEditForm.outbound_by" type="text" :placeholder="$t('qrcode.leaveEmptyNoChange')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('qrcode.warehouseField') }}</label>
              <input v-model="batchEditForm.warehouse" type="text" :placeholder="$t('qrcode.leaveEmptyNoChange')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
          </div>

          <!-- Supply chain fields -->
          <div class="border border-gray-100 rounded-lg p-4 space-y-3">
            <p class="text-xs font-semibold text-text-secondary uppercase tracking-wide">{{ $t('qrcode.supplyChainInfo') }}</p>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('qrcode.supplierIdLabel') }}</label>
              <input v-model="batchEditForm.supplier_id" type="text" :placeholder="$t('qrcode.leaveEmptyNoChange')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('qrcode.dealerIdLabel') }}</label>
              <input v-model="batchEditForm.dealer_id" type="text" :placeholder="$t('qrcode.leaveEmptyNoChange')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
          </div>

          <!-- Store / sales fields -->
          <div class="border border-gray-100 rounded-lg p-4 space-y-3">
            <p class="text-xs font-semibold text-text-secondary uppercase tracking-wide">{{ $t('qrcode.storeSalesInfo') }}</p>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('qrcode.storeIdLabel') }}</label>
              <input v-model="batchEditForm.store_id" type="text" :placeholder="$t('qrcode.leaveEmptyNoChange')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('qrcode.salesPersonLabel') }}</label>
              <input v-model="batchEditForm.sales_person" type="text" :placeholder="$t('qrcode.leaveEmptyNoChange')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
          </div>

          <!-- After-sale fields -->
          <div class="border border-gray-100 rounded-lg p-4 space-y-3">
            <p class="text-xs font-semibold text-text-secondary uppercase tracking-wide">{{ $t('qrcode.afterSaleInfo') }}</p>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('qrcode.afterSaleContactLabel') }}</label>
              <input v-model="batchEditForm.after_sale_contact" type="text" :placeholder="$t('qrcode.leaveEmptyNoChange')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-3 p-5 border-t border-gray-100">
          <button @click="showBatchEdit = false" class="px-4 py-2 rounded-lg text-sm font-medium text-text-secondary hover:bg-gray-100 transition-colors">{{ $t('common.cancel') }}</button>
          <button @click="handleBatchEdit" :disabled="batchEditLoading"
            :class="['flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors', batchEditLoading ? 'bg-gray-100 text-text-secondary cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700 text-white']">
            <span v-if="batchEditLoading" class="material-symbols-outlined text-[16px] animate-spin">refresh</span>
            {{ batchEditLoading ? $t('common.saving') : $t('qrcode.confirmEditBtn') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ 决策 3: 盘点对话框 ═══ -->
    <div v-if="showStocktake" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showStocktake = false">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 max-h-[90vh] overflow-auto">
        <h3 class="text-lg font-semibold mb-4">盘点 (Stocktake)</h3>

        <div v-if="!stocktakeResult" class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">仓库</label>
            <select v-model="stocktakeWarehouseId" class="w-full px-3 py-2 border rounded-lg text-sm">
              <option :value="1">主仓</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">扫码 / 输入二维码 ID（回车添加）</label>
            <input @keyup.enter="addScannedId($event.target.value); $event.target.value = ''"
              class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="例如 3" />
          </div>

          <div v-if="stocktakeScannedIds.length > 0">
            <div class="text-sm font-medium mb-1">已扫 ({{ stocktakeScannedIds.length }}):</div>
            <div class="flex flex-wrap gap-2">
              <span v-for="id in stocktakeScannedIds" :key="id"
                class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">
                {{ id }}
              </span>
            </div>
          </div>

          <div>
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" v-model="stocktakeBlindMode" />
              盲盘模式（不显示差异）
            </label>
          </div>

          <div class="flex justify-end gap-2 pt-4">
            <button @click="showStocktake = false" class="px-4 py-2 rounded-lg text-sm font-medium text-text-secondary hover:bg-gray-100">
              取消
            </button>
            <button @click="runReconcile" :disabled="stocktakeLoading"
              class="px-4 py-2 rounded-lg text-sm font-medium bg-gray-700 hover:bg-gray-800 text-white">
              对账（重建 warehouse_stock）
            </button>
            <button @click="runStocktake" :disabled="stocktakeLoading"
              class="px-4 py-2 rounded-lg text-sm font-medium bg-amber-500 hover:bg-amber-600 text-white">
              {{ stocktakeLoading ? '盘点中...' : '开始盘点' }}
            </button>
          </div>
        </div>

        <div v-else class="space-y-4">
          <div class="bg-gray-50 rounded-lg p-4">
            <div class="text-sm font-medium mb-2">汇总</div>
            <div class="grid grid-cols-2 gap-2 text-sm">
              <div>系统库存: <strong>{{ stocktakeResult.summary.total_stock }}</strong></div>
              <div>实际扫码: <strong>{{ stocktakeResult.summary.scanned }}</strong></div>
              <div class="text-success">匹配: <strong>{{ stocktakeResult.summary.matched }}</strong></div>
              <div class="text-danger">缺失: <strong>{{ stocktakeResult.summary.missing }}</strong></div>
            </div>
          </div>

          <div v-if="stocktakeResult.matched.length > 0">
            <div class="text-sm font-medium text-success mb-1">✓ 匹配 ({{ stocktakeResult.matched.length }})</div>
            <div class="text-xs text-text-secondary">
              <span v-for="q in stocktakeResult.matched" :key="q.id" class="inline-block mr-2">{{ q.code }}</span>
            </div>
          </div>

          <div v-if="stocktakeResult.missing.length > 0">
            <div class="text-sm font-medium text-danger mb-1">✗ 缺失 ({{ stocktakeResult.missing.length }})</div>
            <div class="text-xs text-text-secondary">
              <span v-for="q in stocktakeResult.missing" :key="q.id" class="inline-block mr-2">{{ q.code }}</span>
            </div>
          </div>

          <div v-if="stocktakeResult.extra.length > 0">
            <div class="text-sm font-medium text-warning mb-1">⚠ 多余 ({{ stocktakeResult.extra.length }})</div>
            <div class="text-xs text-text-secondary">
              <span v-for="(q, i) in stocktakeResult.extra" :key="i" class="inline-block mr-2">{{ q.note }}</span>
            </div>
          </div>

          <div v-if="stocktakeResult.diff && stocktakeResult.diff.length > 0" class="bg-amber-50 rounded p-3">
            <div class="text-sm font-medium text-amber-700 mb-1">差异 ({{ stocktakeResult.diff.length }})</div>
            <table class="w-full text-xs">
              <thead><tr class="text-text-secondary"><th>SKU</th><th>系统</th><th>实际</th><th>差异</th></tr></thead>
              <tbody>
                <tr v-for="(d, i) in stocktakeResult.diff" :key="i">
                  <td>{{ d.product_id }}-{{ d.sku_id }}</td>
                  <td>{{ d.system_qty }}</td>
                  <td>{{ d.actual_qty }}</td>
                  <td :class="d.delta > 0 ? 'text-success' : 'text-danger'">{{ d.delta > 0 ? '+' : '' }}{{ d.delta }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex justify-end gap-2 pt-4">
            <button @click="stocktakeResult = null; stocktakeScannedIds = []" class="px-4 py-2 rounded-lg text-sm font-medium text-text-secondary hover:bg-gray-100">
              重新盘点
            </button>
            <button @click="showStocktake = false" class="px-4 py-2 rounded-lg text-sm font-medium bg-primary text-white">
              关闭
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ 决策 5: 重新绑定对话框 ═══ -->
    <div v-if="showRebind" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showRebind = false">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold mb-4">重新绑定</h3>

        <div v-if="!rebindResult" class="space-y-4">
          <div class="bg-gray-50 rounded p-3 text-sm">
            <div><strong>原二维码:</strong> {{ rebindQrcode?.code }}</div>
            <div><strong>当前状态:</strong> {{ rebindQrcode?.status }}</div>
            <div class="text-text-secondary text-xs mt-1">重新绑定后原二维码会变 disabled，并生成新码</div>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">商品 ID</label>
            <input v-model.number="rebindProductId" type="number" class="w-full px-3 py-2 border rounded-lg text-sm" />
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">SKU ID (可选)</label>
            <input v-model.number="rebindSkuId" type="number" class="w-full px-3 py-2 border rounded-lg text-sm" />
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">仓库 ID</label>
            <input v-model.number="rebindWarehouseId" type="number" class="w-full px-3 py-2 border rounded-lg text-sm" />
          </div>

          <div class="flex justify-end gap-2 pt-4">
            <button @click="showRebind = false" class="px-4 py-2 rounded-lg text-sm font-medium text-text-secondary hover:bg-gray-100">
              取消
            </button>
            <button @click="runRebind" :disabled="rebindLoading"
              class="px-4 py-2 rounded-lg text-sm font-medium bg-purple-500 hover:bg-purple-600 text-white">
              {{ rebindLoading ? '处理中...' : '确认重新绑定' }}
            </button>
          </div>
        </div>

        <div v-else class="space-y-3">
          <div class="bg-red-50 rounded p-3 text-sm">
            <div class="font-medium text-danger mb-1">原二维码 (已 disabled)</div>
            <div class="font-mono text-xs">{{ rebindResult.old.code }}</div>
          </div>
          <div class="bg-green-50 rounded p-3 text-sm">
            <div class="font-medium text-success mb-1">新二维码 (已 inStock)</div>
            <div class="font-mono text-xs">{{ rebindResult.new.code }}</div>
            <div class="text-xs text-text-secondary mt-1">
              商品: {{ rebindResult.new.product_id }} / SKU: {{ rebindResult.new.sku_id || '-' }} / 仓库: {{ rebindResult.new.warehouse_id }}
            </div>
          </div>
          <div class="flex justify-end pt-2">
            <button @click="showRebind = false; rebindResult = null" class="px-4 py-2 rounded-lg text-sm font-medium bg-primary text-white">
              完成
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Mobile responsive styles */
@media (max-width: 768px) {
  /* Stats grid - reduce columns */
  .grid-cols-2 {
    grid-template-columns: repeat(1, 1fr) !important;
  }
  .lg\:grid-cols-6 {
    grid-template-columns: repeat(2, 1fr) !important;
  }

  /* Stats cards - smaller padding and text */
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-3 {
    padding: 0.75rem !important;
  }
  .text-lg.font-bold {
    font-size: 1.125rem !important;
  }
  .text-\[10px\] {
    font-size: 0.65rem !important;
  }

  /* Tabs - smaller padding */
  .flex.items-center.gap-2.px-6.py-3 {
    padding: 0.5rem 1rem !important;
    font-size: 0.8rem !important;
  }

  /* Filter toolbar - stack items, reduce padding */
  .flex.flex-wrap.items-center.gap-3.p-4 {
    padding: 0.75rem !important;
    gap: 0.5rem !important;
  }
  .flex-1.min-w-\[200px\] {
    min-width: 100% !important;
  }

  /* Table - smaller font and padding */
  .w-full.text-left.text-sm {
    font-size: 0.75rem !important;
  }
  .px-4.py-3 {
    padding: 0.5rem !important;
  }
  .w-10.h-10 {
    width: 2rem !important;
    height: 2rem !important;
  }
  .font-mono.text-xs {
    font-size: 0.65rem !important;
  }

  /* Action buttons - smaller text */
  .text-xs.font-medium {
    font-size: 0.7rem !important;
  }
  .flex.items-center.justify-end.gap-2 {
    flex-wrap: wrap !important;
    gap: 0.25rem !important;
  }

  /* Modal/dialog - full width with smaller padding */
  .fixed.inset-0.bg-black\/40 .bg-white.rounded-xl.shadow-xl.w-full.max-w-md.p-6,
  .fixed.inset-0.bg-black\/40 .bg-white.rounded-xl.shadow-xl.w-full.max-w-lg.mx-4 {
    width: calc(100% - 2rem) !important;
    max-width: 100% !important;
    margin: 1rem !important;
    padding: 1rem !important;
  }

  /* Print preview modal - smaller padding */
  .fixed.inset-0.bg-black\/60 .bg-white.rounded-xl.shadow-2xl.w-full.max-w-4xl.mx-4 {
    width: calc(100% - 1rem) !important;
    max-width: 100% !important;
    margin: 0.5rem !important;
    padding: 0.75rem !important;
  }

  /* Batch edit modal fields - stack form fields */
  .space-y-4 {
    gap: 0.75rem !important;
  }
  .border.border-gray-100.rounded-lg.p-4 {
    padding: 0.75rem !important;
  }

  /* Buttons - compact sizing on mobile */
  .px-4.py-2.rounded-lg.text-sm {
    padding: 0.4rem 0.75rem !important;
    font-size: 0.75rem !important;
  }
  .flex.items-center.gap-2 .material-symbols-outlined {
    font-size: 16px !important;
  }
}
</style>

<style>
@media print {
  /* Hide everything except the print area */
  body > * { display: none !important; }
  #app { display: block !important; }
  #app > * { display: none !important; }

  /* Show only the print-area div */
  #print-area {
    display: block !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 210mm !important;
    min-height: 297mm !important;
    margin: 0 !important;
    padding: 10mm !important;
    background: white !important;
    z-index: 99999 !important;
  }

  @page {
    size: A4 portrait;
    margin: 0;
  }
}
</style>
