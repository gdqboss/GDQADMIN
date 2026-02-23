<template>
<view>
	<block v-if="isload">
		<view class="banner"  :style="{background:t('color1')}" >
			
		</view>

		<view class="topsearch ">
			<view class="f1 flex-y-center">
				<image class="img" :src="pre_url+'/static/img/search_ico.png'"></image>
				<view class="city" @tap="goto" data-url='searchlist'>{{city1?city1:city}}</view>
				<input :value="keyword" placeholder="输入社区名称" placeholder-style="font-size:24rpx;color:#C2C2C2" @confirm="searchConfirm" ></input>
			</view>
			<view  class="title">当前社区</view>
		</view>
		
		<view class="currentshequ">
			<view class="border">
				<view class="headimg"><image :src="mendian.headimg"></view>
				<view class="name">{{mendian.name}}</view>
				<view class="xqname">{{mendian.xqname}}</view>
				<view class="address" >{{mendian.address}}</view>
			</view>
		</view>
		<view style="height: 500rpx;" ></view>
		<view class="storeitem" >
			<view class="title"> 附近社区</view>
			<view class="radio-item" v-for="(item,index) in datalist" @tap.stop="changeMendian" :data-index="index" :data-id="item.id">
				<view class="headimg"><image :src="item.pic"></view>
				<view class="f1">
					<view class="f11">
						<view>
							<view>	{{item.xqname}}</view>
							<view style="color: #aaaaae;font-size: 26rpx;margin-top: 10rpx;">{{t('门店')}}：{{item.name}}</view>
						</view>
						 <text class="juli">{{item.distance}}</text>
					 </view>
					 <view  class="address" @tap.stop="toMendian" :data-address="item.address" :data-longitude="item.longitude" :data-latitude="item.latitude" >
					 	<view class="address2">
					 		<text v-if="item.province !='北京市' || item.province !='上海市' || item.province !='重庆市' || item.province !='天津市' ">{{item.province}}</text>{{item.city}}{{item.district}}</text>
					 		<text v-if="item.address">{{item.address}}</text>
					 	</view>
					 	<image :src="pre_url+'/static/img/arrowright.png'" style="display: inline-block; width:26rpx; height: 26rpx"/>
					 </view>
        </view>
	
			</view>
		</view>
	</block>
	<loading v-if="loading"></loading>
	<nodata v-if="nodata"></nodata>
	<nomore v-if="nomore"></nomore>
	<dp-tabbar :opt="opt" @getmenuindex="getmenuindex"></dp-tabbar>
	<popmsg ref="popmsg"></popmsg>
</view>
</template>

<script>
var app = getApp();
export default {
	data() {
		return {
			loading:false,
			isload: false,
			nomore:false,
			nodata:false,
			menuindex:-1,
			opt:{},
			pagenum: 1,
			datalist: [],
			keyword :'',
			md_cid:'',
			latitude:'',
			longitude:'',
			mendian:[],
			city:'',
			city1:'',
			locationCache:{},
			pre_url: app.globalData.pre_url,
		}
		
	},
	onLoad: function (opt) {
		this.opt = app.getopts(opt);
		var latitude =  app.getLocationCache('latitude');
		var longitude= app.getLocationCache('longitude');
		var that = this;
		if(latitude==undefined || !longitude==undefined){
			app.getLocation(function(res) {
				that.latitude = res.latitude;
				that.longitude = res.longitude;
				that.getmendian();
			});
		}
		that.city1 = this.opt.city?this.opt.city:''
		uni.setNavigationBarTitle({
			title:'选择' + that.t('门店')
		});
		this.getmendian();
		this.checkLocation()
		
	},
	onPullDownRefresh: function () {
		this.getdata();
	},
	onReachBottom: function () {
		if (!this.nodata && !this.nomore) {
			this.pagenum = this.pagenum + 1;
			this.getdata(true);
		}
	},
	onShow() {
		this.getdata()
	},
	methods: {
		getmendian:function(){
			var that=this;
			var mdid =  app.getLocationCache('mendian_id');
			app.get('ApiMendianup/getmendian', {mdid:mdid}, function (res) {
					that.loading = false;
					if(res.status==1){
							that.mendian = res.mendian;
					}else{
						app.error(res.msg)
					}
					that.getdata();
			})
		},
		getdata:function(loadmore){
			if(!loadmore){
				this.pagenum = 1;
				this.datalist = [];
			}
			var that = this;
			var pagenum = that.pagenum;
			var keyword = that.keyword;
			var cid = that.md_cid;
			 that.loading = true;
			that.nodata = false;
			that.nomore = false;
			app.get('ApiMendian/mendianlist', {pagenum: pagenum,keyword: keyword,cid: cid,longitude: that.longitude,latitude: that.latitude,city:that.city1}, function (res) {
				that.loading = false;
				var data = res.data;
				if (pagenum == 1) {
				   that.datalist = data;
				   if (data.length == 0) {
				     that.nodata = true;
				   }
				   	that.loaded();
				}else{
					
					if (data.length == 0) {
						that.nomore = true;
					} else {
						var datalist = that.datalist;
						var newdata = datalist.concat(data);
						that.datalist = newdata;
					}
				}
			
			})
		},
		//头部定位start 只处理微信小程序，其他端的组件实现
		checkLocation:function(){
			var that = this
			//缓存为空 或 显示城市和当前地址切换 或 同城和自定义范围切换 或 显示距离发生变化
			app.getLocation(function(res) {
				that.latitude = res.latitude;
				that.longitude = res.longitude;
				//如果从当前地址切到当前城市，则重新定位用户位置
				app.post('ApiAddress/getAreaByLocation', {latitude:that.latitude,longitude:that.longitude}, function(res) {
					if(res.status==1){
						that.city = res.city
						}else{
							return;
						}
					}
				)
			})
		},
		searchConfirm: function (e) {
		  var that = this;
		  var keyword = e.detail.value;
		  that.keyword = keyword
		  that.getdata();
		},
		changeCategory:function(e){
			var id = e.currentTarget.dataset.id;
			console.log(e);
			this.md_cid = id;
			this.getdata();
		},
    toMendian:function(e){
    	var latitude = parseFloat(e.currentTarget.dataset.latitude);
    	var longitude = parseFloat(e.currentTarget.dataset.longitude);
    	var address = e.currentTarget.dataset.address;
    	if(!latitude || !longitude){
    		return;
    	}
    	uni.openLocation({
    	 latitude:latitude,
    	 longitude:longitude,
    	 name:address,
    	 scale: 13
    	})
    },
		changeMendian:function(e){
			console.log(e)
			var that = this;
			var mendianid = e.currentTarget.dataset.id;
			var index = e.currentTarget.dataset.index;
			that.mendianid = mendianid
			that.mendian = that.datalist[index]

			app.post('ApiMendian/updatemendian', {
				mendianid:mendianid
			}, function(resp) {
				that.loading = false
				if(resp.status==1){
					
					that.locationCache.mendian_id = that.mendian.id
					that.locationCache.mendian_name = that.mendian.name
					app.setLocationCache('mendian_id',that.mendian.id,that.cacheExpireTime)
					app.setLocationCache('mendian_name',that.mendian.name,that.cacheExpireTime)
					app.setLocationCache('mendian_isinit',0)
					
					that.goback();
				}else{
					app.error(resp.msg || '切换失败')
				}
			})
		
		},
		
	}
}

</script>

<style>
	.banner{ position: absolute; width: 100%;height: 280rpx; }
	
	.topsearch{width:100%;padding:16rpx 20rpx;position: absolute;}
	.topsearch .f1{height:60rpx;border-radius:30rpx;border:0;background-color:#f7f7f7;flex:1}
	.topsearch .f1 .img{width:24rpx;height:24rpx;margin-left:10px}
	.topsearch .f1 input{height:100%;flex:1;padding:0 20rpx;font-size:28rpx;color:#333;}
	.topsearch .search-btn{display:flex;align-items:center;color:#5a5a5a;font-size:30rpx;width:60rpx;text-align:center;margin-left:20rpx}
	.topsearch .city{ font-size: 24rpx;line-height: 60rpx;margin-left: 20rpx;}
	.topsearch .title{ text-align: center; height:100rpx;line-height: 100rpx;color: #fff; }
	
	.currentshequ{margin: 20rpx; width: 94%;background: #fff;position: absolute; top:200rpx;border-radius: 10rpx;}
	.currentshequ .border {border:1rpx dashed #F4F4F4; width: 96%;margin: 10rpx;display: flex;align-items: center;flex-direction: column;border-radius: 10rpx; padding:20rpx 0}
	.currentshequ .headimg { position: absolute; top:-50rpx}
	.currentshequ .headimg image{ width: 100rpx;height: 100rpx;}
	.currentshequ .name{ color:#999;margin-top: 40rpx;}
	.currentshequ .xqname{ color:#000;font-weight: bold;font-size: 30rpx;margin: 20rpx;}
	.currentshequ .address{	color: #666;font-size: 24rpx;}
	
	.storeitem{width: 100%;padding:0 0 20rpx 0;display: flex;flex-direction: column;color: #333;}
	.storeitem .title{ height: 80rpx;line-height: 80rpx;font-size: 30rpx; font-weight:bold;margin-left: 20rpx;}
	.storeitem .radio-item {
	    display: flex;
	    width: 100%;
	    color: #000;
	    align-items: center;
	    background: #fff;
	    padding: 20rpx 20rpx;
	    border-bottom: 1px dotted #f1f1f1;
	}
	.radio-item .headimg image{ width: 100rpx;height: 100rpx;margin-right: 10rpx;}
	.storeitem .radio-item .f1 {
	    color: #333;
	    font-size: 30rpx;
			display: flex;
			flex-direction: column;
			width: 80%;
	}
	.storeitem .radio-item .f11{ display: flex;justify-content: space-between;  }
	.storeitem .radio-item .f1 .address{
		text-align: left;
		font-size: 12px;
		color: #aaaaae;
    margin-top: 10rpx;
    display: flex;
    justify-content: space-between;
    align-items: center;
	}
  .storeitem  .address2{
  	display: -webkit-box;
  	-webkit-box-orient: vertical;
  	-webkit-line-clamp: 1;
  	overflow: hidden;
    width: 450rpx;
  }
	.storeitem .radio-item .juli{color: #f50}
	.iconfont {
	    font-family: "iconfont" !important;
	    font-size: 18px;
	    font-style: normal;
	    -webkit-font-smoothing: antialiased;
	    -moz-osx-font-smoothing: grayscale;
	}
	.tab{width: 100%;
		overflow-y: hidden;
		overflow-x: scroll;
		white-space: nowrap;
		padding: 10rpx 20rpx;
		background: #fff
	}
	.tab-item{
		background: #F5F6F8;
		border-radius: 24rpx;
		color: #6C737F;
		font-size: 24rpx;
		line-height: 48rpx;
		padding: 0 28rpx;
		margin: 12rpx 10rpx 12rpx 0;
		display: inline-block;
		white-space: break-spaces;
		max-width: 610rpx;
		vertical-align: middle;
		border: 2rpx solid #e0e0e0;
		padding:  0 20rpx;
	}
	
</style>