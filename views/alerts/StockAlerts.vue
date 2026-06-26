<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import StatCard from '../../components/StatCard.vue'
import StatusTag from '../../components/StatusTag.vue'
import api from '../../services/api.js'

const { t } = useI18n()
const router = useRouter()

const alerts = ref([])
const filterLevel = ref('unhandled')  // 默认只显示未处理（波哥 2026-07-19 反馈：补完货列表就不出现）
const showReplenishDialog = ref(false)
const selectedAlert = ref(null)
const batchQty = ref(null)  // 批量补货数量（null=用各行 suggest_qty）
const selectedIds = ref(new Set())  // 勾选的 alert ids
const batchBusy = ref(false)

onMounted(async () => {
  const res = await api.get('/stock-alerts')
  alerts.value = res.data
})

// 修复：卡片数字必须和列表一致
// 列表默认显示未处理，所以"全部"/"严重"/"一般"卡片都按"未处理"算
// "已处理"卡片独立
const stats = computed(() => {
  const unhandled = alerts.value.filter(a => !a.handled)
  return {
    unhandled: unhandled.length,                          // 未处理总数（默认 tab 显示）
    critical: unhandled.filter(a => a.level === 'critical').length,  // 未处理中严重的
    warning: unhandled.filter(a => a.level === 'warning').length,    // 未处理中一般的
    handled: alerts.value.filter(a => a.handled).length,  // 已处理
  }
})

const filteredAlerts = computed(() => {
  // 默认全部 tab 也排除已处理（波哥 2026-07-19 反馈：补完货列表就不出现）
  // 想看已处理切到"已处理" tab
  if (filterLevel.value === 'handled') return alerts.value.filter(a => a.handled)
  if (filterLevel.value === 'critical') return alerts.value.filter(a => a.level === 'critical' && !a.handled)
  if (filterLevel.value === 'warning') return alerts.value.filter(a => a.level === 'warning' && !a.handled)
  if (filterLevel.value === 'unhandled') return alerts.value.filter(a => !a.handled)
  // 'all' 也只显示未处理（避免视觉噪音）
  return alerts.value.filter(a => !a.handled)
})

const allSelected = computed(() => {
  const unhandled = filteredAlerts.value.filter(a => !a.handled)
  return unhandled.length > 0 && selectedIds.value.size === unhandled.length
})

const filterTabs = computed(() => [
  { key: 'unhandled', label: t('alert.unhandled') },
  { key: 'critical', label: t('alert.critical') },
  { key: 'warning', label: t('alert.general') },
  { key: 'handled', label: '已处理' },
])

async function markHandled(id) {
  if (!confirm('确认此预警已处理？')) return
  await api.put('/stock-alerts/' + id)
  const res = await api.get('/stock-alerts')
  alerts.value = res.data?.data || []
}

async function deleteAlert(alert) {
  if (!confirm(`确认删除该预警记录？\n商品: ${alert.product_name || alert.product_id}\n仓库: ${alert.warehouse_name || alert.warehouse_id}\n此操作不可恢复`)) return
  try {
    await api.delete('/stock-alerts/' + alert.id)
    const res = await api.get('/stock-alerts')
    alerts.value = res.data?.data || []
  } catch (e) {
    alert('删除失败：' + (e?.message || e?.data?.message || JSON.stringify(e)))
  }
}

function openReplenish(alert) {
  selectedAlert.value = alert
  showReplenishDialog.value = true
}

function closeReplenish() {
  showReplenishDialog.value = false
  selectedAlert.value = null
}

// 方式 A：从其他门店调货到本仓库
async function replenByTransfer() {
  const a = selectedAlert.value
  if (!a) return
  // 预填数据：通过 router query 让 TransferCreate 接收
  const payload = encodeURIComponent(JSON.stringify({
    to_warehouse_id: a.warehouse_id,
    items: [{ product_id: a.product_id, quantity: a.suggest_qty }],
    note: `补仓预警 #${a.id}（${a.product_name} 建议补 ${a.suggest_qty}）`
  }))
  closeReplenish()
  router.push(`/transfer/create?prefill=${payload}`)
}

// 方式 B：直接入库（自己仓库直接进货）
async function replenByInbound() {
  const a = selectedAlert.value
  if (!a) return
  const payload = encodeURIComponent(JSON.stringify({
    warehouse_id: a.warehouse_id,
    items: [{ product_id: a.product_id, quantity: a.suggest_qty }],
    note: `补仓预警 #${a.id}（${a.product_name} 建议补 ${a.suggest_qty}）`
  }))
  closeReplenish()
  // 注意：路由 path 是 'in-out'（中横线），且没有 /create 子路由
  // 预填由 InOutList.vue onMounted 里的 route.query.prefill 消费
  router.push(`/in-out?prefill=${payload}&type=inbound`)
}

// 方式 C：仅标记已处理
async function replenishOnly() {
  closeReplenish()
  await markHandled(selectedAlert.value.id)
}

async function reload() {
  const res = await api.get('/stock-alerts')
  alerts.value = res.data
  selectedIds.value = new Set()
}

function toggleSelect(id) {
  if (selectedIds.value.has(id)) selectedIds.value.delete(id)
  else selectedIds.value.add(id)
  // 触发响应式
  selectedIds.value = new Set(selectedIds.value)
}

function toggleSelectAll() {
  const visible = filteredAlerts.value.filter(a => !a.handled)
  if (selectedIds.value.size === visible.length && visible.length > 0) {
    selectedIds.value = new Set()
  } else {
    selectedIds.value = new Set(visible.map(a => a.id))
  }
}

async function batchReplenish() {
  if (batchBusy.value) return
  const ids = selectedIds.value.size > 0 ? Array.from(selectedIds.value) : null
  const unhandledCount = alerts.value.filter(a => !a.handled).length
  const target = ids ? `${ids.length} 个选中` : `全部 ${unhandledCount} 个未处理`
  if (unhandledCount === 0 && !ids) {
    alert('当前没有未处理的预警')
    return
  }
  if (!confirm(`确认一键补货 ${target}${batchQty.value ? `，每条数量 = ${batchQty.value}` : '（用各行建议数量）'}？`)) return
  batchBusy.value = true
  try {
    const body = ids ? { alert_ids: ids } : {}
    if (batchQty.value !== null && batchQty.value !== '' && Number(batchQty.value) >= 0) {
      body.override_qty = Number(batchQty.value)
    }
    const r = await api.post('/stock-alerts/batch-replenish', body)
    const d = r.data || {}
    const records = d.records || []
    alert(`✅ 一键补货完成\n生成 ${d.created} 张入库单\n处理 ${d.alert_count} 个预警\n\n${records.map(x => '• ' + x.record_no + '（' + x.item_count + ' SKU / 共 ' + x.total_qty + ' 件）').join('\n')}`)
    await reload()
  } catch (e) {
    alert('❌ 补货失败：' + (e?.message || e?.data?.message || JSON.stringify(e)))
  } finally {
    batchBusy.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader :title="$t('alert.title')" :subtitle="$t('alert.subtitle')" />

    <!-- Stats (可点击切换 tab) -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <StatCard
        :title="$t('alert.totalAlerts')"
        :value="String(stats.unhandled)"
        icon="notifications_active"
        colorClass="blue"
        :active="filterLevel === 'unhandled'"
        @click="filterLevel = 'unhandled'"
      />
      <StatCard
        :title="$t('alert.criticalAlerts')"
        :value="String(stats.critical)"
        icon="error"
        colorClass="red"
        :alert="true"
        :active="filterLevel === 'critical'"
        @click="filterLevel = 'critical'"
      >
        <template #footer><span class="text-danger font-medium">{{ $t('dashboard.needsImmediate') }}</span></template>
      </StatCard>
      <StatCard
        :title="$t('alert.generalAlerts')"
        :value="String(stats.warning)"
        icon="warning"
        colorClass="orange"
        :active="filterLevel === 'warning'"
        @click="filterLevel = 'warning'"
      />
      <StatCard
        :title="$t('alert.handled')"
        :value="String(stats.handled)"
        icon="check_circle"
        colorClass="green"
        :active="filterLevel === 'handled'"
        @click="filterLevel = 'handled'"
      />
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
        <div class="ml-auto flex items-center gap-2">
          <label class="text-xs text-text-secondary">{{ $t('alert.batchQty') }}</label>
          <input
            v-model.number="batchQty"
            type="number" min="0" placeholder="默认"
            class="w-20 px-2 py-1.5 border border-gray-200 rounded text-sm text-center focus:outline-none focus:border-primary"
          />
          <button
            @click="batchReplenish"
            :disabled="batchBusy"
            class="flex items-center gap-2 bg-danger hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">
            <span class="material-symbols-outlined text-[18px]">shopping_cart</span>
            {{ $t('alert.quickReplenish') }}
            <span v-if="selectedIds.size > 0" class="bg-white/20 px-1.5 rounded text-xs">({{ selectedIds.size }})</span>
          </button>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium w-10">
                <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="cursor-pointer" />
              </th>
              <th class="px-4 py-3 font-medium">SKU</th>
              <th class="px-4 py-3 font-medium">{{ $t('common.productName') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('inout.warehouse') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('warehouse.currentStock') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('alert.alertStockLine') }}</th>
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
              <td class="px-4 py-3 text-center text-text-secondary">{{ alert.alert_stock }}</td>
              <td class="px-4 py-3 text-center text-primary font-medium">{{ alert.suggest_qty }}</td>
              <td class="px-4 py-3 text-center">
                <StatusTag :type="alert.level === 'critical' ? 'danger' : 'warning'" :text="alert.level === 'critical' ? $t('alert.critical') : $t('alert.general')" />
              </td>
              <td class="px-4 py-3 text-text-secondary text-xs">{{ alert.created_at }}</td>
              <td class="px-4 py-3 text-center">
                <StatusTag :type="alert.handled ? 'success' : 'info'" :text="alert.handled ? $t('alert.handled') : $t('alert.unhandled')" />
              </td>
              <td class="px-4 py-3 text-right space-x-2">
                <button v-if="!alert.handled" @click="openReplenish(alert)" class="text-primary hover:text-primary-hover text-xs font-medium">{{ $t('alert.replenish') }}</button>
                <button v-if="!alert.handled" @click="markHandled(alert.id)" class="text-text-secondary hover:text-text-primary text-xs">标记已处理</button>
                <button v-if="alert.handled" @click="deleteAlert(alert)" class="inline-flex items-center gap-1 text-red-600 hover:text-red-800 text-xs font-medium">
                  <span class="material-symbols-outlined text-[14px]">delete</span>
                  删除
                </button>
                <span v-if="alert.handled === undefined" class="text-text-secondary text-xs">-</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-gray-100 text-sm text-text-secondary">
        {{ filteredAlerts.length }} {{ $t('common.records') }}
      </div>
    </div>

    <!-- 补仓方式选择弹窗 -->
    <div v-if="showReplenishDialog && selectedAlert" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeReplenish">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-text-primary mb-2">📦 补仓方案</h3>
        <p class="text-sm text-text-secondary mb-4">
          <strong>{{ selectedAlert.product_name }}</strong>（{{ selectedAlert.warehouse_name }}）<br>
          当前库存 <span class="text-danger font-bold">{{ selectedAlert.current_stock }}</span>，
          安全线 <span class="text-warning font-bold">{{ selectedAlert.alert_stock }}</span>，
          建议补 <span class="text-primary font-bold">{{ selectedAlert.suggest_qty }}</span>
        </p>
        <div class="space-y-3">
          <button @click="replenByTransfer" class="w-full flex items-center gap-3 p-4 border-2 border-primary rounded-lg hover:bg-primary/5 transition-colors text-left">
            <span class="material-symbols-outlined text-primary text-3xl">local_shipping</span>
            <div class="flex-1">
              <div class="font-medium text-text-primary">从其他门店调货</div>
              <div class="text-xs text-text-secondary mt-1">跳转调货创建页面，预填商品和数量</div>
            </div>
          </button>
          <button @click="replenByInbound" class="w-full flex items-center gap-3 p-4 border-2 border-success rounded-lg hover:bg-success/5 transition-colors text-left">
            <span class="material-symbols-outlined text-success text-3xl">inventory_2</span>
            <div class="flex-1">
              <div class="font-medium text-text-primary">直接入库</div>
              <div class="text-xs text-text-secondary mt-1">跳转入库页面，从供货商采购</div>
            </div>
          </button>
          <button @click="replenishOnly" class="w-full flex items-center gap-3 p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors text-left">
            <span class="material-symbols-outlined text-text-secondary text-3xl">check_circle</span>
            <div class="flex-1">
              <div class="font-medium text-text-secondary">仅标记已处理</div>
              <div class="text-xs text-text-secondary mt-1">不进行实际补货操作</div>
            </div>
          </button>
        </div>
        <button @click="closeReplenish" class="mt-4 w-full px-4 py-2 text-sm text-text-secondary hover:text-text-primary">取消</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
@media (max-width: 768px) {
  /* Stats grid */
  .grid {
    gap: 0.75rem;
    margin-bottom: 1rem;
  }

  /* Table container - ensure horizontal scroll */
  .overflow-x-auto {
    margin: 0;
  }

  /* Table cells - reduce padding and font size */
  table th,
  table td {
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
  }

  /* Filter buttons wrapper */
  .flex.items-center.gap-3.flex-wrap {
    padding: 0.75rem 1rem;
    gap: 0.5rem;
  }

  /* Individual filter button */
  .px-3.py-1\.5.rounded-lg.text-sm.font-medium {
    padding: 0.375rem 0.625rem;
    font-size: 0.75rem;
  }

  /* Quick replenish button */
  .flex.items-center.gap-2.bg-danger {
    padding: 0.375rem 0.625rem;
    font-size: 0.75rem;
    gap: 0.25rem;
  }

  /* SKU column - smaller font */
  .font-mono.text-xs {
    font-size: 0.65rem;
  }

  /* Timestamp column (8th) - hide on mobile */
  table th:nth-child(8),
  table td:nth-child(8) {
    display: none;
  }

  /* Records count bar */
  .px-4.py-3.border-t.border-gray-100 {
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
  }
}
</style>
