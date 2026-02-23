<template>
<view class="container">
  <block v-if="isload">
    <view class="notice" v-if="unreviewedCount>0 || unreviewedColCount>0">
      <text>您有{{unreviewedCount + unreviewedColCount}}个已完成订单未评价</text>
      <view class="btn1" :style="{background:t('color1')}" @tap="goto" data-url="/pagesExt/order/orderlist?st=3">商城订单<view v-if="unreviewedCount>0" class="dot"></view></view>
      <view class="btn1" :style="{background:t('color1'), marginLeft:'12rpx'}" @tap="goto" data-url="/activity/collage/orderlist?st=3">拼团订单<view v-if="unreviewedColCount>0" class="dot"></view></view>
    </view>
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
        <view class="relist" v-if="item.replylist && item.replylist.length>0">
          <view class="reitem" v-for="(hf, j) in item.replylist" :key="j">
            <view class="rf1">{{hf.nickname}}<text class="rf2">{{hf.createtime}}</text></view>
            <view class="rf3">{{hf.content}}</view>
          </view>
        </view>
        <view class="f3">
          <view class="btn2" :style="{borderColor:t('color1'),color:t('color1')}" @tap="goto" :data-url="'/pagesB/comment/reply?cid='+item.id+'&type='+item.ctype">跟帖评价</view>
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
      datalist: [],
      pShop: 1,
      pLC: 1,
      pCol: 1,
      nodata: false,
      nomore: false,
      unreviewedCount: 0,
      unreviewedColCount: 0,
      searchKey: '',
      viewlist: []
      ,activeCat:'pin'
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
    }
  },
  onLoad: function (opt) {
    this.opt = app.getopts(opt);
    this.isload = true;
    this.getUnreviewed();
    if(this.opt.q){this.searchKey=this.opt.q}
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
    normalize:function(arr,ctype){
      var out=[];
      for(var i=0;i<arr.length;i++){
        var it=arr[i];
        it.ctype=(ctype=='collage'||ctype=='tuangou'||ctype=='cycle')?'pin':ctype;
        if(typeof it.content_pic=='string' && it.content_pic!=''){
          var s=it.content_pic;
          try{it.content_pic=JSON.parse(s)}catch(e){it.content_pic=s.indexOf(',')>-1?s.split(','):s?[s]:[]}
        }else if(Array.isArray(it.content_pic)){
          it.content_pic=it.content_pic
        }else if(typeof it.content_pic==='object' && it.content_pic){
          var vals=[]; for(var k in it.content_pic){ if(Object.prototype.hasOwnProperty.call(it.content_pic,k)){ vals.push(it.content_pic[k]) } }
          it.content_pic=vals
        }else{it.content_pic=[]}
        if(!it.proname){ it.proname = it.pro_name || it.pname || '' }
        if(!it.propic){ it.propic = it.product_pic || it.pic || '' }
        if(!Array.isArray(it.replylist)){ it.replylist = [] }
        if(it.replylist.length==0 && it.reply_content){ it.replylist=[{nickname:'商家回复',createtime:it.reply_time||'',content:it.reply_content}] }
        if(!it.headimg){ it.headimg = this.pre_url + '/static/img/nopic.png' }
        it.score = Number(it.score||0)
        if(typeof it.createtime==='number' && it.createtime>0){ it.createtime = this.fmtTime(it.createtime) }
        out.push(it)
      }
      return out
    },
    fmtTime:function(ts){
      var d=new Date(ts*1000);var y=d.getFullYear();var m=('0'+(d.getMonth()+1)).slice(-2);var h=('0'+d.getHours()).slice(-2);var n=('0'+d.getMinutes()).slice(-2);var day=('0'+d.getDate()).slice(-2);return y+'-'+m+'-'+day+' '+h+':'+n
    },
    setCategory:function(e){
      var key=e.currentTarget.dataset.key;
      this.activeCat=key;this.applyFilter();
    },
    getUnreviewed:function() {
      var that = this;
      var done1=false,done3=false;
      var cnt1=0,cnt3=0;
      app.post('ApiOrder/orderlist',{st:'3',pagenum:1},function(res) {
        var list = res.datalist || [];
        for(var i=0;i<list.length;i++){
          var it = list[i];
          if(it.prolist){
            for(var j=0;j<it.prolist.length;j++){
              if(it.prolist[j].iscomment==0){cnt1++;}
            }
          }else if(typeof it.iscomment != 'undefined' && it.status==3 && it.iscomment==0){
            cnt1++;
          }
        }
        done1=true;
        that.unreviewedCount = cnt1;
      });
      app.post('ApiCollage/orderlist',{st:'3',pagenum:1},function(res3){
        var list3 = res3.datalist || [];
        for(var m=0;m<list3.length;m++){
          var it3 = list3[m];
          if(typeof it3.iscomment != 'undefined'){
            if(it3.status==3 && it3.iscomment==0){cnt3++;}
          }else{
            if(it3.status==3){cnt3++;}
          }
        }
        done3=true;
        that.unreviewedColCount = cnt3;
      });
    },
    dosearch:function(){
      this.applyFilter();
    },
    clearsearch:function(){this.searchKey='';this.applyFilter()},
    applyFilter:function(){
      var k=this.searchKey.trim().toLowerCase();
      var v=[];var dl=this.datalist;var cat=this.activeCat;
      for(var i=0;i<dl.length;i++){
        var it=dl[i];
        if(cat!='all' && it.ctype!=cat) continue;
        if(k==''){v.push(it);continue;}
        var txt=(it.content||'')+' '+(it.nickname||'');
        var ok=txt.toLowerCase().indexOf(k)>-1;
        if(!ok && it.replylist){
          for(var j=0;j<it.replylist.length;j++){
            var hf=it.replylist[j];
            var t2=(hf.content||'')+' '+(hf.nickname||'');
            if(t2.toLowerCase().indexOf(k)>-1){ok=true;break}
          }
        }
        if(ok){v.push(it)}
      }
      this.viewlist=v;
    },
    getdata:function(loadmore) {
      var that = this;
      if(!loadmore){
        that.pShop = 1;that.pCol = 1;that.datalist = [];that.viewlist = []
      }
      var pShop = that.pShop;
      var pCol = that.pCol;
      that.loading = true;that.nodata = false;that.nomore = false;
      var shopArr=null,colArr=null;
      function done(){
        if(shopArr==null || colArr==null) return;
        that.loading=false;
        var a=that.normalize(shopArr,'shop');
        var d1=that.normalize(colArr,'collage');
        var merged = a.concat(d1);
        merged = that.dedup(merged);
        merged.sort(function(x,y){var cx=(x.createtime||'');var cy=(y.createtime||'');return cx>cy?-1:(cx<cy?1:0)});
        if(!loadmore){
          that.datalist = merged;
          if(merged.length==0){that.nodata=true;}
          var dlen = d1.length;
          if(that.activeCat=='shop' && a.length==0 && dlen>0){ that.activeCat='pin'; }
          if(that.activeCat=='pin' && dlen==0 && a.length>0){ that.activeCat='shop'; }
          that.applyFilter();
          if(typeof that.loaded=='function'){that.loaded();}
        }else{
          if(merged.length==0){that.nomore=true}else{that.datalist = that.datalist.concat(merged);that.applyFilter();}
        }
        if(a.length>0) that.pShop = pShop + 1;
        if(d1.length>0) that.pCol = pCol + 1;
        if(a.length==0 && d1.length==0 && loadmore){that.nomore=true}
      }
      app.post('ApiShop/commentlist',{proid:0,pagenum:pShop},function(res){shopArr = res.data || res.datalist || [];done()});
      app.post('ApiCollage/commentlist',{pagenum:pCol,show_all:1},function(res){ colArr = res.data || res.datalist || []; done(); });
    },
    getShopProComments:function(cb){
      var that=this; var proids=[];
      app.post('ApiOrder/orderlist',{st:'3',pagenum:1},function(res){
        var list=res.datalist||[];
        for(var i=0;i<list.length;i++){
          var it=list[i];
          if(it.prolist && it.prolist.length){ for(var j=0;j<it.prolist.length;j++){ var p=it.prolist[j]; if(p.proid){ proids.push(p.proid) } } }
        }
        var map={}; var uniq=[]; for(var k=0;k<proids.length;k++){ var pid=proids[k]; if(!map[pid]){ map[pid]=1; uniq.push(pid) } }
        if(uniq.length==0){ if(typeof cb=='function') cb([]); return; }
        var out=[]; var pending=uniq.length; var finished=false;
        for(var m=0;m<uniq.length;m++){
          app.post('ApiShop/commentlist',{proid:uniq[m],pagenum:1},function(res2){ var arr=res2.data||res2.datalist||[]; out=out.concat(arr); pending--; if(pending==0 && !finished){ finished=true; if(typeof cb=='function') cb(out) } })
        }
        setTimeout(function(){ if(!finished){ finished=true; if(typeof cb=='function') cb(out) } },1200)
      })
    },
    getCollageProComments:function(cb){
      var that=this;var proids=[];
      app.post('ApiCollage/orderlist',{st:'3',pagenum:1},function(res){
        var list=res.datalist||[];
        for(var i=0;i<list.length;i++){ var it=list[i]; if(it.proid){ proids.push(it.proid) } }
        var map={}; var uniq=[]; for(var j=0;j<proids.length;j++){ var pid=proids[j]; if(!map[pid]){ map[pid]=1; uniq.push(pid) } }
        if(uniq.length==0){ if(typeof cb=='function') cb([]); return; }
        var out=[]; var pending=uniq.length; var finished=false;
        for(var k=0;k<uniq.length;k++){
          app.post('ApiCollage/commentlist',{proid:uniq[k],pagenum:1,show_all:1},function(res2){
            var arr=res2.data||res2.datalist||[]; out=out.concat(arr);
            pending--; if(pending==0 && !finished){ finished=true; if(typeof cb=='function') cb(out) }
          })
        }
        setTimeout(function(){ if(!finished){ finished=true; if(typeof cb=='function') cb(out) } },1200)
      })
    },
    getCollageOrderComments:function(cb){
      var that=this; var out=[];
      app.post('ApiCollage/orderlist',{st:'3',pagenum:that.pCol},function(res){
        var list=res.datalist||[]; if(list.length==0){ if(typeof cb=='function') cb([]); return; }
        var pending=list.length; var finished=false;
        for(var i=0;i<list.length;i++){
          var oid=list[i].id || list[i].orderid;
          if(!oid){ pending--; continue; }
          app.get('ApiCollage/comment',{orderid:oid,show_all:1},function(r){
            if(r && r.comment){
              var c=r.comment; var pics=[];
              if(typeof c.content_pic=='string'){ pics = c.content_pic? c.content_pic.split(','):[] } else if(Array.isArray(c.content_pic)){ pics=c.content_pic }
              var replylist = Array.isArray(c.replylist) ? c.replylist : []
              out.push({
                id:c.id||undefined,
                content:c.content||'',
                content_pic:pics,
                score:c.score||0,
                createtime:c.createtime||'',
                nickname:c.nickname||'匿名',
                headimg:c.headimg|| (that.pre_url + '/static/img/nopic.png'),
                proname:(r.og && (r.og.proname||r.og.name)) || '',
                propic:(r.og && r.og.propic) || '',
                ischeck: (typeof c.ischeck!=='undefined') ? c.ischeck : 1,
                replylist: replylist
              })
            }
            pending--; if(pending==0 && !finished){ finished=true; if(typeof cb=='function') cb(out) }
          })
        }
        setTimeout(function(){ if(!finished){ finished=true; if(typeof cb=='function') cb(out) } },1500)
      })
    },
    getCollageAllCommentsByOrder:function(cb){
      var that=this; var out=[];
      app.post('ApiCollage/commentlist',{pagenum:that.pCol,show_all:1},function(res){
        var list = res.data || res.datalist || []; if(list.length==0){ if(typeof cb=='function') cb([]); return; }
        var pending=list.length; var finished=false;
        for(var i=0;i<list.length;i++){
          var it=list[i]; var oid = it.orderid || it.order_id || it.oid;
          if(!oid){
            var pics=[]; if(typeof it.content_pic=='string'){ pics = it.content_pic? it.content_pic.split(','):[] } else if(Array.isArray(it.content_pic)){ pics=it.content_pic }
            out.push({
              id:it.id||undefined,
              content:it.content||'',
              content_pic:pics,
              score:it.score||0,
              createtime:it.createtime||'',
              nickname:it.nickname||'匿名',
              headimg:it.headimg|| (that.pre_url + '/static/img/nopic.png'),
              proname:it.proname||it.pro_name||it.pname||'',
              propic:it.propic||it.product_pic||it.pic||'',
              ischeck: (typeof it.ischeck!=='undefined') ? it.ischeck : 1,
              replylist: Array.isArray(it.replylist)?it.replylist:[]
            });
            pending--; if(pending==0 && !finished){ finished=true; if(typeof cb=='function') cb(out) }
            continue;
          }
          app.get('ApiCollage/comment',{orderid:oid,show_all:1},function(r){
            var c=r && r.comment ? r.comment : null;
            if(c){
              var pics=[]; if(typeof c.content_pic=='string'){ pics = c.content_pic? c.content_pic.split(','):[] } else if(Array.isArray(c.content_pic)){ pics=c.content_pic }
              var replylist = Array.isArray(c.replylist) ? c.replylist : []
              out.push({
                id:c.id||undefined,
                content:c.content||'',
                content_pic:pics,
                score:c.score||0,
                createtime:c.createtime||'',
                nickname:c.nickname||'匿名',
                headimg:c.headimg|| (that.pre_url + '/static/img/nopic.png'),
                proname:(r.og && (r.og.proname||r.og.name)) || '',
                propic:(r.og && r.og.propic) || '',
                ischeck: (typeof c.ischeck!=='undefined') ? c.ischeck : 1,
                replylist: replylist
              })
            }
            pending--; if(pending==0 && !finished){ finished=true; if(typeof cb=='function') cb(out) }
          })
        }
        setTimeout(function(){ if(!finished){ finished=true; if(typeof cb=='function') cb(out) } },1500)
      })
    },
    getBusinessAllComments:function(cb){
      var that=this; var out=[];
      app.post('ApiBusiness/blist',{pagenum:1},function(res){
        var status = (typeof res.status!='undefined') ? res.status : 1;
        var bl = res.data || res.datalist || [];
        if(status==0 || !bl || bl.length==0){ if(typeof cb=='function') cb([]); return; }
        var maxBiz = bl.length>8 ? 8 : bl.length;
        var pending = maxBiz; var finished=false;
        for(var i=0;i<maxBiz;i++){
          var bid = bl[i].id || bl[i].bid;
          app.post('ApiBusiness/commentlist',{bid:bid,pagenum:1},function(r){
            var st2 = (typeof r.status!='undefined') ? r.status : 1;
            var arr = r.data || r.datalist || [];
            if(st2==0 || !arr){ pending--; if(pending==0 && !finished){ finished=true; if(typeof cb=='function') cb(out) } return; }
            var bname = r.business && r.business.name ? r.business.name : '';
            var blogo = r.business && r.business.logo ? r.business.logo : '';
            for(var j=0;j<arr.length;j++){
              var it = arr[j];
              if(typeof it.content_pic=='string'){ it.content_pic = it.content_pic ? it.content_pic.split(',') : [] }
              it.proname = it.proname || bname;
              it.propic = it.propic || blogo;
              out.push(it);
            }
            pending--; if(pending==0 && !finished){ finished=true; if(typeof cb=='function') cb(out) }
          })
        }
        setTimeout(function(){ if(!finished){ finished=true; if(typeof cb=='function') cb(out) } },1500)
      })
    },
    dedup:function(arr){
      var map={}; var out=[];
      for(var i=0;i<arr.length;i++){
        var it=arr[i];
        var key = it.id?('id:'+it.id):('k:'+((it.nickname||'')+'|'+(it.createtime||'')+'|'+(it.content||'')).toLowerCase());
        if(!map[key]){ map[key]=1; out.push(it) }
      }
      return out;
    },
    getmenuindex:function(e){this.menuindex = e}
  }
}
</script>

<style>
.container{width:100%;min-height:100vh;background:#f6f6f6}
.notice{width:94%;margin:20rpx 3%;padding:20rpx;background:#fff;border-radius:16rpx;display:flex;align-items:center;box-shadow:0 6rpx 20rpx rgba(0,0,0,0.06)}
.notice .btn1{margin-left:auto;color:#fff;padding:0 24rpx;height:60rpx;line-height:60rpx;border-radius:30rpx;font-size:26rpx;position:relative}
.notice .btn1 .dot{position:absolute;top:-6rpx;right:-6rpx;width:16rpx;height:16rpx;background:#ff3b30;border-radius:50%}
.searchbar{width:94%;margin:10rpx 3%;display:flex;align-items:center;background:#fff;border-radius:12rpx;padding:12rpx}
.searchbar .ipt{flex:1;height:64rpx;line-height:64rpx;padding:0 20rpx;border:0;border-radius:8rpx;font-size:26rpx;background:#f7f8fa}
.searchbar .sbtn{margin-left:12rpx;color:#fff;padding:0 24rpx;height:64rpx;line-height:64rpx;border-radius:8rpx;font-size:26rpx}
.searchbar .cbtn{margin-left:12rpx;color:#333;padding:0 24rpx;height:64rpx;line-height:64rpx;border-radius:8rpx;font-size:26rpx;background:#f0f0f0}
.catbtns{width:94%;margin:0 3% 10rpx;display:flex}
.catbtns .cbtn1{flex:1;text-align:center;height:64rpx;line-height:64rpx;border-radius:32rpx;background:#fff;color:#333;margin-right:12rpx;border:1rpx solid #eee}
.comment{width:100%;background:#fff;margin-top:20rpx}
.item{padding:24rpx 20rpx;border-bottom:1rpx solid #f2f2f2;box-shadow:0 6rpx 20rpx rgba(0,0,0,0.04);border-radius:16rpx;margin:16rpx}
.f1{display:flex;align-items:center}
.f1 .t1{width:64rpx;height:64rpx;border-radius:50%;margin-right:12rpx}
.f1 .t2{font-size:28rpx;color:#333}
.f1 .tag{margin-left:12rpx;padding:0 18rpx;height:40rpx;line-height:40rpx;border-radius:20rpx;color:#666;font-size:22rpx}
.f1 .t3{display:flex}
.f1 .t3 .img{width:32rpx;height:32rpx;margin-left:6rpx}
.ctime{color:#999;font-size:22rpx;margin:6rpx 20rpx}
.proinfo{display:flex;align-items:center;margin:0 20rpx 8rpx}
.proimg{width:64rpx;height:64rpx;border-radius:8rpx;background:#f0f0f0;margin-right:12rpx}
.proname{font-size:26rpx;color:#666;line-height:32rpx;max-width:540rpx;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.f2 .t1{font-size:30rpx;color:#333;display:block;margin:14rpx 20rpx;line-height:42rpx}
.f2 .t2.image{display:none}
.imgs{display:grid;grid-template-columns:repeat(3,1fr);grid-gap:12rpx;padding:0 20rpx 8rpx}
.imgs .imgbox{width:100%;padding-top:100%;position:relative;border-radius:12rpx;overflow:hidden;background:#f6f6f6}
.imgs .imgbox image{position:absolute;top:0;left:0;width:100%;height:100%}
.relist{background:#f9f9f9;border-radius:8rpx;padding:12rpx;margin-top:8rpx}
.reitem{margin-bottom:8rpx}
.rf1{font-size:24rpx;color:#666;padding:0 4rpx}
.rf2{margin-left:12rpx;color:#999}
.rf3{font-size:26rpx;color:#333;margin-top:6rpx;line-height:40rpx}
.f3{margin-top:12rpx;display:flex;justify-content:flex-end}
.btn2{height:60rpx;line-height:60rpx;padding:0 28rpx;border-radius:30rpx;font-size:26rpx;border:1rpx solid #ddd;background:#fff}
</style>
