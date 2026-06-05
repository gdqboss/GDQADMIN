<script setup>
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'

const { t, locale: i18nLocale } = useI18n()
const locale = computed(() => i18nLocale.value || 'en')
const router = useRouter()

const accounts = ref([])
const loading = ref(false)
const showModal = ref(false)
const showTransferModal = ref(false)
const showTransactionsModal = ref(false)
const editingAccount = ref(null)
const selectedAccount = ref(null)
const transactions = ref([])
const transactionsTotal = ref(0)
const transactionsPage = ref(1)
const transactionsPageSize = 50

const formData = ref({
  account_name: '',
  account_type: 'cash',
  account_number: '',
  bank_name: '',
  balance: 0,
  status: 'active'
})

const transferData = ref({
  from_account_id: '',
  to_account_id: '',
  amount: '',
  transaction_date: new Date().toISOString().split('T')[0],
  description: ''
})

const accountTypes = [
  { value: 'cash', label: 'fundAccounts.cash', icon: 'payments', color: 'bg-green-500' },
  { value: 'bank', label: 'fundAccounts.bank', icon: 'account_balance', color: 'bg-blue-500' },
  { value: 'alipay', label: 'fundAccounts.alipay', icon: 'account_balance_wallet', color: 'bg-cyan-500' },
  { value: 'wechat', label: 'fundAccounts.wechat', icon: 'chat', color: 'bg-green-600' },
  { value: 'other', label: 'fundAccounts.other', icon: 'more_horiz', color: 'bg-gray-500' }
]

const totalBalance = computed(() => {
  return accounts.value.reduce((sum, acc) => sum + Number(acc.balance), 0)
})

async function fetchAccounts() {
  loading.value = true
  try {
    const res = await api.get('/finance-simple/accounts')
    if (res.code === 0) accounts.value = res.data
  } finally { loading.value = false }
}

onMounted(fetchAccounts)

function openCreateModal() {
  editingAccount.value = null
  formData.value = {
    account_name: '',
    account_type: 'cash',
    account_number: '',
    bank_name: '',
    balance: 0,
    status: 'active'
  }
  showModal.value = true
}

function openEditModal(account) {
  editingAccount.value = account
  formData.value = {
    account_name: account.account_name,
    account_type: account.account_type,
    account_number: account.account_number || '',
    bank_name: account.bank_name || '',
    balance: account.balance,
    status: account.status
  }
  showModal.value = true
}

async function saveAccount() {
  try {
    if (editingAccount.value) {
      await api.put(`/finance-simple/accounts/${editingAccount.value.id}`, formData.value)
    } else {
      await api.post('/finance-simple/accounts', formData.value)
    }
    showModal.value = false
    fetchAccounts()
  } catch (err) {
    alert(err.message || t('common.operationFailed'))
  }
}

async function deleteAccount(id) {
  if (!confirm(t('common.confirmDelete'))) return
  try {
    await api.delete(`/finance-simple/accounts/${id}`)
    fetchAccounts()
  } catch (err) {
    alert(err.message || t('common.deleteFailed'))
  }
}

function openTransferModal() {
  transferData.value = {
    from_account_id: '',
    to_account_id: '',
    amount: '',
    transaction_date: new Date().toISOString().split('T')[0],
    description: ''
  }
  showTransferModal.value = true
}

async function executeTransfer() {
  try {
    await api.post('/finance-simple/accounts/transfer', transferData.value)
    showTransferModal.value = false
    fetchAccounts()
  } catch (err) {
    alert(err.message || t('common.transferFailed'))
  }
}

async function viewTransactions(account) {
  selectedAccount.value = account
  transactionsPage.value = 1
  await fetchTransactions()
  showTransactionsModal.value = true
}

async function fetchTransactions() {
  try {
    const res = await api.get(`/finance-simple/accounts/${selectedAccount.value.id}/transactions`, {
      params: { page: transactionsPage.value, size: transactionsPageSize }
    })
    if (res.code === 0) {
      transactions.value = res.data.list
      transactionsTotal.value = res.data.total
    }
  } catch (err) {
    console.error(err)
  }
}

function getAccountTypeInfo(type) {
  return accountTypes.find(t => t.value === type) || accountTypes[4]
}

function formatMoney(val) {
  return '¥' + Number(val || 0).toFixed(2)
}

function formatDate(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleDateString(locale.value, { year: 'numeric', month: '2-digit', day: '2-digit' })
}

function getTransactionTypeColor(type) {
  const map = {
    income: 'text-green-600 bg-green-50',
    expense: 'text-red-600 bg-red-50',
    transfer_in: 'text-blue-600 bg-blue-50',
    transfer_out: 'text-orange-600 bg-orange-50'
  }
  return map[type] || 'text-gray-600 bg-gray-50'
}
</script>

<template>
  <div>
    <PageHeader :title="$t('fundAccounts.title')" :subtitle="$t('fundAccounts.subtitle')">
      <div class="flex items-center gap-3">
        <button @click="openTransferModal" class="flex items-center gap-2 bg-white border border-gray-200 text-text-primary px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors">
          <span class="material-symbols-outlined text-[18px]">swap_horiz</span>
          {{ $t('fundAccounts.transfer') }}
        </button>
        <button @click="openCreateModal" class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors">
          <span class="material-symbols-outlined text-[18px]">add</span>
          {{ $t('fundAccounts.addAccount') }}
        </button>
      </div>
    </PageHeader>

    <!-- Total Balance Card -->
    <div class="bg-gradient-to-br from-primary to-primary/80 rounded-lg shadow-card p-6 mb-6 text-white">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-sm opacity-90 mb-1">{{ $t('fundAccounts.totalBalance') }}</div>
          <div class="text-3xl font-bold">{{ formatMoney(totalBalance) }}</div>
        </div>
        <span class="material-symbols-outlined text-[48px] opacity-20">account_balance</span>
      </div>
    </div>

    <!-- Accounts Grid -->
    <div v-if="loading" class="text-center py-12 text-text-secondary">{{ $t('common.loading') }}</div>
    <div v-else-if="accounts.length === 0" class="bg-white rounded-lg border border-gray-100 shadow-card p-12 text-center text-text-secondary">
      {{ $t('common.noData') }}
    </div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="acc in accounts" :key="acc.id" class="bg-white rounded-lg border border-gray-100 shadow-card p-5 hover:shadow-lg transition-shadow">
        <div class="flex items-start justify-between mb-4">
          <div class="flex items-center gap-3">
            <div :class="[getAccountTypeInfo(acc.account_type).color, 'w-12 h-12 rounded-lg flex items-center justify-center text-white']">
              <span class="material-symbols-outlined text-[24px]">{{ getAccountTypeInfo(acc.account_type).icon }}</span>
            </div>
            <div>
              <div class="font-semibold text-text-primary">{{ acc.account_name }}</div>
              <div class="text-xs text-text-secondary">{{ $t(getAccountTypeInfo(acc.account_type).label) }}</div>
            </div>
          </div>
          <div class="flex items-center gap-1">
            <button @click="openEditModal(acc)" class="text-text-secondary hover:text-primary p-1">
              <span class="material-symbols-outlined text-[18px]">edit</span>
            </button>
            <button @click="deleteAccount(acc.id)" class="text-text-secondary hover:text-red-600 p-1">
              <span class="material-symbols-outlined text-[18px]">delete</span>
            </button>
          </div>
        </div>
        <div class="mb-3">
          <div class="text-2xl font-bold text-text-primary">{{ formatMoney(acc.balance) }}</div>
        </div>
        <div v-if="acc.account_number" class="text-xs text-text-secondary mb-3">
          {{ acc.account_type === 'bank' ? acc.bank_name + ' - ' : '' }}{{ acc.account_number }}
        </div>
        <button @click="viewTransactions(acc)" class="w-full text-sm text-primary hover:text-primary/80 border border-primary/20 rounded-lg py-2 hover:bg-primary/5 transition-colors">
          {{ $t('fundAccounts.viewTransactions') }}
        </button>
      </div>
    </div>

    <!-- Add/Edit Account Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showModal = false">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-sm:fixed max-sm:inset-0 max-sm:max-w-none max-sm:max-h-none max-sm:m-0 max-sm:rounded-none">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
          <h3 class="text-lg font-semibold text-text-primary">{{ editingAccount ? $t('fundAccounts.editAccount') : $t('fundAccounts.addAccount') }}</h3>
          <button @click="showModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('fundAccounts.accountName') }} *</label>
            <input v-model="formData.account_name" type="text" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('fundAccounts.accountType') }} *</label>
            <select v-model="formData.account_type" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option v-for="t in accountTypes" :key="t.value" :value="t.value">{{ $t(t.label) }}</option>
            </select>
          </div>
          <div v-if="formData.account_type === 'bank'">
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('fundAccounts.bankName') }}</label>
            <input v-model="formData.bank_name" type="text" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('fundAccounts.accountNumber') }}</label>
            <input v-model="formData.account_number" type="text" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div v-if="!editingAccount">
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('fundAccounts.initialBalance') }}</label>
            <input v-model="formData.balance" type="number" step="0.01" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div v-if="editingAccount">
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('common.status') }}</label>
            <select v-model="formData.status" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option value="active">{{ $t('fundAccounts.active') }}</option>
              <option value="inactive">{{ $t('fundAccounts.inactive') }}</option>
            </select>
          </div>
        </div>
        <div class="flex items-center justify-between gap-3 p-6 border-t border-gray-100 max-sm:gap-2">
          <button @click="showModal = false" class="px-4 py-2 border border-gray-200 rounded-lg text-text-secondary hover:text-text-primary transition-colors">
            {{ $t('common.cancel') }}
          </button>
          <button @click="saveAccount" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
            {{ $t('common.save') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Transfer Modal -->
    <div v-if="showTransferModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showTransferModal = false">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-sm:fixed max-sm:inset-0 max-sm:max-w-none max-sm:max-h-none max-sm:m-0 max-sm:rounded-none">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
          <h3 class="text-lg font-semibold text-text-primary">{{ $t('fundAccounts.transfer') }}</h3>
          <button @click="showTransferModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('fundAccounts.fromAccount') }} *</label>
            <select v-model="transferData.from_account_id" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option value="">{{ $t('common.pleaseSelect') }}</option>
              <option v-for="acc in accounts.filter(a => a.status === 'active')" :key="acc.id" :value="acc.id">
                {{ acc.account_name }} ({{ formatMoney(acc.balance) }})
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('fundAccounts.toAccount') }} *</label>
            <select v-model="transferData.to_account_id" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option value="">{{ $t('common.pleaseSelect') }}</option>
              <option v-for="acc in accounts.filter(a => a.status === 'active' && a.id !== Number(transferData.from_account_id))" :key="acc.id" :value="acc.id">
                {{ acc.account_name }} ({{ formatMoney(acc.balance) }})
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('fundAccounts.amount') }} *</label>
            <input v-model="transferData.amount" type="number" step="0.01" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('fundAccounts.transactionDate') }} *</label>
            <input v-model="transferData.transaction_date" type="date" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('common.description') }}</label>
            <textarea v-model="transferData.description" rows="3" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"></textarea>
          </div>
        </div>
        <div class="flex items-center justify-end gap-3 p-6 border-t border-gray-100">
          <button @click="showTransferModal = false" class="px-4 py-2 border border-gray-200 rounded-lg text-text-secondary hover:text-text-primary transition-colors">
            {{ $t('common.cancel') }}
          </button>
          <button @click="executeTransfer" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
            {{ $t('common.confirm') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Transactions Modal -->
    <div v-if="showTransactionsModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showTransactionsModal = false">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto max-sm:fixed max-sm:inset-0 max-sm:max-w-none max-sm:max-h-none max-sm:m-0 max-sm:rounded-none">
        <div class="flex items-center justify-between p-6 border-b border-gray-100 sticky top-0 bg-white">
          <h3 class="text-lg font-semibold text-text-primary">{{ selectedAccount?.account_name }} - {{ $t('fundAccounts.transactions') }}</h3>
          <button @click="showTransactionsModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-6">
          <div v-if="transactions.length === 0" class="text-center py-8 text-text-secondary">{{ $t('common.noData') }}</div>
          <div v-else class="space-y-3">
            <div v-for="tx in transactions" :key="tx.id" class="border border-gray-100 rounded-lg p-4 hover:bg-gray-50 transition-colors">
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-2">
                    <span :class="getTransactionTypeColor(tx.transaction_type)" class="px-2 py-1 rounded text-xs font-medium">
                      {{ $t('fundAccounts.' + tx.transaction_type) }}
                    </span>
                    <span v-if="tx.related_type" class="text-xs text-text-secondary">{{ $t('fundAccounts.' + tx.related_type) }}</span>
                  </div>
                  <div class="text-sm text-text-primary mb-1">{{ tx.description }}</div>
                  <div class="text-xs text-text-secondary">{{ formatDate(tx.transaction_date) }} · {{ tx.creator_name }}</div>
                </div>
                <div class="text-right">
                  <div :class="[tx.amount > 0 ? 'text-green-600' : 'text-red-600', 'text-lg font-semibold']">
                    {{ tx.amount > 0 ? '+' : '' }}{{ formatMoney(tx.amount) }}
                  </div>
                  <div class="text-xs text-text-secondary">{{ $t('fundAccounts.balanceAfter') }}: {{ formatMoney(tx.balance_after) }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@media (max-width: 768px) {
  /* Header buttons - stack vertically */
  :deep(.page-header) .flex {
    flex-direction: column;
    gap: 12px;
  }

  /* Total balance card - adjust padding and font size */
  .bg-gradient-to-br {
    padding: 16px;
  }
  .bg-gradient-to-br .text-3xl {
    font-size: 24px;
  }
  .bg-gradient-to-br .text-\[48px\] {
    font-size: 32px;
  }

  /* Account cards - reduce padding */
  .shadow-card {
    padding: 16px;
  }
  .shadow-card .text-2xl {
    font-size: 20px;
  }
  .shadow-card .w-12 {
    width: 40px;
    height: 40px;
  }
  .shadow-card .text-\[24px\] {
    font-size: 20px;
  }

  /* Buttons - full width on mobile */
  button[class*="bg-primary"] {
    width: 100%;
    justify-content: center;
  }

  /* Modals - full screen on mobile */
  .fixed.inset-0 {
    padding: 0;
  }
  .bg-white.rounded-lg {
    border-radius: 0;
    max-height: 100vh;
    height: 100vh;
  }

  /* Form inputs - larger touch targets */
  input, select, textarea {
    padding: 12px;
    font-size: 16px; /* Prevent iOS zoom */
  }

  /* Transaction items - compact layout */
  .border.rounded-lg.p-4 {
    padding: 12px;
  }
  .space-y-3 > div {
    margin-bottom: 8px;
  }
}
</style>
