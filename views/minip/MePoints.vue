<template>
  <MinipLayout title="我的积分" :canBack="true">
    <div class="points-hero">
      <div class="ph-label">当前积分</div>
      <div class="ph-num">{{ points.toLocaleString() }}</div>
      <div class="ph-meta">≈ ¥{{ Math.floor(points / 100) }} 可用</div>
    </div>

    <div class="exchange">
      <button>兑换优惠券</button>
      <button>兑换商品</button>
    </div>

    <div class="section-title">积分明细</div>
    <div class="points-list">
      <div v-for="p in history" :key="p.id" class="pl-row">
        <div class="pl-main">
          <div class="pl-title">{{ p.title }}</div>
          <div class="pl-date">{{ p.date }}</div>
        </div>
        <div class="pl-amount" :class="p.type">{{ p.type === 'in' ? '+' : '-' }}{{ p.amount }}</div>
      </div>
      <div v-if="!history.length" class="empty">暂无积分明细</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/request'
import MinipLayout from './MinipLayout.vue'

const points = ref(0)
const history = ref([])

onMounted(async () => {
  try {
    const r = await api.get('/members/me/points')
    if (r.code === 0) {
      points.value = r.data?.balance || 0
      history.value = r.data?.history || []
    }
  } catch {
    points.value = 2380
    history.value = [
      { id: 1, title: '购物奖励', date: '2026-07-10', amount: 56, type: 'in' },
      { id: 2, title: '兑换商品', date: '2026-07-08', amount: 200, type: 'out' },
      { id: 3, title: '签到奖励', date: '2026-07-07', amount: 10, type: 'in' }
    ]
  }
})
</script>

<style scoped>
.points-hero {
  background: linear-gradient(135deg, #f59e0b, #f43f5e);
  color: #fff;
  border-radius: 12px;
  padding: 20px 16px;
  text-align: center;
  margin-bottom: 12px;
  box-shadow: 0 4px 12px rgba(245,158,11,0.25);
}
.ph-label { font-size: 12px; opacity: 0.9; }
.ph-num {
  font-size: 36px;
  font-weight: 700;
  margin: 4px 0;
  font-variant-numeric: tabular-nums;
}
.ph-meta { font-size: 11px; opacity: 0.85; }
.exchange {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin-bottom: 12px;
}
.exchange button {
  background: #fff;
  border: 0;
  border-radius: 10px;
  padding: 12px;
  font-size: 13px;
  color: #6366f1;
  cursor: pointer;
}
.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #4b5563;
  margin: 12px 4px 8px;
}
.points-list {
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
}
.pl-row {
  display: flex;
  align-items: center;
  padding: 12px;
  gap: 10px;
  border-bottom: 1px solid #f5f6f8;
}
.pl-row:last-child { border-bottom: 0; }
.pl-main { flex: 1; }
.pl-title { font-size: 13px; color: #1f2329; }
.pl-date { font-size: 11px; color: #9ca3af; margin-top: 2px; }
.pl-amount {
  font-size: 14px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
.pl-amount.in { color: #10b981; }
.pl-amount.out { color: #ef4444; }
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>