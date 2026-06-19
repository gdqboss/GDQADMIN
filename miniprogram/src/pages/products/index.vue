<template>
  <view class="products-page">
    <view class="search-bar">
      <view class="search-box">
        <text class="search-icon">🔍</text>
        <input class="search-input" placeholder="搜索商品名称/编号" v-model="keyword" />
      </view>
    </view>

    <view class="category-bar">
      <view
        class="category-item"
        :class="{ active: currentCategory === index }"
        v-for="(cat, index) in categories"
        :key="index"
        @click="switchCategory(index)"
      >
        <text>{{ cat }}</text>
      </view>
    </view>

    <view class="products-list">
      <view class="product-card" v-for="item in filteredProducts" :key="item.id" @click="viewDetail(item)">
        <view class="product-image">
          <text class="product-emoji">{{ item.emoji }}</text>
          <view v-if="item.tag" class="product-tag">{{ item.tag }}</view>
        </view>
        <view class="product-info">
          <text class="product-name">{{ item.name }}</text>
          <text class="product-spec">{{ item.spec }}</text>
          <view class="product-bottom">
            <view class="price-area">
              <text class="product-price">¥{{ item.price }}</text>
              <text class="product-original" v-if="item.original">¥{{ item.original }}</text>
            </view>
            <text class="product-stock">库存: {{ item.stock }}</text>
          </view>
          <view class="product-actions">
            <view class="action-btn-mini" @click.stop="editProduct(item)">编辑</view>
            <view class="action-btn-main" @click.stop="handleProduct(item)">操作</view>
          </view>
        </view>
      </view>
    </view>

    <view class="empty-state" v-if="filteredProducts.length === 0">
      <text class="empty-icon">📦</text>
      <text class="empty-text">暂无商品</text>
      <text class="empty-hint">点击下方按钮添加商品</text>
    </view>

    <view class="fab-button" @click="addProduct">
      <text class="fab-icon">➕</text>
    </view>
  </view>
</template>

<script setup>
import { ref, computed } from 'vue'

const keyword = ref('')
const currentCategory = ref(0)
const categories = ref(['全部', '热销', '新品', '特价', '缺货'])

const products = ref([
  { id: 1, name: '经典套装礼盒', spec: '规格: 500g', price: '199.00', original: '299.00', stock: 100, emoji: '🎁', tag: '热销' },
  { id: 2, name: '精选优质商品', spec: '规格: 1kg', price: '299.00', original: '399.00', stock: 50, emoji: '💝', tag: '新品' },
  { id: 3, name: '日用好物推荐', spec: '规格: 2kg', price: '89.00', original: '129.00', stock: 234, emoji: '🏠' },
  { id: 4, name: '时尚单品精选', spec: '规格: 500ml', price: '159.00', original: '199.00', stock: 97, emoji: '✨', tag: '特价' },
  { id: 5, name: '人气爆款推荐', spec: '规格: 300g', price: '128.00', original: '168.00', stock: 8, emoji: '🔥', tag: '热销' },
  { id: 6, name: '限量珍藏系列', spec: '规格: 1.5kg', price: '599.00', original: '799.00', stock: 20, emoji: '💎', tag: '限量' }
])

const filteredProducts = computed(() => {
  let list = products.value
  if (keyword.value) {
    list = list.filter(item => item.name.includes(keyword.value))
  }
  if (currentCategory.value === 1) {
    list = list.filter(item => item.tag === '热销')
  } else if (currentCategory.value === 2) {
    list = list.filter(item => item.tag === '新品')
  } else if (currentCategory.value === 3) {
    list = list.filter(item => item.tag === '特价' || item.tag === '限量')
  } else if (currentCategory.value === 4) {
    list = list.filter(item => item.stock < 20)
  }
  return list
})

function switchCategory(index) {
  currentCategory.value = index
}

function viewDetail(item) {
  uni.showToast({ title: '查看 ' + item.name, icon: 'none' })
}

function editProduct(item) {
  uni.showToast({ title: '编辑商品', icon: 'none' })
}

function handleProduct(item) {
  uni.showToast({ title: '操作按钮', icon: 'none' })
}

function addProduct() {
  uni.showToast({ title: '添加新商品', icon: 'none' })
}
</script>

<style scoped>
.products-page {
  min-height: 100vh;
  background: #f5f7fa;
  padding-bottom: 80rpx;
}

.search-bar {
  padding: 24rpx 32rpx;
  background: #fff;
}

.search-box {
  display: flex;
  align-items: center;
  background: #f0f2f5;
  padding: 20rpx 28rpx;
  border-radius: 40rpx;
}

.search-icon {
  font-size: 28rpx;
  margin-right: 12rpx;
}

.search-input {
  flex: 1;
  font-size: 28rpx;
  color: #333;
}

.category-bar {
  display: flex;
  background: #fff;
  padding: 16rpx 32rpx;
  gap: 16rpx;
  overflow-x: auto;
  border-top: 2rpx solid #f0f0f0;
}

.category-item {
  padding: 16rpx 32rpx;
  background: #f5f7fa;
  border-radius: 32rpx;
  font-size: 26rpx;
  color: #666;
  white-space: nowrap;
}

.category-item.active {
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: #fff;
}

.products-list {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20rpx;
  padding: 24rpx 32rpx;
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
  padding: 6rpx 16rpx;
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

.product-spec {
  display: block;
  font-size: 22rpx;
  color: #999;
  margin-bottom: 16rpx;
}

.product-bottom {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 16rpx;
}

.price-area {
  display: flex;
  flex-direction: column;
}

.product-price {
  font-size: 32rpx;
  font-weight: 700;
  color: #f5576c;
}

.product-original {
  font-size: 20rpx;
  color: #bbb;
  text-decoration: line-through;
}

.product-stock {
  font-size: 22rpx;
  color: #999;
}

.product-actions {
  display: flex;
  gap: 12rpx;
}

.action-btn-mini {
  flex: 1;
  padding: 14rpx 0;
  background: #f5f7fa;
  color: #666;
  border-radius: 12rpx;
  font-size: 24rpx;
  text-align: center;
}

.action-btn-main {
  flex: 1;
  padding: 14rpx 0;
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: #fff;
  border-radius: 12rpx;
  font-size: 24rpx;
  text-align: center;
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
  color: #333;
  margin-bottom: 12rpx;
}

.empty-hint {
  font-size: 24rpx;
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
