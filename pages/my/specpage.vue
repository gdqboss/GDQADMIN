<template>
  <view class="container">
    <view class="content">
      <view class="title">认证中心</view>
      <view class="btn" @tap="goto" data-url="/pagesExt/my/setrealname">实名认证</view>
    </view>
    <loading v-if="loading"></loading>
  </view>
</template>
<script>
var app = getApp();
export default {
  data(){
    return {
      opt:{},
      loading:false,
      pre_url:app.globalData.pre_url
    }
  },
  onLoad:function(opt){
    this.opt = app.getopts(opt);
  }
}
</script>
<style>
.container{width:100%;min-height:100vh;background:#fff}
.content{width:94%;margin:0 auto;padding:40rpx 0}
.title{font-size:32rpx;color:#222;font-weight:600;margin-bottom:20rpx}
.btn{width:100%;height:88rpx;line-height:88rpx;text-align:center;color:#fff;background:#3d7af7;border-radius:8rpx}
</style>
