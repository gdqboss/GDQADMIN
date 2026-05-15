<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import api from '../../services/api.js'
import { useUserStore } from '../../stores/user.js'

const { t } = useI18n()
const userStore = useUserStore()

// ─── State ───────────────────────────────────────────────────────────────────
const activeTab = ref('inbound')
const showForm = ref(false)
const submitting = ref(false)
const formError = ref('')
const formSuccess = ref('')

const inboundRecords = ref([])
const outboundRecords = ref([])
const returnRecords = ref([])
const warehouses = ref([])
const products = ref([])

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
  items: [{ product_id: '', quantity: 1, qrcode_id: '' }],
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
const tabs = computed(() => [
  { key: 'inbound',  label: t('inout.inbound'),  icon: 'input',  count: inboundRecords.value.length },
  { key: 'outbound', label: t('inout.outbound'), icon: 'output', count: outboundRecords.value.length },
  { key: 'return',   label: t('inout.returns'),  icon: 'undo',   count: returnRecords.value.length },
])

const currentRecords = computed(() => {
  if (activeTab.value === 'inbound')  return inboundRecords.value
  if (activeTab.value === 'outbound') return outboundRecords.value
  return returnRecords.value
})

const partyLabel = computed(() => {
  if (activeTab.value === 'inbound')  return t('inout.supplier')
  if (activeTab.value === 'outbound') return t('inout.customer')
  return t('inout.source')
})

const modalTitle = computed(() => {
  if (activeTab.value === 'inbound')  return t('inout.newInbound')
  if (activeTab.value === 'outbound') return t('inout.newOutbound')
  return t('inout.newReturn')
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

const isAdmin = computed(() => userStore.userRole === 'admin')

// ─── Helpers ──────────────────────────────────────────────────────────────────
function formatDate(str) {
  if (!str) return ''
  return String(str).slice(0, 16)
}

function productById(id) {
  return products.value.find(p => String(p.id) === String(id)) || null
}

function needsQrcode(item) {
  if (activeTab.value !== 'outbound') return false
  const p = productById(item.product_id)
  return p?.require_qrcode === true || p?.require_qrcode === 1
}

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
    const res = await api.get('/products')
    products.value = res?.data?.list ?? res?.list ?? res?.data ?? []
  } catch (e) {
    console.error('Failed to load products', e)
  }
}

onMounted(async () => {
  await Promise.all([loadRecords(), loadWarehouses(), loadProducts()])
})

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
  form.value.items.push({ product_id: '', quantity: 1, qrcode_id: '' })
}

function removeItem(index) {
  if (form.value.items.length > 1) {
    form.value.items.splice(index, 1)
  }
}

async function submitForm() {
  formError.value = ''
  formSuccess.value = ''

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
    const validItems = form.value.items.filter(i => i.product_id && Number(i.quantity) > 0)
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
      // Normal mode
      const validItems = form.value.items.filter(i => i.product_id && Number(i.quantity) > 0)
      payload = {
        warehouse_id: form.value.warehouse_id,
        [partyKey]: form.value.party,
        remark: form.value.remark,
        operator: operatorName.value,
        items: validItems.map(i => {
          const item = { product_id: i.product_id, quantity: Number(i.quantity) }
          if (activeTab.value === 'outbound' && needsQrcode(i) && i.qrcode_id) {
            item.qrcode_id = i.qrcode_id
          }
          return item
        }),
      }
    }

    await api.post(endpoint, payload)
    formSuccess.value = t('inout.submitSuccess')
    await loadRecords()
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
function doPrint(record) {
  printRecord.value = record
  nextTick(() => {
    window.print()
  })
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

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">{{ $t('inout.recordNo') }}</th>
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
              <td colspan="8" class="px-4 py-12 text-center text-text-secondary text-sm">
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
                  @click="doPrint(r)"
                  class="text-text-secondary hover:text-text-primary text-xs font-medium mr-3"
                >
                  {{ $t('inout.print') }}
                </button>
                <button
                  v-if="isAdmin"
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
                      <th class="px-3 py-2 text-center text-xs font-medium text-text-secondary w-24">{{ $t('inout.qtyCol') }}</th>
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
                        <select
                          v-model="item.product_id"
                          class="w-full border border-gray-200 rounded px-2 py-1 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                        >
                          <option value="">{{ $t('inout.selectProduct') }}</option>
                          <option v-for="p in products" :key="p.id" :value="p.id">
                            {{ p.sku }} - {{ p.name }}
                          </option>
                        </select>
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
                      <th class="px-3 py-2 text-center text-xs font-medium text-text-secondary">{{ $t('inout.skuCol') }}</th>
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
                      <td class="px-3 py-2">{{ item.product_name || item.name || '—' }}</td>
                      <td class="px-3 py-2 text-center font-mono text-xs">{{ item.sku || '—' }}</td>
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
              v-if="isAdmin"
              @click="handleDeleteRecord"
              class="flex items-center gap-2 px-4 py-2 border border-danger text-danger hover:bg-red-50 rounded-lg text-sm font-medium transition-colors"
            >
              <span class="material-symbols-outlined text-[16px]">delete</span>
              {{ $t('inout.deleteRecord') }}
            </button>
            <div class="flex gap-3 ml-auto">
              <button
                @click="doPrint(detailRecord); showDetail = false"
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
  #print-area { display: block !important; }
}
</style>
