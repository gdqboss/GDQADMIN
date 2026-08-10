<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import Pagination from '../../components/Pagination.vue'
import api from '../../services/api.js'

const { t } = useI18n()
const router = useRouter()

const transfers = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = 20

const filterStatus = ref('')
const filterFromStore = ref('')
const filterToStore = ref('')
const stores = ref([])

onMounted(async () => {
  await fetchStores()
  await fetchTransfers()
})

async function fetchStores() {
  const res = await api.get('/stores')
  if (res.code === 0) {
    stores.value = res.data.list || res.data
  }
}

async function fetchTransfers() {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      size: pageSize,
      status: filterStatus.value,
      from_store_id: filterFromStore.value,
      to_store_id: filterToStore.value
    }
    const res = await api.get('/transfer', { params })
    if (res.code === 0) {
      transfers.value = res.data.list
      total.value = res.data.total
    }
  } finally {
    loading.value = false
  }
}

function getStatusColor(status) {
  const colors = {
    pending: 'bg-yellow-100 text-yellow-800',
    shipped: 'bg-blue-100 text-blue-800',
    received: 'bg-green-100 text-green-800',
    cancelled: 'bg-gray-100 text-gray-800'
  }
  return colors[status] || 'bg-gray-100 text-gray-800'
}

function viewDetail(id) {
  router.push(`/transfer/${id}`)
}

function editTransfer(id) {
  router.push(`/transfer/create?id=${id}`)
}

async function deleteTransfer(transfer) {
  if (!confirm(`确定删除调货单 ${transfer.record_no}？`)) return
  const res = await api.delete(`/transfer/${transfer.id}`)
  if (res.code === 0) {
    alert(t('common.deleted') || '删除成功')
    fetchTransfers()
  } else {
    alert(res.message || '删除失败')
  }
}

function createTransfer() {
  router.push('/transfer/create')
}
</script>

<template>
  <div class="p-6">
    <PageHeader :title="t('transfer.title')" :subtitle="t('transfer.subtitle')">
      <button @click="createTransfer" class="btn-primary">
        <span class="material-symbols-outlined text-lg">add</span>
        {{ t('transfer.createTransfer') }}
      </button>
    </PageHeader>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('transfer.status') }}</label>
          <select v-model="filterStatus" @change="fetchTransfers" class="input">
            <option value="">{{ t('transfer.allStatus') }}</option>
            <option value="pending">{{ t('transfer.pending') }}</option>
            <option value="shipped">{{ t('transfer.shipped') }}</option>
            <option value="received">{{ t('transfer.received') }}</option>
            <option value="cancelled">{{ t('transfer.cancelled') }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('transfer.fromStore') }}</label>
          <select v-model="filterFromStore" @change="fetchTransfers" class="input">
            <option value="">{{ t('common.all') }}</option>
            <option v-for="store in stores" :key="store.id" :value="store.id">{{ store.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('transfer.toStore') }}</label>
          <select v-model="filterToStore" @change="fetchTransfers" class="input">
            <option value="">{{ t('common.all') }}</option>
            <option v-for="store in stores" :key="store.id" :value="store.id">{{ store.name }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-gray-500">{{ t('common.loading') }}</div>
      <div v-else-if="!transfers.length" class="p-8 text-center text-gray-500">{{ t('transfer.noData') }}</div>
      <table v-else class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('transfer.transferNo') }}</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('transfer.fromStore') }}</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('transfer.toStore') }}</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('transfer.totalQty') }}</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('transfer.status') }}</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('transfer.createdAt') }}</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ t('common.action') }}</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="transfer in transfers" :key="transfer.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ transfer.record_no }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ transfer.from_store_name }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ transfer.to_store_name }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ transfer.total_qty }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span :class="getStatusColor(transfer.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                {{ t(`transfer.${transfer.status}`) }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              {{ new Date(transfer.created_at).toLocaleString() }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              <button @click="viewDetail(transfer.id)" class="text-primary hover:text-primary-dark mr-3">
                {{ t('common.view') }}
              </button>
              <template v-if="transfer.status === 'pending'">
                <button @click="editTransfer(transfer.id)" class="text-blue-600 hover:text-blue-800 mr-3">
                  {{ t('common.edit') || '编辑' }}
                </button>
                <button @click="deleteTransfer(transfer)" class="text-red-600 hover:text-red-800">
                  {{ t('common.delete') || '删除' }}
                </button>
              </template>
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
      @update:current-page="(p) => { currentPage = p; fetchTransfers() }"
    />
  </div>
</template>

<style scoped>
@media (max-width: 768px) {
  .p-6 {
    padding: 1rem;
  }

  .bg-white.rounded-lg.shadow {
    overflow-x: auto;
  }

  table {
    min-width: 700px;
  }

  .grid-cols-1.md\:grid-cols-4 {
    grid-template-columns: 1fr;
  }

  .btn-primary {
    width: 100%;
    justify-content: center;
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
  }

  th, td {
    font-size: 0.75rem;
    padding: 0.5rem;
  }
}
</style>
