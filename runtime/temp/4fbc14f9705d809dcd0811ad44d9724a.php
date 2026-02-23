<?php /*a:3:{s:63:"/www/wwwroot/gdqshop.cn/app/view/barcode_inventory/inbound.html";i:1768721409;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;}*/ ?>
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
        /* 美化表单样式 */
        .layui-form-item {
            margin-bottom: 20px;
        }
        
        /* 美化必填项标记 */
        .redstar {
            color: #ff5722;
            margin-right: 4px;
            font-weight: bold;
        }
        
        /* 美化只读输入框 */
        .layui-input:read-only {
            background-color: #f9f9f9;
            border-color: #e6e6e6;
            color: #666;
        }
        
        /* 美化价格和数量输入框 */
        #price, #num {
            width: 200px;
        }
        
        /* 美化库存显示 */
        #currentStock {
            width: 100px;
            background-color: #f0f9eb;
            border-color: #c2e7b0;
            color: #67c23a;
            font-weight: bold;
        }
        
        /* 预计库存样式 */
        #estimatedStock {
            width: 100px;
            background-color: #ecf5ff;
            border-color: #90caf9;
            color: #1989fa;
            font-weight: bold;
        }
        
        /* 美化提交按钮 */
        .layui-btn {
            border-radius: 4px;
            font-weight: bold;
        }
        
        /* 美化选择框 */
        .layui-select {
            width: 350px;
        }
        
        /* 美化验证错误提示 */
        .layui-form-danger {
            border-color: #ff5722 !important;
        }
        
        .layui-text-danger {
            color: #ff5722 !important;
        }
        
        /* 美化卡片样式 */
        .layui-card {
            border-radius: 8px;
            box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .layui-card-header {
            background-color: #fafafa;
            border-bottom: 1px solid #e8e8e8;
            border-radius: 8px 8px 0 0;
            font-weight: bold;
            font-size: 16px;
            padding: 15px;
        }
        
        /* 美化表单标签 */
        .layui-form-label {
            font-weight: bold;
            color: #333;
            width: 120px;
        }
        
        /* 美化表单内容 */
        .layui-card-body {
            padding: 25px;
        }
        
        /* 美化商品信息卡片 */
        .product-info-card {
            margin-top: 15px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 15px;
        }
        
        .product-info-row {
            margin-bottom: 10px;
            display: flex;
            flex-wrap: wrap;
        }
        
        .product-info-label {
            width: 100px;
            font-weight: bold;
            color: #666;
            margin-right: 10px;
        }
        
        .product-info-value {
            color: #333;
            margin-right: 20px;
        }
        
        /* 美化供应商信息显示 */
        .supplier-info {
            margin-top: 10px;
            padding: 10px;
            background-color: #e7f5ff;
            border-left: 4px solid #409eff;
            border-radius: 0 4px 4px 0;
        }
        
        /* 美化表单行间距 */
        .form-row {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        /* 美化合计信息 */
        .total-info {
            margin-top: 20px;
            padding: 15px;
            background-color: #f0f5ff;
            border-radius: 4px;
            border: 1px solid #e0ecff;
        }
        
        .total-info h4 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 16px;
        }
        
        .total-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .total-label {
            width: 120px;
            color: #666;
        }
        
        .total-value {
            color: #1989fa;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="layui-fluid">
        <div class="layui-row layui-col-space15">
            <div class="layui-card layui-col-md12">
                <div class="layui-card-header">
                    <i class="fa fa-plus"></i> <?php echo $title; ?>
                </div>
                <div class="layui-card-body" pad15>
                    <form class="layui-form form-label-w6" id="inboundForm" lay-filter="inboundForm">
                        <!-- 商品选择 -->
                        <div class="layui-form-item">
                            <label class="layui-form-label"><span class="redstar">*</span><?php echo t('商品名称'); ?>：</label>
                            <div class="layui-input-block">
                                <select class="layui-select" name="proid" id="proid" required>
                                    <option value=""><?php echo t('请选择商品'); ?></option>
                                    <?php if(is_array($products) || $products instanceof \think\Collection || $products instanceof \think\Paginator): $i = 0; $__LIST__ = $products;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$product): $mod = ($i % 2 );++$i;?>
                                    <option value="<?php echo $product['id']; ?>" data-english-name="<?php echo $product['english_name']; ?>" data-supplier-id="<?php echo $product['supplier_id']; ?>" data-supplier-name="<?php echo $product['supplier_name']; ?>"><?php echo $product['name']; if($product['english_name']): ?>(<?php echo $product['english_name']; ?>)<?php endif; ?></option>
                                    <?php endforeach; endif; else: echo "" ;endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- 商品规格选择 -->
                        <div class="layui-form-item">
                            <label class="layui-form-label"><?php echo t('商品规格'); ?>：</label>
                            <div class="layui-input-block">
                                <select class="layui-select" name="ggid" id="ggid">
                                    <option value=""><?php echo t('请先选择商品'); ?></option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- 商品详情信息 -->
                        <div class="product-info-card" id="productInfoCard" style="display: none;">
                            <div class="product-info-row">
                                <div class="product-info-label"><?php echo t('商品名称'); ?>：</div>
                                <div class="product-info-value" id="productName"></div>
                                <div class="product-info-label"><?php echo t('英文名称'); ?>：</div>
                                <div class="product-info-value" id="productEnglishName"></div>
                            </div>
                            <div class="product-info-row">
                                <div class="product-info-label"><?php echo t('供应商'); ?>：</div>
                                <div class="product-info-value" id="productSupplier"></div>
                            </div>
                            <div class="supplier-info" id="supplierInfo" style="display: none;"></div>
                        </div>
                        
                        <!-- 库存信息 -->
                        <div class="layui-form-item">
                            <label class="layui-form-label"><?php echo t('当前库存'); ?>：</label>
                            <div class="layui-input-block">
                                <input type="text" class="layui-input" id="currentStock" readonly>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label"><?php echo t('预计库存'); ?>：</label>
                            <div class="layui-input-block">
                                <input type="text" class="layui-input layui-form-danger" id="estimatedStock" readonly>
                            </div>
                        </div>
                        
                        <!-- 入库信息 -->
                        <div class="layui-form-item">
                            <label class="layui-form-label"><span class="redstar">*</span><?php echo t('入库数量'); ?>：</label>
                            <div class="layui-input-block">
                                <input type="number" class="layui-input" name="num" id="num" placeholder="<?php echo t('请输入入库数量'); ?>" required min="1">
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label"><span class="redstar">*</span><?php echo t('入库单价'); ?>：</label>
                            <div class="layui-input-block">
                                <input type="number" class="layui-input" name="price" id="price" placeholder="<?php echo t('请输入入库单价'); ?>" required min="0" step="0.01">
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label"><?php echo t('入库总价'); ?>：</label>
                            <div class="layui-input-block">
                                <input type="text" class="layui-input" id="totalPrice" readonly>
                            </div>
                        </div>
                        
                        <!-- 合计信息 -->
                        <div class="total-info" id="totalInfo" style="display: none;">
                            <h4><?php echo t('入库合计信息'); ?></h4>
                            <div class="total-row">
                                <div class="total-label"><?php echo t('当前库存'); ?>：</div>
                                <div class="total-value" id="totalCurrentStock"></div>
                            </div>
                            <div class="total-row">
                                <div class="total-label"><?php echo t('入库数量'); ?>：</div>
                                <div class="total-value" id="totalInboundNum"></div>
                            </div>
                            <div class="total-row">
                                <div class="total-label"><?php echo t('预计库存'); ?>：</div>
                                <div class="total-value" id="totalEstimatedStock"></div>
                            </div>
                            <div class="total-row">
                                <div class="total-label"><?php echo t('入库总价'); ?>：</div>
                                <div class="total-value" id="totalInboundPrice"></div>
                            </div>
                        </div>
                        
                        <!-- 提交按钮 -->
                        <div class="layui-form-item">
                            <div class="layui-input-block">
                                <button class="layui-btn" type="submit" lay-submit><i class="fa fa-check"></i> <?php echo t('确认入库'); ?></button>
                                <button class="layui-btn layui-btn-primary" type="reset"><i class="fa fa-refresh"></i> <?php echo t('重置'); ?></button>
                            </div>
                        </div>
                    </form>
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
        layui.use(['form', 'layer'], function() {
            var form = layui.form;
            var layer = layui.layer;
            
            // 前端缓存：商品规格信息
            var guigeCache = {};
            
            // 自定义验证规则
            form.verify({
                num: function(value) {
                    if (!/^\d+$/.test(value) || parseInt(value) <= 0) {
                        return '<?php echo t('请输入有效的入库数量'); ?>';
                    }
                },
                price: function(value) {
                    if (!/^\d+(\.\d{1,2})?$/.test(value) || parseFloat(value) < 0) {
                        return '<?php echo t('请输入有效的价格，最多两位小数'); ?>';
                    }
                }
            });
            
            // 渲染表单
            form.render();
            
            // 实时验证：入库数量
            $('#num').on('input propertychange', function() {
                var value = $(this).val();
                var parent = $(this).parent();
                if (value) {
                    if (!/^\d+$/.test(value) || parseInt(value) <= 0) {
                        $(this).addClass('layui-form-danger');
                        parent.find('.layui-form-mid').remove();
                        parent.append('<div class="layui-form-mid layui-word-aux layui-text-danger"><?php echo t('请输入有效的入库数量'); ?></div>');
                    } else {
                        $(this).removeClass('layui-form-danger');
                        parent.find('.layui-form-mid').remove();
                    }
                } else {
                    $(this).removeClass('layui-form-danger');
                    parent.find('.layui-form-mid').remove();
                }
                calculateTotal();
            });
            
            // 实时验证：入库单价
            $('#price').on('input propertychange', function() {
                var value = $(this).val();
                var parent = $(this).parent();
                if (value) {
                    if (!/^\d+(\.\d{1,2})?$/.test(value) || parseFloat(value) < 0) {
                        $(this).addClass('layui-form-danger');
                        parent.find('.layui-form-mid').remove();
                        parent.append('<div class="layui-form-mid layui-word-aux layui-text-danger"><?php echo t('请输入有效的价格，最多两位小数'); ?></div>');
                    } else {
                        $(this).removeClass('layui-form-danger');
                        parent.find('.layui-form-mid').remove();
                    }
                } else {
                    $(this).removeClass('layui-form-danger');
                    parent.find('.layui-form-mid').remove();
                }
                calculateTotal();
            });
            
            // 更新商品详情信息
            function updateProductInfo() {
                var selectedProduct = $('#proid option:selected');
                var productName = selectedProduct.text();
                var englishName = selectedProduct.data('english-name') || '';
                var supplierId = selectedProduct.data('supplier-id') || '';
                var supplierName = selectedProduct.data('supplier-name') || '';
                
                $('#productName').text(productName.split('(')[0]);
                $('#productEnglishName').text(englishName);
                
                if (supplierId && supplierName) {
                    $('#productSupplier').text(supplierId + ' - ' + supplierName);
                    $('#supplierInfo').html('<strong><?php echo t('供应商信息'); ?>：</strong>' + supplierName + ' (ID: ' + supplierId + ')');
                    $('#supplierInfo').show();
                } else {
                    $('#productSupplier').text('<?php echo t('无'); ?>');
                    $('#supplierInfo').hide();
                }
                
                // 显示商品信息卡片
                $('#productInfoCard').show();
            }
            
            // 商品选择变化时，加载对应的规格和更新商品信息
            form.on('select(proid)', function(data) {
                var proid = data.value;
                if (proid) {
                    // 更新商品详情信息
                    updateProductInfo();
                    
                    // 检查缓存
                    if (guigeCache[proid]) {
                        // 使用缓存数据
                        renderGuigeOptions(guigeCache[proid]);
                    } else {
                        // 缓存不存在，发送AJAX请求
                        $.ajax({
                            url: '<?php echo url("BarcodeInventory/get_guige"); ?>',
                            type: 'GET',
                            data: {proid: proid},
                            dataType: 'json',
                            success: function(res) {
                                if (res.status == 1) {
                                    // 存入缓存
                                    guigeCache[proid] = res.data;
                                    // 渲染规格选项
                                    renderGuigeOptions(res.data);
                                } else {
                                    layer.msg(res.msg, {icon: 2});
                                }
                            }
                        });
                    }
                } else {
                    $('#ggid').empty();
                    $('#ggid').append('<option value=""><?php echo t('请先选择商品'); ?></option>');
                    $('#currentStock').val('');
                    $('#price').val('');
                    $('#totalPrice').val('');
                    $('#estimatedStock').val('');
                    // 隐藏商品信息和合计信息
                    $('#productInfoCard').hide();
                    $('#totalInfo').hide();
                    // 重新渲染表单
                    form.render('select');
                    // 清除验证状态
                    $('#ggid, #price, #num').removeClass('layui-form-danger');
                    $('#ggid, #price, #num').parent().find('.layui-form-mid').remove();
                }
            });
            
            // 渲染规格选项
        function renderGuigeOptions(data) {
            $('#ggid').empty();
            
            // 如果没有规格数据，添加默认规格
            if (data.length === 0) {
                $('#ggid').append('<option value="default" data-stock="0" data-price="0"><?php echo t('默认规格'); ?> (<?php echo t('当前库存'); ?>: 0<?php echo t('件'); ?>)</option>');
            } else {
                $('#ggid').append('<option value=""><?php echo t('请选择规格'); ?></option>');
                $.each(data, function(index, item) {
                    $('#ggid').append('<option value="' + item.id + '" data-stock="' + item.stock + '" data-price="' + item.price + '">' + item.name + ' (<?php echo t('当前库存'); ?>: ' + item.stock + '<?php echo t('件'); ?>)</option>');
                });
            }
            
            // 重新渲染表单
            form.render('select');
            
            // 清除规格相关的验证状态
            $('#ggid').removeClass('layui-form-danger');
            $('#ggid').parent().find('.layui-form-mid').remove();
            
            // 如果只有一个选项（默认规格），自动选择
            if ($('#ggid option').length === 1) {
                $('#ggid').val('default');
                // 触发选择事件，更新库存和价格
                var stock = $('#ggid option:selected').data('stock');
                var price = $('#ggid option:selected').data('price');
                $('#currentStock').val(stock);
                $('#price').val(price);
                calculateTotal();
            }
        }
            
            // 规格选择变化时，显示当前库存
            form.on('select(ggid)', function(data) {
                var stock = $(this.elem).find('option:selected').data('stock');
                var price = $(this.elem).find('option:selected').data('price');
                $('#currentStock').val(stock);
                $('#price').val(price);
                // 清除规格验证状态
                $(this.elem).removeClass('layui-form-danger');
                $(this.elem).parent().find('.layui-form-mid').remove();
                calculateTotal();
            });
            
            // 数量或价格变化时，计算总价
            $('#num, #price').on('input', function() {
                calculateTotal();
            });
            
            // 计算总价、预计库存和更新合计信息
            function calculateTotal() {
                var num = parseFloat($('#num').val()) || 0;
                var price = parseFloat($('#price').val()) || 0;
                var currentStock = parseFloat($('#currentStock').val()) || 0;
                var total = num * price;
                var estimatedStock = currentStock + num;
                
                // 更新基础信息
                $('#totalPrice').val(total.toFixed(2));
                $('#estimatedStock').val(estimatedStock);
                
                // 更新合计信息
                if ($('#proid').val() && $('#ggid').val() && num > 0) {
                    $('#totalCurrentStock').text(currentStock);
                    $('#totalInboundNum').text(num);
                    $('#totalEstimatedStock').text(estimatedStock);
                    $('#totalInboundPrice').text(total.toFixed(2));
                    $('#totalInfo').show();
                } else {
                    $('#totalInfo').hide();
                }
            }
            
            // 表单提交
            form.on('submit(inboundForm)', function(data) {
                // 验证所有必填项
                var isValid = true;
                
                // 验证商品选择
                if (!$('#proid').val()) {
                    $('#proid').addClass('layui-form-danger');
                    var proidParent = $('#proid').parent();
                    if (proidParent.find('.layui-form-mid').length === 0) {
                        proidParent.append('<div class="layui-form-mid layui-word-aux layui-text-danger"><?php echo t('请选择商品'); ?></div>');
                    }
                    isValid = false;
                }
                
                // 验证规格选择（可选）
                if (!$('#ggid').val()) {
                    // 如果没有选择规格，使用默认值0
                    data.field.ggid = 'default';
                    // 设置默认库存为0
                    $('#currentStock').val('0');
                    // 重新计算合计
                    calculateTotal();
                }
                
                // 验证入库数量
                if (!$('#num').val() || !/^\d+$/.test($('#num').val()) || parseInt($('#num').val()) <= 0) {
                    $('#num').addClass('layui-form-danger');
                    var numParent = $('#num').parent();
                    if (numParent.find('.layui-form-mid').length === 0) {
                        numParent.append('<div class="layui-form-mid layui-word-aux layui-text-danger"><?php echo t('请输入有效的入库数量'); ?></div>');
                    }
                    isValid = false;
                }
                
                // 验证入库单价
                if (!$('#price').val() || !/^\d+(\.\d{1,2})?$/.test($('#price').val()) || parseFloat($('#price').val()) < 0) {
                    $('#price').addClass('layui-form-danger');
                    var priceParent = $('#price').parent();
                    if (priceParent.find('.layui-form-mid').length === 0) {
                        priceParent.append('<div class="layui-form-mid layui-word-aux layui-text-danger"><?php echo t('请输入有效的价格，最多两位小数'); ?></div>');
                    }
                    isValid = false;
                }
                
                if (!isValid) {
                    return false;
                }
                
                // 显示确认提示
                layer.confirm('<?php echo t('确认要执行入库操作吗？'); ?>', {
                    icon: 3,
                    title: '<?php echo t('入库确认'); ?>',
                    btn: ['<?php echo t('确认'); ?>','<?php echo t('取消'); ?>']
                }, function(index) {
                    layer.close(index);
                    
                    // 显示加载中
                    var loadingIndex = layer.load(2, {time: 10000});
                    
                    $.ajax({
                        url: '<?php echo url("BarcodeInventory/inbound"); ?>',
                        type: 'POST',
                        data: data.field,
                        dataType: 'json',
                        success: function(res) {
                            layer.close(loadingIndex);
                            if (res.status == 1) {
                                layer.msg(res.msg, {icon: 1, time: 1500}, function() {
                                    window.location.reload();
                                });
                            } else {
                                layer.msg(res.msg, {icon: 2});
                            }
                        },
                        error: function() {
                            layer.close(loadingIndex);
                            layer.msg('<?php echo t('网络错误，请稍后重试'); ?>', {icon: 2});
                        }
                    });
                });
                
                return false;
            });
        });
    </script>
</body>
</html>