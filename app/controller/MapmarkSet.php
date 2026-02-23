<?php
// JK客户定制

//custom_file(map_mark)
// +----------------------------------------------------------------------
// |  地图标注参数
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;
class MapmarkSet extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
    public function index(){
		$info = Db::name('mapmark_sysset')->where('aid',aid)->find();
        $info['data'] = jsonEncode($info);
		View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
		$setinfo = Db::name('mapmark_sysset')->where('aid',aid)->find();
		if($setinfo){
			Db::name('mapmark_sysset')->where('aid',aid)->update($info);
		} else {
			$info['aid'] = aid;
			Db::name('mapmark_sysset')->insert($info);
		}
		\app\common\System::plog('地图标注参数设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}
}