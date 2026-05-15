<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import StatCard from '../../components/StatCard.vue'
import StatusTag from '../../components/StatusTag.vue'
import api from '../../services/api.js'

const { t } = useI18n()

const alerts = ref([])
const filterLevel = ref('all')

onMounted(async () => {
  const res = await api.get('/stock-alerts')
  alerts.value = res.data
})

const stats = computed(() => ({
  total: alerts.value.length,
  critical: alerts.value.filter(a => a.level === 'critical').length,
  warning: alerts.value.filter(a => a.level === 'warning').length,
  handled: alerts.value.filter(a => a.handled).length,
}))

const filteredAlerts = computed(() => {
  if (filterLevel.value === 'all') return alerts.value
  if (filterLevel.value === 'critical') return alerts.value.filter(a => a.level === 'critical')
  if (filterLevel.value === 'warning') return alerts.value.filter(a => a.level === 'warning')
  if (filterLevel.value === 'unhandled') return alerts.value.filter(a => !a.handled)
  return alerts.value
})

const filterTabs = computed(() => [
  { key: 'all', label: t('alert.all') },
  { key: 'critical', label: t('alert.critical') },
  { key: 'warning', label: t('alert.general') },
  { key: 'unhandled', label: t('alert.unhandled') },
])

async function markHandled(id) {
  await api.put('/stock-alerts/' + id)
  const res = await api.get('/stock-alerts')
  alerts.value = res.data
}
</script>

<template>
  <div>
    <PageHeader :title="$t('alert.title')" :subtitle="$t('alert.subtitle')" />

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <StatCard :title="$t('alert.totalAlerts')" :value="String(stats.total)" icon="notifications_active" colorClass="blue" />
      <StatCard :title="$t('alert.criticalAlerts')" :value="String(stats.critical)" icon="error" colorClass="red" :alert="true">
        <template #footer><span class="text-danger font-medium">{{ $t('dashboard.needsImmediate') }}</span></template>
      </StatCard>
      <StatCard :title="$t('alert.generalAlerts')" :value="String(stats.warning)" icon="warning" colorClass="orange" />
      <StatCard :title="$t('alert.handled')" :value="String(stats.handled)" icon="check_circle" colorClass="green" />
    </div>

    <!-- Filter + Table -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-3 flex-wrap">
        <button
          v-for="f in filterTabs"
          :key="f.key"
          @click="filterLevel = f.key"
          :class="[
            'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
            filterLevel === f.key ? 'bg-primary text-white' : 'bg-gray-100 text-text-secondary hover:bg-gray-200'
          ]"
        >{{ f.label }}</button>
        <div class="ml-auto">
          <button class="flex items-center gap-2 bg-danger hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">shopping_cart</span>
            {{ $t('alert.quickReplenish') }}
          </button>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">SKU</th>
              <th class="px-4 py-3 font-medium">{{ $t('common.productName') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('inout.warehouse') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('warehouse.currentStock') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('alert.safeStockLine') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('alert.suggestReplenish') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('alert.level') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('alert.alertTime') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('common.status') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('common.action') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="alert in filteredAlerts" :key="alert.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 font-mono text-xs text-text-secondary">{{ alert.sku }}</td>
              <td class="px-4 py-3 font-medium text-text-primary">{{ alert.product_name }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ alert.warehouse_name }}</td>
              <td class="px-4 py-3 text-center font-bold text-danger">{{ alert.current_stock }}</td>
              <td class="px-4 py-3 text-center text-text-secondary">{{ alert.safe_stock }}</td>
              <td class="px-4 py-3 text-center text-primary font-medium">{{ alert.suggest_qty }}</td>
              <td class="px-4 py-3 text-center">
                <StatusTag :type="alert.level === 'critical' ? 'danger' : 'warning'" :text="alert.level === 'critical' ? $t('alert.critical') : $t('alert.general')" />
              </td>
              <td class="px-4 py-3 text-text-secondary text-xs">{{ alert.created_at }}</td>
              <td class="px-4 py-3 text-center">
                <StatusTag :type="alert.handled ? 'success' : 'info'" :text="alert.handled ? $t('alert.handled') : $t('alert.unhandled')" />
              </td>
              <td class="px-4 py-3 text-right">
                <button v-if="!alert.handled" @click="markHandled(alert.id)" class="text-primary hover:text-primary-hover text-xs font-medium">{{ $t('alert.replenish') }}</button>
                <span v-else class="text-text-secondary text-xs">-</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-gray-100 text-sm text-text-secondary">
        {{ filteredAlerts.length }} {{ $t('common.records') }}
      </div>
    </div>
  </div>
</template>
