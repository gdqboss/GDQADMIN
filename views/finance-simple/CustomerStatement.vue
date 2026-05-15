<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../services/api.js'

const { t } = useI18n()

const customerPhone = ref('')
const dateRange = ref([])
const statement = ref(null)
const loading = ref(false)
const recentCustomers = ref([])

onMounted(async () => {
  await loadRecentCustomers()
})

async function loadRecentCustomers() {
  try {
    const res = await api.get('/finance-simple/accounts-receivable', { params: { page: 1, size: 20 } })
    if (res.code === 0) {
      recentCustomers.value = res.data.list || []
    }
  } catch (err) {
    console.error('加载客户列表失败:', err)
  }
}

async function generateStatement() {
  if (!customerPhone.value || !dateRange.value || dateRange.value.length !== 2) {
    alert(t('financeSimple.pleaseEnterCustomerPhoneAndDateRange'))
    return
  }

  loading.value = true
  try {
    const res = await api.get(`/finance-simple/statement/customer/${customerPhone.value}`, {
      params: {
        start_date: dateRange.value[0],
        end_date: dateRange.value[1]
      }
    })
    if (res.code === 0) {
      statement.value = res.data
    }
  } catch (err) {
    console.error('生成对账单失败:', err)
    alert(t('financeSimple.generateStatementFailed'))
  } finally {
    loading.value = false
  }
}

async function exportExcel() {
  if (!statement.value) return

  try {
    const res = await api.post(
      `/finance-simple/statement/customer/${customerPhone.value}/export`,
      {
        start_date: dateRange.value[0],
        end_date: dateRange.value[1]
      },
      { responseType: 'blob' }
    )

    const blob = new Blob([res], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${t('financeSimple.customerStatementFileName')}_${customerPhone.value}_${dateRange.value[0]}_${dateRange.value[1]}.xlsx`
    a.click()
    window.URL.revokeObjectURL(url)
  } catch (err) {
    console.error('导出失败:', err)
    alert(t('common.exportFailed'))
  }
}

function printStatement() {
  window.print()
}

function selectCustomer(phone) {
  customerPhone.value = phone
}
</script>

<template>
  <div class="p-6">
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6 print:hidden">
      <h2 class="text-xl font-bold mb-4">{{ $t('financeSimple.customerStatement') }}</h2>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('financeSimple.customerPhone') }}</label>
          <input
            type="text"
            v-model="customerPhone"
            :placeholder="$t('financeSimple.pleaseEnterCustomerPhone')"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
          >
          <div v-if="recentCustomers.length > 0" class="mt-2">
            <label class="block text-xs text-gray-500 mb-1">{{ $t('financeSimple.recentCustomers') }}</label>
            <div class="flex flex-wrap gap-2">
              <button 
                v-for="customer in recentCustomers.slice(0, 5)" 
                :key="customer.customer_phone"
                @click="selectCustomer(customer.customer_phone)"
                class="text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded"
              >
                {{ customer.customer_name || customer.customer_phone }}
              </button>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('financeSimple.startDate') }}</label>
          <input type="date" v-model="dateRange[0]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('financeSimple.endDate') }}</label>
          <input type="date" v-model="dateRange[1]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
        </div>
      </div>

      <div class="flex gap-3">
        <button @click="generateStatement" :disabled="loading" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark disabled:opacity-50">
          {{ loading ? $t('financeSimple.generating') : $t('financeSimple.generateStatement') }}
        </button>
        <button v-if="statement" @click="printStatement" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
          {{ $t('common.print') }}
        </button>
        <button v-if="statement" @click="exportExcel" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
          {{ $t('common.exportExcel') }}
        </button>
      </div>
    </div>

    <div v-if="statement" class="bg-white rounded-lg shadow-sm p-8 print:shadow-none">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold">{{ $t('financeSimple.customerStatement') }}</h1>
      </div>

      <div class="grid grid-cols-2 gap-4 mb-8 text-sm">
        <div>
          <p><span class="font-medium">{{ $t('financeSimple.customerName') }}{{ $t('financeSimple.colon') }}</span>{{ statement.customer_name }}</p>
          <p><span class="font-medium">{{ $t('financeSimple.contactPhone') }}{{ $t('financeSimple.colon') }}</span>{{ statement.customer_phone }}</p>
        </div>
        <div>
          <p><span class="font-medium">{{ $t('financeSimple.statementPeriod') }}{{ $t('financeSimple.colon') }}</span>{{ statement.start_date }} {{ $t('financeSimple.to') }} {{ statement.end_date }}</p>
        </div>
      </div>

      <div class="mb-6">
        <p class="text-lg font-bold text-green-600">{{ $t('financeSimple.openingReceivableBalance') }}{{ $t('financeSimple.colon') }}¥{{ statement.opening_balance.toFixed(2) }}</p>
      </div>

      <div class="mb-8">
        <h3 class="text-lg font-bold mb-3">{{ $t('financeSimple.salesDetails') }}</h3>
        <table class="w-full border-collapse border border-gray-300">
          <thead>
            <tr class="bg-gray-100">
              <th class="border border-gray-300 px-4 py-2 text-left">{{ $t('financeSimple.date') }}</th>
              <th class="border border-gray-300 px-4 py-2 text-left">{{ $t('financeSimple.recordNo') }}</th>
              <th class="border border-gray-300 px-4 py-2 text-left">{{ $t('financeSimple.product') }}</th>
              <th class="border border-gray-300 px-4 py-2 text-right">{{ $t('financeSimple.quantity') }}</th>
              <th class="border border-gray-300 px-4 py-2 text-right">{{ $t('financeSimple.amount') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in statement.sales" :key="item.record_no">
              <td class="border border-gray-300 px-4 py-2">{{ item.sale_date }}</td>
              <td class="border border-gray-300 px-4 py-2">{{ item.record_no }}</td>
              <td class="border border-gray-300 px-4 py-2">{{ item.product_name }}</td>
              <td class="border border-gray-300 px-4 py-2 text-right">{{ item.quantity }}</td>
              <td class="border border-gray-300 px-4 py-2 text-right">¥{{ item.total_revenue.toFixed(2) }}</td>
            </tr>
            <tr v-if="statement.sales.length === 0">
              <td colspan="5" class="border border-gray-300 px-4 py-2 text-center text-gray-500">{{ $t('financeSimple.noSalesRecords') }}</td>
            </tr>
            <tr class="font-bold bg-gray-50">
              <td colspan="4" class="border border-gray-300 px-4 py-2 text-right">{{ $t('financeSimple.subtotal') }}{{ $t('financeSimple.colon') }}</td>
              <td class="border border-gray-300 px-4 py-2 text-right text-green-600">¥{{ statement.total_sales.toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mb-8">
        <h3 class="text-lg font-bold mb-3">{{ $t('financeSimple.receiptDetails') }}</h3>
        <table class="w-full border-collapse border border-gray-300">
          <thead>
            <tr class="bg-gray-100">
              <th class="border border-gray-300 px-4 py-2 text-left">{{ $t('financeSimple.date') }}</th>
              <th class="border border-gray-300 px-4 py-2 text-left">{{ $t('financeSimple.receiptMethod') }}</th>
              <th class="border border-gray-300 px-4 py-2 text-right">{{ $t('financeSimple.amount') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in statement.receipts" :key="index">
              <td class="border border-gray-300 px-4 py-2">{{ item.receipt_date }}</td>
              <td class="border border-gray-300 px-4 py-2">{{ item.payment_method }}</td>
              <td class="border border-gray-300 px-4 py-2 text-right">¥{{ item.amount.toFixed(2) }}</td>
            </tr>
            <tr v-if="statement.receipts.length === 0">
              <td colspan="3" class="border border-gray-300 px-4 py-2 text-center text-gray-500">{{ $t('financeSimple.noReceiptRecords') }}</td>
            </tr>
            <tr class="font-bold bg-gray-50">
              <td colspan="2" class="border border-gray-300 px-4 py-2 text-right">{{ $t('financeSimple.subtotal') }}{{ $t('financeSimple.colon') }}</td>
              <td class="border border-gray-300 px-4 py-2 text-right text-red-600">¥{{ statement.total_receipts.toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-8 pt-6 border-t-2 border-gray-300">
        <p class="text-xl font-bold text-green-600">{{ $t('financeSimple.closingReceivableBalance') }}{{ $t('financeSimple.colon') }}¥{{ statement.closing_balance.toFixed(2) }}</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
@media print {
  body {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
  }
  
  .print\:hidden {
    display: none !important;
  }
  
  .print\:shadow-none {
    box-shadow: none !important;
  }
}
</style>
