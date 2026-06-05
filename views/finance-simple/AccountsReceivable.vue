<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import Pagination from '../../components/Pagination.vue'
import api from '../../services/api.js'

const { t, locale: i18nLocale } = useI18n()
const locale = computed(() => i18nLocale.value || 'en')

const records = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = 50

const selectedCustomer = ref(null)
const transactions = ref([])
const showTransactionModal = ref(false)
const showReceiptModal = ref(false)
const accounts = ref([])

const receiptForm = ref({
  receipt_date: new Date().toISOString().split('T')[0],
  customer_phone: '',
  customer_name: '',
  amount: '',
  payment_method: 'alipay',
  account_id: '',
  note: ''
})

async function fetchRecords() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize }
    const res = await api.get('/finance-simple/accounts-receivable', { params })
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

function getMethodLabel(method) {
  const map = { cash: t('financeSimple.cash'), bank: t('financeSimple.bank'), alipay: t('financeSimple.alipay'), wechat: t('financeSimple.wechat'), other: t('financeSimple.other') }
  return map[method] || method
}

async function viewTransactions(customer) {
  selectedCustomer.value = customer
  const res = await api.get(`/finance-simple/accounts-receivable/${customer.customer_phone}/transactions`)
  if (res.code === 0) {
    transactions.value = res.data
    showTransactionModal.value = true
  }
}

function openReceiptModal(customer) {
  receiptForm.value = {
    receipt_date: new Date().toISOString().split('T')[0],
    customer_phone: customer.customer_phone,
    customer_name: customer.customer_name || '',
    amount: '',
    payment_method: 'alipay',
    account_id: '',
    note: ''
  }
  selectedCustomer.value = customer
  showReceiptModal.value = true
}

async function submitReceipt() {
  try {
    await api.post('/finance-simple/receipts', receiptForm.value)
    showReceiptModal.value = false
    fetchRecords()
  } catch (err) {
    alert(err.message || t('financeSimple.receiptFailed'))
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
  const d = new Date(dt)
  if (isNaN(d.getTime())) return '-'
  const localeMap = { zh: 'zh-CN', en: 'en-US' }
  return d.toLocaleDateString(localeMap[locale.value] || 'zh-CN')
}
</script>

<template>
  <div>
    <PageHeader :title="$t('financeSimple.accountsReceivable')" :subtitle="$t('financeSimple.accountsReceivableSubtitle')" />

    <!-- Table -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden max-sm:border-0 max-sm:shadow-none max-sm:rounded-none">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.customerPhone') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.customerName') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.totalReceivable') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.totalReceived') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('financeSimple.currentBalance') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('financeSimple.lastTransactionDate') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="loading">
              <td colspan="7" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('common.loading') }}</td>
            </tr>
            <tr v-else-if="records.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('common.noData') }}</td>
            </tr>
            <tr v-for="r in records" :key="r.customer_phone" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 font-mono text-sm text-primary">{{ r.customer_phone }}</td>
              <td class="px-4 py-3 text-text-primary">{{ r.customer_name || '-' }}</td>
              <td class="px-4 py-3 text-right text-text-primary">{{ formatMoney(r.total_receivable) }}</td>
              <td class="px-4 py-3 text-right text-green-600">{{ formatMoney(r.total_received) }}</td>
              <td class="px-4 py-3 text-right font-semibold text-green-600">{{ formatMoney(r.current_balance) }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ formatDate(r.last_transaction_date) }}</td>
              <td class="px-4 py-3 text-center">
                <button @click="viewTransactions(r)" class="text-primary hover:text-primary/80 mr-2">
                  <span class="material-symbols-outlined text-[18px]">visibility</span>
                </button>
                <button @click="openReceiptModal(r)" class="text-green-600 hover:text-green-700">
                  <span class="material-symbols-outlined text-[18px]">account_balance_wallet</span>
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
      <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto max-sm:fixed max-sm:inset-0 max-sm:max-w-none max-sm:max-h-none max-sm:m-0 max-sm:rounded-none">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
          <h3 class="text-lg font-semibold text-text-primary">{{ selectedCustomer?.customer_phone }} - {{ $t('financeSimple.transactionHistory') }}</h3>
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
                <td class="px-4 py-3 text-right" :class="t.amount > 0 ? 'text-green-600' : 'text-red-600'">
                  {{ formatMoney(Math.abs(t.amount)) }}
                </td>
                <td class="px-4 py-3 text-right font-semibold text-text-primary">{{ formatMoney(t.balance) }}</td>
                <td class="px-4 py-3 text-text-secondary">{{ getMethodLabel(t.payment_method) || '-' }}</td>
                <td class="px-4 py-3 text-text-secondary text-xs">{{ t.note || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Receipt Modal -->
    <div v-if="showReceiptModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showReceiptModal = false">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md max-sm:fixed max-sm:inset-0 max-sm:max-w-none max-sm:max-h-none max-sm:m-0 max-sm:rounded-none">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
          <h3 class="text-lg font-semibold text-text-primary">{{ $t('financeSimple.recordReceipt') }}</h3>
          <button @click="showReceiptModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.customerPhone') }}</label>
            <input :value="selectedCustomer?.customer_phone" disabled class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.currentBalance') }}</label>
            <input :value="formatMoney(selectedCustomer?.current_balance)" disabled class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-green-600 font-semibold" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.receiptDate') }} *</label>
            <input v-model="receiptForm.receipt_date" type="date" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.amount') }} *</label>
            <input v-model="receiptForm.amount" type="number" step="0.01" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('financeSimple.paymentMethod') }} *</label>
            <select v-model="receiptForm.payment_method" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option value="cash">{{ $t('financeSimple.cash') }}</option>
              <option value="bank">{{ $t('financeSimple.bank') }}</option>
              <option value="alipay">{{ $t('financeSimple.alipay') }}</option>
              <option value="wechat">{{ $t('financeSimple.wechat') }}</option>
              <option value="other">{{ $t('financeSimple.other') }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('fundAccounts.paymentAccount') || '收款账户' }}</label>
            <select v-model="receiptForm.account_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option value="">{{ $t('common.none') }}</option>
              <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.account_name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('common.note') }}</label>
            <textarea v-model="receiptForm.note" rows="3" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"></textarea>
          </div>
        </div>
        <div class="flex items-center justify-between gap-3 p-6 border-t border-gray-100 max-sm:gap-2">
          <button @click="showReceiptModal = false" class="px-4 py-2 border border-gray-200 rounded-lg text-text-secondary hover:text-text-primary transition-colors">
            {{ $t('common.cancel') }}
          </button>
          <button @click="submitReceipt" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
            {{ $t('common.confirm') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@media (max-width: 768px) {
  /* 容器内边距缩小 */
  .bg-white {
    padding-left: 0;
    padding-right: 0;
  }

  /* 表格横向滚动强化 */
  .overflow-x-auto {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  table {
    min-width: 600px;
  }

  /* 表头文字缩小 */
  thead th {
    font-size: 11px;
    padding: 8px 6px;
  }

  /* 表格单元格缩小 */
  tbody td {
    padding: 8px 6px;
    font-size: 12px;
  }

  /* 操作按钮间距调整 */
  tbody td button {
    margin-right: 4px;
  }

  /* 分页区域适配 */
  .px-4.py-3 {
    padding-left: 8px;
    padding-right: 8px;
  }

  /* 模态框全屏适配 */
  .fixed.inset-0 {
    padding: 0;
  }

  /* 模态框内容区 */
  .bg-white.rounded-lg {
    border-radius: 0;
  }

  /* 表单输入框 */
  input, select, textarea {
    font-size: 16px; /* 防止iOS缩放 */
  }

  /* 按钮适应屏幕 */
  .flex.items-center.justify-between.gap-3 button {
    flex: 1;
    padding: 10px 12px;
    font-size: 14px;
  }
}
</style>
