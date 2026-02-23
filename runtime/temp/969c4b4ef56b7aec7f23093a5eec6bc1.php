<?php /*a:3:{s:58:"/www/wwwroot/gdqshop.cn/app/view/product_qrcode/index.html";i:1768361017;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;}*/ ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>一物一码管理</title>
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
    .layui-table img {
      max-width: 100px;
      max-height: 100px;
    }
    .status-tag {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 12px;
    }
    .status-enabled {
      background-color: #52c41a;
      color: white;
    }
    .status-disabled {
      background-color: #d9d9d9;
      color: #333;
    }
    .status-used {
      background-color: #1890ff;
      color: white;
    }
  </style>
</head>
<body>
  <div class="layui-fluid">
    <div class="layui-row layui-col-space15">
      <div class="layui-card layui-col-md12">
        <div class="layui-card-header">
          一物一码管理
          <div class="layui-btn-group">
            <a href="<?php echo url('ProductQrcode/generate'); ?>" class="layui-btn layui-btn-sm">生成二维码</a>
            <a href="<?php echo url('ProductQrcode/batchGenerate'); ?>" class="layui-btn layui-btn-sm layui-btn-normal">批量生成</a>
            <a href="<?php echo url('ProductQrcode/batchPrint'); ?>" class="layui-btn layui-btn-sm layui-btn-warm">批量打印</a>
            <a href="<?php echo url('ProductQrcode/statistics'); ?>" class="layui-btn layui-btn-sm layui-btn-primary">扫码统计</a>
          </div>
        </div>
        <div class="layui-card-body" pad15>
          <div class="layui-form form-label-w8" lay-filter="">
            <div class="layui-form-item">
              <label class="layui-form-label">搜索</label>
              <div class="layui-input-inline" style="width: 200px;">
                <input type="text" name="keyword" value="<?php echo (isset($params['keyword']) && ($params['keyword'] !== '')?$params['keyword']:''); ?>" placeholder="请输入唯一码、客户姓名或电话" class="layui-input">
              </div>
              <div class="layui-input-inline" style="width: 150px;">
                <select name="status">
                  <option value="" <?php echo empty($params['status']) ? 'selected' : ''; ?>>全部状态</option>
                  <option value="1" <?php echo $params['status']==1 ? 'selected'  :  ''; ?>>启用</option>
                  <option value="0" <?php echo $params['status']==0 ? 'selected'  :  ''; ?>>禁用</option>
                  <option value="2" <?php echo $params['status']==2 ? 'selected'  :  ''; ?>>已使用</option>
                </select>
              </div>
              <button class="layui-btn" lay-submit lay-filter="search">搜索</button>
            </div>
          </div>
          <table class="layui-hide" id="test-table-page" lay-filter="test-table-page"></table>
          <div id="test-table-page-toolbar" style="display: none;">
            <div class="layui-btn-container">
              <button class="layui-btn layui-btn-sm" lay-event="batchDelete">批量删除</button>
              <button class="layui-btn layui-btn-sm" lay-event="batchPrint">批量打印</button>
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
  <script type="text/html" id="barDemo">
    <a class="layui-btn layui-btn-xs" lay-event="detail">详情</a>
    <a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="edit">编辑</a>
    <a class="layui-btn layui-btn-xs layui-btn-warm" lay-event="print">打印</a>
    <a class="layui-btn layui-btn-xs {{d.status == 1 ? 'layui-btn-danger' : 'layui-btn-success'}}" lay-event="{{d.status == 1 ? 'disable' : 'enable'}}">{{d.status == 1 ? '禁用' : '启用'}}</a>
  </script>
  <script type="text/html" id="statusTpl">
    <span class="status-tag status-{{d.status == 1 ? 'enabled' : (d.status == 2 ? 'used' : 'disabled')}}">
      {{d.status == 1 ? '启用' : (d.status == 2 ? '已使用' : '禁用')}}
    </span>
  </script>
  <script>
    layui.use(['table', 'form'], function() {
      var table = layui.table;
      var form = layui.form;
      
      // 渲染表格
      table.render({
        elem: '#test-table-page',
        url: '<?php echo url("ProductQrcode/index"); ?>',
        toolbar: '#test-table-page-toolbar',
        defaultToolbar: ['filter', 'exports', 'print'],
        title: '一物一码列表',
        cols: [[
          {type: 'checkbox', fixed: 'left'},
          {field: 'id', title: 'ID', width: 80, fixed: 'left'},
          {field: 'code', title: '唯一码', width: 200},
          {field: 'qrcode_url', title: '二维码', width: 120, templet: function(d) {
            return d.qrcode_url ? '<img src="' + d.qrcode_url + '" alt="二维码">' : '无';
          }},
          {field: 'product_info', title: '商品名称', templet: function(d) {
            return d.product_info ? d.product_info.name : '未知商品';
          }},
          {field: 'customer_name', title: '客户姓名', width: 120},
          {field: 'customer_phone', title: '客户电话', width: 120},
          {field: 'scan_count', title: '扫码次数', width: 100},
          {field: 'status', title: '状态', width: 100, templet: '#statusTpl'},
          {field: 'create_time', title: '创建时间', width: 160, templet: function(d) {
            return layui.util.toDateString(d.create_time * 1000, 'yyyy-MM-dd HH:mm:ss');
          }},
          {fixed: 'right', title: '操作', toolbar: '#barDemo', width: 220}
        ]],
        page: true,
        limit: 20,
        limits: [10, 20, 50, 100],
        text: {
          none: '暂无数据'
        }
      });
      
      // 搜索表单提交
      form.on('submit(search)', function(data) {
        table.reload('test-table-page', {
          where: data.field,
          page: {curr: 1}
        });
        return false;
      });
      
      // 工具栏事件
      table.on('toolbar(test-table-page)', function(obj) {
        var checkStatus = table.checkStatus(obj.config.id);
        switch(obj.event) {
          case 'batchDelete':
            var data = checkStatus.data;
            if (data.length === 0) {
              layer.msg('请选择要删除的数据', {icon: 2});
              return;
            }
            var ids = data.map(function(item) { return item.id; });
            layer.confirm('确定要删除选中的' + data.length + '条数据吗？', function(index) {
              // 批量删除逻辑
              layer.msg('批量删除功能开发中', {icon: 6});
              layer.close(index);
            });
            break;
          case 'batchPrint':
            var data = checkStatus.data;
            if (data.length === 0) {
              layer.msg('请选择要打印的数据', {icon: 2});
              return;
            }
            var ids = data.map(function(item) { return item.id; });
            // 跳转到批量打印页面并传递选中的ID
            window.location.href = '<?php echo url("ProductQrcode/batchPrint"); ?>?ids=' + ids.join(',');
            break;
        };
      });
      
      // 行工具事件
      table.on('tool(test-table-page)', function(obj) {
        var data = obj.data;
        switch(obj.event) {
          case 'detail':
            window.location.href = '<?php echo url("ProductQrcode/detail"); ?>?id=' + data.id;
            break;
          case 'edit':
            layer.msg('编辑功能开发中', {icon: 6});
            break;
          case 'print':
            layer.msg('打印功能开发中', {icon: 6});
            break;
          case 'enable':
          case 'disable':
            var status = obj.event === 'enable' ? 1 : 0;
            var msg = obj.event === 'enable' ? '启用' : '禁用';
            layer.confirm('确定要' + msg + '该二维码吗？', function(index) {
              $.ajax({
                url: '<?php echo url("ProductQrcode/updateStatus"); ?>',
                type: 'POST',
                data: {id: data.id, status: status},
                dataType: 'JSON',
                success: function(res) {
                  if (res.status == 1) {
                    layer.msg(msg + '成功', {icon: 1});
                    obj.update({status: status});
                  } else {
                    layer.msg(msg + '失败：' + res.msg, {icon: 2});
                  }
                },
                error: function() {
                  layer.msg('网络错误', {icon: 2});
                }
              });
              layer.close(index);
            });
            break;
        }
      });
    });
  </script>
</body>
</html>