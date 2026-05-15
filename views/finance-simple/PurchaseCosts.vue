<script setup>
import { ref, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import Pagination from '../../components/Pagination.vue'
import api from '../../services/api.js'

const { t } = useI18n()

const records = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = 50

const filterSupplier = ref('')
const filterProduct = ref('')
const filterDateStart = ref('')
const filterDateEnd = ref('')
const filterPaymentStatus = ref('')

const suppliers = ref([])
const products = ref([])
const accounts = ref([])
const exporting = ref(false)
const showModal = ref(false)
const editingRecord = ref(null)

const formData = ref({
  supplier_id: '',
  purchase_date: '',
  product_id: '',
  quantity: 1,
  unit_price: '',
  payment_method: 'credit',
  account_id: '',
  note: ''
})

async function fetchRecords() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize }
    if (filterSupplier.value) params.supplier_id = filterSupplier.value
    if (filterProduct.value) params.product_id = filterProduct.value
    if (filterDateStart.value) params.date_start = filterDateStart.value
    if (filterDateEnd.value) params.date_end = filterDateEnd.value
    if (filterPaymentStatus.value) params.payment_status = filterPaymentStatus.value
    const res = await api.get('/finance-simple/purchase-costs', { params })
    if (res.code === 0) {
      records.value = res.data.list
      total.value = res.data.total
    }
  } finally { loading.value = false }
}

async function fetchSuppliers() {
  const res = await api.get('/suppliers')
  if (res.code === 0) suppliers.value = res.data
}

async function fetchProducts() {
  const res = await api.get('/products', { params: { size: 1000 } })
  if (res.code === 0) products.value = res.data.list
}

async function fetchAccounts() {
  const res = await api.get('/finance-simple/accounts')
  if (res.code === 0) accounts.value = res.data.filter(a => a.status === 'active')
}

watch([filterSupplier, filterProduct, filterDateStart, filterDateEnd, filterPaymentStatus], () => {
  currentPage.value = 1
  fetchRecords()
})
watch(currentPage, fetchRecords)

onMounted(() => {
  fetchRecords()
  fetchSuppliers()
  fetchProducts()
  fetchAccounts()
})

function openCreateModal() {
  editingRecord.value = null
  formData.value = {
    supplier_id: '',
    purchase_date: new Date().toISOString().split('T')[0],
    product_id: '',
    quantity: 1,
    unit_price: '',
    payment_method: 'credit',
    account_id: '',
    note: ''
  }
  showModal.value = true
}

function openEditModal(record) {
  editingRecord.value = record
  formData.value = {
    supplier_id: record.supplier_id,
    purchase_date: record.purchase_date,
    product_id: record.product_id,
    quantity: record.quantity,
    unit_price: record.unit_price,
    payment_method: record.payment_method,
    account_id: record.account_id || '',
    note: record.note || ''
  }
  showModal.value = true
}

async function saveRecord() {
  try {
    if (editingRecord.value) {
      await api.put(`/finance-simple/purchase-costs/${editingRecord.value.id}`, formData.value)
    } else {
      await api.post('/finance-simple/purchase-costs', formData.value)
    }
    showModal.value = false
    fetchRecords()
  } catch (err) {
    alert(err.message || t('common.operationFailed'))
  }
}

async function deleteRecord(id) {
  if (!confirm(t('common.confirmDelete'))) return
  try {
    await api.delete(`/finance-simple/purchase-costs/${id}`)
    fetchRecords()
  } catch (err) {
    alert(err.message || t('common.deleteFailed'))
  }
}

function formatMoney(val) {
  return '¥' + Number(val || 0).toFixed(2)
}

function formatDate(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleDateString('zh-CN')
}

function getPaymentStatusText(status) {
  const map = { unpaid: t('financeSimple.unpaid'), partial: t('financeSimple.partial'), paid: t('financeSimple.paid') }
  return map[status] || status
}

function getPaymentStatusColor(status) {
  const map = { unpaid: 'text-red-600 bg-red-50', partial: 'text-orange-600 bg-orange-50', paid: 'text-green-600 bg-green-50' }
  return map[status] || 'text-gray-600 bg-gray-50'
}

async function exportExcel() {
  exporting.value = true
  try {
    const params = {}
    if (filterSupplier.value) params.supplier_id = filterSupplier.value
    if (filterProduct.value) params.product_id = filterProduct.value
    if (filterDateStart.value) params.date_start = filterDateStart.value
    if (filterDateEnd.value) params.date_end = filterDateEnd.value
    if (filterPaymentStatus.value) params.payment_status = filterPaymentStatus.value

    const res = await api.get('/finance-simple/export/purchase-costs', { params, responseType: 'blob' })

    const url = window.URL.createObjectURL(new Blob([res]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `${t('financeSimple.purchaseCostExportFilename')}_${new Date().toISOString().slice(0, 10)}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (err) {
    alert(err.message || t('financeSimple.exportFailed'))
  } finally {
    exporting.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader :title="$t('financeSimple.purchaseCosts')" :subtitle="$t('financeSimple.purchaseCostsSubtitle')">
      <button @click="openCreateModal" class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors">
        <span class="material-symbols-outlined text-[18px]">add</span>
        {{ $t('financeSimple.addPurchaseCost') || '新增采购' }}
      </button>
    </PageHeader>

    <!-- Filters -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 mb-6">
      <div class="flex flex-wrap items-center gap-3 mb-3">
        <select v-model="filterSupplier" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('financeSimple.allSuppliers') }}</option>
          <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <select v-model="filterProduct" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('financeSimple.allProducts') }}</option>
          <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <select v-model="filterPaymentStatus" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('financeSimple.allPaymentStatus') }}</option>
          <option value="unpaid">{{ $t('financeSimple.unpaid') }}</option>
          <option value="partial">{{ $t('financeSimple.partial') }}</option>
          <option value="paid">{{ $t('financeSimple.paid') }}</option>
        </select>
        <input v-model="filterDateStart" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <span class="text-text-secondary">—</span>
        <input v-model="filterDateEnd" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <button @click="filterSupplier=''; filterProduct=''; filterDateStart=''; filterDateEnd=''; filterPaymentStatus=''" class="text-sm text-text-secondary hover:text-text-primary border border-gray-200 rounded-lg px-3 py-2">
          {{ $t('common.reset') }}
        </button>
      </div>
      <div class="flex justify-end">
        <button @click="exportExcel" :disabled="exporting" class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
          <span class="material-symbols-outlined text-sm">download</span>
          {{ exporting ? $t('common.exporting') : $t('common.exportExcel') }}
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.recordNo') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.purchaseDate') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.supplier') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.product') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.quantity') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.unitPrice') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.totalAmount') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('financeSimple.paymentStatus') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="loading">
              <td colspan="9" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('common.loading') }}</td>
            </tr>
            <tr v-else-if="records.length === 0">
              <td colspan="9" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('common.noData') }}</td>
            </tr>
            <tr v-for="r in records" :key="r.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 font-mono text-xs text-primary">{{ r.record_no }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ formatDate(r.purchase_date) }}</td>
              <td class="px-4 py-3 text-text-primary">{{ r.supplier_name }}</td>
              <td class="px-4 py-3">
                <p class="font-medium text-text-primary">{{ r.product_name }}</p>
                <p v-if="r.product_spec" class="text-xs text-text-secondary">{{ r.product_spec }}</p>
              </td>
              <td class="px-4 py-3 text-right text-text-primary">{{ r.quantity }}</td>
              <td class="px-4 py-3 text-right text-text-primary">{{ formatMoney(r.unit_price) }}</td>
              <td class="px-4 py-3 text-right font-semibold text-text-primary">{{ formatMoney(r.total_amount) }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="getPaymentStatusColor(r.payment_status)" class="px-2 py-1 rounded text-xs font-medium">
                  {{ getPaymentStatusText(r.payment_status) }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <button @click="openEditModal(r)" class="text-primary hover:text-primary/80 mr-2">
                  <span class="material-symbols-outlined text-[18px]">edit</span>
                </button>
                <button @click="deleteRecord(r.id)" class="text-red-600 hover:text-red-700">
                  <span class="material-symbols-outlined text-[18px]">delete</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-gray-100">
        <Pagination :total="total" :page="currentPage" :pageSize="pageSize" @update:page="currentPage = $event" />
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showModal = false">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
          <h3 class="text-lg font-semibold text-text-primary">{{ editingRecord ? $t('financeSimple.editPurchaseCost') || '编辑采购' : $t('financeSimple.addPurchaseCost') || '新增采购' }}</h3>
          <button @click="showModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.purchaseDate') }} *</label>
              <input v-model="formData.purchase_date" type="date" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.supplier') }} *</label>
              <select v-model="formData.supplier_id" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                <option value="">{{ $t('common.pleaseSelect') || '请选择' }}</option>
                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.product') }} *</label>
            <select v-model="formData.product_id" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option value="">{{ $t('common.pleaseSelect') || '请选择' }}</option>
              <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.quantity') }} *</label>
              <input v-model="formData.quantity" type="number" min="1" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.unitPrice') }} *</label>
              <input v-model="formData.unit_price" type="number" step="0.01" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.paymentMethod') }} *</label>
              <select v-model="formData.payment_method" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                <option value="credit">{{ $t('financeSimple.credit') || '赊购' }}</option>
                <option value="cash">{{ $t('financeSimple.cash') }}</option>
                <option value="bank">{{ $t('financeSimple.bank') }}</option>
                <option value="alipay">{{ $t('financeSimple.alipay') }}</option>
                <option value="wechat">{{ $t('financeSimple.wechat') }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('fundAccounts.paymentAccount') || '支付账户' }}</label>
              <select v-model="formData.account_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                <option value="">{{ $t('common.none') }}</option>
                <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.account_name }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('common.note') }}</label>
            <textarea v-model="formData.note" rows="3" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"></textarea>
          </div>
        </div>
        <div class="flex items-center justify-end gap-3 p-6 border-t border-gray-100">
          <button @click="showModal = false" class="px-4 py-2 border border-gray-200 rounded-lg text-text-secondary hover:text-text-primary transition-colors">
            {{ $t('common.cancel') }}
          </button>
          <button @click="saveRecord" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
            {{ $t('common.save') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
