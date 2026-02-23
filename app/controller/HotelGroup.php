<?php
// JK客户定制

//custom_file(hotel)
// +----------------------------------------------------------------------
// | 酒店 房型分组
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class HotelGroup extends Common
{
    public function initialize(){
		parent::initialize();
		//if(bid > 0) showmsg('无访问权限');
	}
	//分组列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			//dump($where);
			$count = 0 + Db::name('hotel_group')->where($where)->count();
			$data = Db::name('hotel_group')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('hotel_group')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		$pcatelist = Db::name('hotel_group')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function save(){
		$hotel = Db::name('hotel')->where('aid',aid)->where('bid',bid)->find();
		$info = input('post.info/a');
		if($info['id']){
			$group = Db::name('hotel_group')->where('aid',aid)->where('id',$info['id'])->find();
			if(!$group) showmsg('分组不存在');
			if(bid != 0 && $group['bid']!=bid) showmsg('无权限操作');
			if(!$group['hotelid']) 	$info['hotelid'] = $hotel['id'];
			Db::name('hotel_group')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\common\System::plog('编辑酒店房型分组'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['hotelid'] = $hotel['id'];
			$info['createtime'] = time();
			$id = Db::name('hotel_group')->insertGetId($info);
			\app\common\System::plog('添加酒店房型分组'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('hotel_group')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\common\System::plog('酒店房型分组删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}