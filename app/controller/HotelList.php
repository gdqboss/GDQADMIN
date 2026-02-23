<?php
// JK客户定制

//custom_file(hotel)
// +----------------------------------------------------------------------
// | 酒店-酒店列表
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class HotelList extends Common
{
	public $text;
    public function initialize(){
		parent::initialize();
		$this->set = Db::name('hotel_set')->where('aid',aid)->find();
		$this->text = \app\model\Hotel::gettext(aid);
		//if(bid > 0) showmsg('无访问权限');
	}
	//酒店列表
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

			$count = 0 + Db::name('hotel')->where($where)->count();
			$data = Db::name('hotel')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as &$d){
				$d['cname'] = Db::name('hotel_category')->where('aid',aid)->where('id',$d['cid'])->value('name');
			}
			$getBedarr = \app\model\Hotel::getBedarr();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}

		View::assign('text',$this->text);
		return View::fetch();
    }
	//编辑酒店
	public function edit(){
		$text = \app\model\Hotel::gettext(aid);
		if(input('param.id')){
			$info = Db::name('hotel')->where('aid',aid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg($text['酒店'].'不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
		}
		if(!$info){
			$info=[];
		}
	
		$clist = Db::name('hotel_category')->where('aid',aid)->where('status',1)->order('sort desc,id desc')->select()->toArray();
		if($info['ptsheshi']){
			$info['ptsheshi'] = json_decode($info['ptsheshi'],true);
		}else{
			$info['ptsheshi'] = array(['icon'=>'','text'=>'']);
		}
		//var_dump($info);
		if($info['dikou_text']){
			$info['dikou_text'] = json_decode($info['dikou_text'],true);
		}else{
			$info['dikou_text'] =[['dikou_bl'=>'100','dikou_num'=>'1'],['dikou_bl'=>'30','dikou_num'=>'2'],['dikou_bl'=>'10','dikou_num'=>'3']];
		}
		$ptlenth = count($info['ptsheshi']);
		View::assign('clist',$clist);
		View::assign('textset',$text);
		View::assign('info',$info);
		View::assign('bid',bid);
		View::assign('ptlenth',$ptlenth);
		return View::fetch();
	}
	//保存房型
	public function save(){
		$info = input('post.info/a');
		$bid = input('post.bid/d');
		$rs = Db::name('hotel')->where('aid',aid)->where('bid',$bid)->where('id',$info['id'])->find();
		if(bid != 0 && $rs['bid']!=bid) showmsg('无权限操作');
		$ptsheshi = array_values(input('post.ptsheshi/a'));
		$info['textset'] = jsonEncode(input('post.textset/a'));
		$info['ptsheshi'] = jsonEncode($ptsheshi);
		if($info['money_dikou']==1){
			$info['dikou_text'] = jsonEncode(input('post.dkbl/a'));
		}else{
			$info['dikou_text']='';
		}
		if($rs){
			Db::name('hotel')->where('aid',aid)->where('bid',$bid)->where('id',$info['id'])->update($info);
		}else{
			return json(['status'=>0,'msg'=>$text['酒店'].'不存在']);
		}
		\app\common\System::plog('修改'.$text['酒店'].$info['id']);
		return json(['status'=>1,'msg'=>'修改成功']);
	}

	//删除
	public function del(){
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		$hotellist = Db::name('hotel')->where($where)->select();
		foreach($hotellist as $hotel){
			Db::name('hotel')->where('aid',aid)->where('id',$hotel['id'])->delete();
			//将价格表一并删除
			Db::name('hotel_order')->where('hotelid',$hotel['id'])->delete();	
			Db::name('hotel_order_yajin')->where('hotelid',$hotel['id'])->delete();	
			Db::name('hotel_room')->where('hotelid',$hotel['id'])->delete();	
			Db::name('hotel_room_prices')->where('hotelid',$hotel['id'])->delete();	
		}
		\app\common\System::plog($this->text['酒店'].'删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	
}
