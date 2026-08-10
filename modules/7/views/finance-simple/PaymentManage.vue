<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import Pagination from '../../components/Pagination.vue'
import api from '../../services/api.js'

const { t, locale } = useI18n()

const records = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = 50

const filterSupplierId = ref('')
const filterDateStart = ref('')
const filterDateEnd = ref('')
const filterMethod = ref('')
const suppliers = ref([])
const accounts = ref([])
const showModal = ref(false)
const editingRecord = ref(null)

const formData = ref({
  payment_date: '',
  supplier_id: '',
  amount: '',
  payment_method: 'bank',
  account_id: '',
  note: ''
})

async function fetchRecords() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize }
    if (filterSupplierId.value) params.supplier_id = filterSupplierId.value
    if (filterDateStart.value) params.date_start = filterDateStart.value
    if (filterDateEnd.value) params.date_end = filterDateEnd.value
    if (filterMethod.value) params.payment_method = filterMethod.value

    const res = await api.get('/finance-simple/payments', { params })
    if (res.code === 0) {
      records.value = res.data.list
      total.value = res.data.total
    }
  } finally { loading.value = false }
}

async function fetchSuppliers() {
  const res = await api.get('/suppliers/list')
  if (res.code === 0) suppliers.value = res.data
}

async function fetchAccounts() {
  const res = await api.get('/finance-simple/accounts')
  if (res.code === 0) accounts.value = res.data.filter(a => a.status === 'active')
}

function resetFilters() {
  filterSupplierId.value = ''
  filterDateStart.value = ''
  filterDateEnd.value = ''
  filterMethod.value = ''
  currentPage.value = 1
  fetchRecords()
}

function openCreateModal() {
  editingRecord.value = null
  formData.value = {
    payment_date: new Date().toISOString().split('T')[0],
    supplier_id: '',
    amount: '',
    payment_method: 'bank',
    account_id: '',
    note: ''
  }
  showModal.value = true
}

function openEditModal(record) {
  editingRecord.value = record
  formData.value = {
    payment_date: record.payment_date,
    supplier_id: record.supplier_id,
    amount: record.amount,
    payment_method: record.payment_method,
    account_id: record.account_id || '',
    note: record.note || ''
  }
  showModal.value = true
}

async function saveRecord() {
  try {
    if (editingRecord.value) {
      await api.put(`/finance-simple/payments/${editingRecord.value.id}`, formData.value)
    } else {
      await api.post('/finance-simple/payments', formData.value)
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
    await api.delete(`/finance-simple/payments/${id}`)
    fetchRecords()
  } catch (err) {
    alert(err.message || t('common.deleteFailed'))
  }
}

watch(currentPage, fetchRecords)

onMounted(() => {
  fetchRecords()
  fetchSuppliers()
  fetchAccounts()
})

function formatMoney(val) {
  return '¥' + Number(val || 0).toFixed(2)
}

function formatDate(dt) {
  if (!dt) return '-'
  const d = new Date(dt)
  if (isNaN(d.getTime())) return '-'
  const localeMap = { zh: 'zh-CN', en: 'en-US' }
  return d.toLocaleDateString(localeMap[locale.value] || 'zh-CN')
}

function getMethodLabel(method) {
  const map = {
    cash: t('financeSimple.cash'),
    bank: t('financeSimple.bank'),
    alipay: t('financeSimple.alipay'),
    wechat: t('financeSimple.wechat'),
    other: t('financeSimple.other')
  }
  return map[method] || method
}
</script>

<template>
  <div>
    <PageHeader :title="$t('financeSimple.paymentManage')" :subtitle="$t('financeSimple.paymentManageSubtitle')">
      <button @click="openCreateModal" class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors">
        <span class="material-symbols-outlined text-[18px]">add</span>
        {{ $t('common.add') }}
      </button>
    </PageHeader>

    <!-- Filters -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 mb-6">
      <div class="flex flex-wrap items-center gap-3 max-sm:flex-col max-sm:items-stretch">
        <select v-model="filterSupplierId" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('financeSimple.allSuppliers') }}</option>
          <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <input v-model="filterDateStart" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <span class="text-text-secondary">—</span>
        <input v-model="filterDateEnd" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <select v-model="filterMethod" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('financeSimple.allPaymentMethods') }}</option>
          <option value="cash">{{ $t('financeSimple.cash') }}</option>
          <option value="bank">{{ $t('financeSimple.bank') }}</option>
          <option value="alipay">{{ $t('financeSimple.alipay') }}</option>
          <option value="wechat">{{ $t('financeSimple.wechat') }}</option>
          <option value="other">{{ $t('financeSimple.other') }}</option>
        </select>
        <button @click="fetchRecords" class="bg-primary text-white px-4 py-2 rounded-lg text-sm hover:bg-primary/90 transition-colors">
          {{ $t('common.query') }}
        </button>
        <button @click="resetFilters" class="text-sm text-text-secondary hover:text-text-primary border border-gray-200 rounded-lg px-3 py-2">
          {{ $t('common.reset') }}
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden max-sm:border-0 max-sm:shadow-none max-sm:rounded-none">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.recordNo') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.paymentDate') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.supplier') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.amount') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.paymentMethod') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('fundAccounts.paymentAccount') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('common.note') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('common.creator') }}</th>
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
              <td class="px-4 py-3 font-mono text-sm text-primary">{{ r.record_no }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ formatDate(r.payment_date) }}</td>
              <td class="px-4 py-3 text-text-primary">{{ r.supplier_name || '-' }}</td>
              <td class="px-4 py-3 text-right font-semibold text-red-600">{{ formatMoney(r.amount) }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ getMethodLabel(r.payment_method) }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ r.account_name || '-' }}</td>
              <td class="px-4 py-3 text-text-secondary text-xs max-w-[150px] truncate">{{ r.note || '-' }}</td>
              <td class="px-4 py-3 text-text-secondary text-xs">{{ r.creator_name || '-' }}</td>
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
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto max-sm:fixed max-sm:inset-0 max-sm:max-w-none max-sm:max-h-none max-sm:m-0 max-sm:rounded-none">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
          <h3 class="text-lg font-semibold text-text-primary">{{ editingRecord ? $t('common.edit') : $t('common.add') }} {{ $t('financeSimple.makePayment') }}</h3>
          <button @click="showModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.paymentDate') }} *</label>
            <input v-model="formData.payment_date" type="date" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.supplier') }} *</label>
            <select v-model="formData.supplier_id" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option value="">{{ $t('common.pleaseSelect') }}</option>
              <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.amount') }} *</label>
            <input v-model="formData.amount" type="number" step="0.01" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.paymentMethod') }} *</label>
            <select v-model="formData.payment_method" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option value="cash">{{ $t('financeSimple.cash') }}</option>
              <option value="bank">{{ $t('financeSimple.bank') }}</option>
              <option value="alipay">{{ $t('financeSimple.alipay') }}</option>
              <option value="wechat">{{ $t('financeSimple.wechat') }}</option>
              <option value="other">{{ $t('financeSimple.other') }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('fundAccounts.paymentAccount') }}</label>
            <select v-model="formData.account_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option value="">{{ $t('common.none') }}</option>
              <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.account_name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('common.note') }}</label>
            <textarea v-model="formData.note" rows="3" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"></textarea>
          </div>
        </div>
        <div class="flex items-center justify-between gap-3 p-6 border-t border-gray-100 max-sm:gap-2">
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

<style scoped>
@media (max-width: 768px) {
  /* Filters */
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-4.mb-6 {
    padding: 12px;
  }

  /* Table container */
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.overflow-hidden {
    margin: 0 -16px;
    border-radius: 0;
    border-left: none;
    border-right: none;
    box-shadow: none;
  }

  /* Table */
  table {
    font-size: 12px;
  }

  table th,
  table td {
    padding: 8px 6px;
    min-width: 70px;
  }

  table th:first-child,
  table td:first-child {
    padding-left: 16px;
  }

  table th:last-child,
  table td:last-child {
    padding-right: 16px;
  }

  /* Modal */
  .fixed.inset-0.bg-black\/50 {
    padding: 0;
  }

  .bg-white.rounded-lg.shadow-xl.w-full.max-w-md {
    width: 100%;
    max-width: 100%;
    height: 100%;
    max-height: 100%;
    border-radius: 0;
  }

  .p-6 {
    padding: 16px;
  }

  /* Modal form */
  .space-y-4 > div {
    margin-bottom: 12px;
  }

  /* Buttons */
  button {
    padding: 8px 12px;
    font-size: 13px;
  }

  .material-symbols-outlined {
    font-size: 16px;
  }
}
</style>