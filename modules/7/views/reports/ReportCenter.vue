<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { BarChart, LineChart, PieChart } from 'echarts/charts'
import { GridComponent, TooltipComponent, LegendComponent, TitleComponent } from 'echarts/components'
import VChart from 'vue-echarts'


use([CanvasRenderer, BarChart, LineChart, PieChart, GridComponent, TooltipComponent, LegendComponent, TitleComponent])

const { t } = useI18n()

const activeTab = ref('inout')

const tabs = computed(() => [
  { key: 'inout', label: t('report.inoutTrend'), icon: 'swap_horiz' },
  { key: 'price', label: t('report.purchasePriceTrend'), icon: 'trending_up' },
  { key: 'stock', label: t('report.categoryStock'), icon: 'inventory_2' },
])

const periods = computed(() => [
  { key: 'day', label: t('report.periodDay') },
  { key: 'week', label: t('report.periodWeek') },
  { key: 'month', label: t('report.periodMonth') },
  { key: 'quarter', label: t('report.periodQuarter') },
  { key: 'year', label: t('report.periodYear') },
])
const activePeriod = ref('month')

const monthlyInOutData = ref([])
const catStockData = ref([])
const priceData = ref([])
const warehouseStockSummary = ref([])
const inOutBarOption = ref({})
const priceLineOption = ref({})
const categoryPieOption = ref({})

function buildInOutChart(inOut) {
  if (!Array.isArray(inOut) || inOut.length === 0) {
    inOutBarOption.value = {
      title: { text: t('report.noData'), left: 'center', top: 'center', textStyle: { color: '#909399', fontSize: 14 } }
    }
    return
  }

  inOutBarOption.value = {
    tooltip: { trigger: 'axis' },
    legend: { data: [t('inout.inbound'), t('inout.outbound')], top: 0, right: 0, textStyle: { fontSize: 12, color: '#909399' } },
    grid: { left: 50, right: 20, top: 40, bottom: 30 },
    xAxis: { type: 'category', data: inOut.map(d => d.month || ''), axisLine: { lineStyle: { color: '#dcdfe6' } }, axisLabel: { color: '#909399', fontSize: 11 } },
    yAxis: { type: 'value', axisLine: { show: false }, splitLine: { lineStyle: { color: '#f0f0f0' } }, axisLabel: { color: '#909399', fontSize: 11 } },
    series: [
      { name: t('inout.inbound'), type: 'bar', data: inOut.map(d => d.inbound || 0), itemStyle: { color: '#409eff', borderRadius: [4, 4, 0, 0] }, barWidth: 20 },
      { name: t('inout.outbound'), type: 'bar', data: inOut.map(d => d.outbound || 0), itemStyle: { color: '#e6a23c', borderRadius: [4, 4, 0, 0] }, barWidth: 20 },
    ],
  }
}

async function loadInOutData() {
  try {
    const inOut = await api.get(`/reports/monthly-inout?period=${activePeriod.value}`)
    monthlyInOutData.value = Array.isArray(inOut) ? inOut : (inOut?.data || [])
    buildInOutChart(monthlyInOutData.value)
  } catch (err) {
    console.error('Failed to load in/out data:', err)
    monthlyInOutData.value = []
    buildInOutChart([])
  }
}

function setPeriod(key) {
  activePeriod.value = key
}

watch(activePeriod, () => {
  loadInOutData()
})

async function exportExcel() {
  const XLSX = await import('xlsx')
  let rows, sheetName, filename
  if (activeTab.value === 'inout') {
    rows = monthlyInOutData.value.map(d => ({
      [t('report.excelPeriod')]: d.month,
      [t('report.excelInboundQty')]: d.inbound,
      [t('report.excelOutboundQty')]: d.outbound,
      [t('report.excelNetInbound')]: d.inbound - d.outbound,
    }))
    sheetName = t('report.excelInoutSheet')
    filename = `inout-report-${Date.now()}.xlsx`
  } else if (activeTab.value === 'stock') {
    rows = catStockData.value.map(d => ({
      [t('report.excelCategory')]: d.category || t('report.excelUncategorized'),
      [t('report.excelTotalStock')]: d.total_stock,
      [t('report.excelProductCount')]: d.product_count,
    }))
    sheetName = t('report.excelStockSheet')
    filename = `stock-report-${Date.now()}.xlsx`
  } else {
    rows = priceData.value.map(d => ({
      [t('report.excelCategory')]: d.category || t('report.excelUncategorized'),
      [t('report.excelAvgPurchase')]: d.avg_purchase,
      [t('report.excelAvgSale')]: d.avg_sale,
      [t('report.excelAvgProfit')]: d.avg_profit,
      [t('report.excelAvgMargin')]: d.avg_margin,
    }))
    sheetName = t('report.excelPriceSheet')
    filename = `price-report-${Date.now()}.xlsx`
  }
  const ws = XLSX.utils.json_to_sheet(rows)
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, sheetName)
  XLSX.writeFile(wb, filename)
}

onMounted(async () => {
  try {
    const [inOut, catStock, priceAnalysis, whSummary] = await Promise.all([
      api.get(`/reports/monthly-inout?period=${activePeriod.value}`),
      api.get('/reports/category-stock'),
      api.get('/reports/price-analysis'),
      api.get('/reports/warehouse-summary'),
    ])

    // Safely handle API responses
    monthlyInOutData.value = Array.isArray(inOut) ? inOut : (inOut?.data || [])
    catStockData.value = Array.isArray(catStock) ? catStock : (catStock?.data || [])
    priceData.value = Array.isArray(priceAnalysis) ? priceAnalysis : (priceAnalysis?.data || [])
    warehouseStockSummary.value = Array.isArray(whSummary) ? whSummary : (whSummary?.data || [])

    buildInOutChart(monthlyInOutData.value)

    priceLineOption.value = {
      tooltip: { trigger: 'axis' },
      legend: { data: [t('report.sampleProduct1'), t('report.sampleProduct2'), t('report.sampleProduct3')], top: 0, right: 0, textStyle: { fontSize: 11, color: '#909399' } },
      grid: { left: 50, right: 20, top: 40, bottom: 30 },
      xAxis: { type: 'category', data: priceData.value.map(d => d.month || ''), axisLine: { lineStyle: { color: '#dcdfe6' } }, axisLabel: { color: '#909399', fontSize: 11 } },
      yAxis: { type: 'value', name: t('report.purchasePrice'), axisLine: { show: false }, splitLine: { lineStyle: { color: '#f0f0f0' } }, axisLabel: { color: '#909399', fontSize: 11 } },
      series: [
        { name: t('report.sampleProduct1'), type: 'line', data: priceData.value.map(d => d.sku001 || 0), smooth: true, itemStyle: { color: '#409eff' } },
        { name: t('report.sampleProduct2'), type: 'line', data: priceData.value.map(d => d.sku002 || 0), smooth: true, itemStyle: { color: '#67c23a' } },
        { name: t('report.sampleProduct3'), type: 'line', data: priceData.value.map(d => d.sku003 || 0), smooth: true, itemStyle: { color: '#e6a23c' } },
      ],
    }

    categoryPieOption.value = {
      tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
      legend: { orient: 'vertical', right: 10, top: 'center', textStyle: { fontSize: 12, color: '#909399' } },
      series: [{
        type: 'pie',
        radius: ['40%', '70%'],
        center: ['35%', '50%'],
        avoidLabelOverlap: false,
        itemStyle: { borderRadius: 6, borderColor: '#fff', borderWidth: 2 },
        label: { show: false },
        emphasis: { label: { show: true, fontSize: 14, fontWeight: 'bold' } },
        data: catStockData.value.map((d, i) => ({
          name: d.category || d.name || t('report.excelUncategorized'),
          value: d.total_stock || d.value || 0,
          itemStyle: { color: ['#409eff', '#67c23a', '#e6a23c', '#f56c6c', '#909399'][i % 5] }
        })),
      }],
    }
  } catch (err) {
    console.error('Failed to load report data:', err)
  }
})
</script>

<template>
  <div>
    <PageHeader :title="$t('report.title')" :subtitle="$t('report.subtitle')" />

    <!-- Tabs -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden mb-6">
      <div class="flex border-b border-gray-100">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          :class="[
            'flex items-center gap-2 px-6 py-3 text-sm font-medium border-b-2 transition-colors',
            activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-text-secondary hover:text-text-primary'
          ]"
        >
          <span class="material-symbols-outlined text-[18px]">{{ tab.icon }}</span>
          {{ tab.label }}
        </button>
        <div class="ml-auto flex items-center gap-3 px-4">
          <!-- Time dimension buttons (only shown for inout tab) -->
          <div v-if="activeTab === 'inout'" class="flex items-center gap-1 border border-gray-200 rounded-lg overflow-hidden">
            <button v-for="p in periods" :key="p.key" @click="setPeriod(p.key)"
              :class="['px-3 py-1.5 text-xs font-medium transition-colors',
                activePeriod === p.key ? 'bg-primary text-white' : 'text-text-secondary hover:bg-gray-50']">
              {{ p.label }}
            </button>
          </div>
          <!-- Export button -->
          <button @click="exportExcel" class="flex items-center gap-2 border border-gray-200 text-text-primary hover:bg-gray-50 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">download</span>
            {{ $t('report.exportExcel') }}
          </button>
        </div>
      </div>

      <div class="p-6">
        <!-- In/Out Report -->
        <div v-if="activeTab === 'inout'">
          <div class="mb-6">
            <h3 class="font-bold text-text-primary mb-1">{{ $t('report.inoutTrend') }}</h3>
            <p class="text-xs text-text-secondary">{{ $t('report.inoutStats') }}</p>
          </div>
          <VChart :option="inOutBarOption" style="height: 350px;" autoresize />
          <!-- Summary table -->
          <div class="mt-6 overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
                <tr>
                  <th class="px-4 py-3 font-medium">{{ $t('report.month') }}</th>
                  <th class="px-4 py-3 font-medium text-right">{{ $t('inout.inbound') }}</th>
                  <th class="px-4 py-3 font-medium text-right">{{ $t('inout.outbound') }}</th>
                  <th class="px-4 py-3 font-medium text-right">{{ $t('report.netInbound') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="d in monthlyInOutData" :key="d.month" class="hover:bg-gray-50">
                  <td class="px-4 py-3 text-text-primary font-medium">{{ d.month }}</td>
                  <td class="px-4 py-3 text-right text-primary font-medium">{{ d.inbound.toLocaleString() }}</td>
                  <td class="px-4 py-3 text-right text-warning font-medium">{{ d.outbound.toLocaleString() }}</td>
                  <td class="px-4 py-3 text-right" :class="d.inbound - d.outbound >= 0 ? 'text-success' : 'text-danger'">
                    {{ d.inbound - d.outbound >= 0 ? '+' : '' }}{{ (d.inbound - d.outbound).toLocaleString() }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Price Analysis -->
        <div v-if="activeTab === 'price'">
          <div class="mb-6">
            <h3 class="font-bold text-text-primary mb-1">{{ $t('report.purchasePriceTrend') }}</h3>
            <p class="text-xs text-text-secondary">{{ $t('report.priceChange') }}</p>
          </div>
          <VChart :option="priceLineOption" style="height: 350px;" autoresize />
        </div>

        <!-- Stock Report -->
        <div v-if="activeTab === 'stock'">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Pie Chart -->
            <div>
              <div class="mb-4">
                <h3 class="font-bold text-text-primary mb-1">{{ $t('report.categoryStock') }}</h3>
                <p class="text-xs text-text-secondary">{{ $t('report.categoryStockDesc') }}</p>
              </div>
              <VChart :option="categoryPieOption" style="height: 300px;" autoresize />
            </div>
            <!-- Warehouse Summary -->
            <div>
              <div class="mb-4">
                <h3 class="font-bold text-text-primary mb-1">{{ $t('report.warehouseSummary') }}</h3>
                <p class="text-xs text-text-secondary">{{ $t('report.warehouseSummaryDesc') }}</p>
              </div>
              <div class="space-y-3">
                <div v-for="wh in warehouseStockSummary" :key="wh.warehouse" class="border border-gray-100 rounded-lg p-4">
                  <div class="flex justify-between items-center mb-2">
                    <span class="font-medium text-text-primary text-sm">{{ wh.warehouse }}</span>
                    <span class="text-xs text-text-secondary">{{ $t('report.valueInTenThousand', { value: (wh.total_value / 10000).toFixed(1) }) }}</span>
                  </div>
                  <div class="flex gap-2">
                    <div v-for="(val, cat) in wh.categories" :key="cat" class="flex-1 text-center">
                      <p class="text-xs text-text-secondary">{{ cat }}</p>
                      <p class="text-sm font-bold text-text-primary">{{ val.toLocaleString() }}</p>
                    </div>
                  </div>
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
  /* 标签栏手机适配 */
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.overflow-hidden.mb-6 .flex {
    flex-wrap: wrap;
    overflow-x: auto;
  }
  
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.overflow-hidden.mb-6 .flex button:first-child,
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.overflow-hidden.mb-6 .flex button:nth-child(2),
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.overflow-hidden.mb-6 .flex button:nth-child(3) {
    padding: 10px 12px;
    font-size: 12px;
  }

  /* 头部按钮区域 */
  .ml-auto.flex.items-center.gap-3.px-4 {
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px !important;
  }

  /* 导出按钮 */
  .flex.items-center.gap-2.border.border-gray-200.text-text-primary.hover\:bg-gray-50.px-4.py-2.rounded-lg.text-sm.font-medium.transition-colors {
    padding: 6px 10px !important;
    font-size: 12px;
  }


  /* 内容区域内边距 */
  .p-6 {
    padding: 12px !important;
  }

  /* 标题文字 */
  .mb-6 h3.font-bold.text-text-primary.mb-1 {
    font-size: 14px;
  }
  
  .mb-6 p.text-xs.text-text-secondary {
    font-size: 11px;
  }

  /* 表格手机适配 */
  .mt-6.overflow-x-auto table.w-full.text-left.text-sm {
    font-size: 11px;
  }
  
  .mt-6.overflow-x-auto table.w-full.text-left.text-sm th.px-4.py-3,
  .mt-6.overflow-x-auto table.w-full.text-left.text-sm td.px-4.py-3 {
    padding: 8px 6px;
  }

  /* 图表高度 */
  v-chart {
    height: 280px !important;
  }

  /* Stock 标签页网格 */
  .grid.grid-cols-1.lg\:grid-cols-2.gap-6 {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  /* Warehouse 卡片 */
  .border.border-gray-100.rounded-lg.p-4 {
    padding: 10px !important;
  }

  /* 时间维度按钮 */
  .flex.items-center.gap-1.border.border-gray-200.rounded-lg.overflow-hidden {
    flex-wrap: wrap;
  }
  
  .flex.items-center.gap-1.border.border-gray-200.rounded-lg.overflow-hidden button {
    padding: 6px 10px !important;
    font-size: 11px;
  }
}
</style>
