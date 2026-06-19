<template>
  <view class="home-page">
    <view class="search-bar">
      <view class="search-input">
        <text class="search-icon">🔍</text>
        <text class="search-placeholder">搜索商品</text>
      </view>
      <view class="cart-btn">🛒</view>
    </view>

    <view class="banner">
      <view class="banner-content">
        <text class="banner-title">彩美特</text>
        <text class="banner-subtitle">智慧管理系统</text>
        <text class="banner-tag">新品上线 限时特惠</text>
      </view>
      <view class="banner-shape shape-1"></view>
      <view class="banner-shape shape-2"></view>
    </view>

    <view class="quick-actions">
      <view class="action-item" @click="goToPage('/pages/dashboard/index')">
        <view class="action-icon icon-1">📊</view>
        <text class="action-text">工作台</text>
      </view>
      <view class="action-item" @click="goToPage('/pages/products/index')">
        <view class="action-icon icon-2">📦</view>
        <text class="action-text">商品</text>
      </view>
      <view class="action-item" @click="goToPage('/pages/orders/index')">
        <view class="action-icon icon-3">📋</view>
        <text class="action-text">订单</text>
      </view>
      <view class="action-item" @click="goToPage('/pages/member/index')">
        <view class="action-icon icon-4">👤</view>
        <text class="action-text">会员</text>
      </view>
      <view class="action-item" @click="showTip">
        <view class="action-icon icon-5">💰</view>
        <text class="action-text">财务</text>
      </view>
      <view class="action-item" @click="showTip">
        <view class="action-icon icon-6">📈</view>
        <text class="action-text">报表</text>
      </view>
      <view class="action-item" @click="showTip">
        <view class="action-icon icon-7">🏪</view>
        <text class="action-text">门店</text>
      </view>
      <view class="action-item" @click="showTip">
        <view class="action-icon icon-8">⚙️</view>
        <text class="action-text">设置</text>
      </view>
    </view>

    <view class="stats-card">
      <view class="stats-header">
        <text class="stats-title">今日数据</text>
        <text class="stats-date">{{ currentDate }}</text>
      </view>
      <view class="stats-grid">
        <view class="stat-card stat-1">
          <text class="stat-value">{{ stats.sales }}</text>
          <text class="stat-label">销售额</text>
        </view>
        <view class="stat-card stat-2">
          <text class="stat-value">{{ stats.orders }}</text>
          <text class="stat-label">订单数</text>
        </view>
        <view class="stat-card stat-3">
          <text class="stat-value">{{ stats.customers }}</text>
          <text class="stat-label">新增客户</text>
        </view>
        <view class="stat-card stat-4">
          <text class="stat-value">{{ stats.views }}</text>
          <text class="stat-label">浏览量</text>
        </view>
      </view>
    </view>

    <view class="hot-products">
      <view class="section-header">
        <text class="section-title">热门商品</text>
        <text class="section-more" @click="goToPage('/pages/products/index')">查看更多 ›</text>
      </view>
      <view class="product-list">
        <view class="product-card" v-for="item in hotProducts" :key="item.id">
          <view class="product-image">
            <text class="product-emoji">{{ item.emoji }}</text>
            <view v-if="item.tag" class="product-tag">{{ item.tag }}</view>
          </view>
          <view class="product-info">
            <text class="product-name">{{ item.name }}</text>
            <text class="product-desc">{{ item.desc }}</text>
            <view class="product-bottom">
              <text class="product-price">¥{{ item.price }}</text>
              <text class="product-sales">已售{{ item.sales }}</text>
            </view>
          </view>
        </view>
      </view>
    </view>

    <view class="footer-tip">
      <text>✨ 彩美特智慧管理系统 · 让经营更简单</text>
    </view>
  </view>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const currentDate = ref('')
const stats = ref({
  sales: '¥0',
  orders: '0',
  customers: '0',
  views: '0'
})

const hotProducts = ref([])

const tabBarPages = ['/pages/index/index', '/pages/dashboard/index', '/pages/member/index']

function goToPage(url) {
  if (tabBarPages.includes(url)) {
    uni.switchTab({ url })
  } else {
    uni.navigateTo({ url })
  }
}

function showTip() {
  uni.showToast({
    title: '功能开发中',
    icon: 'none'
  })
}

onMounted(() => {
  loadData()
})

function loadData() {
  const now = new Date()
  currentDate.value = `${now.getMonth() + 1}月${now.getDate()}日`

  stats.value = {
    sales: '¥12,580',
    orders: '38',
    customers: '12',
    views: '256'
  }

  hotProducts.value = [
    { id: 1, name: '经典套装', desc: '热门推荐', price: '199.00', sales: '128', emoji: '🎁', tag: '热销' },
    { id: 2, name: '精选礼盒', desc: '送礼首选', price: '299.00', sales: '86', emoji: '💝', tag: '新品' },
    { id: 3, name: '日用好物', desc: '居家必备', price: '89.00', sales: '234', emoji: '🏠' },
    { id: 4, name: '时尚单品', desc: '潮流之选', price: '159.00', sales: '97', emoji: '✨', tag: '特价' }
  ]
}
</script>

<style scoped>
.home-page {
  min-height: 100vh;
  background: #f5f7fa;
  padding-bottom: 40rpx;
}

.search-bar {
  display: flex;
  align-items: center;
  padding: 24rpx 32rpx;
  background: #fff;
  gap: 20rpx;
}

.search-input {
  flex: 1;
  display: flex;
  align-items: center;
  background: #f0f2f5;
  padding: 20rpx 28rpx;
  border-radius: 40rpx;
  gap: 12rpx;
}

.search-icon {
  font-size: 28rpx;
}

.search-placeholder {
  font-size: 28rpx;
  color: #999;
}

.cart-btn {
  font-size: 40rpx;
  padding: 0 12rpx;
}

.banner {
  margin: 20rpx 32rpx;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 24rpx;
  padding: 48rpx 40rpx;
  position: relative;
  overflow: hidden;
}

.banner-content {
  position: relative;
  z-index: 2;
}

.banner-title {
  display: block;
  font-size: 48rpx;
  font-weight: 700;
  color: #fff;
  margin-bottom: 8rpx;
}

.banner-subtitle {
  display: block;
  font-size: 26rpx;
  color: rgba(255, 255, 255, 0.9);
  margin-bottom: 20rpx;
}

.banner-tag {
  display: inline-block;
  background: rgba(255, 255, 255, 0.25);
  padding: 8rpx 24rpx;
  border-radius: 20rpx;
  font-size: 24rpx;
  color: #fff;
}

.banner-shape {
  position: absolute;
  border-radius: 50%;
  opacity: 0.2;
}

.shape-1 {
  width: 200rpx;
  height: 200rpx;
  background: #fff;
  right: -60rpx;
  top: -60rpx;
}

.shape-2 {
  width: 120rpx;
  height: 120rpx;
  background: #fff;
  right: 80rpx;
  bottom: -40rpx;
}

.quick-actions {
  display: flex;
  flex-wrap: wrap;
  background: #fff;
  margin: 0 32rpx;
  border-radius: 24rpx;
  padding: 32rpx 0;
}

.action-item {
  width: 25%;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 20rpx 0;
}

.action-icon {
  width: 88rpx;
  height: 88rpx;
  border-radius: 24rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 44rpx;
  margin-bottom: 12rpx;
}

.icon-1 { background: linear-gradient(135deg, #667eea, #764ba2); }
.icon-2 { background: linear-gradient(135deg, #f093fb, #f5576c); }
.icon-3 { background: linear-gradient(135deg, #4facfe, #00f2fe); }
.icon-4 { background: linear-gradient(135deg, #43e97b, #38f9d7); }
.icon-5 { background: linear-gradient(135deg, #fa709a, #fee140); }
.icon-6 { background: linear-gradient(135deg, #a8edea, #fed6e3); }
.icon-7 { background: linear-gradient(135deg, #ff9a9e, #fecfef); }
.icon-8 { background: linear-gradient(135deg, #ffecd2, #fcb69f); }

.action-text {
  font-size: 24rpx;
  color: #333;
}

.stats-card {
  margin: 24rpx 32rpx;
  background: #fff;
  border-radius: 24rpx;
  padding: 32rpx;
}

.stats-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 28rpx;
}

.stats-title {
  font-size: 32rpx;
  font-weight: 600;
  color: #1a1a1a;
}

.stats-date {
  font-size: 24rpx;
  color: #999;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20rpx;
}

.stat-card {
  border-radius: 16rpx;
  padding: 28rpx;
  text-align: center;
}

.stat-1 { background: linear-gradient(135deg, #fff5f5, #fff0f6); }
.stat-2 { background: linear-gradient(135deg, #f0fff4, #f0fff4); }
.stat-3 { background: linear-gradient(135deg, #f5f7ff, #ebf4ff); }
.stat-4 { background: linear-gradient(135deg, #fffaf0, #fffbeb); }

.stat-value {
  display: block;
  font-size: 40rpx;
  font-weight: 700;
  color: #1a1a1a;
  margin-bottom: 8rpx;
}

.stat-label {
  display: block;
  font-size: 24rpx;
  color: #999;
}

.hot-products {
  margin: 24rpx 32rpx;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24rpx;
}

.section-title {
  font-size: 32rpx;
  font-weight: 600;
  color: #1a1a1a;
}

.section-more {
  font-size: 24rpx;
  color: #667eea;
}

.product-list {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20rpx;
}

.product-card {
  background: #fff;
  border-radius: 20rpx;
  overflow: hidden;
}

.product-image {
  height: 240rpx;
  background: linear-gradient(135deg, #f5f7fa, #e8ecf3);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}

.product-emoji {
  font-size: 80rpx;
}

.product-tag {
  position: absolute;
  top: 16rpx;
  left: 16rpx;
  background: linear-gradient(135deg, #f5576c, #f093fb);
  color: #fff;
  font-size: 20rpx;
  padding: 4rpx 16rpx;
  border-radius: 8rpx;
}

.product-info {
  padding: 20rpx;
}

.product-name {
  display: block;
  font-size: 28rpx;
  font-weight: 500;
  color: #1a1a1a;
  margin-bottom: 8rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-desc {
  display: block;
  font-size: 22rpx;
  color: #999;
  margin-bottom: 16rpx;
}

.product-bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.product-price {
  font-size: 32rpx;
  font-weight: 700;
  color: #f5576c;
}

.product-sales {
  font-size: 22rpx;
  color: #999;
}

.footer-tip {
  text-align: center;
  padding: 40rpx 0 20rpx;
  font-size: 24rpx;
  color: #bbb;
}
</style>
