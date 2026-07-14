<template>
  <MinipLayout title="OA 中心" :canBack="true" tabbar="enterprise">
    <!-- 概览 -->
    <div class="oa-hero">
      <div class="oa-stats">
        <div class="stat"><strong>{{ stats.pending }}</strong><span>待审批</span></div>
        <div class="stat"><strong>{{ stats.completed }}</strong><span>本月完成</span></div>
        <div class="stat"><strong>{{ stats.overdue }}</strong><span>超时</span></div>
      </div>
    </div>

    <!-- 快捷 -->
    <div class="section-title">协同办公</div>
    <div class="grid">
      <navigator url="/minip/oa/approval" class="cell">
        <div class="cell-icon" style="background:#dbeafe">📋</div>
        <span>审批</span>
      </navigator>
      <navigator url="/minip/oa/schedule" class="cell">
        <div class="cell-icon" style="background:#dcfce7">📅</div>
        <span>排班</span>
      </navigator>
      <navigator url="/minip/oa/meeting" class="cell">
        <div class="cell-icon" style="background:#fef3c7">🏢</div>
        <span>会议</span>
      </navigator>
      <navigator url="/minip/oa/workflow" class="cell">
        <div class="cell-icon" style="background:#fce7f3">⚙️</div>
        <span>流程</span>
      </navigator>
    </div>

    <!-- 待办 -->
    <div class="section-title">待办</div>
    <div class="todo-list">
      <div v-for="t in todos" :key="t.id" class="todo-row">
        <div class="todo-main">
          <div class="todo-title">{{ t.title }}</div>
          <div class="todo-meta">{{ t.applicant }} · {{ t.time }}</div>
        </div>
        <button class="todo-btn primary" @click="approve(t)">通过</button>
        <button class="todo-btn" @click="reject(t)">驳回</button>
      </div>
      <div v-if="!todos.length" class="empty">暂无待办</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/utils/api'
import { ElMessage } from 'element-plus'
import MinipLayout from './MinipLayout.vue'

const stats = ref({ pending: 0, completed: 0, overdue: 0 })
const todos = ref([])

async function approve(t) {
  try {
    await api.post(`/oa/approvals/${t.id}/approve`)
  } catch {}
  todos.value = todos.value.filter(x => x.id !== t.id)
}
async function reject(t) {
  try {
    await api.post(`/oa/approvals/${t.id}/reject`)
  } catch {}
  todos.value = todos.value.filter(x => x.id !== t.id)
}

onMounted(async () => {
  try {
    const r = await api.get('/oa/approvals/pending?limit=20')
    if (r.code === 0) {
      todos.value = r.data || []
      stats.value.pending = todos.value.length
    }
  } catch (e) {
    uni.showToast({ title: '加载失败,请稍后重试', icon: 'none' })
    todos.value = []
  }
})
</script>

<style scoped>
.oa-hero {
  background: linear-gradient(135deg, #f59e0b, #ef4444);
  color: #fff;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
  box-shadow: 0 4px 12px rgba(245,158,11,0.25);
}
.oa-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}
.oa-stats .stat {
  background: rgba(255,255,255,0.15);
  border-radius: 8px;
  padding: 10px;
  text-align: center;
}
.oa-stats .stat strong {
  display: block;
  font-size: 22px;
  font-weight: 700;
}
.oa-stats .stat span {
  font-size: 11px;
  opacity: 0.85;
}
.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #4b5563;
  margin: 12px 4px 8px;
}
.grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  background: #fff;
  border-radius: 12px;
  padding: 12px 8px;
}
.cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8px 4px;
  text-decoration: none;
  color: #1f2329;
  font-size: 12px;
}
.cell-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  margin-bottom: 4px;
}
.todo-list {
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
}
.todo-row {
  display: flex;
  align-items: center;
  padding: 12px;
  gap: 8px;
  border-bottom: 1px solid #f5f6f8;
}
.todo-row:last-child { border-bottom: 0; }
.todo-main { flex: 1; min-width: 0; }
.todo-title { font-size: 13px; font-weight: 500; color: #1f2329; }
.todo-meta { font-size: 11px; color: #6b7280; margin-top: 2px; }
.todo-btn {
  border: 0;
  border-radius: 16px;
  padding: 4px 12px;
  font-size: 11px;
  background: #f3f4f6;
  color: #4b5563;
  cursor: pointer;
}
.todo-btn.primary {
  background: #10b981;
  color: #fff;
}
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>