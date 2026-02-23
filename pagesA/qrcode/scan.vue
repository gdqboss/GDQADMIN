<template>
  <view class="container">
    <view class="scan-container">
      <view class="scan-title">扫码查看商品信息</view>
      <view class="scan-area">
        <image v-if="qrcodeUrl" :src="qrcodeUrl" mode="aspectFit" class="qrcode-img"></image>
        <view v-else class="scan-hint">请扫描商品上的二维码</view>
      </view>
      <button class="scan-btn" @click="startScan">开始扫码</button>
      
      <!-- 扫码结果 -->
      <view v-if="scanResult" class="scan-result">
        <view class="result-title">扫码结果</view>
        <view class="result-content">
          <view class="result-item">
            <text class="result-label">商品名称：</text>
            <text class="result-value">{{ scanResult.product?.name || '未知商品' }}</text>
          </view>
          <view class="result-item">
            <text class="result-label">商品唯一码：</text>
            <text class="result-value">{{ scanResult.qrcode?.code || '' }}</text>
          </view>
          <view class="result-item">
            <text class="result-label">当前状态：</text>
            <text class="result-value">{{ getStatusText(scanResult.qrcode?.current_status) }}</text>
          </view>
        </view>
        
        <!-- 可执行操作 -->
        <view v-if="scanResult.actions && scanResult.actions.length > 0" class="actions-container">
          <view class="actions-title">可执行操作</view>
          <view class="actions-list">
            <button 
              v-for="action in scanResult.actions" 
              :key="action.id" 
              class="action-btn"
              @click="executeAction(action)"
            >
              {{ action.name }}
            </button>
          </view>
        </view>
        
        <!-- 显示内容 -->
        <view v-if="scanResult.display_content" class="display-content">
          <view class="content-title">{{ scanResult.display_content.title }}</view>
          <view v-for="(section, index) in scanResult.display_content.sections" :key="index" class="content-section">
            <view class="section-title">{{ section.name }}</view>
            <view class="section-content">
              <!-- 根据内容类型动态渲染 -->
              <view v-if="typeof section.content === 'object'" class="content-object">
                <view v-for="(value, key) in section.content" :key="key" class="content-item">
                  <text class="item-label">{{ formatKey(key) }}：</text>
                  <text class="item-value">{{ formatValue(value) }}</text>
                </view>
              </view>
              <view v-else class="content-text">{{ section.content }}</view>
            </view>
          </view>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      qrcodeUrl: '',
      scanResult: null,
      operatorType: 7, // 默认客户身份
      operatorId: '',
      operatorName: ''
    };
  },
  onLoad(options) {
    // 从options中获取操作人信息
    if (options.operator_type) {
      this.operatorType = parseInt(options.operator_type);
    }
    if (options.operator_id) {
      this.operatorId = options.operator_id;
    }
    if (options.operator_name) {
      this.operatorName = options.operator_name;
    }
  },
  methods: {
    startScan() {
      // 调用uni-app的扫码API
      uni.scanCode({
        success: (res) => {
          console.log('扫码结果:', res);
          this.handleScanResult(res.result);
        },
        fail: (err) => {
          console.error('扫码失败:', err);
          uni.showToast({
            title: '扫码失败，请重试',
            icon: 'none'
          });
        }
      });
    },
    handleScanResult(result) {
      // 解析扫码结果，获取唯一码
      let code = result;
      if (result.includes('?')) {
        // 如果是URL，提取code参数
        const urlParams = new URLSearchParams(result.split('?')[1]);
        code = urlParams.get('code') || result;
      }
      
      // 调用后端API获取扫码信息
      this.getScanInfo(code);
    },
    getScanInfo(code) {
      uni.showLoading({
        title: '加载中...'
      });
      
      uni.request({
        url: `${this.$baseUrl}/api/qrcode/scan`,
        method: 'GET',
        data: {
          code: code,
          operator_type: this.operatorType,
          operator_id: this.operatorId,
          operator_name: this.operatorName,
          openid: uni.getStorageSync('openid')
        },
        success: (res) => {
          uni.hideLoading();
          if (res.data.status === 1) {
            this.scanResult = res.data.data;
            uni.showToast({
              title: '扫码成功',
              icon: 'success'
            });
          } else {
            uni.showToast({
              title: res.data.msg || '获取信息失败',
              icon: 'none'
            });
          }
        },
        fail: (err) => {
          uni.hideLoading();
          console.error('获取扫码信息失败:', err);
          uni.showToast({
            title: '网络错误，请重试',
            icon: 'none'
          });
        }
      });
    },
    executeAction(action) {
      console.log('执行操作:', action);
      // 根据不同操作类型执行不同逻辑
      switch (action.id) {
        case 1: // 数据录入
          this.navigateToAction('录入数据', action);
          break;
        case 2: // 销售
          this.navigateToAction('销售商品', action);
          break;
        case 3: // 安装
          this.navigateToAction('安装服务', action);
          break;
        case 4: // 维修
          this.navigateToAction('维修服务', action);
          break;
        case 5: // 质保查询
          this.navigateToAction('质保查询', action);
          break;
        case 6: // 激活
          this.activateProduct();
          break;
        default:
          uni.showToast({
            title: '该操作暂未实现',
            icon: 'none'
          });
      }
    },
    navigateToAction(title, action) {
      uni.showModal({
        title: title,
        content: `确定要执行${action.name}操作吗？`,
        success: (res) => {
          if (res.confirm) {
            // 这里可以跳转到对应的操作页面
            uni.showToast({
              title: `${action.name}操作开发中`,
              icon: 'none'
            });
          }
        }
      });
    },
    activateProduct() {
      uni.showModal({
        title: '商品激活',
        content: '确定要激活该商品吗？激活后将开始计算质保期。',
        success: (res) => {
          if (res.confirm) {
            this.executeServiceAction(6, '商品激活');
          }
        }
      });
    },
    executeServiceAction(actionId, actionName) {
      if (!this.scanResult?.qrcode?.code) {
        uni.showToast({
          title: '请先扫码获取商品信息',
          icon: 'none'
        });
        return;
      }
      
      uni.showLoading({
        title: '处理中...'
      });
      
      uni.request({
        url: `${this.$baseUrl}/api/qrcode/executeService`,
        method: 'POST',
        data: {
          code: this.scanResult.qrcode.code,
          operator_type: this.operatorType,
          operator_id: this.operatorId,
          operator_name: this.operatorName,
          action: actionId,
          content: `${actionName}操作`,
          remark: ''
        },
        success: (res) => {
          uni.hideLoading();
          if (res.data.status === 1) {
            uni.showToast({
              title: '操作成功',
              icon: 'success'
            });
            // 重新获取扫码信息，更新状态
            this.getScanInfo(this.scanResult.qrcode.code);
          } else {
            uni.showToast({
              title: res.data.msg || '操作失败',
              icon: 'none'
            });
          }
        },
        fail: (err) => {
          uni.hideLoading();
          console.error('执行服务操作失败:', err);
          uni.showToast({
            title: '网络错误，请重试',
            icon: 'none'
          });
        }
      });
    },
    getStatusText(status) {
      const statusMap = {
        1: '未销售',
        2: '已销售',
        3: '已安装',
        4: '已激活',
        5: '已维修'
      };
      return statusMap[status] || '未知状态';
    },
    formatKey(key) {
      const keyMap = {
        name: '商品名称',
        code: '唯一码',
        status: '状态',
        scan_count: '扫码次数',
        customer_name: '客户姓名',
        customer_phone: '客户电话',
        sale_time: '销售时间',
        install_time: '安装时间',
        quality_start_time: '质保开始时间',
        quality_end_time: '质保结束时间',
        remaining_days: '剩余质保天数',
        current_status: '当前状态',
        last_service_time: '最后服务时间'
      };
      return keyMap[key] || key;
    },
    formatValue(value) {
      // 格式化时间
      if (typeof value === 'number' && value > 1000000000) {
        return new Date(value * 1000).toLocaleString();
      }
      return value;
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

.scan-container {
  background-color: #fff;
  border-radius: 16rpx;
  padding: 40rpx;
  box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.1);
}

.scan-title {
  font-size: 36rpx;
  font-weight: bold;
  text-align: center;
  margin-bottom: 40rpx;
  color: #333;
}

.scan-area {
  width: 100%;
  height: 400rpx;
  background-color: #f0f0f0;
  border-radius: 12rpx;
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 40rpx;
}

.qrcode-img {
  width: 300rpx;
  height: 300rpx;
}

.scan-hint {
  font-size: 28rpx;
  color: #999;
}

.scan-btn {
  width: 100%;
  height: 80rpx;
  background-color: #007aff;
  color: #fff;
  font-size: 32rpx;
  border-radius: 40rpx;
  margin-bottom: 40rpx;
}

.scan-result {
  background-color: #fafafa;
  border-radius: 12rpx;
  padding: 30rpx;
}

.result-title {
  font-size: 32rpx;
  font-weight: bold;
  margin-bottom: 20rpx;
  color: #333;
}

.result-content {
  background-color: #fff;
  border-radius: 8rpx;
  padding: 20rpx;
  margin-bottom: 30rpx;
}

.result-item {
  display: flex;
  margin-bottom: 16rpx;
  font-size: 28rpx;
}

.result-label {
  color: #666;
  width: 200rpx;
}

.result-value {
  color: #333;
  flex: 1;
}

.actions-container {
  margin-bottom: 30rpx;
}

.actions-title {
  font-size: 32rpx;
  font-weight: bold;
  margin-bottom: 20rpx;
  color: #333;
}

.actions-list {
  display: flex;
  flex-wrap: wrap;
  gap: 20rpx;
}

.action-btn {
  flex: 1;
  min-width: 200rpx;
  height: 70rpx;
  background-color: #4cd964;
  color: #fff;
  font-size: 28rpx;
  border-radius: 35rpx;
}

.display-content {
  background-color: #fff;
  border-radius: 8rpx;
  padding: 20rpx;
}

.content-title {
  font-size: 30rpx;
  font-weight: bold;
  margin-bottom: 20rpx;
  color: #333;
  text-align: center;
}

.content-section {
  margin-bottom: 30rpx;
}

.section-title {
  font-size: 28rpx;
  font-weight: bold;
  margin-bottom: 15rpx;
  color: #555;
  border-left: 4rpx solid #007aff;
  padding-left: 15rpx;
}

.section-content {
  background-color: #f9f9f9;
  border-radius: 6rpx;
  padding: 20rpx;
}

.content-object {
  display: flex;
  flex-direction: column;
  gap: 12rpx;
}

.content-item {
  display: flex;
  font-size: 26rpx;
}

.item-label {
  color: #666;
  width: 180rpx;
}

.item-value {
  color: #333;
  flex: 1;
}

.content-text {
  font-size: 26rpx;
  color: #333;
  line-height: 40rpx;
}
</style>