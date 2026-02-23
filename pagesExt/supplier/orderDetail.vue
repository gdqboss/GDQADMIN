<template>
  <view class="order-detail-container">
    <!-- 顶部导航 -->
    <view class="header">
      <view class="back-btn" @click="goBack">←</view>
      <view class="title">订单详情</view>
      <view class="placeholder"></view>
    </view>
    
    <!-- 订单状态 --</view>
    <view class="status-section" v-if="orderInfo">
      <view class="status-icon" :class="getStatusIcon(orderInfo.status)">
        {{ getStatusIconText(orderInfo.status) }}
      </view>
      <view class="status-text" :class="getStatusTextClass(orderInfo.status)">
        {{ getStatusText(orderInfo.status) }}
      </view>
      <view class="status-desc" v-if="orderInfo.status >= 2">
        {{ getStatusDescription(orderInfo.status) }}
      </view>
    </view>
    
    <!-- 物流信息 --</view>
    <view class="express-section" v-if="orderInfo && orderInfo.status >= 2 && orderInfo.express_no">
      <view class="section-title">物流信息</view>
      <view class="express-info">
        <view class="express-item">
          <view class="express-label">物流公司：</view>
          <view class="express-value">{{ orderInfo.express_com }}</view>
        </view>
        <view class="express-item">
          <view class="express-label">快递单号：</view>
          <view class="express-value">{{ orderInfo.express_no }}</view>
          <view class="copy-btn" @click="copyExpressNo">复制</view>
        </view>
        <view class="express-item">
          <view class="express-label">发货时间：</view>
          <view class="express-value">{{ orderInfo.send_time_str || '-' }}</view>
        </view>
        <view class="express-item">
          <view class="express-label">备注：>
          <view class="express-value">{{ orderInfo.express_remark || '无' }}</view>
        </view>
      </view>
      <view class="express-trace-btn" @click="showLogisticsTrace">
        查看物流轨迹
      </view>
    </view>
    
    <!-- 收货信息 -->
    <view class="address-section" v-if="orderInfo">
      <view class="section-title">收货信息</view>
      <view class="address-info">
        <view class="contact-info">
          <text class="name">{{ orderInfo.linkman }}</text>
          <text class="tel">{{ orderInfo.tel }}</text>
        </view>
        <view class="address-detail">{{ orderInfo.province + orderInfo.city + orderInfo.district + orderInfo.address_detail }}</view>
      </view>
    </view>
    
    <!-- 商品信息 --</view>
    <view class="goods-section" v-if="orderInfo && orderInfo.goods">
      <view class="section-title">商品信息</view>
      <view class="goods-list">
        <view class="goods-item" v-for="(good, index) in orderInfo.goods" :key="index">
          <image :src="good.product.image" mode="aspectFill" class="goods-image"></image>
          <view class="goods-info">
            <view class="goods-title">{{ good.product.title }}</view>
            <view class="goods-spec" v-if="good.options">
              {{ good.options }}
            </view>
            <view class="goods-meta">
              <view class="goods-price">¥{{ (good.price/100).toFixed(2) }}</view>
              <view class="goods-quantity">x{{ good.total }}</view>
              <view class="goods-subtotal">小计：¥{{ ((good.price * good.total)/100).toFixed(2) }}</view>
            </view>
          </view>
        </view>
      </view>
    </view>
    
    <!-- 订单信息 -->
    <view class="order-info-section" v-if="orderInfo">
      <view class="section-title">订单信息</view>
      <view class="order-info-list">
        <view class="info-item">
          <view class="info-label">订单编号：</view>
          <view class="info-value">{{ orderInfo.ordernum }}</view>
          <view class="copy-btn" @click="copyOrderNum">复制</view>
        </view>
        <view class="info-item">
          <view class="info-label">下单时间：</view>
          <view class="info-value">{{ orderInfo.addtime_str }}</view>
        </view>
        <view class="info-item">
          <view class="info-label">支付方式：</view>
          <view class="info-value">{{ getPayMethodText(orderInfo.payment) }}>
        </view>
        <view class="info-item">
          <view class="info-label">支付时间：</view>
          <view class="info-value">{{ orderInfo.pay_time_str || '未支付' }}>
        </view>
        <view class="info-item">
          <view class="info-label">订单总价：</view>
          <view class="info-value">¥{{ (orderInfo.total/100).toFixed(2) }}>
        </view>
        <view class="info-item">
          <view class="info-label">商品总价：</view>
          <view class="info-value">¥{{ (orderInfo.goods_total/100).toFixed(2) }}>
        </view>
        <view class="info-item">
          <view class="info-label">运费：</view>
          <view class="info-value">¥{{ (orderInfo.freight/100).toFixed(2) }}>
        </view>
        <view class="info-item" v-if="orderInfo.discount > 0">
          <view class="info-label">优惠金额：</view>
          <view class="info-value discount">-¥{{ (orderInfo.discount/100).toFixed(2) }}>
        </view>
        <view class="info-item bold">
          <view class="info-label">供应商应收：</view>
          <view class="info-value total">¥{{ (orderInfo.supplier_total/100).toFixed(2) }}>
        </view>
      </view>
    </view>
    
    <!-- 发货操作区 -->
    <view class="action-section" v-if="orderInfo && orderInfo.status === 1">
      <button class="send-btn" @click="showSendModal">确认发货</button>
    </view>
    
    <!-- 加载中 -->
    <view class="loading" v-if="loading">
      <image src="/static/images/loading.png" class="loading-icon"></image>
      <text class="loading-text">加载中...</text>
    </view>
    
    <!-- 发货弹窗 -->
    <view class="modal" v-if="showModal">
      <view class="modal-content">
        <view class="modal-header">
          <view class="modal-title">商品发货</view>
          <view class="modal-close" @click="closeModal">×</view>
        </view>
        
        <view class="modal-body">
          <view class="form-item">
            <view class="form-label">快递公司</view>
            <input type="text" v-model="expressData.express_com" placeholder="请输入快递公司名称" />
          </view>
          
          <view class="form-item">
            <view class="form-label">快递单号</view>
            <input type="text" v-model="expressData.express_no" placeholder="请输入快递单号" />
          </view>
          
          <view class="form-item">
            <view class="form-label">备注</view>
            <textarea v-model="expressData.remark" placeholder="请输入备注信息（选填）" rows="3"></textarea>
          </view>
        </view>
        
        <view class="modal-footer">
          <button class="cancel-btn" @click="closeModal">取消</button>
          <button class="confirm-btn" @click="submitSend" :disabled="!expressData.express_com || !expressData.express_no">
            确认发货
          </button>
        </view>
      </view>
    </view>
    
    <!-- 物流跟踪弹窗 -->
    <view class="modal" v-if="showTraceModal">
      <view class="modal-content trace-modal">
        <view class="modal-header">
          <view class="modal-title">物流轨迹</view>
          <view class="modal-close" @click="showTraceModal = false">×</view>
        </view>
        
        <view class="modal-body">
          <view class="express-header" v-if="logisticsInfo">
            <view class="express-com">{{ logisticsInfo.com }}</view>
            <view class="express-no">{{ logisticsInfo.no }}>
          </view>
          
          <view class="trace-list" v-if="logisticsInfo && logisticsInfo.traces && logisticsInfo.traces.length > 0">
            <view class="trace-item" v-for="(trace, index) in logisticsInfo.traces" :key="index">
              <view class="trace-time">{{ trace.time }}</view>
              <view class="trace-content">{{ trace.content }}>
            </view>
          </view>
          
          <view class="empty-trace" v-else-if="logisticsInfo">
            <image src="/static/images/empty.png" mode="aspectFit"></image>
            <text>暂无物流信息>
          </view>
          
          <view class="loading-trace" v-if="loadingTrace">
            加载中...</view>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      orderId: '',
      orderInfo: null,
      loading: true,
      showModal: false,
      expressData: {
        express_com: '',
        express_no: '',
        remark: ''
      },
      showTraceModal: false,
      logisticsInfo: null,
      loadingTrace: false
    };
  },
  
  onLoad(options) {
    this.orderId = options.orderId || '';
    if (this.orderId) {
      this.getOrderDetail();
    }
  },
  
  methods: {
    // 返回上一页
    goBack() {
      wx.navigateBack();
    },
    
    // 获取订单详情
    getOrderDetail() {
      wx.request({
        url: this.$serverUrl + '/api/ApiSupplierOrder/supplierOrderDetail',
        method: 'post',
        data: {
          orderid: this.orderId
        },
        header: {
          'content-type': 'application/x-www-form-urlencoded',
          'Authorization': wx.getStorageSync('token')
        },
        success: (res) => {
          if (res.data.status === 1) {
            this.orderInfo = res.data.data;
          } else {
            wx.showToast({
              title: res.data.msg || '获取订单详情失败',
              icon: 'none'
            });
          }
        },
        complete: () => {
          this.loading = false;
        }
      });
    },
    
    // 显示发货弹窗
    showSendModal() {
      this.expressData = {
        express_com: '',
        express_no: '',
        remark: ''
      };
      this.showModal = true;
    },
    
    // 关闭弹窗
    closeModal() {
      this.showModal = false;
    },
    
    // 提交发货
    submitSend() {
      if (!this.expressData.express_com || !this.expressData.express_no) {
        wx.showToast({
          title: '请填写快递公司和单号',
          icon: 'none'
        });
        return;
      }
      
      wx.showLoading({ title: '处理中' });
      
      wx.request({
        url: this.$serverUrl + '/api/ApiSupplierOrder/supplierSendExpress',
        method: 'post',
        data: {
          orderid: this.orderId,
          express_com: this.expressData.express_com,
          express_no: this.expressData.express_no,
          remark: this.expressData.remark
        },
        header: {
          'content-type': 'application/x-www-form-urlencoded',
          'Authorization': wx.getStorageSync('token')
        },
        success: (res) => {
          wx.hideLoading();
          
          if (res.data.status === 1) {
            wx.showToast({
              title: '发货成功',
              icon: 'success'
            });
            this.closeModal();
            this.getOrderDetail(); // 刷新订单详情
          } else {
            wx.showToast({
              title: res.data.msg || '发货失败',
              icon: 'none'
            });
          }
        },
        fail: () => {
          wx.hideLoading();
          wx.showToast({
            title: '网络错误',
            icon: 'none'
          });
        }
      });
    },
    
    // 获取支付方式文本
    getPayMethodText(payment) {
      switch(payment) {
        case 'wechat': return '微信支付';
        case 'alipay': return '支付宝';
        case 'cod': return '货到付款';
        default: return '其他支付方式';
      }
    },
    
    // 获取状态图标
    getStatusIcon(status) {
      switch(status) {
        case 1: return 'icon-pending';
        case 2: return 'icon-sent';
        case 3: return 'icon-completed';
        default: return '';
      }
    },
    
    // 获取状态图标文本
    getStatusIconText(status) {
      switch(status) {
        case 1: return '待发货';
        case 2: return '已发货';
        case 3: return '已完成';
        default: return '?';
      }
    },
    
    // 获取状态文本
    getStatusText(status) {
      switch(status) {
        case 1: return '待发货';
        case 2: return '已发货';
        case 3: return '已完成';
        default: return '未知状态';
      }
    },
    
    // 获取状态文本样式
    getStatusTextClass(status) {
      switch(status) {
        case 1: return 'text-pending';
        case 2: return 'text-sent';
        case 3: return 'text-completed';
        default: return '';
      }
    },
    
    // 获取状态描述
    getStatusDescription(status) {
      switch(status) {
        case 2: return '商品已发出，正在配送中';
        case 3: return '订单已完成，感谢您的支持';
        default: return '';
      }
    },
    
    // 复制订单号
    copyOrderNum() {
      wx.setClipboardData({
        data: this.orderInfo.ordernum,
        success: () => {
          wx.showToast({
            title: '复制成功',
            icon: 'success'
          });
        }
      });
    },
    
    // 复制快递单号
    copyExpressNo() {
      wx.setClipboardData({
        data: this.orderInfo.express_no,
        success: () => {
          wx.showToast({
            title: '复制成功',
            icon: 'success'
          });
        }
      });
    },
    
    // 显示物流轨迹
    showLogisticsTrace() {
      this.loadingTrace = true;
      this.logisticsInfo = null;
      this.showTraceModal = true;
      
      wx.request({
        url: this.$serverUrl + '/api/ApiSupplierOrder/supplierGetExpress',
        method: 'post',
        data: {
          orderid: this.orderId
        },
        header: {
          'content-type': 'application/x-www-form-urlencoded',
          'Authorization': wx.getStorageSync('token')
        },
        success: (res) => {
          if (res.data.status === 1) {
            this.logisticsInfo = res.data.data;
          }
        },
        complete: () => {
          this.loadingTrace = false;
        }
      });
    }
  }
};
</script>

<style scoped>
.order-detail-container {
  padding-bottom: 30rpx;
  background-color: #f5f5f5;
  min-height: 100vh;
}

.header {
  background-color: #1989fa;
  color: #fff;
  padding: 20rpx 30rpx;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 100;
}

.back-btn {
  font-size: 40rpx;
  width: 40rpx;
}

.title {
  font-size: 36rpx;
  font-weight: bold;
}

.placeholder {
  width: 40rpx;
}

.status-section {
  background-color: #fff;
  padding: 50rpx 30rpx;
  text-align: center;
  margin-bottom: 20rpx;
}

.status-icon {
  width: 120rpx;
  height: 120rpx;
  border-radius: 60rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20rpx;
  font-size: 28rpx;
  font-weight: bold;
}

.icon-pending {
  background-color: #fff7e6;
  color: #ff976a;
}

.icon-sent {
  background-color: #e6f7ff;
  color: #1890ff;
}

.icon-completed {
  background-color: #f0f9ff;
  color: #69c0ff;
}

.status-text {
  font-size: 36rpx;
  font-weight: bold;
  margin-bottom: 10rpx;
}

.text-pending {
  color: #ff976a;
}

.text-sent {
  color: #1890ff;
}

.text-completed {
  color: #69c0ff;
}

.status-desc {
  font-size: 28rpx;
  color: #666;
}

.section-title {
  font-size: 30rpx;
  font-weight: bold;
  color: #333;
  margin-bottom: 20rpx;
  padding-left: 30rpx;
}

.express-section,
.address-section,
.goods-section,
.order-info-section {
  background-color: #fff;
  padding: 20rpx;
  margin-bottom: 20rpx;
}

.express-info {
  background-color: #f5f5f5;
  padding: 20rpx;
  border-radius: 10rpx;
}

.express-item {
  display: flex;
  align-items: center;
  margin-bottom: 15rpx;
  font-size: 28rpx;
}

.express-item:last-child {
  margin-bottom: 0;
}

.express-label {
  width: 100rpx;
  color: #666;
}

.express-value {
  flex: 1;
  color: #333;
}

.copy-btn {
  background-color: #1989fa;
  color: #fff;
  padding: 5rpx 20rpx;
  border-radius: 10rpx;
  font-size: 24rpx;
}

.express-trace-btn {
  margin-top: 20rpx;
  text-align: center;
  color: #1989fa;
  font-size: 28rpx;
}

.address-info {
  background-color: #f5f5f5;
  padding: 20rpx;
  border-radius: 10rpx;
}

.contact-info {
  display: flex;
  margin-bottom: 10rpx;
  font-size: 30rpx;
  font-weight: bold;
  color: #333;
}

.tel {
  margin-left: 30rpx;
}

.address-detail {
  font-size: 28rpx;
  color: #666;
  line-height: 40rpx;
}

.goods-list {
  background-color: #f5f5f5;
  padding: 20rpx;
  border-radius: 10rpx;
}

.goods-item {
  display: flex;
  padding: 20rpx 0;
  border-bottom: 1rpx solid #e0e0e0;
}

.goods-item:last-child {
  border-bottom: none;
}

.goods-image {
  width: 150rpx;
  height: 150rpx;
  border-radius: 10rpx;
}

.goods-info {
  flex: 1;
  margin-left: 20rpx;
}

.goods-title {
  font-size: 28rpx;
  color: #333;
  margin-bottom: 10rpx;
  line-height: 40rpx;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.goods-spec {
  font-size: 26rpx;
  color: #999;
  margin-bottom: 15rpx;
}

.goods-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.goods-price {
  font-size: 28rpx;
  color: #ff4757;
  font-weight: bold;
}

.goods-quantity {
  font-size: 28rpx;
  color: #666;
}

.goods-subtotal {
  font-size: 28rpx;
  color: #333;
}

.order-info-list {
  background-color: #f5f5f5;
  padding: 20rpx;
  border-radius: 10rpx;
}

.info-item {
  display: flex;
  align-items: center;
  margin-bottom: 20rpx;
  font-size: 28rpx;
}

.info-item:last-child {
  margin-bottom: 0;
}

.info-label {
  width: 140rpx;
  color: #666;
}

.info-value {
  flex: 1;
  color: #333;
  word-break: break-all;
}

.info-item.bold {
  font-weight: bold;
  padding-top: 20rpx;
  border-top: 1rpx solid #e0e0e0;
  margin-top: 20rpx;
}

.info-value.discount {
  color: #ff4757;
}

.info-value.total {
  color: #ff4757;
  font-size: 32rpx;
}

.action-section {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background-color: #fff;
  padding: 20rpx 30rpx;
  box-shadow: 0 -5rpx 15rpx rgba(0, 0, 0, 0.1);
  z-index: 10;
}

.send-btn {
  width: 100%;
  height: 80rpx;
  background-color: #1989fa;
  color: #fff;
  border: none;
  border-radius: 40rpx;
  font-size: 32rpx;
}

.loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 100rpx 0;
}

.loading-icon {
  width: 80rpx;
  height: 80rpx;
  margin-bottom: 30rpx;
  animation: rotate 1s linear infinite;
}

.loading-text {
  font-size: 28rpx;
  color: #666;
}

@keyframes rotate {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
}

.modal-content {
  background-color: #fff;
  width: 80%;
  border-radius: 20rpx;
  padding: 30rpx;
}

.trace-modal {
  width: 90%;
  height: 70%;
  display: flex;
  flex-direction: column;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30rpx;
  padding-bottom: 20rpx;
  border-bottom: 1rpx solid #f0f0f0;
}

.modal-title {
  font-size: 32rpx;
  font-weight: bold;
  color: #333;
}

.modal-close {
  font-size: 40rpx;
  color: #999;
}

.modal-body {
  flex: 1;
  overflow-y: auto;
}

.form-item {
  margin-bottom: 30rpx;
}

.form-label {
  font-size: 28rpx;
  color: #333;
  margin-bottom: 10rpx;
}

.form-item input {
  width: 100%;
  height: 80rpx;
  border: 1rpx solid #e0e0e0;
  border-radius: 10rpx;
  padding: 0 20rpx;
  font-size: 28rpx;
}

.form-item textarea {
  width: 100%;
  border: 1rpx solid #e0e0e0;
  border-radius: 10rpx;
  padding: 20rpx;
  font-size: 28rpx;
  min-height: 150rpx;
}

.modal-footer {
  display: flex;
  justify-content: space-between;
  margin-top: 40rpx;
}

.cancel-btn {
  flex: 1;
  height: 80rpx;
  border: 1rpx solid #e0e0e0;
  background-color: #fff;
  border-radius: 10rpx;
  font-size: 28rpx;
  margin-right: 20rpx;
}

.confirm-btn {
  flex: 1;
  height: 80rpx;
  background-color: #1989fa;
  color: #fff;
  border-radius: 10rpx;
  font-size: 28rpx;
  border: none;
}

.confirm-btn:disabled {
  background-color: #ccc;
}

.express-header {
  padding: 20rpx;
  background-color: #f5f5f5;
  border-radius: 10rpx;
  margin-bottom: 20rpx;
}

.express-com {
  font-size: 30rpx;
  font-weight: bold;
  color: #333;
  margin-bottom: 10rpx;
}

.express-no {
  font-size: 28rpx;
  color: #666;
}

.trace-list {
  background-color: #f5f5f5;
  padding: 20rpx;
  border-radius: 10rpx;
}

.trace-item {
  padding: 20rpx 0;
  border-bottom: 1rpx solid #e0e0e0;
}

.trace-item:last-child {
  border-bottom: none;
}

.trace-time {
  font-size: 26rpx;
  color: #999;
  margin-bottom: 10rpx;
}

.trace-content {
  font-size: 28rpx;
  color: #333;
  line-height: 40rpx;
}

.empty-trace {
  text-align: center;
  padding: 60rpx 0;
}

.empty-trace image {
  width: 150rpx;
  height: 150rpx;
  margin-bottom: 30rpx;
}

.empty-trace text {
  font-size: 28rpx;
  color: #999;
}

.loading-trace {
  text-align: center;
  padding: 40rpx 0;
  color: #666;
  font-size: 28rpx;
}
</style>