<?php
// JK客户定制

//custom_file(hotel)
// +----------------------------------------------------------------------
// | 酒店-相册管理
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class HotelPhotos extends Common
{
    public function initialize(){
		parent::initialize();
		//if(bid > 0) showmsg('无访问权限');
	}
	//相册列表
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
			if(bid==0){
				if(input('param.bid')){
					$where[] = ['bid','=',input('param.bid')];
				}elseif(input('param.showtype')==2){
					$where[] = ['bid','<>',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
				}else{
					$where[] = ['bid','=',0];
				}
			}else{
				$where[] = ['bid','=',bid];
			}
			if(input('param.name')) $where[] = ['name','like','%'.$_GET['name'].'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];

			$count = 0 + Db::name('hotel_photo')->where($where)->count();
			$data = Db::name('hotel_photo')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as &$d){
				$d['pics'] =$d['pics']?explode(',',$d['pics']):[];
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑相册
	public function edit(){
		if(input('param.id')){
			$info = Db::name('hotel_photo')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('相册不存在');
		}
		View::assign('info',$info);
        View::assign('bid',bid);
		return View::fetch();
	}
	//保存相册
	public function save(){
		$hotel = Db::name('hotel')->where('aid',aid)->where('bid',bid)->find();
		if(input('post.id')){
			$photo = Db::name('hotel_photo')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
			if(!$photo) showmsg('相册不存在');
		}
		$info = input('post.info/a');
		if($photo){
			Db::name('hotel_photo')->where('aid',aid)->where('bid',bid)->where('id',$photo['id'])->update($info);
			$photoid = $photo['id'];
			\app\common\System::plog('编辑酒店相册'.$photoid);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['hotelid'] = $hotel['id'];
			$photoid = Db::name('hotel_photo')->insertGetId($info);
			\app\common\System::plog('添加酒店相册'.$photoid);
		}
 
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}

    //改状态
    public function setst(){
        $st = input('post.st/d');
        $ids = input('post.ids/a');
        Db::name('hotel_photo')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->update(['status'=>$st]);
        \app\common\System::plog('酒店相册改状态'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'操作成功']);
    }

	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('hotel_photo')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\common\System::plog('酒店相册删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

}
