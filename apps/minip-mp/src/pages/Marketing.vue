<template>
  <MinipLayout title="营销中心" :canBack="true" tabbar="enterprise">
    <!-- 概览 -->
    <div class="mkt-hero">
      <div class="mkt-stats">
        <div class="stat"><strong>{{ stats.campaigns }}</strong><span>活动</span></div>
        <div class="stat"><strong>{{ stats.coupons }}</strong><span>优惠券</span></div>
        <div class="stat"><strong>¥{{ stats.revenue }}</strong><span>本月收入</span></div>
      </div>
    </div>

    <!-- 入口 -->
    <div class="section-title">营销工具</div>
    <div class="grid">
      <navigator url="/minip/marketing/activity" class="cell">
        <div class="cell-icon" style="background:#dbeafe">🎯</div>
        <span>活动</span>
      </navigator>
      <navigator url="/minip/marketing/coupon" class="cell">
        <div class="cell-icon" style="background:#dcfce7">🎟️</div>
        <span>优惠券</span>
      </navigator>
      <navigator url="/minip/marketing/member" class="cell">
        <div class="cell-icon" style="background:#fef3c7">👑</div>
        <span>会员</span>
      </navigator>
      <navigator url="/minip/marketing/referral" class="cell">
        <div class="cell-icon" style="background:#fce7f3">🤝</div>
        <span>分销</span>
      </navigator>
    </div>

    <!-- 活动列表 -->
    <div class="section-title">进行中活动</div>
    <div class="campaign-list">
      <div v-for="c in campaigns" :key="c.id" class="campaign-card">
        <div class="camp-head">
          <span class="camp-icon">{{ c.icon }}</span>
          <div class="camp-main">
            <div class="camp-title">{{ c.title }}</div>
            <div class="camp-sub">{{ c.start_date }} ~ {{ c.end_date }}</div>
          </div>
          <span class="camp-status">{{ c.status }}</span>
        </div>
        <div class="camp-meta">
          <div><span>参与</span><strong>{{ c.participants }}</strong></div>
          <div><span>转化</span><strong>{{ c.conversions }}</strong></div>
          <div><span>营收</span><strong>¥{{ c.revenue }}</strong></div>
        </div>
      </div>
      <div v-if="!campaigns.length" class="empty">暂无活动</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const loading = ref(false)
import api from '@/utils/api'
import MinipLayout from './MinipLayout.vue'

const stats = ref({ campaigns: 0, coupons: 0, revenue: 0 })
const campaigns = ref([])

onMounted(async () => {
  try {
    loading.value = true
    const r = await api.get('/marketing/campaigns?status=active&limit=10')
    if (r.code === 0) campaigns.value = r.data || []
  } catch (e) {
    uni.showToast({ title: '加载失败,请稍后重试', icon: 'none' })
    campaigns.value = []
  }
  finally {
    loading.value = false
  }
})
</script>

<style scoped>
.mkt-hero {
  background: linear-gradient(135deg, #ec4899, #f43f5e);
  color: #fff;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
  box-shadow: 0 4px 12px rgba(236,72,153,0.25);
}
.mkt-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}
.mkt-stats .stat {
  background: rgba(255,255,255,0.15);
  border-radius: 8px;
  padding: 10px;
  text-align: center;
}
.mkt-stats .stat strong {
  display: block;
  font-size: 18px;
  font-weight: 700;
}
.mkt-stats .stat span {
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
.campaign-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.campaign-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
}
.camp-head {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
}
.camp-icon { font-size: 24px; }
.camp-main { flex: 1; min-width: 0; }
.camp-title { font-size: 14px; font-weight: 600; color: #1f2329; }
.camp-sub { font-size: 11px; color: #6b7280; margin-top: 2px; }
.camp-status {
  background: #fef3c7;
  color: #b45309;
  font-size: 10px;
  padding: 2px 8px;
  border-radius: 10px;
}
.camp-meta {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 4px;
  padding-top: 8px;
  border-top: 1px solid #f5f6f8;
}
.camp-meta div {
  display: flex;
  flex-direction: column;
  align-items: center;
}
.camp-meta span {
  font-size: 10px;
  color: #9ca3af;
}
.camp-meta strong {
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