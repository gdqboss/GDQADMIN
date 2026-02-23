<template>
  <view class="inventory-report">
    <view class="header">
      <view class="title">仓库报表</view>
    </view>
    
    <!-- 筛选条件 -->
    <view class="filter-section">
      <view class="filter-item">
        <view class="filter-label">报表类型</view>
        <view class="filter-value" @click="showTypePicker">{{ reportTypeText }}</view>
      </view>
      
      <view class="filter-item">
        <view class="filter-label">时间范围</view>
        <view class="filter-value" @click="showDatePicker">{{ dateRangeText }}</view>
      </view>
      
      <view class="filter-item">
        <view class="filter-label">商品名称</view>
        <view class="filter-value" @click="showProductPicker">{{ selectedProduct || '全部商品' }}</view>
      </view>
      
      <button class="filter-btn" @click="fetchReportData">查询</button>
    </view>
    
    <!-- 统计卡片 -->
    <view class="stats-section">
      <view class="stat-card">
        <view class="stat-value">{{ totalCount }}</view>
        <view class="stat-label">总记录数</view>
      </view>
      <view class="stat-card">
        <view class="stat-value">{{ totalNum }}</view>
        <view class="stat-label">总数量</view>
      </view>
      <view class="stat-card">
        <view class="stat-value">¥{{ totalAmount }}</view>
        <view class="stat-label">总金额</view>
      </view>
    </view>
    
    <!-- 报表数据 -->
    <view class="report-section">
      <view class="report-title">{{ reportTypeText }}</view>
      
      <view class="report-list" v-if="reportData.length > 0">
        <view class="report-item" v-for="(item, index) in reportData" :key="index">
          <view class="item-header">
            <view class="item-id">单据号：{{ item.ordernum }}</view>
            <view class="item-time">{{ item.createtime }}</view>
          </view>
          <view class="item-content">
            <view class="item-product">{{ item.pro_name }}</view>
            <view class="item-spec">{{ item.gg_name }}</view>
            <view class="item-detail">
              <view class="detail-item">
                <view class="detail-label">数量：</view>
                <view class="detail-value">{{ item.num }}</view>
              </view>
              <view class="detail-item">
                <view class="detail-label">单价：</view>
                <view class="detail-value">¥{{ item.price }}</view>
              </view>
              <view class="detail-item">
                <view class="detail-label">金额：</view>
                <view class="detail-value total-amount">¥{{ item.total_amount }}</view>
              </view>
            </view>
          </view>
          <view class="item-footer">
            <view class="item-type" :class="item.type == 'in' ? 'in-type' : 'out-type'">
              {{ item.type_text }}
            </view>
            <view class="item-remark" v-if="item.remark">{{ item.remark }}</view>
          </view>
        </view>
      </view>
      
      <view class="empty-data" v-else>
        暂无数据
      </view>
    </view>
    
    <!-- 底部导航 -->
    <view class="bottom-nav">
      <button class="nav-btn" @click="switchReportType('outbound')">
        <text class="nav-icon">📦</text>
        <text class="nav-text">出库报表</text>
      </button>
      <button class="nav-btn" @click="switchReportType('in')">
        <text class="nav-icon">📥</text>
        <text class="nav-text">入库报表</text>
      </button>
      <button class="nav-btn" @click="printReport">
        <text class="nav-icon">🖨️</text>
        <text class="nav-text">打印报表</text>
      </button>
    </view>
    
    <!-- 选择器弹窗 -->
    <picker-view v-if="showPicker" :value="pickerValue" @change="onPickerChange" class="picker-view">
      <picker-view-column>
        <view class="picker-item" v-for="(option, index) in pickerOptions" :key="index">{{ option.label }}</view>
      </picker-view-column>
    </picker-view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      // 报表类型
      reportType: 'outbound',
      reportTypeText: '出库报表',
      // 日期范围
      startDate: '',
      endDate: '',
      dateRangeText: '全部时间',
      // 选中的商品
      selectedProduct: '',
      selectedProductId: '',
      // 统计数据
      totalCount: 0,
      totalNum: 0,
      totalAmount: '0.00',
      // 报表数据
      reportData: [],
      // 商品列表
      products: [],
      // 选择器相关
      showPicker: false,
      pickerType: '',
      pickerValue: [0],
      pickerOptions: []
    };
  },
  onLoad() {
    this.initData();
  },
  methods: {
    // 初始化数据
    initData() {
      this.fetchProducts();
      this.fetchReportData();
    },
    
    // 获取商品列表
    fetchProducts() {
      // 实际项目中，这里应该调用API获取商品列表
      // 模拟数据
      this.products = [
        { id: '1', name: '商品A' },
        { id: '2', name: '商品B' },
        { id: '3', name: '商品C' }
      ];
    },
    
    // 获取报表数据
    fetchReportData() {
      // 实际项目中，这里应该调用API获取报表数据
      // 模拟数据
      setTimeout(() => {
        this.reportData = [
          {
            ordernum: 'OUT20260122123456',
            pro_name: '商品A',
            gg_name: '规格1',
            num: 10,
            type: 'out',
            type_text: '出库',
            price: 100.00,
            total_amount: 1000.00,
            createtime: '2026-01-22 12:34:56',
            remark: '经销商出库'
          },
          {
            ordernum: 'OUT20260122123457',
            pro_name: '商品B',
            gg_name: '规格2',
            num: 5,
            type: 'out',
            type_text: '出库',
            price: 200.00,
            total_amount: 1000.00,
            createtime: '2026-01-22 12:35:56',
            remark: '本站商城出库'
          },
          {
            ordernum: 'IN20260122123458',
            pro_name: '商品C',
            gg_name: '规格3',
            num: 20,
            type: 'in',
            type_text: '入库',
            price: 50.00,
            total_amount: 1000.00,
            createtime: '2026-01-22 12:36:56',
            remark: '供应商入库'
          }
        ];
        
        // 更新统计数据
        this.updateStats();
      }, 500);
    },
    
    // 更新统计数据
    updateStats() {
      this.totalCount = this.reportData.length;
      this.totalNum = this.reportData.reduce((sum, item) => sum + parseInt(item.num), 0);
      this.totalAmount = this.reportData.reduce((sum, item) => sum + parseFloat(item.total_amount), 0).toFixed(2);
    },
    
    // 切换报表类型
    switchReportType(type) {
      this.reportType = type;
      this.reportTypeText = type === 'outbound' ? '出库报表' : '入库报表';
      this.fetchReportData();
    },
    
    // 显示类型选择器
    showTypePicker() {
      this.pickerType = 'type';
      this.pickerOptions = [
        { value: 'outbound', label: '出库报表' },
        { value: 'in', label: '入库报表' }
      ];
      this.showPicker = true;
    },
    
    // 显示日期选择器
    showDatePicker() {
      // 实际项目中，这里应该使用日期选择器组件
      this.dateRangeText = '2026-01-01 至 2026-01-22';
    },
    
    // 显示商品选择器
    showProductPicker() {
      this.pickerType = 'product';
      this.pickerOptions = [{ value: '', label: '全部商品' }, ...this.products.map(p => ({ value: p.id, label: p.name }))];
      this.showPicker = true;
    },
    
    // 选择器变化
    onPickerChange(e) {
      const index = e.detail.value[0];
      const selectedOption = this.pickerOptions[index];
      
      if (this.pickerType === 'type') {
        this.reportType = selectedOption.value;
        this.reportTypeText = selectedOption.label;
      } else if (this.pickerType === 'product') {
        this.selectedProductId = selectedOption.value;
        this.selectedProduct = selectedOption.label === '全部商品' ? '' : selectedOption.label;
      }
      
      this.showPicker = false;
    },
    
    // 打印报表
    printReport() {
      // 实际项目中，这里应该调用打印API或生成PDF
      wx.showToast({
        title: '打印功能开发中',
        icon: 'none'
      });
    }
  }
};
</script>

<style scoped>
.inventory-report {
  min-height: 100vh;
  background-color: #f5f5f5;
  padding-bottom: 100rpx;
}

.header {
  background-color: #1E9FFF;
  color: white;
  padding: 30rpx;
  text-align: center;
}

.title {
  font-size: 32rpx;
  font-weight: bold;
}

.filter-section {
  background-color: white;
  padding: 20rpx;
  margin-bottom: 20rpx;
}

.filter-item {
  display: flex;
  align-items: center;
  margin-bottom: 20rpx;
}

.filter-label {
  width: 120rpx;
  font-size: 28rpx;
  color: #666;
}

.filter-value {
  flex: 1;
  font-size: 28rpx;
  color: #333;
  padding: 10rpx;
  border: 1px solid #e6e6e6;
  border-radius: 8rpx;
  background-color: #f9f9f9;
}

.filter-btn {
  background-color: #1E9FFF;
  color: white;
  border: none;
  border-radius: 8rpx;
  padding: 15rpx;
  font-size: 28rpx;
  font-weight: bold;
  width: 100%;
}

.stats-section {
  display: flex;
  justify-content: space-around;
  background-color: white;
  padding: 20rpx;
  margin-bottom: 20rpx;
}

.stat-card {
  text-align: center;
  padding: 20rpx;
  background-color: #f5f7fa;
  border-radius: 8rpx;
  width: 30%;
}

.stat-value {
  font-size: 36rpx;
  font-weight: bold;
  color: #1E9FFF;
  margin-bottom: 10rpx;
}

.stat-label {
  font-size: 24rpx;
  color: #666;
}

.report-section {
  background-color: white;
  padding: 20rpx;
}

.report-title {
  font-size: 32rpx;
  font-weight: bold;
  margin-bottom: 20rpx;
  color: #333;
}

.report-list {
  margin-bottom: 20rpx;
}

.report-item {
  border: 1px solid #e6e6e6;
  border-radius: 8rpx;
  padding: 20rpx;
  margin-bottom: 20rpx;
  background-color: white;
}

.item-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 15rpx;
  padding-bottom: 10rpx;
  border-bottom: 1px solid #f0f0f0;
}

.item-id {
  font-size: 28rpx;
  font-weight: bold;
  color: #333;
}

.item-time {
  font-size: 24rpx;
  color: #999;
}

.item-content {
  margin-bottom: 15rpx;
}

.item-product {
  font-size: 28rpx;
  font-weight: bold;
  color: #333;
  margin-bottom: 10rpx;
}

.item-spec {
  font-size: 26rpx;
  color: #666;
  margin-bottom: 15rpx;
}

.item-detail {
  display: flex;
  flex-wrap: wrap;
  gap: 20rpx;
}

.detail-item {
  display: flex;
  align-items: center;
  font-size: 26rpx;
}

.detail-label {
  color: #666;
  margin-right: 5rpx;
}

.detail-value {
  color: #333;
  font-weight: bold;
}

.total-amount {
  color: #f56c6c;
}

.item-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 10rpx;
  border-top: 1px solid #f0f0f0;
}

.item-type {
  padding: 5rpx 15rpx;
  border-radius: 20rpx;
  font-size: 24rpx;
  font-weight: bold;
}

.in-type {
  background-color: #f0f9eb;
  color: #67c23a;
}

.out-type {
  background-color: #fef0f0;
  color: #f56c6c;
}

.item-remark {
  font-size: 24rpx;
  color: #999;
}

.empty-data {
  text-align: center;
  color: #999;
  padding: 50rpx;
  font-size: 28rpx;
}

.bottom-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  background-color: white;
  padding: 20rpx;
  border-top: 1px solid #e6e6e6;
  justify-content: space-around;
}

.nav-btn {
  background-color: #f5f7fa;
  border: none;
  border-radius: 8rpx;
  padding: 15rpx;
  width: 30%;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.nav-icon {
  font-size: 36rpx;
  margin-bottom: 5rpx;
}

.nav-text {
  font-size: 24rpx;
  color: #666;
}

.picker-view {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: 500rpx;
  background-color: white;
  z-index: 999;
  border-top-left-radius: 20rpx;
  border-top-right-radius: 20rpx;
}

.picker-item {
  height: 80rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32rpx;
}
</style>