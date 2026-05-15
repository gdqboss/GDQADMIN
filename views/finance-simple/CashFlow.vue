<script setup>
import { ref, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import Pagination from '../../components/Pagination.vue'
import api from '../../services/api.js'

const { t } = useI18n()

const transactions = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = 50

const filterAccount = ref('')
const filterType = ref('')
const filterDateStart = ref('')
const filterDateEnd = ref('')

const accounts = ref([])

const transactionTypes = [
  { value: 'income', label: 'fundAccounts.income', color: 'text-green-600 bg-green-50' },
  { value: 'expense', label: 'fundAccounts.expense', color: 'text-red-600 bg-red-50' },
  { value: 'transfer_in', label: 'fundAccounts.transfer_in', color: 'text-blue-600 bg-blue-50' },
  { value: 'transfer_out', label: 'fundAccounts.transfer_out', color: 'text-orange-600 bg-orange-50' }
]

async function fetchTransactions() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize }
    if (filterAccount.value) params.account_id = filterAccount.value
    if (filterType.value) params.transaction_type = filterType.value
    if (filterDateStart.value) params.date_start = filterDateStart.value
    if (filterDateEnd.value) params.date_end = filterDateEnd.value

    const res = await api.get('/finance-simple/cash-flow', { params })
    if (res.code === 0) {
      transactions.value = res.data.list
      total.value = res.data.total
    }
  } finally { loading.value = false }
}

async function fetchAccounts() {
  const res = await api.get('/finance-simple/accounts')
  if (res.code === 0) accounts.value = res.data
}

watch([filterAccount, filterType, filterDateStart, filterDateEnd], () => {
  currentPage.value = 1
  fetchTransactions()
})
watch(currentPage, fetchTransactions)

onMounted(() => {
  fetchTransactions()
  fetchAccounts()
})

function formatMoney(val) {
  return '¥' + Number(val || 0).toFixed(2)
}

function formatDate(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleDateString('zh-CN')
}

function getTransactionTypeInfo(type) {
  return transactionTypes.find(t => t.value === type) || { label: type, color: 'text-gray-600 bg-gray-50' }
}

function getAccountTypeIcon(type) {
  const map = {
    cash: 'payments',
    bank: 'account_balance',
    alipay: 'account_balance_wallet',
    wechat: 'chat',
    other: 'more_horiz'
  }
  return map[type] || 'account_balance'
}
</script>

<template>
  <div>
    <PageHeader :title="$t('fundAccounts.cashFlow')" :subtitle="$t('fundAccounts.cashFlowSubtitle')" />

    <!-- Filters -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 mb-6">
      <div class="flex flex-wrap items-center gap-3">
        <select v-model="filterAccount" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('fundAccounts.allAccounts') }}</option>
          <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.account_name }}</option>
        </select>
        <select v-model="filterType" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('fundAccounts.allTypes') }}</option>
          <option v-for="t in transactionTypes" :key="t.value" :value="t.value">{{ $t(t.label) }}</option>
        </select>
        <input v-model="filterDateStart" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <span class="text-text-secondary">—</span>
        <input v-model="filterDateEnd" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <button @click="filterAccount=''; filterType=''; filterDateStart=''; filterDateEnd=''" class="text-sm text-text-secondary hover:text-text-primary border border-gray-200 rounded-lg px-3 py-2">
          {{ $t('common.reset') }}
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">{{ $t('fundAccounts.transactionDate') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('fundAccounts.account') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('fundAccounts.type') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('fundAccounts.amount') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('fundAccounts.balanceAfter') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('fundAccounts.relatedBusiness') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('common.description') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('common.creator') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="loading">
              <td colspan="8" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('common.loading') }}</td>
            </tr>
            <tr v-else-if="transactions.length === 0">
              <td colspan="8" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('common.noData') }}</td>
            </tr>
            <tr v-for="tx in transactions" :key="tx.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 text-text-secondary">{{ formatDate(tx.transaction_date) }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <span class="material-symbols-outlined text-[18px] text-text-secondary">{{ getAccountTypeIcon(tx.account_type) }}</span>
                  <span class="text-text-primary">{{ tx.account_name }}</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <span :class="getTransactionTypeInfo(tx.transaction_type).color" class="px-2 py-1 rounded text-xs font-medium">
                  {{ $t(getTransactionTypeInfo(tx.transaction_type).label) }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <span :class="[tx.amount > 0 ? 'text-green-600' : 'text-red-600', 'font-semibold']">
                  {{ tx.amount > 0 ? '+' : '' }}{{ formatMoney(tx.amount) }}
                </span>
              </td>
              <td class="px-4 py-3 text-right text-text-primary font-medium">{{ formatMoney(tx.balance_after) }}</td>
              <td class="px-4 py-3">
                <span v-if="tx.related_type" class="text-xs text-text-secondary bg-gray-50 px-2 py-1 rounded">
                  {{ $t('fundAccounts.' + tx.related_type) }}
                </span>
                <span v-else class="text-text-secondary">-</span>
              </td>
              <td class="px-4 py-3 text-text-primary">{{ tx.description }}</td>
              <td class="px-4 py-3 text-text-secondary text-xs">{{ tx.creator_name }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-gray-100">
        <Pagination :total="total" :page="currentPage" :pageSize="pageSize" @update:page="currentPage = $event" />
      </div>
    </div>
  </div>
</template>
