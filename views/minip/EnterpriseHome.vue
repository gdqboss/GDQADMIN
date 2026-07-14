<template>
  <MinipLayout title="企业服务" :canBack="true" tabbar="enterprise">
    <!-- 企业概览卡片 -->
    <div class="enterprise-hero">
      <div class="enterprise-name">🏢 {{ enterpriseName }}</div>
      <div class="enterprise-stats">
        <div class="stat-cell">
          <div class="stat-num">{{ stats.pendingApprovals }}</div>
          <div class="stat-label">待审批</div>
        </div>
        <div class="stat-cell">
          <div class="stat-num">{{ stats.todayAttendance }}</div>
          <div class="stat-label">今日打卡</div>
        </div>
        <div class="stat-cell">
          <div class="stat-num">{{ stats.openOrders }}</div>
          <div class="stat-label">订单</div>
        </div>
      </div>
    </div>

    <!-- 功能网格 -->
    <div class="grid-title">企业服务</div>
    <div class="grid">
      <router-link to="/minip/finance" class="grid-cell">
        <div class="grid-icon" style="background:#dbeafe">💰</div>
        <span>财务</span>
      </router-link>
      <router-link to="/minip/hr" class="grid-cell">
        <div class="grid-icon" style="background:#dcfce7">👥</div>
        <span>人力</span>
      </router-link>
      <router-link to="/minip/oa" class="grid-cell">
        <div class="grid-icon" style="background:#fef3c7">📋</div>
        <span>OA</span>
      </router-link>
      <router-link to="/minip/marketing" class="grid-cell">
        <div class="grid-icon" style="background:#fce7f3">📣</div>
        <span>营销</span>
      </router-link>
    </div>

    <!-- 待办 -->
    <div class="grid-title">最近动态</div>
    <div class="card-list">
      <div v-for="(item, i) in todos" :key="i" class="list-row">
        <div class="list-dot" :style="{background: item.color}"></div>
        <div class="list-main">
          <div class="list-title">{{ item.title }}</div>
          <div class="list-sub">{{ item.sub }}</div>
        </div>
        <span class="list-time">{{ item.time }}</span>
      </div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const loading = ref(false)
import api from '@/api/request'
import MinipLayout from './MinipLayout.vue'

const enterpriseName = ref('彩美特总部')
const stats = ref({ pendingApprovals: 0, todayAttendance: 0, openOrders: 0 })
const todos = ref([
  { title: '财务报销待审', sub: '小李 - 出差报销 ¥1,280', time: '5分钟前', color: '#6366f1' },
  { title: '考勤异常', sub: '今日 3 人未打卡', time: '20分钟前', color: '#f59e0b' },
  { title: '新订单', sub: '客户 A 提交订单 #SO-2031', time: '1小时前', color: '#10b981' }
])

onMounted(async () => {
  try {
    loading.value = true
    const r = await api.get('/approvals?status=pending&limit=5')
    if (r.code === 0) stats.value.pendingApprovals = r.data?.length || 0
  } catch {}
  try {
    const r = await api.get('/oa/attendance/today')
    if (r.code === 0) stats.value.todayAttendance = r.data?.count || 0
  } catch {}
  try {
    const r = await api.get('/orders?status=pending&limit=1')
    if (r.code === 0) stats.value.openOrders = r.data?.length || 0
  } catch {}
  finally {
    loading.value = false
  }
})
</script>

<style scoped>
.enterprise-hero {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
  box-shadow: 0 4px 12px rgba(99,102,241,0.2);
}
.enterprise-name {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 12px;
}
.enterprise-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}
.stat-cell {
  background: rgba(255,255,255,0.15);
  border-radius: 8px;
  padding: 10px 4px;
  text-align: center;
  backdrop-filter: blur(8px);
}
.stat-num {
  font-size: 22px;
  font-weight: 700;
}
.stat-label {
  font-size: 11px;
  opacity: 0.85;
  margin-top: 2px;
}
.grid-title {
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
.grid-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8px 4px;
  text-decoration: none;
  color: #1f2329;
  font-size: 12px;
}
.grid-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  margin-bottom: 4px;
}
.card-list {
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
}
.list-row {
  display: flex;
  align-items: center;
  padding: 12px;
  gap: 10px;
  border-bottom: 1px solid #f5f6f8;
}
.list-row:last-child { border-bottom: 0; }
.list-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}
.list-main {
  flex: 1;
  min-width: 0;
}
.list-title {
  font-size: 13px;
  font-weight: 500;
  color: #1f2329;
}
.list-sub {
  font-size: 11px;
  color: #6b7280;
  margin-top: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.list-time {
  font-size: 11px;
  color: #9ca3af;
  flex-shrink: 0;
}
</style>