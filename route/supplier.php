<?php
// 供应商API路由配置

use think\facade\Route;

// 供应商登录注册相关路由
Route::post('api/supplier/login', 'ApiSupplierLogin/login');
Route::get('api/supplier/logout', 'ApiSupplierLogin/logout');
Route::get('api/supplier/getUserInfo', 'ApiSupplierLogin/getUserInfo');
Route::get('api/supplier/changePassword', 'ApiSupplierLogin/changePassword');

// 供应商认证中间件组
Route::group('api/supplier', function() {
    // 订单管理相关路由
    Route::get('orderList', 'ApiSupplierOrder/orderList');
    Route::get('orderDetail', 'ApiSupplierOrder/orderDetail');
    Route::get('sendExpress', 'ApiSupplierOrder/sendExpress');
    Route::get('getExpressInfo', 'ApiSupplierOrder/getExpressInfo');
    Route::get('orderStats', 'ApiSupplierOrder/orderStats');
    
    // 商品管理相关路由（后续实现）
    // Route::get('goodsList', 'ApiSupplierGoods/goodsList');
    // Route::get('goodsDetail', 'ApiSupplierGoods/goodsDetail');
    // Route::get('addGoods', 'ApiSupplierGoods/addGoods');
    // Route::get('updateGoods', 'ApiSupplierGoods/updateGoods');
    // Route::get('deleteGoods', 'ApiSupplierGoods/deleteGoods');
    
    // 库存管理相关路由（后续实现）
    // Route::get('stockList', 'ApiSupplierStock/stockList');
    // Route::get('updateStock', 'ApiSupplierStock/updateStock');
    
    // 供应商信息管理相关路由（后续实现）
    // Route::get('getSupplierInfo', 'ApiSupplierInfo/getSupplierInfo');
    // Route::get('updateSupplierInfo', 'ApiSupplierInfo/updateSupplierInfo');
    
    // 账户结算相关路由（后续实现）
    // Route::get('settlementList', 'ApiSupplierSettlement/settlementList');
    // Route::get('settlementDetail', 'ApiSupplierSettlement/settlementDetail');
    // Route::get('applySettlement', 'ApiSupplierSettlement/applySettlement');
})
->middleware('AuthSupplier'); // 使用供应商认证中间件