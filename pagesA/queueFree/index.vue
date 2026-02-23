<template>
<view class="container">
	<block v-if="isload">
		<view class="tongji flex-y-center "   :style="{background:color1?color1:t('color1')}">
			<view>
				<view class="price">{{set.give_money}}</view>
				<view>累计奖励</view>
			</view>
			<view>
				<view class="price">{{set.sy_money}}</view>
				<view>排队中</view>
			</view>
		</view>
		<view class="tab tab_top" >
			<dd-tab :itemdata="['排队中','已完成']" :itemst="['0','1']" :st="st"  @changetab="changetab"></dd-tab>
		</view>
		<view class="height130" ></view>
		<view class="order-content ">
			<block v-for="(item, index) in datalist" :key="index">
				<view class="order-box">	
					<view class="head">
						<view class="f1">订单号：{{item.ordernum}}</view>
						<view class="flex1"></view>
						<text  class="st0">{{item.statusLabel}}</text>
					</view>
					<view class="content" style="border-bottom:none">
						<view class="detail">
							<text class="t1">商户名称：{{item.bname}}</text>
						</view>
						<view class="detail" v-if="item.title">
							排队名称：<text class="t1" :style="'color:'+t('color1')">{{item.title}}</text>
						</view>
						<view class="detail">
							排队金额：<text class="t1" :style="'color:'+t('color1')">{{item.money}}</text>
						</view>
						<view class="detail" v-if="item.addup_money > 0 && set.back_money_type ==1">
							路上金额：<text class="t1" :style="'color:'+t('color1')">正在路上</text> 
						</view>
						<view class="detail" v-if="!set.back_money_type || (set.back_money_type && item.status ==1 )">
							已获金额：<text class="t1" :style="'color:'+t('color1')">{{item.money_give}}</text> 
							
							<!-- <text v-if="item.status==0 && set.quit_wxhb == 1 && item.money_quit_hb > 0" @tap.stop="quitWithHb" :data-money="item.money_quit_hb" :data-id="item.id" :data-index="index" :style="'color:'+t('color1')+';margin-left: 30rpx;'">退出排队抽红包</text> -->
						</view>
						<view class="detail" v-if="item.queue_no">
							当前排名：<text class="t1" :style="'color:'+t('color1')">{{item.queue_noLabel}}</text>
						</view>
						<view class="detail">
							<text class="t1">排队时间：{{item.createtimeFormat}}</text>
						</view>
					</view>
					<view class="operate flex-y-center flex-x-bottom ">
						<view class="button" v-if="item.status==0 && set.quit_wxhb == 1 && item.money_quit_hb > 0" @tap.stop="quitWithHb" :data-money="item.money_quit_hb" :data-id="item.id" :data-index="index" >退出排队抽红包</view>
						<view class="button" :style="{background:t('color1'),color:'#fff',border:'none'}"  v-if="item.status==0 && set.quit_score == 1 && item.give_score > 0" @tap.stop="quitScore"  :data-id="item.id" :data-givescore="item.give_score" :data-index="index" >退出返{{t('积分')}}</view>
						<view class="button" :style="{background:t('color2'),color:'#fff',border:'none'}" v-if="item.status==0 && set.quit_random_score == 1 " @tap.stop="quitRandomScore" :data-money="item.money_quit_hb" :data-id="item.id" :data-index="index" >退出排队抽{{t('积分')}}</view>
					</view>
				</view>
			</block>
		</view>
		<nomore v-if="nomore"></nomore>
		<nodata v-if="nodata"></nodata>
	</block>
	<!-- 红包区域 -->
	<view class="page-view-hongbao" v-if="prizeVisible" @touchmove.stop.prevent="() => {}" @mousewheel.prevent>
		<!-- 三个红包 -->
		<view class="hongbao-view">
			<view class="wrap">
				<view class="envelope" @tap="receive">
					<image :src="`${pre_url}/static/img/envelope.png`" mode="aspectFill" class="cover" />
					<image :src="`${pre_url}/static/img/btn.png`" mode="aspectFill" class="btn" />
				</view>
			</view>
			<view class="wrap">
				<view class="envelope" @tap="receive">
					<image :src="`${pre_url}/static/img/envelope.png`" mode="aspectFill" class="cover" />
					<image :src="`${pre_url}/static/img/btn.png`" mode="aspectFill" class="btn" />
				</view>
			</view>
			<view class="wrap">
				<view class="envelope" @tap="receive">
					<image :src="`${pre_url}/static/img/envelope.png`" mode="aspectFill" class="cover" />
					<image :src="`${pre_url}/static/img/btn.png`" mode="aspectFill" class="btn" />
				</view>
			</view>
			<!-- 关闭弹窗按钮  -->
            <view class="hongbao-view-close" @click="hbclose">
                <image v-if="!closeImgError" :src="pre_url+'/static/img/close2.png'" @error="closeImgError=true"></image>
                <text v-else style="font-size:40rpx;color:#fff;line-height:60rpx">×</text>
            </view>
		</view>
		<uni-popup ref="popup" @change="">
			<view class="cl-popup">
                <view class="main">
                    <image v-if="!popupTopErr" :src="`${pre_url}/static/img/popup-top.png`" mode="aspectFill" class="top" @error="popupTopErr=true" />
                    <image v-if="!popupIconErr" :src="`${pre_url}/static/img/popup-icon.png`" mode="aspectFill" class="icon" @error="popupIconErr=true" />
                    <image v-if="!popupBottomErr" :src="`${pre_url}/static/img/popup-bottom.png`" mode="aspectFill" class="bottom" @error="popupBottomErr=true" />
					<view class="content">
						<view class="price">
							<text class="num">{{hbmoney}}</text>
							<text class="unit">元</text>
						</view>
						<!-- 标题 -->
						<view class="title"> {{hbtext}} </view>
						<!-- 领取按钮 -->
						<view class="cl-button"  @tap="hbsuccess">
							<text>确定</text>
						</view>
					</view>
				</view>
			</view>
		</uni-popup>
	</view>
	
	
	<view class="page-view-hongbao" v-if="randomScoreVisible" @touchmove.stop.prevent="() => {}" @mousewheel.prevent>
		<!-- 三个宝箱 -->
		<view class="hongbao-view" style="top: 15%;">
			<view class="wrap" style="width: 33%;height: 310rpx;" v-for="(item,index) in 3">
				<view class="envelope" @tap="receiveRandomScore">
					<image :src="`${pre_url}/static/img/queuefree/baoxiang.gif`" mode="aspectFill" class="cover" />
				</view>
			</view>
			<!-- 关闭弹窗按钮  -->
            <view class="hongbao-view-close" style="bottom: -150rpx;" @click="randomScoreclose">
                <image v-if="!closeImgError2" :src="pre_url+'/static/img/close2.png'" @error="closeImgError2=true"></image>
                <text v-else style="font-size:40rpx;color:#fff;line-height:60rpx">×</text>
            </view>
		</view>
		<uni-popup ref="popup" @change="">
			<view class="cl-popup">
                <view class="main">
                    <image v-if="!popupTopErr2" :src="`${pre_url}/static/img/popup-top.png`" mode="aspectFill" class="top" @error="popupTopErr2=true" />
                    <image v-if="!popupIconErr2" :src="`${pre_url}/static/img/popup-icon.png`" mode="aspectFill" class="icon" @error="popupIconErr2=true" />
                    <image v-if="!popupBottomErr2" :src="`${pre_url}/static/img/popup-bottom.png`" mode="aspectFill" class="bottom" @error="popupBottomErr2=true" />
					<view class="content">
						<view class="price">
							<text class="num">{{randomScore}}</text>
							<text class="unit">{{t('积分')}}</text>
						</view>
						<!-- 标题 -->
						<view class="title"> 奖励将发放到个人{{t('积分')}}账户 </view>
						<!-- 领取按钮 -->
						<view class="cl-button" @tap="randomScoreclose">
							<text >确定</text>
						</view>
					</view>
				</view>
			</view>
		</uni-popup>
		
	</view>
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
			menuindex:-1,

      st: '0',
      datalist: [],
			set:{},
			hbmoney:0,
			hbtext:'奖励将发送到微信零钱或余额中',
			tempid:0,
			tempindex:0,
      pagenum: 1,
      nomore: false,
      nodata: false,
			keyword:'',
			prizeVisible: false,//是否展示红包
			pre_url:app.globalData.pre_url,
      randomScoreVisible:false,//随机积分弹窗
      randomScore:0,//获得的积分
      closeImgError:false,
      popupTopErr:false,
      popupIconErr:false,
      popupBottomErr:false,
      closeImgError2:false,
      popupTopErr2:false,
      popupIconErr2:false,
      popupBottomErr2:false,
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
		// 红包关闭
		hbclose(){
			this.prizeVisible = false;
		},
		hbshow(){
			this.prizeVisible = true;
		},
		hbsuccess(){
			this.prizeVisible = false;
			let datalist = this.datalist;
			let tempindex = this.tempindex;
			datalist.splice(tempindex, 1);
			this.tempindex = datalist;
		},
		// 领取红包
		receive() {
			let that = this;
			that.loading = true;
			app.post('ApiQueueFree/quitHb', {id: that.tempid}, function (res) {
				that.loading = false;
			  if(res.status == 0){
					app.alert(res.msg);
					that.hbclose()
					return;
				}
				that.hbmoney = res.data.random_money;
				that.$refs.popup.open('center');
			});
		},
		quitWithHb(e) {
			let that = this;
			let tempid = e.currentTarget.dataset.id;
			let money = e.currentTarget.dataset.money;
			that.tempid = tempid;
			that.tempindex = e.currentTarget.dataset.index;
			that.hbshow();
		},
		quitRandomScore(e) {
			let that = this;
			let tempid = e.currentTarget.dataset.id;
			that.tempid = tempid;
			that.randomScoreVisible = true;
		},
		receiveRandomScore(){
			var that = this;
			var tempid = that.tempid;
			that.loading = true;
			app.post('ApiQueueFree/randomScore', {id:tempid}, function (res) {
				that.loading = false;
				that.randomScore = res.data.random_score;
				console.log(that.randomScore,'that.randomScore');
				that.$refs.popup.open('center');
			});
		},
		randomScoreclose(){
			this.randomScoreVisible = false;
			this.getdata();
		},
		
		quitScore(e){
			let that = this;
			var givescore = e.currentTarget.dataset.givescore;
			console.log(givescore);
			var msg = '退出排队后您将获得'+givescore+that.t('积分')+'，确定退出吗？';
			var id = e.currentTarget.dataset.id;
			app.confirm(msg, function () {
				app.showLoading('提交中');
				app.post('ApiQueueFree/quitscore', {id: id}, function (data) {
					app.showLoading(false);
					app.success(data.msg);
					setTimeout(function () {
						that.getdata();
					}, 1000);
				});
			});
		},
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
      app.post('ApiQueueFree/index', {status: st,pagenum: pagenum,keyword:that.keyword}, function (res) {
				that.loading = false;
				uni.setNavigationBarTitle({
					title: that.t('排队返现')
				});
        var data = res.datalist;
        if (pagenum == 1) {
					that.datalist = data;
					that.set = res.set;
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
.order-content{display:flex;flex-direction:column}
.order-box{ width: 94%;margin:10rpx 3%;padding:6rpx 3%; background: #fff;border-radius:8px}
.order-box .head{ display:flex;width:100%; border-bottom: 1px #f4f4f4 solid; height: 70rpx; line-height: 70rpx; overflow: hidden; color: #999;}
.order-box .head .f1{display:flex;align-items:center;color:#333}
.order-box .head .f1 image{width:34rpx;height:34rpx;margin-right:4px}
.order-box .head .st0{ width: 140rpx; color: #ff8758; text-align: right; }
.order-box .content {line-height: 180%;}
.page-view-hongbao {width: 100%;height: 100%;position: fixed;top:0;left: 0;z-index: 999;background-color: rgba(0, 0, 0, 0.4);overscroll-behavior-y: contain !important;}
.page-view-hongbao .hongbao-view{width: 100%;margin: 266rpx auto;	display: flex;align-items: center;justify-content: space-around;position: relative;}
.hongbao-view .wrap {display: flex;justify-content: center;position: relative;width: 25%;height: 400rpx;overflow: hidden;}
.hongbao-view .wrap .cover{width: 100%;height:230rpx;}
.hongbao-view .wrap .envelope {position: relative;top: 70rpx;animation: envelope-animation 1.8s ;width: 100%;}
@keyframes envelope-animation {
	0% {
		top: 120rpx;
		transform: scaleY(1);
	}
	20% {
		top: 20rpx;
		transform: scaleY(1);
	}
	70% {
		top: 20rpx;
		transform: scaleY(1);
	}
	80% {
		top: 20rpx;
		transform: scaleY(1);
	}
	90% {
		top: 70rpx;
		transform: scaleY(0.9);
	}
	100% {
		top: 70rpx;
		transform: scaleY(1);
	}
}
.hongbao-view .hongbao-view-close{position: absolute;bottom:-188rpx;border: 2px #fff solid;width: 60rpx;height: 60rpx;border-radius: 50%;display: flex;align-items: center;justify-content: center;}
.hongbao-view .hongbao-view-close image{width: 80%;height: 80%;}
.hongbao-view .wrap .btn {position: absolute;top: 30rpx;left: calc(50% - 40rpx);width: 80rpx;height: 80rpx;animation: btn-animation 0.3s 4;animation-direction: alternate;}
@keyframes btn-animation {from {transform: scale(1);}to {	transform: scale(0.6);}}
.cl-popup {}
.cl-popup .main {position: relative;width: 580rpx;height: 770rpx;}
.cl-popup .top {position: absolute;top: 0;width: 100%;height: 560rpx;}
.cl-popup .icon {position: absolute;top: 324rpx;left: calc(50% - 87rpx);width: 174rpx;height: 178rpx;z-index: 2;}
.cl-popup .bottom {position: absolute;bottom: 0;width: 100%;height: 434rpx;}
.cl-popup .content {display: flex;flex-direction: column;align-items: center;position: absolute;top: 0;left: 0;width: 100%;height: 100%;z-index: 5;}
.cl-popup .price {margin-top: 70rpx;margin-bottom: 300rpx;}
.cl-popup .num {font-size: 122rpx;font-weight: bold;color: #fc5c43;}
.cl-popup .unit {position: relative;left: 10rpx;bottom: 10rpx;font-size: 50rpx;font-weight: 500;color: #fc5c43;}
.cl-popup .title {margin-bottom: 40rpx;font-size: 28rpx;font-weight: 400;color: #ffe0be;}
.cl-popup .cl-button {width: 316rpx;height: 78rpx;background: linear-gradient(180deg, #fff7da 0%, #f3a160 100%);box-shadow: 0 3rpx 6rpx #d12200;border-radius: 50rpx;text-align: center;line-height: 78rpx;}
.cl-popup .cl-button text {font-size: 32rpx;font-weight: bold;color: #f74d2e;}
.tab{position: fixed;width: 100%;}
.height50{width: 100%;height: 50px}
/* 累计奖励 */
.tongji{width: 94%;margin: 30rpx 3%;padding: 30rpx;border-radius: 20rpx; background-color: #F2350D;height: 160rpx;color: #fff;position: fixed;justify-content: space-evenly;text-align: center;}
.tongji .price{font-size: 40rpx;font-weight: 700;}
.height130{width: 100%;height: 310rpx}
.tab_top{top:210rpx}

.operate{margin-bottom: 10rpx;}
.operate .button{margin-left:20rpx; margin-top: 10rpx;height:60rpx;line-height:60rpx;color:#333;background:#fff;border:1px solid #cdcdcd;border-radius:8rpx;text-align:center;padding: 0 10rpx;font-size: 28rpx;}
</style>
