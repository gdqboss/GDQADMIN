<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const loading = ref(true)
const transfer = ref(null)
const processing = ref(false)

onMounted(async () => {
  await fetchTransfer()
})

async function fetchTransfer() {
  loading.value = true
  try {
    const res = await fetch(`/api/transfer/${route.params.id}`, {
      headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
    })
    const json = await res.json()
    if (json.code === 0) {
      transfer.value = json.data
    }
  } finally {
    loading.value = false
  }
}

async function ship() {
  if (!confirm(t('transfer.confirmShipAlert'))) return

  processing.value = true
  try {
    const res = await fetch(`/api/transfer/${route.params.id}/ship`, {
      method: 'PUT',
      headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
    })
    const json = await res.json()
    if (json.code === 0) {
      alert(t('transfer.shipSuccess'))
      await fetchTransfer()
    } else {
      alert(json.message)
    }
  } finally {
    processing.value = false
  }
}

async function receive() {
  if (!confirm(t('transfer.confirmReceiveAlert'))) return

  processing.value = true
  try {
    const res = await fetch(`/api/transfer/${route.params.id}/receive`, {
      method: 'PUT',
      headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
    })
    const json = await res.json()
    if (json.code === 0) {
      alert(t('transfer.receiveSuccess'))
      await fetchTransfer()
    } else {
      alert(json.message)
    }
  } finally {
    processing.value = false
  }
}

async function cancel() {
  if (!confirm(t('transfer.confirmCancelAlert'))) return

  processing.value = true
  try {
    const res = await fetch(`/api/transfer/${route.params.id}/cancel`, {
      method: 'PUT',
      headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
    })
    const json = await res.json()
    if (json.code === 0) {
      alert(t('transfer.cancelSuccess'))
      await fetchTransfer()
    } else {
      alert(json.message)
    }
  } finally {
    processing.value = false
  }
}

function statusLabel(status) {
  const map = {
    pending: t('transfer.statusPending'),
    shipped: t('transfer.statusShipped'),
    received: t('transfer.statusReceived'),
    cancelled: t('transfer.statusCancelled')
  }
  return map[status] || status
}

function statusColor(status) {
  const map = {
    pending: 'bg-warning/10 text-warning',
    shipped: 'bg-primary/10 text-primary',
    received: 'bg-success/10 text-success',
    cancelled: 'bg-danger/10 text-danger'
  }
  return map[status] || 'bg-gray-100 text-text-secondary'
}
</script>

<template>
  <div class="p-6">
    <div class="mb-6 flex items-center gap-3">
      <button @click="router.back()" class="text-text-secondary hover:text-primary">
        <span class="material-symbols-outlined text-[24px]">arrow_back</span>
      </button>
      <div>
        <h1 class="text-2xl font-bold text-text-primary">{{ $t('transfer.detailTitle') }}</h1>
        <p class="text-sm text-text-secondary mt-1">{{ transfer?.record_no }}</p>
      </div>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else-if="transfer" class="space-y-4">
      <!-- Status Card -->
      <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
          <span :class="['px-3 py-1 rounded-full text-sm font-medium', statusColor(transfer.status)]">
            {{ statusLabel(transfer.status) }}
          </span>
          <div class="flex gap-2">
            <button
              v-if="transfer.status === 'pending'"
              @click="ship"
              :disabled="processing"
              class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
            >
              {{ $t('transfer.confirmShipAction') }}
            </button>
            <button
              v-if="transfer.status === 'shipped'"
              @click="receive"
              :disabled="processing"
              class="px-4 py-2 bg-success hover:bg-success/90 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
            >
              {{ $t('transfer.confirmReceiveAction') }}
            </button>
            <button
              v-if="transfer.status === 'pending'"
              @click="cancel"
              :disabled="processing"
              class="px-4 py-2 border border-danger text-danger hover:bg-danger/10 rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
            >
              {{ $t('transfer.cancelTransferAction') }}
            </button>
          </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div>
            <p class="text-xs text-text-secondary mb-1">{{ $t('transfer.fromStore') }}</p>
            <p class="text-sm font-medium text-text-primary">{{ transfer.from_store_name }}</p>
          </div>
          <div>
            <p class="text-xs text-text-secondary mb-1">{{ $t('transfer.toStore') }}</p>
            <p class="text-sm font-medium text-text-primary">{{ transfer.to_store_name }}</p>
          </div>
          <div>
            <p class="text-xs text-text-secondary mb-1">{{ $t('transfer.totalQtyLabel') }}</p>
            <p class="text-sm font-medium text-text-primary">{{ transfer.total_qty }}</p>
          </div>
          <div>
            <p class="text-xs text-text-secondary mb-1">{{ $t('transfer.initiatedByLabel') }}</p>
            <p class="text-sm font-medium text-text-primary">{{ transfer.initiated_by_name }}</p>
          </div>
        </div>

        <div v-if="transfer.note" class="mt-4 pt-4 border-t border-gray-200">
          <p class="text-xs text-text-secondary mb-1">{{ $t('transfer.noteLabel') }}</p>
          <p class="text-sm text-text-primary">{{ transfer.note }}</p>
        </div>

        <div class="mt-4 pt-4 border-t border-gray-200 grid grid-cols-3 gap-4 text-xs text-text-secondary">
          <div>
            <span>{{ $t('transfer.createdAtLabel') }}</span>
            <span class="ml-2">{{ transfer.created_at?.slice(0, 16) }}</span>
          </div>
          <div v-if="transfer.shipped_at">
            <span>{{ $t('transfer.shippedAtLabel') }}</span>
            <span class="ml-2">{{ transfer.shipped_at?.slice(0, 16) }}</span>
          </div>
          <div v-if="transfer.received_at">
            <span>{{ $t('transfer.receivedAtLabel') }}</span>
            <span class="ml-2">{{ transfer.received_at?.slice(0, 16) }}</span>
          </div>
        </div>
      </div>

      <!-- Items -->
      <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-bold text-text-primary mb-4">{{ $t('transfer.transferItemsTitle') }}</h2>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('transfer.productNameCol') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('transfer.skuCol') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('transfer.qrcodeCol') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('transfer.quantityCol') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="item in transfer.items" :key="item.id">
                <td class="px-4 py-3 text-sm text-text-primary">{{ item.product_name }}</td>
                <td class="px-4 py-3 text-sm text-text-secondary font-mono">{{ item.sku || '-' }}</td>
                <td class="px-4 py-3 text-sm text-text-secondary font-mono">{{ item.qrcode || '-' }}</td>
                <td class="px-4 py-3 text-sm text-text-primary">{{ item.quantity }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
