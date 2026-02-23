<template>
<view class="container">
  <view class="tab">
    <view class="tab-item" :class="tabIndex==0?'active':''" @tap="tabIndex=0">会员批量修改</view>
    <view class="tab-item" :class="tabIndex==1?'active':''" @tap="tabIndex=1">商品批量修改</view>
  </view>
  <view v-if="tabIndex==0" class="form-box">
    <view class="form-item">
      <view class="f1">上级ID</view>
      <view class="f2"><input type="text" :value="memberFilters.pid" placeholder="可选" placeholder-style="color:#888" @input="onPid" /></view>
    </view>
    <view class="form-item">
      <view class="f1">原始等级</view>
      <view class="f2">
        <picker mode="selector" :range="levelList" range-key="name" @change="onOriginLevelChange">
          <view class="picker">
            <text v-if="memberFilters.levelid">{{levelName(memberFilters.levelid)}}</text>
            <text v-else style="color:#888">请选择</text>
          </view>
        </picker>
      </view>
    </view>
    <view class="op-row">
      <view class="btn" :style="{background:t('color1'),color:'#fff'}" @tap="confirmMemberFilter">确认筛选</view>
      <view class="btn" :style="{background:t('color1'),color:'#fff'}" @tap="toggleSelectAll">全选</view>
    </view>
    <view v-if="memberList.length>0" class="total-row">总人数：{{totalCount}}</view>
    <view v-if="memberList.length>0" class="member-list">
      <checkbox-group :value="selectedMids" @change="onSelectedChange">
        <view v-for="it in memberList" :key="it.id" class="list-item">
          <checkbox :value="''+it.id" :checked="selectedMids.indexOf(''+it.id) >= 0"></checkbox>
          <image :src="it.headimg" class="avatar" @tap="openMember(it.id)"></image>
          <view class="meta">
            <text @tap="openMember(it.id)">{{it.nickname || it.realname || it.tel}}</text>
            <text style="color:#888">{{levelName(it.levelid)}}</text>
          </view>
        </view>
      </checkbox-group>
    </view>
    <view v-if="memberList.length>0 && memberList.length<totalCount" class="op-row">
      <view class="btn" :style="{background:t('color1'),color:'#fff'}" @tap="loadMoreMembers">加载更多</view>
    </view>
    <view v-if="memberList.length>0" class="form-item">
      <view class="f1">目标等级</view>
      <view class="f2">
        <picker mode="selector" :range="levelList" range-key="name" @change="onLevelChange">
          <view class="picker">
            <text v-if="targetLevelId">{{levelName(targetLevelId)}}</text>
            <text v-else style="color:#888">请选择</text>
          </view>
        </picker>
      </view>
    </view>
    <view v-if="memberList.length>0" class="op-row">
      <view class="btn" :style="{background:t('color1'),color:'#fff'}" @tap="submitMember">执行修改</view>
    </view>
  </view>
  <view v-else class="form-box">
    <view class="form-item">
      <view class="f1">供货商ID</view>
      <view class="f2"><input type="text" :value="productFilters.supplier_id" placeholder="可选" placeholder-style="color:#888" @input="e=>productFilters.supplier_id=e.detail.value" /></view>
    </view>
    <view class="form-item">
      <view class="f1">商品分类ID</view>
      <view class="f2"><input type="text" :value="productFilters.cids" placeholder="多个用逗号分隔" placeholder-style="color:#888" @input="e=>productFilters.cids=e.detail.value" /></view>
    </view>
    <view class="form-item">
      <view class="f1">市场价比率</view>
      <view class="f2"><input type="text" :value="productFilters.market_ratio" placeholder="如1.35" placeholder-style="color:#888" @input="e=>productFilters.market_ratio=e.detail.value" /></view>
    </view>
    <view class="form-item">
      <view class="f1">销售价比率</view>
      <view class="f2"><input type="text" :value="productFilters.sell_ratio" placeholder="如1.2" placeholder-style="color:#888" @input="e=>productFilters.sell_ratio=e.detail.value" /></view>
    </view>
    <view class="op-row">
      <view class="btn" :style="{background:t('color1'),color:'#fff'}" @tap="submitProduct('shop')">更新商城商品</view>
      <view class="btn" :style="{background:t('color1'),color:'#fff'}" @tap="submitProduct('tuangou')">更新拼团商品</view>
    </view>
  </view>
  <dd-loading v-if="loading"></dd-loading>
  <dd-nodata v-if="nodata"></dd-nodata>
  <dd-nomore v-if="nomore"></dd-nomore>
  <dd-toast ref="toast"></dd-toast>
</view>
</template>
<script>
var app = getApp();
export default {
  data(){
    return {
      tabIndex:0,
      loading:false,
      nodata:false,
      nomore:false,
      levelList:[],
      memberFilters:{pid:'',levelid:''},
      targetLevelId:'',
      memberList:[],
      totalCount:0,
      pagenum:1,
      pernum:20,
      selectedMids:[],
      productFilters:{supplier_id:'',cids:'',market_ratio:'',sell_ratio:''}
    }
  },
  onLoad(){
    if(!this.uinfo) this.uinfo = {};
    this.getLevelList();
  },
  methods:{
    t(key){return app.t(key)},
    onPid(e){this.memberFilters.pid = e.detail.value},
    onLevelChange(e){
      const idx = parseInt(e.detail.value);
      const lv = this.levelList[idx];
      this.targetLevelId = lv ? lv.id : '';
    },
    onOriginLevelChange(e){
      const idx = parseInt(e.detail.value);
      const lv = this.levelList[idx];
      this.memberFilters.levelid = lv ? lv.id : '';
    },
    levelName(id){
      const it = this.levelList.find(x=>x.id==id);
      return it?it.name:'';
    },
    getLevelList(){
      var that=this;that.loading=true;
      app.post('ApiAdminMember/memberlevel',{},function(res){
        that.loading=false;
        if(res.status==1){
          that.levelList = res.data||[];
        }else{app.error(res.msg)}
      });
    },
    confirmMemberFilter(){
      var that=this;const p=this.memberFilters;
      that.loading=true;
      that.pagenum = 1; that.pernum = 20;
      app.post('ApiAdminMember/batchMemberList',{pid:p.pid,levelid:p.levelid,pagenum:that.pagenum,pernum:that.pernum},function(res){
        that.loading=false;
        if(res.status==1){
          var data = res.data||{};
          that.memberList = data.list||[];
          that.totalCount = data.count || 0;
          if(that.memberList.length==0){ that.nodata = true; } else { that.nodata = false; }
        }else{app.error(res.msg)}
      });
    },
    loadMoreMembers(){
      var that=this;const p=this.memberFilters;
      if(that.memberList.length>=that.totalCount) return;
      that.loading=true; that.pagenum = that.pagenum + 1;
      app.post('ApiAdminMember/batchMemberList',{pid:p.pid,pagenum:that.pagenum,pernum:that.pernum},function(res){
        that.loading=false;
        if(res.status==1){
          var data = res.data||{};
          var more = data.list||[];
          that.memberList = that.memberList.concat(more);
          that.totalCount = data.count || that.totalCount;
        }else{app.error(res.msg)}
      });
    },
    toggleSelectAll(){
      var ids = (this.memberList||[]).map(function(it){return ''+it.id});
      if(this.selectedMids.length === ids.length){
        this.selectedMids = [];
      }else{
        this.selectedMids = ids;
      }
      this.test = Math.random();
    },
    onSelectedChange(e){
      var vals = e.detail.value||[];
      this.selectedMids = vals.map(function(v){return ''+v;});
    },
    openMember(mid){
      app.goto('/admin/member/detail?mid='+mid);
    },
    submitMember(){
      var that=this;const p=this.memberFilters;
      if(!this.targetLevelId){app.error('请选择等级');return}
      that.loading=true;
      var mids = that.selectedMids.map(function(v){return parseInt(v)}).filter(function(x){return x>0});
      app.post('ApiAdminMember/batchChangeLevel',{levelid:this.targetLevelId,pid:p.pid,mids:mids},function(res){
        that.loading=false;
        if(res.status==1){app.success(res.msg)}else{app.error(res.msg)}
      });
    },
    submitProduct(type){
      var that=this;const p=this.productFilters;
      if(!p.market_ratio && !p.sell_ratio){app.error('请输入比率');return}
      const cids = (p.cids||'').split(',').filter(x=>x!=='').map(x=>parseInt(x));
      that.loading=true;
      app.post('ApiAdminProduct/batchUpdatePriceRate',{type:type,supplier_id:p.supplier_id,cids:cids,market_ratio:p.market_ratio,sell_ratio:p.sell_ratio},function(res){
        that.loading=false;
        if(res.status==1){app.success(res.msg)}else{app.error(res.msg)}
      });
    }
  }
}
</script>
<style>
.container{width:100%}
.tab{display:flex;width:94%;margin:20rpx 3%}
.tab-item{flex:1;text-align:center;height:70rpx;line-height:70rpx;border:1px solid #eee}
.tab-item.active{color:#fff;background:#007aff;border-color:#007aff}
.form-box{width:94%;margin:10rpx 3%;background:#fff;border-radius:8rpx;padding:10rpx}
.form-item{display:flex;align-items:center;height:80rpx;border-bottom:1px solid #eee}
.form-item .f1{width:260rpx;color:#333}
.form-item .f2{flex:1}
.form-item .f2 input{height:60rpx}
.picker{height:60rpx;display:flex;align-items:center}
.op-row{display:flex;gap:20rpx;padding:20rpx 0}
.btn{height:70rpx;line-height:70rpx;border-radius:6rpx;padding:0 24rpx}
.member-list{margin-top:10rpx}
.total-row{padding:10rpx 0;color:#666}
.list-item{display:flex;align-items:center;padding:12rpx 0;border-bottom:1px solid #eee}
.avatar{width:60rpx;height:60rpx;border-radius:50%;margin:0 16rpx}
.meta{display:flex;flex-direction:column}
</style>
