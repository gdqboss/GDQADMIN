<?php /*a:4:{s:52:"/www/wwwroot/gdqshop.cn/app/view/mendian/index2.html";i:1747926690;s:48:"/www/wwwroot/gdqshop.cn/app/view/public/css.html";i:1747926690;s:47:"/www/wwwroot/gdqshop.cn/app/view/public/js.html";i:1747926690;s:54:"/www/wwwroot/gdqshop.cn/app/view/public/copyright.html";i:1747926690;}*/ ?>
<!DOCTYPE html><!--custom_file(mendian_upgrade)-->
<html>
<head>
  <meta charset="utf-8">
  <title><?php echo t('门店'); ?>管理</title>
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
          <div class="layui-card-header"><?php echo t('门店'); ?>管理</div>
          <div class="layui-card-body" pad15>
			<blockquote class="layui-elem-quote">用于自提订单选择自提<?php echo t('门店'); ?>进行到店自提</blockquote>
			<div class="layui-col-md5" style="padding-bottom:10px">
				<a class="layui-btn layuiadmin-btn-list" href="javascript:void(0)" onclick="openmax('<?php echo url('edit'); ?>')">添加</a>
				<button class="layui-btn layui-btn-primary layuiadmin-btn-list" onclick="datadel(0)">删除</button>
				<button class="layui-btn layui-btn-primary layuiadmin-btn-list" onclick="setst(0,1)">开启</button>
				<button class="layui-btn layui-btn-primary layuiadmin-btn-list" onclick="setst(0,0)">关闭</button>
				<button class="layui-btn layui-btn-primary layuiadmin-btn-list" onclick="editgroup(0)">修改分组</button>
				<button class="layui-btn layui-btn-primary layuiadmin-btn-list" onclick="editlevel(0)">修改等级</button>
				<button class="layui-btn layui-btn-primary layuiadmin-btn-list" onclick="location.href='<?php echo url('excel'); ?>'+urlEncode(datawhere)">导出</button>
			</div>  
			<div class="layui-form layui-form-search layui-col-md7 ">
				<div class="layui-inline">
					<div class="layui-input-inline" style="width:200px">
						<input type="text" name="name" placeholder="<?php echo t('门店'); ?>姓名/手机号/小区名称" autocomplete="off" class="layui-input">
					</div>
				</div>
				<div class="layui-inline">
					<div class="layui-input-inline">
						<select name="levelid">
							<option value=""><?php echo t('门店'); ?>等级</option>
							<?php foreach($levelarr as $k=>$v): ?>
							<option value="<?php echo $k; ?>"><?php echo $v; ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="layui-inline">
					<div class="layui-input-inline">
						<select name="groupid">
							<option value=""><?php echo t('门店'); ?>分组</option>
							<?php foreach($grouparr as $k=>$v): ?>
							<option value="<?php echo $k; ?>"><?php echo $v; ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="layui-inline">
					<label class="layui-form-label">审核状态</label>
					<div class="layui-input-inline">
						<select name="check_status">
							<option value="">全部</option>
							<option value="0">待审核</option>
							<option value="1">已通过</option>
							<option value="2">已驳回</option>
						</select>
					</div>
				</div>
				<div class="layui-inline">
					<label class="layui-form-label">状态</label>
					<div class="layui-input-inline">
						<select name="status">
							<option value="">全部</option>
							<option value="1">已开启</option>
							<option value="0">已关闭</option>
						</select>
					</div>
				</div>
				<div class="layui-inline">
					<button class="layui-btn layuiadmin-btn-replys" lay-submit="" lay-filter="LAY-app-forumreply-search">
						<i class="layui-icon layui-icon-search layuiadmin-button-btn"></i>
					</button>
				</div>
			</div>
			<div class="layui-col-md12">
				<table id="tabledata" lay-filter="tabledata"></table>
			</div>
		  </div>
  		</div>
  	</div>
  </div>

  <div class="layui-form" lay-filter="" style="margin-top:20px; display: none" id="selectGroup">
	  <div class="layui-form-item">
		  <label class="layui-form-label">分组：</label>
		  <div class="layui-input-inline" style="width:400px">
			  <select name="groupidval" id="groupid" >
				  <option value="">--请选择--</option>
				  <?php foreach($grouparr as $k=>$cv): ?>
				  <option value="<?php echo $k; ?>" ><?php echo $cv; ?></option>
				  <?php endforeach; ?>
			  </select>
		  </div>
	  </div>
  </div>
  <div class="layui-form" lay-filter="" style="margin-top:20px; display: none" id="selectLevel">
	  <div class="layui-form-item">
		  <label class="layui-form-label">等级：</label>
		  <div class="layui-input-inline" style="width:400px">
			  <select name="levelidval" id="levelid" >
				  <option value="">--请选择--</option>
				  <?php foreach($levelarr as $k=>$cv): ?>
				  <option value="<?php echo $k; ?>" ><?php echo $cv; ?></option>
				  <?php endforeach; ?>
			  </select>
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
  var table = layui.table;
	var datawhere = {};
  //数据表
  var tableIns = table.render({
    elem: '#tabledata'
    ,url: "<?php echo app('request')->url(); ?>" //数据接口
    ,page: true //开启分页
    ,cols: [[ //表头
			{type:"checkbox"},
      {field: 'id', title: 'ID',  sort: true,width:80},
      {field: 'xqname', title: '小区名称',templet:function(d){ 
		  var html='<div style="display:flex">';
			  html+='	<div>'
			  html+='		<div>'+d.xqname+'</div>';
			  html+='		<a style="font-size:12px;color:0078D7" onclick="showurl2('+d.mid+',\''+d.type+'\')" >推广二维码</a>';
			  html+='    </div>';
			  html+='</div>';
		  return html;
	  
	  }}, 
      {field: 'pic', title: '<?php echo t('门店'); ?>信息',templet:function(d){ 
		  var html='<div style="display:flex">';
			  html+='	<div style="margin-right:10px"><img src="'+d.pic+'" style="width:50px;border-radius:50%"/></div>';
			  html+='	<div>'
			  html+='		<div>名称:'+d.name+'</div>';
			  html+='		<div>昵称:'+d.nickname+'</div>';
			  html+='		<div>当前<?php echo t('会员'); ?>数量:'+d.membernum+'</div>';
			  html+='		<div>等级:'+d.levelname+'</div>';
			  html+='		<div>'+d.groupname+'</div>';
			  html+='    </div>';
			  html+='</div>';
		  return html;
	  
	  },width:200},
	  {field: 'commission', title: '佣金',templet:function(d){
		 var html='<div >';
			  html+='	<div>可用佣金:'+d.money+'</div>';
			  html+='	<div>已提现:'+d.dkmoney+'</div>';
			  html+='	<div>总收入'+d.totalmoney+'</div>';
			  html+='</div>';
		  return html;
	  }},

	 {field: 'parent', title: '推荐信息',templet:function(d){
		 var html='<div >';
			  html+='	<div>上级<?php echo t('门店'); ?>:'+d.parentname+'</div>';
			  html+='	<div>直推<?php echo t('门店'); ?>:'+d.tjtotal+'</div>';
			  html+='</div>';
		  return html;
	  }},
      {field: 'address', title: '<?php echo t('门店'); ?>电话/地址',templet:function(d){return '<div>'+d.tel+'</div><div>'+d.address+'</div>';}},

   	  {field: 'createtime', title: '申请时间',templet:function(d){
		 var html='<div >';
			  html+='	<div>'+date('Y-m-d H:i',d.createtime)+'</div>'
			  html+='</div>';
		  return html;
	  }},
	  {field: 'check_status', title: '审核状态',templet:function(d){ 
			if(d.check_status==0) return '<span style="color:blue">待审核</span>';
			if(d.check_status==1) return '<span style="color:green">已通过</span>';
			if(d.check_status==2) return '<span style="color:red">已驳回</span>';
		},width:80},
      {field: 'status', title: '状态',templet:function(d){ 
				if(d.status==0){
					return '<span style="color:red">已关闭</span>';
				}else if(d.status==1){
					return '<span style="color:green">已开启</span>';
				}
			},width:80},
      {field: 'operation', title: '操作',templet:function(d){
			var html='';
			html+='<div>';
			html+='<button class="table-btn"  onclick="openmax(\'<?php echo url('edit'); ?>/id/'+d.id+'\')">编辑</button>';
			html+='<button class="table-btn" onclick="datadel('+d.id+')">删除</button></div>';
			if(d.check_status == 0 || d.check_status == 2){
				html += '<button class="table-btn" onclick="setcheckst(\''+d.id+'\',1)">通过</button>';
			}
			if(d.check_status == 0 || d.check_status == 1){
				html += '<button class="table-btn" onclick="setcheckst(\''+d.id+'\',2)">驳回</button>';
			}
			html+='<button class="table-btn"  onclick="openmax(\'<?php echo url('ShopOrder/index'); ?>/isopen/1/mdid/'+d.id+'\')">推广订单</button>';
			html+='<button class="table-btn"  onclick="openmax(\'<?php echo url('MendianMoney/moneylog'); ?>/isopen/1/mdid/'+d.id+'\')" >收益明细</button>';
			html+='<button class="table-btn" onclick="openmax(\'<?php echo url('ShopProduct/index'); ?>/isopen/1/mdid/'+d.id+'\')">查看在售商品</button>';
			html+='<button class="table-btn" onclick="openmax(\'<?php echo url('MendianHxuser/index'); ?>/isopen/1/mdid/'+d.id+'\')">查看核销人员</button>';
			html += '<button class="table-btn" id="yj" onclick="addcommission('+d.id+')">充值佣金</button>';
			
			html+='</div>';
			return html;
		},width:150}
    ]]
  });
	//排序
	table.on('sort(tabledata)', function(obj){
		datawhere.field = obj.field;
		datawhere.order = obj.type;
		tableIns.reload({
			initSort: obj,
			where: datawhere
		});
	});
	//检索
	layui.form.on('submit(LAY-app-forumreply-search)', function(obj){
		var field = obj.field
		var olddatawhere = datawhere
		datawhere = field
		datawhere.field = olddatawhere.field
		datawhere.order = olddatawhere.order
		tableIns.reload({
			where: datawhere,
			page: {curr: 1}
		});
	})
	
	//删除
	function datadel(id){
		var ids = [];
		if(id==0){
			var checkStatus = table.checkStatus('tabledata')
			var checkData = checkStatus.data; //得到选中的数据
			if(checkData.length === 0){
				 return layer.msg('请选择数据');
			}
			for(var i=0;i<checkData.length;i++){
				ids.push(checkData[i]['id']);
			}
		}else{
			ids.push(id);
		}
		layer.confirm('确定要删除吗？删除后无法恢复！',{icon: 7, title:'操作确认'}, function(index){
			//do something
			layer.close(index);
			var index = layer.load();
			$.post("<?php echo url('del'); ?>",{ids:ids},function(data){
				layer.close(index);
				dialog(data.msg,data.status);
				tableIns.reload()
			})
		});
	}
	//上下架
	function setst(id,st){
		var ids = [];
		if(id==0){
			var checkStatus = table.checkStatus('tabledata')
			var checkData = checkStatus.data; //得到选中的数据
			if(checkData.length === 0){
				 return layer.msg('请选择数据');
			}
			for(var i=0;i<checkData.length;i++){
				ids.push(checkData[i]['id']);
			}
		}else{
			ids.push(id);
		}
		layer.confirm('确定要'+(st==0?'关闭':'开启')+'吗?',{icon: 7, title:'操作确认'}, function(index){
			//do something
			layer.close(index);
			var index = layer.load();
			$.post("<?php echo url('setst'); ?>",{ids:ids,st:st},function(data){
				layer.close(index);
				dialog(data.msg,data.status);
				tableIns.reload()
			})
		});
	}

	//修改分组
	function editgroup(id){
		var ids = [];
		if(id==0){
			var checkStatus = table.checkStatus('tabledata')
			var checkData = checkStatus.data; //得到选中的数据
			if(checkData.length === 0){
				 return layer.msg('请选择数据');
			}
			for(var i=0;i<checkData.length;i++){
				ids.push(checkData[i]['id']);
			}
		}else{
			ids.push(id);
		}
		var checkLayer = layer.open({type:1,area:['600px','400px'],title:'批量修改分组',content:$("#selectGroup"),shadeClose:true,btn: ['确定', '取消'],
			yes:function(){
				var groupid = $("#groupid").val(); 
				if(!groupid){
					dialog('请选择分组');
					return false;
				}
				var index = layer.load();
				$.post("<?php echo url('editGroup'); ?>",{ids:ids,groupid:groupid},function(res){
					layer.close(index);
					dialog(res.msg,res.status);
					layer.close(checkLayer);
					tableIns.reload();
				})
			}
		});
		layui.form.render();
	}


	//修改等级
	function editlevel(id){
		var ids = [];
		if(id==0){
			var checkStatus = table.checkStatus('tabledata')
			var checkData = checkStatus.data; //得到选中的数据
			if(checkData.length === 0){
				 return layer.msg('请选择数据');
			}
			for(var i=0;i<checkData.length;i++){
				ids.push(checkData[i]['id']);
			}
		}else{
			ids.push(id);
		}
		var checkLayer = layer.open({type:1,area:['600px','400px'],title:'批量修改等级',content:$("#selectLevel"),shadeClose:true,btn: ['确定', '取消'],
			yes:function(){
				var levelid = $("#levelid").val(); 
				if(!levelid){
					dialog('请选择等级');
					return false;
				}
				var index = layer.load();
				$.post("<?php echo url('editLevel'); ?>",{ids:ids,levelid:levelid},function(res){
					layer.close(index);
					dialog(res.msg,res.status);
					layer.close(checkLayer);
					tableIns.reload();
				})
			}
		});
		layui.form.render();
	}


	function getpaycode(id,bid){
		var pagepath = 'pagesB/maidan/pay?mdid='+id+'&bid='+bid;
		$.post("<?php echo url('getpaycode'); ?>",{id:id,bid:bid,type:'qrmp'},function(res){
			if(res.status==0){
				layer.open({type:1,area:['300px','350px'],content:'<div style="margin:auto auto;text-align:center"><div style="color:red;width:280px;height:180px;margin-top:100px">'+res.msg+'</div><button class="layui-btn layui-btn-sm layui-btn-primary" onclick="showwxpaycode(\''+id+'\',\''+bid+'\')">查看小程序码</button><div style="height:25px;line-height:25px;">'+pagepath+'</div></div>',title:false,shadeClose:true})
			}else{
				layer.open({type:1,area:['350px','400px'],content:'<div style="margin:auto auto;text-align:center"><img src="'+res.paycodepic+'" style="margin-top:20px;max-width:280px;max-height:280px"  onclick="preview(this)" /><div style="margin-top:10px"><button class="layui-btn layui-btn-sm layui-btn-primary" onclick="showwxpaycode(\''+id+'\',\''+bid+'\')">查看小程序码</button></div><div style="height:25px;line-height:25px;">'+pagepath+'</div></div>',title:false,shadeClose:true})
			}
		})
	}
	// 查看小程序码
	function showwxpaycode(id,bid){
		var index = layer.load();
		var pagepath = 'pagesB/maidan/pay?mdid='+id+'&bid='+bid;
		console.log(pagepath);
		$.post("<?php echo url('getpaycode'); ?>",{id:id,bid:bid,type:'qrwx'},function(res){
			layer.close(index);
			if(res.status==0){
				layer.open({type:1,area:['300px','350px'],content:'<div style="margin:auto auto;text-align:center"><div style="color:red;width:280px;height:180px;margin-top:100px">'+res.msg+'</div><div style="height:25px;line-height:25px;">'+pagepath+'</div></div>',title:false,shadeClose:true})
			}else{
				layer.open({type:1,area:['300px','350px'],content:'<div style="margin:auto auto;text-align:center"><img src="'+res.paycodepic+'" style="margin-top:20px;max-width:280px;max-height:280px"/><div style="height:25px;line-height:25px;">'+pagepath+'</div></div>',title:false,shadeClose:true})
			}
		})
	}
  	function showurl(id){
		var pagepath = 'restaurant/shop/index?tableId=0';
		pagepath = pagepath + '&bid=' + <?php echo $bid; ?>;
		pagepath = pagepath + '&mdid=' + id;	  
		viewLink(pagepath);
	  }
	//审核
	function setcheckst(id,st){
		if(st == 2){
			var html = '';
			html+='	<div class="layui-form-item" style="margin-top:40px;margin-right:20px;">';
			html+='		<label class="layui-form-label" style="width:80px">驳回原因</label>';
			html+='		<div class="layui-input-inline" style="width:350px">';
			html+='			<textarea type="text" id="check_reason" class="layui-textarea"></textarea>';
			html+='		</div>';
			html+='	</div>';
			var checkLayer = layer.open({type:1,area:['500px','250px'],title:false,content:html,shadeClose:true,btn: ['确定', '取消'],
				yes:function(){
					var index = layer.load();
					$.post("<?php echo url('setcheckst'); ?>",{id:id,st:st,reason:$('#check_reason').val()},function(res){
						layer.close(index);
						dialog(res.msg,res.status);
						layer.close(checkLayer);
						tableIns.reload()
					})
				}
			})
		}else{
			layer.confirm('确定要审核通过吗?',{icon: 7, title:'操作确认'}, function(index){
				layer.close(index);
				var index = layer.load();
				$.post("<?php echo url('setcheckst'); ?>",{id:id,st:st},function(data){
					layer.close(index);
					dialog(data.msg,data.status);
					tableIns.reload()
				})
			});
		}
	}
	function showurl2(id,type){
	   var pagepath = 'pages/index/index?pid='+id;
		viewLink(pagepath);
	}


		//导出
	function dataexcel(){
		var ids = '';
		var checkStatus = table.checkStatus('tabledata')
		var checkData = checkStatus.data; //得到选中的数据

		for(var i=0;i<checkData.length;i++){
			ids = ids+','+checkData[i]['id'];
		}
		datawhere.ids = ids;
		console.log(datawhere);
		//return false;
		window.location.href='<?php echo url('excel'); ?>'+urlEncode(datawhere)
	}
	function addcommission(id){
		var html = '';
		html+='	<div class="layui-form-item" style="margin-top:40px;margin-right:20px;">';
		html+='		<label class="layui-form-label" style="width:80px">增加金额：</label>';
		html+='		<div class="layui-input-inline" style="width:200px">';
		html+='			<input type="text" id="addcommissionnum" class="layui-input"/>';
		html+='		</div>';
		html+='		<div class="layui-form-mid layui-word-aux">输入负值表示扣除<?php echo t('佣金'); ?></div>';
		html+='	</div>';
		html+='	<div class="layui-form-item" style="margin-top:40px;margin-right:20px;">';
		html+='		<label class="layui-form-label" style="width:80px">备注信息：</label>';
		html+='		<div class="layui-input-inline" style="width:350px">';
		html+='			<input type="text" id="addcommissionremark" class="layui-input"/>';
		html+='		</div>';
		html+='	</div>';
		var addscoreLayer = layer.open({type:1,area:['500px','300px'],title:false,content:html,shadeClose:true,btn: ['确定', '取消'],
			yes:function(){
				var index = layer.load();
				$.post("<?php echo url('addcommission'); ?>",{id:id,commission:$('#addcommissionnum').val(),remark:$('#addcommissionremark').val()},function(res){
					layer.close(index);
					dialog(res.msg,res.status);
					layer.close(addscoreLayer);
					tableIns.reload()
				})
			}
		})
	}
	</script>
	
</body>
</html>