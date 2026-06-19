<template>
  <view class="container">
    <view class="profile-card card">
      <view class="avatar">👤</view>
      <view class="profile-info">
        <view class="username">张三</view>
        <view class="user-role">管理员</view>
      </view>
    </view>

    <view class="card">
      <view class="menu-item" @click="navigate('settings')">
        <view class="menu-icon">⚙️</view>
        <view class="menu-text">设置</view>
        <view class="menu-arrow">›</view>
      </view>
      <view class="menu-item" @click="navigate('help')">
        <view class="menu-icon">❓</view>
        <view class="menu-text">帮助中心</view>
        <view class="menu-arrow">›</view>
      </view>
      <view class="menu-item" @click="navigate('about')">
        <view class="menu-icon">ℹ️</view>
        <view class="menu-text">关于我们</view>
        <view class="menu-arrow">›</view>
      </view>
    </view>

    <view class="logout-btn" @click="logout">
      退出登录
    </view>
  </view>
</template>

<script setup>
function navigate(type) {
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
.profile-card {
  display: flex;
  align-items: center;
  gap: 20rpx;
}

.avatar {
  font-size: 80rpx;
}

.profile-info {
  flex: 1;
}

.username {
  font-size: 32rpx;
  font-weight: 600;
  margin-bottom: 8rpx;
}

.user-role {
  font-size: 24rpx;
  color: #8e8e93;
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

.logout-btn {
  margin-top: 40rpx;
  padding: 24rpx;
  background: #fff;
  border-radius: 16rpx;
  text-align: center;
  font-size: 28rpx;
  color: #ff3b30;
}
</style>
