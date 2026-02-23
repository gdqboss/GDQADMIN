<template>
<view class="container">
  <block v-if="isload">
    <view class="searchbar">
      <input class="ipt" type="text" v-model="searchKey" placeholder="搜索评价内容/昵称" placeholder-style="color:#bbb"/>
      <view class="sbtn" :style="{background:t('color1')}" @tap="dosearch">搜索</view>
      <view class="cbtn" @tap="clearsearch">清空</view>
    </view>
    <view class="catbtns">
      <view :class="'cbtn1 '+(activeCat=='shop'?'on':'')" :style="catStyleShop" @tap="setCategory" data-key="shop">商城订单</view>
      <view :class="'cbtn1 '+(activeCat=='pin'?'on':'')" :style="catStylePin" @tap="setCategory" data-key="pin">拼团订单</view>
    </view>
    <view class="comment">
      <view v-for="(item, index) in viewlist" :key="index" class="item">
        <view class="f1">
          <image class="t1" :src="item.headimg"/>
          <view class="t2">{{item.nickname}}</view>
          <view class="tag" :style="{background:'rgba('+t('color1rgb')+',0.12)',color:t('color1')}">{{item.ctype=='shop'?'商城订单':'拼团订单'}}</view>
          <view class="flex1"></view>
          <view class="t3">
            <image class="img" v-for="(s,i) in [0,1,2,3,4]" :key="i"  :src="pre_url+'/static/img/star' + (item.score>i?'2native':'') + '.png'"/>
          </view>
        </view>
        <view class="ctime">{{item.createtime}}</view>
        <view class="proinfo" v-if="item.proname || item.propic">
          <image v-if="item.propic" class="proimg" :src="item.propic"/>
          <view class="proname">{{item.proname}}</view>
        </view>
        <view class="f2">
          <text class="t1">{{item.content}}</text>
          <view class="t2 imgs">
            <block v-if="item.content_pic && item.content_pic.length>0">
              <block v-for="(itemp, idx) in item.content_pic" :key="idx">
                <view @tap="previewImage" :data-url="itemp" :data-urls="item.content_pic" class="imgbox">
                  <image :src="itemp" mode="aspectFill"/>
                </view>
              </block>
            </block>
          </view>
        </view>
        <view class="audit">
          <view class="st" :style="item.ischeck==1?checkedStyle:uncheckedStyle" @tap="toggleCheck" :data-index="index">{{item.ischeck==1?'已审核':'未审核'}}</view>
        </view>
      </view>
    </view>
    <nomore v-if="nomore"></nomore>
    <nodata v-if="nodata" text="暂无评价~"></nodata>
    <loading v-if="loading"></loading>
  </block>
  <loading v-if="loading && !isload"></loading>
  <view class="tabbar">
    <view class="tabbar-bot"></view>
    <view class="tabbar-bar" style="background-color:#ffffff">
      <view @tap="goto" data-url="../member/index" data-opentype="reLaunch" class="tabbar-item" v-if="auth_data.member">
        <view class="tabbar-image-box">
          <image class="tabbar-icon" :src="pre_url+'/static/img/admin/member.png?v=1'"></image>
        </view>
        <view class="tabbar-text">{{t('会员')}}</view>
      </view>
      <view @tap.stop="goto" data-url="/admin/index/commentAudit" class="tabbar-item">
        <view class="tabbar-image-box">
          <image class="tabbar-icon" :src="pre_url+'/static/img/admin/zixun.png?v=1'"></image>
        </view>
        <view class="tabbar-text active">评价审核</view>
      </view>
      <view @tap="goto" data-url="../finance/index" data-opentype="reLaunch" class="tabbar-item" v-if="auth_data.finance">
        <view class="tabbar-image-box">
          <image class="tabbar-icon" :src="pre_url+'/static/img/admin/finance.png?v=1'"></image>
        </view>
        <view class="tabbar-text">财务</view>
      </view>
      <view @tap="goto" data-url="../index/index" data-opentype="reLaunch" class="tabbar-item">
        <view class="tabbar-image-box">
          <image class="tabbar-icon" :src="pre_url+'/static/img/admin/my2.png?v=1'"></image>
        </view>
        <view class="tabbar-text">我的</view>
      </view>
    </view>
  </view>
  <popmsg ref="popmsg"></popmsg>
  <loading v-if="loading"></loading>
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
      searchKey: '',
      datalist: [],
      viewlist: [],
      pShop: 1,
      pCol: 1,
      nodata: false,
      nomore: false,
      activeCat: 'shop',
      auth_data: {},
      show_auth: [],
      uinfo: {},
      toggleing: false
    }
  },
  computed:{
    catStyleShop(){
      return this.activeCat=='shop'
        ? 'color:'+this.t('color1')+';border-color:'+this.t('color1')+';background:rgba('+this.t('color1rgb')+',0.08)'
        : ''
    },
    catStylePin(){
      return this.activeCat=='pin'
        ? 'color:'+this.t('color1')+';border-color:'+this.t('color1')+';background:rgba('+this.t('color1rgb')+',0.08)'
        : ''
    },
    checkedStyle(){
      return 'border-color:'+this.t('color1')+';color:'+this.t('color1')+';background:rgba('+this.t('color1rgb')+',0.08)'
    },
    uncheckedStyle(){
      return 'border-color:#ccc;color:red;background:#f6f6f6'
    }
  },
  onLoad:function(opt){
    this.opt = app.getopts(opt);
    this.isload = true;
    this.getAuth();
    this.getdata();
  },
  onReachBottom:function(){
    this.getdata(true);
  },
  methods:{
    t:function(k){return app.t(k)},
    goto:function(e){app.goto(e.currentTarget.dataset.url,e.currentTarget.dataset.opentype)},
    previewImage:function(e){
      var url = e.currentTarget.dataset.url;
      var urls = e.currentTarget.dataset.urls;
      if(urls && typeof urls == 'string'){try{urls = JSON.parse(urls)}catch(err){urls=[url]}}
      uni.previewImage({current:url,urls:urls||[url]});
    },
    setCategory:function(e){
      var key=e.currentTarget.dataset.key; this.activeCat=key; this.applyFilter();
    },
    dosearch:function(){ this.applyFilter(); },
    clearsearch:function(){ this.searchKey=''; this.applyFilter(); },
    normalize:function(arr,ctype){
      var out=[]; for(var i=0;i<arr.length;i++){ var it=arr[i]; it.ctype=(ctype=='collage')?'pin':ctype; if(typeof it.content_pic=='string' && it.content_pic!=''){ var s=it.content_pic; try{ it.content_pic=JSON.parse(s) }catch(e){ it.content_pic=s.indexOf(',')>-1? s.split(',') : s? [s] : [] } } else if(!it.content_pic){ it.content_pic=[] } if(!it.proname){ it.proname = it.pro_name || it.pname || '' } if(!it.propic){ it.propic = it.product_pic || it.pic || '' } if(typeof it.status!=='undefined'){ it.ischeck = (parseInt(it.status)||0) } else if(typeof it.ischeck!=='undefined'){ it.ischeck = (parseInt(it.ischeck)||0) } else { it.ischeck = 0 } out.push(it) }
      return out
    },
    applyFilter:function(){
      var k=this.searchKey.trim().toLowerCase(); var v=[]; var dl=this.datalist; var cat=this.activeCat;
      for(var i=0;i<dl.length;i++){ var it=dl[i]; if(cat!='all' && it.ctype!=cat) continue; if(k==''){ v.push(it); continue; } var txt=(it.content||'')+' '+(it.nickname||''); var ok=txt.toLowerCase().indexOf(k)>-1; if(ok){ v.push(it) } }
      this.viewlist=v;
    },
    getdata:function(loadmore){
      var that=this; if(!loadmore){ that.pShop=1; that.pCol=1; that.datalist=[]; that.viewlist=[] }
      var pShop=that.pShop; var pCol=that.pCol; that.loading=true; that.nodata=false; that.nomore=false;
      var shopArr=null,colArr=null;
      function done(){ if(shopArr==null || colArr==null) return; that.loading=false; var a=that.normalize(shopArr,'shop'); var d1=that.normalize(colArr,'collage'); var merged=a.concat(d1); merged.sort(function(x,y){var cx=(x.createtime||'');var cy=(y.createtime||'');return cx>cy?-1:(cx<cy?1:0)}); if(!loadmore){ that.datalist=merged; if(merged.length==0){ that.nodata=true } if(that.activeCat=='shop' && a.length==0 && d1.length>0){ that.activeCat='pin' } if(that.activeCat=='pin' && d1.length==0 && a.length>0){ that.activeCat='shop' } that.applyFilter(); if(typeof that.loaded=='function'){ that.loaded() } } else { if(merged.length==0){ that.nomore=true } else { that.datalist = that.datalist.concat(merged); that.applyFilter(); } } if(a.length>0) that.pShop=pShop+1; if(d1.length>0) that.pCol=pCol+1; if(a.length==0 && d1.length==0 && loadmore){ that.nomore=true } }
      app.post('ApiShop/commentlist',{proid:0,pagenum:pShop,show_all:1,bid:(that.uinfo && that.uinfo.bid)||0},function(res){ shopArr = res.data || res.datalist || []; done() });
      app.post('ApiCollage/commentlist',{pagenum:pCol,show_all:1,bid:(that.uinfo && that.uinfo.bid)||0},function(res){ colArr = res.data || res.datalist || []; done() });
    },
    toggleCheck:function(e){
      if(this.toggleing) return; var idx=e.currentTarget.dataset.index; var it=this.viewlist[idx]; if(!it || !it.id){ return }
      var old=it.ischeck; var nv=old==1?0:1; it.ischeck=nv; var that=this; var endpoint = it.ctype==='shop' ? 'ShopComment/setst' : 'CollageComment/setst'; this.toggleing=true;
      app.post(endpoint,{st:nv,ids:[it.id],apifrom:'vue'},function(res){
        if(res && typeof res.status!=='undefined' && res.status==1){
          that.toggleing=false; app.success('已更新'); that.getdata();
        }else{
          it.ischeck=old; that.toggleing=false; app.error((res&&res.msg)||'操作失败');
        }
      });
    },

    // 在获取到权限后，如果会员级别满足“智慧家长/五星团长”，前端启用满权限开关，保障页面可用
    getAuth:function(){ var that=this; app.get('ApiAdminIndex/levelAutoLogin',{},function(r){ if(r && r.status==1){ app.get('ApiAdminIndex/index',{},function(res){ that.uinfo=res.uinfo||{}; that.auth_data=res.auth_data||{}; that.show_auth=res.show_auth||[]; if(that.isFullOpenLevel(that.uinfo)){ that.applyFullOpen(); } }); } else { app.alert((r&&r.msg)||'无权限'); } }); },
    isFullOpenLevel:function(u){ if(!u) return false; var names=[]; ['levelname','level_name','levelTitle','leader_title','leader_level_name','member_level_name','memberLevelName','role_name','roleName'].forEach(function(k){ if(typeof u[k]==='string'){ names.push(u[k]); } }); if(typeof u.level==='string') names.push(u.level); var ok=false; for(var i=0;i<names.length;i++){ var s=(names[i]||'').toString().trim(); if(!s) continue; if(s.indexOf('智慧家长')>-1 || s.indexOf('五星团长')>-1){ ok=true; break; } } return ok; },
    applyFullOpen:function(){ var ad=this.auth_data||{}; ad.finance=true; ad.order=true; ad.product=true; ad.member=true; ad.zixun=true; this.auth_data=ad; var sa=Array.isArray(this.show_auth)? this.show_auth.slice(): []; ['show_hx_num','Coupon','queue_free','wxadminQueueFreeSet','ShopStock','show_business','invoicebaoxiao'].forEach(function(tok){ if(sa.indexOf(tok)===-1) sa.push(tok) }); this.show_auth=sa; }
  }
}
</script>

<style>
.container{background:#fff}
.searchbar{display:flex;align-items:center;padding:16rpx 20rpx}
.searchbar .ipt{flex:1;border:1px solid #e5e5e5;border-radius:8rpx;padding:12rpx 16rpx;margin-right:12rpx}
.searchbar .sbtn{padding:0 24rpx;height:60rpx;line-height:60rpx;color:#fff;border-radius:8rpx}
.searchbar .cbtn{padding:0 24rpx;height:60rpx;line-height:60rpx;margin-left:12rpx;border:1px solid #ddd;border-radius:8rpx;color:#666}
.catbtns{display:flex;gap:16rpx;padding:0 20rpx 10rpx}
.cbtn1{border:1px solid #ddd;border-radius:30rpx;padding:10rpx 22rpx;color:#666}
.cbtn1.on{}
.comment{display:flex;flex-direction:column;padding:10rpx 0}
.comment .item{background-color:#fff;padding:10rpx 20rpx;display:flex;flex-direction:column;border-bottom:1px solid #f0f0f0}
.comment .item .f1{display:flex;width:100%;align-items:center;padding:10rpx 0}
.comment .item .f1 .t1{width:70rpx;height:70rpx;border-radius:50%}
.comment .item .f1 .t2{padding-left:10rpx;color:#333;font-weight:bold;font-size:30rpx}
.comment .item .f1 .t3{text-align:right}
.comment .item .f1 .t3 .img{width:24rpx;height:24rpx;margin-left:10rpx}
.comment .item .tag{margin-left:12rpx;border-radius:8rpx;padding:6rpx 12rpx;font-size:22rpx}
.ctime{color:#777;font-size:22rpx;padding:0 20rpx}
.proinfo{display:flex;align-items:center;padding:8rpx 20rpx}
.proimg{width:80rpx;height:80rpx;border-radius:8rpx;margin-right:10rpx}
.proname{color:#555;font-size:26rpx}
.comment .item .f2{display:flex;flex-direction:column;width:100%;padding:10rpx 0}
.comment .item .f2 .t1{color:#333;font-size:28rpx}
.comment .item .f2 .t2.imgs{display:flex;flex-wrap:wrap}
.imgbox{width:140rpx;height:140rpx;margin:10rpx;border-radius:10rpx;overflow:hidden}
.imgbox image{width:140rpx;height:140rpx}
.audit{display:flex;justify-content:flex-end;padding:10rpx 20rpx}
.audit .st{border:1px solid #ddd;border-radius:22rpx;padding:8rpx 16rpx;font-size:24rpx}
.audit .st.disabled{border-color:#eee;color:#bbb;background:#f6f6f6}
.tabbar{position:fixed;bottom:0;left:0;width:100%;}
.tabbar-bot{height:110rpx;background-color:#fff}
.tabbar-bar{position:fixed;bottom:0;left:0;width:100%;height:110rpx;display:flex;justify-content:space-around;align-items:center;border-top:1px solid #eee}
.tabbar-item{display:flex;flex-direction:column;align-items:center;justify-content:center}
.tabbar-image-box{width:56rpx;height:56rpx;margin-bottom:6rpx}
.tabbar-icon{width:56rpx;height:56rpx}
.tabbar-text{font-size:22rpx;color:#666}
.tabbar-text.active{color:#000}
</style>
