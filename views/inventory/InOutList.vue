<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import api from '../../services/api.js'
import { useUserStore } from '../../stores/user.js'
import {
  formatDate,
  formatDateTime,
  changeTypeBadge,
  changeTypeLabel,
  formatSkuLabel,
} from './helpers.js'

const { t } = useI18n()
const userStore = useUserStore()
const route = useRoute()

// ─── State ───────────────────────────────────────────────────────────────────
const activeTab = ref('inbound')
const showForm = ref(false)
const submitting = ref(false)
const formError = ref('')
const formSuccess = ref('')
const formConflicts = ref([]) // 新模型：冲突的 SKU（已存在的库存关系）
const editingId = ref(null) // 编辑时的记录ID，null=新建

const inboundRecords = ref([])
const outboundRecords = ref([])
const returnRecords = ref([])

// ─── 操作记录 Tab 数据 ───
const movementsRecords = ref([])
const movementsTotal = ref(0)
const movementsPage = ref(1)
const movementsLimit = ref(20)
const movementsFilter = ref({
  warehouse_id: '', change_type: '', operator: '',
  start_date: '', end_date: '', keyword: '',
})

// ─── 手动调整（补单/冲正/盘点） ───
const showAdjustForm = ref(false)
const adjustSubmitting = ref(false)
const adjustError = ref('')
const adjustForm = ref({
  warehouse_id: '',
  product_id: '',
  sku_id: '',
  current_qty: 0, // 当前数量（加载时自动填）
  new_qty: 0,     // 目标数量
  reason: '',     // 调整原因（必填）
})

const warehouses = ref([])
const products = ref([])
const productSkus = ref({}) // { productId: [ {id, sku, sku_key, specs, stock}, ... ] }
const productFilters = ref({}) // { rowIndex: filterString }

// Pagination
const pagination = ref({ inbound: {}, outbound: {}, return: {} })

// Detail modal
const showDetail = ref(false)
const detailRecord = ref(null)

// Print
const printRecord = ref(null)

// ─── Form model ──────────────────────────────────────────────────────────────
const emptyForm = () => ({
  warehouse_id: '',
  party: '',   // supplier / customer / source depending on tab
  remark: '',
  items: [{ product_id: '', sku_id: '', quantity: 1, qrcode_id: '', alert_stock: 0 }],
})
const form = ref(emptyForm())

// Batch mode for outbound
const batchMode = ref(false)
const batchStartCode = ref('')
const batchQuantity = ref(1)
const batchPreview = ref(null)
const batchLoading = ref(false)
const batchError = ref('')

// ─── Computed ─────────────────────────────────────────────────────────────────
const tabs = computed(() => {
  const all = [
    { key: 'inbound',  label: t('inout.inbound'),  icon: 'input',    count: inboundRecords.value.length },
    { key: 'outbound', label: t('inout.outbound'), icon: 'output',   count: outboundRecords.value.length },
    { key: 'return',   label: t('inout.returns'),  icon: 'undo',     count: returnRecords.value.length },
  ]
  // 操作记录 Tab 独立权限：stock_movements:read
  if (userStore.canAccess?.('stock_movements:read')) {
    all.push({ key: 'movements', label: t('inout.movements') || '操作记录', icon: 'history', count: movementsTotal.value })
  }
  return all
})

const currentRecords = computed(() => {
  if (activeTab.value === 'inbound')  return inboundRecords.value
  if (activeTab.value === 'outbound') return outboundRecords.value
  if (activeTab.value === 'movements') return []  // 操作记录走专属表格
  return returnRecords.value
})

const partyLabel = computed(() => {
  if (activeTab.value === 'inbound')  return t('inout.supplier')
  if (activeTab.value === 'outbound') return t('inout.customer')
  return t('inout.source')
})

const modalTitle = computed(() => {
  const isEdit = editingId.value !== null
  if (activeTab.value === 'inbound')  return isEdit ? t('inout.editInbound')  : t('inout.newInbound')
  if (activeTab.value === 'outbound') return isEdit ? t('inout.editOutbound') : t('inout.newOutbound')
  return isEdit ? t('inout.editReturn') : t('inout.newReturn')
})

const statusMap = computed(() => ({
  completed: { type: 'success',  text: t('inout.completed') },
  pending:   { type: 'warning',  text: t('inout.pending')   },
  shipping:  { type: 'primary',  text: t('inout.shipping')  },
  cancelled: { type: 'danger',   text: t('inout.cancelled') },
}))

const operatorName = computed(() => {
  if (userStore.userName) return userStore.userName
  try {
    const u = JSON.parse(localStorage.getItem('caimeite_user') || 'null')
    return u?.name || ''
  } catch {
    return ''
  }
})

const canDelete = computed(() => userStore.canAccess('inventory:delete'))

// ─── Load data ────────────────────────────────────────────────────────────────
async function loadRecords() {
  const [ibRes, obRes, retRes] = await Promise.allSettled([
    api.get('/inbound'),
    api.get('/outbound'),
    api.get('/returns'),
  ])
  if (ibRes.status === 'fulfilled') {
    const r = ibRes.value
    inboundRecords.value = r?.data?.list ?? r?.list ?? []
  }
  if (obRes.status === 'fulfilled') {
    const r = obRes.value
    outboundRecords.value = r?.data?.list ?? r?.list ?? []
  }
  if (retRes.status === 'fulfilled') {
    const r = retRes.value
    returnRecords.value = r?.data?.list ?? r?.list ?? []
  }
}

async function loadMovements() {
  try {
    const params = {
      page: movementsPage.value,
      limit: movementsLimit.value,
    }
    if (movementsFilter.value.warehouse_id) params.warehouse_id = movementsFilter.value.warehouse_id
    if (movementsFilter.value.change_type)  params.change_type  = movementsFilter.value.change_type
    if (movementsFilter.value.operator)     params.operator     = movementsFilter.value.operator
    if (movementsFilter.value.start_date)   params.start_date   = movementsFilter.value.start_date
    if (movementsFilter.value.end_date)     params.end_date     = movementsFilter.value.end_date
    if (movementsFilter.value.keyword)      params.keyword      = movementsFilter.value.keyword
    const res = await api.get('/stock-movements', { params })
    movementsRecords.value = res?.data?.list ?? []
    movementsTotal.value = res?.data?.total ?? 0
  } catch (e) {
    console.error('loadMovements failed:', e)
    movementsRecords.value = []
    movementsTotal.value = 0
  }
}

function applyMovementsFilter() {
  movementsPage.value = 1
  loadMovements()
}

function resetMovementsFilter() {
  movementsFilter.value = { warehouse_id: '', change_type: '', operator: '', start_date: '', end_date: '', keyword: '' }
  applyMovementsFilter()
}

// ─── 手动调整：弹窗逻辑 ───
function openAdjustForm() {
  adjustForm.value = {
    warehouse_id: warehouses.value[0]?.id ?? '',
    product_id: '',
    sku_id: '',
    current_qty: 0,
    new_qty: 0,
    reason: '',
  }
  adjustError.value = ''
  showAdjustForm.value = true
}

function closeAdjustForm() {
  showAdjustForm.value = false
  adjustError.value = ''
}

// 当切换商品/SKU时，自动加载当前库存
async function loadCurrentQty() {
  const { warehouse_id, product_id, sku_id } = adjustForm.value
  if (!warehouse_id || !product_id) {
    adjustForm.value.current_qty = 0
    return
  }
  try {
    // 用 stock 接口查当前数量（已有接口）
    const res = await api.get('/inventory/stock', {
      params: {
        warehouse_id,
        product_id,
        sku_id: sku_id || undefined,
      },
    })
    // 后端可能返回 list 或单条，兼容两种
    const list = res?.data?.list || res?.data || []
    const item = Array.isArray(list) ? list[0] : list
    adjustForm.value.current_qty = item?.quantity ?? 0
    // 同步显示给 new_qty（让用户看到差异）
    adjustForm.value.new_qty = adjustForm.value.current_qty
  } catch (e) {
    console.warn('loadCurrentQty failed:', e)
    adjustForm.value.current_qty = 0
  }
}

async function submitAdjust() {
  adjustError.value = ''
  const { warehouse_id, product_id, new_qty, reason } = adjustForm.value
  if (!warehouse_id || !product_id) {
    adjustError.value = '请选择仓库和商品'
    return
  }
  if (new_qty == null || Number(new_qty) < 0) {
    adjustError.value = '新数量必须是非负整数'
    return
  }
  if (!reason || !reason.trim()) {
    adjustError.value = '请填写调整原因（必填，可追溯）'
    return
  }
  if (Number(new_qty) === adjustForm.value.current_qty) {
    adjustError.value = '新数量与当前数量一致，无需调整'
    return
  }

  adjustSubmitting.value = true
  try {
    const res = await api.post('/stock-movements/adjust', {
      warehouse_id,
      product_id,
      sku_id: adjustForm.value.sku_id || undefined,
      new_qty: Number(new_qty),
      reason: reason.trim(),
    })
    if (res?.code === 0) {
      formSuccess.value = `调整成功：${res.data.before_qty} → ${res.data.after_qty}（${res.data.delta > 0 ? '+' : ''}${res.data.delta}）`
      setTimeout(() => { formSuccess.value = '' }, 3000)
      closeAdjustForm()
      // 刷新流水列表
      await loadMovements()
      // 刷新入/出库 tab 的数量（因为可能影响计数）
      await loadRecords()
    } else {
      adjustError.value = res?.message || '调整失败'
    }
  } catch (e) {
    adjustError.value = e?.response?.data?.message || e?.message || '请求失败'
  } finally {
    adjustSubmitting.value = false
  }
}

async function loadWarehouses() {
  try {
    const res = await api.get('/warehouses')
    warehouses.value = res?.data?.list ?? res?.list ?? res?.data ?? []
  } catch (e) {
    console.error('Failed to load warehouses', e)
  }
}

async function loadProducts() {
    try {
        const res = await api.get('/products/all')
        products.value = res?.data ?? []
    } catch (e) {
        console.error('Failed to load products', e)
    }
}

async function loadSkus(productId) {
    if (!productId || productSkus.value[productId]) return
    try {
        const res = await api.get(`/products/${productId}/specs`)
        if (res.code === 0) {
            productSkus.value[productId] = res.data?.skus ?? []
        }
    } catch (e) {
        console.warn('loadSkus failed', productId, e)
    }
}

// 给"每个 item"取它对应的 SKU 列表（按 product_id 缓存）
function getSkusForItem(item) {
    if (!item?.product_id) return []
    return productSkus.value[item.product_id] || []
}

// 商品下拉的搜索过滤（按 row index 区分）
function getFilteredProducts(index) {
    const filter = (productFilters.value[index] || '').toLowerCase()
    if (!filter) return products.value
    return products.value.filter(p =>
        (p.sku && p.sku.toLowerCase().includes(filter)) ||
        (p.name && p.name.toLowerCase().includes(filter))
    )
}

function productById(id) {
    return products.value.find(p => String(p.id) === String(id)) || null
}

function needsQrcode(item) {
    if (activeTab.value !== 'outbound') return false
    const p = productById(item.product_id)
    return p?.require_qrcode === true || p?.require_qrcode === 1
}

function filterProduct(val, index) {
    productFilters.value[index] = val || ''
}

function resetProductFilter(index) {
    productFilters.value[index] = ''
}

function onProductChange(item) {
    item.sku_id = '' // reset SKU when product changes
    if (item.product_id) loadSkus(item.product_id)
}

onMounted(async () => {
  await Promise.all([loadRecords(), loadWarehouses(), loadProducts(), loadMovements()])
  // 切到 movements tab 时自动刷新流水
  watch(activeTab, (val) => {
    if (val === 'movements') loadMovements()
  })
  // 补仓预警预填：?type=inbound&prefill=...
  if (route.query.type === 'inbound') activeTab.value = 'inbound'
  if (route.query.prefill) {
    try {
      const data = JSON.parse(decodeURIComponent(route.query.prefill))
      activeTab.value = 'inbound'
      showForm.value = true
      editingId.value = null
      form.value = {
        warehouse_id: data.warehouse_id || '',
        party: '',
        remark: data.note || '',
        items: (data.items || []).map(it => ({
          product_id: it.product_id,
          sku_id: '',
          quantity: it.quantity || 1,
          qrcode_id: '',
          alert_stock: it.alert_stock ?? 0
        }))
      }
    } catch (e) {
      console.error('prefill parse error', e)
    }
  }
  // 入库成功后如果是从预警跳过来的，自动标记 handled
  prefilledAlertId.value = route.query.alert_id ? Number(route.query.alert_id) : null
})

// ─── 预警关联：从库存预警页跳过来预填时记录 alert_id，入库成功后自动 PUT 标记 handled ──
const prefilledAlertId = ref(null)

// ─── Batch mode functions ─────────────────────────────────────────────────────
async function previewBatch() {
  if (!batchStartCode.value || !batchQuantity.value) {
    batchError.value = t('inout.enterStartQrcodeAndQty')
    return
  }

  batchLoading.value = true
  batchError.value = ''
  batchPreview.value = null

  try {
    const res = await api.post('/inventory/outbound/preview', {
      start_qrcode: batchStartCode.value,
      quantity: batchQuantity.value
    })

    if (res.code === 0) {
      batchPreview.value = res.data
      batchError.value = ''
    } else {
      batchError.value = res.message || t('inout.previewFailed')
    }
  } catch (e) {
    batchError.value = e.response?.data?.message || e.message || t('inout.previewFailedCheckFormat')
  } finally {
    batchLoading.value = false
  }
}

function clearBatchPreview() {
  batchPreview.value = null
  batchError.value = ''
}

function toggleBatchMode() {
  batchMode.value = !batchMode.value
  if (!batchMode.value) {
    // Reset batch fields when turning off
    batchStartCode.value = ''
    batchQuantity.value = 1
    batchPreview.value = null
    batchError.value = ''
  }
}

// ─── Form actions ─────────────────────────────────────────────────────────────
function openForm() {
  editingId.value = null
  form.value = emptyForm()
  formError.value = ''
  formSuccess.value = ''
  batchMode.value = false
  batchStartCode.value = ''
  batchQuantity.value = 1
  batchPreview.value = null
  batchError.value = ''
  showForm.value = true
}

function closeForm() {
  showForm.value = false
}

function addItem() {
  form.value.items.push({ product_id: '', sku_id: '', quantity: 1, qrcode_id: '', alert_stock: 0 })
}

function removeItem(index) {
  if (form.value.items.length > 1) {
    form.value.items.splice(index, 1)
  }
}

async function submitForm() {
  formError.value = ''
  formSuccess.value = ''
  formConflicts.value = []

  // Basic validation
  if (!form.value.warehouse_id) {
    formError.value = t('inout.selectWarehouse')
    return
  }

  // For batch mode, validate batch fields
  if (activeTab.value === 'outbound' && batchMode.value) {
    if (!batchStartCode.value || !batchQuantity.value) {
      formError.value = t('inout.batchModeEnterQrcodeAndQty')
      return
    }
    if (!batchPreview.value) {
      formError.value = t('inout.pleasePreviewBatchFirst')
      return
    }
  } else {
    // Normal mode validation
    // 入库允许 0 数量（占位先入库，事后补实际数量）
    const validItems = form.value.items.filter(i => i.product_id && Number(i.quantity) >= 0)
    if (validItems.length === 0) {
      formError.value = t('inout.addAtLeastOneProduct')
      return
    }
  }

  submitting.value = true
  try {
    const endpoint = activeTab.value === 'inbound'
      ? '/inbound'
      : activeTab.value === 'outbound'
        ? '/outbound'
        : '/returns'

    const partyKey = activeTab.value === 'inbound'
      ? 'supplier'
      : activeTab.value === 'outbound'
        ? 'customer'
        : 'source'

    let payload

    // Handle batch mode for outbound
    if (activeTab.value === 'outbound' && batchMode.value) {
      payload = {
        warehouse_id: form.value.warehouse_id,
        customer: form.value.party,
        remark: form.value.remark,
        operator: operatorName.value,
        batch_mode: true,
        start_qrcode: batchStartCode.value,
        quantity: batchQuantity.value
      }
    } else {
      // Normal mode — 入库允许 0 数量（占位先入库，事后补实际数量）
      const validItems = form.value.items.filter(i => i.product_id && Number(i.quantity) >= 0)
      payload = {
        warehouse_id: form.value.warehouse_id,
        [partyKey]: form.value.party,
        remark: form.value.remark,
        operator: operatorName.value,
        items: validItems.map(i => {
          const item = { product_id: i.product_id, quantity: Number(i.quantity) }
          if (i.sku_id) item.sku_id = i.sku_id
          // 入库时同步传 alert_stock（>0 才传，0=不预警表示不修改）
          if (activeTab.value === 'inbound' && Number(i.alert_stock) > 0) {
            item.alert_stock = Number(i.alert_stock)
          }
          if (activeTab.value === 'outbound' && needsQrcode(i) && i.qrcode_id) {
            item.qrcode_id = i.qrcode_id
          }
          return item
        }),
      }
    }

    const isEdit = editingId.value !== null
    if (isEdit) {
      await api.put(`${endpoint}/${editingId.value}`, payload)
      formSuccess.value = t('inout.submitSuccess') || '编辑成功'
    } else {
      try {
        await api.post(endpoint, payload)
        formSuccess.value = t('inout.submitSuccess') || '提交成功'
      } catch (e) {
        // ✅ 新模型：409 冲突（SKU 在此仓库已存在）→ 引导用户去库存管理调整
        if (e?.code === 409 || e?.response?.data?.code === 409) {
          const errData = e?.data || e?.response?.data || e
          formConflicts.value = errData.data?.conflicts || []
          formError.value = errData.message || '有 SKU 已存在，请用「库存管理 → 调整数量」'
          submitting.value = false
          return
        }
        throw e
      }
    }
    await loadRecords()
    // 入库成功后，如果是从预警页跳转过来的，自动标记预警 handled
    if (prefilledAlertId.value && !isEdit) {
      try {
        await api.put('/stock-alerts/' + prefilledAlertId.value)
        formSuccess.value += ' · 预警 #' + prefilledAlertId.value + ' 已标记处理'
        prefilledAlertId.value = null
      } catch (e) {
        console.warn('标记预警 handled 失败（不影响入库）', e)
      }
    }
    setTimeout(() => {
      closeForm()
    }, 800)
  } catch (e) {
    formError.value = e?.message || e?.msg || t('inout.submitFailedRetry')
  } finally {
    submitting.value = false
  }
}

// ─── Detail modal ─────────────────────────────────────────────────────────────
function openDetail(record) {
  detailRecord.value = record
  showDetail.value = true
}

// ─── Print ────────────────────────────────────────────────────────────────────
const showPrintPreview = ref(false)
const printForm = ref({
  remark: '',
  customer: '',
  operator: '',
  includeSignature: true,
  includeQrcode: true,
})

function openPrintPreview(record) {
  printRecord.value = record
  printForm.value = {
    remark: record.remark || '',
    customer: record.customer || '',
    operator: record.operator || operatorName.value || '',
    includeSignature: true,
    includeQrcode: true,
  }
  showPrintPreview.value = true
}

function closePrintPreview() {
  showPrintPreview.value = false
}

function confirmPrint() {
  // Merge edited fields into printRecord for printing
  printRecord.value = {
    ...printRecord.value,
    remark: printForm.value.remark,
    customer: printForm.value.customer,
    operator: printForm.value.operator,
  }
  showPrintPreview.value = false
  nextTick(() => {
    window.print()
  })
}

// ─── Edit Record ──────────────────────────────────────────────────────────────
function editRecord(record) {
  const partyKey = activeTab.value === 'inbound'
    ? 'supplier'
    : activeTab.value === 'outbound'
      ? 'customer'
      : 'source'
  form.value = {
    warehouse_id: record.warehouse_id,
    party: record[partyKey] || '',
    remark: record.remark || '',
    items: (record.items || []).map(i => ({
      product_id: i.product_id,
      sku_id: i.sku_id || '',
      quantity: i.quantity,
      qrcode_id: i.qrcode_id || '',
    })),
  }
  if (form.value.items.length === 0) {
    form.value.items = [{ product_id: '', sku_id: '', quantity: 1, qrcode_id: '' }]
  }
  // Load SKUs for all products in the form
  for (const item of form.value.items) {
    if (item.product_id) loadSkus(item.product_id)
  }
  editingId.value = record.id
  formError.value = ''
  formSuccess.value = ''
  batchMode.value = false
  showForm.value = true
}

// ─── Delete Record ────────────────────────────────────────────────────────────
async function deleteRecord(record) {
  const recordType = activeTab.value === 'inbound' ? t('inout.inboundType') :
                     activeTab.value === 'outbound' ? t('inout.outboundType') : t('inout.returnType')

  if (!confirm(t('inout.confirmDeleteRecord', { type: recordType, no: record.record_no }))) {
    return
  }

  try {
    const endpoint = activeTab.value === 'inbound' ? '/inbound' :
                     activeTab.value === 'outbound' ? '/outbound' : '/returns'

    await api.delete(`${endpoint}/${record.id}`)
    alert(t('inout.deleteSuccess'))
    await loadRecords()
  } catch (err) {
    alert(err.response?.data?.message || err.message || t('inout.deleteFailed'))
  }
}

async function handleDeleteRecord() {
  if (!detailRecord.value) return

  const recordType = activeTab.value === 'inbound' ? t('inout.inboundType') :
                     activeTab.value === 'outbound' ? t('inout.outboundType') : t('inout.returnType')

  if (!confirm(t('inout.confirmDeleteRecord', { type: recordType, no: detailRecord.value.record_no }))) {
    return
  }

  try {
    const endpoint = activeTab.value === 'inbound' ? '/inbound' :
                     activeTab.value === 'outbound' ? '/outbound' : '/returns'

    await api.delete(`${endpoint}/${detailRecord.value.id}`)
    alert(t('inout.deleteSuccess'))
    showDetail.value = false
    await loadRecords()
  } catch (err) {
    alert(err.response?.data?.message || err.message || t('inout.deleteFailed'))
  }
}
</script>

<template>
  <div>
    <PageHeader :title="$t('inout.title')" :subtitle="$t('inout.subtitle')" />

    <!-- Tabs + New button -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card mb-6">
      <div class="flex flex-wrap border-b border-gray-100">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          :class="[
            'flex items-center gap-2 px-6 py-3 text-sm font-medium border-b-2 transition-colors',
            activeTab === tab.key
              ? 'border-primary text-primary'
              : 'border-transparent text-text-secondary hover:text-text-primary'
          ]"
        >
          <span class="material-symbols-outlined text-[18px]">{{ tab.icon }}</span>
          {{ tab.label }}
          <span class="bg-gray-100 text-text-secondary text-xs px-1.5 py-0.5 rounded-full">{{ tab.count }}</span>
        </button>
        <div class="ml-auto flex items-center px-4">
          <button
            @click="openForm"
            class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
          >
            <span class="material-symbols-outlined text-[18px]">add</span>
            {{ modalTitle }}
          </button>
        </div>
      </div>

      <!-- 操作记录 Tab 内容（独立模板） -->
      <div v-if="activeTab === 'movements'" class="p-4">
        <!-- 筛选区 -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3 mb-4">
          <select v-model="movementsFilter.warehouse_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
            <option value="">全部门店</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
          <select v-model="movementsFilter.change_type" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
            <option value="">全部类型</option>
            <option value="inbound">入库</option>
            <option value="adjust">调整</option>
            <option value="outbound">出库</option>
            <option value="return">退货</option>
            <option value="transferIn">调入</option>
            <option value="transferOut">调出</option>
            <option value="delete">删除</option>
          </select>
          <input v-model="movementsFilter.operator" type="text" placeholder="操作人" class="border border-gray-200 rounded-lg px-3 py-2 text-sm" />
          <input v-model="movementsFilter.start_date" type="date" class="border border-gray-200 rounded-lg px-3 py-2 text-sm" />
          <input v-model="movementsFilter.end_date"   type="date" class="border border-gray-200 rounded-lg px-3 py-2 text-sm" />
          <input v-model="movementsFilter.keyword"    type="text" placeholder="商品编号/名称/SKU" class="border border-gray-200 rounded-lg px-3 py-2 text-sm" />
        </div>
        <div class="flex gap-2 mb-4">
          <button @click="applyMovementsFilter" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium">查询</button>
          <button @click="resetMovementsFilter" class="border border-gray-200 hover:bg-gray-50 text-text-secondary px-4 py-2 rounded-lg text-sm">重置</button>
          <!-- 手动调整按钮（需 stock_movements:write 权限） -->
          <button v-if="userStore.canAccess?.('stock_movements:write')"
            @click="openAdjustForm"
            class="ml-auto border border-orange-300 bg-orange-50 hover:bg-orange-100 text-orange-700 px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-1">
            <span class="material-symbols-outlined text-base">tune</span>
            手动调整库存
          </button>
          <span v-else class="ml-auto text-sm text-text-secondary self-center">共 {{ movementsTotal }} 条</span>
          <span v-if="userStore.canAccess?.('stock_movements:write')" class="text-sm text-text-secondary self-center">共 {{ movementsTotal }} 条</span>
        </div>

        <!-- 流水表格 -->
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
              <tr>
                <th class="px-4 py-3 font-medium w-44">操作时间</th>
                <th class="px-4 py-3 font-medium w-28">类型</th>
                <th class="px-4 py-3 font-medium w-28">门店</th>
                <th class="px-4 py-3 font-medium">商品</th>
                <th class="px-4 py-3 font-medium w-24">SKU</th>
                <th class="px-4 py-3 font-medium w-28 text-right">数量变化</th>
                <th class="px-4 py-3 font-medium w-32 text-right">变更前→后</th>
                <th class="px-4 py-3 font-medium w-24">操作人</th>
                <th class="px-4 py-3 font-medium">备注</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="movementsRecords.length === 0">
                <td colspan="9" class="px-4 py-12 text-center text-text-secondary text-sm">
                  <span class="material-symbols-outlined text-4xl block mb-2 text-gray-300">history</span>
                  暂无操作记录
                </td>
              </tr>
              <tr v-for="m in movementsRecords" :key="m.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-text-secondary">{{ formatDateTime(m.created_at) }}</td>
                <td class="px-4 py-3">
                  <span :class="changeTypeBadge(m.change_type)" class="px-2 py-0.5 rounded text-xs font-medium">
                    {{ changeTypeLabel(m.change_type) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-text-secondary">{{ m.warehouse_name || '-' }}</td>
                <td class="px-4 py-3">
                  <div class="font-medium text-text-primary">{{ m.product_name || '-' }}</div>
                  <div class="text-xs text-text-secondary">{{ m.product_code || '' }}</div>
                </td>
                <td class="px-4 py-3 text-text-secondary">{{ m.sku_key || '-' }}</td>
                <td class="px-4 py-3 text-right font-medium" :class="(m.delta ?? 0) > 0 ? 'text-green-600' : 'text-red-600'">
                  {{ (m.delta ?? 0) > 0 ? '+' : '' }}{{ m.delta }}
                </td>
                <td class="px-4 py-3 text-right text-text-secondary">
                  {{ m.before_qty }} → <span class="text-text-primary font-medium">{{ m.after_qty }}</span>
                </td>
                <td class="px-4 py-3 text-text-secondary">{{ m.operator || '-' }}</td>
                <td class="px-4 py-3 text-text-secondary">{{ m.remark || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 分页 -->
        <div v-if="movementsTotal > movementsLimit" class="flex items-center justify-end gap-2 mt-4">
          <button @click="movementsPage = Math.max(1, movementsPage - 1); loadMovements()"
            :disabled="movementsPage === 1"
            class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm disabled:opacity-50">上一页</button>
          <span class="text-sm text-text-secondary">第 {{ movementsPage }} / {{ Math.ceil(movementsTotal / movementsLimit) }} 页</span>
          <button @click="movementsPage = Math.min(Math.ceil(movementsTotal / movementsLimit), movementsPage + 1); loadMovements()"
            :disabled="movementsPage >= Math.ceil(movementsTotal / movementsLimit)"
            class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm disabled:opacity-50">下一页</button>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">{{ $t('inout.recordNo') }}</th>
              <th class="px-4 py-3 font-medium w-12"></th>
              <th class="px-4 py-3 font-medium">{{ $t('inout.date') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('inout.warehouse') }}</th>
              <th class="px-4 py-3 font-medium">{{ partyLabel }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('inout.qty') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('common.status') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('common.remark') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('common.action') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <!-- Empty state -->
            <tr v-if="currentRecords.length === 0">
              <td colspan="9" class="px-4 py-12 text-center text-text-secondary text-sm">
                <span class="material-symbols-outlined text-4xl block mb-2 text-gray-300">inbox</span>
                {{ $t('common.noData') }}
              </td>
            </tr>
            <!-- Records -->
            <tr
              v-for="r in currentRecords"
              :key="r.id"
              class="hover:bg-gray-50 transition-colors"
            >
              <td class="px-4 py-3 font-mono text-xs text-primary font-medium whitespace-nowrap">{{ r.record_no }}</td>
              <td class="px-4 py-3">
                <img
                  v-if="r.items && r.items[0] && r.items[0].image_main"
                  :src="r.items[0].image_main"
                  class="w-10 h-10 object-cover rounded border border-gray-200"
                  @error="$event.target.style.display='none'"
                />
                <div v-else class="w-10 h-10 bg-gray-100 rounded border border-gray-200 flex items-center justify-center">
                  <span class="material-symbols-outlined text-gray-300 text-sm">image</span>
                </div>
              </td>
              <td class="px-4 py-3 text-text-secondary whitespace-nowrap">{{ formatDate(r.created_at) }}</td>
              <td class="px-4 py-3 text-text-primary">{{ r.warehouse_name }}</td>
              <td class="px-4 py-3 text-text-primary">{{ r.supplier || r.customer || r.source || '—' }}</td>
              <td class="px-4 py-3 text-center font-medium text-text-primary">{{ r.total_qty }}</td>
              <td class="px-4 py-3 text-center">
                <StatusTag
                  :type="statusMap[r.status]?.type || 'info'"
                  :text="statusMap[r.status]?.text || r.status"
                />
              </td>
              <td class="px-4 py-3 text-text-secondary text-xs max-w-[160px] truncate">{{ r.remark || '—' }}</td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <button
                  @click="openDetail(r)"
                  class="text-primary hover:text-primary-hover text-xs font-medium mr-3"
                >
                  {{ $t('common.detail') }}
                </button>
                <button
                  @click="editRecord(r)"
                  class="text-warning hover:text-yellow-600 text-xs font-medium mr-3"
                >
                  {{ $t('common.edit') }}
                </button>
                <button
                  @click="openPrintPreview(r)"
                  class="text-text-secondary hover:text-text-primary text-xs font-medium mr-3"
                >
                  {{ $t('inout.print') }}
                </button>
                <button
                  v-if="userStore.canAccess('inventory:delete')"
                  @click="deleteRecord(r)"
                  class="text-danger hover:text-red-700 text-xs font-medium"
                >
                  {{ $t('common.delete') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ─── Create Form Modal ──────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30" @click="closeForm"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="text-lg font-bold text-text-primary">{{ modalTitle }}</h3>
            <button @click="closeForm" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <!-- Body -->
          <div class="overflow-y-auto flex-1 p-6 space-y-5">
            <!-- Warehouse + Party -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">
                  {{ $t('inout.warehouse') }} <span class="text-danger">*</span>
                </label>
                <select
                  v-model="form.warehouse_id"
                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                >
                  <option value="">{{ $t('inout.selectWarehouse') }}</option>
                  <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">{{ partyLabel }}</label>
                <input
                  v-model="form.party"
                  type="text"
                  :placeholder="$t('inout.enterParty', { party: partyLabel })"
                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                />
              </div>
            </div>

            <!-- Operator (read-only) -->
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('inout.operator') }}</label>
              <input
                :value="operatorName"
                type="text"
                readonly
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-text-secondary"
              />
            </div>

            <!-- Batch Mode Toggle (Outbound only) -->
            <div v-if="activeTab === 'outbound'" class="flex items-center gap-3 p-4 bg-blue-50 border border-blue-100 rounded-lg">
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="batchMode"
                  @change="toggleBatchMode"
                  class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                />
                <span class="text-sm font-medium text-text-primary">{{ $t('inout.batchOutboundMode') }}</span>
              </label>
              <span class="text-xs text-text-secondary">{{ $t('inout.batchOutboundHint') }}</span>
            </div>

            <!-- Batch Mode Fields -->
            <div v-if="activeTab === 'outbound' && batchMode" class="space-y-4 p-4 bg-gray-50 border border-gray-200 rounded-lg">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-text-primary mb-1">
                    {{ $t('inout.startQrcodeRequired') }} <span class="text-danger">*</span>
                  </label>
                  <input
                    v-model="batchStartCode"
                    type="text"
                    :placeholder="$t('inout.startQrcodePlaceholder')"
                    @input="clearBatchPreview"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-text-primary mb-1">
                    {{ $t('inout.quantityRequired') }} <span class="text-danger">*</span>
                  </label>
                  <input
                    v-model.number="batchQuantity"
                    type="number"
                    min="1"
                    @input="clearBatchPreview"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                  />
                </div>
              </div>

              <div class="flex gap-2">
                <button
                  @click="previewBatch"
                  :disabled="batchLoading"
                  type="button"
                  class="flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
                >
                  <span class="material-symbols-outlined text-[18px]">preview</span>
                  {{ batchLoading ? $t('inout.previewLoading') : $t('inout.previewBatchBtn') }}
                </button>
              </div>

              <!-- Batch Error -->
              <div v-if="batchError" class="bg-red-50 text-danger text-sm px-4 py-2 rounded-lg border border-red-100">
                {{ batchError }}
              </div>

              <!-- Batch Preview -->
              <div v-if="batchPreview" class="space-y-3">
                <div class="flex items-center justify-between">
                  <h4 class="text-sm font-medium text-text-primary">{{ $t('inout.previewResultTitle') }}</h4>
                  <span class="text-xs text-text-secondary">{{ $t('inout.totalQrcodesCount', { count: batchPreview.total }) }}</span>
                </div>
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                  <table class="w-full text-sm">
                    <thead class="bg-gray-100">
                      <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('inout.productCol') }}</th>
                        <th class="px-3 py-2 w-12"></th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-text-secondary">{{ $t('inout.qtyCol') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('inout.qrcodeCol') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="(item, idx) in batchPreview.items"
                        :key="idx"
                        class="border-t border-gray-100"
                      >
                        <td class="px-3 py-2">
                          <div class="text-sm font-medium text-text-primary">{{ item.product_name }}</div>
                          <div class="text-xs text-text-secondary">{{ item.sku }}</div>
                        </td>
                        <td class="px-3 py-2">
                          <img
                            v-if="item.image_main"
                            :src="item.image_main"
                            class="w-8 h-8 object-cover rounded border border-gray-200"
                            @error="$event.target.style.display='none'"
                          />
                          <div v-else class="w-8 h-8 bg-gray-100 rounded border border-gray-200 flex items-center justify-center">
                            <span class="material-symbols-outlined text-gray-300 text-xs">image</span>
                          </div>
                        </td>
                        <td class="px-3 py-2 text-center font-medium">{{ item.quantity }}</td>
                        <td class="px-3 py-2">
                          <div class="text-xs text-text-secondary max-h-20 overflow-y-auto">
                            <div v-for="qr in item.qrcodes" :key="qr.id" class="font-mono">
                              {{ qr.code }}
                            </div>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- Items (Normal Mode) -->
            <div v-if="!(activeTab === 'outbound' && batchMode)">
              <div class="flex items-center justify-between mb-2">
                <label class="text-sm font-medium text-text-primary">{{ $t('inout.itemDetails') }} <span class="text-danger">*</span></label>
                <button
                  @click="addItem"
                  type="button"
                  class="flex items-center gap-1 text-primary hover:text-primary-hover text-xs font-medium"
                >
                  <span class="material-symbols-outlined text-[16px]">add_circle</span>
                  {{ $t('inout.addProduct') }}
                </button>
              </div>
              <div class="border border-gray-200 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('inout.productCol') }}</th>
                      <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('inout.skuCol') }}</th>
                      <th class="px-3 py-2 text-center text-xs font-medium text-text-secondary w-24">{{ $t('inout.qtyCol') }}</th>
                      <th v-if="activeTab === 'inbound'" class="px-3 py-2 text-center text-xs font-medium text-text-secondary w-32">
                        {{ $t('inout.alertStockCol') }}
                        <span class="text-text-secondary font-normal">({{ $t('inout.alertStockHint') }})</span>
                      </th>
                      <th v-if="activeTab === 'outbound'" class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('inout.qrCodeCol') }}</th>
                      <th class="px-3 py-2 text-center text-xs font-medium text-text-secondary w-10"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(item, index) in form.items"
                      :key="index"
                      class="border-t border-gray-100"
                    >
                      <!-- Product select -->
                      <td class="px-3 py-2">
                        <el-select
                          v-model="item.product_id"
                          filterable
                          clearable
                          :placeholder="$t('inout.selectProduct')"
                          class="w-full"
                          size="small"
                          :filter-method="(val) => filterProduct(val, index)"
                          @visible-change="(open) => { if (!open) resetProductFilter(index) }"
                          @change="onProductChange(item)"
                        >
                          <el-option
                            v-for="p in getFilteredProducts(index)"
                            :key="p.id"
                            :label="p.sku + ' - ' + p.name"
                            :value="p.id"
                          />
                        </el-select>
                      </td>
                      <!-- SKU select (shown only when product has SKUs) -->
                      <td class="px-3 py-2">
                        <template v-if="item.product_id && getSkusForItem(item).length > 0">
                          <el-select
                            v-model="item.sku_id"
                            clearable
                            :placeholder="$t('inout.selectSku')"
                            class="w-full"
                            size="small"
                          >
                            <el-option
                              v-for="sku in getSkusForItem(item)"
                              :key="sku.id"
                              :label="formatSkuLabel(sku)"
                              :value="sku.id"
                            />
                          </el-select>
                        </template>
                        <span v-else class="text-xs text-gray-300">—</span>
                      </td>
                      <!-- Quantity -->
                      <td class="px-3 py-2">
                        <input
                          v-model.number="item.quantity"
                          type="number"
                          min="1"
                          class="w-full border border-gray-200 rounded px-2 py-1 text-sm text-center focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                        />
                      </td>
                      <!-- Alert Stock (inbound only) -->
                      <td v-if="activeTab === 'inbound'" class="px-3 py-2">
                        <input
                          v-model.number="item.alert_stock"
                          type="number"
                          min="0"
                          placeholder="0"
                          :title="$t('inout.alertStockHint')"
                          class="w-full border border-gray-200 rounded px-2 py-1 text-sm text-center focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                        />
                      </td>
                      <!-- QR code (outbound only, for require_qrcode products) -->
                      <td v-if="activeTab === 'outbound'" class="px-3 py-2">
                        <template v-if="needsQrcode(item)">
                          <input
                            v-model="item.qrcode_id"
                            type="text"
                            :placeholder="$t('inout.bindQrCode')"
                            class="w-full border border-gray-200 rounded px-2 py-1 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                          />
                        </template>
                        <span v-else class="text-xs text-gray-300">—</span>
                      </td>
                      <!-- Delete row -->
                      <td class="px-3 py-2 text-center">
                        <button
                          @click="removeItem(index)"
                          :disabled="form.items.length === 1"
                          class="text-danger hover:text-red-700 disabled:opacity-30 disabled:cursor-not-allowed"
                        >
                          <span class="material-symbols-outlined text-[18px]">delete</span>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Remark -->
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('common.remark') }}</label>
              <textarea
                v-model="form.remark"
                rows="2"
                :placeholder="$t('inout.remarkPlaceholder')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none resize-none"
              ></textarea>
            </div>

            <!-- Error / Success messages -->
            <div v-if="formError" class="bg-red-50 text-danger text-sm px-4 py-2 rounded-lg border border-red-100">
              {{ formError }}
            </div>
            <div v-if="formSuccess" class="bg-green-50 text-success text-sm px-4 py-2 rounded-lg border border-green-100">
              {{ formSuccess }}
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t flex gap-3 justify-end bg-white rounded-b-xl">
            <button
              @click="closeForm"
              class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-text-primary hover:bg-gray-50 transition-colors"
            >
              {{ $t('common.cancel') }}
            </button>
            <button
              @click="submitForm"
              :disabled="submitting"
              class="px-4 py-2 bg-primary hover:bg-primary-hover disabled:opacity-60 text-white rounded-lg text-sm font-medium transition-colors"
            >
              {{ submitting ? $t('common.submitting') : $t('common.submit') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ─── 手动调整库存 Modal（补单/冲正/盘点） ───────────────────── -->
    <Teleport to="body">
      <div v-if="showAdjustForm" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30" @click="closeAdjustForm"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="text-lg font-bold text-text-primary flex items-center gap-2">
              <span class="material-symbols-outlined text-orange-600">tune</span>
              手动调整库存
            </h3>
            <button @click="closeAdjustForm" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <!-- Body -->
          <div class="overflow-y-auto flex-1 p-6 space-y-4">
            <!-- 提示 -->
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 text-sm text-orange-800">
              <strong>⚠ 调整须知：</strong>此操作会直接修改库存数量并写入操作流水。<br>
              <strong>原因必填</strong>，用于事后追溯（盘点/纠错/补单）。
            </div>

            <!-- 仓库 -->
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">
                仓库 <span class="text-danger">*</span>
              </label>
              <select v-model="adjustForm.warehouse_id"
                @change="loadCurrentQty"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                <option value="">请选择仓库</option>
                <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
              </select>
            </div>

            <!-- 商品 -->
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">
                商品 <span class="text-danger">*</span>
              </label>
              <select v-model="adjustForm.product_id"
                @change="adjustForm.sku_id = ''; loadCurrentQty()"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                <option value="">请选择商品</option>
                <option v-for="p in products" :key="p.id" :value="p.id">
                  {{ p.sku }} - {{ p.name }}
                </option>
              </select>
            </div>

            <!-- SKU（如果有） -->
            <div v-if="adjustForm.product_id && productSkus[adjustForm.product_id]?.length">
              <label class="block text-sm font-medium text-text-primary mb-1">SKU</label>
              <select v-model="adjustForm.sku_id"
                @change="loadCurrentQty"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                <option value="">全部 SKU</option>
                <option v-for="s in productSkus[adjustForm.product_id]" :key="s.id" :value="s.id">
                  {{ s.sku_key || ('#' + s.id) }}
                </option>
              </select>
            </div>

            <!-- 当前数量（只读） -->
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">当前库存</label>
              <input :value="adjustForm.current_qty" type="number" readonly
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-text-secondary font-mono" />
            </div>

            <!-- 新数量 -->
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">
                调整后数量 <span class="text-danger">*</span>
              </label>
              <input v-model.number="adjustForm.new_qty" type="number" min="0" step="1"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none font-mono" />
              <!-- 差异预览 -->
              <div v-if="adjustForm.new_qty != null && adjustForm.current_qty != null" class="mt-2 text-sm">
                <span class="text-text-secondary">差异：</span>
                <span :class="(adjustForm.new_qty - adjustForm.current_qty) > 0 ? 'text-green-600 font-medium' : ((adjustForm.new_qty - adjustForm.current_qty) < 0 ? 'text-red-600 font-medium' : 'text-text-secondary')">
                  {{ adjustForm.current_qty }} →
                  <span class="text-text-primary font-bold">{{ adjustForm.new_qty }}</span>
                  （{{ (adjustForm.new_qty - adjustForm.current_qty) > 0 ? '+' : '' }}{{ adjustForm.new_qty - adjustForm.current_qty }}）
                </span>
              </div>
            </div>

            <!-- 原因 -->
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">
                调整原因 <span class="text-danger">*</span>
              </label>
              <textarea v-model="adjustForm.reason" rows="3"
                placeholder="例如：盘点发现多3件 / 系统数据有误纠正 / 补单"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none resize-none"></textarea>
            </div>

            <!-- 错误提示 -->
            <div v-if="adjustError" class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
              {{ adjustError }}
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 px-6 py-4 border-t bg-gray-50">
            <button @click="closeAdjustForm"
              class="border border-gray-200 hover:bg-gray-100 text-text-secondary px-4 py-2 rounded-lg text-sm">
              取消
            </button>
            <button @click="submitAdjust" :disabled="adjustSubmitting"
              class="bg-orange-600 hover:bg-orange-700 disabled:bg-orange-300 text-white px-4 py-2 rounded-lg text-sm font-medium">
              {{ adjustSubmitting ? '提交中...' : '确认调整' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ─── Detail Modal ───────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showDetail && detailRecord" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30" @click="showDetail = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[80vh] flex flex-col">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="text-lg font-bold text-text-primary">{{ $t('inout.recordDetail') }}</h3>
            <button @click="showDetail = false" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>
          <div class="overflow-y-auto flex-1 p-6 space-y-4 text-sm">
            <div class="grid grid-cols-2 gap-x-4 gap-y-2">
              <div>
                <span class="text-text-secondary">{{ $t('inout.recordNoLabel') }}</span>
                <p class="font-mono font-medium text-primary mt-0.5">{{ detailRecord.record_no }}</p>
              </div>
              <div>
                <span class="text-text-secondary">{{ $t('inout.dateLabel') }}</span>
                <p class="mt-0.5">{{ formatDate(detailRecord.created_at) }}</p>
              </div>
              <div>
                <span class="text-text-secondary">{{ $t('inout.warehouseLabel') }}</span>
                <p class="mt-0.5">{{ detailRecord.warehouse_name || '—' }}</p>
              </div>
              <div>
                <span class="text-text-secondary">{{ partyLabel }}</span>
                <p class="mt-0.5">{{ detailRecord.supplier || detailRecord.customer || detailRecord.source || '—' }}</p>
              </div>
              <div>
                <span class="text-text-secondary">{{ $t('inout.totalQtyLabel') }}</span>
                <p class="mt-0.5 font-medium">{{ detailRecord.total_qty }}</p>
              </div>
              <div>
                <span class="text-text-secondary">{{ $t('inout.statusLabel') }}</span>
                <p class="mt-0.5">
                  <StatusTag
                    :type="statusMap[detailRecord.status]?.type || 'info'"
                    :text="statusMap[detailRecord.status]?.text || detailRecord.status"
                  />
                </p>
              </div>
              <div v-if="detailRecord.operator" class="col-span-2">
                <span class="text-text-secondary">{{ $t('inout.operatorLabel') }}</span>
                <p class="mt-0.5">{{ detailRecord.operator }}</p>
              </div>
              <div v-if="detailRecord.remark" class="col-span-2">
                <span class="text-text-secondary">{{ $t('inout.remarkDetailLabel') }}</span>
                <p class="mt-0.5">{{ detailRecord.remark }}</p>
              </div>
            </div>

            <!-- Items list -->
            <div v-if="detailRecord.items && detailRecord.items.length">
              <p class="text-text-secondary mb-2">{{ $t('inout.itemDetailLabel') }}</p>
              <div class="border border-gray-200 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('inout.productCol') }}</th>
                      <th class="px-3 py-2 w-16"></th>
                      <th class="px-3 py-2 text-center text-xs font-medium text-text-secondary">{{ $t('inout.skuCol') }}</th>
                      <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">规格</th>
                      <th class="px-3 py-2 text-center text-xs font-medium text-text-secondary">{{ $t('inout.qtyCol') }}</th>
                      <th v-if="detailRecord.items.some(i => i.qrcode_id)" class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('inout.qrCodeCol') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(item, idx) in detailRecord.items"
                      :key="idx"
                      class="border-t border-gray-100"
                    >
                      <td class="px-3 py-2">
                        <div class="font-medium text-sm">{{ item.product_name || item.name || '—' }}</div>
                      </td>
                      <td class="px-3 py-2">
                        <img
                          v-if="item.image_main"
                          :src="item.image_main"
                          class="w-10 h-10 object-cover rounded border border-gray-200"
                          @error="$event.target.style.display='none'"
                        />
                        <div v-else class="w-10 h-10 bg-gray-100 rounded border border-gray-200 flex items-center justify-center">
                          <span class="material-symbols-outlined text-gray-300 text-sm">image</span>
                        </div>
                      </td>
                      <td class="px-3 py-2 text-center font-mono text-xs">{{ item.sku || item.sku_code || '—' }}</td>
                      <td class="px-3 py-2 text-text-secondary text-sm">{{ formatSkuLabel(item.sku_specs ? { specs: item.sku_specs } : item) || '—' }}</td>
                      <td class="px-3 py-2 text-center font-medium">{{ item.quantity }}</td>
                      <td v-if="detailRecord.items.some(i => i.qrcode_id)" class="px-3 py-2 font-mono text-xs">{{ item.qrcode_id || '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="px-6 py-4 border-t flex justify-between gap-3">
            <button
              v-if="userStore.canAccess('inventory:delete')"
              @click="handleDeleteRecord"
              class="flex items-center gap-2 px-4 py-2 border border-danger text-danger hover:bg-red-50 rounded-lg text-sm font-medium transition-colors"
            >
              <span class="material-symbols-outlined text-[16px]">delete</span>
              {{ $t('inout.deleteRecord') }}
            </button>
            <div class="flex gap-3 ml-auto">
              <button
                @click="openPrintPreview(detailRecord); showDetail = false"
                class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm text-text-primary hover:bg-gray-50 transition-colors"
              >
                <span class="material-symbols-outlined text-[16px]">print</span>
                {{ $t('inout.print') }}
              </button>
              <button
                @click="showDetail = false"
                class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium transition-colors"
              >
                {{ $t('common.close') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ─── Print Preview Modal ─────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showPrintPreview && printRecord" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30" @click="closePrintPreview"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="text-lg font-bold text-text-primary">{{ $t('inout.printPreview') || '打印预览' }}</h3>
            <button @click="closePrintPreview" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <!-- Editable fields -->
          <div class="overflow-y-auto flex-1 p-6 space-y-4 border-b">
            <div class="text-sm text-text-secondary mb-3">{{ $t('inout.printEditHint') || '可编辑打印内容' }}</div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-text-secondary mb-1">
                  {{ activeTab === 'outbound' ? $t('inout.printCustomer') : $t('inout.printSupplier') }}
                </label>
                <input
                  v-model="printForm.customer"
                  type="text"
                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                />
              </div>
              <div>
                <label class="block text-xs font-medium text-text-secondary mb-1">{{ $t('inout.printOperator') }}</label>
                <input
                  v-model="printForm.operator"
                  type="text"
                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                />
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-text-secondary mb-1">{{ $t('inout.printRemark') }}</label>
              <textarea
                v-model="printForm.remark"
                rows="2"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm resize-none focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              ></textarea>
            </div>
            <div class="flex gap-6">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="printForm.includeSignature" class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary" />
                <span class="text-sm text-text-primary">{{ $t('inout.printIncludeSignature') || '包含签名栏' }}</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="printForm.includeQrcode" class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary" />
                <span class="text-sm text-text-primary">{{ $t('inout.printIncludeQrcode') || '包含二维码' }}</span>
              </label>
            </div>
          </div>

          <!-- Print preview area -->
          <div class="overflow-y-auto max-h-[50vh] p-6 bg-gray-50">
            <div id="print-preview-area">
              <template v-if="printRecord">
                <div style="width:148mm; padding:12mm; font-family:sans-serif; font-size:12px; color:#111; background:white; border:1px solid #ddd;">
                  <!-- Header -->
                  <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #111; padding-bottom:8px; margin-bottom:12px;">
                    <div>
                      <h1 style="font-size:18px; font-weight:bold; margin:0 0 4px 0;">3号仓库</h1>
                      <p style="margin:0; font-size:11px; color:#555;">
                        {{ activeTab === 'inbound' ? '入库单' : activeTab === 'outbound' ? '出库单' : '退货单' }}
                      </p>
                    </div>
                    <div style="text-align:right; font-size:11px; color:#555;">
                      <p style="margin:0;">单号: {{ printRecord.record_no }}</p>
                      <p style="margin:4px 0 0 0;">日期: {{ formatDate(printRecord.created_at) }}</p>
                    </div>
                  </div>

                  <!-- Info grid -->
                  <table style="width:100%; border-collapse:collapse; margin-bottom:12px;">
                    <tbody>
                      <tr>
                        <td style="padding:3px 6px 3px 0; color:#555; width:30%;">仓库</td>
                        <td style="padding:3px 0;">{{ printRecord.warehouse_name || '—' }}</td>
                        <td style="padding:3px 6px 3px 12px; color:#555; width:30%;">
                          {{ activeTab === 'inbound' ? '供应商' : activeTab === 'outbound' ? '客户' : '来源' }}
                        </td>
                        <td style="padding:3px 0;">{{ printForm.customer || printRecord.customer || printRecord.supplier || printRecord.source || '—' }}</td>
                      </tr>
                      <tr>
                        <td style="padding:3px 6px 3px 0; color:#555;">操作员</td>
                        <td style="padding:3px 0;">{{ printForm.operator || operatorName || '—' }}</td>
                        <td style="padding:3px 6px 3px 12px; color:#555;">总数量</td>
                        <td style="padding:3px 0; font-weight:bold;">{{ printRecord.total_qty }}</td>
                      </tr>
                      <tr v-if="printForm.remark">
                        <td style="padding:3px 6px 3px 0; color:#555;">备注</td>
                        <td colspan="3" style="padding:3px 0;">{{ printForm.remark }}</td>
                      </tr>
                    </tbody>
                  </table>

                  <!-- Items table -->
                  <table style="width:100%; border-collapse:collapse; margin-bottom:16px;">
                    <thead>
                      <tr style="background:#f3f4f6;">
                        <th style="padding:6px 8px; text-align:left; border:1px solid #ddd; font-size:11px;">商品名称</th>
                        <th style="padding:6px 8px; text-align:center; border:1px solid #ddd; font-size:11px;">SKU</th>
                        <th style="padding:6px 8px; text-align:center; border:1px solid #ddd; font-size:11px;">数量</th>
                        <th v-if="printForm.includeQrcode && printRecord.items && printRecord.items.some(i => i.qrcode_id)" style="padding:6px 8px; text-align:left; border:1px solid #ddd; font-size:11px;">二维码</th>
                      </tr>
                    </thead>
                    <tbody>
                      <template v-if="printRecord.items && printRecord.items.length">
                        <tr v-for="(item, idx) in printRecord.items" :key="idx">
                          <td style="padding:6px 8px; border:1px solid #ddd;">{{ item.product_name || item.name || '—' }}</td>
                          <td style="padding:6px 8px; border:1px solid #ddd; text-align:center; font-family:monospace; font-size:10px;">{{ item.sku || '—' }}</td>
                          <td style="padding:6px 8px; border:1px solid #ddd; text-align:center; font-weight:bold;">{{ item.quantity }}</td>
                          <td v-if="printForm.includeQrcode && printRecord.items.some(i => i.qrcode_id)" style="padding:6px 8px; border:1px solid #ddd; font-family:monospace; font-size:10px;">{{ item.qrcode_id || '—' }}</td>
                        </tr>
                      </template>
                      <tr v-else>
                        <td colspan="4" style="padding:6px 8px; border:1px solid #ddd; text-align:center; color:#999;">暂无商品明细</td>
                      </tr>
                    </tbody>
                  </table>

                  <!-- Signature row -->
                  <div v-if="printForm.includeSignature" style="display:flex; gap:32px; margin-top:24px; padding-top:12px; border-top:1px dashed #ccc;">
                    <div style="flex:1;">
                      <p style="margin:0 0 24px 0; color:#555; font-size:11px;">制单人</p>
                      <div style="border-bottom:1px solid #aaa; margin-bottom:4px;"></div>
                      <p style="margin:0; font-size:10px; color:#888; text-align:center;">签字</p>
                    </div>
                    <div style="flex:1;">
                      <p style="margin:0 0 24px 0; color:#555; font-size:11px;">审核人</p>
                      <div style="border-bottom:1px solid #aaa; margin-bottom:4px;"></div>
                      <p style="margin:0; font-size:10px; color:#888; text-align:center;">签字</p>
                    </div>
                    <div style="flex:1;">
                      <p style="margin:0 0 24px 0; color:#555; font-size:11px;">收货人</p>
                      <div style="border-bottom:1px solid #aaa; margin-bottom:4px;"></div>
                      <p style="margin:0; font-size:10px; color:#888; text-align:center;">签字</p>
                    </div>
                  </div>

                  <p style="margin-top:16px; font-size:10px; color:#aaa; text-align:center;">
                    3号仓库 {{ new Date().toLocaleString('zh-CN') }}
                  </p>
                </div>
              </template>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t flex gap-3 justify-end bg-white rounded-b-xl">
            <button
              @click="closePrintPreview"
              class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-text-primary hover:bg-gray-50 transition-colors"
            >
              {{ $t('common.cancel') }}
            </button>
            <button
              @click="confirmPrint"
              class="flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium transition-colors"
            >
              <span class="material-symbols-outlined text-[18px]">print</span>
              {{ $t('inout.confirmPrint') || '确认打印' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ─── Print Area (hidden on screen, visible on print) ───────────────── -->
    <div id="print-area" class="hidden print:block">
      <template v-if="printRecord">
        <div style="width:148mm; min-height:210mm; padding:12mm; font-family:sans-serif; font-size:12px; color:#111;">
          <!-- Header -->
          <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #111; padding-bottom:8px; margin-bottom:12px;">
            <div>
              <h1 style="font-size:18px; font-weight:bold; margin:0 0 4px 0;">{{ $t('inout.printSystemName') }}</h1>
              <p style="margin:0; font-size:11px; color:#555;">
                {{
                  activeTab === 'inbound' ? $t('inout.inboundSlip') :
                  activeTab === 'outbound' ? $t('inout.outboundSlip') : $t('inout.returnSlip')
                }}
              </p>
            </div>
            <div style="text-align:right; font-size:11px; color:#555;">
              <p style="margin:0;">{{ $t('inout.printRecordNo') }}: {{ printRecord.record_no }}</p>
              <p style="margin:4px 0 0 0;">{{ $t('inout.printDate') }}: {{ formatDate(printRecord.created_at) }}</p>
            </div>
          </div>

          <!-- Info grid -->
          <table style="width:100%; border-collapse:collapse; margin-bottom:12px;">
            <tbody>
              <tr>
                <td style="padding:3px 6px 3px 0; color:#555; width:30%;">{{ $t('inout.printWarehouse') }}</td>
                <td style="padding:3px 0;">{{ printRecord.warehouse_name || '—' }}</td>
                <td style="padding:3px 6px 3px 12px; color:#555; width:30%;">
                  {{
                    activeTab === 'inbound' ? $t('inout.printSupplier') :
                    activeTab === 'outbound' ? $t('inout.printCustomer') : $t('inout.printSource')
                  }}
                </td>
                <td style="padding:3px 0;">{{ printRecord.supplier || printRecord.customer || printRecord.source || '—' }}</td>
              </tr>
              <tr>
                <td style="padding:3px 6px 3px 0; color:#555;">{{ $t('inout.printOperator') }}</td>
                <td style="padding:3px 0;">{{ printRecord.operator || operatorName || '—' }}</td>
                <td style="padding:3px 6px 3px 12px; color:#555;">{{ $t('inout.printTotalQty') }}</td>
                <td style="padding:3px 0; font-weight:bold;">{{ printRecord.total_qty }}</td>
              </tr>
              <tr v-if="printRecord.remark">
                <td style="padding:3px 6px 3px 0; color:#555;">{{ $t('inout.printRemark') }}</td>
                <td colspan="3" style="padding:3px 0;">{{ printRecord.remark }}</td>
              </tr>
            </tbody>
          </table>

          <!-- Items table -->
          <table style="width:100%; border-collapse:collapse; margin-bottom:16px;">
            <thead>
              <tr style="background:#f3f4f6;">
                <th style="padding:6px 8px; text-align:left; border:1px solid #ddd; font-size:11px;">{{ $t('inout.printProductName') }}</th>
                <th style="padding:6px 8px; text-align:center; border:1px solid #ddd; font-size:11px;">SKU</th>
                <th style="padding:6px 8px; text-align:center; border:1px solid #ddd; font-size:11px;">{{ $t('inout.printQty') }}</th>
                <th v-if="printRecord.items && printRecord.items.some(i => i.qrcode_id)" style="padding:6px 8px; text-align:left; border:1px solid #ddd; font-size:11px;">{{ $t('inout.printQrCode') }}</th>
              </tr>
            </thead>
            <tbody>
              <template v-if="printRecord.items && printRecord.items.length">
                <tr v-for="(item, idx) in printRecord.items" :key="idx">
                  <td style="padding:6px 8px; border:1px solid #ddd;">{{ item.product_name || item.name || '—' }}</td>
                  <td style="padding:6px 8px; border:1px solid #ddd; text-align:center; font-family:monospace; font-size:10px;">{{ item.sku || '—' }}</td>
                  <td style="padding:6px 8px; border:1px solid #ddd; text-align:center; font-weight:bold;">{{ item.quantity }}</td>
                  <td v-if="printRecord.items.some(i => i.qrcode_id)" style="padding:6px 8px; border:1px solid #ddd; font-family:monospace; font-size:10px;">{{ item.qrcode_id || '—' }}</td>
                </tr>
              </template>
              <tr v-else>
                <td colspan="4" style="padding:6px 8px; border:1px solid #ddd; text-align:center; color:#999;">{{ $t('inout.printNoItems') }}</td>
              </tr>
            </tbody>
          </table>

          <!-- Signature row -->
          <div style="display:flex; gap:32px; margin-top:24px; padding-top:12px; border-top:1px dashed #ccc;">
            <div style="flex:1;">
              <p style="margin:0 0 24px 0; color:#555; font-size:11px;">{{ $t('inout.printCreator') }}</p>
              <div style="border-bottom:1px solid #aaa; margin-bottom:4px;"></div>
              <p style="margin:0; font-size:10px; color:#888; text-align:center;">{{ $t('inout.printSignature') }}</p>
            </div>
            <div style="flex:1;">
              <p style="margin:0 0 24px 0; color:#555; font-size:11px;">{{ $t('inout.printReviewer') }}</p>
              <div style="border-bottom:1px solid #aaa; margin-bottom:4px;"></div>
              <p style="margin:0; font-size:10px; color:#888; text-align:center;">{{ $t('inout.printSignature') }}</p>
            </div>
            <div style="flex:1;">
              <p style="margin:0 0 24px 0; color:#555; font-size:11px;">{{ $t('inout.printReceiver') }}</p>
              <div style="border-bottom:1px solid #aaa; margin-bottom:4px;"></div>
              <p style="margin:0; font-size:10px; color:#888; text-align:center;">{{ $t('inout.printSignature') }}</p>
            </div>
          </div>

          <!-- Footer -->
          <p style="margin-top:16px; font-size:10px; color:#aaa; text-align:center;">
            {{ $t('inout.printFooter') }} {{ new Date().toLocaleString() }}
          </p>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
@media print {
  body > * { display: none; }
  #print-area, #print-preview-area { display: block !important; }
  #print-preview-area > * { display: block !important; }
}

@media (max-width: 768px) {
  /* 表格横向滚动 & 单元格 */
  .overflow-x-auto {
    margin: 0 -1rem;
    padding: 0 1rem;
  }
  table.text-left.text-sm th,
  table.text-left.text-sm td {
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
  }
  table.text-left.text-sm th {
    font-size: 0.65rem;
  }
  /* 操作列按钮 */
  table.text-left.text-sm td button {
    font-size: 0.7rem;
    margin-right: 0.5rem;
  }
  /* Tabs 区域 */
  .flex.flex-wrap.border-b.border-gray-100 {
    padding: 0;
  }
  .flex.flex-wrap.border-b.border-gray-100 button {
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
  }
  .flex.items-center.gap-2.bg-primary {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
  }
  /* 模态框 */
  .fixed.inset-0.z-50 .bg-white.rounded-xl {
    max-width: 100%;
    margin: 1rem;
    max-height: calc(100vh - 2rem);
  }
  /* 表单 grid */
  .grid.grid-cols-1.sm\:grid-cols-2.gap-4 {
    grid-template-columns: 1fr;
  }
  .grid.grid-cols-2.gap-x-4.gap-y-2 {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }
  /* 模态框内边距 */
  .p-6 {
    padding: 1rem;
  }
  /* 按钮缩小 */
  button.px-4.py-2,
  button.px-4.py-2.rounded-lg {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
  }
  /* 分页 */
  .text-sm.text-text-secondary {
    font-size: 0.75rem;
  }
  /* 详情模态框 */
  .max-w-lg {
    max-width: calc(100% - 2rem);
  }
  /* 打印预览模态框 */
  .max-w-2xl {
    max-width: calc(100% - 2rem);
  }
}
</style>
