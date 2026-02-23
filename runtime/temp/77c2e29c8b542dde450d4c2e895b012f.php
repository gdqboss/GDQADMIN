<?php /*a:2:{s:48:"/www/wwwroot/gdqshop.cn/app/view/index/help.html";i:1747926690;s:55:"/www/wwwroot/gdqshop.cn/app/view/index/public/menu.html";i:1747926690;}*/ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title><?php echo $webinfo['webname']; ?></title>
	<meta name="keywords" content="<?php echo $webinfo['webname']; ?>,微信公众号,微信公众号商城,小程序商城,小程序分销系统,小程序在线制作,小程序,微信小程序,小程序制作,微信小程序制作,小程序工具,微信小程序工具,小程序开发,微信小程序开发,小程序平台,微信小程序平台" />
	<meta name="description" content="<?php echo $webinfo['webname']; ?>-微信公众号微信小程序商城及分销解决方案，致力于为广大商家快速搭建自己的微信公众号微信小程序，围绕会员、营销、效率，助力商家轻松经营店铺。" />
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta name="Renderer" content="webkit">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta http-equiv="Pragma" content="no-cache">
	<!-- HTTP 1.0 -->
	<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
	<!-- Prevent caching at the proxy server -->
	<meta http-equiv="Expires" content="0">
	<meta http-equiv="X-UA-Compatible" content="IE=EmulateIE9" />
	<!-- Mobile Metas -->  
	<meta name="viewport" content="width=device-width, initial-scale=1.0,user-scalable=no,maximum-scale=1.0,minimum-scale=1.0">
	<meta http-equiv="Cache-Control" content="no-transform" />
	<meta http-equiv="Cache-Control" content="no-siteapp" />
	<?php if($webinfo['ico']): ?><link rel="shortcut icon" type="image/x-icon" href="<?php echo $webinfo['ico']; ?>" /><?php endif; ?>
	<link rel="stylesheet" href="/static/index/css/help.css?v=<?php echo time(); ?>">
	<script src="/static/index/js/jquery.js" type="text/javascript" charset="utf-8"></script>
	<script src="/static/index/js/jquery.SuperSlide.2.1.1.js" type="text/javascript" charset="utf-8"></script>
</head>
<body style="background:#F3F3F3">
<div class="toptab">
	<div class="content">
    <a href="/" class="logo"><img src="<?php echo $webinfo['logo']; ?>" title="<?php echo $webinfo['webname']; ?>"/>
		 <div class="title"><?php echo $webinfo['webname']; ?></div>
		</a>
    <div class="item">
        <a href="/" class="on">首页</a>
        <a href="<?php echo url('funshow'); ?>">商城功能</a>
        <a href="<?php echo url('lianxi'); ?>">联系我们</a>
        <a href="<?php echo url('help'); ?>">帮助中心</a>
        
    </div>
    <?php if($reg_open): ?>
    <div class="zixun" onclick="location.href='<?php echo url('Login/reg'); ?>'">免费注册</div>
    <?php else: ?>
    <div class="zixun" onclick="location.href='<?php echo url('lianxi'); ?>'">免费咨询</div>
    <?php endif; ?>
    <div class="login" onclick="location.href='<?php echo url('Backstage/index'); ?>'">立即登录</div>
</div>

	<div class="con">
		<div class="f1">帮助中心 我们与您一起成长！</div>
		<div class="f2"><input type="text" placeholder="请输入关键词搜索" class="inp1" id="keyword" value="<?php echo app('request')->param('keyword'); ?>"/><button class="searchbtn" onclick="gosearch()">搜索</button></div>
	</div>
</div>

<div class="contentbox">
	<div class="content">
		<div class="title">常见问题</div>
		<div class="con">
			<?php foreach($list as $k=>$v): ?>
			<div class="item" style="cursor:pointer" onclick="location.href='<?php echo url('helpdetail'); ?>/id/<?php echo $v['id']; ?>'">
				<div class="ti"><?php echo $v['name']; ?></div>
				<div class="viewnum flex-y-center"><img src="/static/index/img/help-eye.png"/>&nbsp;<?php echo $v['readcount']; ?>次浏览</div>
				<div class="time"><?php echo date('Y-m-d',$v['createtime']); ?></div>
			</div>
			<?php endforeach; ?>
			<?php echo $page; ?>
		</div>
	</div>
</div>

<div class="bottombox">
	<div class="f1">
		<a href="/">首页</a>
		<a href="<?php echo url('funshow'); ?>">功能介绍</a>
		<a href="<?php echo url('lianxi'); ?>">联系我们</a>
		<a href="<?php echo url('help'); ?>">帮助中心</a>
	</div>
	<div class="f2">
		<?php echo $webinfo['copyright']; ?>&nbsp;&nbsp;&nbsp;<a href="http://beian.miit.gov.cn/" target="_blank" style="color:#6B798E"><?php echo $webinfo['beian']; ?></a>
	</div>
	<?php if($webinfo['copyright2']): ?>
	<div class="f2">
		<a href="http://www.beian.gov.cn/portal/registerSystemInfo?recordcode=<?php echo $webinfo['beian2']; ?>" target="_blank" style="color:#6B798E"><?php echo $webinfo['copyright2']; ?></a>
	</div>
	<?php endif; ?>
</div>


<script>
function gosearch(){
	var keyword = $('#keyword').val();
  window.location.href = "<?php echo url('help'); ?>/keyword/"+keyword
}
</script>
</body>
</html>