<?php /*a:3:{s:65:"/www/wwwroot/gdqshop.cn/app/view/product_qrcode/service_logs.html";i:1768361017;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;}*/ ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>服务记录管理</title>
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
</head>
<body>
  <div class="layui-fluid">
    <div class="layui-row layui-col-space15">
      <div class="layui-card layui-col-md12">
        <div class="layui-card-header">
          服务记录管理
          <a href="<?php echo url('ProductQrcode/index'); ?>" class="layui-btn layui-btn-sm layui-btn-primary" style="float: right;">返回列表</a>
        </div>
        <div class="layui-card-body" pad15>
          <div class="layui-form form-label-w8" lay-filter="searchForm">
            <div class="layui-form-item">
              <label class="layui-form-label">唯一码</label>
              <div class="layui-input-inline" style="width: 200px;">
                <input type="text" name="keyword" value="<?php echo (isset($params['keyword']) && ($params['keyword'] !== '')?$params['keyword']:''); ?>" placeholder="请输入唯一码" class="layui-input">
              </div>
              
              <label class="layui-form-label">操作类型</label>
              <div class="layui-input-inline" style="width: 150px;">
                <select name="action">
                  <option value="">全部操作</option>
                  <option value="1">数据录入</option>
                  <option value="2">销售</option>
                  <option value="3">安装</option>
                  <option value="4">维修</option>
                  <option value="5">质保查询</option>
                  <option value="6">激活</option>
                </select>
              </div>
              
              <label class="layui-form-label">操作人类型</label>
              <div class="layui-input-inline" style="width: 150px;">
                <select name="operator_type">
                  <option value="">全部身份</option>
                  <option value="1">供货商</option>
                  <option value="2">门店</option>
                  <option value="3">销售员</option>
                  <option value="4">安装员</option>
                  <option value="5">客服</option>
                  <option value="6">技术员</option>
                  <option value="7">客户</option>
                </select>
              </div>
              
              <button class="layui-btn" lay-submit lay-filter="search">搜索</button>
              <button class="layui-btn layui-btn-primary" lay-submit lay-filter="reset">重置</button>
            </div>
          </div>
          
          <!-- 服务记录列表 -->
          <table class="layui-table" lay-data="{id:'serviceLogsTable', page:true, limit:20, limits:[10,20,50,100], url:'<?php echo url("ProductQrcode/serviceLogs"); ?>', where:<?php echo json_encode($params); ?>, defaultToolbar:['filter', 'exports', 'print']}" lay-filter="serviceLogsTable">
            <thead>
              <tr>
                <th lay-data="{field:'id', width:80}">ID</th>
                <th lay-data="{field:'code', width:200}">唯一码</th>
                <th lay-data="{field:'operator_name', width:120}">操作人</th>
                <th lay-data="{field:'operator_type_text', width:120}">身份</th>
                <th lay-data="{field:'action_text', width:120}">操作类型</th>
                <th lay-data="{field:'content', width:200}">操作内容</th>
                <th lay-data="{field:'result_text', width:80}">结果</th>
                <th lay-data="{field:'remark', width:200}">备注</th>
                <th lay-data="{field:'ip', width:150}">IP地址</th>
                <th lay-data="{field:'create_time', width:180, templet:'#timeTpl'}">操作时间</th>
              </tr>
            </thead>
            <tbody>
              <!-- 数据将通过AJAX加载 -->
            </tbody>
          </table>
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
  <script type="text/html" id="timeTpl">
    {{layui.util.toDateString(d.create_time * 1000, 'yyyy-MM-dd HH:mm:ss')}}    
  </script>
  <script>
    layui.use(['table', 'form', 'layer'], function() {
      var table = layui.table;
      var form = layui.form;
      var layer = layui.layer;
      
      // 渲染表格
      table.render({
        elem: '#serviceLogsTable',
        text: {
          none: '暂无服务记录'
        }
      });
      
      // 搜索表单提交
      form.on('submit(search)', function(data) {
        table.reload('serviceLogsTable', {
          where: data.field,
          page: {curr: 1}
        });
        return false;
      });
      
      // 重置表单
      form.on('submit(reset)', function() {
        // 清空表单
        form.val('searchForm', {
          keyword: '',
          action: '',
          operator_type: ''
        });
        
        // 重新加载表格
        table.reload('serviceLogsTable', {
          where: {},
          page: {curr: 1}
        });
        return false;
      });
      
      // 表格工具事件
      table.on('tool(serviceLogsTable)', function(obj) {
        var data = obj.data;
        switch(obj.event) {
          case 'detail':
            // 查看详情
            layer.open({
              type: 1,
              title: '服务记录详情',
              area: ['800px', '500px'],
              content: '<div style="padding: 20px;">' +
                '<p><strong>唯一码：</strong>' + data.code + '</p>' +
                '<p><strong>操作人：</strong>' + data.operator_name + '</p>' +
                '<p><strong>身份：</strong>' + data.operator_type_text + '</p>' +
                '<p><strong>操作类型：</strong>' + data.action_text + '</p>' +
                '<p><strong>操作内容：</strong>' + data.content + '</p>' +
                '<p><strong>结果：</strong>' + data.result_text + '</p>' +
                '<p><strong>备注：</strong>' + data.remark + '</p>' +
                '<p><strong>IP地址：</strong>' + data.ip + '</p>' +
                '<p><strong>操作时间：</strong>' + layui.util.toDateString(data.create_time * 1000, 'yyyy-MM-dd HH:mm:ss') + '</p>' +
              '</div>'
            });
            break;
        }
      });
    });
  </script>
</body>
</html>