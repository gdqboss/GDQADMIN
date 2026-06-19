<template>
  <view class="orders-page">
    <view class="tabs-bar">
      <view
        class="tab-item"
        :class="{ active: currentTab === index }"
        v-for="(tab, index) in tabs"
        :key="index"
        @click="switchTab(index)"
      >
        <text>{{ tab }}</text>
        <view v-if="index > 0 && tabCounts[index - 1] > 0" class="tab-badge">{{ tabCounts[index - 1] }}</view>
      </view>
    </view>

    <view class="orders-list">
      <view class="order-card" v-for="order in filteredOrders" :key="order.id">
        <view class="order-header">
          <text class="order-no">订单号: {{ order.no }}</text>
          <text class="order-status" :class="'status-' + order.statusCode">{{ order.status }}</text>
        </view>

        <view class="order-items">
          <view class="order-item" v-for="(item, idx) in order.items" :key="idx">
            <view class="item-image">
              <text class="item-emoji">{{ item.emoji }}</text>
            </view>
            <view class="item-info">
              <text class="item-name">{{ item.name }}</text>
              <text class="item-spec">{{ item.spec }}</text>
            </view>
            <view class="item-price-qty">
              <text class="item-price">¥{{ item.price }}</text>
              <text class="item-qty">x{{ item.qty }}</text>
            </view>
          </view>
        </view>

        <view class="order-footer">
          <view class="order-total">
            <text class="total-label">合计:</text>
            <text class="total-amount">¥{{ order.total }}</text>
          </view>
          <view class="order-actions">
            <view class="action-btn" @click="viewDetail(order)">查看详情</view>
            <view class="action-btn action-primary" @click="handleAction(order)">{{ actionText(order) }}</view>
          </view>
        </view>
      </view>
    </view>

    <view class="empty-state" v-if="filteredOrders.length === 0">
      <text class="empty-icon">📋</text>
      <text class="empty-text">暂无订单</text>
    </view>

    <view class="fab-button" @click="newOrder">
      <text class="fab-icon">➕</text>
    </view>
  </view>
</template>

<script setup>
import { ref, computed } from 'vue'

const tabs = ref(['全部', '待付款', '待发货', '待收货', '已完成'])
const currentTab = ref(0)
const tabCounts = ref([2, 3, 1, 5])

const orders = ref([
  {
    id: 1,
    no: 'CMT202606190001',
    status: '待付款',
    statusCode: 'pending',
    total: '398.00',
    items: [
      { id: 1, name: '经典套装礼盒', spec: '500g', price: '199.00', qty: 2, emoji: '🎁' }
    ]
  },
  {
    id: 2,
    no: 'CMT202606190002',
    status: '待发货',
    statusCode: 'paid',
    total: '458.00',
    items: [
      { id: 2, name: '精选优质商品', spec: '1kg', price: '299.00', qty: 1, emoji: '💝' },
      { id: 3, name: '日用好物推荐', spec: '2kg', price: '89.00', qty: 2, emoji: '🏠' }
    ]
  },
  {
    id: 3,
    no: 'CMT202606180003',
    status: '待收货',
    statusCode: 'shipping',
    total: '159.00',
    items: [
      { id: 4, name: '时尚单品精选', spec: '500ml', price: '159.00', qty: 1, emoji: '✨' }
    ]
  },
  {
    id: 4,
    no: 'CMT202606170004',
    status: '已完成',
    statusCode: 'done',
    total: '727.00',
    items: [
      { id: 5, name: '人气爆款推荐', spec: '300g', price: '128.00', qty: 1, emoji: '🔥' },
      { id: 6, name: '限量珍藏系列', spec: '1.5kg', price: '599.00', qty: 1, emoji: '💎' }
    ]
  }
])

const filteredOrders = computed(() => {
  if (currentTab.value === 0) return orders.value
  const statusMap = {
    1: 'pending',
    2: 'paid',
    3: 'shipping',
    4: 'done'
  }
  return orders.value.filter(o => o.statusCode === statusMap[currentTab.value])
})

function switchTab(index) {
  currentTab.value = index
}

function actionText(order) {
  if (order.statusCode === 'pending') return '立即付款'
  if (order.statusCode === 'paid') return '去发货'
  if (order.statusCode === 'shipping') return '确认收货'
  return '再次购买'
}

function viewDetail(order) {
  uni.showToast({ title: '查看订单详情', icon: 'none' })
}

function handleAction(order) {
  uni.showToast({ title: actionText(order), icon: 'none' })
}

function newOrder() {
  uni.showToast({ title: '新建订单', icon: 'none' })
}
</script>

<style scoped>
.orders-page {
  min-height: 100vh;
  background: #f5f7fa;
  padding-bottom: 80rpx;
}

.tabs-bar {
  display: flex;
  background: #fff;
  padding: 0 16rpx;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.04);
}

.tab-item {
  flex: 1;
  position: relative;
  text-align: center;
  padding: 28rpx 0;
  font-size: 28rpx;
  color: #666;
}

.tab-item.active {
  color: #667eea;
  font-weight: 600;
}

.tab-item.active::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 48rpx;
  height: 4rpx;
  background: linear-gradient(135deg, #667eea, #764ba2);
  border-radius: 2rpx;
}

.tab-badge {
  position: absolute;
  top: 16rpx;
  right: 24rpx;
  background: #f5576c;
  color: #fff;
  font-size: 18rpx;
  padding: 2rpx 10rpx;
  border-radius: 16rpx;
  min-width: 24rpx;
}

.orders-list {
  padding: 24rpx 32rpx;
}

.order-card {
  background: #fff;
  border-radius: 20rpx;
  margin-bottom: 24rpx;
  overflow: hidden;
}

.order-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24rpx 28rpx;
  border-bottom: 1rpx solid #f0f0f0;
}

.order-no {
  font-size: 26rpx;
  color: #666;
}

.order-status {
  font-size: 26rpx;
  font-weight: 500;
}

.status-pending { color: #f5576c; }
.status-paid { color: #fa709a; }
.status-shipping { color: #4facfe; }
.status-done { color: #43e97b; }

.order-items {
  padding: 16rpx 28rpx;
}

.order-item {
  display: flex;
  align-items: center;
  padding: 16rpx 0;
}

.item-image {
  width: 120rpx;
  height: 120rpx;
  background: linear-gradient(135deg, #f5f7fa, #e8ecf3);
  border-radius: 16rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 20rpx;
}

.item-emoji {
  font-size: 60rpx;
}

.item-info {
  flex: 1;
}

.item-name {
  display: block;
  font-size: 28rpx;
  color: #1a1a1a;
  margin-bottom: 8rpx;
}

.item-spec {
  display: block;
  font-size: 22rpx;
  color: #999;
}

.item-price-qty {
  text-align: right;
}

.item-price {
  display: block;
  font-size: 28rpx;
  color: #1a1a1a;
  font-weight: 500;
  margin-bottom: 8rpx;
}

.item-qty {
  display: block;
  font-size: 22rpx;
  color: #999;
}

.order-footer {
  padding: 20rpx 28rpx;
  border-top: 1rpx solid #f0f0f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.order-total {
  display: flex;
  align-items: center;
  gap: 8rpx;
}

.total-label {
  font-size: 26rpx;
  color: #666;
}

.total-amount {
  font-size: 32rpx;
  color: #f5576c;
  font-weight: 700;
}

.order-actions {
  display: flex;
  gap: 16rpx;
}

.action-btn {
  padding: 14rpx 28rpx;
  background: #f5f7fa;
  color: #666;
  border-radius: 32rpx;
  font-size: 26rpx;
}

.action-btn.action-primary {
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: #fff;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 120rpx 0;
}

.empty-icon {
  font-size: 100rpx;
  margin-bottom: 20rpx;
}

.empty-text {
  font-size: 32rpx;
  color: #999;
}

.fab-button {
  position: fixed;
  right: 40rpx;
  bottom: 80rpx;
  width: 110rpx;
  height: 110rpx;
  background: linear-gradient(135deg, #667eea, #764ba2);
  border-radius: 55rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8rpx 32rpx rgba(102, 126, 234, 0.4);
}

.fab-icon {
  font-size: 48rpx;
  color: #fff;
}
</style>
