<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 商城 商城设置
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class ShopSet extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
    public function index(){
		$info = Db::name('shop_sysset')->where('aid',aid)->find();
        if(getcustom('product_bonus_pool')){
            $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
            $default_cid = $default_cid ? $default_cid : 0;
            $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->field('id,name')->order('sort,id')->select()->toArray();
            $bonus_pool_member_count = json_decode($info['bonus_pool_member_count'],true);
            if($bonus_pool_member_count){
                foreach($levellist as $key=>$lv){
                    $levellist[$key]['count'] =$bonus_pool_member_count[$lv['id']];
                }
            }
            
            View::assign('levellist',$levellist);
            $info['bonus_pool_noreleasetj'] = explode(',',$info['bonus_pool_noreleasetj']);
            $bonus_pool_set_show = false;
            $bonus_pool_status = Db::name('admin')->where('id',aid)->value('bonus_pool_status');
            if(($this->auth_data == 'all' || in_array('ShopSet/bonuspoolset',$this->auth_data)) && $bonus_pool_status ){
                $bonus_pool_set_show= true;
            }
            View::assign('bonus_pool_set_show',$bonus_pool_set_show);
        }
        View::assign('expressdata',express_data(['aid'=>aid,'bid'=>bid]));
		View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
        if(getcustom('product_bonus_pool')){
            $info['bonus_pool_noreleasetj'] = implode(',',$info['bonus_pool_noreleasetj']);
            $bp = input('param.bp');
            $bonus_pool_member_count = [];
            foreach($bp['levelid'] as $key=>$val){
                $bonus_pool_member_count[$val] =$bp['count'][$key];
            }
            $info['bonus_pool_member_count'] = json_encode($bonus_pool_member_count,JSON_UNESCAPED_UNICODE);
        }
        Db::name('shop_sysset')->where('aid',aid)->update($info);
		\app\common\System::plog('商城系统设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}
}