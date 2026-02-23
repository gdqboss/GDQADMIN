<template>
<view>
	<block v-if="isload">
		<view class="orderinfo">
			<view class="item">
				<text class="t1">ID</text>
				<text class="t2">{{member.id}}</text>
			</view>
			<view class="item">
				<text class="t1">头像</text>
				<view class="t2"><image :src="member.headimg" style="width:80rpx;height:80rpx"></image></view>
			</view>
			<view class="item">
				<text class="t1">昵称</text>
				<text class="t2">{{member.nickname}}</text>
			</view>
			<view class="item">
				<text class="t1">加入时间</text>
				<text class="t2">{{member.createtime}}</text>
			</view>
			<view class="item">
				<text class="t1">{{t('余额')}}</text>
				<text class="t2">{{member.money}}</text>
			</view>
			<view class="item">
				<text class="t1">{{t('积分')}}</text>
				<text class="t2">{{member.score}}</text>
			</view>
			<view class="item">
				<text class="t1">等级</text>
				<text class="t2">{{member.levelname}}</text>
			</view>
		</view>
		<view style="width:100%;height:60rpx"></view>
		<button class="btn" @tap="addscore" :data-id="member.id">{{t('积分')}}消费</button>

		
		<uni-popup id="addscoreDialog" ref="addscoreDialog" type="dialog">
			<uni-popup-dialog mode="input" :title="t('积分')+'消费'" value="" :placeholder="'请输入消费'+t('积分')+'数'" @confirm="addscoreConfirm"></uni-popup-dialog>
		</uni-popup>

	</block>
	<popmsg ref="popmsg"></popmsg>
	<loading v-if="loading"></loading>
</view>
</template>

<script>
var app = getApp();

export default {
  data() {
    return {
      isload: false,
      member: "",
			index2:0,
			ordershow:false
    };
  },
  onLoad: function (opt) {
		this.opt = app.getopts(opt);
		this.getdata();
  },
	onPullDownRefresh: function () {
		this.getdata();
	},
  methods: {
		getdata:function(){
			var that = this;
			that.loading = true;
			app.post('ApiAdminMember/detail', {mid: that.opt.mid}, function (res) {
				that.loading = false;
				that.member = res.member;
				that.ordershow = res.ordershow
				uni.setNavigationBarTitle({
					title: that.t('会员') + '信息'
				});

				that.loaded();
			});
		},
    addscore: function (e) {
      this.$refs.addscoreDialog.open();
    },
   addscoreConfirm: function (done,value) {
			this.$refs.addscoreDialog.close();
      var that = this;
      app.post('ApiAdminMember/decscore', {rechargemid:that.opt.mid,rechargescore:value*-1}, function (res) {
				if (res.status == 0) {
				  app.error(res.msg);
				  return;
				}
        // app.success(res.msg);
      });
    },
  }
};
</script>
<style>
.orderinfo{ width:94%;margin:20rpx 3%;border-radius:16rpx;padding: 14rpx 3%;background: #FFF;}
.orderinfo .item{display:flex;width:100%;padding:20rpx 0;border-bottom:1px dashed #ededed;}
.orderinfo .item:last-child{ border-bottom: 0;}
.orderinfo .item .t1{width:200rpx;}
.orderinfo .item .t2{flex:1;text-align:right}
.orderinfo .item .red{color:red}

.btn{ height: 88rpx;line-height: 88rpx;background: #FC4343;width:90%;margin:0 auto;border-radius:8rpx;margin-top:60rpx;color: #fff;font-size: 36rpx;}

.bottom{ width: 100%; padding: 16rpx 20rpx;background: #fff; position: fixed; bottom: 0px; left: 0px;display:flex;justify-content:flex-end;align-items:center;}
.bottom .btn{ border-radius:10rpx; padding:10rpx 16rpx;margin-left: 10px;border: 1px #999 solid;}


.uni-popup-dialog {width: 300px;border-radius: 5px;background-color: #fff;}
.uni-dialog-title {display: flex;flex-direction: row;justify-content: center;padding-top: 15px;padding-bottom: 5px;}
.uni-dialog-title-text {font-size: 16px;font-weight: 500;}
.uni-dialog-content {display: flex;flex-direction: row;justify-content: center;align-items: center;padding: 5px 15px 15px 15px;width:100%}
.uni-dialog-content-text {font-size: 14px;color: #6e6e6e;}
.uni-dialog-button-group {display: flex;flex-direction: row;border-top-color: #f5f5f5;border-top-style: solid;border-top-width: 1px;}
.uni-dialog-button {display: flex;flex: 1;flex-direction: row;justify-content: center;align-items: center;height: 45px;}
.uni-border-left {border-left-color: #f0f0f0;border-left-style: solid;border-left-width: 1px;}
.uni-dialog-button-text {font-size: 14px;}
.uni-button-color {color: #007aff;}
</style>