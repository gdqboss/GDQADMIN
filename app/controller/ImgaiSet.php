<?php
// JK客户定制

//custom_file(image_ai)
// +----------------------------------------------------------------------
// |  百度文心AI绘画参数
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;
use app\custom\BaiduAi;
class ImgaiSet extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
    public function index(){
		$info = Db::name('imgai_sysset')->where('aid',aid)->find();
        $info['data'] = jsonEncode($info);
		View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
		$setinfo = Db::name('imgai_sysset')->where('aid',aid)->find();
		if($setinfo){
			Db::name('imgai_sysset')->where('aid',aid)->update($info);
		} else {
			$info['aid'] = aid;
			Db::name('imgai_sysset')->insert($info);
		}
		\app\common\System::plog('Ai绘画参数设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}
}