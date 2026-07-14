<template>
  <MinipLayout title="我的订单" :canBack="true">
    <div v-if="loading" class="loading-tip">加载中...</div>

    <div class="tabs">
      <div v-for="t in tabs" :key="t.v" :class="{on: filter===t.v}" @click="filter=t.v;load()">{{ t.l }}</div>
    </div>

    <div class="order-list">
      <div v-for="o in list" :key="o.id" class="order-card">
        <div class="ord-head">
          <span class="ord-no">#{{ o.order_no }}</span>
          <span class="ord-status" :class="o.status">{{ statusLabel(o.status) }}</span>
        </div>
        <div class="ord-body">
          <div class="ord-info">
            <div class="ord-product">{{ o.product_name || o.product }}</div>
            <div class="ord-meta">数量 {{ o.quantity || 1 }} · {{ o.created_at }}</div>
          </div>
          <div class="ord-amount">¥{{ formatMoney(o.amount) }}</div>
        </div>
      </div>
      <div v-if="!list.length" class="empty">暂无订单</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/request'
import { ElMessage } from 'element-plus'
import MinipLayout from './MinipLayout.vue'

const list = ref([])
const filter = ref('all')
const loading = ref(false)
const tabs = [
  { v: 'all', l: '全部' },
  { v: 'pending', l: '待付款' },
  { v: 'paid', l: '待发货' },
  { v: 'shipped', l: '待收货' },
  { v: 'completed', l: '已完成' }
]

function formatMoney(v) {
  return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function statusLabel(s) {
  return { pending: '待付款', paid: '待发货', shipped: '待收货', completed: '已完成', cancelled: '已取消' }[s] || s
}

async function load() {
  loading.value = true
  try {
    const r = await api.get(`/orders?status=${filter.value}&limit=50`)
    if (r.code === 0) list.value = r.data || []
  } catch (e) {
    ElMessage.error('加载失败,请稍后重试')
    list.value = []
  } finally {
    loading.value = false
  }
}

onMounted(() => load())
</script>

<style scoped>
.tabs {
  display: flex;
  background: #fff;
  border-radius: 10px;
  padding: 4px;
  margin-bottom: 12px;
  overflow-x: auto;
}
.tabs > div {
  flex: 1;
  text-align: center;
  padding: 8px 4px;
  font-size: 12px;
  color: #6b7280;
  border-radius: 8px;
  cursor: pointer;
  white-space: nowrap;
}
.tabs > div.on {
  background: #6366f1;
  color: #fff;
}
.order-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.order-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
}
.ord-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
  padding-bottom: 8px;
  border-bottom: 1px solid #f5f6f8;
}
.ord-no {
  font-size: 11px;
  color: #6b7280;
  font-family: monospace;
}
.ord-status {
  font-size: 10px;
  padding: 2px 8px;
  border-radius: 10px;
}
.ord-status.pending { background: #fef3c7; color: #b45309; }
.ord-status.paid { background: #dbeafe; color: #1e40af; }
.ord-status.shipped { background: #ede9fe; color: #5b21b6; }
.ord-status.completed { background: #d1fae5; color: #065f46; }
.ord-status.cancelled { background: #f3f4f6; color: #6b7280; }
.ord-body {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.ord-info { flex: 1; }
.ord-product { font-size: 13px; color: #1f2329; font-weight: 500; }
.ord-meta { font-size: 11px; color: #9ca3af; margin-top: 4px; }
.ord-amount {
  font-size: 16px;
  font-weight: 700;
  color: #ec4899;
}
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>