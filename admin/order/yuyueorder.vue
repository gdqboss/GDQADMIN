<template>
<view class="container">
	<block v-if="isload">
		<dd-tab :itemdata="['全部','待付款','派单中','待确认','已完成','已取消']" :itemst="['all','0','1','2','3','4']" :st="st" :isfixed="true" @changetab="changetab"></dd-tab>
		<view style="width:100%;height:100rpx"></view>
		<!-- #ifndef H5 || APP-PLUS -->
		<view class="topsearch flex-y-center">
			<view class="f1 flex-y-center">
				<image class="img" :src="pre_url+'/static/img/search_ico.png'"></image>
				<input :value="keyword" placeholder="输入关键字搜索" placeholder-style="font-size:24rpx;color:#C2C2C2" @confirm="searchConfirm"></input>
			</view>
		</view>
		<!--  #endif -->
		<view class="order-content">
			<block v-for="(item, index) in datalist" :key="index">
				<view class="order-box" @tap.stop="goto" :data-url="'yuyueorderdetail?id=' + item.id">
					<view class="head">
						<view class="f1" v-if="item.bid!=0" @tap.stop="goto" :data-url="'/pagesExt/business/index?id=' + item.bid"><image :src="pre_url+'/static/img/ico-shop.png'"></image> {{item.binfo.name}}</view>
						<view v-else>订单号：{{item.ordernum}}</view>
						<view class="flex1"></view>
						<text v-if="item.status==0" class="st0">待付款</text>
						
						<block v-if="item.status==1 && item.refund_status==0 && item.worker_orderid">
							<text v-if="item.worker.status==0" class="st1">待接单</text>
							<text v-if="item.worker.status==1" class="st1">已接单</text>
							<text v-if="item.worker.status==2" class="st2">服务中</text>
						</block>
						<block v-else-if="item.status==1 && item.refund_status==0">
							<text class="st1">派单中</text>
						</block>	
						<text v-if="item.status==1 && item.refund_status==1" class="st1">退款审核中</text>
						<text v-if="item.status==2" class="st2">服务中</text>
						<text v-if="item.status==3 && item.isconmement==0" class="st3">待评价</text>
						<text v-if="item.status==3" class="st4">已完成</text>
						<text v-if="item.status==4" class="st4">订单已关闭</text>
					</view>
					<view class="content" style="border-bottom:none">
						<view v-if="item.paidan_type==3" >
							<image :src="item.propic" ></image>
						</view>
						<view v-else @tap.stop="goto" :data-url="'product?id=' + item.proid">
							<image :src="item.propic">
						</view>	
						<view class="detail">
							<text class="t1">{{item.proname}}</text>
		
							<text class="t1">预约日期：{{item.yy_time}}</text>
							<text class="t2" v-if="yuyue_sign">服务地址：{{item.area}}</text>
							<view class="t3" v-if="item.balance_price>0"><text class="x1 flex1">实付金额：￥{{item.totalprice}}</text><text class="x1 flex1" v-if="item.balance_price>0">尾款：￥{{item.balance_price}}</text></view>
							<view class="t3" v-else><text class="x1 flex1">实付金额：￥{{item.totalprice}}</text><text class="x1 flex1" v-if="item.showpaidanfee">含跑腿费：￥{{item.paidan_money}}</text></view>
						</view>
					</view>
					<view class="bottom"  v-if="item.send_time>0">
						<text>派单时间：{{item.senddate}}</text>
						<text v-if="item.refund_status==1" style="color:red"> 退款中￥{{item.refund_money}}</text>
						<text v-if="item.refund_status==2" style="color:red"> 已退款￥{{item.refund_money}}</text>
						<text v-if="item.refund_status==3" style="color:red"> 退款申请已驳回</text>
					</view>
					<view class="op">
						<view v-if="yuyue_sign && item.status==2 && item.addmoney>0 && item.addmoneyStatus==0 " @tap.stop="update" class="btn2" :data-addprice="item.addmoney" :data-addmoneyPayorderid="item.addmoneyPayorderid" :data-orderid="item.id">修改差价</view>
						<view @tap.stop="goto" :data-url="'yuyueorderdetail?id=' + item.id" class="btn2">详情</view>
					</view>
					<view class="bottom flex-y-center">
						<image :src="item.member.headimg" style="width:40rpx;height:40rpx;border-radius:50%;margin-right:10rpx"/><text style="font-weight:bold;color:#333;margin-right:8rpx">{{item.member.nickname}}</text>(ID:{{item.mid}})
					</view>
				</view>
			</block>
		</view>
		
		<view class="modal" v-if="showmodal">
			<view class="addmoney">
					<view class="title">{{addprice>0?'修改':'创建'}}补余款</view>
					<view class="item">
						<label class="label">金额：</label><input type="text" @input="bindMoney" name="blance_price" :value="addprice" placeholder="请输入补余款金额"  placeholder-style="font-size:24rpx"/>元
					</view>
					<view class="btn"><button class="btn-cancel" @tap="cancel">取消</button><button class="confirm"  :style="'background:linear-gradient(90deg,'+t('color1')+' 0%,rgba('+t('color1rgb')+',0.8) 100%)'"  @tap.stop="addconfirm">确定</button></view>
			</view>
		</view>	
		
		<nomore v-if="nomore"></nomore>
		<nodata v-if="nodata"></nodata>
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

      st: 'all',
      datalist: [],
      pagenum: 1,
      nomore: false,
			nodata:false,
      codtxt: "",
			keyword:"",
			yuyue_sign:false,
			showmodal:false,
			addprice:0,
			pre_url:app.globalData.pre_url,
    };
  },

  onLoad: function (opt) {
		this.opt = app.getopts(opt);
		this.getdata();
  },
	onPullDownRefresh: function () {
		this.getdata();
	},
	onShow: function () {
			this.getdata();
	},
  onReachBottom: function () {
    if (!this.nodata && !this.nomore) {
      this.pagenum = this.pagenum + 1;
      this.getdata(true);
    }
  },
	onNavigationBarSearchInputConfirmed:function(e){
		this.searchConfirm({detail:{value:e.text}});
	},
  methods: {
    getdata: function (loadmore) {
			if(!loadmore){
				this.pagenum = 1;
				this.datalist = [];
			}
      var that = this;
      var pagenum = that.pagenum;
      var st = that.st;
			that.nodata = false;
			that.nomore = false;
			that.loading = true;
      app.post('ApiAdminOrder/yuyueorder', {keyword:that.keyword,st: st,pagenum: pagenum}, function (res) {
				that.loading = false;
        var data = res.datalist;
				var yuyue_sign = res.yuyue_sign
				that.yuyue_sign = yuyue_sign
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
      });
    },
    changetab: function (st) {
      this.st = st;
      uni.pageScrollTo({
        scrollTop: 0,
        duration: 0
      });
      this.getdata();
    },
		searchConfirm:function(e){
			this.keyword = e.detail.value;
      this.getdata(false);
		},
		cancel:function(e){
			var that=this
			this.showmodal=false
		},
		update:function(e){
			var that=this
			this.showmodal=true
			var index= e.currentTarget.dataset.key
			that.addprice = e.currentTarget.dataset.addprice
			that.addmoneyPayorderid = e.currentTarget.dataset.addmoneypayorderid
			that.orderid = e.currentTarget.dataset.orderid
		},
		bindMoney:function(e){
			var that=this
			that.addprice = e.detail.value
		},
		addconfirm:function(e){
			var that = this
			if(!that.addprice){
				app.error('请输入金额');
				return;
			} 
			app.post('ApiAdminOrder/addyuyuemoney', {orderid:that.orderid,price:that.addprice,addmoneyPayorderid:that.addmoneyPayorderid}, function (data) {
				app.showLoading(false);
				app.success(data.msg);
				if(data.payorderid){
						that.showmodal=false
						that.getdata()
				}
			});
		},
  }
};
</script>
<style>
.container{ width:100%;}
.topsearch{width:94%;margin:10rpx 3%;}
.topsearch .f1{height:60rpx;border-radius:30rpx;border:0;background-color:#fff;flex:1}
.topsearch .f1 .img{width:24rpx;height:24rpx;margin-left:10px}
.topsearch .f1 input{height:100%;flex:1;padding:0 20rpx;font-size:28rpx;color:#333;}
.order-content{display:flex;flex-direction:column}
.order-box{ width: 94%;margin:10rpx 3%;padding:6rpx 3%; background: #fff;border-radius:8px}
.order-box .head{ display:flex;width:100%; border-bottom: 1px #f4f4f4 solid; height: 70rpx; line-height: 70rpx; overflow: hidden; color: #999;}
.order-box .head .f1{display:flex;align-items:center;color:#333}
.order-box .head .f1 image{width:34rpx;height:34rpx;margin-right:4px}
.order-box .head .st0{ width: 140rpx; color: #ff8758; text-align: right; }
.order-box .head .st1{ width: 140rpx; color: #ffc702; text-align: right; }
.order-box .head .st2{ width: 140rpx; color: #ff4246; text-align: right; }
.order-box .head .st3{ width: 140rpx; color: #999; text-align: right; }
.order-box .head .st4{ width: 140rpx; color: #bbb; text-align: right; }

.order-box .content{display:flex;width: 100%; padding:16rpx 0px;border-bottom: 1px #f4f4f4 dashed;position:relative}
.order-box .content:last-child{ border-bottom: 0; }
.order-box .content image{ width: 140rpx; height: 140rpx;}
.order-box .content .detail{display:flex;flex-direction:column;margin-left:14rpx;flex:1}
.order-box .content .detail .t1{font-size:26rpx;line-height:36rpx;margin-bottom:10rpx;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;}
.order-box .content .detail .t2{height: 46rpx;line-height: 46rpx;color: #999;overflow: hidden;font-size: 26rpx;}
.order-box .content .detail .t3{display:flex;height:40rpx;line-height:40rpx;color: #ff4246;}
.order-box .content .detail .x1{ flex:1}
.order-box .content .detail .x2{ width:100rpx;font-size:32rpx;text-align:right;margin-right:8rpx}

.order-box .bottom{ width:100%; padding: 10rpx 0px; border-top: 1px #f4f4f4 solid; color: #555;}
.order-box .op{ display:flex;justify-content:flex-end;align-items:center;width:100%; padding: 10rpx 0px; border-top: 1px #f4f4f4 solid; color: #555;}

.btn1{margin-left:20rpx;width:160rpx;height:60rpx;line-height:60rpx;color:#fff;border-radius:3px;text-align:center}
.btn2{margin-left:20rpx;width:160rpx;height:60rpx;line-height:60rpx;color:#333;background:#fff;border:1px solid #cdcdcd;border-radius:3px;text-align:center}

/*.order-pin{ border: 1px #ffc702 solid; border-radius: 5px; color: #ffc702; float: right; padding: 0 5px; height: 23px; line-height: 23px; margin-left: 5px; font-size: 14px; position: absolute; bottom: 10px; right: 10px; background: #fff; }*/
.order-pin{ border: 1px #ffc702 solid; border-radius: 5px; color: #ffc702; float: right; padding: 0 5px; height: 23px; line-height: 23px; margin-left: 5px;}

.zan-tex{clear:both; display: block; width: 100%; color: #565656; font-size: 12px; height: 30px; line-height: 30px; text-align: center;  }
.ind-bot{ width: 100%; float: left; text-align: center; height: 50px; line-height: 50px; font-size: 13px; color: #ccc; background:#F2F2F2}

.order-box .bottom{ width:100%; padding: 10rpx 0px; border-top: 1px #f4f4f4 solid; color: #555;}
.order-box .op{ display:flex;justify-content:flex-end;align-items:center;width:100%; padding: 10rpx 0px; border-top: 1px #f4f4f4 solid; color: #555;}

.modal{ position: fixed; width: 100%; height: 100%; bottom: 0; background: rgb(0,0,0,0.4); z-index: 100; display: flex; justify-content: center;}
.modal .addmoney{ width: 100%; background: #fff; width: 80%; position: absolute; top: 30%; border-radius: 10rpx; }
.modal .title{ height: 80rpx; ;line-height: 80rpx; text-align: center; font-weight: bold; border-bottom: 1rpx solid #f5f5f5; font-size: 32rpx; }
.modal .item{ display: flex; padding: 30rpx;}
.modal .item input{ width: 200rpx;}
.modal .item label{ width:200rpx; text-align: right; font-weight: bold;}
.modal .item .t2{ color: #008000; font-weight: bold;}
.modal .btn{ display: flex; margin: 30rpx 20rpx; }
.modal .btn .btn-cancel{  background-color: #F2F2F2; width: 150rpx; border-radius: 10rpx;}
.modal .btn .confirm{ width: 150rpx; border-radius: 10rpx; color: #fff;}
.modal .btn .btn-update{ width: 150rpx; border-radius: 10rpx; color: #fff; }
</style>