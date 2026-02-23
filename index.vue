<template>
	<view class="view-width">
		<block v-if="isload">
			<!-- background:'url('+pre_url+'/static/img/admin/headbgimg.png)' -->
        <view class="head-class" :style="{background:(set.bgimg ? 'url('+set.bgimg+')' : 'none'),backgroundSize:'cover',backgroundRepeat:'no-repeat'}">
			<!-- #ifndef H5 -->
			<view :style="{height:(44+statusBarHeight)+'px'}"></view>
			<!-- #endif -->
			<view class="head-view flex-bt flex-y-center">
				<view class="avat-view">
					<view class="user-info flex-row" @tap="goto" :data-url="uinfo.bid==0 ? '/pages/index/index' : '/pagesExt/business/index?id='+uinfo.bid" >
						 <text class="nickname">{{set.name}}</text><text class="nickname" v-if="uinfo.bid > 0" style="font-weight: normal;">(ID:{{uinfo.bid}})</text>
					</view>
          <image class="imgback" :src="`${pre_url}/static/img/location/right-black.png`" ></image>
				</view>
				<view class="option-img-view">
					<view  style="margin-right: 28rpx;" class="setup-view" @tap="saoyisao"  v-if="auth_data.hexiao_auth_data">
						<image :src="`${pre_url}/static/img/admin/saoyisao.png`"></image>
					</view>
					<view class="setup-view" @tap="goto" data-url="setpage">
						<image class="setup-img":src="`${pre_url}/static/img/admin/setup.png`"></image>
					</view>
				</view>
			</view>
			<view class="today-data flex-bt flex-y-center"  v-if="auth_data.finance">
				<view class="option-view flex-col flex-x-center">
					<view class="title-text flex flex-y-center flex-x-center" @click="explanation">今日收款<image class="title-icon" :src="`${pre_url}/static/img/admin/jieshiicon.png`"></image></view>
					<view class="flex-y-center flex-x-center">
						<text class="unit-money">￥</text><text class="num-text">{{today_money}}</text>
					</view>	
				</view>
				<view class="option-view flex-col flex-x-center">
					<text class="title-text">今日订单</text>
					<view class="flex-y-center flex-x-center">
						<text class="num-text">{{today_order_count}}</text>
					</view>	
				</view>
				<view class="option-view flex-col flex-x-center" v-if="inArray('show_hx_num',show_auth)">
					<text class="title-text">核销次数</text>
					<view class="flex-y-center flex-x-center">
						<text class="num-text">{{uinfo.hexiao_num}}</text>
					</view>	
				</view>
				<!-- <view class="option-view flex-col flex-x-center">
					<text class="title-text">今日访客数</text>
					<view class="flex-y-center flex-x-center">
						<text class="num-text">5555</text>
					</view>	
				</view> -->
			</view>
			
	
		<view class="meun-view flex-aw">
			<view class="meun-options flex-col flex-x-center" @tap="goto" data-url="../hexiao/record"  v-if="auth_data.hexiao_auth_data">
				<image :src="`${pre_url}/static/img/admin/menu1.png`" class="menu-img"></image>
				<text class="menu-text">核销记录</text>
			</view>
			<block v-if="auth_data.product">			
				<view class="meun-options flex-col flex-x-center" @tap="goto" data-url="../product/index">
					<image :src="`${pre_url}/static/img/admin/menu2.png`" class="menu-img"></image>
					<text class="menu-text">商品管理</text>
        </view>
        </block>



        
			<view class="meun-options flex-col flex-x-center" @tap="goto" data-url="../index/setnotice" v-if="uinfo.shownotice">
				<image :src="`${pre_url}/static/img/admin/menu3.png`" class="menu-img"></image>
				<text class="menu-text">消息通知</text>
			</view>
			<view class="meun-options flex-col flex-x-center" @tap="goto" data-url="login">
				<image :src="`${pre_url}/static/img/admin/menu4.png`" class="menu-img"></image>
				<text class="menu-text">切换账号</text>
			</view>
			<view class="meun-options flex-col flex-x-center" @tap="goto" data-url="setpwd">
				<image :src="`${pre_url}/static/img/admin/menu5.png`" class="menu-img"></image>
				<text class="menu-text">修改密码</text>
			</view>
		</view>
        
        

		<block v-if="auth_data.order">
		<view class="mall-orders flex-col width" v-if="showshoporder">
			<view class="order-title flex-bt">
				<view class="title-text flex-y-center"><image class="left-img" :src="`${pre_url}/static/img/admin/titletips.png`"></image>商城订单</view>
				<view class="all-text flex-y-center" @tap="goto" data-url="../order/shoporder">全部订单<image class="right-img" :src="`${pre_url}/static/img/admin/jiantou.png`"></image></view>
			</view>
            <view class="order-list flex-bt">
                <view class="option-order flex-col" @tap="goto" data-url="../order/shoporder?st=0">
                    <text class="num-text">{{count0}}</text>
                    <text class="title-text">待付款</text>
                </view>
                <view class="option-order flex-col" @tap="goto" data-url="../order/shoporder?st=1">
                    <text class="num-text">{{count1}}</text>
                    <text class="title-text">待发货</text>
                </view>
                <view class="option-order flex-col" @tap="goto" data-url="../order/shoporder?st=2">
					<text class="num-text">{{count2}}</text>
					<text class="title-text">待收货</text>
				</view>
				<view class="option-order flex-col" @tap="goto" data-url="../order/shopRefundOrder">
					<text class="num-text">{{count4}}</text>
					<text class="title-text">退款/售后</text>
				</view>
				<view class="option-order flex-col" @tap="goto" data-url="../order/shoporder?st=3">
					<text class="num-text">{{count3}}</text>
					<text class="title-text">已完成</text>
				</view>
			</view>
		</view>
		</block>
		</view>

        <block v-if="auth_data.order && showcollageorder">
            <view class="mall-orders flex-col width">
                <view class="order-title flex-bt">
                    <view class="title-text flex-y-center"><image class="left-img" :src="`${pre_url}/static/img/admin/titletips.png`"></image>拼团订单</view>
                    <view class="all-text flex-y-center" @tap="goto" data-url="../order/collageorder">全部订单<image class="right-img" :src="`${pre_url}/static/img/admin/jiantou.png`"></image></view>
                </view>
                <view class="order-list flex-bt">
                    <view class="option-order flex-col" @tap="goto" data-url="../order/collageorder?st=0">
                        <text class="num-text">{{collageCount0}}</text>
                        <text class="title-text">待付款</text>
                    </view>
                    <view class="option-order flex-col" @tap="goto" data-url="../order/collageorder?st=1">
                        <text class="num-text">{{collageCount1}}</text>
                        <text class="title-text">待发货</text>
                    </view>
                    <view class="option-order flex-col" @tap="goto" data-url="../order/collageorder?st=2">
                        <text class="num-text">{{collageCount2}}</text>
                        <text class="title-text">待收货</text>
                    </view>
                    <view class="option-order flex-col" @tap="goto" data-url="../order/collageorder?st=10">
                        <text class="num-text">{{collageCount4}}</text>
                        <text class="title-text">退款/售后</text>
                    </view>
                    <view class="option-order flex-col" @tap="goto" data-url="../order/collageorder?st=3">
                        <text class="num-text">{{collageCount3}}</text>
                        <text class="title-text">已完成</text>
                    </view>
                </view>
            </view>
        </block>

        
		

    <block v-if="showmdmoney == 1 && (auth_data.mendian_mdmoneylog || auth_data.mendian_mdwithdraw || auth_data.mendian_mdwithdrawlog)">
    	<view class="menu-manage flex-col">
    		<view class="menu-title">门店余额</view>
    		<view class="menu-list width">
    			<block>
    				<view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="../finance/mdmoneylog" v-if="auth_data.mendian_mdmoneylog">
    					<image :src="`${pre_url}/static/img/admin/financenbg7.png`" class="menu-img"></image>
    					<text class="menu-text">余额明细</text>
    				</view>
    				<view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="../finance/mdwithdraw" v-if="auth_data.mendian_mdwithdraw">
    					<image :src="`${pre_url}/static/img/admin/financenbg8.png`" class="menu-img"></image>
    					<text class="menu-text">余额提现</text>
    				</view>
    				<view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="../finance/mdwithdrawlog" v-if="auth_data.mendian_mdwithdrawlog">
    					<image :src="`${pre_url}/static/img/admin/financenbg8.png`" class="menu-img"></image>
    					<text class="menu-text">提现记录</text>
    				</view>
    			</block>
    		</view>
    	</view>
    </block>

        <view class="menu-manage" v-if="shopDataShow"></view>
		
		
        <view class="menu-manage flex-col" v-if="false">
			<view class="menu-title">商家数据</view>
			<view class="mer-list">
				<view class="merchant-view cysj-text" :style="{background:'url('+pre_url+'/static/img/admin/merbg1.png)',backgroundSize:'cover',backgroundRepeat:'no-repeat'}">
					<view class="mer-title">餐饮数据</view>
					<block>

					</block>
				</view>
				<view class="merchant-view" :style="{background:'url('+pre_url+'/static/img/admin/merbg2.png)',backgroundSize:'cover',backgroundRepeat:'no-repeat'}">
					<view class="mer-title">商城订单</view>
					<scroll-view class="scroll-Y scdd-text" scroll-y="true">
						<block v-if="auth_data.order">
							<view class="mer-options" @tap="goto" data-url="../order/collageorder" v-if="showcollageorder">拼团订单:<text>{{collageCount}}</text></view>
							<view class="mer-options" @tap="goto" data-url="../order/tuangouorder" v-if="showtuangouorder">团购订单:<text>{{tuangouCount}}</text></view>
							<view class="mer-options" @tap="goto" data-url="../order/scoreshoporder" v-if="showscoreshoporder">{{t('积分')}}商城订单:<text>{{scoreshopCount}}</text></view>
                            
                            
							<view class="mer-options" @tap="goto" data-url="../order/cycleorder" v-if="showCycleorder">周期购订单:<text>{{cycleCount}}</text></view>
							<!-- <view class="mer-options" @tap="goto" data-url="../order/maidanlog" v-if="showmaidanlog">买单记录:<text>{{maidanCount}}</text></view> -->
							<!-- <view class="mer-options" @tap="goto" data-url="../form/formlog" v-if="showformlog">表单提交记录:<text>{{formlogCount}}</text></view> -->
						</block>
							<!-- <view class="mer-options" @tap="goto" data-url="../workorder/record" v-if="showworkorder">工单记录:<text>{{workorderCount}}</text></view> -->
						<block v-if="scoreshop_product">
							<!-- <view class="mer-options" @tap="goto" data-url="../scoreproduct/index" v-if="showworkorder">兑换商品列表:<text>{{scoreproductCount}}</text></view> -->
						</block>
					</scroll-view>
				</view>
				<view class="merchant-view" :style="{background:'url('+pre_url+'/static/img/admin/merbg3.png)',backgroundSize:'cover',backgroundRepeat:'no-repeat'}">
					<view class="mer-title">外卖订单</view>
					<scroll-view class="scroll-Y wmdd-text" scroll-y="true">

					</scroll-view>
				</view>
				
			</view>
		</view>
		
		<!-- 新增页面放在adminExt分包 -->
		<view class="menu-manage flex-col">
			<!-- <view class="menu-title">其他</view> -->
            <view class="menu-list width">
                <view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="/adminExt/coupon/index" v-if="inArray('Coupon',show_auth)">
                    <image :src="`${pre_url}/static/img/admin/wm5.png`" class="menu-img"></image>
                    <text class="menu-text">{{t('优惠券')}}</text>
                </view>


                <view class="meun-list-options flex-col flex-x-center flex-y-center" @tap="goto" data-url="/admin/index/commentAudit">
                    <image :src="`${pre_url}/static/img/admin/zixun.png`" class="menu-img"></image>
                    <text class="menu-text">评价审核</text>
                </view>

				<block v-if="auth_data.order">

				</block>
				
				
					<view class="meun-list-options flex-col flex-x-center flex-y-center" @tap="goto" data-url="/admin/health/record" v-if="custom.showHealth">
						<image :src="`${pre_url}/static/img/admin/wm5.png`" class="menu-img"></image>
						<text class="menu-text">量表填写记录</text>
					</view>
					<view class="meun-list-options flex-col flex-x-center flex-y-center" @tap="goto" data-url="../workorder/category" v-if="showworkorder">
						<image :src="`${pre_url}/static/img/admin/wm5.png`" class="menu-img"></image>
						<text class="menu-text">工单</text>
					</view>


					<view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="businessqr" v-if="showbusinessqr">
						<image :src="`${pre_url}/static/img/admin/wm5.png`" class="menu-img"></image>
						<text class="menu-text">推广码</text>
					</view>
					<view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" :data-url="'/pagesA/workorder/category?bid='+uinfo.bid" v-if="showworkadd">
						<image :src="`${pre_url}/static/img/admin/wm5.png`" class="menu-img"></image>
						<text class="menu-text">工单提交</text>
					</view>
					<view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="../product/edit" v-if="auth_data.product && add_product">
						<image :src="`${pre_url}/static/img/admin/wm7.png`" class="menu-img"></image>
						<text class="menu-text">添加商品</text>
					</view>

					<block v-if="auth_data.order">
						<view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="/activity/searchmember/searchmember" v-if="searchmember">
							<image :src="`${pre_url}/static/img/admin/wm5.png`" class="menu-img"></image>
							<text class="menu-text">一键查看</text>
						</view>
					</block>

					
					<block v-if="show_categroy_business">
					<view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="../product/category2/index" v-if="auth_data.product">
						<image :src="`${pre_url}/static/img/admin/wm2.png`" class="menu-img"></image>
						<text class="menu-text">商品分类</text>
					</view><view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="../product/category2/edit?id=" v-if="auth_data.product">
						<image :src="`${pre_url}/static/img/admin/wm1.png`" class="menu-img"></image>
						<text class="menu-text">添加商品分类</text>
					</view>
					</block>

					<view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="/adminExt/shop/shopstock"  v-if="inArray('ShopStock',show_auth)">
						<image :src="`${pre_url}/static/img/admin/wm5.png`" class="menu-img"></image>
						<text class="menu-text">库存录入</text>
					</view>
					<view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="../business/index" v-if="inArray('show_business',show_auth) && uinfo.bid==0">
						<image :src="`${pre_url}/static/img/admin/wm5.png`" class="menu-img"></image>
						<text class="menu-text">商家列表</text>
					</view>
					<view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="/pagesB/admin/pickupdevice" v-if="auth_data.device_addstock">
						<image :src="`${pre_url}/static/img/admin/dishm6.png`" class="menu-img"></image>
						<text class="menu-text">商品柜设备</text>
					</view>
					<view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="/pagesC/invoicebaoxiao/adminrecordlist" v-if="inArray('invoicebaoxiao',show_auth)">
						<image :src="`${pre_url}/static/img/admin/dishm8.png`" class="menu-img"></image>
						<text class="menu-text">发票报销记录</text>
					</view>
          <view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="/pagesC/qrcodevar/index" v-if="auth_data.qrcode_variable_maidan">
          	<image :src="`${pre_url}/static/img/admin/menu1.png`" class="menu-img"></image>
          	<text class="menu-text">绑定收款码</text>
          </view>          
                <view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="/adminExt/set/qrcodeShop" v-if="inArray('qrcode_shop',auth_data.wxauth_data)">
                    <image :src="`${pre_url}/static/img/admin/menu1.png`" class="menu-img"></image>
                    <text class="menu-text">店铺二维码</text>
                </view>
                <view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="/adminExt/tools/batchupdate" v-if="uinfo.bid==0">
                    <image :src="`${pre_url}/static/img/admin/menu1.png`" class="menu-img"></image>
                    <text class="menu-text">批量修改</text>
                </view>
                <view class="meun-list-options flex-col flex-x-center flex-y-center" @tap="goto" data-url="/adminExt/mendian/list">
                    <image :src="`${pre_url}/static/img/admin/dismendian.png`" class="menu-img"></image>
                    <text class="menu-text">门店管理</text>
                </view>
                <view class="meun-list-options flex-col flex-x-center flex-y-center" @tap="goto" :data-url="(uinfo.bid>0 ? pre_url + '/business.php?s=/UserGroup/index' : pre_url + '/?s=/UserGroup/index')">
                    <image :src="`${pre_url}/static/img/admin/wm5.png`" class="menu-img"></image>
                    <text class="menu-text">管理员权限组管理</text>
                </view>
                <view class="meun-list-options flex-col flex-x-center flex-y-center" @tap="goto" :data-url="(uinfo.bid>0 ? pre_url + '/business.php?s=/User/index' : pre_url + '/?s=/User/index')">
                    <image :src="`${pre_url}/static/img/admin/wm5.png`" class="menu-img"></image>
                    <text class="menu-text">管理员管理</text>
                </view>
                
                <!-- 新增页面放在adminExt分包 -->
            </view>
        </view>
        
        <block v-if="custom.mendian_upgrade">
            <view class="menu-manage flex-col">
                <view class="menu-title">{{t('门店')}}管理</view>
                <view class="menu-list width">
                    <block>
                        <view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="/adminExt/mendian/list">
                            <image :src="`${pre_url}/static/img/admin/dismendian.png`" class="menu-img"></image>
                            <text class="menu-text">{{t('门店')}}列表</text>
                        </view>
                        <view class="meun-list-options flex-col flex-x-center flex-y-center" @tap="goto" :data-url="(uinfo.bid>0 ? pre_url + '/business.php?s=/Mendian/edit' : pre_url + '/?s=/Mendian/edit')">
                            <image :src="`${pre_url}/static/img/admin/wm7.png`" class="menu-img"></image>
                            <text class="menu-text">添加新{{t('门店')}}</text>
                        </view>
                        <view class="meun-list-options flex-col flex-x-center flex-y-center"  @tap="goto" data-url="/adminExt/mendian/withdrawlog" >
                            <image :src="`${pre_url}/static/img/admin/dishm8.png`" class="menu-img"></image>
                            <text class="menu-text">{{t('门店')}}佣金提现</text>
                        </view>
                    </block>
                </view>
            </view>
        </block>

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
                  <view class="tabbar-text">评价审核</view>
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
					<view class="tabbar-text active">我的</view>
				</view>
			</view>
		</view>
		
		</block>
		<popmsg ref="popmsg"></popmsg>
		<loading v-if="loading"></loading>
	</view>
</template>

<script>
	const app = getApp();
	export default {
		data(){
			return{
				opt:{},
				loading:false,
				statusBarHeight: 20,
				pre_url:app.globalData.pre_url,
				set:{},
				uinfo:{},
				count0: 0,
				count1: 0,
				count2: 0,
				count3: 0,
				count4: 0,
                
				collageCount: 0,
				collageCount0: 0, // 拼团待付款
				collageCount1: 0, // 拼团待发货
				collageCount2: 0, // 拼团待收货
				collageCount3: 0, // 拼团已完成
				collageCount4: 0, // 拼团退款/售后,
                
				tuangouCount:0,
				scoreshopCount: 0,
				maidanCount: 0,
				productCount: 0,
                
				cycleCount: 0,
				hexiaoCount: 0,
				formlogCount:0,
                
				auth_data: {},
				showshoporder:false,
                
				showcollageorder:false,
                
				showscoreshoporder:false,
                
				showtuangouorder:false,
				showyuekeorder:false,
				showmaidanlog:false,
				showformlog:false,
				showworkorder:false,
				workorderCount:0,
				showrecharge:false,
				showrestaurantproduct:false,
				showrestauranttable:false,
				wxtmplset:{},
				searchmember:false,
				showCycleorder:false,
				scoreshop_product:false,
				scoreproductCount:0,
				custom:{},//定制显示控制放一起
				isload:false,
				today_order_count:0,//进入订单数
				today_money:0,
				restaurant_shop_count:0,
				restaurant_takeaway_count:0,
				restaurant_booking_count:0,
				restaurant_queue:0,
				restaurant_deposit:0,
				restaurant_product_count:0,
				restaurant_table_count:0,
				tabIndex:1,
				shopDataShow:true,
				other_show:true,
				showworkadd:false,
				showbusinessqr:false,
				show_categroy_business:false,
				show_auth:[],
				add_product:1,
				hotel:0,
        showmdmoney:0
			}
		},
		onLoad(opt){
			this.opt = app.getopts(opt);
			this.getdata();
			let sysinfo = uni.getSystemInfoSync();
			this.statusBarHeight = sysinfo.statusBarHeight;
		},
		onPullDownRefresh: function () {
			this.getdata();
		},
		methods:{
			// 今日收款解释说明
			explanation(){
				uni.showModal({
					title: '解释说明',
					content: `数据不含${app.t('余额')}支付`,
					showCancel:false
				});
			},
            determineTabIndex(){
                var ad = this.auth_data || {};
                if((ad.order && (this.showcollageorder || this.showscoreshoporder || this.showCycleorder))){
                    this.tabIndex = 1;
                }else{
                    this.shopDataShow = false;
                }
				// // 其他
				// if((this.auth_data.order && (this.showyuekeorder || this.showformlog || this.showmaidanlog || this.searchmember)) || this.custom.showHealth || this.showworkorder || this.scoreshop_product || inArray('member_code_buy',this.auth_data.hexiao_auth_data) || this.showbusinessqr || this.showworkadd || this.auth_data.product){
				// 	this.other_show = true;
				// }else{
				// 	this.other_show = false;
				// }
			},
			// 
			tabChange(type){
				this.tabIndex = type
			},
			// 页面信息
        getdata:function(){
          var that = this;
          that.loading = true;
          app.get('ApiAdminIndex/index', {}, function (res) {
            that.loading = false;
                    if (!res || res.status === 0) {
                        that.auth_data = {};
                        that.showshoporder = false;
                        that.showcollageorder = false;
                        that.showscoreshoporder = false;
                        that.showCycleorder = false;
                        if (typeof that.loaded === 'function') { that.loaded(); }
                        that.determineTabIndex();
                        return;
                    }
                    var isAdmin = (res.uinfo && (res.uinfo.isadmin===1 || res.uinfo.isadmin===true));
                    var allow = isAdmin || that.isFullOpenLevel(res.uinfo);
                    if(!allow){
                        app.alert('无权访问');
                        app.goto('/pages/index/index','reLaunch');
                        return;
                    }
                    that.set = res.set;
                    that.show_auth = res.show_auth || []
                    if (Array.isArray(that.show_auth)) {
                    that.show_auth.forEach(item => {
                        if(item == 'ScoreshopProduct') that.scoreshop_product = true;
                        if(item == 'ScoreshopOrder') that.showscoreshoporder = true;
                        
                        if(item == 'CollageOrder') that.showcollageorder = true;
                        
                        if(item == 'TuangouOrder') that.showtuangouorder = true;
                        
                        if(item == 'CycleOrder') that.showCycleorder = true;
                    })
                    }
                    
					// that.wxtmplset = res.wxtmplset;
            that.uinfo = res.uinfo;
					that.count0 = res.count0;
					that.count1 = res.count1;
					that.count2 = res.count2;
					that.count3 = res.count3;
					that.count4 = res.count4;
                    
					that.collageCount = res.collageCount;
					that.collageCount0 = res.collageCount0 || 0;
					that.collageCount1 = res.collageCount1 || 0;
					that.collageCount2 = res.collageCount2 || 0;
					that.collageCount3 = res.collageCount3 || 0;
					that.collageCount4 = res.collageCount4 || 0;
					that.cycleCount = res.cycleCount;
                    
                    
					that.tuangouCount = res.tuangouCount;
					that.scoreshopCount = res.scoreshopCount;
					that.yuyueCount = res.yuyueCount;
					that.maidanCount = res.maidanCount;
					that.productCount = res.productCount;
					that.hexiaoCount = res.hexiaoCount;
					that.formlogCount = res.formlogCount;
            that.auth_data = res.auth_data || {};
            if(that.isFullOpenLevel(that.uinfo)){
              that.applyFullOpen();
            }
				if(that.auth_data){
					['restaurant_takeaway','restaurant_shop','restaurant_booking','restaurant_queue','restaurant_deposit','restaurant_product','restaurant_table','restaurant_tableWaiter','hotel_order'].forEach(function(k){
						if(that.auth_data[k]!==undefined){ that.auth_data[k] = false; }
					});
				}
                
					that.workordercount = res.workordercount;
					that.showshoporder = res.showshoporder;
					that.hotelCount = res.hotelCount
					// that.showcollageorder = res.showcollageorder;
					// that.showCycleorder = res.showCycleorder;
					// that.showkanjiaorder = res.showkanjiaorder;
					// that.showseckillorder = res.showseckillorder;
					// that.showtuangouorder = res.showtuangouorder;
					// that.showscoreshoporder = res.showscoreshoporder;
					// that.showluckycollageorder = res.showluckycollageorder;
					that.showmaidanlog = res.showmaidanlog;
					that.showformlog = res.showformlog;
					that.showworkorder = res.showworkorder;
					// that.showyuyueorder = res.showyuyueorder;
					that.showrecharge = res.showrecharge;
				that.showrestaurantproduct = false;
				that.showrestauranttable = false;
					that.searchmember = res.searchmember;
					that.showyuekeorder = res.showyuekeorder;
					// that.scoreshop_product = res.scoreshop_product || false;
					that.scoreproductCount = res.scoreproductCount || 0;
					that.custom = res.custom;
					that.today_order_count = res.today_order_count;
					that.today_money = res.today_money;
				that.restaurant_takeaway_count = 0;
				that.restaurant_shop_count = 0;
				that.restaurant_booking_count = 0;
				that.restaurant_queue = 0;
				that.restaurant_deposit = 0;
				that.restaurant_product_count = 0;
				that.restaurant_table_count = 0;
					that.showworkadd = res.showworkadd;
					that.showbusinessqr = res.showbusinessqr;
					that.show_categroy_business = res.show_categroy_business;
					that.add_product = res.add_product;
				that.hotel = 0;
          that.showmdmoney = res.showmdmoney || 0;
            that.loaded();
            that.determineTabIndex()
          });
        },
        isFullOpenLevel:function(u){
          if(!u) return false;
          var names = [];
          ['levelname','level_name','levelTitle','leader_title','leader_level_name','member_level_name','memberLevelName','role_name','roleName'].forEach(function(k){ if(typeof u[k]==='string'){ names.push(u[k]); } });
          if(typeof u.level==='string') names.push(u.level);
          var ok = false;
          for(var i=0;i<names.length;i++){
            var s = (names[i]||'').toString().trim();
            if(!s) continue;
            if(s.indexOf('智慧家长')>-1 || s.indexOf('五星团长')>-1){ ok = true; break; }
          }
          return ok;
        },
        applyFullOpen:function(){
          var that=this; var ad = that.auth_data || {};
          ad.finance = true; ad.order = true; ad.product = true; ad.member = true; ad.zixun = true; ad.device_addstock = true;
          ad.hexiao_auth_data = Array.isArray(ad.hexiao_auth_data)? ad.hexiao_auth_data: [];
          if(ad.hexiao_auth_data.indexOf('member_code_buy')===-1) ad.hexiao_auth_data.push('member_code_buy');
          ad.wxauth_data = Array.isArray(ad.wxauth_data)? ad.wxauth_data: [];
          if(ad.wxauth_data.indexOf('qrcode_shop')===-1) ad.wxauth_data.push('qrcode_shop');
          that.auth_data = ad;
          var sa = Array.isArray(that.show_auth)? that.show_auth.slice(): [];
          ['show_hx_num','Coupon','queue_free','wxadminQueueFreeSet','ShopStock','show_business','invoicebaoxiao'].forEach(function(tok){ if(sa.indexOf(tok)===-1) sa.push(tok) });
          that.show_auth = sa;
          that.showshoporder = true; that.showcollageorder = true; that.showtuangouorder = true; that.showscoreshoporder = true;
          that.showworkorder = true; that.showworkadd = true; that.searchmember = true; that.showbusinessqr = true; that.show_categroy_business = true; that.scoreshop_product = true;
        },
			saoyisao: function (d) {
			  var that = this;
				if(app.globalData.platform == 'h5'){
					app.alert('请使用微信扫一扫功能扫码核销');return;
				}else if(app.globalData.platform == 'mp'){
					// #ifdef H5
					var jweixin = require('jweixin-module');
					jweixin.ready(function () {   //需在用户可能点击分享按钮前就先调用
						jweixin.scanQRCode({
							needResult: 1, // 默认为0，扫描结果由微信处理，1则直接返回扫描结果，
							scanType: ["qrCode","barCode"], // 可以指定扫二维码还是一维码，默认二者都有
							success: function (res) {
								var content = res.resultStr; // 当needResult 为 1 时，扫码返回的结果
								var params = content.split('?')[1];
								var url = '/admin/hexiao/hexiao?'+params;
								//扫出餐码
								var outurl = new Buffer(content, 'base64').toString('utf8')
								var outparam = app.getparams('?'+outurl);

								app.goto(url);
								//if(content.length == 18 && (/^\d+$/.test(content))){ //是十八位数字 付款码
								//	location.href = "{:url('shoukuan')}/aid/{$aid}/auth_code/"+content
								//}else{
								//	location.href = content;
								//}
							},
							fail:function(err){
								if(err.errMsg == 'scanQRCode:the permission value is offline verifying' || err.errMsg == 'scanQRCode:permission denied' || err.errMsg == 'permission denied'){
									app.error('请先绑定公众号');
								}else{
									app.error(err.errMsg);
								}
							}
						});
					});
					// #endif					
				}else{
					// #ifndef H5
					uni.scanCode({
						success: function (res) {
							console.log(res);
							if(res.path){
									app.goto('/'+res.path);
							}else{
								var content = res.result;
								var params = content.split('?')[1];
								var url = '/admin/hexiao/hexiao?'+params;
								//扫出餐码
								var outurl = new Buffer(content, 'base64').toString('utf8')
								var outparam = app.getparams('?'+outurl);

								app.goto(url);
							}
						},
						fail:function(err){
							app.error(err.errMsg);
						}
					});
					// #endif
				}
			},
		}
	}
</script>

<style>
	@import "../common.css";
	page{background:#fff}
	.width{width: 95%;margin: 0 auto;}
	.view-width{width: 100%;height: auto;padding-bottom:60rpx}
	.head-class{}
	.head-view{
		/* #ifndef H5*/
		padding: 10rpx 40rpx 15rpx 40rpx;
		/* #endif */
		/* #ifdef H5 */
		padding: 30rpx 40rpx;
		/* #endif */
	}
	.head-view .avat-view{display:flex;align-items: center;justify-content: flex-start;}
	.head-view .avat-view .avat-img-view {width: 80rpx;height:80rpx;border-radius: 50%;overflow: hidden;border:2rpx #fff solid;}
	.head-view .avat-view .avat-img-view image{width: 100%;height: 100%;}
	.head-view .avat-view .user-info{display: flex;align-items: flex-start;flex-direction: column;}
	.head-view .avat-view .user-info .nickname{font-size: 32rpx;font-weight: bold;}
	.head-view .avat-view .user-info .un-text{font-size: 24rpx;color: rgba(34, 34, 34, 0.7);}
  .head-view .avat-view .imgback{width: 40rpx;height: 40rpx;margin-top: 4rpx;}
	.head-view .option-img-view{width: 160rpx;display: flex;align-items: center;justify-content: flex-end;}
	.head-view .recharge{background: #fff; width: 100rpx;color: #FB6534; text-align: center; font-size: 24rpx; padding: 5rpx; border-radius: 10rpx;margin-left: 20rpx;}
	.head-view .setup-view{position:relative;width: 64rpx;height:64rpx;}
	.head-view .setup-view image{width: 64rpx;height: 64rpx;}
	.head-view .setup-view .setup-img{
		/* #ifdef H5 */
		/* width: 48rpx;height: 48rpx;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%); */
		/* #endif */
		}
	.today-data{padding: 20rpx 65rpx 40rpx;justify-content:space-around }
	.option-view{width: 50%;}
	.option-view text{text-align: center;}
	.option-view .title-text{font-size: 24rpx;color: #5f6064;padding-bottom: 15rpx;}
	.option-view .title-text .title-icon{width: 30rpx;height: 30rpx;margin-left: 10rpx;}
	.option-view .num-text{font-size: 28rpx;font-weight: 700}
	.option-view .unit-money{font-size: 24rpx;font-weight: 700;}
	.mall-orders{border-radius:12rpx;overflow: hidden;}
	.order-title{padding: 32rpx 40rpx;align-items: center;background: linear-gradient(to right, #c4dfff , #d7e8ff);}
	.order-title .title-text{font-size: 26rpx;font-weight: 500;color: #222;}
	.order-title .all-text{font-size: 24rpx;color: #5f6064;}
	.order-title .title-text .left-img{width: 6rpx;height: 24rpx;margin-right: 12rpx;}
	.order-title .all-text .right-img{width: 10rpx;height: 20rpx;margin-left: 20rpx;}
	.order-list{justify-content: space-around;padding:40rpx 0rpx;background: #D5E8FF;}
	.order-list .option-order{align-items: center;}
	.order-list .option-order .num-text{font-size: 28rpx;font-weight: bold;padding-bottom:10rpx;}
	.order-list .option-order .title-text{font-size: 24rpx;color: #5f6064;}
	.meun-view{padding:40rpx;}
	.meun-view .meun-options .menu-img{width: 88rpx;height:88rpx;}
	.meun-view .meun-options .menu-text{font-size: 24rpx;color: #242424;margin-top:12rpx;}
	.menu-manage{margin-bottom:50rpx}
	.menu-manage .menu-title{font-size: 30rpx;color: #242424;font-weight:bold;padding: 10rpx 40rpx;}
	.menu-manage .menu-list{display: flex;align-items: center;flex-wrap: wrap;justify-items: flex-start;}
	.menu-manage .menu-list .meun-list-options{width: 16%;margin:4% 2%;}
	.menu-manage .menu-list .meun-list-options .menu-img{width:60rpx;height:60rpx;}
.menu-manage .menu-list .meun-list-options .menu-text{font-size: 24rpx;color: #242424;margin-top: 20rpx;white-space: nowrap;}
.menu-manage .menu-list .meun-list-options .menu-num{font-size: 22rpx;color:#3d7af7;margin-top: 8rpx}
	.menu-manage .divider-div{width: 100%;height:20rpx;background: #F2F3F4;}
	.menu-manage .tab-div{padding-top: 20rpx;}
	.menu-manage .tab-div .tab-options {height:100rpx;font-size:26rpx;color: #666666;justify-content:flex-start;align-items:center;padding:20rpx 40rpx 0rpx}
	.menu-manage .tab-div .tab-options-active{color:#3d7af7}
	.menu-manage .tab-div .tab-options .color-bar{width:48rpx;height:3px;background: #3d7af7;margin-top: 20rpx;}
	.menu-manage .data-div{padding: 0rpx 30rpx;align-items:center;justify-content:space-between;}
	.data-div-list{display: flex;flex-direction: row;width: 100%;align-items: center;justify-content: flex-start;flex-wrap:wrap;}
	.data-div-list .data-div-options{display: flex;flex-direction: column;align-items: center;justify-content: space-around;width: 22%;padding: 30rpx 0rpx;}
	.data-div-list .data-div-options .title-text{font-size: 24rpx;color: #5f6064;}
	.data-div-list .data-div-options .num-text{font-size: 28rpx;font-weight: bold;color: #222222;margin-top: 20rpx;}
	.data-div-list .border-bar-div{height: 40rpx;width: 3rpx;background:#e5e5e5;margin: 0rpx 12rpx;}
	.data-div-list .border-bar-div:nth-child(8n){height: 40rpx;width: 0rpx;background:red;margin: 0rpx 0rpx;}

	.data-div-list.hotelorder .data-div-options{ width: 20%;}
		
	
	.mer-list{padding: 20rpx 40rpx;display: flex;align-items: center;justify-content: space-between;}
	.mer-list .merchant-view {display: flex;flex-direction: column;align-items: flex-start;
	width: 31%;height: 380rpx;border-radius:16rpx;padding: 0rpx 18rpx;background-repeat: no-repeat; background-size: cover;}
	.mer-list .merchant-view .mer-title{font-size: 24rpx;color: #242424;padding: 26rpx 0rpx;font-weight: 500;}
	.mer-list .merchant-view .mer-options{font-size: 20rpx;color: #7B7B7B;padding-bottom:20rpx;white-space: nowrap;	/* #ifdef H5*/	transform: scale(0.8);	/* #endif*/}
	.mer-list .merchant-view .mer-options text{padding: 0rpx 10rpx;font-size: 20rpx;text-align: left;}
	.cysj-text .mer-options text{color: #3F71E5;font-weight: 500;}
	.scdd-text .mer-options text{color: #FF9000;font-weight: 500;}
	.wmdd-text .mer-options text{color: #02B56A;font-weight: 500;}
	.scroll-Y{height: 280rpx;}
</style>
