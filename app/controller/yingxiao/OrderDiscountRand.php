<?php
/**
 * 点大商城（www.diandashop.com） - 微信公众号小程序商城系统!
 * Copyright © 2020 山东点大网络科技有限公司 保留所有权利
 * =========================================================
 * 版本：V2
 * 授权主体：飞鲸(泉州)网络科技有限公司
 * 授权域名：www.ottz.cn
 * 授权码：ZnNpaTeHHFnuyMxIYcI
 * ----------------------------------------------
 * 您只能在商业授权范围内使用，不可二次转售、分发、分享、传播
 * 任何企业和个人不得对代码以任何目的任何形式的再发布
 * =========================================================
 */

// +----------------------------------------------------------------------
// | 营销-下单随机立减 custom_file(yx_order_discount_rand)
// +----------------------------------------------------------------------
namespace app\controller\yingxiao;
use app\controller\Common;
use think\facade\View;
use think\facade\Db;

class OrderDiscountRand extends Common
{
    public function initialize(){
		parent::initialize();
        $this->defaultSet();
	}
	public function set(){

        $set = Db::name('order_discount_rand')->where('aid',aid)->where('bid',bid)->find();

		if(request()->isAjax()){
			$info = input('post.info/a');
            if($info['gettj'])$info['gettj'] = implode(',',$info['gettj']);
            if($info['order_types'])$info['order_types'] = implode(',',$info['order_types']);
            if(empty($info['money_min']) || $info['money_min'] < 0) $info['money_min'] = 0;
            if(empty($info['money_max']) || $info['money_max'] < 0) $info['money_max'] = 0;
            Db::name('order_discount_rand')->where('aid',aid)->where('bid',bid)->update($info);

            \app\common\System::plog('下单随机立减设置');
			return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
		}
		$info = Db::name('order_discount_rand')->where('aid',aid)->where('bid',bid)->find();
		if(!$info) $info = ['status'=>0];
        $info['gettj'] = explode(',',$info['gettj']);
		View::assign('info',$info);
        View::assign('set',$set);
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $memberlevel = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
        View::assign('memberlevel',$memberlevel);
		return View::fetch();
	}

    public function defaultSet(){
        $set = Db::name('order_discount_rand')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            Db::name('order_discount_rand')->insert(['aid'=>aid,'bid'=>bid,'status'=>0,'discount_type'=>0,'createtime'=>time(),'gettj'=>-1,'order_types'=>'all']);
        }
    }


}
