<?php /*a:4:{s:55:"/www/wwwroot/gdqshop.cn/app/view/mendian_set/index.html";i:1747926690;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;s:54:"/www/wwwroot/gdqshop.cn/app/view/public/copyright.html";i:1747926690;}*/ ?>
<!DOCTYPE html><!--custom_file(mendian_upgrade)-->
<html>
<head>
  <meta charset="utf-8">
  <title>系统设置</title>
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
		.layui-form-item .layui-form-label{width: 200px;}
	</style>
</head>
<body>
  <div class="layui-fluid">
    <div class="layui-row layui-col-space15">
      <div class="layui-card layui-col-md12">
				<div class="layui-card-header"><i class="fa fa-cog"></i> 系统设置</div>
				<div class="layui-card-body" pad15>
					<div class="layui-form" lay-filter="">

						<div class="layui-form-item">
							<label class="layui-form-label"><?php echo t('门店'); ?>申请开关：</label>
							<div class="layui-input-inline" style="width:200px">
								<input type="radio" name="info[apply_status]" value="1" title="开启" <?php if($info['apply_status']==1): ?>checked<?php endif; ?>/>
								<input type="radio" name="info[apply_status]" value="0" title="关闭" <?php if($info['apply_status']==0): ?>checked<?php endif; ?>/>
							</div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label"><?php echo t('门店'); ?>服务距离：</label>
							<div class="layui-input-inline" style="width:120px">
								<input type="text" name="info[fwdistance]" value="<?php echo $info['fwdistance']; ?>" class="layui-input"/>
							</div>
							<div class="layui-form-mid">km</div>
							<div class="layui-form-mid layui-word-aux" style="margin-left:10px;"><?php echo t('门店'); ?>服务距离 0为不限制</div>
						</div>
					
						<div class="layui-form-item">
							<label class="layui-form-label">前端添加核销员：</label>
							<div class="layui-input-inline" >
								<input type="radio" name="info[addhxuser_status]" value="1" title="开启" <?php if($info['addhxuser_status']==1): ?>checked<?php endif; ?>/>
								<input type="radio" name="info[addhxuser_status]" value="0" title="关闭" <?php if($info['addhxuser_status']==0): ?>checked<?php endif; ?>/>
							</div>
						</div>
					
						<div class="layui-form-item">
							<label class="layui-form-label">显示详细地址：</label>
							<div class="layui-input-inline" >
								<input type="radio" name="info[showaddress_status]" value="1" title="开启" <?php if($info['showaddress_status']==1): ?>checked<?php endif; ?>/>
								<input type="radio" name="info[showaddress_status]" value="0" title="关闭" <?php if($info['showaddress_status']==0): ?>checked<?php endif; ?>/>
							</div>
						</div>
					
						<div class="layui-form-item">
							<label class="layui-form-label">订单通知：</label>
							<div class="layui-input-inline" >
								<input type="radio" name="info[notice_status]" value="1" title="开启" <?php if($info['notice_status']==1): ?>checked<?php endif; ?>/>
								<input type="radio" name="info[notice_status]" value="0" title="关闭" <?php if($info['notice_status']==0): ?>checked<?php endif; ?>/>
							</div>
						</div>
							
						<div class="layui-form-item">
							<label class="layui-form-label">显示<?php echo t('门店'); ?>等级：</label>
							<div class="layui-input-inline" >
								<input type="radio" name="info[showLevel_status]" value="1" title="开启" <?php if($info['showLevel_status']==1): ?>checked<?php endif; ?>/>
								<input type="radio" name="info[showLevel_status]" value="0" title="关闭" <?php if($info['showLevel_status']==0): ?>checked<?php endif; ?>/>
							</div>
						</div>
						
						<div class="layui-form-item" id="setLevel_status" <?php if(isset($info['setLevel_status']) && $info['setLevel_status']==0): ?>style="display:none"<?php endif; ?>>
							<label class="layui-form-label">设置<?php echo t('会员'); ?>等级：</label>
							<div class="layui-input-inline" >
									<select name="info[member_levelid]" lay-verify="required" class="levelid">
										<?php foreach($levelList as $v): ?>
										<option value="<?php echo $v['id']; ?>" <?php if($v['id'] == $info['member_levelid']): ?>selected<?php endif; ?>><?php echo $v['name']; ?></option>
										<?php endforeach; ?>
									</select>
							</div>
						</div>

						<div class="layui-form-item">
							<label class="layui-form-label">未发货订单核销：</label>
							<div class="layui-input-inline" >
								<input type="radio" name="info[hexiao_status]" value="1" title="开启" <?php if($info['hexiao_status']==1): ?>checked<?php endif; ?>/>
								<input type="radio" name="info[hexiao_status]" value="0" title="关闭" <?php if($info['hexiao_status']==0): ?>checked<?php endif; ?>/>
							</div>
							<div class="layui-form-mid layui-word-aux" style="margin-left:10px;">开启后，<?php echo t('门店'); ?>端可以核销未发货的订单</div>
						</div>
						
						<div id="can_commission">
							<span style="color:#333"><?php echo t('门店'); ?>分销</span><hr/>
							<div class="layui-form-item">
								<label class="layui-form-label">分销权限：</label>
								<div class="layui-input-inline" style="width:70%">
									<input type="radio" name="info[can_agent]" value="0" title="无权限" <?php if($info['can_agent']==0 || $info['can_agent']==''): ?>checked<?php endif; ?> lay-filter="can_agent">
									<input type="radio" name="info[can_agent]" value="1" title="一级" <?php if($info['can_agent']==1): ?>checked<?php endif; ?> lay-filter="can_agent">
									<input type="radio" name="info[can_agent]" value="2" title="二级" <?php if($info['can_agent']==2): ?>checked<?php endif; ?> lay-filter="can_agent">
									<input type="radio" name="info[can_agent]" value="3" title="三级" <?php if($info['can_agent']==3): ?>checked<?php endif; ?> lay-filter="can_agent">
								</div>
								<div class="layui-form-mid layui-word-aux ">开启则表示该级别的<?php echo t('门店'); ?>有分销权限，可以发展下级，拿相应提成</div>
							</div>
		

							<div class="layui-form-item" id="can_agentset"  style="border:0px dashed #f0cccc;padding-top:10px;margin-bottom:20px;<?php if($info['can_agent']==0 || $info['can_agent']==''): ?>display:none<?php endif; ?>">
								<div class="layui-form-item">
									<label class="layui-form-label">提成方式：</label>
									<div class="layui-input-inline" style="width:70%">
										<input type="radio" class="canAgentF-radio" name="info[commissiontype]" value="0" title="百分比" <?php if($info['commissiontype']==0): ?>checked<?php endif; ?>  lay-filter="commissiontype">
										<input type="radio" class="canAgentF-radio" name="info[commissiontype]" value="1" title="固定金额" <?php if($info['commissiontype']==1): ?>checked<?php endif; ?>  lay-filter="commissiontype">
									</div>
									<div class="layui-form-mid layui-word-aux">设置固定金额时按单返<?php echo t('佣金'); ?>，和购买数量无关</div>
								</div>


								<div class="layui-form-item" id="tichengBox1" <?php if($info['commissiontype']==2): ?>style="display:none;"<?php endif; ?>>
									<label class="layui-form-label">提成金额：</label>
									<div class="layui-input-inline layui-module-itemL" commission1>
										<div commission1 >一级(<span class="commissionunit"><?php echo $info['commissiontype']==1 ? '元' : '%'; ?></span>) </div>
										<input type="text" name="info[commission1]" class="layui-input" value="<?php echo $info['commission1']; ?>">
									</div>
									<div class="layui-input-inline layui-module-itemL" commission2 style="<?php if($info['can_agent']<2): ?>display:none;<?php endif; ?>">
										<div commission2 style="<?php if($info['can_agent']<2): ?>display:none;<?php endif; ?>">二级(<span class="commissionunit"><?php echo $info['commissiontype']==1 ? '元' : '%'; ?></span>)</div>
										<input type="text" name="info[commission2]" class="layui-input" value="<?php echo $info['commission2']; ?>">
									</div>
									<div class="layui-input-inline layui-module-itemL" commission3 style="<?php if($info['can_agent']<3): ?>display:none;<?php endif; ?>">
											<div commission3 style="<?php if($info['can_agent']<3): ?>display:none;<?php endif; ?>">三级(<span class="commissionunit"><?php echo $info['commissiontype']==1 ? '元' : '%'; ?></span>)</div>
										<input type="text" name="info[commission3]" class="layui-input" value="<?php echo $info['commission3']; ?>">
									</div>
								</div>
							</div>
						</div>
						
						<div class="layui-form-item">
							<label class="layui-form-label"></label>
							<div class="layui-input-block">
								<button class="layui-btn" lay-submit lay-filter="formsubmit">提 交</button>
							</div>
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
	
	layui.form.on('radio(can_agent)', function(data){
		if(data.value != '0'){
			if(data.value=='1'){
				$('*[commission2]').hide();
				$('[commission3]').hide();
			}else if(data.value=='2'){
				$('*[commission2]').show();
				$('[commission3]').hide();
			}else if(data.value=='3'){
				$('*[commission2]').show();
				$('[commission3]').show();
			}
			$('#can_agentset').show();
		}else{
			$('#can_agentset').hide();
		}
	})
	layui.form.on('radio(commissiontype)', function(data){
		if(data.value == '1'){
			$('.commissionunit').html('元');
		}else{
			$('.commissionunit').html('%');
		}
		if(data.value==2){
			$('#tichengBox2').show();
			$('#tichengBox1').hide();
		}else {
			$('#tichengBox1').show();
			$('#tichengBox2').hide();
		}
	})
	layui.form.on('submit(formsubmit)', function(obj){
		var field = obj.field
		$.post('',field,function(data){
			dialog(data.msg,data.status,data.url);
		})
	})
    </script>
   
</body>
</html>