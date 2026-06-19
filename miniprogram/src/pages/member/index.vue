<template>
  <view class="member-page">
    <view class="profile-card">
      <view class="profile-bg"></view>
      <view class="profile-content">
        <view class="avatar">👤</view>
        <view class="profile-info">
          <text class="username">{{ userInfo.name }}</text>
          <text class="user-role">{{ userInfo.role }}</text>
        </view>
      </view>
    </view>

    <view class="stats-row">
      <view class="stat-item">
        <text class="stat-num">{{ userInfo.orders }}</text>
        <text class="stat-text">订单</text>
      </view>
      <view class="stat-divider"></view>
      <view class="stat-item">
        <text class="stat-num">{{ userInfo.points }}</text>
        <text class="stat-text">积分</text>
      </view>
      <view class="stat-divider"></view>
      <view class="stat-item">
        <text class="stat-num">{{ userInfo.favorite }}</text>
        <text class="stat-text">收藏</text>
      </view>
    </view>

    <view class="section-title">
      <text class="title-text">我的订单</text>
      <text class="title-more" @click="showTip">全部订单 ›</text>
    </view>

    <view class="order-shortcuts">
      <view class="order-item" @click="showTip">
        <view class="order-icon">💳</view>
        <text class="order-label">待付款</text>
      </view>
      <view class="order-item" @click="goToPage('/pages/orders/index')">
        <view class="order-icon">📦</view>
        <text class="order-label">待发货</text>
      </view>
      <view class="order-item" @click="showTip">
        <view class="order-icon">🚚</view>
        <text class="order-label">待收货</text>
      </view>
      <view class="order-item" @click="showTip">
        <view class="order-icon">⭐</view>
        <text class="order-label">待评价</text>
      </view>
    </view>

    <view class="section-title">
      <text class="title-text">常用服务</text>
    </view>

    <view class="service-list">
      <view class="service-item" @click="showTip">
        <view class="service-icon">📍</view>
        <text class="service-text">收货地址</text>
      </view>
      <view class="service-item" @click="showTip">
        <view class="service-icon">💬</view>
        <text class="service-text">消息通知</text>
      </view>
      <view class="service-item" @click="showTip">
        <view class="service-icon">🎫</view>
        <text class="service-text">优惠券</text>
      </view>
      <view class="service-item" @click="showTip">
        <view class="service-icon">❓</view>
        <text class="service-text">帮助中心</text>
      </view>
      <view class="service-item" @click="showTip">
        <view class="service-icon">⚙️</view>
        <text class="service-text">账号设置</text>
      </view>
      <view class="service-item" @click="showTip">
        <view class="service-icon">🔔</view>
        <text class="service-text">通知提醒</text>
      </view>
      <view class="service-item" @click="showTip">
        <view class="service-icon">📋</view>
        <text class="service-text">意见反馈</text>
      </view>
      <view class="service-item" @click="showTip">
        <view class="service-icon">📞</view>
        <text class="service-text">联系客服</text>
      </view>
    </view>

    <view class="section-title">
      <text class="title-text">账户与安全</text>
    </view>

    <view class="account-list">
      <view class="account-item" @click="showTip">
        <view class="account-icon">🔒</view>
        <text class="account-text">修改密码</text>
        <text class="account-arrow">›</text>
      </view>
      <view class="account-item" @click="showTip">
        <view class="account-icon">📱</view>
        <text class="account-text">绑定手机</text>
        <text class="account-arrow">›</text>
      </view>
      <view class="account-item" @click="logout">
        <view class="account-icon">🚪</view>
        <text class="account-text">退出登录</text>
        <text class="account-arrow">›</text>
      </view>
    </view>

    <view class="footer-text">
      <text>彩美特智慧管理系统 v1.0.0</text>
    </view>
  </view>
</template>

<script setup>
import { ref } from 'vue'

const tabBarPages = ['/pages/index/index', '/pages/dashboard/index', '/pages/member/index']

const userInfo = ref({
  name: '管理员',
  role: '彩美特 · 超级管理员',
  orders: '128',
  points: '2,380',
  favorite: '15'
})

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

function logout() {
  uni.showModal({
    title: '提示',
    content: '确定要退出登录吗？',
    success: (res) => {
      if (res.confirm) {
        uni.showToast({
          title: '已退出',
          icon: 'success'
        })
      }
    }
  })
}
</script>

<style scoped>
.member-page {
  min-height: 100vh;
  background: #f5f7fa;
  padding-bottom: 40rpx;
}

.profile-card {
  position: relative;
  padding-top: 80rpx;
  overflow: hidden;
}

.profile-bg {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 300rpx;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.profile-content {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  padding: 40rpx 32rpx;
}

.avatar {
  width: 140rpx;
  height: 140rpx;
  border-radius: 70rpx;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 80rpx;
  box-shadow: 0 8rpx 32rpx rgba(0, 0, 0, 0.15);
}

.profile-info {
  margin-left: 28rpx;
}

.username {
  display: block;
  font-size: 36rpx;
  font-weight: 600;
  color: #fff;
  margin-bottom: 8rpx;
}

.user-role {
  display: block;
  font-size: 24rpx;
  color: rgba(255, 255, 255, 0.85);
}

.stats-row {
  margin: 0 32rpx;
  background: #fff;
  border-radius: 24rpx;
  padding: 32rpx;
  display: flex;
  align-items: center;
  margin-top: -60rpx;
  box-shadow: 0 4rpx 24rpx rgba(0, 0, 0, 0.08);
  position: relative;
  z-index: 3;
}

.stat-item {
  flex: 1;
  text-align: center;
}

.stat-num {
  display: block;
  font-size: 40rpx;
  font-weight: 700;
  color: #1a1a1a;
  margin-bottom: 8rpx;
}

.stat-text {
  display: block;
  font-size: 24rpx;
  color: #999;
}

.stat-divider {
  width: 2rpx;
  height: 60rpx;
  background: #f0f0f0;
}

.section-title {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 32rpx 32rpx 16rpx;
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

.order-shortcuts {
  margin: 0 32rpx;
  background: #fff;
  border-radius: 24rpx;
  padding: 32rpx 0;
  display: flex;
}

.order-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.order-icon {
  width: 80rpx;
  height: 80rpx;
  background: linear-gradient(135deg, #f5f7fa, #e8ecf3);
  border-radius: 20rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 40rpx;
  margin-bottom: 12rpx;
}

.order-label {
  font-size: 24rpx;
  color: #333;
}

.service-list {
  margin: 0 32rpx;
  background: #fff;
  border-radius: 24rpx;
  padding: 32rpx 0;
  display: flex;
  flex-wrap: wrap;
}

.service-item {
  width: 25%;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 20rpx 0;
}

.service-icon {
  width: 80rpx;
  height: 80rpx;
  background: linear-gradient(135deg, #f5f7fa, #e8ecf3);
  border-radius: 20rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 40rpx;
  margin-bottom: 12rpx;
}

.service-text {
  font-size: 24rpx;
  color: #333;
}

.account-list {
  margin: 0 32rpx;
  background: #fff;
  border-radius: 24rpx;
  overflow: hidden;
}

.account-item {
  display: flex;
  align-items: center;
  padding: 28rpx 32rpx;
  border-bottom: 1rpx solid #f0f0f0;
}

.account-item:last-child {
  border-bottom: none;
}

.account-icon {
  width: 56rpx;
  height: 56rpx;
  background: #f5f7fa;
  border-radius: 14rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28rpx;
  margin-right: 20rpx;
}

.account-text {
  flex: 1;
  font-size: 28rpx;
  color: #333;
}

.account-arrow {
  font-size: 36rpx;
  color: #ccc;
}

.footer-text {
  text-align: center;
  padding: 40rpx 0 20rpx;
  font-size: 22rpx;
  color: #bbb;
}
</style>
