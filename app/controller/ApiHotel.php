<?php
// JK客户定制

//custom_file(hotel)
namespace app\controller;
use think\facade\Db;
class ApiHotel extends ApiCommon{
	
	public $text;
	public $set;
	public function initialize(){
		parent::initialize();
		$this->set = Db::name('hotel_set')->where('aid',aid)->find();
		$this->text = \app\model\Hotel::gettext(aid);
		$moeny_weishu = 2;
        if($this->member) $this->member['money'] = dd_money_format($this->member['money'],$moeny_weishu);
	}
	public function getsysset(){
		$this->set['pics'] = $this->set['pics']?explode(',',$this->set['pics']):[];
		$catelist =  Db::name('hotel_category')->where('aid',aid)->where('status',1)->order('sort desc,id desc')->select()->toArray();
		$starttime = strtotime(input('param.starttime'));
		$endtime = strtotime(input('param.endtime'));
		$isshownow = false;
		if($endtime<time()){
			$isshownow = true;
		}
		$startday = date('Y-m-d');
		$endday = date('Y-m-d', strtotime('+1 day'));
		$startweek = \app\model\Hotel::getWeekday(time());
		$endweek =  \app\model\Hotel::getWeekday(strtotime('+1 day'));
	
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['set'] = $this->set;
		$rdata['text'] = $this->text;
		$rdata['catelist']  = $catelist;
		$rdata['startday'] = $startday;
		$rdata['endday'] = $endday;
		$rdata['startweek'] = $startweek;
		$rdata['endweek'] = $endweek;
		$starlist = ['经济型','舒适/三星','高档/四星','豪华/五星'];
		$rdata['starlist'] = $starlist;
		$rdata['maxdays'] = 20;
		$rdata['isshownow'] = $isshownow;
		$rdata['moneyunit'] = (!t('余额单位') || t('余额单位')=='余额单位')?'元':t('余额单位');
		return $this->json($rdata);
	}

	public function gethotels(){
		$where[] = ['h.aid','=',aid];
		$where[] = ['h.status','=',1];
		//分类 
		$cateid = input('param.cateid');
		if(input('param.cateid')){
			$cid = input('param.cateid/d');
			$where[] = ['h.cid','=',$cid];	
		}
		if(input('param.keyword')){
			$where[] = ['h.name|h.address','like','%'.input('param.keyword').'%'];
		}

		$longitude = input('post.longitude');
		$latitude = input('post.latitude');
		$order = input('param.order');
		if($order=='totalnum'){  //接单量
			$orderBy = 'h.sales desc,h.id';
		}elseif($order=='comment_haopercent'){ //好评率
			$orderBy = 'h.comment_haopercent desc,h.id';
		}elseif($order=='createtime'){ //时间
			$orderBy = 'h.createtime desc,h.id';
		}elseif($order=='juli'){ //距离
			$orderBy = Db::raw("({$longitude}-h.longitude)*({$longitude}-h.longitude) + ({$latitude}-h.latitude)*({$latitude}-h.latitude) ");
		}elseif($order=='suiji'){ 
			$orderBy = 'h.createtime desc,h.id';
		}elseif($order=='priceasc'){ 
			$orderBy = 'room.sell_price asc,h.id';
		}elseif($order=='pricedesc'){ 
			$orderBy = 'room.sell_price desc,h.id';
		}
		$juli = input('param.juli');
		if($juli && $juli!='all' && $longitude && $latitude){  
			$juli =$juli*1000;
			$having = "round(6378.138*2*asin(sqrt(pow(sin( ({$latitude}*pi()/180-h.latitude*pi()/180)/2),2)+cos({$latitude}*pi()/180)*cos(h.latitude*pi()/180)* pow(sin( ({$longitude}*pi()/180-h.longitude*pi()/180)/2),2)))*1000) <={$juli}";
		}else{
			$having="1=1";
		}

		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order').',h.sort,h.id desc';
		}else{
			$order = 'h.sort desc,h.id desc';
		}

		if(input('param.stars')){
			$where[] = ['h.hotellevel','in',input('param.stars')];
		}
		if(input('param.cateids')){
			$cateids = input('param.cateids');
			$where[] = ['h.cid','in',$cateids];	
		}
	
		if(input('param.emptystatus')){
			$startdate = input('post.starttime');
			$starttime = strtotime($startdate);
			$enddate = input('post.endtime');
			$entime = strtotime($enddate);
			$where2 .= "  unix_timestamp(room.datetime)>=".$starttime." and unix_timestamp(room.datetime)<".$entime."";
			if(input('param.emptystatus')==1){
				$where2 .= ' and room.stock>0';
			}
			if(input('param.emptystatus')==2){
				$where2 .= ' and room.stock=0 and room.stock<>NULl';
			}
		}else{
			$where2 = '1=1';
		}
	
		//$data = Db::name('mendian')->alias('md')->field('md.name as mdname,sum(order.totalprice) as totalprice')->leftjoin('shop_order order','order.mdid=md.id and `order`.status in(1,2,3) and `order`.paytime>='.$starttime.' and `order`.paytime<= '.$endtime)->where($where)->group('order.mdid')->order('totalprice desc')->select()->toArray();
		$set = Db::name('hotel_set')->where('aid',aid)->find();
		$type = input('post.type');
		$pernum = $set['hotlimit'];
		if($type=='list') $pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;

		$datalist = Db::name('hotel')->alias('h')->field('h.*,room.id as roompriceid')->leftjoin('hotel_room_prices room','h.id=room.hotelid and '.$where2)->where($where)->group('room.hotelid')->having($having)->page($pagenum,$pernum)->order($orderBy)->select()->toArray();

		//$datalist = Db::name('hotel')->where($where)->having($having)->page($pagenum,$pernum)->order($order)->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as &$d){
			if($d['tag']){
				$d['tag'] = explode(',',$d['tag']);
			}else{
				$d['tag'] = [];
			}
			//查询最低的房型价格
			$where = [];
			$where[] =['hotelid','=',$d['id']];
			$nowtime = time();
			$room = Db::name('hotel_room')->where($where)->where('isdaymoney',1)->field('isdaymoney,lvprice,sell_price,daymoney')->find();
			$where[] = Db::raw("unix_timestamp(datetime)>=$nowtime");
			//是否有设置余额定价
			if($room['isdaymoney']==1){
				$d['isdaymoney'] = 1;
				$roomprice = Db::name('hotel_room_prices')->where($where)->where('daymoney','>=','1')->field('daymoney')->order('daymoney')->find();
				if($roomprice){
					$d['min_daymoney'] = $roomprice['daymoney'];
				}else{
					$d['min_daymoney'] = 0;
				}
			}else{
				$roomprice = Db::name('hotel_room_prices')->where($where)->field('sell_price')->order('sell_price')->find();
				//echo db('hotel_room_prices')->getlastsql();
				$d['isdaymoney'] = 0;
				if($roomprice){
					$d['min_price'] = $roomprice['sell_price'];
				}else{
					$d['min_price'] = 0;
				}
				if($room['lvprice'] == 1){
					$roomprice = Db::name('hotel_room_prices')->where($where)->where('lvprice_min','>',0)->field('lvprice_min')->order('lvprice_min')->find();
					if($roomprice){
						$d['min_price'] = $roomprice['lvprice_min'];
					}else{
						$d['min_price'] = 0;
					}
				}
				
			}
		}
		return $this->json(['status'=>1,'data'=>$datalist]);
		$count = Db::name('hotel_order')->where($where)->count();
		$rdata = [];
		$rdata['searchcid'] = $searchcid;
		$rdata['pernum'] = $pernum;
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}

	public function gethotelDetail(){
		$hotelid = input('param.hotelid');
		if(!$hotelid) return $this->json(['status'=>0,'msg'=>'参数有误']);
		$where = [];
		$where[] = ['id','=',$hotelid];
		$where[] = ['aid','=',aid];
		$hotel = Db::name('hotel')->where($where)->find();
		if(!$hotel) return $this->json(['status'=>0,'msg'=>$this->text['酒店'].'不存在']);
		$pernum = 5;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$where = [];
		$where[] = ['room.aid','=',aid];
		$where[] = ['room.hotelid','=',$hotelid];
		$where[] = ['room.status','=',1];
		$gids = input('post.gids');
		if(input('post.gids')){
			$whereGid = [];
			foreach($gids as $k => $g){
				$whereGid[] = "find_in_set({$g},room.gid)";
			}
			$where[] = Db::raw(implode(' or ',$whereGid));
		}
		$startdate = input('post.starttime');
		$starttime = strtotime($startdate);
		$endtime = strtotime(input('post.endtime'));
		if($starttime && $endtime){
			$where2.="  unix_timestamp(roomprice.datetime)>=".$starttime." and unix_timestamp(roomprice.datetime)<".$endtime."";
		}else{
			$where2 = '1=1';
		}
		//$datalist = Db::name('hotel')->alias('h')->field('h.*,room.id as roompriceid')->leftjoin('hotel_room_prices room','h.id=room.hotelid and '.$where2)->where($where)->group('room.hotelid')->having($having)->page($pagenum,$pernum)->order($order)->select()->toArray();

		$datalist = Db::name('hotel_room')->alias('room')->field('room.*,sum(roomprice.stock) as sumstock')->leftjoin('hotel_room_prices roomprice','room.id=roomprice.roomid and '.$where2)->where($where)->group('roomprice.roomid')->page($pagenum,$pernum)->order('sumstock desc,sort desc,id desc')->select()->toArray();

		/*可预订天数*/
		$daydate = time()+86400*$hotel['yddatedays'];
		$roomdaylist = Db::name('hotel_room_prices')->field('sum(stock) as stock,datetime,status
		')->where('aid',aid)->where('hotelid',$hotel['id'])->where('status',1)->where('datetime','>=', date('Y-m-d'))->where('datetime','<', date('Y-m-d',$daydate))->group('datetime')->select()->toArray();
		//echo db('hotel_room_prices')->getlastsql();
		$roomdayprice = [];
		foreach($roomdaylist as $day){
			$roomdayprice[$day['datetime']]['stock'] = $day['stock'];
			$roomdayprice[$day['datetime']]['status'] = $day['status'];
		}	
		$daycounts = count($roomdaylist);
		for($i=0;$i<$hotel['yddatedays'];$i++){
			$daytime = date('Y-m-d',time()+86400*$i);
			if(!$roomdayprice[$daytime]){
				$roomdayprice[$daytime] = ['stock'=>0,'status'=>0];
			}
		}

		$maxenddate =  date('Y-m-d',time()+86400*$daycounts);
		//$maxenddate =date('Y-m-d', time()+86400*($hotel['yddatedays']));
		$maxdays = $hotel['yddays'];
		$daycount = input('post.daycount');
		$dates =[];
		for($i=0;$i<$daycount;$i++){
			$date = $starttime+86400*$i;
			$dates[] = date('Y-m-d',$date);
		}
		//dump(date('Y-m-d H:i:s',$starttime));die;
		foreach($datalist as &$d){
			if($d['tag']){
				$d['tag'] = explode(',',$d['tag']);
			}else{
				$d['tag'] = [];
			}
			//查询最低的房型价格
			$getBedarr = \app\model\Hotel::getBedarr();
			$d['bedxing'] = $getBedarr['bedxing'][$d['bedxing']];			
		    $d['breakfast'] = $getBedarr['zaocan'][$d['breakfast']];
			// dump( $d['breakfast']);die;
			$d['ischuanghu'] = $getBedarr['chuanghu'][$d['ischuanghu']];
			$where2 = [];
			$where2[] = ['aid','=',aid];
			$where2[] = ['roomid','=',$d['id']];
			$where2[] = Db::raw("unix_timestamp(datetime)>=".$starttime." and unix_timestamp(datetime)<=".$endtime."");
			if($d['isdaymoney']==1){
				$room2 = Db::name('hotel_room_prices')->where($where2)->order('daymoney')->find();
				//echo db('hotel_room_prices')->Getlastsql();
				$d['daymoney'] = $room2['daymoney']? $room2['daymoney']:0;
			}else{
				$room2 = Db::name('hotel_room_prices')->where($where2)->order('sell_price')->find();
				$d['sell_price'] = $room2['sell_price']? $room2['sell_price']:0;	
				
				if($room2['lvprice'] === null){
					$room2['lvprice'] = $d['lvprice'];
					$room2['lvprice_data'] = $d['lvprice_data'];
				} 		 
			 
				
				if($room2['lvprice'] ==1 && $d['lvprice'] ==1){
					$lvprice_data = json_decode($room2['lvprice_data'],true);
					// $this->member['levelid']
					$levelid = $this->member['levelid']??1;
					$d['sell_price'] = $lvprice_data[$levelid];
				} 		
			} 
 
			 
			$where1 = [];
			$where1[] = ['aid','=',aid];
			$where1[] = ['roomid','=',$d['id']];
			$where1[] = ['datetime','in',$dates];
			$roomprice = Db::name('hotel_room_prices')->where($where1)->order('stock')->find();
			$d['stock'] = $roomprice['stock']?$roomprice['stock']:0;
			//显示销量
			$d['sales'] = Db::name('hotel_room_prices')->where('roomid',$d['id'])->sum('sales');
			
			$d['isbooking'] = false;
			$roomprice2 = Db::name('hotel_room_prices')->where($where1)->where('status',0)->find();
			if($roomprice2){
				$d['isbooking'] = true;	continue;
			}

		}
		$count = Db::name('hotel_room')->alias('room')->where($where)->order('sort desc,id desc')->count();
		$totalpagenum = ceil($count/$pernum);
		if($pagenum>1)	return $this->json(['status'=>1,'count'=>$count,'datalist'=>$datalist,'totalpagenum'=>$totalpagenum]);
		if($hotel['tag']){
			$hotel['tag'] = explode(',',$hotel['tag']);
		}else{
			$hotel['tag'] = [];
		}
		$hotel['ptsheshi'] = json_decode($hotel['ptsheshi'],true);
		//酒店相册
		$photos = Db::name('hotel_photo')->where('hotelid',$hotelid)->where('aid',aid)->where('bid',$hotel['bid'])->select()->toArray();
		foreach($photos as &$photo){
			$photo['pics'] = $photo['pics']?explode(',',$photo['pics']):[];
		}
		//房型分组
		$grouplist = Db::name('hotel_group')->where('hotelid',$hotelid)->where('aid',aid)->where('bid',$hotel['bid'])->select()->toArray();

		//获取前10条记录
		$commentlist = Db::name('hotel_comment')->where('aid',aid)->where('hotelid',$hotelid)->where('status',1)->order('id desc')->limit(10)->select()->toArray();
		if(!$commentlist) $commentlist = [];
		foreach($commentlist as $k=>$pl){
			$commentlist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
			if($pl['content_pic']) $commentlist[$k]['content_pic'] = explode(',',$pl['content_pic']);
		}
		$commentcount = Db::name('hotel_comment')->where('aid',aid)->where('hotelid',$hotelid)->where('status',1)->count();
		$haoping = '';
		if($hotel['comment_score']<=1){
			$haoping = '很糟糕';
		}elseif($hotel['comment_score']<=2 && $hotel['comment_score']>1){
			$haoping = '较差';
		}elseif($hotel['comment_score']<=3 && $hotel['comment_score']>2){
			$haoping = '一般';
		}elseif($hotel['comment_score']<=4 && $hotel['comment_score']>3){
			$haoping = '还可以';
		}elseif($hotel['comment_score']<=5 && $hotel['comment_score']>4){
			$haoping = '很棒';
		}
		$latitude = input('param.latitude');
		$longitude = input('param.longitude');
		if($longitude && $latitude){
			$hotel['juli'] = ''.getdistance($longitude,$latitude,$hotel['longitude'],$hotel['latitude'],2).'公里';
		}else{
			$hotel['juli'] = '';
		}
 		$rdata = [];
		$rdata['status'] = 1;
		$rdata['detail'] = $hotel;
		$rdata['set'] = $this->set;
		$rdata['datalist'] = $datalist;
		$rdata['photos'] = $photos;
		$rdata['grouplist'] = $grouplist;
		$rdata['totalpagenum'] = $totalpagenum;
		$rdata['commentlist'] = $commentlist;
		$rdata['haoping'] = $haoping;
		$rdata['roomdayprice'] = $roomdayprice;
		$rdata['maxenddate'] = $maxenddate;
		$rdata['minstock'] = $minstock;
		$rdata['maxdays'] = $maxdays;
	 
		return $this->json($rdata);

	}

	public function getRoomList(){
		$ids = input('param.roomids');
		if(!$ids) return $this->json(['status'=>0,'msg'=>'参数有误']);
		$where = [];
		$where[] = ['room.aid','=',aid];
		$where[] = ['room.id','in',implode(',',$ids)];
		$where[] = ['room.status','=',1];
		$startdate = input('post.starttime');
		$starttime = strtotime($startdate);
		$endtime = strtotime(input('post.endtime'));
		if($starttime && $endtime){
			$where2.="  unix_timestamp(roomprice.datetime)>=".$starttime." and unix_timestamp(roomprice.datetime)<".$endtime."";
		}else{
			$where2 = '1=1';
		}

		if(input('param.sortby') == 'sort') $order = 'room.sort desc,room.id desc';
		if(input('param.sortby') == 'createtimedesc') $order = 'room.createtime desc';
		if(input('param.sortby') == 'createtime') $order = 'room.createtime';
		if(input('param.sortby') == 'sales') $order = 'room.sales desc,room.sort desc';
		if(input('param.sortby') == 'stock') $order = 'room.stock desc';

		$datalist = Db::name('hotel_room')->alias('room')->field('room.*,sum(roomprice.stock) as sumstock')->leftjoin('hotel_room_prices roomprice','room.id=roomprice.roomid and '.$where2)->where($where)->group('roomprice.roomid')->order($order)->select()->toArray();
		$maxenddate =  date('Y-m-d',time()+86400*90);
		//$maxenddate =date('Y-m-d', time()+86400*($hotel['yddatedays']));
		//dump(date('Y-m-d H:i:s',$starttime));die;
		$getBedarr = \app\model\Hotel::getBedarr();
		foreach($datalist as &$d){
			if($d['tag']) $d['tag'] = explode(',',$d['tag']);
			$hotel = Db::name('hotel')->where('id',$d['hotelid'])->find();
			$d['bedxing'] = $getBedarr['bedxing'][$d['bedxing']];	
			//查询最低的房型价格
			$where2 = [];
			$where2[] = ['aid','=',aid];
			$where2[] = ['roomid','=',$d['id']];
			$where2[] = Db::raw("unix_timestamp(datetime)>=".$starttime." and unix_timestamp(datetime)<=".$endtime."");
			if($d['isdaymoney']==1 && $hotel['money_dikou']==1){
				$room2 = Db::name('hotel_room_prices')->where($where2)->order('daymoney')->find();
				//echo db('hotel_room_prices')->Getlastsql();
				$d['min_daymoney'] = $room2['daymoney']? $room2['daymoney']:0;
			}else{
				$room2 = Db::name('hotel_room_prices')->where($where2)->order('sell_price')->find();
				$d['sell_price'] = $room2['sell_price']? $room2['sell_price']:0;
			}
			$where1 = [];
			$where1[] = ['aid','=',aid];
			$where1[] = ['roomid','=',$d['id']];
		    $where1[] = Db::raw("unix_timestamp(datetime)>=$starttime and unix_timestamp(datetime)<=$endtime");
			$roomprice = Db::name('hotel_room_prices')->where($where1)->order('stock')->find();
			$d['stock'] = $roomprice['stock']?$roomprice['stock']:0;
			//显示销量
			$d['sales'] = Db::name('hotel_room_prices')->where('roomid',$d['id'])->sum('sales');
			
			$d['isbooking'] = false;
			$roomprice2 = Db::name('hotel_room_prices')->where($where1)->where('status',0)->find();
			if($roomprice2){
				$d['isbooking'] = true;	continue;
			}

		}
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['set'] = $this->set;
		$rdata['datalist'] = $datalist;
		$rdata['maxenddate'] = $maxenddate;
		return $this->json($rdata);
	}

	public function getroomDetail(){
		$id = input('param.id');
		if(!$id) return $this->json(['status'=>0,'msg'=>'参数有误']);
		$where = [];
		$where[] = ['id','=',$id];
		$where[] = ['aid','=',aid];
		$room = Db::name('hotel_room')->where($where)->find();
		if(!$room) return $this->json(['status'=>0,'msg'=>'房型不存在']);
		$getBedarr = \app\model\Hotel::getBedarr();
		$room['bedxing'] = $getBedarr['bedxing'][$room['bedxing']];
		 
		$room['breakfast'] = $getBedarr['zaocan'][$room['breakfast']];
	
		// dump( $room['breakfast']);die;

		$room['ischuanghu'] = $getBedarr['chuanghu'][$room['ischuanghu']];
		$room['pics'] = $room['pics']?explode(',',$room['pics']):[];
		
		$dayCount = input('param.dayCount');
		$startdate = input('param.startDate');
		$enddate = input('param.endDate');
		$starttime = strtotime($startdate);
		$endtime = strtotime($enddate.' 00:00:00');
		$roomprice =  Db::name('hotel_room_prices')->where('roomid',$id)->where('datetime',$startdate)->find();
		$minstock = $roomprice['stock']?$roomprice['stock']:0;
		$room['isbooking'] = false;
		for($i=0;$i<$daycount;$i++){
			$datetime = $starttime+86400*$i;
			$datetime = date('Y-m-d',$datetime);
			$rooms =  Db::name('hotel_room_prices')->where('roomid',$id)->where('datetime',$datetime)->find();
			if($rooms['stock']<$minstock){
				$minstock = $rooms['stock'];
			}
		}

		$where2 = [];
		$where2[] = ['aid','=',aid];
		$where2[] = ['roomid','=',$id];
		$where2[] = Db::raw("unix_timestamp(datetime)>=".$starttime." and unix_timestamp(datetime)<".$endtime."");
		$roomst =  Db::name('hotel_room_prices')->where($where2)->where('status',0)->find();
		$room['isbooking'] = false;
		if($roomst){	
			$room['isbooking'] = true;
		}
		//查看房费
		if($roomprice['lvprice'] === null){
			$roomprice['lvprice'] = $room['lvprice'];
			$roomprice['lvprice_data'] = $room['lvprice_data'];
		} 		 
		 
		if($roomprice['lvprice'] ==1 && $room['lvprice'] ==1){
			$lvprice_data = json_decode($roomprice['lvprice_data'],true);
			// $this->member['levelid']
			$levelid = $this->member['levelid']??1;
			$roomprice['sell_price'] = $lvprice_data[$levelid];
		} 		

		$sell_price =$roomprice['sell_price'];
	
		$room['price'] = $sell_price;
		$room['daymoney'] = $roomprice['daymoney'];

		$hotel = Db::name('hotel')->where('id',$room['hotelid'])->find();

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['room'] = $room;
		$rdata['set'] = $this->set;
		$rdata['minstock'] = $minstock;
		$rdata['hotel'] = $hotel; //组件需要
 		return $this->json($rdata);
	}

	public function buy(){
		$this->checklogin();
		$id = input('param.id');
		if(!$id) return $this->json(['status'=>0,'msg'=>'参数有误']);
		$where = [];
		$where[] = ['id','=',$id];
		$where[] = ['aid','=',aid];
		$room = Db::name('hotel_room')->where($where)->find();
		if(!$room) return $this->json(['status'=>0,'msg'=>'房型不存在']);
		$room['pics'] = $room['pics']?explode(',',$room['pics']):[];

		$gettj = explode(',',$room['gettj']);
		if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj) && (!in_array('0',$gettj) || $this->member['subscribe']!=1)){ //不是所有人
			if(!$room['gettjtip']) $room['gettjtip'] = '没有权限预定该房型';
			return $this->json(['status'=>0,'msg'=>$room['gettjtip'],'url'=>$room['gettjurl']]);
		}

		$where = [];
		$where[] = ['id','=',$room['hotelid']];
		$where[] = ['aid','=',aid];
		$hotel = Db::name('hotel')->where($where)->find();
		
		$dayCount = input('param.dayCount');
		$startdate = input('param.startdate');
		$totalroomprice = 0;
		$roomprices = [];
		for($i=0;$i<$dayCount;$i++){
			$datetime = strtotime($startdate)+86400*$i;
			$date = date('Y-m-d',$datetime);
			$roomprice =  Db::name('hotel_room_prices')->where('roomid',$id)->where('datetime',$date)->find();
				/***会员等级定价*** */
			if($roomprice['lvprice'] === null){
				$roomprice['lvprice'] = $room['lvprice'];
				$roomprice['lvprice_data'] = $room['lvprice_data'];
			} 		 
			 
			if($roomprice['lvprice'] ==1 && $room['lvprice'] ==1){
				$lvprice_data = json_decode($roomprice['lvprice_data'],true);
				// $this->member['levelid']
				$levelid = $this->member['levelid']??1;
				$roomprice['sell_price'] = $lvprice_data[$levelid];
			} 		
		 
     	/***会员等级定价*** */
			$totalroomprice +=$roomprice['sell_price'];
			$roomprices[$i]['datetime'] = $roomprice['datetime'];
			$roomprices[$i]['sell_price'] = $roomprice['sell_price'];
			$roomprices[$i]['daymoney'] = $roomprice['daymoney'];
		}
		$dikoutext =  $hotel['dikou_text']?json_decode($hotel['dikou_text'],true):[];
		$yjcount = 0;
 		//是否有存在未退押金的订单
		if($room['isiyajin']!='-1'){
			if($room['isyajin']==1){ 
				$yjorder =  Db::name('hotel_order_yajin')->where('aid',aid)->where('mid',mid)->where('refund_status','in','0,1')->where('yajin_money','>',0)->where('yd_num',1)->find();
			}elseif($room['isyajin']==2){
				$yjorder =  Db::name('hotel_order_yajin')->where('aid',aid)->where('mid',mid)->where('refund_status','in','0,1')->where('yajin_money','>',0)->where('yajin_type',2)->find();
			}else{
				//跟随系统设置
				if($hotel['isyajin']==1){
					$yjorder =  Db::name('hotel_order_yajin')->where('aid',aid)->where('mid',mid)->where('refund_status','in','0,1')->where('yajin_money','>',0)->where('yd_num',1)->find();
				}elseif($hotel['isyajin']==2){
					$yjorder =  Db::name('hotel_order_yajin')->where('aid',aid)->where('mid',mid)->where('refund_status','in','0,1')->where('yajin_money','>',0)->where('yajin_type',2)->find();
				}
			}
		}
		if($yjorder){
			$yjcount = 1;
		}
		
		$money_weishu = 0;
		$room['limitnums'] = [];
		for($i=1;$i<=$room['limitnum'];$i++){
			$room['limitnums'][] = $i;
		}
		//关于积分抵扣
		$userinfo = [];
		$userinfo['score'] = $this->member['score'];
		$userinfo['scoredk_money'] = round($userinfo['score'] * $hotel['score2money'],2);
		$userinfo['scoredkmaxpercent'] = $hotel['scoredkmaxpercent'];
		$userinfo['money'] = $this->member['money'];

		$bid = $hotel['bid'];
		//关于优惠券
	 
		$couponList = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('type','in','6')->where('status',0)
		        	->whereRaw("bid=-1 or bid=".$bid)
					->where('minprice','<=',$totalroomprice)
					->where('starttime','<=',time())
					->where('endtime','>',time())->order('id desc')->select()->toArray();
 		 
		if(!$couponList) $couponList = [];

		foreach($couponList as $k=>$v){
			$couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$v['couponid'])->find();
			if($v['bid'] > 0){
				$binfo = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->find();
				$couponList[$k]['bname'] = $binfo['name'];
			}
            $fwscene = [0,4];
            if(!in_array($couponinfo['fwscene'],$fwscene)){//全部可用 
                unset($couponList[$k]);			 
            }			
			if(empty($couponinfo) || ($couponinfo['fwtype']!==0 && $couponinfo['fwtype']!==2)){  
				unset($couponList[$k]);
			}
 			if($couponinfo['fwtype'] == 2){
				//  查询指定房型			 
				if(!in_array($id,explode(',', $couponinfo['roomids']))){
					unset($couponList[$k]);
				}
			}
            if($couponinfo['isgive'] == 2){
                unset($couponList[$k]);
            }
		}

		$couponList = array_values($couponList);
		if(platform == 'wx'){
			$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
			$tmplids = [];
			$tmplids[] = $wx_tmplset['tmpl_moneychange'];
		}
		$hotel['formdata'] = json_decode($hotel['formdata'],true);
		$hotel['editorFormdata'] = [];
		$getBedarr = \app\model\Hotel::getBedarr();
		$room['bedxing'] = $getBedarr['bedxing'][$room['bedxing']];			
		$room['breakfast'] = $getBedarr['zaocan'][$room['breakfast']];
		// dump( $room['breakfast']);die;
		$room['ischuanghu'] = $getBedarr['chuanghu'][$room['ischuanghu']];
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['room'] = $room;
		$rdata['hotel'] = $hotel;
		$rdata['set'] = $this->set;
		$rdata['text'] = $this->text;
		$rdata['roomprices'] = $roomprices;
		$rdata['totalroomprice'] = $totalroomprice;
		$rdata['userinfo'] = $userinfo;
		$rdata['dikoutext'] = $dikoutext;
		$rdata['yjcount'] = $yjcount;
		$rdata['couponList'] = $couponList;
		$rdata['tmplids'] = $tmplids;
		$rdata['moneyunit'] = (!t('余额单位') || t('余额单位')=='余额单位')?'元':t('余额单位');
		return $this->json($rdata);
	}
	public function getYajin(){
		$this->checklogin();
		$num = input('param.num');
		//是否有存在未退押金的订单
		$id = input('param.roomid');
		$startdate = input('param.startdate');
		$where = [];
		$where[] = ['id','=',$id];
		$where[] = ['aid','=',aid];

		$room = Db::name('hotel_room')->where($where)->find();
		$where = [];
		$where[] = ['id','=',$room['hotelid']];
		$where[] = ['aid','=',aid];
		$hotel = Db::name('hotel')->where($where)->find();
		
		if($room['isiyajin']!='-1'){
			if($room['isyajin']==1){ 
				$yjorder =  Db::name('hotel_order_yajin')->where('aid',aid)->where('mid',mid)->where('refund_status','in','0')->where('yajin_money','>',0)->where('yd_num',$num)->find();
			}elseif($room['isyajin']==2){
				$yjorder =  Db::name('hotel_order_yajin')->where('aid',aid)->where('mid',mid)->where('refund_status','in','0')->where('yajin_money','>',0)->where('yajin_type',2)->find();
			}else{
				//跟随系统设置
				if($hotel['isyajin']==1){
					$yjorder =  Db::name('hotel_order_yajin')->where('aid',aid)->where('mid',mid)->where('refund_status','in','0,1')->where('yajin_money','>',0)->where('yd_num',$num)->find();
				}elseif($hotel['isyajin']==2){
					$yjorder =  Db::name('hotel_order_yajin')->where('aid',aid)->where('mid',mid)->where('refund_status','in','0,1')->where('yajin_money','>',0)->where('yajin_type',2)->find();
				}
			}
		}
		$yjcount =0;
		if($yjorder){
			$yjcount = 1;
		}
		$coouponlist = $this->chooseCoupon($num,$id,$startdate);
		return $this->json(['status'=>1,'yjcount'=>$yjcount,'couponlist'=>$coouponlist]);
	}

	//根据入住天数和入住人数计算总价来筛选优惠券
	public function chooseCoupon($num,$roomid,$startdate){
		// $this->checklogin();
		// $num = input('param.num');
		// $roomid = input('param.roomid');
		// $startdate = input('param.startdate');

		$where = [];
		$where[] = ['id','=',$roomid];
		$where[] = ['aid','=',aid];
		$room = Db::name('hotel_room')->where($where)->find();
		$where = [];
		$where[] = ['id','=',$room['hotelid']];
		$where[] = ['aid','=',aid];
		$hotel = Db::name('hotel')->where($where)->find();
		$bid = $hotel['bid'];
		$totalroomprice = 0;
		$dayCount = $num;
	 
	 
		// dump($room);
		for($i=0;$i<$dayCount;$i++){
			$datetime = strtotime($startdate)+86400*$i;
			$date = date('Y-m-d',$datetime);
			$roomprice =  Db::name('hotel_room_prices')->where('roomid',$roomid)->where('datetime',$date)->find();
				/***会员等级定价*** */
			if($roomprice['lvprice'] === null){
				$roomprice['lvprice'] = $room['lvprice'];
				$roomprice['lvprice_data'] = $room['lvprice_data'];
			} 		 
			 
			if($roomprice['lvprice'] ==1 && $room['lvprice'] ==1){
				$lvprice_data = json_decode($roomprice['lvprice_data'],true);
				// $this->member['levelid']
				$levelid = $this->member['levelid']??1;
				$roomprice['sell_price'] = $lvprice_data[$levelid];
			} 		
		 
     	/***会员等级定价*** */
			$totalroomprice +=$roomprice['sell_price'];
	 
		}
		$totalroomprice= $totalroomprice *$num;

		$couponList = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('type','in','6')->where('status',0)
		->whereRaw("bid=-1 or bid=".$bid)
		->where('minprice','<=',$totalroomprice)
		->where('starttime','<=',time())
		->where('endtime','>',time())->order('id desc')->select()->toArray();
			if(!$couponList) $couponList = [];
			foreach($couponList as $k=>$v){
			$couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$v['couponid'])->find();
			if($v['bid'] > 0){
				$binfo = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->find();
				$couponList[$k]['bname'] = $binfo['name'];
			}
			$fwscene = [0,4];
			if(!in_array($couponinfo['fwscene'],$fwscene)){//全部可用 
				unset($couponList[$k]);			 
			}
			if(empty($couponinfo) || ($couponinfo['fwtype']!==0 && $couponinfo['fwtype']!==2)){ 
				unset($couponList[$k]);
			}
			if($couponinfo['fwtype'] == 2){
				//  查询指定商品			 
				if(!in_array($roomid,explode(',', $couponinfo['hotelids']))){
					unset($couponList[$k]);
				}
			}

			if($couponinfo['isgive'] == 2){
				unset($couponList[$k]);
			}
			}		 
		    return array_values($couponList);
	}	
		
	public function createOrder(){
		$this->checklogin();
		$post = input('post.');
		$id = $post['roomid'];
		$names = $post['formdata']['names'];
		$tel = $post['formdata']['tel'];
		$score_weishu = $this->score_weishu;
		$formdata = $post['formdata'];
		$where = [];
		$where[] = ['id','=',$id];
		$where[] = ['aid','=',aid];
		$room = Db::name('hotel_room')->where($where)->find();
		if(!$room) return $this->json(['status'=>0,'msg'=>'房型不存在']);
		
		$gettj = explode(',',$room['gettj']);
		if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj) && (!in_array('0',$gettj) || $this->member['subscribe']!=1)){ 
			//不是所有人
			if(!$room['gettjtip']) $room['gettjtip'] = '没有权限预定该房型';
			return $this->json(['status'=>0,'msg'=>$room['gettjtip'],'url'=>$room['gettjurl']]);
		}
		$num = $post['num'];
		if($num>$room['limitnum']){
			return $this->json(['status'=>0,'msg'=>'最多可订'.$num.$this->text['间']]);
		}
		$where = [];
		$where[] = ['id','=',$room['hotelid']];
		$where[] = ['aid','=',aid];
		$hotel = Db::name('hotel')->where($where)->find();

		$dayCount = $post['dayCount'];
		$starttime = input('?param.startTime')?input('param.startTime'):'';
		$endtime   = input('?param.endTime')?input('param.endTime'):'';
		//$starttime 和 $endtime 2024.12.31新增加，需处理之前未增加的此参数跨年的问题
		if($starttime && $endtime){
			$startdate = $starttime;
			$enddate   = $endtime;
		}else{
			$startdate = $post['startDate'];
			$startdate = date('Y-').str_replace('月','-',$startdate);
			$enddate   = $post['endDate'];
			$enddate   = date('Y-').str_replace('月','-',$enddate);

			$nowmonth = strtotime(date("Y-m"));//现在月份
			//判断开始月份是否小于当前月份，若小于则为第二年
			$starttime  = strtotime($startdate);
			$startmonth = strtotime(date("Y-m",$starttime));//选项开始月份
			if($nowmonth>$startmonth){
				$postyear  = date('Y')+1;
				$startdate = $postyear.'-'.str_replace('月','-',$post['startDate']);
				$enddate   = $postyear.'-'.str_replace('月','-',$post['endDate']);
			}
			//判断结束月份是否小于当前月份，若小于则为第二年
			$endtime  = strtotime($enddate);//选项结束月份
			$endmonth = strtotime(date("Y-m",$endtime));//选项结束月份
			if($nowmonth>$endmonth){
				$postyear = date('Y')+1;
				$enddate  = $postyear.'-'.str_replace('月','-',$post['endDate']);
			}
		}
		$starttime = strtotime($startdate);
		$endtime   = strtotime($enddate);

		//同一时间不可下多笔订单
		if($hotel['islimit']==1){
			$where = [];
			$where[] = ['aid','=',aid];
			//$where[] = ['hotelid','=',$hotel['id']];
			$where[] = ['mid','=',mid];
			$where[] = ['status','in','1,2,3'];
			$where[] = Db::raw(" (unix_timestamp(in_date)<$endtime and unix_timestamp(leave_date)>$endtime) or unix_timestamp(leave_date)>$starttime");
			$ordercount = Db::name('hotel_order')->where($where)->count();
			if($ordercount>0){
				return $this->json(['status'=>0,'msg'=>'同一时间不可下多笔订单']);
			}
		}

		
		//查询每天的房价
		$totalroomprice = 0;
		$roomprices = [];
	
		$roomprice =  Db::name('hotel_room_prices')->where('roomid',$id)->where('datetime',$startdate)->find();
		$minstock = $roomprice['stock']?$roomprice['stock']:0;
		$isstatus=1;
		for($i=0;$i<$dayCount;$i++){
			$datetime = strtotime($startdate)+86400*$i;
			$date = date('Y-m-d',$datetime);
			$roomprice =  Db::name('hotel_room_prices')->where('roomid',$id)->where('datetime',$date)->find();
			if(!$roomprice['status']){
				$isstatus = 0;
			}
			/***会员等级定价*** */
			if($roomprice['lvprice'] === null){
				$roomprice['lvprice'] = $room['lvprice'];
				$roomprice['lvprice_data'] = $room['lvprice_data'];
			} 		 
			 
			if($roomprice['lvprice'] ==1 && $room['lvprice'] ==1){
				$lvprice_data = json_decode($roomprice['lvprice_data'],true);
				// $this->member['levelid']
				$levelid = $this->member['levelid']??1;
				$roomprice['sell_price'] = $lvprice_data[$levelid];
			} 		
			/**会员等级定价 */ 
		 
			$totalroomprice +=$roomprice['sell_price'];
			$roomprices[$i]['datetime'] = $roomprice['datetime'];
			$roomprices[$i]['sell_price'] = $roomprice['sell_price'];
			$roomprices[$i]['daymoney'] = $roomprice['daymoney'];
			if($roomprice['stock']<$minstock){
				$minstock = $roomprice['stock'];
			}
		}
		if(!$isstatus){
			return $this->json(['status'=>0,'msg'=>'该日期不可预订，请选择其他日期或其他房型']);
		}
		if($minstock==0){
			return $this->json(['status'=>0,'msg'=>'该日期已订满，请选择其他日期或其他房型']);
		}elseif($minstock<$num){
			return $this->json(['status'=>0,'msg'=>'该日期最多可订'.$minstock.$this->text['间'].'，请选择其他日期或其他房型']);
		}

		//服务费
		$service_money = 0;
		if($room['isservice_money']==1){
			$service_money = $room['service_money']*$num*$dayCount;
		}
		$yjcount = 0;
		//是否有存在未退押金的订单
		if($room['isiyajin']!='-1'){
			if($room['isyajin']==1){ 
				$yjorder =  Db::name('hotel_order_yajin')->where('aid',aid)->where('mid',mid)->where('refund_status','in','0')->where('yajin_money','>',0)->where('yd_num',$num)->find();
			}elseif($room['isyajin']==2){
				$yjorder =  Db::name('hotel_order_yajin')->where('aid',aid)->where('mid',mid)->where('refund_status','in','0')->where('yajin_money','>',0)->where('yajin_type',2)->find();
			}else{
				//跟随系统设置
				if($hotel['isyajin']==1){
					$yjorder =  Db::name('hotel_order_yajin')->where('aid',aid)->where('mid',mid)->where('refund_status','in','0')->where('yajin_money','>',0)->where('yd_num',$num)->find();
				}elseif($hotel['isyajin']==2){
					$yjorder =  Db::name('hotel_order_yajin')->where('aid',aid)->where('mid',mid)->where('refund_status','in','0')->where('yajin_money','>',0)->where('yajin_type',2)->find();
				}
			}
		}
		if($yjorder){
			$yjcount = 1;
		}
		$yajin=0;
		$yajintype=0;
		if(!$yjcount){
			if($room['isyajin']==1){
				$yajin = $room['yajin_money']*$num;
				$yajintype = 1;
			}else if($room['isyajin']==2){
				$yajin = $room['yajin_money'];
				$yajintype = 2;
			}elseif($room['isyajin']==-1){
				$yajin=0;
			}else{
				if($hotel['isyajin']==1){
					$yajin = $hotel['yajin_money']*$num;
					$yajintype = 1;
				}else if($hotel['isyajin']==2){
					$yajin = $hotel['yajin_money'];
					$yajintype = 2;
				}
			}	
		}

		$totalroomprice = $totalroomprice*$num;
		$totalprice = $totalroomprice+$service_money+$yajin;
		
		$money = $this->member['money'];
		$dikoubl = 0; 
		$dkmoney = 0; //抵扣金额
		$usednum = 0; 
		$usemoney = 0; //消耗多少余额

		if($hotel['money_dikou']==1 && $post['moneydec']){
			$dikoutext = json_decode($hotel['dikou_text'],true);
			
			for($i=0;$i<$num;$i++){
				$thisdikou = $dikoutext[0];
				foreach($dikoutext as $k=>$thisdikoutext){
					if($k <= $i) $thisdikou = $thisdikoutext;
				}
				for($j=0;$j<$dayCount;$j++){
					$thisdkmoney = $thisdikou['dikou_bl'] * $roomprices[$j]['sell_price'] * 0.01;
					$olddkmoney = $dkmoney;
					$dkmoney += $thisdkmoney;
					$oldusemoney = $usemoney;
					if($hotel['money_dikou_type'] == 0){ //余额抵扣的是金额
						$usemoney += $thisdkmoney;
						if($usemoney > $money){
							$usemoney = $money;
							$dkmoney = $money;
							break;
						}
					}else{ //余额抵扣的是天数 1余额代表1天
						$usemoney += $thisdikou['dikou_bl'] * $roomprices[$j]['daymoney'] * 0.01;
						if($usemoney > $money){
							$usemoney = $oldusemoney;
							$dkmoney = $olddkmoney;
							break;
						}
					}
				}
			}

			if($hotel['money_dikou_type'] == 1 && $usemoney > 0){
				$usemoney = round($usemoney);
			}

			/*
			foreach($dikou_text as $dikou){
				if($num>=$dikou['dikou_num']){
					$dikoubl = $dikou['dikou_bl'];
					$thisdkmoney = $dikou['dikou_bl'] * $totalroomprice * 0.01 * ($dikou['dikou_num'] - $usednum);
					$dkmoney += $thisdkmoney;
					$usednum = $dikou['dikou_num'];
					if($hotel['money_dikou_type'] == 0){ //余额抵扣的是金额
						$usemoney += $thisdkmoney;
					}else{ //余额抵扣的是天数 1余额代表1天
						$usemoney += $dikou['dikou_num']; * 0.01
					}
				}
			}
			*/
		}
		if($post['moneydec']==1){
			$leftmoney = $totalroomprice-$dkmoney;
		}else{
			$leftmoney = $totalroomprice;
		}
		
		$totalprice = $totalprice-$dkmoney;
		$scoredkmaxmoney = round($this->member['score'] * $hotel['score2money'],2);
		//积分抵扣
		$scoredkscore = 0;
		$scoredk_money = 0;
		if($post['usescore']==1){
			if($leftmoney<=0){
				return $this->json(['status'=>0,'msg'=>'房费为0不可使用'.t('积分').'抵扣']);
			}
			$score2money = $hotel['score2money'];
			$scoredkmaxpercent = $hotel['scoredkmaxpercent'];
			$scoredk_money = round($this->member['score'] * $score2money,$score_weishu);
			if($scoredkmaxpercent >= 0 && $scoredkmaxpercent < 100 && $scoredk_money > 0 && $scoredk_money > $leftmoney * $scoredkmaxpercent * 0.01){
				$scoredk_money = $leftmoney * $scoredkmaxpercent * 0.01;
			}else{
				if($scoredk_money > $scoredkmaxmoney) $scoredk_money = $scoredkmaxmoney;
			}
			$scoredk_money = round($scoredk_money,2);
			$totalprice = round($totalprice*100)/100;
			if($scoredk_money > 0){
				$scoredkscore = $scoredk_money / $score2money;
				$scoredkscore = dd_money_format($scoredkscore,$score_weishu);
			}
		}
		//$leftmoney = round($leftmoney - $scoredk_money,2);
		//$dkmoney = $totalroomprice*$dikoubl*0.01;

		$bid = $hotel['bid'];
		//优惠券
		try{
			Db::startTrans();
		if($post['couponrid'] > 0){
			if($leftmoney<=0){
				Db::rollback();
				return $this->json(['status'=>0,'msg'=>'房费为0不可使用'.t('优惠券').'抵扣']);
			}
			$couponrid = $post['couponrid'];
			$couponrecord = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('id',$couponrid)->whereRaw("bid=-1 or bid=".$bid)->find();
			if(!$couponrecord){
				Db::rollback();
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不存在']);
			}elseif($couponrecord['status']!=0){
				Db::rollback();
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'已使用过了']);	
			}elseif($couponrecord['starttime'] > time()){
				Db::rollback();
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'尚未开始使用']);	
			}elseif($couponrecord['endtime'] < time()){
				Db::rollback();
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'已过期']);	
			}elseif($couponrecord['minprice'] > $totalprice){
				Db::rollback();
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);	
			}elseif($couponrecord['type']!=6){
				Db::rollback();
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);	
			}
			$couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$couponrecord['couponid'])->find();
            if(empty($couponinfo)){
                Db::rollback();
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不存在或已作废']);
            }
            if($couponrecord['from_mid']==0 && $couponinfo && $couponinfo['isgive']==2){
                Db::rollback();
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'仅可转赠']);
            }
			if($couponinfo['fwtype']!==0 && $couponinfo['fwtype']!==2) {
				Db::rollback();
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
			}
            //适用场景
            $fwscene = [0,4];
            if(!in_array($couponinfo['fwscene'],$fwscene)){//全部可用 
                Db::rollback();
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
            }

			Db::name('coupon_record')->where('id',$couponrid)->update(['status'=>1,'usetime'=>time()]);
			$coupon_money = $couponrecord['money'];
			if($coupon_money > $totalprice) $coupon_money = $totalprice;
			
		}else{
			$coupon_money = 0;
		}
		$leftmoney = round($leftmoney-$scoredk_money- $coupon_money,2);
		$totalprice = $totalprice - $scoredk_money-$coupon_money;

		$ordernum = date('ymdHis').aid.rand(1000,9999);
		$orderdata = [];
		$orderdata['aid'] = aid;
		$orderdata['bid'] = $hotel['bid'];
		$orderdata['mid'] = mid;
		if(count($buydata) > 1){
			$orderdata['ordernum'] = $ordernum.'_'.$i;
		}else{
			$orderdata['ordernum'] = $ordernum;
		}
		$orderdata['pic'] = $room['pic'];
		$orderdata['title'] = $room['name'];
		$orderdata['hotelname'] = $hotel['name'];
		$orderdata['linkman'] = implode(',',$names);
		$orderdata['tel'] = $tel;
		$orderdata['sell_price'] = $totalroomprice;
		$orderdata['totalprice'] = $totalprice;
		$orderdata['totalnum'] = $num;
		$orderdata['yajin_money'] = $yajin;
		$orderdata['fuwu_money'] = $service_money;
		$orderdata['fuwu_money_dj'] = $room['service_money'];
		$orderdata['in_date'] = $startdate; 
		$orderdata['leave_date'] = $enddate; 
		$orderdata['roomid'] = $room['id']; 
		$orderdata['hotelid'] = $hotel['id'];
		$orderdata['signatureurl'] = $post['signatureurl'];
		$orderdata['daycount'] = $dayCount;
		$orderdata['message'] = $post['formdata']['message'];
		$orderdata['roomprices'] = json_encode($roomprices);
		$orderdata['createtime'] = time();
		$orderdata['hexiao_code'] = random(16);
		$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=hotel&co='.$orderdata['hexiao_code']));
		$orderdata['platform'] = platform;
		$orderdata['dikou_money'] = $dkmoney;
		$orderdata['use_money'] = $usemoney;
		$orderdata['scoredk_money'] = $scoredk_money;	//积分抵扣
		$orderdata['scoredkscore'] = $scoredkscore;		//抵扣掉的积分
		$orderdata['coupon_money'] = $coupon_money; //优惠券抵扣
		//抵扣完了剩余的现金
		$orderdata['leftmoney'] = $leftmoney;
		$orderdata['yajin_orderid'] = $yjorder['id']?$yjorder['id']:0;
		//dump($orderdata);die;

		if($hotel['isqianzi']==1){
			if($hotel['xieyiword']){
				$xieyiurl = $this->generate_contract($post['signatureurl'],$ordernum,$hotel);
				$orderdata['xieyi_word'] = $xieyiurl;
			}else{
				return $this->json(['status'=>0,'msg'=>'请上传签字协议文件']);
			}
		}
		$orderdata['yajin_type'] = $yajintype;
		/*if($usemoney>0){
			$res = \app\common\Member::addmoney(aid,mid,-$usemoney,t('余额').'抵扣，订单号: '.$orderdata['ordernum']);
			if($res['status'] != 1){
				return $this->json(['status'=>0,'msg'=>t('余额').'抵扣失败']);
			}
		}*/
		
		//计算佣金的商品金额
		$commission_totalprice = $totalroomprice;
		$agleveldata = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		if($agleveldata['can_agent'] > 0 && $agleveldata['commission1own']==1){
			$this->member['pid'] = mid;
		}
		if($room['commissionset']!=-1){
			if($this->member['pid']){
				$parent1 = Db::name('member')->where('aid',aid)->where('id',$this->member['pid'])->find();
				if($parent1){
					$agleveldata1 = Db::name('member_level')->where('aid',aid)->where('id',$parent1['levelid'])->find();
					if($agleveldata1['can_agent']!=0){
						$orderdata['parent1'] = $parent1['id'];
					}
				}
			}
			if($parent1['pid']){
				$parent2 = Db::name('member')->where('aid',aid)->where('id',$parent1['pid'])->find();
				if($parent2){
					$agleveldata2 = Db::name('member_level')->where('aid',aid)->where('id',$parent2['levelid'])->find();
					if($agleveldata2['can_agent']>1){
						$orderdata['parent2'] = $parent2['id'];
					}
				}
			}
			if($parent2['pid']){
				$parent3 = Db::name('member')->where('aid',aid)->where('id',$parent2['pid'])->find();
				if($parent3){
					$agleveldata3 = Db::name('member_level')->where('aid',aid)->where('id',$parent3['levelid'])->find();
					if($agleveldata3['can_agent']>2){
						$orderdata['parent3'] = $parent3['id'];
					}
				}
			}
			if($room['commissionset']==1){//按商品设置的分销比例
				$commissiondata = json_decode($room['commissiondata1'],true);
				if($commissiondata){
					if($agleveldata1) $orderdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01;
					if($agleveldata2) $orderdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $commission_totalprice * 0.01;
					if($agleveldata3) $orderdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $commission_totalprice * 0.01;
				}
			}elseif($room['commissionset']==2){//按固定金额
				$commissiondata = json_decode($room['commissiondata2'],true);
				if($commissiondata){
					if($agleveldata1) $orderdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'];
					if($agleveldata2) $orderdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'];
					if($agleveldata3) $orderdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'];
				}
			}elseif($room['commissionset']==3){//提成是积分
				$commissiondata = json_decode($room['commissiondata3'],true);
				if($commissiondata){
					if($agleveldata1) $orderdata['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'];
					if($agleveldata2) $orderdata['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'];
					if($agleveldata3) $orderdata['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'];
				}
			}else{ //按会员等级设置的分销比例
				if($agleveldata1){
					if($agleveldata1['commissiontype']==1){ //固定金额按单
						$orderdata['parent1commission'] = $agleveldata1['commission1'];	
					}else{
						$orderdata['parent1commission'] = $agleveldata1['commission1'] * $commission_totalprice * 0.01;
					}
				}
				if($agleveldata2){
					if($agleveldata2['commissiontype']==1){
						$orderdata['parent2commission'] = $agleveldata2['commission2'];				
					}else{
						$orderdata['parent2commission'] = $agleveldata2['commission2'] * $commission_totalprice * 0.01;
					}
				}
				if($agleveldata3){
					if($agleveldata3['commissiontype']==1){
						$orderdata['parent3commission'] = $agleveldata3['commission3'];
					}else{
						$orderdata['parent3commission'] = $agleveldata3['commission3'] * $commission_totalprice * 0.01;
					}
				}
			}
		}

		$orderid = Db::name('hotel_order')->insertGetId($orderdata);
		$re = $this->saveformdata($orderid,'hotel_order',$formdata,$hotel['id']);
		if($re['status'] == 0){
			Db::rollback();
			return $this->json(['status'=>0,'msg'=>$re['msg']]);
		}

		if($orderdata['parent1'] && ($orderdata['parent1commission'] || $orderdata['parent1score'])){
			Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent1'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$room['id'],'type'=>'hotel','commission'=>$orderdata['parent1commission'],'score'=>$orderdata['parent1score'],'remark'=>'下级预定房间奖励','createtime'=>time()]);
		}
		if($orderdata['parent2'] && ($orderdata['parent2commission'] || $orderdata['parent2score'])){
			Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent2'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$room['id'],'type'=>'hotel','commission'=>$orderdata['parent2commission'],'score'=>$orderdata['parent2score'],'remark'=>'下二级预定房间奖励','createtime'=>time()]);
		}
		if($orderdata['parent3'] && ($orderdata['parent3commission'] || $orderdata['parent3score'])){
			Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent3'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$room['id'],'type'=>'hotel','commission'=>$orderdata['parent3commission'],'score'=>$orderdata['parent3score'],'remark'=>'下三级预定房间奖励','createtime'=>time()]);
		}

		$payorderid = \app\model\Payorder::createorder(aid,$orderdata['bid'],$orderdata['mid'],'hotel',$orderid,$orderdata['ordernum'],$orderdata['title'],$orderdata['totalprice'],$orderdata['scoredkscore']);
            //创建订单完成事件
            \app\common\Order::order_create_done(aid,$orderid,'hotel');
	}catch(\Exception $e){
		Db::rollback();
		return $this->json(['status'=>0,'msg'=>$e->getMessage()]);
	}
	//  提交
	Db::commit();	
		return $this->json(['status'=>1,'orderid'=>$orderid,'payorderid'=>$payorderid,'msg'=>'提交成功']);
	}

	public function saveformdata($orderid,$type='hotel_order',$formdata,$proid){
		if(!$orderid || !$formdata) return ['status'=>0];
		//根据orderid 取出proid
		$formfield = Db::name('hotel')->where('id',$proid)->find();
		$formdataSet = json_decode($formfield['formdata'],true);
		//var_dump($formdataSet);die;
		$data = [];
		foreach($formdataSet as $k=>$v){
			$value = $formdata['form_'.$k];
			if(is_array($value)){
				$value = implode(',',$value);
			}
			$value = strval($value);
			$data['form'.$k] = $v['val1'] . '^_^' .$value . '^_^' .$v['key'];
			if($v['val3']==1 && $value===''){
				return ['status'=>0,'msg'=>$v['val1'].' 必填'];
			}
		}
		$data['aid'] = aid;
		$data['type'] = 'hotel_order';
		$data['orderid'] = $orderid;
		$data['createtime'] = time();

		 
		Db::name('freight_formdata')->insert($data);
		return ['status'=>1];
	}
	//生成合同文件，参数是签名图片的路径
	public static function generate_contract($sign_img_url,$ordernum,$hotel)
	{
		$hetong =  explode('upload/',$hotel['xieyiword']);
		//获取后台配置的模板文件
		//$contract = explode(',',$set['hetong']);
		//var_dump($contract);die;
		$contract_list = array();

		//$name = 'ht/'.$ordernum.'.docx';
		$name = aid.date('/Ym').'/'.$ordernum.'.docx';
		if (!file_exists(dirname(ROOT_PATH.'upload/'.$name))) {
			mk_dir(dirname(ROOT_PATH.'upload/'.$name));
		}
		$sign_image = explode('upload/',$sign_img_url);
		//读取模板文件
		//$templateProcessor = new TemplateProcessor(ROOT_PATH.'public'.$value);
		//$phpword = new PhpWord();
		$templateProcessor =new \PhpOffice\PhpWord\TemplateProcessor(ROOT_PATH.'upload/'.$hetong[1]);//实例化
		//模板变量替换
		//$templateProcessor->setValue('company_name', $companyname); //模板变量值替换
		$templateProcessor->setValue('sign_date',date('Y-m-d')); //变量值替换
		$templateProcessor->setImageValue('sign_pic', ['path' => ROOT_PATH.'upload/'.$sign_image[1], 'width' => 130, 'height' => 40, 'ratio' => true]); //写入图片
		//输出文件
		//$out_docx_name = $file_path.time().$key.'.docx';
		$url = PRE_URL.'/upload/'.$name;
		$templateProcessor->saveAs(ROOT_PATH.'upload/'.$name);
		//$contract_list[] = $out_docx_name;
		return $url;
	}

	public function orderlist(){
		$this->checklogin();
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$where[] = ['delete','=',0];

        if(input('param.keyword')) $where[] = ['ordernum|title', 'like', '%'.input('param.keyword').'%'];
		if($st == 'all'){
			
		}elseif($st == '0'){
			$where[] = ['status','=',0];
		}elseif($st == '1'){
			$where[] = ['status','=',1];
		}elseif($st == '2'){
			$where[] = ['status','=',2];
		}elseif($st == '3'){
			$where[] = ['status','=',3];
		}elseif($st == '4'){
			$where[] = ['status','=',4];
		}elseif($st == '5'){
			$where[] = ['status','=',5];
		}elseif($st == '10'){
			$where[] = ['refund_status','>',0];
		}
		if(input('param.hotelid')){
			$where[] = ['hotelid','=',input('param.hotelid')];
		}
		//var_dump($where);
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('hotel_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		foreach($datalist as &$d){
			$hotel =  Db::name('hotel')->where('id',$d['hotelid'])->find();
			$d['isrefund'] =0;
			if($hotel['isrefund']==1){
				$refunddate = $d['in_date'].' '.$hotel['refund_hour'].':00';
				$refundtime = strtotime($refunddate);
				if(time()<$refundtime){
					$d['isrefund'] =1;
				}
			}
			$d['hotel'] =$hotel;

		}
		if(!$datalist) $datalist = array();
		$rdata = [];
		$rdata['datalist'] = $datalist;
		if($pagenum==1){
			$rdata['set'] = $this->set;
			$rdata['st'] = $st;
			$rdata['text'] = $this->text;
		}
		$rdata['moneyunit'] = (!t('余额单位') || t('余额单位')=='余额单位')?'元':t('余额单位');
		return $this->json($rdata);
	}

	public function orderdetail(){
		$this->checklogin();
        $score_weishu = $this->score_weishu;
		$detail = Db::name('hotel_order')->where('id',input('param.id/d'))->where('aid',aid)->where('mid',mid)->find();
		$detail['formdata'] = \app\model\Freight::getformdata($detail['id'],'hotel_order');

        $detail['totalscore'] = dd_money_format($detail['totalscore'],$score_weishu);
		if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['daodian_time'] = $detail['daodian_time'] ? date('Y-m-d H:i:s',$detail['daodian_time']) : '';

		$hotel = Db::name('hotel')->where('aid',aid)->where('id',$detail['hotelid'])->find();

		$hotelset = Db::name('hotel_set')->where('aid',aid)->field('autoclose')->find();
		if($detail['status']==0 && $hotelset['autoclose'] > 0){
			$lefttime = strtotime($detail['createtime']) + $hotelset['autoclose']*60 - time();
			if($lefttime < 0) $lefttime = 0;
		}else{
			$lefttime = 0;
		}

		//费用明细
		$dayCount = $detail['daycount'];
		$startdate = $detail['in_date'];
		$totalroomprice = 0;
		$roomprices = [];
		for($i=0;$i<$dayCount;$i++){
			$datetime = strtotime($startdate)+86400*$i;
			$date = date('Y-m-d',$datetime);
			$roomprice =  Db::name('hotel_room_prices')->where('roomid',$detail['roomid'])->where('datetime',$date)->find();
			$totalroomprice +=$roomprice['sell_price'];
			$roomprices[$i]['datetime'] = $roomprice['datetime'];
			$roomprices[$i]['sell_price'] = $roomprice['sell_price'];
		}
		$where = [];
		$where[] = ['id','=',$detail['roomid']];
		$where[] = ['aid','=',aid];
		$room = Db::name('hotel_room')->where($where)->find();
		$room['pics'] = $room['pics']?explode(',',$room['pics']):[];
		//是否可申请退款
		$detail['isrefund'] =0;
		if($hotel['isrefund']==1){
			$refunddate = $startdate.' '.$hotel['refund_hour'].':00';
			$refundtime = strtotime($refunddate);
			if(time()<$refundtime){
				$detail['isrefund'] =1;
			}
		}
		$detail['yajin'] =  Db::name('hotel_order_yajin')->where('orderid',input('param.id/d'))->where('aid',aid)->where('mid',mid)->find();
		$getBedarr = \app\model\Hotel::getBedarr();
		$room['bedxing'] = $getBedarr['bedxing'][$room['bedxing']];			
		$room['breakfast'] = $getBedarr['zaocan'][$room['breakfast']];
		// dump( $room['breakfast']);die;
		$room['ischuanghu'] = $getBedarr['chuanghu'][$room['ischuanghu']];
		//$detail['leftmoney'] = $detail['totalprice']-$detail['yajin_money']-$detail['fuwu_money'];
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['detail'] = $detail;
		$rdata['storeinfo'] = $hotel;
		$rdata['lefttime'] = $lefttime;
		$rdata['room'] = $room;
		$rdata['text'] = $this->text;
		$rdata['totalroomprice'] = $totalroomprice;
		$rdata['roomprices'] = $roomprices;
		$rdata['moneyunit'] = (!t('余额单位') || t('余额单位')=='余额单位')?'元':t('余额单位');
		return $this->json($rdata);
	}

	public function closeOrder(){
		$this->checklogin();
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('hotel_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || $order['status']!=0){
			return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		$rs = Db::name('hotel_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>-1]);
        //关闭订单触发
        \app\common\Order::order_close_done(aid,$orderid,'hotle');
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}

    public function delOrder(){
		$this->checklogin();
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('hotel_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();

		if(!$order || ($order['status']!=4 && $order['status']!=3)){
			return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
		}
		if($order['status']==3){
			$rs = Db::name('hotel_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['delete'=>1]);
		}else{
			$rs = Db::name('hotel_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->delete();
		}
        \app\common\Order::order_close_done(aid,$orderid,'hotle');
		return $this->json(['status'=>1,'msg'=>'删除成功']);
	}


	//评价
	public function comment(){
		$this->checklogin();
		$oid = input('param.oid/d');
		$order = Db::name('hotel_order')->where('id',$oid)->where('mid',mid)->find();
		if(!$order){
			return $this->json(['status'=>0,'msg'=>'未查找到相关记录']);
		}
		$hotel =  Db::name('hotel')->field('name')->where('id',$order['hotelid'])->find();
		$order['hotelname'] = $hotel['name'];
		$comment = Db::name('hotel_comment')->where('orderid',$oid)->where('aid',aid)->where('mid',mid)->find();
		if(request()->isPost()){
			if($comment){
				return $this->json(['status'=>0,'msg'=>'您已经评价过了']);
			}		
			$content = input('post.content');
			$content_pic = input('post.content_pic');
			$score = input('post.score/d');
			if($score < 1){
				return $this->json(['status'=>0,'msg'=>'请打分']);
			}
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['bid'] = $order['bid'];
			$data['hotelid'] =$order['hotelid'];
			$data['hotelname'] = $order['hotelname'];
			$data['pic'] = $order['pic'];
			$data['orderid']= $order['id'];
			$data['ordernum']= $order['ordernum'];
			$data['score'] = $score;
			$data['content'] = $content;
			$data['openid']= $this->member['openid'];
			$data['nickname']= $this->member['nickname'];
			$data['headimg'] = $this->member['headimg'];
			$data['createtime'] = time();
			$data['content_pic'] = $content_pic;
			$data['roomid'] = $order['roomid'];
			$data['roomname'] = $order['title'];
			Db::name('hotel_comment')->insert($data);
			Db::name('hotel_order')->where('aid',aid)->where('mid',mid)->where('id',$oid)->update(['iscomment'=>1]);

			//如果不需要审核 增加产品评论数及评分
			$countnum = Db::name('hotel_comment')->where('hotelid',$order['hotelid'])->where('status',1)->count();
			$score = Db::name('hotel_comment')->where('hotelid',$order['hotelid'])->where('status',1)->avg('score'); //平均评分
			$haonum = Db::name('hotel_comment')->where('hotelid',$order['hotelid'])->where('status',1)->where('score','>',3)->count(); //好评数
			if($countnum > 0){
				$haopercent = $haonum/$countnum*100;
			}else{
				$haopercent = 100;
			}
			Db::name('hotel')->where('id',$order['hotelid'])->update(['comment_num'=>$countnum,'comment_score'=>$score,'comment_haopercent'=>$haopercent]);
			
			return $this->json(['status'=>1,'msg'=>'评价成功']);
		}
		$rdata = [];
		$rdata['order'] = $order;
		$rdata['comment'] = $comment;
		$rdata['text'] = $this->text;
		return $this->json($rdata);
	}


	//申请退款
	public function refundinit(){
		$this->checklogin();
	    //查询订单信息
        $detail = Db::name('hotel_order')->where('id',input('param.orderid/d'))->where('aid',aid)->where('mid',mid)->find();
        if(!$detail)
            return $this->json(['status'=>0,'msg'=>'订单不存在']);
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $storeinfo = Db::name('hotel')->where('id',$detail['hotelid'])->find();

        $rdata = [];
        $rdata['status'] = 1;
        $rdata['detail'] = $detail;
        $rdata['storeinfo'] = $storeinfo;

		//订阅消息
		$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
		$tmplids = [];

		if($wx_tmplset['tmpl_tuisuccess_new']){
			$tmplids[] = $wx_tmplset['tmpl_tuisuccess_new'];
		}elseif($wx_tmplset['tmpl_tuisuccess']){
			$tmplids[] = $wx_tmplset['tmpl_tuisuccess'];
		}
		if($wx_tmplset['tmpl_tuierror_new']){
			$tmplids[] = $wx_tmplset['tmpl_tuierror_new'];
		}elseif($wx_tmplset['tmpl_tuierror']){
			$tmplids[] = $wx_tmplset['tmpl_tuierror'];
		}
		if($detail['use_money']>0){
			if($wx_tmplset['tmpl_moneychange']){
				$tmplids[] = $wx_tmplset['tmpl_moneychange'];
			}
		}
		$rdata['tmplids'] = $tmplids;
		return $this->json($rdata);
	}

    public function refund(){//申请退款
		$this->checklogin();
		if(request()->isPost()){
			$post = input('post.');
			$orderid = intval($post['orderid']);
			$money = floatval($post['money']);
			$order = Db::name('hotel_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
			
			$hotel = Db::name('hotel')->where('aid',aid)->where('id',$order['hotelid'])->find();
			//是否可申请退款
			$isrefund=0;
			if($hotel['isrefund']==1){
				$refunddate = $order['in_date'].' '.$hotel['refund_hour'].':00';
				$refundtime = strtotime($refunddate);
				if(time()<$refundtime){
					$isrefund=1;
				}
			}
			if(!$isrefund){
				return $this->json(['status'=>0,'msg'=>'该订单不可申请退款']);
			}

			if(!$order || ($order['status']!=1 && $order['status'] != 2) || $order['refund_status'] == 2){
				return $this->json(['status'=>0,'msg'=>'订单状态不符合退款要求']);
			}
			if($money < 0 || $money > $order['totalprice']){
				return $this->json(['status'=>0,'msg'=>'退款金额有误']);
			}
			Db::name('hotel_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['refund_time'=>time(),'refund_status'=>1,'refund_reason'=>$post['reason'],'refund_money'=>$money]);
			

			$tmplcontent = [];
			$tmplcontent['first'] = '有'.$this->text['酒店'].'订单客户申请退款';
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $order['ordernum'];
			$tmplcontent['keyword2'] = $money.'元';
			$tmplcontent['keyword3'] = $post['reason'];
            $tmplcontentNew = [];
            $tmplcontentNew['number2'] = $order['ordernum'];//订单号
            $tmplcontentNew['amount4'] = $money;//退款金额
			\app\common\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,m_url('adminExt/order/orderlist'),$order['mdid'],$tmplcontentNew);

			$tmplcontent = [];
			$tmplcontent['thing1'] = $order['title'];
			$tmplcontent['character_string4'] = $order['ordernum'];
			$tmplcontent['amount2'] = $order['totalprice'];
			$tmplcontent['amount9'] = $money.'元';
			$tmplcontent['thing10'] = $post['reason'];
			\app\common\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,'adminExt/order/orderlist',$order['mdid']);

			return $this->json(['status'=>1,'msg'=>'提交成功,请等待商家审核']);
		}
		$rdata = [];
		$rdata['price'] = input('param.price/f');
		$rdata['orderid'] = input('param.orderid/d');
		$order = Db::name('hotel_order')->where('aid',aid)->where('mid',mid)->where('id',$rdata['orderid'])->find();
		$rdata['price'] = $order['totalprice'];

		return $this->json($rdata);
	}

	//酒店评价
	public function commentlist(){
		$hotelid = input('param.hotelid/d');
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['hotelid','=',$hotelid];
		$where[] = ['status','=',1];
		$datalist = Db::name('hotel_comment')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$pl){
			$datalist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
			if($datalist[$k]['content_pic']) $datalist[$k]['content_pic'] = explode(',',$datalist[$k]['content_pic']);
		}
		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}
	
	//申请退押金
	public function refundYajin()
    {
        $id = input('param.orderid/d');
        $post = input('post.');
        $detail = Db::name('hotel_order')->where('id',$id)->where('aid',aid)->where('mid',mid)->find();
        if(empty($detail)) return $this->json(['status'=>0,'msg'=>'订单不存在']);
		$orderyj = Db::name('hotel_order_yajin')->where('orderid',$id)->where('aid',aid)->where('mid',mid)->order('id desc')->find();
		if(!$orderyj) $orderyj = ['refund_status'=>$detail['yajin_refund_status']];
		if($orderyj['createtime']){
			$orderyj['createtime'] = date('Y-m-d H:i:s',$orderyj['createtime']);
		}
		if($orderyj['refund_time']){
			$orderyj['refund_time'] = date('Y-m-d H:i:s',$orderyj['refund_time']);
		}
       	if(request()->isPost()){
			//查看是否有已入住的订单，不让退押金
			$orderyglcount = Db::name('hotel_order')->where('yajin_orderid',$orderyj['id'])->where('status','3')->where('aid',aid)->where('mid',mid)->count();
			if($orderyglcount>0){
				return $this->json(['status'=>0,'msg'=>'有进行中的免押订单，暂不可申请退押金']);
			}

			$orderyj1 = Db::name('hotel_order_yajin')->where('orderid',$id)->where('refund_status',1)->where('aid',aid)->where('mid',mid)->find();
			if($orderyj1){
				return $this->json(['status'=>0,'msg'=>'该订单已申请，请等待审核']);
			}
			//修改押金状态
			Db::name('hotel_order_yajin')->where('id',$orderyj['id'])->where('aid',aid)->where('mid',mid)->update(['refund_status'=>1,'apply_time'=>time()]);
			

			Db::name('hotel_order')->where('id',$id)->where('aid',aid)->where('mid',mid)->update(['yajin_refund_status'=>1]);
			//发送消息通知
			$tmplcontent = [];
			$tmplcontent['first'] = '有'.$this->text['酒店'].'订单客户申请押金退款';
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $detail['ordernum'];
			$tmplcontent['keyword2'] = $detail['yajin_money'].'元';
			$tmplcontent['keyword3'] = '退押金';
            $tmplcontentNew = [];
            $tmplcontentNew['number2'] = $detail['ordernum'];//订单号
            $tmplcontentNew['amount4'] = $detail['yajin_money'];//退款金额
			\app\common\Wechat::sendhttmpl(aid,$detail['bid'],'tmpl_ordertui',$tmplcontent,m_url('adminExt/order/orderlist'),0,$tmplcontentNew);

			$tmplcontent = [];
			$tmplcontent['thing1'] = $detail['title'];
			$tmplcontent['character_string4'] = $detail['ordernum'];
			$tmplcontent['amount2'] = $detail['yajin_money'];
			$tmplcontent['amount9'] = $detail['yajin_money'].'元';
			$tmplcontent['thing10'] = '退押金';
			\app\common\Wechat::sendhtwxtmpl(aid,$detail['bid'],'tmpl_ordertui',$tmplcontent,'adminExt/order/orderlist',0);

			return $this->json(['status'=>1,'msg'=>'提交成功,请等待商家审核']);
        }


		//订阅消息
		$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
		$tmplids = [];

		if($wx_tmplset['tmpl_tuisuccess_new']){
			$tmplids[] = $wx_tmplset['tmpl_tuisuccess_new'];
		}elseif($wx_tmplset['tmpl_tuisuccess']){
			$tmplids[] = $wx_tmplset['tmpl_tuisuccess'];
		}
		if($wx_tmplset['tmpl_tuierror_new']){
			$tmplids[] = $wx_tmplset['tmpl_tuierror_new'];
		}elseif($wx_tmplset['tmpl_tuierror']){
			$tmplids[] = $wx_tmplset['tmpl_tuierror'];
		}
		$rdata['tmplids'] = $tmplids;
        $rdata['status'] = 1;
        $rdata['detail'] = $orderyj;
        return $this->json($rdata);
    }
}