<template>
<view class="container">
	<block v-if="isload">
		<view v-if="show_rebate" class="container-top">
			<view class="top-content">
					<text class="t1" >待返{{t('余额')}}：{{info.un_send_money}} </text>
					<text class="t1" >已返{{t('余额')}}：{{info.send_money}} </text>
			</view>
			<view class="top-content">
				<text class="t1" >待返{{t('佣金')}}：{{info.un_send_commission}} </text>
				<text class="t1" >已返{{t('佣金')}}：{{info.send_commission}} </text>
			</view>
			<view class="top-content">
				<text class="t1" >待返{{t('积分')}}：{{info.un_send_score}} </text>
				<text class="t1" >已返{{t('积分')}}：{{info.send_score}} </text>
			</view>
		</view>

		<dd-tab :class="show_rebate?'container-tab':''" :itemdata="['全部','进行中','已结束']" :itemst="['all','1','2']" :st="st" :isfixed="true" @changetab="changetab"></dd-tab>
		<view :style="show_rebate?'height:260rpx;width:100%;':'height:100rpx;width:100%;'"></view>
		<view class="order-content">
			<block v-for="(item, index) in datalist" :key="index">
			<view class="order-box"  @tap.stop="goto" :data-url="'recordlog?cashback_id=' + item.cashback_id+'&pro_id=' + item.pro_id" >	
			<view class="head flex flex-y-center">
				<view class="head-top-view flex flex-y-center">
					<image :src="pre_url+'/static/img/ico-shop.png'"></image>
					<view class="head-title">活动名称：{{item.name}}</view>
				</view>
				<text class="st0">{{item.status}}</text>
			</view>
			<view class="content flex-col">
				<view class="flex-y-center option-content" v-if="item.cashback_money_max >0" >额度:{{item.cashback_money_max}}</view>
				<view class="flex-y-center option-content" v-else >额度：不限</view>
				<view class="flex-y-center option-content">已返{{item.back_type_name}}：{{item.cashback_num}}</view>
				<view class="flex-y-center option-content"><view class="progress-box"><progress :percent="item.progress" show-info stroke-width="5" activeColor='#ff8758' border-radius='50' /></view></view>
			</view>
			</view>
			</block>
		</view>
		<nomore v-if="nomore"></nomore>
		<nodata v-if="nodata"></nodata>
	</block>
	<loading v-if="loading"></loading>
	<dp-tabbar :opt="opt"></dp-tabbar>
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
      isload: false,
      st: 'all',
      datalist: [],
      pagenum: 1,
      nomore: false,
      nodata: false,
			info:[],
			show_rebate:0,
			pre_url:app.globalData.pre_url,
    };
  },
  onLoad: function (opt) {
		this.opt = app.getopts(opt);
		this.getdata();
  },
	onPullDownRefresh: function () {
		this.getdata();
	},
  onReachBottom: function () {
    if (!this.nodata && !this.nomore) {
      this.pagenum = this.pagenum + 1;
      this.getdata(true);
    }
  },
	onNavigationBarSearchInputConfirmed:function(e){
		this.searchConfirm({detail:{value:e.text}});
	},
  methods: {
    changetab: function (st) {
			this.st = st;
      uni.pageScrollTo({
        scrollTop: 0,
        duration: 0
      });
      this.getdata();
    },
    getdata: function (loadmore) {
			if(!loadmore){
				this.pagenum = 1;
				this.datalist = [];
			}
      var that = this;
      var pagenum = that.pagenum;
      var st = that.st;
			that.nodata = false;
			that.nomore = false;
			that.loading = true;
      app.post('ApiCashback/index', {status: st,pagenum: pagenum}, function (res) {
				that.loading = false;
				that.show_rebate = res.show_rebate;
				that.info = res;
        var data = res.datalist;
        if (pagenum == 1) {
					that.datalist = data;
          if (data.length == 0) {
            that.nodata = true;
          }
					that.loaded();
        }else{
          if (data.length == 0) {
            that.nomore = true;
          } else {
            var datalist = that.datalist;
            var newdata = datalist.concat(data);
            that.datalist = newdata;
          }
        }
      });
    }
  }
};
</script>
<style>
.container{ width:100%;}
.container .container-top{position: fixed;background-color:#fff;width: 100%;padding: 16rpx 3%;color: #333;justify-content: space-between;flex-wrap: wrap;line-height:50rpx;z-index: 11;}
.container .container-top .top-content{ display:flex; justify-content:left;align-items: center;white-space:nowrap;overflow: hidden;margin: 0 30rpx;}
.container .container-top .top-content .t1{text-align: left;width: 50%;overflow: hidden;}
.container .container-tab {top: 156rpx;}
.container .container-tab .dd-tab2{top: 156rpx;}

.order-content{display:flex;flex-direction:column}
.order-box{ width: 94%;margin:10rpx 3%;padding:6rpx 3%; background: #fff;border-radius:8px}
.order-box .head{width:100%; border-bottom: 1px #f4f4f4 solid; height: 70rpx; line-height: 70rpx;justify-content: space-between;}
.order-box .head .head-top-view{color:#333;width:calc(100% - 130rpx);justify-content: flex-start;}
.order-box .head .head-top-view .head-title{width: calc(100% - 40rpx);white-space: nowrap;overflow: hidden;text-overflow: ellipsis;color:#333}
.order-box .head .head-top-view image{width:34rpx;height:34rpx;margin-right:4px}
.order-box .head .st0{ width: 130rpx; color: #ff8758; text-align: right;}
.order-box .content{}
.order-box .content .option-content{padding: 3px 0px;}
.progress-box {width: 100%;}

</style>