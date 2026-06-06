<template>
  <view class="container">
    <view class="card">
      <view class="card-title">工作台</view>
      <view class="menu-list">
        <view class="menu-item" @click="handleMenu('products')">
          <view class="menu-icon">📦</view>
          <view class="menu-text">商品管理</view>
          <view class="menu-arrow">›</view>
        </view>
        <view class="menu-item" @click="handleMenu('orders')">
          <view class="menu-icon">📋</view>
          <view class="menu-text">订单管理</view>
          <view class="menu-arrow">›</view>
        </view>
        <view class="menu-item" @click="handleMenu('inventory')">
          <view class="menu-icon">📊</view>
          <view class="menu-text">库存管理</view>
          <view class="menu-arrow">›</view>
        </view>
        <view class="menu-item" @click="handleMenu('finance')">
          <view class="menu-icon">💰</view>
          <view class="menu-text">财务管理</view>
          <view class="menu-arrow">›</view>
        </view>
        <view class="menu-item" @click="handleMenu('members')">
          <view class="menu-icon">👥</view>
          <view class="menu-text">会员管理</view>
          <view class="menu-arrow">›</view>
        </view>
        <view class="menu-item" @click="handleMenu('report')">
          <view class="menu-icon">📈</view>
          <view class="menu-text">数据报表</view>
          <view class="menu-arrow">›</view>
        </view>
      </view>
    </view>

    <view class="card">
      <view class="card-title">待办事项</view>
      <view class="todo-list">
        <view class="todo-item">
          <view class="todo-title">待发货订单 (5)</view>
          <view class="todo-time">2小时前</view>
        </view>
        <view class="todo-item">
          <view class="todo-title">库存预警商品 (3)</view>
          <view class="todo-time">1天前</view>
        </view>
        <view class="todo-item">
          <view class="todo-title">待审批申请 (2)</view>
          <view class="todo-time">3天前</view>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
function handleMenu(type) {
  let url = ''
  switch (type) {
    case 'products':
      url = '/pages/products/index'
      break
    case 'orders':
      url = '/pages/orders/index'
      break
    default:
      uni.showToast({
        title: '功能开发中',
        icon: 'none'
      })
      return
  }
  uni.navigateTo({ url })
}
</script>

<style scoped>
.menu-list {
  display: flex;
  flex-direction: column;
}

.menu-item {
  display: flex;
  align-items: center;
  padding: 24rpx 0;
  border-bottom: 1rpx solid #f0f0f0;
}

.menu-item:last-child {
  border-bottom: none;
}

.menu-icon {
  font-size: 36rpx;
  margin-right: 20rpx;
}

.menu-text {
  flex: 1;
  font-size: 28rpx;
}

.menu-arrow {
  font-size: 32rpx;
  color: #c7c7cc;
}

.todo-list {
  display: flex;
  flex-direction: column;
  gap: 16rpx;
}

.todo-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16rpx;
  background: #f8f8f8;
  border-radius: 8rpx;
}

.todo-title {
  font-size: 28rpx;
}

.todo-time {
  font-size: 24rpx;
  color: #8e8e93;
}
</style>
