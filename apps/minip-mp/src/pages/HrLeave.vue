<template>
  <MinipLayout title="请假申请" :canBack="true">
    <!-- 假期余额 -->
    <div class="balance-grid">
      <div class="bal-cell">
        <div class="bal-num">{{ balance.annual }}</div>
        <div class="bal-label">年假</div>
      </div>
      <div class="bal-cell">
        <div class="bal-num">{{ balance.sick }}</div>
        <div class="bal-label">病假</div>
      </div>
      <div class="bal-cell">
        <div class="bal-num">{{ balance.personal }}</div>
        <div class="bal-label">事假</div>
      </div>
    </div>

    <!-- 申请按钮 -->
    <button class="apply-btn" @click="showApply">+ 新建请假申请</button>

    <!-- 申请记录 -->
    <div class="section-title">申请记录</div>
    <div class="leave-list">
      <div v-for="l in list" :key="l.id" class="leave-card">
        <div class="leave-top">
          <span class="leave-type">{{ typeLabel(l.type) }}</span>
          <span class="leave-status" :class="l.status">{{ statusLabel(l.status) }}</span>
        </div>
        <div class="leave-time">{{ l.start_date }} 至 {{ l.end_date }}</div>
        <div class="leave-days">{{ l.days }} 天</div>
        <div class="leave-reason">{{ l.reason }}</div>
      </div>
      <div v-if="!list.length" class="empty">暂无申请记录</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const loading = ref(false)
import api from '@/utils/api'
import MinipLayout from './MinipLayout.vue'

const balance = ref({ annual: 5, sick: 3, personal: 2 })
const list = ref([])

function typeLabel(t) {
  return { annual: '年假', sick: '病假', personal: '事假', marriage: '婚假', funeral: '丧假' }[t] || t
}
function statusLabel(s) {
  return { pending: '待审', approved: '已通过', rejected: '已驳回' }[s] || s
}
function showApply() {
  const type = prompt('请假类型：annual/sick/personal', 'annual')
  const days = prompt('请假天数', '1')
  const reason = prompt('请假原因', '')
  if (type && days) {
    api.post('/oa/leave/apply', {
      type,
      days: Number(days),
      reason: reason || '',
      start_date: new Date().toISOString().slice(0, 10),
      end_date: new Date(Date.now() + (Number(days) - 1) * 86400000).toISOString().slice(0, 10)
    }).catch(() => {})
    list.value.unshift({
      id: Date.now(),
      type,
      days: Number(days),
      start_date: new Date().toISOString().slice(0, 10),
      end_date: new Date(Date.now() + (Number(days) - 1) * 86400000).toISOString().slice(0, 10),
      reason: reason || '',
      status: 'pending'
    })
  }
}

onMounted(async () => {
  try {
    loading.value = true
    const r = await api.get('/oa/leave?limit=50')
    if (r.code === 0) {
      list.value = r.data || []
      if (r.data?.balance) balance.value = r.data.balance
    }
  } catch (e) {
    uni.showToast({ title: '加载失败,请稍后重试', icon: 'none' })
    list.value = []
  }
  finally {
    loading.value = false
  }
})
</script>

<style scoped>
.balance-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  margin-bottom: 12px;
}
.bal-cell {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  border-radius: 10px;
  padding: 14px 8px;
  text-align: center;
}
.bal-num { font-size: 22px; font-weight: 700; }
.bal-label { font-size: 11px; opacity: 0.85; margin-top: 2px; }
.apply-btn {
  width: 100%;
  background: linear-gradient(135deg, #10b981, #14b8a6);
  color: #fff;
  border: 0;
  border-radius: 10px;
  padding: 14px;
  font-size: 15px;
  font-weight: 600;
  margin-bottom: 16px;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(16,185,129,0.25);
}
.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #4b5563;
  margin: 0 4px 8px;
}
.leave-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.leave-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
}
.leave-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}
.leave-type {
  background: #eef2ff;
  color: #6366f1;
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 4px;
}
.leave-status {
  font-size: 10px;
  padding: 2px 8px;
  border-radius: 10px;
}
.leave-status.pending { background: #fef3c7; color: #b45309; }
.leave-status.approved { background: #d1fae5; color: #065f46; }
.leave-status.rejected { background: #fee2e2; color: #b91c1c; }
.leave-time { font-size: 13px; color: #1f2329; font-weight: 500; }
.leave-days { font-size: 11px; color: #6b7280; margin-top: 2px; }
.leave-reason { font-size: 11px; color: #9ca3af; margin-top: 4px; }
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>