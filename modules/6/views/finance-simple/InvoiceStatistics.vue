<template>
  <div class="invoice-statistics p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold">{{ $t('invoice.statistics') }}</h1>
    </div>

    <!-- Date Filter -->
    <div class="mb-6 flex gap-4">
      <input
        v-model="filters.start_date"
        type="date"
        class="px-3 py-2 border rounded"
      />
      <input
        v-model="filters.end_date"
        type="date"
        class="px-3 py-2 border rounded"
      />
      <button
        @click="loadStatistics"
        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
      >
        {{ $t('common.search') }}
      </button>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 gap-6 mb-8">
      <!-- Input Invoices -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4 text-blue-600">{{ $t('invoice.inputInvoices') }}</h2>
        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-gray-600">{{ $t('invoice.count') }}</span>
            <span class="font-bold text-xl">{{ summary.input.count }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">{{ $t('invoice.totalAmount') }}</span>
            <span class="font-bold text-xl text-blue-600">¥{{ formatNumber(summary.input.total_amount) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">{{ $t('invoice.taxAmount') }}</span>
            <span class="font-medium text-lg">¥{{ formatNumber(summary.input.tax_amount) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">{{ $t('invoice.amountWithoutTax') }}</span>
            <span class="font-medium text-lg">¥{{ formatNumber(summary.input.amount_without_tax) }}</span>
          </div>
        </div>
      </div>

      <!-- Output Invoices -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4 text-green-600">{{ $t('invoice.outputInvoices') }}</h2>
        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-gray-600">{{ $t('invoice.count') }}</span>
            <span class="font-bold text-xl">{{ summary.output.count }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">{{ $t('invoice.totalAmount') }}</span>
            <span class="font-bold text-xl text-green-600">¥{{ formatNumber(summary.output.total_amount) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">{{ $t('invoice.taxAmount') }}</span>
            <span class="font-medium text-lg">¥{{ formatNumber(summary.output.tax_amount) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">{{ $t('invoice.amountWithoutTax') }}</span>
            <span class="font-medium text-lg">¥{{ formatNumber(summary.output.amount_without_tax) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Monthly Chart -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
      <h2 class="text-lg font-semibold mb-4">{{ $t('invoice.monthlyTrend') }}</h2>
      <div class="h-80">
        <canvas ref="monthlyChart"></canvas>
      </div>
    </div>

    <!-- Type Distribution -->
    <div class="grid grid-cols-2 gap-6">
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">{{ $t('invoice.inputTypeDistribution') }}</h2>
        <div class="h-64">
          <canvas ref="inputTypeChart"></canvas>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">{{ $t('invoice.outputTypeDistribution') }}</h2>
        <div class="h-64">
          <canvas ref="outputTypeChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../services/api.js'
import Chart from 'chart.js/auto'

const { t } = useI18n()

const monthlyChart = ref(null)
const inputTypeChart = ref(null)
const outputTypeChart = ref(null)

let monthlyChartInstance = null
let inputTypeChartInstance = null
let outputTypeChartInstance = null

const filters = reactive({
  start_date: '',
  end_date: ''
})

const summary = reactive({
  input: { count: 0, total_amount: 0, tax_amount: 0, amount_without_tax: 0 },
  output: { count: 0, total_amount: 0, tax_amount: 0, amount_without_tax: 0 }
})

const monthlyData = ref([])
const inputTypeData = ref([])
const outputTypeData = ref([])

onMounted(() => {
  // Set default date range (last 12 months)
  const endDate = new Date()
  const startDate = new Date()
  startDate.setMonth(startDate.getMonth() - 12)

  filters.end_date = endDate.toISOString().split('T')[0]
  filters.start_date = startDate.toISOString().split('T')[0]

  loadStatistics()
})

const loadStatistics = async () => {
  try {
    await Promise.all([
      loadSummary(),
      loadMonthlyData(),
      loadTypeData()
    ])

    await nextTick()
    renderCharts()
  } catch (error) {
    console.error('Failed to load statistics:', error)
  }
}

const loadSummary = async () => {
  const response = await api.get('/invoices/statistics/summary', {
    params: filters
  })
  Object.assign(summary, response.data.data)
}

const loadMonthlyData = async () => {
  const response = await api.get('/invoices/statistics/monthly', {
    params: filters
  })
  monthlyData.value = response.data.data
}

const loadTypeData = async () => {
  const [inputResponse, outputResponse] = await Promise.all([
    axios.get('/api/invoices/statistics/by-type', {
      params: { ...filters, direction: 'input' }
    }),
    axios.get('/api/invoices/statistics/by-type', {
      params: { ...filters, direction: 'output' }
    })
  ])

  inputTypeData.value = inputResponse.data.data
  outputTypeData.value = outputResponse.data.data
}

const renderCharts = () => {
  renderMonthlyChart()
  renderInputTypeChart()
  renderOutputTypeChart()
}

const renderMonthlyChart = () => {
  if (monthlyChartInstance) {
    monthlyChartInstance.destroy()
  }

  const months = [...new Set(monthlyData.value.map(d => d.month))].sort()
  const inputData = months.map(month => {
    const item = monthlyData.value.find(d => d.month === month && d.direction === 'input')
    return item ? parseFloat(item.total_amount) : 0
  })
  const outputData = months.map(month => {
    const item = monthlyData.value.find(d => d.month === month && d.direction === 'output')
    return item ? parseFloat(item.total_amount) : 0
  })

  monthlyChartInstance = new Chart(monthlyChart.value, {
    type: 'bar',
    data: {
      labels: months,
      datasets: [
        {
          label: t('invoice.inputInvoices'),
          data: inputData,
          backgroundColor: 'rgba(59, 130, 246, 0.5)',
          borderColor: 'rgb(59, 130, 246)',
          borderWidth: 1
        },
        {
          label: t('invoice.outputInvoices'),
          data: outputData,
          backgroundColor: 'rgba(34, 197, 94, 0.5)',
          borderColor: 'rgb(34, 197, 94)',
          borderWidth: 1
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: (value) => '¥' + formatNumber(value)
          }
        }
      },
      plugins: {
        tooltip: {
          callbacks: {
            label: (context) => {
              return context.dataset.label + ': ¥' + formatNumber(context.parsed.y)
            }
          }
        }
      }
    }
  })
}

const renderInputTypeChart = () => {
  if (inputTypeChartInstance) {
    inputTypeChartInstance.destroy()
  }

  const labels = inputTypeData.value.map(d => t(`invoice.${d.invoice_type}`))
  const data = inputTypeData.value.map(d => parseFloat(d.total_amount))

  inputTypeChartInstance = new Chart(inputTypeChart.value, {
    type: 'pie',
    data: {
      labels,
      datasets: [{
        data,
        backgroundColor: [
          'rgba(59, 130, 246, 0.7)',
          'rgba(16, 185, 129, 0.7)',
          'rgba(245, 158, 11, 0.7)'
        ]
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        tooltip: {
          callbacks: {
            label: (context) => {
              return context.label + ': ¥' + formatNumber(context.parsed)
            }
          }
        }
      }
    }
  })
}

const renderOutputTypeChart = () => {
  if (outputTypeChartInstance) {
    outputTypeChartInstance.destroy()
  }

  const labels = outputTypeData.value.map(d => t(`invoice.${d.invoice_type}`))
  const data = outputTypeData.value.map(d => parseFloat(d.total_amount))

  outputTypeChartInstance = new Chart(outputTypeChart.value, {
    type: 'pie',
    data: {
      labels,
      datasets: [{
        data,
        backgroundColor: [
          'rgba(34, 197, 94, 0.7)',
          'rgba(59, 130, 246, 0.7)',
          'rgba(245, 158, 11, 0.7)'
        ]
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        tooltip: {
          callbacks: {
            label: (context) => {
              return context.label + ': ¥' + formatNumber(context.parsed)
            }
          }
        }
      }
    }
  })
}

const formatNumber = (num) => {
  return parseFloat(num || 0).toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}
</script>
