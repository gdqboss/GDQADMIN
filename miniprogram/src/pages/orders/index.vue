<template>
  <view class="container">
    <view class="tabs">
      <view 
        class="tab-item" 
        :class="{ active: currentTab === index }"
        v-for="(tab, index) in tabs" 
        :key="index"
        @click="switchTab(index)"
      >
        {{ tab }}
      </view>
    </view>

    <view class="order-list">
      <view class="order-item card" v-for="item in orders" :key="item.id">
        <view class="order-header">
          <view class="order-no">订单号: {{ item.no }}</view>
          <view class="order-status" :class="item.statusClass">{{ item.status }}</view>
        </view>
        <view class="order-content">
          <view class="order-product">{{ item.product }}</view>
          <view class="order-amount">¥{{ item.amount }}</view>
        </view>
        <view class="order-footer">
          <view class="order-time">{{ item.time }}</view>
          <view class="order-actions">
            <button class="action-btn" v-if="item.status === '待发货'" @click="ship(item)">发货</button>
            <button class="action-btn secondary" @click="detail(item)">详情</button>
          </view>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref } from 'vue'

const tabs = ['全部', '待付款', '待发货', '已完成']
const currentTab = ref(0)
const orders = ref([
  { id: 1, no: '20240606001', product: '商品A x1', amount: '99.00', status: '待发货', statusClass: 'pending', time: '2024-06-06 10:30' },
  { id: 2, no: '20240606002', product: '商品B x2', amount: '398.00', status: '已完成', statusClass: 'completed', time: '2024-06-05 15:20' }
])

function switchTab(index) {
  currentTab.value = index
}

function ship(item) {
  uni.showToast({
    title: '发货成功',
    icon: 'success'
  })
  item.status = '已完成'
  item.statusClass = 'completed'
}

function detail(item) {
  uni.showToast({
    title: '查看详情',
    icon: 'none'
  })
}
</script>

<style scoped>
.tabs {
  display: flex;
  background: #fff;
  margin-bottom: 20rpx;
  border-radius: 16rpx;
}

.tab-item {
  flex: 1;
  text-align: center;
  padding: 24rpx 0;
  font-size: 28rpx;
  color: #8e8e93;
}

.tab-item.active {
  color: #007AFF;
  font-weight: 600;
  border-bottom: 4rpx solid #007AFF;
}

.order-list {
  display: flex;
  flex-direction: column;
  gap: 16rpx;
}

.order-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 16rpx;
}

.order-no {
  font-size: 26rpx;
  color: #8e8e93;
}

.order-status {
  font-size: 26rpx;
  font-weight: 600;
}

.order-status.pending {
  color: #ff9500;
}

.order-status.completed {
  color: #34c759;
}

.order-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16rpx 0;
  border-bottom: 1rpx solid #f0f0f0;
  margin-bottom: 16rpx;
}

.order-product {
  font-size: 28rpx;
}

.order-amount {
  font-size: 32rpx;
  font-weight: 700;
  color: #ff6b6b;
}

.order-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.order-time {
  font-size: 24rpx;
  color: #8e8e93;
}

.order-actions {
  display: flex;
  gap: 12rpx;
}

.action-btn {
  padding: 12rpx 24rpx;
  font-size: 24rpx;
  border-radius: 8rpx;
  border: none;
  background: #007AFF;
  color: #fff;
}

.action-btn.secondary {
  background: #f0f0f0;
  color: #333;
}
</style>
