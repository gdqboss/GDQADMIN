<?php
// JK客户定制

//custom_file(hotel)
// +----------------------------------------------------------------------
// | 酒店-房型管理
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class HotelRoom extends Common
{
	public $text;
    public function initialize(){
		parent::initialize();
		$this->set = Db::name('hotel_set')->where('aid',aid)->find();
		$this->text = \app\model\Hotel::gettext(aid);
		//if(bid > 0) showmsg('无访问权限');
	}
	 //选择商品
	 
	//房型列表
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

			$count = 0 + Db::name('hotel_room')->where($where)->count();
			$data = Db::name('hotel_room')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$getBedarr = \app\model\Hotel::getBedarr();
			$typearr = ['1'=>'即时确认','2'=>'手动确认'];
			foreach($data as &$d){
				$d['bedxing'] = $getBedarr['bedxing'][$d['bedxing']];
				$d['qrtype'] = $typearr[$d['qrtype']];
				$d['breakfast'] = $getBedarr['zaocan'][$d['breakfast']];
				$d['ischuanghu'] = $getBedarr['chuanghu'][$d['ischuanghu']];
				$hotel = Db::name('hotel')->where('id',$d['hotelid'])->find();
				$d['hotelname'] = $hotel['name'];
				$roomprice = Db::name('hotel_room_prices')->where('roomid',$d['id'])->where('aid',aid)->order('id desc')->find();
				$d['enddate'] = $roomprice['datetime'];

			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		
		View::assign('text',$this->text);
		return View::fetch();
    }
	//编辑房型
	public function edit(){
		if(input('param.id')){
			$info = Db::name('hotel_room')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('房型不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
		}
		$bset = Db::name('business_sysset')->where('aid',aid)->find();
		$getBedarr = \app\model\Hotel::getBedarr();

		$set = Db::name('hotel_set')->where('aid',aid)->find();
		$text = \app\model\Hotel::gettext(aid);

		$glist = Db::name('hotel_group')->where('aid',aid)->where('bid',bid)->where('status',1)->order('sort desc,id')->select()->toArray();
		
		$default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid',$default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
		$levellist = Db::name('member_level')->where('aid',aid)->where('cid',$default_cid)->order('sort,id')->select()->toArray();
		$gdlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('fenhong','>','0')->order('sort,id')->select()->toArray();
		$teamlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('teamfenhonglv','>','0')->order('sort,id')->select()->toArray();
		$areafhlevellist = Db::name('member_level')->where('aid',aid)->where('areafenhong','>','0')->select()->toArray();
	 
        $hotel = array();
        if($info && $info['hotelids']){
            $hotel = Db::name('hotel_room')->where('aid',aid) ->where('id','in',$info['hotelids'])->order('sort desc,id')->select()->toArray();
         }

        View::assign('hotel',$hotel);

		$info['gettj'] = explode(',',$info['gettj']);
		$info['lvprice_data']= json_decode($info['lvprice_data'],true);

		if(getcustom('teamfenhong_pingji')){
			$teampjlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('teamfenhong_pingji_lv','>','0')->order('sort,id')->select()->toArray();
			View::assign('teampjlevellist',$teampjlevellist);
		}
		$moneyunit = (!t('余额单位') || t('余额单位')=='余额单位')?'元':t('余额单位');

		View::assign('moneyunit',$moneyunit);
		View::assign('aglevellist',$aglevellist);
		View::assign('levellist',$levellist);
		View::assign('gdlevellist',$gdlevellist);
		View::assign('teamlevellist',$teamlevellist);
		View::assign('areafhlevellist',$areafhlevellist);
		View::assign('bset',$bset);

		View::assign('glist',$glist);
		View::assign('info',$info);
		View::assign('text',$text);
		View::assign('getBedarr',$getBedarr);
        View::assign('bid',bid);
		return View::fetch();
	}
	//保存房型
	public function save(){
		$hotel = Db::name('hotel')->where('aid',aid)->where('bid',bid)->find();
		if(!$hotel){
		   	return json(['status'=>0,'msg'=>'请先设置'.$this->text['酒店'].'再添加房型']);
		}
		if(input('post.id')){
			$room = Db::name('hotel_room')->where('aid',aid)->where('id',input('post.id/d'))->find();
			if(!$room) showmsg('房型不存在');
			if(bid != 0 && $room['bid']!=bid) showmsg('无权限操作');
		}
		$info = input('post.info/a');
		 
		if(!$info['name']){
			return json(['status'=>0,'msg'=>'房型名称不能为空']);
		}
		if(!$info['limitnum']){
			return json(['status'=>0,'msg'=>'可选'.$this->text['间'].'不能为0']);
		}
		if($info['gid']){
			$info['gid'] = implode(',',$info['gid']);
		}else{
			$info['gid'] = '';
		}
		$info['detail'] = \app\common\Common::geteditorcontent($info['detail']);
		$data = array();
		$data = $info;
		if($info['gettj']){
			$data['gettj'] = implode(',',$info['gettj']);
		}
		
		
		if(bid == 0){
			$data['fenhongset'] = $info['fenhongset'];
			$data['gdfenhongset'] = $info['gdfenhongset'];
			$data['gdfenhongdata1'] = jsonEncode(input('post.gdfenhongdata1/a'));
			$data['gdfenhongdata2'] = jsonEncode(input('post.gdfenhongdata2/a'));
			$data['teamfenhongset'] = $info['teamfenhongset'];
			$data['teamfenhongdata1'] = jsonEncode(input('post.teamfenhongdata1/a'));
			$data['teamfenhongdata2'] = jsonEncode(input('post.teamfenhongdata2/a'));
			$data['areafenhongset'] = $info['areafenhongset'];
			$data['areafenhongdata1'] = jsonEncode(input('post.areafenhongdata1/a'));
			$data['areafenhongdata2'] = jsonEncode(input('post.areafenhongdata2/a'));
			if(getcustom('teamfenhong_pingji')){
				$data['teamfenhongpjset'] = $info['teamfenhongpjset'];
				$data['teamfenhongpjdata1'] = jsonEncode(input('post.teamfenhongpjdata1/a'));
				$data['teamfenhongpjdata2'] = jsonEncode(input('post.teamfenhongpjdata2/a'));
			}
		}
		if(bid > 0){
            $bset = Db::name('business_sysset')->where('aid',aid)->find();
        }
		if(bid > 0 && $bset['commission_canset']!=1){
			$data['commissionset'] = -1;
        }else{
			$data['commissionset'] = $info['commissionset'];
			$data['commissiondata1'] = jsonEncode(input('post.commissiondata1/a'));
			$data['commissiondata2'] = jsonEncode(input('post.commissiondata2/a'));
			$data['commissiondata3'] = jsonEncode(input('post.commissiondata3/a'));
		}
		if(!$data['limitnum']) $data['limitnum']=7; //设置默认可选间数
		 $data['lvprice_data'] = json_encode($info['lvprice_data']);
		 
		if($room){
			if(!$room['createtime']){
				$data['createtime'] = time();
			}
			Db::name('hotel_room')->where('aid',aid)->where('id',$room['id'])->update($data);
			
			$roomid = $room['id'];
			//查询是否增加房价房态
			$roomprice = Db::name('hotel_room_prices')->where('aid',aid)->where('roomid',$room['id'])->order('id desc')->find();
			$maxenddate = time()+$hotel['yddatedays']*86400;
			if($roomprice['datetime']){
				$enddate = strtotime($roomprice['datetime']);
			}
			$days = round(($maxenddate-$enddate)/86400,0);
			$time = time();
			if($days>0){
				$begintime = $enddate;
				//添加后自动创建 30天的动态数据
				$data['bid'] = $room['bid'];
				\app\model\Hotel::addroomdays(aid,$hotel['id'],$data,$roomid,$days,$begintime);
			}else{
				$rooms = Db::name('hotel_room_prices')->where('aid',aid)->where('hotelid',$hotel['id'])->where('roomid',$roomid)->where("unix_timestamp(datetime)>".$time)->select()->toArray();
				foreach($rooms as $r){
					$data1 =[];
					if(!$rooms['week']){
						//dump($date);
						$time = strtotime($r['datetime']);
						$data1['week'] = \app\model\Hotel::getWeekday($time);
						$data1['stock'] = $info['stock'];
						$data1['sell_price'] = $info['sell_price'];
						$data1['daymoney'] = $info['daymoney']?$info['daymoney']:0;
						Db::name('hotel_room_prices')->where('aid',aid)->where('id',$r['id'])->update($data1);
					}
				}
			}

			\app\common\System::plog('编辑'.$this->text['酒店'].'房型'.$hotel['id']);
		}else{
			$data['aid'] = aid;
			$data['bid'] = bid;
			$data['hotelid'] = $hotel['id'];
			$data['createtime'] = time();
			$roomid = Db::name('hotel_room')->insertGetId($data);
			$begintime = time()-86400;
			//添加后自动创建 30天的动态数据
			\app\model\Hotel::addroomdays(aid,$hotel['id'],$data,$roomid,$hotel['yddatedays'],$begintime);
			\app\common\System::plog('添加'.$this->text['酒店'].'房型'.$roomid);
		}
 
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('hotel_room')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\common\System::plog($this->text['酒店'].'房型改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		if(bid != 0) showmsg('无权限操作');
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('hotel_room')->where('aid',aid)->where('bid',bid)->where('id',$id)->update(['ischecked'=>$st,'check_reason'=>$reason]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
        $where[] = ['bid','=',bid];
		$roomlist = Db::name('hotel_room')->where($where)->select();
		foreach($roomlist as $room){
			Db::name('hotel_room')->where('aid',aid)->where('bid',bid)->where('id',$room['id'])->delete();
			//将价格表一并删除
			Db::name('hotel_room_prices')->where('roomid',$room['id'])->delete();	
		}
		\app\common\System::plog($this->text['酒店'].'房型删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	
	//补充天数
	public function setroomprice(){
		$id = input('post.roomid/d');
		$bcdays = input('post.bcdays/d');
		$room = Db::name('hotel_room')->where('aid',aid)->where('id',$id)->find();
		if(!$room) showmsg('房型不存在');
		if(bid != 0 && $room['bid']!=bid) showmsg('无权限操作');

		//查询该房型最后一天的日期
		$roomprice = Db::name('hotel_room_prices')->where('aid',aid)->where('roomid',$id)->order('id desc')->find();
		$roomtime = strtotime($roomprice['datetime']);

		//自动创建 $bcdays天的动态数据
		for($i=1;$i<=$bcdays;$i++){
			$year = date('Y');
			$time = $roomtime +86400*$i;
			$date = date('m/d',$time);
			$data1 = [];
			$data1['aid'] = aid;
			$data1['bid'] = bid;
			$data1['roomid'] = $id;
			$data1['date'] = $date;
			$data1['sell_price'] = $room['sell_price'];
			$data1['stock'] = $room['stock'];
			$data1['name'] = $room['name'];
			$data1['year'] = $year;
			$data1['datetime'] = date('Y-m-d',$time);
			//dump($date);
			$data1['week'] = \app\model\Hotel::getWeekday($time);
			$data1['hotelid'] = $room['hotelid'];
			if($room['isdaymoney']==1){
				$data1['daymoney'] = $room['daymoney'];
			}else{
				$data1['daymoney'] = 0;
			}
			$roomprice = Db::name('hotel_room_prices')->where('aid',aid)->where('roomid',$id)->where('datetime',$time)->find();
			if(!$roomprice){
				Db::name('hotel_room_prices')->insertGetId($data1);	
			}
		}
		\app\common\System::plog('房型补充天数id:'.$id);
		return json(['status'=>1,'msg'=>'补充成功']);

	}

 //选择房型
	public function choosehotelroom(){
		// ajax
		if(request()->isAjax()){
	
			$page = input('param.page');
				$limit = input('param.limit');
				if(input('param.field') && input('param.order')){
					$order = input('param.field').' '.input('param.order');
				}else{
					$order = 'sort desc,id desc';
				}
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',$this->user['bid']];
		
			$roomlist = Db::name('hotel_room')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$count = 0 + Db::name('hotel_room')->where($where)->count();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$roomlist]);
		}
		return View::fetch();
	}

	public function chooseproduct(){
		return View::fetch();
	}
	//房型详情
	 
	public function gethotelroom(){
		$proid = input('post.proid/d');
		$room = Db::name('hotel_room')->where('aid',aid)->where('id',$proid)->find();
		 
		return json(['room'=>$room]);
	}
}
