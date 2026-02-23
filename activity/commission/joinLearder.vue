<template>
	<view class="flex flex-col flex-xy-center" style="height: 100vh;">
		<view class="tips">
			您的朋友{{options.name}}邀请您成为团长，以后可以参与本平台优惠购物，享受更多特权。
		</view>
		<button class="join-btn" @click="inviteBtn">确认参与</button>
	</view>
</template>

<script>
	const app = getApp()
	export default {
		data() {
			return {
				options:{}
			}
		},
		onLoad(options) {
			if(!options.uid || !options.name || !options.invite_id)
			{
				uni.showModal({
					title:'友情提示',
					content:'邀请参数不能为空',
					showCancel:false,
					success() {
						uni.navigateTo({
							url:'/pages/index/index'
						})
					}
				})
				return
			}
			this.options = options
		},
		methods: {
			inviteBtn(){
				uni.showLoading({
					title:'加载中...'
				})
				app.post('ApiMy/joinLeader', {invite_id:this.options.invite_id,uid:this.options.uid}, function (res) {
					uni.hideLoading()
					if(!res.status){
						uni.showToast({
							title:res.msg,
							icon:'none'
						})
						return false;
					}
					uni.navigateTo({
						url:'/pages/index/index'
					})					
				})
			}							
		}
	}
</script>

<style>
	.tips{
		width: 90%;
		font-size: 36rpx;
		margin-bottom: 100rpx;
	}
.join-btn{
	width: 90%;
	    background: linear-gradient(90deg, #FF3143 0%, #FE6748 100%);
	    color: #fff;
	    font-size: 30rpx;
	    border-radius: 16rpx;
		height: 100rpx;
	    display: flex;
	    align-items: center;
	    justify-content: center;
}
</style>
