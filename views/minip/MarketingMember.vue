<template>
  <MinipLayout title="会员管理" :canBack="true">
    <div class="level-cards">
      <div v-for="lv in levels" :key="lv.name" class="lv-card" :style="{background: lv.color}">
        <div class="lv-name">{{ lv.name }}</div>
        <div class="lv-count">{{ lv.count }}</div>
        <div class="lv-discount">{{ lv.discount }}</div>
      </div>
    </div>

    <div class="section-title">会员列表</div>
    <div class="member-list">
      <div v-for="m in list" :key="m.id" class="member-row">
        <div class="m-avatar">{{ (m.name || '?').charAt(0) }}</div>
        <div class="m-main">
          <div class="m-name">{{ m.name }}</div>
          <div class="m-phone">{{ m.phone }}</div>
        </div>
        <span class="m-level" :style="{background: levelColor(m.level)}">{{ m.level }}</span>
        <div class="m-points">{{ m.points }} 分</div>
      </div>
      <div v-if="!list.length" class="empty">暂无会员</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const loading = ref(false)
import api from '@/api/request'
import { ElMessage } from 'element-plus'
import MinipLayout from './MinipLayout.vue'

const list = ref([])
const levels = ref([
  { name: '黄金', count: 156, discount: '9.5 折', color: 'linear-gradient(135deg, #fbbf24, #f59e0b)' },
  { name: '铂金', count: 88, discount: '9 折', color: 'linear-gradient(135deg, #94a3b8, #64748b)' },
  { name: '钻石', count: 32, discount: '8.5 折', color: 'linear-gradient(135deg, #c4b5fd, #8b5cf6)' }
])

function levelColor(lv) {
  return { '黄金': '#fbbf24', '铂金': '#94a3b8', '钻石': '#8b5cf6' }[lv] || '#6b7280'
}

onMounted(async () => {
  try {
    loading.value = true
    const r = await api.get('/minip/enterprise/coupons?limit=50')
    if (r.code === 0) list.value = r.data || []
  } catch (e) {
    ElMessage.error('加载失败,请稍后重试')
    list.value = []
  }
  finally {
    loading.value = false
  }
})
</script>

<style scoped>
.level-cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  margin-bottom: 12px;
}
.lv-card {
  color: #fff;
  border-radius: 10px;
  padding: 12px 8px;
  text-align: center;
  box-shadow: 0 4px 8px rgba(0,0,0,0.08);
}
.lv-name { font-size: 12px; opacity: 0.9; }
.lv-count { font-size: 20px; font-weight: 700; margin: 4px 0; }
.lv-discount { font-size: 10px; opacity: 0.85; }
.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #4b5563;
  margin: 0 4px 8px;
}
.member-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.member-row {
  background: #fff;
  border-radius: 10px;
  padding: 10px 12px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.m-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  flex-shrink: 0;
}
.m-main { flex: 1; min-width: 0; }
.m-name { font-size: 13px; font-weight: 500; color: #1f2329; }
.m-phone { font-size: 11px; color: #6b7280; margin-top: 2px; }
.m-level {
  font-size: 10px;
  color: #fff;
  padding: 2px 8px;
  border-radius: 10px;
  font-weight: 600;
}
.m-points {
  font-size: 11px;
  color: #6b7280;
  min-width: 50px;
  text-align: right;
}
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>