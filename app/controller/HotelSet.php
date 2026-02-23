<?php
// JK客户定制

//custom_file(hotel)
// +----------------------------------------------------------------------
// | 酒店 系统设置
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class HotelSet extends Common
{
    public $text;
    public function initialize(){
		parent::initialize();
		//if(bid > 0) showmsg('无访问权限');
		$this->set = Db::name('hotel_set')->where('aid',aid)->find();
		$this->text = \app\model\Hotel::gettext(aid);
	}
	//系统设置
	public function index(){
        $info = Db::name('hotel_set')->where('aid',aid)->find();
		$textset = json_decode($info['textset'], true);
		if(!$info){
			$info=[];
		}
		if(!$textset) {
			$textset = ['酒店'=>'基地','间'=>'间','服务费'=>'打赏'];
		} 
		View::assign('textset',$textset);
		View::assign('info',$info);
		return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		$bid = input('post.bid/d');
		$rs = Db::name('hotel_set')->where('aid',aid)->find();
		$info['textset'] = jsonEncode(input('post.textset/a'));
		if($rs){
			Db::name('hotel_set')->where('aid',aid)->update($info);
		}else{
			$info['aid'] = aid;
			$info['bid'] =  $bid?$bid:0;
			Db::name('hotel_set')->insert($info);
		}
		\app\common\System::plog('酒店系统设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}
}