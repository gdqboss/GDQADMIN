<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'

const { t } = useI18n()
const router = useRouter()

// ─── State ───────────────────────────────────────────────────
const stockList = ref([])
const warehouses = ref([])
const pagination = ref({ page: 1, size: 20, total: 0 })
const filterWarehouse = ref('')
const filterKeyword = ref('')
const loading = ref(false)

// 调整弹窗
const showAdjust = ref(false)
const adjustingStock = ref(null)
const adjustDelta = ref(0)
const adjustRemark = ref('')
const adjustError = ref('')
const adjusting = ref(false)

// 流水弹窗
const showMovements = ref(false)
const currentMovements = ref([])
const currentStock = ref(null)

// 警告
const message = ref('')
const messageType = ref('success') // success | error

// ─── 加载 ────────────────────────────────────────────────────
async function loadWarehouses() {
  try {
    const r = await api.get('/warehouses', { params: { page: 1, size: 100 } })
    warehouses.value = r?.data?.list || r?.list || []
  } catch (e) {
    console.error('loadWarehouses failed', e)
  }
}

async function loadStock() {
  loading.value = true
  try {
    const params = {
      page: pagination.value.page,
      size: pagination.value.size,
    }
    if (filterWarehouse.value) params.warehouse_id = filterWarehouse.value
    if (filterKeyword.value) params.keyword = filterKeyword.value
    const r = await api.get('/stock', { params })
    stockList.value = r?.data?.list || r?.list || []
    pagination.value.total = r?.data?.total || r?.total || 0
  } catch (e) {
    showMessage(t('stock.loadFailed') || '加载失败', 'error')
  } finally {
    loading.value = false
  }
}

function showMessage(text, type = 'success') {
  message.value = text
  messageType.value = type
  setTimeout(() => { message.value = '' }, 3000)
}

// ─── 调整 ────────────────────────────────────────────────────
function openAdjust(stock) {
  adjustingStock.value = { ...stock }
  adjustDelta.value = 0
  adjustRemark.value = ''
  adjustError.value = ''
  showAdjust.value = true
}

async function submitAdjust() {
  if (!adjustDelta.value || adjustDelta.value === 0) {
    adjustError.value = '请输入调整数量（正数增加，负数减少）'
    return
  }
  adjusting.value = true
  adjustError.value = ''
  try {
    await api.put(`/stock/${adjustingStock.value.id}/adjust`, {
      delta: Number(adjustDelta.value),
      remark: adjustRemark.value || null
    })
    showMessage(`调整成功：${adjustingStock.value.quantity} → ${adjustingStock.value.quantity + Number(adjustDelta.value)}`)
    showAdjust.value = false
    await loadStock()
  } catch (e) {
    adjustError.value = e?.message || e?.msg || '调整失败'
  } finally {
    adjusting.value = false
  }
}

// ─── 删除（必须 quantity=0） ──────────────────────────────────
async function deleteStock(stock) {
  if (stock.quantity !== 0) {
    if (!confirm(`当前数量为 ${stock.quantity}，必须先调整到 0 才能删除。继续去调整吗？`)) return
    openAdjust(stock)
    return
  }
  if (!confirm(`确定删除「${stock.product_name} - ${stock.sku_code || '无SKU'}」的库存关系吗？此操作不可恢复（但会留流水）。`)) return
  try {
    await api.delete(`/stock/${stock.id}`)
    showMessage('已删除')
    await loadStock()
  } catch (e) {
    showMessage(e?.message || e?.msg || '删除失败', 'error')
  }
}

// ─── 流水 ────────────────────────────────────────────────────
async function openMovements(stock) {
  currentStock.value = stock
  showMovements.value = true
  currentMovements.value = []
  try {
    const r = await api.get(`/stock/${stock.id}/movements`)
    currentMovements.value = r?.data?.movements || []
  } catch (e) {
    showMessage('加载流水失败', 'error')
  }
}

function changeTypeLabel(type) {
  const map = {
    inbound: '入库',
    adjust: '调整',
    outbound: '出库',
    return: '退货',
    transfer_in: '调入',
    transfer_out: '调出',
    delete: '删除'
  }
  return map[type] || type
}

function changeTypeColor(type) {
  if (type === 'inbound' || type === 'transfer_in' || type === 'return') return 'text-green-600'
  if (type === 'outbound' || type === 'transfer_out') return 'text-orange-600'
  if (type === 'adjust') return 'text-blue-600'
  if (type === 'delete') return 'text-red-600'
  return 'text-gray-600'
}

function formatSkuLabel(sku) {
  if (!sku) return '—'
  if (sku.sku_code) {
    // 优先用 specs 解析
    if (sku.specs) {
      try {
        const specs = typeof sku.specs === 'string' ? JSON.parse(sku.specs) : sku.specs
        const values = Object.values(specs).filter(v => v != null && v !== '')
        if (values.length) return `${sku.sku_code} - ${values.join('/')}`
      } catch {}
    }
    return sku.sku_code
  }
  return '—'
}

// ─── Init ────────────────────────────────────────────────────
onMounted(async () => {
  await loadWarehouses()
  await loadStock()
})
</script>

<template>
  <div class="p-6 max-w-7xl mx-auto">
    <PageHeader :title="t('stock.title') || '库存管理'" :subtitle="t('stock.subtitle') || 'SKU × 仓库 唯一存在关系'">
      <template #actions>
        <button
          @click="router.push('/inventory/in-out')"
          class="px-4 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary-dark"
        >
          <span class="material-symbols-outlined text-[18px] align-middle mr-1">add</span>
          {{ t('stock.goInbound') || '去入库' }}
        </button>
      </template>
    </PageHeader>

    <!-- 全局消息 -->
    <div v-if="message" class="mb-4 px-4 py-2 rounded text-sm"
         :class="messageType === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'">
      {{ message }}
    </div>

    <!-- 筛选 -->
    <div class="bg-white rounded-lg border border-gray-200 p-4 mb-4 flex flex-wrap gap-3 items-center">
      <div>
        <label class="text-xs text-text-secondary mr-2">仓库</label>
        <select v-model="filterWarehouse" @change="loadStock"
                class="border border-gray-200 rounded px-3 py-1.5 text-sm">
          <option value="">全部</option>
          <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
        </select>
      </div>
      <div class="flex-1 min-w-[200px]">
        <input v-model="filterKeyword" @keyup.enter="loadStock" type="text" placeholder="商品/SKU/规格"
               class="w-full border border-gray-200 rounded px-3 py-1.5 text-sm" />
      </div>
      <button @click="loadStock" class="px-4 py-1.5 text-sm bg-primary text-white rounded">查询</button>
    </div>

    <!-- 表格 -->
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-text-secondary text-xs">
          <tr>
            <th class="px-4 py-3 text-left">仓库</th>
            <th class="px-4 py-3 text-left">商品</th>
            <th class="px-4 py-3 text-left">SKU</th>
            <th class="px-4 py-3 text-left">规格</th>
            <th class="px-4 py-3 text-right">数量</th>
            <th class="px-4 py-3 text-center">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="px-4 py-8 text-center text-text-secondary">加载中...</td>
          </tr>
          <tr v-else-if="stockList.length === 0">
            <td colspan="6" class="px-4 py-8 text-center text-text-secondary">暂无库存数据</td>
          </tr>
          <tr v-for="stock in stockList" :key="stock.id" class="border-t border-gray-100 hover:bg-gray-50">
            <td class="px-4 py-3">{{ stock.warehouse_name || '—' }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <img v-if="stock.image_main" :src="stock.image_main" class="w-8 h-8 rounded object-cover" />
                <span>{{ stock.product_name }}</span>
              </div>
            </td>
            <td class="px-4 py-3 font-mono text-xs">{{ stock.sku_code || '—' }}</td>
            <td class="px-4 py-3 text-xs text-text-secondary">{{ formatSkuLabel(stock) }}</td>
            <td class="px-4 py-3 text-right font-semibold text-base">{{ stock.quantity }}</td>
            <td class="px-4 py-3 text-center">
              <button @click="openAdjust(stock)" class="text-primary hover:underline text-xs mr-3">调整</button>
              <button @click="openMovements(stock)" class="text-blue-600 hover:underline text-xs mr-3">流水</button>
              <button @click="deleteStock(stock)" class="text-red-600 hover:underline text-xs">删除</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 分页 -->
    <div class="mt-4 flex justify-between items-center text-sm">
      <div class="text-text-secondary">共 {{ pagination.total }} 条</div>
      <div class="flex gap-2">
        <button @click="pagination.page = Math.max(1, pagination.page - 1); loadStock()"
                :disabled="pagination.page <= 1"
                class="px-3 py-1 border border-gray-200 rounded disabled:opacity-30">上一页</button>
        <span class="px-3 py-1">第 {{ pagination.page }} 页</span>
        <button @click="pagination.page++; loadStock()"
                :disabled="pagination.page * pagination.size >= pagination.total"
                class="px-3 py-1 border border-gray-200 rounded disabled:opacity-30">下一页</button>
      </div>
    </div>

    <!-- 调整弹窗 -->
    <div v-if="showAdjust" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold mb-4">调整数量</h3>
        <div v-if="adjustingStock" class="space-y-3 mb-4 text-sm">
          <div><span class="text-text-secondary">商品：</span>{{ adjustingStock.product_name }}</div>
          <div><span class="text-text-secondary">SKU：</span>{{ adjustingStock.sku_code || '—' }}</div>
          <div><span class="text-text-secondary">仓库：</span>{{ adjustingStock.warehouse_name }}</div>
          <div><span class="text-text-secondary">当前数量：</span><span class="font-semibold text-lg">{{ adjustingStock.quantity }}</span></div>
        </div>
        <div class="mb-3">
          <label class="text-sm text-text-secondary">调整数量（正数增加，负数减少）</label>
          <input v-model.number="adjustDelta" type="number"
                 class="w-full mt-1 border border-gray-200 rounded px-3 py-2 text-base"
                 :placeholder="`如 +5 或 -3`" />
        </div>
        <div class="mb-4">
          <label class="text-sm text-text-secondary">备注（可选）</label>
          <input v-model="adjustRemark" type="text"
                 class="w-full mt-1 border border-gray-200 rounded px-3 py-2 text-sm"
                 placeholder="如：盘点差异 / 退货入仓" />
        </div>
        <div v-if="adjustError" class="mb-3 px-3 py-2 bg-red-50 text-red-700 text-sm rounded">{{ adjustError }}</div>
        <div class="flex gap-2 justify-end">
          <button @click="showAdjust = false" class="px-4 py-2 text-sm text-text-secondary hover:bg-gray-100 rounded">取消</button>
          <button @click="submitAdjust" :disabled="adjusting"
                  class="px-4 py-2 text-sm bg-primary text-white rounded hover:bg-primary-dark disabled:opacity-50">
            {{ adjusting ? '调整中...' : '确认调整' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 流水弹窗 -->
    <div v-if="showMovements" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 max-h-[80vh] overflow-y-auto">
        <h3 class="text-lg font-semibold mb-4">库存流水</h3>
        <div v-if="currentStock" class="mb-4 text-sm text-text-secondary">
          {{ currentStock.warehouse_name }} / {{ currentStock.product_name }} / {{ currentStock.sku_code || '—' }}
          — 当前：<span class="font-semibold">{{ currentStock.quantity }}</span>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs">
            <tr>
              <th class="px-3 py-2 text-left">时间</th>
              <th class="px-3 py-2 text-left">类型</th>
              <th class="px-3 py-2 text-right">变化</th>
              <th class="px-3 py-2 text-right">前→后</th>
              <th class="px-3 py-2 text-left">操作人</th>
              <th class="px-3 py-2 text-left">备注</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="currentMovements.length === 0">
              <td colspan="6" class="px-3 py-4 text-center text-text-secondary">暂无流水</td>
            </tr>
            <tr v-for="m in currentMovements" :key="m.id" class="border-t border-gray-100">
              <td class="px-3 py-2 text-xs text-text-secondary">{{ m.created_at }}</td>
              <td class="px-3 py-2" :class="changeTypeColor(m.change_type)">{{ changeTypeLabel(m.change_type) }}</td>
              <td class="px-3 py-2 text-right font-mono">
                <span :class="m.delta > 0 ? 'text-green-600' : (m.delta < 0 ? 'text-red-600' : 'text-gray-500')">
                  {{ m.delta > 0 ? '+' : '' }}{{ m.delta }}
                </span>
              </td>
              <td class="px-3 py-2 text-right text-xs text-text-secondary">{{ m.before_qty }} → {{ m.after_qty }}</td>
              <td class="px-3 py-2 text-xs">{{ m.operator }}</td>
              <td class="px-3 py-2 text-xs text-text-secondary">{{ m.remark || '—' }}</td>
            </tr>
          </tbody>
        </table>
        <div class="mt-4 text-right">
          <button @click="showMovements = false" class="px-4 py-2 text-sm text-text-secondary hover:bg-gray-100 rounded">关闭</button>
        </div>
      </div>
    </div>
  </div>
</template>
