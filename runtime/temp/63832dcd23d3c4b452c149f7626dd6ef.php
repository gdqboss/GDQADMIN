<?php /*a:4:{s:54:"/www/wwwroot/gdqshop.cn/app/view/backstage/sysset.html";i:1747926690;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;s:54:"/www/wwwroot/gdqshop.cn/app/view/public/copyright.html";i:1747926690;}*/ ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>系统设置</title>
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
		.checkbox-text { display: inline-block;vertical-align: middle;}
		.commission_frozen .layui-form-checkbox {float: left;}
		.commission_frozen_row {overflow: hidden;margin-bottom: 5px;}
		/*加载图标预览*/
		.ldPicList{display: flex;}
		.ldPicList .layui-imgbox{width: 20px;height: 20px;position: relative;left: 150px;top: -2px}
		.loadingpic-box{display: flex;flex-direction: column;align-items: center;margin-right: 20px;width: 120px;padding: 10px;border-radius: 4px;background: #f4f4f4}
		.loadingpic-box.active{background: #38ada2}
		.loading-img{background: #FFF;border: 1px solid #e2e2e2;display: flex;justify-content: center;width: 90px;height: 90px;border-radius: 2px}
		.loading-img img{max-height: 100%;max-width: 100%}
		.loading-preview{background: #FFF;width: 120px;height: 120px;
			display: flex;align-items: center;justify-content: center;border: 1px solid #e2e2e2;margin-top: 5px;border-radius: 4px}
		.loading-preview img{height: 80px;width: 80px;}
		.loading-preview .animation0{animation: loading 1.5s linear 0s infinite;}
		.loading-preview .animation1{animation: loading 1.3s ease 0s infinite;}
		@keyframes loading {0% {transform: rotate(0);}100% {transform: rotate(360deg);}}
		@-webkit-keyframes loading {0% {transform: rotate(0);}100% {transform: rotate(360deg);}}
		.layui-imgbox .layui-icon{font-size:25px;}
		.layui-imgbox-close{width: 25px;height: 22px;right: -18px;top:-10px;z-index: 3;}
		.withdraw-custom{position: relative;display:flex;height: 30px;line-height: 30px;margin-top: 4px;width:293px;cursor: pointer;}
		.withdraw-custom span{background-color: #d2d2d2;color: #fff;border-radius: 2px 0 0 2px;padding: 0 10px;height: 100%;font-size: 14px;overflow: hidden;white-space: nowrap;text-overflow: ellipsis;}
		.withdraw-custom input{height:30px}
		.withdraw-custom i{position: absolute;right: 0;top: 0;width: 30px;height: 28px;border: 1px solid #d2d2d2;border-left: none;border-radius: 0 2px 2px 0;color: #fff;font-size: 20px;text-align: center;}
        .withdraw-custom:hover span{ background-color: #c2c2c2; }
        .withdraw-custom:hover i{ border-color: #c2c2c2;color: #c2c2c2 }
        .withdraw-custom-checked span,.withdraw-custom-checked:hover span{background-color: #5FB878;}
        .withdraw-custom-checked i, .withdraw-custom-checked:hover i { color: #5FB878;}
	</style>
</head>
<body>
  <div class="layui-fluid">
    <div class="layui-row layui-col-space15">
      <div class="layui-card layui-col-md12">
				<div class="layui-card-header"><i class="fa fa-cog"></i> 系统设置</div>
				<div class="layui-card-body" pad15>
					<div class="layui-form form-label-w8" lay-filter="">
						<div class="layui-tab layui-tab-brief" lay-filter="mytab">
							<ul class="layui-tab-title">
								<li class="layui-this" lay-id="1">基础设置</li>
								<li lay-id="4">财务设置</li>
								<li lay-id="5">积分设置</li>
								<li lay-id="6">分销分红</li>
								<li lay-id="7">文本自定义</li>
								<li lay-id="8">登录注册</li>
								<li lay-id="9">注册协议</li>
								<li lay-id="10">附件设置</li>
								<?php if(getcustom('jushuitan')): if($admin['jushuitan_status']==1): ?>
									<li lay-id="11">聚水潭设置</li>
								<?php endif; ?>
								<?php endif; ?>
								<li lay-id="12">加载图标</li>
								<?php if(getcustom('admin_login_page')): if($open_login_page == 1): ?>
									<li lay-id="13">独立系统信息</li>
									<?php endif; ?>
								<?php endif; ?>
							</ul>
							<div class="layui-tab-content">
								<div class="layui-tab-item layui-show">
									<div class="layui-form-item">
										<label class="layui-form-label">商家名称：</label>
										<div class="layui-input-inline">
											<input type="text" name="info[name]" class="layui-input" value="<?php echo $info['name']; ?>">
										</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">商家LOGO：</label>
										<input type="hidden" name="info[logo]" id="logo" class="layui-input" value="<?php echo $info['logo']; ?>">
										<button style="float:left;margin-right: 10px" type="button" class="layui-btn layui-btn-primary" onclick="uploader(this)" upload-input="logo" upload-preview="logoPreview">上传图片</button>
										<div class="layui-form-mid layui-word-aux">建议尺寸：200×200像素</div>
										<div id="logoPreview" class="picsList-class-padding">
											<div class="layui-imgbox" style="width:100px;"><div class="layui-imgbox-img"><img src="<?php echo $info['logo']; ?>"/></div></div>
										</div>
									</div>

<!--									<div class="layui-form-item">-->
<!--										<label class="layui-form-label">商家照片：</label>-->
<!--										<input type="hidden" name="info[pics]" value="<?php echo $info['pics']; ?>" id="pics">-->
<!--										<button style="float:left;" type="button" class="layui-btn layui-btn-primary" onclick="uploader(this,true)" upload-input="pics" upload-preview="picList" >批量上传</button>-->
<!--										<div class="layui-form-mid layui-word-aux" style="margin-left:10px;">建议尺寸：600*340像素</div>-->
<!--										<div id="picList" style="float:left;padding-top:10px;padding-left:160px;clear: both;">-->
<!--											<?php if($info['pics']): ?>-->
<!--											<?php $pics = explode(',',$info['pics']); ?>-->
<!--											<?php foreach($pics as $pic): ?>-->
<!--											<div class="layui-imgbox" style="margin-right: 20px;">-->
<!--												<a class="layui-imgbox-close" href="javascript:void(0)" onclick="$(this).parent().remove();getpicsval('pics','picList')" title="删除"><i class="layui-icon layui-icon-close-fill"></i></a>-->
<!--												<span class="layui-imgbox-img" style="height: auto;"><img src="<?php echo $pic; ?>" onclick="previewImg('<?php echo $pic; ?>')"></span>-->
<!--											</div>-->
<!--											<?php endforeach; ?><?php endif; ?>-->
<!--										</div>-->
<!--									</div>-->
									<?php if(getcustom('show_location')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">系统模式：</label>
										<div class="layui-input-inline" style="width:80%">
											<input type="radio" name="info[mode]" value="0" <?php if($info['mode']==0): ?>checked<?php endif; ?> title="商城模式" lay-skin="primary" lay-filter="shopmode">
											<?php if(getcustom('loc_business')): ?>
											<input type="radio" name="info[mode]" value="1" <?php if($info['mode']==1): ?>checked<?php endif; ?> title="商户门店模式" lay-skin="primary" lay-filter="shopmode">
											<?php endif; if(getcustom('location_city_address')): ?>
											<input type="radio" name="info[mode]" value="2" <?php if($info['mode']==2): ?>checked<?php endif; ?> title="定位模式" lay-skin="primary" lay-filter="shopmode">
											<?php endif; if(getcustom('location_mendian')): ?>
											<input type="radio" name="info[mode]" value="3" <?php if($info['mode']==3): ?>checked<?php endif; ?> title="门店模式" lay-skin="primary" lay-filter="shopmode">
											<?php endif; ?>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">
											商城模式：传统在线商城；
											<?php if(getcustom('loc_business')): ?>
											<br>商户门店模式：根据定位显示最近商户首页，不显示平台页面；
											<?php endif; if(getcustom('location_city_address')): ?>
											<br>定位模式：根据用户位置，显示范围内的商家和商品信息；
											<?php endif; if(getcustom('location_mendian')): ?>
											<br>门店模式：根据组件归属门店，显示对应的门店和商品信息等；
											<?php endif; ?><br>
										</div>
									</div>
									<?php if(getcustom('loc_business')): ?>
									<div id="mode_1" <?php if($info['mode']!=1): ?>style="display:none"<?php endif; ?>>
										<div class="layui-form-item">
											<label class="layui-form-label">显示商户：</label>
											<div class="layui-input-inline" style="width:40%">
												<input type="radio" name="info[loc_business_show_type]" value="0" <?php if($info['loc_business_show_type']==0): ?>checked<?php endif; ?> title="距离最近" lay-skin="primary" >
												<input type="radio" name="info[loc_business_show_type]" value="1" <?php if($info['loc_business_show_type']==1): ?>checked<?php endif; ?> title="推荐人商户" lay-skin="primary">
											</div>
										</div>
									</div>
									<?php endif; ?>
									<div id="mode_2" <?php if($info['mode']!=2): ?>style="display:none"<?php endif; ?>>
										<div class="layui-form-item">
											<label class="layui-form-label">定位显示：</label>
											<div class="layui-input-inline" style="width:40%">
												<input type="radio" name="info[loc_area_type]" value="0" <?php if($info['loc_area_type']==0): ?>checked<?php endif; ?> title="当前城市" lay-skin="primary" >
												<input type="radio" name="info[loc_area_type]" value="1" <?php if($info['loc_area_type']==1): ?>checked<?php endif; ?> title="当前地址" lay-skin="primary">
											</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label">显示范围：</label>
											<div class="layui-input-inline">
												<input type="radio" name="info[loc_range_type]" value="0" <?php if($info['loc_range_type']==0): ?>checked<?php endif; ?> title="同城" lay-skin="primary" lay-filter="loc_range_type">
												<input type="radio" name="info[loc_range_type]" value="1" <?php if($info['loc_range_type']==1): ?>checked<?php endif; ?> title="自定范围" lay-skin="primary" lay-filter="loc_range_type">
											</div>
											<div id="loc_range_block" <?php if($info['loc_range_type']!=1): ?>style="display:none"<?php else: ?>style="display: inline-block"<?php endif; ?>>
												<div class="layui-form-mid">附近</div>
												<div class="layui-input-inline" style="width:120px">
													<input type="text" name="info[loc_range]" value="<?php echo $info[loc_range]; ?>" class="layui-input">
												</div>
												<div class="layui-form-mid">Km</div>
											</div>
										</div>
									</div>
									<?php endif; ?>
									<div class="layui-form-item">
										<label class="layui-form-label">商家简介：</label>
										<div class="layui-input-inline" style="width:600px">
											<input type="text" name="info[desc]" value="<?php echo $info['desc']; ?>" class="layui-input">
										</div>
									</div>
									<?php if(getcustom('shop_other_infor')): ?>
										<div class="layui-form-item">
											<label class="layui-form-label">主营业务：</label>
											<div class="layui-input-inline" style="width:600px">
												<input type="text" name="info[main_business]" class="layui-input" value="<?php echo $info['main_business']; ?>">
											</div>
											<div class="layui-form-mid layui-word-aux">在商品列表价格下面展示主营业务息，建议20字以内</div>
										</div>
									<?php endif; ?>
									<div class="layui-form-item">
										<label class="layui-form-label">商家服务电话：</label>
										<div class="layui-input-inline">
											<input type="text" name="info[tel]" value="<?php echo $info['tel']; ?>" class="layui-input">
										</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">商家地址：</label>
										<div class="layui-input-inline">
											<input type="text" name="info[address]" value="<?php echo $info['address']; ?>" class="layui-input">
										</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">经纬度：</label>
										<div class="layui-input-inline" style="width:150px">
											<input type="text" name="info[longitude]" value="<?php echo $info['longitude']; ?>" class="layui-input">
										</div>
										<div class="layui-form-mid">-</div>
										<div class="layui-input-inline" style="width:150px">
											<input type="text" name="info[latitude]" value="<?php echo $info['latitude']; ?>" class="layui-input">
										</div>
										<button class="layui-btn layui-btn-primary" onclick="choosezuobiao()" style="float:left">选择坐标</button>
									</div>
									<!-- <div class="layui-form-item" <?php if(!in_array('mp',$platform)): ?>style="display:none"<?php endif; ?>>
										<label class="layui-form-label" style="width:130px">客服系统链接：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="text" name="info[kfurl]" value="<?php echo $info['kfurl']; ?>" class="layui-input">
										</div>
										<div class="layui-form-mid layui-word-aux" style="margin-left:10px;">商品详情页客服链接，不填写则使用商城内部客服系统</div>
									</div> -->

									<div class="layui-form-item">
										<label class="layui-form-label">客服系统链接：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="text" name="info[kfurl]" value="<?php echo $info['kfurl']; ?>" class="layui-input"/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">商品详情页客服链接，不填写则使用商城内部客服系统</div>
										<div class="layui-form-mid layui-popover layui-default-link">
											示例
											<div class="layui-popover-div">
												<img src="/static/admin/img/dianda_kefu.png"/>
											</div>
										</div>
									</div>

									<div class="layui-form-item" <?php if(!in_array('wx',$platform)): ?>style="display:none"<?php endif; ?>>
										<label class="layui-form-label">微信小程序客服：</label>
										<div class="layui-input-inline" style="width:360px">
											<input type="radio" name="info[wxkf]" value="0" <?php if($info['wxkf']==0): ?>checked<?php endif; ?> title="客服链接" lay-skin="primary" lay-filter="wxkfset">
											<input type="radio" name="info[wxkf]" value="1" <?php if($info['wxkf']==1): ?>checked<?php endif; ?> title="小程序客服" lay-skin="primary" lay-filter="wxkfset">
											<input type="radio" name="info[wxkf]" value="2" <?php if($info['wxkf']==2): ?>checked<?php endif; ?> title="微信客服" lay-skin="primary" lay-filter="wxkfset">
										</div>
										<div <?php if($info['wxkf']==1): ?>style="display:none"<?php endif; ?> id="wxkfurl">
											<div class="layui-form-mid">客服链接：</div>
											<div class="layui-input-inline" style="width:220px">
												<input type="text" name="info[wxkfurl]" value="<?php echo $info['wxkfurl']; ?>" class="layui-input">
											</div>
											</div>
											<div <?php if($info['wxkf']!=2): ?>style="display:none"<?php endif; ?> id="wxkfcorpid">
											<div class="layui-form-mid">企业ID：</div>
											<div class="layui-input-inline" style="width:170px">
												<input type="text" name="info[corpid]" value="<?php echo $info['corpid']; ?>" class="layui-input">
											</div>
										</div>
										<div <?php if($info['wxkf']!=1): ?>style="display:none"<?php endif; ?> class="wxkf1set">
											<div class="layui-form-mid">客服人员：</div>
											<div class="layui-input-inline" style="width:170px">
												<select name="info[wxkftransfer]">
													<option value="0" <?php if($info['wxkftransfer']==0): ?>selected<?php endif; ?>>系统管理员</option>
													<option value="1" <?php if($info['wxkftransfer']==1): ?>selected<?php endif; ?>>小程序客服</option>
												</select>
											</div>
											<span class="layui-form-mid">选择系统管理员时需要配置消息推送</span>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">
										客服链接：使用填写的客服链接，不填写则使用商城内部客服系统；如使用电话客服，链接格式为：tel:0539-123456；<br>
										小程序客服：在小程序后台-功能-客服-小程序客服，配置客服人员；<br>
										微信客服：在<a href="https://work.weixin.qq.com/kf" target="_blank">微信客服</a>系统注册账号并绑定，<a href="https://work.weixin.qq.com/nl/act/p/229e9eb2c10f417a" target="_blank">查看绑定流程</a>，填写企业ID并复制客服链接填写到客服链接处
										</div>
									</div>
			
									<div class="layui-form-item" <?php if(!in_array('wx',$platform)): ?>style="display:none"<?php endif; ?>>
										<label class="layui-form-label">公众号关注组件：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[official_account_status]" value="1" title="开启" <?php if($info['official_account_status']==1): ?>checked<?php endif; ?>>
											<input type="radio" name="info[official_account_status]" value="0" title="关闭" <?php if($info['official_account_status']==0): ?>checked<?php endif; ?>>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">仅微信小程序端生效（支持场景：扫描二维码、扫描小程序码），设置方式：小程序后台，在“设置”->“关注公众号”中设置要展示的公众号</div>
									</div>
									
									<?php if(getcustom('member_vip_edit')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('会员'); ?>无订单天数：</label>
										<div class="layui-input-inline" >
											<input type="text" name="info[member_vip_no_order_days]" value="<?php echo $info['member_vip_no_order_days']; ?>" class="layui-input"/>
										</div>
										<div class="layui-form-mid">天</div>
										<div class="layui-form-mid layui-word-aux layui-clear">普通客户升级为vip，x天内没订单，变成普通用户</div>
									</div>
	
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('会员'); ?>当天无订单：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[member_no_order_expire_status]" value="1" title="开启" <?php if($info['member_no_order_expire_status']==1): ?>checked<?php endif; ?>>
											<input type="radio" name="info[member_no_order_expire_status]" value="0" title="关闭" <?php if($info['member_no_order_expire_status']==0): ?>checked<?php endif; ?>>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">普通客户被设置成vip后，当天必须下单，不下单，第二天自动变成普通用户</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('会员'); ?>无订单天数：</label>
										<div class="layui-input-inline" >
											<input type="text" name="info[member_vip_no_order_days]" value="<?php echo $info['member_vip_no_order_days']; ?>" class="layui-input"/>
										</div>
										<div class="layui-form-mid">天</div>
										<div class="layui-form-mid layui-word-aux layui-clear">普通客户升级为vip，x天内没订单，变成普通用户</div>
									</div>
								<?php endif; if(getcustom('agent_card') && $ainfo['agent_card'] == 1): ?>
								<div class="layui-form-item">
									<label class="layui-form-label">代理卡片：</label>
									<div class="layui-input-inline">
										<input type="radio" name="info[agent_card]" value="1" title="开启" <?php if($info['agent_card']==1): ?>checked<?php endif; ?>>
										<input type="radio" name="info[agent_card]" value="0" title="关闭" <?php if($info['agent_card']==0): ?>checked<?php endif; ?>>
									</div>
								</div>
								<?php endif; ?>
									<div class="layui-form-item" <?php if(!in_array('mp',$platform)): ?>style="display:none"<?php endif; ?>>
										<label class="layui-form-label">关注提示：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="checkbox" name="gzts[]" value="1" <?php if(in_array('1',explode(',',$info['gzts']))): ?>checked<?php endif; ?> title="首页" lay-skin="primary">
											<input type="checkbox" name="gzts[]" value="2" <?php if(in_array('2',explode(',',$info['gzts']))): ?>checked<?php endif; ?>  title="商品详情页" lay-skin="primary">
										</div>
										<div class="layui-form-mid layui-word-aux">仅公众号端有效</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">订单播报：</label>
										<div class="layui-input-inline" style="width:400px">
											<input type="checkbox" name="ddbb[]" value="1" <?php if(in_array('1',explode(',',$info['ddbb']))): ?>checked<?php endif; ?> title="首页" lay-skin="primary">
											<input type="checkbox" name="ddbb[]" value="2" <?php if(in_array('2',explode(',',$info['ddbb']))): ?>checked<?php endif; ?>  title="商品详情页" lay-skin="primary">
											<?php if(getcustom('ngmm')): ?>
											<input type="checkbox" name="ddbb[]" value="3" <?php if(in_array('3',explode(',',$info['ddbb']))): ?>checked<?php endif; ?> title="拼团" lay-skin="primary">
											<input type="checkbox" name="ddbb[]" value="4" <?php if(in_array('4',explode(',',$info['ddbb']))): ?>checked<?php endif; ?> title="秒杀" lay-skin="primary">
											<?php endif; if(getcustom('ddbb_luckycollage')): ?>
											<input type="checkbox" name="ddbb[]" value="5" <?php if(in_array('5',explode(',',$info['ddbb']))): ?>checked<?php endif; ?> title="幸运拼团" lay-skin="primary">
											<?php endif; if(getcustom('business_nearby_list')): ?>
											<input type="checkbox" name="ddbb[]" value="6" <?php if(in_array('6',explode(',',$info['ddbb']))): ?>checked<?php endif; ?> title="商户入驻" lay-skin="primary">
											<?php endif; if(getcustom('ddbb_maidan')): ?>
 											<input type="checkbox" name="ddbb[]" value="7" <?php if(in_array('7',explode(',',$info['ddbb']))): ?>checked<?php endif; ?> title="买单" lay-skin="primary">
											<?php endif; ?>
										 
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">首页或商品详情页滚动显示最近购买信息</div>
									</div>
									<?php if(getcustom('ngmm')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">订单播报链接：</label>
										<div class="layui-input-inline">
											<input class="layui-input" name="info[ddbbtourl]" value="<?php echo $info['ddbbtourl']; ?>" id="ddbbtourl"/>
										</div>
										<div class="layui-btn layui-btn-primary" style="float:left" onclick="chooseUrl2('ddbbtourl')">选择链接</div>
										<div class="layui-form-mid layui-word-aux">不填写默认跳转到商品详情页</div>
									</div>
									<?php endif; ?>
									<div class="layui-form-item">
										<label class="layui-form-label">主色调：</label>
										<div class="layui-input-inline">
											<input type="text" name="info[color1]" value="<?php echo $info['color1']; ?>" class="layui-input">
										</div>
										<div class="_colorpicker" style="float:left"></div>
										<div class="layui-form-mid layui-word-aux">商城主色调，如：#FD4A46</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">辅色调：</label>
										<div class="layui-input-inline">
											<input type="text" name="info[color2]" value="<?php echo $info['color2']; ?>" class="layui-input">
										</div>
										<div class="_colorpicker" style="float:left"></div>
										<div class="layui-form-mid layui-word-aux">商城辅色调，如：#7E71F6</div>
									</div>
									<?php if(getcustom('index_fav_tip')): ?>
									<div class="layui-form-item" <?php if(!in_array('wx',$platform)): ?>style="display:none"<?php endif; ?>>
										<label class="layui-form-label">小程序收藏提示：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[indexfavtip]" value="1" title="开启" <?php if($info['indexfavtip']==1): ?>checked<?php endif; ?>>
											<input type="radio" name="info[indexfavtip]" value="0" title="关闭" <?php if($info['indexfavtip']==0): ?>checked<?php endif; ?>>
										</div>
										<div class="layui-form-mid layui-word-aux">微信小程序，首次打开首页右上角提示添加到桌面并收藏</div>
									</div>
									<?php endif; if(getcustom('freight_add_district')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">配送开启县区限制：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[open_freight_district]" value="1" title="开启" <?php if($info['open_freight_district']==1): ?>checked<?php endif; ?>>
											<input type="radio" name="info[open_freight_district]" value="0" title="关闭" <?php if($info['open_freight_district']==0): ?>checked<?php endif; ?>>
										</div>
										<div class="layui-form-mid layui-word-aux">开启或关闭请及时修改或删除配送模板的普通快递方式</div>
									</div>
									<?php endif; ?>
									<div class="layui-form-item">
										<label class="layui-form-label">进入条件：</label>
										<div class="layui-input-inline" style="width:800px">
											<input type="checkbox" name="info[gettj][]" value="-1" title="所有人" lay-skin="primary" <?php if(in_array('-1',$info['gettj'])): ?>checked<?php endif; ?> lay-filter="gettjset"/>
											<?php foreach($levellist as $v): ?>
											<input type="checkbox" name="info[gettj][]" value="<?php echo $v['id']; ?>" title="<?php echo $v['name']; ?>" lay-skin="primary" <?php if(in_array($v['id'],$info['gettj'])): ?>checked<?php endif; ?>/>
											<?php endforeach; ?>
										</div>
									</div>
									<div id="gettjset" <?php if(!$info['id'] || in_array('-1',$info['gettj'])): ?>style="display:none"<?php endif; ?>>
										<div class="layui-form-item">
											<div class="layui-form-label">无权限提示：</div>
											<div class="layui-input-inline">
												<input class="layui-input" name="info[gettjtip]" value="<?php if(!$info['id']): ?>您没有权限进入<?php else: ?><?php echo $info['gettjtip']; ?><?php endif; ?>"/>
											</div>
											<div class="layui-form-mid"></div>
										</div>
									</div>

									<?php if(getcustom('edit_locking') && $isadmin>0): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">开锁密码：</label>
										<div class="layui-input-inline">
											<input type="password" name="info[locking_pwd]" value="<?php echo $info['locking_pwd']; ?>" class="layui-input"/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear"></div>
									</div>
									<?php endif; if(getcustom('system_copy')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label" style="width:130px">剪贴板：</label>
										<div class="layui-input-inline" style="width:300px">
											<textarea type="text" name="info[copyinfo]" class="layui-textarea"><?php echo $info['copyinfo']; ?></textarea>
										</div>
										<div class="layui-form-mid layui-word-aux" style="margin-left:10px;">若设置内容，前端页面将自动复制到剪贴板上</div>
									</div>
									<?php endif; if(getcustom('zhongkang_sync')): ?>
										<div class="layui-form-item">
										  <label class="layui-form-label" style="width:130px">中康时代接口秘钥：</label>
										  <div class="layui-input-inline" style="width:300px">
											  <input type="text" name="info[zhongkang_secret]" value="<?php echo $info['zhongkang_secret']; ?>" class="layui-input"/>
										  </div>
										  <div class="layui-form-mid layui-word-aux" style="margin-left:10px;">中康时代API 密钥</div>
										</div>
  									<?php endif; if($ainfo['ali_appcode_choose'] == 0): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">快递查询AppCode：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="text" name="info[ali_appcode]" class="layui-input" value="<?php echo $info['ali_appcode']; ?>"/>
										</div>
										<div class="layui-form-mid layui-word-aux" style="margin-left:10px;">用于用户查询快递物流状态，请先在阿里云购买快递查询接口，然后在[阿里云控制台 - 云市场 - 已购买的服务]中查找AppCode，<a  href="https://market.aliyun.com/products/57126001/cmapi021863.html#sku=yuncode15863000017" target="_blank">去购买</a></div>
									</div>
									<?php endif; if(getcustom('system_nologin_day')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">前端免登录天数：</label>
										<div class="layui-input-inline">
											<input type="text" name="info[nologin_day]" class="layui-input" value="<?php echo $info['nologin_day']; ?>"/>
										</div>
										<div class="layui-form-mid layui-word-aux">天 前端用户登录一次，多少天内免登录，默认七天，最少填写一天</div>
									</div>
									<?php endif; ?>
								</div>

								<div class="layui-tab-item form-label-w10">
									<fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
										<legend>余额</legend>
									</fieldset>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('余额'); ?>支付：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[moneypay]" title="开启" value="1" <?php if($info['moneypay']==1): ?>checked<?php endif; ?> lay-filter="moneypay"/>
											<input type="radio" name="info[moneypay]" title="关闭" value="0" <?php if($info['moneypay']==0): ?>checked<?php endif; ?> lay-filter="moneypay"/>
										</div>
									</div>
									<?php if(getcustom('member_level_moneypay_price')): ?>
									<div class="layui-form-item" id='moneypay_priceset' style="<?php if(!$info['moneypay'] || $info['moneypay']!=1): ?>display:none<?php endif; ?>">
										<label class="layui-form-label"><?php echo t('会员'); ?>价仅限<?php echo t('余额'); ?>支付：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[moneypay_lvprice_status]" title="开启" value="1" <?php if($info['moneypay_lvprice_status']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[moneypay_lvprice_status]" title="关闭" value="0" <?php if(!$info['moneypay_lvprice_status'] || $info['moneypay_lvprice_status']==0): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">
											1、开启后商城商品<?php if(getcustom('restaurant')): ?>、餐饮商品(点餐、外卖)<?php endif; ?>支付时，只有用<?php echo t('余额'); ?>支付的才享受<?php echo t('会员'); ?>价格<br>
											2、注意：此功能不能随意开启和关闭，否则将影响正在支付的用户
										</div>
									</div>
									<?php endif; ?>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('余额'); ?>充值：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[recharge]" title="开启" value="1" <?php if($info['recharge']==1): ?>checked<?php endif; ?> lay-filter="recharge"/>
											<input type="radio" name="info[recharge]" title="关闭" value="0" <?php if($info['recharge']==0): ?>checked<?php endif; ?> lay-filter="recharge"/>
										</div>
									</div>
									<?php if(getcustom('money_recharge_transfer') && getcustom('pay_transfer')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('余额'); ?>充值支付方式：</label>
										<div class="layui-input-inline" style="width:450px">
											<input type="checkbox" name="info[money_recharge_pay_type][]" title="微信支付" value="wxpay" <?php if(in_array('wxpay',explode(',',$info['money_recharge_pay_type']))): ?>checked<?php endif; ?>/>
											<input type="checkbox" name="info[money_recharge_pay_type][]" title="支付宝支付" value="alipay" <?php if(in_array('alipay',explode(',',$info['money_recharge_pay_type']))): ?>checked<?php endif; ?>/>
											<input type="checkbox" name="info[money_recharge_pay_type][]" title="<?php echo t('转账汇款'); ?>" value="transfer" <?php if(in_array('transfer',explode(',',$info['money_recharge_pay_type']))): ?>checked<?php endif; ?>/>
										</div>
									</div>
									<?php endif; if(getcustom('member_recharge_minimum')): ?>
									<div class="layui-form-item" id='rechargeminimumset' style="<?php if(!$info['recharge'] || $info['recharge']!=1): ?>display:none<?php endif; ?>">
										<label class="layui-form-label">充值门槛：</label>
										<div class="layui-input-inline">
											<input type="text" name="info[recharge_minimum]" class="layui-input" value="<?php echo $info['recharge_minimum']; ?>"/>
										</div>
										<div class="layui-form-mid layui-word-aux">元</div>
										<div class="layui-form-mid layui-word-aux layui-clear">最低充值多少余额，0为不限制</div>
									</div>
									<?php endif; ?>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('佣金'); ?>转<?php echo t('余额'); ?>：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[commission2money]" title="开启" value="1" <?php if($info['commission2money']==1): ?>checked<?php endif; ?> lay-filter="commission2money"/>
											<input type="radio" name="info[commission2money]" title="关闭" value="0" <?php if($info['commission2money']==0): ?>checked<?php endif; ?> lay-filter="commission2money"/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">开启后<?php echo t('会员'); ?>可以将<?php echo t('佣金'); ?>直接转入<?php echo t('余额'); ?>中用于消费</div>
									</div>
									<?php if(getcustom('commission_to_money_rate')): ?>
									<div class="layui-form-item" id="commission_to_money_rate" <?php if($info['commission2money'] != 1): ?> style="display:none"<?php endif; ?>>
										<label class="layui-form-label"><?php echo t('佣金'); ?>转<?php echo t('余额'); ?>手续费：</label>
										<div class="layui-input-inline" style="width:120px">
											<input type="text" name="info[commission_to_money_rate]" value="<?php echo $info['commission_to_money_rate']; ?>" class="layui-input"/>
										</div>
										<div class="layui-form-mid">%</div>
									</div>
									<?php endif; if(getcustom('commission_tomoney_need_score')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('佣金'); ?>转<?php echo t('余额'); ?>消耗<?php echo t('积分'); ?>：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[comtomoney_need_score]" title="开启" value="1" <?php if($info['comtomoney_need_score']==1): ?>checked<?php endif; ?> />
											<input type="radio" name="info[comtomoney_need_score]" title="关闭" value="0" <?php if($info['comtomoney_need_score']==0): ?>checked<?php endif; ?> />
										</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('佣金'); ?><?php echo t('余额'); ?>兑换比例：</label>
										<div class="layui-input-inline" style="width: 120px;">
											<input type="number" class="layui-input" name="info[commission_money_exchange_num]" value="<?php echo $info['commission_money_exchange_num']; ?>">
										</div>
										<div class="layui-form-mid layui-word-aux"><?php echo t('佣金'); ?>可兑换1<?php echo t('余额'); ?></div>
									</div>
									<?php endif; if(getcustom('commission_perc_to_score')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('佣金'); ?>百分比到<?php echo t('积分'); ?>：</label>
										<div class="layui-input-inline" style="width: 120px;">
											<input type="number" class="layui-input" name="info[commission_perc_to_score]" value="<?php echo $info['commission_perc_to_score']; ?>">
										</div>
										<div class="layui-form-mid">%</div>
										<div class="layui-form-mid layui-word-aux">获取<?php echo t('佣金'); ?>时百分比到<?php echo t('积分'); ?></div>
									</div>
									<?php endif; if(getcustom('money_transfer') || getcustom('money_friend_transfer')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('余额'); ?>转账：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[money_transfer]" title="开启" value="1" <?php if($info['money_transfer']==1): ?>checked<?php endif; ?> lay-filter="diandaSwitch"/>
											<input type="radio" name="info[money_transfer]" title="关闭" value="0" <?php if($info['money_transfer']==0): ?>checked<?php endif; ?> lay-filter="diandaSwitch"/>
										</div>
									</div>
									<div diandaSwitch="info[money_transfer]" <?php if($info['money_transfer'] != 1): ?> style="display:none"<?php endif; ?>>
										<div class="layui-form-item">
											<label class="layui-form-label"><?php echo t('余额'); ?>转账范围：</label>
											<div class="layui-input-inline" style="width: 60%">
												<?php if(getcustom('money_transfer')): ?>
												<input type="radio" name="info[money_transfer_range]" title="全站" value="0" <?php if($info['money_transfer_range']==0): ?>checked<?php endif; ?>/>
												<input type="radio" name="info[money_transfer_range]" title="所有上下级" value="1" <?php if($info['money_transfer_range']==1): ?>checked<?php endif; ?>/>
												<?php endif; if(getcustom('money_friend_transfer')): ?>
												<input type="radio" name="info[money_transfer_range]" title="好友" value="2" <?php if($info['money_transfer_range']==2): ?>checked<?php endif; ?>/>
												<?php endif; ?>
											</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label"><?php echo t('余额'); ?>转账支付密码：</label>
											<div class="layui-input-inline">
												<input type="radio" lay-filter="money_transfer_pwd" name="info[money_transfer_pwd]" title="开启" value="1" <?php if($info['money_transfer_pwd']==1): ?>checked<?php endif; ?>/>
												<input type="radio" lay-filter="money_transfer_pwd" name="info[money_transfer_pwd]" title="关闭" value="0" <?php if($info['money_transfer_pwd']==0): ?>checked<?php endif; ?>/>
											</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label"><?php echo t('余额'); ?>转账方式：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="checkbox" name="info[money_transfer_type][]" title="<?php echo t('会员'); ?>ID" value="id" <?php if(in_array('id',explode(',',$info['money_transfer_type']))): ?>checked<?php endif; ?>/>
												<input type="checkbox" name="info[money_transfer_type][]" title="手机号" value="tel" <?php if(in_array('tel',explode(',',$info['money_transfer_type']))): ?>checked<?php endif; ?>/>
											</div>
										</div>
										  <div class="layui-form-item">
											  <label class="layui-form-label"><?php echo t('余额'); ?>转账最小金额：</label>
											  <div class="layui-input-inline" style="width: 120px;">
												  <input type="number" name="info[money_transfer_min]" value="<?php echo $info['money_transfer_min']; ?>" class="layui-input"  />
											  </div>
											  <div class="layui-form-mid layui-word-aux">0为不限</div>
										  </div>
									</div>
									<?php endif; if(getcustom('money_dec') || getcustom('cashier_money_dec')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('余额'); ?>抵扣：</label>
										<div class="layui-input-inline">
											<input type="radio" lay-filter="moneydec" name="info[money_dec]" title="开启" value="1" <?php if($info['money_dec']==1): ?>checked<?php endif; ?>/>
											<input type="radio" lay-filter="moneydec" name="info[money_dec]" title="关闭" value="0" <?php if(!$info['money_dec'] || $info['money_dec']==0): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux">注意：商城商品使用<?php echo t('余额'); ?>抵扣后支付页面<?php echo t('余额'); ?>支付将不显示</div>
									</div>
									<div class="layui-form-item" id="money_dec" <?php if($info['money_dec']!=1): ?>style="display:none"<?php endif; ?>>
										<label class="layui-form-label">最高抵扣比例：</label>
										<div class="layui-input-inline" style="width: 120px;">
											<input type="number" name="info[money_dec_rate]" value="<?php echo !empty($info['money_dec_rate']) ? $info['money_dec_rate'] : 0; ?>" class="layui-input"  />
										</div>
										<div class="layui-form-mid layui-word-aux">% <?php echo t('余额'); ?>最高抵扣比例</div>
									</div>
									<?php endif; if(getcustom('member_overdraft_money') && getcustom('cashier_overdraft_money_dec')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('信用额度'); ?>抵扣：</label>
										<div class="layui-input-inline">
											<input type="radio" lay-filter="overdraftmoneydec" name="info[overdraft_money_dec]" title="开启" value="1" <?php if($info['overdraft_money_dec']==1): ?>checked<?php endif; ?>/>
											<input type="radio" lay-filter="overdraftmoneydec" name="info[overdraft_money_dec]" title="关闭" value="0" <?php if(!$info['overdraft_money_dec'] || $info['overdraft_money_dec']==0): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux">注意：仅收银台支付时可用</div>
									</div>
									<div class="layui-form-item" id="overdraft_money_dec" <?php if($info['overdraft_money_dec']!=1): ?>style="display:none"<?php endif; ?>>
										<label class="layui-form-label">最高抵扣比例：</label>
										<div class="layui-input-inline" style="width: 120px;">
											<input type="number" name="info[overdraft_money_dec_rate]" value="<?php echo !empty($info['overdraft_money_dec_rate']) ? $info['overdraft_money_dec_rate'] : 100; ?>" class="layui-input"  />
										</div>
										<div class="layui-form-mid layui-word-aux" >% <?php echo t('信用额度'); ?>最高抵扣比例</div>
									</div>
									<?php endif; if(getcustom('pay_money_combine')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">余额组合支付：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[iscombine]" value="1" title="开启" <?php if($info['iscombine']==1): ?>checked<?php endif; ?>>
											<input type="radio" name="info[iscombine]" value="0" title="关闭" <?php if($info['iscombine']==0): ?>checked<?php endif; ?>>
										</div>
										<div class="layui-form-mid layui-word-aux">
											开启后：仅商城商品订单支付可以选择余额和微信，或者余额和支付宝多形式的组合支付;<br>
											使用组合支付，提交支付不管微信或支付宝是否支付成功，都会直接扣除余额支付的部分<br>
											订单退款先退微信或支付宝支付部分，后退余额支付部分
										</div>
									</div>
									<?php endif; if(getcustom('pay_daifu')): ?>
										<fieldset class="layui-elem-field layui-field-title"  style="margin-top: 30px;">
											<legend>好友代付</legend>
										</fieldset>
										<div class="layui-form-item">
											<label class="layui-form-label">好友代付：</label>
											<div class="layui-input-inline">
												<input type="radio" lay-filter="daifu" name="info[pay_daifu]" title="开启" value="1" <?php if($info['pay_daifu']==1): ?>checked<?php endif; ?>/>
												<input type="radio" lay-filter="daifu" name="info[pay_daifu]" title="关闭" value="0" <?php if(!$info['pay_daifu'] || $info['pay_daifu']==0): ?>checked<?php endif; ?>/>
											</div>
										</div>
										<div class="layui-form-item" id="daifu-desc" <?php if($info['pay_daifu']!=1): ?>style="display:none"<?php endif; ?>>
											<label class="layui-form-label">代付说明：</label>
											<div class="layui-input-inline" style="width: 40%;">
												<textarea style="height: 90px" class="layui-textarea" name="info[pay_daifu_desc]"><?php echo $info['pay_daifu_desc']; ?></textarea>
											</div>
										</div>
									<?php endif; if(getcustom('plug_yuebao')): ?>
										<fieldset class="layui-elem-field layui-field-title"  style="margin-top: 30px;">
											<legend>余额宝</legend>
										</fieldset>
										<div class="layui-form-item">
											<label class="layui-form-label">是否开启：</label>
											<div class="layui-input-inline">
												<input type="radio" name="info[open_yuebao]" title="开启" value="1" <?php if($info['open_yuebao']==1): ?>checked<?php endif; ?> lay-filter="set_yuebao"/>
												<input type="radio" name="info[open_yuebao]" title="关闭" value="0" <?php if($info['open_yuebao']==0): ?>checked<?php endif; ?> lay-filter="set_yuebao"/>
											</div>
										</div>
										<div id="yuebaoset" <?php if(!$info || $info['open_yuebao']==0): ?>style="display:none"<?php endif; ?>>
											<div class="layui-form-item">
												<label class="layui-form-label">余额宝收益利率：</label>
												<div class="layui-input-inline" style="width:120px">
													<input type="text" name="info[yuebao_rate]" value="<?php echo !empty($info['yuebao_rate']) ? $info['yuebao_rate'] : 0; ?>" class="layui-input"/>
												</div>
												<div class="layui-form-mid">%</div>
											</div>
											<div class="layui-form-item">
												<label class="layui-form-label">收益提现天数：</label>
												<div class="layui-input-inline" style="width:120px">
													<input type="text" name="info[yuebao_withdraw_time]" value="<?php echo !empty($info['yuebao_withdraw_time']) ? $info['yuebao_withdraw_time'] : 0; ?>" class="layui-input"/>
												</div>
												<div class="layui-form-mid">天 注意：1、必须为整数 2、填0为不限制，填其他数字，如填写10，为每10天可提现一次 3、<?php echo t('会员'); ?>单独设置后此设置失效</div>
											</div>
											<div class="layui-form-item">
												<label class="layui-form-label">收益转余额：</label>
												<div class="layui-input-inline">
													<input type="radio" name="info[yuebao_turn_yue]" title="开启" value="1" <?php if($info['yuebao_turn_yue']==1): ?>checked<?php endif; ?>/>
													<input type="radio" name="info[yuebao_turn_yue]" title="关闭" value="0" <?php if($info['yuebao_turn_yue']==0): ?>checked<?php endif; ?>/>
												</div>
											</div>
										</div>
									<?php endif; if(getcustom('pay_yuanbao')): ?>
										<fieldset class="layui-elem-field layui-field-title"  style="margin-top: 30px;">
                        <legend><?php echo t('元宝'); ?></legend>
                    </fieldset>
                    <div class="layui-form-item">
                        <label class="layui-form-label"><?php echo t('元宝'); ?>支付：</label>
                        <div class="layui-input-inline">
                            <input type="radio" name="info[yuanbao_pay]" title="开启" value="1" <?php if($info['yuanbao_pay']==1): ?>checked<?php endif; ?>/>
                            <input type="radio" name="info[yuanbao_pay]" title="关闭" value="0" <?php if($info['yuanbao_pay']==0): ?>checked<?php endif; ?>/>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label"><?php echo t('元宝'); ?>转账：</label>
                        <div class="layui-input-inline">
                            <input type="radio" name="info[yuanbao_transfer]" title="开启" value="1" <?php if($info['yuanbao_transfer']==1): ?>checked<?php endif; ?>/>
                            <input type="radio" name="info[yuanbao_transfer]" title="关闭" value="0" <?php if($info['yuanbao_transfer']==0): ?>checked<?php endif; ?>/>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">与现金兑换比例：</label>
                        <div class="layui-input-inline" style="width:120px">
                            <input type="number" name="info[yuanbao_money_ratio]" value="<?php echo $info['yuanbao_money_ratio']; ?>" class="layui-input">
                        </div>
                        <div class="layui-form-mid">% </div>
                    </div>
									<?php endif; if(getcustom('wxpay_member_level')): ?>
										<fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
											<legend>微信支付</legend>
										</fieldset>
										<div class="layui-form-item">
											<label class="layui-form-label">使用权限：</label>
											<div class="layui-input-inline" style="width:800px">
												<input type="checkbox" name="info[wxpay_gettj][]" value="-1" title="所有人" lay-skin="primary" <?php if(in_array('-1',$info['wxpay_gettj'])): ?>checked<?php endif; ?>/>
												<?php foreach($levellist as $v): ?>
												<input type="checkbox" name="info[wxpay_gettj][]" value="<?php echo $v['id']; ?>" title="<?php echo $v['name']; ?>" lay-skin="primary" <?php if(in_array($v['id'],$info['wxpay_gettj'])): ?>checked<?php endif; ?>/>
												<?php endforeach; ?>
											</div>
										</div>
									<?php endif; ?>
									<fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
										<legend>提现</legend>
									</fieldset>
									<div class="layui-form-item">
										<label class="layui-form-label">提现方式：</label>
										<div class="layui-input-inline" style="width:500px">
											<input type="checkbox" name="info[withdraw_weixin]" title="微信钱包" value="1" <?php if($info['withdraw_weixin']==1): ?>checked<?php endif; ?>>
											<input type="checkbox" name="info[withdraw_aliaccount]" title="支付宝" value="1" <?php if($info['withdraw_aliaccount']==1): ?>checked<?php endif; ?>/>
											<input type="checkbox" name="info[withdraw_bankcard]" title="银行卡" value="1" <?php if($info['withdraw_bankcard']==1): ?>checked<?php endif; ?>/>
											<?php if(getcustom('pay_adapay')): ?>
											<input type="checkbox" name="info[withdraw_adapay]" title="汇付天下银行卡" value="1" <?php if($info['withdraw_adapay']==1): ?>checked<?php endif; ?>/>
											<?php endif; if(getcustom('pay_huifu')): ?>
											<input type="checkbox" name="info[withdraw_huifu]" title="汇付斗拱银行卡" value="1" <?php if($info['withdraw_huifu']==1): ?>checked<?php endif; ?>/>
											<?php endif; if($auth_data=='all' || in_array('Xiaoetong/*',$auth_data)): if(getcustom('transfer_farsion')): ?>
											<input type="checkbox" name="info[withdraw_aliaccount_xiaoetong]" title="小额通支付宝" value="1" <?php if($info['withdraw_aliaccount_xiaoetong']==1): ?>checked<?php endif; ?>/>
											<input type="checkbox" name="info[withdraw_bankcard_xiaoetong]" title="小额通银行卡" value="1" <?php if($info['withdraw_bankcard_xiaoetong']==1): ?>checked<?php endif; ?>/>
											<?php endif; ?>
											<?php endif; if(getcustom('extend_linghuoxin')): if($auth_data=='all' || in_array('LinghuoxinSet/*',$auth_data)): ?>
												<input type="checkbox" name="info[withdraw_aliaccount_linghuoxin]" title="灵活薪支付宝" value="1" <?php if($info['withdraw_aliaccount_linghuoxin']==1): ?>checked<?php endif; ?>/>
												<input type="checkbox" name="info[withdraw_bankcard_linghuoxin]" title="灵活薪银行卡" value="1" <?php if($info['withdraw_bankcard_linghuoxin']==1): ?>checked<?php endif; ?>/>
												<?php endif; ?>
											<?php endif; if(getcustom('withdraw_paycode')): ?>
												<input type="checkbox" name="info[withdraw_paycode]" title="收款码" value="1" <?php if($info['withdraw_paycode']==1): ?>checked<?php endif; ?>/>
											<?php endif; if(getcustom('pay_allinpay')): if($auth_data=='all' || in_array('AllinpaySet/*',$auth_data)): ?>
												<input type="checkbox" name="info[withdraw_bankcard_allinpayYunst]" title="云商通银行卡" value="1" <?php if($info['withdraw_bankcard_allinpayYunst']==1): ?>checked<?php endif; ?>/>
												<?php endif; ?>
											<?php endif; if(getcustom('withdraw_custom')): ?>
											<div class="withdraw-custom <?php echo !empty($info['custom_status']) ? 'withdraw-custom-checked' : ''; ?>">
												<span>自定义提现方式：</span>
												<div style="width: 130px">
													<input type="text" name="info[custom_name]" value="<?php echo $info['custom_name']; ?>" placeholder="自定义提现名称" class="layui-input">
													<input type="hidden" name="info[custom_status]" value="<?php echo $info['custom_status']; ?>" class="layui-input">
												</div>
												<i class="layui-icon layui-icon-ok"></i>
											</div>
											<?php endif; ?>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">提现到微信钱包需开通企业付款到零钱功能<?php if(getcustom('pay_huifu')): ?>；汇付斗拱银行卡开启后自动替换默认银行卡，用户端需要进件<?php endif; ?></div>
									</div>

									<?php if(getcustom('other_money')): ?>
										<div class="layui-form-item">
											<label class="layui-form-label">其他<?php echo t('余额'); ?>提现：</label>
											<div class="layui-input-inline" style="width:200px">
												<input type="radio" name="info[othermoney_withdraw]" value="0" title="关闭" <?php if($info['othermoney_withdraw']==0): ?>checked<?php endif; ?>/>
												<input type="radio" name="info[othermoney_withdraw]" value="1" title="开启" <?php if($info['othermoney_withdraw']==1): ?>checked<?php endif; ?>/>
											</div>
										</div>
									<?php endif; ?>
									  <div class="layui-form-item">
										  <label class="layui-form-label">微信新版商家转账：</label>
										  <div class="layui-input-inline" style="width:200px">
											  <input type="radio" name="info[wx_transfer_type]" value="0" title="关闭" <?php if($info['wx_transfer_type']==0): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[wx_transfer_type]" value="1" title="开启" <?php if($info['wx_transfer_type']==1): ?>checked<?php endif; ?>/>
										  </div>
										  <div class="layui-form-mid layui-word-aux">使用微信新版商家转账功能，<a target="_blank" href="https://pay.weixin.qq.com/doc/v3/merchant/4012711988">查看详情</a></div>
									  </div>
									<div class="layui-form-item">
										<label class="layui-form-label">自动打款：</label>
										<div class="layui-input-inline" style="width:200px">
											<input type="radio" name="info[withdraw_autotransfer]" value="0" title="关闭" <?php if($info['withdraw_autotransfer']==0): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[withdraw_autotransfer]" value="1" title="开启" <?php if($info['withdraw_autotransfer']==1): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux">开启后提现到微信钱包时不需要审核，自动用企业付款打款，需开通企业付款到零钱功能<?php if($auth_data=='all' || in_array('Xiaoetong/*',$auth_data)): if(getcustom('transfer_farsion')): ?>小额通打款，会自动发送到小额通审核<?php endif; ?><?php endif; if(getcustom('extend_linghuoxin')): if($auth_data=='all' || in_array('LinghuoxinSet/*',$auth_data)): ?>;灵活薪打款，会自动发送到灵活薪审核;<?php endif; ?><?php endif; ?></div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">日提现次数：</label>
										<div class="layui-input-inline" style="width:120px">
											<input type="number" name="info[day_withdraw_num]" value="<?php echo $info['day_withdraw_num']; ?>" class="layui-input"/>
										</div>
										<div class="layui-form-mid">次</div><div class="layui-form-mid layui-word-aux"><?php echo t('会员'); ?>每日<?php echo t('余额'); ?>和<?php echo t('佣金'); ?>可提现总次数，设为0时为无限制提现</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">提现说明：</label>
										<div class="layui-input-inline" style="width: 40%;">
											<textarea style="height: 90px" class="layui-textarea" name="info[withdraw_desc]"><?php echo $info['withdraw_desc']; ?></textarea>
										</div>
									</div>
									  <?php if(getcustom('pay_huifu')): ?>
									  <fieldset class="layui-elem-field">
										  <legend>斗拱提现</legend>
										  <div class="layui-field-box">
											  <div class="layui-form-item">
												  <label class="layui-form-label">取现配置-业务类型：</label>
												  <div class="layui-input-inline" style="width:220px">
													  <input type="radio" name="info[withdraw_huifu_cash_type]" value="T1" title="T1" <?php if($info['withdraw_huifu_cash_type']=='T1'): ?>checked<?php endif; ?>/>
													  <input type="radio" name="info[withdraw_huifu_cash_type]" value="D1" title="D1" <?php if($info['withdraw_huifu_cash_type']=='D1'): ?>checked<?php endif; ?>/>
													  <input type="radio" name="info[withdraw_huifu_cash_type]" value="D0" title="D0" <?php if($info['withdraw_huifu_cash_type']=='D0'): ?>checked<?php endif; ?>/>
												  </div>
												  <div class="layui-form-mid layui-word-aux layui-clear">用户业务入驻接口配置，此配置需和商户后台保持一致，T1:下一工作日到银行账户；D1：下一自然日到银行账户；D0：当日到银行账户；<?php echo t('会员'); ?>进件使用此配置</div>
											  </div>
											  <div class="layui-form-item">
												  <label class="layui-form-label">取现配置-提现手续费率：</label>
												  <div class="layui-input-inline" style="width:160px">
													  <input type="number" name="info[withdraw_huifu_fee_rate]" value="<?php echo $info['withdraw_huifu_fee_rate']; ?>" class="layui-input"/>
												  </div>
												  <div class="layui-form-mid">%</div>
												  <div class="layui-form-mid layui-word-aux">用户业务入驻接口配置，此配置需和商户后台保持一致，需保留小数点后两位，取值范围[0.00,100.00]，不收费请填写0.00</div>
											  </div>
										  </div>
									  </fieldset>
									  <?php endif; if(getcustom('alipay_auto_transfer')): ?>
										<fieldset class="layui-elem-field">
											<legend>支付宝打款</legend>
											<div class="layui-field-box">
												<div class="layui-form-item">
													<label class="layui-form-label">支付宝自动打款：</label>
													<div class="layui-input-inline">
														<input type="radio" name="info[ali_withdraw_autotransfer]" value="0" title="关闭" <?php if($info['ali_withdraw_autotransfer']==0): ?>checked<?php endif; ?>/>
														<input type="radio" name="info[ali_withdraw_autotransfer]" value="1" title="开启" <?php if($info['ali_withdraw_autotransfer']==1): ?>checked<?php endif; ?>/>
													</div>
													<div class="layui-form-mid layui-word-aux">开启后提现到支付宝时不需要审核，自动用转账到支付宝账户，需开通转账到支付宝账户</div>
												</div>

												<div id="aliWithdrawSet">
													<div class="layui-form-item">
														<label class="layui-form-label">支付宝APPID：</label>
														<div class="layui-input-inline">
															<input type="text" name="info[ali_appid]" value="<?php echo $info['ali_appid']; ?>" class="layui-input"/>
														</div>
														<div class="layui-form-mid layui-word-aux">支付宝分配给开发者的应用ID</div>
													</div>
													<div class="layui-form-item">
														<label class="layui-form-label">应用私钥：</label>
														<div class="layui-input-inline" style="width:700px">
															<input type="text" name="info[ali_privatekey]" value="<?php echo $info['ali_privatekey']; ?>" class="layui-input"/>
															<?php if($info['ali_privatekey']): ?><div style="position: absolute;left: 0;top: 0;right: 0;bottom: 0; background: #fff;padding: .35rem .7rem;border: 1px solid rgba(0, 0, 0, .15);border-radius: .15rem;color: #636c72;" onclick="$(this).hide()">已隐藏内容，点击查看或编辑</div><?php endif; ?>
														</div>
														<div class="layui-form-mid layui-word-aux">请填写应用私钥去头去尾去回车，一行字符串</div>
													</div>
													<div class="layui-form-item">
														<label class="layui-form-label">应用公钥：</label>
														<div class="layui-input-inline" style="width:450px">
															<input style="float:left;" readonly="readonly" class="layui-input" type="text" name="info[ali_apppublickey]" value="<?php echo $info['ali_apppublickey']; ?>" id="ali_apppublickey"/>
														</div>
														<button style="float:left;" type="button" class="layui-btn layui-btn-primary uploadfile" upload-input="ali_apppublickey" upload-preview="apppublickeyPreview">上传</button>
														<div id="apppublickeyPreview" class="layui-form-mid" style="margin-left:20px;"><?php if($info['ali_apppublickey']): ?>已上传<?php endif; ?></div>
													</div>
													<div class="layui-form-item">
														<label class="layui-form-label">支付宝公钥：</label>
														<div class="layui-input-inline" style="width:450px">
															<input style="float:left;" readonly="readonly" class="layui-input" type="text" name="info[ali_publickey]" value="<?php echo $info['ali_publickey']; ?>" id="ali_publickey"/>
														</div>
														<button style="float:left;" type="button" class="layui-btn layui-btn-primary uploadfile" upload-input="ali_publickey" upload-preview="publickeyPreview">上传</button>
														<div id="publickeyPreview" class="layui-form-mid" style="margin-left:20px;"><?php if($info['ali_publickey']): ?>已上传<?php endif; ?></div>
													</div>
													<div class="layui-form-item">
														<label class="layui-form-label">支付宝根证书：</label>
														<div class="layui-input-inline" style="width:450px">
															<input style="float:left;" readonly="readonly" class="layui-input" type="text" name="info[ali_rootcert]" value="<?php echo $info['ali_rootcert']; ?>" id="ali_rootcert"/>
														</div>
														<button style="float:left;" type="button" class="layui-btn layui-btn-primary uploadfile" upload-input="ali_rootcert" upload-preview="rootcertPreview">上传</button>
														<div id="rootcertPreview" class="layui-form-mid" style="margin-left:20px;"><?php if($info['ali_rootcert']): ?>已上传<?php endif; ?></div>
													</div>
												</div>
											</div>
										</fieldset>
									<?php endif; ?>
									<fieldset class="layui-elem-field">
										<legend><?php echo t('余额'); ?>提现</legend>
										<div class="layui-field-box">
											<div class="layui-form-item">
												<label class="layui-form-label"><?php echo t('余额'); ?>提现：</label>
												<div class="layui-input-inline">
													<input type="radio" name="info[withdraw]" title="开启" value="1" <?php if($info['withdraw']==1): ?>checked<?php endif; ?> lay-filter="diandaSwitch"/>
													<input type="radio" name="info[withdraw]" title="关闭" value="0" <?php if($info['withdraw']==0): ?>checked<?php endif; ?> lay-filter="diandaSwitch"/>
												</div>
											</div>
											<div diandaSwitch="info[withdraw]" <?php if($info['withdraw']==0): ?>style="display:none"<?php endif; ?>>
												<div class="layui-form-item">
													<label class="layui-form-label">提现最小金额：</label>
													<div class="layui-input-inline" style="width:120px">
														<input type="text" name="info[withdrawmin]" value="<?php echo $info['withdrawmin']; ?>" class="layui-input"/>
													</div>
													<div class="layui-form-mid">元</div>
												</div>
												<div class="layui-form-item">
													<label class="layui-form-label">提现最高金额：</label>
													<div class="layui-input-inline" style="width:120px">
														<input type="text" name="info[withdrawmax]" value="<?php echo $info['withdrawmax']; ?>" class="layui-input"/>
													</div>
													<div class="layui-form-mid">元 </div>
													<div class="layui-form-mid layui-word-aux">单笔最高提现金额，大于0生效，0为无限制</div>
												</div>

											<?php if(getcustom('withdraw_mul')): ?>
												<div class="layui-form-item">
													<label class="layui-form-label">提现倍数：</label>
													<div class="layui-input-inline" style="width:120px">
														<input type="text" name="info[withdrawmul]" value="<?php echo $info['withdrawmul']; ?>" class="layui-input"/>
													</div>
													<div class="layui-form-mid">元 </div>
													<div class="layui-form-mid layui-word-aux">单笔提现整数倍，提现金额必须为设定值的整数倍，设置0或空不做限制</div>
												</div>
											<?php endif; if(!getcustom('money_withdraw_level_sxf')): ?>
												<div class="layui-form-item">
													<label class="layui-form-label">提现手续费：</label>
													<div class="layui-input-inline" style="width:120px">
														<input type="text" name="info[withdrawfee]" value="<?php echo $info['withdrawfee']; ?>" class="layui-input"/>
													</div>
													<div class="layui-form-mid">%</div>
												</div>
												<?php endif; if(getcustom('money_withdraw_level_sxf')): 
												$withdrawfee_level= json_decode($info['withdrawfee_level'],true);
												if(!$withdrawfee_level) $withdrawfee_level = [];
												 ?>
												<div class="layui-form-item">
													<label class="layui-form-label">提现手续费：</label>
													<?php foreach($levels as $level): ?>
													<div class="layui-input-inline layui-module-itemL" style="margin-bottom:10px">
														<div> <?php echo $level['name']; ?>(%) 	<?php echo $withdrawfee_level[$level['id']]['sxf']; ?></div>
														<input type="text" name="withdrawfee_level[<?php echo $level['id']; ?>][sxf]" class="layui-input" value="<?php echo $withdrawfee_level[$level['id']]['sxf']; ?>">
													</div>
													<?php endforeach; ?>
												</div>
												<?php endif; if(getcustom('comwithdrawdate')): ?>
													<div class="layui-form-item">
														<label class="layui-form-label"><?php echo t('余额'); ?>可提现日期：</label>
														<div class="layui-input-inline" style="width:120px">
															<input type="text" name="info[comwithdrawdate_money]" value="<?php echo $info['comwithdrawdate_money']; ?>" class="layui-input"/>
														</div>
														<div class="layui-form-mid layui-word-aux">0表示不限制，多个用英文逗号分隔，如：01,02 表示每月1号和2号可以提现</div>
													</div>
												<?php endif; if(getcustom('product_givetongzheng')): ?>
											<div class="layui-form-item">
												<label class="layui-form-label">提现到账<?php echo t('通证'); ?>：</label>
												<div class="layui-input-inline" style="width:120px">
													<input type="text" name="info[withdraw2tongzheng]" value="<?php echo $info['withdraw2tongzheng']; ?>" class="layui-input"/>
												</div>
												<div class="layui-form-mid">% </div>
												<div class="layui-form-mid layui-word-aux">例：设置20%，提现100实际打款80，自动转通证20</div>
											</div>
											<?php endif; if(getcustom('money_commission_withdraw_fenxiao')): ?>
											<div class="layui-form-item">
												<label class="layui-form-label">提现手续费分销：</label>
												<div class="layui-input-inline" style="width: 400px">
													<input type="radio" name="info[money_withdrawfee_fenxiao]" title="按照<?php echo t('会员'); ?>等级" value="0" <?php if($info['money_withdrawfee_fenxiao']==0): ?>checked<?php endif; ?> lay-filter="money_withdrawfee_fenxiao"/>

													<input type="radio" name="info[money_withdrawfee_fenxiao]" title="单独设置提成比例" value="1" <?php if($info['money_withdrawfee_fenxiao']==1): ?>checked<?php endif; ?> lay-filter="money_withdrawfee_fenxiao"/>
													<input type="radio" name="info[money_withdrawfee_fenxiao]" title="不参与" value="-1" <?php if($info['money_withdrawfee_fenxiao']==-1): ?>checked<?php endif; ?> lay-filter="money_withdrawfee_fenxiao"/>
												</div>
											</div>
											<?php 	
											$mwcommissiondata = json_decode($info['money_withdrawfee_commissiondata'],true);
											if(!$mwcommissiondata) $mwcommissiondata = [];
											 ?>	
											<div id="money_withdrawfee_commissiondata" <?php if($info['money_withdrawfee_fenxiao'] !=1): ?>style="display:none"<?php endif; ?>>
												<?php foreach($aglevellist as $level): ?>
												<div class="layui-form-item" >
													<label class="layui-form-label"><?php echo $level['name']; ?>：</label>
													<div class="layui-input-inline layui-module-itemL">
														<div> 一级(%) </div>
														<input type="text" name="mwcommissiondata[<?php echo $level['id']; ?>][commission1]" class="layui-input" value="<?php echo $mwcommissiondata[$level['id']]['commission1']; ?>">
													</div>
													<?php if($level['can_agent']>1): ?>
													<div class="layui-input-inline layui-module-itemL">
														<div>二级(%)</div>
														<input type="text" name="mwcommissiondata[<?php echo $level['id']; ?>][commission2]" class="layui-input" value="<?php echo $mwcommissiondata[$level['id']]['commission2']; ?>">
													</div>
													<?php endif; if($level['can_agent']>2): ?>
													<div class="layui-input-inline layui-module-itemL">
														<div>三级(%)</div>
														<input type="text" name="mwcommissiondata[<?php echo $level['id']; ?>][commission3]" class="layui-input" value="<?php echo $mwcommissiondata[$level['id']]['commission3']; ?>">
													</div>
													<?php endif; ?>
												</div>
												<?php endforeach; ?>
											</div>
											<?php endif; ?>
											</div>
										</div>
									</fieldset>

									<?php if(getcustom('plug_yuebao')): ?>
									<fieldset class="layui-elem-field">
										<legend><?php echo t('余额宝'); ?>收益提现</legend>
										<div class="layui-field-box">
											<div class="layui-form-item">
												<label class="layui-form-label"><?php echo t('余额宝'); ?>收益提现：</label>
												<div class="layui-input-inline">
													<input type="radio" name="info[yuebao_withdraw]" title="开启" value="1" <?php if($info['yuebao_withdraw']==1): ?>checked<?php endif; ?> lay-filter="yuebao_withdraw"/>
													<input type="radio" name="info[yuebao_withdraw]" title="关闭" value="0" <?php if($info['yuebao_withdraw']==0): ?>checked<?php endif; ?> lay-filter="yuebao_withdraw"/>
												</div>
											</div>
											<div id="yuebaowithdrawotherset" <?php if($info['yuebao_withdraw']==0): ?>style="display:none"<?php endif; ?>>
												<div class="layui-form-item">
													<label class="layui-form-label">提现最小金额：</label>
													<div class="layui-input-inline" style="width:120px">
														<input type="text" name="info[yuebao_withdrawmin]" value="<?php echo $info['yuebao_withdrawmin']; ?>" class="layui-input"/>
													</div>
													<div class="layui-form-mid">元</div>
												</div>
												<div class="layui-form-item">
													<label class="layui-form-label">提现手续费：</label>
													<div class="layui-input-inline" style="width:120px">
														<input type="text" name="info[yuebao_withdrawfee]" value="<?php echo $info['yuebao_withdrawfee']; ?>" class="layui-input"/>
													</div>
													<div class="layui-form-mid">%</div>
												</div>
											</div>
										</div>
									</fieldset>
									<?php endif; ?>

									<fieldset class="layui-elem-field">
										<legend><?php echo t('佣金'); ?>提现</legend>
										<div class="layui-field-box">
											<div class="layui-form-item">
												<label class="layui-form-label"><?php echo t('佣金'); ?>提现：</label>
												<div class="layui-input-inline">
													<input type="radio" name="info[comwithdraw]" title="开启" value="1" <?php if($info['comwithdraw']==1): ?>checked<?php endif; ?> lay-filter="comwithdraw"/>
													<input type="radio" name="info[comwithdraw]" title="关闭" value="0" <?php if($info['comwithdraw']==0): ?>checked<?php endif; ?> lay-filter="comwithdraw"/>
												</div>
											</div>
											<!--佣金提现内容  start-->
											<div id="comwithdrawotherset" <?php if($info['comwithdraw']==0): ?>style="display:none"<?php endif; ?>>

												<?php if(getcustom('commission_autowithdraw')): ?>
													<div class="layui-form-item">
														<label class="layui-form-label"><?php echo t('佣金'); ?>自动提现：</label>
														<div class="layui-input-inline">
															<input type="radio" name="info[commission_autowithdraw]" title="开启" value="1" <?php if($info['commission_autowithdraw']==1): ?>checked<?php endif; ?>/>
															<input type="radio" name="info[commission_autowithdraw]" title="关闭" value="0" <?php if($info['commission_autowithdraw']==0): ?>checked<?php endif; ?>/>
														</div>
														<div class="layui-form-mid layui-word-aux">开启后<?php echo t('佣金'); ?>结算后将自动通过企业付款到零钱，需开通企业付款到零钱功能</div>
													</div>
												<?php endif; if(getcustom('commission_withdraw_need_score')): ?>
													<div class="layui-form-item">
														<label class="layui-form-label"><?php echo t('佣金'); ?>提现消耗<?php echo t('积分'); ?>：</label>
														<div class="layui-input-inline">
															<input type="radio" name="info[comwithdraw_need_score]" title="开启" value="1" <?php if($info['comwithdraw_need_score']==1): ?>checked<?php endif; ?> />
															<input type="radio" name="info[comwithdraw_need_score]" title="关闭" value="0" <?php if($info['comwithdraw_need_score']==0): ?>checked<?php endif; ?> />
														</div>
													</div>
													<div class="layui-form-item">
														<label class="layui-form-label"><?php echo t('佣金'); ?><?php echo t('积分'); ?>兑换比例：</label>
														<div class="layui-input-inline" style="width: 120px;">
															<input type="number" class="layui-input" name="info[commission_score_exchange_num]" value="<?php echo $info['commission_score_exchange_num']; ?>">
														</div>
														<div class="layui-form-mid layui-word-aux"><?php echo t('积分'); ?>可兑换1<?php echo t('佣金'); ?></div>
													</div>
												<?php endif; ?>
												
												<div class="layui-form-item">
													<label class="layui-form-label">提现最小金额：</label>
													<div class="layui-input-inline" style="width:120px">
														<input type="text" name="info[comwithdrawmin]" value="<?php echo $info['comwithdrawmin']; ?>" class="layui-input"/>
													</div>
													<div class="layui-form-mid">元</div>
												</div>
												<div class="layui-form-item">
													<label class="layui-form-label">提现最高金额：</label>
													<div class="layui-input-inline" style="width:120px">
														<input type="text" name="info[comwithdrawmax]" value="<?php echo $info['comwithdrawmax']; ?>" class="layui-input"/>
													</div>
													<div class="layui-form-mid">元 </div>
													<div class="layui-form-mid layui-word-aux">单笔最高提现金额，大于0生效，0为无限制</div>
												</div>
											<?php if(getcustom('withdraw_mul')): ?>
												<div class="layui-form-item">
													<label class="layui-form-label">提现倍数：</label>
													<div class="layui-input-inline" style="width:120px">
														<input type="text" name="info[comwithdrawmul]" value="<?php echo $info['comwithdrawmul']; ?>" class="layui-input"/>
													</div>
													<div class="layui-form-mid">元 </div>
													<div class="layui-form-mid layui-word-aux">单笔提现整数倍，提现金额必须为设定值的整数倍，设置0或空不做限制</div>
												</div>
											<?php endif; if(getcustom('commission_duipeng_score_withdraw') && ($auth_data=='all' || in_array('CommissionWithdrawScorePower/authscorelog',$auth_data))): ?>
													<div class="layui-form-item">
														<label class="layui-form-label"><?php echo t('佣金'); ?>提现最小金额单位：</label>
														<div class="layui-input-inline" style="width:400px">
															<input type="radio" name="info[comwithdraw_integer_type]" title="不限" value="0" <?php if($info['comwithdraw_integer_type']==0): ?>checked<?php endif; ?> />
															<input type="radio" name="info[comwithdraw_integer_type]" title="个" value="1" <?php if($info['comwithdraw_integer_type']==1): ?>checked<?php endif; ?> />
															<input type="radio" name="info[comwithdraw_integer_type]" title="十" value="2" <?php if($info['comwithdraw_integer_type']==2): ?>checked<?php endif; ?> />
															<input type="radio" name="info[comwithdraw_integer_type]" title="百" value="3" <?php if($info['comwithdraw_integer_type']==3): ?>checked<?php endif; ?> />
															<input type="radio" name="info[comwithdraw_integer_type]" title="千" value="4" <?php if($info['comwithdraw_integer_type']==4): ?>checked<?php endif; ?> />
														</div>
														<div class="layui-form-mid layui-word-aux">提现最小金额 按 个，十，百，千 整数提现选择。
														</div>
													</div>
													<div class="layui-form-item">
														<label class="layui-form-label">提现积分与<?php echo t('佣金'); ?>对碰比例：</label>
														<div class="layui-input-inline" style="width: 120px;">
															<input type="number" class="layui-input" name="info[comwithdraw_duipeng_score_bili]" value="<?php echo $info['comwithdraw_duipeng_score_bili']; ?>">
														</div>
														<div class="layui-form-mid layui-word-aux">例：填2，提现积分:佣金提现=2:1 ，两个提现积分对比1 填写0则没有对碰比例
														</div>
													</div>
												<?php endif; if(!getcustom('commission_withdraw_level_sxf')): ?>
												<div class="layui-form-item">
													<label class="layui-form-label">提现手续费：</label>
													<div class="layui-input-inline" style="width:120px">
														<input type="text" name="info[comwithdrawfee]" value="<?php echo $info['comwithdrawfee']; ?>" class="layui-input"/>
													</div>
													<div class="layui-form-mid">%</div>
												</div>
												<?php endif; if(getcustom('commission_withdraw_level_sxf')): 
												$comwithdrawfee_level= json_decode($info['comwithdrawfee_level'],true);
												if(!$comwithdrawfee_level) $comwithdrawfee_level = [];
												 ?>
												<div class="layui-form-item">
													<label class="layui-form-label">提现手续费：</label>
													<?php foreach($levels as $level): ?>
													<div class="layui-input-inline layui-module-itemL" style="margin-bottom:10px">
														<div> <?php echo $level['name']; ?>(%) </div>
														<input type="text" name="comwithdrawfee_level[<?php echo $level['id']; ?>][sxf]" class="layui-input" value="<?php echo $comwithdrawfee_level[$level['id']]['sxf']; ?>">
													</div>
													<?php endforeach; ?>
						
												</div>
												<?php endif; if(getcustom('commission_withdraw_freeze')): ?>
												<div class="layui-form-item commission_frozen">
													  <label class="layui-form-label">提现冻结：</label>
													  <div style="float:left; margin-left: 0;">
														  <div class="commission_frozen_row">
															  <input type="checkbox" name="info[comwithdraw_freeze][]" value="1" <?php if(in_array('1',explode(',',$info['comwithdraw_freeze']))): ?>checked<?php endif; ?> title="" lay-skin="primary">
															  <div style="float:left;">
																  <div class="checkbox-text">累计提现 <div class="layui-input-inline" style="width: 100px;float: none;">
																	  <input type="text" name="info[comwithdraw_totalmoney]" class="layui-input" value="<?php echo $info['comwithdraw_totalmoney']; ?>">
																	  </div>元
																  </div>
															  </div>
														  </div>
													</div>
											    </div>
												<div class="layui-form-item commission_frozen">
													  <label class="layui-form-label">解冻条件：</label>
													  <div style="float:left; margin-left: 0;">
														  <div class="commission_frozen_row">
															  <input type="checkbox" name="info[jiedong_condtion][]" value="1" <?php if(in_array('1',explode(',',$info['jiedong_condtion']))): ?>checked<?php endif; ?> title="" lay-skin="primary">
															  <div style="float:left;">
																  <div class="checkbox-text">购买商品ID <div class="layui-input-inline" style="float: none;">
																	  <input type="text" name="info[buy_proid]" class="layui-input" value="<?php echo $info['buy_proid']; ?>">
																	  </div>
																  </div>

																    <div class="checkbox-text">数量 <div class="layui-input-inline" style="width: 100px;float: none;">
																	  <input type="text" name="info[buypro_num]" class="layui-input" value="<?php echo $info['buypro_num']; ?>">
																	  </div>
																  </div>
															  </div>
															
														  </div>
													</div>
													<div class="layui-form-mid layui-word-aux layui-clear">商品ID：多个ID用英文逗号分隔，对应的购买数量也用英文逗号分隔，用法1：多个商品ID多个购买数量表示任一商品购买数量≥对应数量即满足条件，用法2：多个商品ID一个购买数量表示所有商品的购买总量≥数量即满足条件</div>
											  </div>
												<div class="layui-form-item">
													<label class="layui-form-label">是否解冻：</label>
													<div class="layui-input-inline">
														<input type="radio" name="info[comwithdraw_isjiedong]" title="开启" value="1" <?php if($info['comwithdraw_isjiedong']==1): ?>checked<?php endif; ?>/>
														<input type="radio" name="info[comwithdraw_isjiedong]" title="关闭" value="0" <?php if($info['comwithdraw_isjiedong']==0): ?>checked<?php endif; ?> />
													</div>
													<div class="layui-form-mid layui-word-aux">开启后解冻符合条件的<?php echo t('会员'); ?></div>
												</div>
											<?php endif; if(getcustom('comwithdrawdate')): ?>
													<div class="layui-form-item">
														<label class="layui-form-label"><?php echo t('佣金'); ?>可提现日期：</label>
														<div class="layui-input-inline" style="width:120px">
															<input type="text" name="info[comwithdrawdate]" value="<?php echo $info['comwithdrawdate']; ?>" class="layui-input"/>
														</div>
														<div class="layui-form-mid layui-word-aux">0表示不限制，多个用英文逗号分隔，如：01,02 表示每月1号和2号可以提现</div>
													</div>
												<?php endif; if(getcustom('fengdanjiangli')): ?>
													<div class="layui-form-item">
														<label class="layui-form-label">可提现比例：</label>
														<div class="layui-input-inline" style="width:120px">
															<input type="text" name="info[comwithdrawbl]" value="<?php echo $info['comwithdrawbl']; ?>" class="layui-input"/>
														</div>
														<div class="layui-form-mid">%</div>
														<div class="layui-form-mid layui-word-aux">可提现比例不是100%时,不可提现部分在提现时直接转换为余额</div>
													</div>
												<?php endif; if(getcustom('product_givetongzheng')): ?>
											<div class="layui-form-item">
												<label class="layui-form-label">提现到账<?php echo t('通证'); ?>：</label>
												<div class="layui-input-inline" style="width:120px">
													<input type="text" name="info[commissionwithdraw2tongzheng]" value="<?php echo $info['commissionwithdraw2tongzheng']; ?>" class="layui-input"/>
												</div>
												<div class="layui-form-mid">% </div>
												<div class="layui-form-mid layui-word-aux">例：设置20%，提现100实际打款80，自动转通证20</div>
											</div>
											<?php endif; if(getcustom('money_commission_withdraw_fenxiao')): ?>
											<div class="layui-form-item">
												<label class="layui-form-label">提现手续费分销：</label>
												<div class="layui-input-inline" style="width: 400px">
													<input type="radio" name="info[commission_withdrawfee_fenxiao]" title="按照<?php echo t('会员'); ?>等级" value="0" <?php if($info['commission_withdrawfee_fenxiao']==0): ?>checked<?php endif; ?> lay-filter="commission_withdrawfee_fenxiao"/>
													<input type="radio" name="info[commission_withdrawfee_fenxiao]" title="单独设置提成比例" value="1" <?php if($info['commission_withdrawfee_fenxiao']==1): ?>checked<?php endif; ?> lay-filter="commission_withdrawfee_fenxiao"/>
													<input type="radio" name="info[commission_withdrawfee_fenxiao]" title="不参与" value="-1" <?php if($info['commission_withdrawfee_fenxiao']==-1): ?>checked<?php endif; ?> lay-filter="commission_withdrawfee_fenxiao"/>
												</div>
											</div>
											<?php 
											$cwcommissiondata = json_decode($info['commission_withdrawfee_commissiondata'],true);
											if(!$cwcommissiondata) $cwcommissiondata = [];
											 ?>
											<div id="commission_withdrawfee_commissiondata" <?php if($info['commission_withdrawfee_fenxiao'] !=1): ?>style="display:none"<?php endif; ?>>
												<?php foreach($aglevellist as $level): ?>
												<div class="layui-form-item" >
													<label class="layui-form-label"><?php echo $level['name']; ?>：</label>
													<div class="layui-input-inline layui-module-itemL">
														<div> 一级(%) </div>
														<input type="text" name="cwcommissiondata[<?php echo $level['id']; ?>][commission1]" class="layui-input" value="<?php echo $cwcommissiondata[$level['id']]['commission1']; ?>">
													</div>
													<?php if($level['can_agent']>1): ?>
													<div class="layui-input-inline layui-module-itemL">
														<div>二级(%)</div>
														<input type="text" name="cwcommissiondata[<?php echo $level['id']; ?>][commission2]" class="layui-input" value="<?php echo $cwcommissiondata[$level['id']]['commission2']; ?>">
													</div>
													<?php endif; if($level['can_agent']>2): ?>
													<div class="layui-input-inline layui-module-itemL">
														<div>三级(%)</div>
														<input type="text" name="cwcommissiondata[<?php echo $level['id']; ?>][commission3]" class="layui-input" value="<?php echo $cwcommissiondata[$level['id']]['commission3']; ?>">
													</div>
													<?php endif; ?>
												</div>
												<?php endforeach; ?>
											</div>
											<?php endif; ?>
											</div>
											<?php if(getcustom('commission_withdraw_score_conversion')): ?>
											<div class="layui-form-item">
												<label class="layui-form-label"><?php echo t('佣金'); ?>提现到账比例：</label>
												<div class="layui-input-inline layui-module-itemL">
													<div><?php echo t('佣金'); ?>到账</div>
													<input type="text" name="info[commission_conversion]" class="layui-input" value="<?php echo $info['commission_conversion']; ?>">
													%
												</div>
												<div class="layui-input-inline layui-module-itemL">
													<div><?php echo t('积分'); ?>到账</div>
													<input type="text" name="info[score_conversion]" class="layui-input" value="<?php echo $info['score_conversion']; ?>">
													%
												</div>
												<div class="layui-input-inline layui-module-itemL">
													<div><?php echo t('积分'); ?>到账</div>
													<input type="text" name="info[score_doubling]" class="layui-input" value="<?php echo $info['score_doubling']; ?>">
													倍
												</div>
												<div class="layui-form-mid layui-word-aux"><?php echo t('积分'); ?>到账时按1:1自动转成<?php echo t('积分'); ?>;<?php echo t('积分'); ?>可设置到账倍数 0表示不翻倍</div>
											</div>
											<?php endif; if(getcustom('commission_withdraw_buy_product')): ?>
											<div class="layui-form-item commission_frozen">
												<label class="layui-form-label">提现购买商品：</label>
												<div style="float:left; margin-left: 0;">
													<div class="commission_frozen_row">
														<div class="checkbox-text">购买商品ID
															<div class="layui-input-inline" style="float: none;">
																<input type="text" name="info[withdraw_buy_proid]" class="layui-input" value="<?php echo $info['withdraw_buy_proid']; ?>">
															</div>
														</div>
														<div class="checkbox-text">数量
															<div class="layui-input-inline" style="width: 100px;float: none;">
																<input type="text" name="info[withdraw_buy_pro_num]" class="layui-input" value="<?php echo $info['withdraw_buy_pro_num']; ?>">
															</div>
														</div>
													</div>
												</div>
												<div class="layui-form-mid layui-word-aux layui-clear">商品ID：多个ID用英文逗号分隔，对应的购买数量也用英文逗号分隔，用法1：多个商品ID多个购买数量表示任一商品购买数量≥对应数量即满足条件，用法2：多个商品ID一个购买数量表示所有商品的购买总量≥数量即满足条件</div>
											</div>
											<?php endif; ?>
											<!--佣金提现内容  end-->
										</div>
									</fieldset>
									<?php if(getcustom('mendian_money_transfer')): ?>
									<!--门店余额  start-->
									<fieldset class="layui-elem-field">
										<legend>门店余额</legend>
											<div class="layui-form-item">
												<label class="layui-form-label">余额转账：</label>
												<div class="layui-input-inline">
													<input type="radio" name="info[mendian_money_transfer]" value="1" title="开启" <?php if(!$info['id'] || $info['mendian_money_transfer']==1): ?>checked<?php endif; ?>>
													<input type="radio" name="info[mendian_money_transfer]" value="0" title="关闭" <?php if($info['id'] && $info['mendian_money_transfer']==0): ?>checked<?php endif; ?>>
												</div>
											</div>
									</fieldset>
									<!--门店余额  end-->
									<?php endif; if(getcustom('commission2scorepercent')): ?>
									  <div class="layui-form-item">
										  <label class="layui-form-label"><?php echo t('佣金'); ?>到<?php echo t('积分'); ?>账户<br>比例：</label>
										  <div class="layui-input-inline" style="width:120px">
											  <input type="text" name="info[commission2scorepercent]" value="<?php echo $info['commission2scorepercent']; ?>" class="layui-input"/>
										  </div>
										  <div class="layui-form-mid">%</div>
										  <div class="layui-form-mid layui-word-aux">产生<?php echo t('佣金'); ?>时其中一部分按设置比例自动转成<?php echo t('积分'); ?></div>
									  </div>
									  <?php endif; if(getcustom('commission2moneypercent')): ?>
									  <div class="layui-form-item">
										  <label class="layui-form-label"><?php echo t('佣金'); ?>到<?php echo t('余额'); ?>账户<br>比例：</label>
										  <div class="layui-input-inline layui-module-itemL">
											  <div>首单</div>
											  <input type="text" name="info[commission2moneypercent1]" class="layui-input" value="<?php echo $info['commission2moneypercent1']; ?>">
											  %
										  </div>
										  <div class="layui-input-inline layui-module-itemL">
											  <div>复购</div>
											  <input type="text" name="info[commission2moneypercent2]" class="layui-input" value="<?php echo $info['commission2moneypercent2']; ?>">
											  %
										  </div>
										  <div class="layui-form-mid layui-word-aux">设置后商城订单产生<?php echo t('佣金'); ?>时就有一部分比例自动转成<?php echo t('余额'); ?></div>
									  </div>
									  <?php endif; if(getcustom('member_recharge_yj')): ?>
										<fieldset class="layui-elem-field">
											<legend>充值业绩提现</legend>
											<div class="layui-field-box">
												<div class="layui-form-item">
													<label class="layui-form-label">充值业绩提现：</label>
													<div class="layui-input-inline">
														<input type="radio" name="info[rechargeyj_withdraw]" title="开启" value="1" <?php if($info['rechargeyj_withdraw']==1): ?>checked<?php endif; ?> lay-filter="rechargeyj_withdraw"/>
														<input type="radio" name="info[rechargeyj_withdraw]" title="关闭" value="0" <?php if($info['rechargeyj_withdraw']==0): ?>checked<?php endif; ?> lay-filter="rechargeyj_withdraw"/>
													</div>
												</div>
												<!--充值业绩提现 start-->
												<div id="rechargeyjwithdrawotherset" <?php if($info['rechargeyj_withdraw']==0): ?>style="display:none"<?php endif; ?>>
													<div class="layui-form-item">
														<label class="layui-form-label">提现最小金额：</label>
														<div class="layui-input-inline" style="width:120px">
															<input type="text" name="info[rechargeyj_withdrawmin]" value="<?php echo $info['rechargeyj_withdrawmin']; ?>" class="layui-input"/>
														</div>
														<div class="layui-form-mid">元</div>
													</div>
													<div class="layui-form-item">
														<label class="layui-form-label">提现手续费：</label>
														<div class="layui-input-inline" style="width:120px">
															<input type="text" name="info[rechargeyj_withdrawfee]" value="<?php echo $info['rechargeyj_withdrawfee']; ?>" class="layui-input"/>
														</div>
														<div class="layui-form-mid">%</div>
													</div>
												</div>
												<!--充值业绩提现 end-->
											</div>
										</fieldset>
									<?php endif; ?>
									<!--提现部分 end-->


									<fieldset class="layui-elem-field layui-field-title">
										<legend>发票</legend>
									</fieldset>
									<div class="layui-form-item">
										<label class="layui-form-label">发票开关：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[invoice]" value="1" <?php if($info['invoice']==1): ?>checked<?php endif; ?> title="开启" lay-filter="invoice"/>
											<input type="radio" name="info[invoice]" value="0" <?php if($info['invoice']==0): ?>checked<?php endif; ?> title="关闭" lay-filter="invoice"/>
										</div>
									</div>
  									<div id="invoice-set" <?php if($info['invoice']==0): ?>style="display:none"<?php endif; ?>>
									  <div class="layui-form-item">
										  <label class="layui-form-label">发票类型：</label>
										  <div class="layui-input-inline" style="width:300px">
											  <input type="checkbox" name="info[invoice_type][]" value="1" title="普通发票" lay-skin="primary" <?php if(in_array('1',$info['invoice_type'])): ?>checked<?php endif; ?>/>
											  <input type="checkbox" name="info[invoice_type][]" value="2" title="增值税专用发票" lay-skin="primary" <?php if(in_array('2',$info['invoice_type'])): ?>checked<?php endif; ?>/>
										  </div>
									  </div>
									<?php if(getcustom('invoice_rate')): ?>
									  <div class="layui-form-item">
										  <label class="layui-form-label">发票税点：</label>
										  <div class="layui-input-inline" style="width:120px">
											  <input type="text" name="info[invoice_rate]" value="<?php echo $info['invoice_rate']; ?>" class="layui-input"/>
										  </div>
										  <div class="layui-form-mid">%</div>
									  </div>
  									<?php endif; ?>
									</div>
  									<?php if(getcustom('pay_transfer')): ?>
									<fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
										<legend><?php echo t('转账汇款'); ?></legend>
									</fieldset>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('转账汇款'); ?>支付：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[pay_transfer]" title="开启" value="1" <?php if($info['pay_transfer']==1): ?>checked<?php endif; ?> lay-filter="pay_transfer"/>
											<input type="radio" name="info[pay_transfer]" title="关闭" value="0" <?php if($info['pay_transfer']==0): ?>checked<?php endif; ?> lay-filter="pay_transfer"/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">开启后<?php echo t('会员'); ?>下单时可使用此支付方式，后台需审核并处理订单，仅平台支持</div>
									</div>
									<div id="pay_transfer_set" <?php if($info['pay_transfer']==0): ?>style="display:none"<?php endif; ?>>
										<div class="layui-form-item">
											<label class="layui-form-label">户名：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="info[pay_transfer_account_name]" value="<?php echo $info['pay_transfer_account_name']; ?>" class="layui-input"/>
											</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label">账号：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="info[pay_transfer_account]" value="<?php echo $info['pay_transfer_account']; ?>" class="layui-input"/>
											</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label">开户行：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="info[pay_transfer_bank]" value="<?php echo $info['pay_transfer_bank']; ?>" class="layui-input"/>
											</div>
										</div>
										<?php if(getcustom('pay_transfer_qrcode')): ?>
										<div class="layui-form-item">
											<label class="layui-form-label">图片：</label>
											<input type="hidden" name="info[pay_transfer_qrcode]" value="<?php echo $info['pay_transfer_qrcode']; ?>" id="qrcodepics">
											<button style="float:left;" type="button" class="layui-btn layui-btn-primary" onclick="uploader(this,true)" upload-input="qrcodepics" upload-preview="qrcodepicsList" >批量上传</button>
											<div class="layui-form-mid layui-word-aux" style="margin-left:10px;">建议尺寸：640×640像素</div>
											<div id="qrcodepicsList" class="picsList-class-padding">
												<?php if($info['pay_transfer_qrcode']): $pics = explode(',',$info['pay_transfer_qrcode']); foreach($pics as $pic): ?>
													<div class="layui-imgbox">
														<a class="layui-imgbox-close" href="javascript:void(0)" onclick="$(this).parent().remove();getpicsval('qrcodepics','qrcodepicsList')" title="删除"><i class="layui-icon layui-icon-close-fill-opaque"></i></a>
														<span class="layui-imgbox-img"><img src="<?php echo $pic; ?>"></span>
													</div>
												<?php endforeach; ?><?php endif; ?>
											</div>
										</div>
										<?php endif; ?>

										<div class="layui-form-item">
											<label class="layui-form-label">提示信息：</label>
											<div class="layui-input-inline" style="width:400px">
												<textarea type="text" name="info[pay_transfer_desc]" class="layui-textarea" value=""><?php echo $info['pay_transfer_desc']; ?></textarea>
											</div>
											<div class="layui-form-mid layui-word-aux layui-clear">展示在转账信息下面的提示说明，如：汇款时请将订单号填写在附言，备注等栏位</div>
										</div>
										  <div class="layui-form-item">
											  <label class="layui-form-label">使用权限：</label>
											  <div class="layui-input-inline" style="width:800px">
												  <input type="checkbox" name="info[pay_transfer_gettj][]" value="-1" title="所有人" lay-skin="primary" <?php if(in_array('-1',$info['pay_transfer_gettj'])): ?>checked<?php endif; ?>/>
												  <?php foreach($levellist as $v): ?>
												  <input type="checkbox" name="info[pay_transfer_gettj][]" value="<?php echo $v['id']; ?>" title="<?php echo $v['name']; ?>" lay-skin="primary" <?php if(in_array($v['id'],$info['pay_transfer_gettj'])): ?>checked<?php endif; ?>/>
												  <?php endforeach; ?>
											  </div>
										  </div>
										<div class="layui-form-item">
											<label class="layui-form-label">转账审核：</label>
											<div class="layui-input-inline">
												<input type="radio" name="info[pay_transfer_check]" title="开启" value="1" <?php if($info['pay_transfer_check']==1): ?>checked<?php endif; ?> lay-filter="pay_transfer_check"/>
												<input type="radio" name="info[pay_transfer_check]" title="关闭" value="0" <?php if(!$info['pay_transfer_check'] || $info['pay_transfer_check']==0): ?>checked<?php endif; ?> lay-filter="pay_transfer_check"/>
											</div>
											<div class="layui-form-mid layui-word-aux layui-clear">开启后<?php echo t('会员'); ?>下单时需要先审核，才能使用转账汇款功能</div>
										</div>
									</div>
									<?php endif; if(getcustom('member_overdraft_money')): ?>
										<fieldset class="layui-elem-field layui-field-title"  style="margin-top: 30px;">
										  <legend><?php echo t('信用额度'); ?></legend>
										</fieldset>
										<div class="layui-form-item">
										  <label class="layui-form-label"><?php echo t('信用额度'); ?>支付：</label>
										  <div class="layui-input-inline">
											  <input type="radio" name="info[overdraft_moneypay]" title="开启" value="1" <?php if($info['overdraft_moneypay']==1): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[overdraft_moneypay]" title="关闭" value="0" <?php if($info['overdraft_moneypay']==0): ?>checked<?php endif; ?>/>
										  </div>
										</div>
										<!--<div class="layui-form-item">
										  <label class="layui-form-label">最大信用额度：</label>
										  <div class="layui-input-inline">
											  <input type="text" class="layui-input" name="info[overdraft_money_limit]" value="<?php echo (isset($info['overdraft_money_limit']) && ($info['overdraft_money_limit'] !== '')?$info['overdraft_money_limit']:0); ?>">
										  </div>
										  <div class="layui-form-mid layui-word-aux">当额度低于该额度时，不可使用。0表示不受限制。</div>
										</div>-->
  									<?php endif; if(getcustom('w7moneyscore')): ?>
									<fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
										<legend>微擎同步</legend>
									</fieldset>
									<div class="layui-form-item">
										<label class="layui-form-label">使用微擎平台余额积分：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[w7moneyscore]" title="关闭" value="0" <?php if($info['w7moneyscore']==0): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[w7moneyscore]" title="开启" value="1" <?php if($info['w7moneyscore']==1): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux">开启后余额和积分将使用微擎平台的<?php echo t('会员'); ?>余额积分，即和微擎平台的<?php echo t('会员'); ?>余额和积分同步，请谨慎操作</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">微擎平台或应用uniacid：</label>
										<div class="layui-input-inline">
											<input type="text" name="info[w7uniacid]" value="<?php echo $info['w7uniacid']; ?>" class="layui-input"/>
										</div>
										<div class="layui-form-mid layui-word-aux">根据uniacid同步信息，请谨慎操作</div>
									</div>
									<?php endif; if(getcustom('product_givetongzheng')): ?>
								  <fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
									  <legend><?php echo t('通证'); ?></legend>
								  </fieldset>
								  <div class="layui-form-item">
									  <label class="layui-form-label"><?php echo t('通证'); ?>抵扣：</label>
									  <div class="layui-input-inline layui-module-itemL">
										  <div>每<?php echo t('通证'); ?>抵扣</div>
										  <input type="text" name="info[tongzheng2money]" class="layui-input" value="<?php echo $info['tongzheng2money']; ?>">
										  元
									  </div>
									  <div class="layui-form-mid layui-word-aux">付款时一个<?php echo t('通证'); ?>可抵扣多少元，0表示不开启<?php echo t('通证'); ?>抵扣</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label"></label>
									  <div class="layui-input-inline layui-module-itemL">
										  <div>最多抵扣百分比</div>
										  <input type="text" name="info[tongzhengdkmaxpercent]" class="layui-input" value="<?php echo $info['tongzhengdkmaxpercent']; ?>">
										  %
									  </div>
									  <div class="layui-form-mid layui-word-aux">选择使用<?php echo t('通证'); ?>抵扣时，最多可抵扣订单额的百分之多少</div>
								  </div>
  								<?php endif; ?>
								</div>
								<div class="layui-tab-item">
									<div class="layui-form-item">
										<label class="layui-form-label">消费赠<?php echo t('积分'); ?>：</label>
										<div class="layui-input-inline layui-module-itemL">
											<div>消费每满</div>
											<input type="text" name="info[scorein_money]" class="layui-input" value="<?php echo $info['scorein_money']; ?>">
											元
										</div>
										<div class="layui-input-inline layui-module-itemL">
											<div>赠送</div>
											<input type="text" name="info[scorein_score]" class="layui-input" value="<?php echo $info['scorein_score']; ?>">
											<?php echo t('积分'); ?>
										</div>
										<div class="layui-form-mid layui-word-aux">支付后积分即到账，退款时扣除，<?php echo t('积分'); ?>不足时可扣除为负值</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">充值赠<?php echo t('积分'); ?>：</label>
										<div class="layui-input-inline layui-module-itemL">
											<div>充值每满</div>
											<input type="text" name="info[scorecz_money]" class="layui-input" value="<?php echo $info['scorecz_money']; ?>">
											元
										</div>
										<div class="layui-input-inline layui-module-itemL">
											<div>赠送</div>
											<input type="text" name="info[scorecz_score]" class="layui-input" value="<?php echo $info['scorecz_score']; ?>">
											<?php echo t('积分'); ?>
										</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('积分'); ?>抵扣：</label>
										<div class="layui-input-inline layui-module-itemL">
											<div>每<?php echo t('积分'); ?>抵扣</div>
											<input type="text" name="info[score2money]" class="layui-input" value="<?php echo $info['score2money']; ?>">
											元
										</div>
										<div class="layui-form-mid layui-word-aux">付款时一个<?php echo t('积分'); ?>可抵扣多少元，0表示不开启<?php echo t('积分'); ?>抵扣</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label"></label>
										<div class="layui-input-inline layui-module-itemL">
											<div>最多抵扣百分比</div>
											<input type="text" name="info[scoredkmaxpercent]" class="layui-input" value="<?php echo $info['scoredkmaxpercent']; ?>">
											%
										</div>
										<div class="layui-form-mid layui-word-aux">选择使用<?php echo t('积分'); ?>抵扣时，最多可抵扣订单额的百分之多少</div>

										<?php if(getcustom('sysset_scoredkmaxpercent_memberset')): if($hasmemberset): ?>
											<div class="layui-form-item">
												<label class="layui-form-label"></label>
												<div class="layui-input-inline">
													<input type="checkbox" name="info[scoredkmaxpercent_memberset]" value="1" <?php if($info['scoredkmaxpercent_memberset']==1): ?>checked<?php endif; ?> lay-text="开启<?php echo t('会员'); ?>单独设置|关闭<?php echo t('会员'); ?>单独设置" lay-skin="switch" lay-filter="scoredkmaxpercent_memberset">
												</div>
												<div class="layui-form-mid layui-word-aux">开启单独设置后上方的最多抵扣百分比设置失效，将会按照<?php echo t('会员'); ?>单独设置来计算最多抵扣</div>
											</div>

											<div <?php if(!$info['scoredkmaxpercent_memberset'] || $info['scoredkmaxpercent_memberset']==0): ?>style="display:none"<?php endif; ?> id="scoredkmaxpercentMemberset">
												<?php foreach($memberscoredks as $memberscoredk): ?>
												<div class="layui-form-item">
													<label class="layui-form-label"><?php echo $memberscoredk['name']; ?>：</label>
													<div class="layui-input-inline layui-module-itemL">
														<div>最多抵扣百分比 </div>
														<input type="text" name="memberscoredks[<?php echo $memberscoredk['id']; ?>][scoredkmaxpercent]" class="layui-input" value="<?php echo $memberscoredk['scoredkmaxpercent']; ?>">
														%
													</div>
													<div class="layui-form-mid layui-word-aux">选择使用<?php echo t('积分'); ?>抵扣时，最多可抵扣订单额的百分之多少</div>
												</div>
												<?php endforeach; ?>
											</div>
											<?php endif; ?>
										<?php endif; ?>

									</div>
									<?php if(getcustom('shop_alone_give_score')): ?>
									<div class="layui-form-item">
									    <label class="layui-form-label">商品独立赠送<?php echo t('积分'); ?>：</label>
									    <div class="layui-input-inline" style="width:300px">
										    <input type="radio" name="info[alone_give_score]" title="固定积分" value="1" <?php if(!$info['id'] || $info['alone_give_score']==1): ?>checked<?php endif; ?>/>
										    <input type="radio" name="info[alone_give_score]" title="按实付比例赠送" value="2" <?php if($info['alone_give_score']==2): ?>checked<?php endif; ?>/>
									    </div>
									    <div class="layui-form-mid layui-word-aux">按实付比例赠送：赠送积分数量*（支付金额/商品金额）;赠送<?php echo t('积分'); ?>为0时则不赠送<?php echo t('积分'); ?></div>
									</div>
                                    <div class="layui-form-item">
									    <label class="layui-form-label">买单按实际比例赠送<?php echo t('积分'); ?>：</label>
									    <div class="layui-input-inline" style="width:300px">
										    <input type="radio" name="info[maidan_give_score]" title="固定积分" value="1" <?php if(!$info['id'] || $info['maidan_give_score']==1): ?>checked<?php endif; ?>/>
										    <input type="radio" name="info[maidan_give_score]" title="按实付比例赠送" value="2" <?php if($info['maidan_give_score']==2): ?>checked<?php endif; ?>/>
									    </div>
									    <div class="layui-form-mid layui-word-aux">按实付比例赠送：赠送积分数量*（实付金额/支付金额）;赠送<?php echo t('积分'); ?>为0时则不赠送<?php echo t('积分'); ?></div>
									</div>
									<?php endif; ?>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('积分'); ?>不抵扣运费：</label>
										<div class="layui-input-inline" style="width:70px">
											<input type="checkbox" name="info[scorebdkyf]" value="1" lay-text="开启|关闭" lay-skin="switch" <?php if($info['scorebdkyf']==1): ?>checked<?php endif; ?>>
										</div>
										<div class="layui-form-mid layui-word-aux">开启后<?php echo t('积分'); ?>不能抵扣运费</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('余额'); ?>支付送<?php echo t('积分'); ?>：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[score_from_moneypay]" title="开启" value="1" <?php if($info['score_from_moneypay']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[score_from_moneypay]" title="关闭" value="0" <?php if($info['score_from_moneypay']==0): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux">[营销]-[充值赠送]也可赠送<?php echo t('积分'); ?>，开启后<?php echo t('余额'); ?>支付会再次赠送</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('货到付款'); ?>支付送<?php echo t('积分'); ?>：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[score_from_xianxiapay]" title="开启" value="1" <?php if($info['score_from_xianxiapay']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[score_from_xianxiapay]" title="关闭" value="0" <?php if($info['score_from_xianxiapay']==0): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux">开启后使用[货到付款]支付会赠送<?php echo t('积分'); ?></div>
									</div>
									<?php if(getcustom('score_expire')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('积分'); ?>过期：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[score_expire_status]" title="开启" value="1" <?php if($info['score_expire_status']==1): ?>checked<?php endif; ?> lay-filter="diandaSwitch"/>
											<input type="radio" name="info[score_expire_status]" title="关闭" value="0" <?php if($info['score_expire_status']==0): ?>checked<?php endif; ?> lay-filter="diandaSwitch"/>
										</div>
										<div class="layui-form-mid layui-word-aux">开启后，达到过期天数的所有<?php echo t('积分'); ?>会过期，请谨慎操作</div>
									</div>
									<div diandaSwitch="info[score_expire_status]" <?php if($info['score_expire_status'] != 1): ?> style="display:none"<?php endif; ?>>
										<div class="layui-form-item">
											<label class="layui-form-label">过期天数：</label>
											<div class="layui-input-inline" style="width: 100px;">
												<input type="number" min="0" name="info[score_expire_days]" class="layui-input" value="<?php echo $info['score_expire_days']; ?>">
											</div>
											<div class="layui-form-mid layui-word-aux">0为不过期</div>
										</div>
									</div>
									<?php endif; if(getcustom('score_transfer') || getcustom('score_friend_transfer')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('积分'); ?>转赠：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[score_transfer]" title="开启" value="1" <?php if($info['score_transfer']==1): ?>checked<?php endif; ?> lay-filter="diandaSwitch"/>
											<input type="radio" name="info[score_transfer]" title="关闭" value="0" <?php if($info['score_transfer']==0): ?>checked<?php endif; ?> lay-filter="diandaSwitch"/>
										</div>
									</div>
									<div diandaSwitch="info[score_transfer]" <?php if($info['score_transfer'] != 1): ?> style="display:none"<?php endif; ?>>
										<div class="layui-form-item">
											<label class="layui-form-label"><?php echo t('积分'); ?>转赠范围：</label>
											<div class="layui-input-inline" style="width: 60%">
												<?php if(getcustom('score_transfer')): ?>
													<input type="radio" name="info[score_transfer_range]" title="全站" value="0" <?php if($info['score_transfer_range']==0): ?>checked<?php endif; ?>/>
													<input type="radio" name="info[score_transfer_range]" title="所有上下级" value="1" <?php if($info['score_transfer_range']==1): ?>checked<?php endif; ?>/>
												<?php endif; if(getcustom('score_friend_transfer')): ?>
													<input type="radio" name="info[score_transfer_range]" title="好友" value="2" <?php if($info['score_transfer_range']==2): ?>checked<?php endif; ?>/>
												<?php endif; ?>
											</div>
										</div>
										<div class="layui-form-item">
										  <label class="layui-form-label"><?php echo t('积分'); ?>转赠使用权限：</label>
										  <div class="layui-input-inline" style="width:800px">
											  <input type="checkbox" name="info[score_transfer_gettj][]" value="-1" title="所有人" lay-skin="primary" <?php if(in_array('-1',explode(',',$info['score_transfer_gettj']))): ?>checked<?php endif; ?>/>
											  <?php foreach($levellist as $v): ?>
											  <input type="checkbox" name="info[score_transfer_gettj][]" value="<?php echo $v['id']; ?>" title="<?php echo $v['name']; ?>" lay-skin="primary" <?php if(in_array($v['id'],explode('-1',$info['score_transfer_gettj']))): ?>checked<?php endif; ?>/>
											  <?php endforeach; ?>
										  </div>
										</div>
										<div class="layui-form-item">
										  <label class="layui-form-label"><?php echo t('积分'); ?>转赠支付密码：</label>
										  <div class="layui-input-inline">
											  <input type="radio" lay-filter="score_transfer_pwd" name="info[score_transfer_pwd]" title="开启" value="1" <?php if($info['score_transfer_pwd']==1): ?>checked<?php endif; ?>/>
											  <input type="radio" lay-filter="score_transfer_pwd" name="info[score_transfer_pwd]" title="关闭" value="0" <?php if($info['score_transfer_pwd']==0): ?>checked<?php endif; ?>/>
										  </div>
										</div>
									    <?php if(getcustom('score_transfer_sxf')): ?>
										<div class="layui-form-item">
										  <label class="layui-form-label"><?php echo t('积分'); ?>转赠手续费：</label>
										  <div class="layui-input-inline">
											  <input type="radio" name="info[score_transfer_sxf]"  lay-filter="diandaSwitch" title="开启" value="1" <?php if($info['score_transfer_sxf']==1): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[score_transfer_sxf]"  lay-filter="diandaSwitch" title="关闭" value="0" <?php if($info['score_transfer_sxf']==0): ?>checked<?php endif; ?>/>
										  </div>
										</div>
										<div class="layui-form-item" diandaSwitch="info[score_transfer_sxf]" <?php if($info['score_transfer_sxf'] != 1): ?> style="display:none"<?php endif; ?>>
										  <label class="layui-form-label">自动返还转赠<?php echo t('积分'); ?>：</label>
										  <div class="layui-input-inline">
											  <input type="text" name="info[autoclose_score_transfer]" lay-verify="required" lay-verType="tips" class="layui-input" value="<?php echo $info['autoclose_score_transfer']; ?>">
										  </div>
										  <div class="layui-form-mid">分钟</div>
										  <div class="layui-form-mid layui-word-aux" style="margin-left:10px;">下单后多久不支付，转赠<?php echo t('积分'); ?>会自动返还</div>
										</div>
  										<?php endif; if(!getcustom('score_transfer_sxf')): ?>
											<div class="layui-form-item">
												<label class="layui-form-label"><?php echo t('积分'); ?>转赠手续费比例</label>
												<div class="layui-input-inline">
													<input type="text" name="info[score_transfer_sxf_ratio]" lay-verify="required" lay-verType="tips" class="layui-input" value="<?php echo (isset($info['score_transfer_sxf_ratio']) && ($info['score_transfer_sxf_ratio'] !== '')?$info['score_transfer_sxf_ratio']: 0.00); ?>">
												</div>
												<div class="layui-form-mid">%</div>
												<div class="layui-form-mid layui-word-aux" style="margin-left:10px;"><?php echo t('积分'); ?>转赠时支付的手续费比例</div>
											</div>
										<?php endif; ?>
									</div>
									<?php endif; if(getcustom('score_withdraw')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('积分'); ?>提现到<?php echo t('余额'); ?>：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[score_withdraw]" title="开启" value="1" <?php if($info['score_withdraw']==1): ?>checked<?php endif; ?> lay-filter="score_withdraw"/>
											<input type="radio" name="info[score_withdraw]" title="关闭" value="0" <?php if($info['score_withdraw']==0): ?>checked<?php endif; ?> lay-filter="score_withdraw"/>
										</div>
										<div class="layui-form-mid layui-word-aux">允提<?php echo t('积分'); ?>提现，每日更新允提<?php echo t('积分'); ?></div>
									</div>
									<div id="score_withdraw_div" <?php if($info['score_withdraw']==0): ?>style="display:none"<?php endif; ?>>
										<div class="layui-form-item">
											<label class="layui-form-label">每日转允提<?php echo t('积分'); ?>比例：</label>
											<div class="layui-input-inline" style="width:120px">
												<input type="number" min="0" max="100" name="info[score_withdraw_percent_day]" value="<?php echo $info['score_withdraw_percent_day']; ?>" class="layui-input"/>
											</div>
											<div class="layui-form-mid">%</div>
											<div class="layui-form-mid layui-word-aux">每日普通<?php echo t('积分'); ?>转为可提现<?php echo t('积分'); ?>的百分比，不足1<?php echo t('积分'); ?>按1<?php echo t('积分'); ?>，大于1积分时，对小数位四舍五入取整数</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label"><?php echo t('积分'); ?>转<?php echo t('余额'); ?>换算比例：</label>
											<div class="layui-input-inline" style="width:120px">
												<input type="number" min="0" name="info[score_to_money_percent]" value="<?php echo $info['score_to_money_percent']; ?>" class="layui-input"/>
											</div>
											<div class="layui-form-mid layui-word-aux">如设置0.5，表示1<?php echo t('积分'); ?>换0.5元<?php echo t('余额'); ?></div>
										</div>
										<div class="layui-form-item">
										  <label class="layui-form-label">最低兑换金额：</label>
										  <div class="layui-input-inline" style="width:120px">
											  <input type="number" min="0" name="info[score_to_money_min_money]" value="<?php echo $info['score_to_money_min_money']; ?>" class="layui-input"/>
										  </div>
										  <div class="layui-form-mid layui-word-aux">兑换时的最低金额</div>
										</div>
									</div>
									<?php endif; if(getcustom('member_score_withdraw')): ?>
									<div class="layui-form-item">
									  <label class="layui-form-label"><?php echo t('积分'); ?>提现到<?php echo t('余额'); ?>：</label>
									  <div class="layui-input-inline">
										  <input type="radio" name="info[member_score_withdraw]" title="开启" value="1" <?php if($info['member_score_withdraw']==1): ?>checked<?php endif; ?> lay-filter="member_score_withdraw"/>
										  <input type="radio" name="info[member_score_withdraw]" title="关闭" value="0" <?php if($info['member_score_withdraw']==0): ?>checked<?php endif; ?> lay-filter="member_score_withdraw"/>
									  </div>
									</div>
									 <div id="member_score_withdraw_div" <?php if($info['member_score_withdraw']==0): ?>style="display:none"<?php endif; ?>>
										<div class="layui-form-item">
											<label class="layui-form-label"><?php echo t('积分'); ?>转<?php echo t('余额'); ?>比例：</label>
											<div class="layui-input-inline" style="width:120px">
												<input type="number" min="0" name="info[member_score_to_money_ratio]" value="<?php echo $info['member_score_to_money_ratio']; ?>" class="layui-input"/>
											</div>
											<div class="layui-form-mid layui-word-aux">如设置0.5，表示1<?php echo t('积分'); ?>换0.5元<?php echo t('余额'); ?></div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label">最低兑换金额：</label>
											<div class="layui-input-inline" style="width:120px">
												<input type="number" min="0" name="info[member_score_to_money_min]" value="<?php echo $info['member_score_to_money_min']; ?>" class="layui-input"/>
											</div>
											<div class="layui-form-mid layui-word-aux">兑换时的最低金额</div>
										</div>
									
									</div>
									<?php endif; if(getcustom('score_to_money_auto')): ?>
									  <div class="layui-form-item">
										  <label class="layui-form-label"><?php echo t('积分'); ?>自动释放到<?php echo t('余额'); ?>：</label>
										  <div class="layui-input-inline">
											  <input type="radio" name="info[score_to_money_auto]" title="开启" value="1" <?php if($info['score_to_money_auto']==1): ?>checked<?php endif; ?> lay-filter="score_to_money"/>
											  <input type="radio" name="info[score_to_money_auto]" title="关闭" value="0" <?php if($info['score_to_money_auto']==0): ?>checked<?php endif; ?> lay-filter="score_to_money"/>
										  </div>
									  </div>
									  <div id="score_to_money_div" <?php if($info['score_to_money_auto']==0): ?>style="display:none"<?php endif; ?>>
									  <div class="layui-form-item">
										  <label class="layui-form-label">每日<?php echo t('积分'); ?>自动释放到<?php echo t('余额'); ?>比例：</label>
										  <div class="layui-input-inline" style="width:120px">
											  <input type="number" min="0" max="100" name="info[score_to_money_auto_day]" value="<?php echo $info['score_to_money_auto_day']; ?>" class="layui-input"/>
										  </div>
										  <div class="layui-form-mid">%</div>
										  <div class="layui-form-mid layui-word-aux">每日普通<?php echo t('积分'); ?>释放到<?php echo t('余额'); ?>的百分比</div>
									  </div>
									  <div class="layui-form-item">
										  <label class="layui-form-label">每日<?php echo t('积分'); ?>释放到<?php echo t('余额'); ?>换算比例：</label>
										  <div class="layui-input-inline" style="width:120px">
											  <input type="number" min="0" name="info[score_to_money_auto_percent]" value="<?php echo $info['score_to_money_auto_percent']; ?>" class="layui-input"/>
										  </div>
										  <div class="layui-form-mid layui-word-aux">如设置0.5，表示1<?php echo t('积分'); ?>换0.5元<?php echo t('余额'); ?></div>
									  </div>
									  </div>
									  <?php endif; if(getcustom('score_weishu')): ?>
									  <div class="layui-form-item">
										  <label class="layui-form-label"><?php echo t('积分'); ?>小数位数：</label>
										  <div class="layui-input-inline" style="width:120px">
											  <input type="number" min="0" name="info[score_weishu]" value="<?php echo $info['score_weishu']; ?>" class="layui-input"/>
										  </div>
										  <div class="layui-form-mid layui-word-aux">系统中积分保留小数位数，最高支持三位</div>
									  </div>
  									<?php endif; if(getcustom('mendian_hexiao_money_to_score')): ?>
										<div class="layui-form-item">
										  <label class="layui-form-label">门店核销商品金额转<?php echo t('积分'); ?>：</label>
										  <div class="layui-input-inline">
											  <input type="radio" name="info[money_to_score]" title="开启" value="1" <?php if($info['money_to_score']==1): ?>checked<?php endif; ?> lay-filter="money_to_score"/>
											  <input type="radio" name="info[money_to_score]" title="关闭" value="0" <?php if($info['money_to_score']==0): ?>checked<?php endif; ?> lay-filter="money_to_score"/>
										  </div>
										</div>
		
										<div class="layui-form-item" id="mendian_hexiao_money_to_score"  <?php if($info['money_to_score']==0): ?>style="display:none"<?php endif; ?>>
											<label class="layui-form-label">转换比例：</label>
											<div class="layui-input-inline" style="width:120px">
												<input type="number" min="0" name="info[money_to_score_bili]" value="<?php echo $info['money_to_score_bili']; ?>" class="layui-input"/>
											</div>
											<div class="layui-form-mid layui-word-aux">% 商品金额/<?php echo t('积分'); ?>抵扣*转换比例，计算结果向下取整数</div>
										</div>
									<?php endif; if(getcustom('score_stacking_give_set')): ?>
										<div class="layui-form-item">
											<label class="layui-form-label"><?php echo t('积分'); ?>赠送规则：</label>
											<div class="layui-input-inline" style="width:650px">
												<input type="radio" name="info[score_stacking_give_set]" title="消费和购买商品叠加赠送<?php echo t('积分'); ?>" value="0" <?php if($info['score_stacking_give_set']==0): ?>checked<?php endif; ?>/>
												<input type="radio" name="info[score_stacking_give_set]" title="只按系统设置消费送<?php echo t('积分'); ?>" value="1" <?php if($info['score_stacking_give_set']==1): ?>checked<?php endif; ?>/>
												<input type="radio" name="info[score_stacking_give_set]" title="只按商品独立设置送<?php echo t('积分'); ?>" value="2" <?php if($info['score_stacking_give_set']==2): ?>checked<?php endif; ?>/>
											</div>
											<div class="layui-form-mid layui-word-aux"></div>
										</div>
									<?php endif; if(getcustom('member_shopscore') && $membershopscoreauth): ?>
									 	<fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
											<legend><?php echo t('产品积分'); ?></legend>
										</fieldset>
										<div class="layui-form-item">
											<label class="layui-form-label">状态：</label>
											<div class="layui-input-inline">
												<input type="radio" name="info[shopscorestatus]" value="1" <?php if($info['shopscorestatus']==1): ?>checked<?php endif; ?> title="开启"/>
												<input type="radio" name="info[shopscorestatus]" value="0" <?php if($info['shopscorestatus']==0): ?>checked<?php endif; ?> title="关闭"/>
											</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label"><?php echo t('产品积分'); ?>抵扣：</label>
											<div class="layui-input-inline layui-module-itemL">
												<div>每<?php echo t('产品积分'); ?>抵扣</div>
												<input type="text" name="info[shopscore2money]" class="layui-input" value="<?php echo $info['shopscore2money']; ?>">
												元
											</div>
											<div class="layui-form-mid layui-word-aux">商城商品付款时一个<?php echo t('产品积分'); ?>可抵扣多少元，0表示不开启<?php echo t('产品积分'); ?>抵扣</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label"></label>
											<div class="layui-input-inline layui-module-itemL">
												<div>最多抵扣百分比</div>
												<input type="text" name="info[shopscoredkmaxpercent]" class="layui-input" value="<?php echo $info['shopscoredkmaxpercent']; ?>">
												%
											</div>
											<div class="layui-form-mid layui-word-aux">商城商品选择使用<?php echo t('产品积分'); ?>抵扣时，最多可抵扣订单额的百分之多少；大于0时起效，最大为100%</div>
										</div>
									<?php endif; ?>
								</div>
								<div class="layui-tab-item form-label-w10">
									<fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
										<legend>分销</legend>
									</fieldset>
									<div class="layui-form-item">
										<label class="layui-form-label">分销结算方式：</label>
										<div class="layui-input-inline" style="width:400px">
											<input type="radio" name="info[fxjiesuantype]" title="按商品价格" value="0" <?php if($info['fxjiesuantype']==0): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[fxjiesuantype]" title="按成交价格" value="1" <?php if($info['fxjiesuantype']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[fxjiesuantype]" title="按销售利润" value="2" <?php if($info['fxjiesuantype']==2): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">按商品价格结算：商品价格×提成百分比；按成交价格结算：成交价格×提成百分比,即扣除<?php echo t('会员'); ?>折扣、满减优惠、<?php echo t('优惠券'); ?>抵扣及<?php echo t('积分'); ?>抵扣后计算分销；按销售利润结算：（成交价格-商品成本）×提成百分比</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">分销结算时间：</label>
										<div class="layui-input-inline" style="width:250px">
											<input type="radio" name="info[fxjiesuantime]" title="确认收货后" value="0" <?php if($info['fxjiesuantime']==0): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[fxjiesuantime]" title="付款后" value="1" <?php if($info['fxjiesuantime']==1): ?>checked<?php endif; ?>/>
										</div>
										<?php if(getcustom('fxjiesuantime_perweek')): ?>
										<div class="layui-form-mid">周</div>
										<div class="layui-input-inline" style="width:100px">
											<input type="text" name="info[fxjiesuantime_delaydays]" class="layui-input" value="<?php echo $info['fxjiesuantime_delaydays']; ?>">
										</div>
										<div class="layui-form-mid">结算</div>
										<?php endif; if(!getcustom('fxjiesuantime_perweek')): ?>
										<div class="layui-input-inline" style="width:100px">
											<input type="text" name="info[fxjiesuantime_delaydays]" class="layui-input" value="<?php echo $info['fxjiesuantime_delaydays']; ?>">
										</div>
										<div class="layui-form-mid">天结算</div>
										<?php endif; ?>
										<div class="layui-form-mid layui-word-aux layui-clear">注意：0代表付款或确认收货后立即结算，如果产生退款，已发放的<?php echo t('佣金'); ?>不会扣除</div>
									</div>
									<?php if(getcustom('commission_jicha')): if($auth_data=='all' || in_array('commission_jicha',$auth_data)): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">分销级差：</label>
										<div class="layui-input-inline" style="width:160px">
											<input type="radio" name="info[fx_differential]" title="是" value="1" <?php if($info['fx_differential']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[fx_differential]" title="否" value="0" <?php if($info['fx_differential']==0): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">开启后分销将按照级差进行计算，仅限按金额和比例方式</div>
									</div>
									<?php endif; ?>
									<?php endif; if(getcustom('commission_jinsuo')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">分销紧缩：</label>
										<div class="layui-input-inline" style="width:160px">
											<input type="radio" name="info[fx_jinsuo]" title="开启" value="1" <?php if($info['fx_jinsuo']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[fx_jinsuo]" title="关闭" value="0" <?php if($info['fx_jinsuo']==0): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">开启后分销将紧缩掉分销"提成比例"并且"买单提成积分"为0的用户</div>
									</div>
									<?php endif; if(getcustom('commission_butie')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo t('分销补贴'); ?>：</label>
										<div class="layui-input-inline" style="width:160px">
											<input type="radio" name="info[fx_butie_type]" title="按周" value="1" <?php if($info['fx_butie_type']==1): ?>checked<?php endif; ?> lay-filter="fx_butie_type"/>
											<input type="radio" name="info[fx_butie_type]" title="按月" value="0" <?php if($info['fx_butie_type']==0): ?>checked<?php endif; ?> lay-filter="fx_butie_type"/>
										</div>
										<div class="layui-form-mid">补贴周期</div>
										<div class="layui-input-inline" style="width:170px">
											<input type="text" name="info[fx_butie_circle]" class="layui-input" value="<?php echo $info['fx_butie_circle']; ?>">
										</div>

									</div>
									<div class="layui-form-item">
										<label class="layui-form-label"></label>
										<div class="layui-input-inline layui-module-itemL fx-butie-send-week" <?php if($info['fx_butie_type']!=1): ?>style='display:none;'<?php endif; ?>>
											<div>每周</div>
											<input type="text" name="info[fx_butie_send_week]" class="layui-input" value="<?php echo $info['fx_butie_send_week']; ?>">
											发放（填写数字1-7）
										</div>
										<div class="layui-input-inline layui-module-itemL fx-butie-send-day" <?php if($info['fx_butie_type']!=0): ?>style='display:none;'<?php endif; ?>>
											<div>每月</div>
											<input type="text" name="info[fx_butie_send_day]" class="layui-input" value="<?php echo $info['fx_butie_send_day']; ?>">
											号发放（填写数字1-31）
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">
											1、发放佣金时立马发放第一期补贴<br/>
											2、补贴周期：共分多少期发放<br/>
											3、按周：填写数字1-7，每周发放一次<br/>
											4、按月：填写数字1-31，每月发放一次，没有31号的月份自动提前到30号发放<br/>
											5、产生补贴时会记录当前设置参数，补贴以已生成再改参数不会生效<br/>
										</div>
									</div>
									<?php endif; if(getcustom('commission_recursion')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">直推复购奖</label>
										<div class="layui-input-inline" style="width:50%">
											<input type="radio" name="info[is_fugou_commission]" value="1" title="是" <?php if($info['is_fugou_commission']==1): ?>checked<?php endif; ?> lay-filter="is_fugou_commission">
											<input type="radio" name="info[is_fugou_commission]" value="0" title="否" <?php if($info['is_fugou_commission']==0 || $info['is_fugou_commission']==''): ?>checked<?php endif; ?> lay-filter="is_fugou_commission">
										</div>
									</div>
									<div class="layui-form-item" id="fugouBox" <?php if($info['is_fugou_commission']!='1'): ?>style="display:none;"<?php endif; ?>>
										<label class="layui-form-label">复购奖励设置</label>
										<div class="layui-input-inline" style="width:80px">
											<input type="text" class="layui-input" name="info[fugou_recursion_percent]" value="<?php echo (isset($info['fugou_recursion_percent']) && ($info['fugou_recursion_percent'] !== '')?$info['fugou_recursion_percent']:0); ?>">
										</div>
										<div class="layui-form-mid layui-word-aux">%，逐级递减，当金额低于</div>
										<div class="layui-input-inline" style="width:80px">
											<input type="number" step="1" min="0" name="info[fugou_commission_min]" lay-verify="number|Ndouble" class="layui-input" value="<?php echo (isset($info['fugou_commission_min']) && ($info['fugou_commission_min'] !== '')?$info['fugou_commission_min']:0); ?>">
										</div>
										<div class="layui-form-mid layui-word-aux">元时，停止<?php echo t('佣金'); ?>发放</div>
									</div>
									<?php endif; if(getcustom('commission_parent_pj') && !getcustom('commission_parent_pj_stop') && $auth_data=='all' || in_array('commission_parent_pj',$auth_data)): ?>
									  <div class="layui-form-item">
										  <label class="layui-form-label">分销平级奖限制：</label>
										  <div class="layui-form-mid">不依赖上级奖励</div>
										  <div class="layui-input-inline" style="width: 280px;">
											  <input type="radio" name="info[commission_parent_pj_no_limit]" title="否" value="0" <?php if($info['commission_parent_pj_no_limit']==0): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[commission_parent_pj_no_limit]" title="是" value="1" <?php if($info['commission_parent_pj_no_limit']==1): ?>checked<?php endif; ?>/>
										  </div>
										  <div class="layui-form-mid layui-word-aux layui-clear">
											  选择是：针对产品单独设置了分销平级奖金额，即使上级没有分销提成也可以拿到平级奖<br/>
											  选择否：平级奖依赖上级奖励，如果上级没有分销提成，则平级奖无效<br/>
											  默认否<br/>
										  </div>
									  </div>
									  <?php endif; ?>
									<div class="layui-form-item">
										<label class="layui-form-label">买单收款分销：</label>
										<div class="layui-input-inline">
											<input type="radio" name="info[maidanfenxiao]" class="maidanfenxiao" title="关闭" value="0" <?php if($info['maidanfenxiao']==0): ?>checked<?php endif; ?> lay-filter="maidanfenxiao"/>
											<input type="radio" name="info[maidanfenxiao]" class="maidanfenxiao" title="开启" value="1" <?php if($info['maidanfenxiao']==1): ?>checked<?php endif; ?> lay-filter="maidanfenxiao"/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">买单收款功能是否参与分销</div>
									</div>
  									<!--买单分红 start-->
									<?php if(getcustom('maidan_fenhong_new')): ?>
								  <div class="layui-form-item" id="maidanfenxiaoset" <?php if($info['maidanfenxiao']==0): ?>style="display:none;"<?php endif; ?> >
									  <label class="layui-form-label">买单分销结算方式：</label>
									  <div class="layui-input-inline" style="width:400px">
										  <input type="radio" name="info[maidanfenxiao_type]" title="按销售额" value="0" <?php if($info['maidanfenxiao_type']==0): ?>checked<?php endif; ?> />
										  <input type="radio" name="info[maidanfenxiao_type]" title="利润" value="1" <?php if($info['maidanfenxiao_type']==1): ?>checked<?php endif; ?> />
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">买单分销结算方式：选择利润则分销时，销售额会减去成本价格，按剩余价格进行分销</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">买单收款分红：</label>
									  <div class="layui-input-inline">
										  <input type="radio" name="info[maidanfenhong]" class="maidanfenhong" title="关闭" value="0" <?php if($info['maidanfenhong']==0): ?>checked<?php endif; ?> lay-filter="maidan_fenhong"/>
										  <input type="radio" name="info[maidanfenhong]" class="maidanfenhong" title="开启" value="1" <?php if($info['maidanfenhong']==1): ?>checked<?php endif; ?> lay-filter="maidan_fenhong"/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">买单收款功能是否参与分红</div>
								  </div>
									<div class="maidan_fenhong" <?php if($info['maidanfenhong']==0): ?>style="display:none;"<?php endif; ?>>
									  <div class="layui-form-item">
										  <label class="layui-form-label">买单分红结算方式：</label>
										  <div class="layui-input-inline" style="width:400px">
											  <input type="radio" name="info[maidanfenhong_type]" title="按销售额" value="0" <?php if($info['maidanfenhong_type']==0): ?>checked<?php endif; ?> />
											  <input type="radio" name="info[maidanfenhong_type]" title="利润" value="1" <?php if($info['maidanfenhong_type']==1): ?>checked<?php endif; ?> />
										  </div>
										  <div class="layui-form-mid layui-word-aux layui-clear">买单分红结算方式</div>
									  </div>
									</div>
									<div class="layui-form-item" id="maidancost" <?php if($info['maidanfenxiao']==0 && $info['maidanfenhong']==0): ?>style="display:none;"<?php endif; ?>>
									  <label class="layui-form-label">买单成本比例(%)：</label>
									  <div class="layui-input-inline">
										  <input type="text" class="layui-input" name="info[maidan_cost]" value="<?php echo $info['maidan_cost']; ?>" />
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">买单成本比例：仅平台买单使用此比例，多商户的需要在多商户那单独设置</div>
								  </div>
									<?php endif; if(getcustom('cashdesk_commission')): ?>
									<div class="layui-form-item">
									  <label class="layui-form-label">收银台分销：</label>
									  <div class="layui-input-inline">
										  <input type="radio" name="info[cashdeskfenxiao]" title="关闭" value="0" <?php if($info['cashdeskfenxiao']==0): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[cashdeskfenxiao]" title="开启" value="1" <?php if($info['cashdeskfenxiao']==1): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">收银台付款功能是否参与分销</div>
									</div>
									<?php endif; ?>
  									<!--买单分红 end-->
									<?php if(getcustom('wx_channels')): ?>
									<div class="layui-form-item">
									  <label class="layui-form-label">视频号小店订单分销：</label>
									  <div class="layui-input-inline" style="width:auto">
										  <input type="radio" name="info[channels_order_fenxiao]" title="按商城推荐网" value="0" <?php if($info['channels_order_fenxiao']==0): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[channels_order_fenxiao]" title="按分享人" value="1" <?php if($info['channels_order_fenxiao']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[channels_order_fenxiao]" title="不参与分销" value="-1" <?php if($info['channels_order_fenxiao']==-1): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">
										  1、按商城推荐网：根据订单用户unionid查找商城<?php echo t('会员'); ?>信息，优先按<?php echo t('会员'); ?>推荐网体发放，没有推荐人时绑定分享员作为推荐人同时发放分销<br/>
										  2、按分享人：根据订单用户和分享员unionid查找商城<?php echo t('会员'); ?>信息，优先使用分享员作为推荐人发放，没有分享员时按商城推荐网发放<br/>
										  3、分享员如果没有在商城系统注册账号，会自动生成<?php echo t('会员'); ?><br/>
										  4、视频号小店需要和小程序、公众号绑定到同一开放平台才可以做到<?php echo t('会员'); ?>匹配<br/>
									  </div>
									</div>
									<?php endif; ?>
									<div class="layui-form-item">
									  <label class="layui-form-label">分销订单：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[fxorder_show]" title="显示" value="1" <?php if($info['fxorder_show']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[fxorder_show]" title="不显示" value="0" <?php if($info['fxorder_show']==0): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">前端我的佣金页面是否显示分销订单</div>
									</div>
									<?php if(getcustom('teamyeji_show')): ?>
									<div class="layui-form-item">
									  <label class="layui-form-label">团队业绩：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[teamyeji_show]" title="显示" value="1" <?php if($info['teamyeji_show']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[teamyeji_show]" title="不显示" value="0" <?php if($info['teamyeji_show']==0): ?>checked<?php endif; ?>/>
									  </div>
									
									  <div class="layui-form-mid layui-word-aux layui-clear">前端我的佣金页面是否显示团队业绩</div>
									</div>
									<div class="layui-form-item">
									  <label class="layui-form-label">团队业绩包含自身：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[teamyeji_self]" title="包含" value="1" <?php if($info['teamyeji_self']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[teamyeji_self]" title="不包含" value="0" <?php if($info['teamyeji_self']==0): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">显示团队业绩是否包含自身</div>
									</div>
									<div class="layui-form-item">
									  <label class="layui-form-label">团队总人数：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[teamnum_show]" title="显示" value="1" <?php if($info['teamnum_show']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[teamnum_show]" title="不显示" value="0" <?php if($info['teamnum_show']==0): ?>checked<?php endif; ?>/>
									  </div>
									
									  <div class="layui-form-mid layui-word-aux layui-clear">前端我的佣金页面是否显示团队总人数</div>
									</div>
									<?php endif; if(getcustom('yx_team_yeji_cashdesk')): ?>
									<div class="layui-form-item">
									  <label class="layui-form-label">统计收银台业绩：</label>
									  <div class="layui-input-inline">
										  <input type="radio" name="info[include_cashdesk_yeji]" value="0" title="关闭" <?php if($info['include_cashdesk_yeji']==0): ?>checked<?php endif; ?>>
										  <input type="radio" name="info[include_cashdesk_yeji]" value="1" title="开启" <?php if($info['include_cashdesk_yeji']==1): ?>checked<?php endif; ?>>
									  </div>
									  <div class="layui-form-mid layui-word-aux">开启后，手机端[我的团队]的团队业绩包含收银台的业绩</div>
									</div>
									<?php endif; ?>
									<div class="layui-form-item">
									  <label class="layui-form-label">推荐人：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[parent_show]" title="显示" value="1" <?php if($info['parent_show']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[parent_show]" title="不显示" value="0" <?php if($info['parent_show']==0): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">前端我的佣金页面是否显示推荐人</div>
									</div>
									<?php if(getcustom('business_agent')): ?>
									<div class="layui-form-item">
									  <label class="layui-form-label">推荐商家列表：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[tjbusiness_show]" title="显示" value="1" <?php if($info['tjbusiness_show']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[tjbusiness_show]" title="不显示" value="0" <?php if($info['tjbusiness_show']==0): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">前端我的佣金页面是否显示推荐商家列表</div>
									</div>
									<?php endif; ?>
									<fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
									  <legend>分红</legend>
									</fieldset>
  									<?php if(getcustom('restaurant_fenhong')): ?>
	  									<div class="layui-form-item">
										  <label class="layui-form-label">餐饮分红开关：</label>
										  <div class="layui-input-inline">
											  <input type="radio" name="info[restaurant_fenhong_status]" title="关闭" value="0" <?php if($info['restaurant_fenhong_status']==0): ?>checked<?php endif; ?> lay-filter="maidan_fenhong"/>
											  <input type="radio" name="info[restaurant_fenhong_status]" title="开启" value="1" <?php if($info['restaurant_fenhong_status']==1): ?>checked<?php endif; ?> lay-filter="maidan_fenhong"/>
										  </div>
										  <div class="layui-form-mid layui-word-aux layui-clear">开启后餐饮版块参与分红</div>
									  </div>
								  <?php endif; if(getcustom('fenhong_cashier_order')): ?>
									  <div class="layui-form-item">
										  <label class="layui-form-label">收银台订单<?php echo t('股东分红'); ?>：</label>
										  <div class="layui-input-inline" style="width:200px">
										  	<input type="radio" name="info[fenhong_cashier_order_money]" title="开启" value="1" <?php if($info['fenhong_cashier_order_money']==1): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[fenhong_cashier_order_money]" title="关闭" value="0" <?php if(!$info['fenhong_cashier_order_money'] || $info['fenhong_cashier_order_money']==0): ?>checked<?php endif; ?>/>
										  </div>
										  <div class="layui-form-mid layui-word-aux layui-clear">仅限平台</div>
									  </div>
									<?php endif; if(getcustom('fenhong_gudong_huiben') && ($auth_data=='all' || in_array('gdfenhong_huiben',$auth_data))): ?>
								  <div class="layui-form-item">
									  <label class="layui-form-label"><?php echo t('回本股东分红'); ?>结算时间：</label>
									  <div class="layui-input-inline" style="width:600px">
										  <input type="radio" name="info[fhjiesuantime_type_huiben]" title="确认收货后结算" value="0" <?php if($info['fhjiesuantime_type_huiben']==0): ?>checked<?php endif; ?> lay-filter="fhjiesuantime_type_huiben"/>
										  <input type="radio" name="info[fhjiesuantime_type_huiben]" title="付款后结算" value="1" <?php if($info['fhjiesuantime_type_huiben']==1): ?>checked<?php endif; ?> lay-filter="fhjiesuantime_type_huiben"/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">确认收货后结算：可选计算周期，付款后结算：每分钟计算一次</div>
									  <div class="layui-form-mid layui-clear" style="margin-left:190px;color:red;">注意：选择付款后结算退款时无法退回分红</div>
								  </div>
								  <div class="layui-form-item" id="fhjiesuantime_type_huiben-set"<?php if($info['fhjiesuantime_type_huiben']==1): ?>style="display:none"<?php endif; ?>>
								  <label class="layui-form-label"><?php echo t('回本股东分红'); ?>结算周期：</label>
								  <div class="layui-input-inline" style="width:700px">
									  <input type="radio" name="info[fhjiesuantime_huiben]" title="每天结算" value="0" <?php if($info['fhjiesuantime_huiben']==0): ?>checked<?php endif; ?>/>
									  <input type="radio" name="info[fhjiesuantime_huiben]" title="每小时结算" value="2" <?php if($info['fhjiesuantime_huiben']==2): ?>checked<?php endif; ?>/>
									  <input type="radio" name="info[fhjiesuantime_huiben]" title="每分钟结算" value="3" <?php if($info['fhjiesuantime_huiben']==3): ?>checked<?php endif; ?>/>
									  <input type="radio" name="info[fhjiesuantime_huiben]" title="月初结算" value="1" <?php if($info['fhjiesuantime_huiben']==1): ?>checked<?php endif; ?>/>
									  <input type="radio" name="info[fhjiesuantime_huiben]" title="月底结算" value="4" <?php if($info['fhjiesuantime_huiben']==4): ?>checked<?php endif; ?>/>
									  <input type="radio" name="info[fhjiesuantime_huiben]" title="年底结算" value="5" <?php if($info['fhjiesuantime_huiben']==5): ?>checked<?php endif; ?>/>
									  <?php if(getcustom('fhjiesuantime_monday')): ?>
									  <input type="radio" name="info[fhjiesuantime_huiben]" title="周一结算" value="7" <?php if($info['fhjiesuantime_huiben']==7): ?>checked<?php endif; ?>/>
									  <?php endif; ?>
								  </div>
								  <div class="layui-form-mid layui-word-aux layui-clear">每天结算则第二天计算前一天的分红并发放，月初结算则每月一号计算上一个月的分红并发放（确认收货的订单）</div>
								  </div>
								  <?php endif; if($auth_data=='all' || in_array('gdfenhong',$auth_data) || in_array('teamfenhong',$auth_data) || in_array('areafenhong',$auth_data)): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">分红结算方式：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="radio" name="info[fhjiesuantype]" title="按销售金额" value="0" <?php if($info['fhjiesuantype']==0): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[fhjiesuantype]" title="按销售利润" value="1" <?php if($info['fhjiesuantype']==1): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">按销售金额结算即：实付销售价格×提成百分比，按销售利润即：（实付销售价格-商品成本）×提成百分比</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">分红结算时间：</label>
										<div class="layui-input-inline" style="width:600px">
											<input type="radio" name="info[fhjiesuantime_type]" title="确认收货后结算" value="0" <?php if($info['fhjiesuantime_type']==0): ?>checked<?php endif; ?> lay-filter="fhjiesuantime_type"/>
											<input type="radio" name="info[fhjiesuantime_type]" title="付款后结算" value="1" <?php if($info['fhjiesuantime_type']==1): ?>checked<?php endif; ?> lay-filter="fhjiesuantime_type"/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">确认收货后结算：可选计算周期，付款后结算：每分钟计算一次</div>
										<div class="layui-form-mid layui-clear" style="margin-left:190px;color:red;">注意：选择付款后结算退款时无法退回分红</div>
									</div>
									<div class="layui-form-item" id="fhjiesuantime_type-set"<?php if($info['fhjiesuantime_type']==1): ?>style="display:none"<?php endif; ?>>
										<label class="layui-form-label">分红结算周期：</label>
										<div class="layui-input-inline" style="width:700px">
											<input type="radio" name="info[fhjiesuantime]" title="每天结算" value="0" <?php if($info['fhjiesuantime']==0): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[fhjiesuantime]" title="每小时结算" value="2" <?php if($info['fhjiesuantime']==2): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[fhjiesuantime]" title="每分钟结算" value="3" <?php if($info['fhjiesuantime']==3): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[fhjiesuantime]" title="月初结算" value="1" <?php if($info['fhjiesuantime']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[fhjiesuantime]" title="月底结算" value="4" <?php if($info['fhjiesuantime']==4): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[fhjiesuantime]" title="年底结算" value="5" <?php if($info['fhjiesuantime']==5): ?>checked<?php endif; ?>/>
											<?php if(getcustom('fhjiesuantime_monday')): ?>
											<input type="radio" name="info[fhjiesuantime]" title="周一结算" value="7" <?php if($info['fhjiesuantime']==7): ?>checked<?php endif; ?>/>
											<?php endif; if(getcustom('fhjiesuantime_shoudong')): ?>
											<input type="radio" name="info[fhjiesuantime]" title="手动结算" value="10" <?php if($info['fhjiesuantime']==10): ?>checked<?php endif; ?>/>
											<?php endif; ?>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">每天结算则第二天计算前一天的分红并发放，月初结算则每月一号计算上一个月的分红并发放（确认收货的订单）<?php if(getcustom('fhjiesuantime_monday')): ?>，周一凌晨1点结算上周的<?php endif; ?></div>
									</div>
									<?php if(getcustom('fenhong_fafang_type')): ?>
									<div class="layui-form-item">
									  <label class="layui-form-label">分红发放方式：</label>
									  <div class="layui-input-inline" style="width:600px">
										  <input type="radio" name="info[fenhong_fafang_type]" title="自动发放" value="0" <?php if($info['fenhong_fafang_type']==0): ?>checked<?php endif; ?> lay-filter=""/>
										  <input type="radio" name="info[fenhong_fafang_type]" title="审核发放" value="1" <?php if($info['fenhong_fafang_type']==1): ?>checked<?php endif; ?> lay-filter=""/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">审核发放需要再分红记录页面，点击【发放】后才可发放</div>
									</div>
									<?php endif; if($auth_data=='all' || in_array('teamfenhong',$auth_data)): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">团队分红级差：</label>
										<div class="layui-input-inline" style="width:160px">
											<input type="radio" name="info[teamfenhong_differential]" title="开启" value="1" <?php if($info['teamfenhong_differential']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[teamfenhong_differential]" title="关闭" value="0" <?php if($info['teamfenhong_differential']==0): ?>checked<?php endif; ?>/>
										</div>
										<?php if(getcustom('teamfenhong_pingji') && ($auth_data == 'all' || in_array('teamfenhong_pingji',$auth_data))): ?>
										<div class="layui-input-inline" style="width:150px" id="teamfenhong_differential_pj">
											<input type="checkbox" name="info[teamfenhong_differential_pj]" value="1" title="包含平级奖" lay-skin="primary" <?php if($info['teamfenhong_differential_pj'] == 1): ?>checked<?php endif; ?>/>
										</div>
										<?php endif; if(getcustom('teamfenhong_jicha_add_pj')): ?>
										<div class="layui-input-inline" style="width:150px" id="teamfenhong_jicha_add_pj">
											<input type="checkbox" name="info[teamfenhong_jicha_add_pj]" value="1" title="团队分红级差减掉平级奖" lay-skin="primary" <?php if($info['teamfenhong_jicha_add_pj'] == 1): ?>checked<?php endif; ?>/>
										</div>
										<?php endif; ?>
										<div class="layui-form-mid layui-word-aux layui-clear">
											1、开启后团队分红将按照级差进行分红<br/>
											2、包含平级奖：选中后平级奖也按级差方式计算发放<br/>
											<?php if(getcustom('teamfenhong_jicha_add_pj')): ?>
											3、团队分红级差减掉平级奖：选中后团队分红级差计算方式：应得分红-前面<?php echo t('会员'); ?>已得分红-前面<?php echo t('会员'); ?>已得平级奖<br/>
											<?php endif; ?>
										</div>
									</div>
									  <?php if(getcustom('teamfenhong_pingji_fenhong')): ?>
									  <div class="layui-form-item">
										  <label class="layui-form-label">团队分红平级奖计算基数：</label>
										  <div class="layui-input-inline" style="width:460px">
											  <input type="radio" name="info[teamfenhong_pingji_fenhong]" title="团队分红计算" value="0" <?php if($info['teamfenhong_pingji_fenhong']==0): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[teamfenhong_pingji_fenhong]" title="团队分红+平级奖计算" value="1" <?php if($info['teamfenhong_pingji_fenhong']==1): ?>checked<?php endif; ?>/>
										  </div>
										  <div class="layui-form-mid layui-word-aux layui-clear">
											  1、计算平级奖时两种不同的计算方式<br/>
											  2、团队分红计算：使用下级<?php echo t('会员'); ?>的团队分红计算平级奖，例：四个同级<?php echo t('会员'); ?>A推B推C推D，A的平级奖是用D的团队分红奖金进行计算<br/>
											  3、团队分红+平级奖计算：使用下级<?php echo t('会员'); ?>的团队分红+平级奖，例：四个同级<?php echo t('会员'); ?>A推B推C推D，A的平级奖是用D团队分红+C平级奖+B平级奖的奖金和进行计算<br/>
											  4、团队分红+平级奖计算：仅适用于平级奖是按奖金比例计算方式
										  </div>
									  </div>
									  <?php endif; if(getcustom('teamfenhong_pingji_source')): ?>
									  <div class="layui-form-item">
										  <label class="layui-form-label">团队分红平级奖来源：</label>
										  <div class="layui-input-inline" style="width:460px">
											  <input type="radio" name="info[teamfenhong_pingji_source]" title="平台" value="0" <?php if($info['teamfenhong_pingji_source']==0): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[teamfenhong_pingji_source]" title="下级团队分红" value="1" <?php if($info['teamfenhong_pingji_source']==1): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[teamfenhong_pingji_source]" title="上级团队分红" value="2" <?php if($info['teamfenhong_pingji_source']==2): ?>checked<?php endif; ?>/>
										  </div>
										  <div class="layui-form-mid layui-word-aux layui-clear">比如推荐关系：金牌B—>银牌C—>银牌D—>铜牌F，铜牌F下单，银牌D拿团队奖励，银牌C拿平级奖励，金牌B拿团队奖励<br>
											  下级团队分红：C的平级奖励从下级D的团队分红里扣除；上级团队分红：C的平级奖励从上级B的团队分红里扣除（最近的高等级，平级跳过继续向上找）；找不到对应的来源或者来源没有团队分红则平台承担</div>
									  </div>
									  <?php endif; if(getcustom('teamfenhong_pingji_single_bl')): ?>
									  <div class="layui-form-item">
										  <label class="layui-form-label">平级奖受限于上级<?php echo t('团队分红'); ?>：</label>
										  <div class="layui-input-inline" style="width:460px">
											  <input type="radio" name="info[teamfenhong_pingji_parent_limit]" title="否" value="0" <?php if($info['teamfenhong_pingji_parent_limit']==0): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[teamfenhong_pingji_parent_limit]" title="是" value="1" <?php if($info['teamfenhong_pingji_parent_limit']==1): ?>checked<?php endif; ?>/>
										  </div>
										  <div class="layui-form-mid layui-word-aux layui-clear">选择【是】上级没有团队分红时不发放平级奖，该配置仅针对团队分红平级奖独立比例插件生效</div>
									  </div>
									  <?php endif; ?>
									<?php endif; if(getcustom('areafenhong_differential') && ($auth_data=='all' || in_array('areafenhong',$auth_data))): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">区域代理分红级差：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="radio" name="info[areafenhong_differential]" title="开启" value="1" <?php if($info['areafenhong_differential']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[areafenhong_differential]" title="关闭" value="0" <?php if($info['areafenhong_differential']==0): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">开启后区域代理分红将按照级差进行分红</div>
									</div>
									<?php endif; if(getcustom('member_area_agent_multi') && getcustom('member_area_agent_multi_price') && ($auth_data=='all' || in_array('areafenhong',$auth_data))): ?>
								  <div class="layui-form-item">
									  <label class="layui-form-label">多区域代理分红级差：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[areafenhong_differential_multi]" title="开启" value="1" <?php if($info['areafenhong_differential_multi']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[areafenhong_differential_multi]" title="关闭" value="0" <?php if($info['areafenhong_differential_multi']==0): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">开启后多区域代理分红将按照级差进行分红(仅独立设置分红金额生效)</div>
								  </div>
								  <?php endif; if(getcustom('teamfenhong_jiandan')): if($auth_data=='all' || in_array('teamfenhongJiandan',$auth_data)): ?>
										<div class="layui-form-item">
											<label class="layui-form-label">团队见单分红级差：</label>
											<div class="layui-input-inline" style="width:160px">
												<input type="radio" name="info[teamfenhong_jiandan_differential]" title="开启" value="1" <?php if($info['teamfenhong_jiandan_differential']==1): ?>checked<?php endif; ?>/>
												<input type="radio" name="info[teamfenhong_jiandan_differential]" title="关闭" value="0" <?php if($info['teamfenhong_jiandan_differential']==0): ?>checked<?php endif; ?>/>
											</div>
											<!--<div class="layui-input-inline" style="width:150px" id="teamfenhong_jiandan_differential_pj">
												<input type="checkbox" name="info[teamfenhong_jiandan_differential_pj]" value="1" title="包含平级奖" lay-skin="primary" <?php if($info['teamfenhong_differential_pj'] == 1): ?>checked<?php endif; ?>/>
											</div>-->
											<div class="layui-form-mid layui-word-aux layui-clear">开启后见单分红将按照级差进行分红</div>
										</div>
									<?php endif; ?>
  									<?php endif; ?>
									<div class="layui-form-item">
										<label class="layui-form-label">多商户商品分红：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="radio" name="info[fhjiesuanbusiness]" title="开启" value="1" <?php if($info['fhjiesuanbusiness']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[fhjiesuanbusiness]" title="关闭" value="0" <?php if($info['fhjiesuanbusiness']==0): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">开启后多商户的商品也参与分红</div>
									</div>
									<?php endif; if(getcustom('fenhong_max')): ?>
								  <div class="layui-form-item">
									  <label class="layui-form-label">股东分红上限累加低级别：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[fenhong_max_add]" title="开启" value="1" <?php if($info['fenhong_max_add']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[fenhong_max_add]" title="关闭" value="0" <?php if($info['fenhong_max_add']==0): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">开启后，当多个<?php echo t('会员'); ?>等级存在股东分红时，当前<?php echo t('会员'); ?>的分红上限和低级别等级分红上限累加</div>
								  </div>
								  <?php endif; if(getcustom('partner_parent_only')): ?>
									  <div class="layui-form-item">
										  <label class="layui-form-label">股东分红仅奖励购买人上级等级：</label>
										  <div class="layui-input-inline" style="width:300px">
											  <input type="radio" name="info[partner_parent_only]" title="开启" value="1" <?php if($info['partner_parent_only']==1): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[partner_parent_only]" title="关闭" value="0" <?php if($info['partner_parent_only']==0): ?>checked<?php endif; ?>/>
										  </div>
										  <div class="layui-form-mid layui-word-aux layui-clear">开启后股东分红仅奖励购买人上级等级，其他等级不奖励，无上级也不奖励</div>
									  </div>
									  <?php endif; if(getcustom('partner_jiaquan') && $partner_jiaquan): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">股东加权分红：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="radio" name="info[partner_jiaquan]" title="开启" value="1" <?php if($info['partner_jiaquan']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[partner_jiaquan]" title="关闭" value="0" <?php if($info['partner_jiaquan']==0): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">开启后高等级的股东也会参与低等级的股东分红</div>
									</div>
									<?php endif; if(getcustom('gdfenhong_add') && ($auth_data == 'all' || in_array('gdfenhong_add',$auth_data))): ?>
								  <div class="layui-form-item">
									  <label class="layui-form-label">股东分红叠加：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[gdfenhong_add]" title="开启" value="1" <?php if($info['gdfenhong_add']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[gdfenhong_add]" title="关闭" value="0" <?php if($info['gdfenhong_add']==0): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">
										  1、开启后高等级的股东也会参与低等级的股东分红<br/>
										  2、高等级不参与低等级的人数计算，例：A股东分红=（A总分红/A人数）B股东分红=（A总分红/A人数+B总分红/B人数）<br/>
										  <?php if(getcustom('partner_jiaquan') && $partner_jiaquan): ?>
										  3、与《股东加权分红》冲突，不可同时开启
										  <?php endif; ?>
									  </div>
								  </div>
								  <?php endif; if(getcustom('gdfenhong_jiesuantype')): ?>
								  <div class="layui-form-item">
									  <label class="layui-form-label"><?php echo t('股东分红'); ?>结算方式：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[gdfenhong_jiesuantype]" title="按会员等级" value="0" <?php if($info['gdfenhong_jiesuantype']==0): ?>checked<?php endif; ?> lay-filter="diandaSwitch"/>
										  <input type="radio" name="info[gdfenhong_jiesuantype]" title="独立比例" value="1" <?php if($info['gdfenhong_jiesuantype']==1): ?>checked<?php endif; ?> lay-filter="diandaSwitch"/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">
										  1、按会员等级:多个级别会发放多次股东分红；<br/>
										  2、独立比例：所有可拿股东分红级别的会员共同平分股东分红,会员等级及产品独立设置的比例失效；<br/>
										  <?php if(getcustom('partner_jiaquan') && $partner_jiaquan): ?>
										  3、该方式股东加权分红无法使用
										  <?php endif; ?>
									  </div>
								  </div>
  								<div diandaSwitch="info[gdfenhong_jiesuantype]" data-val="1" <?php if($info['gdfenhong_jiesuantype']!=1): ?>style="display:none"<?php endif; ?>>
								  <fieldset class="layui-elem-field">
									  <legend style="font-size:16px">独立比例</legend>
									  <div class="layui-form-item">
										  <label class="layui-form-label"><?php echo t('股东分红'); ?>比例(%)：</label>
										  <div class="layui-input-inline" style="width:80px">
											  <input type="text" name="info[gdfh_bili]" class="layui-input" value="<?php echo $info['gdfh_bili']; ?>" />
										  </div>
									  </div>
									  <div class="layui-form-item">
										  <label class="layui-form-label"><?php echo t('股东分红'); ?>参与等级：</label>
										  <div class="layui-input-inline" style="width:800px">
											  <input type="checkbox" name="info[gdfh_levelids][]" value="-1" title="所有人" lay-skin="primary" <?php if(in_array('-1',explode(',',$info['gdfh_levelids']))): ?>checked<?php endif; ?> />
											  <?php foreach($levellist as $v): ?>
											  <input type="checkbox" name="info[gdfh_levelids][]" value="<?php echo $v['id']; ?>" title="<?php echo $v['name']; ?>" lay-skin="primary" <?php if(in_array($v['id'],explode(',',$info['gdfh_levelids']))): ?>checked<?php endif; ?>/>
											  <?php endforeach; ?>
										  </div>
										  <div class="layui-form-mid layui-word-aux layui-clear">未选中等级不参与</div>
									  </div>
									  <div class="layui-form-item">
										  <label class="layui-form-label">合并结算：</label>
										  <div class="layui-input-inline" style="width:600px">
											  <input type="radio" name="info[gd_fhjiesuanhb]" title="否" value="0" <?php if($info['gd_fhjiesuanhb']==0): ?>checked<?php endif; ?> />
											  <input type="radio" name="info[gd_fhjiesuanhb]" title="是" value="1" <?php if($info['gd_fhjiesuanhb']==1): ?>checked<?php endif; ?> />
										  </div>
										  <div class="layui-form-mid layui-word-aux layui-clear">分红合并结算即结算时所有需要分红的订单合并为一条,产生一条结算记录</div>
									  </div>
										  <div class="layui-form-item">
											  <label class="layui-form-label">分红结算时间：</label>
											  <div class="layui-input-inline" style="width:600px">
												  <input type="radio" name="info[gdfhjiesuantime_type]" title="确认收货后结算" value="0" <?php if($info['gdfhjiesuantime_type']==0): ?>checked<?php endif; ?> lay-filter="gdfhjiesuantime_type"/>
												  <input type="radio" name="info[gdfhjiesuantime_type]" title="付款后结算" value="1" <?php if($info['gdfhjiesuantime_type']==1): ?>checked<?php endif; ?> lay-filter="gdfhjiesuantime_type"/>
											  </div>
											  <div class="layui-form-mid layui-word-aux layui-clear">确认收货后结算：可选计算周期，付款后结算：每分钟计算一次</div>
											  <div class="layui-form-mid layui-clear" style="margin-left:190px;color:red;">注意：选择付款后结算退款时无法退回分红</div>
										  </div>
										  <div class="layui-form-item" id="gdfhjiesuantime_type-set"<?php if($info['gdfhjiesuantime_type']==1): ?>style="display:none"<?php endif; ?>>
										  <label class="layui-form-label">分红结算周期：</label>
										  <div class="layui-input-inline" style="width:700px">
											  <input type="radio" name="info[gd_fhjiesuantime]" title="每天结算" value="0" <?php if($info['gd_fhjiesuantime']==0): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[gd_fhjiesuantime]" title="每小时结算" value="2" <?php if($info['gd_fhjiesuantime']==2): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[gd_fhjiesuantime]" title="每分钟结算" value="3" <?php if($info['gd_fhjiesuantime']==3): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[gd_fhjiesuantime]" title="月初结算" value="1" <?php if($info['gd_fhjiesuantime']==1): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[gd_fhjiesuantime]" title="月底结算" value="4" <?php if($info['gd_fhjiesuantime']==4): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[gd_fhjiesuantime]" title="年底结算" value="5" <?php if($info['gd_fhjiesuantime']==5): ?>checked<?php endif; ?>/>
										  </div>
										  <div class="layui-form-mid layui-word-aux layui-clear">每天结算则第二天计算前一天的分红并发放，月初结算则每月一号计算上一个月的分红并发放（确认收货的订单）</div>
										  </div>
							  		</fieldset>
								</div>
  								<?php endif; if(getcustom('areafenhong_jiaquan')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">区域代理加权分红：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="radio" name="info[areafenhong_jiaquan]" title="开启" value="1" <?php if($info['areafenhong_jiaquan']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[areafenhong_jiaquan]" title="关闭" value="0" <?php if($info['areafenhong_jiaquan']==0): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">开启后上级区域的区域代理也会参与下级区域的区域分红</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">区域判断方式：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="radio" name="info[areafenhong_checktype]" title="收货地址" value="0" <?php if($info['areafenhong_checktype']==0): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[areafenhong_checktype]" title="手机号归属地" value="1" <?php if($info['areafenhong_checktype']==1): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear"></div>
									</div>
									<?php endif; if(getcustom('partner_gongxian') && $partner_gongxian): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">股东贡献量分红：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="radio" name="info[partner_gongxian]" title="开启" value="1" <?php if($info['partner_gongxian']==1): ?>checked<?php endif; ?> lay-filter="partner_gongxian"/>
											<input type="radio" name="info[partner_gongxian]" title="关闭" value="0" <?php if($info['partner_gongxian']==0): ?>checked<?php endif; ?> lay-filter="partner_gongxian"/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">开启后可设置一定比例的分红金额按照股东的团队业绩量分红</div>
									</div>
									<div id="partner_gongxian_div" <?php if($info['partner_gongxian']==0): ?>style="display:none"<?php endif; ?>>
										<div class="layui-form-item">
											<label class="layui-form-label">预计股东贡献量分红：</label>
											<div class="layui-input-inline" style="width:200px">
												<input type="radio" name="info[gongxianfenhong_show]" title="显示" value="1" <?php if($info['gongxianfenhong_show']==1): ?>checked<?php endif; ?>/>
												<input type="radio" name="info[gongxianfenhong_show]" title="不显示" value="0" <?php if($info['gongxianfenhong_show']==0): ?>checked<?php endif; ?>/>
											</div>
											<div class="layui-form-mid">自定义名称</div>
											<div class="layui-input-inline" style="width:170px">
												<input type="text" name="info[gongxianfenhong_txt]" class="layui-input" value="<?php echo $info['gongxianfenhong_txt']; ?>">
											</div>
										</div>
									</div>
									<?php endif; if(getcustom('business_agent')): ?>
									  <div class="layui-form-item">
										  <label class="layui-form-label">推荐商家结算方式：</label>
										  <div class="layui-input-inline" style="width:400px">
											  <input type="radio" name="info[tjbusiness_jiesuan_type]" title="按结算金额" value="0" <?php if($info['tjbusiness_jiesuan_type']==0): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[tjbusiness_jiesuan_type]" title="按平台抽成金额" value="1" <?php if($info['tjbusiness_jiesuan_type']==1): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[tjbusiness_jiesuan_type]" title="按利润金额" value="2" <?php if($info['tjbusiness_jiesuan_type']==2): ?>checked<?php endif; ?>/> <!--有买单成本比例功能时，根据此比例计算利润-->
										  </div>
										  <div class="layui-form-mid layui-word-aux layui-clear">结算金额为扣除平台抽成后商家所得金额，商家和平台结算完成后，推荐人获得<?php echo t('佣金'); ?> ；按平台抽成金额：根据平台抽成金额*提成百分比；按销售利润结算：（成交价格-商品成本）×提成百分比</div>
									  </div>
									  <?php endif; if(getcustom('yx_team_yeji_include_self')): ?>
									<div class="layui-form-item">
									  <label class="layui-form-label">团队业绩包含自己：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[teamyeji_include_self]" title="不包含" value="0" <?php if($info['teamyeji_include_self']==0): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[teamyeji_include_self]" title="包含" value="1" <?php if($info['teamyeji_include_self']==1): ?>checked<?php endif; ?>/>
									  </div>
									
									  <div class="layui-form-mid layui-word-aux layui-clear">团队业绩是否包含自己的业绩</div>
									</div>
								  <?php endif; ?>
								  
								 
								  <div class="layui-form-item">
									  <label class="layui-form-label"><?php echo t('团队分红'); ?>：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[teamfenhong_show]" title="显示" value="1" <?php if($info['teamfenhong_show']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[teamfenhong_show]" title="不显示" value="0" <?php if($info['teamfenhong_show']==0): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">前端我的佣金页面是否显示<?php echo t('团队分红'); ?></div>
								  </div>
								  <?php if(getcustom('business_teamfenhong')): ?>
								  <div class="layui-form-item">
									  <label class="layui-form-label"><?php echo t('商家团队分红'); ?>：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[business_teamfenhong_show]" title="显示" value="1" <?php if($info['business_teamfenhong_show']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[business_teamfenhong_show]" title="不显示" value="0" <?php if($info['business_teamfenhong_show']==0): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">前端我的佣金页面是否显示<?php echo t('商家团队分红'); ?></div>
								  </div>
								  <?php endif; ?>
								  <div class="layui-form-item">
									  <label class="layui-form-label"><?php echo t('佣金'); ?>明细：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[commissionlog_show]" title="显示" value="1" <?php if($info['commissionlog_show']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[commissionlog_show]" title="不显示" value="0" <?php if($info['commissionlog_show']==0): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">前端我的佣金页面是否显示<?php echo t('佣金'); ?>明细</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label"><?php echo t('佣金'); ?>记录：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[commissionrecord_show]" title="显示" value="1" <?php if($info['commissionrecord_show']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[commissionrecord_show]" title="不显示" value="0" <?php if($info['commissionrecord_show']==0): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">前端我的佣金页面是否显示<?php echo t('佣金'); ?>记录</div>
								  </div>
								  <div class="layui-form-item">
									<label class="layui-form-label"><?php echo t('佣金'); ?>提现记录：</label>
									<div class="layui-input-inline" style="width:300px">
										<input type="radio" name="info[commissionrecord_withdrawlog_show]" title="显示" value="1" <?php if($info['commissionrecord_withdrawlog_show']==1): ?>checked<?php endif; ?>/>
										<input type="radio" name="info[commissionrecord_withdrawlog_show]" title="不显示" value="0" <?php if($info['commissionrecord_withdrawlog_show']==0): ?>checked<?php endif; ?>/>
									</div>
									<div class="layui-form-mid layui-word-aux layui-clear">前端佣金提现页面是否显示<?php echo t('佣金'); ?>提现记录</div>
								</div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">分红订单：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[fhorder_show]" title="显示" value="1" <?php if($info['fhorder_show']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[fhorder_show]" title="不显示" value="0" <?php if($info['fhorder_show']==0): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">前端我的佣金页面是否显示分红订单</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">分红记录：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[fhlog_show]" title="显示" value="1" <?php if($info['fhlog_show']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[fhlog_show]" title="不显示" value="0" <?php if($info['fhlog_show']==0): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">前端我的佣金页面是否显示分红记录</div>
								  </div>
  								  <?php if(getcustom('fenhong_jiaquan_bylevel')): ?>
								  <div class="layui-form-item" style="display: flex;align-items: center;">
									  <label class="layui-form-label"><?php echo t('加权分红'); ?>结算比例：</label>
									  <div class="layui-input-inline" style="width:120px">
										  <input class="layui-input" name="info[fenhong_jqjs_rate]" id="fenhong_jqjs_rate" value="<?php echo (isset($info['fenhong_jqjs_rate']) && ($info['fenhong_jqjs_rate'] !== '')?$info['fenhong_jqjs_rate']:0); ?>">
									  </div>
									  <div class="layui-form-mid layui-word-aux">%,平台销售额的x%用于结算<?php echo t('加权分红'); ?></div>
								  </div>
								  <div class="layui-form-item" style="display: flex;align-items: center;">
									  <label class="layui-form-label"><?php echo t('加权分红'); ?>结算时间：</label>
									  <div class="layui-form-mid"><input type="radio" name="jqtype" title="每天" value="0" checked /></div>
									  <div class="layui-input-inline" style="width:120px">
										  <select name="info[fenhong_jqjs_time]">
											  <?php $__FOR_START_1605676324__=1;$__FOR_END_1605676324__=24;for($i=$__FOR_START_1605676324__;$i < $__FOR_END_1605676324__;$i+=1){ ?>
											  <option value="<?php echo $i; ?>" <?php if($info['fenhong_jqjs_time']==$i): ?>selected<?php endif; ?>><?php echo $i; ?>点</option>
											  <?php } ?>
										  </select>
									  </div>
									  <div class="layui-form-mid layui-word-aux">每天x点发放前一天的<?php echo t('加权分红'); ?></div>
								  </div>
  								  <?php endif; if(getcustom('fenhong_money_weishu')): ?>
								  <div class="layui-form-item">
									  <label class="layui-form-label"><?php echo t('佣金'); ?>小数位数：</label>
									  <div class="layui-input-inline layui-module-itemR">
										  <input type="number" min="2" max="6" name="info[fenhong_money_weishu]" class="layui-input" value="<?php echo (isset($info['fenhong_money_weishu']) && ($info['fenhong_money_weishu'] !== '')?$info['fenhong_money_weishu']:2); ?>">
											<div>位</div>
										</div>
									  <div class="layui-form-mid layui-word-aux layui-clear">产生佣金保留的小数位数，最大支持6位最小支持2位，默认2位</div>
								  </div>
								  <?php endif; if(getcustom('member_money_weishu')): ?>
								  <div class="layui-form-item">
									  <label class="layui-form-label"><?php echo t('余额'); ?>小数位数：</label>
									  <div class="layui-input-inline layui-module-itemR">
										  <input type="number" min="2" max="6" name="info[member_money_weishu]" class="layui-input" value="<?php echo (isset($info['member_money_weishu']) && ($info['member_money_weishu'] !== '')?$info['member_money_weishu']:2); ?>">
											<div>位</div>
										</div>
									  <div class="layui-form-mid layui-word-aux layui-clear"><?php echo t('会员'); ?>产生余额保留的小数位数，最大支持6位最小支持2位，默认2位</div>
								  </div>
								  <?php endif; if(getcustom('commission_service_fee')): ?>
								  <div class="layui-form-item">
									  <label class="layui-form-label"><?php echo t('佣金'); ?>平台服务费：</label>
									  <div class="layui-input-inline layui-module-itemR">
										  <input type="number" min="0" max="100" name="info[commission_service_fee]" class="layui-input" value="<?php echo (isset($info['commission_service_fee']) && ($info['commission_service_fee'] !== '')?$info['commission_service_fee']:0); ?>">
										  <div>%</div>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">产生<?php echo t('佣金'); ?>平台收取服务费比例，如设置10%，结算到<?php echo t('会员'); ?>佣金账户的金额为90%</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">移动端平台服务费：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[commission_service_fee_show]" title="显示" value="1" <?php if($info['commission_service_fee_show']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[commission_service_fee_show]" title="不显示" value="0" <?php if($info['commission_service_fee_show']==0): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">移动端<?php echo t('佣金'); ?>明细页面是否显示服务费</div>
								  </div>
								  <?php endif; if(getcustom('product_baodan')): ?>
								  <div class="layui-form-item">
									  <label class="layui-form-label">报单收益倍数：</label>
									  <div class="layui-input-inline layui-module-itemR">
										  <input type="number" min="1"  name="info[baodan_beishu]" class="layui-input" value="<?php echo (isset($info['baodan_beishu']) && ($info['baodan_beishu'] !== '')?$info['baodan_beishu']:2); ?>">
											<div>倍</div>
										</div>
									  <div class="layui-form-mid layui-word-aux layui-clear">收益的上限计算方式：报单产品的销售价格*数量*倍数</div>
								  </div>
								  <?php endif; if(getcustom('teamfenhong_max')): ?>
								<div class="layui-form-item">
								  <label class="layui-form-label">团队分红上限：</label>
								  <div class="layui-input-inline" style="width:300px">
									  <input type="radio" name="info[teamfenhong_max_type]" title="不限制" value="0" <?php if($info['teamfenhong_max_type']==0): ?>checked<?php endif; ?>/>
									  <input type="radio" name="info[teamfenhong_max_type]" title="订单金额" value="1" <?php if($info['teamfenhong_max_type']==1): ?>checked<?php endif; ?>/>
								  </div>
								  <div class="layui-form-mid layui-word-aux layui-clear">订单金额类型：当前订单的团队分红达到订单金额时，不再进行分红</div>
								</div>
								<?php endif; if(getcustom('teamfenhong_yueji')): ?>
							  <div class="layui-form-item">
								  <label class="layui-form-label">平级奖仅限直推上级：</label>
								  <div class="layui-input-inline" style="width:300px">
									  <input type="radio" name="info[teamfenhong_yueji]" title="关" value="0" <?php if($info['teamfenhong_yueji']==0): ?>checked<?php endif; ?>/>
									  <input type="radio" name="info[teamfenhong_yueji]" title="开" value="1" <?php if($info['teamfenhong_yueji']==1): ?>checked<?php endif; ?>/>
								  </div>
								  <div class="layui-form-mid layui-word-aux layui-clear">开启后，当前<?php echo t('会员'); ?>级别不等于上级<?php echo t('会员'); ?>级别就不再发团队分红平级奖</div>
							  </div>
  							<?php endif; if(getcustom('teamfenhong_pingji_yueji')): ?>
						  <div class="layui-form-item">
							  <label class="layui-form-label">平级奖允许越级：</label>
							  <div class="layui-input-inline" style="width:300px">
								  <input type="radio" name="info[teamfenhong_pingji_yueji]" title="关" value="0" <?php if($info['teamfenhong_pingji_yueji']==0): ?>checked<?php endif; ?>/>
								  <input type="radio" name="info[teamfenhong_pingji_yueji]" title="开" value="1" <?php if($info['teamfenhong_pingji_yueji']==1): ?>checked<?php endif; ?>/>
							  </div>
							  <div class="layui-form-mid layui-word-aux layui-clear">关闭后中间有人级别越级后不享受团队分红平级奖</div>
						  </div>
						  <?php endif; if(getcustom('teamfenhong_pingji_yueji_bonus')): ?>
						  <div class="layui-form-item">
							  <label class="layui-form-label">越级平级奖：</label>
							  <div class="layui-input-inline" style="width:300px">
								  <input type="radio" name="info[teamfenhong_pingji_yueji_bonus]" title="关" value="0" <?php if($info['teamfenhong_pingji_yueji_bonus']==0): ?>checked<?php endif; ?>/>
								  <input type="radio" name="info[teamfenhong_pingji_yueji_bonus]" title="开" value="1" <?php if($info['teamfenhong_pingji_yueji_bonus']==1): ?>checked<?php endif; ?>/>
							  </div>
							  <div class="layui-form-mid layui-word-aux layui-clear">开启后高级别可拿低级别平级奖</div>
						  </div>
						  <?php endif; if(getcustom('teamfenhong_pingji_diji_bonus')): ?>
						  <div class="layui-form-item">
							  <label class="layui-form-label">低级平级奖：</label>
							  <div class="layui-input-inline" style="width:300px">
								  <input type="radio" name="info[teamfenhong_pingji_diji_bonus]" title="关" value="0" <?php if($info['teamfenhong_pingji_diji_bonus']==0): ?>checked<?php endif; ?>/>
								  <input type="radio" name="info[teamfenhong_pingji_diji_bonus]" title="开" value="1" <?php if($info['teamfenhong_pingji_diji_bonus']==1): ?>checked<?php endif; ?>/>
							  </div>
							  <div class="layui-form-mid layui-word-aux layui-clear">开启后低级别可拿高级别平级奖</div>
						  </div>
						  <?php endif; if(getcustom('member_commission_max') && !getcustom('add_commission_max')  && ($auth_data == 'all' || in_array('member_commission_max',$auth_data))): ?>
						  <div class="layui-form-item">
							  <label class="layui-form-label"><?php echo t('佣金上限'); ?>：</label>
							  <div class="layui-input-inline" style="width:300px">
								  <input type="radio" name="info[member_commission_max]" title="关" value="0" <?php if($info['member_commission_max']==0): ?>checked<?php endif; ?> lay-filter="commission_max"/>
								  <input type="radio" name="info[member_commission_max]" title="开" value="1" <?php if($info['member_commission_max']==1): ?>checked<?php endif; ?> lay-filter="commission_max"/>
							  </div>
							  <div class="layui-form-mid layui-word-aux layui-clear">开启后<?php echo t('会员'); ?>累计获得佣金不可高于<?php echo t('佣金上限'); ?></div>
						  </div>
						  <?php endif; if(getcustom('member_commission_max') && getcustom('member_commission_max_toscore') && !getcustom('add_commission_max')  && ($auth_data == 'all' || in_array('member_commission_max',$auth_data))): ?>
						  <div class="layui-form-item" id="commission_max_toscore" <?php if($info['member_commission_max']==0): ?>style="display:none"<?php endif; ?>>
							  <label class="layui-form-label"><?php echo t('佣金上限'); ?>转<?php echo t('积分'); ?>：</label>
							  <div class="layui-input-inline" style="width:140px">
								  <input type="radio" name="info[member_commission_max_toscore_st]" title="关" value="0" <?php if($info['member_commission_max_toscore_st']==0): ?>checked<?php endif; ?> lay-filter="commission_max_toscore_st"/>
								  <input type="radio" name="info[member_commission_max_toscore_st]" title="开" value="1" <?php if($info['member_commission_max_toscore_st']==1): ?>checked<?php endif; ?> lay-filter="commission_max_toscore_st"/>
							  </div>
							  <div class="layui-input-inline layui-module-itemR" id="commission_max_toscore_ratio" <?php if($info['member_commission_max_toscore_st'] ==0): ?>style="display:none"<?php endif; ?>>
								  <input type="number" min="0" max="100" name="info[member_commission_max_toscore_ratio]" class="layui-input" value="<?php echo $info['member_commission_max_toscore_ratio']; ?>">
								  <div>%</div>
							  </div>
							  <div class="layui-form-mid layui-word-aux layui-clear">开启后当<?php echo t('会员'); ?><?php echo t('佣金'); ?>达到上限后，会按照比例转换为<?php echo t('积分'); ?></div>
						  </div>
						  <?php endif; if(getcustom('commission_orderrefund_deduct')): ?>
						  <div class="layui-form-item">
							  <label class="layui-form-label">退款扣除佣金：</label>
							  <div class="layui-input-inline" style="width:300px">
								  <input type="radio" name="info[open_commission_orderrefund_deduct]" title="关" value="0" <?php if($info['open_commission_orderrefund_deduct']==0): ?>checked<?php endif; ?>/>
								  <input type="radio" name="info[open_commission_orderrefund_deduct]" title="开" value="1" <?php if($info['open_commission_orderrefund_deduct']==1): ?>checked<?php endif; ?>/>
							  </div>
							  <div class="layui-form-mid layui-word-aux layui-clear">开启后订单退款，已发放的佣金追回扣除</div>
						  </div>
						  <?php endif; if(getcustom('ciruikang_fenxiao')): ?>
							<div class="layui-form-item">
							  <label class="layui-form-label">商品需上级购买足量：</label>
							  <div class="layui-input-inline" style="width:300px">
							  	<input type="radio" name="info[open_product_parentbuy]" title="开启" value="1" <?php if($info['open_product_parentbuy']==1): ?>checked<?php endif; ?>/>
								  <input type="radio" name="info[open_product_parentbuy]" title="关闭" value="0" <?php if(!$info['open_product_parentbuy'] || $info['open_product_parentbuy']==0): ?>checked<?php endif; ?>/>
							  </div>
							  <div class="layui-form-mid layui-word-aux layui-clear">开启后:商城商品下级购买商品规格需要上级已购买过同样的商品规格，且上级购买数量大于等于下级购买数量，下级支付成功上级相同商品规格的库存减少<br>不开启则不验证上级相同商品规格库存，上级库存不减少</div>
						  </div>
						  <?php endif; if(getcustom('commission_to_money')): ?>
						  <div class="layui-form-item">
							  <label class="layui-form-label"><?php echo t('佣金'); ?>发放到<?php echo t('余额'); ?>：</label>
							  <div class="layui-input-inline" style="width:200px">
							  	<input type="radio" name="info[commission_send_money]" title="开启" value="1" <?php if($info['commission_send_money']==1): ?>checked<?php endif; ?>/>
								  <input type="radio" name="info[commission_send_money]" title="关闭" value="0" <?php if(!$info['commission_send_money'] || $info['commission_send_money']==0): ?>checked<?php endif; ?>/>
							  </div>

							  	<label class="layui-form-label">发放比例：</label>
							    <div class="layui-input-inline layui-module-itemR">
								    <input type="number" min="0" max="100" name="info[commission_send_money_bili]" class="layui-input" value="<?php echo $info['commission_send_money_bili']; ?>">
									<div>默认是100%</div>
								</div>
							  <div class="layui-form-mid layui-word-aux layui-clear">开启后发放<?php echo t('佣金'); ?>数额将按照填写的比例，发放到<?php echo t('余额'); ?>中</div>
						  </div>
						<?php endif; if(getcustom('restaurant_team_yeji')): ?>
						  <div class="layui-form-item">
							  <label class="layui-form-label">餐饮订单计入团队业绩：</label>
							  <div class="layui-input-inline" style="width:200px">
							  	<input type="radio" name="info[restaurant_team_yeji_open]" title="开启" value="1" <?php if($info['restaurant_team_yeji_open']==1): ?>checked<?php endif; ?>/>
								  <input type="radio" name="info[restaurant_team_yeji_open]" title="关闭" value="0" <?php if(!$info['restaurant_team_yeji_open'] || $info['restaurant_team_yeji_open']==0): ?>checked<?php endif; ?>/>
							  </div>
							  <div class="layui-form-mid layui-word-aux layui-clear">开启后餐饮订单参与升级条件统计</div>
						  </div>
						<?php endif; if(getcustom('teamfenhong_bole')): ?>
						  <div class="layui-form-item">
							  <label class="layui-form-label"><?php echo t('团队分红伯乐奖'); ?>参与等级：</label>
							  <div class="layui-input-inline" style="width:800px">
								  <input type="checkbox" name="info[teamfenhong_bl_levelids][]" value="-1" title="所有人" lay-skin="primary" <?php if(in_array('-1',explode(',',$info['teamfenhong_bl_levelids']))): ?>checked<?php endif; ?> />
								  <?php foreach($levellist as $v): ?>
								  <input type="checkbox" name="info[teamfenhong_bl_levelids][]" value="<?php echo $v['id']; ?>" title="<?php echo $v['name']; ?>" lay-skin="primary" <?php if(in_array($v['id'],explode(',',$info['teamfenhong_bl_levelids']))): ?>checked<?php endif; ?>/>
								  <?php endforeach; ?>
							  </div>
							  <div class="layui-form-mid layui-word-aux layui-clear">未选中等级不参与<?php echo t('团队分红伯乐奖'); ?></div>
						  </div>
						  <?php endif; if(getcustom('commission_frozen')): if($admin['commission_frozen']): ?>
								  <fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
									  <legend>扶持金</legend>
								  </fieldset>
								  <div class="layui-form-item">
									  <label class="layui-form-label">扶持金比例：</label>
									  <div class="layui-input-inline layui-module-itemR">
										  <input type="number" min="0" max="100" name="info[fuchi_percent]" class="layui-input" value="<?php echo $info['fuchi_percent']; ?>">
											<div>%</div>
										</div>
									  <div class="layui-form-mid layui-word-aux layui-clear">
										  <input type="checkbox" name="info[fuchi_only_teamfenhong]" value="1" <?php if($info['fuchi_only_teamfenhong']==1): ?>checked<?php endif; ?> title="仅冻结团队分红" lay-skin="primary">
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">请输入0-100之间的数字，100代表全部；冻结<?php echo t('会员'); ?>获得<?php echo t('佣金'); ?>的百分比作为扶持金</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">扶持金参与等级：</label>
									  <div class="layui-input-inline" style="width:800px">
										  <input type="checkbox" name="info[fuchi_levelids][]" value="-1" title="所有人" lay-skin="primary" <?php if(in_array('-1',explode(',',$info['fuchi_levelids']))): ?>checked<?php endif; ?> />
										  <?php foreach($levellist as $v): ?>
										  <input type="checkbox" name="info[fuchi_levelids][]" value="<?php echo $v['id']; ?>" title="<?php echo $v['name']; ?>" lay-skin="primary" <?php if(in_array($v['id'],explode(',',$info['fuchi_levelids']))): ?>checked<?php endif; ?>/>
										  <?php endforeach; ?>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">未选中等级不参与冻结</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">解冻方式：</label>
									  <div class="layui-input-inline" style="width:400px">
										  <input type="radio" name="info[fuchi_unfrozen_type]" value="1" title="全部解冻" <?php if($info['fuchi_unfrozen_type']==1): ?>checked<?php endif; ?>/ lay-filter="diandaSwitchMulti">
										  <input type="radio" name="info[fuchi_unfrozen_type]" value="2" title="直推<?php echo t('会员'); ?>单线解冻" <?php if($info['fuchi_unfrozen_type']==2): ?>checked<?php endif; ?>/ lay-filter="diandaSwitchMulti">
									  </div>
								  </div>
								  <div class="layui-form-item commission_frozen">
									  <label class="layui-form-label">解冻条件：</label>
									  <div style="float:left; margin-left: 0;">
										  <div class="commission_frozen_row">
											  <input type="checkbox" name="info[fuchi_unfrozen][]" value="1" <?php if(in_array('1',explode(',',$info['fuchi_unfrozen']))): ?>checked<?php endif; ?> title="" lay-skin="primary">
											  <div style="float:left;">
												  <div class="checkbox-text">伞下 <div class="layui-input-inline" style="width: 100px;float: none;">
													  <input type="text" name="info[fuchi_unfrozen1_num]" class="layui-input" value="<?php echo $info['fuchi_unfrozen1_num']; ?>">
												  </div>个,
													  <div class="layui-input-inline" style="width: 100px;float: none;">
														  <input type="text" name="info[fuchi_unfrozen1_ceng]" class="layui-input" value="<?php echo $info['fuchi_unfrozen1_ceng']; ?>">
													  </div>层内,
													  等级ID为<div class="layui-input-inline" style="width: 100px;margin-left:5px;float: none;">
													  <input type="text" name="info[fuchi_unfrozen1_levelid]" class="layui-input" value="<?php echo $info['fuchi_unfrozen1_levelid']; ?>">
												  </div>的<?php echo t('会员'); ?></div>
											  </div>
										  </div>
										  <div diandaSwitchMulti="info[fuchi_unfrozen_type]" data-val="1" <?php if($info['fuchi_unfrozen_type'] != 1): ?> style="display:none"<?php endif; ?>>
											  <div class="commission_frozen_row">
												  <input type="checkbox" name="info[fuchi_unfrozen][]" value="2" <?php if(in_array('2',explode(',',$info['fuchi_unfrozen']))): ?>checked<?php endif; ?>  title="" lay-skin="primary">
												  <div class="checkbox-text">直推脱离的所有<?php echo t('会员'); ?>等级ID升级为<div class="layui-input-inline" style="width: 100px;margin-left:5px;float: none;">
													  <input type="text" name="info[fuchi_unfrozen2_levelid]" class="layui-input" value="<?php echo $info['fuchi_unfrozen2_levelid']; ?>"></div></div>
											  </div>
									      </div>
										  <div diandaSwitchMulti="info[fuchi_unfrozen_type]" data-val="2" <?php if($info['fuchi_unfrozen_type'] != 2): ?> style="display:none"<?php endif; ?>>
											  <div class="commission_frozen_row">
												  <input type="checkbox" name="info[fuchi_unfrozen][]" value="3" <?php if(in_array('3',explode(',',$info['fuchi_unfrozen']))): ?>checked<?php endif; ?>  title="" lay-skin="primary">
												  <div class="checkbox-text">直推<?php echo t('会员'); ?>等级ID升级为<div class="layui-input-inline" style="width: 100px;margin-left:5px;float: none;">
													  <input type="text" name="info[fuchi_unfrozen3_levelid]" class="layui-input" value="<?php echo $info['fuchi_unfrozen3_levelid']; ?>"></div></div>
											  </div>
										  </div>
									</div>
									<div class="layui-form-mid layui-word-aux layui-clear">解冻规则一次性设置好，不可随意改动（已解冻会员满足条件后不会再次冻结），等级ID多个用英文逗号分隔</div>
								  </div>
									  <?php endif; ?>
								  <?php endif; if(getcustom('commission_parent_pj_send_once')): ?>
										<div class="layui-form-item">
											<label class="layui-form-label">分销平级奖只发一次：</label>
											<div class="layui-input-inline">
												<input type="radio" name="info[commission_parent_pj_send_once]" title="关闭" value="0" <?php if($info['commission_parent_pj_send_once']==0): ?>checked<?php endif; ?>/>
												<input type="radio" name="info[commission_parent_pj_send_once]" title="开启" value="1" <?php if($info['commission_parent_pj_send_once']==1): ?>checked<?php endif; ?>/>
											</div>
											<div class="layui-form-mid layui-word-aux layui-clear">开启后分销平级奖只会给最近的上级发一次，关闭则只要是平级就会发</div>
										</div>
									<?php endif; if(getcustom('commission_parent_bcy_send_once')): ?>
										<div class="layui-form-item">
											<label class="layui-form-label">被超越奖：</label>
											<div class="layui-input-inline">
												<input type="radio" name="info[commission_parent_bcy_send_once]" title="关闭" value="0" <?php if($info['commission_parent_bcy_send_once']==0): ?>checked<?php endif; ?>/>
												<input type="radio" name="info[commission_parent_bcy_send_once]" title="开启" value="1" <?php if($info['commission_parent_bcy_send_once']==1): ?>checked<?php endif; ?>/>
											</div>
											<div class="layui-form-mid layui-word-aux layui-clear">开启后当下级超越上级的时候，下级以下团队再出现订单会有一个被超越奖给被超越的上级</div>
										</div>
									<?php endif; if(getcustom('commission_zhitui_special_first_order')): ?>
										<div class="layui-form-item">
											<label class="layui-form-label">直推特殊奖：</label>
											<div class="layui-input-inline">
												<input type="radio" name="info[commission_zhitui_special_first_order]" title="关闭" value="0" <?php if($info['commission_zhitui_special_first_order']==0): ?>checked<?php endif; ?>/>
												<input type="radio" name="info[commission_zhitui_special_first_order]" title="开启" value="1" <?php if($info['commission_zhitui_special_first_order']==1): ?>checked<?php endif; ?>/>
											</div>
											<div class="layui-form-mid layui-word-aux layui-clear">开启后当用户购买首单时，直推上级会根据级别里的设置获得额外百分比的奖励，仅限于第一个订单</div>
										</div>
									<?php endif; if(getcustom('commission_shengdai_special')): ?>
										<div class="layui-form-item">
											<label class="layui-form-label">省代特殊奖：</label>
											<div class="layui-input-inline">
												<input type="radio" name="info[commission_shengdai_special]" title="关闭" value="0" <?php if($info['commission_shengdai_special']==0): ?>checked<?php endif; ?>/>
												<input type="radio" name="info[commission_shengdai_special]" title="开启" value="1" <?php if($info['commission_shengdai_special']==1): ?>checked<?php endif; ?>/>
											</div>
											<div class="layui-form-mid layui-word-aux layui-clear">开启后如果级别里设置了奖励，分销奖励会根据下单会员级别里的设置进行发放</div>
										</div>
									<?php endif; if(getcustom('mendian_member_levelup_fenhong')): ?>
										<div class="layui-form-item">
											<label class="layui-form-label">扫门店码升级分红：</label>
											<div class="layui-input-inline">
												<input type="radio" name="info[mendian_member_levelup_fenhong]" title="关闭" value="0" <?php if($info['mendian_member_levelup_fenhong']==0): ?>checked<?php endif; ?>/>
												<input type="radio" name="info[mendian_member_levelup_fenhong]" title="开启" value="1" <?php if($info['mendian_member_levelup_fenhong']==1): ?>checked<?php endif; ?>/>
											</div>
											<div class="layui-form-mid layui-word-aux layui-clear">开启后<?php echo t('会员'); ?>扫描门店二维码支付升级后，会给门店绑定的会员分红（只针对于升级费用）</div>
										</div>
									<?php endif; if(getcustom('commission_xiaofei')): ?>
							  <fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
								  <legend><?php echo t('冻结佣金'); ?></legend>
							  </fieldset>
							  <div class="layui-form-item">
								  <label class="layui-form-label">冻结<?php echo t('佣金'); ?>比例：</label>
								  <div class="layui-input-inline layui-module-itemR">
									  <input type="number" min="0" max="100" name="info[xiaofei_percent]" class="layui-input" value="<?php echo $info['xiaofei_percent']; ?>">
									  <div>%</div>
								  </div>
								  <div class="layui-form-mid layui-word-aux layui-clear">请输入0-100之间的数字，100代表全部；所有直推、间推、团队分红等收益<?php echo t('佣金'); ?>冻结，用于购买指定商品</div>
							  </div>
							  <div class="layui-form-item">
								  <label class="layui-form-label"><?php echo t('冻结佣金'); ?>参与等级：</label>
								  <div class="layui-input-inline" style="width:800px">
									  <input type="checkbox" name="info[xiaofei_levelids][]" value="-1" title="所有人" lay-skin="primary" <?php if(in_array('-1',explode(',',$info['xiaofei_levelids']))): ?>checked<?php endif; ?> />
									  <?php foreach($levellist as $v): ?>
									  <input type="checkbox" name="info[xiaofei_levelids][]" value="<?php echo $v['id']; ?>" title="<?php echo $v['name']; ?>" lay-skin="primary" <?php if(in_array($v['id'],explode(',',$info['xiaofei_levelids']))): ?>checked<?php endif; ?>/>
									  <?php endforeach; ?>
								  </div>
								  <div class="layui-form-mid layui-word-aux layui-clear">未选中等级不参与</div>
							  </div>

							  <?php endif; if(getcustom('up_giveparent_help')): ?>
							 <div class="layui-form-item">
								  <label class="layui-form-label">脱离帮扶：</label>
								  <div class="layui-input-inline layui-module-itemL">
									<div>脱离人员</div>
									<input type="text" name="info[help_con_day]" class="layui-input" value="<?php echo $info['help_con_day']; ?>">天内
								  </div>
								 <div class="layui-input-inline layui-module-itemL">
									<div>总佣金收入少于</div>
									<input type="text" name="info[help_con_commission]" class="layui-input" value="<?php echo $info['help_con_commission']; ?>">元
								  </div>
								 <div class="layui-input-inline layui-module-itemL">
									<div>直推会员</div>
									<input type="text" name="info[help_member_num]" class="layui-input" value="<?php echo $info['help_member_num']; ?>">人随机划拨给脱离人员的现上级
								  </div>
								  <div class="layui-form-mid layui-word-aux layui-clear">
									  当脱离人员N天内的佣金收益未达到Y元，那么现下级随机N人自动划拨给脱离人员的现上级(当我变成Y等级（老板）开始计算周期，也就是脱离之后 )
								  </div>
							  </div>
  							<?php endif; if(getcustom('commissionranking')): ?>
								 <fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
										<legend>排行榜</legend>
									</fieldset>
								  <div class="layui-form-item">
									  <label class="layui-form-label">排行榜标题：</label>
									  <div class="layui-input-inline">
										  <input type="text" name="info[rank_title]" class="layui-input" value="<?php echo (isset($info['rank_title']) && ($info['rank_title'] !== '')?$info['rank_title']:'业绩排行榜'); ?>">
									  </div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">排行榜描述：</label>
									  <div class="layui-input-inline">
										  <input type="text" name="info[rank_desc]" class="layui-input" value="<?php echo (isset($info['rank_desc']) && ($info['rank_desc'] !== '')?$info['rank_desc']:'历史累积榜单'); ?>">
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">标题下的描述</div>
								  </div>
									<div class="layui-form-item">
										<label class="layui-form-label">排行榜状态：</label>
										<div class="layui-input-inline" style="width:400px">
											<input type="radio" name="info[rank_status]" value="0" title="关闭" <?php if($info['rank_status']==0): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[rank_status]" value="1" title="开启" <?php if($info['rank_status']==1): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">开启后，手机端佣金排行榜页面展示排行</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">排行榜类型：</label>
										<div class="layui-input-inline" style="width:400px">
											<input type="checkbox" name="info[rank_type][]" value="1" <?php if(in_array('1',explode(',',$info['rank_type']))): ?>checked<?php endif; ?> title="累计佣金" lay-skin="primary">
											<input type="checkbox" name="info[rank_type][]" value="2" <?php if(in_array('2',explode(',',$info['rank_type']))): ?>checked<?php endif; ?>  title="自购订单金额" lay-skin="primary">
										</div>
									</div>
                                    <?php if(getcustom('yx_queue_free')): ?>
									<div class="layui-form-item">
									  <label class="layui-form-label">统计<?php echo t('排队返利'); ?>：</label>
									  <div class="layui-input-inline" style="width:400px">
										  <input type="radio" name="info[rank_queue_free_status]" value="0" title="关闭" <?php if($info['rank_queue_free_status']==0): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[rank_queue_free_status]" value="1" title="开启" <?php if($info['rank_queue_free_status']==1): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">开启后，将统计<?php echo t('排队返利'); ?>获得的佣金，前端数据不再支持分页 </div>
									</div>
  									<?php endif; ?>
									<div class="layui-form-item">
										<label class="layui-form-label">排行榜日期范围：</label>
										<div class="layui-input-inline" style="width:570px">
											<input type="radio" name="info[rank_date]" value="1" title="历史累计" <?php if($info['rank_date']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[rank_date]" value="2" title="自然周" <?php if($info['rank_date']==2): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[rank_date]" value="3" title="自然月" <?php if($info['rank_date']==3): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[rank_date]" value="4" title="近七日" <?php if($info['rank_date']==4): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[rank_date]" value="5" title="近30天" <?php if($info['rank_date']==5): ?>checked<?php endif; ?>/>
										</div>							
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">排行榜显示人数：</label>
										<div class="layui-input-inline layui-module-itemR">
											<input type="text" name="info[rank_people]" class="layui-input" value="<?php echo $info['rank_people']; ?>">
											<div>人</div>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">请输入1-100之间的数字，0代表全部</div>
									</div>
								 <?php endif; if(getcustom('fenhong_ranking')): ?>
								  <fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
									  <legend>分红排行榜</legend>
								  </fieldset>
								  <div class="layui-form-item">
									  <label class="layui-form-label">排行榜标题：</label>
									  <div class="layui-input-inline">
										  <input type="text" name="info[fenhong_rank_title]" class="layui-input" value="<?php echo (isset($info['fenhong_rank_title']) && ($info['fenhong_rank_title'] !== '')?$info['fenhong_rank_title']:'分红排行榜'); ?>">
									  </div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">排行榜描述：</label>
									  <div class="layui-input-inline">
										  <input type="text" name="info[fenhong_rank_desc]" class="layui-input" value="<?php echo (isset($info['fenhong_rank_desc']) && ($info['fenhong_rank_desc'] !== '')?$info['fenhong_rank_desc']:'历史分红累积榜单'); ?>">
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">标题下的描述</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">排行榜状态：</label>
									  <div class="layui-input-inline" style="width:400px">
										  <input type="radio" name="info[fenhong_rank_status]" value="0" title="关闭" <?php if($info['fenhong_rank_status']==0): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[fenhong_rank_status]" value="1" title="开启" <?php if($info['fenhong_rank_status']==1): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">开启后，手机端佣金排行榜页面展示排行</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">排行榜类型：</label>
									  <div class="layui-input-inline" style="width:400px">
										  <input type="checkbox" name="info[fenhong_rank_type][]" value="1" <?php if(in_array('1',explode(',',$info['fenhong_rank_type']))): ?>checked<?php endif; ?> title="分红佣金" lay-skin="primary">
										  <input type="checkbox" name="info[fenhong_rank_type][]" value="2" <?php if(in_array('2',explode(',',$info['fenhong_rank_type']))): ?>checked<?php endif; ?>  title="分销佣金" lay-skin="primary">
									  </div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">排行榜日期范围：</label>
									  <div class="layui-input-inline" style="width:570px">
										  <input type="radio" name="info[fenhong_rank_date]" value="1" title="历史累计" <?php if($info['fenhong_rank_date']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[fenhong_rank_date]" value="2" title="自然周" <?php if($info['fenhong_rank_date']==2): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[fenhong_rank_date]" value="3" title="自然月" <?php if($info['fenhong_rank_date']==3): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[fenhong_rank_date]" value="4" title="近七日" <?php if($info['fenhong_rank_date']==4): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[fenhong_rank_date]" value="5" title="近30天" <?php if($info['fenhong_rank_date']==5): ?>checked<?php endif; ?>/>
									  </div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">排行榜显示人数：</label>
									  <div class="layui-input-inline layui-module-itemR">
										  <input type="text" name="info[fenhong_rank_people]" class="layui-input" value="<?php echo $info['fenhong_rank_people']; ?>">
											<div>人</div>
										</div>
									  <div class="layui-form-mid layui-word-aux layui-clear">请输入1-100之间的数字，0代表全部</div>
								  </div>
								  <?php endif; if(getcustom('areafenhong_region_ranking')): ?>
								  <fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
									  <legend>区域代理排行榜</legend>
								  </fieldset>
								  <div class="layui-form-item">
									  <label class="layui-form-label">排行榜状态：</label>
									  <div class="layui-input-inline" style="width:400px">
										  <input type="radio" name="info[region_rank_status]" value="0" title="关闭" <?php if($info['region_rank_status']==0): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[region_rank_status]" value="1" title="开启" <?php if($info['region_rank_status']==1): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">开启后，手机端区域代理排行榜页面展示排行</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">时间范围：</label>
									  <div class="layui-inline">
										  <div class="layui-input-inline" style="width:300px;margin-left:0px">
											  <input type="text" name="info[region_ctime]" id="region_ctime" autocomplete="off" value="<?php echo $info['region_ctime']; ?> " class="layui-input">
										  </div>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">开启后，手机端区域代理排行榜页面展示排行</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">统计类型：</label>
									  <div class="layui-input-inline" style="width:800px">
										  <input type="checkbox" name="info[region_show_type][]" value="1" title="订单金额" lay-skin="primary" <?php if(in_array('1',explode(',',$info['region_show_type']))): ?>checked<?php endif; ?> />
										  <input type="checkbox" name="info[region_show_type][]" value="2" title="订单数量" lay-skin="primary" <?php if(in_array('2',explode(',',$info['region_show_type']))): ?>checked<?php endif; ?> />
									  </div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">区域代理查看等级：</label>
									  <div class="layui-input-inline" style="width:800px">
										  <input type="checkbox" name="info[region_rank_levelids][]" value="-1" title="所有人" lay-skin="primary" <?php if(in_array('-1',explode(',',$info['region_rank_levelids']))): ?>checked<?php endif; ?> />
										  <?php foreach($levellist as $v): ?>
										  <input type="checkbox" name="info[region_rank_levelids][]" value="<?php echo $v['id']; ?>" title="<?php echo $v['name']; ?>" lay-skin="primary" <?php if(in_array($v['id'],explode(',',$info['region_rank_levelids']))): ?>checked<?php endif; ?>/>
										  <?php endforeach; ?>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">未选中等级不能查看</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">排行榜显示人数：</label>
									  <div class="layui-input-inline layui-module-itemR">
										  <input type="text" name="info[region_rank_people]" class="layui-input" value="<?php echo $info['region_rank_people']; ?>">
										  <div>人</div>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">请输入1-100之间的数字，0代表全部</div>
								  </div>
								  <?php endif; if(getcustom('score_ranking')): ?>
								  <fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
									  <legend><?php echo t('积分'); ?>排行榜</legend>
								  </fieldset>
								  <div class="layui-form-item">
									  <label class="layui-form-label">排行榜状态：</label>
									  <div class="layui-input-inline" style="width:400px">
										  <input type="radio" name="info[score_rank_status]" value="0" title="关闭" <?php if($info['score_rank_status']==0): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[score_rank_status]" value="1" title="开启" <?php if($info['score_rank_status']==1): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">开启后，手机端佣金排行榜页面展示排行</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">排行榜日期范围：</label>
									  <div class="layui-input-inline" style="width:570px">
										  <input type="radio" name="info[score_rank_date]" value="1" title="历史累计" <?php if($info['score_rank_date']==1): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[score_rank_date]" value="2" title="自然周" <?php if($info['score_rank_date']==2): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[score_rank_date]" value="3" title="自然月" <?php if($info['score_rank_date']==3): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[score_rank_date]" value="4" title="近七日" <?php if($info['score_rank_date']==4): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[score_rank_date]" value="5" title="近30天" <?php if($info['score_rank_date']==5): ?>checked<?php endif; ?>/>
									  </div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">排行榜显示人数：</label>
									  <div class="layui-input-inline layui-module-itemR">
										  <input type="text" name="info[score_rank_people]" class="layui-input" value="<?php echo $info['score_rank_people']; ?>">
											<div>人</div>
										</div>
									  <div class="layui-form-mid layui-word-aux layui-clear">请输入1-100之间的数字，0代表全部</div>
								  </div>
								  <?php endif; if(getcustom('team_yeji_ranking')): if($team_yeji_show): ?>
									  <fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
										  <legend>团队业绩排行榜</legend>
									  </fieldset>
									  <div class="layui-form-item">
										  <label class="layui-form-label">排行榜状态：</label>
										  <div class="layui-input-inline" style="width:400px">
											  <input type="radio" name="info[teamyj_rank_status]" value="0" title="关闭" <?php if($info['teamyj_rank_status']==0): ?>checked<?php endif; ?>/>
											  <input type="radio" name="info[teamyj_rank_status]" value="1" title="开启" <?php if($info['teamyj_rank_status']==1): ?>checked<?php endif; ?>/>
										  </div>
										  <div class="layui-form-mid layui-word-aux layui-clear">开启后，手机端团队业绩排行榜页面展示排行</div>
									  </div>
									  <div class="layui-form-item">
										  <label class="layui-form-label">排行榜显示人数：</label>
										  <div class="layui-input-inline layui-module-itemR">
											  <input type="text" name="info[teamyj_rank_people]" class="layui-input" value="<?php echo $info['teamyj_rank_people']; ?>">
											  <div>人</div>
										  </div>
										  <div class="layui-form-mid layui-word-aux layui-clear">请输入1-100之间的数字，0代表全部</div>
									  </div>
  									<?php endif; ?>
								  <?php endif; if(getcustom('member_gongxian')): if($admin['member_gongxian_status']): ?>
								  <fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
									  <legend><?php echo t('贡献'); ?></legend>
								  </fieldset>
								  <div class="layui-form-item">
									  <label class="layui-form-label">消费赠<?php echo t('贡献'); ?>：</label>
											<div class="layui-input-inline layui-module-itemL">
												<div>消费每满</div>
												<input type="text" name="info[gongxianin_money]" class="layui-input" value="<?php echo $info['gongxianin_money']; ?>">
												元
											</div>
											<div class="layui-input-inline layui-module-itemL">
												<div>赠送</div>
												<input type="text" name="info[gognxianin_value]" class="layui-input" value="<?php echo $info['gognxianin_value']; ?>">
												<?php echo t('贡献'); ?>
											</div>
									  <div class="layui-form-mid layui-word-aux layui-clear">支付后即到账</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">多商户商品不赠送：</label>
									  <div class="layui-input-inline" style="width:300px">
										  <input type="radio" name="info[gongxian_bonus_disable]" title="否" value="0" <?php if($info['gongxian_bonus_disable']==0): ?>checked<?php endif; ?>/>
										  <input type="radio" name="info[gongxian_bonus_disable]" title="是" value="1" <?php if($info['gongxian_bonus_disable']==1): ?>checked<?php endif; ?>/>
									  </div>
									  <div class="layui-form-mid layui-word-aux layui-clear">如果选择“是”，购买多商户商品将不参与赠送贡献值</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">有效期：</label>
									  <div class="layui-input-inline layui-module-itemR">
										  <input type="text" name="info[gongxian_days]" class="layui-input" value="<?php echo $info['gongxian_days']; ?>">
											<div>天</div>
										</div>
									  <div class="layui-form-mid layui-word-aux layui-clear">如设置的有效期为30天，则30天后过期</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">订单分配比例：</label>
									  <div class="layui-input-inline layui-module-itemR">
										  <input type="text" name="info[gongxian_percent]" class="layui-input" value="<?php echo $info['gongxian_percent']; ?>">
											<div>%</div>
										</div>
									  <div class="layui-form-mid layui-word-aux layui-clear">如设置10%，则拿出订单金额的10%计算分红</div>
								  </div>

								<?php endif; ?>
  								<?php endif; if(getcustom('touzi_fenhong')): if($admin['shareholder_status']): ?>
							  <fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
								  <legend><?php echo t('投资分红'); ?></legend>
							  </fieldset>
							  <div class="layui-form-item">
								  <label class="layui-form-label">分红结算方式：</label>
								  <div class="layui-input-inline" style="width:300px">
									  <input type="radio" name="info[touzi_fh_type]" title="按销售金额" value="0" <?php if($info['touzi_fh_type']==0): ?>checked<?php endif; ?>/>
									  <input type="radio" name="info[touzi_fh_type]" title="按销售利润" value="1" <?php if($info['touzi_fh_type']==1): ?>checked<?php endif; ?>/>
								  </div>
								  <div class="layui-form-mid layui-word-aux layui-clear">按销售金额结算即：销售价格×提成百分比，按销售利润即：（销售价格-商品成本）×提成百分比</div>
							  </div>
  								<div class="layui-form-item">
								  <label class="layui-form-label">分配比例：</label>
								  <div class="layui-input-inline layui-module-itemR">
									  <input type="text" name="info[touzi_fh_percent]" class="layui-input" value="<?php echo $info['touzi_fh_percent']; ?>">
										<div>%</div>
									</div>
								  <div class="layui-form-mid layui-word-aux layui-clear">如设置10%，则是本人投资额 ÷ 所有人的投资额总和 x 订单销售额（或利润）x 10%</div>
							  </div>
							  <?php endif; ?>
						  <?php endif; if(getcustom('member_dedamount')): ?>
							  <fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
								  <legend>抵扣金</legend>
							  </fieldset>
							  <div class="layui-form-item">
									<label class="layui-form-label">消费赠抵扣金：</label>
									<div class="layui-input-inline layui-module-itemL">
										<div>消费每满</div>
										<input type="text" name="info[dedamount_fullmoney]" class="layui-input" value="<?php echo $info['dedamount_fullmoney']; ?>">
										元
									</div>
									<div class="layui-input-inline layui-module-itemL">
										<div>赠送</div>
										<input type="text" name="info[dedamount_givemoney]" class="layui-input" value="<?php echo $info['dedamount_givemoney']; ?>">
										抵扣金
									</div>
									<div class="layui-form-mid layui-word-aux">会员在完成商家商城商品订单及商家买单时，会得到未抵扣、未优惠前的金额对应比例的抵扣金赠送</div>
								</div>
								<div class="layui-form-item">
								  <label class="layui-form-label">消费赠送类型一：</label>
								  <div class="layui-input-inline" style="width:400px">
									  <input type="radio" name="info[dedamount_type]" title="全部" value="0" <?php if($info['dedamount_type']==0): ?>checked<?php endif; ?>/>
									  <input type="radio" name="info[dedamount_type]" title="仅平台消费" value="1" <?php if($info['dedamount_type']==1): ?>checked<?php endif; ?>/>
									  <input type="radio" name="info[dedamount_type]" title="仅商户消费" value="2" <?php if($info['dedamount_type']==2): ?>checked<?php endif; ?>/>
								  </div>
							  </div>
							  <div class="layui-form-item">
								  <label class="layui-form-label">消费赠送类型二：</label>
								  <div class="layui-input-inline" style="width:400px">
									  <input type="radio" name="info[dedamount_type2]" title="全部" value="0" <?php if($info['dedamount_type2']==0): ?>checked<?php endif; ?>/>
									  <input type="radio" name="info[dedamount_type2]" title="仅商城订单" value="1" <?php if($info['dedamount_type2']==1): ?>checked<?php endif; ?>/>
									  <input type="radio" name="info[dedamount_type2]" title="仅买单订单" value="2" <?php if($info['dedamount_type2']==2): ?>checked<?php endif; ?>/>
								  </div>
								  <div class="layui-form-mid layui-word-aux layui-clear">消费赠送目前仅支持商城订单和买单订单赠送 </div>
							  </div>
							  <div class="layui-form-item">
								  <label class="layui-form-label">下单抵扣比例：</label>
								  <div class="layui-input-inline layui-module-itemR">
									  <input type="number" min="0" max="100" name="info[dedamount_dkpercent]" class="layui-input" value="<?php echo $info['dedamount_dkpercent']; ?>">
									  <div>%</div>
								  </div>
								  <div class="layui-form-mid layui-word-aux layui-clear">
								  	会员下单商家商城商品订单时，在商家设置的让利部分金额基础上进行百分比抵扣；
								  	<br>注意：商家若设置了让利比例，则此商家的商城订单及买单的分销及分红(团队、股东、区域代理)按用户实际使用的抵抗金金额进行发放
								  </div>
							  </div>
							 <?php endif; if(getcustom('commission_gangwei')): ?>
							   <fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;">
								  <legend>岗位提成</legend>
							   </fieldset>
							   <div class="layui-form-item">
									<label class="layui-form-label">紧缩：</label>
									<div class="layui-input-inline">
										<input type="radio" name="info[gangwei_jinsuo_status]" value="1" title="开启" <?php if(!$info['id'] || $info['gangwei_jinsuo_status']==1): ?>checked<?php endif; ?>>
										<input type="radio" name="info[gangwei_jinsuo_status]" value="0" title="关闭" <?php if($info['gangwei_jinsuo_status']==0): ?>checked<?php endif; ?>>
									</div>
								</div>
					            <div class="layui-form-item">
					                <label class="layui-form-label">推N得N：</label>
					                <div class="layui-input-inline">
					                    <input type="radio" name="info[gangwei_tndn_status]" value="1" title="开启" <?php if($info['gangwei_tndn_status']==1): ?>checked<?php endif; ?>>
					                    <input type="radio" name="info[gangwei_tndn_status]" value="0" title="关闭" <?php if(!$info['id'] || $info['gangwei_tndn_status']==0): ?>checked<?php endif; ?>>
					                </div>
					            </div>
					            <div class="layui-form-item">
					                <label class="layui-form-label">发放上级选择：</label>
					                <div class="layui-input-inline" style="width: 600px;">
					                	<input type="radio" name="info[gangwei_give_origin_status]" value="0" title="现上级" <?php if(!$info['id'] || $info['gangwei_give_origin_status']==0): ?>checked<?php endif; ?>>
					                    <input type="radio" name="info[gangwei_give_origin_status]" value="1" title="原上级（购买人的原上级一个团队，现上级一个团队，没有原上级给现上级这个团队）" <?php if($info['gangwei_give_origin_status']==1): ?>checked<?php endif; ?>>
					                    <input type="radio" name="info[gangwei_give_origin_status]" value="2" title="每层都发给原上级（没有原上级发给现上级）" <?php if(!$info['id'] || $info['gangwei_give_origin_status']==2): ?>checked<?php endif; ?>>
					                </div>
					            </div>
							 <?php endif; ?>
  
								</div>
								<div class="layui-tab-item">
									<?php foreach($textset as $k=>$v): ?>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo $k; ?>：</label>
										<div class="layui-input-inline">
											<input type="text" name="textset[<?php echo $k; ?>]" class="layui-input" value="<?php echo $v; ?>">
										</div>
										 <?php if($k=='购买数量'): ?>
										<div class="layui-form-mid layui-word-aux"> 购买数量为预约详情的购买数量</div>
										<?php endif; ?>
									</div>
									<?php endforeach; ?>
								</div>
								<div class="layui-tab-item">
									<?php foreach($platform as $pl): ?>
									<div class="layui-form-item">
										<label class="layui-form-label"><?php echo getplatformname($pl); ?>：</label>
										<div class="layui-input-inline" style="width:<?php if($pl=='h5'): ?>400px<?php else: ?>550px<?php endif; ?>">
											<input type="checkbox" name="info[logintype_<?php echo $pl; ?>][]" title="注册登录" value="1" <?php if(in_array('1',$info['logintype_'.$pl])): ?>checked<?php endif; ?>/>
											<input type="checkbox" name="info[logintype_<?php echo $pl; ?>][]" title="手机号登录" value="2" <?php if(in_array('2',$info['logintype_'.$pl])): ?>checked<?php endif; ?>/>
											<?php if($pl!='h5'): ?><input type="checkbox" name="info[logintype_<?php echo $pl; ?>][]" title="授权登录" value="3" <?php if(in_array('3',$info['logintype_'.$pl])): ?>checked<?php endif; ?>/><?php endif; if(getcustom('googlelogin') && ($pl=='h5' || $pl=='app')): ?>
											<input type="checkbox" name="info[logintype_<?php echo $pl; ?>][]" title="Google登录" value="6" <?php if(in_array('6',$info['logintype_'.$pl])): ?>checked<?php endif; ?>/>
											<?php endif; if(getcustom('sxpay_h5') && $pl=='h5'): ?>
											<input type="checkbox" name="info[logintype_<?php echo $pl; ?>][]" title="支付宝登录(仅支付宝内)" value="7" <?php if(in_array('7',$info['logintype_'.$pl])): ?>checked<?php endif; ?>/>
											<?php endif; ?>
										</div>
										<?php if(getcustom('googlelogin') && $pl=='h5'): ?>
										<div class="layui-form-mid">Google客户端ID</div>
										<div class="layui-input-inline" style="width:200px">
											<input type="text" name="info[google_client_id]" class="layui-input" value="<?php echo $info['google_client_id']; ?>">
										</div>
										<?php endif; ?>
										<div class="layui-form-mid layui-word-aux layui-clear"></div>
									</div>
									<?php endforeach; if(in_array('wx',$platform)): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">设置头像昵称：</label>
										<div class="layui-input-inline" style="width:350px">
											<input type="radio" name="info[login_setnickname]" title="不需要设置" value="0" <?php if($info['login_setnickname']==0): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[login_setnickname]" title="可选设置" value="1" <?php if($info['login_setnickname']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[login_setnickname]" title="强制设置" value="2" <?php if($info['login_setnickname']==2): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux">小程序端首次授权登录时是否提示设置头像昵称</div>
									</div>
									<?php endif; if(getcustom('login_setnickname_checklogin')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">登录内页设置头像昵称：</label>
										<div class="layui-input-inline" style="width:350px">
											<input type="radio" name="info[login_setnickname_checklogin]" title="不需要设置" value="0" <?php if($info['login_setnickname_checklogin']==0): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[login_setnickname_checklogin]" title="设置" value="1" <?php if($info['login_setnickname_checklogin']==1): ?>checked<?php endif; ?>/>
										 
										</div>
										 
									</div>
									<?php endif; ?>
									<div class="layui-form-item">
										<label class="layui-form-label">绑定手机号：</label>
										<div class="layui-input-inline" style="width:350px">
											<input type="radio" name="info[login_bind]" title="不绑定" value="0" <?php if($info['login_bind']==0): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[login_bind]" title="可选绑定" value="1" <?php if($info['login_bind']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[login_bind]" title="强制绑定" value="2" <?php if($info['login_bind']==2): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux">授权登录时是否提示绑定手机号</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">强制登录：</label>
										<div class="layui-input-inline" style="width:500px">
											<?php foreach($platform as $pl): ?>
											<input type="checkbox" name="login_mast[]" title="<?php echo getplatformname($pl); ?>" value="<?php echo $pl; ?>" <?php if(in_array($pl,explode(',',$info['login_mast']))): ?>checked<?php endif; ?> lay-skin="primary"/>
											<?php endforeach; ?>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">开启后进入系统必须先登录，存在被封的可能，请谨慎开启</div>
									</div>
									<?php if(getcustom('reg_invite_code')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">注册邀请码：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="radio" name="info[reg_invite_code]" title="开启" value="1" <?php if($info['reg_invite_code']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[reg_invite_code]" title="关闭" value="0" <?php if($info['reg_invite_code']==0): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[reg_invite_code]" title="强制邀请" value="2" <?php if($info['reg_invite_code']==2): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">通过邀请链接注册时展示邀请人信息，不能输入邀请码，强制邀请时必须邀请才能注册</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">邀请码类型：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="radio" name="info[reg_invite_code_type]" title="手机号" value="0" <?php if($info['reg_invite_code_type']==0): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[reg_invite_code_type]" title="邀请码" value="1" <?php if($info['reg_invite_code_type']==1): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux"></div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">邀请人邀请码：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="radio" name="info[reg_invite_code_show]" title="隐藏" value="0" <?php if($info['reg_invite_code_show']==0): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[reg_invite_code_show]" title="显示" value="1" <?php if($info['reg_invite_code_show']==1): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux">有邀请人时登录、注册页面是否显示邀请码和邀请人</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">注册审核：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="radio" name="info[reg_check]" title="关闭" value="0" <?php if($info['reg_check']==0): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[reg_check]" title="开启" value="1" <?php if($info['reg_check']==1): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux"></div>
									</div>
									<?php endif; if(getcustom('member_auto_reg')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">游客自动注册<?php echo t('会员'); ?>：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="radio" name="info[member_auto_reg]" title="开启" value="1" <?php if($info['member_auto_reg']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[member_auto_reg]" title="关闭" value="0" <?php if($info['member_auto_reg']==0): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">开启后游客自动注册登录，实现游客快速下单</div>
									</div>
									<?php endif; if(getcustom('member_auto_addlogin')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">游客自动注册<?php echo t('会员'); ?>：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="radio" name="info[is_member_auto_addlogin]" title="开启" value="1" <?php if($info['is_member_auto_addlogin']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="info[is_member_auto_addlogin]" title="关闭" value="0" <?php if($info['is_member_auto_addlogin']==0): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">开启后<?php echo t('会员'); ?>自动添加和登录，实现不需要登录系统购买商城商品，支付后填写收货地址，商城使用</div>
									</div>
									<?php endif; if(getcustom('app_reg')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">app注冊跳转连接：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="text" name="info[appurl]" value="<?php echo $info['appurl']; ?>" class="layui-input">
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">app后注册后跳转连接，不填写默认跳转到<?php echo t('会员'); ?>中心</div>
									</div>
									<?php endif; if(getcustom('update_member_pid')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">修改推荐人：</label>
										<div class="layui-form-mid">推荐人ID为</div>
										<div class="layui-input-inline" style="width:100px">
											<input type="text" name="info[pid_origin]" value="<?php echo $info['pid_origin']; ?>" class="layui-input">
										</div>
										<div class="layui-form-mid">的新用户，按顺序分给</div>
										<div class="layui-input-inline" style="width:200px">
											<input type="text" name="info[pid_new]" class="layui-input" value="<?php echo $info['pid_new']; ?>" placeholder="新推荐人ID">
										</div>
										<div class="layui-form-mid">当前分配位置</div>
										<div class="layui-input-inline" style="width:100px">
											<input type="text" name="info[pid_new_pos]" class="layui-input" value="<?php echo $info['pid_new_pos']; ?>" placeholder="">
										</div>
										<div class="layui-form-mid layui-word-aux layui-clear">多个新推荐人ID使用英文“,”间隔，分配位置默认从1开始</div>
									</div>
									<?php endif; ?>
								</div>

								<div class="layui-tab-item">
									<div class="layui-form-item">
										<label class="layui-form-label">用户注册协议：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="radio" name="xyinfo[status]" title="开启" value="1" <?php if($xyinfo['status']==1): ?>checked<?php endif; ?>/>
											<input type="radio" name="xyinfo[status]" title="关闭" value="0" <?php if($xyinfo['status']==0): ?>checked<?php endif; ?>/>
										</div>
										<div class="layui-form-mid layui-popover layui-default-link">
											示例
											<div class="layui-popover-div">
												<img src="/static/admin/img/dianda_xieyi.png"/>
											</div>
										</div>
									</div>
									<?php if(getcustom('xieyi_agree_type')): ?>
									<div class="layui-form-item">
										<label class="layui-form-label">注册协议同意方式：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="radio" name="xyinfo[agree_type]" title="打勾" value="0" <?php if($xyinfo['agree_type']==0): ?>checked<?php endif; ?>/>
											<input type="radio" name="xyinfo[agree_type]" title="阅读到最后" value="1" <?php if($xyinfo['agree_type']==1): ?>checked<?php endif; ?>/>
										</div>
									</div>
									<?php endif; ?>
									<div class="layui-form-item">
										<label class="layui-form-label">注册协议名称：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="text" name="xyinfo[name]" value="<?php echo $xyinfo['name']; ?>" class="layui-input">
										</div>
										<div class="layui-form-mid layui-word-aux"></div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">注册协议内容：</label>
										<div class="layui-input-inline" style="width:500px">
											<script id="xycontent" name="xyinfo[content]" type="text/plain" style="width:100%;height:400px"><?php echo $xyinfo['content']; ?></script>
										</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">隐私政策名称：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="text" name="xyinfo[name2]" value="<?php echo $xyinfo['name2']; ?>" class="layui-input">
										</div>
										<div class="layui-form-mid layui-word-aux"></div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">隐私政策内容：</label>
										<div class="layui-input-inline" style="width:500px">
											<script id="xycontent2" name="xyinfo[content2]" type="text/plain" style="width:100%;height:400px"><?php echo $xyinfo['content2']; ?></script>
										</div>
									</div>
								</div>

								<div class="layui-tab-item">
									<div class="layui-form-item">
										<label class="layui-form-label">附件存储类型：</label>
										<div class="layui-input-inline" style="width:300px">
											<select name="rinfo[type]" lay-filter="changetype">
												<?php if(in_array('0',explode(',',$ainfo['remotearr']))): ?><option value="0" <?php if($rinfo['type']==0): ?>selected<?php endif; ?>>跟随平台附件设置</option><?php endif; if($rinfo['type']==1 || in_array('1',explode(',',$ainfo['remotearr']))): ?><option value="1" <?php if($rinfo['type']==1): ?>selected<?php endif; ?>>本地存储</option><?php endif; if(in_array('2',explode(',',$ainfo['remotearr']))): ?><option value="2" <?php if($rinfo['type']==2): ?>selected<?php endif; ?>>阿里云</option><?php endif; if(in_array('3',explode(',',$ainfo['remotearr']))): ?><option value="3" <?php if($rinfo['type']==3): ?>selected<?php endif; ?>>七牛云</option><?php endif; if(in_array('4',explode(',',$ainfo['remotearr']))): ?><option value="4" <?php if($rinfo['type']==4): ?>selected<?php endif; ?>>腾讯云</option><?php endif; ?>
											</select>
										</div>
									</div>
									<div id="aliossset" <?php if($rinfo['type']!=2): ?>style="display:none"<?php endif; ?>>
										<div class="layui-form-item">
											<label class="layui-form-label">Access Key ID：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="rinfo[alioss][key]" value="<?php echo $rinfo['alioss']['key']; ?>" class="layui-input">
											</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label">Access Key Secret：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="rinfo[alioss][secret]" value="<?php echo $rinfo['alioss']['secret']; ?>" class="layui-input">
											</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label">Bucket名称：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="rinfo[alioss][bucket]" value="<?php echo $rinfo['alioss']['bucket']; ?>" class="layui-input">
											</div>
											<div class="layui-form-mid layui-word-aux">空间名称</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label">EndPoint（地域节点）</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="rinfo[alioss][ossurl]" value="<?php echo $rinfo['alioss']['ossurl']; ?>" class="layui-input">
											</div>
											<div class="layui-form-mid layui-word-aux">如：oss-cn-qingdao.aliyuncs.com</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label">Bucket域名：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="rinfo[alioss][url]" value="<?php echo $rinfo['alioss']['url']; ?>" class="layui-input">
											</div>
											<div class="layui-form-mid layui-word-aux">开头须加https:// ，微信小程序需要在downloadFile合法域名添加配置此url</div>
										</div>
									</div>
									<div id="qiniuset" <?php if($rinfo['type']!=3): ?>style="display:none"<?php endif; ?>>
										<div class="layui-form-item">
											<label class="layui-form-label">Accesskey：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="rinfo[qiniu][accesskey]" value="<?php echo $rinfo['qiniu']['accesskey']; ?>" class="layui-input">
											</div>
											<div class="layui-form-mid layui-word-aux">在密钥管理中查找</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label">Secretkey：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="rinfo[qiniu][secretkey]" value="<?php echo $rinfo['qiniu']['secretkey']; ?>" class="layui-input">
											</div>
											<div class="layui-form-mid layui-word-aux">在密钥管理中查找</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label">Bucket：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="rinfo[qiniu][bucket]" value="<?php echo $rinfo['qiniu']['bucket']; ?>" class="layui-input">
											</div>
											<div class="layui-form-mid layui-word-aux">空间名称</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label">Url：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="rinfo[qiniu][url]" value="<?php echo $rinfo['qiniu']['url']; ?>" class="layui-input">
											</div>
											<div class="layui-form-mid layui-word-aux">开头须加https:// ，微信小程序需要在downloadFile合法域名添加配置此url</div>
										</div>
										  <?php if(getcustom('qiniu_transcode')): ?>
										  <div class="layui-form-item">
											  <label class="layui-form-label">转码：</label>
											  <div class="layui-input-inline" style="width:300px">
												  <input type="radio" name="rinfo[qiniu][transcode]" value="" <?php if($rinfo[qiniu][transcode]==''): ?>checked<?php endif; ?> title="关闭">
												  <input type="radio" name="rinfo[qiniu][transcode]" value="webp" <?php if($rinfo[qiniu][transcode]=='webp'): ?>checked<?php endif; ?> title="webp">
											  </div>
											  <div class="layui-form-mid layui-word-aux"></div>
										  </div>
										  <?php endif; ?>
									</div>
									<div id="cosset" <?php if($rinfo['type']!=4): ?>style="display:none"<?php endif; ?>>
										<div class="layui-form-item">
											<label class="layui-form-label">APPID：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="rinfo[cos][appid]" value="<?php echo $rinfo['cos']['appid']; ?>" class="layui-input">
											</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label">SecretID：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="rinfo[cos][secretid]" value="<?php echo $rinfo['cos']['secretid']; ?>" class="layui-input">
											</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label">SecretKEY：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="rinfo[cos][secretkey]" value="<?php echo $rinfo['cos']['secretkey']; ?>" class="layui-input">
											</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label">Bucket：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="rinfo[cos][bucket]" value="<?php echo $rinfo['cos']['bucket']; ?>" class="layui-input">
											</div>
											<div class="layui-form-mid layui-word-aux">存储桶名称</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label">bucket所属地域：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="rinfo[cos][local]" value="<?php echo $rinfo['cos']['local']; ?>" class="layui-input">
											</div>
											<div class="layui-form-mid layui-word-aux">地域代码，如：ap-beijing</div>
										</div>
										<div class="layui-form-item">
											<label class="layui-form-label">Url：</label>
											<div class="layui-input-inline" style="width:300px">
												<input type="text" name="rinfo[cos][url]" value="<?php echo $rinfo['cos']['url']; ?>" class="layui-input">
											</div>
											<div class="layui-form-mid layui-word-aux">开头须加https:// ，微信小程序需要在downloadFile合法域名添加配置此url</div>
										</div>
									</div>
								  <?php if(getcustom('file_size_limit')): if($remote['file_limit_user'] == 1): ?>
								  <div class="layui-form-item">
									  <label class="layui-form-label">图片上传限制：</label>
									  <div class="layui-input-inline layui-module-itemR">
										  <input type="number" step="1" min="0" max="1000" name="info[file_image_limit]" value="<?php echo $info['file_image_limit']; ?>" class="layui-input">
											<div>MB</div>
									  </div>
									  <div class="layui-form-mid layui-word-aux">单个文件上传大小限制，0为不限制</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">视频上传限制：</label>
									  <div class="layui-input-inline layui-module-itemR">
										  <input type="number" step="1" min="0" max="10000" name="info[file_video_limit]" value="<?php echo $info['file_video_limit']; ?>" class="layui-input">
											<div>MB</div>
									  </div>
									  <div class="layui-form-mid layui-word-aux">单个文件上传大小限制，0为不限制</div>
								  </div>
								  <div class="layui-form-item">
									  <label class="layui-form-label">其他上传限制：</label>
									  <div class="layui-input-inline layui-module-itemR">
										  <input type="number" step="1" min="0" max="10000" name="info[file_other_limit]" value="<?php echo $info['file_other_limit']; ?>" class="layui-input">
											<div>MB</div>
									  </div>
									  <div class="layui-form-mid layui-word-aux">图片和视频外的其他文件，单个文件上传大小限制，0为不限制</div>
								  </div>
  									<?php endif; if($remote['file_upload_limit'] > 0 && in_array($rinfo['type'],[0,1]) && $admin['file_upload_limit'] >= 0): ?>
								  <div class="layui-form-item remotePercent">
									  <label class="layui-form-label">附件空间：</label>
									  <?php if($admin['file_upload_limit'] == 0 || $admin['file_upload_limit'] == ''): ?>
									  <div class="layui-form-mid" style="width:100px;font-weight:bold"><?php echo round($admin['file_upload_total']/1024/1024,2); ?>MB/<?php echo $remote['file_upload_limit']; ?>MB</div>
									  <div style="clear: both; padding-top: 10px;padding-left: 160px; width: 360px;">
										  <div class="layui-progress" lay-showpercent="true">
											  <div class="layui-progress-bar" lay-percent="<?php echo round($admin['file_upload_total']/1024/1024/$remote['file_upload_limit'],4)*100; ?>%"></div>
										  </div>
									  </div>
									  <?php else: ?>
									  <div class="layui-form-mid" style="width:100px;font-weight:bold"><?php echo round($admin['file_upload_total']/1024/1024,2); ?>MB/<?php echo $admin['file_upload_limit']; ?>MB</div>
									  <div style="clear: both; padding-top: 10px;padding-left: 160px; width: 360px;">
										  <div class="layui-progress" lay-showpercent="true">
											  <div class="layui-progress-bar" lay-percent="<?php echo round($admin['file_upload_total']/1024/1024/$admin['file_upload_limit'],4)*100; ?>%"></div>
										  </div>
									  </div>
									  <?php endif; ?>
								  </div>
								  <?php endif; ?>
								  <?php endif; ?>
								</div>

								<?php if(getcustom('jushuitan')): if($admin['jushuitan_status']==1): ?>
								<div class="layui-tab-item">
									<div class="layui-form-item">
										<div style="padding-left:20px;font-weight:bold">一、填写聚水潭信息</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">App Key：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="text" name="info[jushuitankey]" value="<?php echo $info['jushuitankey']; ?>" class="layui-input">
										</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">App Secret：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="text" name="info[jushuitansecret]" value="<?php echo $info['jushuitansecret']; ?>" class="layui-input">
										</div>
									</div>
									<div class="layui-form-item">
										<label class="layui-form-label">店铺编号：</label>
										<div class="layui-input-inline" style="width:300px">
											<input type="text" name="info[shop_id]" value="<?php echo $info['shop_id']; ?>" class="layui-input">
										</div>
										<div class="layui-form-mid layui-word-aux">店铺如何创建，可查看操作 <a href="http://www.erp321.com/app/support/document.html#page=1865" target="_blank">http://www.erp321.com/app/support/document.html#page=1865</a></div>
									</div>
									<div class="layui-form-item">
										<div style="padding-left:20px;font-weight:bold">二、配置回调域名</div>
									</div>
									<div class="layui-form-item" style="margin-bottom:0">
										<div class="layui-form-mid" style="margin-left:40px;">登录聚水潭开放平台(https://open.jushuitan.com/)在[消息管理]-[订阅消息]-[开启订阅]，登录（https://www.erp321.com/epaas） 聚水潭ERP中的[店铺设置]配置回调域名：详细说明:<a href="https://open.jushuitan.com/document/2119.html" target='_blank'>请点击这里</a></div>
									</div>
									<div class="layui-form-item" style="margin-bottom:0">
										<label class="layui-form-label">回调域名：</label>
										<div class="layui-form-mid" style="width:200px;font-weight:bold"><?php echo PRE_URL2; ?>/notify2.php</div>
										<div class="layui-form-mid layui-word-aux"></div>
									</div>
  								</div>
							  <?php endif; ?>
							  <?php endif; ?>
							  <!--loading 图标End-->
							  <div class="layui-tab-item">
								  <!--<div class="layui-form-item">
									  <label class="layui-form-label" style="width:100px">加载样式：</label>
									  <div class="layui-input-inline" style="40%">
										  <input type="radio" name="info[loading_style]" title="样式一" value="0" <?php if(!$info['loading_style'] || $info['loading_style']===0): ?>checked<?php endif; ?> lay-filter='loadingStyle' />
										  <input type="radio" name="info[loading_style]" title="样式二" value="1" <?php if($info['loading_style']==1): ?>checked<?php endif; ?> lay-filter='loadingStyle' />
									  </div>
								  </div>-->
								  <div class="layui-form-item">
									  <label class="layui-form-label" style="width: 100px !important">加载图标：</label>
									  <input type="hidden" name="info[loading_icon]" value="<?php echo $info['loading_icon']; ?>" id="loading_icon">
									  <button style="float:left;" type="button" class="layui-btn layui-btn-primary" onclick="uploaderLoading(this)" >上传图标</button>

									  <div class="ldPicList" style="float:left;padding-top:10px;padding-left:130px;clear: both;">
										  <div class="loading-main-box">
											  <div class="loadingpic-box active">
											  <div class="loading-img" onclick="loadImgPreview(this)">
												  <img id="loading_icon_img" src="<?php echo $info['loading_icon']; ?>?v=<?php echo time(); ?>">
											  </div>
											  <div class="loading-preview">
											  <img id="loading_icon_preview" class="animation<?php echo $info['loading_style']; ?>" height="120" width="120" src="<?php echo $info['loading_icon']; ?>?v=<?php echo time(); ?>">
										  </div>
									  </div>
											  <div class="layui-form-mid layui-word-aux">
												  <span style="color: #cc0000">* </span>可自定义上传，最大尺寸：100×100像素；支持格式：png；
												  <br><span style="color: #cc0000">* </span>修改图标后，需清理前端缓存！</div>
								  </div>
								  </div>
								</div>
								  <div class="layui-form-item">
									  <label class="layui-form-label" style="width: 100px !important;">选择图标：</label>
									 <div class="layui-form-mid">以下加载案例可供选择</div>
									  <div id="ldPicList" class="ldPicList" style="float:left;padding-top:10px;padding-left:130px;clear: both;">
										  <?php foreach($info['loading_pics'] as $ldkey=>$pic): ?>
										  <div class="loading-main-box">
											  <div class="loadingpic-box">
												  <div class="loading-img" onclick="loadImgPreview(this)">
													  <img src="<?php echo $pic; ?>">
												  </div>
												  <div class="loading-preview" style="display:none">
													  <img class="animation0" height="120" width="120" src="<?php echo $pic; ?>">
												  </div>
											  </div>
										  </div>
										  <?php endforeach; ?>
									  </div>
								  </div>
							  </div>
							  <!--loading 图标End-->

							  <!--loading 系统信息s-->
							  <div class="layui-tab-item">
								<div class="layui-form-item" style="margin-top:30px">
									<label class="layui-form-label">系统名称</label>
									<div class="layui-input-inline" style="width:300px">
										<input type="text" name="webinfo[webname]" value="<?php echo $webinfo['webname']; ?>" lay-verType="tips" class="layui-input">
									</div>
								</div>
								<div class="layui-form-item">
									<label class="layui-form-label">LOGO</label>
									<div class="layui-input-inline" style="width:300px">
										<input type="text" name="webinfo[logo]" id="webinfologo" class="layui-input" value="<?php echo $webinfo['logo']; ?>">
									</div>
									<button style="float:left;" type="button" class="layui-btn layui-btn-primary" onclick="uploader(this)" upload-input="webinfologo" upload-preview="logoPreview">上传图片</button>
									<div class="layui-form-mid layui-word-aux" style="margin-left:10px;">建议尺寸：108×108像素</div>
									<div id="logoPreview" style="float:left;padding-top:10px;margin-left:160px;clear: both;">
										<div class="layui-imgbox"><div class="layui-imgbox-img"><img src="<?php echo $webinfo['logo']; ?>"/></div></div>
									</div>
								</div>
								<div class="layui-form-item">
									<label class="layui-form-label">ico图标</label>
									<div class="layui-input-inline" style="width:300px">
										<input type="text" name="webinfo[ico]" id="ico" class="layui-input" value="<?php echo $webinfo['ico']; ?>">
									</div>
									<button style="float:left;" type="button" class="layui-btn layui-btn-primary" onclick="uploader(this)" upload-input="ico" upload-preview="icoPreview">上传图片</button>
									<div class="layui-form-mid layui-word-aux" style="margin-left:10px;"></div>
									<div id="icoPreview" style="float:left;padding-top:10px;margin-left:160px;clear: both;">
										<div class="layui-imgbox" style="width:44px;height:44px"><div class="layui-imgbox-img"><img src="<?php echo $webinfo['ico']; ?>"/></div></div>
									</div>
								</div>
								<div class="layui-form-item">
									<label class="layui-form-label">登录页背景图</label>
									<div class="layui-input-inline" style="width:300px">
										<input type="text" name="webinfo[adminloginbg]" id="adminloginbg" class="layui-input" value="<?php echo $webinfo['adminloginbg']; ?>">
									</div>
									<button style="float:left;" type="button" class="layui-btn layui-btn-primary" onclick="uploader(this)" maxwidth="2500" maxheight="2000" upload-input="adminloginbg" upload-preview="adminloginbgPreview">上传图片</button>
									<div class="layui-form-mid layui-word-aux" style="margin-left:10px;">建议尺寸：1920×1080像素</div>
									<div id="adminloginbgPreview" style="float:left;padding-top:10px;margin-left:160px;clear: both;">
										<div class="layui-imgbox" style="width:144px;height:90px"><div class="layui-imgbox-img"><img src="<?php echo $webinfo['adminloginbg']; ?>"/></div></div>
									</div>
								</div>
								
								<div class="layui-form-item">
									<label class="layui-form-label">版权设置</label>
									<div class="layui-input-inline" style="width:300px">
										<input type="text" name="webinfo[copyright]" class="layui-input" value="<?php echo $webinfo['copyright']; ?>"/>
									</div>
								</div>
								<div class="layui-form-item">
									<label class="layui-form-label">备案号</label>
									<div class="layui-input-inline" style="width:300px">
										<input type="text" name="webinfo[beian]" class="layui-input" value="<?php echo $webinfo['beian']; ?>"/>
									</div>
								</div>
								<div class="layui-form-item">
									<label class="layui-form-label">公安备案文字</label>
									<div class="layui-input-inline" style="width:300px">
										<input type="text" name="webinfo[copyright2]" class="layui-input" value="<?php echo $webinfo['copyright2']; ?>" placeholder="公网安备1001号"/>
									</div>
									<div class="layui-form-mid layui-word-aux" style="margin-left:10px;">官网底部显示</div>
								</div>
								<div class="layui-form-item">
									<label class="layui-form-label">公安备案号</label>
									<div class="layui-input-inline" style="width:300px">
										<input type="text" name="webinfo[beian2]" class="layui-input" value="<?php echo $webinfo['beian2']; ?>" placeholder="1001"/>
									</div>
									<div class="layui-form-mid layui-word-aux" style="margin-left:10px;">点击“公安备案文字”跳转链接，拼接此备案号</div>
								</div>
							  </div>
							  <!--loading 系统信息end-->

							</div>
						</div>

						<div class="layui-form-item">
							<label class="layui-form-label"></label>
							<div class="layui-input-block">
								<button class="layui-btn" lay-submit lay-filter="formsubmit">提 交</button>
							</div>
						</div>
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
	var ueditor = UE.getEditor('xycontent',{imageScaleEnabled:false});
	var ueditor2 = UE.getEditor('xycontent2',{imageScaleEnabled:false});
	function choosezuobiao(){
		var address = $("input[name='info[address]']").val();
		var longitude = $("input[name='info[longitude]']").val();
		var latitude = $("input[name='info[latitude]']").val();
		var choosezblayer = layer.open({type:2,shadeClose: true,area: ['800px', '560px'],'title': '选择坐标',content: "<?php echo url('DesignerPage/choosezuobiao'); ?>&address="+(address?address:"")+"&jd="+(longitude?longitude:"")+"&wd="+(latitude?latitude:""),btn:['确定','取消'],yes:function(index, layero){
			var longitude = layero.find('iframe').contents().find('#mapjd').val();
			var latitude = layero.find('iframe').contents().find('#mapwd').val();
			$("input[name='info[longitude]']").val(longitude);
			$("input[name='info[latitude]']").val(latitude);
			layer.close(choosezblayer);
		}});
	}
	//日期范围选择
	layui.laydate.render({
		elem: '#region_ctime',
		trigger: 'click',
		type:'date',
		range: '~' //或 range: '~' 来自定义分割字符
	});
	layui.form.on('radio(set_yuebao)', function(data){
		if(data.value == '1'){
			$('#yuebaoset').show();
		}else{
			$('#yuebaoset').hide();
		}
	})

	layui.form.on('radio(invoice)', function(data){
		if(data.value == '1'){
			$('#invoice-set').show();
		}else{
			$('#invoice-set').hide();
		}
	})
	layui.form.on('radio(fhjiesuantime_type)', function(data){
		if(data.value == '1'){
			$('#fhjiesuantime_type-set').hide();
		}else{
			$('#fhjiesuantime_type-set').show();
		}
	})
	layui.form.on('radio(fhjiesuantime_type_huiben)', function(data){
		if(data.value == '1'){
			$('#fhjiesuantime_type_huiben-set').hide();
		}else{
			$('#fhjiesuantime_type_huiben-set').show();
		}
	})
	layui.form.on('radio(pay_transfer)', function(data){
		if(data.value == '0'){
			$('#pay_transfer_set').hide();
		}else{
			$('#pay_transfer_set').show();
		}
	})
	layui.form.on('radio(alipayset)', function(data){
		if(data.value == '0'){
			$('#alipayset').hide();
		}else{
			$('#alipayset').show();
		}
	})
	layui.form.on('radio(yuebao_withdraw)', function(data){
		if(data.value == '1'){
			$('#yuebaowithdrawotherset').show();
		}else{
			$('#yuebaowithdrawotherset').hide();
		}
	})
	layui.form.on('radio(rechargeyj_withdraw)', function(data){
		if(data.value == '1'){
			$('#rechargeyjwithdrawotherset').show();
		}else{
			$('#rechargeyjwithdrawotherset').hide();
		}
	})
	layui.form.on('radio(comwithdraw)', function(data){
		if(data.value == '1'){
			$('#comwithdrawotherset').show();
		}else{
			$('#comwithdrawotherset').hide();
		}
	})

	layui.form.on('radio(daifu)', function(data){
		if(data.value == '1'){
			$('#daifu-desc').show();
		}else{
			$('#daifu-desc').hide();
		}
	})

	<?php if(getcustom('commission_to_money_rate')): ?>
	layui.form.on('radio(commission2money)', function(data){
		if(data.value == '1'){
			$('#commission_to_money_rate').show();
		}else{
			$('#commission_to_money_rate').hide();
		}
	})
	<?php endif; if(getcustom('money_dec') || getcustom('cashier_money_dec')): ?>
		layui.form.on('radio(moneydec)', function(data){
			if(data.value == '1'){
				$('#money_dec').show();
			}else{
				$('#money_dec').hide();
			}
		})
	<?php endif; if(getcustom('member_overdraft_money') && getcustom('cashier_overdraft_money_dec')): ?>
		layui.form.on('radio(overdraftmoneydec)', function(data){
			if(data.value == '1'){
				$('#overdraft_money_dec').show();
			}else{
				$('#overdraft_money_dec').hide();
			}
		})
	<?php endif; if(getcustom('fenhong_jiaquan_bylevel')): ?>
		layui.laydate.render({
			elem: '#fenhong_jqjs_time',
			trigger: 'click',
			type:'time'
		});
	<?php endif; ?>

	
	layui.form.on('radio(score_withdraw)', function(data){
		if(data.value == '1'){
			$('#score_withdraw_div').show();
		}else{
			$('#score_withdraw_div').hide();
		}
	})
		<?php if(getcustom('member_score_withdraw')): ?>
		layui.form.on('radio(member_score_withdraw)', function(data){
			if(data.value == '1'){
				$('#member_score_withdraw_div').show();
			}else{
				$('#member_score_withdraw_div').hide();
			}
		})
		<?php endif; if(getcustom('mendian_hexiao_money_to_score')): ?>
		layui.form.on('radio(money_to_score)', function(data){
			if(data.value == '1'){
				$('#mendian_hexiao_money_to_score').show();
			}else{
				$('#mendian_hexiao_money_to_score').hide();
			}
		})
		<?php endif; ?>
		layui.form.on('radio(score_to_money)', function(data){
			if(data.value == '1'){
				$('#score_to_money_div').show();
			}else{
				$('#score_to_money_div').hide();
			}
		})
	layui.form.on('radio(wxkfset)', function(data){
		$('#wxkfcorpid').hide();
		$('#wxkfurl').hide();
		$('.wxkf1set').hide();
		if(data.value == '1'){
			$('.wxkf1set').show();
		}else if(data.value == '2'){
			$('#wxkfcorpid').show();
			$('#wxkfurl').show();
		}else{
			$('#wxkfurl').show();
		}
	})
	layui.form.on('radio(partner_gongxian)', function(data){
		if(data.value == '1'){
			$('#partner_gongxian_div').show();
		}else{
			$('#partner_gongxian_div').hide();
		}
	})

	layui.form.on('radio(ali_withdraw)', function(data){
		if(data.value == '1'){
			$('#aliWithdrawSet').show();
		}else{
			$('#aliWithdrawSet').hide();
		}
	})

	layui.form.on('submit(formsubmit)', function(obj){
		var field = obj.field
		if(field['info[logo]'] == ''){
			dialog('请上传商家LOGO',0);return;
		}
		if(!field['info[withdraw_weixin]'])  field['info[withdraw_weixin]'] = 0
		if(!field['info[withdraw_bankcard]'])  field['info[withdraw_bankcard]'] = 0
		if(!field['info[withdraw_aliaccount]'])  field['info[withdraw_aliaccount]'] = 0
		if(!field['info[scorebdkyf]'])  field['info[scorebdkyf]'] = 0
		if(!field['info[wxkf]'])  field['info[wxkf]'] = 0
		<?php if(getcustom('teamfenhong_pingji') && ($auth_data == 'all' || in_array('teamfenhong_pingji',$auth_data))): ?>
		if(!field['info[teamfenhong_differential_pj]'])  field['info[teamfenhong_differential_pj]'] = 0
		<?php endif; ?>

		field['xyinfo[content]'] = ueditor.getContent();
		field['xyinfo[content2]'] = ueditor2.getContent();

		//哪个选项卡 用于刷新后保持
		var tabindex = 0;
		$('.layui-tab-title>li').each(function(i,v){
			if($(this).hasClass('layui-this')){
				tabindex = i;
				console.log(i)
				return false;
			}
		})
		field.tabindex = tabindex;
		var index = layer.load();
		$.post('',field,function(data){
			layer.close(index);
			dialog(data.msg,data.status,data.url);
		})
	})

	layui.form.on('checkbox(gettjset)', function(data){
		console.log(data)
		if(data.elem.checked){
			$('#gettjset').hide();
		}else{
			$('#gettjset').show();
		}
	})
	layui.form.on('select(changetype)',function(data){
		$('#aliossset').hide()
		$('#qiniuset').hide()
		$('#cosset').hide()
		$('.remotePercent').hide()
		if(data.value==0 || data.value==1){
			$('.remotePercent').show()
		}
		if(data.value==2){
			$('#aliossset').show()
		}
		if(data.value==3){
			$('#qiniuset').show()
		}
		if(data.value==4){
			$('#cosset').show()
		}
	})

		layui.form.on('radio(shopmode)',function (data){
			var shopmode = data.value;
			if(shopmode==2){
				$('#mode_2').show()
			}else{
				$('#mode_2').hide()
			}
			if(shopmode ==1){
		        $('#mode_1').show();
			}else {
				$('#mode_1').hide();
			}
		
		})
		layui.form.on('radio(maidanfenxiao)', function(data){
			if(data.value == '1'){
				$('#maidancost').show();
				$('#maidanfenxiaoset').show();
			}else{
				var maidanfenhong = $('.maidanfenhong:checked').val();
				console.log(maidanfenhong)
				if(maidanfenhong != 1){
					$('#maidancost').hide();
				}
				$('#maidanfenxiaoset').hide();
			}
		})
		layui.form.on('radio(maidan_fenhong)',function (data){
			var val = data.value;
			if(val==1){
				$('#maidancost').show();
				$('.maidan_fenhong').show()
			}else{
				var maidanfenxiao = $('.maidanfenxiao:checked').val()
				console.log(maidanfenxiao)
				if(maidanfenxiao != 1){
					$('#maidancost').hide();
				}
				$('.maidan_fenhong').hide()
			}
		})
		layui.form.on('radio(loc_range_type)',function (data){
			var loc_range_type = data.value;
			console.log(loc_range_type)
			if(loc_range_type==1){
				$('#loc_range_block').show()
			}else{
				$('#loc_range_block').hide()
			}
		})

	
	var chooseUrlField = '';
	function chooseUrl2(field){
		chooseUrlField = field;
		layer.open({type:2,shadeClose:true,area:['1200px', '650px'],'title':'选择链接',content:"<?php echo url('DesignerPage/chooseurl'); ?>&callback=chooseLink2"})
	}
	function chooseLink2(urlname,url){
		$("#"+chooseUrlField).val(url);
	}
		function loadImgPreview(obj){
			// var loading_style = $('input[name="info[loading_style]"]:checked').val();
			var loading_style = 0;
			$('#ldPicList .loading-preview>img').each(function(){
				$(this).removeClass().addClass('animation'+loading_style);
			})
			// $(obj).closest('.loading-main-box').siblings().find('.loadingpic-box').removeClass('active')
			// $(obj).closest('.loading-main-box').find('.loadingpic-box').addClass('active');
			$(obj).closest('.loading-main-box').siblings().find('.loading-preview').hide()
			$(obj).closest('.loading-main-box').find('.loading-preview').show();
			var icon = $(obj).closest('.loading-main-box').find('.loading-preview>img').attr('src');
			$('#loading_icon').val(icon)
			$('#loading_icon_img').attr('src',icon)
			$('#loading_icon_preview').attr('src',icon)
		}
		layui.form.on('radio(loadingStyle)', function(data){
			$('#ldPicList .loading-preview>img').each(function(){
				$(this).removeClass().addClass('animation'+data.value);
			})
		})
		layui.form.on('radio(is_fugou_commission)', function(data){
			if(data.value==1){
				$('#fugouBox').show()
			}else{
				$('#fugouBox').hide()
			}
		})
		function uploaderLoading(obj,tabs){
			var multi = false
			var loading_style = 0;
			if(!tabs){
				tabs = {'browser':'active','selecticon':''};
			}
			var maxwidth = 100;
			if($(obj).attr('maxwidth')){
				maxwidth = $(obj).attr('maxwidth')
			}
			var maxheight = 100;
			if($(obj).attr('maxheight')){
				maxheight = $(obj).attr('maxheight')
			}
			fileUploader.show(function(imgs,ret) {
				if (imgs.length == 0) {
					return;
				}else{
					$('#loading_icon').val(imgs[0].url)
					$('#loading_icon_img').attr('src',imgs[0].url)
					$('#loading_icon_preview').attr('src',imgs[0].url)
				}
			},{type: 'image',direct: false,maxheight:maxheight,maxwidth:maxwidth,multi: true,tabs:tabs,other_param:''});
		}

		<?php if(getcustom('commission_butie')): ?>
		layui.form.on('radio(fx_butie_type)', function(data){
			if(data.value == '1'){
				$('.fx-butie-send-day').hide();
				$('.fx-butie-send-week').show();
			}else{
				$('.fx-butie-send-day').show();
				$('.fx-butie-send-week').hide();
			}
		})
		<?php endif; if(getcustom('member_commission_max') && getcustom('member_commission_max_toscore')): ?>
		layui.form.on('radio(commission_max)', function(data){
			if(data.value == '1'){
				$('#commission_max_toscore').show();
			}else{
				$('#commission_max_toscore').hide();
			}
		})
			layui.form.on('radio(commission_max_toscore_st)', function(data){
				if(data.value == '1'){
					$('#commission_max_toscore_ratio').show();
				}else{
					$('#commission_max_toscore_ratio').hide();
				}
			})	
		<?php endif; if(getcustom('sysset_scoredkmaxpercent_memberset')): ?>
			layui.form.on('switch(scoredkmaxpercent_memberset)', function(data){
				var checked = data.elem.checked;
				if(checked){
					$('#scoredkmaxpercentMemberset').show();
				}else{
					$('#scoredkmaxpercentMemberset').hide();
				}
			})	
		<?php endif; ?>

		layui.form.on('radio(commission_withdrawfee_fenxiao)', function(data){
			if(data.value=='0' || data.value=='-1'){
				$('#commission_withdrawfee_commissiondata').hide();
			}else if(data.value=='1'){
				$('#commission_withdrawfee_commissiondata').show();
			}
		})
		layui.form.on('radio(money_withdrawfee_fenxiao)', function(data){
			if(data.value=='0' || data.value=='-1'){
				$('#money_withdrawfee_commissiondata').hide();
			}else if(data.value=='1'){
				$('#money_withdrawfee_commissiondata').show();
			}
		})
		layui.form.on('radio(recharge)', function(data){
			if(data.value=='1'){
				$('#rechargeminimumset').show();
			}else{
				$('#rechargeminimumset').hide();
			}
		})

		layui.form.on('radio(moneypay)', function(data){
			if(data.value=='1'){
				$('#moneypay_priceset').show();
			}else{
				$('#moneypay_priceset').hide();
			}
		})

        <?php if(getcustom('withdraw_custom')): ?>
		$(".withdraw-custom").click(function(e){
            e.stopPropagation();//阻止事件冒泡到父元素，防止触发非目标元素的点击事件
            if (!$(e.target).is('input')) {
                var $hiddenInput =$(this).find('input[type="hidden"][name="info[custom_status]"]');

                $hiddenInput.val($hiddenInput.val() === '1' ? '0' : '1');

                $(this).toggleClass('withdraw-custom-checked');
            }
        });
        <?php endif; ?>
			layui.form.on('radio(gdfhjiesuantime_type)', function(data){
				if(data.value == '1'){
					$('#gdfhjiesuantime_type-set').hide();
				}else{
					$('#gdfhjiesuantime_type-set').show();
				}
			})
	</script>

	
</body>
</html>