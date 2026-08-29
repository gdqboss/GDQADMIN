<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import Pagination from '../../components/Pagination.vue'
import api from '../../services/api.js'

const { t } = useI18n()

const returns = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = 20

const filterStatus = ref('')
const filterWarehouse = ref('')
const warehouses = ref([])

const showCreateDialog = ref(false)
const createForm = ref({
  product_id: null,
  qrcode_id: null,
  warehouse_id: null,
  quantity: 1,
  reason: '',
  return_type: 'other'
})
const products = ref([])
const createLoading = ref(false)

onMounted(async () => {
  await fetchWarehouses()
  await fetchProducts()
  await fetchReturns()
})

async function fetchWarehouses() {
  const res = await api.get('/warehouses')
  if (res.code === 0) {
    warehouses.value = res.data.list || res.data
  }
}

async function fetchProducts() {
  const res = await api.get('/products', { params: { page: 1, size: 1000 } })
  if (res.code === 0) {
    products.value = res.data.list || res.data
  }
}

async function fetchReturns() {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      size: pageSize,
      status: filterStatus.value,
      warehouse_id: filterWarehouse.value
    }
    const res = await api.get('/returns', { params })
    if (res.code === 0) {
      returns.value = res.data.list
      total.value = res.data.total
    }
  } finally {
    loading.value = false
  }
}

async function createReturn() {
  if (!createForm.value.product_id || !createForm.value.warehouse_id || !createForm.value.reason) {
    alert(t('common.required'))
    return
  }

  createLoading.value = true
  try {
    const res = await api.post('/returns', createForm.value)
    if (res.code === 0) {
      showCreateDialog.value = false
      createForm.value = { product_id: null, qrcode_id: null, warehouse_id: null, quantity: 1, reason: '', return_type: 'other' }
      await fetchReturns()
    }
  } finally {
    createLoading.value = false
  }
}

function getStatusColor(status) {
  const colors = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    completed: 'bg-blue-100 text-blue-800'
  }
  return colors[status] || 'bg-gray-100 text-gray-800'
}

async function deleteReturn(ret) {
  if (!confirm(`确定要删除退货记录 ${ret.record_no} 吗？此操作不可恢复！`)) return
  try {
    const res = await api.delete(`/returns/${ret.id}`)
    if (res.code === 0) {
      await fetchReturns()
    } else {
      alert(res.message || '删除失败')
    }
  } catch (err) {
    alert(err.response?.data?.message || err.message || '删除失败')
  }
}
</script>

<template>
  <div class="p-6">
    <PageHeader :title="t('returns.title')" :subtitle="t('returns.subtitle')">
      <button @click="showCreateDialog = true" class="btn-primary">
        <span class="material-symbols-outlined text-lg">add</span>
        {{ t('returns.createReturn') }}
      </button>
    </PageHeader>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('returns.status') }}</label>
          <select v-model="filterStatus" @change="fetchReturns" class="input">
            <option value="">{{ t('returns.allStatus') }}</option>
            <option value="pending">{{ t('returns.pending') }}</option>
            <option value="approved">{{ t('returns.approved') }}</option>
            <option value="rejected">{{ t('returns.rejected') }}</option>
            <option value="completed">{{ t('returns.completed') }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('returns.warehouse') }}</label>
          <select v-model="filterWarehouse" @change="fetchReturns" class="input">
            <option value="">{{ t('common.all') }}</option>
            <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-gray-500">{{ t('common.loading') }}</div>
      <div v-else-if="!returns.length" class="p-8 text-center text-gray-500">{{ t('returns.noData') }}</div>
      <table v-else class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('returns.returnNo') }}</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('returns.product') }}</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('returns.warehouse') }}</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('returns.quantity') }}</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('returns.returnType') }}</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('returns.status') }}</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('returns.createdAt') }}</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="ret in returns" :key="ret.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ ret.record_no }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ ret.product_name }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ ret.warehouse_name }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ ret.quantity }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ t(`returns.${ret.return_type}`) }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span :class="getStatusColor(ret.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                {{ t(`returns.${ret.status}`) }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              {{ new Date(ret.created_at).toLocaleString() }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
              <button @click="deleteReturn(ret)"
                      class="text-red-600 hover:text-red-800 font-medium">
                {{ t('common.delete') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Pagination
      v-if="total > pageSize"
      :current-page="currentPage"
      :total="total"
      :page-size="pageSize"
      @update:current-page="(p) => { currentPage = p; fetchReturns() }"
    />

    <!-- Create Dialog -->
    <div v-if="showCreateDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-medium mb-4">{{ t('returns.createReturn') }}</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('returns.product') }} *</label>
            <select v-model="createForm.product_id" class="input">
              <option :value="null">{{ t('common.select') }}</option>
              <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('returns.warehouse') }} *</label>
            <select v-model="createForm.warehouse_id" class="input">
              <option :value="null">{{ t('common.select') }}</option>
              <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('returns.quantity') }} *</label>
            <input v-model.number="createForm.quantity" type="number" min="1" class="input" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('returns.returnType') }}</label>
            <select v-model="createForm.return_type" class="input">
              <option value="quality">{{ t('returns.quality') }}</option>
              <option value="wrong_item">{{ t('returns.wrongItem') }}</option>
              <option value="customer_return">{{ t('returns.customerReturn') }}</option>
              <option value="other">{{ t('returns.other') }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('returns.reason') }} *</label>
            <textarea v-model="createForm.reason" :placeholder="t('returns.reasonPlaceholder')" class="input" rows="3"></textarea>
          </div>
        </div>
        <div class="flex gap-2 mt-6">
          <button @click="showCreateDialog = false" class="btn-secondary flex-1">{{ t('common.cancel') }}</button>
          <button @click="createReturn" :disabled="createLoading" class="btn-primary flex-1">
            {{ createLoading ? t('common.submitting') : t('common.submit') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@media (max-width: 768px) {
  .p-6 {
    padding: 12px;
  }

  .bg-white.rounded-lg.shadow {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  table {
    min-width: 600px;
  }

  table th,
  table td {
    padding: 8px 10px;
    font-size: 12px;
  }

  .input,
  select,
  textarea {
    padding: 6px 8px;
    font-size: 13px;
  }

  .btn-primary,
  .btn-secondary {
    padding: 6px 12px;
    font-size: 13px;
  }

  .max-w-md {
    max-width: 95%;
    margin: 0 10px;
  }

  .grid-cols-1.md\:grid-cols-3 {
    grid-template-columns: 1fr;
  }
}
</style>
