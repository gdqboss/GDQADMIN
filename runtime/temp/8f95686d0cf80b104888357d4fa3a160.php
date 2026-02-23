<?php /*a:3:{s:61:"/www/wwwroot/gdqshop.cn/app/view/product_qrcode/generate.html";i:1768388615;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;}*/ ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>生成二维码</title>
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
    .qrcode-preview {
      margin: 20px 0;
      text-align: center;
    }
    .qrcode-preview img {
      max-width: 300px;
      max-height: 300px;
      border: 1px solid #eee;
    }
    .form-group {
      margin-bottom: 15px;
    }
    .form-label {
      display: inline-block;
      width: 100px;
      text-align: right;
      margin-right: 10px;
      vertical-align: top;
    }
    .form-control {
      display: inline-block;
      width: 300px;
    }
    .layui-form-item {
      margin-bottom: 15px;
    }
  </style>
</head>
<body>
  <div class="layui-fluid">
    <div class="layui-row layui-col-space15">
      <div class="layui-card layui-col-md12">
        <div class="layui-card-header">
          生成二维码
          <a href="<?php echo url('ProductQrcode/index'); ?>" class="layui-btn layui-btn-sm layui-btn-primary" style="float: right;">返回列表</a>
        </div>
        <div class="layui-card-body" pad15>
          <form class="layui-form form-label-w8" lay-filter="generateForm">
            <div class="layui-form-item">
              <label class="layui-form-label">商品</label>
              <div class="layui-input-inline" style="width: 300px;">
                <select name="product_id" lay-verify="required" lay-filter="productSelect">
                  <option value="">请选择商品</option>
                  <?php if(is_array($products) || $products instanceof \think\Collection || $products instanceof \think\Paginator): $i = 0; $__LIST__ = $products;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$product): $mod = ($i % 2 );++$i;?>
                  <option value="<?php echo $product['id']; ?>"><?php echo $product['name']; ?></option>
                  <?php endforeach; endif; else: echo "" ;endif; ?>
                </select>
              </div>
            </div>
            
            <div class="layui-form-item">
              <label class="layui-form-label">商品类型</label>
              <div class="layui-input-inline">
                <select name="product_type">
                  <option value="1">普通商品</option>
                  <option value="2">拼团商品</option>
                </select>
              </div>
            </div>
            
            <div class="layui-form-item">
              <label class="layui-form-label">规格</label>
              <div class="layui-input-inline" style="width: 300px;">
                <select name="specs_id" id="specsSelect">
                  <option value="">请选择规格</option>
                </select>
              </div>
            </div>
            
            <div class="layui-form-item">
              <label class="layui-form-label">客户姓名</label>
              <div class="layui-input-inline" style="width: 300px;">
                <input type="text" name="customer_name" placeholder="请输入客户姓名" class="layui-input">
              </div>
            </div>
            
            <div class="layui-form-item">
              <label class="layui-form-label">客户电话</label>
              <div class="layui-input-inline" style="width: 300px;">
                <input type="text" name="customer_phone" placeholder="请输入客户电话" class="layui-input">
              </div>
            </div>
            
            <div class="layui-form-item">
              <label class="layui-form-label">关联订单</label>
              <div class="layui-input-inline" style="width: 300px;">
                <input type="text" name="order_id" placeholder="请输入订单ID（可选）" class="layui-input">
              </div>
            </div>
            
            <div class="layui-form-item">
              <label class="layui-form-label">销售员</label>
              <div class="layui-input-inline" style="width: 300px;">
                <input type="text" name="salesman_id" placeholder="请输入销售员ID（可选）" class="layui-input">
              </div>
            </div>
            
            <div class="layui-form-item">
              <label class="layui-form-label">发货员</label>
              <div class="layui-input-inline" style="width: 300px;">
                <input type="text" name="delivery_id" placeholder="请输入发货员ID（可选）" class="layui-input">
              </div>
            </div>
            
            <div class="layui-form-item">
              <label class="layui-form-label">门店</label>
              <div class="layui-input-inline" style="width: 300px;">
                <input type="text" name="store_id" placeholder="请输入门店ID（可选）" class="layui-input">
              </div>
            </div>
            
            <div class="layui-form-item">
              <label class="layui-form-label">备注</label>
              <div class="layui-input-inline" style="width: 300px;">
                <textarea name="remark" placeholder="请输入备注信息（可选）" class="layui-textarea" rows="3"></textarea>
              </div>
            </div>
            
            <div class="layui-form-item">
              <div class="layui-input-block" style="margin-left: 110px;">
                <button class="layui-btn" lay-submit lay-filter="generate">生成二维码</button>
                <button type="reset" class="layui-btn layui-btn-primary">重置</button>
              </div>
            </div>
          </form>
          
          <!-- 二维码预览区域 -->
          <div id="qrcodePreview" class="qrcode-preview" style="display: none;">
            <h3>二维码预览</h3>
            <div id="qrcodeImage"></div>
            <div style="margin-top: 10px;">
              <a id="downloadBtn" class="layui-btn layui-btn-sm" href="" download>下载二维码</a>
              <a id="viewDetailBtn" class="layui-btn layui-btn-sm layui-btn-normal" href="">查看详情</a>
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
  <script>
    layui.use(['form', 'layer', 'jquery'], function() {
      var form = layui.form;
      var layer = layui.layer;
      var $ = layui.jquery;
      
      // 商品选择事件
      form.on('select(productSelect)', function(data) {
        var productId = data.value;
        if (productId) {
          // TODO: 根据商品ID获取规格列表
          // 这里可以通过AJAX请求获取规格列表
          console.log('获取商品规格:', productId);
        } else {
          $('#specsSelect').html('<option value="">请选择规格</option>');
          form.render('select');
        }
      });
      
      // 表单提交
      form.on('submit(generate)', function(data) {
        layer.load(2);
        
        // 发送AJAX请求生成二维码
        $.ajax({
          url: '<?php echo url("ProductQrcode/generate"); ?>',
          type: 'POST',
          data: data.field,
          dataType: 'JSON',
          success: function(res) {
            layer.closeAll('loading');
            if (res.code == 0) {
              layer.msg('二维码生成成功', {icon: 1});
              
              // 显示二维码预览
              $('#qrcodePreview').show();
              $('#qrcodeImage').html('<img src="' + res.data.qrcode_url + '" alt="二维码">');
              $('#downloadBtn').attr('href', res.data.qrcode_url);
              $('#viewDetailBtn').attr('href', '<?php echo url("ProductQrcode/detail"); ?>?id=' + res.data.id);
            } else {
              layer.msg('二维码生成失败：' + res.msg, {icon: 2});
            }
          },
          error: function() {
            layer.closeAll('loading');
            layer.msg('网络错误', {icon: 2});
          }
        });
        
        return false;
      });
    });
  </script>
</body>
</html>