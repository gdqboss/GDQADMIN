<template>
<view>
	<!-- #ifndef H5 -->
	<view class="navigation">
		<view class='navcontent' :style="{marginTop:navigationMenu.top+'px',width:(navigationMenu.right)+'px'}">
			<view class="header-location-top" :style="{height:navigationMenu.height+'px'}">
				<view class="header-page-title" style="color:#000000;">财务</view>
			</view>
		</view>
	</view>
	<!-- #endif -->
	<!-- <view style="width:100%" :style="{height:(44+statusBarHeight)+'px'}"></view> -->
	<!-- <image src="../../static/img/arrowright.png"></image> -->
	<!-- <image :src="pre_url+'/static/img/admin/jiantoushang.png'"> -->
	<loading v-if="loading"></loading>
	
	<!-- 时间范围选择 -->
	<view class="time-select"><view class="time-label">时间范围：</view>
		<picker @change="timeRangeChange" :range="timeRangeList" :value="timeRangeIndex" class="time-picker">
			<view class="picker-value">{{timeRangeList[timeRangeIndex]}}</view>
		</picker>
	</view>
	
	<!-- 累计数据 - 对所有用户可见 -->
	<view class="surverycontent" v-if="true">
		<view class="item" v-if="true">
				<view class="t1"><text>累计收款</text></view>
				<view class="t2">{{info.wxpayCount || 0}}<text class="price-unit">元</text></view>
				<view class="t3">昨日新增：<view class="price-color">￥{{info.wxpayLastDayCount || 0}}</view></view>
				<text class="t3">本月新增：￥{{info.wxpayThisMonthCount || 0}}</text>
		 </view>
		 <view class="item" v-if="true">
				<view class="t1"><text>累计退款</text></view>
				<view class="t2">{{info.refundCount || 0}}<text class="price-unit">元</text></view>
				<view class="t3">昨日新增：<view class="price-color">￥{{info.refundLastDayCount || 0}}</view></view>
				<text class="t3">本月新增：￥{{info.refundThisMonthCount || 0}}</text>
		 </view>
		 <!-- <view class="item" v-if="true">
				<view class="t1"><text>累计提现</text></view>
				<view class="t2">{{info.withdrawCount || 0}}<text class="price-unit">元</text></view>
				<view class="t3">昨日新增：<view class="price-color">￥{{info.withdrawLastDayCount || 0}}</view></view>
				<text class="t3">本月新增：￥{{info.withdrawThisMonthCount || 0}}</text>
		 </view> -->
		 <!-- <view class="item" v-if="true">
				<view class="t1"><text>累计佣金</text></view>
				<view class="t2">{{info.commissiontotal || 0}}<text class="price-unit">元</text></view>
				<view class="t3">待提佣金：<view class="price-color">￥{{info.commission || 0}}</view></view>
				<text class="t3">已提佣金：￥{{info.commissionwithdraw || 0}}</text>
		 </view> -->
		 <view class="item" v-if="show.show_salesquota">
				<view class="t1"><text>已销售额度</text></view>
				<view class="t2">{{info.total_sales_quota || 0}}<text class="price-unit">元</text></view>
				<view class="t3">总额度：<view class="price-color">{{info.sales_quota || 0}}</view></view>
		 </view>
		 
	</view>
	
	<!-- 营业报表和支出记录 - 所有人可见 -->
	<view class="listcontent">
		<!-- 营业报表 -->
		<view class="list">
			<view class="item" @tap="goto" data-url="/pagesB/admin/tradereport">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg6.png'"></image></view>
				<view class="f2">营业报表</view>
				<text class="f3"></text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
		
		<!-- 支出记录 -->
		<view class="list">
			<view class="item" @tap="goto" data-url="/pagesB/admin/expend">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg6.png'"></image></view>
				<view class="f2">支出记录</view>
				<text class="f3"></text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
		<view class="list">
			<view class="item" @tap="goto" data-url="/pagesB/admin/expendEdit">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg6.png'"></image></view>
				<view class="f2">添加支出</view>
				<text class="f3"></text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
	</view>
	
	<!-- 系统管理员可见内容 -->
	<block v-if="isSystemAdmin()">
	<block v-if="bid == 0">
	<view class="listcontent">
		<view class="list">
			<view class="item" @tap="goto" data-url="rechargelog"  v-if="(auth_data_menu.includes('all') || auth_data_menu.includes('Money/rechargelog'))">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg1.png'"></image></view>
				<view class="f2">充值记录</view>
				<text class="f3"></text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
		<view class="list">
			<view class="item" @tap="goto" data-url="moneylog" v-if="(auth_data_menu.includes('all') || auth_data_menu.includes('Money/moneylog'))">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg2.png'"></image></view>
				<view class="f2">{{t('余额')}}明细</view>
				<text class="f3">查看账户收支情况</text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
		<view class="list" v-if="show && show.scorelog && (auth_data_menu.includes('all') || auth_data_menu.includes('Score/scorelog'))">
			<view class="item" @tap="goto" data-url="scorelog">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg9.png'"></image></view>
				<view class="f2">{{t('积分')}}明细</view>
				<text class="f3"></text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
		<view class="list"  v-if="showyuebao_moneylog">
			<view class="item" @tap="goto" data-url="yuebaolog">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg3.png'"></image></view>
				<view class="f2">{{t('余额宝')}}明细</view>
				<text class="f3"></text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
		<view class="list">
			<view class="item" @tap="goto" data-url="commissionlog" v-if="(auth_data_menu.includes('all') || auth_data_menu.includes('Commission/commissionlog'))">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg4.png'"></image></view>
				<view class="f2">{{t('佣金')}}明细</view>
				<text class="f3"></text>
				<!-- <text class="f3 f3-price">￥2165.45</text> -->
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
		<view class="divider-line"></view>
		<view class="list">
			<view class="item" @tap="goto" data-url="withdrawlog"  v-if="(auth_data_menu.includes('all') || auth_data_menu.includes('Money/withdrawlog'))">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg5.png'"></image></view>
				<view class="f2">{{t('余额')}}提现列表</view>
				<view class="f3">
					<!-- <view class="f3-tisp">7</view> -->
				</view>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
		<view class="list"  v-if="showyuebao_withdrawlog">
			<view class="item" @tap="goto" data-url="yuebaowithdrawlog">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg5.png'"></image></view>
				<view class="f2">{{t('余额宝')}}提现列表</view>
				<text class="f3"></text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
		<view class="list">
			<view class="item" @tap="goto" data-url="comwithdrawlog" v-if="(auth_data_menu.includes('all') || auth_data_menu.includes('Commission/withdrawlog'))">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg6.png'"></image></view>
				<view class="f2">{{t('佣金')}}提现列表</view>
				<text class="f3"></text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
	</view>
	</block>
	<block v-if="bid!=0">
	<view class="listcontent">
		<view class="list" v-if="show && show.scorelog && (auth_data_menu.includes('all') || auth_data_menu.includes('Score/scorelog'))">
			<view class="item" @tap="goto" data-url="scorelog">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg9.png'"></image></view>
				<view class="f2">{{t('积分')}}明细</view>
				<text class="f3"></text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
		<view class="list" v-if="showbscore && (auth_data_menu.includes('all') || auth_data_menu.includes('Score/scorelog') || auth_data_menu.includes('BusinessScore/scorelog'))">
			<view class="item" @tap="goto" data-url="bscorelog">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg9.png'"></image></view>
				<view class="f2">{{t('积分')}}明细</view>
				<text class="f3">剩余{{t('积分')}}{{info.score}}</text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
		<view class="list">
			<view class="item" @tap="goto" data-url="bmoneylog" v-if="(auth_data_menu.includes('all') || auth_data_menu.includes('Money/moneylog') || auth_data_menu.includes('BusinessMoney/moneylog'))">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg2.png'"></image></view>
				<view class="f2">余额明细</view>
				<text class="f3"></text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
		<view class="list">
			<view class="item" @tap="goto" data-url="bwithdraw" v-if="(auth_data_menu.includes('all') || auth_data_menu.includes('Money/withdrawlog') || auth_data_menu.includes('BusinessMoney/withdraw'))">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg5.png'"></image></view>
				<view class="f2">余额提现</view>
				<text class="f3"></text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
		<view class="list">
			<view class="item" @tap="goto" data-url="bwithdrawlog" v-if="(auth_data_menu.includes('all') || auth_data_menu.includes('Money/withdrawlog') || auth_data_menu.includes('BusinessMoney/withdrawlog'))">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg6.png'"></image></view>
				<view class="f2">提现记录</view>
				<text class="f3"></text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
	</view>
	</block>
	<block v-if="showmdmoney">
		<view class="listcontent">
			<view class="list">
				<view class="item" @tap="goto" data-url="mdmoneylog" >
					<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg7.png'"></image></view>
					<view class="f2">门店余额明细</view>
					<text class="f3"></text>
					<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
				</view>
			</view>
			<view class="list">
				<view class="item" @tap="goto" data-url="mdwithdraw">
					<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg8.png'"></image></view>
					<view class="f2">门店余额提现</view>
					<text class="f3"></text>
					<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
				</view>
			</view>
			<view class="list">
				<view class="item" @tap="goto" data-url="mdwithdrawlog">
					<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg8.png'"></image></view>
					<view class="f2">门店提现记录</view>
					<text class="f3"></text>
					<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
				</view>
			</view>
		</view>
	</block>
	<block v-if="bid!=0 && showcouponmoney">
	<view class="listcontent">
		<view class="list">
			<view class="item" @tap="goto" data-url="../couponmoney/record">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg8.png'"></image></view>
				<view class="f2">补贴券使用明细</view>
				<text class="f3"></text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
		<view class="list">
			<view class="item" @tap="goto" data-url="../couponmoney/withdraw">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg8.png'"></image></view>
				<view class="f2">补贴券提现</view>
				<text class="f3"></text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
		<view class="list">
			<view class="item" @tap="goto" data-url="../couponmoney/withdrawlog">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg8.png'"></image></view>
				<view class="f2">补贴券提现记录</view>
				<text class="f3"></text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
	</view>
	</block>
	<block v-if="bid!=0 && show.showdepositlog">
	<view class="listcontent">
		<view class="list">
			<view class="item" @tap="goto" data-url="/pagesB/admin/depositlog">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg6.png'"></image></view>
				<view class="f2">{{t('入驻保证金')}}记录</view>
				<text class="f3"></text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
	</view>
	</block>
	</block>
	
	<!-- 系统管理员可见内容 -->
	<block v-if="isSystemAdmin()">
	<block v-if="bid!=0 && show.bonus_pool_gold">
	<view class="listcontent">
		<view class="list">
			<view class="item" @tap="goto" data-url="/adminExt/bonuspoolgold/goldlog">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg6.png'"></image></view>
				<view class="f2">{{t('金币')}}明细</view>
				<text class="f3">{{t('金币')}}价格：{{info.gold_price}}</text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
		<view class="list">
			<view class="item" @tap="goto" data-url="/adminExt/bonuspoolgold/goldwithdraw">
				<view class="f1"><image :src="pre_url+'/static/img/admin/financenbg8.png'"></image></view>
				<view class="f2">{{t('金币')}}兑换</view>
				<text class="f3">总{{t('金币')}}：{{info.gold}}</text>
				<image :src="pre_url+'/static/img/admin/financejiantou.png'" class="f4"></image>
			</view>
		</view>
	</view>
	</block>
	</block>
	<view class="tabbar">
		<view class="tabbar-bot"></view>
		<view class="tabbar-bar" style="background-color:#ffffff;">
			<view @tap="goto" data-url="../member/index" data-opentype="reLaunch" class="tabbar-item" v-if="auth_data.member">
				<view class="tabbar-image-box">
					<image class="tabbar-icon" :src="pre_url+'/static/img/admin/member.png?v=1'"></image>
				</view>
				<view class="tabbar-text">{{t('会员')}}</view>
			</view>
            <view @tap="goto" data-url="/admin/index/commentAudit" data-opentype="reLaunch" class="tabbar-item" v-if="auth_data.zixun">
                <view class="tabbar-image-box">
                    <image class="tabbar-icon" :src="pre_url+'/static/img/admin/zixun.png?v=1'"></image>
                </view>
                <view class="tabbar-text">评价审核</view>
            </view>
            <view @tap="goto" data-url="../finance/index" data-opentype="reLaunch" class="tabbar-item" v-if="auth_data.finance">
                <view class="tabbar-image-box">
                    <image class="tabbar-icon" :src="pre_url+'/static/img/admin/finance.png?v=1'"></image>
                </view>
                <view class="tabbar-text active">财务</view>
            </view>
            <view @tap="goto" data-url="../index/index" data-opentype="reLaunch" class="tabbar-item">
                <view class="tabbar-image-box">
                    <image class="tabbar-icon" :src="pre_url+'/static/img/admin/my2.png?v=1'"></image>
                </view>
                <view class="tabbar-text">我的</view>
            </view>
		</view>
	</view>
	<popmsg ref="popmsg"></popmsg>
</view>
</template>

<script>
var app = getApp();

export default {
  data() {
    return {
			opt:{},
			loading:false,
			pre_url:app.globalData.pre_url || '',
			navigationMenu:{},
			bid:0,
			showmdmoney:0,
      info: {},
      auth_data: {
				tradereport: true,
				finance: true
			},
      wxauth_data: [],
			auth_data_menu: ['all'],
      showyuebao_moneylog:false,
      showyuebao_withdrawlog:false,
			showbscore:false,
			showcouponmoney:false,
			show:{finance: true},
			platform: app.globalData.platform,
			statusBarHeight: 20,
			mdid:0,
			index_data:['all'],
			uinfo: {}, // 用户信息，用于判断权限
			// 时间范围选择
			timeRangeList: ['当天', '当周', '当月', '当季', '当年', '全部'],
			timeRangeIndex: 0, // 默认选中当天
			timeRange: 'today' // 默认时间范围
    };
  },
  onLoad: function (opt) {
		this.opt = app.getopts(opt);
		this.getdata();
		var sysinfo = uni.getSystemInfoSync();
		this.statusBarHeight = sysinfo.statusBarHeight;
		this.wxNavigationBarMenu();
  },
	onPullDownRefresh: function () {
		this.getdata();
	},
  methods: {
		// 检查数组是否包含指定元素
		inArray: function(value, array) {
			if (!Array.isArray(array)) {
				return false;
			}
			return array.indexOf(value) !== -1;
		},
		wxNavigationBarMenu:function(){
			if(this.platform=='wx'){
				//胶囊菜单信息
				this.navigationMenu = wx.getMenuButtonBoundingClientRect()
			}
		},
		// 时间范围选择变化
		timeRangeChange: function(e) {
			console.log('timeRangeChange - start:', e);
			this.timeRangeIndex = e.detail.value;
			const timeRangeMap = ['today', 'week', 'month', 'quarter', 'year', 'all'];
			this.timeRange = timeRangeMap[this.timeRangeIndex];
			console.log('timeRangeChange - timeRange:', this.timeRange);
			this.getdata(); // 重新获取数据
		},
		// 判断是否为系统管理员
		isSystemAdmin: function() {
			// 日志：检查用户权限信息
			console.log('isSystemAdmin - uinfo:', this.uinfo);
			const isAdmin = this.uinfo && (this.uinfo.isadmin === 1 || this.uinfo.isadmin === true || this.uinfo.isadmin === '1');
			console.log('isSystemAdmin - result:', isAdmin);
			return isAdmin;
		},
		getdata:function(){
			var that = this
			console.log('getdata - start, timeRange:', that.timeRange);
			that.loading = true;
			// 添加时间范围参数
			var params = {
				time_range: that.timeRange
			};
			console.log('getdata - API request params:', params);
			app.post('ApiAdminFinance/index', params, function (res) {
				console.log('getdata - API response:', res);
				that.loading = false;
				if (res.status == 0) {
					console.log('getdata - API error:', res.msg);
					app.alert(res.msg);
					return;
				}
				// 保存旧数据用于比较
				var oldInfo = JSON.stringify(that.info);
				that.info = res.info || {};
				var newInfo = JSON.stringify(that.info);
				console.log('getdata - info updated:', oldInfo !== newInfo, {oldInfo: oldInfo, newInfo: newInfo});
				that.bid = res.bid || 0;
				that.mdid = res.mdid || 0;
				that.uinfo = res.uinfo || {}; // 获取用户信息用于判断权限
				that.showmdmoney = res.showmdmoney || 0;
				that.showbscore = res.showbscore || false;
				that.showcouponmoney = res.showcouponmoney || false;
				that.auth_data = res.auth_data || {};
				that.wxauth_data = res.wxauth_data || {};
				that.auth_data_menu = res.auth_data_menu||['all'];
				that.showyuebao_moneylog    = res.showyuebao_moneylog || false;
				that.showyuebao_withdrawlog = res.showyuebao_withdrawlog || false;
				that.show = {...that.show, ...(res.show || {})};
				that.index_data = res.index_data||['all'];
				
				// 日志：检查关键数据
			console.log('getdata - processed data:', {
				info: that.info,
				bid: that.bid,
				auth_data: that.auth_data,
				auth_data_menu: that.auth_data_menu,
				index_data: that.index_data,
				show: that.show,
				wxauth_data: that.wxauth_data,
				mdid: that.mdid
			});
			
			// 日志：检查累计数据显示条件
			console.log('getdata - 累计数据总显示条件:', that.show.finance);
			console.log('getdata - 累计收款显示条件:', that.index_data.indexOf('all') > -1 || that.index_data.indexOf('total_receive') > -1);
			console.log('getdata - 累计退款显示条件:', that.index_data.indexOf('all') > -1 || that.index_data.indexOf('total_refund') > -1);
			console.log('getdata - 累计提现显示条件:', that.bid == 0 && (that.index_data.indexOf('all') > -1 || that.index_data.indexOf('total_withdraw') > -1) && that.info.show_tixian == 1);
			console.log('getdata - 累计佣金显示条件:', that.bid == 0 && (that.index_data.indexOf('all') > -1 || that.index_data.indexOf('total_commission') > -1) && that.mdid == 0);
			console.log('getdata - info数据:', that.info);
			console.log('getdata - index_data:', that.index_data);
			
			// 日志：检查营业报表和支出记录显示条件
			console.log('getdata - 营业报表显示条件:', that.auth_data.tradereport && that.auth_data.finance);
			console.log('getdata - 支出记录显示条件:', that.wxauth_data.includes && that.wxauth_data.includes('expend'));
			});
		}
  }
};
</script>
<style>
@import "../common.css";
page{background: #fff;}
.surverycontent{width: 100%;padding:20rpx 24rpx 30rpx;display:flex;flex-wrap:wrap;background: #fff;padding-top:20rpx;display:flex;justify-content: space-between;}
.surverycontent .item{width:340rpx;background: linear-gradient(to right,#ddeafe,#e6effe);margin-bottom:20rpx;padding:26rpx 30rpx;display:flex;flex-direction:column;border-radius:20rpx;}
.surverycontent .item .t1{width: 100%;color: #121212;font-size:24rpx;display: flex;align-items: center;justify-content: space-between;}
.surverycontent .item .t1 image{width: 25rpx;height: 25rpx;}
.surverycontent .item .t2{width: 100%;color: #222;font-size:36rpx;font-weight:bold;overflow-wrap: break-word;display: flex;align-items: flex-end;justify-content: flex-start;padding: 15rpx 0rpx;}
.surverycontent .item .t2 .price-unit{font-size: 24rpx;color: #222;font-weight:none;padding-bottom: 6rpx;margin-left: 5rpx;}
.surverycontent .item .t3{width: 100%;color: #999;font-size:24rpx;display: flex;align-items: center;flex-wrap: wrap;}
.surverycontent .item .t3:nth-first{margin-bottom: 10rpx;}
.surverycontent .item .t3 .price-color{color: #0060FF;display: flex;align-items: center;display: flex;align-items: center;}
.surverycontent .item .t3 .price-color image{width: 20rpx;height: 24rpx;margin-left: 10rpx;}
.listcontent{width: 100%;padding:0 40rpx;background: #fff;margin-bottom: 20rpx;}
.listcontent .title-view{position: relative;color: #242424;font-size: 30rpx;text-align: center;padding: 40rpx 0rpx 28rpx;font-weight: bold;}
.listcontent .title-view::before{content:" ";width:120rpx;height: 8rpx;border-radius: 8rpx;background: rgba(50, 143, 255, 0.2);display: block;position: absolute;left: 50%;margin-left: -60rpx;top: 68rpx;}
.list{ width: 100%;background: #fff;}
.divider-line{width: 670rpx;height: 8rpx;background: #F2F3F4;margin: 20rpx 0rpx;}
.list {margin-bottom: 20rpx;}
.list .item{ height:100rpx;line-height:100rpx;display:flex;align-items:center;}
.list .f1{width:56rpx;height:56rpx;line-height:56rpx;display:flex;align-items:center}
.list .f1 image{ width:56rpx;height:56rpx;}
.list .f1 span{ width:40rpx;height:40rpx;font-size:40rpx}
.list .f2{font-size: 28rpx;color:#222;font-weight: bold;margin-left: 20rpx;}
.list .f3{ color: #979797;text-align:right;flex:1;font-size: 24rpx;margin-right: 20rpx;display: flex;justify-content:flex-end;}
.list .f3-price{color: #2A6DF7;}
.list .f3 .f3-tisp{width: 28rpx;height: 28rpx;background: #EB4237;color: #fff;font-size: 24rpx;border-radius: 50%;text-align: center;line-height: 28rpx;}
.list .f4{ width: 40rpx; height: 40rpx;}
.navigation {width: 100%;padding-bottom:10px;overflow: hidden;}
.navcontent {display: flex;align-items: center;padding-left: 10px;}
.header-location-top{position: relative;display: flex;justify-content: center;align-items: center;flex:1;}
.header-back-but{position: absolute;left:0;display: flex;align-items: center;width: 40rpx;height: 45rpx;overflow: hidden;}
.header-back-but image{width: 40rpx;height: 45rpx;} 
.header-page-title{font-size: 36rpx;font-weight: bold;}

/* 时间范围选择 */
.time-select {
  display: flex;
  align-items: center;
  padding: 20rpx 40rpx;
  background: linear-gradient(to right, #f0f8ff, #e6f3ff);
  border-radius: 16rpx;
  margin: 20rpx 40rpx;
  box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.05);
}

.time-label {
  font-size: 28rpx;
  font-weight: bold;
  color: #2c5aa0;
  margin-right: 20rpx;
}

.time-picker {
  flex: 1;
  background: #fff;
  border-radius: 12rpx;
  padding: 18rpx 24rpx;
  border: 2rpx solid #d0e6ff;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.08);
  transition: all 0.3s ease;
}

.time-picker:active {
  transform: scale(0.98);
  box-shadow: 0 1rpx 4rpx rgba(0, 0, 0, 0.1);
}

.picker-value {
  font-size: 28rpx;
  color: #333;
  text-align: center;
  font-weight: 500;
}
</style>
