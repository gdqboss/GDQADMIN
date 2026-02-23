<?php /*a:5:{s:47:"/www/wwwroot/gdqshop.cn/app/view/user/edit.html";i:1747926690;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:54:"/www/wwwroot/gdqshop.cn/app/view/public/user_auth.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;s:54:"/www/wwwroot/gdqshop.cn/app/view/public/copyright.html";i:1747926690;}*/ ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>账号编辑</title>
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
	<script src="/static/admin/js/address3.js"></script>
	<style>
		.layui-input-block{margin-left:160px!important}
		</style>
</head>
<body>
  <div class="layui-fluid">
    <div class="layui-row layui-col-space15">
      <div class="layui-card layui-col-md12">
			 <div class="layui-card-header">
				<?php if(!$info['id']): ?><i class="fa fa-plus"></i> 新增账号<?php else: ?><i class="fa fa-pencil"></i> 编辑账号<?php endif; ?>
			 <i class="layui-icon layui-icon-close" style="font-size:18px;font-weight:bold;cursor:pointer" onclick="closeself()"></i></div>
				<div class="layui-card-body" pad15>
					<div class="layui-form form-label-w6" lay-filter="">
						<input type="hidden" name="info[id]" value="<?php echo $info['id']; ?>"/>
						<!-- <div class="layui-form-item">
							<label class="layui-form-label">头像</label>
							<input type="hidden" name="info[headimg]" id="headimg" class="layui-input" value="<?php echo $info['headimg']; ?>">
							<button style="float:left;" type="button" class="layui-btn layui-btn-primary" upload-input="headimg" upload-preview="headimgPreview" onclick="uploader(this)">上传图片</button>
							<div class="layui-form-mid layui-word-aux" style="margin-left:10px;">建议尺寸：150×150像素</div>
							<div id="headimgPreview" style="float:left;padding-top:10px;margin-left:110px;clear: both;">
								<div class="layui-imgbox" style="width:100px;"><div class="layui-imgbox-img"><img src="<?php echo $info['headimg']; ?>"/></div></div>
							</div>
						</div> -->
						<div class="layui-form-item">
							<label class="layui-form-label">登录账号：</label>
							<div class="layui-input-inline">
								<input type="text" name="info[un]" lay-verify="required" lay-verType="tips" class="layui-input" value="<?php echo $info['un']; ?>">
							</div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label">登录密码：</label>
							<div class="layui-input-inline">
								<input type="text" name="info[pwd]" class="layui-input" autocomplete="off" value="" <?php if($info['id']): ?> placeholder="留空表示不修改密码"<?php else: ?> lay-verify="required" lay-verType="tips"<?php endif; ?>>
							</div>
						</div>
						<div class="layui-form-item" style="<?php if($bid!=0): ?>display:none<?php endif; ?>">
							<label class="layui-form-label"><?php echo t('会员'); ?>ID：</label>
							<div class="layui-input-inline">
								<input type="text" name="info[mid]" class="layui-input" value="<?php echo $info['mid']; ?>">
							</div>
							<div class="layui-form-mid layui-word-aux">用于绑定会员接收消息通知<?php if($bid==0): ?><a href="javascript:vodi(0)" onclick="openmax('<?php echo url('Member/index'); ?>/isopen/1')">查找<?php echo t('会员'); ?>ID</a><?php endif; ?></div>
						</div>
						
						
						<div class="layui-form-item">
							<label class="layui-form-label">备注：</label>
							<div class="layui-input-inline">
								<input type="text" name="info[remark]" class="layui-input" value="<?php echo $info['remark']; ?>">
							</div>
						</div>
						
						<?php if($thisuser_isadmin!=0 && $info['id'] && $info['id']!=$thisuserid): ?>
						<div class="layui-form-item">
							<label class="layui-form-label">创建人：</label>
							<div class="layui-input-inline">
								<select name="info[addid]">
									<?php foreach($userlist as $u): ?>
									<option value="<?php echo $u['id']; ?>" <?php if($u['id'] == $info['addid']): ?>selected<?php endif; ?>><?php echo $u['un']; ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<?php endif; if((!$info['id'] || $info['isadmin']==0 || $info['isadmin']==3) && $info['id']!=$thisuserid): ?>
						<div class="layui-form-item">
							<label class="layui-form-label">权限组：</label>
							<div class="layui-input-inline">
								<select name="info[groupid]" lay-filter="groupidChange">
									<?php foreach($groupList as $group): ?>
									<option value="<?php echo $group['id']; ?>" <?php if($info['groupid']==$group['id']): ?>selected<?php endif; ?>><?php echo $group['name']; ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						
						<div id="quanxianset_div" style="<?php if($info['groupid']!=0): ?>display:none<?php endif; ?>">
							<?php if($thisuser_mdid == 0): ?>
							<div class="layui-form-item">
								<label class="layui-form-label">门店：</label>
								<div class="layui-input-inline" style="width:800px">
									<input type="radio" name="info[mdid]" value="0" <?php if(!$info['id'] || $info['mdid']==0): ?>checked<?php endif; ?> title="全部">
									<?php foreach($mendianlist as $md): ?><input type="radio" name="info[mdid]" value="<?php echo $md['id']; ?>" <?php if($md['id']==$info['mdid']): ?>checked<?php endif; ?> title="<?php echo $md['name']; ?>"><?php endforeach; ?>
								</div>
								<div class="layui-word-aux layui-clear">指定门店后，该管理员的订单权限只能看到指定门店的订单</div>
							</div>
							<?php elseif(!$info['id']): ?>
							<input type="hidden" value="info[mdid]" value="<?php echo $thisuser_mdid; ?>"/>
							<?php endif; if((!getcustom('user_bquanxian') || $bid != 0) && !$info['id']): ?>
							<input type="hidden" value="bids[]" value="0"/>
							<?php endif; if($thisuser_showtj == 1): ?>
							<div class="layui-form-item">
								<label class="layui-form-label">首页数据统计：</label>
								<div class="layui-input-inline">
									<input type="radio" name="info[showtj]" value="1" <?php if($info['showtj']==1): ?>checked<?php endif; ?> title="显示"/>
									<input type="radio" name="info[showtj]" value="0" <?php if($info['showtj']==0): ?>checked<?php endif; ?> title="不显示"/>
								</div>
							</div>
							<?php elseif(!$info['id']): ?>
							<input type="hidden" value="info[showtj]" value="0"/>
							<?php endif; if(getcustom('kecheng_lecturer')): if($haslecturer): ?>
								<div class="layui-form-item">
									<label class="layui-form-label">课程讲师：</label>
									<div class="layui-input-inline">
										<select name="info[lecturerid]">
											<option <?php if(!$info['id'] || $info['lecturerid']==0): ?>selected<?php endif; ?> value="0" >请选择</option>
											<?php foreach($lecturers as $lecturer): ?>
											<option <?php if($info['lecturerid']==$lecturer['id']): ?>selected<?php endif; ?> value="<?php echo $lecturer['id']; ?>" ><?php echo $lecturer['nickname']; ?> <?php echo $lecturer['tel']; ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								</div>
								<?php endif; ?>
							<?php endif; ?>

							<!-- 需同步修改 web_user\edit.html -->
<div class="layui-form-item">
    <label class="layui-form-label">权限设置：</label>
    <div class="layui-input-block">
        <div style="margin-top:10px;color:#303030; font-size:14px; font-weight:600; ">
            <input type="checkbox" title="全部选择" lay-skin="primary" lay-filter="checkall_all"/>
        </div>
        <?php $i=0; foreach($menudata as $k=>$v): $i++; ?>
        <div>
            <div style="clear:left;margin-top:10px;color:#303030; font-size:14px; font-weight:600; ">
                <input type="checkbox" title="<?php echo $v['name']; ?>" lay-skin="primary" lay-filter="checkall"/>
            </div>
            <div style="margin-left:20px">
                <?php foreach($v['child'] as $k1=>$v1): 
                if(!$v1['authdata'] && $v1['child']){
                $path = array();
                echo '<div style="width: 100px;float:left;margin-top:8px;clear:left">'.$v1['name'].'</div>';
                foreach($v1['child'] as $v2){
                echo '<div style="min-width: 120px;float: left;">';
                echo '	<input type="checkbox" value="'.$v2['path'].','.str_replace('/*','^_^',$v2['authdata']).'" name="auth_data[]" '.(in_array($v2['path'].','.$v2['authdata'],$auth_data)?'checked':'').' title="'.$v2['name'].'" lay-skin="primary"/>';
                echo '</div>';
                }
                echo '<div style="clear:both;float: left;"></div>';
                }
                 if(!$v1['child'] || $v1['authdata']): ?>
                <div style="min-width: 120px;float: left;">
                    <input type="checkbox" value="<?php echo $v1['path']; ?>,<?php echo str_replace('/*','^_^',$v1['authdata']); ?>" name="auth_data[]" <?php if(in_array($v1['path'].','.$v1['authdata'],$auth_data)): ?>checked<?php endif; ?> title="<?php echo $v1['name']; ?>" lay-skin="primary"/>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div style="clear:both;float: left;margin-bottom:10px"></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<!-- 需同步修改 web_user\edit.html --><!-- 需同步修改 web_user\edit.html --><!-- 需同步修改 web_user\edit.html --><!-- 需同步修改 web_user\edit.html --><!-- 需同步修改 web_user\edit.html -->
<div class="layui-form-item">
    <label class="layui-form-label">手机端权限：</label>
    <div class="layui-input-block">
        <div style="margin-top:10px;color:#303030; font-size:14px; font-weight:600; ">
            <input type="checkbox" title="全部选择" lay-skin="primary" lay-filter="checkall_all"/>
        </div>
        <?php if($thisuser_wxauth): ?>
        <div>
            <div style="margin-top:10px;color:#303030; font-size:14px; font-weight:600; ">
                <input type="checkbox" title="查看权限" lay-skin="primary" lay-filter="checkall"/>
            </div>
            <div style="margin-left:20px">
                <?php if($bid==0 && ($thisuser['auth_type'] == 1 || in_array('member',$thisuser_wxauth))): ?>
                <div style="min-width: 120px;float: left;">
                    <input type="checkbox" value="member" name="wxauth_data[]" <?php if(in_array('member',$wxauth_data)): ?>checked<?php endif; ?> title="<?php echo t('会员'); ?>" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('product',$thisuser_wxauth)): ?>
                <div style="min-width: 120px;float: left;">
                    <input type="checkbox" value="product" name="wxauth_data[]" <?php if(in_array('product',$wxauth_data)): ?>checked<?php endif; ?> title="商品" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('order',$thisuser_wxauth)): ?>
                <div style="min-width: 120px;float: left;">
                    <input type="checkbox" value="order" name="wxauth_data[]" <?php if(in_array('order',$wxauth_data)): ?>checked<?php endif; ?> title="订单" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('finance',$thisuser_wxauth)): ?>
                <div style="min-width: 120px;float: left;">
                    <input type="checkbox" value="finance" name="wxauth_data[]" <?php if(in_array('finance',$wxauth_data)): ?>checked<?php endif; ?> title="财务" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('zixun',$thisuser_wxauth)): ?>
                <div style="min-width: 120px;float: left;">
                    <input type="checkbox" value="zixun" name="wxauth_data[]" <?php if(in_array('zixun',$wxauth_data)): ?>checked<?php endif; ?> title="咨询" lay-skin="primary"/>
                </div>
                <?php endif; if(getcustom('expend') && ($thisuser['auth_type'] == 1 || in_array('expend',$thisuser_wxauth))): ?>
                <div style="min-width: 120px;float: left;">
                    <input type="checkbox" value="expend" name="wxauth_data[]" <?php if(in_array('expend',$wxauth_data)): ?>checked<?php endif; ?> title="财务-支出" lay-skin="primary"/>
                </div>
                <?php endif; if(getcustom('mobile_admin_qrcode_variable_maidan')): if($thisuser['auth_type'] == 1 || in_array('qrcode_variable_maidan',$thisuser_wxauth)): ?>
                    <div style="min-width: 120px;float: left;">
                        <input type="checkbox" value="qrcode_variable_maidan" name="wxauth_data[]" <?php if(in_array('qrcode_variable_maidan',$wxauth_data)): ?>checked<?php endif; ?> title="首页-绑定收款码" lay-skin="primary"/>
                    </div>
                    <?php endif; ?>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('qrcode_shop',$thisuser_wxauth)): ?>
                <div style="min-width: 120px;float: left;">
                    <input type="checkbox" value="qrcode_shop" name="wxauth_data[]" <?php if(in_array('qrcode_shop',$wxauth_data)): ?>checked<?php endif; ?> title="首页-店铺二维码" lay-skin="primary"/>
                </div>
                <?php endif; ?>
                <div style="clear:both;float: left;margin-bottom:10px"></div>
            </div>
        </div>
        <?php endif; ?>
        <!-- 需同步修改 web_user\edit.html -->
        <?php if($thisuser_notice_auth): ?>
        <div>
            <div style="clear:left;margin-top:10px;color:#303030; font-size:14px; font-weight:600; ">
                <input type="checkbox" title="接收通知权限" lay-skin="primary" lay-filter="checkall"/>
            </div>
            <div style="margin-left:20px">
                <?php if($thisuser['auth_type'] == 1 || in_array('tmpl_orderconfirm',$thisuser_notice_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="订单提交通知" name="notice_auth_data[]" <?php if(in_array('tmpl_orderconfirm',$notice_auth_data)): ?>checked<?php endif; ?> value="tmpl_orderconfirm" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('tmpl_orderpay',$thisuser_notice_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="订单支付通知" name="notice_auth_data[]" <?php if(in_array('tmpl_orderpay',$notice_auth_data)): ?>checked<?php endif; ?> value="tmpl_orderpay" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('tmpl_ordershouhuo',$thisuser_notice_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="订单收货通知" name="notice_auth_data[]" <?php if(in_array('tmpl_ordershouhuo',$notice_auth_data)): ?>checked<?php endif; ?> value="tmpl_ordershouhuo" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('tmpl_ordertui',$thisuser_notice_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="退款申请通知" name="notice_auth_data[]" <?php if(in_array('tmpl_ordertui',$notice_auth_data)): ?>checked<?php endif; ?> value="tmpl_ordertui" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('tmpl_withdraw',$thisuser_notice_auth)): if($bid==0): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="提现申请通知" name="notice_auth_data[]" <?php if(in_array('tmpl_withdraw',$notice_auth_data)): ?>checked<?php endif; ?> value="tmpl_withdraw" lay-skin="primary"/>
                </div>
                <?php endif; ?>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('tmpl_uplv',$thisuser_notice_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="升级申请通知" name="notice_auth_data[]" <?php if(in_array('tmpl_uplv',$notice_auth_data)): ?>checked<?php endif; ?> value="tmpl_uplv" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('tmpl_formsub',$thisuser_notice_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="表单提交通知" name="notice_auth_data[]" <?php if(in_array('tmpl_formsub',$notice_auth_data)): ?>checked<?php endif; ?> value="tmpl_formsub" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('tmpl_kehuzixun',$thisuser_notice_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="用户咨询通知" name="notice_auth_data[]" <?php if(in_array('tmpl_kehuzixun',$notice_auth_data)): ?>checked<?php endif; ?> value="tmpl_kehuzixun" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('tmpl_maidanpay',$thisuser_notice_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="买单付款通知" name="notice_auth_data[]" <?php if(in_array('tmpl_maidanpay',$notice_auth_data)): ?>checked<?php endif; ?> value="tmpl_maidanpay" lay-skin="primary"/>
                </div>
                <?php endif; if(getcustom('shop_stock_warning_notice')): if($thisuser['auth_type'] == 1 || in_array('tmpl_stockwarning',$thisuser_notice_auth)): ?>
				  <div style="width: 120px;float: left;">
					  <input type="checkbox" title="库存不足通知" name="notice_auth_data[]" <?php if(in_array('tmpl_stockwarning',$notice_auth_data)): ?>checked<?php endif; ?> value="tmpl_stockwarning" lay-skin="primary"/>
				  </div>
				  <?php endif; ?>
				  <?php endif; if(getcustom('product_pickup_device')): if($thisuser['auth_type'] == 1 || in_array('tmpl_device_addstock_remind',$thisuser_notice_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="商品柜设备缺货通知" name="notice_auth_data[]" <?php if(in_array('tmpl_device_addstock_remind',$notice_auth_data)): ?>checked<?php endif; ?> value="tmpl_device_addstock_remind" lay-skin="primary"/>
                </div>
                <?php endif; ?>
                <?php endif; if(getcustom('hotel')): if($thisuser['auth_type'] == 1 || in_array('tmpl_hotelbooking_success',$thisuser_notice_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="客户预定成功通知" name="notice_auth_data[]" <?php if(in_array('tmpl_hotelbooking_success',$notice_auth_data)): ?>checked<?php endif; ?> value="tmpl_hotelbooking_success" lay-skin="primary"/>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <div style="clear:both;float: left;margin-bottom:10px"></div>
            </div>
        </div>
        <?php endif; if($thisuser_hexiao_auth): ?>
        <div>
            <div style="clear:left;margin-top:10px;color:#303030; font-size:14px; font-weight:600; ">
                <input type="checkbox" title="核销权限" lay-skin="primary" lay-filter="checkall"/>
            </div>
            <div style="margin-left:20px">
                <?php if(getcustom('member_code') && getcustom('business_update_member_score')): if($thisuser['auth_type'] == 1 || in_array('member_code_buy',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="会员消费" name="hexiao_auth_data[]" <?php if(in_array('member_code_buy',$hexiao_auth_data)): ?>checked<?php endif; ?> value="member_code_buy" lay-skin="primary"/>
                </div>
                <?php endif; ?>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('shop',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="商城订单" name="hexiao_auth_data[]" <?php if(in_array('shop',$hexiao_auth_data)): ?>checked<?php endif; ?> value="shop" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('collage',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="拼团订单" name="hexiao_auth_data[]" <?php if(in_array('collage',$hexiao_auth_data)): ?>checked<?php endif; ?> value="collage" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('lucky_collage',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="幸运拼团订单" name="hexiao_auth_data[]" <?php if(in_array('lucky_collage',$hexiao_auth_data)): ?>checked<?php endif; ?> value="lucky_collage" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('cycle',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="周期购" name="hexiao_auth_data[]" <?php if(in_array('cycle',$hexiao_auth_data)): ?>checked<?php endif; ?> value="cycle" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('kanjia',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="砍价订单" name="hexiao_auth_data[]" <?php if(in_array('kanjia',$hexiao_auth_data)): ?>checked<?php endif; ?> value="kanjia" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('seckill',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="秒杀订单" name="hexiao_auth_data[]" <?php if(in_array('seckill',$hexiao_auth_data)): ?>checked<?php endif; ?> value="seckill" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('yuyue',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="预约订单" name="hexiao_auth_data[]" <?php if(in_array('yuyue',$hexiao_auth_data)): ?>checked<?php endif; ?> value="yuyue" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('scoreshop',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="<?php echo t('积分'); ?>兑换" name="hexiao_auth_data[]" <?php if(in_array('scoreshop',$hexiao_auth_data)): ?>checked<?php endif; ?> value="scoreshop" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('coupon',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="<?php echo t('优惠券'); ?>" name="hexiao_auth_data[]" <?php if(in_array('coupon',$hexiao_auth_data)): ?>checked<?php endif; ?> value="coupon" lay-skin="primary"/>
                </div>
                <?php endif; if(getcustom('hotel')): if($thisuser['auth_type'] == 1 || in_array('hotel',$thisuser_hexiao_auth)): ?>
					<div style="width: 120px;float: left;">
						<input type="checkbox" title="<?php echo $text['酒店']; ?>订单" name="hexiao_auth_data[]" <?php if(in_array('hotel',$hexiao_auth_data)): ?>checked<?php endif; ?> value="hotel" lay-skin="primary"/>
					</div>
					<?php endif; ?>
				<?php endif; if(getcustom('form_hxqrcode')): if($thisuser['auth_type'] == 1 || in_array('form',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="表单" name="hexiao_auth_data[]" <?php if(in_array('form',$hexiao_auth_data)): ?>checked<?php endif; ?> value="form" lay-skin="primary"/>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <!-- 过滤掉商户权限选择 -->
                <?php 
                $request = request();
                $controller = $request->controller();
                 if($controller != 'Business'): if($thisuser['auth_type'] == 1 || in_array('choujiang',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="抽奖活动" name="hexiao_auth_data[]" <?php if(in_array('choujiang',$hexiao_auth_data)): ?>checked<?php endif; ?> value="choujiang" lay-skin="primary"/>
                </div>
                <?php endif; ?>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('restaurant_shop',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="点餐订单" name="hexiao_auth_data[]" <?php if(in_array('restaurant_shop',$hexiao_auth_data)): ?>checked<?php endif; ?> value="restaurant_shop" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('restaurant_takeaway',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="外卖订单" name="hexiao_auth_data[]" <?php if(in_array('restaurant_takeaway',$hexiao_auth_data)): ?>checked<?php endif; ?> value="restaurant_takeaway" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('tuangou',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="团购订单" name="hexiao_auth_data[]" <?php if(in_array('tuangou',$hexiao_auth_data)): ?>checked<?php endif; ?> value="tuangou" lay-skin="primary"/>
                </div>
                <?php endif; if(getcustom('freight_selecthxbids')): if($thisuser['auth_type'] == 1 || in_array('shopproduct',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="计次商品" name="hexiao_auth_data[]" <?php if(in_array('shopproduct',$hexiao_auth_data)): ?>checked<?php endif; ?> value="shopproduct" lay-skin="primary"/>
                </div>
                <?php endif; ?>
                <?php endif; if(getcustom('goods_hexiao')): if($thisuser['auth_type'] == 1 || in_array('takeaway_order_product',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="外卖订单商品" name="hexiao_auth_data[]" <?php if(in_array('takeaway_order_product',$hexiao_auth_data)): ?>checked<?php endif; ?> value="takeaway_order_product" lay-skin="primary"/>
                </div>
                <?php endif; ?>
                <?php endif; if(getcustom('yx_hbtk')): if($thisuser['auth_type'] == 1 || in_array('hbtk',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="拓客活动" name="hexiao_auth_data[]" <?php if(in_array('hbtk',$hexiao_auth_data)): ?>checked<?php endif; ?> value="hbtk" lay-skin="primary"/>
                </div>
                <?php endif; ?>
                <?php endif; if(getcustom('huodong_baoming')): if($thisuser['auth_type'] == 1 || in_array('hbtk',$thisuser_hexiao_auth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="活动报名" name="hexiao_auth_data[]" <?php if(in_array('huodong_baoming',$hexiao_auth_data)): ?>checked<?php endif; ?> value="huodong_baoming" lay-skin="primary"/>
                </div>
                <?php endif; ?>
                <?php endif; if(getcustom('extend_gift_bag')): if($thisuser['auth_type'] == 1 || in_array('gift_bag',$thisuser_hexiao_auth)): ?>
                    <div style="width: 120px;float: left;">
                        <input type="checkbox" title="礼包订单" name="hexiao_auth_data[]" <?php if(in_array('gift_bag',$hexiao_auth_data)): ?>checked<?php endif; ?> value="gift_bag" lay-skin="primary"/>
                    </div>
                    <?php endif; if($thisuser['auth_type'] == 1 || in_array('gift_bag_goods',$thisuser_hexiao_auth)): ?>
                    <div style="width: 120px;float: left;">
                        <input type="checkbox" title="礼包订单项目" name="hexiao_auth_data[]" <?php if(in_array('gift_bag_goods',$hexiao_auth_data)): ?>checked<?php endif; ?> value="gift_bag_goods" lay-skin="primary"/>
                    </div>
                    <?php endif; ?>
                    
                <?php endif; if(getcustom('restaurant_take_food')): if($thisuser['auth_type'] == 1 || in_array('outfood',$thisuser_hexiao_auth)): ?>
					<div style="width: 120px;float: left;">
						<input type="checkbox" title="出餐" name="hexiao_auth_data[]" <?php if(in_array('outfood',$thisuser_hexiao_auth)): ?>checked<?php endif; ?> value="outfood" lay-skin="primary"/>
					</div>
					<?php endif; ?>
				<?php endif; ?>

				
                <div style="clear:both;float: left;margin-bottom:10px"></div>
            </div>
        </div>
        <?php endif; if($restaurant_auth): ?>
        <div>
            <div style="clear:left;margin-top:10px;color:#303030; font-size:14px; font-weight:600; ">
                <input type="checkbox" title="餐饮权限" lay-skin="primary" lay-filter="checkall"/>
            </div>
            <div style="margin-left:20px">
                <?php if($thisuser['auth_type'] == 1 || in_array('restaurant_product',$thisuser_wxauth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="菜品管理" name="wxauth_data[]" <?php if(in_array('restaurant_product',$wxauth_data)): ?>checked<?php endif; ?> value="restaurant_product" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('restaurant_table',$thisuser_wxauth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="餐桌设置" name="wxauth_data[]" <?php if(in_array('restaurant_table',$wxauth_data)): ?>checked<?php endif; ?> value="restaurant_table" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('restaurant_tableWaiter',$thisuser_wxauth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="餐桌管理" name="wxauth_data[]" <?php if(in_array('restaurant_tableWaiter',$wxauth_data)): ?>checked<?php endif; ?> value="restaurant_tableWaiter" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('restaurant_shop',$thisuser_wxauth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="点餐订单" name="wxauth_data[]" <?php if(in_array('restaurant_shop',$wxauth_data)): ?>checked<?php endif; ?> value="restaurant_shop" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('restaurant_takeaway',$thisuser_wxauth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="外卖订单" name="wxauth_data[]" <?php if(in_array('restaurant_takeaway',$wxauth_data)): ?>checked<?php endif; ?> value="restaurant_takeaway" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('restaurant_booking',$thisuser_wxauth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="预定" name="wxauth_data[]" <?php if(in_array('restaurant_booking',$wxauth_data)): ?>checked<?php endif; ?> value="restaurant_booking" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('restaurant_deposit',$thisuser_wxauth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="寄存" name="wxauth_data[]" <?php if(in_array('restaurant_deposit',$wxauth_data)): ?>checked<?php endif; ?> value="restaurant_deposit" lay-skin="primary"/>
                </div>
                <?php endif; if($thisuser['auth_type'] == 1 || in_array('restaurant_queue',$thisuser_wxauth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="排队" name="wxauth_data[]" <?php if(in_array('restaurant_queue',$wxauth_data)): ?>checked<?php endif; ?> value="restaurant_queue" lay-skin="primary"/>
                </div>
                <?php endif; ?>
                <div style="clear:both;float: left;margin-bottom:10px"></div>
            </div>
        </div>
        <?php endif; if(getcustom('hotel')): ?>
        <div>
            <div style="clear:left;margin-top:10px;color:#303030; font-size:14px; font-weight:600; ">
                <input type="checkbox" title="<?php echo $text['酒店']; ?>权限" lay-skin="primary" lay-filter="checkall"/>
            </div>
            <div style="margin-left:20px">
                <?php if($thisuser['auth_type'] == 1 || in_array('hotel_order',$thisuser_wxauth)): ?>
                <div style="width: 120px;float: left;">
                    <input type="checkbox" title="<?php echo $text['酒店']; ?>订单" name="wxauth_data[]" <?php if(in_array('hotel_order',$wxauth_data)): ?>checked<?php endif; ?> value="hotel_order" lay-skin="primary"/>
                </div>
                <?php endif; ?>
       
                <div style="clear:both;float: left;margin-bottom:10px"></div>
            </div>
        </div>
        <?php endif; if(getcustom('mobile_admin_user_mendian_money')): if($thisuser['auth_type'] == 1 || (in_array('mendian_mdmoneylog',$thisuser_wxauth) || in_array('mendian_mdwithdraw',$thisuser_wxauth) || in_array('mendian_mdwithdrawlog',$thisuser_wxauth))): ?>
            <div>
                <div style="clear:left;margin-top:10px;color:#303030; font-size:14px; font-weight:600; ">
                    <input type="checkbox" title="首页<?php echo t('门店'); ?>余额权限" lay-skin="primary" lay-filter="checkall"/>
                </div>
                <div style="margin-left:20px">
                    <?php if($thisuser['auth_type'] == 1 || in_array('mendian_mdmoneylog',$thisuser_wxauth)): ?>
                    <div style="width: 120px;float: left;">
                        <input type="checkbox" title="余额明细" name="wxauth_data[]" <?php if(in_array('mendian_mdmoneylog',$wxauth_data)): ?>checked<?php endif; ?> value="mendian_mdmoneylog" lay-skin="primary"/>
                    </div>
                    <?php endif; if($thisuser['auth_type'] == 1 || in_array('mendian_mdwithdraw',$thisuser_wxauth)): ?>
                    <div style="width: 120px;float: left;">
                        <input type="checkbox" title="余额提现" name="wxauth_data[]" <?php if(in_array('mendian_mdwithdraw',$wxauth_data)): ?>checked<?php endif; ?> value="mendian_mdwithdraw" lay-skin="primary"/>
                    </div>
                    <?php endif; if($thisuser['auth_type'] == 1 || in_array('mendian_mdwithdrawlog',$thisuser_wxauth)): ?>
                    <div style="width: 120px;float: left;">
                        <input type="checkbox" title="提现记录" name="wxauth_data[]" <?php if(in_array('mendian_mdwithdrawlog',$wxauth_data)): ?>checked<?php endif; ?> value="mendian_mdwithdrawlog" lay-skin="primary"/>
                    </div>
                    <?php endif; ?>
                    <div style="clear:both;float: left;margin-bottom:10px"></div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<!-- 需同步修改 web_user\edit.html -->
						</div>
						<?php endif; ?>
						<div class="layui-form-item">
							<label class="layui-form-label"></label>
							<div class="layui-input-inline">
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
	layui.form.on('checkbox(checkall_all)',function(data){
		if(data.elem.checked){
			$(data.elem).parent().parent().find('input[type=checkbox]').prop('checked',true);
		}else{
			$(data.elem).parent().parent().find('input[type=checkbox]').prop('checked',false);
		}
		layui.form.render('checkbox');
	})
	layui.form.on('checkbox(checkall)',function(data){
		if(data.elem.checked){
			$(data.elem).parent().parent().find('input[type=checkbox]').prop('checked',true);
		}else{
			$(data.elem).parent().parent().find('input[type=checkbox]').prop('checked',false);
		}
		layui.form.render('checkbox');
	})
	layui.form.on('select(groupidChange)',function(data){
		if(data.value == 0){
			$('#quanxianset_div').show();
		}else{
			$('#quanxianset_div').hide();
		}
	});
	layui.form.on('submit(formsubmit)', function(obj){
		var field = obj.field
		var index = layer.load();
		$.post("<?php echo url('save'); ?>",obj.field,function(data){
			layer.close(index);
			dialog(data.msg,data.status);
			if(data.status == 1){
				setTimeout(function(){
					parent.layer.closeAll();
					parent.tableIns.reload()
				},1000)
			}
		})
	})

	//这里写在定制里面
	
	//这里写在定制里面


  </script>
	
</body>
</html>