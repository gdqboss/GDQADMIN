<template>
  <MinipLayout title="审批列表" :canBack="true">
    <div class="filter-bar">
      <button v-for="f in filters" :key="f.v" :class="{on: filter===f.v}" @click="filter=f.v;load()">{{ f.l }}</button>
    </div>

    <div class="approval-list">
      <div v-for="a in list" :key="a.id" class="approval-card">
        <div class="apr-head">
          <span class="apr-type">{{ a.type || '审批' }}</span>
          <span class="apr-status" :class="a.status">{{ statusLabel(a.status) }}</span>
        </div>
        <div class="apr-title">{{ a.title }}</div>
        <div class="apr-meta">
          <span>申请人：{{ a.applicant || '-' }}</span>
          <span>{{ a.created_at }}</span>
        </div>
        <div v-if="a.amount" class="apr-amount">¥{{ formatMoney(a.amount) }}</div>
        <div v-if="a.status === 'pending'" class="apr-actions">
          <button class="btn-ok" @click="act(a, 'approve')">通过</button>
          <button class="btn-no" @click="act(a, 'reject')">驳回</button>
        </div>
      </div>
      <div v-if="!list.length" class="empty">暂无审批</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/request'
import { ElMessage } from 'element-plus'
import MinipLayout from './MinipLayout.vue'

const list = ref([])
const filter = ref('pending')
const filters = [
  { v: 'pending', l: '待审' },
  { v: 'approved', l: '已通过' },
  { v: 'rejected', l: '已驳回' },
  { v: 'all', l: '全部' }
]

function formatMoney(v) {
  return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function statusLabel(s) {
  return { pending: '待审', approved: '已通过', rejected: '已驳回' }[s] || s
}

async function act(a, action) {
  try {
    await api.post(`/oa/approvals/${a.id}/${action}`)
  } catch {}
  list.value = list.value.filter(x => x.id !== a.id)
}

async function load() {
  try {
    const r = await api.get(`/oa/approvals?status=${filter.value}&limit=50`)
    if (r.code === 0) list.value = r.data || []
  } catch (e) {
    ElMessage.error('加载失败,请稍后重试')
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
.approval-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.approval-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
}
.apr-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}
.apr-type {
  background: #eef2ff;
  color: #6366f1;
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 4px;
}
.apr-status {
  font-size: 10px;
  padding: 2px 8px;
  border-radius: 10px;
}
.apr-status.pending { background: #fef3c7; color: #b45309; }
.apr-status.approved { background: #d1fae5; color: #065f46; }
.apr-status.rejected { background: #fee2e2; color: #b91c1c; }
.apr-title {
  font-size: 14px;
  font-weight: 600;
  color: #1f2329;
  margin-bottom: 4px;
}
.apr-meta {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: #6b7280;
}
.apr-amount {
  font-size: 16px;
  font-weight: 700;
  color: #ef4444;
  margin-top: 6px;
}
.apr-actions {
  display: flex;
  gap: 8px;
  margin-top: 8px;
}
.btn-ok, .btn-no {
  flex: 1;
  border: 0;
  border-radius: 8px;
  padding: 8px;
  font-size: 13px;
  cursor: pointer;
}
.btn-ok { background: #10b981; color: #fff; }
.btn-no { background: #f3f4f6; color: #4b5563; }
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>