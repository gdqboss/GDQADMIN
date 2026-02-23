<template>
	<view class="popmsg" :class="popmsg==1?'popmsg-show':(popmsg==2?'popmsg-hide':'')" @tap="gotourl" :data-url="tourl">
		<image class="popmsg-pic flex0" :src="logo" v-if="logo"/>
		<view class="popmsg-content">
			<view class="popmsg-content-title">{{title}}</view>
			<view class="popmsg-content-desc" v-if="desc">{{desc}}</view>
		</view>
		<view class="popmsg-close" @tap.stop="close"><image :src="pre_url+'/static/img/close2.png'" class="popmsg-close-img"/></view>
	</view>
</template>
<script>
	export default {
		props: {
			//popmsg:{default:false},
			//popdata:{default:{}},
			//duration: {type: Number,default: 3000}
		},
		data() {
			return {
				popmsg:0,
				logo:'',
				title:'',
				desc:'',
				tourl:'',
				duration:0,
				pre_url:getApp().globalData.pre_url
			}
		},
		methods: {
			open(data) {
				console.log(data);
				if(data.type=='tokefu'){
					this.logo = data.data.headimg;
					this.title = data.data.nickname+' 发来消息';
					this.desc = data.data.content;
					this.tourl = '/admin/kefu/message?mid='+data.data.mid;
				}else if(data.type=='tokehu'){
					this.logo = data.data.uheadimg;
					this.title = data.data.unickname+' 发来消息';
					this.desc = data.data.content;
					this.tourl = '/pages/kefu/index?bid='+data.data.bid;
				}else if(data.type=='peisong'){
					this.logo = '';
					this.title = data.data.title;
					this.desc = data.data.desc;
					if(data.data.type==1){
						this.tourl = '/activity/peisong/orderlist';
					}else{
						this.tourl = '/activity/peisong/dating';
					}
				}else{
					this.logo = '';
					this.title = data.data.title;
					this.desc = data.data.desc;
					this.tourl = data.data.url;
				}
				this.popmsg = 1;
				const innerAudioContext = uni.createInnerAudioContext();
				innerAudioContext.autoplay = true;
				innerAudioContext.src = this.pre_url + '/static/chat/default.mp3';
				innerAudioContext.onPlay(() => {
					console.log('开始播放');
				});


				if (this.duration === 0) return
				clearTimeout(this.popuptimer)
				this.popuptimer = setTimeout(() => {
					this.close();
				}, this.duration);
			},
			close() {
				this.popmsg = 2;
				clearTimeout(this.popuptimer)
			},
			gotourl(e){
				this.close();
				this.goto(e);
			}
		}
	}
</script>
<style>
.popmsg{width: 100%;height:100rpx;background:rgba(0,0,0,0.7);position: fixed;z-index:999;color:#fff;font-size:28rpx;top:-100rpx;text-align: left;padding:15rpx 20rpx;overflow:hidden;display:none}
.popmsg-show{display:flex;top: 0rpx;animation: popmsg-show 0.2s;animation-timing-function:ease;}
@keyframes popmsg-show{from {top:-100rpx;}to {top:0rpx;}}
.popmsg-hide{top: -100rpx;animation: popmsg-hide 0.2s;animation-timing-function:ease;}
@keyframes popmsg-hide{from {top:0rpx;}to {top:-100rpx;}}

.popmsg-pic{width:70rpx;height:70rpx;border-radius:8rpx;margin-right:10rpx;}
.popmsg-content{flex:1;display:flex;flex-direction:column;justify-content:center;overflow:hidden}
.popmsg-content-title{height:40rpx;line-height:40rpx;font-weight:bold;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:1;overflow:hidden;}
.popmsg-content-desc{color:#eee;height:30rpx;line-height:30rpx;font-size:20rpx;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:1;overflow:hidden;}
.popmsg-close{margin-top:6rpx;width:50rpx;height:50rpx;border-radius:50%;border:1px solid #aaa;display:flex;align-items:center;justify-content:center;opacity:0.7}
.popmsg-close-img{width:22rpx;height:22rpx}
</style>