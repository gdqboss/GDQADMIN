<?php
// JK客户定制

//custom_file(hotel)
// +----------------------------------------------------------------------
// | 酒店-酒店订单
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;
class HotelOrder extends Common
{
	public $text;
    public function initialize(){
		parent::initialize();
		//if(bid > 0) showmsg('无访问权限');
		$this->set = Db::name('hotel_set')->where('aid',aid)->find();
		$this->text = \app\model\Hotel::gettext(aid);
	}
	//订单列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
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
			if(input('param.roomid')){
				$where[] = ['roomid','=',input('param.roomid')];
				$where[] = ['status','in','1,2,3,4'];
			}
			if(input('param.hotelid')){
				$where[] = ['hotelid','=',input('param.hotelid')];
			}
			if(input('param.yajin_orderid')){
				$where[] = ['yajin_orderid','=',input('param.yajin_orderid')];
			}
			if(input('param.orderids')) $where[] = ['id','in',input('param.orderids')];
			if(input('param.orderid')) $where[] = ['id','=',input('param.orderid')];
			if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
			if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
			if(input('param.linkman')) $where[] = ['linkman','like','%'.input('param.linkman').'%'];
			if(input('param.tel')) $where[] = ['tel','like','%'.input('param.tel').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			if(input('?param.status') && input('param.status')!==''){
				if(input('param.status') == 5){
					$where[] = ['refund_status','=',1];
				}elseif(input('param.status') == 6){
					$where[] = ['refund_status','=',2];
				}elseif(input('param.status') == 7){
					$where[] = ['refund_status','=',3];
				}else{
					$where[] = ['status','=',input('param.status')];
				}
			}	
			if(input('?param.yajin_status') && input('param.yajin_status')!==''){
				if(input('param.yajin_status') == 1){
					$where[] = ['status','>','0'];
					$where[] = ['yajin_refund_status','in','0,1,3'];
				}elseif(input('param.yajin_status') == 2){
					$where[] = ['yajin_refund_status','=',2];
				}
			}
	
			$count = 0 + Db::name('Hotel_order')->where($where)->count();
			//echo M()->_sql();
			$list = Db::name('Hotel_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
           
			foreach($list as $k=>$vo){
				$member = Db::name('member')->where('id',$vo['mid'])->find();
				$list[$k]['nickname'] = $member['nickname'];
				$list[$k]['headimg'] = $member['headimg'];
				$hotel = Db::name('hotel')->field('name')->where('aid',aid)->where('id',$vo['hotelid'])->find();
				$list[$k]['hotelname'] = $hotel['name'];

			}
            if($page==1){
                //统计
                $tongji = Db::name('hotel_order')->where('aid',aid)->where($where)->field('sum(totalprice) as z_totalprice,sum(totalnum) as z_totalnum,sum(yajin_money) as z_yajin_money,sum(use_money) as z_use_money,sum(fuwu_money) as z_fuwu_money,sum(leftmoney) as z_leftmoney')->find();
            }
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list,'tongji'=>$tongji??[]]);
		}
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
        $count = Db::name('hotel_order')->where('aid',aid)->where($where)->group('status')->field('count(id) as total,status')->select()->toArray();
		$total = Db::name('hotel_order')->where('aid',aid)->where($where)->count('id');
		$moneyunit = (!t('余额单位') || t('余额单位')=='余额单位')?'元':t('余额单位');
		View::assign('total',$total);  
		$tempArr = array_column($count, null, 'status'); 
		View::assign('tempArr',$tempArr);
		$hotel = Db::name('hotel')->field('refundyj_type')->where('aid',aid)->where('bid',bid)->find();
		View::assign('hotel',$hotel);
		$hotellist =  Db::name('hotel')->where('aid',aid)->where('bid','<>',0)->where('status',1)->select()->toArray();
		View::assign('hotellist',$hotellist);
		View::assign('text',$this->text);
		View::assign('moneyunit',$moneyunit);
		return View::fetch();
    }
	//导出
	public function excel(){
		set_time_limit(0);
		ini_set('memory_limit', '2000M');
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'id desc';
		}
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
		$where = [];
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
		if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
		if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
		if(input('param.linkman')) $where[] = ['linkman','like','%'.input('param.linkman').'%'];
		if(input('param.tel')) $where[] = ['tel','like','%'.input('param.tel').'%'];
		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['createtime','>=',strtotime($ctime[0])];
			$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
		}
		if(input('?param.status') && input('param.status')!==''){
			if(input('param.status') == 5){
				$where[] = ['refund_status','=',1];
			}elseif(input('param.status') == 6){
				$where[] = ['refund_status','=',2];
			}elseif(input('param.status') == 7){
				$where[] = ['refund_status','=',3];
			}else{
				$where[] = ['status','=',input('param.status')];
			}
		}
		if(input('?param.yajin_status') && input('param.yajin_status')!==''){
			if(input('param.yajin_status') == 1){
				$where[] = ['status','>','0'];
				$where[] = ['yajin_refund_status','in','0,1,3'];
			}elseif(input('param.yajin_status') == 2){
				$where[] = ['yajin_refund_status','=',2];
			}
		}

		if(input('param.hotelid')){
			$where[] = ['hotelid','=',input('param.hotelid')];
		}
		$text = \app\model\Hotel::gettext(aid);
		$moneyunit = (!t('余额单位') || t('余额单位')=='余额单位')?'元':t('余额单位');

		$list = Db::name('hotel_order')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('hotel_order')->where($where)->order($order)->count();
		$title = array('订单号','下单人','酒店名称','房间名称','数量','支付总金额','支付房费','押金',$text['服务费'],t('余额').'抵扣','支付方式','姓名','电话','入住日期','离店日期','客户留言','后台备注','下单时间','状态','备注');
		$data = [];
		foreach($list as $k=>$vo){
			$member = Db::name('member')->where('id',$vo['mid'])->find();
			$hotel =  Db::name('hotel')->field('name')->where('id',$vo['hotelid'])->find();
			$status='';
			if($vo['status']==0){
				$status = '未支付';
			}elseif($vo['status']==1){
				$status = '待确认';
			}elseif($vo['status']==2){
				$status = '待使用';
			}elseif($vo['status']==3){
				$status = '已到店';
			}elseif($vo['status']==4){
				$status = '已完成';
			}elseif($vo['status']==-1){
				$status = '已关闭';
			}
			$sell_price = $vo['sell_price'];
			if($vo['leftmoney']>0 && $vo['use_money']>0){
				$sell_price = $vo['leftmoney'].'+'.$vo['use_money'].$moneyunit;
			}elseif($vo['leftmoney']==0 && $vo['use_money']>0){
				$sell_price = $vo['use_money'].$moneyunit;
			}elseif($vo['leftmoney']>0 && $vo['use_money']==0){
				$sell_price = $vo['leftmoney'];
			}
			$data[$k] = [
				' '.$vo['ordernum'],
				$member['nickname'],
				$hotel['name'],
				$vo['title'],
                $vo['totalnum'],
				$vo['totalprice'].'+'.$vo['use_money'].$moneyunit,
				$sell_price,
				$vo['yajin_money'],
				$vo['fuwu_money'],
				$vo['use_money'].$moneyunit,
				$vo['paytype'],
				$vo['linkman'],
				$vo['tel'],
				$vo['in_date'],
				$vo['leave_date'],
				$vo['message'],
				$vo['remark'],
				date('Y-m-d H:i:s',$vo['createtime']),
				$status,
			];
 
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//订单详情
	public function getdetail(){
		$orderid = input('post.orderid');
		$order = Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->find();
		$order['formdata'] = \app\model\Freight::getformdata($order['id'],'hotel_order');

		if(!$order) return json(['status'=>1,'msg'=>'订单不存在']);
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if($order['coupon_rid']){
			$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
		}else{
			$couponrecord = false;
		}
        $score_weishu = 0;
        $hotel =  Db::name('hotel')->where('aid',aid)->where('id',$order['hotelid'])->find();
		$order['hotelname'] = $hotel['name'];
        $order['totalscore'] = dd_money_format($order['totalscore'],$score_weishu);
		$member = Db::name('member')->field('id,nickname,headimg,realname,tel,wxopenid,unionid')->where('id',$order['mid'])->find();
		if(!$member) $member = ['id'=>$order['mid'],'nickname'=>'','headimg'=>''];
		$comdata = array();
		$comdata['parent1'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
		$comdata['parent2'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
		$comdata['parent3'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
		foreach($oglist as $v){
			if($v['parent1']){
				$parent1 = Db::name('member')->where('id',$v['parent1'])->find();
				$comdata['parent1']['mid'] = $v['parent1'];
				$comdata['parent1']['nickname'] = $parent1['nickname'];
				$comdata['parent1']['headimg'] = $parent1['headimg'];
				$comdata['parent1']['money'] += $v['parent1commission'];
				$comdata['parent1']['score'] += $v['parent1score'];
			}
			if($v['parent2']){
				$parent2 = Db::name('member')->where('id',$v['parent2'])->find();
				$comdata['parent2']['mid'] = $v['parent2'];
				$comdata['parent2']['nickname'] = $parent2['nickname'];
				$comdata['parent2']['headimg'] = $parent2['headimg'];
				$comdata['parent2']['money'] += $v['parent2commission'];
				$comdata['parent2']['score'] += $v['parent2score'];
			}
			if($v['parent3']){
				$parent3 = Db::name('member')->where('id',$v['parent3'])->find();
				$comdata['parent3']['mid'] = $v['parent3'];
				$comdata['parent3']['nickname'] = $parent3['nickname'];
				$comdata['parent3']['headimg'] = $parent3['headimg'];
				$comdata['parent3']['money'] += $v['parent3commission'];
				$comdata['parent3']['score'] += $v['parent3score'];
			}
		}
		
		$orderyajin =  Db::name('hotel_order_yajin')->where('orderid',$orderid)->where('aid',aid)->where('mid',$order['mid'])->find();
			
		return json(['order'=>$order,'member'=>$member,'comdata'=>$comdata,'orderyajin'=>$orderyajin]);
	}
	
	//设置备注
	public function setremark(){
		$orderid = input('post.orderid/d');
		$content = input('post.content');
		if(bid == 0){
			Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->update(['remark'=>$content]);
		}else{
			Db::name('hotel_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['remark'=>$content]);
		}
		\app\common\System::plog($this->text['酒店'].'订单设置备注'.$orderid);
		return json(['status'=>1,'msg'=>'设置完成']);
	}
	//改价格
	public function changeprice(){
		$orderid = input('post.orderid/d');
		$newprice = input('post.newprice/f');
		$newordernum = date('ymdHis').rand(100000,999999);
		if(bid == 0){
			Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->update(['totalprice'=>$newprice,'ordernum'=>$newordernum]);
		}else{
			Db::name('hotel_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['totalprice'=>$newprice,'ordernum'=>$newordernum]);
		}
		\app\common\System::plog($this->text['酒店'].'订单改价格'.$orderid);
		return json(['status'=>1,'msg'=>'修改完成']);
	}
	//关闭订单
	public function closeOrder(){
		$orderid = input('post.orderid/d');
		$order = Db::name('hotel_order')->where('id',$orderid)->where('aid',aid)->find();
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if(!$order || $order['status']!=0){
			return json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		$rs = Db::name('hotel_order')->where('id',$orderid)->where('aid',aid)->update(['status'=>-1]);
        //关闭订单触发
        \app\common\Order::order_close_done(aid,$orderid,'hotel');
		\app\common\System::plog($this->text['酒店'].'订单关闭订单'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//改为已支付
	public function ispay(){
		if(bid > 0) showmsg('无权限操作');
		$orderid = input('post.orderid/d');
		$order = Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->find();
		if(!$order){
			return json(['status'=>0,'msg'=>'订单不存在']);
		}

		$room = Db::name('hotel_room')->where('id',$order['roomid'])->find();
		//加销量
		\app\model\Hotel::addroomsales($order,$order['totalnum']);
		
		//增加押金记录
		if($order['yajin_money']>0){
		    $yajin = Db::name('hotel_order_yajin')->where('aid',aid)->where('orderid',$orderid)->where('mid',mid)->find();
		    if(!$yajin){
		       	$yjdata = [];
    			$yjdata['aid'] = $order['aid'];
    			$yjdata['bid'] = $order['bid'];
    			$yjdata['mid'] = $order['mid'];
    			$yjdata['orderid'] = $order['id'];
    			$yjdata['ordernum'] = $order['ordernum'];
    			$yjdata['yajin_money'] = $order['yajin_money'];
    			$yjdata['yajin_type'] = $order['yajin_type'];
    			$yjdata['refund_money'] = $order['yajin_money'];
    			$yjdata['refund_status'] = 0;
    			$yjdata['refund_ordernum'] = '' . date('ymdHis') . rand(100000, 999999);
    			//$yjdata['apply_time'] = time();
    			$yajinid = Db::name('hotel_order_yajin')->insertGetId($yjdata); 
		    }
		}
		$data = [];
	    $data['paytime'] = time();
	    $data['paytype']='后台支付';
		//是否为即时确认
		if($room['qrtype']==1){
            $data['status'] = 2;
            $data['confirm_time'] = time();
		}else{
		   $data['status'] = 1;
		}
		
		Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->update($data);
		if($room['qrtype']==1){
		    //发送消息通知
			\app\model\Hotel::sendNotice(aid,$order);
		}
		\app\common\System::plog($this->text['酒店'].'订单改为已支付'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}

	//退款审核
	public function refundCheck(){
		$orderid = input('post.orderid/d');
		$st = input('post.st/d');
		$remark = input('post.remark');
		$order = Db::name('hotel_order')->where('id',$orderid)->where('aid',aid)->find();
		if(!$order){
			return json(['status'=>0,'msg'=>'订单不存在']);
		}
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if($st==2){
			Db::name('hotel_order')->where('id',$orderid)->where('aid',aid)->update(['refund_status'=>3,'refund_checkremark'=>$remark]);
			
			//退款申请驳回通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的退款申请被商家驳回，可与商家协商沟通。';
			$tmplcontent['remark'] = $remark.'，请点击查看详情~';
			$tmplcontent['orderProductPrice'] = $order['refund_money'];
			$tmplcontent['orderProductName'] = $order['title'];
			$tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $order['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
			\app\common\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['amount3'] = $order['refund_money'];
			$tmplcontent['thing2'] = $order['title'];
			$tmplcontent['character_string1'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $order['refund_money'];
			$tmplcontentnew['thing8'] = $order['title'];
			$tmplcontentnew['character_string4'] = $order['ordernum'];
			\app\common\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
			//短信通知
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($member['tel']){
				$tel = $member['tel'];
			}else{
				$tel = $order['tel'];
			}
			$rs = \app\common\Sms::send(aid,$tel,'tmpl_tuierror',['ordernum'=>$order['ordernum'],'reason'=>$remark]);
			\app\common\System::plog('订单退款驳回'.$orderid);
			return json(['status'=>1,'msg'=>'退款已驳回']);
		}elseif($st == 1){
			if($order['status']!=1 && $order['status']!=2){
				return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
			}

            if($order['refund_money'] > 0) {
                $rs = \app\common\Order::refund($order,$order['refund_money'],$order['refund_reason']);
                if($rs['status']==0){
                    return json(['status'=>0,'msg'=>$rs['msg'] ? $rs['msg'] : 'error']);
                }
            }
			Db::name('hotel_order')->where('id',$orderid)->where('aid',aid)->update(['status'=>-1,'refund_status'=>2,'refund_checkremark'=>$remark]);
            //退款减去销量 增加库存
            \app\model\Hotel::addroomsales($order,-$order['totalnum']);

            //余额返还
            if($order['use_money'] > 0){
                \app\common\Member::addmoney(aid,$order['mid'],$order['use_money'],$this->text['酒店'].'订单退款返还');
            }
			$orderyajin =  Db::name('hotel_order_yajin')->where(['orderid'=>$orderid,'aid'=>aid, 'mid' => $order['mid'],'refund_status'=>0])->find();
			if($orderyajin){
				 Db::name('hotel_order_yajin')->where('id',$orderyajin['id'])->update(['refund_status'=>2,'refund_time'=>time(),'refund_reason'=>$order['refund_reason']]);
			}
			//其他使用押金的订单
			if($order['yajin_type']==2 && $order['yajin_orderid']>0){
				$yj_order = Db::name('hotel_order_yajin')->where('id',$order['yajin_orderid'])->find();
				$otherlist = Db::name('hotel_order')->where('yajin_orderid',$yj_order['id'])->where('mid',$order['mid'])->where('aid',aid)->where('status','in','1,2,3')->select()->where('id','<>',$yj_order['orderid'])->toArray();
				foreach($otherlist as $k=>$o){
					$realstarttime = strtotime($o['in_date']);
					$realendtime = strtotime($o['leave_date']);
					$datetime2 =  time();
					//提前了几天
					$beforedays = abs($datetime2 - $realendtime) / 86400;
					//如果退款时间大于 入住时间则直接退款 /未住店状态
					if($datetime2<$realstarttime || $beforedays<1 || $o['status']==1){
						//退抵扣的余额
						if($o['use_money']){
							\app\common\Member::addmoney(aid,$o['mid'],$o['use_money'],t('余额').'抵扣退款: '.$o['ordernum']);
						}
						//退押金退回
						$rs = \app\common\Order::refund($o,$o['totalprice'],'关联押金订单退款退回');
						//退回的日期增加库存
						for($i=0;$i<$o['daycount'];$i++){
							$meiday = $realstarttime+$i*86400;
							$meidate = date('Y-m-d',$meiday);
							//库存增加
							Db::name('hotel_room_prices')->where('aid',aid)->where('roomid',$o['roomid'])->where('datetime',$meidate)->inc('stock',$o['totalnum'])->update();
							//销量减少
							Db::name('hotel_room_prices')->where('aid',aid)->where('roomid',$o['roomid'])->where('datetime',$meidate)->dec('sales',$o['totalnum'])->update();
						}		
						Db::name('hotel')->where('aid',aid)->where('id',$o['hotelid'])->dec('sales',$o['totalnum'])->update();
						Db::name('hotel_order')->where('id',$o['id'])->where('aid',aid)->update(['status'=>-1,'refund_status'=>2,'refund_money'=>$o['totalprice'],'refund_reason'=>'关联押金订单退款']);
					}elseif($datetime2>$realendtime){
						//如果退款时间大于离店时间 则直接完成订单
						$rs = \app\common\Order::collect($o, 'hotel');
						Db::name('hotel_order')->where('aid',aid)->where('id',$o['id'])->update(['status'=>4,'collect_time'=>time()]);

					}else if($beforedays>=1){ //如果在住店期间，且未离店，查看提前了几天
						$rs = \app\model\Hotel::beforeEnd($o,$beforedays);
						if($rs['refund_money']>0){
							\app\common\Member::addmoney(aid,$o['mid'],$rs['refund_money'],t('余额').'抵扣退款: '.$o['ordernum']);
						}
						//提前退服务费
						$fuwufei = $beforedays*$o['totalnum']*$o['fuwu_money_dj'];
						$refund_price = $fuwufei+$rs['refund_price'];
						$rs = \app\common\Order::refund($o,$refund_price,'提前离店');
						if($rs['status']==0){
							return json(['status'=>0,'msg'=>$rs['msg']]);
						}
						//提前的日期增加库存
						for($i=0;$i<$beforedays;$i++){
							$meiday = $datetime1+$i*86400;
							$meidate = date('Y-m-d',$meiday);
							Db::name('hotel_room_prices')->where('aid',$o['aid'])->where('roomid',$o['roomid'])->where('datetime',$meidate)->inc('stock',$o['totalnum'])->update();
						}
						$real_leavedate = date('Y-m-d',time());
						$rs = \app\common\Order::collect($o, 'hotel');
						if($rs['status']==0) return $rs;
						Db::name('hotel_order')->where('aid',aid)->where('id',$o['id'])->update(['status'=>4,'collect_time'=>time(),'fuwu_refund_money'=>$fuwufei,'fuwu_refund_time'=>time(),'real_leavedate'=>$real_leavedate]);
					}		
				}

			}

            //关闭订单触发
            \app\common\Order::order_close_done(aid,$orderid,'hotel');
			//退款成功通知
			$tmplcontent = [];
            $tmplcontent['first'] = '您的订单已经完成退款，';
            if ($order['refund_money']) {
                $tmplcontent['first'] .= '¥'.$order['refund_money'].' ';
            }
            if ($order['totalscore']) {
                $tmplcontent['first'] .= $order['totalscore']. t('积分') . '，已经退回您的付款账户，请留意查收。';
            }
			$tmplcontent['remark'] = $remark.'，请点击查看详情~';
			$tmplcontent['orderProductPrice'] = $order['refund_money'];
			$tmplcontent['orderProductName'] = $order['title'];
			$tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $order['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
			\app\common\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['amount6'] = $order['refund_money'];
			$tmplcontent['thing3'] = $order['title'];
			$tmplcontent['character_string2'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $order['refund_money'];
			$tmplcontentnew['thing6'] = $order['title'];
			$tmplcontentnew['character_string4'] = $order['ordernum'];
			\app\common\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

			//短信通知
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($member['tel']){
				$tel = $member['tel'];
			}else{
				$tel = $order['tel'];
			}
			$rs = \app\common\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$order['refund_money']]);
			
			\app\common\System::plog($this->text['酒店'].'订单退款审核通过并退款'.$orderid);
			return json(['status'=>1,'msg'=>'已退款成功']);
		}
	}
	//退款
	public function refund(){
		$orderid = input('post.orderid/d');
		$reason = input('post.reason');
		$order = Db::name('hotel_order')->where('id',$orderid)->where('aid',aid)->find();
		if(!$order){
			return json(['status'=>0,'msg'=>'订单不存在']);
		}
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');

		$refund_money = $order['totalprice'];
		if($order['status']!=1 && $order['status']!=2){
			return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
		}

        if($refund_money > 0) {
            $rs = \app\common\Order::refund($order,$refund_money,$reason);
            if($rs['status']==0){
                return json(['status'=>0,'msg'=>$rs['msg']]);
            }
        }
		Db::name('hotel_order')->where('id',$orderid)->where('aid',aid)->update(['status'=>-1,'refund_status'=>2,'refund_money'=>$refund_money,'refund_reason'=>$reason]);
	    //退款减去销量 增加库存
		\app\model\Hotel::addroomsales($order,-$order['totalnum']);

		//余额返还
		if($order['use_money'] > 0){
			\app\common\Member::addmoney(aid,$order['mid'],$order['use_money'],$this->text['酒店'].'订单退款返还');
		}

		$orderyajin =  Db::name('hotel_order_yajin')->where(['orderid'=>$orderid,'aid'=>aid, 'mid' => $order['mid'],'refund_status'=>0])->find();
		if($orderyajin){
			 Db::name('hotel_order_yajin')->where('id',$orderyajin['id'])->update(['refund_status'=>2,'refund_time'=>time(),'refund_reason'=>$order['refund_reason']]);
		}
		//其他使用押金的订单
		if($order['yajin_type']==2 && $order['yajin_orderid']>0){
			$yj_order = Db::name('hotel_order_yajin')->where('id',$order['yajin_orderid'])->find();
			$otherlist = Db::name('hotel_order')->where('yajin_orderid',$yj_order['id'])->where('mid',$order['mid'])->where('aid',aid)->where('status','in','1,2,3')->select()->where('id','<>',$yj_order['orderid'])->toArray();
			foreach($otherlist as $k=>$o){
				$realstarttime = strtotime($o['in_date']);
				$realendtime = strtotime($o['leave_date']);
				$datetime2 =  time();
				//提前了几天
				$beforedays = abs($datetime2 - $realendtime) / 86400;
				//如果退款时间大于 入住时间则直接退款 /未住店状态
				if($datetime2<$realstarttime || $beforedays<1 || $o['status']==1){
					//退抵扣的余额
					if($o['use_money']){
						\app\common\Member::addmoney(aid,$o['mid'],$o['use_money'],t('余额').'抵扣退款: '.$o['ordernum']);
					}
					//退押金退回
					$rs = \app\common\Order::refund($o,$o['totalprice'],'关联押金订单退款退回');
					//退回的日期增加库存
					for($i=0;$i<$o['daycount'];$i++){
						$meiday = $realstarttime+$i*86400;
						$meidate = date('Y-m-d',$meiday);
						//库存增加
						Db::name('hotel_room_prices')->where('aid',aid)->where('roomid',$o['roomid'])->where('datetime',$meidate)->inc('stock',$o['totalnum'])->update();
						//销量减少
						Db::name('hotel_room_prices')->where('aid',aid)->where('roomid',$o['roomid'])->where('datetime',$meidate)->dec('sales',$o['totalnum'])->update();
					}		
					Db::name('hotel')->where('aid',aid)->where('id',$o['hotelid'])->dec('sales',$o['totalnum'])->update();
					Db::name('hotel_order')->where('id',$o['id'])->where('aid',aid)->update(['status'=>-1,'refund_status'=>2,'refund_money'=>$o['totalprice'],'refund_reason'=>'关联押金订单退款']);
				}elseif($datetime2>$realendtime){
					//如果退款时间大于离店时间 则直接完成订单
					$rs = \app\common\Order::collect($o, 'hotel');
					Db::name('hotel_order')->where('aid',aid)->where('id',$o['id'])->update(['status'=>4,'collect_time'=>time()]);

				}else if($beforedays>=1){ //如果在住店期间，且未离店，查看提前了几天
					$rs = \app\model\Hotel::beforeEnd($o,$beforedays);
					if($rs['refund_money']>0){
						\app\common\Member::addmoney(aid,$o['mid'],$rs['refund_money'],t('余额').'抵扣退款: '.$o['ordernum']);
					}
					//提前退服务费
					$fuwufei = $beforedays*$o['totalnum']*$o['fuwu_money_dj'];
					$refund_price = $fuwufei+$rs['refund_price'];
					$rs = \app\common\Order::refund($o,$refund_price,'提前离店');
					if($rs['status']==0){
						return json(['status'=>0,'msg'=>$rs['msg']]);
					}
					//提前的日期增加库存
					for($i=0;$i<$beforedays;$i++){
						$meiday = $datetime1+$i*86400;
						$meidate = date('Y-m-d',$meiday);
						Db::name('hotel_room_prices')->where('aid',$o['aid'])->where('roomid',$o['roomid'])->where('datetime',$meidate)->inc('stock',$o['totalnum'])->update();
					}
					$real_leavedate = date('Y-m-d',time());
					$rs = \app\common\Order::collect($o, 'hotel');
					if($rs['status']==0) return $rs;
					Db::name('hotel_order')->where('aid',aid)->where('id',$o['id'])->update(['status'=>4,'collect_time'=>time(),'fuwu_refund_money'=>$fuwufei,'fuwu_refund_time'=>time(),'real_leavedate'=>$real_leavedate]);
				}		
			}

		}


        //关闭订单触发
        \app\common\Order::order_close_done(aid,$orderid,'hotel');
		//退款成功通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的订单已经完成退款，¥'.$refund_money.'已经退回您的付款账户，请留意查收。';
		$tmplcontent['remark'] = $reason.'，请点击查看详情~';
		$tmplcontent['orderProductPrice'] = $refund_money;
		$tmplcontent['orderProductName'] = $order['title'];
		$tmplcontent['orderName'] = $order['ordernum'];
        $tmplcontentNew = [];
        $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
        $tmplcontentNew['thing2'] = $order['title'];//商品名称
        $tmplcontentNew['amount3'] = $refund_money;//退款金额
		\app\common\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['amount6'] = $refund_money;
		$tmplcontent['thing3'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['amount3'] = $refund_money;
		$tmplcontentnew['thing6'] = $order['title'];
		$tmplcontentnew['character_string4'] = $order['ordernum'];
		\app\common\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

		//短信通知
		$member = Db::name('member')->where('id',$order['mid'])->find();
		if($member['tel']){
			$tel = $member['tel'];
		}else{
			$tel = $order['tel'];
		}
		$rs = \app\common\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$refund_money]);
		
		\app\common\System::plog($this->text['酒店'].'订单退款'.$orderid);
		return json(['status'=>1,'msg'=>'已退款成功']);
	}

	function orderQueren(){ //确认订单
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->find();
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if(!$order || ($order['status']!=1)){
			$return = ['status'=>0,'msg'=>'订单状态有误'];
		}else{
			//$rs = \app\common\Order::collect($order, 'scoreshop', $this->user['mid']);
            // if($rs['status']==0) return $rs;
			Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->update(['status'=>2,'confirm_time'=>time()]);
			\app\common\System::plog($this->text['酒店'].'订单确认'.$orderid);
			$return = ['status'=>1,'msg'=>'确认成功'];
		}
		return json($return);
	}

	function qrdaodian(){ //确认到店
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->find();
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if(!$order || ($order['status']!=2)){
			$return = ['status'=>0,'msg'=>'订单状态有误'];
		}else{
			//$rs = \app\common\Order::collect($order, 'scoreshop', $this->user['mid']);
            // if($rs['status']==0) return $rs;
			Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->update(['status'=>3,'daodian_time'=>time()]);
			\app\common\System::plog($this->text['酒店'].'订单确认到店'.$orderid);
			$return = ['status'=>1,'msg'=>'确认成功'];
		}
		return json($return);
	}

	
	function qrlidian(){ //确认离店
		$text = \app\model\Hotel::gettext(aid);
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->find();
		$hotel = Db::name('hotel')->where('aid',aid)->where('id',$order['hotelid'])->find();
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if(!$order || ($order['status']!=3)){
			$return = ['status'=>0,'msg'=>'订单状态有误'];
		}else{
			$real_leavedate = $post['real_leavedate'];
			$intime = strtotime($order['in_date']);
			$datetime1 =  strtotime($real_leavedate);
			$datetime2 =  strtotime($order['leave_date']);
			if($datetime1<$intime){
				return json(['status'=>0,'msg'=>'离店日期不可小于入住日期']);
			}
			if($datetime1>$datetime2){
				return json(['status'=>0,'msg'=>'离店日期不可大于入住日期']);
			}
			$beforedays = abs($datetime2 - $datetime1) / 86400;
			$fuwufei = 0;
			if($beforedays>0 && $order['fuwu_money_dj']>0){
				$rs = \app\model\Hotel::beforeEnd($order,$beforedays);
				if($rs['refund_money']>0){
					\app\common\Member::addmoney(aid,$order['mid'],$rs['refund_money'],t('余额').'抵扣退款: '.$orderdata['ordernum']);
				}
				//提前退服务费
				$fuwufei = $beforedays*$order['totalnum']*$order['fuwu_money_dj'];
				$refund_price = $fuwufei+$rs['refund_price'];
				$rs = \app\common\Order::refund($order,$refund_price,'提前离店');
				if($rs['status']==0){
					return json(['status'=>0,'msg'=>$rs['msg']]);
				}
				
				//提前的日期增加库存
				for($i=0;$i<$beforedays;$i++){
					$meiday = $datetime1+$i*86400;
					$meidate = date('Y-m-d',$meiday);
					Db::name('hotel_room_prices')->where('aid',$order['aid'])->where('roomid',$order['roomid'])->where('datetime',$meidate)->inc('stock',$order['totalnum'])->update();
				}
			}
			$rs = \app\common\Order::collect($order, 'hotel');

            if($rs['status']==0) return $rs;
			Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->update(['status'=>4,'leavetime'=>time(),'fuwu_refund_money'=>$fuwufei,'fuwu_refund_time'=>time(),'real_leavedate'=>$real_leavedate]);
		
			//退款成功通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的'.$text['酒店'].'订单服务费退款，¥'.$fuwufei.'已经退回您的付款账户，请留意查收。';
			$tmplcontent['remark'] = $remark.'，请点击查看详情~';
			$tmplcontent['orderProductPrice'] = $fuwufei.'元';
			$tmplcontent['orderProductName'] = $order['title'];
			$tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $order['title'];//
            $tmplcontentNew['amount3'] = $fuwufei;//退款金额
			\app\common\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['amount6'] = $fuwufei;
			$tmplcontent['thing3'] = $order['title'];
			$tmplcontent['character_string2'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $fuwufei;
			$tmplcontentnew['thing6'] = $order['title'];
			$tmplcontentnew['character_string4'] = $order['ordernum'];
			\app\common\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

			//短信通知
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($member['tel']){
				$tel = $member['tel'];
			}else{
				$tel = $order['tel'];
			}
			$rs = \app\common\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$fuwufei]);
			
			\app\common\System::plog($text['酒店'].'订单提前离店'.$orderid);
			return json(['status'=>1,'msg'=>'确认成功']);
		}
		return json($return);
	}

	//操作退款
	public function refundinit(){
	    //查询订单信息
        $detail = Db::name('hotel_order')->where('id',input('param.orderid/d'))->where('aid',aid)->find();
		//echo db('hotel_order')->getlastsql();
        if(!$detail)  return json(['status'=>0,'msg'=>'订单不存在']);

		$totalprice = $detail['yajin_money'];
        $rdata = [];
        $rdata['status'] = 1;
        $rdata['detail'] = $detail;
		return json($rdata);
	}

	//退押金
	public function refundyajin(){
		$orderid = input('post.orderid/d');
		$reason = input('post.reason');
		$order = Db::name('hotel_order')->where('id',$orderid)->where('aid',aid)->find();
		if(!$order){
			return json(['status'=>0,'msg'=>'订单不存在']);
		}
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');

		$refund_money = $order['yajin_money'];
		if($order['status']!=4){
			return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
		}
		//查看是否有进行中的订单
		$otherorder = Db::name('hotel_order')->where('id','<>',$orderid)->where('mid',$order['mid'])->where('aid',aid)->where('status','in','1,2,3')->where('yajin_money',0)->count();
		if($otherorder>0){
			return json(['status'=>0,'msg'=>'有未完成免押金订单，暂不可退押金']);
		}


        if($refund_money > 0) {
            $rs = \app\common\Order::refund($order,$refund_money,$reason);
            if($rs['status']==0){
                return json(['status'=>0,'msg'=>$rs['msg']]);
            }
        }

		Db::name('hotel_order')->where('id',$orderid)->where('aid',aid)->update(['yajin_refund_status'=>2,'yajin_refund_money'=>$refund_money,'yajin_refund_reason'=>$reason,'yajin_refund_time'=>time()]);


		//退款成功通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的订单押金已退还，¥'.$refund_money.'已经退回您的付款账户，请留意查收。';
		$tmplcontent['remark'] = $reason.'，请点击查看详情~';
		$tmplcontent['orderProductPrice'] = $refund_money;
		$tmplcontent['orderProductName'] = $order['title'];
		$tmplcontent['orderName'] = $order['ordernum'];
        $tmplcontentNew = [];
        $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
        $tmplcontentNew['thing2'] = $order['title'];//房型名称
        $tmplcontentNew['amount3'] = $refund_money;//退款金额
		\app\common\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['amount6'] = $refund_money;
		$tmplcontent['thing3'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['amount3'] = $refund_money;
		$tmplcontentnew['thing6'] = $order['title'];
		$tmplcontentnew['character_string4'] = $order['ordernum'];
		\app\common\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

		//短信通知
		$member = Db::name('member')->where('id',$order['mid'])->find();
		if($member['tel']){
			$tel = $member['tel'];
		}else{
			$tel = $order['tel'];
		}
		$rs = \app\common\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$refund_money]);
		
		\app\common\System::plog($this->text['酒店'].'押金订单退款'.$orderid);
		return json(['status'=>1,'msg'=>'已退款成功']);
	}


	//删除
	public function del(){
		$id = input('post.id/d');
		if(bid == 0){
			Db::name('hotel_order')->where('aid',aid)->where('id',$id)->delete();
		}else{
			Db::name('hotel_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
		}
        \app\common\Order::order_close_done(aid,$id,'hotel');
		\app\common\System::plog($this->text['酒店'].'订单删除'.$id);
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//押金列表
    public function yajinlist(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
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
			if(input('param.orderid')) $where[] = ['id','=',input('param.orderid')];
			if(input('?param.status') && input('param.status')!==''){
				if(input('param.status') == 5){
					$where[] = ['refund_status','=',1];
				}elseif(input('param.status') == 6){
					$where[] = ['refund_status','=',2];
				}elseif(input('param.status') == 7){
					$where[] = ['refund_status','=',3];
				}else{
					$where[] = ['status','=',input('param.status')];
				}
			}
			$count = 0 + Db::name('Hotel_order_yajin')->where($where)->count();
			//echo M()->_sql();
			$list = Db::name('Hotel_order_yajin')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($list as $k=>$vo){
				$member = Db::name('member')->where('id',$vo['mid'])->find();
				$list[$k]['nickname'] = $member['nickname'];
				$list[$k]['headimg'] = $member['headimg'];
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		View::assign('text',$this->text);
		return View::fetch();
    }

	// 获取订单每个状态总数getstatuscount
	public function getstatuscount(){
		$count = Db::name('hotel_order')->where('aid',aid)->group('status')->field('count(id) as total,status')->select()->toArray();
 
		$tempArr = array_column($count, null, 'status'); 
		return json(['code'=>0,'msg'=>'查询成功','data'=>$count]);

	}
	
}
