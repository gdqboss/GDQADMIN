<?php /*a:4:{s:57:"/www/wwwroot/gdqshop.cn/app/view/designer_page/index.html";i:1747926690;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;s:54:"/www/wwwroot/gdqshop.cn/app/view/public/copyright.html";i:1747926690;}*/ ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>页面列表</title>
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
</head>
<body>
  <div class="layui-fluid">
    <div class="layui-row layui-col-space15">
        <div class="layui-card layui-col-md12">
          <div class="layui-card-header"><i class="fa fa-list"></i> 页面列表</div>
          <?php if(getcustom('design_template')): if($template_tip): ?>
	          	<blockquote class="layui-elem-quote">模板库设置页面时要使用通用选项和参数，否则其他账号或多商户使用后显示页面效果不明显</blockquote>
	          <?php endif; ?>
          <?php endif; ?>
          <div class="layui-card-body" pad15>
          	<?php if(getcustom('design_template')): if($tab): ?>
	          	<div class="layui-tab layui-tab-brief" style="margin-bottom: 20px">
								<ul class="layui-tab-title">
									<li <?php if(!$type): ?>class="layui-this"<?php endif; ?> onclick="location.href='<?php echo url('index'); ?>/type/0'">页面设计</li>
									<li <?php if($type == 1): ?>class="layui-this"<?php endif; ?> onclick="location.href='<?php echo url('index'); ?>/type/1'">模板库</li>
									<?php if(getcustom('design_template_cat_create')): if($template_auth == 2): ?>
										<li <?php if($type == 2): ?>class="layui-this"<?php endif; ?> onclick="location.href='<?php echo url('index'); ?>/type/2'">自己模板库</li>
										<?php endif; ?>
									<?php endif; ?>
								</ul>
							</div>
							<?php endif; ?>
						<?php endif; ?>
						<div class="layui-form layui-col-md12 layui-form-search" style="justify-content: flex-start">
							<?php if($auth['add']): ?>
							<a class="layui-btn layuiadmin-btn-list" href="javascript:void(0)" onclick="openmax2('<?php echo url('editnew'); ?><?php echo $typeparam; ?>')" style="margin-left:10px">添加</a>
							<?php endif; if(getcustom('design_cat')): ?>
								<div class="layui-inline layuiadmin-input-useradmin">
									<label class="layui-form-label">分类</label>
									<div class="layui-input-inline">
										<select name="cid">
											<option value="">全部</option>
											<?php foreach($cat_list as $cv): ?>
												<option value="<?php echo $cv['id']; ?>"><?php echo $cv['name']; ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								</div>
							<?php endif; ?>
							<div class="layui-inline layuiadmin-input-useradmin">
								<label class="layui-form-label">标题</label>
								<div class="layui-input-inline" style="width:200px">
									<input type="text" name="name" autocomplete="off" class="layui-input" value="">
								</div>
							</div>
							
							<div class="layui-inline">
								<button class="layui-btn layui-btn-primary layuiadmin-btn-replys" lay-submit="" lay-filter="LAY-app-forumreply-search">
									<i class="layui-icon layui-icon-search layuiadmin-button-btn"></i>
								</button>
							</div>
						</div>
						<div class="layui-col-md12">
							<div id="datalist"></div>
							<div class="layui-col-md12 layui-col-sm12">
									<div id="demo0"></div>
							</div>
						</div>
						<!-- <table id="tabledata" lay-filter="tabledata"></table> -->
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
	let element = $('#datalist');
	let width = element.width();
	let limit = Math.floor(width/285)*2;
	var wheredata = {op:'getdata',limit:limit,page:1};
	var pagecount = "<?php echo $count; ?>";
	getdata();
	function getdata(){
		var index =layer.load();
		$.get('',wheredata,function(res){
			layer.close(index);
			pagecount = res.count;
			var html = '';
			for(var i in res.data){
				var item = res.data[i]
				html+='<div style="float: left; margin:9px 10px; padding:12px; width: 250px; background-color: #fff; box-shadow: 0 0 2px #919dab;">';
				html+='	<h1 style="width: 250px; height: 20px; line-height: 20px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: normal; font-size:14px; color: #282828;" class="flex-y-center">'
				if(item.ishome=='2'){
				html+= '<span style="color:#fb6b5b">[会员中心]</span>'
				}
				if(item.ishome=='1'){
				html+= '<span style="color:#fb6b5b">[首页]</span>'
				}
				html+= item.name+'</h1>';
				html+='	<div style="height: 20px; line-height: 20px; margin-bottom: 6px; color: #282828; font-size: 12px;">'+date("Y-m-d H:i",item.createtime)+'</div>';
				html+='	<div class="" style="width: 250px; height:400px;overflow: hidden;">';
				html+='		<iframe style="height:400px; width: 248px; overflow-y: hidden;border:1px solid #eee" frameborder="0" src="<?php echo url('preview'); ?>/id/'+item.id+'<?php echo $typeparam; ?>" class="iframe_page" width="100%"></iframe>';
				html+='	</div>';
				html+='	<div class="clearfix" style="border-top:1px solid #e6ebf1; padding:13px 0 5px;height:13px;">';
				
				if(!item.type){
					if(item.ishome=='0'){
						<?php if($auth['del']): ?>
							html+='		<a href="javascript:void(0)" onclick="datadel('+item.id+')" class="fa fa-remove" style="text-decoration: none; float: right; font-size: 18px; padding:0 8px; color: #6c707f;" title="删除" rel="nofollow"></a>';
						<?php endif; ?>
					}
					
					html+='		<a href="javascript:void(0)" onclick="pagecopy('+item.id+')" class="fa fa-copy" style="text-decoration: none; float: right; font-size: 18px; padding:0 8px; color: #6c707f;" title="复制"></a>';
					html+='		<a href="javascript:void(0)" onclick="openmax2(\'<?php echo url('editnew'); ?>/id/'+item.id+'\')" class="fa fa-edit" style="text-decoration: none; float: right; font-size: 18px; padding:0 8px; color: #6c707f;" title="编辑"></a>';
					if(item.ishome=='0'){
						<?php if($auth['setHome']): ?>html+='		<a href="javascript:void(0)" onclick="sethome('+item.id+')" class="fa fa-home" style="text-decoration: none; float: right; font-size: 18px; padding:0 8px; color: #6c707f;" title="设为首页"></a>';<?php endif; ?>
					}
					html+='		<a href="javascript:void(0)" onclick="showqr('+item.id+','+item.ishome+')" class="fa fa-qrcode" style="text-decoration: none; float: right; font-size: 18px; padding:0 8px; color: #6c707f;" title="查看"></a>';
				}

				<?php if(getcustom('design_template')): ?>
					if(item.type == 1){
						if(item.ishome=='0'){
							<?php if($auth['del']): ?>
								html+='		<a href="javascript:void(0)" onclick="datadel('+item.id+',\'<?php echo $typeparam; ?>\')" class="fa fa-remove" style="text-decoration: none; float: right; font-size: 18px; padding:0 8px; color: #6c707f;" title="删除" rel="nofollow"></a>';
							<?php endif; ?>
						}
						<?php if($auth['copy']): ?>
						html+='		<a href="javascript:void(0)" onclick="pagecopy('+item.id+',\'<?php echo $type; ?>\',\'<?php echo $into; ?>\')" class="fa fa-save" style="text-decoration: none; float: right; font-size: 18px; padding:0 8px; color: #6c707f;" title="<?php echo $template_auth==1 ? '复制' : '创建页面'; ?>"></a>';
						<?php endif; if($auth['edit']): ?>
							html+='		<a href="javascript:void(0)" onclick="openmax2(\'<?php echo url('editnew'); ?>/id/'+item.id+'<?php echo $typeparam; ?>\')" class="fa fa-edit" style="text-decoration: none; float: right; font-size: 18px; padding:0 8px; color: #6c707f;" title="编辑"></a>'
						<?php endif; ?>
						html+='		<a href="javascript:void(0)" onclick="showqr('+item.id+','+item.ishome+',1)" class="fa fa-qrcode" style="text-decoration: none; float: right; font-size: 18px; padding:0 8px; color: #6c707f;" title="预览"></a>';
					}
				<?php endif; ?>
				html+='	</div>';
				html+='</div>';
			}
			$('#datalist').html(html);
			layui.laypage.render({
				elem: 'demo0',
				curr:wheredata.page,
				limit:wheredata.limit,
				layout:['prev', 'page', 'next','count'],
				count: pagecount, //数据总数
				jump:function(obj,first){
					if(!first){
						wheredata.page = obj.curr
						getdata();
					}
				}
			});
			layui.laypage.render({
				elem: 'demo1',
				curr:wheredata.page,
				limit:wheredata.limit,
				layout:['prev', 'page', 'next','count'],
				//count: res.count, //数据总数
				jump:function(obj,first){
					if(!first){
						wheredata.page = obj.curr
						getdata()
					}
				}
			});
		});
	}
	//检索
	layui.form.on('submit(LAY-app-forumreply-search)', function(obj){
		var field = obj.field
		wheredata.page = 1;
		wheredata.type  = <?php echo !empty($type) ? $type : '0'; ?>;
		wheredata.cid  = field.cid?field.cid:0;
		wheredata.name = field.name;
		if(field.remark){
			wheredata.remark = field.remark;
		}
		getdata()
	})
	function openedit(id){
		layer.open({type:2,content:"<?php echo url('edit'); ?>/id/"+id,title:false,area:['100%','100%'],closeBtn:0,scrollbar:false})
	}
	function pagecopy(id,type = 0,into=0){
		if(!type){
			var msg = '确定要复制吗?';
		}else{
			<?php if($template_auth && $template_auth>=1): ?>
			var msg = '确定要复制吗?';
			<?php else: ?>
			var msg = '确定要使用此模板吗?';
			<?php endif; ?>
		}
		layer.confirm(msg,{icon: 7, title:'操作确认'}, function(index){
			layer.close(index);
			var index = layer.load();
			$.post("<?php echo url('copy'); ?>",{id:id,type:type,into:into},function(data){
				layer.close(index);
				dialog(data.msg,data.status,location.href);
			})
		})
	}
	function rehome(id){
		layer.confirm('确定要取消首页设置吗?',{icon: 7, title:'操作确认'}, function(index){
			//do something
			layer.close(index);
			var index = layer.load();
			$.post("<?php echo url('rehome'); ?>",{id:id,ishome:1},function(data){
				layer.close(index);
				dialog(data.msg,data.status);
				reload()
			})
		});
	}
	//删除
	function datadel(id,param=''){
		if(id==0){
			var checkStatus = table.checkStatus('tabledata')
			var checkData = checkStatus.data; //得到选中的数据
			if(checkData.length === 0){
				 return layer.msg('请选择数据');
			}
			var id = [];
			for(var i=0;i<checkData.length;i++){
				id.push(checkData[i]['id']);
			}
		}
		if(param){
			var  go_url = "<?php echo url('del'); ?><?php echo $typeparam; ?>";
		}else{
			var  go_url = "<?php echo url('del'); ?>";
		}
		layer.confirm('确定要删除吗？删除后无法恢复！',{icon: 7, title:'操作确认'}, function(index){
			//do something
			layer.close(index);
			var index = layer.load();
			$.post(go_url,{id:id},function(data){
				layer.close(index);
				dialog(data.msg,data.status,location.href);
			})
		});
	}
	//设为首页
	function sethome(id){
		layer.open({type:0,content:'是否要将本页面设计为首页?',title:false,btn:['设为首页',<?php if($bid==0): ?>'设为会员中心',<?php endif; ?>'取消'],
			yes:function(){
				var index = layer.load();
				$.post("<?php echo url('sethome'); ?>",{id:id,ishome:1},function(data){
					layer.close(index);
					dialog(data.msg,data.status);
					reload()
				})
			},
		<?php if($bid==0): ?>
			btn2:function(){
				var index = layer.load();
				$.post("<?php echo url('sethome'); ?>",{id:id,ishome:2},function(data){
					layer.close(index);
					dialog(data.msg,data.status);
					reload()
				})
			}
		<?php endif; ?>
		});
	}
	function openmax2(url){
		window.open(url)
		// window.parent.location.href = url;
		// var index = layer.open({type:2,content:url,title:'111',closeBtn:0,scrollbar:false,moveOut:true,maxmin:true})
		// layer.full(index);
	}
	function showqr(id,ishome,type=0){
		if(ishome==1){
			if('<?php echo $bid; ?>'==0){
				var pagepath = 'pages/index/index';
			}else{
				var pagepath = 'pagesExt/business/index?id='+ <?php echo $bid; ?>;
			}
		}else if(ishome==2){
			var pagepath = 'pages/my/usercenter'
		}else{
			var pagepath = 'pages/index/main?id='+id
		}
		var url = "<?php echo m_url('"+pagepath+"'); ?>"; //拼接 H5 链接
		if(type == 1){
			//https://hexiao.com/h5/1.html#/pages/index/main?id=183
			var before = url.indexOf("h5/")+2;
			var after  = url.indexOf(".html");

			url = url.substring(0,before)+'/1'+url.substring(after);
			console.log(url)
		}
		viewLink(pagepath,url);
	}


	
	</script>
	
</body>
</html>