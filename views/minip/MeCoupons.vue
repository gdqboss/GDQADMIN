<template>
  <MinipLayout title="我的优惠券" :canBack="true">
    <div class="tabs">
      <div v-for="t in tabs" :key="t.v" :class="{on: filter===t.v}" @click="filter=t.v">{{ t.l }}</div>
    </div>

    <div class="cp-list">
      <div v-for="c in list" :key="c.id" class="cp-card">
        <div class="cp-left">
          <span class="cp-cur">¥</span>
          <span class="cp-num">{{ c.amount }}</span>
        </div>
        <div class="cp-right">
          <div class="cp-name">{{ c.name }}</div>
          <div class="cp-cond">满 ¥{{ c.min_amount }} 可用</div>
          <div class="cp-date">{{ c.end_date }} 到期</div>
        </div>
        <button class="cp-use" @click="useCoupon(c)">立即使用</button>
      </div>
      <div v-if="!list.length" class="empty">暂无优惠券</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref } from 'vue'
import MinipLayout from './MinipLayout.vue'

const filter = ref('unused')
const tabs = [
  { v: 'unused', l: '未使用' },
  { v: 'used', l: '已使用' },
  { v: 'expired', l: '已过期' }
]

const list = ref([
  { id: 1, name: '新人礼券', amount: 30, min_amount: 100, end_date: '2026-07-31' },
  { id: 2, name: '满减优惠', amount: 50, min_amount: 200, end_date: '2026-07-20' },
  { id: 3, name: '会员专享', amount: 20, min_amount: 50, end_date: '2026-08-05' }
])

function useCoupon(c) {
  alert(`使用优惠券：${c.name}`)
}
</script>

<style scoped>
.tabs {
  display: flex;
  background: #fff;
  border-radius: 10px;
  padding: 4px;
  margin-bottom: 12px;
}
.tabs > div {
  flex: 1;
  text-align: center;
  padding: 8px;
  font-size: 12px;
  color: #6b7280;
  border-radius: 8px;
  cursor: pointer;
}
.tabs > div.on {
  background: #ec4899;
  color: #fff;
}
.cp-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.cp-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.cp-left {
  background: linear-gradient(135deg, #ec4899, #f43f5e);
  color: #fff;
  padding: 12px;
  border-radius: 8px;
  text-align: center;
  min-width: 70px;
}
.cp-cur { font-size: 11px; }
.cp-num { font-size: 22px; font-weight: 700; margin-left: 2px; }
.cp-right { flex: 1; }
.cp-name { font-size: 13px; font-weight: 600; color: #1f2329; }
.cp-cond { font-size: 11px; color: #6b7280; margin-top: 2px; }
.cp-date { font-size: 10px; color: #9ca3af; margin-top: 2px; }
.cp-use {
  background: #ec4899;
  color: #fff;
  border: 0;
  border-radius: 14px;
  padding: 4px 10px;
  font-size: 11px;
  cursor: pointer;
}
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>