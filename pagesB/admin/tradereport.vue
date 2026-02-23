<template>
<view class="container">
	<block v-if="isload">
		<!-- 时间范围选择 -->
		<view class="time-select"><view class="time-label">时间范围：</view>
			<picker @change="timeRangeChange" :range="timeRangeList" :value="timeRangeIndex" class="time-picker">
				<view class="picker-value">{{timeRangeList[timeRangeIndex]}}</view>
			</picker>
		</view>
		
		<view class="search flex" >
			<view class="paytype" @tap="choosePaytype"><input type="text" placeholder="支付类型" name="time" :value="paytypestr"></view>
			<uni-datetime-picker v-model="datetimerange" type="datetimerange" @change="timeChange" rangeSeparator="至" />
		</view>
		<view class="content" >
			<view class="item">
				<view style="line-height: 60rpx;">开始时间：{{data.logintime}}</view>
				<view style="line-height: 60rpx;">结束时间：{{data.jiaobantime}}</view>
			</view>
			<view class="item">
				<view class="title">订单数量：{{data.total_ordercount}}</view>
				<view class="flex item-view">
					<view>收银机订单数：{{data.cashdesk_ordercount}}</view>
					<view>线上订单数：{{data.online_ordercount}}</view>
				</view>
			</view>
			<view class="item">
				<view class="title">收银机营业额：￥{{data.today_total_money}}</view>
				<view class="flex item-view">
					<view v-show="(paytypeid =='0' || paytypeid=='') && parseFloat(data.today_cash_money) > 0">现金支付：￥{{data.today_cash_money}}</view>
					<view v-show="(paytypeid =='1' || paytypeid=='') && parseFloat(data.today_yue_money) > 0">余额支付：￥{{data.today_yue_money}}</view>
					<view v-show="(paytypeid =='2' || paytypeid=='') && parseFloat(data.today_wx_money) > 0">微信支付：￥{{data.today_wx_money}}</view>
					<view v-show="(paytypeid =='3' || paytypeid=='') && parseFloat(data.today_alipay_money) > 0">支付宝支付：￥{{data.today_alipay_money}}</view>
					<view v-show="(paytypeid =='81' || paytypeid=='') && parseFloat(data.today_sxf_money) > 0">随行付支付：￥{{data.today_sxf_money}}</view>
					<block v-if="data.today_custom_pay_list && typeof data.today_custom_pay_list === 'object' && Object.keys(data.today_custom_pay_list).length >0">
					<view v-for="(item,index) in data.today_custom_pay_list" v-if="(paytypeid == item.paytypeid || paytypeid=='') && parseFloat(item.money) > 0">{{item.title}}支付：{{item.money}}</view>
				</block>
					<block v-if="data.cashpay_show && (paytypeid =='0' || paytypeid=='' )">
						<view v-if="parseFloat(data.mix_wx_pay) > 0">混合支付微信：￥{{data.mix_wx_pay}}</view>
						<view v-if="parseFloat(data.mix_alipay_pay) > 0">混合支付支付宝：￥{{data.mix_alipay_pay}}</view>
						<view v-if="data.sxfpay_show && parseFloat(data.mix_sxf_pay) > 0">混合支付随行付：￥{{data.mix_sxf_pay}}</view>
					</block>
				</view>
			</view>
			<view class="item">
				<view class="title">线上营业额：￥{{data.online_total_money}}</view>
				<view class="flex item-view">
					<view v-show="(paytypeid =='1' || paytypeid=='') && parseFloat(data.online_yue_money) > 0">余额支付：￥{{data.online_yue_money}}</view>
					<view v-show="(paytypeid =='2' || paytypeid=='') && parseFloat(data.online_wx_money) > 0">微信线上支付：￥{{data.online_wx_money}}</view>
					<view v-show="(paytypeid =='3' || paytypeid=='') && parseFloat(data.online_alipay_money) > 0">支付宝线上支付：￥{{data.online_alipay_money}}</view>
					<view v-show="(paytypeid =='0' || paytypeid=='') && parseFloat(data.online_admin_money) > 0">后台补录：￥{{data.online_admin_money}}</view>
				</view>
			</view>
			<view class="item">
				<view class="title">优惠金额（不参与其他统计）：-￥{{data.youhui_total}}</view>
				<view class="flex item-view">
					
				</view>
			</view>
			<view class="item" v-if="data.recharge_show">
				<view class="title">会员储值 (预付款)收款小计：￥{{data.recharge_total_money}}</view>
				<view class="flex item-view">
					<view v-show="(paytypeid =='0' || paytypeid=='') && parseFloat(data.recharge_cash_money) > 0">现金支付：￥{{data.recharge_cash_money}}</view>
					<view v-show="(paytypeid =='2' || paytypeid=='') && parseFloat(data.recharge_wx_money) > 0">微信线上支付：￥{{data.recharge_wx_money}}</view>
					<view v-show="(paytypeid =='3' || paytypeid=='') && parseFloat(data.recharge_alipay_money) > 0">支付宝线上支付：￥{{data.recharge_alipay_money}}</view>
					<view v-show="data.sxfpay_show && (paytypeid =='81' || paytypeid=='') && parseFloat(data.recharge_sxf_money) > 0">随行付：￥{{data.recharge_sxf_money}}</view>
				</view>
			</view>
			<view class="item">
				<view class="title">退款总额：￥{{data.refund_total_money}}</view>
				<view class="flex item-view">
					<view v-show="(paytypeid =='0' || paytypeid=='') && parseFloat(data.refund_cash_money) > 0">现金退款：￥{{data.refund_cash_money}}</view>
					<view v-show="(paytypeid =='1' || paytypeid=='') && parseFloat(data.refund_yue_money) > 0">余额退款：￥{{data.refund_yue_money}}</view>
					<view v-show="(paytypeid =='2' || paytypeid=='') && parseFloat(data.refund_wx_money) > 0">微信退款：￥{{data.refund_wx_money}}</view>
					<view v-show="(paytypeid =='3' || paytypeid=='') && parseFloat(data.refund_alipay_money) > 0">支付宝退款：￥{{data.refund_alipay_money}}</view>
					<view v-show="data.sxfpay_show && (paytypeid =='81' || paytypeid=='') && parseFloat(data.refund_sxf_money) > 0">随行付退款：￥{{data.refund_sxf_money}}</view>
					<block v-if="data.refund_custom_list && typeof data.refund_custom_list === 'object' && Object.keys(data.refund_custom_list).length >0">
					<view v-for="(item,index) in data.refund_custom_list" v-if="(paytypeid == item.paytypeid || paytypeid=='') && parseFloat(item.refund_money) > 0">{{item.title}}退款：{{item.refund_money}}</view>
				</block>
					<block v-if="data.cashpay_show && (paytypeid =='0' || paytypeid=='' )">
						<view v-if="parseFloat(data.mix_refund_wx_pay) > 0">混合支付微信退款：￥{{data.mix_refund_wx_pay}}</view>
						<view v-if="parseFloat(data.mix_refund_alipay_pay) > 0">混合支付支付宝退款：￥{{data.mix_refund_alipay_pay}}</view>
						<view v-if="data.sxfpay_show && parseFloat(data.mix_refund_sxf_pay) > 0">混合支付随行付退款：￥{{data.mix_refund_sxf_pay}}</view>
					</block>
				</view>
			</view>
			<view class="item">
				<view class="title">汇总（以下数据均扣除退款金额）</view>
				<view class="flex item-view">
					<view v-if="parseFloat(data.all_yingyee_money) > 0">营业额汇总（包含会员储值与会员消费）：￥{{data.all_yingyee_money}}</view>
					<view v-if="parseFloat(data.yingyee_money) > 0">营业额汇总（仅不含会员储值）：￥{{data.yingyee_money}}</view>
					<view v-if="parseFloat(data.total_in_money) > 0">收款汇总（仅不含会员余额消费）：￥{{data.total_in_money}}</view>
					<!-- <view >收款汇总：￥{{data.all_total_in_money}}</view> -->
					<view v-if="parseFloat(data.all_yue_money) > 0">余额支付汇总（线上+线下）：￥{{data.all_yue_money}}</view>
				</view>
			</view>
		</view>
		<view style="height: 80rpx;"></view>
		<button class="savebtn" @tap="wifiPrint" :style="'background:linear-gradient(90deg,'+t('color1')+' 0%,rgba('+t('color1rgb')+',0.8) 100%)'" >打印</button>
	</block>
	<view v-if="showPaytype" class="popup__container">
		<view class="popup__overlay" @tap.stop="hidePaytypeDialog"></view>
		<view class="popup__modal">
			<view class="popup__title">
				<text class="popup__title-text">请选择支付方式</text>
				<image :src="pre_url+'/static/img/close.png'" class="popup__close" style="width:36rpx;height:36rpx" @tap.stop="hidePaytypeDialog"/>
			</view>
			<view class="popup__content">
				<view class="pstime-item" :key="index" @tap="paytypeRadioChange" :data-index="-1">
					<view class="flex1">全部</view>
					<view class="radio" :style="''"><image class="radio-img" :src="pre_url+'/static/img/checkd.png'"/></view>
				</view>
				<view class="pstime-item" v-for="(item, index) in paytypelist" :key="index" @tap="paytypeRadioChange" :data-index="index">
					<view class="flex1">{{item.title}}</view>
					<view class="radio" :style="''"><image class="radio-img" :src="pre_url+'/static/img/checkd.png'"/></view>
				</view>
			</view>
		</view>
	</view>
	</block>
	<loading v-if="loading"></loading>
</view>
</template>

<script>
var app = getApp();
import uniDatetimePicker from './uni-datetime-picker/uni-datetime-picker.vue'
export default {
	components: {
		uniDatetimePicker
	},
	data() {
			return {
				opt:{},
				loading:false,
				isload: false,
				menuindex:-1,
				pre_url:app.globalData.pre_url,
				data: {
					today_custom_pay_list: {},
					refund_custom_list: {}
				},
				paytypestr :'支付方式',
				showPaytype:false,
				paytypelist:[],
				paytypeid:'',
				// 时间范围选择
				timeRangeList: ['当天', '当周', '当月', '当季', '当年', '全部'],
				timeRangeIndex: 0, // 默认选中当天
				timeRange: 'today', // 默认时间范围
				// 日期时间范围选择
				datetimerange: [],
				starttime:'',
				endtime:'',
				rangetime:''
			};
		},
	onLoad: function (opt) {
		this.opt = app.getopts(opt);
		this.getpaytype();
		this.getdata();
	},
	onPullDownRefresh: function () {
		this.getdata();
	},
  methods: {
	// 时间范围选择变化
	timeRangeChange: function(e) {
		this.timeRangeIndex = e.detail.value;
		const timeRangeMap = ['today', 'week', 'month', 'quarter', 'year', 'all'];
		this.timeRange = timeRangeMap[this.timeRangeIndex];
		this.datetimerange = []; // 清空日期时间范围，优先使用时间范围选择
		this.getdata(); // 重新获取数据
	},
	// 日期时间范围变化
	timeChange:function(){
		this.timeRangeIndex = 0; // 重置时间范围选择，优先使用日期时间范围
		this.timeRange = 'today'; // 重置时间范围值
		this.getdata();
	},
    getdata: function () {
	var that = this;	
	that.loading = true;
	var paytypeid = that.paytypeid;
	var params = {paytypeid: paytypeid, isprint:0};
	// 根据情况使用不同的时间参数
	if (that.datetimerange && that.datetimerange.length > 0) {
		params.ctime = that.datetimerange;
	} else {
		params.time_range = that.timeRange;
	}
	app.post('ApiAdminFinance/gettradereport', params, function (res) {
		var data = res.data;
		that.data = data;
		that.loading = false;
		that.loaded();
      });
    },
	getpaytype:function(){
		var that = this;	
		app.post('ApiAdminFinance/getpaytypelist', {}, function (res) {
			that.loading = false;
			that.paytypelist =  res.data;		
		});
	},
	choosePaytype:function(){
		this.showPaytype = true;
	},
	hidePaytypeDialog:function(){
		this.showPaytype = false;
	},
	paytypeRadioChange:function(e){
		var index = e.currentTarget.dataset.index;
		console.log(index);
		if(index >=0){
			var patypedata = this.paytypelist[index];
			this.paytypeid = patypedata['id'];
			this.paytypestr = patypedata['title'];
		}else{
			this.paytypeid = '';
			this.paytypestr = '全部'
		}
	
		this.showPaytype = false;
		this.getdata();
	},
	wifiPrint:function(){
		var that = this;
		that.loading = true;
		var paytypeid = that.paytypeid;
		var params = {paytypeid: paytypeid, isprint:1};
		// 根据情况使用不同的时间参数
		if (that.datetimerange && that.datetimerange.length > 0) {
			params.ctime = that.datetimerange;
		} else {
			params.time_range = that.timeRange;
		}
		app.post('ApiAdminFinance/gettradereport', params, function (res) {
			that.loading = false;
			app.success(res.msg);
		});
	}
  }
};
</script>
<style>
.container{padding: 20rpx;}
.search{justify-content: flex-start;margin-bottom: 20rpx;}
.search .paytype{ width: 40%;overflow:hidden;border-radius: 4px;border: 1px solid #e5e5e5}
.search .paytype input{height: 70rpx;line-height: 70rpx;text-align: center;background-color: #fff;color: #666;font-size: 28rpx;}
.search .timesearch{width: 40%;}
.content{ width:94%;margin:0 3%;}
.content .item{margin-bottom: 20rpx;}
.content .item .title{font-size: 32rpx;font-weight: 700;line-height: 70rpx;border-bottom: 1rpx solid #bdbdbd;margin-bottom: 10rpx;}
.content .item .item-view {flex-wrap: wrap;justify-content: space-between}
.content .item .item-view view{line-height: 60rpx;padding: 10rpx 10rpx;min-width: 40%;}

.pstime-item{display:flex;border-bottom: 1px solid #f5f5f5;padding:20rpx 30rpx;}
.pstime-item .radio{flex-shrink:0;width: 32rpx;height: 32rpx;background: #FFFFFF;border: 2rpx solid #BFBFBF;border-radius: 50%;margin-right:30rpx}
.pstime-item .radio .radio-img{width:100%;height:100%}
.savebtn{ width: 90%; height:80rpx; line-height: 80rpx; text-align:center;border-radius:8rpx; color: #fff;font-weight:bold;margin: 0 5%; border: none; position: fixed; bottom: 30rpx;}

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