<?php /*a:3:{s:62:"/www/wwwroot/gdqshop.cn/app/view/barcode_inventory/report.html";i:1768704632;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;}*/ ?>
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
        .report-stats {
            margin-bottom: 20px;
        }
        .report-stats .layui-col-md3 {
            padding: 15px;
        }
        .stat-card {
            background-color: #f5f7fa;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
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
        .chart-container {
            height: 400px;
            margin: 20px 0;
        }
        .layui-tab-content {
            padding: 20px 0;
        }
        .report-toolbar {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="layui-fluid">
        <div class="layui-row layui-col-space15">
            <div class="layui-card layui-col-md12">
                <div class="layui-card-header">
                    <i class="fa fa-bar-chart"></i> <?php echo $title; ?>
                    <div class="layui-card-header-tool">
                        <button class="layui-btn layui-btn-sm layui-btn-primary" id="exportBtn">
                            <i class="fa fa-download"></i> <?php echo t('导出报表'); ?>
                        </button>
                    </div>
                </div>
                <div class="layui-card-body" pad15>
                    <!-- 报表工具栏 -->
                    <div class="report-toolbar layui-form">
                        <div class="layui-form-item">
                            <div class="layui-inline">
                                <label class="layui-form-label"><?php echo t('报表类型'); ?>：</label>
                                <div class="layui-input-inline" style="width: 150px;">
                                    <select name="reportType" id="reportType" lay-filter="reportType" class="layui-select">
                                        <option value="summary"><?php echo t('库存汇总'); ?></option>
                                        <option value="inventory"><?php echo t('库存明细'); ?></option>
                                        <option value="movement"><?php echo t('库存变动'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="layui-inline">
                                <label class="layui-form-label"><?php echo t('开始日期'); ?>：</label>
                                <div class="layui-input-inline">
                                    <input type="date" name="startDate" id="startDate" class="layui-input">
                                </div>
                            </div>
                            <div class="layui-inline">
                                <label class="layui-form-label"><?php echo t('结束日期'); ?>：</label>
                                <div class="layui-input-inline">
                                    <input type="date" name="endDate" id="endDate" class="layui-input">
                                </div>
                            </div>
                            <div class="layui-inline">
                                <button class="layui-btn layui-btn-primary" id="searchBtn">
                                    <i class="fa fa-search"></i> <?php echo t('查询'); ?>
                                </button>
                                <button class="layui-btn layui-btn-primary" id="resetBtn">
                                    <i class="fa fa-refresh"></i> <?php echo t('重置'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 库存汇总统计 -->
                    <div id="summaryStats" class="report-stats layui-row">
                        <!-- 统计数据将通过JS动态生成 -->
                    </div>
                    
                    <!-- 图表区域 -->
                    <div id="chartContainer" class="chart-container"></div>
                    
                    <!-- 数据表格 -->
                    <table class="layui-hide" id="reportTable" lay-filter="reportTable"></table>
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
    
    <!-- ECharts -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    
    <script>
        layui.use(['form', 'table', 'layer', 'laydate'], function() {
            var form = layui.form;
            var table = layui.table;
            var layer = layui.layer;
            var laydate = layui.laydate;
            
            // 渲染表单
            form.render();
            
            // 初始化ECharts实例
            var myChart = echarts.init(document.getElementById('chartContainer'));
            
            // 当前报表类型
            var currentReportType = 'summary';
            
            // 渲染表格
            var tableIns = table.render({
                elem: '#reportTable',
                url: '<?php echo url("BarcodeInventory/report"); ?>',
                method: 'POST',
                where: {
                    type: currentReportType
                },
                page: true,
                limit: 15,
                limits: [15, 30, 50, 100],
                cols: [[
                    // 默认显示汇总表格，根据报表类型动态切换
                    {field: 'product_name', title: '<?php echo t('商品名称'); ?>', minWidth: 200},
                    {field: 'guige_name', title: '<?php echo t('规格'); ?>', minWidth: 150},
                    {field: 'stock', title: '<?php echo t('库存数量'); ?>', width: 120, sort: true},
                    {field: 'price', title: '<?php echo t('单价'); ?>', width: 100, templet: function(d) { return d.price.toFixed(2); }},
                    {field: 'total_amount', title: '<?php echo t('库存金额'); ?>', width: 120, templet: function(d) { return (d.stock * d.price).toFixed(2); }, sort: true},
                    {field: 'warn_stock', title: '<?php echo t('预警库存'); ?>', width: 120}
                ]],
                done: function(res, curr, count) {
                    // 根据当前报表类型动态调整表格列
                    if (currentReportType === 'movement') {
                        tableIns.reload({
                            cols: [[
                                {field: 'product_name', title: '<?php echo t('商品名称'); ?>', minWidth: 200},
                                {field: 'guige_name', title: '<?php echo t('规格'); ?>', minWidth: 150},
                                {field: 'type', title: '<?php echo t('类型'); ?>', width: 100, templet: function(d) { return d.type == 1 ? '<?php echo t('入库'); ?>' : '<?php echo t('出库'); ?>'; }},
                                {field: 'num', title: '<?php echo t('数量'); ?>', width: 100},
                                {field: 'price', title: '<?php echo t('单价'); ?>', width: 100, templet: function(d) { return d.price.toFixed(2); }},
                                {field: 'createtime', title: '<?php echo t('时间'); ?>', width: 180, templet: function(d) { return new Date(d.createtime * 1000).toLocaleString(); }},
                                {field: 'remark', title: '<?php echo t('备注'); ?>', minWidth: 150}
                            ]]
                        });
                    }
                }
            });
            
            // 初始化汇总数据
            loadSummaryData();
            
            // 加载库存汇总数据
            function loadSummaryData() {
                $.ajax({
                    url: '<?php echo url("BarcodeInventory/report"); ?>',
                    type: 'POST',
                    data: {
                        type: 'summary'
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status == 1) {
                            renderSummaryStats(res.data);
                            renderInventoryChart(res.data);
                        } else {
                            layer.msg(res.msg, {icon: 2});
                        }
                    }
                });
            }
            
            // 渲染库存汇总统计
            function renderSummaryStats(data) {
                var html = '';
                html += '<div class="layui-col-md3">';
                html += '<div class="stat-card">';
                html += '<div class="stat-value">' + data.total_products + '</div>';
                html += '<div class="stat-label"><?php echo t('总商品数'); ?></div>';
                html += '</div>';
                html += '</div>';
                
                html += '<div class="layui-col-md3">';
                html += '<div class="stat-card">';
                html += '<div class="stat-value">' + data.total_guiges + '</div>';
                html += '<div class="stat-label"><?php echo t('总规格数'); ?></div>';
                html += '</div>';
                html += '</div>';
                
                html += '<div class="layui-col-md3">';
                html += '<div class="stat-card">';
                html += '<div class="stat-value">' + data.total_stock + '</div>';
                html += '<div class="stat-label"><?php echo t('总库存数量'); ?></div>';
                html += '</div>';
                html += '</div>';
                
                html += '<div class="layui-col-md3">';
                html += '<div class="stat-card">';
                html += '<div class="stat-value">¥' + data.total_amount + '</div>';
                html += '<div class="stat-label"><?php echo t('总库存金额'); ?></div>';
                html += '</div>';
                html += '</div>';
                
                html += '<div class="layui-col-md3">';
                html += '<div class="stat-card">';
                html += '<div class="stat-value" style="color: #f5222d;">' + data.warning_stock + '</div>';
                html += '<div class="stat-label"><?php echo t('库存预警数'); ?></div>';
                html += '</div>';
                html += '</div>';
                
                $('#summaryStats').html(html);
            }
            
            // 渲染库存图表
            function renderInventoryChart(data) {
                var option = {
                    title: {
                        text: '<?php echo t('库存概况'); ?>',
                        left: 'center'
                    },
                    tooltip: {
                        trigger: 'item',
                        formatter: '{b}: {c} ({d}%)'
                    },
                    legend: {
                        orient: 'vertical',
                        left: 'left',
                        data: ['<?php echo t('正常库存'); ?>', '<?php echo t('库存预警'); ?>']
                    },
                    series: [
                        {
                            name: '<?php echo t('库存状态'); ?>',
                            type: 'pie',
                            radius: '50%',
                            data: [
                                {
                                    value: data.total_guiges - data.warning_stock,
                                    name: '<?php echo t('正常库存'); ?>',
                                    itemStyle: { color: '#52c41a' }
                                },
                                {
                                    value: data.warning_stock,
                                    name: '<?php echo t('库存预警'); ?>',
                                    itemStyle: { color: '#f5222d' }
                                }
                            ],
                            emphasis: {
                                itemStyle: {
                                    shadowBlur: 10,
                                    shadowOffsetX: 0,
                                    shadowColor: 'rgba(0, 0, 0, 0.5)'
                                }
                            }
                        }
                    ]
                };
                
                myChart.setOption(option);
            }
            
            // 监听报表类型切换
            form.on('select(reportType)', function(data) {
                currentReportType = data.value;
                
                if (currentReportType === 'summary') {
                    // 显示汇总统计和图表
                    $('#summaryStats').show();
                    $('#chartContainer').show();
                    loadSummaryData();
                } else {
                    // 隐藏汇总统计和图表
                    $('#summaryStats').hide();
                    $('#chartContainer').hide();
                }
                
                // 重新加载表格数据
                tableIns.reload({
                    where: {
                        type: currentReportType,
                        start_date: $('#startDate').val(),
                        end_date: $('#endDate').val()
                    },
                    page: {
                        curr: 1
                    }
                });
            });
            
            // 搜索
            $('#searchBtn').click(function() {
                var startDate = $('#startDate').val();
                var endDate = $('#endDate').val();
                
                if (currentReportType === 'summary') {
                    // 汇总报表不需要日期
                    loadSummaryData();
                } else {
                    // 重新加载表格数据
                    tableIns.reload({
                        where: {
                            type: currentReportType,
                            start_date: startDate,
                            end_date: endDate
                        },
                        page: {
                            curr: 1
                        }
                    });
                }
            });
            
            // 重置
            $('#resetBtn').click(function() {
                $('#startDate').val('');
                $('#endDate').val('');
                $('#reportType').val('summary');
                form.render('select');
                
                currentReportType = 'summary';
                $('#summaryStats').show();
                $('#chartContainer').show();
                
                // 重新加载汇总数据和表格
                loadSummaryData();
                tableIns.reload({
                    where: {
                        type: currentReportType,
                        start_date: '',
                        end_date: ''
                    },
                    page: {
                        curr: 1
                    }
                });
            });
            
            // 导出报表
            $('#exportBtn').click(function() {
                var startDate = $('#startDate').val();
                var endDate = $('#endDate').val();
                
                layer.confirm('<?php echo t('确定要导出当前报表吗？'); ?>', {
                    btn: ['<?php echo t('确定'); ?>', '<?php echo t('取消'); ?>']
                }, function() {
                    // 这里可以添加导出逻辑
                    layer.msg('<?php echo t('导出功能开发中...'); ?>', {icon: 1});
                });
            });
            
            // 监听窗口大小变化，调整图表大小
            window.addEventListener('resize', function() {
                myChart.resize();
            });
        });
    </script>
</body>
</html>