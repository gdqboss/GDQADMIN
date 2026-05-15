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

const selectedSupplier = ref(null)
const transactions = ref([])
const showTransactionModal = ref(false)
const showPaymentModal = ref(false)
const accounts = ref([])

const paymentForm = ref({
  payment_date: new Date().toISOString().split('T')[0],
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
    const res = await api.get('/finance-simple/accounts-payable', { params })
    if (res.code === 0) {
      records.value = res.data.list
      total.value = res.data.total
    }
  } finally { loading.value = false }
}

async function fetchAccounts() {
  const res = await api.get('/finance-simple/accounts')
  if (res.code === 0) accounts.value = res.data.filter(a => a.status === 'active')
}

async function viewTransactions(supplier) {
  selectedSupplier.value = supplier
  const res = await api.get(`/finance-simple/accounts-payable/${supplier.supplier_id}/transactions`)
  if (res.code === 0) {
    transactions.value = res.data
    showTransactionModal.value = true
  }
}

function openPaymentModal(supplier) {
  paymentForm.value = {
    payment_date: new Date().toISOString().split('T')[0],
    supplier_id: supplier.supplier_id,
    amount: '',
    payment_method: 'bank',
    account_id: '',
    note: ''
  }
  selectedSupplier.value = supplier
  showPaymentModal.value = true
}

async function submitPayment() {
  try {
    await api.post('/finance-simple/payments', paymentForm.value)
    showPaymentModal.value = false
    fetchRecords()
  } catch (err) {
    alert(err.message || t('financeSimple.paymentFailed'))
  }
}

watch(currentPage, fetchRecords)
onMounted(() => {
  fetchRecords()
  fetchAccounts()
})

function formatMoney(val) {
  return '¥' + Number(val || 0).toFixed(2)
}

function formatDate(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleDateString('zh-CN')
}
</script>

<template>
  <div>
    <PageHeader :title="$t('financeSimple.accountsPayable')" :subtitle="$t('financeSimple.accountsPayableSubtitle')" />

    <!-- Table -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.supplier') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.contactPerson') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.phone') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.totalPayable') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.totalPaid') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.currentBalance') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.lastTransactionDate') }}</th>
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
            <tr v-for="r in records" :key="r.supplier_id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 font-medium text-text-primary">{{ r.supplier_name }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ r.contact_person || '-' }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ r.phone || '-' }}</td>
              <td class="px-4 py-3 text-right text-text-primary">{{ formatMoney(r.total_payable) }}</td>
              <td class="px-4 py-3 text-right text-green-600">{{ formatMoney(r.total_paid) }}</td>
              <td class="px-4 py-3 text-right font-semibold text-red-600">{{ formatMoney(r.current_balance) }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ formatDate(r.last_transaction_date) }}</td>
              <td class="px-4 py-3 text-center">
                <button @click="viewTransactions(r)" class="text-primary hover:text-primary/80 mr-2">
                  <span class="material-symbols-outlined text-[18px]">visibility</span>
                </button>
                <button @click="openPaymentModal(r)" class="text-green-600 hover:text-green-700">
                  <span class="material-symbols-outlined text-[18px]">payment</span>
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

    <!-- Transaction Modal -->
    <div v-if="showTransactionModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showTransactionModal = false">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
          <h3 class="text-lg font-semibold text-text-primary">{{ selectedSupplier?.supplier_name }} - {{ $t('financeSimple.transactionHistory') }}</h3>
          <button @click="showTransactionModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-6">
          <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
              <tr>
                <th class="px-4 py-3 font-medium">{{ $t('financeSimple.transactionDate') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('financeSimple.transactionType') }}</th>
                <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.amount') }}</th>
                <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.balance') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('financeSimple.paymentMethod') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('common.note') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="t in transactions" :key="t.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-text-secondary">{{ formatDate(t.transaction_date) }}</td>
                <td class="px-4 py-3 text-text-primary">{{ $t('financeSimple.' + t.transaction_type) }}</td>
                <td class="px-4 py-3 text-right" :class="t.amount > 0 ? 'text-red-600' : 'text-green-600'">
                  {{ formatMoney(Math.abs(t.amount)) }}
                </td>
                <td class="px-4 py-3 text-right font-semibold text-text-primary">{{ formatMoney(t.balance) }}</td>
                <td class="px-4 py-3 text-text-secondary">{{ t.payment_method || '-' }}</td>
                <td class="px-4 py-3 text-text-secondary text-xs">{{ t.note || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Payment Modal -->
    <div v-if="showPaymentModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showPaymentModal = false">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
          <h3 class="text-lg font-semibold text-text-primary">{{ $t('financeSimple.makePayment') }}</h3>
          <button @click="showPaymentModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.supplier') }}</label>
            <input :value="selectedSupplier?.supplier_name" disabled class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.currentBalance') }}</label>
            <input :value="formatMoney(selectedSupplier?.current_balance)" disabled class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-red-600 font-semibold" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.paymentDate') }} *</label>
            <input v-model="paymentForm.payment_date" type="date" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.amount') }} *</label>
            <input v-model="paymentForm.amount" type="number" step="0.01" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.paymentMethod') }} *</label>
            <select v-model="paymentForm.payment_method" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option value="cash">{{ $t('financeSimple.cash') }}</option>
              <option value="bank">{{ $t('financeSimple.bank') }}</option>
              <option value="alipay">{{ $t('financeSimple.alipay') }}</option>
              <option value="wechat">{{ $t('financeSimple.wechat') }}</option>
              <option value="other">{{ $t('financeSimple.other') }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('fundAccounts.paymentAccount') || '支付账户' }}</label>
            <select v-model="paymentForm.account_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option value="">{{ $t('common.none') }}</option>
              <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.account_name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('common.note') }}</label>
            <textarea v-model="paymentForm.note" rows="3" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"></textarea>
          </div>
        </div>
        <div class="flex items-center justify-end gap-3 p-6 border-t border-gray-100">
          <button @click="showPaymentModal = false" class="px-4 py-2 border border-gray-200 rounded-lg text-text-secondary hover:text-text-primary transition-colors">
            {{ $t('common.cancel') }}
          </button>
          <button @click="submitPayment" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
            {{ $t('common.confirm') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
