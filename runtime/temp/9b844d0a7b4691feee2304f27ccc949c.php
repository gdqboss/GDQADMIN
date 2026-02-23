<?php /*a:3:{s:60:"/www/wwwroot/gdqshop.cn/app/view/barcode_inventory/edit.html";i:1769502648;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;}*/ ?>
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
        /* 通用样式优化 */
        body {
            background-color: #f2f2f2;
        }
        
        /* 表格样式优化 */
        #ggnamediv,
        #ggvaldiv {
            margin: 10px 0;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        #ggnamediv .layui-table,
        #ggvaldiv .layui-table {
            margin: 0;
            border: none;
        }
        
        /* 输入框样式优化 */
        #ggnamediv .layui-input,
        #ggvaldiv .layui-input {
            display: inline-block;
            height: 36px;
            vertical-align: middle;
            margin-right: 8px;
            width: calc(100% - 30px);
        }
        
        /* 确保表格单元格内容不换行 */
        #ggvaldiv td {
            white-space: nowrap;
            vertical-align: middle;
        }
        
        /* 批量设置图标样式优化 */
        #ggvaldiv .fa-hand-o-down {
            display: inline-block;
            vertical-align: middle;
            margin-left: 5px;
            color: #1E9FFF;
            cursor: pointer;
            font-size: 14px;
        }
        
        /* 规格图片样式 */
        .guigeimg {
            display: inline-block;
            position: relative;
            margin-right: 10px;
        }
        
        .guigeimg_close {
            width: 25px;
            height: 25px;
            right: -14px;
            top: -14px;
            background-color: #ff5722;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .layui-imgbox .layui-imgbox-close {
            z-index: 6;
        }
        
        /* 按钮样式优化 */
        .layui-btn {
            margin-right: 10px;
        }
        
        /* 规格信息布局优化 */
        #setgg {
            margin-bottom: 20px;
        }
        
        /* 辅助文字样式 */
        .layui-word-aux {
            color: #999;
            font-size: 12px;
            margin: 10px 0;
            display: block;
        }
        
        /* 批量设置图标样式 */
        .fa-hand-o-down {
            color: #1E9FFF;
            cursor: pointer;
            font-size: 14px;
            margin-left: 5px;
        }
        
        /* 规格分组输入框宽度优化 */
        #ggnamediv input[name="ggname"] {
            width: 150px;
            margin-bottom: 8px;
        }
        
        #ggnamediv input[name="ggnameguige"] {
            width: 200px;
            margin-bottom: 8px;
        }
        
        /* 表格列宽度优化 */
        #ggvaldiv th {
            background-color: #f8f8f8;
            font-weight: bold;
            color: #333;
        }
        
        /* 分隔线样式 */
        .divider {
            height: 1px;
            background-color: #e2e2e2;
            margin: 20px 0;
        }
        
        /* 按钮组样式 */
        .btn-group {
            margin: 15px 0;
        }
        
        /* 商品主图预览样式优化 */
        #picPreview {
            margin-top: 10px;
        }
        
        /* 规格项目表格行悬停效果 */
        #ggvaldiv tbody tr:hover {
            background-color: #fafafa;
        }
        
        /* 隐藏元素过渡效果 */
        .showguigeimg {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="layui-fluid">
        <div class="layui-row layui-col-space15">
            <div class="layui-card layui-col-md12">
                <div class="layui-card-header">
                    <?php if(!$info['id']): ?><i class="fa fa-plus"></i> <?php echo $title; else: ?><i class="fa fa-pencil"></i> <?php echo $title; ?><?php endif; ?>
                    <i class="layui-icon layui-icon-close" style="font-size:18px;font-weight:bold;cursor:pointer" onclick="closeself()"></i>
                    <a href="<?php echo url('BarcodeInventory/index'); ?>" class="layui-btn layui-btn-sm layui-btn-primary" style="margin-left:10px;">返回列表</a>
                </div>
                <div class="layui-card-body" pad15>
                    <form class="layui-form form-label-w6" id="productForm" lay-filter="productForm">
                        <div class="layui-tab" lay-filter="mytab">
                            <ul class="layui-tab-title">
                                <li class="layui-this" lay-id="1">商品信息</li>
                                <li lay-id="2">规格信息</li>
                            </ul>
                            <div class="layui-tab-content">
                                <!-- 商品信息 -->
                                <div class="layui-tab-item layui-show">
                                    <input type="hidden" name="id" value="<?php echo (isset($info['id']) && ($info['id'] !== '')?$info['id']:'0'); ?>">
                                    
                                    <!-- 基本信息 -->
                                    <div class="layui-card">
                                        <div class="layui-card-header">
                                            <h5><?php echo t('基本信息'); ?></h5>
                                            <div class="layui-card-header-right">
                                                <a class="layui-btn layui-btn-xs layui-btn-primary">
                                                    <i class="fa fa-chevron-down"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="layui-card-body">
                                            <div class="layui-form-item">
                                                <label class="layui-form-label"><span class="redstar">*</span><?php echo t('商品名称'); ?>：</label>
                                                <div class="layui-input-inline" style="width:350px">
                                                    <input type="text" class="layui-input" name="name" value="<?php echo (isset($info['name']) && ($info['name'] !== '')?$info['name']:''); ?>" placeholder="<?php echo t('请输入商品名称'); ?>" required>
                                                </div>
                                                <label class="layui-form-label" style="font-size: 12px; padding-left: 20px; width: auto;"><?php echo t('English Name'); ?>：</label>
                                                <div class="layui-input-inline" style="width:250px">
                                                    <input type="text" class="layui-input" name="english_name" value="<?php echo (isset($info['english_name']) && ($info['english_name'] !== '')?$info['english_name']:''); ?>" placeholder="<?php echo t('请输入英文名称'); ?>">
                                                </div>
                                            </div>
                                            
                                            <!-- 供应商选择 -->
                                            <div class="layui-form-item">
                                                <label class="layui-form-label"><?php echo t('供应商'); ?>：</label>
                                                <div class="layui-input-inline">
                                                    <?php if($is_admin): if($suppliers): ?>
                                                        <select class="layui-select" name="supplier_id">
                                                            <option value=""><?php echo t('请选择供应商'); ?></option>
                                                            <?php if(is_array($suppliers) || $suppliers instanceof \think\Collection || $suppliers instanceof \think\Paginator): $i = 0; $__LIST__ = $suppliers;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$supplier): $mod = ($i % 2 );++$i;?>
                                                            <option value="<?php echo $supplier['id']; ?>" <?php if(isset($info['supplier_id']) && $info['supplier_id'] == $supplier['id']): ?>selected<?php endif; ?>><?php echo $supplier['supplier_name']; ?></option>
                                                            <?php endforeach; endif; else: echo "" ;endif; ?>
                                                        </select>
                                                        <?php endif; else: ?>
                                                        <!-- 非管理员，显示只读的供应商信息 -->
                                                        <input type="text" class="layui-input" value="<?php echo $user_supplier_name; ?>" readonly placeholder="<?php echo t('无法修改供应商'); ?>">
                                                        <input type="hidden" name="supplier_id" value="<?php echo $user_supplier_id; ?>">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- 商品主图 -->
                    <div class="layui-form-item">
                        <label class="layui-form-label"><span class="redstar">*</span><?php echo t('商品主图'); ?>：</label>
                        <button style="float:left;" type="button" class="layui-btn layui-btn-primary" upload-input="pic" upload-preview="picPreview" onclick="uploader(this)"><?php echo t('上传图片'); ?></button>
                        <div class="layui-form-mid layui-word-aux" style="margin-left:10px;"><?php echo t('建议尺寸：640×640像素'); ?></div>
                        <div id="picPreview" class="picsList-class-padding" style="clear:both;padding-top:10px;">
                            <div class="layui-imgbox imgbox-required">
                                <a <?php if($info['pic']): ?>style="display: block;" <?php else: ?> style="display: none;"  <?php endif; ?> class="layui-imgbox-close" href="javascript:void(0)" onclick="$(this).parent().find('img').attr('src','').hide();getpicsval('pic','picPreview');$(this).hide();" title="<?php echo t('删除'); ?>"><i class="layui-icon layui-icon-close-fill-opaque"></i></a>
                                <div class="layui-imgbox-img">
                                    <img src="<?php echo $info['pic']; ?>"/>
                                </div>
                                <input autocomplete='off' type="text" id="pic" name="pic" value="<?php echo (isset($info['pic']) && ($info['pic'] !== '')?$info['pic']:''); ?>" style="cursor:default;display:none;">
                            </div>
                        </div>
                    </div>
                    

                    
                    <!-- 生产日期 -->
                    <div class="layui-form-item">
                        <label class="layui-form-label"><?php echo t('生产日期'); ?>：</label>
                        <div class="layui-input-inline">
                            <input type="date" class="layui-input" name="production_date" value="<?php echo (isset($info['production_date']) && ($info['production_date'] !== '')?$info['production_date']:date('Y-m-d')); ?>" placeholder="<?php echo t('请选择生产日期'); ?>">
                        </div>
                    </div>
                    
                    <!-- 入仓日期 -->
                    <div class="layui-form-item">
                        <label class="layui-form-label"><?php echo t('入仓日期'); ?>：</label>
                        <div class="layui-input-inline">
                            <input type="date" class="layui-input" name="warehouse_date" value="<?php echo (isset($info['warehouse_date']) && ($info['warehouse_date'] !== '')?$info['warehouse_date']:date('Y-m-d')); ?>" placeholder="<?php echo t('请选择入仓日期'); ?>">
                        </div>
                    </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 规格信息 -->
                                <div class="layui-tab-item">
                                    <div class="layui-card">
                                        <div class="layui-card-header">
                                            <h5><?php echo t('规格信息'); ?></h5>
                                        </div>
                                        <div class="layui-card-body">
                                            <div class="layui-form-item" id="setgg">
                                                <label class="layui-form-label"><?php echo t('设置规格'); ?>：</label>
                                                <div class="layui-input-inline" style="width:auto">
                                                    <!-- 规格分组表格 -->
                                                    <table id="ggnamediv"  class="layui-table" style="width:700px">
                                                        <thead>
                                                        <tr>
                                                            <th><?php echo t('规格分组'); ?></th>
                                                            <th><?php echo t('规格名称'); ?></th>
                                                            <th><?php echo t('操作'); ?></th>
                                                        </tr>
                                                        </thead>
                                                        <tbody>
                                                        <tr>
                                                            <td><input type="text" class="layui-input" name="ggname" value="<?php echo t('规格'); ?>"/></td>
                                                            <td>
                                                                <div>
                                                                <input name="ggnameguige" type="text" class="layui-input" value="<?php echo t('默认规格'); ?>" placeholder="<?php echo t('请输入规格名称'); ?>"> <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="addggname(this)"><i class="fa fa-plus" style="font-size:14px!important"></i></button>
                                                                </div>
                                                            </td>
                                                            <td><button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="addggtype()"><?php echo t('添加'); ?></button></td>
                                                        </tr>
                                                        </tbody>
                                                    </table>
                                                    
                                                    <!-- 辅助文字 -->
                                                    <div class="layui-word-aux">
                                                        <?php echo t('提示：先点击“添加按钮”添加规格分组，如：“颜色”，“尺码”，若只有一种规格则不需要添加规格分组；然后分别点击"＋"号添加规格名称，如：“红色”，“蓝色”；“大号”，“小号”；设置完成后点击“刷新规格项目表”按钮进行编辑各个规格的价格、库存等信息'); ?>
                                                    </div>
                                                    
                                                    <!-- 按钮组 -->
                                                    <div class="btn-group">
                                                        <button type="button"  class="layui-btn layui-btn-sm layui-btn-primary" onclick="refreshgg()">
                                                            <i class="fa fa-refresh" style="font-size:14px!important"></i> <?php echo t('刷新规格项目表'); ?>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- 分隔线 -->
                                                    <div class="divider"></div>
                                                    
                                                    <!-- 规格项目表格 -->
                                                    <table id="ggvaldiv" class="layui-table"></table>
                                                    
                                                    <input type="hidden" name="specs" value=""/>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 提交按钮 -->
                        <div class="layui-form-item">
                            <div class="layui-input-block">
                                <button class="layui-btn" type="submit" lay-submit lay-filter="productForm"><i class="fa fa-check"></i> <?php echo t('保存商品'); ?></button>
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
        // 全局变量定义
        var lang = {
            upload_success: '<?php echo t('上传成功'); ?>',
            network_error: '<?php echo t('网络错误，请稍后重试'); ?>',
            delete: '<?php echo t('删除'); ?>'
        };
        
        // 页面加载完成后初始化
        $(document).ready(function() {
            layui.use(['form', 'layer', 'upload', 'element'], function() {
                var form = layui.form;
                var layer = layui.layer;
                var upload = layui.upload;
                var element = layui.element;
                
                // 渲染表单
                form.render();
                
                // 初始化标签页
                element.init();
                
                // 监听标签页切换
                element.on('tab(mytab)', function(data){});
                

                

                
                // 规格处理相关函数
                // 添加规格名称
                window.addggname = function(obj) {
                    var html = '<div><input type="text" name="ggnameguige" class="layui-input" style="width:180px;margin-bottom:5px" placeholder="<?php echo t('请输入规格名称'); ?>"> <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="delggname(this,0)" style="margin-top:-3px;"><i class="fa fa-remove" style="font-size:14px!important"></i></button></div>';
                    $(obj).parent().after(html);
                };
                
                // 删除规格名称
                window.delggname = function(obj, type) {
                    $(obj).parent().remove();
                };
                
                // 添加规格分组
                window.addggtype = function() {
                    var html = '<tr><td><input type="text" class="layui-input" name="ggname" value="" style="width:120px;margin-bottom: 5px;"/></td><td><div><input type="text" name="ggnameguige" class="layui-input" style="width:180px;margin-bottom:5px" placeholder="<?php echo t('请输入规格名称'); ?>"> <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="addggname(this)" style="margin-top:-3px;"><i class="fa fa-plus" style="font-size:14px!important"></i></button></div></td><td><button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="$(this).parent().parent().remove()"><?php echo t('删除'); ?></button></td></tr>';
                    $('#ggnamediv').append(html);
                };
                
                // 刷新规格项目表
                window.refreshgg = function() {
                    var ggdata = [];
                    var gg_list = [];
                    
                    // 收集规格分组和规格值
                    $('#ggnamediv tbody tr').each(function() {
                        var ggname = $(this).find('input[name="ggname"]').val();
                        var ggval = [];
                        
                        $(this).find('input[name="ggnameguige"]').each(function() {
                            var val = $(this).val();
                            if (val) {
                                ggval.push(val);
                            }
                        });
                        
                        if (ggname && ggval.length > 0) {
                            ggdata.push({name: ggname, items: ggval});
                            gg_list.push(ggval);
                        }
                    });
                    
                    // 如果没有规格数据，添加默认数据
                    if (ggdata.length === 0) {
                        ggdata.push({name: '<?php echo t('规格'); ?>', items: ['<?php echo t('默认规格'); ?>']});
                        gg_list.push(['<?php echo t('默认规格'); ?>']);
                    }
                    
                    // 生成所有规格组合
                    var combinations = [];
                    
                    if (gg_list.length === 1) {
                        // 只有一个规格分组
                        for (var i = 0; i < gg_list[0].length; i++) {
                            combinations.push([gg_list[0][i]]);
                        }
                    } else if (gg_list.length > 1) {
                        // 多个规格分组，生成所有组合
                        combinations = gg_list[0].map(function(item) {
                            return [item];
                        });
                        
                        for (var i = 1; i < gg_list.length; i++) {
                            var temp = [];
                            for (var j = 0; j < combinations.length; j++) {
                                for (var k = 0; k < gg_list[i].length; k++) {
                                    temp.push(combinations[j].concat([gg_list[i][k]]));
                                }
                            }
                            combinations = temp;
                        }
                    }
                    
                    // 生成规格项目表
                    var html = '<thead><tr><th><?php echo t('规格名称'); ?></th><th><?php echo t('库存量'); ?></th><th><?php echo t('成本价'); ?></th><th><?php echo t('出厂价'); ?></th><th><?php echo t('销售价'); ?></th><th><?php echo t('条码/二维码'); ?></th><th><?php echo t('图片'); ?></th></tr></thead><tbody>';
                    
                    // 检查是否有现有规格数据
                    var hasExistingData = false;
                    <?php if($gglist): ?>
                        hasExistingData = true;
                    <?php endif; ?>
                    
                    for (var i = 0; i < combinations.length; i++) {
                        var specName = combinations[i].join(' / ');
                        
                        // 获取现有规格数据
                        var existingData = {id: '', stock: 0, cost_price: 0, market_price: 0, sell_price: 0, code_type: 1, barcode: '', pic: ''};
                        <?php if($gglist): if(is_array($gglist) || $gglist instanceof \think\Collection || $gglist instanceof \think\Paginator): $k = 0; $__LIST__ = $gglist;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$item): $mod = ($k % 2 );++$k;?>
                                if (i == <?php echo $k; ?>) {
                                    existingData = {id: "<?php echo $item['id']; ?>", stock: <?php echo $item['stock']; ?>, cost_price: <?php echo $item['cost_price']; ?>, market_price: <?php echo $item['market_price']; ?>, sell_price: <?php echo $item['sell_price']; ?>, code_type: <?php echo $item['code_type']; ?>, barcode: "<?php echo $item['barcode']; ?>", pic: "<?php echo (isset($item['pic']) && ($item['pic'] !== '')?$item['pic']:''); ?>"};
                                }
                            <?php endforeach; endif; else: echo "" ;endif; ?>
                        <?php endif; ?>
                        
                        html += '<tr>';
                        html += '<td><input type="hidden" name="option[' + i + '][id]" value="' + existingData.id + '"><input type="text" class="layui-input" name="option[' + i + '][name]" value="' + specName + '" placeholder="<?php echo t('规格名称'); ?>" required></td>';
                        html += '<td>';
                        html += '<input type="number" class="layui-input" name="option[' + i + '][stock]" value="' + existingData.stock + '" placeholder="<?php echo t('库存量'); ?>" min="0" required>';
                        if (i == 0) {
                            html += ' <i class="fa fa-hand-o-down" style="cursor:pointer" title="<?php echo t('批量设置'); ?>" onclick="plset(\'stock\')"></i>';
                        }
                        html += '</td>';
                        html += '<td>';
                        html += '<input type="number" class="layui-input" name="option[' + i + '][cost_price]" value="' + existingData.cost_price.toFixed(2) + '" placeholder="<?php echo t('成本价'); ?>" min="0" step="0.01">';
                        if (i == 0) {
                            html += ' <i class="fa fa-hand-o-down" style="cursor:pointer" title="<?php echo t('批量设置'); ?>" onclick="plset(\'cost_price\')"></i>';
                        }
                        html += '</td>';
                        html += '<td>';
                        html += '<input type="number" class="layui-input" name="option[' + i + '][market_price]" value="' + existingData.market_price.toFixed(2) + '" placeholder="<?php echo t('出厂价'); ?>" min="0" step="0.01">';
                        if (i == 0) {
                            html += ' <i class="fa fa-hand-o-down" style="cursor:pointer" title="<?php echo t('批量设置'); ?>" onclick="plset(\'market_price\')"></i>';
                        }
                        html += '</td>';
                        html += '<td>';
                        html += '<input type="number" class="layui-input" name="option[' + i + '][sell_price]" value="' + existingData.sell_price.toFixed(2) + '" placeholder="<?php echo t('销售价'); ?>" min="0" step="0.01" required>';
                        if (i == 0) {
                            html += ' <i class="fa fa-hand-o-down" style="cursor:pointer" title="<?php echo t('批量设置'); ?>" onclick="plset(\'sell_price\')"></i>';
                        }
                        html += '</td>';
                        html += '<td>';
                        html += '<input type="hidden" name="option[' + i + '][code_type]" value="1">';
                        html += '<input type="text" class="layui-input" name="option[' + i + '][barcode]" value="' + existingData.barcode + '" placeholder="<?php echo t('请输入条码/二维码内容'); ?>">';
                        if (i == 0) {
                            html += ' <i class="fa fa-hand-o-down" style="cursor:pointer" title="<?php echo t('批量设置'); ?>" onclick="plset(\'barcode\')"></i>';
                        }
                        html += '</td>';
                        html += '<td class="showguigeimg" id="ggpic' + i + '">';
                        html += '<input type="hidden" name="option[' + i + '][pic]" id="ggpicval' + i + '" value="' + (existingData.pic == null ? '' : existingData.pic) + '">';
                        html += '<div class="guigeimg"><img class="thumb_img" src="' + (existingData.pic ? existingData.pic : '') + '" style="max-width:50px;">';
                        html += '<a class="layui-imgbox-close guigeimg_close" style="' + (existingData.pic == '' || existingData.pic == null ? 'display:none' : 'display:block') + '" href="javascript:void(0)" onclick="$(this).prev(\'.thumb_img\').attr(\'src\',\'\');$(this).parent().prev(\'input\').val(\'\');$(this).hide();" title="<?php echo t('删除'); ?>"><i class="layui-icon layui-icon-close-fill-opaque"></i></a></div>';
                        html += ' <button class="layui-btn layui-btn-sm layui-btn-primary" onclick="uploader(this)" upload-input="ggpicval' + i + '" upload-preview="ggpic' + i + '" type="button"><?php echo t('上传'); ?></button></td>';
                        html += '</tr>';
                    }
                    
                    html += '</tbody>';
                    $('#ggvaldiv').html(html);
                    
                    // 重新渲染表单
                    form.render();
                };
                
                // 页面加载时，先恢复原有规格数据，再生成规格行
                function restoreOriginalSpecs() {
                    // 清空现有规格分组
                    $('#ggnamediv tbody').html('<tr><td><input type="text" class="layui-input" name="ggname" value="" style="width:120px;margin-bottom: 5px;"/></td><td><div><input type="text" name="ggnameguige" class="layui-input" style="width:180px;margin-bottom:5px" placeholder="<?php echo t('请输入规格名称'); ?>"> <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="addggname(this)" style="margin-top:-3px;"><i class="fa fa-plus" style="font-size:14px!important"></i></button></div></td><td><button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="addggtype()"><?php echo t('添加'); ?></button></td></tr>');
                    
                    // 检查是否有现有规格数据
                    <?php if($gglist): ?>
                        // 提取所有规格名称
                        var specNames = [];
                        <?php if(is_array($gglist) || $gglist instanceof \think\Collection || $gglist instanceof \think\Paginator): $i = 0; $__LIST__ = $gglist;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$item): $mod = ($i % 2 );++$i;?>
                            specNames.push("<?php echo $item['name']; ?>");
                        <?php endforeach; endif; else: echo "" ;endif; ?>
                        
                        if (specNames.length > 0) {
                            // 假设第一个规格名称的格式是 "规格1 / 规格2 / ..."，从中提取规格分组
                            var firstSpec = specNames[0];
                            var groups = firstSpec.split(' / ');
                            
                            if (groups.length > 1) {
                                // 有多个规格分组，创建相应的分组行
                                $('#ggnamediv tbody').html('');
                                
                                for (var i = 0; i < groups.length; i++) {
                                    var html = '<tr><td><input type="text" class="layui-input" name="ggname" value="' + (['颜色', '尺码', '口味', '容量'][i] || '<?php echo t('规格'); ?>' + (i+1)) + '" style="width:120px;margin-bottom: 5px;"/></td><td>';
                                    
                                    // 提取该分组下的所有唯一规格值
                                    var groupValues = [];
                                    for (var j = 0; j < specNames.length; j++) {
                                        var values = specNames[j].split(' / ');
                                        if (values[i] && groupValues.indexOf(values[i]) === -1) {
                                            groupValues.push(values[i]);
                                        }
                                    }
                                    
                                    for (var j = 0; j < groupValues.length; j++) {
                                        if (j === 0) {
                                            html += '<div><input type="text" name="ggnameguige" class="layui-input" style="width:180px;margin-bottom:5px" placeholder="<?php echo t('请输入规格名称'); ?>" value="' + groupValues[j] + '"> <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="addggname(this)" style="margin-top:-3px;"><i class="fa fa-plus" style="font-size:14px!important"></i></button></div>';
                                        } else {
                                            html += '<div><input type="text" name="ggnameguige" class="layui-input" style="width:180px;margin-bottom:5px" placeholder="<?php echo t('请输入规格名称'); ?>" value="' + groupValues[j] + '"> <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="delggname(this,0)" style="margin-top:-3px;"><i class="fa fa-remove" style="font-size:14px!important"></i></button></div>';
                                        }
                                    }
                                    
                                    html += '</td><td>';
                                    if (i === 0) {
                                        html += '<button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="addggtype()"><?php echo t('添加'); ?></button>';
                                    } else {
                                        html += '<button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="$(this).parent().parent().remove()"><?php echo t('删除'); ?></button>';
                                    }
                                    html += '</td></tr>';
                                    
                                    $('#ggnamediv tbody').append(html);
                                }
                            } else {
                                // 只有一个规格分组，提取所有唯一规格值
                                var uniqueValues = [];
                                for (var i = 0; i < specNames.length; i++) {
                                    if (uniqueValues.indexOf(specNames[i]) === -1) {
                                        uniqueValues.push(specNames[i]);
                                    }
                                }
                                
                                // 更新第一个规格分组的规格值
                                var firstRow = $('#ggnamediv tbody tr:first');
                                var inputsHtml = '<div><input type="text" name="ggnameguige" class="layui-input" style="width:180px;margin-bottom:5px" placeholder="<?php echo t('请输入规格名称'); ?>" value="' + uniqueValues[0] + '"> <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="addggname(this)" style="margin-top:-3px;"><i class="fa fa-plus" style="font-size:14px!important"></i></button></div>';
                                
                                for (var i = 1; i < uniqueValues.length; i++) {
                                    inputsHtml += '<div><input type="text" name="ggnameguige" class="layui-input" style="width:180px;margin-bottom:5px" placeholder="<?php echo t('请输入规格名称'); ?>" value="' + uniqueValues[i] + '"> <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="delggname(this,0)" style="margin-top:-3px;"><i class="fa fa-remove" style="font-size:14px!important"></i></button></div>';
                                }
                                
                                firstRow.find('td:eq(1)').html(inputsHtml);
                            }
                        }
                    <?php endif; ?>
                }
                
                // 页面加载时，先恢复原有规格数据，再生成规格行
                restoreOriginalSpecs();
                refreshgg();
                
                // 批量设置
                window.plset = function(name) {
                    // 获取第一个规格的值
                    var firstValue = $('input[name="option[0][' + name + ']"]').val();
                    
                    // 将第一个规格的值填充到所有规格
                    $('input[name*="option["][name*="[' + name + ']"]').each(function() {
                        $(this).val(firstValue);
                    });
                };
                
                // 表单提交
                form.on('submit(productForm)', function(data) {
                    // 直接输出请求URL，便于调试
                    var url = '<?php echo url('BarcodeInventory/save_product'); ?>';
                    console.log('Request URL:', url);
                    console.log('Request Data:', $('#productForm').serialize());
                    
                    $.ajax({
                        url: url,
                        type: 'POST',
                        data: $('#productForm').serialize(),
                        dataType: 'json',
                        success: function(res) {
                            console.log('Response:', res);
                            if (res.status == 1) {
                                layer.msg(res.msg, {icon: 1, time: 1500}, function() {
                                    window.location.href = res.url;
                                });
                            } else {
                                layer.msg(res.msg, {icon: 2});
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log('AJAX Error Status:', status);
                            console.log('AJAX Error:', error);
                            console.log('Response Status:', xhr.status);
                            console.log('Response Headers:', xhr.getAllResponseHeaders());
                            console.log('Response Text:', xhr.responseText);
                            layer.msg(lang.network_error + ': ' + status + ', ' + error + ', Status: ' + xhr.status, {icon: 2});
                        }
                    });
                    return false;
                });
            });
        });
        
        // 关闭窗口函数
        function closeself() {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        }
    </script>
</body>
</html>