<template>
  <view class="container">
    <view class="manage-container">
      <view class="header">
        <view class="title">二维码管理</view>
        <button class="add-btn" @click="generateQrcode">生成二维码</button>
      </view>
      
      <view class="search-bar">
        <input 
          type="text" 
          class="search-input" 
          placeholder="输入唯一码、客户姓名或电话搜索" 
          v-model="searchKeyword"
          @input="handleSearch"
        >
        <button class="search-btn" @click="handleSearch">搜索</button>
      </view>
      
      <view class="qrcode-list">
        <view 
          v-for="qrcode in qrcodeList" 
          :key="qrcode.id" 
          class="qrcode-item"
          @click="viewDetail(qrcode)"
        >
          <view class="item-header">
            <view class="qrcode-code">{{ qrcode.code }}</view>
            <view class="qrcode-status" :class="'status-' + qrcode.status">
              {{ getStatusText(qrcode.status) }}
            </view>
          </view>
          <view class="item-content">
            <view class="product-info">
              <text class="label">商品：</text>
              <text class="value">{{ qrcode.product_info?.name || '未知商品' }}</text>
            </view>
            <view class="customer-info" v-if="qrcode.customer_name">
              <text class="label">客户：</text>
              <text class="value">{{ qrcode.customer_name }} {{ qrcode.customer_phone }}</text>
            </view>
            <view class="scan-count">
              <text class="label">扫码次数：</text>
              <text class="value">{{ qrcode.scan_count }}</text>
            </view>
          </view>
          <view class="item-footer">
            <text class="create-time">{{ formatTime(qrcode.create_time) }}</text>
            <view class="item-actions">
              <button class="action-btn small" @click.stop="printQrcode(qrcode)">打印</button>
              <button class="action-btn small" @click.stop="toggleStatus(qrcode)">
                {{ qrcode.status == 1 ? '禁用' : '启用' }}
              </button>
            </view>
          </view>
        </view>
      </view>
      
      <!-- 分页 -->
      <view class="pagination" v-if="total > 0">
        <button class="page-btn" @click="prevPage" :disabled="currentPage <= 1">上一页</button>
        <view class="page-info">
          {{ currentPage }} / {{ totalPages }}
        </view>
        <button class="page-btn" @click="nextPage" :disabled="currentPage >= totalPages">下一页</button>
      </view>
      
      <!-- 空状态 -->
      <view class="empty-state" v-if="qrcodeList.length === 0">
        <image src="/static/images/empty.png" class="empty-img"></image>
        <text class="empty-text">暂无二维码数据</text>
        <button class="empty-btn" @click="generateQrcode">生成第一个二维码</button>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      qrcodeList: [],
      searchKeyword: '',
      currentPage: 1,
      pageSize: 10,
      total: 0,
      totalPages: 0
    };
  },
  onLoad() {
    this.loadQrcodeList();
  },
  methods: {
    loadQrcodeList() {
      uni.showLoading({
        title: '加载中...'
      });
      
      uni.request({
        url: `${this.$baseUrl}/api/qrcode/getQrcodeList`,
        method: 'POST',
        data: {
          keyword: this.searchKeyword,
          page: this.currentPage,
          limit: this.pageSize
        },
        success: (res) => {
          uni.hideLoading();
          if (res.data.status === 1) {
            this.qrcodeList = res.data.data.list || [];
            this.total = res.data.data.total || 0;
            this.totalPages = Math.ceil(this.total / this.pageSize);
          } else {
            uni.showToast({
              title: res.data.msg || '获取二维码列表失败',
              icon: 'none'
            });
          }
        },
        fail: (err) => {
          uni.hideLoading();
          console.error('获取二维码列表失败:', err);
          uni.showToast({
            title: '网络错误，请重试',
            icon: 'none'
          });
        }
      });
    },
    handleSearch() {
      this.currentPage = 1;
      this.loadQrcodeList();
    },
    prevPage() {
      if (this.currentPage > 1) {
        this.currentPage--;
        this.loadQrcodeList();
      }
    },
    nextPage() {
      if (this.currentPage < this.totalPages) {
        this.currentPage++;
        this.loadQrcodeList();
      }
    },
    getStatusText(status) {
      const statusMap = {
        1: '启用',
        0: '禁用',
        2: '已使用'
      };
      return statusMap[status] || '未知状态';
    },
    formatTime(timestamp) {
      if (!timestamp) return '';
      return new Date(timestamp * 1000).toLocaleString();
    },
    viewDetail(qrcode) {
      uni.navigateTo({
        url: `/pagesA/qrcode/detail?id=${qrcode.id}`
      });
    },
    generateQrcode() {
      uni.navigateTo({
        url: `/pagesA/qrcode/generate`
      });
    },
    printQrcode(qrcode) {
      uni.showModal({
        title: '打印二维码',
        content: `确定要打印二维码 ${qrcode.code} 吗？`,
        success: (res) => {
          if (res.confirm) {
            // 调用打印API
            uni.request({
              url: `${this.$baseUrl}/api/qrcode/print`,
              method: 'POST',
              data: {
                qrcode_id: qrcode.id
              },
              success: (res) => {
                if (res.data.status === 1) {
                  uni.showToast({
                    title: '打印请求已发送',
                    icon: 'success'
                  });
                } else {
                  uni.showToast({
                    title: res.data.msg || '打印失败',
                    icon: 'none'
                  });
                }
              },
              fail: (err) => {
                console.error('打印失败:', err);
                uni.showToast({
                  title: '网络错误，请重试',
                  icon: 'none'
                });
              }
            });
          }
        }
      });
    },
    toggleStatus(qrcode) {
      const newStatus = qrcode.status === 1 ? 0 : 1;
      const statusText = newStatus === 1 ? '启用' : '禁用';
      
      uni.showModal({
        title: `${statusText}二维码`,
        content: `确定要${statusText}二维码 ${qrcode.code} 吗？`,
        success: (res) => {
          if (res.confirm) {
            uni.request({
              url: `${this.$baseUrl}/api/qrcode/updateStatus`,
              method: 'POST',
              data: {
                id: qrcode.id,
                status: newStatus
              },
              success: (res) => {
                if (res.data.status === 1) {
                  uni.showToast({
                    title: `${statusText}成功`,
                    icon: 'success'
                  });
                  // 更新本地数据
                  qrcode.status = newStatus;
                } else {
                  uni.showToast({
                    title: res.data.msg || `${statusText}失败`,
                    icon: 'none'
                  });
                }
              },
              fail: (err) => {
                console.error(`${statusText}失败:`, err);
                uni.showToast({
                  title: '网络错误，请重试',
                  icon: 'none'
                });
              }
            });
          }
        }
      });
    }
  }
};
</script>

<style scoped>
.container {
  padding: 20rpx;
  background-color: #f5f5f5;
  min-height: 100vh;
}

.manage-container {
  background-color: #fff;
  border-radius: 16rpx;
  padding: 30rpx;
  box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.1);
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30rpx;
}

.title {
  font-size: 36rpx;
  font-weight: bold;
  color: #333;
}

.add-btn {
  height: 70rpx;
  background-color: #007aff;
  color: #fff;
  font-size: 28rpx;
  border-radius: 35rpx;
  padding: 0 30rpx;
}

.search-bar {
  display: flex;
  margin-bottom: 30rpx;
}

.search-input {
  flex: 1;
  height: 70rpx;
  border: 2rpx solid #e0e0e0;
  border-radius: 35rpx 0 0 35rpx;
  padding: 0 30rpx;
  font-size: 28rpx;
  background-color: #f9f9f9;
}

.search-btn {
  height: 70rpx;
  background-color: #007aff;
  color: #fff;
  font-size: 28rpx;
  border-radius: 0 35rpx 35rpx 0;
  padding: 0 30rpx;
  border: none;
}

.qrcode-list {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
}

.qrcode-item {
  background-color: #fafafa;
  border-radius: 12rpx;
  padding: 25rpx;
  border: 2rpx solid #e0e0e0;
  transition: all 0.3s ease;
}

.qrcode-item:active {
  background-color: #f0f0f0;
  transform: scale(0.98);
}

.item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20rpx;
}

.qrcode-code {
  font-size: 32rpx;
  font-weight: bold;
  color: #333;
}

.qrcode-status {
  padding: 8rpx 16rpx;
  border-radius: 20rpx;
  font-size: 24rpx;
  font-weight: bold;
}

.status-1 {
  background-color: #52c41a;
  color: #fff;
}

.status-0 {
  background-color: #d9d9d9;
  color: #333;
}

.status-2 {
  background-color: #1890ff;
  color: #fff;
}

.item-content {
  display: flex;
  flex-direction: column;
  gap: 12rpx;
  margin-bottom: 20rpx;
}

.product-info,
.customer-info,
.scan-count {
  display: flex;
  font-size: 28rpx;
}

.label {
  color: #666;
  width: 120rpx;
}

.value {
  color: #333;
  flex: 1;
}

.item-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 2rpx solid #e0e0e0;
  padding-top: 20rpx;
}

.create-time {
  font-size: 24rpx;
  color: #999;
}

.item-actions {
  display: flex;
  gap: 10rpx;
}

.action-btn {
  height: 50rpx;
  font-size: 24rpx;
  border-radius: 25rpx;
  padding: 0 20rpx;
}

.action-btn.small {
  min-width: 100rpx;
  height: 50rpx;
  font-size: 24rpx;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-top: 30rpx;
  gap: 20rpx;
}

.page-btn {
  height: 60rpx;
  min-width: 120rpx;
  font-size: 28rpx;
  border-radius: 30rpx;
  background-color: #f0f0f0;
  color: #333;
}

.page-btn:disabled {
  opacity: 0.5;
}

.page-info {
  font-size: 28rpx;
  color: #666;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 100rpx 0;
}

.empty-img {
  width: 200rpx;
  height: 200rpx;
  margin-bottom: 30rpx;
  opacity: 0.5;
}

.empty-text {
  font-size: 28rpx;
  color: #999;
  margin-bottom: 30rpx;
}

.empty-btn {
  height: 70rpx;
  background-color: #007aff;
  color: #fff;
  font-size: 28rpx;
  border-radius: 35rpx;
  padding: 0 40rpx;
}
</style>