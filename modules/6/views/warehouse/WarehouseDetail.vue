<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import api from '../../services/api.js'

const { t } = useI18n()

const route = useRoute()
const router = useRouter()
const warehouse = ref(null)
const stockItems = ref([])

// Modals
const showAdjustModal = ref(false)
const showTransferModal = ref(false)
const showHistoryModal = ref(false)
const selectedItem = ref(null)

// Adjust form
const adjustForm = ref({
  adjustment: 0,
  reason: ''
})

// Transfer form
const transferForm = ref({
  toWarehouseId: '',
  quantity: 0,
  reason: ''
})

// History
const historyRecords = ref([])
const warehouses = ref([])

onMounted(async () => {
  await loadWarehouse()
  await loadWarehouses()
})

async function loadWarehouse() {
  const id = route.params.id
  const res = await api.get('/warehouses/' + id)
  warehouse.value = res.data
  stockItems.value = res.data.stockList || []
}

async function loadWarehouses() {
  try {
    const res = await api.get('/warehouses')
    warehouses.value = res.data || []
  } catch (e) {
    console.error('Failed to load warehouses', e)
  }
}

// ─── Adjust Stock ───────────────────────────────────────────────────────────
function openAdjustModal(item) {
  selectedItem.value = item
  adjustForm.value = { adjustment: 0, reason: '' }
  showAdjustModal.value = true
}

async function submitAdjust() {
  try {
    if (!adjustForm.value.adjustment || adjustForm.value.adjustment === 0) {
      alert(t('warehouse.adjustCannotBeZero'))
      return
    }

    await api.post(
      `/warehouses/${warehouse.value.id}/stock/${selectedItem.value.product_id}/adjust`,
      adjustForm.value
    )

    alert(t('warehouse.adjustSuccess'))
    showAdjustModal.value = false
    await loadWarehouse()
  } catch (err) {
    alert(err.response?.data?.message || err.message || t('warehouse.adjustFailed'))
  }
}

// ─── Delete Stock ───────────────────────────────────────────────────────────
async function deleteStock(item) {
  if (!confirm(t('warehouse.confirmDeleteStock', { name: item.product_name, qty: item.quantity }))) {
    return
  }

  try {
    await api.delete(`/warehouses/${warehouse.value.id}/stock/${item.product_id}`)
    alert(t('warehouse.stockDeleted'))
    await loadWarehouse()
  } catch (err) {
    alert(err.response?.data?.message || err.message || t('warehouse.stockDeleteFailed'))
  }
}

// ─── Transfer Stock ─────────────────────────────────────────────────────────
function openTransferModal(item) {
  selectedItem.value = item
  transferForm.value = {
    toWarehouseId: '',
    quantity: 0,
    reason: ''
  }
  showTransferModal.value = true
}

async function submitTransfer() {
  try {
    if (!transferForm.value.toWarehouseId) {
      alert(t('warehouse.selectTargetWarehouseAlert'))
      return
    }

    if (!transferForm.value.quantity || transferForm.value.quantity <= 0) {
      alert(t('warehouse.transferQtyMustBePositive'))
      return
    }

    if (transferForm.value.quantity > selectedItem.value.quantity) {
      alert(t('warehouse.transferQtyExceedsStock'))
      return
    }

    await api.post(
      `/warehouses/${warehouse.value.id}/stock/${selectedItem.value.product_id}/transfer`,
      transferForm.value
    )

    alert(t('warehouse.transferSuccess'))
    showTransferModal.value = false
    await loadWarehouse()
  } catch (err) {
    alert(err.response?.data?.message || err.message || t('warehouse.transferFailed'))
  }
}

// ─── View History ───────────────────────────────────────────────────────────
async function openHistoryModal(item) {
  selectedItem.value = item
  showHistoryModal.value = true

  try {
    const res = await api.get(`/warehouses/${warehouse.value.id}/stock/${item.product_id}/history`)
    historyRecords.value = res.data || []
  } catch (err) {
    console.error('Failed to load history', err)
    historyRecords.value = []
  }
}

function formatDate(str) {
  if (!str) return ''
  return String(str).slice(0, 16).replace('T', ' ')
}

function getTypeLabel(type) {
  const map = {
    inbound: t('warehouse.typeInbound'),
    outbound: t('warehouse.typeOutbound'),
    transfer_in: t('warehouse.typeTransferIn'),
    transfer_out: t('warehouse.typeTransferOut')
  }
  return map[type] || type
}

function getTypeColor(type) {
  const map = {
    inbound: 'text-green-600',
    outbound: 'text-red-600',
    transfer_in: 'text-blue-600',
    transfer_out: 'text-orange-600'
  }
  return map[type] || 'text-gray-600'
}
</script>

<template>
  <div>
    <div class="flex items-center gap-3 mb-6">
      <button @click="router.push('/warehouses')" class="p-2 hover:bg-gray-100 rounded-lg transition-colors text-text-secondary">
        <span class="material-symbols-outlined">arrow_back</span>
      </button>
      <div>
        <h2 class="text-xl font-bold text-text-primary">{{ warehouse?.name || $t('warehouse.detailTitle') }}</h2>
        <p class="text-sm text-text-secondary">{{ warehouse?.address }}</p>
      </div>
      <span class="ml-3 text-xs font-medium px-2 py-0.5 rounded bg-blue-100 text-primary">{{ warehouse?.type }}</span>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
        <p class="text-xs text-text-secondary mb-1">{{ $t('warehouse.stockCount') }}</p>
        <p class="text-2xl font-bold text-text-primary">{{ stockItems.reduce((s, i) => s + (i.quantity || 0), 0).toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
        <p class="text-xs text-text-secondary mb-1">{{ $t('warehouse.productTypes') }}</p>
        <p class="text-2xl font-bold text-text-primary">{{ stockItems.length }}</p>
      </div>
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
        <p class="text-xs text-text-secondary mb-1">{{ $t('warehouse.manager') }}</p>
        <p class="text-2xl font-bold text-text-primary">{{ warehouse?.manager }}</p>
      </div>
    </div>

    <!-- Stock Table -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-bold text-text-primary">{{ $t('warehouse.inventoryDetails') }}</h3>
        <span class="text-xs text-text-secondary">{{ stockItems.length }} {{ $t('warehouse.products') }}</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">SKU</th>
              <th class="px-4 py-3 font-medium">{{ $t('common.productName') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('warehouse.currentStock') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('product.category') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('product.unit') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('common.action') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="item in stockItems" :key="item.sku" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 font-mono text-xs text-text-secondary">{{ item.sku }}</td>
              <td class="px-4 py-3 font-medium text-text-primary">{{ item.product_name }}</td>
              <td class="px-4 py-3 text-center font-medium text-text-primary">{{ item.quantity }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ item.category }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ item.unit }}</td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <button @click="openAdjustModal(item)" class="text-primary hover:text-primary-hover text-xs font-medium mr-3">
                  {{ $t('warehouse.adjust') }}
                </button>
                <button @click="openTransferModal(item)" class="text-blue-600 hover:text-blue-700 text-xs font-medium mr-3">
                  {{ $t('warehouse.transfer') }}
                </button>
                <button @click="openHistoryModal(item)" class="text-text-secondary hover:text-text-primary text-xs font-medium mr-3">
                  {{ $t('warehouse.history') }}
                </button>
                <button @click="deleteStock(item)" class="text-danger hover:text-red-700 text-xs font-medium">
                  {{ $t('warehouse.deleteStock') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ─── Adjust Modal ──────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showAdjustModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30" @click="showAdjustModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="text-lg font-bold text-text-primary">{{ $t('warehouse.adjustStock') }}</h3>
            <button @click="showAdjustModal = false" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <div class="p-6 space-y-4">
            <div class="bg-gray-50 rounded-lg p-3 text-sm">
              <p class="text-text-secondary mb-1">{{ $t('warehouse.productLabel') }}</p>
              <p class="font-medium text-text-primary">{{ selectedItem?.product_name }}</p>
              <p class="text-text-secondary mt-2 mb-1">{{ $t('warehouse.currentStockLabel') }}</p>
              <p class="font-bold text-xl text-primary">{{ selectedItem?.quantity }} {{ selectedItem?.unit }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">
                {{ $t('warehouse.adjustQty') }} <span class="text-danger">*</span>
              </label>
              <input
                v-model.number="adjustForm.adjustment"
                type="number"
                :placeholder="$t('warehouse.adjustQtyPlaceholder')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              />
              <p class="text-xs text-text-secondary mt-1">
                {{ $t('warehouse.adjustQtyHint') }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('warehouse.adjustReason') }}</label>
              <textarea
                v-model="adjustForm.reason"
                rows="3"
                :placeholder="$t('warehouse.adjustReasonPlaceholder')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none resize-none"
              ></textarea>
            </div>

            <div v-if="adjustForm.adjustment !== 0" class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm">
              <p class="text-blue-800">
                {{ $t('warehouse.adjustedStock') }}
                <span class="font-bold">{{ (selectedItem?.quantity || 0) + (adjustForm.adjustment || 0) }}</span>
                {{ selectedItem?.unit }}
              </p>
            </div>
          </div>

          <div class="px-6 py-4 border-t flex gap-3 justify-end">
            <button
              @click="showAdjustModal = false"
              class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-text-primary hover:bg-gray-50 transition-colors"
            >
              {{ $t('common.cancel') }}
            </button>
            <button
              @click="submitAdjust"
              class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium transition-colors"
            >
              {{ $t('warehouse.confirmAdjust') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ─── Transfer Modal ────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showTransferModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30" @click="showTransferModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="text-lg font-bold text-text-primary">{{ $t('warehouse.transferStock') }}</h3>
            <button @click="showTransferModal = false" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <div class="p-6 space-y-4">
            <div class="bg-gray-50 rounded-lg p-3 text-sm">
              <p class="text-text-secondary mb-1">{{ $t('warehouse.productLabel') }}</p>
              <p class="font-medium text-text-primary">{{ selectedItem?.product_name }}</p>
              <p class="text-text-secondary mt-2 mb-1">{{ $t('warehouse.currentWarehouseStock') }}</p>
              <p class="font-bold text-xl text-primary">{{ selectedItem?.quantity }} {{ selectedItem?.unit }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">
                {{ $t('warehouse.targetWarehouse') }} <span class="text-danger">*</span>
              </label>
              <select
                v-model="transferForm.toWarehouseId"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              >
                <option value="">{{ $t('warehouse.selectTargetWarehouse') }}</option>
                <option
                  v-for="wh in warehouses.filter(w => String(w.id) !== String(warehouse?.id))"
                  :key="wh.id"
                  :value="wh.id"
                >
                  {{ wh.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">
                {{ $t('warehouse.transferQty') }} <span class="text-danger">*</span>
              </label>
              <input
                v-model.number="transferForm.quantity"
                type="number"
                min="1"
                :max="selectedItem?.quantity"
                :placeholder="$t('warehouse.transferQtyPlaceholder')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('warehouse.transferReason') }}</label>
              <textarea
                v-model="transferForm.reason"
                rows="2"
                :placeholder="$t('warehouse.transferReasonPlaceholder')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none resize-none"
              ></textarea>
            </div>
          </div>

          <div class="px-6 py-4 border-t flex gap-3 justify-end">
            <button
              @click="showTransferModal = false"
              class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-text-primary hover:bg-gray-50 transition-colors"
            >
              {{ $t('common.cancel') }}
            </button>
            <button
              @click="submitTransfer"
              class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium transition-colors"
            >
              {{ $t('warehouse.confirmTransfer') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ─── History Modal ─────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showHistoryModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30" @click="showHistoryModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[80vh] flex flex-col">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <div>
              <h3 class="text-lg font-bold text-text-primary">{{ $t('warehouse.inoutHistory') }}</h3>
              <p class="text-sm text-text-secondary mt-0.5">{{ selectedItem?.product_name }}</p>
            </div>
            <button @click="showHistoryModal = false" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <div class="flex-1 overflow-y-auto p-6">
            <div v-if="historyRecords.length === 0" class="text-center py-12 text-text-secondary text-sm">
              <span class="material-symbols-outlined text-4xl block mb-2 text-gray-300">history</span>
              {{ $t('warehouse.noHistoryRecords') }}
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="(record, idx) in historyRecords"
                :key="idx"
                class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors"
              >
                <div class="flex items-start justify-between mb-2">
                  <div class="flex items-center gap-2">
                    <span :class="['font-medium text-sm', getTypeColor(record.type)]">
                      {{ getTypeLabel(record.type) }}
                    </span>
                    <span class="text-xs font-mono text-text-secondary">{{ record.record_no }}</span>
                  </div>
                  <span class="text-xs text-text-secondary">{{ formatDate(record.created_at) }}</span>
                </div>

                <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                  <div>
                    <span class="text-text-secondary">{{ $t('warehouse.quantityLabel') }}</span>
                    <span :class="['font-medium', record.type.includes('out') ? 'text-red-600' : 'text-green-600']">
                      {{ record.type.includes('out') ? '-' : '+' }}{{ record.quantity }}
                    </span>
                  </div>
                  <div>
                    <span class="text-text-secondary">{{ $t('warehouse.operatorLabel') }}</span>
                    <span class="text-text-primary">{{ record.operator || '—' }}</span>
                  </div>
                  <div class="col-span-2" v-if="record.party">
                    <span class="text-text-secondary">{{ $t('warehouse.relatedParty') }}</span>
                    <span class="text-text-primary">{{ record.party }}</span>
                  </div>
                  <div class="col-span-2" v-if="record.remark">
                    <span class="text-text-secondary">{{ $t('warehouse.remarkLabel') }}</span>
                    <span class="text-text-primary">{{ record.remark }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="px-6 py-4 border-t flex justify-end">
            <button
              @click="showHistoryModal = false"
              class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium transition-colors"
            >
              {{ $t('common.close') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
