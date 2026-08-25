<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import api from '../../services/api.js'
import { useUserStore } from '../../stores/user.js'

const { t } = useI18n()
const userStore = useUserStore()

// ─── State ───────────────────────────────────────────────────────────────────
const activeTab = ref('list')  // list / detail
const stocktakes = ref([])
const currentStocktake = ref(null)  // 当前盘点单详情
const currentItems = ref([])

// 表单
const showStartForm = ref(false)
const startingForm = ref({ warehouse_id: '', notes: '', blind_mode: false })
const submitting = ref(false)
const formError = ref('')
const formSuccess = ref('')

// 仓库
const warehouses = ref([])

// Pagination
const pagination = ref({ page: 1, limit: 20, total: 0 })

// ─── 权限检查 ───────────────────────────────────────────────────────────────
const canRun = computed(() => userStore.canAccess('stocktake:run'))
const canReport = computed(() => userStore.canAccess('stocktake:report'))

// ─── Computed ────────────────────────────────────────────────────────────────
const activeStocktakes = computed(() =>
  stocktakes.value.filter(s => s.status === 'in_progress')
)
const completedStocktakes = computed(() =>
  stocktakes.value.filter(s => s.status === 'completed')
)
const countedProgress = computed(() => {
  if (!currentStocktake.value) return 0
  const total = Number(currentStocktake.value.total_items) || 0
  const counted = Number(currentStocktake.value.counted_items) || 0
  return total ? Math.round(counted / total * 100) : 0
})

const uncountedItems = computed(() =>
  currentItems.value.filter(i => !i.counted)
)

// ─── Methods ─────────────────────────────────────────────────────────────────
const loadWarehouses = async () => {
  try {
    const res = await api.get('/api/warehouses', { params: { status: 'active', limit: 100 } })
    warehouses.value = res.data?.list || res.data || []
  } catch (e) {
    console.error('load warehouses failed:', e)
  }
}

const loadStocktakes = async () => {
  try {
    const res = await api.get('/api/stocktakes', {
      params: { limit: pagination.value.limit, offset: (pagination.value.page - 1) * pagination.value.limit }
    })
    stocktakes.value = res.data?.list || []
    pagination.value.total = res.data?.total || 0
  } catch (e) {
    console.error('load stocktakes failed:', e)
    formError.value = e.response?.data?.message || e.message
  }
}

const startStocktake = async () => {
  if (!startingForm.value.warehouse_id) {
    formError.value = '请选择仓库'
    return
  }
  submitting.value = true
  formError.value = ''
  try {
    const res = await api.post('/api/stocktakes', startingForm.value)
    formSuccess.value = `盘点单 #${res.data.stocktake_id} 已创建（${res.data.total_items} 个 SKU）`
    showStartForm.value = false
    startingForm.value = { warehouse_id: '', notes: '', blind_mode: false }
    await loadStocktakes()
    setTimeout(() => { formSuccess.value = '' }, 3000)
  } catch (e) {
    formError.value = e.response?.data?.message || e.message
  } finally {
    submitting.value = false
  }
}

const openStocktake = async (st) => {
  try {
    const res = await api.get(`/api/stocktakes/${st.id}`)
    currentStocktake.value = res.data
    currentItems.value = res.data.items || []
    activeTab.value = 'detail'
  } catch (e) {
    formError.value = e.response?.data?.message || e.message
  }
}

const countItem = async (item) => {
  const input = prompt(`盘点数（系统库存: ${item.system_stock}）`, String(item.system_stock))
  if (input === null) return
  const counted = Number(input)
  if (Number.isNaN(counted) || counted < 0) {
    alert('请输入有效数字')
    return
  }
  const notes = prompt('备注（可选）', '') || ''
  try {
    const res = await api.post(`/api/stocktakes/${currentStocktake.value.id}/count`, {
      item_id: item.id, counted_stock: counted, notes
    })
    const diff = res.data.diff
    formSuccess.value = `✅ 已提交：系统 ${res.data.system_stock} → 实际 ${res.data.counted_stock}（${diff >= 0 ? '+' : ''}${diff}）`
    // 刷新详情
    await openStocktake({ id: currentStocktake.value.id })
    setTimeout(() => { formSuccess.value = '' }, 3000)
  } catch (e) {
    formError.value = e.response?.data?.message || e.message
  }
}

const completeStocktake = async () => {
  if (!confirm(`确认完成盘点单 #${currentStocktake.value.id}？系统库存将被覆盖。`)) return
  try {
    const res = await api.post(`/api/stocktakes/${currentStocktake.value.id}/complete`)
    formSuccess.value = `🎉 盘点完成：已更新 ${res.data.updated_items} 条库存`
    await loadStocktakes()
    setTimeout(() => {
      activeTab.value = 'list'
      formSuccess.value = ''
    }, 2000)
  } catch (e) {
    formError.value = e.response?.data?.message || e.message
  }
}

const backToList = () => {
  activeTab.value = 'list'
  currentStocktake.value = null
  currentItems.value = []
  formError.value = ''
}

const formatDate = (d) => {
  if (!d) return '-'
  const dt = new Date(d)
  return dt.toLocaleString('zh-CN')
}

// ─── Lifecycle ───────────────────────────────────────────────────────────────
onMounted(async () => {
  await loadWarehouses()
  await loadStocktakes()
})
</script>

<template>
  <div class="p-4 md:p-6 max-w-7xl mx-auto">
    <PageHeader
      :title="t('stocktake.title', '库存盘点')"
      :subtitle="activeTab === 'list' ? t('stocktake.subtitle', '盘点单管理与盘点执行') : t('stocktake.detail_subtitle', `盘点单 #${currentStocktake?.id}`)"
    />

    <!-- 操作提示 -->
    <div v-if="formError" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg">
      ❌ {{ formError }}
    </div>
    <div v-if="formSuccess" class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg">
      {{ formSuccess }}
    </div>

    <!-- Tabs -->
    <div v-if="activeTab === 'list'" class="flex gap-2 mb-4 border-b border-gray-200">
      <button @click="activeTab = 'list'"
        :class="['px-4 py-2 font-medium', activeTab === 'list' ? 'border-b-2 border-primary text-primary' : 'text-gray-500 hover:text-gray-700']">
        📋 盘点列表 ({{ pagination.total }})
      </button>
    </div>

    <div v-else class="flex gap-2 mb-4 border-b border-gray-200">
      <button @click="backToList"
        class="px-4 py-2 font-medium text-gray-500 hover:text-gray-700">
        ← 返回列表
      </button>
      <button class="px-4 py-2 font-medium border-b-2 border-primary text-primary">
        📦 盘点单 #{{ currentStocktake?.id }}（{{ currentStocktake?.warehouse_name }}）
      </button>
    </div>

    <!-- 列表视图 -->
    <div v-if="activeTab === 'list'">
      <div class="flex justify-between items-center mb-4">
        <div class="text-sm text-gray-600">
          共 {{ activeStocktakes.length }} 个进行中，{{ completedStocktakes.length }} 个已完成
        </div>
        <button v-if="canRun" @click="showStartForm = true"
          class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition">
          ➕ 开始盘点
        </button>
      </div>

      <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">仓库</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">状态</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">总数/已盘/差异</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">操作员</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">开始时间</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">操作</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-if="stocktakes.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-gray-400">暂无盘点记录，点击"开始盘点"创建第一个盘点单</td>
            </tr>
            <tr v-for="st in stocktakes" :key="st.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm font-mono">#{{ st.id }}</td>
              <td class="px-4 py-3 text-sm">{{ st.warehouse_name || `仓库 ${st.warehouse_id}` }}</td>
              <td class="px-4 py-3 text-sm">
                <span :class="[
                  'px-2 py-1 text-xs rounded-full font-medium',
                  st.status === 'in_progress' ? 'bg-yellow-100 text-yellow-700' :
                  st.status === 'completed' ? 'bg-green-100 text-green-700' :
                  'bg-gray-100 text-gray-700'
                ]">
                  {{ st.status === 'in_progress' ? '进行中' : st.status === 'completed' ? '已完成' : '已取消' }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm">
                <span class="font-medium">{{ st.total_items }}</span> /
                <span :class="st.counted_items === st.total_items ? 'text-green-600 font-medium' : 'text-orange-600'">
                  {{ st.counted_items }}
                </span> /
                <span :class="st.diff_items > 0 ? 'text-red-600 font-medium' : 'text-gray-400'">
                  {{ st.diff_items }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ st.operator_name }}</td>
              <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(st.started_at) }}</td>
              <td class="px-4 py-3 text-sm">
                <button @click="openStocktake(st)" class="text-primary hover:underline">
                  {{ st.status === 'in_progress' ? '继续盘点' : '查看报告' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 分页 -->
      <div v-if="pagination.total > pagination.limit" class="mt-4 flex justify-between items-center">
        <div class="text-sm text-gray-500">共 {{ pagination.total }} 条</div>
        <div class="flex gap-2">
          <button :disabled="pagination.page === 1" @click="pagination.page--; loadStocktakes()"
            class="px-3 py-1 border rounded disabled:opacity-50">上一页</button>
          <span class="px-3 py-1">{{ pagination.page }} / {{ Math.ceil(pagination.total / pagination.limit) }}</span>
          <button :disabled="pagination.page * pagination.limit >= pagination.total" @click="pagination.page++; loadStocktakes()"
            class="px-3 py-1 border rounded disabled:opacity-50">下一页</button>
        </div>
      </div>
    </div>

    <!-- 详情视图 -->
    <div v-else-if="currentStocktake">
      <!-- 进度条 -->
      <div class="bg-white rounded-lg shadow p-4 mb-4">
        <div class="flex justify-between items-center mb-2">
          <div class="font-medium">{{ currentStocktake.warehouse_name }}</div>
          <div class="text-sm text-gray-500">操作员：{{ currentStocktake.operator_name }}</div>
        </div>
        <div class="flex items-center gap-4 mb-3">
          <div class="flex-1">
            <div class="text-sm text-gray-600 mb-1">
              进度：{{ currentStocktake.counted_items }} / {{ currentStocktake.total_items }}
              <span v-if="currentStocktake.diff_items > 0" class="ml-2 text-red-600">
                ⚠️ {{ currentStocktake.diff_items }} 条差异
              </span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div class="bg-primary h-2 rounded-full transition-all" :style="`width: ${countedProgress}%`"></div>
            </div>
          </div>
          <button v-if="canRun && currentStocktake.status === 'in_progress'"
            @click="completeStocktake"
            :disabled="currentStocktake.counted_items === 0"
            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
            ✅ 完成盘点
          </button>
        </div>
      </div>

      <!-- 明细表 -->
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">商品</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">系统库存</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">实盘</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">差异</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">状态</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">操作</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="item in currentItems" :key="item.id" :class="item.counted ? 'bg-gray-50' : ''">
              <td class="px-4 py-3 text-sm font-mono">{{ item.sku || '-' }}</td>
              <td class="px-4 py-3 text-sm">{{ item.product_name || `商品 ${item.product_id}` }}</td>
              <td class="px-4 py-3 text-sm font-medium">{{ item.system_stock }}</td>
              <td class="px-4 py-3 text-sm">
                <span v-if="item.counted" class="font-medium">{{ item.counted_stock }}</span>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-4 py-3 text-sm">
                <span v-if="item.counted && item.diff !== 0"
                  :class="item.diff > 0 ? 'text-green-600 font-medium' : 'text-red-600 font-medium'">
                  {{ item.diff > 0 ? '+' : '' }}{{ item.diff }}
                </span>
                <span v-else-if="item.counted" class="text-gray-400">0</span>
                <span v-else class="text-gray-300">-</span>
              </td>
              <td class="px-4 py-3 text-sm">
                <span :class="[
                  'px-2 py-1 text-xs rounded-full',
                  item.counted ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'
                ]">
                  {{ item.counted ? '已盘' : '未盘' }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm">
                <button v-if="canRun && currentStocktake.status === 'in_progress' && !item.counted"
                  @click="countItem(item)" class="text-primary hover:underline">
                  盘点
                </button>
                <button v-else-if="canRun && currentStocktake.status === 'in_progress' && item.counted"
                  @click="countItem(item)" class="text-gray-500 hover:text-primary">
                  改盘
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 text-sm text-gray-500">
        剩余 <span class="text-orange-600 font-medium">{{ uncountedItems.length }}</span> 个 SKU 未盘点
      </div>
    </div>

    <!-- 开始盘点弹窗 -->
    <div v-if="showStartForm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-semibold mb-4">开始盘点</h3>
        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">仓库 *</label>
            <select v-model="startingForm.warehouse_id"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
              <option value="">请选择仓库</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">
                {{ w.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">备注</label>
            <textarea v-model="startingForm.notes" rows="2"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
              placeholder="盘点原因 / 备注"></textarea>
          </div>
          <div class="flex items-center">
            <input v-model="startingForm.blind_mode" type="checkbox" id="blind"
              class="w-4 h-4 text-primary rounded">
            <label for="blind" class="ml-2 text-sm text-gray-700">
              盲盘模式（盘点员不显示系统库存）
            </label>
          </div>
        </div>
        <div class="flex gap-2 mt-4">
          <button @click="showStartForm = false" :disabled="submitting"
            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50">
            取消
          </button>
          <button @click="startStocktake" :disabled="submitting || !startingForm.warehouse_id"
            class="flex-1 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark disabled:opacity-50">
            {{ submitting ? '创建中...' : '开始盘点' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>