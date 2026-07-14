<template>
  <MinipLayout title="发票管理" :canBack="true">
    <div class="filter-bar">
      <button v-for="f in filters" :key="f.v" :class="{on: filter===f.v}" @click="filter=f.v;load()">{{ f.l }}</button>
    </div>

    <div class="invoice-list">
      <div v-for="inv in list" :key="inv.id" class="invoice-card">
        <div class="invoice-head">
          <span class="inv-no">#{{ inv.invoice_no }}</span>
          <span class="inv-status" :class="inv.status">{{ statusLabel(inv.status) }}</span>
        </div>
        <div class="invoice-row"><span>客户</span><strong>{{ inv.customer }}</strong></div>
        <div class="invoice-row"><span>金额</span><strong>¥{{ formatMoney(inv.amount) }}</strong></div>
        <div class="invoice-row"><span>开票日期</span><strong>{{ inv.date }}</strong></div>
      </div>
      <div v-if="!list.length" class="empty">暂无发票</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/utils/api'
import MinipLayout from './MinipLayout.vue'

const list = ref([])
const filter = ref('all')
const filters = [
  { v: 'all', l: '全部' },
  { v: 'pending', l: '待开' },
  { v: 'issued', l: '已开' },
  { v: 'cancelled', l: '已作废' }
]

function formatMoney(v) {
  return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function statusLabel(s) {
  return { pending: '待开', issued: '已开', cancelled: '已作废' }[s] || s
}

async function load() {
  try {
    const r = await api.get(`/finance/invoices?status=${filter.value}&limit=50`)
    if (r.code === 0) list.value = r.data || []
  } catch (e) {
    uni.showToast({ title: '加载失败,请稍后重试', icon: 'none' })
    list.value = []
  }
}

onMounted(() => load())

const loading = ref(false)
</script>

<style scoped>
.filter-bar {
  display: flex;
  gap: 6px;
  margin-bottom: 8px;
  overflow-x: auto;
}
.filter-bar button {
  padding: 6px 14px;
  background: #fff;
  border: 0;
  border-radius: 16px;
  font-size: 12px;
  color: #6b7280;
  cursor: pointer;
  white-space: nowrap;
}
.filter-bar button.on {
  background: #6366f1;
  color: #fff;
}
.invoice-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.invoice-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
}
.invoice-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}
.inv-no {
  font-size: 13px;
  font-weight: 600;
  color: #1f2329;
}
.inv-status {
  font-size: 10px;
  padding: 2px 8px;
  border-radius: 10px;
}
.inv-status.pending { background: #fef3c7; color: #b45309; }
.inv-status.issued { background: #d1fae5; color: #065f46; }
.inv-status.cancelled { background: #fee2e2; color: #b91c1c; }
.invoice-row {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  padding: 4px 0;
  color: #6b7280;
}
.invoice-row strong {
  color: #1f2329;
  font-weight: 600;
}
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>