import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../services/api.js'
import { qrcodeStatusMap } from '../mock/qrcode.js'

export const useQrcodeStore = defineStore('qrcode', () => {
  const qrcodes = ref([])
  const qrTotal = ref(0)
  const scanLogs = ref([])
  const afterSaleRecords = ref([])
  const userHierarchy = ref([])
  const commissionRecords = ref([])
  const loading = ref(false)

  const stats = computed(() => ({
    total: qrcodes.value.length,
    unused: qrcodes.value.filter(q => q.status === 'unused').length,
    bound: qrcodes.value.filter(q => q.status === 'bindProduct').length,
    inStock: qrcodes.value.filter(q => q.status === 'inStock').length,
    outStock: qrcodes.value.filter(q => q.status === 'outStock').length,
    shipped: qrcodes.value.filter(q => q.status === 'shipped').length,
    sold: qrcodes.value.filter(q => q.status === 'sold').length,
    activated: qrcodes.value.filter(q => q.status === 'activated').length,
    afterSale: qrcodes.value.filter(q => q.status === 'afterSale').length,
    returned: qrcodes.value.filter(q => q.status === 'returned').length,
    totalScans: qrcodes.value.reduce((s, q) => s + (q.scan_count || q.scanCount || 0), 0),
  }))

  const totalCommission = computed(() =>
    commissionRecords.value.reduce((s, c) => s + Number(c.amount || 0), 0)
  )
  const pendingCommission = computed(() =>
    commissionRecords.value.filter(c => c.status === 'pending').reduce((s, c) => s + Number(c.amount || 0), 0)
  )

  async function fetchQrcodes(params = {}) {
    loading.value = true
    try {
      const res = await api.get('/qrcodes', { params })
      if (res.code === 0) {
        qrcodes.value = res.data.list || res.data
        qrTotal.value = res.data.total ?? qrcodes.value.length
      }
    } finally { loading.value = false }
  }

  async function generateBatch(count) {
    const res = await api.post('/qrcodes/batch', { count })
    if (res.code === 0) await fetchQrcodes()
    return res
  }

  async function bindProduct(qrcodeId, productId, skuId = null, options = {}) {
    // options: { mode: 'single'|'batch', batch_quantity: number, warehouse_id: number }
    const payload = { product_id: productId }
    if (skuId) payload.sku_id = skuId
    if (options.mode) payload.mode = options.mode
    if (options.batch_quantity) payload.batch_quantity = options.batch_quantity
    if (options.warehouse_id) payload.warehouse_id = options.warehouse_id
    const res = await api.put(`/qrcodes/${qrcodeId}/bind`, payload)
    if (res.code === 0) await fetchQrcodes()
    return res
  }

  async function sell(qrcodeId, options = {}) {
    // options: { quantity, buyer, sales_person }
    const res = await api.post(`/qrcodes/${qrcodeId}/sell`, options)
    if (res.code === 0) await fetchQrcodes()
    return res
  }

  async function adjustBatch(qrcodeId, delta, reason = '') {
    const res = await api.put(`/qrcodes/${qrcodeId}/adjust-batch`, { delta, reason })
    if (res.code === 0) await fetchQrcodes()
    return res
  }

  async function updateStatus(qrcodeId, newStatus, extra = {}) {
    await api.put(`/qrcodes/${qrcodeId}/status`, { status: newStatus, ...extra })
    await fetchQrcodes()
  }

  async function fetchScanLogs(qrcodeId) {
    const res = await api.get(`/qrcodes/${qrcodeId}/scan-logs`)
    if (res.code === 0) scanLogs.value = res.data
  }

  async function fetchAfterSale() {
    const res = await api.get('/qrcodes/after-sale')
    if (res.code === 0) afterSaleRecords.value = res.data
  }

  async function fetchHierarchy() {
    const res = await api.get('/qrcodes/user-hierarchy')
    if (res.code === 0) userHierarchy.value = res.data
  }

  async function fetchCommissions(params = {}) {
    const res = await api.get('/qrcodes/commissions', { params })
    if (res.code === 0) commissionRecords.value = res.data
  }

  async function deleteQrcodes(ids) {
    if (ids.length === 1) {
      await api.delete(`/qrcodes/${ids[0]}`)
    } else {
      await api.delete('/qrcodes/batch', { data: { ids } })
    }
    await fetchQrcodes()
  }

  return {
    qrcodes, qrTotal, scanLogs, afterSaleRecords, userHierarchy, commissionRecords,
    loading, stats, totalCommission, pendingCommission, qrcodeStatusMap,
    fetchQrcodes, generateBatch, bindProduct, sell, adjustBatch, updateStatus,
    fetchScanLogs, fetchAfterSale, fetchHierarchy, fetchCommissions, deleteQrcodes,
  }
})
