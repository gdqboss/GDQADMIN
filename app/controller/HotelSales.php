<?php
// JK客户定制

//custom_file(hotel)
// +----------------------------------------------------------------------
// | 基地销量
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class HOtelSales extends Common
{
	public $text;
	public function initialize(){
		parent::initialize();
		$this->set = Db::name('hotel_set')->where('aid',aid)->find();
		$this->text = \app\model\Hotel::gettext(aid);
		if(bid > 0) showmsg('无操作权限');
	}


	//商户销量统计
	public function index(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 'room.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'room.bid desc';
            }
            $where = array();
            $where[] = ['room.aid','=',aid];
            if(input('param.bid')) $where[] = ['room.bid','=',input('param.bid/d')];
            if(input('param.name')) $where[] = ['h.name','like','%'.input('param.name').'%'];
            if(input('param.hotelid')) $where[] = ['room.hotelid','=',input('param.hotelid/d')];

            $count = 0 + Db::name('hotel_room')->alias('room')->join('hotel h','room.hotelid=h.id','left')->where($where)->count();
            $data = Db::name('hotel_room')->alias('room')->join('hotel h','room.hotelid=h.id','left')->field('h.id as hid,room.name as roomname,h.name as hotelname,room.id as roomid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			foreach($data as & $d){
				$where = [];
				$where[] = ['roomid','=',$d['roomid']];
				$where[] = ['status','in','1,2,3,4'];
				if(input('param.ctime') ){
					$ctime = explode(' ~ ',input('param.ctime'));
					$where[] = ['createtime','>=',strtotime($ctime[0])];
					$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
				}
				$d['sales'] = Db::name('hotel_order')->where($where)->sum('totalnum');
				$d['money'] = Db::name('hotel_order')->where($where)->sum('use_money');
				$d['totalprice'] = Db::name('hotel_order')->where($where)->sum('totalprice');
				$d['totalyajin'] = Db::name('hotel_order')->where($where)->sum('yajin_money');
				$d['refundyajin'] = Db::name('hotel_order')->where($where)->where('yajin_refund_status',2)->sum('yajin_money');
				$d['wtrefundyajin'] = number_format($d['totalyajin']-$d['refundyajin'],2);
				$d['totalprice']  =number_format($d['totalprice']-$d['totalyajin'],2);
			}


            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
		$hotellist =  Db::name('hotel')->where('aid',aid)->where('status',1)->select()->toArray();
		View::assign('hotellist',$hotellist);
        View::assign('text',$this->text);

        return View::fetch();
    }

    //导出
    public function excel(){

		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'room.bid desc';
		}
		$page = input('param.page')?:1;
		$limit = input('param.limit')?:10;

		$where = array();
		$where[] = ['room.aid','=',aid];
		if(input('param.bid')) $where[] = ['room.bid','=',input('param.bid/d')];
		if(input('param.name')) $where[] = ['h.name','like','%'.input('param.name').'%'];
		if(input('param.hotelid')) $where[] = ['room.hotelid','=',input('param.hotelid/d')];

		$count = 0 + Db::name('hotel_room')->alias('room')->join('hotel h','room.hotelid=h.id','left')->where($where)->count();
		$lists = Db::name('hotel_room')->alias('room')->join('hotel h','room.hotelid=h.id','left')->field('h.id as hid,room.name as roomname,h.name as hotelname,room.id as roomid')->where($where)->order($order)->page($page,$limit)->select()->toArray();


		$title = array($this->text['酒店'].'ID',$this->text['酒店'].'名称','房型名称','预定人数','收款'.t('余额'),'收款金额','总押金','已退押金','未退押金');
		$data = array();

		foreach($lists as & $d){
			$where = [];
			$where[] = ['roomid','=',$d['roomid']];
			$where[] = ['status','in','1,2,3,4'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			$d['sales'] = Db::name('hotel_order')->where($where)->sum('totalnum');
			$d['money'] = Db::name('hotel_order')->where($where)->sum('use_money');
			$d['totalprice'] = Db::name('hotel_order')->where($where)->sum('totalprice');
			$d['totalyajin'] = Db::name('hotel_order')->where($where)->sum('yajin_money');
			$d['refundyajin'] = Db::name('hotel_order')->where($where)->where('yajin_refund_status',2)->sum('yajin_money');
			$d['wtrefundyajin'] = number_format($d['totalyajin']-$d['refundyajin'],2);
			$d['totalprice']  =number_format($d['totalprice']-$d['totalyajin'],2);

			$data[] = [
				$d['hid'],
				$d['hotelname'],
				$d['roomname'],
				$d['sales'],
				$d['money'],
				$d['totalprice'],
				$d['totalyajin'],
				$d['refundyajin'],
				$d['wtrefundyajin'],
			];
		}
		//dump($data);die;
		return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);

    }

}
