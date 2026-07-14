<template>
  <MinipLayout title="个人中心" :canBack="true" tabbar="me">
    <!-- 用户卡 -->
    <div class="me-hero">
      <div class="me-avatar">{{ user.avatar }}</div>
      <div class="me-info">
        <div class="me-name">{{ user.name }}</div>
        <div class="me-phone">{{ user.phone }}</div>
        <div class="me-level">{{ user.level }}</div>
      </div>
    </div>

    <!-- 数据 -->
    <div class="data-grid">
      <div class="data-cell"><strong>{{ user.points }}</strong><span>积分</span></div>
      <div class="data-cell"><strong>{{ user.coupons }}</strong><span>优惠券</span></div>
      <div class="data-cell"><strong>¥{{ user.balance }}</strong><span>余额</span></div>
      <div class="data-cell"><strong>{{ user.orders }}</strong><span>订单</span></div>
    </div>

    <!-- 我的服务 -->
    <div class="section-title">个人服务</div>
    <div class="grid">
      <router-link to="/minip/me/orders" class="cell">
        <div class="cell-icon" style="background:#dbeafe">📦</div>
        <span>我的订单</span>
      </router-link>
      <router-link to="/minip/me/coupons" class="cell">
        <div class="cell-icon" style="background:#dcfce7">🎟️</div>
        <span>优惠券</span>
      </router-link>
      <router-link to="/minip/me/points" class="cell">
        <div class="cell-icon" style="background:#fef3c7">⭐</div>
        <span>积分</span>
      </router-link>
      <router-link to="/minip/me/wallet" class="cell">
        <div class="cell-icon" style="background:#fce7f3">💰</div>
        <span>钱包</span>
      </router-link>
      <router-link to="/minip/me/favorites" class="cell">
        <div class="cell-icon" style="background:#e0e7ff">❤️</div>
        <span>收藏</span>
      </router-link>
      <router-link to="/minip/me/reviews" class="cell">
        <div class="cell-icon" style="background:#fed7aa">⭐</div>
        <span>评价</span>
      </router-link>
      <router-link to="/minip/me/address" class="cell">
        <div class="cell-icon" style="background:#c7d2fe">📍</div>
        <span>地址</span>
      </router-link>
      <router-link to="/minip/me/profile" class="cell">
        <div class="cell-icon" style="background:#bfdbfe">👤</div>
        <span>资料</span>
      </router-link>
    </div>

    <!-- 设置 -->
    <div class="section-title">其他</div>
    <div class="settings-list">
      <div class="settings-row" @click="$router.push('/minip/misc/feedback')">
        <span>💬</span><span class="label">意见反馈</span><span class="arrow">›</span>
      </div>
      <div class="settings-row" @click="$router.push('/minip/misc/about')">
        <span>ℹ️</span><span class="label">关于</span><span class="arrow">›</span>
      </div>
      <div class="settings-row" @click="logout">
        <span>🚪</span><span class="label">退出登录</span><span class="arrow">›</span>
      </div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const loading = ref(false)
import api from '@/api/request'
import { useRouter } from 'vue-router'
import MinipLayout from './MinipLayout.vue'

const router = useRouter()
const user = ref({
  avatar: '👤', name: '会员用户', phone: '138****0000', level: '黄金会员',
  points: 2380, coupons: 5, balance: '1,280', orders: 28
})

async function logout() {
  if (confirm('确定退出登录？')) {
    localStorage.removeItem('caimeite_token')
    localStorage.removeItem('caimeite_user')
    location.href = '/minip/me/login'
  }
}

onMounted(async () => {
  try {
    loading.value = true
    const r = await api.get('/users/me')
    if (r.code === 0 && r.data) {
      user.value = { ...user.value, ...r.data }
      user.value.avatar = (r.data.name || '?').charAt(0).toUpperCase() || '👤'
    }
  } catch {}
  finally {
    loading.value = false
  }
})
</script>

<style scoped>
.me-hero {
  background: linear-gradient(135deg, #6366f1, #ec4899);
  color: #fff;
  border-radius: 12px;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
  box-shadow: 0 4px 12px rgba(99,102,241,0.25);
}
.me-avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: rgba(255,255,255,0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
}
.me-info { flex: 1; }
.me-name { font-size: 18px; font-weight: 700; }
.me-phone { font-size: 12px; opacity: 0.85; margin-top: 2px; }
.me-level {
  display: inline-block;
  background: rgba(255,255,255,0.2);
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 10px;
  margin-top: 6px;
}
.data-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
  margin-bottom: 12px;
}
.data-cell {
  background: #fff;
  border-radius: 10px;
  padding: 10px 4px;
  text-align: center;
}
.data-cell strong {
  display: block;
  font-size: 16px;
  font-weight: 700;
  color: #6366f1;
}
.data-cell span {
  font-size: 10px;
  color: #6b7280;
  margin-top: 2px;
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
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  margin-bottom: 4px;
}
.settings-list {
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
}
.settings-row {
  display: flex;
  align-items: center;
  padding: 12px;
  gap: 10px;
  border-bottom: 1px solid #f5f6f8;
  font-size: 13px;
  color: #1f2329;
  cursor: pointer;
}
.settings-row:last-child { border-bottom: 0; }
.settings-row .label { flex: 1; }
.settings-row .arrow { color: #9ca3af; font-size: 18px; }
</style>