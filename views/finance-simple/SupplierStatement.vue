<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../services/api.js'

const { t } = useI18n()

const suppliers = ref([])
const selectedSupplier = ref(null)
const dateRange = ref([])
const statement = ref(null)
const loading = ref(false)

onMounted(async () => {
  await loadSuppliers()
})

async function loadSuppliers() {
  try {
    const res = await api.get('/suppliers', { params: { page: 1, size: 1000 } })
    if (res.code === 0) {
      suppliers.value = Array.isArray(res.data) ? res.data : (res.data.list || [])
    }
  } catch (err) {
    console.error('加载供货商失败:', err)
  }
}

async function generateStatement() {
  if (!selectedSupplier.value || !dateRange.value || dateRange.value.length !== 2) {
    alert(t('financeSimple.pleaseSelectSupplierAndDateRange'))
    return
  }

  loading.value = true
  try {
    const res = await api.get(`/finance-simple/statement/supplier/${selectedSupplier.value}`, {
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
      `/finance-simple/statement/supplier/${selectedSupplier.value}/export`,
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
    a.download = `${t('financeSimple.supplierStatementFileName')}_${statement.value.supplier_name}_${dateRange.value[0]}_${dateRange.value[1]}.xlsx`
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
</script>

<template>
  <div class="p-6">
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6 print:hidden">
      <h2 class="text-xl font-bold mb-4">{{ $t('financeSimple.supplierStatement') }}</h2>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('financeSimple.selectSupplier') }}</label>
          <select v-model="selectedSupplier" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            <option :value="null">{{ $t('financeSimple.pleaseSelectSupplier') }}</option>
            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
              {{ supplier.name }}
            </option>
          </select>
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
        <h1 class="text-3xl font-bold">{{ $t('financeSimple.supplierStatement') }}</h1>
      </div>

      <div class="grid grid-cols-2 gap-4 mb-8 text-sm">
        <div>
          <p><span class="font-medium">{{ $t('financeSimple.supplierName') }}{{ $t('financeSimple.colon') }}</span>{{ statement.supplier_name }}</p>
          <p><span class="font-medium">{{ $t('financeSimple.contactPerson') }}{{ $t('financeSimple.colon') }}</span>{{ statement.contact_person || '-' }}</p>
        </div>
        <div>
          <p><span class="font-medium">{{ $t('financeSimple.contactPhone') }}{{ $t('financeSimple.colon') }}</span>{{ statement.phone || '-' }}</p>
          <p><span class="font-medium">{{ $t('financeSimple.statementPeriod') }}{{ $t('financeSimple.colon') }}</span>{{ statement.start_date }} {{ $t('financeSimple.to') }} {{ statement.end_date }}</p>
        </div>
      </div>

      <div class="mb-6">
        <p class="text-lg font-bold text-red-600">{{ $t('financeSimple.openingPayableBalance') }}{{ $t('financeSimple.colon') }}¥{{ statement.opening_balance.toFixed(2) }}</p>
      </div>

      <div class="mb-8">
        <h3 class="text-lg font-bold mb-3">{{ $t('financeSimple.purchaseDetails') }}</h3>
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
            <tr v-for="item in statement.purchases" :key="item.record_no">
              <td class="border border-gray-300 px-4 py-2">{{ item.purchase_date }}</td>
              <td class="border border-gray-300 px-4 py-2">{{ item.record_no }}</td>
              <td class="border border-gray-300 px-4 py-2">{{ item.product_name }}</td>
              <td class="border border-gray-300 px-4 py-2 text-right">{{ item.quantity }}</td>
              <td class="border border-gray-300 px-4 py-2 text-right">¥{{ item.total_amount.toFixed(2) }}</td>
            </tr>
            <tr v-if="statement.purchases.length === 0">
              <td colspan="5" class="border border-gray-300 px-4 py-2 text-center text-gray-500">{{ $t('financeSimple.noPurchaseRecords') }}</td>
            </tr>
            <tr class="font-bold bg-gray-50">
              <td colspan="4" class="border border-gray-300 px-4 py-2 text-right">{{ $t('financeSimple.subtotal') }}{{ $t('financeSimple.colon') }}</td>
              <td class="border border-gray-300 px-4 py-2 text-right text-red-600">¥{{ statement.total_purchases.toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mb-8">
        <h3 class="text-lg font-bold mb-3">{{ $t('financeSimple.paymentDetails') }}</h3>
        <table class="w-full border-collapse border border-gray-300">
          <thead>
            <tr class="bg-gray-100">
              <th class="border border-gray-300 px-4 py-2 text-left">{{ $t('financeSimple.date') }}</th>
              <th class="border border-gray-300 px-4 py-2 text-left">{{ $t('financeSimple.paymentMethod') }}</th>
              <th class="border border-gray-300 px-4 py-2 text-right">{{ $t('financeSimple.amount') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in statement.payments" :key="index">
              <td class="border border-gray-300 px-4 py-2">{{ item.payment_date }}</td>
              <td class="border border-gray-300 px-4 py-2">{{ item.payment_method }}</td>
              <td class="border border-gray-300 px-4 py-2 text-right">¥{{ item.amount.toFixed(2) }}</td>
            </tr>
            <tr v-if="statement.payments.length === 0">
              <td colspan="3" class="border border-gray-300 px-4 py-2 text-center text-gray-500">{{ $t('financeSimple.noPaymentRecords') }}</td>
            </tr>
            <tr class="font-bold bg-gray-50">
              <td colspan="2" class="border border-gray-300 px-4 py-2 text-right">{{ $t('financeSimple.subtotal') }}{{ $t('financeSimple.colon') }}</td>
              <td class="border border-gray-300 px-4 py-2 text-right text-green-600">¥{{ statement.total_payments.toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-8 pt-6 border-t-2 border-gray-300">
        <p class="text-xl font-bold text-red-600">{{ $t('financeSimple.closingPayableBalance') }}{{ $t('financeSimple.colon') }}¥{{ statement.closing_balance.toFixed(2) }}</p>
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
