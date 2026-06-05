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

const filterCategory = ref('')
const filterStore = ref('')
const filterDateStart = ref('')
const filterDateEnd = ref('')

const stores = ref([])
const accounts = ref([])
const showModal = ref(false)
const editingRecord = ref(null)
const exporting = ref(false)

const formData = ref({
  expense_date: '',
  category: 'other',
  category_name: '',
  amount: '',
  payment_method: 'cash',
  account_id: '',
  store_id: '',
  payee: '',
  description: '',
  note: ''
})

const categories = [
  { value: 'rent', label: 'financeSimple.categoryRent' },
  { value: 'salary', label: 'financeSimple.categorySalary' },
  { value: 'utility', label: 'financeSimple.categoryUtility' },
  { value: 'marketing', label: 'financeSimple.categoryMarketing' },
  { value: 'logistics', label: 'financeSimple.categoryLogistics' },
  { value: 'maintenance', label: 'financeSimple.categoryMaintenance' },
  { value: 'other', label: 'financeSimple.categoryOther' }
]

async function fetchRecords() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize }
    if (filterCategory.value) params.category = filterCategory.value
    if (filterStore.value) params.store_id = filterStore.value
    if (filterDateStart.value) params.date_start = filterDateStart.value
    if (filterDateEnd.value) params.date_end = filterDateEnd.value
    const res = await api.get('/finance-simple/expenses', { params })
    if (res.code === 0) {
      records.value = res.data.list
      total.value = res.data.total
    }
  } finally { loading.value = false }
}

async function fetchStores() {
  const res = await api.get('/stores')
  if (res.code === 0) stores.value = res.data
}

async function fetchAccounts() {
  const res = await api.get('/finance-simple/accounts')
  if (res.code === 0) accounts.value = res.data.filter(a => a.status === 'active')
}

watch([filterCategory, filterStore, filterDateStart, filterDateEnd], () => {
  currentPage.value = 1
  fetchRecords()
})
watch(currentPage, fetchRecords)

onMounted(() => {
  fetchRecords()
  fetchStores()
  fetchAccounts()
})

function openCreateModal() {
  editingRecord.value = null
  formData.value = {
    expense_date: new Date().toISOString().split('T')[0],
    category: 'other',
    category_name: '',
    amount: '',
    payment_method: 'cash',
    account_id: '',
    store_id: '',
    payee: '',
    description: '',
    note: ''
  }
  showModal.value = true
}

function openEditModal(record) {
  editingRecord.value = record
  formData.value = {
    expense_date: record.expense_date,
    category: record.category,
    category_name: record.category_name || '',
    amount: record.amount,
    payment_method: record.payment_method,
    account_id: record.account_id || '',
    store_id: record.store_id || '',
    payee: record.payee || '',
    description: record.description,
    note: record.note || ''
  }
  showModal.value = true
}

async function saveRecord() {
  try {
    if (editingRecord.value) {
      await api.put(`/finance-simple/expenses/${editingRecord.value.id}`, formData.value)
    } else {
      const res = await api.post('/finance-simple/expenses', formData.value)
      // 检查是否需要审批
      if (res.data?.needs_approval) {
        alert(t('financeSimple.expenseApprovalAutoSubmit'))
      }
    }
    showModal.value = false
    await fetchRecords()
  } catch (err) {
    alert(err.message || t('common.operationFailed'))
  }
}

async function deleteRecord(id) {
  if (!confirm(t('common.confirmDelete'))) return
  try {
    await api.delete(`/finance-simple/expenses/${id}`)
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
  const d = new Date(dt)
  if (isNaN(d.getTime())) return '-'
  const localeMap = { zh: 'zh-CN', en: 'en-US' }
  return d.toLocaleDateString(localeMap[locale.value] || 'zh-CN')
}

function getCategoryText(cat) {
  const found = categories.find(c => c.value === cat)
  return found ? t(found.label) : cat
}

function getApprovalStatusColor(status) {
  const map = { pending: 'text-orange-600 bg-orange-50', approved: 'text-green-600 bg-green-50', rejected: 'text-red-600 bg-red-50' }
  return map[status] || 'text-gray-600 bg-gray-50'
}

function getMethodLabel(method) {
  const map = { cash: t('financeSimple.cash'), bank: t('financeSimple.bank'), alipay: t('financeSimple.alipay'), wechat: t('financeSimple.wechat'), other: t('financeSimple.other') }
  return map[method] || method
}

function viewApproval(approvalId) {
  // 跳转到审批详情页
  window.open(`/oa/approvals?id=${approvalId}`, '_blank')
}

async function exportExcel() {
  exporting.value = true
  try {
    const params = {}
    if (filterCategory.value) params.category = filterCategory.value
    if (filterStore.value) params.store_id = filterStore.value
    if (filterDateStart.value) params.date_start = filterDateStart.value
    if (filterDateEnd.value) params.date_end = filterDateEnd.value

    const res = await api.get('/finance-simple/export/expenses', { params, responseType: 'blob' })

    const url = window.URL.createObjectURL(new Blob([res]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `${t('financeSimple.expenseDetailExportFilename')}_${new Date().toISOString().slice(0, 10)}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (err) {
    alert(err.message || t('common.exportFailed'))
  } finally {
    exporting.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader :title="$t('financeSimple.expenses')" :subtitle="$t('financeSimple.expensesSubtitle')">
      <button @click="openCreateModal" class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors">
        <span class="material-symbols-outlined text-[18px]">add</span>
        {{ $t('financeSimple.addExpense') }}
      </button>
    </PageHeader>

    <!-- Filters -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 mb-6">
      <div class="flex flex-wrap items-center gap-3 mb-3 max-sm:flex-col max-sm:items-stretch">
        <select v-model="filterCategory" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('financeSimple.allCategories') }}</option>
          <option v-for="c in categories" :key="c.value" :value="c.value">{{ $t(c.label) }}</option>
        </select>
        <select v-model="filterStore" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('financeSimple.allStores') }}</option>
          <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <input v-model="filterDateStart" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <span class="text-text-secondary">—</span>
        <input v-model="filterDateEnd" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <button @click="filterCategory=''; filterStore=''; filterDateStart=''; filterDateEnd=''" class="text-sm text-text-secondary hover:text-text-primary border border-gray-200 rounded-lg px-3 py-2">
          {{ $t('common.reset') }}
        </button>
      </div>
      <div class="flex justify-end max-sm:flex-col max-sm:gap-2">
        <button @click="exportExcel" :disabled="exporting" class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
          <span class="material-symbols-outlined text-sm">download</span>
          {{ exporting ? $t('common.exporting') : $t('common.exportExcel') }}
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
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.expenseDate') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.category') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.description') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.amount') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.paymentMethod') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('financeSimple.approvalStatus') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="loading">
              <td colspan="8" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('common.loading') }}</td>
            </tr>
            <tr v-else-if="records.length === 0">
              <td colspan="8" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('common.noData') }}</td>
            </tr>
            <tr v-for="r in records" :key="r.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 font-mono text-xs text-primary">{{ r.record_no }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ formatDate(r.expense_date) }}</td>
              <td class="px-4 py-3 text-text-primary">{{ getCategoryText(r.category) }}</td>
              <td class="px-4 py-3 text-text-primary">{{ r.description }}</td>
              <td class="px-4 py-3 text-right font-semibold text-red-600">{{ formatMoney(r.amount) }}</td>
              <td class="px-4 py-3 text-text-secondary text-xs">{{ getMethodLabel(r.payment_method) }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="getApprovalStatusColor(r.approval_status)" class="px-2 py-1 rounded text-xs font-medium">
                  {{ $t('financeSimple.' + r.approval_status) }}
                </span>
                <button v-if="r.approval_id && r.approval_status === 'pending'" @click="viewApproval(r.approval_id)" class="ml-2 text-primary hover:text-primary/80 text-xs underline">
                  {{ $t('financeSimple.viewApproval') }}
                </button>
              </td>
              <td class="px-4 py-3 text-center">
                <button v-if="r.approval_status !== 'pending'" @click="openEditModal(r)" class="text-primary hover:text-primary/80 mr-2">
                  <span class="material-symbols-outlined text-[18px]">edit</span>
                </button>
                <button v-if="r.approval_status !== 'pending'" @click="deleteRecord(r.id)" class="text-red-600 hover:text-red-700">
                  <span class="material-symbols-outlined text-[18px]">delete</span>
                </button>
                <span v-if="r.approval_status === 'pending'" class="text-xs text-text-secondary">{{ $t('financeSimple.pendingApproval') }}</span>
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
      <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto max-sm:fixed max-sm:inset-0 max-sm:max-w-none max-sm:max-h-none max-sm:m-0 max-sm:rounded-none">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
          <h3 class="text-lg font-semibold text-text-primary">{{ editingRecord ? $t('financeSimple.editExpense') : $t('financeSimple.addExpense') }}</h3>
          <button @click="showModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.expenseDate') }} *</label>
              <input v-model="formData.expense_date" type="date" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.category') }} *</label>
              <select v-model="formData.category" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                <option v-for="c in categories" :key="c.value" :value="c.value">{{ $t(c.label) }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.amount') }} *</label>
            <input v-model="formData.amount" type="number" step="0.01" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div class="grid grid-cols-2 gap-4">
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
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.store') }}</label>
            <select v-model="formData.store_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option value="">{{ $t('common.none') }}</option>
              <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.payee') }}</label>
            <input v-model="formData.payee" type="text" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.description') }} *</label>
            <input v-model="formData.description" type="text" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
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
  /* 筛选区域 */
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-4.mb-6 {
    padding: 12px;
  }
  .flex.flex-wrap.items-center.gap-3.mb-3.max-sm\:flex-col.max-sm\:items-stretch {
    gap: 8px;
  }
  .flex.justify-end.max-sm\:flex-col.max-sm\:gap-2 {
    flex-direction: column;
    align-items: stretch;
  }

  /* 表格区域 */
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.overflow-hidden.max-sm\:border-0.max-sm\:shadow-none.max-sm\:rounded-none {
    margin: 0 -12px;
    border-radius: 0;
  }
  .overflow-x-auto {
    font-size: 12px;
  }
  table {
    min-width: 600px;
  }
  th, td {
    padding: 8px 6px !important;
    font-size: 12px;
  }
  th {
    white-space: nowrap;
  }
  .px-4\.py-3 {
    padding: 8px 6px;
  }

  /* 操作按钮 */
  .text-center .material-symbols-outlined {
    font-size: 16px;
  }

  /* 分页 */
  .px-4\.py-3.border-t.border-gray-100 {
    padding: 12px 8px;
  }

  /* 模态框 */
  .fixed.inset-0.bg-black\/50 {
    padding: 0;
  }
  .bg-white.rounded-lg.shadow-xl.w-full.max-w-2xl.max-h-\[90vh\].overflow-y-auto.max-sm\:fixed.max-sm\:inset-0.max-sm\:max-w-none.max-sm\:max-h-none.max-sm\:m-0.max-sm\:rounded-none {
    height: 100vh;
    max-height: 100vh;
    border-radius: 0;
    max-width: 100vw;
  }
  .p-6 {
    padding: 16px;
  }
  .p-6.space-y-4 {
    padding: 12px;
  }
  .grid.grid-cols-2.gap-4.max-sm\:grid-cols-1 {
    grid-template-columns: 1fr;
    gap: 12px;
  }
  .grid.grid-cols-2.gap-4 {
    grid-template-columns: 1fr;
    gap: 12px;
  }
  input, select, textarea {
    font-size: 14px;
  }
  .block.text-sm.font-medium.text-text-primary.mb-1 {
    font-size: 13px;
  }
  label {
    margin-bottom: 4px;
  }

  /* 模态框底部按钮 */
  .flex.items-center.justify-between.gap-3.p-6.border-t.border-gray-100.max-sm\:gap-2 {
    padding: 12px;
    gap: 8px;
  }
  button.px-4\.py-2 {
    flex: 1;
    padding: 10px 12px;
    font-size: 14px;
  }
}
</style>
