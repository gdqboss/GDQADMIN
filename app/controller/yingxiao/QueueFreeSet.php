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
// | 营销-排队免单 custom_file(yx_queue_free)
// +----------------------------------------------------------------------
namespace app\controller\yingxiao;
use app\controller\Common;
use think\facade\View;
use think\facade\Db;

class QueueFreeSet extends Common
{
    public function initialize(){
		parent::initialize();
        $this->defaultSet();
	}
	public function index(){
        $setPlatform = Db::name('queue_free_set')->where('aid',aid)->where('bid',0)->find();
        if(bid > 0){
            if($setPlatform['status'] == 0){
                showmsg('未开启相关功能');
            }
        }
		if(request()->isAjax()){
//            dd(input('post.'));
			$info = input('post.info/a');
            if($info['gettj_children'])$info['gettj_children'] = implode(',',$info['gettj_children']);
            if($info['order_types'])$info['order_types'] = implode(',',$info['order_types']);
            if(bid > 0){
                $bset = Db::name('queue_free_set')->where('aid',aid)->where('bid',bid)->find();
                if(($bset['rate_status_business'] == -1 && $setPlatform['rate_status_business'] == 1) || $bset['rate_status_business'] == 1){
                    if($info['rate'] > $setPlatform['rate_max'] || $info['rate'] < $setPlatform['rate_min']){
                        return json(['status'=>0,'msg'=>'比例范围为'.$setPlatform['rate_min'].'~'.$setPlatform['rate_max']]);
                    }
                }                   
                }
            if(bid > 0){
                $binfo = [];
                if(($bset['rate_status_business'] == -1 && $setPlatform['rate_status_business'] == 1) || $bset['rate_status_business'] == 1){
                    $binfo['rate'] = $info['rate'];
                }
                if(getcustom('business_apply_queue_free_rate_back') || getcustom('yx_queue_free_business_fanli_range')){
                    $binfo['rate_back'] = $info['rate_back'];
                }
                if($binfo){
                    Db::name('queue_free_set')->where('aid',aid)->where('bid',bid)->update($binfo);
                }                
            }else{
                Db::name('queue_free_set')->where('aid',aid)->where('bid',bid)->update($info);
            }
           
            \app\common\System::plog('排队免单设置');
			return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
		}
		$info = Db::name('queue_free_set')->where('aid',aid)->where('bid',bid)->find();
		if(!$info) $info = ['status'=>0];
        View::assign('info',$info);

        View::assign('setPlatform',$setPlatform);//平台设置
        //定制标签显示
        $mode_show = 0;
        if(getcustom('yx_queue_free_other_mode') || getcustom('yx_queue_free_today_average')){
            $mode_show = 1;
        }
        View::assign('mode_show',$mode_show);//平台设置
		return View::fetch();
	}

    public function defaultSet(){
        $set = Db::name('queue_free_set')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            if(bid > 0){
                $queue_free_insert = ['aid'=>aid,'bid'=>bid,'status'=>0,'rate_status_business'=>-1,'money_max'=>null,'createtime'=>time(),'gettj_children'=>-1];
                Db::name('queue_free_set')->insert($queue_free_insert);
            }else{
                Db::name('queue_free_set')->insert(['aid'=>aid,'bid'=>bid,'status'=>0,'createtime'=>time(),'gettj_children'=>-1,'queue_type_business' => -1]);
            }
        }
    }

}
