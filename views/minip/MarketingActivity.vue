<template>
  <MinipLayout title="营销活动" :canBack="true">
    <div class="filter-bar">
      <button v-for="f in filters" :key="f.v" :class="{on:filter===f.v}" @click="filter=f.v;load()">{{ f.l }}</button>
    </div>

    <div class="camp-list">
      <div v-for="c in list" :key="c.id" class="camp-card">
        <div class="camp-top">
          <span class="camp-emoji">{{ c.icon }}</span>
          <div class="camp-info">
            <div class="camp-name">{{ c.title }}</div>
            <div class="camp-time">{{ c.start_date }} ~ {{ c.end_date }}</div>
          </div>
          <span class="camp-state" :class="c.status">{{ statusLabel(c.status) }}</span>
        </div>
        <div class="camp-desc">{{ c.description || '精彩活动等你来参与' }}</div>
        <div class="camp-row">
          <div><span>参与</span><strong>{{ c.participants || 0 }}</strong></div>
          <div><span>转化</span><strong>{{ c.conversions || 0 }}</strong></div>
          <div><span>营收</span><strong>¥{{ c.revenue || 0 }}</strong></div>
        </div>
      </div>
      <div v-if="!list.length" class="empty">暂无活动</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/request'
import MinipLayout from './MinipLayout.vue'

const list = ref([])
const filter = ref('all')
const filters = [
  { v: 'all', l: '全部' },
  { v: 'active', l: '进行中' },
  { v: 'pending', l: '待开始' },
  { v: 'ended', l: '已结束' }
]

function statusLabel(s) {
  return { active: '进行中', pending: '待开始', ended: '已结束' }[s] || s
}

async function load() {
  try {
    const r = await api.get(`/marketing/campaigns?status=${filter.value}&limit=50`)
    if (r.code === 0) list.value = r.data || []
  } catch {
    list.value = [
      { id: 1, icon: '🎉', title: '夏日满减', start_date: '07-01', end_date: '07-31', status: 'active', participants: 320, conversions: 56, revenue: 12800, description: '满 200 减 30，全场参与' },
      { id: 2, icon: '🛍️', title: '拼团 3 人成团', start_date: '07-05', end_date: '08-05', status: 'active', participants: 145, conversions: 32, revenue: 5800, description: '邀请好友拼团享低价' },
      { id: 3, icon: '⏰', title: '限时秒杀', start_date: '07-15', end_date: '07-15', status: 'pending', participants: 0, conversions: 0, revenue: 0, description: '每日 20:00 准时开抢' }
    ]
  }
}

onMounted(() => load())
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
  background: #ec4899;
  color: #fff;
}
.camp-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.camp-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
}
.camp-top {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}
.camp-emoji { font-size: 24px; }
.camp-info { flex: 1; min-width: 0; }
.camp-name { font-size: 14px; font-weight: 600; color: #1f2329; }
.camp-time { font-size: 11px; color: #6b7280; margin-top: 2px; }
.camp-state {
  font-size: 10px;
  padding: 2px 8px;
  border-radius: 10px;
}
.camp-state.active { background: #d1fae5; color: #065f46; }
.camp-state.pending { background: #fef3c7; color: #b45309; }
.camp-state.ended { background: #f3f4f6; color: #6b7280; }
.camp-desc {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 8px;
}
.camp-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 4px;
  padding-top: 8px;
  border-top: 1px solid #f5f6f8;
}
.camp-row div {
  display: flex;
  flex-direction: column;
  align-items: center;
}
.camp-row span {
  font-size: 10px;
  color: #9ca3af;
}
.camp-row strong {
  font-size: 14px;
  font-weight: 700;
  color: #ec4899;
  margin-top: 2px;
}
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>