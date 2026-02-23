<?php /*a:3:{s:66:"/www/wwwroot/gdqshop.cn/app/view/barcode_inventory/store_list.html";i:1768722663;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;}*/ ?>
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
        
        /* 美化搜索区域 */
        .search-box {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        /* 美化按钮 */
        .layui-btn {
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="layui-fluid">
        <div class="layui-row layui-col-space15">
            <div class="layui-card layui-col-md12">
                <div class="layui-card-header">
                    <i class="fa fa-home"></i> <?php echo $title; ?>
                    <button class="layui-btn layui-btn-sm layui-btn-primary pull-right" onclick="window.location.href='/?s=/BarcodeInventory/dealer_list'">
                        <i class="fa fa-arrow-left"></i> <?php echo t('返回经销商列表'); ?>
                    </button>
                </div>
                <div class="layui-card-body" pad15>
                    <!-- 搜索区域 -->
                    <div class="search-box">
                        <form class="layui-form" action="" method="get">
                            <div class="layui-form-item">
                                <div class="layui-inline">
                                    <label class="layui-form-label"><?php echo t('经销商'); ?>：</label>
                                    <div class="layui-input-inline">
                                        <select name="dealer_id" id="dealer_id" lay-search class="layui-select">
                                            <option value=""><?php echo t('请选择经销商'); ?></option>
                                            <?php if(is_array($dealers) || $dealers instanceof \think\Collection || $dealers instanceof \think\Paginator): $i = 0; $__LIST__ = $dealers;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$dealer): $mod = ($i % 2 );++$i;?>
                                            <option value="<?php echo $dealer['id']; ?>" <?php if(isset($_GET['dealer_id']) && $_GET['dealer_id'] == $dealer['id']): ?>selected<?php endif; ?>><?php echo $dealer['dealer_name']; ?></option>
                                            <?php endforeach; endif; else: echo "" ;endif; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="layui-inline">
                                    <label class="layui-form-label"><?php echo t('门店名称'); ?>：</label>
                                    <div class="layui-input-inline">
                                        <input type="text" name="store_name" id="store_name" placeholder="<?php echo t('请输入门店名称'); ?>" class="layui-input">
                                    </div>
                                </div>
                                <div class="layui-inline">
                                    <button class="layui-btn layui-btn-primary" type="button" onclick="searchData()">
                                        <i class="fa fa-search"></i> <?php echo t('搜索'); ?>
                                    </button>
                                    <button class="layui-btn" type="button" onclick="addStore()">
                                        <i class="fa fa-plus"></i> <?php echo t('添加门店'); ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- 数据表格 -->
                    <table class="layui-hide" id="storeTable" lay-filter="storeTable"></table>
                    
                    <!-- 操作栏模板 -->
                    <script type="text/html" id="storeBar">
                        <a class="layui-btn layui-btn-xs" lay-event="edit"><?php echo t('编辑'); ?></a>
                        {{#  if(d.is_main == 0) { }}
                        <a class="layui-btn layui-btn-xs layui-btn-warm" lay-event="set_main"><?php echo t('设为主店'); ?></a>
                        {{#  } }}
                        <a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="del"><?php echo t('删除'); ?></a>
                    </script>
                    
                    <!-- 主店状态模板 -->
                    <script type="text/html" id="isMainTpl">
                        {{#  if(d.is_main == 1) { }}
                        <span class="layui-badge layui-bg-green"><?php echo t('是'); ?></span>
                        {{#  } else { }}
                        <span class="layui-badge"><?php echo t('否'); ?></span>
                        {{#  } }}
                    </script>
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
        layui.use(['table', 'layer', 'form'], function() {
            var table = layui.table;
            var layer = layui.layer;
            var form = layui.form;
            
            // 渲染表单
            form.render();
            
            // 渲染表格
            var tableIns = table.render({
                elem: '#storeTable',
                url: '/?s=/BarcodeInventory/store_list',
                method: 'post',
                where: {
                    dealer_id: $('#dealer_id').val()
                },
                page: true,
                limit: 10,
                limits: [10, 20, 50, 100],
                cols: [[
                    {field: 'id', title: 'ID', width: 80, align: 'center'},
                    {field: 'dealer_name', title: '<?php echo t('经销商'); ?>', minWidth: 150},
                    {field: 'store_name', title: '<?php echo t('门店名称'); ?>', minWidth: 150},
                    {field: 'contact_person', title: '<?php echo t('负责人'); ?>', width: 120, align: 'center'},
                    {field: 'phone', title: '<?php echo t('联系电话'); ?>', width: 150, align: 'center'},
                    {field: 'address', title: '<?php echo t('经营地址'); ?>', minWidth: 200},
                    {field: 'is_main', title: '<?php echo t('是否主店'); ?>', width: 120, align: 'center', templet: '#isMainTpl'},
                    {field: 'created_at', title: '<?php echo t('创建时间'); ?>', width: 160, align: 'center', templet: function(d) { return layui.util.toDateString(d.created_at * 1000, 'yyyy-MM-dd HH:mm:ss'); }},
                    {field: 'updated_at', title: '<?php echo t('更新时间'); ?>', width: 160, align: 'center', templet: function(d) { return layui.util.toDateString(d.updated_at * 1000, 'yyyy-MM-dd HH:mm:ss'); }},
                    {title: '<?php echo t('操作'); ?>', width: 200, align: 'center', toolbar: '#storeBar'}
                ]],
                id: 'storeTableReload'
            });
            
            // 搜索功能
            window.searchData = function() {
                tableIns.reload({
                    where: {
                        dealer_id: $('#dealer_id').val(),
                        store_name: $('#store_name').val()
                    },
                    page: {
                        curr: 1
                    }
                });
            };
            
            // 添加门店
            window.addStore = function() {
                var dealerId = $('#dealer_id').val();
                var url = '/?s=/BarcodeInventory/add_store';
                if (dealerId) {
                    url += '&dealer_id=' + dealerId;
                }
                window.location.href = url;
            };
            
            // 监听工具条事件
            table.on('tool(storeTable)', function(obj) {
                var data = obj.data;
                var layEvent = obj.event;
                
                switch(layEvent) {
                    case 'edit':
                        // 编辑门店
                        window.location.href = '/?s=/BarcodeInventory/edit_store&id=' + data.id;
                        break;
                    case 'set_main':
                        // 设置主店
                        layer.confirm('<?php echo t('确认要将该门店设为主店吗？设置后，该经销商下的其他门店将自动设为非主店。'); ?>', {
                            icon: 3,
                            title: '<?php echo t('设置确认'); ?>',
                            btn: ['<?php echo t('确认'); ?>','<?php echo t('取消'); ?>']
                        }, function(index) {
                            layer.close(index);
                            
                            // 显示加载中
                            var loadingIndex = layer.load(2);
                            
                            $.ajax({
                                url: '/?s=/BarcodeInventory/set_main_store',
                                type: 'POST',
                                data: {id: data.id},
                                dataType: 'json',
                                success: function(res) {
                                    layer.close(loadingIndex);
                                    if (res.status == 1) {
                                        layer.msg(res.msg, {icon: 1, time: 1500}, function() {
                                            // 刷新表格
                                            tableIns.reload();
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
                        break;
                    case 'del':
                        // 删除门店
                        layer.confirm('<?php echo t('确认要删除该门店吗？'); ?>', {
                            icon: 3,
                            title: '<?php echo t('删除确认'); ?>',
                            btn: ['<?php echo t('确认'); ?>','<?php echo t('取消'); ?>']
                        }, function(index) {
                            layer.close(index);
                            
                            // 显示加载中
                            var loadingIndex = layer.load(2);
                            
                            $.ajax({
                                url: '/?s=/BarcodeInventory/del_store',
                                type: 'POST',
                                data: {id: data.id},
                                dataType: 'json',
                                success: function(res) {
                                    layer.close(loadingIndex);
                                    if (res.status == 1) {
                                        layer.msg(res.msg, {icon: 1, time: 1500}, function() {
                                            // 刷新表格
                                            tableIns.reload();
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
                        break;
                }
            });
        });
    </script>
</body>
</html>