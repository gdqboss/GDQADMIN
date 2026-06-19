<template>
  <view class="container">
    <view class="card">
      <view class="card-title">欢迎使用彩美特</view>
      <view class="text-secondary">智慧管理系统 - 微信小程序版</view>
    </view>

    <view class="card">
      <view class="card-title">快捷入口</view>
      <view class="grid">
        <view class="grid-item" @click="goToPage('/pages/dashboard/index')">
          <view class="icon">📊</view>
          <view class="label">工作台</view>
        </view>
        <view class="grid-item" @click="goToPage('/pages/products/index')">
          <view class="icon">📦</view>
          <view class="label">商品</view>
        </view>
        <view class="grid-item" @click="goToPage('/pages/orders/index')">
          <view class="icon">📋</view>
          <view class="label">订单</view>
        </view>
        <view class="grid-item" @click="goToPage('/pages/member/index')">
          <view class="icon">👤</view>
          <view class="label">会员</view>
        </view>
      </view>
    </view>

    <view class="card">
      <view class="card-title">今日数据</view>
      <view class="stats">
        <view class="stat-item">
          <view class="stat-value">{{ stats.sales }}</view>
          <view class="stat-label">销售额</view>
        </view>
        <view class="stat-item">
          <view class="stat-value">{{ stats.orders }}</view>
          <view class="stat-label">订单数</view>
        </view>
        <view class="stat-item">
          <view class="stat-value">{{ stats.customers }}</view>
          <view class="stat-label">新增客户</view>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const stats = ref({
  sales: '¥0',
  orders: '0',
  customers: '0'
})

const tabBarPages = ['/pages/index/index', '/pages/dashboard/index', '/pages/member/index']

function goToPage(url) {
  if (tabBarPages.includes(url)) {
    uni.switchTab({ url })
  } else {
    uni.navigateTo({ url })
  }
}

onMounted(() => {
  loadStats()
})

function loadStats() {
  stats.value = {
    sales: '¥12,580',
    orders: '38',
    customers: '12'
  }
}
</script>

<style scoped>
.grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20rpx;
}

.grid-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 24rpx 0;
}

.icon {
  font-size: 48rpx;
  margin-bottom: 12rpx;
}

.label {
  font-size: 24rpx;
  color: #333;
}

.stats {
  display: flex;
  justify-content: space-around;
}

.stat-item {
  text-align: center;
}

.stat-value {
  font-size: 40rpx;
  font-weight: 700;
  color: #007AFF;
  margin-bottom: 8rpx;
}

.stat-label {
  font-size: 24rpx;
  color: #8e8e93;
}
</style>
