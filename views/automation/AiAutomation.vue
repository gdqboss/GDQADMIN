<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import api from '../../services/api.js'

const { t } = useI18n()
const activeSection = ref('tasks')

const aiTasks = ref([])
const aiCollectedData = ref([])
const aiAutomationRules = ref([])
const aiLogs = ref([])

onMounted(async () => {
  try {
    const [tasksRes, dataRes, rulesRes, logsRes] = await Promise.all([
      api.get('/ai/tasks'),
      api.get('/ai/collected-data'),
      api.get('/ai/rules'),
      api.get('/ai/logs'),
    ])
    if (tasksRes.code === 0) aiTasks.value = tasksRes.data
    if (dataRes.code === 0) aiCollectedData.value = dataRes.data
    if (rulesRes.code === 0) aiAutomationRules.value = rulesRes.data
    if (logsRes.code === 0) aiLogs.value = logsRes.data
  } catch (e) { /* ignore */ }
})

const statusColors = {
  running: 'text-success',
  idle: 'text-info',
  error: 'text-danger',
}
const statusIcons = {
  running: 'play_circle',
  idle: 'pause_circle',
  error: 'error',
}
const statusLabels = computed(() => ({
  running: t('dashboard.running'),
  idle: t('dashboard.idle'),
  error: t('dashboard.error'),
}))

const sectionTabs = computed(() => [
  { key: 'tasks', label: t('ai.collectData'), icon: 'database' },
  { key: 'rules', label: t('ai.automationRules'), icon: 'tune' },
  { key: 'logs', label: t('ai.runLogs'), icon: 'terminal' },
])

const logLevelColors = {
  info: 'text-primary',
  success: 'text-success',
  warning: 'text-warning',
  error: 'text-danger',
}
</script>

<template>
  <div>
    <PageHeader :title="$t('ai.title')" :subtitle="$t('ai.subtitle')" />

    <!-- Task Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div v-for="task in aiTasks" :key="task.id" class="bg-white rounded-lg border border-gray-100 shadow-card p-4 hover:shadow-card-hover transition-shadow">
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center gap-2">
            <span :class="['material-symbols-outlined text-[20px]', statusColors[task.status]]">{{ statusIcons[task.status] }}</span>
            <span class="text-sm font-bold text-text-primary">{{ task.name }}</span>
          </div>
          <StatusTag :type="task.status === 'running' ? 'success' : task.status === 'error' ? 'danger' : 'info'" :text="statusLabels[task.status]" />
        </div>
        <div class="space-y-2 text-xs text-text-secondary">
          <div class="flex justify-between"><span>{{ $t('ai.platform') }}</span><span class="text-text-primary">{{ task.platform }}</span></div>
          <div class="flex justify-between"><span>{{ $t('ai.schedule') }}</span><span class="text-text-primary">{{ task.schedule }}</span></div>
          <div class="flex justify-between"><span>{{ $t('ai.lastRun') }}</span><span>{{ task.last_run }}</span></div>
          <div class="flex justify-between"><span>{{ $t('ai.nextRun') }}</span><span>{{ task.next_run }}</span></div>
          <div class="flex justify-between"><span>{{ $t('ai.successRate') }}</span><span :class="task.success_rate >= 90 ? 'text-success' : task.success_rate >= 80 ? 'text-warning' : 'text-danger'" class="font-medium">{{ task.success_rate }}%</span></div>
        </div>
        <div class="mt-3 pt-3 border-t border-gray-100 flex gap-2">
          <button v-if="task.status !== 'running'" class="flex-1 flex items-center justify-center gap-1 bg-primary/10 text-primary hover:bg-primary hover:text-white py-1.5 rounded text-xs font-medium transition-colors">
            <span class="material-symbols-outlined text-[14px]">play_arrow</span> {{ $t('ai.run') }}
          </button>
          <button v-else class="flex-1 flex items-center justify-center gap-1 bg-gray-100 text-text-secondary hover:bg-gray-200 py-1.5 rounded text-xs font-medium transition-colors">
            <span class="material-symbols-outlined text-[14px]">stop</span> {{ $t('ai.stop') }}
          </button>
          <button class="flex items-center justify-center bg-gray-100 text-text-secondary hover:bg-gray-200 px-2 py-1.5 rounded text-xs transition-colors">
            <span class="material-symbols-outlined text-[14px]">settings</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Section Tabs -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <div class="flex border-b border-gray-100">
        <button
          v-for="tab in sectionTabs"
          :key="tab.key"
          @click="activeSection = tab.key"
          :class="[
            'flex items-center gap-2 px-6 py-3 text-sm font-medium border-b-2 transition-colors',
            activeSection === tab.key ? 'border-primary text-primary' : 'border-transparent text-text-secondary hover:text-text-primary'
          ]"
        >
          <span class="material-symbols-outlined text-[18px]">{{ tab.icon }}</span>
          {{ tab.label }}
        </button>
      </div>

      <!-- Collected Data -->
      <div v-if="activeSection === 'tasks'" class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">{{ $t('inout.source') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('common.productName') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('common.price') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('ai.priceChange') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('product.supplier') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('common.category') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('ai.collectionTime') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="item in aiCollectedData" :key="item.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3">
                <StatusTag :type="item.source === '1688' ? 'warning' : item.source === '淘宝' ? 'danger' : 'primary'" :text="item.source" />
              </td>
              <td class="px-4 py-3 font-medium text-text-primary max-w-[200px] truncate">{{ item.product_name }}</td>
              <td class="px-4 py-3 text-right text-text-primary font-medium">¥{{ item.price.toFixed(2) }}</td>
              <td class="px-4 py-3 text-right">
                <span v-if="item.price_change !== 0" :class="item.price_change < 0 ? 'text-success' : 'text-danger'" class="font-medium text-xs">
                  {{ item.price_change > 0 ? '+' : '' }}{{ item.price_change.toFixed(1) }}%
                </span>
                <span v-else class="text-text-secondary text-xs">-</span>
              </td>
              <td class="px-4 py-3 text-text-secondary text-xs">{{ item.supplier }}</td>
              <td class="px-4 py-3 text-text-secondary text-xs">{{ item.category }}</td>
              <td class="px-4 py-3 text-text-secondary text-xs">{{ item.collected_at }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Rules -->
      <div v-if="activeSection === 'rules'" class="p-4 space-y-3">
        <div v-for="rule in aiAutomationRules" :key="rule.id" class="flex items-center gap-4 p-4 border border-gray-100 rounded-lg hover:bg-gray-50 transition-colors">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
              <span class="text-sm font-bold text-text-primary">{{ rule.name }}</span>
              <StatusTag :type="rule.enabled ? 'success' : 'info'" :text="rule.enabled ? $t('ai.enabled') : $t('ai.disabled')" />
            </div>
            <div class="text-xs text-text-secondary space-y-0.5">
              <p><span class="font-medium">{{ $t('ai.triggerCondition') }}:</span> {{ rule.trigger }}</p>
              <p><span class="font-medium">{{ $t('ai.executeAction') }}:</span> {{ rule.action }}</p>
              <p><span class="font-medium">{{ $t('ai.lastTriggered') }}:</span> {{ rule.last_triggered }}</p>
            </div>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" :checked="rule.enabled" class="sr-only peer" />
            <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:bg-primary transition-colors after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
          </label>
        </div>
      </div>

      <!-- Logs -->
      <div v-if="activeSection === 'logs'" class="p-4">
        <div class="bg-gray-900 rounded-lg p-4 font-mono text-xs max-h-[400px] overflow-y-auto custom-scrollbar space-y-1">
          <div v-for="(log, i) in aiLogs" :key="i" class="flex gap-3">
            <span class="text-gray-500 shrink-0">{{ log.time }}</span>
            <span :class="logLevelColors[log.level]" class="shrink-0 uppercase w-16">[{{ log.level }}]</span>
            <span class="text-gray-300">{{ log.message }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
