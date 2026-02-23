<?php /*a:1:{s:71:"/www/wwwroot/gdqshop.cn/app/view/shop_product/choose_store_product.html";i:1768762501;}*/ ?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>选择仓库商品</title>
	<meta name="renderer" content="webkit">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=0">
	<!-- 引用CSS资源 -->
	<link rel="stylesheet" type="text/css" href="//static/admin/layui/css/layui.css?v=20200519" media="all">
	<link rel="stylesheet" type="text/css" href="//static/admin/layui/css/modules/formSelects-v4.css?v=20200516" media="all">
	<link rel="stylesheet" type="text/css" href="//static/admin/css/admin.css?v=202406" media="all">
	<link rel="stylesheet" type="text/css" href="//static/admin/css/font-awesome.min.css?v=20200516" media="all">
	<style>
		.product-item {
			border: 1px solid #e6e6e6;
			padding: 10px;
			margin-bottom: 10px;
			border-radius: 5px;
		}
		.product-name {
			font-size: 16px;
			font-weight: bold;
			margin-bottom: 5px;
		}
		.product-stock {
			color: #666;
			margin-bottom: 10px;
		}
		.guige-list {
			display: flex;
			flex-wrap: wrap;
			margin-top: 10px;
		}
		.guige-item {
			border: 1px solid #e6e6e6;
			padding: 5px 10px;
			margin-right: 10px;
			margin-bottom: 5px;
			border-radius: 3px;
			cursor: pointer;
		}
		.guige-item:hover {
			border-color: #1E9FFF;
		}
	</style>
</head>
<body>
<div class="layui-fluid">
	<div class="layui-row layui-col-space15">
		<div class="layui-card">
			<div class="layui-card-header">
				<div class="layui-input-inline">
					<input type="text" id="proname" placeholder="搜索商品名称" class="layui-input">
				</div>
				<button class="layui-btn layui-btn-primary" id="searchBtn">搜索</button>
			</div>
			<div class="layui-card-body">
				<!-- 使用Layui表格 -->
				<table class="layui-hide" id="productTable" lay-filter="productTable"></table>
			</div>
		</div>
	</div>
</div>

<!-- 引用JavaScript资源 -->
<script type="text/javascript" src="//static/admin/layui/layui.js?v=20200519"></script>
<script type="text/javascript" src="//static/admin/js/jquery.min.js?v=20200519"></script>
<script type="text/javascript" src="//static/admin/js/ajax.js?v=20200519"></script>
<script type="text/javascript" src="//static/admin/js/common.js?v=20200519"></script>
<script>
	layui.use(['table', 'layer'], function() {
		var table = layui.table;
		var layer = layui.layer;
		var $ = layui.jquery;
		
		// 渲染表格
		var tableIns = table.render({
			elem: '#productTable',
			url: '/?s=/ShopProduct/getStoreProductList',
			method: 'GET',
			where: {
				proname: $('#proname').val()
			},
			page: true,
			limit: 10,
			limits: [10, 20, 30, 50],
			cols: [[
				{field: 'id', title: 'ID', width: 80, align: 'center'},
				{field: 'name', title: '商品名称', minWidth: 200},
				{field: 'stock', title: '库存', width: 100, align: 'center'},
				{title: '规格', width: 300, align: 'center', templet: function(d) {
					var html = '';
					if (d.guige && d.guige.length > 0) {
						d.guige.forEach(function(gg) {
							html += '<button type="button" class="layui-btn layui-btn-xs layui-btn-primary choose-guige" ' +
								'data-proid="' + d.id + '" ' +
								'data-ggid="' + gg.id + '" ' +
								'data-proname="' + d.name + '" ' +
								'data-ggname="' + gg.name + '" ' +
								'data-stock="' + gg.stock + '" ' +
								'data-barcode="' + (gg.barcode || '') + '">' +
								gg.name + ' (库存: ' + gg.stock + ')'
							'</button> ';
						});
					} else {
						html += '<span class="layui-badge layui-bg-gray">无规格</span>';
					}
					return html;
				}},
				{title: '操作', width: 120, align: 'center', templet: function(d) {
					if (d.guige && d.guige.length > 0) {
						return '<span class="layui-badge layui-bg-green">可选择</span>';
					} else {
						return '<span class="layui-badge layui-bg-red">不可选择</span>';
					}
				}}
			]],
			done: function(res, curr, count) {
				if (count === 0) {
					// 如果没有数据，显示提示
					$('#productTable').next().find('.layui-table-main').html('<div style="text-align: center; padding: 50px; color: #999;">暂无仓库商品数据</div>');
				}
			}
		});
		
		// 搜索功能
		$('#searchBtn').on('click', function() {
			tableIns.reload({
				where: {
					proname: $('#proname').val()
				},
				page: {
					curr: 1
				}
			});
		});
		
		// 回车搜索
		$('#proname').on('keydown', function(e) {
			if (e.keyCode == 13) {
				$('#searchBtn').click();
			}
		});
		
		// 事件委托 - 选择规格
		$(document).on('click', '.choose-guige', function() {
			var proid = $(this).data('proid');
			var ggid = $(this).data('ggid');
			var proname = $(this).data('proname');
			var ggname = $(this).data('ggname');
			var stock = $(this).data('stock');
			var barcode = $(this).data('barcode');
			
			// 构建商品和规格信息
			var product = {
				id: proid,
				name: proname
			};
			var guige = {
				id: ggid,
				name: ggname,
				stock: stock,
				barcode: barcode
			};
			
			// 调用父页面的回调函数
			var parentWindow = window.parent;
			if (typeof parentWindow.chooseStoreProduct === 'function') {
				parentWindow.chooseStoreProduct(product, guige);
			} else if (typeof top.window.chooseStoreProduct === 'function') {
				top.window.chooseStoreProduct(product, guige);
			} else {
				alert('选择成功，但无法调用回调函数，请手动关闭弹窗');
				return;
			}
			
			// 关闭当前弹窗
			parent.layer.closeAll();
		});
	});
</script>
</body>
</html>