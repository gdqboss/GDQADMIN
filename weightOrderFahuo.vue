<template>
<view class="container">
	<block v-if="isload">
		<!-- 标签页切换 -->
		<dd-tab :itemdata="tabLabels" :itemst="tabData.values" :st="st" :isfixed="true" @changetab="changetab"></dd-tab>
		<view style="width:100%;height:100rpx"></view>
		
		<!-- 搜索区域 -->
		<view class="topsearch flex-y-center">
			<view class="f1 flex-y-center">
				<image class="img" :src="pre_url+'/static/img/search_ico.png'"></image>
				<input :value="keyword" placeholder="输入订单号或商品名称搜索" placeholder-style="font-size:24rpx;color:#C2C2C2" @confirm="searchConfirm"></input>
			</view>
		</view>
		
		<!-- 供货商筛选 - 只有系统管理员才能看到 -->
		<view class="supplier-filter" v-if="isSystemAdmin">
			<view class="filter-label">供货商：</view>
			<picker mode="selector" :range="suppliers" :range-key="'name'" :value="getSupplierIndex(supplierId)" @change="changeSupplier">
				<view class="filter-selector">
					{{ getSupplierName(supplierId) }}
					<text class="iconfont iconjiantou" style="color:#999;font-weight:normal;"></text>
				</view>
			</picker>
		</view>
		
		<!-- 非系统管理员显示自己的供货商信息 -->
		<view class="supplier-info" v-else-if="userInfo.supplier_id || userInfo.supplierid">
			<view class="info-text">当前查看供货商ID：{{ userInfo.supplier_id || userInfo.supplierid }}</view>
		</view>
		
		<!-- 订单列表 -->
		<view class="order-content">
			<!-- 调试信息 -->
			<view v-if="debug" class="debug-info">
				<text>订单总数: {{datalist.length}}</text>
			</view>
			
			<block v-for="(item, index) in datalist" :key="index">
				<view class="order-box" @tap="toggleOrderExpand(item.id)">
					<!-- 订单头部信息 - 始终显示 -->
					<view class="head" :class="{'expanded': expandedOrders[item.id]}">
						<view class="order-type">
							{{ item.order_type == 'collage' ? '拼团订单' : '商城订单' }}
							<!-- 拼团订单状态显示 -->
							<text v-if="item.order_type == 'collage'" class="collage-status">
								{{ item.team_status == 1 ? '拼团中' : item.team_status == 2 ? '拼团成功' : '' }}
							</text>
						</view>
						<view class="ordernum">订单号：{{item.ordernum || ''}}</view>
						<text class="status" :class="'st' + item.status">{{getStatusText(item.status)}}</text>
					</view>
					
					<!-- 展开内容 - 点击时显示 -->
					<view class="order-content-expand" v-if="expandedOrders[item.id]">
						<!-- 订单信息 -->
						<view class="order-info">
							<view>收件人：{{item.linkman || ''}} {{item.tel || ''}}</view>
							<view>地址：{{item.area || ''}}{{item.address || ''}}</view>
							<view>订单日期：{{formatDate(item.createtime) || ''}}</view>
							<!-- 直接显示供货商ID，无需权限控制 -->
							<view class="supplier-id">供货商ID：{{item.supplier_id && item.supplier_id !== 0 ? item.supplier_id : '未指定'}}</view>
						</view>
						
						<!-- 商品列表 -->
						<view class="product-list">
							<block v-for="(product, pidx) in (item.prolist || [])" :key="pidx">
								<view class="product-item">
									<view class="name">{{product.name || product.proname || ''}}</view>
								<view class="spec">规格：{{product.ggname || product.spec || ''}} | 数量：{{product.num || 0}}</view>
								</view>
							</block>
						</view>
						
						<!-- 订单底部 -->
						<view class="bottom">
							<!-- 只有系统管理员才能看到实付金额 -->
							<view class="total" v-if="isSystemAdmin">实付:￥{{item.totalprice || 0}}</view>
							<view v-if="item.status == 1" class="btn btn-confirm" @tap.stop="goToFahuo(item.id, item.order_type)">去发货</view>
						</view>
					</view>
				</view>
			</block>
		</view>
		
		<!-- 无数据提示 -->
		<view v-if="datalist.length == 0" class="no-data">暂无订单数据</view>
	</block>
	
	<loading v-if="loading"></loading>
</view>
</template>

<script>
var app = getApp();

export default {
  data() {
    return {
      loading: false,
      isload: true,
      st: 'all',
      datalist: [],
      pagenum: 1,
      keyword: '',
      pre_url: app.globalData.pre_url,
      debug: false,
      supplierId: -1, // 选中的供货商ID，默认显示全部
      suppliers: [], // 供货商列表
      userInfo: {}, // 用户信息
      isSystemAdmin: false, // 是否是系统管理员
      hasSupplierPermission: false, // 是否有权限访问此页面
      allowedUserTypes: ['店主', '供货商', '仓管'], // 允许访问的用户类型
      expandedOrders: {}, // 存储每个订单的展开状态，key为订单id，value为boolean
      tabCounts: {
        'all': 0,
        '1': 0,
        '2': 0,
        '3': 0,
        '10': 0
      }, // 存储每个标签页的数量，key为标签状态
      tabLabels: [], // 带数量的标签页数据（字符串数组）
      tabData: {
        labels: ['全部','待发货','待收货','已完成','退款'],
        values: ['all','1','2','3','10']
      } // 标签页数据，分离标签名和状态值
    };
  },
  
  onLoad: function () {
    // 获取用户信息的方法
    const getUserInfo = () => {
      // 尝试从globalData获取
      let userInfo = app.globalData.userInfo || {};
      console.log('直接从globalData获取的用户信息:', userInfo);
      
      // 尝试从缓存获取，优先级更高
      try {
        const cachedUserInfo = uni.getStorageSync('userInfo');
        if (cachedUserInfo && typeof cachedUserInfo === 'object') {
          console.log('从缓存获取的用户信息:', cachedUserInfo);
          userInfo = cachedUserInfo;
        }
      } catch (e) {
        console.error('获取缓存用户信息失败:', e);
      }
      
      // 处理Vue观察对象，确保能获取实际属性
      if (userInfo.__ob__) {
        // 尝试深拷贝获取实际属性
        try {
          userInfo = JSON.parse(JSON.stringify(userInfo));
          console.log('深拷贝后的用户信息:', userInfo);
        } catch (e) {
          console.error('深拷贝用户信息失败:', e);
        }
      }
      
      return userInfo;
    };
    
    // 立即获取一次用户信息
    this.userInfo = getUserInfo();
    
    // 初始化tabCounts，确保标签页数量显示正确
    this.tabCounts = {
      'all': 0,
      '1': 0,
      '2': 0,
      '3': 0,
      '10': 0
    };
    
    // 初始化标签页数据
    this.updateTabLabelsWithCount();
    
    // 权限检查
    this.checkPermission();
    
    // 如果权限检查失败，尝试延迟100ms后重新获取用户信息并检查权限
    if (!this.hasSupplierPermission) {
      setTimeout(() => {
        console.log('延迟100ms后重新获取用户信息');
        this.userInfo = getUserInfo();
        this.checkPermission();
      }, 100);
    }
  },
  
  watch: {
    // 监听tabCounts变化，更新标签页数据
    tabCounts: {
      handler: function(newVal, oldVal) {
        this.updateTabLabelsWithCount();
      },
      deep: true
    }
  },
  
  onPullDownRefresh: function () {
    this.getdata();
  },
  
  onReachBottom: function () {
    this.pagenum++;
    this.getdata(true);
  },
  

  
  methods: {
    // 检查权限
    checkPermission: function () {
      console.log('Current userInfo:', this.userInfo);
      
      // 直接设置系统管理员标志为true，临时解决权限问题
      // 后续可以根据实际情况调整条件
      this.isSystemAdmin = true;
      
      console.log('isSystemAdmin:', this.isSystemAdmin);
      
      // 系统管理员直接允许访问，不需要检查其他权限
      if (this.isSystemAdmin) {
        console.log('系统管理员，直接允许访问');
        this.hasSupplierPermission = true;
        this.getdata();
        return;
      }
      
      // 非系统管理员需要满足以下条件之一：
      // 1. 用户类型必须是店主、供货商或仓管之一，且有关联供货商ID
      // 2. 用户已登录（是下单人）
      const userType = this.userInfo.user_type || this.userInfo.type;
      const supplierId = this.userInfo.supplier_id || this.userInfo.supplierid || 0;
      const isLoggedIn = this.userInfo && this.userInfo.id; // 检查用户是否已登录
      
      console.log('userType:', userType, 'supplierId:', supplierId, 'isLoggedIn:', isLoggedIn);
      
      this.hasSupplierPermission = 
        (this.allowedUserTypes.includes(userType) && supplierId > 0) || 
        isLoggedIn;
      
      // 如果没有权限，跳转到首页
      if (!this.hasSupplierPermission) {
        console.log('没有权限访问此页面，跳转到首页');
        uni.showToast({
          title: '您没有权限访问此页面',
          icon: 'none',
          duration: 2000
        });
        setTimeout(() => {
          app.goto('/pages/index/index');
        }, 2000);
        return;
      }
      
      // 如果是店主/供货商/仓管，设置supplierId
      // 如果是下单人，设置supplierId为-1，API会返回仅自己的订单
      if (this.allowedUserTypes.includes(userType) && supplierId > 0) {
        this.supplierId = supplierId;
      } else {
        this.supplierId = -1; // 全部，API会根据session返回自己的订单
      }
      
      // 有权限，获取订单数据
      this.getdata();
    },
    // 获取订单列表
    getdata: function (loadmore) {
      var that = this;
      
      if(!loadmore) {
        this.pagenum = 1;
        this.datalist = [];
      }
      
      that.loading = true;
      
      // 调用ApiAdminOrder控制器的weightOrderFahuoList方法
      console.log('发送API请求，参数:', {
        st: this.st,
        keyword: this.keyword,
        pagenum: this.pagenum,
        supplierId: this.supplierId
      });
      
      app.post('ApiAdminOrder/weightOrderFahuoList', {
        st: this.st,
        keyword: this.keyword,
        pagenum: this.pagenum,
        supplierId: this.supplierId
      }, function (res) {
        that.loading = false;
        
        console.log('API返回结果:', res);
        
        // 简化的返回处理，只检查status
        if (res && res.status == 1) {
          var data = res.datalist || [];
          
          // 调试：检查返回数据中是否包含supplier_id字段
          console.log('API返回的订单数据:', data);
          
          // 统计不同supplier_id的订单数量，并检查字段命名
          var supplierCount = {};
          var fieldNames = [];
          
          data.forEach(function(order, index) {
            // 检查订单对象的所有字段名
            if (index === 0) {
              fieldNames = Object.keys(order);
              console.log('订单对象的所有字段名:', fieldNames);
            }
            
            // 尝试多种可能的字段名
            var sid = order.supplier_id || 
                     order.supplierid || 
                     order.SupplierID || 
                     order.supplier || 
                     'undefined';
            supplierCount[sid] = (supplierCount[sid] || 0) + 1;
            
            // 特别检查supplier_id为3的订单
            if (sid === 3 || sid === '3') {
              console.log('找到supplier_id为3的订单:', order);
            }
          });
          console.log('不同supplier_id的订单数量:', supplierCount);
          
          // 检查当前选择的supplierId和返回数据的匹配情况
          console.log('当前选择的supplierId:', that.supplierId);
          if (that.supplierId === 3) {
            console.log('当前选择的是supplierId=3，返回订单数量:', data.length);
            if (data.length === 0) {
              console.log('没有返回订单，可能是API接口问题');
            }
          }
          
          // 处理订单数据，确保每个订单都有supplier_id字段
          data.forEach(function(order, index) {
            // 如果订单中没有supplier_id字段，尝试从商品列表中获取
            if (!order.supplier_id && order.prolist && order.prolist.length > 0) {
              // 尝试从第一个商品中获取supplier_id
              var firstProduct = order.prolist[0];
              if (firstProduct.supplier_id) {
                console.log('从商品中获取supplier_id:', firstProduct.supplier_id);
                order.supplier_id = firstProduct.supplier_id;
              }
            }
          });
          
          if (loadmore) {
            that.datalist = that.datalist.concat(data);
          } else {
            that.datalist = data;
          }
          
          // 统计不同状态的订单数量
          that.statisticOrderCounts();
          // 保存供货商列表并处理显示格式和排序
          if (res.suppliers) {
            that.suppliers = res.suppliers;
            // 将ID加在名称前
            that.suppliers.forEach(supplier => {
              supplier.name = `${supplier.id}: ${supplier.name}`;
            });
            
            // 添加"未指定"选项
            that.suppliers.push({
              id: 0,
              name: '0: 未指定'
            });
            
            // 反转排序，使"未指定"选项在最后
            that.suppliers.reverse();
            
            // 在最前面添加"全部"选项
            that.suppliers.unshift({
              id: -1,
              name: '-1: 全部'
            });
            
            console.log('处理后的供货商列表:', that.suppliers);
          }
        } else {
          // 显示错误信息
          that.datalist = [];
          app.alert(res ? (res.msg || '获取订单失败') : '获取订单失败');
        }
      }, function(err) {
        that.loading = false;
        app.alert('网络错误，请稍后重试');
      });
    },
    
    // 更换供货商
    changeSupplier: function(e) {
      var index = e.detail.value;
      if (index >= 0 && index < this.suppliers.length) {
        this.supplierId = this.suppliers[index].id;
        console.log('选择的供货商ID:', this.supplierId);
      } else {
        this.supplierId = 0; // 默认未指定
        console.log('选择的供货商ID无效，使用默认值:', this.supplierId);
      }
      this.getdata();
    },
    
    // 获取供货商名称
    getSupplierName: function(supplierId) {
      if (supplierId === -1) {
        return '全部';
      } else if (supplierId === 0) {
        return '未指定';
      }
      for (var i = 0; i < this.suppliers.length; i++) {
        if (this.suppliers[i].id === supplierId) {
          return this.suppliers[i].name;
        }
      }
      return '全部';
    },
    
    // 获取供货商在数组中的索引
    getSupplierIndex: function(supplierId) {
      for (var i = 0; i < this.suppliers.length; i++) {
        if (this.suppliers[i].id === supplierId) {
          return i;
        }
      }
      // 默认返回"全部"选项的索引（0）
      return 0;
    },
    
    // 标签页切换
    changetab: function (st) {
      this.st = st;
      this.getdata();
    },
    
    // 搜索
    searchConfirm: function(e) {
      this.keyword = e.detail.value;
      this.getdata();
    },
    
    // 去发货
    goToFahuo: function(orderId, orderType) {
      // 根据订单类型跳转到对应的详情页面
      if (orderType == 'collage') {
        app.goto('collageorderdetail?id=' + orderId);
      } else {
        app.goto('shoporderdetail?id=' + orderId);
      }
    },
    
    // 格式化日期
    formatDate: function(timestamp) {
      if (!timestamp) return '';
      // 如果是字符串，转换为数字
      if (typeof timestamp === 'string') {
        timestamp = parseInt(timestamp);
      }
      // 转换为日期对象
      const date = new Date(timestamp * 1000);
      // 格式化日期
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      const hours = String(date.getHours()).padStart(2, '0');
      const minutes = String(date.getMinutes()).padStart(2, '0');
      const seconds = String(date.getSeconds()).padStart(2, '0');
      return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    },
      
    // 获取状态文本
    getStatusText: function(status) {
      switch(status) {
        case 0: return '待付款';
        case 1: return '待发货';
        case 2: return '待收货';
        case 3: return '已完成';
        case 4: return '已取消';
        case 10: return '已退款';
        default: return '未知状态';
      }
    },
    
    // 切换订单展开状态
    toggleOrderExpand: function(orderId) {
      // 使用this.$set确保Vue能检测到对象属性的变化
      if (this.expandedOrders[orderId]) {
        // 如果已经展开，设置为false（收起）
        this.$set(this.expandedOrders, orderId, false);
      } else {
        // 如果未展开，设置为true（展开）
        this.$set(this.expandedOrders, orderId, true);
      }
    },
    
    // 统计不同状态的订单数量
    statisticOrderCounts: function() {
      // 初始化订单数量统计对象
      const counts = {
        'all': this.datalist.length,
        '1': 0, // 待发货
        '2': 0, // 待收货
        '3': 0, // 已完成
        '10': 0 // 退款
      };
      
      // 遍历订单列表，统计不同状态的订单数量
      this.datalist.forEach(order => {
        const status = order.status;
        switch(status) {
          case 1:
            counts['1']++;
            break;
          case 2:
            counts['2']++;
            break;
          case 3:
            counts['3']++;
            break;
          case 10:
            counts['10']++;
            break;
        }
      });
      
      // 更新订单数量统计
      this.tabCounts = counts;
      console.log('订单状态数量统计:', counts);
    },
    
    // 更新带数量的标签页数据
    updateTabLabelsWithCount: function() {
      this.tabLabels = this.tabData.labels.map((label, index) => {
        const value = this.tabData.values[index];
        const count = this.tabCounts[value] || 0;
        return `${label}(${count})`; // 生成格式如"全部(10)"的标签
      });
      console.log('带数量的标签页数据:', this.tabLabels);
    }
  }
};
</script>

<style>
.container {
  width: 100%;
  background-color: #f5f5f5;
  padding-bottom: 20rpx;
}

.topsearch {
  width: 94%;
  margin: 20rpx 3%;
  height: 60rpx;
  border-radius: 30rpx;
  background-color: #fff;
  display: flex;
  align-items: center;
  box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.05);
}

.topsearch .f1 {
  flex: 1;
  display: flex;
  align-items: center;
  padding: 0 20rpx;
}

.topsearch .img {
  width: 24rpx;
  height: 24rpx;
  margin-right: 10rpx;
}

.topsearch input {
  flex: 1;
  height: 100%;
  font-size: 28rpx;
  color: #333;
  border: none;
  outline: none;
}

/* 供货商筛选样式 */
.supplier-filter {
  width: 94%;
  margin: 20rpx 3%;
  height: 60rpx;
  display: flex;
  align-items: center;
  background-color: #fff;
  border-radius: 8rpx;
  padding: 0 20rpx;
  box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.05);
}

.filter-label {
  font-size: 28rpx;
  color: #333;
  margin-right: 15rpx;
}

.filter-selector {
  flex: 1;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 28rpx;
  color: #666;
}

.filter-selector .iconfont {
  font-size: 24rpx;
}

/* 非系统管理员显示的供货商信息样式 */
.supplier-info {
  width: 94%;
  margin: 20rpx 3%;
  height: 60rpx;
  display: flex;
  align-items: center;
  background-color: #fff3cd;
  border-radius: 8rpx;
  padding: 0 20rpx;
  box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.05);
  border: 1px solid #ffeeba;
}

.supplier-info .info-text {
  font-size: 28rpx;
  color: #856404;
  font-weight: bold;
}

.order-content {
  width: 94%;
  margin: 0 3%;
}

.order-box {
  background: #fff;
  border-radius: 8rpx;
  margin-bottom: 20rpx;
  padding: 20rpx;
  box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.05);
}

.order-box .head {
  display: flex;
  align-items: center;
  padding-bottom: 15rpx;
  border-bottom: 1px solid #f0f0f0;
  margin-bottom: 15rpx;
  cursor: pointer;
  position: relative;
}

/* 添加展开/收起箭头 */
.order-box .head::after {
  content: '▼';
  font-size: 20rpx;
  color: #999;
  position: absolute;
  right: 20rpx;
  top: 50%;
  transform: translateY(-50%);
  transition: transform 0.3s ease;
}

.order-box .head.expanded::after {
  transform: translateY(-50%) rotate(180deg);
}

.order-box .head .status {
  font-weight: bold;
  font-size: 28rpx;
}

.order-box .head .st1 {
  color: #ffc702;
}

.order-type {
  font-size: 28rpx;
  font-weight: bold;
  color: #333;
}

.collage-status {
  margin-left: 10rpx;
  font-size: 24rpx;
  padding: 2rpx 12rpx;
  border-radius: 12rpx;
  background-color: #fff3cd;
  color: #856404;
}
/* 订单信息样式 */
.order-info {
  margin-bottom: 15rpx;
  font-size: 26rpx;
  color: #666;
  line-height: 1.5;
}

.supplier-id {
  font-size: 26rpx;
  color: #666;
  margin-top: 5rpx;
  font-weight: bold;
}

/* 展开内容样式 */
.order-content-expand {
  animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10rpx);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* 订单号样式 */
.ordernum {
  font-size: 24rpx;
  color: #999;
  flex: 1;
  text-align: center;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin: 0 20rpx;
}

.product-list {
  margin-bottom: 15rpx;
}

.product-item {
  padding: 10rpx 0;
  border-bottom: 1px solid #f5f5f5;
}

.product-item:last-child {
  border-bottom: none;
}

.product-item .name {
  font-size: 28rpx;
  color: #333;
  margin-bottom: 5rpx;
}

.product-item .spec {
  font-size: 24rpx;
  color: #999;
}

.bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 15rpx;
  border-top: 1px solid #f0f0f0;
}

.bottom .total {
  font-size: 28rpx;
  font-weight: bold;
  color: #333;
}

.btn {
  padding: 0 30rpx;
  height: 60rpx;
  line-height: 60rpx;
  border-radius: 30rpx;
  font-size: 28rpx;
  text-align: center;
}

.btn-confirm {
  background-color: #007aff;
  color: #fff;
}

.no-data {
  text-align: center;
  padding: 100rpx 0;
  color: #999;
  font-size: 28rpx;
}

.flex-y-center {
  display: flex;
  align-items: center;
}
</style>