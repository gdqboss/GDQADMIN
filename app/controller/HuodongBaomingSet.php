<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 活动报名 - 系统设置 custom_file(huodong_baoming)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class HuodongBaomingSet extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
    public function set(){
		$info = Db::name('huodong_baoming_set')->where('aid',aid)->where('bid',bid)->find();
		if(!$info){
			Db::name('huodong_baoming_set')->insert(['aid'=>aid,'bid'=>bid]);
			$info = Db::name('huodong_baoming_set')->where('aid',aid)->where('bid',bid)->find();
		
		}
		$textset=$info['textset'];
		if(!$textset) {
			$textset = ['活动单位'=>'活动单位','联系电话'=>'联系电话','活动地址'=>'活动地址','活动时间'=>'活动时间'];
		} else{
			$textset = json_decode($info['textset'], true);
		}
		View::assign('textset',$textset);
		View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
		if($info['yyzhouqi']) $info['yyzhouqi'] =  implode(',',$info['yyzhouqi']);
		$info['textset'] = jsonEncode(input('post.textset/a'));

		Db::name('huodong_baoming_set')->where('aid',aid)->where('bid',bid)->update($info);
		\app\common\System::plog('活动报名系统设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>true]);
	}
}
