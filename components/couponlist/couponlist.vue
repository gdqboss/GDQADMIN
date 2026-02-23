<template>
<view class="couponlist">
	<view v-for="(item, index) in couponlist" :key="index" class="coupon" :style="couponstyle">
		<view :class="item.type==1?'pt_img1':'pt_img2'"></view>
		<view class="pt_left" @tap="goto" :data-url="'/pagesExt/coupon/coupondetail?'+(choosecoupon?'rid':'id')+'=' + item.id">
			<view class="pt_left-content">
				<view class="f1" v-if="item.type==1" :style="{color:t('color1')}"><text class="t0">￥</text><text class="t1">{{item.money}}</text></view>
				<view class="f1" v-if="item.type==10" :style="{color:t('color1')}"><text class="t1">{{item.discount/10}}</text><text class="t0">折</text></view>
				<view class="f1" v-if="item.type==2" :style="{color:t('color1')}">礼品券</view>
				<view class="f1" v-if="item.type==3" :style="{color:t('color1')}"><text class="t1">{{item.limit_count}}</text><text class="t2">次</text></view>
				<view class="f1" v-if="item.type==4" :style="{color:t('color1')}">抵运费</view>
				<view class="f1" v-if="item.type==11" :style="{color:t('color1')}">兑换券</view>
						<view class="f1" :style="{color:t('color1')}" v-if="item.type==5"><text class="t0">￥</text><text class="t1">{{item.money}}</text></view>
				<view class="f2" v-if="item.type==1 || item.type==4 || item.type==5 || item.type==6">
					<text v-if="item.minprice>0">满{{item.minprice}}元可用</text>
					<text v-else>无门槛</text>
				</view>
			</view>
		</view>
		<view class="pt_right">
			<view class="f1">
				<view class="t1">{{choosecoupon ? item.couponname : item.name}}</view>
				<view style="height: 45rpx;">
					<text class="t2" v-if="item.type==1" :style="{background:'rgba('+t('color1rgb')+',0.1)',color:t('color1')}">代金券</text>
					<text class="t2" v-if="item.type==2" :style="{background:'rgba('+t('color1rgb')+',0.1)',color:t('color1')}">礼品券</text>
					<text class="t2" v-if="item.type==3" :style="{background:'rgba('+t('color1rgb')+',0.1)',color:t('color1')}">计次券</text>
					<text class="t2" v-if="item.type==4" :style="{background:'rgba('+t('color1rgb')+',0.1)',color:t('color1')}">运费抵扣券</text>
					<text class="t2" :style="{background:'rgba('+t('color1rgb')+',0.1)',color:t('color1')}" v-if="item.type==5">餐饮券</text>
					<text class="t2" :style="{background:'rgba('+t('color1rgb')+',0.1)',color:t('color1')}" v-if="item.type==6">酒店券</text>
				</view>
				<view class="t3" style="item.bid>0?'margin-top:0':'margin-top:10rpx'">有效期至 {{choosecoupon ? dateFormat(item.endtime) : dateFormat(item.yxqdate)}}</view>
				<view class="t4" v-if="item.bid>0">适用商家：{{item.bname}}</view>
			</view>
			<block v-if="choosecoupon">
				<button class="btn" style="width:160rpx;background:#555" @tap="chooseCoupon" :data-rid="item.id" :data-key="index" v-if="selectedrid==item.id || inArray(item.id,selectedrids)">取消选择</button>
				<button class="btn" style="width:160rpx;" :style="{background:'linear-gradient(270deg,'+t('color1')+' 0%,rgba('+t('color1rgb')+',0.8) 100%)'}" @tap="chooseCoupon" :data-rid="item.id" :data-key="index" v-else>选择使用</button>
			</block>
			<block v-else>
				<button class="btn" v-if="item.haveget>=item.perlimit" style="background:#9d9d9d">已领取</button>
				<button class="btn" v-else-if="item.stock<=0" style="background:#9d9d9d">已抢光了</button>
				<button class="btn" v-else :style="{background:'linear-gradient(270deg,'+t('color1')+' 0%,rgba('+t('color1rgb')+',0.8) 100%)'}" @tap="getcoupon" :data-id="item.id" :data-price="item.price" :data-score="item.score" :data-key="index">领取</button>
			</block>
		</view>
	</view>
</view>
</template>
<script>
	var app = getApp();
	export default {
		data(){
			return {

			}
		},
		props: {
			menuindex:{default:-1},
			couponlist:{},
			couponstyle:{default:''},
			bid:{default:''},
			selectedrid:{default:''},
			selectedrids:{type:Array,default(){ return []}},
			choosecoupon:{default:false}
		},
		methods: {
			getcoupon:function(e){
				var that = this;
				var couponlist = that.couponlist;
				var key = e.currentTarget.dataset.key;
				var couponinfo = couponlist[key];
				if (app.globalData.platform == 'wx' && couponinfo && couponinfo.rewardedvideoad && wx.createRewardedVideoAd) {
					app.showLoading();
					if(!app.globalData.rewardedVideoAd[couponinfo.rewardedvideoad]){
						app.globalData.rewardedVideoAd[couponinfo.rewardedvideoad] = wx.createRewardedVideoAd({ adUnitId: couponinfo.rewardedvideoad});
					}
					var rewardedVideoAd = app.globalData.rewardedVideoAd[couponinfo.rewardedvideoad];
					rewardedVideoAd.load().then(() => {app.showLoading(false);rewardedVideoAd.show();}).catch(err => { app.alert('加载失败');});
					rewardedVideoAd.onError((err) => {
						app.showLoading(false);
						app.alert(err.errMsg);
						console.log('onError event emit', err)
						rewardedVideoAd.offLoad()
						rewardedVideoAd.offClose();
					});
					rewardedVideoAd.onClose(res => {
						app.globalData.rewardedVideoAd[couponinfo.rewardedvideoad] = null;
						if (res && res.isEnded) {
							//app.alert('播放结束 发放奖励');
							that.getcouponconfirm(e);
						} else {
							console.log('播放中途退出，不下发奖励');
						}
						rewardedVideoAd.offLoad()
						rewardedVideoAd.offClose();
					});
				}else{
					that.getcouponconfirm(e);
				}
			},
			getcouponconfirm:function(e){
				var that = this
				var id = e.currentTarget.dataset.id;
				var score = e.currentTarget.dataset.score;
				var price = e.currentTarget.dataset.price;
				if(price > 0){
					app.post('ApiCoupon/buycoupon', {id: id}, function (res) {
						if(res.status == 0) {
								app.error(res.msg);
						} else {
							app.goto('/pagesExt/pay/pay?id=' + res.payorderid);
						}
					})
					return;
				}
				if(score > 0){
					app.confirm('确定要消耗'+score+''+that.t('积分')+'兑换吗?',function(){
						app.showLoading('兑换中');
						app.post('ApiCoupon/getcoupon',{id:id},function(data){
							app.showLoading(false);
							if(data.status==0){
								app.error(data.msg);
							}else{
								app.success(data.msg);
								that.$emit('getcoupon');
							}
						});
					})
				}else{
					app.showLoading('领取中');
					app.post('ApiCoupon/getcoupon',{id:id},function(data){
						app.showLoading(false);
						if(data.status==0){
							app.error(data.msg);
						}else{
							app.success(data.msg);
							that.$emit('getcoupon');
						}
					});
				}
			},
			chooseCoupon:function(e){
				var rid = e.currentTarget.dataset.rid
				var key = e.currentTarget.dataset.key
				this.$emit('chooseCoupon',{rid:rid,bid:this.bid,key:key});
			}
		}
	}
</script>
<style>
.couponlist{width:94%;margin:0 3%;padding:20rpx}
.coupon{width:100%;display:flex;margin-bottom:20rpx;border-radius:10rpx;overflow:hidden;border:1px solid #eee}
.coupon .pt_left{background: #fff;height:200rpx;color: #FFF;width:30%;display:flex;flex-direction:column;align-items:center;justify-content:center}
.coupon .pt_left-content{width:100%;height:100%;margin:30rpx 0;border-right:1px solid #EEEEEE;display:flex;flex-direction:column;align-items:center;justify-content:center}
.coupon .pt_left .f1{font-size:40rpx;font-weight:bold;text-align:center;}
.coupon .pt_left .t0{padding-right:0;}
.coupon .pt_left .t1{font-size:60rpx;}
.coupon .pt_left .t2{padding-left:10rpx;}
.coupon .pt_left .f2{font-size:20rpx;color:#4E535B;text-align:center;}
.coupon .pt_right{background: #fff;width:70%;display:flex;height:220rpx;text-align: left;padding:20rpx 0 20rpx 20rpx;position:relative}
.coupon .pt_right .f1{flex-grow: 1;flex-shrink: 1;}
.coupon .pt_right .f1 .t1{font-size:28rpx;color:#2B2B2B;font-weight:bold;height:60rpx;line-height:60rpx;overflow:hidden}
.coupon .pt_right .f1 .t2{display:inline-block;height:36rpx;line-height:36rpx;font-size:20rpx;font-weight:bold;padding:0 16rpx;border-radius:4rpx}
.coupon .pt_right .f1 .t3{font-size:20rpx;color:#999999;height:46rpx;line-height:46rpx;}
.coupon .pt_right .f1 .t4{font-size:20rpx;color:#999999;height:46rpx;line-height:46rpx;max-width: 76%;text-overflow: ellipsis;overflow: hidden;white-space: nowrap;}
.coupon .pt_right .btn{position:absolute;right:20rpx;top:50%;margin-top:-28rpx;border-radius:28rpx;width:140rpx;height:56rpx;line-height:56rpx;color:#fff}
</style>