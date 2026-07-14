<template>
  <MinipLayout title="优惠券" :canBack="true">
    <div class="summary">
      <div><span>已发放</span><strong>{{ stats.issued }}</strong></div>
      <div><span>已使用</span><strong class="ok">{{ stats.used }}</strong></div>
      <div><span>核销率</span><strong>{{ stats.rate }}%</strong></div>
    </div>

    <button class="new-btn" @click="uni.showModal({ content: '创建优惠券功能开发中', showCancel: false })">+ 创建优惠券</button>

    <div class="section-title">优惠券列表</div>
    <div class="coupon-list">
      <div v-for="c in list" :key="c.id" class="coupon-card">
        <div class="cp-left">
          <div class="cp-amount">
            <span class="cp-cur">¥</span>
            <span class="cp-num">{{ c.amount }}</span>
          </div>
          <div class="cp-condition">满 {{ c.min_amount }} 可用</div>
        </div>
        <div class="cp-right">
          <div class="cp-name">{{ c.name }}</div>
          <div class="cp-date">{{ c.start_date }} ~ {{ c.end_date }}</div>
          <div class="cp-stock">已发放 {{ c.issued }}/{{ c.total }}</div>
        </div>
      </div>
      <div v-if="!list.length && !loading" class="empty">暂无优惠券</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/utils/api'
import { ElMessage } from 'element-plus'
import MinipLayout from './MinipLayout.vue'

const list = ref([])
const stats = ref({ issued: 0, used: 0, rate: 0 })

onMounted(async () => {
  try {
    const r = await api.get('/marketing/coupons?limit=50')
    if (r.code === 0) list.value = r.data || []
  } catch (e) {
    uni.showToast({ title: '加载失败,请稍后重试', icon: 'none' })
    list.value = []
  }
})
</script>

<style scoped>
.summary {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  margin-bottom: 12px;
}
.summary > div {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
  text-align: center;
}
.summary span {
  font-size: 11px;
  color: #6b7280;
}
.summary strong {
  display: block;
  font-size: 18px;
  font-weight: 700;
  color: #ec4899;
  margin-top: 4px;
}
.summary strong.ok { color: #10b981; }
.new-btn {
  width: 100%;
  background: linear-gradient(135deg, #ec4899, #f43f5e);
  color: #fff;
  border: 0;
  border-radius: 10px;
  padding: 12px;
  font-size: 14px;
  font-weight: 500;
  margin-bottom: 16px;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(236,72,153,0.25);
}
.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #4b5563;
  margin: 0 4px 8px;
}
.coupon-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.coupon-card {
  background: #fff;
  border-radius: 10px;
  padding: 0;
  display: flex;
  overflow: hidden;
  min-height: 84px;
}
.cp-left {
  background: linear-gradient(135deg, #ec4899, #f43f5e);
  color: #fff;
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  min-width: 96px;
  border-radius: 10px 0 0 10px;
  position: relative;
}
.cp-left::after {
  content: '';
  position: absolute;
  right: -4px;
  top: 50%;
  transform: translateY(-50%);
  width: 8px;
  height: 8px;
  background: #f5f6f8;
  border-radius: 50%;
}
.cp-amount { display: flex; align-items: baseline; }
.cp-cur { font-size: 12px; }
.cp-num { font-size: 28px; font-weight: 700; margin-left: 2px; }
.cp-condition { font-size: 10px; opacity: 0.9; margin-top: 2px; }
.cp-right {
  flex: 1;
  padding: 12px;
}
.cp-name { font-size: 13px; font-weight: 600; color: #1f2329; }
.cp-date { font-size: 10px; color: #6b7280; margin-top: 4px; }
.cp-stock { font-size: 10px; color: #9ca3af; margin-top: 4px; }
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>