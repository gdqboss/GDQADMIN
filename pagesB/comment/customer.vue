<template>
<view class="container">
  <block v-if="isload">
    <view class="notice" v-if="unreviewedCount>0">
      <text>您有{{unreviewedCount}}个已完成订单未评价</text>
      <view class="btn1" :style="{background:t('color1')}" @tap="goto" data-url="/pagesExt/order/orderlist?st=3">去评价</view>
    </view>
    <view class="tabs">
      <view class="tab" :class="curtab=='shop'?'active':''" @tap="switchTab" data-tab="shop">商店评价</view>
      <view class="tab" :class="curtab=='tuangou'?'active':''" @tap="switchTab" data-tab="tuangou">拼团评价</view>
    </view>
    <view class="comment">
      <view v-for="(item, index) in datalist" :key="index" class="item">
        <view class="f1">
          <image class="t1" :src="item.headimg"/>
          <view class="t2">{{item.nickname}}</view>
          <view class="flex1"></view>
          <view class="t3">
            <image class="img" v-for="(s,i) in [0,1,2,3,4]" :key="i"  :src="pre_url+'/static/img/star' + (item.score>i?'2native':'') + '.png'"/>
          </view>
        </view>
        <view style="color:#777;font-size:22rpx;">{{item.createtime}}</view>
        <view class="f2">
          <text class="t1">{{item.content}}</text>
          <view class="t2">
            <block v-if="item.content_pic!=''">
              <block v-for="(itemp, idx) in item.content_pic" :key="idx">
                <view @tap="previewImage" :data-url="itemp" :data-urls="item.content_pic">
                  <image :src="itemp" mode="widthFix"/>
                </view>
              </block>
            </block>
          </view>
        </view>
        <view class="relist" v-if="item.replylist && item.replylist.length>0">
          <view class="reitem" v-for="(hf, j) in item.replylist" :key="j">
            <view class="rf1">{{hf.nickname}}<text class="rf2">{{hf.createtime}}</text></view>
            <view class="rf3">{{hf.content}}</view>
          </view>
        </view>
        <view class="f3">
          <view class="btn2" @tap="goto" :data-url="'/pagesB/comment/reply?cid='+item.id+'&type='+curtab">跟帖评价</view>
        </view>
      </view>
    </view>
    <nomore v-if="nomore"></nomore>
    <nodata v-if="nodata" text="暂无评价~"></nodata>
    <loading v-if="loading"></loading>
    <dp-tabbar :opt="opt"></dp-tabbar>
    <popmsg ref="popmsg"></popmsg>
  </block>
  <loading v-if="loading && !isload"></loading>
  <dp-tabbar :opt="opt" @getmenuindex="getmenuindex"></dp-tabbar>
  <popmsg ref="popmsg"></popmsg>
</view>
</template>

<script>
var app = getApp();
export default {
  data() {
    return {
      pre_url: app.globalData.pre_url,
      opt: {},
      loading: false,
      isload: false,
      menuindex: -1,
      curtab: 'shop',
      datalist: [],
      pagenum: 1,
      nodata: false,
      nomore: false,
      unreviewedCount: 0
    }
  },
  onLoad: function (opt) {
    this.opt = app.getopts(opt);
    this.isload = true;
    this.getUnreviewed();
    this.getdata();
  },
  onReachBottom: function(){
    this.getdata(true);
  },
  methods: {
    t:function(k){return app.t(k)},
    goto:function(e){app.goto(e.currentTarget.dataset.url)},
    previewImage:function(e){
      var url = e.currentTarget.dataset.url;
      var urls = e.currentTarget.dataset.urls;
      if(urls && typeof urls == 'string'){try{urls = JSON.parse(urls)}catch(err){urls=[url]}}
      uni.previewImage({current:url,urls:urls||[url]});
    },
    switchTab:function(e) {
      var tab = e.currentTarget.dataset.tab;
      if(tab==this.curtab) return;
      this.curtab = tab;
      this.getdata();
    },
    getUnreviewed:function() {
      var that = this;
      app.post('ApiOrder/orderlist',{st:'3',pagenum:1},function(res) {
        var list = res.datalist || [];
        var cnt = 0;
        for(var i=0;i<list.length;i++){
          var it = list[i];
          if(it.prolist){
            for(var j=0;j<it.prolist.length;j++){
              if(it.prolist[j].iscomment==0){cnt++;}
            }
          }else if(typeof it.iscomment != 'undefined' && it.status==3 && it.iscomment==0){
            cnt++;
          }
        }
        that.unreviewedCount = cnt;
      });
    },
    getdata:function(loadmore) {
      if(!loadmore){
        this.pagenum = 1;
        this.datalist = [];
      }
      var that = this;
      var pagenum = that.pagenum;
      that.loading = true;
      that.nodata = false;
      that.nomore = false;
      var api = that.curtab=='shop' ? 'ApiShop/commentlist' : 'ApiTuangou/commentlist';
      var params = {proid:0,pagenum:pagenum};
      app.post(api, params, function(res) {
        that.loading = false;
        var data = res.data || res.datalist || [];
        if(pagenum==1){
          that.datalist = data;
          if(data.length==0){that.nodata = true;}
          that.loaded();
        }else{
          if(data.length==0){that.nomore = true;}else{
            that.datalist = that.datalist.concat(data);
          }
        }
        if(!that.nomore && data && data.length>0){that.pagenum = pagenum + 1}
      });
    },
    getmenuindex:function(e){this.menuindex = e}
  }
}
</script>

<style>
.container{width:100%;min-height:100vh;background:#f6f6f6}
.notice{width:94%;margin:20rpx 3%;padding:20rpx;background:#fff;border-radius:12rpx;display:flex;align-items:center}
.notice .btn1{margin-left:auto;color:#fff;padding:0 24rpx;height:60rpx;line-height:60rpx;border-radius:30rpx;font-size:26rpx}
.tabs{width:100%;display:flex;background:#fff}
.tab{flex:1;text-align:center;height:80rpx;line-height:80rpx;font-size:28rpx;color:#666}
.tab.active{color:#000;font-weight:bold;border-bottom:4rpx solid #000}
.comment{width:100%;background:#fff;margin-top:20rpx}
.item{padding:20rpx;border-bottom:1rpx solid #f2f2f2}
.f1{display:flex;align-items:center}
.f1 .t1{width:60rpx;height:60rpx;border-radius:50%;margin-right:12rpx}
.f1 .t2{font-size:28rpx;color:#333}
.f1 .t3{display:flex}
.f1 .t3 .img{width:30rpx;height:30rpx;margin-left:6rpx}
.f2 .t1{font-size:28rpx;color:#333;display:block;margin:12rpx 0}
.f2 .t2 image{width:200rpx;height:auto;margin-right:12rpx;border-radius:8rpx}
.relist{background:#f9f9f9;border-radius:8rpx;padding:12rpx;margin-top:8rpx}
.reitem{margin-bottom:8rpx}
.rf1{font-size:24rpx;color:#666}
.rf2{margin-left:12rpx;color:#999}
.rf3{font-size:26rpx;color:#333;margin-top:6rpx}
.f3{margin-top:12rpx;display:flex;justify-content:flex-end}
.btn2{height:60rpx;line-height:60rpx;padding:0 24rpx;background:#f0f0f0;border-radius:30rpx;font-size:26rpx}
</style>
