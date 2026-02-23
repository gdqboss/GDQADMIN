<?php
// JK客户定制

//custom_file(wx_channels)
//视频号小店 预约直播设置
namespace app\controller;

use think\facade\Db;
use think\facade\View;

class WxChannelsLiveSet extends Common
{
    public function initialize()
    {
        parent::initialize();
        if (!getcustom('wx_channels_business') && bid > 0) showmsg('无访问权限');
    }


    /**
     * 预约直播设置
     */
    public static function set(){
        $info = Db::name('channels_reservation_live_set')->where('aid',aid)->where('bid',bid)->find();
        $coupon_list = [];
        $coupons = $info['coupon_list']?json_decode($info['coupon_list'], true):[];
        foreach ($coupons as $coupon) {
            $c = Db::name('coupon')->where('aid',aid)->whereIn('id', $coupon['coupon_id'])->find();
            $temp = [
                "id" => $coupon['coupon_id'],
                "name" => $c['name'],
                "stock" => $c['stock'],
                "coupon_num" => $coupon['coupon_num']
            ];
            $coupon_list[] = $temp;
        }
        View::assign('couponList', $coupon_list);
        View::assign('info',$info);
        View::assign('bid',bid);
        return View::fetch();
    }

    /**
     * 保存预约直播设置
     */
    public static function save(){
        if(request()->isPost()) {
            $id = input('post.id/d');
            $info = input('post.info/a');
            $info['aid'] = aid;
            $info['bid'] = bid;

            //优惠券奖励重新设置
            $coupon_list = $info['coupon_list'];
            $coupon_ids = $coupon_list['coupon_id'];
            $coupon_num = $coupon_list['coupon_num'];
            $coupons = [];
            foreach ($coupon_ids as $k=>$coupon_id) {
                $coupons[] = [
                    'coupon_id'=>$coupon_id,
                    'coupon_num'=>$coupon_num[$k]
                ];
            }
            $info['coupon_list'] = jsonEncode($coupons);

            \app\common\System::plog('预约直播设置');
            if($id){
                Db::name('channels_reservation_live_set')->where('aid',aid)->where('bid',bid)->update($info);
            }else{
                Db::name('channels_reservation_live_set')->insert($info);
            }
            return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('set')]);
        }
    }
}