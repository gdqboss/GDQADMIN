<template>
  <MinipLayout title="财务支出" :canBack="true">
    <div class="summary-bar">
      <div class="summary-item">
        <span class="label">本月支出</span>
        <span class="val down">¥{{ formatMoney(summary.expense) }}</span>
      </div>
      <div class="summary-item">
        <span class="label">待审批</span>
        <span class="val pending">{{ summary.pending }}</span>
      </div>
    </div>

    <div class="filter-bar">
      <button v-for="f in filters" :key="f.v" :class="{on: filter===f.v}" @click="filter=f.v;load()">{{ f.l }}</button>
    </div>

    <div class="expense-list">

      <div v-if="loading" class="empty">加载中…</div>
      <div v-for="item in list" :key="item.id" class="expense-card">
        <div class="expense-top">
          <span class="expense-cat">{{ item.category }}</span>
          <span class="expense-amount">¥{{ formatMoney(item.amount) }}</span>
        </div>
        <div class="expense-desc">{{ item.description }}</div>
        <div class="expense-bottom">
          <span class="expense-date">{{ item.date }}</span>
          <span class="status-tag" :class="item.status">{{ statusLabel(item.status) }}</span>
        </div>
      </div>
      <div v-if="!list.length && !loading" class="empty">暂无支出记录</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import api from '@/utils/api'
import MinipLayout from './MinipLayout.vue'

const list = ref([])
const summary = ref({ expense: 0, pending: 0 })
const filter = ref('all')
const loading = ref(false)
const filters = [
  { v: 'all', l: '全部' },
  { v: 'pending', l: '待审批' },
  { v: 'approved', l: '已通过' },
  { v: 'rejected', l: '已驳回' }
]

function formatMoney(v) {
  return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function statusLabel(s) {
  return { pending: '待审批', approved: '已通过', rejected: '已驳回' }[s] || s
}

async function load() {
  loading.value = true
  try {
    const r = await api.get(`/finance/expenses?status=${filter.value}&limit=50`)
    if (r.code === 0) {
      list.value = r.data || []
    } else {
      uni.showToast({ title: r.message || '加载失败', icon: 'none' })
      list.value = []
    }
  } catch (e) {
    uni.showToast({ title: '网络错误,请稍后重试', icon: 'none' })
    list.value = []
  } finally {
    loading.value = false
  }
}

onMounted(() => load())
</script>

<style scoped>
.summary-bar {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin-bottom: 12px;
}
.summary-item {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
  display: flex;
  flex-direction: column;
}
.summary-item .label { font-size: 11px; color: #6b7280; }
.summary-item .val { font-size: 18px; font-weight: 700; margin-top: 4px; }
.summary-item .val.down { color: #ef4444; }
.summary-item .val.pending { color: #f59e0b; }
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
.expense-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.expense-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
}
.expense-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}
.expense-cat {
  background: #eef2ff;
  color: #6366f1;
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 4px;
}
.expense-amount {
  font-size: 16px;
  font-weight: 700;
  color: #ef4444;
}
.expense-desc {
  font-size: 13px;
  color: #1f2329;
  margin-bottom: 6px;
}
.expense-bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11px;
  color: #9ca3af;
}
.status-tag {
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 10px;
}
.status-tag.pending { background: #fef3c7; color: #b45309; }
.status-tag.approved { background: #d1fae5; color: #065f46; }
.status-tag.rejected { background: #fee2e2; color: #b91c1c; }
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>