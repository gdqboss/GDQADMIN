<template>
  <MinipLayout title="分销管理" :canBack="true">
    <div class="ref-hero">
      <div class="ref-stats">
        <div><strong>{{ stats.agents }}</strong><span>分销员</span></div>
        <div><strong>{{ stats.orders }}</strong><span>分销订单</span></div>
        <div><strong>¥{{ stats.commission }}</strong><span>佣金</span></div>
      </div>
    </div>

    <div class="section-title">分销员排行</div>
    <div class="agent-list">
      <div v-for="(a, i) in list" :key="a.id" class="agent-row">
        <span class="agent-rank" :class="i < 3 ? 'top' : ''">{{ i + 1 }}</span>
        <div class="agent-avatar">{{ (a.name || '?').charAt(0) }}</div>
        <div class="agent-main">
          <div class="agent-name">{{ a.name }}</div>
          <div class="agent-meta">订单 {{ a.orders }} · 转化 {{ a.conversion }}%</div>
        </div>
        <div class="agent-commission">¥{{ a.commission }}</div>
      </div>
      <div v-if="!list.length" class="empty">暂无分销员</div>
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
const stats = ref({ agents: 0, orders: 0, commission: 0 })

onMounted(async () => {
  try {
    loading.value = true
    const r = await api.get('/minip/enterprise/approvals?limit=20')
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
.ref-hero {
  background: linear-gradient(135deg, #f43f5e, #ec4899);
  color: #fff;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
  box-shadow: 0 4px 12px rgba(244,63,94,0.25);
}
.ref-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}
.ref-stats div {
  background: rgba(255,255,255,0.15);
  border-radius: 8px;
  padding: 10px;
  text-align: center;
}
.ref-stats strong {
  display: block;
  font-size: 18px;
  font-weight: 700;
}
.ref-stats span {
  font-size: 11px;
  opacity: 0.85;
}
.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #4b5563;
  margin: 0 4px 8px;
}
.agent-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.agent-row {
  background: #fff;
  border-radius: 10px;
  padding: 10px 12px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.agent-rank {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #f3f4f6;
  color: #6b7280;
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.agent-rank.top {
  background: linear-gradient(135deg, #fbbf24, #f59e0b);
  color: #fff;
}
.agent-avatar {
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
.agent-main { flex: 1; min-width: 0; }
.agent-name { font-size: 13px; font-weight: 500; color: #1f2329; }
.agent-meta { font-size: 11px; color: #6b7280; margin-top: 2px; }
.agent-commission {
  font-size: 14px;
  font-weight: 700;
  color: #f43f5e;
}
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>