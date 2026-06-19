<template>
  <view class="dashboard-page">
    <view class="header-card">
      <view class="header-content">
        <text class="header-title">工作台</text>
        <text class="header-subtitle">高效管理，轻松经营</text>
      </view>
      <view class="header-badge">📋</view>
    </view>

    <view class="overview">
      <view class="overview-item">
        <text class="overview-value">{{ overview.orders }}</text>
        <text class="overview-label">待处理</text>
      </view>
      <view class="overview-divider"></view>
      <view class="overview-item">
        <text class="overview-value">{{ overview.inventory }}</text>
        <text class="overview-label">库存预警</text>
      </view>
      <view class="overview-divider"></view>
      <view class="overview-item">
        <text class="overview-value">{{ overview.pending }}</text>
        <text class="overview-label">待审批</text>
      </view>
    </view>

    <view class="section-title">
      <text class="title-text">常用功能</text>
    </view>

    <view class="menu-grid">
      <view class="menu-item" @click="goToPage('/pages/products/index')">
        <view class="menu-icon icon-purple">📦</view>
        <text class="menu-text">商品管理</text>
      </view>
      <view class="menu-item" @click="goToPage('/pages/orders/index')">
        <view class="menu-icon icon-blue">📋</view>
        <text class="menu-text">订单管理</text>
      </view>
      <view class="menu-item" @click="showTip">
        <view class="menu-icon icon-green">📊</view>
        <text class="menu-text">库存管理</text>
      </view>
      <view class="menu-item" @click="showTip">
        <view class="menu-icon icon-orange">💰</view>
        <text class="menu-text">财务管理</text>
      </view>
      <view class="menu-item" @click="showTip">
        <view class="menu-icon icon-pink">👥</view>
        <text class="menu-text">会员管理</text>
      </view>
      <view class="menu-item" @click="showTip">
        <view class="menu-icon icon-teal">📈</view>
        <text class="menu-text">数据报表</text>
      </view>
      <view class="menu-item" @click="showTip">
        <view class="menu-icon icon-yellow">🏪</view>
        <text class="menu-text">门店管理</text>
      </view>
      <view class="menu-item" @click="showTip">
        <view class="menu-icon icon-indigo">👨‍💼</view>
        <text class="menu-text">员工管理</text>
      </view>
    </view>

    <view class="section-title">
      <text class="title-text">待办事项</text>
      <text class="title-more" @click="showTip">全部 ›</text>
    </view>

    <view class="todo-list">
      <view class="todo-item" v-for="item in todos" :key="item.id" @click="handleTodo(item)">
        <view class="todo-icon">{{ item.icon }}</view>
        <view class="todo-content">
          <text class="todo-title">{{ item.title }}</text>
          <text class="todo-desc">{{ item.desc }}</text>
        </view>
        <view class="todo-arrow">›</view>
      </view>
    </view>

    <view class="section-title">
      <text class="title-text">快捷操作</text>
    </view>

    <view class="action-list">
      <view class="action-item" @click="showTip">
        <view class="action-ico ico-add">➕</view>
        <text class="action-text">新增商品</text>
      </view>
      <view class="action-item" @click="showTip">
        <view class="action-ico ico-sync">🔄</view>
        <text class="action-text">同步数据</text>
      </view>
      <view class="action-item" @click="showTip">
        <view class="action-ico ico-export">📤</view>
        <text class="action-text">导出报表</text>
      </view>
      <view class="action-item" @click="showTip">
        <view class="action-ico ico-scan">📷</view>
        <text class="action-text">扫码录入</text>
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref } from 'vue'

const tabBarPages = ['/pages/index/index', '/pages/dashboard/index', '/pages/member/index']

const overview = ref({
  orders: '5',
  inventory: '3',
  pending: '2'
})

const todos = ref([
  { id: 1, icon: '📦', title: '待发货订单 (5)', desc: '请及时处理发货', type: 'orders' },
  { id: 2, icon: '⚠️', title: '库存预警 (3)', desc: '有3件商品库存不足', type: 'inventory' },
  { id: 3, icon: '✍️', title: '待审批 (2)', desc: '有2条申请待您审批', type: 'approval' }
])

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

function handleTodo(item) {
  if (item.type === 'orders') {
    uni.navigateTo({ url: '/pages/orders/index' })
  } else if (item.type === 'inventory') {
    showTip()
  } else {
    showTip()
  }
}
</script>

<style scoped>
.dashboard-page {
  min-height: 100vh;
  background: #f5f7fa;
  padding-bottom: 40rpx;
}

.header-card {
  margin: 32rpx;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 24rpx;
  padding: 40rpx;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  overflow: hidden;
}

.header-content {
  z-index: 2;
}

.header-title {
  display: block;
  font-size: 40rpx;
  font-weight: 700;
  color: #fff;
  margin-bottom: 8rpx;
}

.header-subtitle {
  display: block;
  font-size: 26rpx;
  color: rgba(255, 255, 255, 0.9);
}

.header-badge {
  font-size: 80rpx;
  opacity: 0.9;
}

.overview {
  margin: -20rpx 52rpx 32rpx;
  background: #fff;
  border-radius: 20rpx;
  padding: 32rpx;
  display: flex;
  align-items: center;
  box-shadow: 0 4rpx 24rpx rgba(0, 0, 0, 0.08);
}

.overview-item {
  flex: 1;
  text-align: center;
}

.overview-value {
  display: block;
  font-size: 44rpx;
  font-weight: 700;
  color: #f5576c;
  margin-bottom: 8rpx;
}

.overview-label {
  display: block;
  font-size: 24rpx;
  color: #999;
}

.overview-divider {
  width: 2rpx;
  height: 60rpx;
  background: #eee;
}

.section-title {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16rpx 32rpx;
}

.title-text {
  font-size: 30rpx;
  font-weight: 600;
  color: #1a1a1a;
}

.title-more {
  font-size: 24rpx;
  color: #667eea;
}

.menu-grid {
  margin: 0 32rpx;
  background: #fff;
  border-radius: 24rpx;
  padding: 32rpx 0;
  display: flex;
  flex-wrap: wrap;
}

.menu-item {
  width: 25%;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 20rpx 0;
}

.menu-icon {
  width: 88rpx;
  height: 88rpx;
  border-radius: 24rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 44rpx;
  margin-bottom: 12rpx;
}

.icon-purple { background: linear-gradient(135deg, #667eea, #764ba2); }
.icon-blue { background: linear-gradient(135deg, #4facfe, #00f2fe); }
.icon-green { background: linear-gradient(135deg, #43e97b, #38f9d7); }
.icon-orange { background: linear-gradient(135deg, #fa709a, #fee140); }
.icon-pink { background: linear-gradient(135deg, #f093fb, #f5576c); }
.icon-teal { background: linear-gradient(135deg, #a8edea, #fed6e3); }
.icon-yellow { background: linear-gradient(135deg, #ffecd2, #fcb69f); }
.icon-indigo { background: linear-gradient(135deg, #8ec5fc, #e0c3fc); }

.menu-text {
  font-size: 24rpx;
  color: #333;
}

.todo-list {
  margin: 0 32rpx;
  background: #fff;
  border-radius: 24rpx;
  overflow: hidden;
}

.todo-item {
  display: flex;
  align-items: center;
  padding: 28rpx 32rpx;
  border-bottom: 1rpx solid #f0f0f0;
}

.todo-item:last-child {
  border-bottom: none;
}

.todo-icon {
  font-size: 44rpx;
  width: 68rpx;
  height: 68rpx;
  background: #f0f2f5;
  border-radius: 16rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 20rpx;
}

.todo-content {
  flex: 1;
}

.todo-title {
  display: block;
  font-size: 28rpx;
  color: #1a1a1a;
  margin-bottom: 6rpx;
}

.todo-desc {
  display: block;
  font-size: 22rpx;
  color: #999;
}

.todo-arrow {
  font-size: 36rpx;
  color: #ccc;
}

.action-list {
  margin: 0 32rpx;
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx 32rpx;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 24rpx;
}

.action-item {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.action-ico {
  width: 84rpx;
  height: 84rpx;
  border-radius: 20rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 40rpx;
  margin-bottom: 12rpx;
}

.ico-add { background: linear-gradient(135deg, #f093fb, #f5576c); }
.ico-sync { background: linear-gradient(135deg, #4facfe, #00f2fe); }
.ico-export { background: linear-gradient(135deg, #43e97b, #38f9d7); }
.ico-scan { background: linear-gradient(135deg, #fa709a, #fee140); }

.action-text {
  font-size: 24rpx;
  color: #333;
  text-align: center;
}
</style>
