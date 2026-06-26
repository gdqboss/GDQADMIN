<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { useUserStore } from '../../stores/user.js'

const { t } = useI18n()
const router = useRouter()
const userStore = useUserStore()

const warehouses = ref([])
const showModal = ref(false)
const isEdit = ref(false)
const form = ref({
  id: null,
  name: '',
  address: '',
  type: t('warehouse.domestic'),
  manager: '',
  status: 'active'
})

// ─── Operation Log Modal ────────────────────────────────────────────────────
const showOpLogModal = ref(false)
const opLogWarehouse = ref(null)
const opLogRecords = ref([])
const opLogLoading = ref(false)
const opLogFilters = ref({ type: '', operator: '', start_date: '', end_date: '' })

const typeColors = computed(() => ({
  [t('warehouse.domestic')]: 'bg-blue-100 text-primary',
  [t('warehouse.overseas')]: 'bg-green-100 text-success',
  [t('warehouse.bonded')]: 'bg-orange-100 text-warning',
}))

const canDelete = computed(() => userStore.canAccess('warehouse:delete'))

async function loadWarehouses() {
  const res = await api.get('/warehouses')
  warehouses.value = res.data
}

onMounted(loadWarehouses)

function openAddModal() {
  isEdit.value = false
  form.value = {
    id: null,
    name: '',
    address: '',
    type: t('warehouse.domestic'),
    manager: '',
    status: 'active'
  }
  showModal.value = true
}

function openEditModal(wh, event) {
  event.preventDefault()
  event.stopPropagation()
  isEdit.value = true
  form.value = {
    id: wh.id,
    name: wh.name,
    address: wh.address || '',
    type: wh.type || t('warehouse.domestic'),
    manager: wh.manager || '',
    status: wh.status || 'active'
  }
  showModal.value = true
}

async function handleSubmit() {
  try {
    if (!form.value.name) {
      alert(t('warehouse.nameRequired'))
      return
    }

    if (isEdit.value) {
      await api.put(`/warehouses/${form.value.id}`, form.value)
      alert(t('warehouse.updateSuccess'))
    } else {
      await api.post('/warehouses', form.value)
      alert(t('warehouse.createSuccess'))
    }

    showModal.value = false
    await loadWarehouses()
  } catch (err) {
    alert(err.response?.data?.message || err.message || t('warehouse.operationFailed'))
  }
}

async function handleDelete(wh, event) {
  event.preventDefault()
  event.stopPropagation()

  if (!confirm(t('warehouse.confirmDelete', { name: wh.name }))) {
    return
  }

  try {
    await api.delete(`/warehouses/${wh.id}`)
    alert(t('warehouse.deleteSuccess'))
    await loadWarehouses()
  } catch (err) {
    alert(err.response?.data?.message || err.message || t('warehouse.deleteFailed'))
  }
}

function viewDetail(wh) {
  router.push(`/warehouses/${wh.id}`)
}

// ─── Operation Log Functions ────────────────────────────────────────────────
async function openOpLog(wh, event) {
  event.preventDefault()
  event.stopPropagation()
  opLogWarehouse.value = wh
  opLogRecords.value = []
  opLogFilters.value = { type: '', operator: '', start_date: '', end_date: '' }
  showOpLogModal.value = true
  await loadOpLog()
}

async function loadOpLog() {
  if (!opLogWarehouse.value) return
  opLogLoading.value = true
  try {
    const params = new URLSearchParams()
    params.append('limit', '200')
    if (opLogFilters.value.type) params.append('type', opLogFilters.value.type)
    if (opLogFilters.value.operator) params.append('operator', opLogFilters.value.operator)
    if (opLogFilters.value.start_date) params.append('start_date', opLogFilters.value.start_date)
    if (opLogFilters.value.end_date) params.append('end_date', opLogFilters.value.end_date)
    const res = await api.get(`/warehouses/${opLogWarehouse.value.id}/history?${params}`)
    opLogRecords.value = res.data || []
  } catch (err) {
    console.error('Failed to load operation log', err)
    opLogRecords.value = []
  } finally {
    opLogLoading.value = false
  }
}

function resetOpLogFilters() {
  opLogFilters.value = { type: '', operator: '', start_date: '', end_date: '' }
  loadOpLog()
}

function getOpTypeLabel(type) {
  const map = {
    inbound: '入库',
    outbound: '出库',
    transfer: '调库',
    adjust: '调整',
    delete: '删除',
    return: '退货'
  }
  return map[type] || type
}

function getOpTypeColor(type) {
  const map = {
    inbound: 'text-green-600 bg-green-50',
    outbound: 'text-red-600 bg-red-50',
    transfer: 'text-blue-600 bg-blue-50',
    adjust: 'text-orange-600 bg-orange-50',
    delete: 'text-red-700 bg-red-50',
    return: 'text-purple-600 bg-purple-50'
  }
  return map[type] || 'text-gray-600 bg-gray-50'
}

function getDeltaColor(delta) {
  if (delta > 0) return 'text-green-600'
  if (delta < 0) return 'text-red-600'
  return 'text-gray-500'
}

function formatDate(str) {
  if (!str) return ''
  return String(str).slice(0, 19).replace('T', ' ')
}
</script>

<template>
  <div>
    <PageHeader :title="$t('warehouse.title')" :subtitle="$t('warehouse.subtitle')">
      <template #actions>
        <button
          @click="openAddModal"
          class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
        >
          <span class="material-symbols-outlined text-[18px]">add</span>
          {{ $t('warehouse.add') }}
        </button>
      </template>
    </PageHeader>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div
        v-for="wh in warehouses"
        :key="wh.id"
        @click="viewDetail(wh)"
        class="bg-white rounded-lg border border-gray-100 shadow-card hover:shadow-card-hover transition-all p-5 group cursor-pointer"
      >
        <div class="flex items-start justify-between mb-4">
          <div class="flex-1">
            <h3 class="text-lg font-bold text-text-primary group-hover:text-primary transition-colors">{{ wh.name }}</h3>
            <p class="text-xs text-text-secondary mt-1">{{ wh.address }}</p>
          </div>
          <div class="flex items-center gap-2">
            <span :class="['text-xs font-medium px-2 py-0.5 rounded', typeColors[wh.type] || 'bg-gray-100 text-info']">{{ wh.type }}</span>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-4 mb-4">
          <div>
            <p class="text-xs text-text-secondary">{{ $t('warehouse.stockCount') }}</p>
            <p class="text-lg font-bold text-text-primary">{{ (wh.totalQty || 0).toLocaleString() }}</p>
          </div>
          <div>
            <p class="text-xs text-text-secondary">{{ $t('warehouse.productTypes') }}</p>
            <p class="text-lg font-bold text-text-primary">{{ wh.productCount || 0 }}</p>
          </div>
          <div>
            <p class="text-xs text-text-secondary">{{ $t('warehouse.manager') }}</p>
            <p class="text-sm font-medium text-text-primary">{{ wh.manager }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2 pt-3 border-t border-gray-100">
          <button
            @click="openEditModal(wh, $event)"
            class="flex items-center gap-1 text-primary hover:text-primary-hover text-xs font-medium transition-colors"
          >
            <span class="material-symbols-outlined text-[16px]">edit</span>
            {{ $t('common.edit') }}
          </button>
          <button
            @click="openOpLog(wh, $event)"
            class="flex items-center gap-1 text-blue-600 hover:text-blue-700 text-xs font-medium transition-colors"
          >
            <span class="material-symbols-outlined text-[16px]">history</span>
            操作记录
          </button>
          <button
            v-if="userStore.canAccess('warehouse:delete')"
            @click="handleDelete(wh, $event)"
            class="flex items-center gap-1 text-danger hover:text-red-700 text-xs font-medium transition-colors ml-auto"
          >
            <span class="material-symbols-outlined text-[16px]">delete</span>
            {{ $t('common.delete') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30" @click="showModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="text-lg font-bold text-text-primary">{{ isEdit ? $t('warehouse.editWarehouse') : $t('warehouse.add') }}</h3>
            <button @click="showModal = false" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <div class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">
                {{ $t('settings.warehouseName') }} <span class="text-danger">*</span>
              </label>
              <input
                v-model="form.name"
                type="text"
                :placeholder="$t('warehouse.namePlaceholder')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('warehouse.address') }}</label>
              <input
                v-model="form.address"
                type="text"
                :placeholder="$t('warehouse.addressPlaceholder')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('warehouse.type') }}</label>
              <select
                v-model="form.type"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              >
                <option :value="$t('warehouse.domestic')">{{ $t('warehouse.domestic') }}</option>
                <option :value="$t('warehouse.overseas')">{{ $t('warehouse.overseas') }}</option>
                <option :value="$t('warehouse.bonded')">{{ $t('warehouse.bonded') }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('warehouse.manager') }}</label>
              <input
                v-model="form.manager"
                type="text"
                :placeholder="$t('warehouse.managerPlaceholder')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              />
            </div>

            <div v-if="isEdit">
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('common.status') }}</label>
              <select
                v-model="form.status"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              >
                <option value="active">{{ $t('common.active') }}</option>
                <option value="inactive">{{ $t('common.inactive') }}</option>
              </select>
            </div>
          </div>

          <div class="px-6 py-4 border-t flex gap-3 justify-end">
            <button
              @click="showModal = false"
              class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-text-primary hover:bg-gray-50 transition-colors"
            >
              {{ $t('common.cancel') }}
            </button>
            <button
              @click="handleSubmit"
              class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium transition-colors"
            >
              {{ isEdit ? $t('common.save') : $t('common.create') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Operation Log Modal -->
    <Teleport to="body">
      <div v-if="showOpLogModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30" @click="showOpLogModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-5xl max-h-[90vh] flex flex-col">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <div>
              <h3 class="text-lg font-bold text-text-primary">
                <span class="material-symbols-outlined align-middle mr-1">history</span>
                操作记录
              </h3>
              <p class="text-sm text-text-secondary mt-0.5">{{ opLogWarehouse?.name }}</p>
            </div>
            <button @click="showOpLogModal = false" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <!-- Filters -->
          <div class="px-6 py-3 border-b bg-gray-50 flex items-center gap-3 flex-wrap">
            <select
              v-model="opLogFilters.type"
              @change="loadOpLog"
              class="px-3 py-1.5 border border-gray-200 rounded text-sm bg-white focus:border-primary focus:outline-none"
            >
              <option value="">所有类型</option>
              <option value="inbound">入库</option>
              <option value="outbound">出库</option>
              <option value="transfer">调库</option>
              <option value="adjust">调整</option>
              <option value="delete">删除</option>
              <option value="return">退货</option>
            </select>
            <input
              v-model="opLogFilters.operator"
              @keyup.enter="loadOpLog"
              type="text"
              placeholder="操作人"
              class="px-3 py-1.5 border border-gray-200 rounded text-sm bg-white focus:border-primary focus:outline-none w-32"
            />
            <input
              v-model="opLogFilters.start_date"
              @change="loadOpLog"
              type="date"
              class="px-3 py-1.5 border border-gray-200 rounded text-sm bg-white focus:border-primary focus:outline-none"
            />
            <span class="text-text-secondary text-sm">至</span>
            <input
              v-model="opLogFilters.end_date"
              @change="loadOpLog"
              type="date"
              class="px-3 py-1.5 border border-gray-200 rounded text-sm bg-white focus:border-primary focus:outline-none"
            />
            <button
              @click="resetOpLogFilters"
              class="px-3 py-1.5 text-xs text-text-secondary hover:text-text-primary border border-gray-200 rounded bg-white"
            >
              重置
            </button>
            <span class="ml-auto text-sm text-text-secondary">
              共 {{ opLogRecords.length }} 条记录
            </span>
          </div>

          <!-- Records -->
          <div class="flex-1 overflow-y-auto p-6">
            <div v-if="opLogLoading" class="text-center py-12 text-text-secondary text-sm">
              <span class="material-symbols-outlined text-4xl block mb-2 text-gray-300 animate-spin">progress_activity</span>
              加载中...
            </div>

            <div v-else-if="opLogRecords.length === 0" class="text-center py-12 text-text-secondary text-sm">
              <span class="material-symbols-outlined text-4xl block mb-2 text-gray-300">inbox</span>
              暂无操作记录
            </div>

            <div v-else class="overflow-x-auto">
              <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
                  <tr>
                    <th class="px-3 py-2 font-medium">时间</th>
                    <th class="px-3 py-2 font-medium">类型</th>
                    <th class="px-3 py-2 font-medium">商品</th>
                    <th class="px-3 py-2 font-medium text-center">变化</th>
                    <th class="px-3 py-2 font-medium text-center">数量</th>
                    <th class="px-3 py-2 font-medium">操作人</th>
                    <th class="px-3 py-2 font-medium">单据</th>
                    <th class="px-3 py-2 font-medium">备注</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="rec in opLogRecords" :key="rec.id + '-' + rec.source" class="hover:bg-gray-50">
                    <td class="px-3 py-2 text-xs text-text-secondary whitespace-nowrap">{{ formatDate(rec.created_at) }}</td>
                    <td class="px-3 py-2">
                      <span :class="['px-2 py-0.5 rounded text-xs font-medium', getOpTypeColor(rec.change_type)]">
                        {{ getOpTypeLabel(rec.change_type) }}
                      </span>
                    </td>
                    <td class="px-3 py-2">
                      <div class="font-medium text-text-primary text-xs">{{ rec.product_name || `商品#${rec.product_id}` }}</div>
                      <div v-if="rec.product_sku" class="text-xs text-text-secondary font-mono">{{ rec.product_sku }}</div>
                    </td>
                    <td class="px-3 py-2 text-center">
                      <span :class="['font-bold', getDeltaColor(rec.delta)]">
                        {{ rec.delta > 0 ? '+' : '' }}{{ rec.delta }}
                      </span>
                    </td>
                    <td class="px-3 py-2 text-center text-xs text-text-secondary">
                      {{ rec.before_qty ?? '—' }} → {{ rec.after_qty ?? '—' }}
                    </td>
                    <td class="px-3 py-2 text-text-primary text-xs">{{ rec.operator }}</td>
                    <td class="px-3 py-2 text-xs font-mono text-text-secondary">{{ rec.record_no || '—' }}</td>
                    <td class="px-3 py-2 text-xs text-text-secondary max-w-xs truncate" :title="rec.remark">{{ rec.remark || '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="px-6 py-3 border-t flex justify-between items-center text-xs text-text-secondary">
            <span>数据来源：warehouse_stock_history + stock_movements + inbound_audit_log</span>
            <button
              @click="showOpLogModal = false"
              class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium transition-colors"
            >
              关闭
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@media (max-width: 768px) {
  /* 卡片列表 - 改为单列 */
  .grid {
    grid-template-columns: 1fr !important;
    gap: 12px !important;
  }

  /* 卡片本身 */
  .bg-white.rounded-lg {
    padding: 16px !important;
  }

  /* 卡片内网格 - 改为双列 */
  .grid.grid-cols-3 {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 12px !important;
  }

  /* 卡片标题 */
  .text-lg.font-bold {
    font-size: 15px !important;
  }

  /* 数量文字 */
  .text-lg.font-bold.text-text-primary {
    font-size: 18px !important;
  }

  /* 操作按钮区 */
  .flex.items-center.gap-2.pt-3 {
    padding-top: 12px !important;
  }

  /* 弹窗 */
  .relative.bg-white.rounded-xl.shadow-xl.w-full.max-w-md {
    max-width: calc(100vw - 32px) !important;
    margin: 0 16px !important;
  }

  /* 弹窗内边距 */
  .p-6.space-y-4 {
    padding: 16px !important;
  }

  /* 弹窗按钮 */
  .px-6.py-4.border-t.flex.gap-3.justify-end {
    padding: 12px 16px !important;
  }

  /* 头部按钮文字 */
  .px-4.py-2.rounded-lg.text-sm {
    font-size: 13px !important;
    padding: 8px 12px !important;
  }

  /* 分隔线按钮文字 */
  .text-xs.font-medium {
    font-size: 12px !important;
  }

  /* 表单项全宽 */
  input.w-full,
  select.w-full {
    width: 100% !important;
  }

  /* 标签 */
  .block.text-sm.font-medium {
    font-size: 13px !important;
  }
}
</style>
