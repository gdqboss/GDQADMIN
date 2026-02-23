<template>
<view class="container">
  <block v-if="isload">
    <form @submit="formSubmit">
      <view class="form-content">
        <view class="form-item3 flex-col">
          <view class="label">跟帖内容</view>
          <textarea placeholder="输入您的跟帖内容" placeholder-style="color:#ccc;" name="content" style="height:200rpx"></textarea>
        </view>
        <view class="form-item4 flex-col">
          <view class="label">上传图片</view>
          <view id="content_picpreview" class="flex" style="flex-wrap:wrap;padding-top:20rpx">
            <view v-for="(item, index) in content_pic" :key="index" class="layui-imgbox">
              <view class="layui-imgbox-close" @tap="removeimg" :data-index="index" data-field="content_pic"><image :src="pre_url+'/static/img/ico-del.png'"></image></view>
              <view class="layui-imgbox-img"><image :src="item" @tap="previewImage" :data-url="item" mode="widthFix"></image></view>
            </view>
            <view class="uploadbtn" :style="'background:url('+pre_url+'/static/img/shaitu_icon.png) no-repeat 60rpx;background-size:80rpx 80rpx;background-color:#F3F3F3;'" @tap="uploadimg" data-field="content_pic" v-if="content_pic.length<5"></view>
          </view>
        </view>
      </view>
      <button class="btn" form-type="submit" :style="{background:t('color1')}">提交跟帖</button>
    </form>
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
      menuindex:-1,
      pre_url:app.globalData.pre_url,
      content_pic: [],
      tempFilePaths: ""
    };
  },
  onLoad: function (opt) {
    this.opt = app.getopts(opt);
    this.isload = true;
  },
  methods: {
    t:function(k){return app.t(k)},
    formSubmit: function (e) {
      var that = this;
      var cid = that.opt.cid;
      var type = that.opt.type;
      var content = e.detail.value.content;
      var content_pic = that.content_pic;
      if (!cid) { app.error('缺少评论ID'); return; }
      if (content == '') { app.error('请填写跟帖内容'); return; }
      app.showLoading('提交中');
      app.post('ApiComment/reply', {cid: cid,type: type,content: content,content_pic: content_pic.join(',')}, function (data) {
        app.showLoading(false);
        app.success(data.msg);
        setTimeout(function () { app.goback(true); }, 1500);
      });
    },
    uploadimg:function(e){
      var that = this;
      var field= e.currentTarget.dataset.field
      var pics = that[field]
      if(!pics) pics = [];
      app.chooseImage(function(urls){
        for(var i=0;i<urls.length;i++){
          pics.push(urls[i]);
        }
      },1)
    },
    removeimg:function(e){
      var that = this;
      var index= e.currentTarget.dataset.index
      var field= e.currentTarget.dataset.field
      var pics = that[field]
      pics.splice(index,1)
    },
    previewImage:function(e){
      var url = e.currentTarget.dataset.url;
      var urls = e.currentTarget.dataset.urls || [url];
      wx.previewImage({current:url,urls:urls});
    }
  }
};
</script>
<style>
.form-content{width:94%;margin:10rpx 3%;border-radius:10rpx;display:flex;flex-direction:column;background:#fff;overflow:hidden}
.form-item3{width:100%;background: #fff; padding: 8rpx 20rpx;margin-top:1px}
.form-item3 .label{ width:100%;height:60rpx;line-height:60rpx}
.form-item3 textarea{width: 100%;border: 1px #dedede solid; border-radius: 10rpx; padding: 10rpx;height: 120rpx;}
.form-item4{width:100%;background: #fff; padding: 20rpx 20rpx;margin-top:1px}
.form-item4 .label{ width:150rpx;}
.layui-imgbox{margin-right:16rpx;margin-bottom:10rpx;font-size:24rpx;position: relative;}
.layui-imgbox-img{display: block;width:200rpx;height:200rpx;padding:2px;border: #d3d3d3 1px solid;background-color: #f6f6f6;overflow:hidden}
.layui-imgbox-img>image{max-width:100%;}
.uploadbtn{position:relative;height:200rpx;width:200rpx}
.btn{ height:100rpx;line-height: 100rpx;width:90%;margin:0 auto;border-radius:50rpx;margin-top:50rpx;color: #fff;font-size: 30rpx;font-weight:bold}
</style>
