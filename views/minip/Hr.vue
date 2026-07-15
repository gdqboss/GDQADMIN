<template>
  <MinipLayout title="人力中心" :canBack="true" tabbar="enterprise">
    <!-- HR 概览 -->
    <div class="hr-hero">
      <div class="hr-stats">
        <div class="stat"><strong>{{ stats.total }}</strong><span>总人数</span></div>
        <div class="stat"><strong>{{ stats.active }}</strong><span>在职</span></div>
        <div class="stat"><strong>{{ stats.attendance }}</strong><span>今日打卡</span></div>
      </div>
    </div>

    <!-- 快捷功能 -->
    <div class="section-title">员工管理</div>
    <div class="grid">
      <router-link to="/minip/hr/directory" class="cell">
        <div class="cell-icon" style="background:#dbeafe">👥</div>
        <span>员工名册</span>
      </router-link>
      <router-link to="/minip/hr/attendance" class="cell">
        <div class="cell-icon" style="background:#dcfce7">⏰</div>
        <span>考勤打卡</span>
      </router-link>
      <router-link to="/minip/hr/salary" class="cell">
        <div class="cell-icon" style="background:#fef3c7">💰</div>
        <span>薪酬管理</span>
      </router-link>
      <router-link to="/minip/hr/leave" class="cell">
        <div class="cell-icon" style="background:#fce7f3">🏖️</div>
        <span>请假申请</span>
      </router-link>
    </div>

    <!-- 部门 -->
    <div class="section-title">部门列表</div>
    <div class="dept-list">
      <div v-for="d in departments" :key="d.name" class="dept-row">
        <span class="dept-name">{{ d.name }}</span>
        <span class="dept-count">{{ d.count }} 人</span>
      </div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const loading = ref(false)
import api from '@/api/request'
import MinipLayout from './MinipLayout.vue'

const stats = ref({ total: 0, active: 0, attendance: 0 })
const departments = ref([
  { name: '运营部', count: 12 },
  { name: '财务部', count: 5 },
  { name: '人事部', count: 3 },
  { name: '销售部', count: 8 }
])

onMounted(async () => {
  try {
    loading.value = true
    const r = await api.get('/oa/employees?limit=1')
    if (r.code === 0) stats.value.total = r.data?.total || 0
  } catch {}
  try {
    const r = await api.get('/minip/enterprise/attendance?type=today')
    if (r.code === 0) stats.value.attendance = r.data?.count || 0
  } catch {}
  finally {
    loading.value = false
  }
})
</script>

<style scoped>
.hr-hero {
  background: linear-gradient(135deg, #06b6d4, #3b82f6);
  color: #fff;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
  box-shadow: 0 4px 12px rgba(6,182,212,0.25);
}
.hr-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}
.hr-stats .stat {
  background: rgba(255,255,255,0.15);
  border-radius: 8px;
  padding: 10px;
  text-align: center;
}
.hr-stats .stat strong {
  display: block;
  font-size: 22px;
  font-weight: 700;
}
.hr-stats .stat span {
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
.dept-list {
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
}
.dept-row {
  display: flex;
  justify-content: space-between;
  padding: 12px;
  border-bottom: 1px solid #f5f6f8;
  font-size: 13px;
}
.dept-row:last-child { border-bottom: 0; }
.dept-name { color: #1f2329; }
.dept-count { color: #6b7280; }
</style>