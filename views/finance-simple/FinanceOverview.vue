<script setup>
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'

const { t } = useI18n()

const loading = ref(false)
const exporting = ref(false)
const overview = ref({
  total_revenue: 0,
  total_cost: 0,
  gross_profit: 0,
  total_expense: 0,
  net_profit: 0,
  sales_count: 0,
  total_payable: 0,
  total_receivable: 0
})

const dateStart = ref('')
const dateEnd = ref('')

const profitMargin = computed(() => {
  if (overview.value.total_revenue === 0) return 0
  return ((overview.value.gross_profit / overview.value.total_revenue) * 100).toFixed(2)
})

const netProfitMargin = computed(() => {
  if (overview.value.total_revenue === 0) return 0
  return ((overview.value.net_profit / overview.value.total_revenue) * 100).toFixed(2)
})

async function fetchOverview() {
  loading.value = true
  try {
    const params = {}
    if (dateStart.value) params.date_start = dateStart.value
    if (dateEnd.value) params.date_end = dateEnd.value
    const res = await api.get('/finance-simple/overview', { params })
    if (res.code === 0) {
      overview.value = res.data
    }
  } finally {
    loading.value = false
  }
}

onMounted(fetchOverview)

function formatMoney(val) {
  return '¥' + Number(val || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

async function exportExcel() {
  exporting.value = true
  try {
    const params = { period_type: 'month' }
    if (dateStart.value) params.date_start = dateStart.value
    if (dateEnd.value) params.date_end = dateEnd.value

    const res = await api.get('/finance-simple/export/financial-summary', { params, responseType: 'blob' })

    const url = window.URL.createObjectURL(new Blob([res]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `${t('financeSimple.financialSummaryExportFilename')}_${new Date().toISOString().slice(0, 10)}.xlsx`)
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
    <PageHeader :title="$t('financeSimple.overview')" :subtitle="$t('financeSimple.overviewSubtitle')" />

    <!-- Filters -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 mb-6">
      <div class="flex items-center gap-3">
        <span class="text-sm text-text-secondary">{{ $t('common.dateRange') }}:</span>
        <input v-model="dateStart" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <span class="text-text-secondary">—</span>
        <input v-model="dateEnd" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <button @click="fetchOverview" class="bg-primary text-white px-4 py-2 rounded-lg text-sm hover:bg-primary/90 transition-colors">
          {{ $t('common.query') }}
        </button>
        <button @click="dateStart=''; dateEnd=''; fetchOverview()" class="text-sm text-text-secondary hover:text-text-primary border border-gray-200 rounded-lg px-3 py-2">
          {{ $t('common.reset') }}
        </button>
        <button @click="exportExcel" :disabled="exporting" class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
          <span class="material-symbols-outlined text-sm">download</span>
          {{ exporting ? $t('common.exporting') : $t('common.exportExcel') }}
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <!-- 销售收入 -->
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-5">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm text-text-secondary">{{ $t('financeSimple.totalRevenue') }}</span>
          <div class="size-10 rounded-lg bg-green-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-green-600 text-[20px]">trending_up</span>
          </div>
        </div>
        <p class="text-2xl font-bold text-text-primary mb-1">{{ formatMoney(overview.total_revenue) }}</p>
        <p class="text-xs text-text-secondary">{{ $t('financeSimple.salesCount') }}: {{ overview.sales_count }}</p>
      </div>

      <!-- 毛利润 -->
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-5">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm text-text-secondary">{{ $t('financeSimple.grossProfit') }}</span>
          <div class="size-10 rounded-lg bg-blue-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-blue-600 text-[20px]">account_balance</span>
          </div>
        </div>
        <p class="text-2xl font-bold text-text-primary mb-1">{{ formatMoney(overview.gross_profit) }}</p>
        <p class="text-xs text-text-secondary">{{ $t('financeSimple.profitMargin') }}: {{ profitMargin }}%</p>
      </div>

      <!-- 费用支出 -->
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-5">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm text-text-secondary">{{ $t('financeSimple.totalExpense') }}</span>
          <div class="size-10 rounded-lg bg-orange-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-orange-600 text-[20px]">payments</span>
          </div>
        </div>
        <p class="text-2xl font-bold text-text-primary mb-1">{{ formatMoney(overview.total_expense) }}</p>
        <p class="text-xs text-text-secondary">{{ $t('financeSimple.operatingExpense') }}</p>
      </div>

      <!-- 净利润 -->
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-5">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm text-text-secondary">{{ $t('financeSimple.netProfit') }}</span>
          <div class="size-10 rounded-lg bg-purple-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-purple-600 text-[20px]">savings</span>
          </div>
        </div>
        <p class="text-2xl font-bold" :class="overview.net_profit >= 0 ? 'text-green-600' : 'text-red-600'">
          {{ formatMoney(overview.net_profit) }}
        </p>
        <p class="text-xs text-text-secondary">{{ $t('financeSimple.netProfitMargin') }}: {{ netProfitMargin }}%</p>
      </div>
    </div>

    <!-- 应收应付 -->
    <div class="grid grid-cols-2 gap-4 mb-6">
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-5">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm text-text-secondary">{{ $t('financeSimple.totalPayable') }}</span>
          <div class="size-10 rounded-lg bg-red-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-red-600 text-[20px]">arrow_upward</span>
          </div>
        </div>
        <p class="text-2xl font-bold text-red-600">{{ formatMoney(overview.total_payable) }}</p>
        <p class="text-xs text-text-secondary">{{ $t('financeSimple.payableDesc') }}</p>
      </div>

      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-5">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm text-text-secondary">{{ $t('financeSimple.totalReceivable') }}</span>
          <div class="size-10 rounded-lg bg-green-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-green-600 text-[20px]">arrow_downward</span>
          </div>
        </div>
        <p class="text-2xl font-bold text-green-600">{{ formatMoney(overview.total_receivable) }}</p>
        <p class="text-xs text-text-secondary">{{ $t('financeSimple.receivableDesc') }}</p>
      </div>
    </div>

    <!-- Quick Links -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-6">
      <h3 class="text-base font-semibold text-text-primary mb-4">{{ $t('financeSimple.quickLinks') }}</h3>
      <div class="grid grid-cols-4 gap-4">
        <router-link to="/finance/purchase-costs" class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg hover:border-primary hover:bg-primary/5 transition-all">
          <span class="material-symbols-outlined text-primary text-[24px]">shopping_cart</span>
          <span class="text-sm font-medium text-text-primary">{{ $t('financeSimple.purchaseCosts') }}</span>
        </router-link>
        <router-link to="/finance/sales-revenues" class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg hover:border-primary hover:bg-primary/5 transition-all">
          <span class="material-symbols-outlined text-primary text-[24px]">point_of_sale</span>
          <span class="text-sm font-medium text-text-primary">{{ $t('financeSimple.salesRevenues') }}</span>
        </router-link>
        <router-link to="/finance/expenses" class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg hover:border-primary hover:bg-primary/5 transition-all">
          <span class="material-symbols-outlined text-primary text-[24px]">receipt</span>
          <span class="text-sm font-medium text-text-primary">{{ $t('financeSimple.expenses') }}</span>
        </router-link>
        <router-link to="/finance/profit-analysis" class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg hover:border-primary hover:bg-primary/5 transition-all">
          <span class="material-symbols-outlined text-primary text-[24px]">analytics</span>
          <span class="text-sm font-medium text-text-primary">{{ $t('financeSimple.profitAnalysis') }}</span>
        </router-link>
        <router-link to="/finance/accounts-payable" class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg hover:border-primary hover:bg-primary/5 transition-all">
          <span class="material-symbols-outlined text-primary text-[24px]">payments</span>
          <span class="text-sm font-medium text-text-primary">{{ $t('financeSimple.accountsPayable') }}</span>
        </router-link>
        <router-link to="/finance/accounts-receivable" class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg hover:border-primary hover:bg-primary/5 transition-all">
          <span class="material-symbols-outlined text-primary text-[24px]">account_balance_wallet</span>
          <span class="text-sm font-medium text-text-primary">{{ $t('financeSimple.accountsReceivable') }}</span>
        </router-link>
        <router-link to="/finance/supplier-statement" class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg hover:border-primary hover:bg-primary/5 transition-all">
          <span class="material-symbols-outlined text-primary text-[24px]">description</span>
          <span class="text-sm font-medium text-text-primary">{{ $t('financeSimple.supplierStatement') }}</span>
        </router-link>
        <router-link to="/finance/customer-statement" class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg hover:border-primary hover:bg-primary/5 transition-all">
          <span class="material-symbols-outlined text-primary text-[24px]">assignment</span>
          <span class="text-sm font-medium text-text-primary">{{ $t('financeSimple.customerStatement') }}</span>
        </router-link>
        <router-link to="/finance/reminders" class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg hover:border-primary hover:bg-primary/5 transition-all">
          <span class="material-symbols-outlined text-primary text-[24px]">notifications</span>
          <span class="text-sm font-medium text-text-primary">{{ $t('financeSimple.reminders') }}</span>
        </router-link>
        <router-link to="/finance/reminder-settings" class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg hover:border-primary hover:bg-primary/5 transition-all">
          <span class="material-symbols-outlined text-primary text-[24px]">tune</span>
          <span class="text-sm font-medium text-text-primary">{{ $t('financeSimple.reminderSettings') }}</span>
        </router-link>
      </div>
    </div>
  </div>
</template>
