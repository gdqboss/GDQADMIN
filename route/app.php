<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP6!';
});

Route::get('hello/:name', 'index/hello');

Route::get('/', function () {
    return redirect('/index/index');
});
Route::any('gdqadmin', 'Login/index');
Route::any('gdqadmin/', 'Login/index');
//营销
Route::any('DayGive/:function', 'yingxiao.DayGive/:function');
Route::any('TeamSaleYeji/:function', 'yingxiao.TeamSaleYeji/:function');
Route::any('TeamYejiManage/:function', 'yingxiao.TeamYejiManage/:function');
if(getcustom('extend_certificate')){
    Route::any('CertificateList/:function', 'extend.CertificateList/:function');
    Route::any('CertificateCategory/:function', 'extend.CertificateCategory/:function');
    Route::any('CertificateJob/:function', 'extend.CertificateJob/:function');
    Route::any('CertificateEducation/:function', 'extend.CertificateEducation/:function');
}
if(getcustom('yx_queue_free')){
    Route::any('ApiQueueFree/:function', 'yingxiao.ApiQueueFree/:function');
    Route::any('QueueFree/:function', 'yingxiao.QueueFree/:function');
    Route::any('QueueFreeSet/:function', 'yingxiao.QueueFreeSet/:function');
    Route::any('ApiAdminQueueFree/:function', 'yingxiao.ApiAdminQueueFree/:function');
}
if(getcustom('yx_order_discount_rand')){
    Route::any('OrderDiscountRand/:function', 'yingxiao.OrderDiscountRand/:function');
}
if(getcustom('shop_paiming_fenhong')){
    Route::any('PaimingFenhong/:function', 'yingxiao.PaimingFenhong/:function');
    Route::any('ApiPaimingFenhong/:function', 'yingxiao.ApiPaimingFenhong/:function');
}

if(getcustom('yx_mangfan')){
    Route::any('ApiMangfan/:function', 'yingxiao.ApiMangfan/:function');
    Route::any('Mangfan/:function', 'yingxiao.Mangfan/:function');
    Route::any('MangfanSet/:function', 'yingxiao.MangfanSet/:function');
}
if(getcustom('yx_buy_fenhong')){
    Route::any('BuyFenhong/:function', 'yingxiao.BuyFenhong/:function');
    Route::any('BuyFenhongSet/:function', 'yingxiao.BuyFenhongSet/:function');
}
if(getcustom('supply_zhenxin')){
    Route::any('SupplyZhenxinProduct/:function', 'extend.SupplyZhenxinProduct/:function');
    Route::any('SupplyZhenxinSet/:function', 'extend.SupplyZhenxinSet/:function');
}
if(getcustom('yx_hongbao_queue_free')){
    Route::any('HongbaoQueueFree/:function', 'yingxiao.HongbaoQueueFree/:function');
    Route::any('HongbaoQueueFreeSet/:function', 'yingxiao.HongbaoQueueFreeSet/:function');
}
if(getcustom('extend_linghuoxin')){
    Route::any('LinghuoxinSet/:function', 'extend.LinghuoxinSet/:function');
}
if(getcustom('extend_invite_redpacket')){
    Route::any('InviteRedpacket/:function', 'yingxiao.InviteRedpacket/:function');
}
if(getcustom('yx_team_yeji_weight')){
    Route::any('TeamYejiWeight/:function', 'yingxiao.TeamYejiWeight/:function');
}
if(getcustom('yx_team_yeji_tongji')){
    Route::any('TeamYejiTongji/:function', 'yingxiao.TeamYejiTongji/:function');
    Route::any('ApiTeamYejiTongji/:function', 'yingxiao.ApiTeamYejiTongji/:function');
}
if(getcustom('yx_score_freeze')){
    Route::any('ScoreFreeze/:function', 'yingxiao.ScoreFreeze/:function');
}
if(getcustom('yx_shop_order_team_yeji_bonus')){
    Route::any('ShopOrderTeamYejiBonus/:function', 'yingxiao.ShopOrderTeamYejiBonus/:function');
}
if(getcustom('yx_yeji_fenhong')){
    Route::any('YejiFenhong/:function', 'yingxiao.YejiFenhong/:function');
}
if(getcustom('pay_allinpay')){
    Route::any('AllinpayYunstSet/:function', 'extend.AllinpayYunstSet/:function');
}
if(getcustom('mobile_admin_qrcode_variable_maidan')){
    Route::any('BindQrcodeVar/:function', 'extend.BindQrcodeVar/:function');
}
if(getcustom('yx_buy_product_manren_choujiang')){
    Route::any('ManrenChoujiang/:function', 'yingxiao.ManrenChoujiang/:function');
    Route::any('ApiManrenChoujiang/:function', 'yingxiao.ApiManrenChoujiang/:function');
}
if(getcustom('transfer_order_parent_check')){
    Route::any('TransferOrderParentCheckTongji/:function', 'yingxiao.TransferOrderParentCheckTongji/:function');
}
