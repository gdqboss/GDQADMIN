<?php
// JK客户定制

//custom_file(hotel)
// +----------------------------------------------------------------------
// | 酒店-房态房价
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class HotelRoomPrices extends Common
{
	public $text;
    public function initialize(){
		parent::initialize();
		//if(bid > 0) showmsg('无访问权限');
		$this->set = Db::name('hotel_set')->where('aid',aid)->find();
		$this->text = \app\model\Hotel::gettext(aid);
	}
	//房型列表
    public function index(){
		$date = date('Y-m-d',time());
		$date = input('param.today')?input('param.today'):$date;
		//var_dump(input('param.today'));
		$time = time();
		if(input('param.today')){
			$time = strtotime(input('param.today'));	
		}
		$totaldays = 10;
		if(request()->isAjax()){
			$page = input('param.page')?:1;
			$limit = input('param.limit')?:10;
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
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!==''){
				$status = input('param.status');
				$where[] = ['status','=',$status];
			}
			if(input('param.hotelid')) $where[] = ['hotelid','like','%'.input('param.hotelid').'%'];

			$count = 0 + Db::name('hotel_room')->where($where)->count();
			$data = Db::name('hotel_room')->where($where)->page($page,$limit)->order($order)->select()->toArray();

		

			if(!$date){
				$datetime = time();
				$endtime = time()+86400*$totaldays;
			}else{
				$datetime = strtotime($date);
				$endtime = $datetime +86400*$totaldays;
			}
			foreach($data as $k=>$v){
				if(!$room_prices)$room_prices=[];
				$newroom_prices = [];
				for($i=0;$i<$totaldays;$i++){
					$daytime = $datetime+86400*$i;
					$daydate = date('Y-m-d',$daytime);
					$where1 = [];
					$where1[] = ['roomid','=',$v['id']];
					$where1[] = ['datetime','=',$daydate];
					$room = Db::name('hotel_room_prices')->where($where1)->find();
					if($room){
				 
						if($room['lvprice'] === null){
							$room['lvprice'] = $v['lvprice'];
							$room['lvprice_data'] = $v['lvprice_data'];
						} 
						
						if($room['lvprice'] ==1){
							$room['sell_price'] = min(json_decode($room['lvprice_data'],true));
						}
					}
				
					$newroom_prices[$i] = $room;
				}
				$data[$k]['room_prices'] =array_values($newroom_prices);
				$hotel = Db::name('hotel')->field('name')->where('id',$v['hotelid'])->find();
				$data[$k]['hotel_name']  = $hotel['name'];
				//echo db('hotel_room_prices')->Getlastsql();
				
			}
			$page_total = ceil($count/$limit);
			$page = [
				'current' => (int)$page,
				'limit' => (int)$limit,
				'pages' => $page_total,
				'total' => $count,
			];
		 
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'page'=>$page]);
		}
		//当前日期往后推10天
		$datelist = [];
		for($i=0;$i<$totaldays;$i++){
			$times = $time+86400*$i;
			$datelist[$i]['days'] = date('m/d',$times);
			$datelist[$i]['week'] =  \app\model\Hotel::getWeekday($times);
		}
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
		$default_cid = $default_cid ? $default_cid : 0;
		$levellist = Db::name('member_level')->where('aid',aid)->where('cid',$default_cid)->order('sort,id')->select()->toArray();
		$weeks = ['周一'=>'周一','周二'=>'周二','周三'=>'周三','周四'=>'周四','周五'=>'周五','周六'=>'周六','周日'=>'周日'];
		View::assign('weeks',$weeks);
		 
		$hotellist =  Db::name('hotel')->where('aid',aid)->where('status',1)->where('bid','<>',0)->select()->toArray();
		 
		View::assign('hotellist',$hotellist);
		View::assign('levellist',$levellist);	 
		View::assign('date',$date);
		View::assign('datelist',$datelist);
		View::assign('text',$this->text);
		View::assign('showtype',input('param.showtype'));
		View::assign('hotelid',input('param.hotelid'));
		View::assign('name',input('param.name'));
		View::assign('status',input('param.status'));
		$moneyunit = (!t('余额单位') || t('余额单位')=='余额单位')?'元':t('余额单位');
		View::assign('moneyunit',$moneyunit);
		return View::fetch();
    }
	/*设置房态*/
	public function setRoomStatus(){
		$ids = input('param.roomids');
		$ctime = explode(' ~ ',input('param.ctime1'));
		$start_time = strtotime($ctime[0]);
		$end_time = strtotime($ctime[1]);
		$weeks = input('param.weeks');
		$status = input('param.status');
		if($status===''){
			return json(['status'=>0,'msg'=>'请选择房型状态']);
		}
		$where = [];
		$where[] = ['aid','=',aid];
		if(bid !=0){
			$where[] = ['bid','=',bid];
		}
		$where[] = ['roomid','in',$ids];
		$where[] =Db::raw("(unix_timestamp(datetime) between ".$start_time." and ".$end_time.")");
		$where[] = ['week','in',$weeks];
		$list = Db::name('hotel_room_prices')->where($where)->select()->toArray();
		$roomlist = Db::name('hotel_room_prices')->where($where)->update(['status'=>$status]);

        \app\common\System::plog($this->text['酒店'].'批量修改房态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'修改成功']);
	}


	/*批量设置房量*/
	public function setRoomNum(){
		$post = input('post.');
		$ids = $post['roomidsnum'];
		$ctime = explode(' ~ ',$post['ctime2']);
		$start_time = strtotime($ctime[0]);
		$end_time = strtotime($ctime[1]);
		$weeks = $post['numweeks'];
		$stock = $post['num'];

		$data = [];
		$data['qrtype'] = $post['qrtype']?$post['qrtype']:1;
		$data['stock'] = $stock;
		$where = [];
		$where[] = ['aid','=',aid];
        if(bid !=0){
			$where[] = ['bid','=',bid];
		}
		$where[] = ['roomid','in',$ids];
		$where[] =Db::raw("(unix_timestamp(datetime) between ".$start_time." and ".$end_time.")");
		$where[] = ['week','in',$weeks];
		$roomlist = Db::name('hotel_room_prices')->where($where)->update($data);
        \app\common\System::plog($this->text['酒店'].'批量修改房量'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'修改成功']);
	}

	/*获取选择的房型*/
	public function getrooms(){
		$ids = input('param.ids');
		$where = [];
		$where[] = ['aid','=',aid];
        if(bid !=0){
			$where[] = ['bid','=',bid];
		}
		$where[] = ['id','in',$ids];
		$roomlist = Db::name('hotel_room')->where($where)->select()->toArray();
		return json(['status'=>1,'datas'=>$roomlist]);
	}

	/*批量设置房价*/
	public function setRoomPrice(){
		$post = input('post.');
 		$ctime = explode(' ~ ',$post['ctime3']);
		$start_time = strtotime($ctime[0]);
		$end_time = strtotime($ctime[1]);
		$weeks = $post['weeks'];
		$prices = $post['price'];
		$lvprice_data  = $post['lvprice_data'];
		$daymoney = $post['daymoney'];
		$roomids = explode(',',$post['roomidsprice']);
		$lvprice = $post['lvprice'];
		foreach($roomids as $k=>$id){
			foreach($weeks as $kk=>$week){
				$where = [];
				$where[] = ['aid','=',aid];
                 if(bid !=0){
					$where[] = ['bid','=',bid];
				}
				$where[] = ['roomid','=',$id];
				$where[] =Db::raw("(unix_timestamp(datetime) between ".$start_time." and ".$end_time.")");
				$where[] = ['week','in',$week];
				$daymoney1 = $daymoney[$kk][$k]?$daymoney[$kk][$k]:0;
				$lvpriceArr = [];
				foreach($lvprice_data as $kkk=>$v){
					$lvpriceArr[$kkk] = $v[$kk][$k];
				}
				// $lvprice[$k]
				 
				//取出元素最小值
				$min = min($lvpriceArr);
			 
  		  $roomlist = Db::name('hotel_room_prices')->where($where)->update(['sell_price'=>$prices[$kk][$k],'lvprice_min'=>$min,'lvprice'=>$lvprice[$k],'daymoney'=>$daymoney1,'lvprice_data'=>json_encode($lvpriceArr)]);	
		 
			}	
		}
		 
        \app\common\System::plog($this->text['酒店'].'房价房态修改'.$post['roomidsprice']);
		return json(['status'=>1,'msg'=>'修改成功']);
	}

	/*设置详情*/
	public function setRoomDetail(){
		$post = input('post.');
		$id = $post['hid'];
		$roomid = $post['roomid'];
		$stock = $post['stock'];
		$where1 = [];
		 $time = $post['time'];
		 $timeArr = explode('~',$time);
		 $timeArr = array_map('trim', $timeArr);
		 
	    $where1[] = ['datetime','between',$timeArr];
		$where1[] = ['aid','=',aid];
		if(bid !=0){
			$where1[] = ['bid','=',bid];
		}	
		$where1[] = ['roomid','=',$roomid];
		$roomprice = Db::name('hotel_room_prices')->where($where1)->find();
 
		if(!$roomprice){
			return json(['status'=>0,'msg'=>'信息不存在']);
		}
		$json = json_decode($post['lvprice_data'],true);
		//取出元素最小值
		$min = min($json);
	 
		$data = [];
		$data['stock'] = $stock;
		$data['status'] = $post['status'];
		$data['qrtype'] = $post['qrtype'];
		$data['sell_price'] =  $post['sell_price'];
		$data['daymoney'] =  $post['daymoney'];
		$data['lvprice_data'] =  $post['lvprice_data'];
		$data['lvprice'] = $post['lvprice'];
		$data['lvprice_min'] = $min;
		$where = [];
	    $where[] = ['datetime','between',$timeArr];
		$where[] = ['aid','=',aid];
		if(bid !=0){
			$where[] = ['bid','=',bid];
		}	
		$where[] = ['roomid','=',$roomid];
	 
		$roomlist = Db::name('hotel_room_prices')->where($where)->update($data);	
		
		//确认方式统一修改
		//Db::name('hotel_room')->where('id',$roomprice['roomid'])->update(['qrtype'=>$post['qrtype']]);
        \app\common\System::plog($this->text['酒店'].'房价房态修改'.$id);

		return json(['status'=>1,'msg'=>'修改成功']);
	}

}
