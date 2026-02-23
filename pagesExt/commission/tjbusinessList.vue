<template>
<view class="container">
	<block v-if="isload">
		<view class="topfix">
			<view class="toplabel">
				<text class="t1">商家个数（{{count}}）</text>
			</view>
		</view>
		<view class="ind_business">
			<view class="ind_buslist" id="datalist">
				<block v-for="(item, index) in datalist" :key="index">
				<view @tap="goto" :data-url="'/pagesExt/business/index?id=' + item.id">
					<view class="ind_busbox flex1 flex-row">
						<view class="ind_buspic flex0"><image :src="item.logo"></image></view>
						<view class="flex1">
							<view class="bus_title">{{item.name}}</view>
							<view style="padding-top: 10px;">商户ID:{{item.id}}</view>
							<view class="bus_sales">销量：{{item.sales}}</view>
						</view>
					</view>
				</view>
				</block>
				<nomore v-if="nomore"></nomore>
				<nodata v-if="nodata"></nodata>
			</view>
		</view>
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
      field: 'juli',
			order:'asc',
			clist:[],
      datalist: [],
      pagenum: 1,
      nomore: false,
      nodata: false,
      types: "",
      showfilter: "",
			showtype:0,
			buydialogShow:false,
			count:0
    };
  },

  onLoad: function (opt) {
		this.opt = app.getopts(opt);
		this.getDataList();
  },
	onPullDownRefresh: function () {
		this.getDataList();
	},
  onReachBottom: function () {
    if (!this.nodata && !this.nomore) {
      this.pagenum = this.pagenum + 1;
      this.getDataList(true);
    }
  },
  methods: {
    getDataList: function (loadmore) {
			if(!loadmore){
				this.pagenum = 1;
				this.datalist = [];
			}
      var that = this;
      var pagenum = that.pagenum;
      var keyword = that.keyword;
			that.loading = true;
			that.nodata = false;
			that.nomore = false;
      app.post('ApiAgent/tjblist', {pagenum: pagenum,field: that.field,order: that.order}, function (res) {
        that.loading = false;
				uni.stopPullDownRefresh();
        var data = res.data;
				var count =  res.count
				that.count = count
				that.loaded();
        if (pagenum == 1) {
          that.datalist = data;
          if (data.length == 0) {
            that.nodata = true;
          }
        }else{
          if (data.length == 0) {
            that.nomore = true;
          } else {
            var datalist = that.datalist;
            var newdata = datalist.concat(data);
            that.datalist = newdata;
          }
        }

      });
    },
  }
};
</script>
<style>
.toplabel{width: 100%;background: #fff;padding: 30rpx;display:flex;}


.ind_business {width: 100%;font-size:26rpx;padding:0 24rpx; margin-top: 20rpx;}
.ind_business .ind_busbox{ width:100%;background: #fff;padding:20rpx;overflow: hidden; margin-bottom:20rpx;border-radius:8rpx;position:relative}
.ind_business .ind_buspic{ width:100rpx;height:100rpx; margin-right: 28rpx; }
.ind_business .ind_buspic image{ width: 100%;height:100%;border-radius: 8rpx;object-fit: cover;}
.ind_business .bus_title{ font-size: 30rpx; color: #222;font-weight:bold;line-height:46rpx}
.ind_business .bus_score{font-size: 24rpx;color:#FC5648;display:flex;align-items:center}
.ind_business .bus_score .img{width:24rpx;height:24rpx;margin-right:10rpx}
.ind_business .bus_score .txt{margin-left:20rpx}
.ind_business .indsale_box{ display: flex}
.ind_business .bus_sales{ font-size: 24rpx; color:#999;position:absolute;top:20rpx;right:28rpx}

</style>