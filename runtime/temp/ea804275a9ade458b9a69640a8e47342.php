<?php /*a:3:{s:64:"/www/wwwroot/gdqshop.cn/app/view/barcode_inventory/outbound.html";i:1769502637;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;}*/ ?>
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
        
        /* 美化选择框 */
        .layui-select {
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .layui-select:focus {
            border-color: #1E9FFF;
            box-shadow: 0 0 0 2px rgba(30, 159, 255, 0.2);
        }
        
        /* 美化已选规格列表 */
        #specList {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #e6e6e6;
        }
        
        /* 美化规格项 */
        .layui-row.spec-item {
            background-color: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            border: 1px solid #e6e6e6;
            transition: all 0.3s ease;
        }
        
        .layui-row.spec-item:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-color: #1E9FFF;
        }
        
        /* 美化库存显示 */
        #stockDisplay {
            font-weight: bold;
            color: #67c23a;
        }
        
        /* 美化错误提示 */
        .layui-form-danger {
            border-color: #f56c6c !important;
        }
        
        .layui-text-danger {
            color: #f56c6c !important;
        }
        
        /* 美化必填项标记 */
        .redstar {
            color: #f56c6c;
            margin-right: 4px;
            font-weight: bold;
        }
        
        /* 美化单选按钮 */
        .layui-radio {
            margin-right: 20px;
        }
        
        /* 美化数量输入框 */
        .spec-num {
            width: 100px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .spec-num:focus {
            border-color: #1E9FFF;
            box-shadow: 0 0 0 2px rgba(30, 159, 255, 0.2);
        }
        
        /* 美化移除按钮 */
        .remove-spec {
            margin-top: 5px;
        }
        
        /* 添加响应式布局 */
        @media screen and (max-width: 768px) {
            .layui-row.spec-item .layui-col-md3,
            .layui-row.spec-item .layui-col-md2 {
                width: 100%;
                margin-bottom: 10px;
            }
        }
        
        /* 美化总库存显示 */
        .current-stock {
            background-color: #f0f9eb;
            color: #67c23a;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
        }
        
        /* 美化添加规格按钮 */
        #addSpecBtn {
            margin-left: 10px;
        }
        
        /* 美化表单标签 */
        .layui-form-label {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="layui-fluid">
        <div class="layui-row layui-col-space15">
            <div class="layui-card layui-col-md12">
                <div class="layui-card-header">
                    <i class="fa fa-minus-square-o"></i> <?php echo $title; ?>
                </div>
                <div class="layui-card-body" pad15>
                    <form class="layui-form form-label-w6" id="outboundForm" lay-filter="outboundForm">
                        <div class="layui-form-item">
                            <label class="layui-form-label"><span class="redstar">*</span><?php echo t('商品名称'); ?>：</label>
                            <div class="layui-input-block">
                                <select class="layui-select" name="proid" id="proid" required lay-filter="proid">
                                    <option value=""><?php echo t('请选择商品'); ?></option>
                                    <?php if(is_array($products) || $products instanceof \think\Collection || $products instanceof \think\Paginator): $i = 0; $__LIST__ = $products;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$product): $mod = ($i % 2 );++$i;?>
                                    <option value="<?php echo $product['id']; ?>"><?php echo $product['name']; ?></option>
                                    <?php endforeach; endif; else: echo "" ;endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                <label class="layui-form-label"><span class="redstar">*</span><?php echo t('商品规格'); ?>：</label>
                <div class="layui-input-block">
                    <select class="layui-select" id="addGgid" lay-filter="addGgid">
                        <option value=""><?php echo t('请先选择商品'); ?></option>
                    </select>
                    <span id="stockDisplay" style="margin-left: 10px; color: #666;"></span>
                    <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="addSpecBtn" style="margin-left: 10px;">
                        <i class="fa fa-plus"></i> <?php echo t('添加规格'); ?>
                    </button>
                </div>
            </div>
            
            <!-- 规格图片显示 -->
            <div class="layui-form-item">
                <label class="layui-form-label"><?php echo t('规格图片'); ?>：</label>
                <div class="layui-input-block">
                    <div id="specImageDisplay" style="margin-bottom: 10px;">
                        <img id="specImage" src="" alt="<?php echo t('规格图片'); ?>" style="max-width: 200px; max-height: 200px; display: none; border: 1px solid #e6e6e6; border-radius: 4px;">
                    </div>
                </div>
            </div>
                        
                        <!-- 已选规格列表 -->
                        <div class="layui-form-item">
                            <label class="layui-form-label"><?php echo t('已选规格'); ?>：</label>
                            <div class="layui-input-block">
                                <div id="specList" style="margin-bottom: 10px;"></div>
                                <input type="hidden" name="specs" id="specs" required>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label"><span class="redstar">*</span><?php echo t('出库对象'); ?>：</label>
                            <div class="layui-input-block">
                                <div class="layui-radio">
                                    <input type="radio" name="outbound_type" value="1" title="<?php echo t('本站商城'); ?>" lay-filter="outbound_type">
                                    <input type="radio" name="outbound_type" value="2" title="<?php echo t('经销商'); ?>" lay-filter="outbound_type" checked>
                                </div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item" id="dealerSelect" style="display: none;">
                            <label class="layui-form-label"><?php echo t('选择经销商'); ?>：</label>
                            <div class="layui-input-block">
                                <select class="layui-select" name="dealer_id" id="dealer_id">
                                    <option value=""><?php echo t('请选择经销商'); ?></option>
                                    <?php if(is_array($dealers) || $dealers instanceof \think\Collection || $dealers instanceof \think\Paginator): $i = 0; $__LIST__ = $dealers;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$dealer): $mod = ($i % 2 );++$i;?>
                                    <option value="<?php echo $dealer['id']; ?>"><?php echo $dealer['dealer_name']; ?></option>
                                    <?php endforeach; endif; else: echo "" ;endif; ?>
                                </select>
                            </div>
                        </div>
                        

                        
                        <div class="layui-form-item">
                            <div class="layui-input-block">
                                <button class="layui-btn" type="submit" lay-submit lay-filter="saveOutbound"><i class="fa fa-check"></i> <?php echo t('确认出库'); ?></button>
                                <button class="layui-btn layui-btn-primary" type="reset" id="resetBtn"><i class="fa fa-refresh"></i> <?php echo t('重置'); ?></button>
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
            
            // 前端缓存：商品信息映射表
            var productsMap = {};
            // 前端缓存：商品规格信息
            var guigeCache = {};
            // 已选规格列表
            var selectedSpecs = [];
            // 当前商品ID
            var currentProId = '';
            
            // 自定义验证规则
            form.verify({
                num: function(value) {
                    if (!/^\d+$/.test(value) || parseInt(value) <= 0) {
                        return '<?php echo t('请输入有效的出库数量'); ?>';
                    }
                }
            });
            
            // 初始化表单
            form.render();
            
            // 初始化商品信息映射表
            $('#proid option').each(function() {
                var proid = $(this).val();
                if (proid) {
                    // 获取商品信息，包括主图
                    var productName = $(this).text();
                    // 这里需要获取商品主图，但是在选择框中没有直接提供
                    // 所以我们需要通过AJAX获取商品主图
                    $.ajax({
                        url: '<?php echo url("BarcodeInventory/getProductPic"); ?>',
                        type: 'GET',
                        data: {proid: proid},
                        dataType: 'json',
                        success: function(res) {
                            if (res.status == 1) {
                                productsMap[proid] = {
                                    name: productName,
                                    pic: res.data
                                };
                            }
                        }
                    });
                }
            });
            
            // 页面加载时，默认显示经销商选择（因为默认出库类型是经销商）
            $('#dealerSelect').show();
            
            // 监听出库类型选择
            form.on('radio(outbound_type)', function(data) {
                var outboundType = data.value;
                if (outboundType == 2) {
                    // 选择经销商，显示经销商选择
                    $('#dealerSelect').show();
                } else {
                    // 选择本站商城，隐藏经销商选择
                    $('#dealerSelect').hide();
                    // 清除经销商选择
                    $('#dealer_id').val('');
                    // 清除经销商验证状态
                    $('#dealer_id').removeClass('layui-form-danger');
                    $('#dealer_id').parent().find('.layui-form-mid').remove();
                }
            });
            
            // 商品选择变化时，加载对应的规格
            form.on('select(proid)', function(data) {
                var proid = data.value;
                currentProId = proid;
                
                if (proid) {
                    // 清空已选规格
                    selectedSpecs = [];
                    updateSpecList();
                    
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
                    $('#addGgid').empty();
                    $('#addGgid').append('<option value=""><?php echo t('请先选择商品'); ?></option>');
                    // 清空库存显示
                    $('#stockDisplay').text('');
                    // 清空已选规格
                    selectedSpecs = [];
                    updateSpecList();
                    // 重新渲染表单
                    form.render('select');
                    // 清除验证状态
                    $('#addGgid').removeClass('layui-form-danger');
                    $('#addGgid').parent().find('.layui-form-mid').remove();
                }
            });
            
            // 渲染规格选项
            function renderGuigeOptions(data) {
                $('#addGgid').empty();
                $('#addGgid').append('<option value=""><?php echo t('请选择规格'); ?></option>');
                $.each(data, function(index, item) {
                    // 排除已选的规格
                    var isSelected = selectedSpecs.some(function(spec) { return spec.ggid == item.id; });
                    if (!isSelected) {
                        $('#addGgid').append('<option value="' + item.id + '" data-stock="' + item.stock + '" data-name="' + item.name + '" data-price="' + item.price + '" data-pic="' + (item.pic || '') + '">' + item.name + '</option>');
                    }
                });
                // 重新渲染表单
                form.render('select');
                // 清除规格相关的验证状态
                $('#addGgid').removeClass('layui-form-danger');
                $('#addGgid').parent().find('.layui-form-mid').remove();
            }
            
            // 规格选择变化时，显示当前库存和规格图片
            form.on('select(addGgid)', function(data) {
                var stock = $(data.elem).find('option:selected').data('stock');
                var pic = $(data.elem).find('option:selected').data('pic');
                // 在规格选择框右侧显示库存数量
                $('#stockDisplay').text('(<?php echo t('当前库存'); ?>: ' + stock + ')');
                
                // 显示规格图片
                if (pic) {
                    $('#specImage').attr('src', pic);
                    $('#specImage').show();
                } else {
                    // 显示商品主图
                    var proid = $('#proid').val();
                    if (proid && productsMap[proid]) {
                        $('#specImage').attr('src', productsMap[proid].pic);
                        $('#specImage').show();
                    } else {
                        $('#specImage').hide();
                    }
                }
                
                // 清除规格验证状态
                $(data.elem).removeClass('layui-form-danger');
                $(data.elem).parent().find('.layui-form-mid').remove();
            });
            
            // 添加规格按钮点击事件
            $('#addSpecBtn').click(function() {
                var ggid = $('#addGgid').val();
                if (!ggid) {
                    $('#addGgid').addClass('layui-form-danger');
                    var parent = $('#addGgid').parent();
                    if (parent.find('.layui-form-mid').length === 0) {
                        parent.append('<div class="layui-form-mid layui-word-aux layui-text-danger"><?php echo t('请选择规格'); ?></div>');
                    }
                    return;
                }
                
                // 获取规格信息
                var option = $('#addGgid option:selected');
                var specInfo = {
                    ggid: ggid,
                    name: option.data('name'),
                    stock: option.data('stock'),
                    price: option.data('price'),
                    pic: option.data('pic'),
                    num: 1
                };
                
                // 添加到已选规格列表
                selectedSpecs.push(specInfo);
                
                // 更新已选规格列表
                updateSpecList();
                
                // 重新渲染规格选择框（排除已选规格）
                renderGuigeOptions(guigeCache[currentProId]);
                
                // 清空库存显示
                $('#stockDisplay').text('');
            });
            
            // 更新已选规格列表
            function updateSpecList() {
                var html = '';
                if (selectedSpecs.length === 0) {
                    html = '<div style="color: #999;"><?php echo t('暂无已选规格'); ?></div>';
                } else {
                    $.each(selectedSpecs, function(index, spec) {
                        // 获取规格图片
                        var pic = spec.pic || '';
                        html += '<div class="layui-form-item layui-row" style="margin-bottom: 10px; padding: 10px; background-color: #f9f9f9; border-radius: 4px;">';
                        html += '<div class="layui-col-md3">';
                        html += '<div style="display: flex; align-items: center;">';
                        // 添加规格图片
                        if (pic) {
                            html += '<img src="' + pic + '" style="width: 40px; height: 40px; margin-right: 10px; border: 1px solid #e6e6e6; border-radius: 4px;" alt="' + spec.name + '">';
                        } else if (currentProId && productsMap[currentProId]) {
                            // 如果规格没有图片，使用商品主图
                            html += '<img src="' + productsMap[currentProId].pic + '" style="width: 40px; height: 40px; margin-right: 10px; border: 1px solid #e6e6e6; border-radius: 4px;" alt="' + spec.name + '">';
                        }
                        html += '<div>' + spec.name + '</div>';
                        html += '</div>';
                        html += '</div>';
                        html += '<div class="layui-col-md2">';
                        html += '<div style="color: #666; line-height: 38px;"><?php echo t('库存'); ?>: ' + spec.stock + '</div>';
                        html += '</div>';
                        html += '<div class="layui-col-md3">';
                        html += '<input type="number" name="spec_num_' + index + '" class="layui-input spec-num" data-index="' + index + '" value="' + spec.num + '" min="1" max="' + spec.stock + '" placeholder="<?php echo t('出库数量'); ?>">';
                        html += '</div>';
                        html += '<div class="layui-col-md2">';
                        html += '<div style="line-height: 38px;"><?php echo t('小计'); ?>: ' + (spec.num * spec.price).toFixed(2) + '</div>';
                        html += '</div>';
                        html += '<div class="layui-col-md2">';
                        html += '<button type="button" class="layui-btn layui-btn-sm layui-btn-danger remove-spec" data-index="' + index + '">';
                        html += '<i class="fa fa-trash"></i> <?php echo t('移除'); ?>';
                        html += '</button>';
                        html += '</div>';
                        html += '</div>';
                    });
                }
                
                $('#specList').html(html);
                
                // 更新隐藏字段
                $('#specs').val(JSON.stringify(selectedSpecs));
                
                // 绑定移除按钮事件
                bindRemoveSpecEvents();
                
                // 绑定数量输入事件
                bindSpecNumEvents();
            }
            
            // 绑定移除规格事件
            function bindRemoveSpecEvents() {
                $('.remove-spec').click(function() {
                    var index = $(this).data('index');
                    // 移除规格
                    selectedSpecs.splice(index, 1);
                    // 更新列表
                    updateSpecList();
                    // 重新渲染规格选择框
                    renderGuigeOptions(guigeCache[currentProId]);
                });
            }
            
            // 绑定规格数量输入事件
            function bindSpecNumEvents() {
                $('.spec-num').on('input propertychange', function() {
                    var index = $(this).data('index');
                    var value = $(this).val();
                    var spec = selectedSpecs[index];
                    
                    if (value) {
                        // 验证是否为有效数字
                        if (!/^\d+$/.test(value) || parseInt(value) <= 0) {
                            $(this).addClass('layui-form-danger');
                        } 
                        // 验证是否超过库存
                        else if (parseInt(value) > spec.stock) {
                            $(this).addClass('layui-form-danger');
                        } else {
                            $(this).removeClass('layui-form-danger');
                            // 更新规格数量
                            spec.num = parseInt(value);
                            // 更新隐藏字段
                            $('#specs').val(JSON.stringify(selectedSpecs));
                            // 重新渲染列表
                            updateSpecList();
                        }
                    } else {
                        $(this).removeClass('layui-form-danger');
                    }
                });
            }
            
            // 重置按钮
            $('#resetBtn').click(function() {
                $('#outboundForm')[0].reset();
                form.render();
                // 隐藏经销商选择
                $('#dealerSelect').hide();
                // 清除经销商选择
                $('#dealer_id').val('');
                // 清空已选规格
                selectedSpecs = [];
                updateSpecList();
                // 清空当前商品ID
                currentProId = '';
                // 清空规格选择
                $('#addGgid').empty();
                $('#addGgid').append('<option value=""><?php echo t('请先选择商品'); ?></option>');
                form.render('select');
                // 清空库存显示
                $('#stockDisplay').text('');
                // 清除所有验证状态
                $('#proid, #addGgid, #dealer_id').removeClass('layui-form-danger');
                $('#proid, #addGgid, #dealer_id').parent().find('.layui-form-mid').remove();
            });
            
            // 表单提交
            form.on('submit(saveOutbound)', function(data) {
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
                
                // 验证已选规格
                if (selectedSpecs.length === 0) {
                    layer.msg('<?php echo t('请至少选择一个规格'); ?>', {icon: 2});
                    isValid = false;
                }
                
                // 验证每个规格的数量
                for (var i = 0; i < selectedSpecs.length; i++) {
                    var spec = selectedSpecs[i];
                    var numInput = $('.spec-num[data-index="' + i + '"]');
                    var num = parseInt(numInput.val()) || 0;
                    
                    if (!num || num <= 0 || num > spec.stock) {
                        numInput.addClass('layui-form-danger');
                        isValid = false;
                    }
                }
                
                // 验证出库类型
                var outboundType = $('input[name="outbound_type"]:checked').val();
                if (!outboundType) {
                    layer.msg('<?php echo t('请选择出库对象'); ?>', {icon: 2});
                    isValid = false;
                }
                
                // 验证经销商（如果选择了经销商）
                if (outboundType == 2) {
                    if (!$('#dealer_id').val()) {
                        $('#dealer_id').addClass('layui-form-danger');
                        var dealerParent = $('#dealer_id').parent();
                        if (dealerParent.find('.layui-form-mid').length === 0) {
                            dealerParent.append('<div class="layui-form-mid layui-word-aux layui-text-danger"><?php echo t('请选择经销商'); ?></div>');
                        }
                        isValid = false;
                    }
                }
                
                if (!isValid) {
                    return false;
                }
                
                // 更新数据中的规格信息
                data.field.specs = JSON.stringify(selectedSpecs);
                
                $.ajax({
                    url: '<?php echo url("BarcodeInventory/outbound"); ?>',
                    type: 'POST',
                    data: data.field,
                    dataType: 'json',
                    success: function(res) {
                        if (res.status == 1) {
                            layer.msg(res.msg, {icon: 1, time: 1500}, function() {
                                window.location.reload();
                            });
                        } else {
                            layer.msg(res.msg, {icon: 2});
                        }
                    },
                    error: function() {
                        layer.msg('<?php echo t('网络错误，请稍后重试'); ?>', {icon: 2});
                    }
                });
                
                return false;
            });
        });
    </script>
</body>
</html>