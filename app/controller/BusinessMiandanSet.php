<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 多商户免单 - 系统设置 custom_file(yx_business_miandan)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class BusinessMiandanSet extends Common
{
    public function initialize(){
		parent::initialize();
	}
    public function set(){
		$info = Db::name('business_miandan_set')->where('aid',aid)->where('bid',bid)->find();
		if(!$info){
			Db::name('business_miandan_set')->insert(['aid'=>aid,'bid'=>bid]);
			$info = Db::name('business_miandan_set')->where('aid',aid)->where('bid',bid)->find();
		
		}
		View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
		Db::name('business_miandan_set')->where('aid',aid)->where('bid',bid)->update($info);
		\app\common\System::plog('免单系统设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>true]);
	}
}
