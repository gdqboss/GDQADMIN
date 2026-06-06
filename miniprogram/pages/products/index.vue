<template>
  <view class="container">
    <view class="search-bar">
      <input class="search-input" placeholder="搜索商品" v-model="keyword" />
      <button class="search-btn" @click="search">搜索</button>
    </view>

    <view class="product-list">
      <view class="product-item card" v-for="item in products" :key="item.id" @click="viewDetail(item)">
        <view class="product-info">
          <view class="product-name">{{ item.name }}</view>
          <view class="product-spec">{{ item.spec }}</view>
          <view class="product-price">¥{{ item.price }}</view>
        </view>
        <view class="product-stock">库存: {{ item.stock }}</view>
      </view>
    </view>

    <view class="empty" v-if="products.length === 0">
      <view class="empty-icon">📦</view>
      <view class="empty-text">暂无商品</view>
    </view>
  </view>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const keyword = ref('')
const products = ref([])

onMounted(() => {
  loadProducts()
})

function loadProducts() {
  products.value = [
    { id: 1, name: '商品A', spec: '规格: 500g', price: '99.00', stock: 100 },
    { id: 2, name: '商品B', spec: '规格: 1kg', price: '199.00', stock: 50 },
    { id: 3, name: '商品C', spec: '规格: 2kg', price: '299.00', stock: 25 }
  ]
}

function search() {
  uni.showToast({
    title: '搜索功能',
    icon: 'none'
  })
}

function viewDetail(item) {
  uni.showToast({
    title: item.name,
    icon: 'none'
  })
}
</script>

<style scoped>
.search-bar {
  display: flex;
  gap: 16rpx;
  margin-bottom: 20rpx;
}

.search-input {
  flex: 1;
  background: #fff;
  padding: 16rpx 24rpx;
  border-radius: 8rpx;
  font-size: 28rpx;
}

.search-btn {
  padding: 16rpx 32rpx;
  background: #007AFF;
  color: #fff;
  border: none;
  border-radius: 8rpx;
  font-size: 28rpx;
}

.product-list {
  display: flex;
  flex-direction: column;
  gap: 16rpx;
}

.product-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.product-info {
  flex: 1;
}

.product-name {
  font-size: 30rpx;
  font-weight: 600;
  margin-bottom: 8rpx;
}

.product-spec {
  font-size: 24rpx;
  color: #8e8e93;
  margin-bottom: 8rpx;
}

.product-price {
  font-size: 32rpx;
  font-weight: 700;
  color: #ff6b6b;
}

.product-stock {
  font-size: 24rpx;
  color: #8e8e93;
}

.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 120rpx 0;
}

.empty-icon {
  font-size: 80rpx;
  margin-bottom: 20rpx;
}

.empty-text {
  font-size: 28rpx;
  color: #8e8e93;
}
</style>
