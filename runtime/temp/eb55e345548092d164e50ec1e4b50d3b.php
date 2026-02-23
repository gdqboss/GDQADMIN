<?php /*a:6:{s:45:"/www/wwwroot/gdqshop.cn/app/view/sms/set.html";i:1747926690;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:53:"/www/wwwroot/gdqshop.cn/app/view/public/sms_tmpl.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;s:56:"/www/wwwroot/gdqshop.cn/app/view/public/sms_tmpl_js.html";i:1747926690;s:54:"/www/wwwroot/gdqshop.cn/app/view/public/copyright.html";i:1747926690;}*/ ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>短信设置</title>
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=0">
  <style>
	  .hide{display:none}
  </style>
  <link rel="stylesheet" type="text/css" href="/static/admin/layui/css/layui.css?v=20200519" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/layui/css/modules/formSelects-v4.css?v=20200516" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/css/admin.css?v=202406" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/css/font-awesome.min.css?v=20200516" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/webuploader/webuploader.css?v=<?php echo time(); ?>" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/css/designer.css?v=202410" media="all">
<link rel="stylesheet" type="text/css" href="/static/fonts/iconfont.css?v=20201218" media="all">
</head>
<body>
  <div class="layui-fluid">
    <div class="layui-row layui-col-space15">
      <div class="layui-card layui-col-md12">
				<!-- <div class="layui-card-header">短信设置</div> -->
				<div class="layui-tab layui-tab-brief">
					<ul class="layui-tab-title">
						<li class="layui-this" onclick="location.href='<?php echo url('set'); ?>'">短信设置</li>
						<li onclick="location.href='<?php echo url('sendlog'); ?>'">发送记录</li>
					</ul>
				</div>
				<div class="layui-card-body" pad15>
					
					<div class="layui-form form-label-w8" lay-filter="">
						<?php if($ainfo['sms_system_status'] != 1): ?>
						<div class="layui-form-item">
							<label class="layui-form-label">短信状态</label>
							<div class="layui-input-inline" style="width:300px">
								<input type="radio" name="info[status]" value="1" title="开启" <?php if($info['status']==1): ?>checked<?php endif; ?>/>
								<input type="radio" name="info[status]" value="0" title="关闭" <?php if($info['status']==0): ?>checked<?php endif; ?>/>
							</div>
							<div class="layui-form-mid layui-word-aux"></div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label">短信接口</label>
							<div class="layui-input-inline" style="width:300px">
								<select name="info[smstype]" lay-filter="smstypeset">
									<option value="1" <?php if($info['smstype']==1): ?>selected<?php endif; ?>>阿里云</option>
									<option value="2" <?php if($info['smstype']==2): ?>selected<?php endif; ?>>腾讯云</option>
									<!-- <option value="3">聚合短信</option> -->
								</select>
							</div>
							<div class="layui-form-mid layui-word-aux"></div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label" id="keyid"><?php if($info['smstype']==1): ?>AccessKey ID<?php else: ?>SecretId<?php endif; ?></label>
							<div class="layui-input-inline" style="width:300px">
								<input type="text" name="info[accesskey]" value="<?php echo $info['accesskey']; ?>" class="layui-input"/>
								<?php if($info['accesskey']): ?><div style="position: absolute;left: 0;top: 0;right: 0;bottom: 0; background: #fff;padding: .35rem .7rem;border: 1px solid rgba(0, 0, 0, .15);border-radius: .15rem;color: #636c72;" onclick="$(this).hide()">已隐藏内容，点击查看或编辑</div><?php endif; ?>
							</div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label" id="secretid"><?php if($info['smstype']==1): ?>AccessKey Secret<?php else: ?>SecretKey<?php endif; ?></label>
							<div class="layui-input-inline" style="width:300px">
								<input type="text" name="info[accesssecret]" value="<?php echo $info['accesssecret']; ?>" class="layui-input"/>
								<?php if($info['accesssecret']): ?><div style="position: absolute;left: 0;top: 0;right: 0;bottom: 0; background: #fff;padding: .35rem .7rem;border: 1px solid rgba(0, 0, 0, .15);border-radius: .15rem;color: #636c72;" onclick="$(this).hide()">已隐藏内容，点击查看或编辑</div><?php endif; ?>
							</div>
						</div>
						<div class="layui-form-item" id="sdkappiddiv" style="<?php if($info['smstype']!=2): ?>display:none;<?php endif; ?>">
							<label class="layui-form-label">应用SDK AppID</label>
							<div class="layui-input-inline" style="width:300px">
								<input type="text" name="info[sdkappid]" value="<?php echo $info['sdkappid']; ?>" class="layui-input"/>
								<?php if($info['sdkappid']): ?><div style="position: absolute;left: 0;top: 0;right: 0;bottom: 0; background: #fff;padding: .35rem .7rem;border: 1px solid rgba(0, 0, 0, .15);border-radius: .15rem;color: #636c72;" onclick="$(this).hide()">已隐藏内容，点击查看或编辑</div><?php endif; ?>
							</div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label">短信签名</label>
							<div class="layui-input-inline" style="width:300px">
								<input class="layui-input" name="info[sign_name]" value="<?php echo $info['sign_name']; ?>"/>
							</div>
							<div class="layui-form-mid layui-word-aux"></div>
						</div>
						<div class="layui-form-item" id="codelength" style="<?php if($info['smstype']!=2): ?>display:none;<?php endif; ?>">
							<label class="layui-form-label">变量长度</label>
							<div class="layui-input-inline" style="width:300px">
								<input type="radio" name="info[code_length]" value="0" title="不限制" lay-filter="codelength" <?php if($info['code_length']==0): ?>checked<?php endif; ?>/>
								<input type="radio" name="info[code_length]" value="1" title="6个字符" lay-filter="codelength" <?php if($info['code_length']==1): ?>checked<?php endif; ?>/>
							</div>
							<div class="layui-form-mid layui-word-aux">腾讯云新账号限制变量最多6个字符</div>
						</div>
						<?php endif; if($ainfo['sms_system_status'] == 1): ?>
						<blockquote class="layui-elem-quote" style="color:#333">
							当前使用平台短信
						</blockquote>
						<div class="layui-form-item">
							<label class="layui-form-label">短信签名</label>
							<div class="layui-input-inline" style="width:300px">
								<input class="layui-input" value="<?php echo $info['sign_name']; ?>" disabled/>
							</div>
							<div class="layui-form-mid layui-word-aux"></div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label">每条价格</label>
							<div class="layui-input-inline" style="width:300px">
								<input class="layui-input" name="" value="<?php echo $ainfo['sms_system_price']; ?>" disabled/>
							</div>
							<div class="layui-form-mid layui-word-aux"></div>
						</div>
						<?php endif; if($ainfo['sms_system_status'] != 1): ?>
						短信模板设置
						<hr>
						<div class="layui-form-item">
  <label class="layui-form-label">短信验证码</label>
  <div class="layui-input-inline" style="width:300px">
    <input class="layui-input" name="info[tmpl_smscode]" value="<?php echo $info['tmpl_smscode']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_smscode_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_smscode_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您的验证码：${code}，该验证码5分钟内有效，请勿泄漏于他人！</div>
  <div class="layui-form-mid layui-word-aux smstip2" style="margin-left:10px;<?php if($info['smstype']!=2): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：您的验证码：{1}，该验证码5分钟内有效，请勿泄漏于他人！</div>
</div>
<!-- <div class="layui-form-item">
    <label class="layui-form-label">订单提交成功</label>
    <div class="layui-input-inline" style="width:300px">
        <input class="layui-input" name="info[tmpl_orderconfirm]" value="<?php echo $info['tmpl_orderconfirm']; ?>"/>
    </div>
    <div class="layui-input-inline" style="width:100px">
        <input type="checkbox" name="info[tmpl_orderconfirm_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_orderconfirm_st']==1): ?>checked<?php endif; ?>>
    </div>
    <div class="layui-form-mid layui-word-aux" style="margin-left:10px;"></div>
</div> -->
<div class="layui-form-item">
  <label class="layui-form-label">订单支付成功</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_orderpay]" value="<?php echo $info['tmpl_orderpay']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_orderpay_txy]" value="<?php echo $info['tmpl_orderpay_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_orderpay_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_orderpay_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：订单支付成功，订单号：${ordernum}，我们会尽快为您发货。</div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：订单支付成功，订单号：{1}，我们会尽快为您发货。</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：订单支付成功，订单尾号：{1}，我们会尽快为您发货。</div>
</div>
<div class="layui-form-item">
  <label class="layui-form-label">订单发货通知</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_orderfahuo]" value="<?php echo $info['tmpl_orderfahuo']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_orderfahuo_txy]" value="<?php echo $info['tmpl_orderfahuo_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_orderfahuo_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_orderfahuo_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您的订单${ordernum}已发货，快递公司：${express_com}，快递单号：${express_no}，请留意查收。</div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：您的订单{1}已发货，快递公司：{2}，快递单号：{3}，请留意查收。</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：您的订单尾号{1}已发货，快递公司：{2}，快递尾号：{3}，请留意查收。</div>
</div>
<div class="layui-form-item">
  <label class="layui-form-label">拼团成功通知</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_collagesuccess]" value="<?php echo $info['tmpl_collagesuccess']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_collagesuccess_txy]" value="<?php echo $info['tmpl_collagesuccess_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_collagesuccess_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_collagesuccess_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：订单${ordernum}拼团成功，我们会尽快为您发货。</div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：订单{1}拼团成功，我们会尽快为您发货。</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：订单尾号{1}拼团成功，我们会尽快为您发货。</div>
</div>
<div class="layui-form-item">
  <label class="layui-form-label">退款成功通知</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_tuisuccess]" value="<?php echo $info['tmpl_tuisuccess']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>

  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_tuisuccess_txy]" value="<?php echo $info['tmpl_tuisuccess_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->

  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_tuisuccess_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_tuisuccess_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您的订单${ordernum}退款成功，退款金额：${money}元，请留意查收。</div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：您的订单{1}退款成功，退款金额：{2}元，请留意查收。</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：您的订单尾号{1}退款成功，退款金额：{2} {3}元，请留意查收。</div>
</div>
<div class="layui-form-item">
  <label class="layui-form-label">退款驳回通知</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_tuierror]" value="<?php echo $info['tmpl_tuierror']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_tuierror_txy]" value="<?php echo $info['tmpl_tuierror_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_tuierror_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_tuierror_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：抱歉您的订单${ordernum}退款申请失败，原因：${reason}。</div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：抱歉您的订单{1}退款申请失败，原因：{2}。</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：抱歉您的订单尾号{1}退款申请失败，原因：{2}，具体原因登录系统查看。</div>
</div>
<div class="layui-form-item">
  <label class="layui-form-label">提现成功通知</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_tixiansuccess]" value="<?php echo $info['tmpl_tixiansuccess']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_tixiansuccess_txy]" value="<?php echo $info['tmpl_tixiansuccess_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_tixiansuccess_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_tixiansuccess_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：提现成功，打款金额：${money}，请留意查收。</div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：提现成功，打款金额：{1}，请留意查收。</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：提现成功，打款金额：{1} {2}，请留意查收。</div>
</div>
<div class="layui-form-item">
  <label class="layui-form-label">提现失败通知</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_tixianerror]" value="<?php echo $info['tmpl_tixianerror']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_tixianerror_txy]" value="<?php echo $info['tmpl_tixianerror_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_tixianerror_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_tixianerror_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：抱歉您的提现申请失败，原因：${reason}。</div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：抱歉您的提现申请失败，原因：{1}。</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：抱歉您的提现申请失败，原因：{1}，具体原因登录系统查看。</div>
</div>
<div class="layui-form-item">
  <label class="layui-form-label">分销成功提醒</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_fenxiaosuccess]" value="<?php echo $info['tmpl_fenxiaosuccess']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_fenxiaosuccess_txy]" value="<?php echo $info['tmpl_fenxiaosuccess_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_fenxiaosuccess_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_fenxiaosuccess_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：成功获得佣金：${money}元，请留意查收。</div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：成功获得佣金：{1}元，请留意查收。</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：成功获得佣金：{1} {2}元，请留意查收。</div>
</div>
<?php if(getcustom('plug_zhangyuan')): ?>
<div class="layui-form-item">
  <label class="layui-form-label">审核通过通知</label>
  <div class="layui-input-inline" style="width:300px">
    <input class="layui-input" name="info[tmpl_checksuccess]" value="<?php echo $info['tmpl_checksuccess']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_checksuccess_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_checksuccess_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：恭喜您的注册审核已通过。</div>
  <div class="layui-form-mid layui-word-aux smstip2" style="margin-left:10px;<?php if($info['smstype']!=2): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：恭喜您的注册审核已通过。</div>
</div>
<div class="layui-form-item">
  <label class="layui-form-label">审核驳回通知</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_checkerror]" value="<?php echo $info['tmpl_checkerror']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_checkerror_txy]" value="<?php echo $info['tmpl_checkerror_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_checkerror_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_checkerror_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：抱歉您的注册审核未通过，驳回原因${reason}。</div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：抱歉您的注册审核未通过，驳回原因{1}。</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：抱歉您的注册审核未通过，驳回原因{1}，具体原因登录系统查看。</div>
</div>
<?php endif; if(getcustom('form_submit_notice')): ?>
<div class="layui-form-item">
  <label class="layui-form-label">表单提交通知</label>
  <div class="layui-input-inline" style="width:300px">
    <input class="layui-input" name="info[tmpl_formsubmit]" value="<?php echo $info['tmpl_formsubmit']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_formsubmit_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_formsubmit_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您的表单${ordernum}，已提交成功。</div>
  <div class="layui-form-mid layui-word-aux smstip2" style="margin-left:10px;<?php if($info['smstype']!=2): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：您的表单{1}，已提交成功。</div>
</div>
<?php endif; if($auth_data=='all' || in_array('Restaurant/*',$auth_data)): ?>
<div class="layui-form-item">
  <label class="layui-form-label">餐饮预定成功提醒</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_restaurant_booking]" value="<?php echo $info['tmpl_restaurant_booking']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_restaurant_booking_txy]" value="<?php echo $info['tmpl_restaurant_booking_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_restaurant_booking_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_restaurant_booking_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：预定成功，餐厅名称：${restaurant_name}，订位信息：${table}，预定时间：${time_range}，请准时到达。</div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：预定成功，餐厅名称：{1}，订位信息：{2}，预定时间：{3}，请准时到达。</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：预定成功，餐厅名称：{1}，订位信息：{2}，预定时间：{3}年{4}月{5}日 {6}~{7}，请准时到达。</div>
</div>
<div class="layui-form-item">
  <label class="layui-form-label">餐饮预定失败提醒</label>
  <div class="layui-input-inline" style="width:300px">
    <input class="layui-input" name="info[tmpl_restaurant_booking_fail]" value="<?php echo $info['tmpl_restaurant_booking_fail']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_restaurant_booking_fail_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_restaurant_booking_fail_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：抱歉您的预定失败，餐厅名称：${restaurant_name}，请重新预定。</div>
  <div class="layui-form-mid layui-word-aux smstip2" style="margin-left:10px;<?php if($info['smstype']!=2): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：抱歉您的预定失败，餐厅名称：{1}，请重新预定。</div>
</div>
<?php endif; if(getcustom('yuyue_sms')): ?>
<div class="layui-form-item">
  <label class="layui-form-label">预约成功通知</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_yysucess]" value="<?php echo $info['tmpl_yysucess']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_yysucess_txy]" value="<?php echo $info['tmpl_yysucess_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_yysucess_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_yysucess_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您预定的${name}，已为您准备好，预定时间：${time}，敬请准时赴约。</div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：您预定的{1}，已为您准备好，预定时间：{2}，敬请准时赴约。</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板ID</span> 模板内容示例：您预定的{1}，已为您准备好，预定时间 {2}年{3}月{4}日 {5}时{6}分{7}秒，敬请准时赴约。</div>
</div>
<?php endif; if(getcustom('zhaopin')): ?>
<div class="layui-form-item">
  <label class="layui-form-label">充值成功通知</label>
  <div class="layui-input-inline" style="width:300px">
    <input class="layui-input" name="info[tmpl_recharge]" value="<?php echo $info['tmpl_recharge']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_recharge_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_recharge_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：会员充值成功，请登录查看。</div>
  <div class="layui-form-mid layui-word-aux smstip2" style="margin-left:10px;<?php if($info['smstype']!=2): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：会员充值成功，请登录查看。</div>
</div>
<div class="layui-form-item">
  <label class="layui-form-label">审核通过通知</label>
  <div class="layui-input-inline" style="width:300px">
    <input class="layui-input" name="info[tmpl_checksuccess]" value="<?php echo $info['tmpl_checksuccess']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_checksuccess_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_checksuccess_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：恭喜您的注册审核已通过。</div>
  <div class="layui-form-mid layui-word-aux smstip2" style="margin-left:10px;<?php if($info['smstype']!=2): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：恭喜您的注册审核已通过。</div>
</div>
<div class="layui-form-item">
  <label class="layui-form-label">审核驳回通知</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_checkerror]" value="<?php echo $info['tmpl_checkerror']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_checkerror_txy]" value="<?php echo $info['tmpl_checkerror_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_checkerror_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_checkerror_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：抱歉您的注册审核未通过，驳回原因${reason}。</div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：抱歉您的注册审核未通过，驳回原因{1}。</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：抱歉您的注册审核未通过，驳回原因{1}，具体原因请登录后台查看。</div>。</div>
</div>
<div class="layui-form-item">
  <label class="layui-form-label">待审核通知</label>
  <div class="layui-input-inline" style="width:300px">
    <input class="layui-input" name="info[tmpl_checknotice]" value="<?php echo $info['tmpl_checknotice']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_checknotice_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_checknotice_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您有一条新的审核信息，请登录查看。</div>
  <div class="layui-form-mid layui-word-aux smstip2" style="margin-left:10px;<?php if($info['smstype']!=2): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您有一条新的审核信息，请登录查看。</div>
</div>
<div class="layui-form-item">
  <label class="layui-form-label">新私信提醒</label>
  <div class="layui-input-inline" style="width:300px">
    <input class="layui-input" name="info[tmpl_sysmsg_notice]" value="<?php echo $info['tmpl_sysmsg_notice']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_sysmsg_notice_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_sysmsg_notice_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您有一条来自于新的私信未读，请登录查看。</div>
  <div class="layui-form-mid layui-word-aux smstip2" style="margin-left:10px;<?php if($info['smstype']!=2): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您有一条来自于新的私信未读，请登录查看。</div>
</div>
<?php endif; if(getcustom('lot_cerberuse')): ?>
<div class="layui-form-item">
  <label class="layui-form-label">消费时间到期提醒</label>
  <div class="layui-input-inline" style="width:300px">
    <input class="layui-input" name="info[tmpl_use_expire]" value="<?php echo $info['tmpl_use_expire']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_use_expire_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_use_expire_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您消费的时间即将到期，为不影响您正常的使用，如有需要，请及时续费！</div>
  <div class="layui-form-mid layui-word-aux smstip2" style="margin-left:10px;<?php if($info['smstype']!=2): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您消费的时间即将到期，为不影响您正常的使用，如有需要，请及时续费。</div>
</div>
<?php endif; if(getcustom('car_hailing')): ?>
<div class="layui-form-item">
  <label class="layui-form-label">约车支付成功提醒</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_carhailing_sucess]" value="<?php echo $info['tmpl_carhailing_sucess']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_carhailing_sucess_txy]" value="<?php echo $info['tmpl_carhailing_sucess_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_carhailing_sucess_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_carhailing_sucess_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：约车订单(${ordernum})已支付成功！我们会尽快提前安排，祝您旅途愉快！</div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：约车订单({1})已支付成功！我们会尽快提前安排，祝您旅途愉快！</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：约车订单尾号({1})已支付成功！我们会尽快提前安排，祝您旅途愉快！</div>
</div>
<?php endif; if(getcustom('sms_temp_money_recharge')): ?>
<div class="layui-form-item">
  <label class="layui-form-label">充值通知提醒</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_money_recharge]" value="<?php echo $info['tmpl_money_recharge']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_money_recharge_txy]" value="<?php echo $info['tmpl_money_recharge_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_money_recharge_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_money_recharge_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您本次储值${money}元，赠送${givemoney}元，如有疑问，请联系店员核对您的储值金额。</div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您本次储值{1}元，赠送{2}元，如有疑问，请联系店员核对您的储值金额。</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您本次储值{1} {2}元，赠送{2} {3}元，如有疑问，请联系店员核对您的储值金额。</div>
</div>
<?php endif; if(getcustom('sms_temp_money_use')): ?>
<div class="layui-form-item">
  <label class="layui-form-label">消费通知提醒</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_money_use]" value="<?php echo $info['tmpl_money_use']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_money_use_txy]" value="<?php echo $info['tmpl_money_use_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_money_use_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_money_use_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您本次的消费总额${money}元，实收${real_money}元，储值余额${sy_money}元。</div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您本次的消费总额{1}元，实收{2}元，储值余额{3}元。</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您本次的消费总额{1} {2}元，实收{3} {4}元，储值余额{5} {6}元。</div>
</div>
<?php endif; if(getcustom('sms_temp_coupon_get')): ?>
<div class="layui-form-item">
  <label class="layui-form-label">发券通知提醒</label>
  <div class="layui-input-inline" style="width:300px">
    <input class="layui-input" name="info[tmpl_coupon_get]" value="<?php echo $info['tmpl_coupon_get']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_coupon_get_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_coupon_get_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：${bname}送您${num}张${coupon_name}，期待您的光临!</div>
  <div class="layui-form-mid layui-word-aux smstip2" style="margin-left:10px;<?php if($info['smstype']!=2): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：{1}送您{2}张{3}，期待您的光临!</div>
</div>
<?php endif; if(getcustom('restaurant_finance_notice_switch')): ?>
<div class="layui-form-item">
  <label class="layui-form-label">消费通知提醒</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_money_change]" value="<?php echo $info['tmpl_money_change']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_money_change_txy]" value="<?php echo $info['tmpl_money_change_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_money_change_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_money_change_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您本次消费${money}元，会员余额${sy_money}元。如不是本人消费请2分钟内立即致电我们。
  </div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您本次消费{1}元，会员余额{2}元。如不是本人消费请2分钟内立即致电我们。</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您本次消费{1} {2}元，会员余额{3} {4}元。如不是本人消费请2分钟内立即致电我们。</div>
</div>
<?php endif; if(getcustom('product_pickup_device')): ?>
<div class="layui-form-item">
  <label class="layui-form-label">设备缺货通知</label>
  <div class="layui-input-inline" style="width:300px">
    <input class="layui-input" name="info[tmpl_device_addstock_remind]" value="<?php echo $info['tmpl_device_addstock_remind']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_device_addstock_remind_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_device_addstock_remind_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：位于${address}的${name}自提柜已缺货，请及时进行补货。</div>
  <div class="layui-form-mid layui-word-aux smstip2" style="margin-left:10px;<?php if($info['smstype']!=2): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：位于{1}的{2}自提柜已缺货，请及时进行补货。</div>
</div>
<?php endif; if(getcustom('yx_queue_free_multi_team')): ?>
<div class="layui-form-item">
  <label class="layui-form-label">排队返利出局前通知</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_queue_free_before_quit]" value="<?php echo $info['tmpl_queue_free_before_quit']; ?>" <?php if($ainfo['tmpl_queue_free_before_quit'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_queue_free_before_quit_txy]" value="<?php echo $info['tmpl_queue_free_before_quit_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_queue_free_before_quit_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_queue_free_before_quit_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您好!您的账户将未返红包将于${date}到期，到期后将自动清零，请尽快消费保号使用。</div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您好!您的账户将未返红包将于{1}到期，到期后将自动清零，请尽快消费保号使用。</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您好!您的账户将未返红包将于{1}年{2}月{3}日到期，到期后将自动清零，请尽快消费保号使用。</div>
</div>
<?php endif; if(getcustom('yuyue_before_starting')): ?>
<div class="layui-form-item">
  <label class="layui-form-label">预约服务开始前通知</label>
  <div class="layui-input-inline normal-tmpl <?php if($info['code_length'] == 1 || $info['systype'] == 1): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_yuyue_before_starting]" value="<?php echo $info['tmpl_yuyue_before_starting']; ?>" <?php if($ainfo['tmpl_yuyue_before_starting'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 -->
  <div class="layui-input-inline length-limit-tmpl <?php if($info['code_length'] == 0): ?>hide<?php endif; ?>" style="width:300px">
    <input class="layui-input" name="info[tmpl_yuyue_before_starting_txy]" value="<?php echo $info['tmpl_yuyue_before_starting_txy']; ?>" <?php if($ainfo['sms_system_status'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <!-- 腾讯云 限制变量长度模板 end -->
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_yuyue_before_starting_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_yuyue_before_starting_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：服务项目${title}，订单号 ${ordernum}，将于${begintime}开始，请合理安排时间和准备。</div>
  <div class="layui-form-mid layui-word-aux smstip2 normal-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：服务项目{1}，订单号 {2}，将于{3}开始，请合理安排时间和准备。</div>
  <div class="layui-form-mid layui-word-aux smstip3 length-limit-tmpl-tips" style="margin-left:10px;<?php if($info['smstype']!=2 || $info['code_length'] == 0): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：服务项目{1}，订单号尾号 {2}，将于{3}年{4}月{5}日开始，请合理安排时间和准备。</div>
</div>
<?php endif; if(getcustom('finance_invoice_baoxiao')): ?>
<div class="layui-form-item">
  <label class="layui-form-label">报销打款提醒</label>
  <div class="layui-input-inline" style="width:300px">
    <input class="layui-input" name="info[tmpl_baoxiaosuccess]" value="<?php echo $info['tmpl_baoxiaosuccess']; ?>" <?php if($ainfo['tmpl_baoxiaosuccess'] == 1 && !$webset): ?>disabled<?php endif; ?>/>
  </div>
  <div class="layui-input-inline" style="width:100px">
    <input type="checkbox" name="info[tmpl_baoxiaosuccess_st]" value="1" lay-skin="switch" lay-text="开启|关闭" <?php if($info['tmpl_baoxiaosuccess_st']==1): ?>checked<?php endif; ?>>
  </div>
  <div class="layui-form-mid layui-word-aux smstip1" style="margin-left:10px;<?php if($info['smstype']!=1): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您提交报销已打款，请及时查看！</div>
  <div class="layui-form-mid layui-word-aux smstip2" style="margin-left:10px;<?php if($info['smstype']!=2): ?>display:none<?php endif; ?>"><span style="color:#666">请填写模板编号</span> 模板内容示例：您提交报销已打款，请及时查看。</div>
</div>
<?php endif; ?>

						<div class="layui-form-item">
							<label class="layui-form-label"></label>
							<div class="layui-input-block">
								<button class="layui-btn" lay-submit lay-filter="setmypass">确认修改</button>
							</div>
						</div>
						<?php endif; ?>
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
  	<script type="text/javascript">

  layui.form.on('submit(setmypass)', function(obj){
    var field = obj.field
    if(!field['info[tmpl_smscode_st]']) field['info[tmpl_smscode_st]'] = 0;
    if(!field['info[tmpl_orderconfirm_st]']) field['info[tmpl_orderconfirm_st]'] = 0;
    if(!field['info[tmpl_orderpay_st]']) field['info[tmpl_orderpay_st]'] = 0;
    if(!field['info[tmpl_collagesuccess_st]']) field['info[tmpl_collagesuccess_st]'] = 0;
    if(!field['info[tmpl_orderfahuo_st]']) field['info[tmpl_orderfahuo_st]'] = 0;
    if(!field['info[tmpl_tuisuccess_st]']) field['info[tmpl_tuisuccess_st]'] = 0;
    if(!field['info[tmpl_tuierror_st]']) field['info[tmpl_tuierror_st]'] = 0;
    if(!field['info[tmpl_tixiansuccess_st]']) field['info[tmpl_tixiansuccess_st]'] = 0;
    if(!field['info[tmpl_tixianerror_st]']) field['info[tmpl_tixianerror_st]'] = 0;
    if(!field['info[tmpl_fenxiaosuccess_st]']) field['info[tmpl_fenxiaosuccess_st]'] = 0;
    if(!field['info[tmpl_checksuccess_st]']) field['info[tmpl_checksuccess_st]'] = 0;
    if(!field['info[tmpl_checkerror_st]']) field['info[tmpl_checkerror_st]'] = 0;
    if(!field['info[tmpl_restaurant_booking_st]']) field['info[tmpl_restaurant_booking_st]'] = 0;
    if(!field['info[tmpl_restaurant_booking_fail_st]']) field['info[tmpl_restaurant_booking_fail_st]'] = 0;
    if(!field['info[tmpl_recharge_st]']) field['info[tmpl_recharge_st]'] = 0;
    if(!field['info[tmpl_sysmsg_notice_st]']) field['info[tmpl_sysmsg_notice_st]'] = 0;
    if(!field['info[tmpl_checknotice_st]']) field['info[tmpl_checknotice_st]'] = 0;
    
    $.post('',obj.field,function(data){
      dialog(data.msg,data.status,true);
    })
  })
</script>
	<script>
	layui.form.on('select(smstypeset)', function(data){
		if(data.value==1){
			$('#keyid').html('AccessKey ID');
			$('#secretid').html('AccessKey Secret');
			$('#sdkappiddiv').hide();
			$('.smstip1').show();
			$('.smstip2').hide();
			$('#codelength').hide();
			$('.normal-tmpl').show();
			$('.normal-tmpl-tips').hide();
			$('.length-limit-tmpl,.length-limit-tmpl-tips').hide();
		}else{
			$('#keyid').html('SecretId');
			$('#secretid').html('SecretKey');
			$('#sdkappiddiv').show();
			$('.smstip1').hide();
			$('.smstip2').show();
			$('#codelength').show();
			//获取当前变量长度状态
			let thiscodelength = $("input[name='info[code_length]']:checked").val();
			if(thiscodelength == '1'){
				$('.normal-tmpl,normal-tmpl-tips').hide();
				$('.length-limit-tmpl,length-limit-tmpl-tips').show();
			}else{
				$('.normal-tmpl,normal-tmpl-tips').show();
				$('.length-limit-tmpl,length-limit-tmpl-tips').hide();
			}
		}
	})

	layui.form.on('radio(codelength)', function(data){
		if(data.value === '1'){
			$('.normal-tmpl,.normal-tmpl-tips').hide();
			$('.length-limit-tmpl,.length-limit-tmpl-tips').show();
		}else{
			$('.normal-tmpl,.normal-tmpl-tips').show();
			$('.length-limit-tmpl,.length-limit-tmpl-tips').hide();
		}
	})
  </script>
	
</body>
</html>