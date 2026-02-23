<template>
<view class="container">
	<block v-if="isload">
		<view class="couponbg" :style="{background:'linear-gradient(90deg,'+t('color1')+' 0%,rgba('+t('color1rgb')+',0.8) 100%)'}"></view>
		<view class="orderinfo" :style="{backgroundColor:coupon.bg_color} ">
			<block v-if="record.id"><!-- 已领取的券查看详情 -->
				<view class="topitem">
					<view class="f1" :style="{color:t('color1')}" v-if="record.type==1"><text style="font-size:32rpx">￥</text><text class="t1">{{record.money}}</text></view>
					<view class="f1" :style="{color:t('color1')}" v-else-if="record.type==10"><text class="t1">{{(record.discount/10).toFixed(2)}}</text><text style="font-size:32rpx">折</text></view>
					<view class="f1" :style="{color:t('color1')}" v-else-if="record.type==2">礼品券</view>
					<view class="f1" :style="{color:t('color1')}" v-else-if="record.type==3"><text class="t1">{{record.limit_count}}</text><text class="t2">次</text></view>
					<view class="f1" :style="{color:t('color1')}" v-else-if="record.type==4">抵运费</view>
					<view class="f1" :style="{color:t('color1')}" v-else-if="record.type==5">餐饮券</view>
					<view class="f1" :style="{color:t('color1')}" v-else-if="record.type==20">券包</view>
					<view class="f2">
						<view class="t1" :style="{color:coupon.title_color}">{{record.couponname}}</view>
						<view class="t2" v-if="record.type==1 || record.type==4 || record.type==5">
							<text :style="{color:coupon.font_color}" v-if="record.minprice>0">满{{record.minprice}}元可用</text>
							<text :style="{color:coupon.font_color}" v-else>无门槛</text>
						</view>
						<!-- 类型 -->
						<view class="t2" :style="{color:coupon.font_color ? coupon.font_color : '#2B2B2B'}" v-if="record.type==1">代金券<text v-if="coupon.isgive == 1 || coupon.isgive == 2">（可赠送）</text></view>
						<view class="t2" :style="{color:coupon.font_color ? coupon.font_color : '#2B2B2B'}" v-if="record.type==10">折扣券<text v-if="coupon.isgive == 1 || coupon.isgive == 2">（可赠送）</text></view>
						<view class="t2" :style="{color:coupon.font_color ? coupon.font_color : '#2B2B2B'}" v-if="record.type==2">礼品券<text v-if="coupon.isgive == 1 || coupon.isgive == 2 ">（可赠送）</text></view>
						<view class="t2" :style="{color:coupon.font_color ? coupon.font_color : '#2B2B2B'}" v-if="record.type==3">计次券<text v-if="coupon.isgive == 1 || coupon.isgive == 2">（可赠送）</text></view>
						<view class="t2" :style="{color:coupon.font_color ? coupon.font_color : '#2B2B2B'}" v-if="record.type==4">运费抵扣券<text v-if="coupon.isgive == 1 || coupon.isgive == 2">（可赠送）</text></view>
						<view class="t2" :style="{color:coupon.font_color ? coupon.font_color : '#2B2B2B'}" v-if="record.type==5">餐饮券<text v-if="coupon.isgive == 1 || coupon.isgive == 2">（可赠送）</text></view>
						<view class="t2" :style="{color:coupon.font_color ? coupon.font_color : '#2B2B2B'}" v-if="record.type==20">券包<text v-if="coupon.isgive == 1 || coupon.isgive == 2">（可赠送）</text></view>
					</view>
				</view>
				<view class="item" v-if="coupon.bid!=0">
					<text class="t1" :style="{color:coupon.title_color}">适用商家</text>
					<text class="t2" :style="{color:coupon.font_color}">{{coupon.bname}}</text>
				</view>
				<view class="item" v-if="record.type==3 && record.status==1">
					<text class="t1" :style="{color:coupon.title_color}">次数</text>
					<view class="flex-x-start" :style="{color:coupon.title_color}">共计<view :style="{color:coupon.font_color}">{{record.limit_count}}</view>次  剩余<view :style="{color:t('color1')}" @tap="goto" :data-url="'/pagesExt/coupon/record?crid='+record.id">{{record.surplus_count}}</view>次</view>
				</view>
				<view class="item flex-col" v-if="record.status==0 && record.hexiaoqr && coupon.isgive!=2">
					
					<!-- 餐饮券和定制的折扣券存在 手机端和pc收银台，免费券只有pc，其它的只有手机 -->
					<view class="flex flex-bt" v-if="(coupon.type ==5 || coupon.type ==10) && record.cashdesk_hexiaoqr">
						<text class="t1" @click="changeqrcode('nomal')" style="text-align: center;" :style="{color:qrcodetype=='nomal'?'red':''}" >核销码</text>		
						<text class="t1" @click="changeqrcode('cashdesk')" style="text-align: center;" :style="{color:qrcodetype=='cashdesk'?'red':''}" >收银核销码</text>
					</view>
					<!-- 餐饮收银台免费券，只有收银台能使用 -->
					<view class="flex flex-bt" v-else-if="coupon.type ==51">
						<text class="t1" :style="{color:coupon.title_color}" >收银核销码</text>		
					</view>
					<!-- 其他普通券 -->
					<view class="flex flex-bt" v-else>
						<text class="t1" :style="{color:coupon.title_color}" >核销码</text>		
					</view>
					<!-- 餐饮券，手机端和pc收银台，免费券只有pc，其它的只有手机 end -->
					<view style="margin-bottom: 20rpx;" v-if="record.type==3">
						<view class="flex-x-center" :style="{color:coupon.title_color}">共计<view :style="{color:coupon.font_color}">{{record.limit_count}}</view>次  剩余<view :style="{color:t('color1')}" @tap="goto" :data-url="'/pagesExt/coupon/record?crid='+record.id">{{record.surplus_count}}</view>次</view>
					</view>
					
					<view class="flex-x-center" >
						<image :src="hexiaoqr" style="width:250rpx;height:250rpx" @tap="previewImage" :data-url="hexiaoqr"></image>
					</view>
					<!-- 免费券或者是切换为收银台券码时 显示该提示语 -->
					<block v-if="coupon.type == 51 || qrcodetype =='cashdesk'">
						<text class="flex-x-center" :style="{color:coupon.title_color,fontWeight:'700'}" >券码：{{record.code}}</text>
						<text class="flex-x-center" :style="{color:coupon.title_color}">(仅限店内收银台使用)</text>				
					</block>
					<text class="flex-x-center" :style="{color:coupon.title_color}">到店使用时请出示核销码进行核销</text>
				</view>
				<view class="item">
					<text class="t1" :style="{color:coupon.title_color}">领取时间</text>
					<text class="t2" :style="{color:coupon.font_color}">{{record.createtime}}</text>
				</view>
				<block v-if="record.status==1">
				<view class="item">
					<text class="t1" :style="{color:coupon.title_color}">使用时间</text>
					<text class="t2" :style="{color:coupon.font_color}">{{record.usetime}}</text>
				</view>
				</block>
				
				<view class="item flex-col">
					<text class="t1" :style="{color:coupon.title_color}">有效期</text>
					<text class="t2" :style="{color:coupon.font_color}">{{record.starttime}} 至 {{record.endtime}}</text>
				</view>
				<view class="item flex-col" v-if="coupon.pack_coupon_list.length > 0">
					<text class="t1" :style="{color:coupon.title_color}">包含</text>
					<view class="t2" :style="{color:coupon.font_color}" v-for="(item, index) in coupon.pack_coupon_list" :key="item.id" >{{item.name}}</view>
				</view>
				<view class="item flex-col">
					<text class="t1" :style="{color:coupon.title_color}">使用说明</text>
					<view class="t2" :style="{color:coupon.font_color}">{{coupon.usetips}}</view>
				</view>
			</block>
			<!-- 未领取的券查看详情 -->
			<block v-else>
				<view class="topitem">
					<view class="f1" :style="{color:t('color1')}" v-if="coupon.type==1"><text style="font-size:32rpx">￥</text><text class="t1">{{coupon.money}}</text></view>
					<view class="f1" :style="{color:t('color1')}" v-if="coupon.type==10"><text class="t1">{{coupon.discount/10}}</text><text style="font-size:32rpx">折</text></view>
					<view class="f1" :style="{color:t('color1')}" v-else-if="coupon.type==2">礼品券</view>
					<view class="f1" :style="{color:t('color1')}" v-else-if="coupon.type==3"><text class="t1">{{coupon.limit_count}}</text><text class="t2">次</text></view>
					<view class="f1" :style="{color:t('color1')}" v-else-if="coupon.type==4">抵运费</view>
					<view class="f1" :style="{color:t('color1')}" v-else-if="coupon.type==5">餐饮券</view>
					<view class="f1" :style="{color:t('color1')}" v-else-if="coupon.type==20">券包</view>
					<view class="f2">
						<view class="t1" :style="{color:coupon.title_color}">{{coupon.name}}</view>
						<view class="t2" v-if="coupon.type==1 || coupon.type==4 || coupon.type==5">
							<text :style="{color:coupon.font_color ? coupon.font_color:'#2B2B2B' }"  v-if="coupon.minprice>0">满{{coupon.minprice}}元可用</text>
							<text :style="{color:coupon.font_color ? coupon.font_color:'#2B2B2B' }"  v-else>无门槛</text>
						</view>
						<!-- 类型 -->
						<text class="t2" :style="{color:coupon.font_color ? coupon.font_color : '#2B2B2B'}" v-if="coupon.type==1">代金券</text>
						<text class="t2" :style="{color:coupon.font_color ? coupon.font_color : '#2B2B2B'}" v-if="coupon.type==10">折扣券</text>
						<text class="t2" :style="{color:coupon.font_color ? coupon.font_color : '#2B2B2B'}" v-if="coupon.type==2">礼品券</text>
						<text class="t2" :style="{color:coupon.font_color ? coupon.font_color : '#2B2B2B'}" v-if="coupon.type==3">计次券</text>
						<text class="t2" :style="{color:coupon.font_color ? coupon.font_color : '#2B2B2B'}" v-if="coupon.type==4">运费抵扣券</text>
						<text class="t2" :style="{color:coupon.font_color ? coupon.font_color : '#2B2B2B'}" v-if="coupon.type==5">餐饮券</text>
						<text class="t2" :style="{color:coupon.font_color ? coupon.font_color : '#2B2B2B'}" v-if="coupon.type==20">券包</text>
					</view>
				</view>

				<view class="item" v-if="coupon.bid!=0">
					<text class="t1" :style="{color:coupon.title_color}">适用商家</text>
					<text class="t2" :style="{color:coupon.font_color}">{{coupon.bname}}</text>
				</view>
				<view class="item" v-if="coupon.house_status">
					<text class="t1" :style="{color:coupon.title_color}">领取限制</text>
					<text class="t2" :style="{color:coupon.font_color}">一户仅限一次</text>
				</view>
				<block v-if="coupon.type==3">
				<view class="item">
					<text class="t1" :style="{color:coupon.title_color}">共计次数</text>
					<text class="t2" :style="{color:coupon.font_color}">{{coupon.limit_count}}次</text>
				</view>
				<block v-if="coupon.limit_perday>0">
				<view class="item">
					<text class="t1" :style="{color:coupon.title_color}">每天限制使用</text>
					<text class="t2" :style="{color:coupon.font_color}">{{coupon.limit_perday}}次</text>
				</view>
				</block>
				</block>
				<block v-if="coupon.use_tongzheng==1">
					<view class="item">
						<text class="t1" :style="{color:coupon.title_color}">所需{{t('通证')}}</text>
						<text class="t2" :style="{color:coupon.font_color}">{{coupon.tongzheng}}{{t('通证')}}</text>
					</view>
				</block>
				<block v-if="coupon.price>0">
				<view class="item">
					<text class="t1" :style="{color:coupon.title_color}">所需金额</text>
					<text class="t2" :style="{color:coupon.font_color}">￥{{coupon.price}}</text>
				</view>
				</block>
				<block v-if="coupon.score>0">
				<view class="item">
					<text class="t1" :style="{color:coupon.title_color}">所需{{t('积分')}}</text>
					<text class="t2" :style="{color:coupon.font_color}">{{coupon.score}}{{t('积分')}}</text>
				</view>
				</block>
				<view class="item">
					<text class="t1" :style="{color:coupon.title_color}">活动时间</text>
					<text class="t2" :style="{color:coupon.font_color}">{{coupon.starttime}} ~ {{coupon.endtime}}</text>
				</view>
				<view class="item" v-if="coupon.type != 20">
					<text class="t1" :style="{color:coupon.title_color}">有效期</text>
					<block v-if="coupon.yxqtype==1">
					<text class="t2" :style="{color:coupon.font_color}">{{coupon.yxqtime}}</text>
					</block>
					<block v-else-if="coupon.yxqtype==2">
					<text class="t2" :style="{color:coupon.font_color}">领取后{{coupon.yxqdate}}天内有效</text>
					</block>
					<block v-else-if="coupon.yxqtype==3">
					<text class="t2" :style="{color:coupon.font_color}">领取后{{coupon.yxqdate}}天内有效（次日0点生效）</text>
					</block>
				</view>
				<view class="item" v-if="coupon.pack_coupon_list.length > 0">
					<text class="t1" :style="{color:coupon.title_color}">包含</text>
					<view class="t2" :style="{color:coupon.font_color}" v-for="(item, index) in coupon.pack_coupon_list" :key="item.id" >{{item.name}}</view>
				</view>
				<view class="item">
					<text class="t1" :style="{color:coupon.title_color}">使用说明</text>
					<view class="t2" :style="{color:coupon.font_color}">{{coupon.usetips}}</view>
				</view>
			</block>
		</view>
		<bloack v-if="coupon.use_tongzheng==1">
			<view v-if="!record.id" class="btn-add" :style="{background:'linear-gradient(90deg,'+t('color1')+' 0%,rgba('+t('color1rgb')+',0.8) 100%)'}" @tap="getcouponbytongzheng" :data-id="coupon.id" :data-price="coupon.price" :data-tongzheng="coupon.tongzheng">立即兑换</view>
		</bloack>
		<bloack v-if="coupon.is_birthday_coupon==1 && coupon.birthday_coupon_status > 0">
		
			<view v-if="coupon.birthday_coupon_status==3" class="btn-add" :style="{background:'linear-gradient(90deg,'+t('color1')+' 0%,rgba('+t('color1rgb')+',0.8) 100%)'}" @tap.stop="goto" :data-url="'/pagesExt/my/setbirthday'">设置生日</view>
		</bloack>	
		<block v-else>
		<view v-if="!record.id" class="btn-add" :style="{background:'linear-gradient(90deg,'+t('color1')+' 0%,rgba('+t('color1rgb')+',0.8) 100%)'}" @tap="getcoupon" :data-id="coupon.id" :data-price="coupon.price" :data-score="coupon.score">{{coupon.price>0?'立即购买':(coupon.score>0?'立即兑换':'立即领取')}}</view>
		</block>
		<block v-if="mid == record.mid">
			<!-- 自用+转赠 -->
			<block v-if="coupon.isgive != 2">
				<block v-if="record.id && (coupon.type==1 || coupon.type==10) && record.status==0">
					<view class="btn-add" :style="{background:'linear-gradient(90deg,'+t('color1')+' 0%,rgba('+t('color1rgb')+',0.8) 100%)'}" v-if="inArray(coupon.fwtype,[0,1,2])" @tap.stop="goto" :data-url="'/pages/shop/prolist?cpid='+record.couponid+(coupon.bid?'&bid='+coupon.bid:'')">去使用</view>
					<view class="btn-add" :style="{background:'linear-gradient(90deg,'+t('color1')+' 0%,rgba('+t('color1rgb')+',0.8) 100%)'}" v-if="coupon.fwtype == 4" @tap.stop="goto" :data-url="'/activity/yuyue/prolist?cpid=' + record.couponid+(coupon.bid?'&bid='+coupon.bid:'')">去使用</view>
				</block>
				<view class="btn-add" :style="{background:'linear-gradient(90deg,'+t('color1')+' 0%,rgba('+t('color1rgb')+',0.8) 100%)'}" v-if="record.id && coupon.type==3 && record.status==0 && record.yuyue_proid > 0" @tap.stop="goto" :data-url="'/activity/yuyue/product?id=' + record.yuyue_proid">去预约</view>
				<view class="btn-add" :style="{background:'linear-gradient(90deg,'+t('color1')+' 0%,rgba('+t('color1rgb')+',0.8) 100%)'}" v-if="record.id && coupon.type==20 && record.status==0" @tap.stop="getcouponpack" :data-id="coupon.id">使用</view>
			</block>
			<block v-if="record.id && record.status==0 && !record.from_mid && (coupon.isgive == 1 || coupon.isgive == 2)">
				<view class="btn-add" @tap="shareapp" v-if="getplatform() == 'app'" :style="{background:'linear-gradient(90deg,'+t('color2')+' 0%,rgba('+t('color2rgb')+',0.8) 100%)'}" :data-id="record.id">转赠好友</view>
				<view class="btn-add" @tap="sharemp" v-else-if="getplatform() == 'mp'" :style="{background:'linear-gradient(90deg,'+t('color2')+' 0%,rgba('+t('color2rgb')+',0.8) 100%)'}" :data-id="record.id">转赠好友</view>
				<view class="btn-add" @tap="sharemp" v-else-if="getplatform() == 'h5'" :style="{background:'linear-gradient(90deg,'+t('color2')+' 0%,rgba('+t('color2rgb')+',0.8) 100%)'}" :data-id="record.id">转赠好友</view>
				<button class="btn-add" open-type="share" v-else :style="{background:'linear-gradient(90deg,'+t('color2')+' 0%,rgba('+t('color2rgb')+',0.8) 100%)'}" :data-id="record.id">转赠好友</button>
			</block>
		</block>
		<block v-else>
			<view v-if="(coupon.isgive == 1 || coupon.isgive == 2) && opt.pid == record.mid && opt.pid > 0" class="btn-add" @tap="receiveCoupon" :style="{background:'linear-gradient(90deg,'+t('color2')+' 0%,rgba('+t('color2rgb')+',0.8) 100%)'}" :data-id="record.id">立即领取</view>
		</block>
		
		<view class='text-center' @tap="goto" data-url='/pages/index/index' style="margin-top: 40rpx; line-height: 60rpx;"><text>返回首页</text></view>

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

			textset:{},
			record:{},
			coupon:{},
			shareTitle:'',
			sharePic:'',
			shareDesc:'',
			shareLink:'',
			mid:0,
			qrcodetype:'nomal',
			hexiaoqr:''//二维码链接
		}
  },

  onLoad: function (opt) {
		this.opt = app.getopts(opt);
		this.getdata();
  },
	onPullDownRefresh: function () {
		this.getdata();
	},
	onShareAppMessage:function(){
		return this._sharewx({title:this.shareTitle,pic:this.sharePic,desc:this.shareDesc,link:this.shareLink});
	},
	onShareTimeline:function(){
		var sharewxdata = this._sharewx({title:this.shareTitle,pic:this.sharePic,desc:this.shareDesc,link:this.shareLink});
		var query = (sharewxdata.path).split('?')[1]+'&seetype=circle';
		console.log(sharewxdata)
		console.log(query)
		return {
			title: sharewxdata.title,
			imageUrl: sharewxdata.imageUrl,
			query: query
		}
	},
  methods: {
		getdata: function () {
			var that = this;
			that.loading = true;
			app.get('ApiCoupon/coupondetail', {rid: that.opt.rid,id: that.opt.id}, function (res) {
				that.loading = false;
				that.textset = app.globalData.textset;
				uni.setNavigationBarTitle({
					title: that.t('优惠券') + '详情'
				});
				if(res.status == 0) {
						app.error(res.msg);return;
				}
				if(!res.coupon.id) {
					app.alert(that.t('优惠券')+'不存在');return;
				}
				that.mid = app.globalData.mid;
				that.record = res.record;
				that.coupon = res.coupon;
				that.shareTitle = '送你一张'+that.t('优惠券')+'，点击领取';
				that.shareDesc = that.coupon.name;
				that.sharePic = app.globalData.initdata.logo;
				that.shareLink = app.globalData.pre_url +'/h5/'+app.globalData.aid+'.html#/pagesExt/coupon/coupondetail?scene=id_'+that.coupon.id+'-pid_' + app.globalData.mid+'-rid_' + that.record.id;
				that.hexiaoqr = that.record.hexiaoqr;
				if(that.record.type==51){
					//免费券只显示收银台码
					that.hexiaoqr = that.record.cashdesk_hexiaoqr;
				}
				that.loaded({title:that.shareTitle,pic:that.sharePic,desc:that.shareDesc,link:that.shareLink});
			});
		},

		getcoupon:function(e){
			var that = this;
			var couponinfo = that.coupon;
			if (app.globalData.platform == 'wx' && couponinfo.rewardedvideoad && wx.createRewardedVideoAd) {
				app.showLoading();
				if(!app.globalData.rewardedVideoAd[couponinfo.rewardedvideoad]){
					app.globalData.rewardedVideoAd[couponinfo.rewardedvideoad] = wx.createRewardedVideoAd({ adUnitId: couponinfo.rewardedvideoad});
				}
				var rewardedVideoAd = app.globalData.rewardedVideoAd[couponinfo.rewardedvideoad];
				rewardedVideoAd.load().then(() => {app.showLoading(false);rewardedVideoAd.show();}).catch(err => { app.alert('加载失败');});
				rewardedVideoAd.onError((err) => {
					app.showLoading(false);
					app.alert(err.errMsg);
					console.log('onError event emit', err)
					rewardedVideoAd.offLoad()
					rewardedVideoAd.offClose();
				});
				rewardedVideoAd.onClose(res => {
					app.globalData.rewardedVideoAd[couponinfo.rewardedvideoad] = null;
					if (res && res.isEnded) {
						//app.alert('播放结束 发放奖励');
						that.getcouponconfirm(e);
					} else {
						console.log('播放中途退出，不发奖励');
					}
					rewardedVideoAd.offLoad()
					rewardedVideoAd.offClose();
				});
			}else{
				that.getcouponconfirm(e);
			}
		},
    getcouponconfirm: function (e) {
			var that = this;
			var datalist = that.datalist;
			var id = e.currentTarget.dataset.id;
			var score = parseInt(e.currentTarget.dataset.score);
			var price = e.currentTarget.dataset.price;

			if (price > 0) {
				app.post('ApiCoupon/buycoupon', {id: id}, function (res) {
					if(res.status == 0) {
							app.error(res.msg);
					} else {
						app.goto('/pagesExt/pay/pay?id=' + res.payorderid);
					}
				})
				return;
			}
			var key = e.currentTarget.dataset.key;
			if (score > 0) {
				app.confirm('确定要消耗' + score + '' + that.t('积分') + '兑换吗?', function () {
					app.showLoading('兑换中');
					app.post('ApiCoupon/getcoupon', {id: id}, function (data) {
						app.showLoading(false);
						if (data.status == 0) {
							app.error(data.msg);
						} else {
							app.success(data.msg);
							setTimeout(function(){
								app.goto('mycoupon');
							},1000)
						}
					});
				});
			} else {
				app.showLoading('领取中');
				app.post('ApiCoupon/getcoupon', {id: id}, function (data) {
					app.showLoading(false);
					if (data.status == 0) {
						app.error(data.msg);
					} else {
						app.success(data.msg);
						setTimeout(function(){
							app.goto('mycoupon');
						},1000)
					}
				});
			}
    },
		getcouponpack: function (e) {
			var that = this;
			var id = e.currentTarget.dataset.id;
			app.confirm('确定要使用吗?使用后领取卡券到账户，不可转赠', function () {
				app.showLoading('领取中');
				app.post('ApiCoupon/getcouponpack', {id: id}, function (data) {
					app.showLoading(false);
					if (data.status == 0) {
						app.error(data.msg);
					} else {
						app.success(data.msg);
						setTimeout(function(){
							app.goto('mycoupon');
						},1000)
					}
				});
			});
    },
		receiveCoupon:function(e){
			var that = this;
			var couponinfo = that.coupon;
			if (app.globalData.platform == 'wx' && couponinfo.rewardedvideoad && wx.createRewardedVideoAd) {
				app.showLoading();
				if(!app.globalData.rewardedVideoAd[couponinfo.rewardedvideoad]){
					app.globalData.rewardedVideoAd[couponinfo.rewardedvideoad] = wx.createRewardedVideoAd({ adUnitId: couponinfo.rewardedvideoad});
				}
				var rewardedVideoAd = app.globalData.rewardedVideoAd[couponinfo.rewardedvideoad];
				rewardedVideoAd.load().then(() => {app.showLoading(false);rewardedVideoAd.show();}).catch(err => { app.alert('加载失败');});
				rewardedVideoAd.onError((err) => {
					app.showLoading(false);
					app.alert(err.errMsg);
					console.log('onError event emit', err)
					rewardedVideoAd.offLoad()
					rewardedVideoAd.offClose();
				});
				rewardedVideoAd.onClose(res => {
					app.globalData.rewardedVideoAd[couponinfo.rewardedvideoad] = null;
					if (res && res.isEnded) {
						//app.alert('播放结束 发放奖励');
						that.receiveCouponConfirm(e);
					} else {
						console.log('播放中途退出，不下发奖励');
					}
					rewardedVideoAd.offLoad()
					rewardedVideoAd.offClose();
				});
			}else{
				that.receiveCouponConfirm(e);
			}
		},
		receiveCouponConfirm:function(e){
			var that = this;
			var datalist = that.datalist;
			var rid = that.record.id;
			var id = that.coupon.id;
			app.showLoading('领取中');
			app.post('ApiCoupon/receiveCoupon', {id: id,rid:rid}, function (data) {
				app.showLoading(false);
				if (data.status == 0) {
					app.error(data.msg);
				} else {
					app.success(data.msg);
					that.getdata();
				}
			});
		},
		
		sharemp:function(){
			// app.error('点击右上角发送给好友或分享到朋友圈');
			let that = this;
			uni.setClipboardData({
				data: that.shareLink,
				success: function() {
					uni.showToast({
						title: '复制成功,快去分享吧！',
						duration: 3000,
						icon: 'none'
					});
				},
				fail: function(err) {
					uni.showToast({
						title: '复制失败',
						duration: 2000,
						icon: 'none'
					});
				}
			});
			this.sharetypevisible = false
		},
		shareapp:function(){
			var that = this;
			that.sharetypevisible = false;
			uni.showActionSheet({
		    itemList: ['发送给微信好友', '分享到微信朋友圈'],
		    success: function (res){
					if(res.tapIndex >= 0){
						var scene = 'WXSceneSession';
						if (res.tapIndex == 1) {
							scene = 'WXSenceTimeline';
						}
						var sharedata = {};
						sharedata.provider = 'weixin';
						sharedata.type = 0;
						sharedata.scene = scene;
						sharedata.title = '送你一张优惠券，点击领取';
						sharedata.summary = that.shareDesc;
						sharedata.href = that.shareLink;
						sharedata.imageUrl = that.sharePic;
						
						uni.share(sharedata);
					}
		    }
		  });
		},
		changeqrcode(type){
			var that = this;
			that.qrcodetype = type
			var hexiaoqr = that.record.hexiaoqr;
			if(type =='cashdesk'){
				hexiaoqr = that.record.cashdesk_hexiaoqr;
			}else{
				hexiaoqr = that.record.hexiaoqr;
			}
			that.hexiaoqr = hexiaoqr;
		},
		getcouponbytongzheng: function (e) {
			var that = this;
			var datalist = that.datalist;
			var id = e.currentTarget.dataset.id;
			var tongzheng = parseInt(e.currentTarget.dataset.tongzheng);
			var price = e.currentTarget.dataset.price;
			var key = e.currentTarget.dataset.key;
			if (tongzheng > 0) {
				app.confirm('确定要消耗' + tongzheng + '' + that.t('通证') + '兑换吗?', function () {
					app.showLoading('兑换中');
					app.post('ApiCoupon/getcoupon', {id: id}, function (data) {
						app.showLoading(false);
						if (data.status == 0) {
							app.error(data.msg);
						} else {
							app.success(data.msg);
							setTimeout(function(){
								app.goto('mycoupon');
							},1000)
						}
					});
				});
			} else {
				app.showLoading('领取中');
				app.post('ApiCoupon/getcoupon', {id: id}, function (data) {
					app.showLoading(false);
					if (data.status == 0) {
						app.error(data.msg);
					} else {
						app.success(data.msg);
						setTimeout(function(){
							app.goto('mycoupon');
						},1000)
					}
				});
			}
		}
  }
};
</script>
<style>
.container{display:flex;flex-direction:column; padding-bottom: 30rpx;}
.couponbg{width:100%;height:500rpx;}
.orderinfo{ width:94%;margin:-400rpx 3% 20rpx 3%;border-radius:8px;padding:14rpx 3%;background: #FFF;color:#333;}
.orderinfo .topitem{display:flex;padding:24rpx 40rpx;align-items:center;border-bottom:2px dashed #E5E5E5;position:relative}
.orderinfo .topitem .f1{font-size:50rpx;font-weight:bold;white-space: nowrap;}
.orderinfo .topitem .f1 .t1{font-size:60rpx;}
.orderinfo .topitem .f1 .t2{font-size:40rpx;}
.orderinfo .topitem .f2{margin-left:40rpx}
.orderinfo .topitem .f2 .t1{font-size:36rpx;color:#2B2B2B;font-weight:bold;word-break: break-all;}
.orderinfo .topitem .f2 .t2{font-size:24rpx;color:#999999;height:50rpx;line-height:50rpx}
.orderinfo .item{display:flex;flex-direction:column;width:100%;padding:0 40rpx;margin-top:38rpx}
.orderinfo .item:last-child{ border-bottom: 0;}
.orderinfo .item .t1{width:200rpx;color:#2B2B2B;font-weight:bold;font-size:30rpx;height:60rpx;line-height:60rpx}
.orderinfo .item .t2{font-size:28rpx;height:auto;line-height:40rpx;white-space:pre-wrap;}
.orderinfo .item .red{color:red}
.flex-x-start{display: flex;justify-content: flex-start;}
.text-center { text-align: center;}
.btn-add{width:90%;margin:30rpx 5%;height:96rpx; line-height:96rpx; text-align:center;color: #fff;font-size:30rpx;font-weight:bold;border-radius:48rpx;}
</style>