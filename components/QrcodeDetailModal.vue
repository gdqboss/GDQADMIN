<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import StatusTag from './StatusTag.vue'
import api from '../services/api.js'
import { useUserStore } from '../stores/user.js'

const { t } = useI18n()

const props = defineProps({ qrcodeId: { type: Number, default: null } })
const emit = defineEmits(['close', 'updated'])

const userStore = useUserStore()
const canEdit = computed(() => ['admin', 'manager'].includes(userStore.userRole))

const detail = ref(null)
const loading = ref(false)
const activeSection = ref('basic')
const saving = ref(false)

// Section-level editing: null | 'chain' | 'sale'
const editingSection = ref(null)
const editForm = ref({})

// Repair
const showRepairForm = ref(false)
const repairForm = ref({ repair_person: '', issue: '', solution: '', cost: 0, status: 'pending' })
const editingRepairId = ref(null)
const editingRepairForm = ref({})

// After-sale handling
const handlingAfterSaleId = ref(null)
const afterSaleHandleForm = ref({ handler: '', handler_note: '', status: 'resolved', channel_qrcodes: { telegram: '', whatsapp: '', wecom: '' } })

const suppliers = ref([])
const dealers = ref([])
const stores = ref([])
const saveError = ref('')

const sections = computed(() => [
  { key: 'basic', label: t('qrcode.basicInfo'), icon: 'info' },
  { key: 'chain', label: t('qrcode.supplyChain'), icon: 'account_tree' },
  { key: 'sale', label: t('qrcode.saleInfo'), icon: 'shopping_cart' },
  { key: 'repair', label: t('qrcode.repairRecords'), icon: 'build' },
  { key: 'scan', label: t('qrcode.scanLogs'), icon: 'qr_code_scanner' },
  { key: 'aftersale', label: t('qrcode.afterSaleRecords'), icon: 'support_agent' },
])

const repairStatusMap = computed(() => ({
  pending: { type: 'warning', text: t('qrcode.processingStatus') },
  in_progress: { type: 'primary', text: t('qrcode.startRepair') },
  completed: { type: 'success', text: t('qrcode.completeRepair') },
}))

const qrcodeStatusMap = computed(() => ({
  unused: { type: 'info', label: t('qrcode.unused') },
  bindProduct: { type: 'primary', label: t('qrcode.bound') },
  inStock: { type: 'primary', label: t('qrcode.inStock') },
  outStock: { type: 'warning', label: t('qrcode.outStock') },
  shipped: { type: 'warning', label: t('qrcode.shipped') },
  sold: { type: 'success', label: t('qrcode.sold') },
  activated: { type: 'success', label: t('qrcode.activated') },
  afterSale: { type: 'danger', label: t('qrcode.afterSale') },
  returned: { type: 'danger', label: t('qrcode.returned') },
  disabled: { type: 'danger', label: t('qrcode.disabled') },
}))

// 合法状态流转按钮配置
const statusActions = computed(() => {
  if (!detail.value) return []
  const s = detail.value.status
  const map = {
    unused: [],
    bindProduct: [
      { status: 'inStock', label: t('qrcode.confirmInStock'), icon: 'inventory_2', color: 'bg-blue-600 hover:bg-blue-700' },
      { status: 'unused', label: t('qrcode.revertToUnused'), icon: 'undo', color: 'bg-gray-500 hover:bg-gray-600' },
    ],
    inStock: [
      { status: 'outStock', label: t('qrcode.confirmOutStock'), icon: 'output', color: 'bg-orange-500 hover:bg-orange-600' },
      { status: 'bindProduct', label: t('qrcode.revertToBind'), icon: 'undo', color: 'bg-gray-500 hover:bg-gray-600' },
    ],
    outStock: [
      { status: 'shipped', label: t('qrcode.confirmShipped'), icon: 'local_shipping', color: 'bg-orange-500 hover:bg-orange-600' },
      { status: 'inStock', label: t('qrcode.revertToInStock'), icon: 'undo', color: 'bg-gray-500 hover:bg-gray-600' },
    ],
    shipped: [
      { status: 'sold', label: t('qrcode.confirmSale'), icon: 'shopping_cart', color: 'bg-green-600 hover:bg-green-700' },
    ],
    sold: [
      { status: 'activated', label: t('qrcode.activated'), icon: 'verified', color: 'bg-green-600 hover:bg-green-700' },
    ],
    activated: [],
    afterSale: [
      { status: 'returned', label: t('qrcode.returned'), icon: 'assignment_return', color: 'bg-red-500 hover:bg-red-600' },
    ],
    returned: [
      { status: 'inStock', label: t('qrcode.returnToInStock'), icon: 'inventory_2', color: 'bg-blue-600 hover:bg-blue-700' },
    ],
    disabled: [],
  }
  const actions = map[s] || []
  // 所有非 disabled 状态都可以禁用
  if (s !== 'disabled') {
    actions.push({ status: 'disabled', label: t('qrcode.markDisabled'), icon: 'block', color: 'bg-red-600 hover:bg-red-700' })
  }
  return actions
})

async function changeStatus(newStatus) {
  if (!confirm(`${qrcodeStatusMap.value[detail.value.status]?.label} → ${qrcodeStatusMap.value[newStatus]?.label}?`)) return
  saving.value = true
  try {
    await api.put(`/qrcodes/${props.qrcodeId}/status`, { status: newStatus })
    await loadDetail(props.qrcodeId)
    emit('updated')
  } catch (e) {
    alert(e?.response?.data?.message || e?.message || 'Failed')
  } finally { saving.value = false }
}

async function loadDetail(id) {
  loading.value = true
  editingSection.value = null
  editingRepairId.value = null
  handlingAfterSaleId.value = null
  try {
    const [detailRes, suppRes, dealerRes, storeRes] = await Promise.all([
      api.get(`/qrcodes/${id}/detail`),
      api.get('/suppliers'),
      api.get('/dealers'),
      api.get('/stores'),
    ])
    if (detailRes.code === 0) detail.value = detailRes.data
    if (suppRes.code === 0) suppliers.value = Array.isArray(suppRes.data) ? suppRes.data : (suppRes.data?.list || [])
    if (dealerRes.code === 0) dealers.value = Array.isArray(dealerRes.data) ? dealerRes.data : (dealerRes.data?.list || [])
    if (storeRes.code === 0) stores.value = Array.isArray(storeRes.data) ? storeRes.data : (storeRes.data?.list || [])
  } finally { loading.value = false }
}

watch(() => props.qrcodeId, async (id) => {
  if (!id) { detail.value = null; return }
  await loadDetail(id)
}, { immediate: true })

// ---- Supply chain edit ----
function startChainEdit() {
  editForm.value = {
    supplier_id: detail.value.supplier_id ?? '',
    dealer_id: detail.value.dealer_id ?? '',
    store_id: detail.value.store_id ?? '',
    inbound_by: detail.value.inbound_by ?? '',
    outbound_by: detail.value.outbound_by ?? '',
  }
  editingSection.value = 'chain'
}

// ---- Sale info edit ----
function startSaleEdit() {
  editForm.value = {
    buyer: detail.value.buyer ?? '',
    buy_date: detail.value.buy_date ? detail.value.buy_date.slice(0, 10) : '',
    warranty_period: detail.value.warranty_period ?? '',
    warranty_unit: detail.value.warranty_unit ?? 'year',
    sales_person: detail.value.sales_person ?? '',
    warehouse: detail.value.warehouse ?? '',
    after_sale_contact: detail.value.after_sale_contact ?? '',
  }
  editingSection.value = 'sale'
}

async function saveSection() {
  saving.value = true
  saveError.value = ''
  try {
    const payload = {}
    for (const [k, v] of Object.entries(editForm.value)) {
      payload[k] = v === '' ? null : v
    }
    await api.put(`/qrcodes/${props.qrcodeId}/detail`, payload)
    editingSection.value = null
    const res = await api.get(`/qrcodes/${props.qrcodeId}/detail`)
    if (res.code === 0) { detail.value = res.data; emit('updated') }
  } catch (e) {
    saveError.value = e?.message || t('common.saveFailed')
  } finally { saving.value = false }
}

function cancelEdit() {
  editingSection.value = null
  editForm.value = {}
}

// ---- Repair ----
async function addRepair() {
  await api.post(`/qrcodes/${props.qrcodeId}/repair`, { ...repairForm.value })
  showRepairForm.value = false
  repairForm.value = { repair_person: '', issue: '', solution: '', cost: 0, status: 'pending' }
  const res = await api.get(`/qrcodes/${props.qrcodeId}/detail`)
  if (res.code === 0) detail.value = res.data
}

async function advanceRepairStatus(r) {
  const next = r.status === 'pending' ? 'in_progress' : 'completed'
  await api.put(`/qrcodes/${props.qrcodeId}/repair/${r.id}`, { status: next })
  const res = await api.get(`/qrcodes/${props.qrcodeId}/detail`)
  if (res.code === 0) detail.value = res.data
}

function startRepairEdit(r) {
  editingRepairId.value = r.id
  editingRepairForm.value = {
    repair_person: r.repair_person ?? '',
    issue: r.issue ?? '',
    solution: r.solution ?? '',
    cost: r.cost ?? 0,
  }
}

async function saveRepairEdit() {
  saving.value = true
  try {
    await api.put(`/qrcodes/${props.qrcodeId}/repair/${editingRepairId.value}`, editingRepairForm.value)
    editingRepairId.value = null
    const res = await api.get(`/qrcodes/${props.qrcodeId}/detail`)
    if (res.code === 0) detail.value = res.data
  } finally { saving.value = false }
}

// ---- After-sale handling ----
function openAfterSaleHandle(r) {
  handlingAfterSaleId.value = r.id
  const existing = r.channel_qrcodes || {}
  afterSaleHandleForm.value = {
    handler: userStore.userName || '',
    handler_note: r.handler_note || '',
    status: 'resolved',
    channel_qrcodes: {
      telegram: existing.telegram || '',
      whatsapp: existing.whatsapp || '',
      wecom: existing.wecom || '',
    },
  }
}

async function saveAfterSaleHandle() {
  saving.value = true
  try {
    await api.put(`/qrcodes/after-sale/${handlingAfterSaleId.value}`, afterSaleHandleForm.value)
    handlingAfterSaleId.value = null
    const res = await api.get(`/qrcodes/${props.qrcodeId}/detail`)
    if (res.code === 0) detail.value = res.data
  } finally { saving.value = false }
}

async function handleChannelQrUpload(channel, event) {
  const file = event.target.files?.[0]
  if (!file) return
  const fd = new FormData()
  fd.append('file', file)
  try {
    const res = await api.post('/upload', fd)
    if (res.code === 0) {
      afterSaleHandleForm.value.channel_qrcodes[channel] = res.data.url
    }
  } catch (e) {
    console.error('Upload failed:', e)
  }
  event.target.value = ''
}

function removeChannelQr(channel) {
  afterSaleHandleForm.value.channel_qrcodes[channel] = ''
}

const channelQrPreview = ref('')

// Inline channel qrcode editing
const editingChannelQrId = ref(null)
const channelQrForm = ref({ telegram: '', whatsapp: '', wecom: '' })

function startEditChannelQr(r) {
  if (editingChannelQrId.value === r.id) return
  editingChannelQrId.value = r.id
  const existing = r.channel_qrcodes || {}
  channelQrForm.value = {
    telegram: existing.telegram || '',
    whatsapp: existing.whatsapp || '',
    wecom: existing.wecom || '',
  }
}

async function handleChannelQrUploadInline(channel, event, r) {
  const file = event.target.files?.[0]
  if (!file) return
  const fd = new FormData()
  fd.append('file', file)
  try {
    const res = await api.post('/upload', fd)
    if (res.code === 0) {
      startEditChannelQr(r)
      channelQrForm.value[channel] = res.data.url
    }
  } catch (e) {
    console.error('Upload failed:', e)
  }
  event.target.value = ''
}

async function saveChannelQrcodes(r) {
  saving.value = true
  try {
    await api.put(`/qrcodes/after-sale/${r.id}`, {
      handler: r.handler || '',
      handler_note: r.handler_note || '',
      status: r.status,
      channel_qrcodes: channelQrForm.value
    })
    editingChannelQrId.value = null
    const res = await api.get(`/qrcodes/${props.qrcodeId}/detail`)
    if (res.code === 0) detail.value = res.data
  } finally { saving.value = false }
}

function warrantyClass(date) {
  if (!date) return ''
  return new Date(date) < new Date() ? 'text-danger font-medium' : 'text-success font-medium'
}

const saleFields = computed(() => [
  { label: t('qrcode.buyerLabel'), key: 'buyer', type: 'text', hint: t('qrcode.buyerHint') },
  { label: t('qrcode.buyDate'), key: 'buy_date', type: 'date', hint: '' },
  { label: t('qrcode.warrantyPeriod'), key: 'warranty_period', type: 'number', hint: t('qrcode.warrantyPeriodHint') },
  { label: t('qrcode.warrantyUnit'), key: 'warranty_unit', type: 'select', hint: '', options: [
    { value: 'day', label: t('qrcode.day') },
    { value: 'month', label: t('qrcode.month') },
    { value: 'year', label: t('qrcode.year') }
  ]},
  { label: t('qrcode.salesPerson'), key: 'sales_person', type: 'text', hint: t('qrcode.salesPersonHint') },
  { label: t('qrcode.warehouseLabel'), key: 'warehouse', type: 'text', hint: '' },
  { label: t('qrcode.afterSaleContact'), key: 'after_sale_contact', type: 'text', hint: t('qrcode.afterSaleContactHint') },
])
</script>

<template>
  <div v-if="qrcodeId" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" @click="$emit('close')"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[92vh] flex flex-col">

      <!-- Header -->
      <div class="flex items-center justify-between px-5 py-3 border-b bg-gray-50 rounded-t-xl gap-3">
        <div class="flex items-center gap-3 min-w-0">
          <img v-if="detail?.code" :src="`/uploads/qrcodes/${detail.code}.png`"
            class="w-14 h-14 shrink-0 rounded border border-gray-200 bg-white" :alt="detail.code" />
          <span v-else class="material-symbols-outlined text-primary text-[40px] shrink-0">qr_code_2</span>
          <div class="min-w-0">
            <h2 class="font-bold text-text-primary text-base truncate">{{ detail?.code || $t('common.loading') }}</h2>
            <div class="flex items-center gap-2 mt-0.5">
              <StatusTag v-if="detail" :type="qrcodeStatusMap[detail.status]?.type" :text="qrcodeStatusMap[detail.status]?.label" />
              <span v-if="detail?.product_name" class="text-xs text-text-secondary truncate">{{ detail.product_name }}</span>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <a v-if="detail?.code" :href="`/uploads/qrcodes/${detail.code}.png`" :download="`${detail.code}.png`"
            class="flex items-center gap-1 px-3 py-1.5 bg-gray-100 text-text-secondary rounded-lg text-sm hover:bg-gray-200 transition-colors">
            <span class="material-symbols-outlined text-[16px]">download</span>{{ $t('common.download') }}
          </a>
          <button @click="$emit('close')" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex-1 flex items-center justify-center text-text-secondary">
        <span class="material-symbols-outlined animate-spin text-[32px] text-primary">refresh</span>
      </div>

      <div v-else-if="detail" class="flex flex-1 overflow-hidden">
        <!-- Left nav -->
        <div class="w-32 border-r bg-gray-50 flex flex-col py-2 shrink-0">
          <button v-for="sec in sections" :key="sec.key"
            @click="activeSection = sec.key; editingSection = null"
            :class="['flex items-center gap-1.5 px-2 py-2.5 text-sm transition-colors mx-1 rounded-lg',
              activeSection === sec.key ? 'bg-primary text-white font-medium' : 'text-text-secondary hover:text-text-primary hover:bg-gray-100']">
            <span class="material-symbols-outlined text-[15px]">{{ sec.icon }}</span>
            <span class="text-xs">{{ sec.label }}</span>
            <!-- dot indicator for non-empty sub-records -->
            <span v-if="sec.key === 'repair' && detail.repairRecords?.length"
              class="ml-auto w-4 h-4 rounded-full bg-warning/80 text-white text-[10px] flex items-center justify-center">
              {{ detail.repairRecords.length }}
            </span>
            <span v-if="sec.key === 'aftersale' && detail.afterSaleRecords?.length"
              class="ml-auto w-4 h-4 rounded-full bg-danger/80 text-white text-[10px] flex items-center justify-center">
              {{ detail.afterSaleRecords.length }}
            </span>
          </button>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-5">

          <!-- Basic Info -->
          <div v-if="activeSection === 'basic'">
            <h3 class="section-title">
              <span class="material-symbols-outlined text-primary text-[18px]">info</span>{{ $t('qrcode.basicInfo') }}
              <span class="ml-auto text-xs text-text-secondary font-normal">{{ $t('common.readOnly') }}</span>
            </h3>
            <div class="grid grid-cols-2 gap-3">
              <div class="info-cell"><p class="info-label">{{ $t('qrcode.codeId') }}</p><p class="font-mono text-sm text-primary font-medium">{{ detail.code }}</p></div>
              <div class="info-cell"><p class="info-label">{{ $t('common.status') }}</p><StatusTag :type="qrcodeStatusMap[detail.status]?.type" :text="qrcodeStatusMap[detail.status]?.label" /></div>
              <div class="info-cell"><p class="info-label">{{ $t('qrcode.productName') }}</p><p class="text-sm">{{ detail.product_name || '-' }}</p></div>
              <div class="info-cell"><p class="info-label">{{ $t('qrcode.productSku2') }}</p><p class="font-mono text-sm">{{ detail.sku || '-' }}</p></div>
              <div class="info-cell"><p class="info-label">{{ $t('qrcode.productSpec') }}</p><p class="text-sm">{{ detail.spec || '-' }}</p></div>
              <div class="info-cell"><p class="info-label">{{ $t('qrcode.category') }}</p><p class="text-sm">{{ detail.category || '-' }}</p></div>
              <div class="info-cell"><p class="info-label">{{ $t('common.createdAt') }}</p><p class="text-sm">{{ detail.created_at?.slice(0,19) || '-' }}</p></div>
              <div class="info-cell"><p class="info-label">{{ $t('qrcode.boundAt') }}</p><p class="text-sm">{{ detail.bound_at?.slice(0,19) || '-' }}</p></div>
              <div class="info-cell col-span-2"><p class="info-label">{{ $t('qrcode.scanTotal') }}</p><p class="text-lg font-bold text-primary">{{ detail.scan_count }} {{ $t('qrcode.times') }}</p></div>
            </div>
            <!-- 状态操作按钮 -->
            <div v-if="canEdit && statusActions.length" class="mt-4 flex flex-wrap gap-2">
              <button v-for="act in statusActions" :key="act.status"
                @click="changeStatus(act.status)" :disabled="saving"
                :class="[act.color, 'text-white px-3 py-1.5 rounded-lg text-xs font-medium flex items-center gap-1 transition-colors disabled:opacity-50']">
                <span class="material-symbols-outlined text-[14px]">{{ act.icon }}</span>
                {{ act.label }}
              </button>
            </div>
          </div>

          <!-- Supply Chain -->
          <div v-if="activeSection === 'chain'">
            <h3 class="section-title">
              <span class="material-symbols-outlined text-primary text-[18px]">account_tree</span>{{ $t('qrcode.supplyChain') }}
              <button v-if="canEdit && editingSection !== 'chain'" @click="startChainEdit"
                class="ml-auto edit-btn">
                <span class="material-symbols-outlined text-[15px]">edit</span>{{ $t('common.edit') }}
              </button>
              <template v-else-if="editingSection === 'chain'">
                <button @click="saveSection" :disabled="saving" class="ml-auto save-btn">{{ saving ? $t('common.saving') : $t('common.saveChanges') }}</button>
                <button @click="cancelEdit" class="cancel-btn">{{ $t('common.cancel') }}</button>
              </template>
            </h3>
            <div v-if="saveError && editingSection === 'chain'" class="mb-3 px-3 py-2 bg-danger/10 text-danger text-sm rounded-lg flex items-center gap-2">
              <span class="material-symbols-outlined text-[16px]">error</span>{{ saveError }}
            </div>

            <!-- Supplier -->
            <div class="sub-card">
              <h4 class="sub-title"><span class="material-symbols-outlined text-[15px]">local_shipping</span>{{ $t('qrcode.supplierSection') }}</h4>
              <div v-if="editingSection !== 'chain'" class="grid grid-cols-2 gap-3">
                <div><p class="info-label">{{ $t('common.name') }}</p><p class="text-sm" :class="detail.supplier_name ? '' : 'text-text-secondary italic'">{{ detail.supplier_name || $t('qrcode.unlinked') }}</p></div>
                <div><p class="info-label">{{ $t('qrcode.supplierContact') }}</p><p class="text-sm">{{ detail.supplier_contact || '-' }}</p></div>
                <div><p class="info-label">{{ $t('qrcode.supplierPhone') }}</p><p class="text-sm">{{ detail.supplier_phone || '-' }}</p></div>
                <div><p class="info-label">{{ $t('qrcode.inboundBy') }}</p>
                  <p class="text-sm" :class="detail.inbound_by ? 'text-text-primary font-medium' : 'text-text-secondary'">
                    {{ detail.inbound_by || $t('qrcode.notRecorded') }}
                  </p>
                </div>
              </div>
              <div v-else class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                  <label class="edit-label">{{ $t('qrcode.supplierSection') }} <span class="text-text-secondary font-normal">（{{ $t('common.optional') }}）</span></label>
                  <select v-model="editForm.supplier_id" class="edit-input">
                    <option value="">— {{ $t('common.unlinked') }} —</option>
                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                  <p v-if="editForm.supplier_id" class="text-xs text-text-secondary mt-1">
                    {{ suppliers.find(s => s.id == editForm.supplier_id)?.contact }} ·
                    {{ suppliers.find(s => s.id == editForm.supplier_id)?.phone }}
                  </p>
                </div>
                <div class="col-span-2">
                  <label class="edit-label">{{ $t('qrcode.inboundBy') }} <span class="text-text-secondary font-normal">（{{ $t('qrcode.inboundByHint') }}）</span></label>
                  <input v-model="editForm.inbound_by" :placeholder="$t('common.optional')" class="edit-input" />
                </div>
              </div>
            </div>

            <!-- Dealer -->
            <div class="sub-card">
              <h4 class="sub-title"><span class="material-symbols-outlined text-[15px]">handshake</span>{{ $t('qrcode.dealerSection') }}</h4>
              <div v-if="editingSection !== 'chain'" class="grid grid-cols-2 gap-3">
                <div><p class="info-label">{{ $t('common.name') }}</p><p class="text-sm" :class="detail.dealer_name ? '' : 'text-text-secondary italic'">{{ detail.dealer_name || $t('qrcode.unlinked') }}</p></div>
                <div><p class="info-label">{{ $t('qrcode.dealerRegion') }}</p><p class="text-sm">{{ detail.dealer_region || '-' }}</p></div>
                <div><p class="info-label">{{ $t('qrcode.supplierContact') }}</p><p class="text-sm">{{ detail.dealer_contact || '-' }}</p></div>
                <div><p class="info-label">{{ $t('qrcode.supplierPhone') }}</p><p class="text-sm">{{ detail.dealer_phone || '-' }}</p></div>
                <div><p class="info-label">{{ $t('qrcode.outboundBy') }}</p>
                  <p class="text-sm" :class="detail.outbound_by ? 'text-text-primary font-medium' : 'text-text-secondary'">
                    {{ detail.outbound_by || $t('qrcode.notRecorded') }}
                  </p>
                </div>
              </div>
              <div v-else class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                  <label class="edit-label">{{ $t('qrcode.dealerSection') }} <span class="text-text-secondary font-normal">（{{ $t('common.optional') }}）</span></label>
                  <select v-model="editForm.dealer_id" class="edit-input">
                    <option value="">— {{ $t('common.unlinked') }} —</option>
                    <option v-for="d in dealers" :key="d.id" :value="d.id">{{ d.name }}{{ d.region ? ` (${d.region})` : '' }}</option>
                  </select>
                  <p v-if="editForm.dealer_id" class="text-xs text-text-secondary mt-1">
                    {{ dealers.find(d => d.id == editForm.dealer_id)?.contact }} ·
                    {{ dealers.find(d => d.id == editForm.dealer_id)?.phone }}
                  </p>
                </div>
                <div class="col-span-2">
                  <label class="edit-label">{{ $t('qrcode.outboundBy') }} <span class="text-text-secondary font-normal">（{{ $t('qrcode.outboundByHint') }}）</span></label>
                  <input v-model="editForm.outbound_by" :placeholder="$t('common.optional')" class="edit-input" />
                </div>
              </div>
            </div>

            <!-- Store -->
            <div class="sub-card">
              <h4 class="sub-title"><span class="material-symbols-outlined text-[15px]">storefront</span>{{ $t('qrcode.storeSection') }}</h4>
              <div v-if="editingSection !== 'chain'" class="grid grid-cols-2 gap-3">
                <div><p class="info-label">{{ $t('common.name') }}</p><p class="text-sm" :class="detail.store_name ? '' : 'text-text-secondary italic'">{{ detail.store_name || $t('qrcode.unlinked') }}</p></div>
                <div><p class="info-label">{{ $t('qrcode.storeCity') }}</p><p class="text-sm">{{ detail.store_city || '-' }}</p></div>
                <div><p class="info-label">{{ $t('qrcode.supplierContact') }}</p><p class="text-sm">{{ detail.store_contact || '-' }}</p></div>
                <div><p class="info-label">{{ $t('qrcode.supplierPhone') }}</p><p class="text-sm">{{ detail.store_phone || '-' }}</p></div>
              </div>
              <div v-else>
                <label class="edit-label">{{ $t('qrcode.storeSection') }} <span class="text-text-secondary font-normal">（{{ $t('common.optional') }}）</span></label>
                <select v-model="editForm.store_id" class="edit-input">
                  <option value="">— {{ $t('common.unlinked') }} —</option>
                  <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}{{ s.city ? ` · ${s.city}` : '' }}</option>
                </select>
                <p v-if="editForm.store_id" class="text-xs text-text-secondary mt-1">
                  {{ stores.find(s => s.id == editForm.store_id)?.contact }} ·
                  {{ stores.find(s => s.id == editForm.store_id)?.phone }}
                </p>
              </div>
            </div>

            <p v-if="!canEdit" class="text-xs text-text-secondary mt-3 text-center">{{ $t('qrcode.adminManagerOnly') }}</p>
          </div>

          <!-- Sale Info -->
          <div v-if="activeSection === 'sale'">
            <h3 class="section-title">
              <span class="material-symbols-outlined text-primary text-[18px]">shopping_cart</span>{{ $t('qrcode.saleInfo') }}
              <button v-if="canEdit && editingSection !== 'sale'" @click="startSaleEdit"
                class="ml-auto edit-btn">
                <span class="material-symbols-outlined text-[15px]">edit</span>{{ $t('common.edit') }}
              </button>
              <template v-else-if="editingSection === 'sale'">
                <button @click="saveSection" :disabled="saving" class="ml-auto save-btn">{{ saving ? $t('common.saving') : $t('common.saveChanges') }}</button>
                <button @click="cancelEdit" class="cancel-btn">{{ $t('common.cancel') }}</button>
              </template>
            </h3>
            <div v-if="saveError && editingSection === 'sale'" class="mb-3 px-3 py-2 bg-danger/10 text-danger text-sm rounded-lg flex items-center gap-2">
              <span class="material-symbols-outlined text-[16px]">error</span>{{ saveError }}
            </div>

            <div v-if="editingSection !== 'sale'" class="grid grid-cols-2 gap-3">
              <div class="info-cell">
                <p class="info-label">{{ $t('qrcode.buyerLabel') }}</p>
                <p class="text-sm" :class="detail.buyer ? 'font-medium' : 'text-text-secondary'">{{ detail.buyer || $t('qrcode.notRecorded') }}</p>
              </div>
              <div class="info-cell">
                <p class="info-label">{{ $t('qrcode.buyDate') }}</p>
                <p class="text-sm">{{ detail.buy_date?.slice(0,10) || '-' }}</p>
              </div>
              <div class="info-cell">
                <p class="info-label">{{ $t('qrcode.warrantyEnd') }}</p>
                <div v-if="detail.warranty_end" class="flex items-center gap-2">
                  <p class="text-sm" :class="warrantyClass(detail.warranty_end)">{{ detail.warranty_end.slice(0,10) }}</p>
                  <span :class="['text-xs px-1.5 py-0.5 rounded-full', new Date(detail.warranty_end) >= new Date() ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger']">
                    {{ new Date(detail.warranty_end) >= new Date() ? $t('qrcode.warrantyValid') : $t('qrcode.warrantyExpired') }}
                  </span>
                </div>
                <p v-else class="text-sm text-text-secondary">{{ $t('qrcode.notSet') }}</p>
                <p v-if="detail.warranty_period && detail.warranty_unit" class="text-xs text-text-secondary mt-1">
                  ({{ detail.warranty_period }}{{ detail.warranty_unit === 'day' ? $t('qrcode.day') : detail.warranty_unit === 'month' ? $t('qrcode.month') : $t('qrcode.year') }})
                </p>
              </div>
              <div class="info-cell">
                <p class="info-label">{{ $t('qrcode.salesPerson') }}</p>
                <p class="text-sm" :class="detail.sales_person ? 'font-medium' : 'text-text-secondary'">{{ detail.sales_person || $t('qrcode.notRecorded') }}</p>
              </div>
              <div class="info-cell">
                <p class="info-label">{{ $t('qrcode.warehouseLabel') }}</p>
                <p class="text-sm">{{ detail.warehouse || '-' }}</p>
              </div>
              <div class="info-cell">
                <p class="info-label">{{ $t('qrcode.afterSaleContact') }}</p>
                <p class="text-sm" :class="detail.after_sale_contact ? 'font-medium text-primary' : 'text-text-secondary'">
                  {{ detail.after_sale_contact || $t('qrcode.notSet') }}
                </p>
              </div>
            </div>

            <div v-else class="grid grid-cols-2 gap-3">
              <div v-for="f in saleFields" :key="f.key">
                <div>
                  <label class="edit-label">{{ f.label }} <span v-if="f.hint" class="text-text-secondary font-normal text-[11px]">{{ f.hint }}</span></label>
                  <select v-if="f.type === 'select'" v-model="editForm[f.key]" class="edit-input">
                    <option v-for="opt in f.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                  <input v-else v-model="editForm[f.key]" :type="f.type" :placeholder="$t('common.optional')" class="edit-input" />
                </div>
              </div>
            </div>

            <p v-if="!canEdit" class="text-xs text-text-secondary mt-3 text-center">{{ $t('qrcode.adminManagerOnlySale') }}</p>
          </div>

          <!-- Repair Records -->
          <div v-if="activeSection === 'repair'">
            <div class="flex items-center justify-between mb-4">
              <h3 class="section-title mb-0">
                <span class="material-symbols-outlined text-primary text-[18px]">build</span>{{ $t('qrcode.repairRecords') }}
              </h3>
              <button v-if="canEdit" @click="showRepairForm = !showRepairForm"
                class="flex items-center gap-1 px-3 py-1.5 bg-primary text-white rounded-lg text-sm hover:bg-primary-hover transition-colors">
                <span class="material-symbols-outlined text-[16px]">add</span>{{ $t('qrcode.addRepair') }}
              </button>
            </div>

            <!-- New Repair Form -->
            <div v-if="showRepairForm" class="border border-primary/30 rounded-lg p-4 bg-primary/5 mb-4 space-y-3">
              <h4 class="font-medium text-text-primary text-sm">{{ $t('qrcode.newRepairTitle') }}</h4>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="edit-label">{{ $t('qrcode.repairPerson') }} <span class="text-text-secondary font-normal">（{{ $t('common.optional') }}）</span></label>
                  <input v-model="repairForm.repair_person" :placeholder="$t('common.optional')" class="edit-input" />
                </div>
                <div>
                  <label class="edit-label">{{ $t('qrcode.repairCost') }}</label>
                  <input v-model="repairForm.cost" type="number" min="0" step="0.01" placeholder="0.00" class="edit-input" />
                </div>
                <div class="col-span-2">
                  <label class="edit-label">{{ $t('qrcode.faultDesc') }} <span class="text-text-secondary font-normal">（{{ $t('common.optional') }}）</span></label>
                  <textarea v-model="repairForm.issue" rows="2" :placeholder="$t('qrcode.faultDescPlaceholder')" class="edit-input resize-none"></textarea>
                </div>
                <div class="col-span-2">
                  <label class="edit-label">{{ $t('qrcode.solutionLabel') }} <span class="text-text-secondary font-normal">（{{ $t('common.optional') }}）</span></label>
                  <textarea v-model="repairForm.solution" rows="2" :placeholder="$t('qrcode.solutionPlaceholder')" class="edit-input resize-none"></textarea>
                </div>
              </div>
              <div class="flex gap-2 justify-end">
                <button @click="showRepairForm = false" class="cancel-btn">{{ $t('common.cancel') }}</button>
                <button @click="addRepair" class="save-btn">{{ $t('qrcode.submitRepair') }}</button>
              </div>
            </div>

            <div v-if="!detail.repairRecords?.length" class="text-center py-8 text-text-secondary text-sm">{{ $t('qrcode.noRepairRecords') }}</div>

            <div v-for="r in detail.repairRecords" :key="r.id" class="sub-card mb-3">
              <!-- Record header -->
              <div v-if="editingRepairId !== r.id" class="space-y-2">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span class="font-medium text-sm text-text-primary">{{ r.repair_person || $t('qrcode.unspecifiedRepair') }}</span>
                    <StatusTag :type="repairStatusMap[r.status]?.type" :text="repairStatusMap[r.status]?.text" />
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="text-xs text-text-secondary">{{ r.created_at?.slice(0,10) }}</span>
                    <template v-if="canEdit">
                      <button v-if="r.status !== 'completed'" @click="advanceRepairStatus(r)"
                        class="text-xs text-primary border border-primary/30 px-2 py-0.5 rounded hover:bg-primary/5">
                        {{ r.status === 'pending' ? $t('qrcode.startRepair') : $t('qrcode.completeRepair') }}
                      </button>
                      <button @click="startRepairEdit(r)"
                        class="text-xs text-text-secondary hover:text-text-primary border border-gray-200 px-2 py-0.5 rounded hover:bg-gray-50">
                        {{ $t('common.edit') }}
                      </button>
                    </template>
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm mt-2">
                  <div>
                    <p class="info-label">{{ $t('qrcode.faultDesc') }}</p>
                    <p class="text-text-primary">{{ r.issue || '-' }}</p>
                  </div>
                  <div>
                    <p class="info-label">{{ $t('qrcode.solutionLabel') }}</p>
                    <p class="text-text-primary">{{ r.solution || '-' }}</p>
                  </div>
                  <div>
                    <p class="info-label">{{ $t('qrcode.repairCostLabel') }}</p>
                    <p class="font-medium text-primary">¥{{ Number(r.cost || 0).toFixed(2) }}</p>
                  </div>
                  <div v-if="r.repaired_at">
                    <p class="info-label">{{ $t('qrcode.completedAt') }}</p>
                    <p>{{ r.repaired_at?.slice(0,10) }}</p>
                  </div>
                </div>
              </div>

              <!-- Edit Repair Record -->
              <div v-else class="space-y-3">
                <h4 class="font-medium text-sm text-text-primary flex items-center gap-1">
                  <span class="material-symbols-outlined text-[15px] text-primary">edit</span>{{ $t('qrcode.editRepairTitle') }}
                </h4>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="edit-label">{{ $t('qrcode.repairPerson') }}</label>
                    <input v-model="editingRepairForm.repair_person" class="edit-input" :placeholder="$t('common.optional')" />
                  </div>
                  <div>
                    <label class="edit-label">{{ $t('qrcode.repairCost') }}</label>
                    <input v-model="editingRepairForm.cost" type="number" min="0" step="0.01" class="edit-input" />
                  </div>
                  <div class="col-span-2">
                    <label class="edit-label">{{ $t('qrcode.faultDesc') }}</label>
                    <textarea v-model="editingRepairForm.issue" rows="2" class="edit-input resize-none"></textarea>
                  </div>
                  <div class="col-span-2">
                    <label class="edit-label">{{ $t('qrcode.solutionLabel') }}</label>
                    <textarea v-model="editingRepairForm.solution" rows="2" class="edit-input resize-none"></textarea>
                  </div>
                </div>
                <div class="flex gap-2 justify-end">
                  <button @click="editingRepairId = null" class="cancel-btn">{{ $t('common.cancel') }}</button>
                  <button @click="saveRepairEdit" :disabled="saving" class="save-btn">{{ saving ? $t('common.saving') : $t('common.saveChanges') }}</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Scan Logs -->
          <div v-if="activeSection === 'scan'">
            <h3 class="section-title">
              <span class="material-symbols-outlined text-primary text-[18px]">qr_code_scanner</span>{{ $t('qrcode.scanLogs') }}
              <span class="ml-auto text-xs text-text-secondary font-normal">{{ $t('qrcode.latest20') }}</span>
            </h3>
            <div v-if="!detail.scanLogs?.length" class="text-center py-8 text-text-secondary text-sm">{{ $t('qrcode.noScanLogs') }}</div>
            <div v-for="log in detail.scanLogs" :key="log.id"
              class="flex items-center justify-between py-3 border-b border-gray-100 last:border-0">
              <div class="flex items-center gap-3">
                <div class="size-8 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                  <span class="material-symbols-outlined text-primary text-[14px]">person</span>
                </div>
                <div>
                  <p class="text-sm font-medium text-text-primary">{{ log.scanner || $t('common.anonymous') }}</p>
                  <p class="text-xs text-text-secondary">{{ log.role }} · {{ log.action }}</p>
                </div>
              </div>
              <div class="text-right">
                <p class="text-xs text-text-secondary">{{ log.created_at?.slice(0,19) }}</p>
                <p class="text-xs text-text-secondary">{{ log.location || '-' }}</p>
              </div>
            </div>
          </div>

          <!-- After-Sale Records -->
          <div v-if="activeSection === 'aftersale'">
            <h3 class="section-title">
              <span class="material-symbols-outlined text-primary text-[18px]">support_agent</span>{{ $t('qrcode.afterSaleRecords') }}
            </h3>
            <div v-if="!detail.afterSaleRecords?.length" class="text-center py-8 text-text-secondary text-sm">{{ $t('qrcode.noAfterSaleRecords') }}</div>

            <div v-for="r in detail.afterSaleRecords" :key="r.id" class="sub-card mb-3">
              <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-2 flex-wrap">
                  <span v-if="r.ticket_no" class="font-mono text-xs text-primary">{{ r.ticket_no }}</span>
                  <span class="font-medium text-sm text-text-primary">{{ r.buyer || $t('qrcode.anonymousUser') }}</span>
                  <StatusTag
                    :type="r.status === 'resolved' ? 'success' : r.status === 'rejected' ? 'danger' : 'warning'"
                    :text="r.status === 'resolved' ? $t('qrcode.resolvedStatus') : r.status === 'rejected' ? $t('qrcode.rejectedStatus') : $t('qrcode.processingStatus')" />
                  <span v-if="r.type" class="px-1.5 py-0.5 text-[10px] rounded bg-blue-50 text-blue-600">{{ $t('qrcode.type' + r.type.charAt(0).toUpperCase() + r.type.slice(1)) }}</span>
                  <span v-if="r.priority && r.priority !== 'normal'" :class="['px-1.5 py-0.5 text-[10px] rounded', r.priority === 'urgent' ? 'bg-red-50 text-red-600' : r.priority === 'high' ? 'bg-orange-50 text-orange-600' : 'bg-gray-50 text-gray-500']">{{ $t('qrcode.priority' + r.priority.charAt(0).toUpperCase() + r.priority.slice(1)) }}</span>
                </div>
                <span class="text-xs text-text-secondary shrink-0">{{ r.created_at?.slice(0,10) }}</span>
              </div>

              <!-- 时间线 -->
              <div v-if="r.responded_at || r.resolved_at" class="flex items-center gap-3 mt-2 text-[10px] text-text-secondary">
                <span>{{ r.created_at?.slice(0,16) }}</span>
                <span class="text-gray-300">→</span>
                <span :class="r.responded_at ? 'text-blue-600' : 'text-gray-300'">{{ r.responded_at ? r.responded_at.slice(0,16) : '-' }}</span>
                <span class="text-gray-300">→</span>
                <span :class="r.resolved_at ? 'text-green-600' : 'text-gray-300'">{{ r.resolved_at ? r.resolved_at.slice(0,16) : '-' }}</span>
              </div>

              <div class="grid grid-cols-2 gap-2 text-sm mt-3">
                <div class="col-span-2">
                  <p class="info-label">{{ $t('qrcode.issueDesc') }}</p>
                  <p class="text-text-primary">{{ r.issue || '-' }}</p>
                </div>
                <div>
                  <p class="info-label">{{ $t('qrcode.handlerCol') }}</p>
                  <p :class="r.handler ? 'text-text-primary font-medium' : 'text-text-secondary'">{{ r.handler || $t('common.notRecorded') }}</p>
                </div>
                <div>
                  <p class="info-label">{{ $t('qrcode.handlerNote') }}</p>
                  <p class="text-text-primary">{{ r.handler_note || '-' }}</p>
                </div>
              </div>

              <!-- 渠道二维码展示/编辑 -->
              <div class="mt-3 pt-3 border-t border-gray-100">
                <p class="info-label mb-2">{{ $t('qrcode.channelQrcodes') || '渠道二维码' }}</p>
                <div class="grid grid-cols-3 gap-3">
                  <div v-for="ch in ['telegram', 'whatsapp', 'wecom']" :key="ch" class="border border-gray-200 rounded-lg p-2 text-center">
                    <p class="text-xs font-medium mb-1">{{ $t('qrcode.channel' + ch.charAt(0).toUpperCase() + ch.slice(1)) }}</p>
                    <div v-if="(r.channel_qrcodes && r.channel_qrcodes[ch]) || (editingChannelQrId === r.id && channelQrForm[ch])" class="relative inline-block">
                      <img :src="editingChannelQrId === r.id && channelQrForm[ch] ? channelQrForm[ch] : r.channel_qrcodes[ch]" class="w-16 h-16 object-contain rounded border cursor-pointer hover:shadow-md transition-shadow" @click="channelQrPreview = (editingChannelQrId === r.id && channelQrForm[ch]) ? channelQrForm[ch] : r.channel_qrcodes[ch]" />
                      <button v-if="canEdit" type="button" @click="startEditChannelQr(r); channelQrForm[ch] = ''" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center leading-none">×</button>
                    </div>
                    <label v-else-if="canEdit" class="inline-flex flex-col items-center gap-1 cursor-pointer text-primary hover:text-primary-hover py-2">
                      <span class="material-symbols-outlined text-[24px]">add_photo_alternate</span>
                      <span class="text-xs">{{ $t('qrcode.uploadQrcode') || '上传' }}</span>
                      <input type="file" accept="image/*" @change="startEditChannelQr(r); handleChannelQrUploadInline(ch, $event, r)" class="hidden" />
                    </label>
                    <p v-else class="text-xs text-text-secondary py-2">-</p>
                  </div>
                </div>
                <div v-if="editingChannelQrId === r.id" class="flex gap-2 justify-end mt-2">
                  <button @click="editingChannelQrId = null" class="cancel-btn">{{ $t('common.cancel') }}</button>
                  <button @click="saveChannelQrcodes(r)" :disabled="saving" class="save-btn">{{ saving ? $t('common.submitting') : $t('common.save') }}</button>
                </div>
              </div>

              <!-- Handle form (admin/manager when status is processing) -->
              <template v-if="canEdit && (r.status === 'processing' || handlingAfterSaleId === r.id)">
                <div v-if="handlingAfterSaleId !== r.id" class="mt-3 pt-3 border-t border-gray-100 flex gap-2">
                  <button @click="openAfterSaleHandle(r)"
                    class="flex items-center gap-1 px-3 py-1.5 bg-primary text-white rounded-lg text-sm hover:bg-primary-hover transition-colors">
                    <span class="material-symbols-outlined text-[16px]">reply</span>{{ $t('qrcode.handleTicket') }}
                  </button>
                </div>
                <div v-else class="mt-3 pt-3 border-t border-gray-100 space-y-3">
                  <h4 class="font-medium text-sm text-text-primary flex items-center gap-1">
                    <span class="material-symbols-outlined text-[15px] text-primary">reply</span>{{ $t('qrcode.handleTicket') }}
                  </h4>
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="edit-label">{{ $t('qrcode.handler') }}</label>
                      <input v-model="afterSaleHandleForm.handler" class="edit-input" :placeholder="$t('qrcode.handler')" />
                    </div>
                    <div>
                      <label class="edit-label">{{ $t('qrcode.handleResult') }}</label>
                      <select v-model="afterSaleHandleForm.status" class="edit-input">
                        <option value="resolved">{{ $t('qrcode.resolvedStatus') }}</option>
                        <option value="rejected">{{ $t('qrcode.rejectedStatus') }}</option>
                      </select>
                    </div>
                    <div class="col-span-2">
                      <label class="edit-label">{{ $t('qrcode.handlerNote') }}</label>
                      <textarea v-model="afterSaleHandleForm.handler_note" rows="2" :placeholder="$t('qrcode.handleNotePlaceholder')" class="edit-input resize-none"></textarea>
                    </div>
                  </div>
                  <div class="flex gap-2 justify-end">
                    <button @click="handlingAfterSaleId = null" class="cancel-btn">{{ $t('common.cancel') }}</button>
                    <button @click="saveAfterSaleHandle" :disabled="saving" class="save-btn">{{ saving ? $t('common.submitting') : $t('qrcode.submitHandle') }}</button>
                  </div>
                </div>
              </template>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- 渠道二维码放大预览 -->
    <div v-if="channelQrPreview" class="fixed inset-0 z-[60] bg-black/70 flex items-center justify-center" @click="channelQrPreview = ''">
      <img :src="channelQrPreview" class="max-w-[80vw] max-h-[80vh] rounded-lg shadow-2xl" />
    </div>
  </div>
</template>

<style scoped>
@reference "../style.css";
.section-title {
  @apply font-bold text-text-primary text-base flex items-center gap-2 mb-4;
}
.sub-card {
  @apply border border-gray-100 rounded-lg p-4;
}
.sub-title {
  @apply font-medium text-text-primary text-sm mb-3 flex items-center gap-1.5 text-text-secondary;
}
.info-cell {
  @apply bg-gray-50 rounded-lg p-3;
}
.info-label {
  @apply text-xs text-text-secondary mb-1;
}
.edit-label {
  @apply text-xs font-medium text-text-secondary mb-1 block;
}
.edit-input {
  @apply w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none;
}
.edit-btn {
  @apply flex items-center gap-1 px-3 py-1 bg-primary/10 text-primary rounded-lg text-sm hover:bg-primary/20 transition-colors;
}
.save-btn {
  @apply px-3 py-1.5 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-hover transition-colors disabled:opacity-50;
}
.cancel-btn {
  @apply px-3 py-1.5 border border-gray-200 rounded-lg text-sm text-text-secondary hover:bg-gray-50 transition-colors;
}
</style>
