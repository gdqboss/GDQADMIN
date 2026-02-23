<?php /*a:3:{s:61:"/www/wwwroot/gdqshop.cn/app/view/designer_page/chooseurl.html";i:1747926690;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;}*/ ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>选择链接</title>
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=0">
  <link rel="stylesheet" type="text/css" href="/static/admin/layui/css/layui.css?v=20200519" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/layui/css/modules/formSelects-v4.css?v=20200516" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/css/admin.css?v=202406" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/css/font-awesome.min.css?v=20200516" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/webuploader/webuploader.css?v=<?php echo time(); ?>" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/css/designer.css?v=202410" media="all">
<link rel="stylesheet" type="text/css" href="/static/fonts/iconfont.css?v=20201218" media="all">
	<style>
	#urlqr img{margin:0 auto}
	.fe-tab-link::-webkit-scrollbar {width: 2px;height: 0px;}
	.fe-tab-link::-webkit-scrollbar-thumb {border-radius: 1px;-webkit-box-shadow: inset 0 0 1px rgba(0,0,0,0.5);background: rgba(0,0,0,0.5);}
	.fe-tab-link::-webkit-scrollbar-track {-webkit-box-shadow: inset 0 0 0px rgba(0,0,0,0.2);border-radius: 0;background: rgba(0,0,0,0);}
	.radio-tips {display:inline-block;line-height: 28px;    vertical-align: middle;		margin: 3px 10px 0 0;		padding-right: 10px;		cursor: pointer;color: #999}
	.layui-tab-title li{padding:0 8px}
	.layui-tab-content .layui-tab-item .layui-btn-div{margin-left: 10px;}
	.layui-tab-content .layui-tab-item .layui-btn-div .layui-btn{margin: 10px 10px 0px 0px;}
	</style>
</head>
<body style="margin:0;">
<div class="layui-fluid">
<div class="layui-row layui-col-space15">
	<div class="layui-card layui-col-md12">
	<div class="layui-tab">
		<ul class="layui-tab-title">
				<li class="layui-this">基础功能</li>
				<?php if($auth_data=='all' || in_array('ShopCategory/*',$auth_data)): ?><li>商品分类</li><?php endif; if($auth_data=='all' || in_array('ShopGroup/*',$auth_data)): ?><li>商品分组</li><?php endif; if($auth_data=='all' || in_array('ShopProduct/*',$auth_data)): ?><li>商城商品</li><?php endif; if($auth_data=='all' || in_array('CollageProduct/*',$auth_data)): ?><li>拼团商品</li><?php endif; if($auth_data=='all' || in_array('KanjiaProduct/*',$auth_data)): ?><li>砍价商品</li><?php endif; if($auth_data=='all' || in_array('ScoreshopProduct/*',$auth_data)): ?><li><?php echo t('积分'); ?>兑换</li><?php endif; if($auth_data=='all' || in_array('ArticleCategory/*',$auth_data)): ?><li>文章分类</li><?php endif; if($auth_data=='all' || in_array('Article/*',$auth_data)): ?><li>文章</li><?php endif; if($auth_data=='all' || in_array('Business/category',$auth_data)): ?><li>商户分类</li><?php endif; if($auth_data=='all' || in_array('Business/*',$auth_data)): ?><li>商户</li><?php endif; if(getcustom('huodong_baoming')): if($auth_data=='all' || in_array('HuodongBaomingCategory/*',$auth_data)): ?><li>活动分类</li><?php endif; if($auth_data=='all' || in_array('HuodongBaomingList/*',$auth_data)): ?><li>活动列表</li><?php endif; ?>
				<?php endif; if(getcustom('hotel')): if($auth_data=='all' || in_array('HotelCategory/*',$auth_data)): ?><li><?php echo $text['酒店']; ?>分类</li><?php endif; if($auth_data=='all' || in_array('Hotel/*',$auth_data)): ?><li><?php echo $text['酒店']; ?>列表</li><?php endif; ?>
				<?php endif; if($auth_data=='all' || in_array('DesignerPage/*',$auth_data) || in_array('DesignerPage/index',$auth_data)): ?><li>设计页面</li><?php endif; if(app('request')->param('type')!='geturl'): ?><li>功能</li><?php endif; if(getcustom('goods_hexiao')): if($auth_data=='all' || in_array('RestaurantProduct/*',$auth_data)): ?><li>外卖商品</li><?php endif; ?>
				<?php endif; if($auth_data=='all' || in_array('CycleProduct/*',$auth_data)): ?><li>周期购商品</li><?php endif; if($auth_data=='all' || in_array('TuangouProduct/*',$auth_data)): ?><li>团购商品</li><?php endif; if($auth_data=='all' || in_array('LuckyCollageProduct/*',$auth_data)): ?><li>幸运拼团商品</li><?php endif; if($auth_data=='all' || in_array('LuckyCollageCategory/*',$auth_data)): ?><li>幸运拼团分类</li><?php endif; if($auth_data=='all' || in_array('KechengCategory/*',$auth_data)): ?><li>课程分类</li><?php endif; if($auth_data=='all' || in_array('KechengList/*',$auth_data)): ?><li>课程列表</li><?php endif; if(getcustom('form_data')): if($auth_data=='all' || in_array('Form/*',$auth_data)): ?><li>表单数据</li><?php endif; ?>
				<?php endif; ?>
		</ul>
		<div class="layui-tab-content" style="padding:0">
			<!--基础功能-->
			<div class="layui-tab-item layui-show fe-tab-link">
				<div class="page-header" style="margin:20px 0 0 0;padding-bottom:0">
					<h4><i class="fa fa-folder-open-o"></i> 基础页面</h4>
				</div>
				<div class="layui-btn-div">
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/index/index','首页')">首页</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/my/usercenter','<?php echo t('会员'); ?>中心')"><?php echo t('会员'); ?>中心</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/shop/classify','分类商品')">分类商品</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/shop/prolist','商品列表')">商品列表</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/shop/cart','购物车')">购物车</div>
				</div>
				<?php if($auth_data=='all' || in_array('ShopProduct/*',$auth_data)): ?>
				<div class="page-header" style="margin:10px 0 0 0;padding-bottom:0">
					<h4><i class="fa fa-folder-open-o"></i> 商城页面</h4>
				</div>
				<div class="layui-btn-div">
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/shop/category1','一级分类')">一级分类</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/shop/category2','二级分类')">二级分类</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/shop/category4','二级分类2')">二级分类2</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/shop/category3','三级分类')">三级分类</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/shop/classify','分类商品')">分类商品</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/shop/classify2','分类商品2')">分类商品2</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/shop/prolist','商品列表')">商品列表</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/shop/fastbuy','快速购买')">快速购买</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/shop/fastbuy2','快速购买2')">快速购买2</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/shop/cart','购物车')">购物车</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/shop/search','商品搜索')">商品搜索</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/order/orderlist','订单列表')">订单列表</div>
					<?php if(getcustom('product_thali')): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/productthali/index','商品套餐列表')">商品套餐列表</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/productthali/orderlist','我的套餐订单')">我的套餐订单</div>
					<?php endif; if(getcustom('product_brand')): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/shop/brandlist','商品品牌')">商品品牌</div>
					<?php endif; if(getcustom('product_lvxin_replace_remind')): ?>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/my/productlvxinreplace','我的滤芯')">我的滤芯</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<div class="page-header" style="margin:10px 0 0 0;padding-bottom:0">
					<h4><i class="fa fa-folder-open-o"></i> 营销活动</h4>
				</div>
				<div class="layui-btn-div">
				<?php if($auth_data=='all' || in_array('SeckillProduct/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/seckill/index','秒杀列表')">秒杀列表</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/seckill/orderlist','秒杀订单')">秒杀订单</div>
				<?php endif; if($auth_data=='all' || in_array('KanjiaProduct/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/kanjia/index','砍价列表')">砍价首页</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/kanjia/orderlist','砍价订单')">砍价订单</div>
				<?php endif; if($auth_data=='all' || in_array('CollageProduct/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/collage/index','拼团列表')">拼团首页</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/collage/orderlist','拼团订单')">拼团订单</div>
				<?php if(getcustom('yx_collage_classify')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/collage/classify','拼团分类')">拼团分类</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/collage/prolist','拼团列表')">拼团列表</div>
				<?php endif; ?>
				<?php endif; if($auth_data=='all' || in_array('ScoreshopProduct/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/scoreshop/index','<?php echo t('积分'); ?>兑换')">积分兑换</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/scoreshop/orderlist','<?php echo t('积分'); ?>兑换订单')">积分兑换订单</div>
					<?php if(getcustom('scoreshop_classify2')): ?>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/scoreshop/classify2','<?php echo t('积分'); ?>兑换二级分类')">积分兑换二级分类</div>
					<?php endif; ?>
				<?php endif; if(getcustom('plug_luckycollage')): if($auth_data=='all' || in_array('LuckyCollageProduct/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/luckycollage/index','幸运拼团预告页')">幸运拼团预告页</div>
					<?php endif; ?>
				<?php endif; if($auth_data=='all' || in_array('LuckyCollageProduct/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/luckycollage/classify','幸运拼团分类')">幸运拼团分类</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/luckycollage/prolist','幸运拼团列表')">幸运拼团列表</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/luckycollage/orderlist','幸运拼团订单')">幸运拼团订单</div>
				<?php endif; if($auth_data=='all' || in_array('TuangouProduct/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/tuangou/prolist','团购商品')">团购商品</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/tuangou/orderlist','团购订单')">团购订单</div>
				<?php endif; if($auth_data=='all' || in_array('Sign/set',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/sign/index','签到')">签到</div>
				<?php endif; if(getcustom('yx_invite_cashback')): if($auth_data=='all' || in_array('InviteCashback/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/my/invitecashbacklog','邀请返现明细')">邀请返现明细</div>
				<?php endif; ?>
				<?php endif; if(getcustom('everyday_hongbao') && ($auth_data=='all' || in_array('HongbaoEveryday/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/hongbaoEveryday/index','每日红包')">每日红包</div>
				<?php endif; if(getcustom('yx_kouling') && ($auth_data=='all' || in_array('Kouling/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/yx/kouling?bid=<?php echo $bid; ?>','口令')">口令</div>
				<?php endif; if(getcustom('yx_hbtk')): if($auth_data=='all' || in_array('HbtkActivity/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/hbtk/orderlist','拓客活动记录')">拓客活动记录</div>
				<?php endif; ?>
				<?php endif; if(getcustom('yx_choujiang_manren') && ($auth_data=='all' || in_array('ChoujiangManren/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/lottery/index','满人开奖')">满人开奖</div>
				<?php endif; if(getcustom('taocan_product') && ($auth_data=='all' || in_array('TaocanProduct/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/taocan/index','套餐产品')">套餐产品</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/taocan/orderlist','套餐订单')">套餐订单</div>
				<?php endif; if(getcustom('yx_mangfan')): if($auth_data=='all' || in_array('Mangfan/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/my/mangfanlog','<?php echo t('消费盲返'); ?>明细')"><?php echo t('消费盲返'); ?>明细</div>
					<?php endif; ?>
				<?php endif; if(getcustom('invite_free') && ($auth_data=='all' || in_array('InviteFree/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/invite_free/index','我的免单')">我的免单</div>
				<?php endif; if(getcustom('yx_buy_product_manren_choujiang') && ($auth_data=='all' || in_array('ManrenChoujiang/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/manrenchoujiang/list','满人开奖')">满人开奖</div>
				<?php endif; if(getcustom('yx_cashback_multiply')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/releasePoints/cashbacklog','倍增返现记录')">倍增返现记录</div>
				<?php endif; ?>
				</div>
				<div class="page-header" style="margin:10px 0 0 0;padding-bottom:0">
					<h4><i class="fa fa-folder-open-o"></i> <?php echo t('会员'); ?>中心</h4>
				</div>
				<div class="layui-btn-div">
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/my/usercenter','<?php echo t('会员'); ?>中心')"><?php echo t('会员'); ?>中心</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/my/favorite','我的收藏')">我的收藏</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/my/history','我的足迹')">我的足迹</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/my/scorelog','<?php echo t('积分'); ?>明细')"><?php echo t('积分'); ?>明细</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/money/recharge','<?php echo t('会员'); ?>充值')"><?php echo t('会员'); ?>充值</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/money/moneylog','<?php echo t('余额'); ?>明细')"><?php echo t('余额'); ?>明细</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/money/withdraw','<?php echo t('余额'); ?>提现')"><?php echo t('余额'); ?>提现</div>
				<?php if(getcustom('money_transfer') || getcustom('money_friend_transfer')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/money/rechargeToMember','<?php echo t('余额'); ?>转账')"><?php echo t('余额'); ?>转账</div>
				<?php endif; if(getcustom('score_transfer')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/my/scoreTransfer','<?php echo t('积分'); ?>转赠')"><?php echo t('积分'); ?>转赠</div>
				<?php endif; if(getcustom('product_service_fee') && ($auth_data=='all' || in_array('service_fee_switch',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/service_fee/recharge','<?php echo t('服务费'); ?>充值')"><?php echo t('服务费'); ?>充值</div>
				<?php endif; if(getcustom('product_givetongzheng')): if($auth_data=='all' || in_array('Tongzheng/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/my/tongzhengtransfer','<?php echo t('通证'); ?>转赠')"><?php echo t('通证'); ?>转赠</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/my/tongzhengorderlog','<?php echo t('通证'); ?>释放记录')"><?php echo t('通证'); ?>订单记录</div>
					<?php endif; ?>
				<?php endif; ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/my/set','个人设置')">个人设置</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/my/paypwd','支付密码')">支付密码</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/my/levelinfo','<?php echo t('会员'); ?>等级')"><?php echo t('会员'); ?>等级</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/my/levelup','<?php echo t('会员'); ?>升级')"><?php echo t('会员'); ?>升级</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/address/address','收货地址')">收货地址</div>
				<?php if($auth_data=='all' || in_array('Coupon/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/coupon/couponlist','领券中心')">领券中心</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/coupon/mycoupon','我的<?php echo t('优惠券'); ?>')">我的<?php echo t('优惠券'); ?></div>
				<?php endif; if(getcustom('woniudz')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/commission/myteamline','我的上下级')">我的上下级</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/commission/mysameline','我的同级会员')">我的同级会员</div>
				<?php endif; if(getcustom('member_friend')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/friend/index','我的好友')">我的好友</div>
				<?php endif; ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/index/login','用户登录')">用户登录</div>
				<?php if(getcustom('other_money')): if($othermoney_status): ?>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/othermoney/moneylog?type=money2','<?php echo t('余额2'); ?>明细')"><?php echo t('余额2'); ?>明细</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/othermoney/withdraw?type=money2','<?php echo t('余额2'); ?>提现')"><?php echo t('余额2'); ?>提现</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/othermoney/moneylog?type=money3','<?php echo t('余额3'); ?>明细')"><?php echo t('余额3'); ?>明细</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/othermoney/withdraw?type=money3','<?php echo t('余额3'); ?>提现')"><?php echo t('余额3'); ?>提现</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/othermoney/moneylog?type=money4','<?php echo t('余额4'); ?>明细')"><?php echo t('余额4'); ?>明细</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/othermoney/withdraw?type=money4','<?php echo t('余额4'); ?>提现')"><?php echo t('余额4'); ?>提现</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/othermoney/moneylog?type=money5','<?php echo t('tivity/yuyue/apply余额5'); ?>明细')"><?php echo t('余额5'); ?>明细</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/othermoney/withdraw?type=money5','<?php echo t('余额5'); ?>提现')"><?php echo t('余额5'); ?>提现</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/othermoney/frozen_moneylog','<?php echo t('冻结金额'); ?>明细')"><?php echo t('冻结金额'); ?>明细</div>
					<?php endif; ?>
				<?php endif; if(getcustom('member_archives')): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/archives/index','档案列表')">档案列表</div>
				<?php endif; if(getcustom('product_glass')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/glass/index','验光档案')">验光档案</div>
				<?php endif; if($auth_data=='all' || in_array('CashierOrder/index',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/cashier/orderlist','收银台订单')">收银台订单</div>
				<?php endif; if($auth_data=='all' || in_array('Cashback/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/cashback/index','返现参与列表')">返现参与列表</div>
				<?php endif; if(getcustom('yx_queue_free')): if($auth_data=='all' || in_array('QueueFree/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/queueFree/index','<?php echo t('排队免单'); ?>记录')"><?php echo t('排队免单'); ?>记录</div>
				<?php endif; ?>
				<?php endif; if(getcustom('mendian_member_levelup_fenhong')): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/mendian/memberlevelupfenhong','分红门店')">分红门店</div>
				<?php endif; if(getcustom('member_commission_max_toscore') && getcustom('member_commission_max')): if($auth_data=='all' || in_array('member_commission_max',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/my/commissionmaxtoscore','<?php echo t('佣金上限'); ?>转<?php echo t('积分'); ?>')"><?php echo t('佣金上限'); ?>转<?php echo t('积分'); ?></div>
				<?php endif; ?>
				<?php endif; if(getcustom('shop_paiming_fenhong') && $bid==0 && ($auth_data=='all' || in_array('PaimingFenhong/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/paimingfenhong/user','商城消费<?php echo t('排名分红'); ?>')">商城消费<?php echo t('排名分红'); ?></div>
				<?php endif; if(getcustom('business_reward_member')): if($auth_data=='all' || in_array('BusinessReward/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/my/rewardToMember','商户打赏')">商户打赏</div>
				<?php endif; ?>
				<?php endif; if(getcustom('consumer_value_add')): if($auth_data=='all' || in_array('GreenScore/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/my/greenscorelog','<?php echo t('绿色积分'); ?>记录')"><?php echo t('绿色积分'); ?>明细</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/my/greenscoreWithdraw','<?php echo t('绿色积分'); ?>提现')"><?php echo t('绿色积分'); ?>提现</div>
				<?php endif; ?>
				<?php endif; if(getcustom('consumer_value_add') && getcustom('greenscore_max')): if($auth_data=='all' || in_array('GreenScore/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/greenscore/commissiontogreenscore','<?php echo t('绿色积分'); ?>记录')"><?php echo t('佣金'); ?>转<?php echo t('绿色积分'); ?></div>
				<?php endif; ?>
				<?php endif; if(getcustom('consumer_value_add') && getcustom('green_score_new')): if($auth_data=='all' || in_array('GreenScore/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/releasePoints/releasegreenscore','<?php echo t('绿色积分'); ?>释放记录')"><?php echo t('绿色积分'); ?>释放记录</div>
				<?php endif; ?>
				<?php endif; if(getcustom('member_shop_favorite') &&  $auth_data=='all' || in_array('shopfavorite',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/my/shopfavorite','我的商品收藏')">我的商品收藏</div>
				<?php endif; if(getcustom('member_buy_business')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/business/blist?type=buylog','购买商户列表')">购买商户列表</div>
				<?php endif; if(getcustom('bonus_pool_gold') && ( $auth_data=='all' || in_array('BonusPoolGold/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/bonuspoolgold/bonuspool','<?php echo t('奖金池'); ?>')"><?php echo t('奖金池'); ?></div>
				<?php endif; if(getcustom('car_management')): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/registervehicle/vehicleList','车辆管理')">车辆管理</div>
				<?php endif; if(getcustom('member_notice')): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/registervehicle/systemMsg','站内信')">站内信</div>
				<?php endif; if(getcustom('teamyeji_pv')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/teamyeji/teamyejipv','社群服务业绩')">社群服务业绩</div>
				<?php endif; ?>
				</div>
				<div class="page-header" style="margin:10px 0 0 0;padding-bottom:0">
					<h4><i class="fa fa-folder-open-o"></i> 分销页面</h4>
				</div>
				<div class="layui-btn-div">
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/commission/myteam','我的团队')">我的团队</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/commission/index','我的<?php echo t('佣金'); ?>')">我的<?php echo t('佣金'); ?></div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/commission/downorder','分销订单')">分销订单</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/commission/commissionlog','<?php echo t('佣金'); ?>明细')"><?php echo t('佣金'); ?>明细</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/commission/poster','分享海报')">分享海报</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/commission/fhlog','分红记录')">分红记录</div>
					<?php if(getcustom('fenhong_gudong_huiben') && ($auth_data=='all' || in_array('gdfenhong_huiben',$auth_data))): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/commission/fenhong_huiben','<?php echo t('回本股东分红'); ?>')"><?php echo t('回本股东分红'); ?>明细</div>
					<?php endif; if(getcustom('commissionranking')): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/commission/commissionranking','佣金排行榜')">佣金排行榜</div>
				<?php endif; if(getcustom('fenhong_ranking')): if($auth_data=='all' || in_array('Commission/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/commission/fenhongranking','分红排行榜')">分红排行榜</div>
					<?php endif; ?>
				<?php endif; if(getcustom('business_fenxiao') && ($auth_data=='all' || in_array('BusinessFenxiao/*',$auth_data))): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/businessFenxiao/shuju','店铺分销')">店铺分销</div>
				<?php endif; if(getcustom('ganer_fenxiao') && ($auth_data=='all' || in_array('PrizePool/*',$auth_data))): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/taocan/fenhong','店铺分销')">奖金池分红</div>
				<?php endif; if(getcustom('team_yeji_ranking')): if($auth_data=='all' || in_array('TeamYejiRanking',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/team/teamyjranking','团队业绩排行榜')">团队业绩排行榜</div>
					<?php endif; ?>
				<?php endif; if(getcustom('region_partner') && ($auth_data=='all' || in_array('RegionPartner/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/regionpartner/apply','申请合伙人')">申请合伙人</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/regionpartner/partner','申请合伙人记录')">申请合伙人记录</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/regionpartner/fhlog','合伙人分红记录')">合伙人分红记录</div>
				<?php endif; if(getcustom('areafenhong_region_ranking')): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/commission/regionagent','区域代理排行榜')">区域代理排行榜</div>
					<?php if($auth_data=='all' || in_array('RegionRanking/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/commission/ranking','排行榜活动')">排行榜活动</div>
					<?php endif; ?>
				<?php endif; if(getcustom('score_ranking')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/score/scoreranking','<?php echo t('积分'); ?>排行榜')"><?php echo t('积分'); ?>排行榜</div>
				<?php endif; if(getcustom('shoporder_ranking')): if($auth_data=='all' || in_array('ShoporderRanking/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/shoporderranking/index','消费排行榜')">消费排行榜</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/shoporderranking/log','消费排行榜记录')">消费排行榜记录</div>
				<?php endif; ?>
				<?php endif; if(getcustom('wx_channels')): if($auth_data=='all' || in_array('WxChannelsOrder/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/wxchannels/downorder','视频号小店分销订单')">视频号小店分销订单</div>
				<?php endif; ?>
				<?php endif; if(getcustom('yx_team_yeji_tongji')): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/teamyejitongji/myteam','团队业绩')">团队业绩</div>
				<?php endif; if(getcustom('transfer_order_parent_check')): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/transferorderparent/tongji','分销数据统计')">分销数据统计</div>
				<?php endif; ?>
				</div>
				<?php if($auth_data=='all' || in_array('Restaurant/*',$auth_data)): ?>
				<div class="page-header" style="margin:10px 0 0 0;padding-bottom:0">
					<h4><i class="fa fa-folder-open-o"></i> 餐饮页面</h4>
				</div>
				<div class="layui-btn-div">
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/restaurant/takeaway/index?bid=<?php echo $bid; ?>','外卖')">外卖</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/restaurant/takeaway/orderlist','外卖订单')">外卖订单</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/restaurant/shop/index?bid=<?php echo $bid; ?>','点餐')">点餐</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/restaurant/shop/orderlist','点餐订单')">点餐订单</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/restaurant/booking/add?bid=<?php echo $bid; ?>','预定')">预定</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/restaurant/booking/orderlist','预定记录')">预定记录</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/restaurant/queue/index?bid=<?php echo $bid; ?>','排队')">排队</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/restaurant/queue/record?bid=<?php echo $bid; ?>','排队记录')">排队记录</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/restaurant/deposit/orderdetail','寄存')">寄存</div>
				</div>
				<?php endif; if(getcustom('hotel')): if($auth_data=='all' || in_array('Hotel/*',$auth_data)): ?>
					<div class="page-header" style="margin:10px 0 0 0;padding-bottom:0">
						<h4><i class="fa fa-folder-open-o"></i> <?php echo $text['酒店']; ?>页面</h4>
					</div>
					<div class="layui-btn-div">
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/hotel/index/index','<?php echo $text['酒店']; ?>首页')"><?php echo $text['酒店']; ?>首页</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/hotel/index/hotellist','<?php echo $text['酒店']; ?>首页')"><?php echo $text['酒店']; ?>列表</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/hotel/order/orderlist','<?php echo $text['酒店']; ?>订单')"><?php echo $text['酒店']; ?>订单</div>
					</div>
					<?php endif; ?>
				<?php endif; if($auth_data=='all' || in_array('Business/*',$auth_data)): ?>
				<div class="page-header" style="margin:10px 0 0 0;padding-bottom:0">
					<h4><i class="fa fa-folder-open-o"></i> 商户页面</h4>
				</div>
				<div class="layui-btn-div">
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/business/apply','商户入驻')">商户入驻</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/business/blist','商户列表')">商户列表</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/business/clist','分类商户')">分类商户</div>
					<?php if(getcustom('business_buy_bind_show_page')): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/business/index?type=firstbuy','首消店铺')">首消店铺</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>
				<div class="page-header" style="margin:10px 0 0 0;padding-bottom:0">
					<h4><i class="fa fa-folder-open-o"></i> 财务页面</h4>
				</div>
				<div class="layui-btn-div">
					<?php if($auth_data=='all' || in_array('Maidan/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/maidan/pay','买单收款')">买单收款</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/maidan/maidanlog','买单记录')">买单记录</div>
					<?php endif; if(getcustom('member_recharge_yj') && ($auth_data=='all' || in_array('RechargeYjLog/index',$auth_data))): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/rechargeyj/index','充值业绩页面')">充值业绩页面</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/rechargeyj/log','充值业绩明细')">充值业绩明细</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/rechargeyj/withdraw','充值业绩提现')">充值业绩提现</div>
					<?php endif; if(getcustom('pay_huifu_fenzhang') && ($auth_data=='all' || in_array('HuifuFenzhang/*',$auth_data))): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/my/sethuifuinfo','汇付进件')">汇付进件</div>
					<?php endif; ?>
				</div>	
				<div class="page-header" style="margin:10px 0 0 0;padding-bottom:0">
					<h4><i class="fa fa-folder-open-o"></i> 其他页面</h4>
				</div>
				<div class="layui-btn-div">
				<?php if($auth_data=='all' || in_array('Form/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/form/formlog','提交记录')">表单提交记录</div>
				<?php endif; if($auth_data=='all' || in_array('Article/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/article/artlist','文章列表')">文章列表</div>
				<?php endif; if(getcustom('article_files')): if($auth_data=='all' || in_array('Article/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/article/record','文章资源列表')">文章资源列表</div>
				<?php endif; ?>
				<?php endif; if($auth_data=='all' || in_array('Lipin/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/lipin/index','礼品卡兑换')">礼品卡兑换</div>
				<?php endif; if($auth_data=='all' || in_array('Luntan/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/luntan/index','用户论坛')">用户论坛</div>
					<?php if(getcustom('luntan_call')): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/luntan/fatielog','论坛发帖记录')">论坛发帖记录</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/luntan/focuslog','论坛关注记录')">论坛关注记录</div>
					<?php endif; ?>
				<?php endif; if(getcustom('luntan_second_category')): if($auth_data=='all' || in_array('Luntan/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/luntan/class','论坛横版分类')">论坛横版分类</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/luntan/class2','论坛竖版分类')">论坛竖版分类</div>
				<?php endif; ?>
				<?php endif; if($auth_data=='all' || in_array('Yuyue/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/yuyue/yuyue/prolist?bid=<?php echo $bid; ?>','预约服务')">预约服务</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/yuyue/yuyue/orderlist?bid=<?php echo $bid; ?>','预约订单')">预约订单</div>
					<?php if(getcustom('hmy_yuyue')): ?>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/yuyue/yuyue/peolist2?bid=<?php echo $bid; ?>','师傅列表')">师傅列表</div>
					<?php endif; if(getcustom('yuyue_apply')): ?>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/yuyue/yuyue/apply?bid=<?php echo $bid; ?>','预约师傅申请')">预约师傅申请</div>
					<?php endif; ?>
				<?php endif; if($auth_data=='all' || in_array('KechengList/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/kecheng/classify?bid=<?php echo $bid; ?>','课程分类')">课程分类</div>
				<?php if(getcustom('kecheng_discount')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/kecheng/category3','课程分类2')">课程分类2</div>
				<?php endif; ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/kecheng/list?bid=<?php echo $bid; ?>','课程列表')">课程列表</div>
				<?php if(getcustom('kecheng_lecturer')): if($auth_data=='all' || in_array('KechengLecturer/*',$auth_data)): ?>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/kecheng/list?bid=<?php echo $bid; ?>&chaptertype=1','非讲师课程列表')">非讲师课程列表</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/kecheng/list?bid=<?php echo $bid; ?>&chaptertype=2','讲师课程列表')">讲师课程列表</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/kecheng/lecturerapply','申请讲师')">申请讲师</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/kecheng/lecturercenter','讲师主页')">讲师主页</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/kecheng/lecturerlist','讲师列表')">讲师列表</div>
					<?php endif; ?>
				<?php endif; ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/kecheng/orderlist?bid=<?php echo $bid; ?>','我的课程')">我的课程</div>
				<?php endif; if($auth_data=='all' || in_array('Shortvideo/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/shortvideo/index','短视频')">短视频</div>
				<?php endif; if(getcustom('express')): if($auth_data=='all' || in_array('Express/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/express/index','查寄快递')">查寄快递</div>
				<?php endif; ?>
				<?php endif; if(getcustom('workorder')): if($auth_data=='all' || in_array('WorkorderCategory/*',$auth_data)): ?>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/workorder/category','工单类型')">工单类型</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/workorder/index','提交工单')">提交工单</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/workorder/record','工单记录')">工单记录</div>
					<?php endif; ?>
				<?php endif; if(getcustom('plug_yuebao')): if($auth_data=='all' || in_array('Yuebao/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/yuebao/withdraw','余额宝')">余额宝</div>
					<?php endif; ?>
				<?php endif; if($image_search): if($auth_data=='all' || in_array('Backstage/imgsearch',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/shop/imgsearch?bid=<?php echo $bid; ?>','图搜')">图搜</div>
					<?php endif; ?>
				<?php endif; if($diy_light): if($auth_data=='all' || in_array('Backstage/diylight',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/shop/diylight?bid=<?php echo $bid; ?>','配灯')">配灯</div>
					<?php endif; ?>
				<?php endif; if(getcustom('pay_yuanbao')): if($auth_data=='all' || in_array('YuanBao/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/yuanbao/yuanbaolog','<?php echo t('元宝'); ?>')"><?php echo t('元宝'); ?></div>
					<?php endif; ?>
				<?php endif; if(getcustom('renovation_calculator') && ($auth_data=='all' || in_array('RenovationCalculator/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/renovation/form','装修计算器')">装修计算器</div>
				<?php endif; if(getcustom('counsel_fee') && ($auth_data=='all' || in_array('CounselFee/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/counsel/index','律师费计算器')">律师费计算器</div>
				<?php endif; if(getcustom('legal_fee') && ($auth_data=='all' || in_array('LegalFee/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/legal/index','诉讼费计算器')">诉讼费计算器</div>
				<?php endif; if(getcustom('yueke')): if($auth_data=='all' || in_array('YuekeProduct/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/yueke/my','<?php echo t('教练'); ?>界面')"><?php echo t('教练'); ?>界面</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/yueke/prolist','约课列表')">约课列表</div>
					<?php endif; if($auth_data=='all' || in_array('YuekeOrder/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/yueke/orderlist','约课记录')">约课记录</div>
					<?php endif; ?>
				<?php endif; if(getcustom('car_hailing')): if($auth_data=='all' || in_array('CarHailingProduct/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/carhailing/prolist','约车列表')">约车列表</div>
					<?php endif; if($auth_data=='all' || in_array('CarHailingOrder/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/carhailing/orderlist','约车记录')">约车记录</div>
					<?php endif; if($auth_data=='all' || in_array('CarHailingCategory/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/carhailing/classify','约车分类列表')">约车分类列表</div>
					<?php endif; ?>
				<?php endif; if($auth_data=='all' || in_array('Mingpian/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/mingpian/index','名片详情')">名片详情</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/mingpian/edit','创建名片')">创建名片</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/mingpian/favorite','名片夹')">名片夹</div>
					<?php if(getcustom('mingpian_addfields_membercustom')): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/mingpian/list','名片列表')">名片列表</div>
					<?php endif; ?>
				<?php endif; if($auth_data=='all' || in_array('CycleProduct/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/cycle/prolist','周期购商品')">周期购商品</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/cycle/orderList','周期购订单')">周期购订单</div>
				<?php endif; if(getcustom('membercard_custom') && ($auth_data=='all' || in_array('Membercard/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/membercard/share','会员卡分享')">会员卡分享</div>
				<?php endif; if(getcustom('task_banner')): if($auth_data=='all' || in_array('TaskBanner/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/taskbanner/index','广告任务')">广告任务</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/taskbanner/record','广告任务记录')">广告任务记录</div>
					<?php endif; ?>
				<?php endif; if(getcustom('paotui')): if($auth_data=='all' || in_array('PaotuiSet/*',$auth_data)): ?>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/paotui/index','跑腿页面')">跑腿页面</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/paotui/orderlist','跑腿订单列表')">跑腿订单列表</div>
					<?php endif; ?>
				<?php endif; if(getcustom('extend_tour')): if($auth_data=='all' || in_array('TourSet/*',$auth_data)): ?>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/tour/index','活动列表')">活动列表</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/tour/orderlist','活动订单列表')">活动订单列表</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/tour/apply','申请导游')">申请导游</div>
					<?php endif; ?>
				<?php endif; if(getcustom('extend_gift_bag')): if($auth_data=='all' || in_array('GiftBag/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/giftbag/index','礼包列表')">礼包列表</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/giftbag/orderlist','礼包订单列表')">礼包订单列表</div>
					<?php endif; ?>
				<?php endif; if($auth_data=='all' || in_array('Yuyue/*',$auth_data)): if(getcustom('extend_yuyue_car')): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/yuyuecar/car','车牌信息')">车牌信息</div>
					<?php endif; ?>
				<?php endif; if(getcustom('product_handwork') && ($auth_data=='all' || in_array('ShopHandOrder/*',$auth_data))): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/handwork/list','回寄总列表')">回寄总列表</div>
				<?php endif; if(getcustom('douyin_groupbuy')): if($auth_data=='all' || in_array('DouyinGroupbuySet/*',$auth_data)): ?>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/douyin_groupbuy/index','抖音团购核销')">抖音团购核销</div>
					<?php endif; ?>
				<?php endif; if(getcustom('huodong_baoming')): if($auth_data=='all' || in_array('HuodongBaomingList/*',$auth_data)): ?>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/huodongbaoming/prolist','活动报名')">活动报名分类列表</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/huodongbaoming/prolist2','活动列表')">活动列表</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/huodongbaoming/orderlist','活动报名订单')">活动报名订单</div>
					<?php endif; ?>
				<?php endif; if(getcustom('yx_business_miandan')): if($auth_data=='all' || in_array('BusinessMiandan/*',$auth_data)): ?>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/businessmiandan/orderlist','多商户免单订单')">多商户免单订单</div>
					<?php endif; ?>
				<?php endif; if(getcustom('daily_jinju') && $bid==0): if($auth_data=='all' || in_array('DailyjinjuProduct/*',$auth_data)): ?>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/daily/wqlist','每日金句')">每日金句</div>
					<?php endif; ?>
				<?php endif; if(getcustom('ciruikang_fenxiao')): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/ciruikang/stocklog','库存记录')">库存记录</div>
				<?php endif; if(getcustom('business_mybusiness')): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/business/mybusiness','我的店铺')">我的店铺</div>
				<?php endif; if(getcustom('image_ai') && ($auth_data=='all' || in_array('ImgaiOrder/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/imgai/create','百度AI绘画')">百度AI绘画</div>
				<?php endif; if(getcustom('map_mark') && ($auth_data=='all' || in_array('MapmarkOrder/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/mapmark/edit','地图标注')">地图标注</div>
				<?php endif; if(getcustom('video_spider') && ($auth_data=='all' || in_array('VideospiderOrder/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/videospider/analysis','短视频去水印')">短视频去水印</div>
				<?php endif; if(getcustom('health_assessment')): if($auth_data=='all' || in_array('HealthAssessment/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/health/index','量表记录')">量表列表</div>
				<?php endif; if($auth_data=='all' || in_array('HealthRecord/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/health/record','量表记录')">量表记录</div>
				<?php endif; ?>
				<?php endif; if(getcustom('sxpay_c2b')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/sxpay/apply','随行付入驻')">随行付入驻</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/sxpay/myapply','随行付入驻记录')">随行付入驻记录</div>
				<?php endif; if(getcustom('lot_cerberuse')): if($auth_data=='all' || in_array('Cerberuse/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesZ/cerberuse/index','智能开门')">智能开门</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesZ/cerberuse/orderlist','智能开门订单列表')">智能开门订单列表</div>
				<?php endif; ?>
				<?php endif; if(getcustom('product_bonus_pool')): if(($auth_data=='all' || in_array('ShopProduct/product_bonus_pool',$auth_data)) && $bonus_pool_status): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/bonuspool/withdraw','<?php echo t('贡献值'); ?>提现')"><?php echo t('贡献值'); ?>提现</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/bonuspool/commissionlog','<?php echo t('贡献值'); ?>提现记录')"><?php echo t('贡献值'); ?>提现记录</div>
				<?php endif; ?>
				<?php endif; if(getcustom('coupon_xianxia_buy')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/xianxia/mycoupon','销售<?php echo t('优惠券'); ?>')">销售<?php echo t('优惠券'); ?></div>
				<?php if($auth_data=='all' || in_array('XianxiaCommissionLog/index',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/xianxia/commissionlog','线下<?php echo t('优惠券'); ?>打款记录')">线下<?php echo t('优惠券'); ?>打款记录</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/xianxia/membercommissionlog','线下<?php echo t('优惠券'); ?>提现记录')">线下<?php echo t('优惠券'); ?>提现记录</div>
				<?php endif; ?>
				<?php endif; if(getcustom('extend_certificate')): if($auth_data=='all' || in_array('CertificateList/*',$auth_data)): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/certificate/index','证书列表')">证书列表</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/certificate/mylist','我的证书列表')">我的证书列表</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/certificate/edit','证书编辑')">证书编辑</div>
				<?php endif; ?>
				<?php endif; if(getcustom('camera_hikvision') && ($auth_data=='all' || in_array('Hikvision/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/hikvision/hikvisionlist','海康监控')">海康监控</div>
				<?php endif; if(getcustom('mendian_list')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/mendian/list','门店列表')">门店列表</div>
				<?php endif; if(getcustom('member_levelup_auth')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/levelupauth/salelist','售卖列表')">售卖列表</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/levelupauth/saleorderlist','售卖订单')">售卖订单</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/levelupauth/buyorderlist','购买订单')">购买订单</div>
				<?php endif; if(getcustom('yx_gift_pack')): if(($auth_data=='all' || in_array('GiftPack/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/giftpack/list','礼包列表')">礼包列表</div>
				<?php endif; ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/banklist/bank','银行卡列表')">银行卡列表</div>
				<?php endif; if($showmendianapply && ($auth_data=='all' || in_array('Mendian/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/mendianup/apply','<?php echo t('门店'); ?>申请')"><?php echo t('门店'); ?>申请</div>
				<?php endif; if(getcustom('mendian_upgrade') && ($auth_data=='all' || in_array('Mendian/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/mendianup/apply','<?php echo t('门店'); ?>中心')"><?php echo t('门店'); ?>中心</div>
				<?php endif; if(getcustom('custom_help') && ($auth_data=='all' || in_array('Helpnew/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/helpnew/lists','帮助中心')">帮助中心</div>
				<?php endif; if(getcustom('pos_score_tongji') && ($auth_data=='all' || in_array('Posscore/*',$auth_data))): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/posscore/detail','pos设备中心')">pos设备中心</div>
				<?php endif; if(getcustom('extend_staff')): if($auth_data=='all' || in_array('Staff/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/my/staffcommissionlog','员工提成记录')">员工提成记录</div>
					<?php endif; ?>
				<?php endif; if(getcustom('extend_fish_pond') && ($auth_data=='all' || in_array('FishPond/*',$auth_data))): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/fishpond/list','场地管理')">场地管理</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/fishpond/orderlist','场地订单')">场地订单</div>
				<?php endif; if(getcustom('shop_purchase_order') && ($auth_data=='all' || in_array('shopPurchaseOrder',$auth_data))): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/purchaseorder/index','采购单')">采购单</div>
				<?php endif; if(getcustom('member_goldmoney_silvermoney')): if($canshowsilvermoney): ?>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/my/silvermoneylog','<?php echo t('银币'); ?>记录')"><?php echo t('银币'); ?>记录</div>
					<?php endif; if($canshowgoldmoney): ?>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/my/goldmoneylog','<?php echo t('金币'); ?>记录')"><?php echo t('金币'); ?>记录</div>
					<?php endif; ?>
				<?php endif; if(getcustom('h5zb') && ($auth_data=='all' || in_array('H5zbLiveRoom/*',$auth_data))): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/h5zb/client/zblist','直播列表')">直播列表</div>
				<?php endif; if(getcustom('extend_invite_redpacket')): if($auth_data=='all' || in_array('InviteRedpacket/index',$auth_data) || in_array('InviteRedpacket/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/inviteredpacket/redpacketlist','邀请分红包列表')">邀请分红包列表</div>
					<?php endif; ?>
				<?php endif; if(getcustom('yueke_extend') && ($auth_data=='all' || in_array('YuekeWorker/*',$auth_data))): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesExt/yueke/workerorderlist','<?php echo t('教练'); ?>登录')"><?php echo t('教练'); ?>登录</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/yueke/apply','<?php echo t('教练'); ?>入驻')"><?php echo t('教练'); ?>入驻</div>
				<?php endif; if(getcustom('extend_certificate_query')): if($auth_data=='all' || in_array('CertificateQuery/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/certificatequery/index','证书查询')">证书查询</div>
					<?php endif; ?>
					<?php endif; if(getcustom('extend_material')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/material/index','素材库')">素材库</div>
				<?php endif; if(getcustom('finance_invoice_baoxiao')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/invoicebaoxiao/index','发票报销')">发票报销</div>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/invoicebaoxiao/recordlist','发票报销记录')">发票报销记录</div>
				<?php endif; if(getcustom('member_dedamount')): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/my/dedamountlog','我的抵扣金')">我的抵扣金</div>
				<?php endif; if(getcustom('member_shopscore') && $membershopscoreauth): ?>
				<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/my/shopscorelog','我的<?php echo t('产品积分'); ?>')">我的<?php echo t('产品积分'); ?></div>
				<?php endif; if(getcustom('yx_task')): if($auth_data=='all' || in_array('Task/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesC/task/index','任务')">任务</div>
					<?php endif; ?>
				<?php endif; ?>
				</div>
				<div class="page-header" style="margin:10px 0 0 0;padding-bottom:0">
					<h4><i class="fa fa-folder-open-o"></i> 后台页面</h4>
				</div>
				<div class="layui-btn-div">
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/admin/index/index','手机端后台')">手机端后台</div>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/activity/peisong/dating','配送员抢单大厅')">配送员抢单大厅</div>
					<?php if($auth_data=='all' || in_array('Yuyue/*',$auth_data) || in_array('YuyueList/*',$auth_data) || in_array('YuyueOrder/*',$auth_data) || in_array('YuyueWorker/*',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/yuyue/yuyue/my','预约服务人员')">预约服务人员</div>
					<?php endif; ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pages/kefu/index','在线咨询')">在线咨询</div>
					<?php if(getcustom('product_pickup_device')): if($auth_data=='all' || in_array('ProductPickupDevice/addstocklist',$auth_data) || in_array('ProductPickupDevice/addstock',$auth_data) || in_array('ProductPickupDevice/addstocklog',$auth_data) || in_array('ProductPickupDevice/logdetail',$auth_data)): ?>
					<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesB/admin/pickupdevice','商品柜设备')">商品柜设备</div>
					<?php endif; ?>
					<?php endif; ?>
				</div>
				<?php if(getcustom('extend_chongzhi')): if($auth_data=='all' || in_array('Livepay/*',$auth_data)): ?>
					<div class="page-header" style="margin:10px 0 0 0;padding-bottom:0">
						<h4><i class="fa fa-folder-open-o"></i> 生活缴费</h4>
					</div>
					<div class="layui-btn-div">
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/livepay/mobile_recharge?type=phone','话费充值')">话费充值</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/livepay/ordinary_recharge?type=electric','电费')">电费</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/livepay/ordinary_recharge?type=water','水费')">水费</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/livepay/ordinary_recharge?type=gas','燃气费用')">燃气费用</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/livepay/ordinary_recharge?type=heating','供暖费')">供暖费</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/livepay/ordinary_recharge?type=charging','充电桩电费')">充电桩电费</div>
						<div class="layui-btn layui-btn-primary layui-btn-sm" onclick="chooseLink(1,'/pagesA/livepay/record_recharge?type=all','缴费订单记录')">缴费订单记录</div>
					</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<!--基础功能 end-->
			<!--商品分类-->
			<?php if($auth_data=='all' || in_array('ShopCategory/*',$auth_data)): ?>
			<div class="layui-tab-item fe-tab-link">
				<?php foreach($clist as $pKey=>$c): ?>
				<div>
					<div class="fe-tab-link-line" style='height:60px;line-height:60px;float:left;width:100%;'>
						<div class="fe-tab-link-sub"><a href="javascript:void(0);" onclick="chooseLinkCategory(2,'<?php echo $c['id']; ?>','<?php echo $c['name']; ?>')">选择</a></div>
						<div style="float:left;">
						<?php if($c['pic']): ?><img src="<?php echo $c['pic']; ?>" style='width:50px;height:50px;padding:1px;border:1px solid #ccc'/><?php endif; ?>
					</div>
					<div class="fe-tab-link-text" style="float:left;height: 40px;">
						<?php if($c['child']): ?><i class="layui-icon layui-icon-triangle-d" index=<?php echo $pKey; ?> type="parent" state="block" style="cursor: pointer;"></i><?php endif; ?>
						<?php echo $c['name']; ?>
					</div>
					</div>
				</div>
				<span class="parent_<?php echo $pKey; ?>">
					<?php foreach($c['child'] as $cKey=>$v): ?>
					<div style="width:97%;float:right">
						<div class="fe-tab-link-line" style='height:60px;line-height:60px;float:left;width:100%;'>
							<div class="fe-tab-link-sub"><a href="javascript:void(0);" onclick="chooseLink(2,'/pages/shop/prolist?cid=<?php echo $v['id']; ?>','<?php echo $v['name']; ?>')">选择</a></div>
							<div style="float:left;">
							<?php if($v['pic']): ?><img src="<?php echo $v['pic']; ?>" style='width:50px;height:50px;padding:1px;border:1px solid #ccc'/><?php endif; ?>
						</div>
						<div class="fe-tab-link-text" style="float:left;height: 40px;">
							<?php if($v['child']): ?><i class="layui-icon layui-icon-triangle-d" index=<?php echo $pKey; ?><?php echo $cKey; ?> type="child" state="block" style="cursor: pointer;"></i><?php endif; ?>
							<?php echo $v['name']; ?>
						</div>
						</div>
					</div>
					<?php foreach($v['child'] as $v2): ?>
					<div style="width:94%;float:right" class="child_<?php echo $pKey; ?><?php echo $cKey; ?>">
						<div class="fe-tab-link-line" style='height:60px;line-height:60px;float:left;width:100%;'>
							<div class="fe-tab-link-sub"><a href="javascript:void(0);" onclick="chooseLink(2,'/pages/shop/prolist?cid=<?php echo $v2['id']; ?>','<?php echo $v2['name']; ?>')">选择</a></div>
							<div style="float:left;">
							<?php if($v2['pic']): ?><img src="<?php echo $v2['pic']; ?>" style='width:50px;height:50px;padding:1px;border:1px solid #ccc'/><?php endif; ?>
						</div>
						<div class="fe-tab-link-text" style="float:left">
							<?php echo $v2['name']; ?></div>
						</div>
					</div>
					<?php endforeach; ?>
					<?php endforeach; ?>
				</span>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
			<!--商品分类 end-->
			<!--商品分组-->
			<?php if($auth_data=='all' || in_array('ShopGroup/*',$auth_data)): ?>
			<div class="layui-tab-item fe-tab-link">
				<?php foreach($glist as $g): ?>
				<div>
					<div class="fe-tab-link-line" style='height:60px;line-height:60px;float:left;width:100%;'>
						<div class="fe-tab-link-sub"><a href="javascript:void(0);" onclick="chooseLink(3,'/pages/shop/prolist?gid=<?php echo $g['id']; ?>','<?php echo $g['name']; ?>')">选择</a></div>
						<div style="float:left;">
						<?php if($g['pic']): ?><img src="<?php echo $g['pic']; ?>" style='width:50px;height:50px;padding:1px;border:1px solid #ccc'/><?php endif; ?>
					</div>
					<div class="fe-tab-link-text" style="float:left">
						<?php echo $g['name']; ?></div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
			<!--商品分组 end-->
			<!--商城商品-->
			<?php if($auth_data=='all' || in_array('ShopProduct/*',$auth_data)): ?>
			<div class="layui-tab-item fe-tab-link">
				<div class="layui-form layui-inline">
					<div class="layui-input-inline">
						<input type="text" class="layui-input" style="height:30px;" name="productname" value="" placeholder=请输入商品名称进行搜索>
					</div>
					<button type="button" class="layui-btn layui-btn-primary layui-btn-sm" lay-submit="" lay-filter="LAY-app-forumreply-search-product">搜索</button>
				</div>
				<div class="layui-col-md12" style="margin-top:5px">
					<table id="producttabledata" lay-filter="producttabledata"></table>
				</div>
			</div>
			<?php endif; ?>
			<!--商城商品 end-->
			<!--拼团商品-->
			<?php if($auth_data=='all' || in_array('CollageProduct/*',$auth_data)): ?>
			<div class="layui-tab-item fe-tab-link">
				<div class="layui-form layui-inline">
					<div class="layui-input-inline">
						<input type="text" class="layui-input" style="height:30px;" name="collageproductname" value="" placeholder=请输入商品名称进行搜索>
					</div>
					<button type="button" class="layui-btn layui-btn-primary layui-btn-sm" lay-submit="" lay-filter="LAY-app-forumreply-search-collageproduct">搜索</button>
				</div>
				<div class="layui-col-md12" style="margin-top:5px">
					<table id="collageproducttabledata" lay-filter="collageproducttabledata"></table>
				</div>
			</div>
			<?php endif; ?>
			<!--拼团商品 end-->
			<!--砍价商品-->
			<?php if($auth_data=='all' || in_array('KanjiaProduct/*',$auth_data)): ?>
			<div class="layui-tab-item fe-tab-link">
				<div class="layui-form layui-inline">
					<div class="layui-input-inline">
						<input type="text" class="layui-input" style="height:30px;" name="kanjiaproductname" value="" placeholder=请输入商品名称进行搜索>
					</div>
					<button type="button" class="layui-btn layui-btn-primary layui-btn-sm" lay-submit="" lay-filter="LAY-app-forumreply-search-kanjiaproduct">搜索</button>
				</div>
				<div class="layui-col-md12" style="margin-top:5px">
					<table id="kanjiaproducttabledata" lay-filter="kanjiaproducttabledata"></table>
				</div>
			</div>
			<?php endif; ?>
			<!--砍价商品 end-->
			<!--积分兑换-->
			<?php if($auth_data=='all' || in_array('ScoreshopProduct/*',$auth_data)): ?>
			<div class="layui-tab-item fe-tab-link">
				<div class="layui-form layui-inline">
					<div class="layui-input-inline">
						<input type="text" class="layui-input" style="height:30px;" name="scoreshopproductname" value="" placeholder=请输入商品名称进行搜索>
					</div>
					<button type="button" class="layui-btn layui-btn-primary layui-btn-sm" lay-submit="" lay-filter="LAY-app-forumreply-search-scoreshopproduct">搜索</button>
				</div>
				<div class="layui-col-md12" style="margin-top:5px">
					<table id="scoreshopproducttabledata" lay-filter="scoreshopproducttabledata"></table>
				</div>
			</div>
			<?php endif; ?>
			<!--积分兑换 end-->
			<!--文章分类-->
			<?php if($auth_data=='all' || in_array('ArticleCategory/*',$auth_data)): ?>
			<div class="layui-tab-item fe-tab-link">
				<?php foreach($aclist as $pKey=>$c): ?>
				<div>
					<div class="fe-tab-link-line" style='height:60px;line-height:60px;float:left;width:100%;'>
						<?php if(getcustom('article_portion')): ?>
						<div class="fe-tab-link-sub"><a href="javascript:void(0);" onclick="chooseLink(8,'/pagesExt/article/artlist?bid=<?php echo $bid; ?>&cid=<?php echo $c['id']; ?>&look_type=1','<?php echo $c['name']; ?>')">选择</a></div>
						<?php endif; if(!getcustom('article_portion')): ?>
						<div class="fe-tab-link-sub"><a href="javascript:void(0);" onclick="chooseLink(8,'/pagesExt/article/artlist?bid=<?php echo $bid; ?>&cid=<?php echo $c['id']; ?>','<?php echo $c['name']; ?>')">选择</a></div>
						<?php endif; ?>
						<div style="float:left;"><?php if($c['pic']): ?><img src="<?php echo $c['pic']; ?>" style='width:50px;height:50px;padding:1px;border:1px solid #ccc'/><?php endif; ?></div>
						<div class="fe-tab-link-text" style="float:left;height: 40px;">
							<?php if($c['child']): ?><i class="layui-icon layui-icon-triangle-d" index=<?php echo $pKey; ?> type="parent" state="block" style="cursor: pointer;"></i><?php endif; ?>
							<?php echo $c['name']; ?>
						</div>
					</div>
				</div>
				<?php foreach($c['child'] as $cKey=>$v): ?>
				<div style="width:97%;float:right" class="parent_<?php echo $pKey; ?>">
					<div class="fe-tab-link-line" style='height:60px;line-height:60px;float:left;width:100%;'>
						<?php if(getcustom('article_portion')): ?>
						<div class="fe-tab-link-sub"><a href="javascript:void(0);" onclick="chooseLink(8,'/pagesExt/article/artlist?bid=<?php echo $bid; ?>&cid=<?php echo $v['id']; ?>&look_type=1','<?php echo $v['name']; ?>')">选择</a></div>
						<?php endif; if(!getcustom('article_portion')): ?>
						<div class="fe-tab-link-sub"><a href="javascript:void(0);" onclick="chooseLink(8,'/pagesExt/article/artlist?bid=<?php echo $bid; ?>&cid=<?php echo $v['id']; ?>','<?php echo $v['name']; ?>')">选择</a></div>
						<?php endif; ?>
						<div style="float:left;"><?php if($v['pic']): ?><img src="<?php echo $v['pic']; ?>" style='width:50px;height:50px;padding:1px;border:1px solid #ccc'/><?php endif; ?></div>
						<div class="fe-tab-link-text" style="float:left"><?php echo $v['name']; ?></div>
					</div>
				</div>
				<?php endforeach; ?>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
			<!--文章分类 end-->
			<!--文章-->
			<?php if($auth_data=='all' || in_array('Article/*',$auth_data)): ?>
			<div class="layui-tab-item fe-tab-link">
				<div class="layui-form layui-inline">
					<div class="layui-input-inline">
						<input type="text" class="layui-input" style="height:30px;" name="articlename" value="" placeholder=请输入文章标题进行搜索>
					</div>
					<button type="button" class="layui-btn layui-btn-primary layui-btn-sm" lay-submit="" lay-filter="LAY-app-forumreply-search-article">搜索</button>
				</div>
				<div class="layui-col-md12" style="margin-top:5px">
					<table id="articletabledata" lay-filter="articletabledata"></table>
				</div>
			</div>
			<?php endif; ?>
			<!--文章 end-->
			<!--商户分类-->
			<?php if($auth_data=='all' || in_array('Business/category',$auth_data)): ?>
			<div class="layui-tab-item fe-tab-link">
				<?php foreach($bclist as $bc): ?>
				<div>
					<div class="fe-tab-link-line" style='height:60px;line-height:60px;float:left;width:100%;'>
						<div class="fe-tab-link-sub"><a href="javascript:void(0);" onclick="chooseLink(10,'/pagesExt/business/blist?cid=<?php echo $bc['id']; ?>','<?php echo $bc['name']; ?>')">商户列表</a></div>
						<div class="fe-tab-link-sub"><a href="javascript:void(0);" onclick="chooseLink(10,'/pagesExt/business/clist?cid=<?php echo $bc['id']; ?>','<?php echo $bc['name']; ?>')">分类商户</a></div>
						<div style="float:left;"><?php if($bc['pic']): ?><img src="<?php echo $bc['pic']; ?>" style='width:50px;height:50px;padding:1px;border:1px solid #ccc'/><?php endif; ?></div>
						<div class="fe-tab-link-text" style="float:left"><?php echo $bc['name']; ?></div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
			<!--商户分类 end-->
			<!--商户-->
			<?php if($auth_data=='all' || in_array('Business/*',$auth_data)): ?>
			<div class="layui-tab-item fe-tab-link">
				<div class="layui-form layui-inline">
					<div class="layui-input-inline">
						<input type="text" class="layui-input" style="height:30px;" name="businessname" value="" placeholder=请输入商户名称进行搜索>
					</div>
					<button type="button" class="layui-btn layui-btn-primary layui-btn-sm" lay-submit="" lay-filter="LAY-app-forumreply-search-business">搜索</button>
				</div>
				<div class="layui-col-md12" style="margin-top:5px">
					<table id="businesstabledata" lay-filter="businesstabledata"></table>
				</div>
			</div>
			<?php endif; ?>
			<!--商户 end-->



			<?php if(getcustom('huodong_baoming')): ?>
			<!--活动报名f分类-->
			<?php if($bid==0 && ($auth_data=='all' || in_array('HuodongBaomingCategory/*',$auth_data))): ?>
			<div class="layui-tab-item fe-tab-link">
				<?php foreach($hdbaomingcatelist as $c): ?>
				<div>
					<div class="fe-tab-link-line" style='height:60px;line-height:60px;float:left;width:100%;'>
						<div class="fe-tab-link-sub"><a href="javascript:void(0);" onclick="chooseLink(3,'/pagesB/huodongbaoming/prolist2?cid=<?php echo $c['id']; ?>','<?php echo $c['name']; ?>')">选择</a></div>
						<div style="float:left;">
						<?php if($c['pic']): ?><img src="<?php echo $c['pic']; ?>" style='width:50px;height:50px;padding:1px;border:1px solid #ccc'/><?php endif; ?>
					</div>
					<div class="fe-tab-link-text" style="float:left">
						<?php echo $c['name']; ?></div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
			<!--活动报名分类end-->
			<!--活动报名列表-->
			<?php if($auth_data=='all' || in_array('HuodongBaomingList/*',$auth_data)): ?>
			<div class="layui-tab-item fe-tab-link">
				<div class="layui-form layui-inline">
					<div class="layui-input-inline">
						<input type="text" class="layui-input" style="height:30px;" name="huodongname" value="" placeholder=请输入活动名称进行搜索>
					</div>
					<button type="button" class="layui-btn layui-btn-primary layui-btn-sm" lay-submit="" lay-filter="LAY-app-forumreply-search-huodong">搜索</button>
				</div>
				<div class="layui-col-md12" style="margin-top:5px">
					<table id="huodongtabledata" lay-filter="huodongtabledata"></table>
				</div>
			</div>
			<?php endif; ?>
			<?php endif; ?>
			<!--活动报名列表 end-->

			<?php if(getcustom('hotel')): ?>
			<!--酒店分类-->
			<?php if($auth_data=='all' || in_array('HotelCategory/*',$auth_data)): ?>
			<div class="layui-tab-item fe-tab-link">
				<?php foreach($hotelcatelist as $c): ?>
				<div>
					<div class="fe-tab-link-line" style='height:60px;line-height:60px;float:left;width:100%;'>
						<div class="fe-tab-link-sub"><a href="javascript:void(0);" onclick="chooseLink(3,'/hotel/index/hotellist?cateid=<?php echo $c['id']; ?>','<?php echo $c['name']; ?>')">选择</a></div>
						<div style="float:left;">
						<?php if($c['pic']): ?><img src="<?php echo $c['pic']; ?>" style='width:50px;height:50px;padding:1px;border:1px solid #ccc'/><?php endif; ?>
					</div>
					<div class="fe-tab-link-text" style="float:left">
						<?php echo $c['name']; ?></div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
			<!--酒店分类 end-->


			<!--酒店列表-->
			<?php if($bid==0 && ($auth_data=='all' || in_array('Hotel/*',$auth_data))): ?>
			<div class="layui-tab-item fe-tab-link">
				<div class="layui-form layui-inline">
					<div class="layui-input-inline">
						<input type="text" class="layui-input" style="height:30px;" name="hotelname" value="" placeholder=请输入<?php echo $text['酒店']; ?>名称进行搜索>
					</div>
					<button type="button" class="layui-btn layui-btn-primary layui-btn-sm" lay-submit="" lay-filter="LAY-app-forumreply-search-hotel">搜索</button>
				</div>
				<div class="layui-col-md12" style="margin-top:5px">
					<table id="hoteltabledata" lay-filter="hoteltabledata"></table>
				</div>
			</div>
			<?php endif; ?>
			<!--酒店列表 end-->
			<?php endif; ?>

			<!--设计页面-->
			<?php if($auth_data=='all' || in_array('DesignerPage/*',$auth_data) || in_array('DesignerPage/index',$auth_data)): ?>
			<div class="layui-tab-item fe-tab-link" id="DesignerPage">
				<div class="layui-form layui-col-md12">
					<?php if(getcustom('design_cat')): 					$whereCt = [];
					$whereCt[] = ['type','=',0];
					$whereCt[] = ['aid','=',aid];
					$whereCt[] = ['bid','=',bid];
					$whereCt[] = ['status','=',1];
					$whereCt[] = ['is_del','=',0];
					$pageCat = db('designerpage_category')->where($whereCt)->select()->toArray();
					?>
					<div class="layui-inline">
						<div class="layui-input-inline">
							<select name="designerpagecid">
								<option value="">全部</option>
								<?php foreach($pageCat as $cv): ?>
								<option value="<?php echo $cv['id']; ?>"><?php echo $cv['name']; ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<?php endif; if(getcustom('design_remark')): ?>
					<div class="layui-inline">
						<div class="layui-input-inline">
							<input type="text" class="layui-input" name="designerpageremark" value="" placeholder=请输入页面备注>
						</div>
					</div>
					<?php endif; ?>
					<div class="layui-inline">
						<div class="layui-input-inline">
							<input type="text" class="layui-input" name="designerpagename" value="" placeholder=请输入页面名称进行搜索>
						</div>
						<button type="button" class="layui-btn layui-btn-primary layui-btn-sm" lay-submit="" lay-filter="LAY-app-forumreply-search-designerpage">搜索</button>
					</div>
				</div>
				<div class="layui-col-md12" style="margin-top:5px">
					<table id="designerpagetabledata" lay-filter="designerpagetabledata"></table>
				</div>
			</div>
			<?php endif; ?>
			<!--设计页面 end-->

			<!--选择功能-->
			<?php if(app('request')->param('type')!='geturl'): ?>
				<div class="layui-tab-item fe-tab-link" id="">
					<div class="layui-form" lay-filter="" style="padding-top:20px">
					<div class="layui-form-item">
						<label class="layui-form-label" style="width:100px">选择功能：</label>
						<div class="layui-input-inline" style="width: 80%;display: flex;flex-direction: column">
							<input type="radio" title="拨打电话" name="linktype" value="tel" lay-filter="linktype"/>
							<label><input type="radio" title="小程序客服" name="linktype" value="contact" lay-filter="linktype"/><div class="radio-tips">[仅支持以下组件：单图、按钮、悬浮按钮]</div></label>
							<label><input type="radio" title="分享转发" name="linktype" value="share" lay-filter="linktype"/><div class="radio-tips">[小程序分享转发仅支持以下组件：单图、按钮、悬浮按钮]</div></label>
							<input type="radio" title="扫一扫" name="linktype" value="scan" lay-filter="linktype"/>
							<input type="radio" title="坐标导航" name="linktype" value="location" lay-filter="linktype"/>
							<?php if(in_array('mp',$platform)): ?><input type="radio" title="领取会员卡" name="linktype" value="getmembercard" lay-filter="linktype"/><?php endif; if(in_array('wx',$platform)): ?>							
							<input type="radio" title="跳转其他微信小程序" name="linktype" value="miniProgram" lay-filter="linktype"/>
							<input type="radio" title="半屏微信小程序" name="linktype" value="embeddedMiniProgram" lay-filter="linktype"/>
							<input type="radio" title="小程序激励广告" name="linktype" value="rewardedVideoAd" lay-filter="linktype"/>
							<label><input type="radio" title="视频号主页" name="linktype" value="channelsUserProfile" lay-filter="linktype"/><div class="radio-tips">[仅微信小程序支持]</div></label>
							<?php endif; if($bid==0 && in_array('wx',$platform)): ?>
							<label><input type="radio" title="视频号直播" name="linktype" value="channelsLive" lay-filter="linktype"/><div class="radio-tips">[要求小程序与视频号同主体或关联主体]</div></label>
							<?php endif; ?>
							<input type="radio" title="复制文本" name="linktype" value="copy" lay-filter="linktype"/>
							<input type="radio" title="外部链接" name="linktype" value="url" lay-filter="linktype"/>
							<?php if(getcustom('chooseurl_tosendsms') && in_array('wx',$platform)): ?>
							<input type="radio" title="发送短信" name="linktype" value="sendsms" lay-filter="linktype"/>
							<?php endif; ?>
							<label><input type="radio" title="商品收藏" name="linktype" value="addfavorite" lay-filter="linktype"/><div class="radio-tips">[仅商品详情页支持]</div></label>
							<?php if(getcustom('connect_wifi') && (in_array('wx',$platform) || in_array('alipay',$platform)) && in_array($is_admin ,[1,2])): ?>
							<input type="radio" title="连接WiFi" name="linktype" value="connectwifi" lay-filter="linktype"/>
							<?php endif; if(getcustom('member_mpbing_openid')): ?>
							<label>
								<input type="radio" title="公众号绑定" name="linktype" value="mpbind" lay-filter="linktype"/>
								<div class="radio-tips">[绑定公众号OpenID,接收公众号订单通知;支持小程序和公众号H5]</div>
							</label>
							<?php endif; ?>
						</div>
						<div class="layui-form-mid layui-word-aux"></div>
					</div>
					<div id="linktype-content">
						<div class="layui-form-item tel" style="display:none">
							<label class="layui-form-label" style="width:100px">电话号码：</label>
							<div class="layui-input-inline">
								<input type="text" name="tel" value="" class="layui-input"/>
							</div>
						</div>
						<div class="layui-form-item contact" style="display:none">
							<label class="layui-form-label" style="width:100px"></label>
							<div class="layui-form-mid layui-word-aux">小程序在线客服仅支持以下组件：单图、按钮、悬浮按钮</div>
						</div>
						<div class="layui-form-item share" style="display:none">
							<label class="layui-form-label" style="width:100px"></label>
							<div class="layui-form-mid layui-word-aux">小程序分享转发仅支持以下组件：单图、按钮、悬浮按钮</div>
						</div>

						<div class="layui-form-item location" style="display:none">
							<label class="layui-form-label" style="width:100px">地点名称：</label>
							<div class="layui-input-inline">
								<input type="text" name="addressname" value="天安门广场" class="layui-input"/>
							</div>
						</div>
						<div class="layui-form-item location" style="display:none">
							<div class="layui-form-label" style="width:100px">经纬度：</div>
							<div class="layui-input-inline" style="width:150px">
								<input class="layui-input" name="longitude" value="116.397827"/>
							</div>
							<div class="layui-form-mid">,</div>
							<div class="layui-input-inline" style="width:150px">
								<input class="layui-input" name="latitude" value="39.90374"/>
							</div>
							<div class="layui-btn  layui-btn-primary" onclick="choosezuobiao()">选择坐标</div>
						</div>
						<div class="layui-form-item getmembercard" style="display:none">
							<label class="layui-form-label" style="width:100px"></label>
							<div class="layui-form-mid layui-word-aux">设置前请先创建微信会员卡</div>
						</div>
						<div class="layui-form-item url" style="display:none">
							<label class="layui-form-label" style="width:100px">链接地址：</label>
							<div class="layui-input-inline" style="width:300px">
								<input type="text" name="url" value="" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux" style="margin-left:130px">小程序跳转外部链接必须是https://开头的完整链接地址 可打开关联的公众号的文章，其它网页需登录<a href="https://mp.weixin.qq.com/" target="_blank">公众平台小程序账号</a>配置业务域名，个人类型与海外类型的小程序暂不支持使用。如果使用第三方开放平台，在第三方开发平台里添加业务域名后，小程序需要重新授权绑定。</div>
						</div>
						<div class="layui-form-item miniProgram" style="display:none">
							<label class="layui-form-label" style="width:100px">小程序 appId：</label>
							<div class="layui-input-inline">
								<input type="text" name="appid1" value="<?php echo $rwvideoad['unitid']; ?>" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">要跳转的小程序AppID，此功能仅小程序或APP可用</div>
						</div>
						<div class="layui-form-item miniProgram" style="display:none">
							<label class="layui-form-label" style="width:100px">小程序原始id：</label>
							<div class="layui-input-inline">
								<input type="text" name="username" value="" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux"><?php if(in_array('app',$platform)): ?>APP及微信会员卡跳转小程序必填<?php endif; ?></div>
						</div>

						<div class="layui-form-item channelsUserProfile" style="display:none">
							<label class="layui-form-label" style="width:100px">视频号ID：</label>
							<div class="layui-input-inline">
								<input type="text" name="finderUserName" value="" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">必填，登录视频号助手，在首页可以查看自己的视频号ID，此功能仅微信小程序可用</div>
						</div>
						<div class="layui-form-item channelsLive" style="display:none">
							<label class="layui-form-label" style="width:100px">视频号ID：</label>
							<div class="layui-input-inline">
								<input type="text" name="finderUserNameLive" value="" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">必填，登录视频号助手，在首页可以查看自己的视频号ID，此功能仅微信小程序可用</div>
						</div>
						<div class="layui-form-item miniProgram" style="display:none">
							<label class="layui-form-label" style="width:100px">页面路径：</label>
							<div class="layui-input-inline">
								<input type="text" name="path1" value="" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">不填写则默认打开首页</div>
						</div>
						<?php if(getcustom('tominiprogram_givemoney')): ?>
						<div class="layui-form-item miniProgram" style="display:none">
							<label class="layui-form-label" style="width:100px">赠送<?php echo t('积分'); ?>：</label>
							<div class="layui-input-inline">
								<input type="text" name="mini_givescore" value="<?php echo $rwvideoad['givescore']; ?>" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">成功跳转后奖励<?php echo t('积分'); ?>数</div>
						</div>
						<div class="layui-form-item miniProgram" style="display:none">
							<label class="layui-form-label" style="width:100px">赠送<?php echo t('余额'); ?>：</label>
							<div class="layui-input-inline">
								<input type="text" name="mini_givemoney" value="<?php echo $rwvideoad['givemoney']; ?>" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">成功跳转后奖励<?php echo t('余额'); ?></div>
						</div>
						<div class="layui-form-item miniProgram" style="display:none">
							<label class="layui-form-label" style="width:100px">赠送上级<?php echo t('余额'); ?>：</label>
							<div class="layui-input-inline">
								<input type="text" name="mini_givemoneyparent" value="<?php echo $rwvideoad['givemoneyparent']; ?>" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">成功跳转后奖励上级<?php echo t('余额'); ?></div>
						</div>
						<div class="layui-form-item miniProgram" style="display:none">
							<label class="layui-form-label" style="width:100px">每天限制赠送次数：</label>
							<div class="layui-input-inline">
								<input type="text" name="mini_givetimes" value="<?php echo $rwvideoad['givetimes']; ?>" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">每人每天最多可赠送多少次</div>
						</div>
						<div class="layui-form-item miniProgram" style="display:none">
							<label class="layui-form-label" style="width:100px">总限制赠送次数：</label>
							<div class="layui-input-inline">
								<input type="text" name="mini_givetimestotal" value="<?php echo $rwvideoad['givetimestotal']; ?>" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">每人最多可赠送多少次</div>
						</div>
						<?php endif; if(getcustom('scoreshop_otheradmin_buy')): if($auth_data=='all' || in_array('BuyOverallScoreshop',$auth_data)): ?>
						<div class="layui-form-item miniProgram" style="display:none">
							<label class="layui-form-label" style="width:100px">传递<?php echo t('会员'); ?>ID：</label>
							<div class="layui-input-inline">
								<input type="radio" title="关闭" name="sendmid" value="0" checked />
								<input type="radio" title="开启" name="sendmid" value="1"/>
							</div>
							<div class="layui-form-mid layui-word-aux">系统平台之间跳转使用(目前仅当前平台用户兑换总平台<?php echo t('积分'); ?>商品有效)；开启后需要用户先登录账号，然后才能跳转到总平台页面</div>
						</div>
						<?php endif; ?>
						<?php endif; ?>
						<div class="layui-form-item miniProgram" style="display:none">
							<label class="layui-form-label" style="width:100px"></label>
						</div>

						<div class="layui-form-item embeddedMiniProgram" style="display:none">
							<label class="layui-form-label" style="width:100px">小程序AppID：</label>
							<div class="layui-input-inline">
								<input type="text" name="appid2" value="<?php echo $rwvideoad['unitid']; ?>" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">
								<?php if($authtype ==1): ?>
								前往[平台-微信小程序-半屏小程序]中进行申请
								<?php else: ?>
								请登录<a href="https://mp.weixin.qq.com/">微信公众平台</a>进入[设置-第三方设置-半屏小程序管理]中进行申请
								<?php endif; ?>
							</div>
						</div>
						<div class="layui-form-item embeddedMiniProgram" style="display:none">
							<label class="layui-form-label" style="width:100px">页面路径：</label>
							<div class="layui-input-inline">
								<input type="text" name="path2" value="" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">不填写则默认打开首页</div>
						</div>
						<div class="layui-form-item copy" style="display:none">
							<label class="layui-form-label" style="width:100px">复制文字内容：</label>
							<div class="layui-input-inline" style="width:300px">
								<input type="text" name="copytext" value="" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux"></div>
						</div>
						<div class="layui-form-item copy" style="display:none">
							<label class="layui-form-label" style="width:100px">提示文字：</label>
							<div class="layui-input-inline" style="width:300px">
								<input type="text" name="copymsg" value="复制成功" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">复制后提示文字</div>
						</div>
						
						<div class="layui-form-item rewardedVideoAd" style="display:none">
							<label class="layui-form-label" style="width:100px">广告位ID：</label>
							<div class="layui-input-inline" style="width:300px">
								<input type="text" name="rad_unitid" value="<?php echo $rwvideoad['unitid']; ?>" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">在小程序后台"流量主-广告管理-激励广告"中查找</div>
						</div>
						<div class="layui-form-item rewardedVideoAd" style="display:none">
							<label class="layui-form-label" style="width:100px">赠送<?php echo t('积分'); ?>：</label>
							<div class="layui-input-inline" style="width:300px">
								<input type="text" name="rad_givescore" value="<?php echo $rwvideoad['givescore']; ?>" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">观看结束后奖励<?php echo t('积分'); ?>数</div>
						</div>
						<div class="layui-form-item rewardedVideoAd" style="display:none">
							<label class="layui-form-label" style="width:100px">赠送<?php echo t('余额'); ?>：</label>
							<div class="layui-input-inline" style="width:300px">
								<input type="text" name="rad_givemoney" value="<?php echo $rwvideoad['givemoney']; ?>" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">观看结束后奖励<?php echo t('余额'); ?></div>
						</div>
						<div class="layui-form-item rewardedVideoAd" style="display:none">
							<label class="layui-form-label" style="width:100px">赠送上级<?php echo t('余额'); ?>：</label>
							<div class="layui-input-inline" style="width:300px">
								<input type="text" name="rad_givemoneyparent" value="<?php echo $rwvideoad['givemoneyparent']; ?>" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">观看结束后奖励上级<?php echo t('余额'); ?></div>
						</div>
						<div class="layui-form-item rewardedVideoAd" style="display:none">
							<label class="layui-form-label" style="width:100px">每天限制赠送次数：</label>
							<div class="layui-input-inline" style="width:300px">
								<input type="text" name="rad_givetimes" value="<?php echo $rwvideoad['givetimes']; ?>" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">每天最多可赠送多少次</div>
						</div>
						<div class="layui-form-item rewardedVideoAd" style="display:none">
							<label class="layui-form-label" style="width:100px">总限制赠送次数：</label>
							<div class="layui-input-inline" style="width:300px">
								<input type="text" name="rad_givetimestotal" value="<?php echo $rwvideoad['givetimestotal']; ?>" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">每人最多可赠送多少次</div>
						</div>
						<?php if(getcustom('rewardedVideoAd_url')): ?>
						<div class="layui-form-item rewardedVideoAd" style="display:none">
							<label class="layui-form-label" style="width:100px">跳转链接：</label>
							<div class="layui-input-inline" style="width:300px">
								<input type="text" name="rad_url" value="<?php echo $rwvideoad['rad_url']; ?>" id="rad_url" class="layui-input"/>
							</div>
							<div class="layui-btn layui-btn-primary" style="float:left" onclick="chooseUrl2('rad_url')">选择链接</div>
							<div class="layui-form-mid layui-word-aux">广告完成后跳转链接</div>
						</div>
						<?php endif; if(getcustom('chooseurl_tosendsms') && in_array('wx',$platform)): ?>
						<div class="layui-form-item sendsms" style="display:none">
							<label class="layui-form-label" style="width:100px">短信模板ID：</label>
							<div class="layui-input-inline" style="width:300px">
								<input type="text" name="sendsms_tmpl" value="" id="tmpl_sms" class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">模板内容示例：推荐您一个实用的小程序，点击链接前往：${link}</div>
						</div>
						<div class="layui-form-item sendsms" style="display:none">
							<label class="layui-form-label" style="width:100px">链接地址：</label>
							<div class="layui-input-inline" style="width:300px">
								<input type="text" name="sendsms_link" value="" id="sendsms_link" class="layui-input"/>
							</div>
						</div>
						<?php endif; if(getcustom('connect_wifi') && (in_array('wx',$platform) || in_array('alipay',$platform)) && in_array($is_admin ,[1,2])): ?>
						<div class="layui-form-item connectwifi" style="display:none">
							<label class="layui-form-label" style="width:100px">wifi名称：</label>
							<div class="layui-input-inline" style="width:300px">
								<input type="text" name="wifi_name" value="" id="wifi_name"  class="layui-input"/>
							</div>
							<div class="layui-form-mid layui-word-aux">仅Android与iOS11以上版本支持</div>
						</div>
						<div class="layui-form-item connectwifi" style="display:none">
							<label class="layui-form-label" style="width:100px">wifi密码：</label>
							<div class="layui-input-inline" style="width:300px">
								<input type="text" name="wifi_password" value="" id="wifi_password" class="layui-input"/>
							</div>
						</div>
						<?php endif; ?>
					</div>

					<div class="layui-form-item">
						<div class="layui-input-block" style="margin-left:130px;">
							<button class="layui-btn" lay-submit lay-filter="formsubmit">确 定</button>
						</div>
					</div>
					</div>
				</div>
			<?php endif; ?>
			<!--选择功能 end-->

			<?php if(getcustom('goods_hexiao')): if($auth_data=='all' || in_array('RestaurantProduct/*',$auth_data)): ?>
			<div class="layui-tab-item fe-tab-link" id="RestaurantProduct">
				<div class="layui-form layui-inline">
					<div class="layui-input-inline">
						<input type="text" class="layui-input" style="height:30px;" name="productname" value="" placeholder=请输入商品名称进行搜索>
					</div>
					<button type="button" class="layui-btn layui-btn-primary layui-btn-sm" lay-submit="" lay-filter="LAY-app-forumreply-search-restaurant_product">搜索</button>
				</div>
				<div class="layui-col-md12" style="margin-top:5px">
					<table id="restaurant_producttabledata" lay-filter="restaurant_producttabledata"></table>
				</div>
			</div>
			<?php endif; ?>
			<?php endif; ?>

			<!--周期购商品-->
			<?php if($auth_data=='all' || in_array('CycleProduct/*',$auth_data)): ?>
			<div class="layui-tab-item fe-tab-link" id="CycleProduct">
				<div class="layui-form layui-inline">
					<div class="layui-input-inline">
						<input type="text" class="layui-input" style="height:30px;" name="cycleproductname" value="" placeholder=请输入商品名称进行搜索>
					</div>
					<button type="button" class="layui-btn layui-btn-primary layui-btn-sm" lay-submit="" lay-filter="LAY-app-forumreply-search-cycleproduct">搜索</button>
				</div>
				<div class="layui-col-md12" style="margin-top:5px">
					<table id="cycleproducttabledata" lay-filter="cycleproducttabledata"></table>
				</div>
			</div>
			<?php endif; ?>
			<!--周期购商品 end-->

			<!--团购商品-->
			<?php if($auth_data=='all' || in_array('TuangouProduct/*',$auth_data)): ?>
			<div class="layui-tab-item fe-tab-link" id="TuangouProduct">
				<div class="layui-form layui-inline">
					<div class="layui-input-inline">
						<input type="text" class="layui-input" style="height:30px;" name="tuangouproductname" value="" placeholder=请输入商品名称进行搜索>
					</div>
					<button type="button" class="layui-btn layui-btn-primary layui-btn-sm" lay-submit="" lay-filter="LAY-app-forumreply-search-tuangouproduct">搜索</button>
				</div>
				<div class="layui-col-md12" style="margin-top:5px">
					<table id="tuangouproducttabledata" lay-filter="tuangouproducttabledata"></table>
				</div>
			</div>
			<?php endif; ?>
			<!--团购商品 end-->
			<!--幸运拼团商品-->
				<?php if($auth_data=='all' || in_array('LuckyCollageProduct/*',$auth_data)): ?>
				<div class="layui-tab-item fe-tab-link" id="LuckyCollageProduct">
					<div class="layui-form layui-inline">
						<div class="layui-input-inline">
							<input type="text" class="layui-input" style="height:30px;" name="luckycollageproductname" value="" placeholder=请输入商品名称进行搜索>
						</div>
						<button type="button" class="layui-btn layui-btn-primary layui-btn-sm" lay-submit="" lay-filter="LAY-app-forumreply-search-luckycollageproduct">搜索</button>
					</div>
					<div class="layui-col-md12" style="margin-top:5px">
						<table id="luckycollageproducttabledata" lay-filter="luckycollageproducttabledata"></table>
					</div>
				</div>
				<?php endif; ?>
			<!--幸运拼团商品 end-->
			<!--幸运拼团分类-->
			<?php if($auth_data=='all' || in_array('LuckyCollageCategory/*',$auth_data)): ?>
			<div class="layui-tab-item fe-tab-link" id="LuckyCollageCategory">
				<div class="layui-col-md12" style="margin-top:5px">
					<table id="luckycollagecategorytabledata" lay-filter="luckycollagecategorytabledata"></table>
				</div>
			</div>
			<?php endif; ?>
			<!--幸运拼团分类 end-->

			<!--课程分类-->
			<?php if(($auth_data=='all' || in_array('KechengCategory/*',$auth_data))): ?>
			<div class="layui-tab-item fe-tab-link" id="KechengCategory">
				<?php foreach($kclist as $kcKey=>$kc): ?>
				<div>
					<div class="fe-tab-link-line" style='height:60px;line-height:60px;float:left;width:100%;'>
						<div class="fe-tab-link-sub"><a href="javascript:void(0);" onclick="chooseLink(2,'/activity/kecheng/list?bid=<?php echo $bid; ?>&cid=<?php echo $kc['id']; ?>','<?php echo $kc['name']; ?>')">选择</a></div>
						<div style="float:left;"><?php if($kc['pic']): ?><img src="<?php echo $kc['pic']; ?>" style='width:50px;height:50px;padding:1px;border:1px solid #ccc'/><?php endif; ?></div>
						<div class="fe-tab-link-text" style="float:left;height: 40px;">
							<?php if($kc['child']): ?><i class="layui-icon layui-icon-triangle-d" index=<?php echo $kcKey; ?> type="parent" state="block" style="cursor: pointer;"></i><?php endif; ?>
							<?php echo $kc['name']; ?>
						</div>
					</div>
				</div>
				<span class="parent_<?php echo $kcKey; ?>">
					<?php foreach($kc['child'] as $kcKey=>$kcv): ?>
						<div style="width:97%;float:right">
							<div class="fe-tab-link-line" style='height:60px;line-height:60px;float:left;width:100%;'>
								<div class="fe-tab-link-sub"><a href="javascript:void(0);" onclick="chooseLink(100,'/activity/kecheng/list?bid=<?php echo $bid; ?>&cid=<?php echo $kcv['id']; ?>','<?php echo $kcv['name']; ?>')">选择</a></div>
								<div style="float:left;"><?php if($kcv['pic']): ?><img src="<?php echo $kcv['pic']; ?>" style='width:50px;height:50px;padding:1px;border:1px solid #ccc'/><?php endif; ?></div>
								<div class="fe-tab-link-text" style="float:left;height: 40px;">
									<?php if($kcv['child']): ?><i class="layui-icon layui-icon-triangle-d" index=<?php echo $kcKey; ?><?php echo $kcKey; ?> type="child" state="block" style="cursor: pointer;"></i><?php endif; ?>
									<?php echo $kcv['name']; ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</span>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
			<!--课程分类 end-->

			<!--课程列表商品-->
			<?php if(($auth_data=='all' || in_array('KechengList/*',$auth_data))): ?>
				<div class="layui-tab-item fe-tab-link" id="KechengList">
					<div class="layui-form layui-inline">
						<div class="layui-input-inline">
							<input type="text" class="layui-input" style="height:30px;" name="coursename" value="" placeholder=请输入课程标题进行搜索>
						</div>
						<button type="button" class="layui-btn layui-btn-primary layui-btn-sm" lay-submit="" lay-filter="LAY-app-forumreply-search-course">搜索</button>
					</div>
					<div class="layui-col-md12" style="margin-top:5px">
						<table id="coursetable" lay-filter="coursetabledata"></table>
					</div>
				</div>
			<?php endif; ?>
			<!--课程列表商品 end-->

			<!--表单-->
			<?php if(getcustom('form_data')): if($auth_data=='all' || in_array('Form/*',$auth_data)): ?>
				<div class="layui-tab-item fe-tab-link" id="Form">
					<div class="layui-form layui-inline">
						<div class="layui-input-inline">
							<input type="text" class="layui-input" style="height:30px;" name="formname" value="" placeholder=请输入名称进行搜索>
						</div>
						<button type="button" class="layui-btn layui-btn-primary layui-btn-sm" lay-submit="" lay-filter="LAY-app-forumreply-search-form">搜索</button>
					</div>
					<div class="layui-col-md12" style="margin-top:5px">
						<table id="formtabledata" lay-filter="formtabledata"></table>
					</div>
				</div>
				<?php endif; ?>
			<?php endif; ?>
			<!--表单 end-->
		</div>
	</div>
	</div>
</div>
</div>
<script type="text/javascript" src="/static/admin/layui/layui.all.js?v=20210226"></script>
<script type="text/javascript" src="/static/admin/layui/lay/modules/flow.js?v=1"></script>
<script type="text/javascript" src="/static/admin/layui/lay/modules/formSelects-v4.js"></script>
<script type="text/javascript" src="/static/admin/js/jquery-ui.min.js?v=20200228"></script>
<script type="text/javascript" src="/static/admin/ueditor/ueditor.js?v=20220707"></script>
<script type="text/javascript" src="/static/admin/ueditor/135editor.js?v=20200228"></script>
<script type="text/javascript" src="/static/admin/webuploader/webuploader.js?v=2024"></script>
<script type="text/javascript" src="/static/admin/js/qrcode.min.js?v=20200228"></script>
<script type="text/javascript" src="/static/admin/js/dianda.js?v=2022"></script>
<script type="text/javascript" src="/static/admin/js/inputTags.js?v=2026"></script>

<div id="NewsToolBox"></div>
<script type="text/javascript">
		// 解释文字浮层展示
		$('.layui-text-popover').mouseenter(function(){
			let pageHeight = $(window).height() + $(document).scrollTop();
			let bottom = pageHeight - $(this).offset().top
			let topNum = ($(this).offset().top - $(document).scrollTop()).toFixed(2);
			let Height = $(this).find('.layui-textpopover-div').outerHeight();
			$(this).find('.layui-textpopover-div').show()
			let that = this;
			setTimeout(function(){
				if(topNum < (Height/2-15)){
					$(that).find('.layui-textpopover-div').css({'top':-topNum+10+'px','opacity':1,'transition':'opacity .3s'})	
				}else if(bottom < (Height/2-15)){
					$(that).find('.layui-textpopover-div').css({'top': bottom-Height-10 +'px','opacity':1,'transition':'opacity .3s'})	
				}else{
					$(that).find('.layui-textpopover-div').css({'top':-(Height/2-15)+'px','opacity':1,'transition':'opacity .3s'})	
				}
			},100)
		}) 
		$('.layui-text-popover').mouseleave(function(){
			$(this).find('.layui-textpopover-div').css({'opacity':0})	
			$(this).find('.layui-textpopover-div').hide()
		}) 
		$('.layui-textpopover-div').mouseenter(function(){
			$(this).find('.layui-textpopover-div').show()
		})
		$('.layui-textpopover-div').mouseleave(function(){
			$(this).find('.layui-textpopover-div').css({'opacity':0})
			$(this).find('.layui-textpopover-div').hide()
		})
		// 图片浮层展示 示例
		$('.layui-popover').mouseenter(function(){
			let pageHeight = $(window).height() + $(document).scrollTop();
			let bottom = pageHeight - $(this).offset().top
			let topNum = ($(this).offset().top - $(document).scrollTop()).toFixed(2);
			let Height = $(this).find('.layui-popover-div').outerHeight();
			$(this).find('.layui-popover-div').show()
			let that = this;
			setTimeout(function(){
				if(topNum < (Height/2-15)){
					$(that).find('.layui-popover-div').css({'top':-topNum+10+'px','opacity':1,'transition':'opacity .3s'})	
				}else if(bottom < (Height/2-15)){
					$(that).find('.layui-popover-div').css({'top': bottom-Height-10 +'px','opacity':1,'transition':'opacity .3s'})	
				}else{
					$(that).find('.layui-popover-div').css({'top':-(Height/2-15)+'px','opacity':1,'transition':'opacity .3s'})	
				}
			},100)
		}) 
		$('.layui-popover').mouseleave(function(){
			$(this).find('.layui-popover-div').css({'opacity':0})	
			$(this).find('.layui-popover-div').hide()
		}) 
    function copyText(text) {
        var top = document.documentElement.scrollTop;
        var textarea = document.createElement("textarea"); //创建input对象
        var currentFocus = document.activeElement; //当前获得焦点的元素
        var toolBoxwrap = document.getElementById('NewsToolBox'); //将文本框插入到NewsToolBox这个之后
        toolBoxwrap.appendChild(textarea); //添加元素
        textarea.value = text;
        textarea.focus();
        document.documentElement.scrollTop = top;
        if (textarea.setSelectionRange) {
            textarea.setSelectionRange(0, textarea.value.length); //获取光标起始位置到结束位置
        } else {
            textarea.select();
        }
        try {
            var flag = document.execCommand("copy"); //执行复制
        } catch (eo) {
            var flag = false;
        }
        toolBoxwrap.removeChild(textarea); //删除元素
        currentFocus.focus();
        if(flag) layer.msg('复制成功');
        return flag;
    }
		// 查看链接
		function viewLink(path,url=''){
			var pagepath = path;
			if(!url){
				var url = "<?php echo m_url('"+pagepath+"'); ?>"; //拼接 H5 链接
			}
			<?php if(!in_array('mp',$platform)): ?>
				showwxqrcode(pagepath);
				return;
			<?php endif; ?>
			var html = '';
			html+='<div style="margin:20px">';
			html+='	<div style="width:100%;margin:10px 0" id="urlqr"></div>';
			<?php if(in_array('wx',$platform)): ?>
			html+='	<div style="width:100%;text-align:center"><button class="layui-btn layui-btn-sm layui-btn-primary" onclick="showwxqrcode(\''+pagepath+'\')">查看小程序码</button></div>';
			<?php endif; ?>
			html+='	<div style="line-height:25px;"><div><span style="width: 70px;display: inline-block;">链接地址：</span><button class="layui-btn layui-btn-xs layui-btn-primary" onclick="copyText(\''+url+'\')">复制</button></div><div>'+url+'</div></div>';
			html+='	<div style="height:50px;line-height:25px;"><div><span style="width: 70px;display: inline-block;">页面路径：</span><button style="box-sizing: border-box;" class="layui-btn layui-btn-xs layui-btn-primary" onclick="copyText(\'/'+pagepath+'\')">复制</button></div><div>/'+pagepath+'</div></div>';
			html+='</div>';
			layer.open({type:1,'title':'查看链接',area:['500px','430px'],shadeClose:true,'content':html})
			var qrcode = new QRCode('urlqr', {
					text: 'your content',
					width: 200,
					height: 200,
					colorDark : '#000000',
					colorLight : '#ffffff',
					correctLevel : QRCode.CorrectLevel.L
				});
				qrcode.clear();
				qrcode.makeCode(url);
		}
		// 查看小程序码
		function showwxqrcode(pagepath){
			var index = layer.load();
			$.post("<?php echo url('DesignerPage/getwxqrcode'); ?>",{path:pagepath},function(res){
				layer.close(index);
				if(res.status==0){
					layer.open({type:1,area:['300px','350px'],content:'<div style="margin:auto auto;text-align:center"><div style="color:red;width:280px;height:180px;margin-top:100px">'+res.msg+'</div><div style="height:25px;line-height:25px;">'+'/'+pagepath+'</div></div>',title:false,shadeClose:true})
				}else{
					layer.open({type:1,area:['300px','350px'],content:'<div style="margin:auto auto;text-align:center"><img src="'+res.url+'" style="margin-top:20px;max-width:280px;max-height:280px"/><div style="height:25px;line-height:25px;">'+'/'+pagepath+'</div></div>',title:false,shadeClose:true})
				}
			})
		}
</script>
<!-------使用js导出excel文件--------->
<script src="/static/admin/excel/excel.js?v=2024"></script>
<script src="/static/admin/excel/layui_exts/excel.js"></script>
<script>

    var excel = new Excel();
    var excel_name = '<?php echo $excel_name; ?>';
    excel.bind(function (data,title) {
        var excel_field = JSON.parse('<?php echo $excel_field; ?>');
        if(title && title!=undefined){
            //接口返回的title
            var excel_title = title;
        }else{
            //excel_field.php 配置的title
            var excel_title = JSON.parse('<?php echo $excel_title; ?>');
        }
        if(!excel_title || excel_title.length<=0){
            //上面两种都没有title,读取table表格cols中的title，同时filed也更新为table表格cols中的field
            excel_title = [];
            excel_field = [];
            var cols = tableIns.config.cols;
            cols.forEach(function (cols_item, cols_index) {
                console.log(cols_item);
                cols_item.forEach(function (cols_item2, cols_index2) {
                    console.log(cols_item2);
                    if(cols_item2.title){
                        excel_title.push(cols_item2.title)
                        excel_field.push(cols_item2.field)
                    }
                })
            })
        }
        // if(!excel_title || excel_title.length<=0){
        //     layer.msg('未设置标题');
        //     return;
        // }

        // 设置表格内容
        data.forEach(function (item, index) {
            var _data = [];
            excel_title.forEach(function (title, index2) {
                var field = excel_field[index2];
                if(item[field] && item[field]!=undefined){
                    //有filed 匹配field
                    var field_val = item[field];
                    //是整数 长度为10 字段名包含time 判定为时间戳
                    if(parseInt(field_val) == field_val && (field_val.toString()).length==10 && field.includes('time')){
                        field_val = date('Y-m-d H:i:s',field_val);
                    }
                }else{
                    //没有filed 根据顺序来
                    var field_val = item[index2];
                }
                _data.push(field_val);
            })
            data[index] = _data;
        });
        // 设置表头内容
        if(excel_title && excel_title.length>0){
            data.unshift(excel_title);
        }
        // 应用表格样式
        return this.withStyle(data);

    }, excel_name+layui.util.toDateString(Date.now(), '_yyyyMMdd_HHmmss'));

</script>
<script>
layui.form.on('radio(linktype)', function(data){
	$('#linktype-content .layui-form-item').hide();
	$('.layui-form-item.'+data.value).show();
})

layui.form.on('submit(formsubmit)', function(obj){
	var field = obj.field
	var linktype = field.linktype
	var linkval = '';
	var hrefname = '';
	if(!linktype){
			layer.msg('请选择功能');return;
		}
	if(linktype == 'tel'){
		if(field.tel == ''){
			layer.msg('请填写电话号码');return;
		}
		linkval = field.tel
		hrefname = '拨打电话:'+field.tel;
	}
	if(linktype == 'url'){
		if(field.url == ''){
			layer.msg('请填写链接地址');return;
		}
		linkval = field.url
		hrefname = '外部链接:'+field.url;
	}
	if(linktype == 'contact'){
		hrefname = '在线客服';
	}
	if(linktype == 'scan'){
		hrefname = '扫一扫';
	}
	if(linktype == 'share'){
		hrefname = '分享转发';
	}
	if(linktype == 'location'){
		hrefname = '坐标导航';
		linkval = $('input[name=addressname]').val() + '|' + $('input[name=longitude]').val() + ','+$('input[name=latitude]').val();
	}
	if(linktype == 'getmembercard'){
		hrefname = '领取会员卡';
	}
	if(linktype == 'addfavorite'){
		hrefname = '商品收藏';
	}
	if(linktype == 'miniProgram'){
		if(field.appid1 == ''){
			//layer.msg('请填写小程序appid');return;
		}
		console.log(field)
		if(field.username){
			linkval = field.appid1 + '|'+field.path1 + '|'+field.username;
		}else{
			linkval = field.appid1 + '|'+field.path1+'|';
		}
		hrefname = '小程序:'+linkval;
		<?php if(getcustom('tominiprogram_givemoney')): ?>
		var index = layer.load();
		$.post("<?php echo url('addRewardedVideoAd'); ?>",{id:'<?php echo $rwvideoad['id']; ?>',type:1,unitid:field.appid1,givescore:field.mini_givescore,givemoney:field.mini_givemoney,givemoneyparent:field.mini_givemoneyparent,givetimes:field.mini_givetimes,givetimestotal:field.mini_givetimestotal},function(res){
			chooseLink(13,linktype+'::'+linkval+'|'+res.adid,hrefname);
			layer.close(index);
		});
		return;
		<?php endif; if(getcustom('scoreshop_otheradmin_buy')): ?>
			var sendmid = field.sendmid;
			if(sendmid && sendmid == 1){
				linkval += '|sendmid|';
			}
		<?php endif; ?>
	}
	if(linktype =='embeddedMiniProgram'){
		hrefname = '半屏小程序';
		linkval = field.appid2+'|'+field.path2;
	}
	if(linktype =='channelsUserProfile'){
		hrefname = '视频号主页';
		linkval = field.finderUserName;
	}
	if(linktype =='channelsLive'){
		hrefname = '视频号直播';
		linkval = field.finderUserNameLive;
	}
	if(linktype == 'copy'){
		hrefname = '复制';
		linkval = $('input[name=copytext]').val() + '|' + $('input[name=copymsg]').val();
	}
	if(linktype == 'rewardedVideoAd'){
		linkval = field.rad_unitid + '|'+field.rad_givescore + '|'+field.rad_givetimes;
		hrefname = '激励广告:'+field.rad_unitid;
		var index = layer.load();
		$.post("<?php echo url('addRewardedVideoAd'); ?>",{id:'<?php echo $rwvideoad['id']; ?>',type:0,unitid:field.rad_unitid,givescore:field.rad_givescore,givemoney:field.rad_givemoney,givemoneyparent:field.rad_givemoneyparent,givetimes:field.rad_givetimes,givetimestotal:field.rad_givetimestotal,rad_url:field.rad_url},function(res){
			chooseLink(13,linktype+'::'+res.adid,hrefname);
			layer.close(index);
		});
		return;
	}
	if(linktype == 'sendsms'){
		hrefname = '发送短信';
		linkval = field.sendsms_tmpl + '|'+field.sendsms_link;
	}
	if(linktype == 'connectwifi'){
		hrefname = '连接wifi';
		linkval = field.wifi_name + '|'+field.wifi_password;
	}
	if(linktype == 'url'){
		var hrefurl = linkval;
	}else if(linktype == 'tel'){
		var hrefurl = linktype + ':' + linkval;
	}else if(linktype == 'mpbind'){
		hrefname = '公众号绑定';
		var hrefurl = '<?php echo PRE_URL2; ?>/?s=/ApiMpBind/mpbind&aid=<?php echo $aid; ?>';
	}else{
		var hrefurl = linktype + '::' + linkval;
	}
	chooseLink(13,hrefurl,hrefname)
});

	var chooseUrlField = '';
	function chooseUrl2(field){
		chooseUrlField = field;
		layer.open({type:2,shadeClose:true,area:['900px', '550px'],'title':'选择链接',content:"<?php echo url('DesignerPage/chooseurl'); ?>&callback=chooseLink2"});
	}
	function chooseLink2(urlname,url){
		$("#"+chooseUrlField).val(url);
	}
</script>
<script type="text/html" id="productnameTpl">
		<img src="{{d.pic}}" style="max-width:70px;float:left"/>
		<div style="float: left;width:200px;margin-left: 10px;white-space:normal;line-height:20px;">
			<div style="width:100%;">{{# if(d.cid!=0){ }}<span style="color:red">[{{d.cname}}]</span>{{# } }}{{d.name}}</div>
			<div style="padding-top:5px;color:#f60">￥{{d.sell_price}}</div></div>
		</div>
</script>

<script type="text/html" id="coursenameTpl">
	<img src="{{d.pic}}'" style="max-width:70px;float:left"/>
	<div style="float: left;width:200px;margin-left: 10px;white-space:normal;line-height:20px;">
		<div style="width:100%;">{{d.name}}</div>
		<div style="color:#666;font-size:12px">价格￥{{d.price}}</div>
	</div>
</script>
<script type="text/html" id="articlenameTpl">
		<img src="{{d.pic}}" style="max-height:50px;max-width:50px;float:left"/>
		<div style="float: left;width:200px;margin-left: 10px;white-space:normal;line-height:20px;">
			<div style="width:100%;padding-top:5px">{{# if(d.cid>0){ }}<span style="color:red">[{{d.cname}}] </span>{{# } }}{{d.name}}</div>
			<div style="padding-top:5px;font-size:12px">{{d.subname}}</div></div>
		</div>
	</script>
	<script type="text/html" id="kanjianameTpl">
		<img src="{{d.pic}}" style="max-width:70px;float:left"/>
		<div style="float: left;width:200px;margin-left: 10px;white-space:normal;line-height:20px;">
			<div style="width:100%;">{{d.name}}</div>
			<div style="color:#666;font-size:12px">原价: ￥{{d.sell_price}}</div>
			<div style="color:#f60;font-size:12px">最低价: ￥{{d.min_price}}</div>
		</div>
	</script>
	<script type="text/html" id="collagenameTpl">
		<img src="{{d.pic}}" style="max-width:70px;float:left"/>
		<div style="float: left;width:200px;margin-left: 10px;white-space:normal;line-height:20px;">
			<div style="width:100%;">{{# if(d.cid!=0){ }}<span style="color:red">[{{d.cname}}]</span>{{# } }}{{d.name}}</div>
			<div style="padding-top:5px;color:#f60">￥{{d.sell_price}}</div></div>
		</div>
	</script>
	<script type="text/html" id="scoreshopnameTpl">
		<img src="{{d.pic}}" style="max-width:70px;float:left"/>
		<div style="float: left;width:200px;margin-left: 10px;white-space:normal;line-height:20px;">
			<div style="width:100%;">{{d.name}}</div>
			<div style="color:#666;font-size:12px">价值: ￥{{d.sell_price}}</div>
			<div style="color:#f60;font-size:12px">{{d.score_price}}<?php echo t('积分'); ?> {{# if(d.money_price>0){ }}+ {{d.money_price}}元{{# } }}</div>
		</div>
	</script>
	<script type="text/html" id="yuyuenameTpl">
		<img src="{{d.pic}}" style="max-width:70px;float:left"/>
		<div style="float: left;width:200px;margin-left: 10px;white-space:normal;line-height:20px;">
			<div style="width:100%;">{{# if(d.cid!=0){ }}<span style="color:red">[{{d.cname}}]</span>{{# } }}{{d.name}}</div>
			<div style="padding-top:5px;color:#f60">￥{{d.sell_price}}</div></div>
		</div>
	</script>
<script type="text/html" id="restaurant_productnameTpl">
	<img src="{{d.pic}}" style="max-width:70px;float:left"/>
	<div style="float: left;width:200px;margin-left: 10px;white-space:normal;line-height:20px;">
		<div style="width:100%;">{{# if(d.cid!=0){ }}<span style="color:red">[{{d.cname}}]</span>{{# } }}{{d.name}}</div>
		<div style="padding-top:5px;color:#f60">￥{{d.sell_price}}</div></div>
	</div>
</script>
<script type="text/html" id="hotelnameTpl">
	<img src="{{d.pic}}" style="max-width:70px;float:left"/>
	<div style="float: left;width:200px;margin-left: 10px;white-space:normal;line-height:20px;">
		<div style="width:100%;">{{# if(d.cid!=0){ }}<span style="color:red">[{{d.cname}}]</span>{{# } }}{{d.name}}</div>
	</div>
</script>
<script>
  //商品列表
	var productdatawhere = {};
  var tableInsproduct = layui.table.render({
    elem: '#producttabledata'
    ,url: "<?php echo url('shop_product/index'); ?>" //数据接口
    ,page: true //开启分页
		,width:1068
		,height:480
    ,cols: [[ //表头
			//{type:"checkbox"},
      {field: 'id', title: 'ID',  sort: true,width:80},
      {field: 'name', title: '商品信息',templet: '#productnameTpl',width:450},
      //{field: 'ggdata', title: '规格 × 库存'},
      {field: 'createtime', title: '创建时间',sort: true,templet:function(d){ return date('Y-m-d H:i',d.createtime)},width:150},
      {field: 'status', title: '状态',templet:function(d){ return d.status==1?'<span style="color:green">已上架</span>':'<span style="color:red">未上架</span>'},width:80},
      {field: 'operation', title: '操作',templet: '<div><button class="table-btn" onclick="chooseLink(4,\'/pages/shop/product?id={{d.id}}\',\'{{d.name}}\')">选择</button></div>'}
    ]]
  });
	//排序
	layui.table.on('sort(producttabledata)', function(obj){
		productdatawhere.field = obj.field;
		productdatawhere.order = obj.type;
		tableInsproduct.reload({
			initSort: obj,
			where: productdatawhere
		});
	});
	//检索
	layui.form.on('submit(LAY-app-forumreply-search-product)', function(obj){
		var olddatawhere = productdatawhere
		productdatawhere = {name:obj.field.productname}
		productdatawhere.field = olddatawhere.field
		productdatawhere.order = olddatawhere.order
		console.log(productdatawhere)
		tableInsproduct.reload({
			where: productdatawhere,
			page: {curr: 1}
		});
	})

  //拼团商品列表
	var collageproductdatawhere = {};
  var tableInscollageproduct = layui.table.render({
    elem: '#collageproducttabledata'
    ,url: "<?php echo url('collage_product/index'); ?>" //数据接口
    ,page: true //开启分页
		,width:1068
		,height:480
    ,cols: [[ //表头
			//{type:"checkbox"},
      {field: 'id', title: 'ID',  sort: true,width:80},
      {field: 'name', title: '商品信息',templet: '#collagenameTpl',width:450},
      //{field: 'ggdata', title: '规格 × 库存'},
      {field: 'createtime', title: '创建时间',sort: true,templet:function(d){ return date('Y-m-d H:i',d.createtime)},width:150},
      {field: 'status', title: '状态',templet:function(d){ return d.status==1?'<span style="color:green">已上架</span>':'<span style="color:red">未上架</span>'},width:80},
      {field: 'operation', title: '操作',templet: '<div><button class="table-btn" onclick="chooseLink(5,\'/activity/collage/product?id={{d.id}}\',\'{{d.name}}\')">选择</button></div>'}
    ]]
  });
	//排序
	layui.table.on('sort(collageproducttabledata)', function(obj){
		collageproductdatawhere.field = obj.field;
		collageproductdatawhere.order = obj.type;
		tableInscollageproduct.reload({
			initSort: obj,
			where: collageproductdatawhere
		});
	});
	//检索
	layui.form.on('submit(LAY-app-forumreply-search-collageproduct)', function(obj){
		var olddatawhere = collageproductdatawhere
		collageproductdatawhere = {name:obj.field.collageproductname}
		collageproductdatawhere.field = olddatawhere.field
		collageproductdatawhere.order = olddatawhere.order
		console.log(collageproductdatawhere)
		tableInscollageproduct.reload({
			where: collageproductdatawhere,
			page: {curr: 1}
		});
	});


  //周期购商品列表
  var cycleproductdatawhere = {};
  var tableInscycleproduct = layui.table.render({
	  elem: '#cycleproducttabledata'
	  ,url: "<?php echo url('cycle_product/index'); ?>" //数据接口
	  ,page: true //开启分页
	  ,width:1068
	  ,height:480
	  ,cols: [[ //表头
		  //{type:"checkbox"},
		  {field: 'id', title: 'ID',  sort: true,width:80},
		  {field: 'name', title: '商品信息',templet: '#collagenameTpl',width:450},
		  //{field: 'ggdata', title: '规格 × 库存'},
		  {field: 'createtime', title: '创建时间',sort: true,templet:function(d){ return date('Y-m-d H:i',d.createtime)},width:150},
		  {field: 'status', title: '状态',templet:function(d){ return d.status==1?'<span style="color:green">已上架</span>':'<span style="color:red">未上架</span>'},width:80},
		  {field: 'operation', title: '操作',templet: '<div><button class="table-btn" onclick="chooseLink(14,\'/pagesExt/cycle/product?id={{d.id}}\',\'{{d.name}}\')">选择</button></div>'}
	  ]]
  });
  //排序
  layui.table.on('sort(cycleproducttabledata)', function(obj){
	  collageproductdatawhere.field = obj.field;
	  collageproductdatawhere.order = obj.type;
	  tableInscollageproduct.reload({
		  initSort: obj,
		  where: collageproductdatawhere
	  });
  });
  //检索
  layui.form.on('submit(LAY-app-forumreply-search-cycleproduct)', function(obj){
	  var olddatawhere = cycleproductdatawhere
	  cycleproductdatawhere = {name:obj.field.cycleproductname}
	  cycleproductdatawhere.field = olddatawhere.field
	  cycleproductdatawhere.order = olddatawhere.order
	  tableInscycleproduct.reload({
		  where: cycleproductdatawhere,
		  page: {curr: 1}
	  });
  });
	

  //砍价商品列表
	var kanjiaproductdatawhere = {};
  var tableInskanjiaproduct = layui.table.render({
    elem: '#kanjiaproducttabledata'
    ,url: "<?php echo url('kanjia_product/index'); ?>" //数据接口
    ,page: true //开启分页
		,width:1068
		,height:480
    ,cols: [[ //表头
			//{type:"checkbox"},
      {field: 'id', title: 'ID',  sort: true,width:80},
      {field: 'name', title: '商品信息',templet: '#kanjianameTpl',width:350},
      {field: 'sort', title: '活动时间',templet:function(d){ return date('Y-m-d H:i',d.starttime) + ' ~ '+date('Y-m-d H:i',d.endtime)},width:250},
      {field: 'status', title: '状态',templet:function(d){
				if(d.status==0){
					return '<span style="color:red">未上架</span>';
				}else{
					if(d.starttime > <?php echo time(); ?>){
						return '<button class="layui-btn layui-btn-sm" style="background-color:#888">未开始</button>';
					}else if(d.endtime < <?php echo time(); ?>){
						return '<button class="layui-btn layui-btn-sm" style="background-color:#3e5">已结束</button>';
					}else if(d.endtime > <?php echo time(); ?> && d.starttime < <?php echo time(); ?>){
						return '<button class="layui-btn layui-btn-sm" style="background-color:#f55">进行中</button>';
					}
				}
			}},
      {field: 'operation', title: '操作',templet: '<div><button class="table-btn" onclick="chooseLink(6,\'/activity/kanjia/product?id={{d.id}}\',\'{{d.name}}\')">选择</button></div>'}
    ]]
  });
	//排序
	layui.table.on('sort(kanjiaproducttabledata)', function(obj){
		kanjiaproductdatawhere.field = obj.field;
		kanjiaproductdatawhere.order = obj.type;
		tableInskanjiaproduct.reload({
			initSort: obj,
			where: kanjiaproductdatawhere
		});
	});
	//检索
	layui.form.on('submit(LAY-app-forumreply-search-kanjiaproduct)', function(obj){
		var olddatawhere = kanjiaproductdatawhere
		kanjiaproductdatawhere = {name:obj.field.kanjiaproductname}
		kanjiaproductdatawhere.field = olddatawhere.field
		kanjiaproductdatawhere.order = olddatawhere.order
		console.log(kanjiaproductdatawhere)
		tableInskanjiaproduct.reload({
			where: kanjiaproductdatawhere,
			page: {curr: 1}
		});
	});

	//积分商城商品列表
	var scoreshopproductdatawhere = {};
  var tableInsscoreshopproduct = layui.table.render({
    elem: '#scoreshopproducttabledata'
    ,url: "<?php echo url('scoreshop_product/index'); ?>" //数据接口
    ,page: true //开启分页
		,width:1068
		,height:480
    ,cols: [[ //表头
			//{type:"checkbox"},
      {field: 'id', title: 'ID',  sort: true,width:80},
      {field: 'name', title: '商品信息',templet: '#scoreshopnameTpl',width:350},
      {field: 'stock', title: '库存'},
      {field: 'sales', title: '销量',sort: true},
      {field: 'sort', title: '序号',sort: true},
      {field: 'status', title: '状态',templet:function(d){
				if(d.status==0){
					return '<span style="color:red">未上架</span>';
				}else{
					return '<span style="color:green">已上架</span>';
				}
			}},
      {field: 'operation', title: '操作',templet: '<div><button class="table-btn" onclick="chooseLink(7,\'/activity/scoreshop/product?id={{d.id}}\',\'{{d.name}}\')">选择</button></div>'}
    ]]
  });
	//排序
	layui.table.on('sort(scoreshopproducttabledata)', function(obj){
		scoreshopproductdatawhere.field = obj.field;
		scoreshopproductdatawhere.order = obj.type;
		tableInsscoreshopproduct.reload({
			initSort: obj,
			where: scoreshopproductdatawhere
		});
	});
	//检索
	layui.form.on('submit(LAY-app-forumreply-search-scoreshopproduct)', function(obj){
		var olddatawhere = scoreshopproductdatawhere
		scoreshopproductdatawhere = {name:obj.field.scoreshopproductname}
		scoreshopproductdatawhere.field = olddatawhere.field
		scoreshopproductdatawhere.order = olddatawhere.order
		console.log(scoreshopproductdatawhere)
		tableInsscoreshopproduct.reload({
			where: scoreshopproductdatawhere,
			page: {curr: 1}
		});
	});

	//团购商品列表
	var tuangouproductdatawhere = {};
  var tableInstuangouproduct = layui.table.render({
    elem: '#tuangouproducttabledata'
    ,url: "<?php echo url('tuangou_product/index'); ?>" //数据接口
    ,page: true //开启分页
		,width:1068
		,height:480
    ,cols: [[ //表头
			//{type:"checkbox"},
      {field: 'id', title: 'ID',  sort: true,width:80},
      {field: 'name', title: '商品信息',templet: '#collagenameTpl',width:450},
      //{field: 'ggdata', title: '规格 × 库存'},
      {field: 'createtime', title: '创建时间',sort: true,templet:function(d){ return date('Y-m-d H:i',d.createtime)},width:150},
      {field: 'status', title: '状态',templet:function(d){ return d.status==1?'<span style="color:green">已上架</span>':'<span style="color:red">未上架</span>'},width:80},
      {field: 'operation', title: '操作',templet: '<div><button class="table-btn" onclick="chooseLink(5,\'/activity/tuangou/product?id={{d.id}}\',\'{{d.name}}\')">选择</button></div>'}
    ]]
  });
	//排序
	layui.table.on('sort(tuangouproducttabledata)', function(obj){
		tuangouproductdatawhere.field = obj.field;
		tuangouproductdatawhere.order = obj.type;
		tableInstuangouproduct.reload({
			initSort: obj,
			where: tuangouproductdatawhere
		});
	});
	//检索
	layui.form.on('submit(LAY-app-forumreply-search-tuangouproduct)', function(obj){
		var olddatawhere = tuangouproductdatawhere
		tuangouproductdatawhere = {name:obj.field.tuangouproductname}
		tuangouproductdatawhere.field = olddatawhere.field
		tuangouproductdatawhere.order = olddatawhere.order
		console.log(tuangouproductdatawhere)
		tableInstuangouproduct.reload({
			where: tuangouproductdatawhere,
			page: {curr: 1}
		});
	});
	//幸运拼团商品列表
	var luckycollageproductdatawhere = {};
  var tableInsluckycollageproduct = layui.table.render({
    elem: '#luckycollageproducttabledata'
    ,url: "<?php echo url('LuckyCollageProduct/index'); ?>" //数据接口
    ,page: true //开启分页
		,width:1068
		,height:480
    ,cols: [[ //表头
			//{type:"checkbox"},
      {field: 'id', title: 'ID',  sort: true,width:80},
      {field: 'name', title: '商品信息',templet: '#collagenameTpl',width:450},
      //{field: 'ggdata', title: '规格 × 库存'},
      {field: 'createtime', title: '创建时间',sort: true,templet:function(d){ return date('Y-m-d H:i',d.createtime)},width:150},
      {field: 'status', title: '状态',templet:function(d){ return d.status==1?'<span style="color:green">已上架</span>':'<span style="color:red">未上架</span>'},width:80},
      {field: 'operation', title: '操作',templet: '<div><button class="table-btn" onclick="chooseLink(5,\'/activity/luckycollage/product?id={{d.id}}\',\'{{d.name}}\')">选择</button></div>'}
    ]]
  });
	//排序
	layui.table.on('sort(luckycollageproducttabledata)', function(obj){
		luckycollageproductdatawhere.field = obj.field;
		luckycollageproductdatawhere.order = obj.type;
		tableInsluckycollageproduct.reload({
			initSort: obj,
			where: luckycollageproductdatawhere
		});
	});
	//检索
	layui.form.on('submit(LAY-app-forumreply-search-luckycollageproduct)', function(obj){
		var olddatawhere = luckycollageproductdatawhere
		luckycollageproductdatawhere = {name:obj.field.luckycollageproductname}
		luckycollageproductdatawhere.field = olddatawhere.field
		luckycollageproductdatawhere.order = olddatawhere.order
		console.log(luckycollageproductdatawhere)
		tableInsluckycollageproduct.reload({
			where: luckycollageproductdatawhere,
			page: {curr: 1}
		});
	});

	//幸运拼团分类
	var luckycollagecategorydatawhere = {};
  	var tableInsluckycollagecategory = layui.table.render({
		elem: '#luckycollagecategorytabledata'
		,url: "<?php echo url('LuckyCollageCategory/index'); ?>" //数据接口
		,page: true //开启分页
			,width:1068
			,height:480
		,cols: [[ //表头
			//{type:"checkbox"},
		  {field: 'id', title: 'ID',  sort: true,width:80},
		  {field: 'name', title: '名称',width:450},
		  //{field: 'ggdata', title: '规格 × 库存'},
		  {field: 'sort', title: '序号'},
		  {field: 'status', title: '状态',templet:function(d){ return d.status==1?'<span style="color:green">显示</span>':'<span style="color:red">隐藏</span>'},width:80},
		  {field: 'operation', title: '操作',templet: '<div><button class="table-btn" onclick="chooseLink(5,\'/activity/luckycollage/classify?cid={{d.id}}\',\'{{d.name}}\')">商品分类页</button><button class="table-btn" onclick="chooseLink(5,\'/activity/luckycollage/prolist?cid={{d.id}}\',\'{{d.name}}\')">商品列表页</button></div>'}
		]]
  	});

	<?php if(getcustom('goods_hexiao')): ?>
		//外卖商品列表
		var productdatawhere = {};
		var tableInsproduct = layui.table.render({
			elem: '#restaurant_producttabledata'
			,url: "<?php echo url('RestaurantProduct/index'); ?>" //数据接口
			,page: true //开启分页
			,width:1068
			,height:480
			,cols: [[ //表头
				//{type:"checkbox"},
				{field: 'id', title: 'ID',  sort: true,width:80},
				{field: 'name', title: '商品信息',templet: '#restaurant_productnameTpl',width:450},
				{field: 'stock', title: '库存'},
				{field: 'createtime', title: '创建时间',sort: true,templet:function(d){ return date('Y-m-d H:i',d.createtime)},width:150},
				{field: 'status', title: '状态',templet:function(d){ return d.status==1?'<span style="color:green">已上架</span>':'<span style="color:red">未上架</span>'},width:80},
				{field: 'operation', title: '操作',templet: '<div><button class="table-btn" onclick="chooseLink(4,\'/restaurant/takeaway/product?id={{d.id}}\',\'{{d.name}}\')">选择</button></div>'}
			]]
		});
		//排序
		layui.table.on('sort(restaurant_producttabledata)', function(obj){
			productdatawhere.field = obj.field;
			productdatawhere.order = obj.type;
			tableInsproduct.reload({
				initSort: obj,
				where: productdatawhere
			});
		});
		//检索
		layui.form.on('submit(LAY-app-forumreply-search-restaurant_product)', function(obj){
			var olddatawhere = productdatawhere
			productdatawhere = {name:obj.field.productname}
			productdatawhere.field = olddatawhere.field
			productdatawhere.order = olddatawhere.order
			console.log(productdatawhere)
			tableInsproduct.reload({
				where: productdatawhere,
				page: {curr: 1}
			});
		})
	<?php endif; ?>
	
  //设计页面列表
	var designerpagedatawhere = {};
  var tableInsdesignerpage = layui.table.render({
    elem: '#designerpagetabledata'
    ,url: "<?php echo url('DesignerPage/index'); ?>/ishome/0" //数据接口
    ,page: true //开启分页
		,width:1068
		,height:480
    ,cols: [[ //表头
			//{type:"checkbox"},
      {field: 'id', title: 'ID',  sort: true,width:80},
      {field: 'name', title: '页面名称',templet: '<div>{{# if(d.ishome==1){ }}<i class="fa fa-home" title="小程序首页" style="color:#fb6b5b;font-size:20px"></i> {{# } }}{{d.name}}</div>',width:403},
      //{field: 'readcount', title: '页面链接',templet: '<div>/pages/main/main?PageId={{d.id}}</div>'},
      //{field: 'sort', title: '序号',width:80,sort: true},
      //{field: 'createtime', title: '创建时间',sort: true,templet:function(d){ return date('Y-m-d H:i',d.createtime)},width:150},
      {field: 'updatetime', title: '最后修改时间',sort: true,templet:function(d){ return date('Y-m-d H:i',d.updatetime)},width:200},
      {field: 'operation', title: '操作',templet: function(d){
				var html = '';
				<?php if(input('param.sharegive')==1): ?>
				html += '<button class="table-btn" onclick="chooseLink(12,'+d.id+',\''+d.name+'\')">选择</button>';
				<?php else: ?>
				html += '<button class="table-btn" onclick="chooseLink(12,\'/pages/index/main?id='+d.id+'\',\''+d.name+'\')">选择</button>';
				<?php endif; if(input('param.fromdsn')==1): ?>
				html += '<button class="table-btn" onclick="chooseLink(12,\'/pages/index/main?id='+d.id+'&reloadthispage=1\',\''+d.name+'\')">当前页加载</button>';
				<?php endif; ?>
				return html;
      }}
    ]]
  });
	//排序
	layui.table.on('sort(designerpagetabledata)', function(obj){
		designerpagedatawhere.field = obj.field;
		designerpagedatawhere.order = obj.type;
		tableInsdesignerpage.reload({
			initSort: obj,
			where: designerpagedatawhere
		});
	});
	//检索
	layui.form.on('submit(LAY-app-forumreply-search-designerpage)', function(obj){
		//var field = obj.field
		var olddatawhere = designerpagedatawhere
		designerpagedatawhere = {name:obj.field.designerpagename}
		designerpagedatawhere.field = olddatawhere.field
		designerpagedatawhere.order = olddatawhere.order
		console.log(obj.field)
		if(obj.field.designerpagecid){
			designerpagedatawhere.cid = obj.field.designerpagecid
		}
		if(obj.field.designerpageremark){
			designerpagedatawhere.remark = obj.field.designerpageremark
		}
		tableInsdesignerpage.reload({
			where: designerpagedatawhere,
			page: {curr: 1}
		});
	})

//课程列表
		var coursedatawhere = {};
		var tableInscourse = layui.table.render({
			elem: '#coursetable'
			,url: "<?php echo url('kechengList/index'); ?>/chaptertype/all" //数据接口
			,page: true //开启分页
			,width:1068
			,height:480
			,cols: [[ //表头
				//{type:"checkbox"},
				{field: 'id', title: 'ID',  sort: true,width:80},
				{field: 'name', title: '课程信息',templet: '#coursenameTpl'},
				{field: 'createtime', title: '创建时间',sort: true,templet:function(d){ return date('Y-m-d H:i',d.createtime)},width:200},
				{field: 'status', title: '状态',templet:function(d){ return d.status==1?'<span style="color:green">已上架</span>':'<span style="color:red">未上架</span>'},width:150},
				{field: 'operation', title: '操作',templet:function(d){ 
					var html = '';
					if(d.chaptertype && d.chaptertype == 2){
						html += '<div><button class="table-btn" onclick="chooseLink(101,\'/pagesB/kecheng/lecturermldetail?kcid='+d.id+'\',\''+d.name+'\')">选择</button></div>'
					}else{
						html += '<div><button class="table-btn" onclick="chooseLink(101,\'/activity/kecheng/product?id='+d.id+'\',\''+d.name+'\')">选择</button></div>'
					}
					return html;
				},width:100}
			]]
		});
		//排序
		layui.table.on('sort(coursetabledata)', function(obj){
			coursedatawhere.field = obj.field;
			coursedatawhere.order = obj.type;
			tableInscourse.reload({
				initSort: obj,
				where: coursedatawhere
			});
		});
		//检索
		layui.form.on('submit(LAY-app-forumreply-search-course)', function(obj){
			var field = obj.field
			var olddatawhere = coursedatawhere
			coursedatawhere = {name:obj.field.coursename}
			coursedatawhere.field = olddatawhere.field
			coursedatawhere.order = olddatawhere.order
			tableInscourse.reload({
				where: coursedatawhere,
				page: {curr: 1}
			});
		})

  //文章列表
	var articledatawhere = {};
  var tableInsarticle = layui.table.render({
    elem: '#articletabledata'
    ,url: "<?php echo url('article/index'); ?>" //数据接口
    ,page: true //开启分页
		,width:1068
		,height:480
    ,cols: [[ //表头
			//{type:"checkbox"},
      {field: 'id', title: 'ID',  sort: true,width:80},
      {field: 'name', title: '文章信息',templet: '#articlenameTpl'},
      {field: 'createtime', title: '创建时间',sort: true,templet:function(d){ return date('Y-m-d H:i',d.createtime)},width:200},
      //{field: 'sendtime', title: '发布时间',sort: true,templet:function(d){ return date('Y-m-d H:i',d.sendtime)},width:120},
      {field: 'status', title: '状态',templet:function(d){ return d.status==1?'<span style="color:green">已发布</span>':'<span style="color:red">未发布</span>'},width:150},
      {field: 'operation', title: '操作',templet: '<div><button class="table-btn" onclick="chooseLink(9,\'/pagesExt/article/detail?id={{d.id}}\',\'{{d.name}}\')">选择</button></div>',width:100}
    ]]
  });
	//排序
	layui.table.on('sort(articletabledata)', function(obj){
		articledatawhere.field = obj.field;
		articledatawhere.order = obj.type;
		tableInsarticle.reload({
			initSort: obj,
			where: articledatawhere
		});
	});
	//检索
	layui.form.on('submit(LAY-app-forumreply-search-article)', function(obj){
		var field = obj.field
		var olddatawhere = articledatawhere
		articledatawhere = {name:obj.field.articlename}
		articledatawhere.field = olddatawhere.field
		articledatawhere.order = olddatawhere.order
		tableInsarticle.reload({
			where: articledatawhere,
			page: {curr: 1}
		});
	})
	//商户列表
	var businessdatawhere = {};
  var tableInsbusiness = layui.table.render({
    elem: '#businesstabledata'
    ,url: "<?php echo url('business/index'); ?>" //数据接口
    ,page: true //开启分页
		,width:1068
		,height:480
    ,cols: [[ //表头
			//{type:"checkbox"},
      {field: 'id', title: 'ID',  sort: true,width:80},
      {field: 'name', title: '商户名称'},
      {field: 'pic', title: '商户图片',templet:function(d){
				return '<img src="'+d.logo+'" style="width:60px;height:60px"/>'
      }},
      {field: 'createtime', title: '创建时间',sort: true,templet:function(d){ return date('Y-m-d H:i',d.createtime)},width:200},
      //{field: 'sendtime', title: '发布时间',sort: true,templet:function(d){ return date('Y-m-d H:i',d.sendtime)},width:120},
      {field: 'status', title: '状态',width:150,templet:function(d){
				if(d.status==1){
					return '<span style="color:green">已通过</span>'
				}else if(d.status==2){
					return '<span style="color:red">未通过</span>'
				}else{
					return '<span>审核中</span>'
				}
			}},
      {field: 'operation', title: '操作',templet: '<div><button class="table-btn" onclick="chooseLink(9,\'/pagesExt/business/index?id={{d.id}}\',\'{{d.name}}\')">选择</button></div>',width:100}
    ]]
  });
	//排序
	layui.table.on('sort(businesstabledata)', function(obj){
		businessdatawhere.field = obj.field;
		businessdatawhere.order = obj.type;
		tableInsbusiness.reload({
			initSort: obj,
			where: businessdatawhere
		});
	});
	//检索
	layui.form.on('submit(LAY-app-forumreply-search-business)', function(obj){
		var field = obj.field
		var olddatawhere = businessdatawhere
		businessdatawhere = {name:obj.field.businessname}
		businessdatawhere.field = olddatawhere.field
		businessdatawhere.order = olddatawhere.order
		tableInsbusiness.reload({
			where: businessdatawhere,
			page: {curr: 1}
		});
	})


 <?php if(getcustom('huodong_baoming')): ?>
	
  //酒店列表
  var huodongtabledatawhere = {};
  var tableInshuodong = layui.table.render({
	elem: '#huodongtabledata'
	,url: "<?php echo url('HuodongBaomingList/index'); ?>" //数据接口
	,page: true //开启分页
		,width:1068
		,height:480
	,cols: [[ //表头
			//{type:"checkbox"},
	  {field: 'id', title: 'ID',  sort: true,width:80},
	  {field: 'name', title: "活动名称",templet: '#huodongnameTpl',width:450},
	  //{field: 'ggdata', title: '规格 × 库存'},
	  {field: 'createtime', title: '创建时间',sort: true,templet:function(d){ return date('Y-m-d H:i',d.createtime)},width:150},
	  {field: 'status', title: '状态',templet:function(d){ return d.status==1?'<span style="color:green">已上架</span>':'<span style="color:red">未上架</span>'},width:80},
	  {field: 'operation', title: '操作',templet: '<div><button class="table-btn" onclick="chooseLink(5,\'/pagesB/huodongbaoming/product?id={{d.id}}\',\'{{d.name}}\')">选择</button></div>'}
	]]
  });
	//排序
	layui.table.on('sort(huodongtabledata)', function(obj){
		huodongtabledatawhere.field = obj.field;
		huodongtabledatawhere.order = obj.type;
		tableInshuodong.reload({
			initSort: obj,
			where: huodongtabledatawhere
		});
	});
	//检索
	layui.form.on('submit(LAY-app-forumreply-search-huodong)', function(obj){
		var olddatawhere = huodongtabledatawhere
		huodongtabledatawhere = {name:obj.field.hotelname}
		huodongtabledatawhere.field = olddatawhere.field
		huodongtabledatawhere.order = olddatawhere.order
		console.log(huodongtabledatawhere)
		tableInshuodong.reload({
			where: huodongtabledatawhere,
			page: {curr: 1}
		});
	});

	<?php endif; if(getcustom('hotel')): ?>
	
  //酒店列表
  var hoteltabledatawhere = {};
  var tableInshotel = layui.table.render({
	elem: '#hoteltabledata'
	,url: "<?php echo url('Hotel/getlist'); ?>" //数据接口
	,page: true //开启分页
		,width:1068
		,height:480
	,cols: [[ //表头
			//{type:"checkbox"},
	  {field: 'id', title: 'ID',  sort: true,width:80},
	  {field: 'name', title: "<?php echo $text['酒店']; ?>名称",templet: '#hotelnameTpl',width:450},
	  //{field: 'ggdata', title: '规格 × 库存'},
	  {field: 'createtime', title: '创建时间',sort: true,templet:function(d){ return date('Y-m-d H:i',d.createtime)},width:150},
	  {field: 'status', title: '状态',templet:function(d){ return d.status==1?'<span style="color:green">已上架</span>':'<span style="color:red">未上架</span>'},width:80},
	  {field: 'operation', title: '操作',templet: '<div><button class="table-btn" onclick="chooseLink(5,\'/hotel/index/hoteldetails?id={{d.id}}\',\'{{d.name}}\')">选择</button></div>'}
	]]
  });
	//排序
	layui.table.on('sort(hoteltabledata)', function(obj){
		hoteltabledatawhere.field = obj.field;
		hoteltabledatawhere.order = obj.type;
		tableInshotel.reload({
			initSort: obj,
			where: hoteltabledatawhere
		});
	});
	//检索
	layui.form.on('submit(LAY-app-forumreply-search-hotel)', function(obj){
		var olddatawhere = hoteltabledatawhere
		hoteltabledatawhere = {name:obj.field.hotelname}
		hoteltabledatawhere.field = olddatawhere.field
		hoteltabledatawhere.order = olddatawhere.order
		console.log(hoteltabledatawhere)
		tableInshotel.reload({
			where: hoteltabledatawhere,
			page: {curr: 1}
		});
	});

	<?php endif; if(getcustom('form_data')): ?>
	//表单列表
	var formdatawhere = {};
  var tableInsform = layui.table.render({
    elem: '#formtabledata'
    ,url: "<?php echo url('form/index'); ?>/isopen/1" //数据接口
    ,page: true //开启分页
		,width:1068
		,height:480
    ,cols: [[ //表头
      {field: 'id', title: 'ID',  sort: true,width:80},
      {field: 'name', title: '名称',templet: '#formnameTpl',width:450},
      {field: 'ctime', title: '有效时间',templet: '<div>{{d.starttime}} ~ {{d.endtime}}</div>'},
      {field: 'createtime', title: '创建时间',sort: true,templet:function(d){ return date('Y-m-d H:i',d.createtime)},width:150},
      {field: 'status', title: '状态',width:100},
      {field: 'operation', title: '操作',templet: '<div><button class="table-btn" onclick="chooseLink(4,\'/pagesA/formdata/index?id={{d.id}}\',\'{{d.name}}\')">选择</button></div>'}
    ]]
  });
	//排序
	layui.table.on('sort(formtabledata)', function(obj){
		formdatawhere.field = obj.field;
		formdatawhere.order = obj.type;
		tableInsform.reload({
			initSort: obj,
			where: formdatawhere
		});
	});
	//检索
	layui.form.on('submit(LAY-app-forumreply-search-form)', function(obj){
		var olddatawhere = formdatawhere
		formdatawhere = {name:obj.field.formname}
		formdatawhere.field = olddatawhere.field
		formdatawhere.order = olddatawhere.order
		console.log(formdatawhere)
		tableInsform.reload({
			where: formdatawhere,
			page: {curr: 1}
		});
	})
	<?php endif; ?>

	$(".layui-icon").click(function(){
		let type = $(this).attr('type');
		let state = $(this).attr('state');
		let index = $(this).attr('index');
		if(type=='parent'){
			if(state=='block'){
				$(".parent_" + index).hide();
			}
			if(state=='hide'){
				$(".parent_" + index).show();
			}
		}
		if(type=='child'){
			if(state=='block'){
				$(".child_" + index).hide();
			}
			if(state=='hide'){
				$(".child_" + index).show();
			}
		}
		if(state=='block'){
			$(this).removeClass("layui-icon-triangle-d")
			$(this).addClass("layui-icon-triangle-r");
			$(this).attr('state','hide');
		}
		if(state=='hide'){
			$(this).addClass("layui-icon-triangle-d")
			$(this).removeClass("layui-icon-triangle-r");
			$(this).attr('state','block');
		}
	})
	
	function chooseLinkCategory(type,cid,hrefname){
		var html = '';
		html+='<div style="margin:auto auto;text-align:center">';
		html+='	<div style="display:flex;justify-content:center;margin-top:40px;color:#333;font-size:16px;">';
		html+='		<a style="margin:0 10px;width:100px;height:50px;line-height:50px;background:#fff;border:1px solid #e7e7eb;border-radius:5px;text-align:center;position:relative;cursor:pointer" onclick="chooseLink(\''+type+'\',\'/pages/shop/prolist?cid='+cid+'\',\''+hrefname+'\')">商品列表</a>';
		html+='		<a style="margin:0 10px;width:100px;height:50px;line-height:50px;background:#fff;border:1px solid #e7e7eb;border-radius:5px;text-align:center;position:relative;cursor:pointer" onclick="chooseLink(\''+type+'\',\'/pages/shop/classify?cid='+cid+'\',\''+hrefname+'\')">分类商品</a>';
		html+='		<div style="margin:0 10px;width:100px;height:50px;line-height:50px;background:#fff;border:1px solid #e7e7eb;border-radius:5px;text-align:center;position:relative;cursor:pointer" onclick="chooseLink(\''+type+'\',\'/pagesB/shop/classify2?cid='+cid+'\',\''+hrefname+'\')">分类商品2</div>';
		html+='		<div style="margin:0 10px;width:100px;height:50px;line-height:50px;background:#fff;border:1px solid #e7e7eb;border-radius:5px;text-align:center;position:relative;cursor:pointer" onclick="chooseLink(\''+type+'\',\'/pages/shop/fastbuy?cid='+cid+'\',\''+hrefname+'\')">快速购买</div>';
		html+='		<div style="margin:0 10px;width:100px;height:50px;line-height:50px;background:#fff;border:1px solid #e7e7eb;border-radius:5px;text-align:center;position:relative;cursor:pointer" onclick="chooseLink(\''+type+'\',\'/pages/shop/fastbuy2?cid='+cid+'\',\''+hrefname+'\')">快速购买2</div>';
		html+='	</div>'
		html+='</div>'
		layer.open({type:1,area:['600px','220px'],content:html,title:'请选择链接',shadeClose:true})
	}
	function chooseLink(type,hrefurl,hrefname){
		<?php if(app('request')->param('type')=='geturl'): ?>
			var pagepath = hrefurl.substr(1);
		
			url = '<?php echo PRE_URL2; ?>/h5/<?php echo $aid; ?>.html#/'+pagepath;
			var html = '';
			html+='<div style="margin:20px">';
			html+='	<div style="width:100%;margin:10px 0" id="urlqr"></div>';
			html+='	<div style="width:100%;text-align:center">' +
				<?php if(in_array('wx',$platform)): ?>
				'<button class="layui-btn layui-btn-sm layui-btn-primary" onclick="showwxqrcode(\''+pagepath+'\')">查看微信小程序码</button>' +
				<?php endif; if(getcustom('alipay_qrcode') && in_array('alipay',$platform)): ?>
				'<button class="layui-btn layui-btn-sm layui-btn-primary" onclick="showalipayqrcode(\''+pagepath+'\')">查看支付宝小程序码</button>' +
				<?php endif; ?>
				'</div>';

			html+='	<div style="margin-top:30px;height:50px;line-height:25px;">链接地址：<button class="layui-btn layui-btn-xs layui-btn-primary" onclick="copyText(\''+url+'\')">复制</button><br>'+url+'</div>';
			html+='	<div style="height:50px;line-height:25px;">页面路径：<button class="layui-btn layui-btn-xs layui-btn-primary" onclick="copyText(\'/'+pagepath+'\')">复制</button><br>/'+pagepath+'</div>';
			html+='</div>'
			layer.open({type:1,'title':'链接地址',area:['650px','480px'],shadeClose:true,'content':html})
			var qrcode = new QRCode('urlqr', {
				text: 'your content',
				width: 200,
				height: 200,
				colorDark : '#000000',
				colorLight : '#ffffff',
				correctLevel : QRCode.CorrectLevel.L
			});
			qrcode.clear();
			qrcode.makeCode(url);
		<?php else: ?>
			var urlname = $('#fe-tab-link-nav-' + type).text() + '>' + hrefname
			<?php if(input('param.callback')): ?>
			window.parent.<?php echo app('request')->param('callback'); ?>(urlname,hrefurl,"<?php echo app('request')->param('args'); ?>");
			<?php else: ?>
			window.parent.chooseLink(urlname,hrefurl,"<?php echo app('request')->param('args'); ?>");
			<?php endif; ?>
			closeself();
		<?php endif; ?>
	}
	function showwxqrcode(pagepath){
		var index = layer.load();
		$.post("<?php echo url('getwxqrcode'); ?>",{path:pagepath},function(res){
			layer.close(index);
			if(res.status==0){
				//dialog(res.msg);
				layer.open({type:1,area:['300px','350px'],content:'<div style="margin:auto auto;text-align:center"><div style="color:red;width:280px;height:180px;margin-top:100px">'+res.msg+'</div><div style="height:25px;line-height:25px;">'+pagepath+'</div></div>',title:false,shadeClose:true})
			}else{
				layer.open({type:1,area:['300px','350px'],content:'<div style="margin:auto auto;text-align:center"><img src="'+res.url+'" style="margin-top:20px;max-width:280px;max-height:280px"/><div style="height:25px;line-height:25px;">'+pagepath+'</div></div>',title:false,shadeClose:true})
			}
		})
	}
	<?php if(getcustom('alipay_qrcode')): ?>
	function showalipayqrcode(pagepath){
		var index = layer.load();
		$.post("<?php echo url('getalipayqrcode'); ?>",{path:pagepath},function(res){
			layer.close(index);
			if(res.status==0){
				//dialog(res.msg);
				layer.open({type:1,area:['300px','350px'],content:'<div style="margin:auto auto;text-align:center"><div style="color:red;width:280px;height:180px;margin-top:100px">'+res.msg+'</div><div style="height:25px;line-height:25px;">'+pagepath+'</div></div>',title:false,shadeClose:true})
			}else{
				layer.open({type:1,area:['300px','360px'],content:'<div style="margin:auto auto;text-align:center"><img src="'+res.url+'" style="margin-top:20px;max-width:280px;max-height:280px"/><div style="height:25px;line-height:25px;margin-top: 10px">'+pagepath+'</div></div>',title:false,shadeClose:true})
			}
		})
	}
	<?php endif; ?>
	function copyText(text) {
			var textarea = document.createElement("textarea"); //创建input对象
			var currentFocus = document.activeElement; //当前获得焦点的元素
			var toolBoxwrap = document.getElementById('NewsToolBox'); //将文本框插入到NewsToolBox这个之后
			toolBoxwrap.appendChild(textarea); //添加元素
			textarea.value = text;
			textarea.focus();
			if (textarea.setSelectionRange) {
					textarea.setSelectionRange(0, textarea.value.length); //获取光标起始位置到结束位置
			} else {
					textarea.select();
			}
			try {
					var flag = document.execCommand("copy"); //执行复制
			} catch (eo) {
					var flag = false;
			}
			toolBoxwrap.removeChild(textarea); //删除元素
			currentFocus.focus();
			if(flag) layer.msg('复制成功');
			return flag;
	}

	function choosezuobiao(address,jd,wd){
		var address = $('input[name=address]').val();
		var jd = $('input[name=longitude]').val();
		var wd = $('input[name=latitude]').val();
		if(!jd){
			jd = '116.397827';
			wd = '39.90374';
		}
		var choosezblayer = layer.open({type:2,shadeClose: true,area: ['800px', '560px'],'title': '选择坐标',content: "<?php echo url('DesignerPage/choosezuobiao'); ?>/Mid/1&address="+(address?address:'')+'&jd='+(jd?jd:'')+'&wd='+(wd?wd:''),btn:['确定','取消'],yes:function(index, layero){
				var longitude = layero.find('iframe').contents().find('#mapjd').val();
				var latitude = layero.find('iframe').contents().find('#mapwd').val();
				$('input[name=longitude]').val(longitude);
				$('input[name=latitude]').val(latitude);
				layer.close(choosezblayer);
			}
		});
	}
</script>
<div id="NewsToolBox"></div>
<!-- <script src="/static/admin/js/clipboard.min.js"></script> -->
</body>
</html>