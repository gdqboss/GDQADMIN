<?php /*a:3:{s:68:"/www/wwwroot/gdqshop.cn/app/view/barcode_inventory/print_report.html";i:1769013205;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;}*/ ?>
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
        /* 全局样式 */
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
        }
        
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
            margin-right: 10px;
        }
        
        .layui-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }
        
        /* 美化表单元素 */
        .layui-form-item {
            margin-bottom: 15px;
        }
        
        .layui-form-label {
            font-weight: bold;
        }
        
        .layui-select,
        .layui-input {
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .layui-select:focus,
        .layui-input:focus {
            border-color: #1E9FFF;
            box-shadow: 0 0 0 2px rgba(30, 159, 255, 0.2);
        }
        
        /* 打印样式 */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                background-color: white;
            }
            .print-container {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .print-title {
                text-align: center;
                font-size: 18px;
                font-weight: bold;
                margin: 20px 0;
                color: #333;
            }
            .print-info {
                text-align: center;
                margin: 10px 0;
                color: #666;
                font-size: 12px;
            }
            .print-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                font-size: 12px;
            }
            .print-table th {
                background-color: #f2f2f2;
                border: 1px solid #ddd;
                padding: 8px;
                text-align: center;
                font-weight: bold;
                color: #333;
            }
            .print-table td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: center;
                color: #666;
            }
            .print-summary {
                margin: 20px 0;
                text-align: right;
                font-weight: bold;
                color: #333;
            }
            .print-table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .print-table tr:hover {
                background-color: #f5f5f5;
            }
        }
        
        /* 页面样式 */
        .print-btn-container {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 4px;
        }
        
        .report-stats {
            margin-bottom: 20px;
        }
        
        .report-stats .layui-col-md3 {
            padding: 15px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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
            color: #1E9FFF;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        /* 美化统计卡片不同颜色 */
        .stat-card:nth-child(1) .stat-value {
            color: #67c23a;
        }
        
        .stat-card:nth-child(2) .stat-value {
            color: #e6a23c;
        }
        
        .stat-card:nth-child(3) .stat-value {
            color: #f56c6c;
        }
        
        /* 美化打印容器 */
        .print-container {
            background-color: white;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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
        
        /* 美化搜索筛选区域 */
        .layui-form {
            background-color: white;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* 美化导出按钮 */
        .layui-btn-primary {
            border-color: #dcdfe6;
            color: #606266;
        }
        
        /* 响应式设计 */
        @media screen and (max-width: 768px) {
            .layui-form-item {
                display: block;
            }
            
            .layui-inline {
                display: block;
                margin-bottom: 10px;
            }
            
            .layui-input-inline {
                display: block;
                width: 100% !important;
            }
        }
    </style>
</head>
<body>
    <div class="layui-fluid">
        <div class="layui-row layui-col-space15">
            <div class="layui-card layui-col-md12">
                <div class="layui-card-header">
                    <i class="fa fa-print"></i> <?php echo $title; ?>
                </div>
                <div class="layui-card-body" pad15>
                    <!-- 打印按钮 -->
                    <div class="layui-row no-print">
                        <div class="layui-col-md12">
                            <div class="print-btn-container">
                                <button type="button" class="layui-btn layui-btn-primary" id="printBtn">
                                    <i class="fa fa-print"></i> <?php echo t('打印报表'); ?>
                                </button>
                                <button type="button" class="layui-btn layui-btn-primary" id="exportBtn">
                                    <i class="fa fa-download"></i> <?php echo t('导出报表'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 搜索筛选区域 -->
                    <div class="layui-form no-print" style="margin-bottom: 15px;">
                        <div class="layui-form-item">
                            <div class="layui-inline">
                                <label class="layui-form-label"><?php echo t('报表类型'); ?>：</label>
                                <div class="layui-input-inline" style="width: 120px;">
                                    <select name="type" id="type" class="layui-select">
                                        <option value="outbound" <?php if($type == 'outbound'): ?>selected<?php endif; ?>><?php echo t('出库报表'); ?></option>
                                        <option value="in" <?php if($type == 'in'): ?>selected<?php endif; ?>><?php echo t('入库报表'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="layui-inline">
                                <label class="layui-form-label"><?php echo t('商品'); ?>：</label>
                                <div class="layui-input-inline" style="width: 200px;">
                                    <select name="proid" id="proid" class="layui-select">
                                        <option value=""><?php echo t('全部商品'); ?></option>
                                        <?php if(is_array($products) || $products instanceof \think\Collection || $products instanceof \think\Paginator): $i = 0; $__LIST__ = $products;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$product): $mod = ($i % 2 );++$i;?>
                                        <option value="<?php echo $product['id']; ?>" <?php if($proid == $product['id']): ?>selected<?php endif; ?>><?php echo $product['name']; ?></option>
                                        <?php endforeach; endif; else: echo "" ;endif; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="layui-inline">
                                <label class="layui-form-label"><?php echo t('开始日期'); ?>：</label>
                                <div class="layui-input-inline">
                                    <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>" class="layui-input">
                                </div>
                            </div>
                            <div class="layui-inline">
                                <label class="layui-form-label"><?php echo t('结束日期'); ?>：</label>
                                <div class="layui-input-inline">
                                    <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>" class="layui-input">
                                </div>
                            </div>
                            <div class="layui-inline">
                                <button type="button" class="layui-btn layui-btn-primary" id="searchBtn">
                                    <i class="fa fa-search"></i> <?php echo t('查询'); ?>
                                </button>
                                <button type="button" class="layui-btn layui-btn-primary" id="resetBtn">
                                    <i class="fa fa-refresh"></i> <?php echo t('重置'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 统计数据 -->
                    <div class="report-stats layui-row no-print">
                        <div class="layui-col-md3">
                            <div class="stat-card">
                                <div class="stat-value" id="totalCount">0</div>
                                <div class="stat-label"><?php echo t('总记录数'); ?></div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="stat-card">
                                <div class="stat-value" id="totalNum">0</div>
                                <div class="stat-label"><?php echo t('总数量'); ?></div>
                            </div>
                        </div>
                        <div class="layui-col-md3">
                            <div class="stat-card">
                                <div class="stat-value" id="totalAmount">0</div>
                                <div class="stat-label"><?php echo t('总金额'); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 打印容器 -->
                    <div class="print-container">
                        <!-- 打印标题 -->
                        <div class="print-title">
                            <?php echo $title; ?> - <?php if($type == 'outbound'): ?><?php echo t('出库报表'); else: ?><?php echo t('入库报表'); ?><?php endif; ?>
                        </div>
                        
                        <!-- 打印信息 -->
                        <div class="print-info">
                            <?php echo t('打印时间'); ?>：<?php echo date('Y-m-d H:i:s'); ?><br>
                            <?php echo t('统计时间'); ?>：
                            <?php if($start_date && $end_date): ?>
                                <?php echo $start_date; ?> - <?php echo $end_date; else: ?>
                                <?php echo t('全部'); ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- 报表数据表格 -->
                        <table class="layui-hide" id="reportTable" lay-filter="reportTable"></table>
                        
                        <!-- 打印用表格 -->
                        <table class="print-table" id="printTable" style="display: none;">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th><?php echo t('单据号'); ?></th>
                                    <th><?php echo t('商品名称'); ?></th>
                                    <th><?php echo t('商品规格'); ?></th>
                                    <th><?php echo t('数量'); ?></th>
                                    <th><?php echo t('类型'); ?></th>
                                    <th><?php echo t('单价'); ?></th>
                                    <th><?php echo t('金额'); ?></th>
                                    <th><?php echo t('日期'); ?></th>
                                    <th><?php echo t('备注'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="printTableBody">
                                <!-- 数据将通过JS动态填充 -->
                            </tbody>
                        </table>
                        
                        <!-- 打印汇总信息 -->
                        <div class="print-summary">
                            <?php echo t('总记录数'); ?>：<span id="printTotalCount">0</span> &nbsp;&nbsp;
                            <?php echo t('总数量'); ?>：<span id="printTotalNum">0</span> &nbsp;&nbsp;
                            <?php echo t('总金额'); ?>：<span id="printTotalAmount">0</span>
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
                elem: '#reportTable',
                url: '<?php echo url("BarcodeInventory/print_report"); ?>',
                method: 'POST',
                where: {
                    type: '<?php echo $type; ?>',
                    start_date: '<?php echo $start_date; ?>',
                    end_date: '<?php echo $end_date; ?>',
                    proid: <?php echo $proid; ?>
                },
                page: true,
                limit: 20,
                limits: [10, 20, 50, 100],
                cols: [[
                    {field: 'id', title: 'ID', width: 80, sort: true},
                    {field: 'ordernum', title: '<?php echo t('单据号'); ?>', width: 200},
                    {field: 'pro_name', title: '<?php echo t('商品名称'); ?>', minWidth: 150},
                    {field: 'gg_name', title: '<?php echo t('商品规格'); ?>', minWidth: 150},
                    {field: 'num', title: '<?php echo t('数量'); ?>', width: 100, sort: true},
                    {field: 'type_text', title: '<?php echo t('类型'); ?>', width: 100},
                    {field: 'price', title: '<?php echo t('单价'); ?>', width: 100, templet: function(d) { return d.price.toFixed(2); }},
                    {field: 'total_amount', title: '<?php echo t('金额'); ?>', width: 120, templet: function(d) { return d.total_amount.toFixed(2); }, sort: true},
                    {field: 'createtime', title: '<?php echo t('日期'); ?>', width: 180, sort: true},
                    {field: 'remark', title: '<?php echo t('备注'); ?>', minWidth: 150}
                ]],
                done: function(res, curr, count) {
                    // 更新统计数据
                    updateStats(res.data);
                }
            });
            
            // 更新统计数据
            function updateStats(data) {
                var totalCount = data.length;
                var totalNum = 0;
                var totalAmount = 0;
                
                // 计算总数量和总金额
                $.each(data, function(index, item) {
                    totalNum += parseInt(item.num);
                    totalAmount += parseFloat(item.total_amount);
                });
                
                // 更新页面统计
                $('#totalCount').text(totalCount);
                $('#totalNum').text(totalNum);
                $('#totalAmount').text(totalAmount.toFixed(2));
            }
            
            // 搜索
            $('#searchBtn').click(function() {
                var type = $('#type').val();
                var proid = $('#proid').val();
                var start_date = $('#start_date').val();
                var end_date = $('#end_date').val();
                
                tableIns.reload({
                    where: {
                        type: type,
                        proid: proid,
                        start_date: start_date,
                        end_date: end_date
                    },
                    page: {
                        curr: 1
                    }
                });
            });
            
            // 重置
            $('#resetBtn').click(function() {
                $('#type').val('outbound');
                $('#proid').val('');
                $('#start_date').val('');
                $('#end_date').val('');
                form.render('select');
                tableIns.reload({
                    where: {
                        type: 'outbound',
                        proid: 0,
                        start_date: '',
                        end_date: ''
                    },
                    page: {
                        curr: 1
                    }
                });
            });
            
            // 打印报表
            $('#printBtn').click(function() {
                // 获取当前页面的所有数据
                $.ajax({
                    url: '<?php echo url("BarcodeInventory/print_report"); ?>',
                    type: 'POST',
                    data: {
                        type: $('#type').val(),
                        proid: $('#proid').val(),
                        start_date: $('#start_date').val(),
                        end_date: $('#end_date').val()
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.code == 0) {
                            generatePrintTable(res.data);
                            window.print();
                        } else {
                            layer.msg(res.msg, {icon: 2});
                        }
                    },
                    error: function() {
                        layer.msg('<?php echo t('网络错误，请稍后重试'); ?>', {icon: 2});
                    }
                });
            });
            
            // 生成打印表格
            function generatePrintTable(data) {
                var html = '';
                var totalCount = data.length;
                var totalNum = 0;
                var totalAmount = 0;
                
                // 生成表格行
                $.each(data, function(index, item) {
                    html += '<tr>';
                    html += '<td>' + item.id + '</td>';
                    html += '<td>' + item.ordernum + '</td>';
                    html += '<td>' + item.pro_name + '</td>';
                    html += '<td>' + item.gg_name + '</td>';
                    html += '<td>' + item.num + '</td>';
                    html += '<td>' + item.type_text + '</td>';
                    html += '<td>' + item.price.toFixed(2) + '</td>';
                    html += '<td>' + item.total_amount.toFixed(2) + '</td>';
                    html += '<td>' + item.createtime + '</td>';
                    html += '<td>' + (item.remark || '') + '</td>';
                    html += '</tr>';
                    
                    totalNum += parseInt(item.num);
                    totalAmount += parseFloat(item.total_amount);
                });
                
                // 更新打印表格
                $('#printTableBody').html(html);
                
                // 更新打印汇总信息
                $('#printTotalCount').text(totalCount);
                $('#printTotalNum').text(totalNum);
                $('#printTotalAmount').text(totalAmount.toFixed(2));
            }
            
            // 导出报表
            $('#exportBtn').click(function() {
                layer.msg('<?php echo t('导出功能开发中...'); ?>', {icon: 1});
            });
        });
    </script>
</body>
</html>