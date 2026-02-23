<?php /*a:3:{s:61:"/www/wwwroot/gdqshop.cn/app/view/barcode_inventory/check.html";i:1769163529;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;}*/ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="renderer" content="webkit">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title><?php echo $cfg['title']; ?> - <?php echo $title; ?></title>
    <link rel="stylesheet" type="text/css" href="/static/admin/layui/css/layui.css?v=20200519" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/layui/css/modules/formSelects-v4.css?v=20200516" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/css/admin.css?v=202406" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/css/font-awesome.min.css?v=20200516" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/webuploader/webuploader.css?v=<?php echo time(); ?>" media="all">
<link rel="stylesheet" type="text/css" href="/static/admin/css/designer.css?v=202410" media="all">
<link rel="stylesheet" type="text/css" href="/static/fonts/iconfont.css?v=20201218" media="all">
    <style>
        /* 美化卡片样式 */
        .layui-card {
            border-radius: 8px;
            box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .layui-card:hover {
            box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 0.15);
        }
        
        /* 美化卡片标题 */
        .layui-card-header {
            background-color: #fafafa;
            border-bottom: 1px solid #e8e8e8;
            border-radius: 8px 8px 0 0;
            font-weight: bold;
            font-size: 16px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* 美化卡片内容 */
        .layui-card-body {
            padding: 20px;
        }
        
        /* 美化按钮样式 */
        .layui-btn {
            border-radius: 4px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .layui-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }
        
        /* 美化搜索框 */
        .layui-input {
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .layui-input:focus {
            border-color: #1E9FFF;
            box-shadow: 0 0 0 2px rgba(30, 159, 255, 0.2);
        }
        
        /* 美化表格样式 */
        .layui-table {
            border-radius: 4px;
            overflow: hidden;
        }
        
        .layui-table th {
            background-color: #f5f7fa;
            font-weight: bold;
            color: #333;
        }
        
        .layui-table tbody tr {
            transition: all 0.3s ease;
        }
        
        .layui-table tbody tr:hover {
            background-color: #fafafa;
        }
        
        /* 美化操作按钮 */
        .layui-btn-xs {
            border-radius: 3px;
            font-size: 12px;
            padding: 4px 12px;
            margin-right: 5px;
            transition: all 0.3s ease;
        }
        
        .layui-btn-xs:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }
        
        /* 美化下拉选择框 */
        .layui-select {
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .layui-select:focus {
            border-color: #1E9FFF;
            box-shadow: 0 0 0 2px rgba(30, 159, 255, 0.2);
        }
        
        /* 美化响应式布局 */
        @media screen and (max-width: 768px) {
            .layui-card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .layui-card-header-tool {
                margin-top: 10px;
            }
            
            .layui-btn {
                margin-bottom: 5px;
            }
        }
        
        /* 添加加载动画 */
        .layui-table th, .layui-table td {
            transition: all 0.3s ease;
        }
        
        /* 美化统计卡片 */
        .stat-card {
            background-color: #f5f7fa;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body class="gray-bg">
    <!-- 页面内容 -->
    <div class="wrapper wrapper-content animated fadeInRight">
        <div class="row">
            <div class="col-sm-12">
                <div class="layui-card">
                    <div class="layui-card-header">
                        <h5><i class="fa fa-list"></i> <?php echo $title; ?></h5>
                        <div class="layui-card-header-tool">
                            <a href="<?php echo url('BarcodeInventory/create_check'); ?>" class="layui-btn layui-btn-normal layui-btn-sm">
                                <i class="fa fa-plus"></i> <?php echo t('创建盘点任务'); ?>
                            </a>
                        </div>
                    </div>
                    <div class="layui-card-body">
                        <!-- 搜索区域 -->
                        <form class="layui-form" style="margin-bottom: 15px; padding: 15px; background-color: #f8f9fa; border-radius: 4px; border: 1px solid #e9ecef;">
                            <div class="layui-form-item">
                                <div class="layui-inline">
                                    <label class="layui-form-label"><?php echo t('盘点名称'); ?>：</label>
                                    <div class="layui-input-inline" style="width: 200px;">
                                        <input type="text" name="check_name" id="check_name" placeholder="<?php echo t('请输入盘点名称'); ?>" class="layui-input">
                                    </div>
                                </div>
                                <div class="layui-inline">
                                    <label class="layui-form-label"><?php echo t('状态'); ?>：</label>
                                    <div class="layui-input-inline" style="width: 120px;">
                                        <select name="status" id="status" class="layui-select">
                                            <option value="-1"><?php echo t('全部'); ?></option>
                                            <option value="0"><?php echo t('未完成'); ?></option>
                                            <option value="1"><?php echo t('已完成'); ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="layui-inline">
                                    <button type="button" class="layui-btn layui-btn-primary" id="searchBtn">
                                        <i class="fa fa-search"></i> <?php echo t('搜索'); ?>
                                    </button>
                                    <button type="button" class="layui-btn layui-btn-primary" id="resetBtn">
                                        <i class="fa fa-refresh"></i> <?php echo t('重置'); ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <!-- 盘点记录列表 -->
                        <div class="table-responsive">
                            <table class="layui-hide" id="checkTable" lay-filter="checkTable"></table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 底部脚本 -->
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
        layui.use(['table', 'form', 'layer'], function() {
            var table = layui.table;
            var form = layui.form;
            var layer = layui.layer;
            
            // 渲染表单
            form.render();
            
            // 渲染表格
            var tableIns = table.render({
                elem: '#checkTable',
                url: '<?php echo url('BarcodeInventory/check'); ?>',
                toolbar: '#toolbarDemo',
                defaultToolbar: ['filter', 'exports', 'print'],
                page: true,
                limit: 10,
                limits: [10, 20, 30, 50],
                loading: true,
                text: {
                    none: '<div style="padding: 30px 0; text-align: center;"><i class="fa fa-inbox" style="font-size: 50px; color: #ccc; margin-bottom: 10px; display: block;"></i><?php echo t('暂无数据'); ?><br><span style="color: #999;"><?php echo t('点击右上角按钮创建盘点任务'); ?></span></div>'
                },
                cols: [[
                    {type: 'checkbox', fixed: 'left', width: 50},
                    {field: 'id', title: 'ID', width: 80, sort: true, align: 'center'},
                    {field: 'check_sn', title: '<?php echo t('盘点单号'); ?>', width: 200, align: 'center', templet: function(d) {
                        return '<code style="background-color: #f0f0f0; padding: 2px 8px; border-radius: 3px;">' + d.check_sn + '</code>';
                    }},
                    {field: 'check_name', title: '<?php echo t('盘点名称'); ?>', minWidth: 180, align: 'center', style: 'font-weight: 500;'},
                    {field: 'check_time', title: '<?php echo t('盘点时间'); ?>', width: 180, align: 'center'},
                    {field: 'check_user', title: '<?php echo t('盘点人'); ?>', width: 100, align: 'center'},
                    {field: 'total_products', title: '<?php echo t('盘点商品数'); ?>', width: 120, sort: true, align: 'center', templet: function(d) {
                        return '<span class="layui-badge layui-bg-blue">' + d.total_products + '</span>';
                    }},
                    {field: 'total_diff', title: '<?php echo t('差异总数'); ?>', width: 120, sort: true, align: 'center', templet: function(d) {
                        var diff = d.total_diff > 0 ? '+' + d.total_diff : d.total_diff;
                        return '<span style="color: ' + (d.total_diff > 0 ? '#f56c6c' : (d.total_diff < 0 ? '#67c23a' : '#909399')) + ';">' + diff + '</span>';
                    }},
                    {field: 'total_diff_amount', title: '<?php echo t('差异金额'); ?>', width: 130, sort: true, align: 'center', templet: function(d) {
                        var amount = d.total_diff_amount.toFixed(2);
                        var prefix = parseFloat(amount) > 0 ? '+' : '';
                        return '<span style="color: ' + (d.total_diff_amount > 0 ? '#f56c6c' : (d.total_diff_amount < 0 ? '#67c23a' : '#909399')) + ';">¥' + prefix + amount + '</span>';
                    }},
                    {field: 'status', title: '<?php echo t('状态'); ?>', width: 100, align: 'center', templet: '#statusTpl'},
                    {field: 'remark', title: '<?php echo t('备注'); ?>', minWidth: 150, align: 'left', templet: function(d) {
                        return d.remark ? d.remark : '<span style="color: #999;"><?php echo t('无'); ?></span>';
                    }},
                    {field: 'createtime', title: '<?php echo t('创建时间'); ?>', width: 180, align: 'center'},
                    {title: '<?php echo t('操作'); ?>', width: 200, align: 'center', toolbar: '#checkBar'}
                ]],
                done: function(res, curr, count) {
                    // 表格渲染完成回调
                    console.log('Table data:', res);
                    if (count === 0) {
                        // 无数据时的提示
                        $('.layui-table-empty').html('<div style="padding: 30px 0; text-align: center;"><i class="fa fa-inbox" style="font-size: 50px; color: #ccc; margin-bottom: 10px; display: block;"></i><?php echo t('暂无数据'); ?><br><span style="color: #999;"><?php echo t('点击右上角按钮创建盘点任务'); ?></span></div>');
                    }
                }
            });
            
            // 工具栏
            table.on('toolbar(checkTable)', function(obj) {
                if (obj.event === 'add') {
                    window.location.href = '<?php echo url('BarcodeInventory/create_check'); ?>';
                }
            });
            
            // 搜索
            $('#searchBtn').click(function() {
                var check_name = $('#check_name').val();
                var status = $('#status').val();
                
                tableIns.reload({
                    where: {
                        check_name: check_name,
                        status: status
                    },
                    page: {
                        curr: 1
                    },
                    done: function(res) {
                        console.log('Search results:', res);
                        if (res.count === 0) {
                            layer.msg('<?php echo t('未找到匹配的数据'); ?>', {icon: 5});
                        }
                    }
                });
            });
            
            // 重置
            $('#resetBtn').click(function() {
                $('#check_name').val('');
                $('#status').val('-1');
                form.render('select');
                tableIns.reload({
                    where: {
                        check_name: '',
                        status: -1
                    },
                    page: {
                        curr: 1
                    }
                });
            });
            
            // 监听表格操作
            table.on('tool(checkTable)', function(obj) {
                var data = obj.data;
                var layEvent = obj.event;
                
                if (layEvent === 'edit') {
                    // 执行盘点
                    layer.confirm('<?php echo t('确定要执行这个盘点任务吗？'); ?>', {
                        icon: 3,
                        title: '<?php echo t('执行盘点'); ?>',
                        btn: ['<?php echo t('确定'); ?>', '<?php echo t('取消'); ?>']
                    }, function(index) {
                        layer.close(index);
                        window.location.href = '<?php echo url('BarcodeInventory/check_detail'); ?>?id=' + data.id;
                    });
                } else if (layEvent === 'analysis') {
                    // 查看分析
                    window.location.href = '<?php echo url('BarcodeInventory/check_analysis'); ?>?id=' + data.id;
                } else if (layEvent === 'detail') {
                    // 查看详情
                    window.location.href = '<?php echo url('BarcodeInventory/check_detail'); ?>?id=' + data.id;
                }
            });
            
            // 按回车键搜索
            $('#check_name').on('keydown', function(e) {
                if (e.keyCode === 13) {
                    $('#searchBtn').click();
                }
            });
        });
    </script>
    
    <!-- 自定义工具栏 -->
    <script type="text/html" id="toolbarDemo">
        <div class="layui-btn-container">
            <button class="layui-btn layui-btn-normal layui-btn-sm" lay-event="add">
                <i class="fa fa-plus"></i> <?php echo t('创建盘点任务'); ?>
            </button>
        </div>
    </script>
    
    <!-- 操作列 -->
    <script type="text/html" id="checkBar">
        {{#  if(d.status == 0){ }}
            <a class="layui-btn layui-btn-xs" lay-event="edit"><?php echo t('执行盘点'); ?></a>
        {{#  } else { }}
            <a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="analysis"><?php echo t('查看分析'); ?></a>
        {{#  } }}
        <a class="layui-btn layui-btn-xs layui-btn-warm" lay-event="detail"><?php echo t('查看详情'); ?></a>
    </script>
    
    <!-- 状态列 -->
    <script type="text/html" id="statusTpl">
        {{#  if(d.status == 1){ }}
            <span class="layui-badge layui-bg-green"><?php echo t('已完成'); ?></span>
        {{#  } else { }}
            <span class="layui-badge layui-bg-orange"><?php echo t('未完成'); ?></span>
        {{#  } }}
    </script>
</body>
</html>