<?php /*a:4:{s:57:"/www/wwwroot/gdqshop.cn/app/view/designer_icon/index.html";i:1765905808;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;s:54:"/www/wwwroot/gdqshop.cn/app/view/public/copyright.html";i:1747926690;}*/ ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>图标设定</title>
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
    body{background-color:#f5f5f5}
    .icon-img{width:48px;height:48px;border:1px solid #eee;border-radius:6px;object-fit:contain;transition: all 0.3s ease;display:block;cursor:pointer}
    .icon-img:hover{border-color:#1E9FFF;transform: scale(1.05);}
    .top-actions{position:sticky;top:0;z-index:9;background:#fff;padding:15px 0;border-bottom:1px solid #f2f2f2;box-shadow: 0 2px 4px rgba(0,0,0,0.05)}
    .layui-tab{margin-top:20px}
    .icon-grid{display:flex;flex-wrap:wrap;gap:20px;padding:20px 0}
    .icon-card{width:260px;border:1px solid #eee;border-radius:8px;padding:16px;background:#fff;display:flex;align-items:center;justify-content:space-between;transition: all 0.3s ease;box-shadow: 0 2px 8px rgba(0,0,0,0.05)}
    .icon-card:hover{box-shadow: 0 4px 12px rgba(0,0,0,0.1);transform: translateY(-2px);border-color: #1E9FFF}
    .icon-info{display:flex;align-items:center;gap:16px}
    .icon-name{font-size:14px;font-weight:500;color:#333}
    .icon-actions{display:flex;gap:8px}
    .search-bar{display:flex;align-items:center;gap:15px;padding:10px 0}
    .search-bar .layui-input{width:260px}
    .icon-key{font-size:12px;color:#999;margin-top:2px}
    .layui-card-header{background-color:#f8f8f8;padding:15px 20px;font-size:16px;font-weight:500}
    .layui-card-body{padding:0}
    .layui-tab-title li{font-size:14px}
    .layui-tab-title li.layui-this{color:#1E9FFF}
    .layui-tab-content{padding:0}
  </style>
  </head>
<body>
  <div class="layui-fluid">
    <div class="layui-row layui-col-space15">
      <div class="layui-card layui-col-md12">
        <div class="layui-card-header">全站图标设定</div>
        <div class="layui-card-body layui-col-md12" pad15 ng-app="myApp" ng-controller="iconCtrl">
          <div class="layui-form form-label-w6">
            <div class="top-actions">
              <div class="search-bar">
                <input class="layui-input" type="text" ng-model="q" placeholder="搜索图标名称或键"/>
                <div style="flex:1"></div>
                <button class="layui-btn" ng-click="save()">保存</button>
              </div>
            </div>
            <div class="layui-tab" lay-filter="iconTab">
              <ul class="layui-tab-title">
                <li class="layui-this">顶部操作</li>
                <li>底部导航</li>
                <li>常规菜单</li>
                <li>订单与提示</li>
                <li>财务相关</li>
                <li>门店餐厅</li>
                <li>分享与海报</li>
                <li>其他功能</li>
              </ul>
              <div class="layui-tab-content">
                <div class="layui-tab-item layui-show">
                  <div class="icon-grid">
                    <div class="icon-card" ng-repeat="(midx,m) in mobile_icons_list | filter:byGroup('顶部操作') | filter:byKeyword(q) track by $index">
                      <div class="icon-info">
                        <img class="icon-img" ng-src="{{m.url + '?' + $scope.cacheBuster}}" ng-alt="{{iconMeta[m.key].name || m.key}}"/>
                        <div style="display:flex;flex-direction:column">
                          <div class="icon-name">{{iconMeta[m.key].name || m.key}}</div>
                          <div class="icon-key">键名: {{m.key}}</div>
                        </div>
                      </div>
                      <div class="icon-actions" style="margin-left:auto">
                        <button class="layui-btn layui-btn-primary" ng-click="uploadIcon('mobile',m,1)">修改图标</button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="layui-tab-item">
                  <div class="icon-grid">
                    <div class="icon-card" ng-repeat="(midx,m) in mobile_icons_list | filter:byGroup('底部导航') | filter:byKeyword(q) track by $index">
                      <div class="icon-info">
                        <img class="icon-img" ng-src="{{m.url + '?' + $scope.cacheBuster}}" ng-alt="{{iconMeta[m.key].name || m.key}}"/>
                        <div style="display:flex;flex-direction:column">
                          <div class="icon-name">{{iconMeta[m.key].name || m.key}}</div>
                          <div class="icon-key">键名: {{m.key}}</div>
                        </div>
                      </div>
                      <div class="icon-actions" style="margin-left:auto">
                        <button class="layui-btn layui-btn-primary" ng-click="uploadIcon('mobile',m,1)">修改图标</button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="layui-tab-item">
                  <div class="icon-grid">
                    <div class="icon-card" ng-repeat="(midx,m) in mobile_icons_list | filter:byGroup('常规菜单') | filter:byKeyword(q) track by $index">
                      <div class="icon-info">
                        <img class="icon-img" ng-src="{{m.url + '?' + $scope.cacheBuster}}" ng-alt="{{iconMeta[m.key].name || m.key}}"/>
                        <div style="display:flex;flex-direction:column">
                          <div class="icon-name">{{iconMeta[m.key].name || m.key}}</div>
                          <div class="icon-key">键名: {{m.key}}</div>
                        </div>
                      </div>
                      <div class="icon-actions" style="margin-left:auto">
                        <button class="layui-btn layui-btn-primary" ng-click="uploadIcon('mobile',m,1)">修改图标</button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="layui-tab-item">
                  <div class="icon-grid">
                    <div class="icon-card" ng-repeat="(midx,m) in mobile_icons_list | filter:byGroup('订单与提示') | filter:byKeyword(q) track by $index">
                      <div class="icon-info">
                        <img class="icon-img" ng-src="{{m.url + '?' + $scope.cacheBuster}}" ng-alt="{{iconMeta[m.key].name || m.key}}"/>
                        <div style="display:flex;flex-direction:column">
                          <div class="icon-name">{{iconMeta[m.key].name || m.key}}</div>
                          <div class="icon-key">键名: {{m.key}}</div>
                        </div>
                      </div>
                      <div class="icon-actions" style="margin-left:auto">
                        <button class="layui-btn layui-btn-primary" ng-click="uploadIcon('mobile',m,1)">修改图标</button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="layui-tab-item">
                  <div class="icon-grid">
                    <div class="icon-card" ng-repeat="(midx,m) in mobile_icons_list | filter:byGroup('财务相关') | filter:byKeyword(q) track by $index">
                      <div class="icon-info">
                        <img class="icon-img" ng-src="{{m.url + '?' + $scope.cacheBuster}}" ng-alt="{{iconMeta[m.key].name || m.key}}"/>
                        <div style="display:flex;flex-direction:column">
                          <div class="icon-name">{{iconMeta[m.key].name || m.key}}</div>
                          <div class="icon-key">键名: {{m.key}}</div>
                        </div>
                      </div>
                      <div class="icon-actions" style="margin-left:auto">
                        <button class="layui-btn layui-btn-primary" ng-click="uploadIcon('mobile',m,1)">修改图标</button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="layui-tab-item">
                  <div class="icon-grid">
                    <div class="icon-card" ng-repeat="(midx,m) in mobile_icons_list | filter:byGroup('门店餐厅') | filter:byKeyword(q) track by $index">
                      <div class="icon-info">
                        <img class="icon-img" ng-src="{{m.url + '?' + $scope.cacheBuster}}" ng-alt="{{iconMeta[m.key].name || m.key}}"/>
                        <div style="display:flex;flex-direction:column">
                          <div class="icon-name">{{iconMeta[m.key].name || m.key}}</div>
                          <div class="icon-key">键名: {{m.key}}</div>
                        </div>
                      </div>
                      <div class="icon-actions" style="margin-left:auto">
                        <button class="layui-btn layui-btn-primary" ng-click="uploadIcon('mobile',m,1)">修改图标</button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="layui-tab-item">
                  <div class="icon-grid">
                    <div class="icon-card" ng-repeat="(midx,m) in mobile_icons_list | filter:byGroup('分享与海报') | filter:byKeyword(q) track by $index">
                      <div class="icon-info">
                        <img class="icon-img" ng-src="{{m.url + '?' + $scope.cacheBuster}}" ng-alt="{{iconMeta[m.key].name || m.key}}"/>
                        <div style="display:flex;flex-direction:column">
                          <div class="icon-name">{{iconMeta[m.key].name || m.key}}</div>
                          <div class="icon-key">键名: {{m.key}}</div>
                        </div>
                      </div>
                      <div class="icon-actions" style="margin-left:auto">
                        <button class="layui-btn layui-btn-primary" ng-click="uploadIcon('mobile',m,1)">修改图标</button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="layui-tab-item">
                  <div class="icon-grid">
                    <div class="icon-card" ng-repeat="(midx,m) in mobile_icons_list | filter:byGroup('其他功能') | filter:byKeyword(q) track by $index">
                      <div class="icon-info">
                        <img class="icon-img" ng-src="{{m.url + '?' + $scope.cacheBuster}}" ng-alt="{{iconMeta[m.key].name || m.key}}"/>
                        <div style="display:flex;flex-direction:column">
                          <div class="icon-name">{{iconMeta[m.key].name || m.key}}</div>
                          <div class="icon-key">键名: {{m.key}}</div>
                        </div>
                      </div>
                      <div class="icon-actions" style="margin-left:auto">
                        <button class="layui-btn layui-btn-primary" ng-click="uploadIcon('mobile',m,1)">修改图标</button>
                      </div>
                    </div>
                  </div>
                </div>
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
  <script type="text/javascript" src="/static/admin/js/angular.min.js"></script>
  <script>
  var app = angular.module('myApp', []);
  app.controller('iconCtrl', function($scope) {
    $scope.mobile_icons_list = <?php echo $mobile_icons_list_json; ?>;
    $scope.cacheBuster = new Date().getTime(); // 添加时间戳防止缓存
    $scope.iconMeta = {
      // 顶部操作图标
      saoyisao:{name:'扫一扫',group:'顶部操作'},
      setup:{name:'设置',group:'顶部操作'},
      right_black:{name:'返回箭头',group:'顶部操作'},
      
      // 底部导航图标
      member:{name:'会员',group:'底部导航'},
      member2:{name:'会员2',group:'底部导航'},
      zixun:{name:'评价审核',group:'底部导航'},
      zixun2:{name:'评价审核2',group:'底部导航'},
      finance:{name:'财务',group:'底部导航'},
      finance2:{name:'财务2',group:'底部导航'},
      my:{name:'我的',group:'底部导航'},
      my2:{name:'我的2',group:'底部导航'},
      
      // 常规菜单图标
      menu1:{name:'核销记录',group:'常规菜单'},
      menu2:{name:'商品管理',group:'常规菜单'},
      menu3:{name:'消息通知',group:'常规菜单'},
      menu4:{name:'切换账号',group:'常规菜单'},
      menu5:{name:'修改密码',group:'常规菜单'},
      
      // 订单与提示图标
      titletips:{name:'标题提示',group:'订单与提示'},
      jiantou:{name:'列表箭头',group:'订单与提示'},
      order1:{name:'待付款订单',group:'订单与提示'},
      order2:{name:'待发货订单',group:'订单与提示'},
      order3:{name:'待收货订单',group:'订单与提示'},
      order4:{name:'退款/售后',group:'订单与提示'},
      
      // 财务相关图标
      financenbg1:{name:'财务图标1',group:'财务相关'},
      financenbg2:{name:'财务图标2',group:'财务相关'},
      financenbg3:{name:'财务图标3',group:'财务相关'},
      financenbg4:{name:'财务图标4',group:'财务相关'},
      financenbg5:{name:'财务图标5',group:'财务相关'},
      financenbg6:{name:'财务图标6',group:'财务相关'},
      financenbg7:{name:'余额明细',group:'财务相关'},
      financenbg8:{name:'余额提现',group:'财务相关'},
      financenbg9:{name:'财务图标9',group:'财务相关'},
      financenbg10:{name:'财务图标10',group:'财务相关'},
      financejiantou:{name:'财务箭头',group:'财务相关'},
      
      // 门店餐厅图标
      fork:{name:'用餐状态',group:'门店餐厅'},
      dish:{name:'菜品',group:'门店餐厅'},
      money:{name:'金额',group:'门店餐厅'},
      change:{name:'找零',group:'门店餐厅'},
      clean:{name:'清台',group:'门店餐厅'},
      close:{name:'关闭',group:'门店餐厅'},
      start:{name:'开始',group:'门店餐厅'},
      pause:{name:'暂停',group:'门店餐厅'},
      
      // 分享与海报图标
      share:{name:'分享图标',group:'分享与海报'},
      share_poster:{name:'生成分享图片',group:'分享与海报'},
      share_wechat:{name:'微信分享',group:'分享与海报'},
      share_friends:{name:'朋友圈分享',group:'分享与海报'},
      share_qq:{name:'QQ分享',group:'分享与海报'},
      share_weibo:{name:'微博分享',group:'分享与海报'},
      poster_generate:{name:'海报生成',group:'分享与海报'},
      poster_save:{name:'保存海报',group:'分享与海报'},
      share_link:{name:'链接分享',group:'分享与海报'},
      
      // 其他功能图标
      wm1:{name:'添加分类',group:'其他功能'},
      wm2:{name:'商品分类',group:'其他功能'},
      wm5:{name:'通用图标',group:'其他功能'},
      wm7:{name:'添加商品',group:'其他功能'},
      dismendian:{name:'门店管理',group:'其他功能'},
      dishm6:{name:'商品柜设备',group:'其他功能'},
      dishm8:{name:'发票报销',group:'其他功能'},
      jieshiicon:{name:'解释图标',group:'其他功能'},
      goback:{name:'返回',group:'其他功能'}
    };
    $scope.byGroup = function(g){
      return function(item){
        var k = item.key;
        return $scope.iconMeta[k] && $scope.iconMeta[k].group===g;
      };
    };
    $scope.byKeyword = function(q){
      return function(item){
        if(!q) return true;
        var k = item.key;
        var n = ($scope.iconMeta[k] && $scope.iconMeta[k].name) ? $scope.iconMeta[k].name : '';
        q = (q+'').toLowerCase();
        return (k.toLowerCase().indexOf(q) >= 0) || (n.toLowerCase().indexOf(q) >= 0);
      };
    };

    $scope.uploadIcon = function(group, target, which){
      // 保存原始图标URL作为备份
      if(group==='mobile'){
        var m = target;
        if(typeof target === 'number'){
          m = $scope.mobile_icons_list[target];
        }
        
        // 检查fileUploader是否存在，如果不存在则使用简易上传方案
        if(typeof fileUploader === 'undefined' || !fileUploader.show) {
          // 使用原生的文件上传
          var input = document.createElement('input');
          input.type = 'file';
          input.accept = 'image/*';
          input.onchange = function(e) {
            var file = e.target.files[0];
            if(file) {
              // 创建FormData对象
              var formData = new FormData();
              formData.append('file', file);
              formData.append('type', 'image');
              
              // 显示加载提示
              var loading = layer.load();
              
              // 发送AJAX请求上传文件
              $.ajax({
                url: '<?php echo url("Upload/index"); ?>',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(data) {
                  layer.close(loading);
                  if(data && data.status === 1 && data.url) {
                    // 上传成功后更新图标URL
                    m.url = data.url;
                    // 立即刷新缓存时间戳，确保新图标立即显示
                    $scope.cacheBuster = new Date().getTime();
                    layer.msg('图标更新成功，请点击保存按钮以永久保存更改');
                    // 确保AngularJS更新视图
                    $scope.$apply();
                  } else {
                    layer.msg('上传失败：' + (data.msg || '未知错误'));
                  }
                },
                error: function() {
                  layer.close(loading);
                  layer.msg('上传失败，请检查网络连接');
                }
              });
            }
          };
          input.click();
        } else {
          // 尝试使用fileUploader
          try {
            fileUploader.show(function(data){
              if(data && data.url){
                // 上传成功后更新图标URL
                m.url = data.url;
                // 立即刷新缓存时间戳，确保新图标立即显示
                $scope.cacheBuster = new Date().getTime();
                layer.msg('图标更新成功，请点击保存按钮以永久保存更改');
                // 确保AngularJS更新视图
                $scope.$apply();
              } else {
                layer.msg('上传失败，请重试');
              }
            }, {type :'image',maxheight:110,maxwidth:110, title: '选择新图标'});
          } catch(e) {
            console.error('上传器调用失败:', e);
            layer.msg('无法打开上传器，请刷新页面重试');
          }
        }
      }
    };


    $scope.save = function(){
      // 过滤掉可能为空的URL，确保数据完整性
      var mobileIcons = $scope.mobile_icons_list.map(function(icon) {
          return {
              key: icon.key,
              url: icon.url || '' // 确保url字段始终存在
          };
      });
      
      var field = {};
      if($scope.mobile_icons_list){
        field.mobile_icons_list = mobileIcons;
      }
      
      // 显示加载提示
      var loadlay = layer.open({type:3});
      
      // 使用form表单格式提交数据，与后端接口匹配
      $.post("<?php echo url('save'); ?>/type/<?php echo $type; ?>", {info: field}, function(data){
        layer.close(loadlay);
        if(data && data.status === 1){
          layer.msg('保存成功，建议刷新页面查看效果');
          // 确保页面能正确跳转
          setTimeout(function() {
            window.location.href = data.url || "<?php echo url('index'); ?>/type/<?php echo $type; ?>";
          }, 1500);
        } else {
          layer.msg(data && data.msg ? data.msg : '保存失败，请重试');
        }
      }).fail(function(){
        layer.close(loadlay);
        layer.msg('网络错误，保存失败');
      });
    };
  });
  layui.use('element', function(){
    var element = layui.element;
  });
  </script>
  
</body>
</html>
