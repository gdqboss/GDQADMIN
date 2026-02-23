<?php /*a:3:{s:61:"/www/wwwroot/gdqshop.cn/app/view/barcode_inventory/index.html";i:1769163069;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;}*/ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="renderer" content="webkit">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title><?php echo $cfg['title']; ?> - <?php echo $menuname; ?></title>
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
        #proname {
            border-radius: 4px;
            border: 1px solid #e6e6e6;
            transition: all 0.3s ease;
        }
        
        #proname:focus {
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
        .btn {
            border-radius: 3px;
            font-size: 12px;
            padding: 4px 12px;
            margin-right: 5px;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }
        
        /* 美化商品图片 */
        .layui-table img {
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .layui-table img:hover {
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        /* 美化规格库存显示 */
        .spec-stock {
            background-color: #f0f9eb;
            color: #67c23a;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
        }
        
        /* 添加加载动画 */
        .layui-table th, .layui-table td {
            transition: all 0.3s ease;
        }
        
        /* 美化响应式布局 */
        @media screen and (max-width: 768px) {
            .layui-card-header-right {
                float: none;
                margin-top: 10px;
            }
            
            .layui-btn {
                margin-bottom: 5px;
            }
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
        
        /* 美化库存数量显示 */
        .stock-high {
            color: #67c23a;
        }
        
        .stock-medium {
            color: #e6a23c;
        }
        
        .stock-low {
            color: #f56c6c;
        }
        
        /* 添加动画效果 */
        .animated {
            animation-duration: 0.5s;
            animation-fill-mode: both;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        .fadeIn {
            animation-name: fadeIn;
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
                        <h5><?php echo $menuname; ?></h5>
                        <div class="layui-card-header-right">
                            <a href="<?php echo url('BarcodeInventory/edit'); ?>" class="layui-btn layui-btn-primary layui-btn-xs">
                                <i class="fa fa-plus"></i> <?php echo t('添加商品'); ?>
                            </a>
                            <a href="<?php echo url('BarcodeInventory/outbound'); ?>" class="layui-btn layui-btn-success layui-btn-xs">
                                <i class="fa fa-minus"></i> <?php echo t('出库'); ?>
                            </a>
                        </div>
                    </div>
                    <div class="layui-card-body">
                        <!-- 搜索框 -->
                        <div class="layui-form-item">
                            <div class="layui-input-inline" style="width: 300px;">
                                <input type="text" id="proname" placeholder="<?php echo t('请输入商品名称'); ?>" class="layui-input">
                            </div>
                            <div class="layui-input-inline">
                                <button type="button" id="searchBtn" class="layui-btn layui-btn-primary"><?php echo t('搜索'); ?></button>
                            </div>
                        </div>
                        
                        <!-- 库存列表 -->
                        <div class="table-responsive">
                            <table class="layui-hide" id="inventoryTable" lay-filter="inventoryTable"></table>
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
        layui.use(['table', 'layer'], function() {
            var table = layui.table;
            var layer = layui.layer;
            
            // 渲染表格
        var tableIns = table.render({
            elem: '#inventoryTable',
            url: '<?php echo url("BarcodeInventory/index"); ?>',
            page: true,
            limit: 10,
            limits: [10, 20, 30, 50],
            cols: [[
                {field: 'id', title: 'ID', width: 80, sort: true},
                {field: 'name', title: '<?php echo t('商品名称'); ?>', width: 250, templet: function(d) {
                    var html = d.name;
                    if (d.english_name) {
                        html += '<br><span style="font-size: 12px; color: #999;">' + d.english_name + '</span>';
                    }
                    return html;
                }},
                {field: 'pic', title: '<?php echo t('商品图片'); ?>', width: 100, templet: function(d) {
                    return d.pic ? '<img src="' + d.pic + '" width="50" height="50">' : '<span style="color: #999;"><?php echo t('无图片'); ?></span>';
                }},
                {field: 'stock', title: '<?php echo t('总库存'); ?>', width: 100, sort: true, templet: function(d) {
                    // 根据库存数量显示不同颜色
                    var stockClass = '';
                    if (d.stock > 100) {
                        stockClass = 'stock-high';
                    } else if (d.stock > 10) {
                        stockClass = 'stock-medium';
                    } else {
                        stockClass = 'stock-low';
                    }
                    return '<span class="' + stockClass + ' font-weight-bold">' + d.stock + '</span>';
                }},
                {field: 'supplier_id', title: '<?php echo t('供货商'); ?>', width: 200, templet: function(d) {
                    return d.supplier_id + ' - ' + d.supplier_name;
                }},
                {field: 'guige', title: '<?php echo t('规格库存'); ?>', width: 300, templet: function(d) {
                    var html = '';
                    if (d.guige && d.guige.length > 0) {
                        $.each(d.guige, function(index, item) {
                            // 根据规格库存显示不同颜色
                            var stockClass = '';
                            if (item.stock > 50) {
                                stockClass = 'stock-high';
                            } else if (item.stock > 5) {
                                stockClass = 'stock-medium';
                            } else {
                                stockClass = 'stock-low';
                            }
                            html += '<span class="spec-item" style="margin-right: 10px; display: inline-block; margin-bottom: 5px;">' + 
                                    '<strong>' + item.name + ':</strong> ' + 
                                    '<span class="spec-stock ' + stockClass + '">' + item.stock + '</span>' + 
                                    '</span>';
                        });
                    } else {
                        html = '<span style="color: #999;"><?php echo t('无规格'); ?></span>';
                    }
                    return html;
                }},
                {title: '<?php echo t('操作'); ?>', width: 200, align: 'center', templet: function(d) {
                    return '<button class="layui-btn layui-btn-xs layui-btn-normal" onclick="window.location.href=\'/?s=/BarcodeInventory/edit&id=' + d.id + '\'">' +
                           '<i class="fa fa-edit"></i> <?php echo t('编辑'); ?></button> ' +
                           '<button class="layui-btn layui-btn-xs layui-btn-success" onclick="window.location.href=\'/?s=/BarcodeInventory/outbound&proid=' + d.id + '\'">' +
                           '<i class="fa fa-minus"></i> <?php echo t('出库'); ?></button> ' +
                           '<button class="layui-btn layui-btn-xs layui-btn-danger" onclick="delProduct(' + d.id + ')">' +
                           '<i class="fa fa-trash"></i> <?php echo t('删除'); ?></button>';
                }},
            ]],
            done: function(res, curr, count) {
                // 表格渲染完成后的回调
                console.log('表格渲染完成', res);
            }
        });
            
            // 搜索功能
            $('#searchBtn').click(function() {
                var proname = $('#proname').val();
                tableIns.reload({
                    where: {
                        proname: proname
                    },
                    page: {
                        curr: 1 // 重新从第1页开始
                    }
                });
            });
            
            // 回车键搜索
            $('#proname').keypress(function(e) {
                if (e.which == 13) {
                    $('#searchBtn').click();
                }
            });
        });
        
        // 删除商品
        function delProduct(id) {
            layer.confirm('<?php echo t('确定要删除该商品吗？'); ?>', {
                btn: ['<?php echo t('确定'); ?>','<?php echo t('取消'); ?>']
            }, function() {
                $.ajax({
                    url: '<?php echo url('BarcodeInventory/del_product'); ?>',
                    type: 'POST',
                    data: {id: id},
                    dataType: 'json',
                    success: function(res) {
                        if (res.status == 1) {
                            layer.msg(res.msg, {icon: 1, time: 1500}, function() {
                                // 刷新表格
                                layui.table.reload('inventoryTable');
                            });
                        } else {
                            layer.msg(res.msg, {icon: 2});
                        }
                    },
                    error: function() {
                        layer.msg('<?php echo t('网络错误，请稍后重试'); ?>', {icon: 2});
                    }
                });
            });
        }
    </script>
</body>
</html>