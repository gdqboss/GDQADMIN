<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 约课 - 系统设置 custom_file(yueke)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class YuekeSet extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
    public function set(){
		$info = Db::name('yueke_set')->where('aid',aid)->where('bid',bid)->find();
		if(!$info){
			Db::name('yueke_set')->insert(['aid'=>aid,'bid'=>bid]);
			$info = Db::name('yueke_set')->where('aid',aid)->where('bid',bid)->find();
		
		}
		View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
		if($info['yyzhouqi']) $info['yyzhouqi'] =  implode(',',$info['yyzhouqi']);
		Db::name('yueke_set')->where('aid',aid)->where('bid',bid)->update($info);
		\app\common\System::plog('约课系统设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>true]);
	}
}
