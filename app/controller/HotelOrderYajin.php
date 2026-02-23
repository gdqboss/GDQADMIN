<?php
// JK客户定制

//custom_file(hotel)
// +----------------------------------------------------------------------
// | 酒店-押金记录
// +----------------------------------------------------------------------
namespace app\controller;
use app\common\Order;
use think\facade\View;
use think\facade\Db;
class HotelOrderYajin extends Common
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
				$order = 'ro.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'ro.id desc';
			}
			$where = [];
            $where[] = ['ro.aid','=',aid];
            if(bid==0){
                if(input('param.bid')){
                    $where[] = ['ro.bid','=',input('param.bid')];
                }elseif(input('param.showtype')==2){
                    $where[] = ['ro.bid','<>',0];
                }elseif(input('param.showtype')=='all'){
					$where[] = ['bid','>=',0];
				}else{
                    $where[] = ['ro.bid','=',0];
                }
            }else{
                $where[] = ['ro.bid','=',bid];
            }
            if($this->mdid){
                $where[] = ['ro.mdid','=',$this->mdid];
            }
			if(input('param.hotelid')){
				$where[] = ['hotelid','=',input('param.hotelid')];
			}
            if(input('param.mid')) $where[] = ['ro.mid','=',input('param.mid')];
            if(input('param.refund_ordernum')) $where[] = ['refund_ordernum','like','%'.input('param.refund_ordernum').'%'];
            if(input('param.orderid')) $where[] = ['orderid','=',input('param.orderid')];
            if(input('param.ordernum')) $where[] = ['ro.ordernum','like','%'.input('param.ordernum').'%'];
            if(input('param.linkman')) $where[] = ['o.linkman','like','%'.input('param.linkman').'%'];
            if(input('param.tel')) $where[] = ['o.tel','like','%'.input('param.tel').'%'];
            if(input('param.apply_time') ){
                $ctime = explode(' ~ ',input('param.apply_time'));
                $where[] = ['o.createtime','>=',strtotime($ctime[0])];
                $where[] = ['o.createtime','<',strtotime($ctime[1]) + 86400];
            }
			//$total
			$arr = [];
			$arr['totalmoney'] = 0 + Db::name('hotel_order_yajin')->alias('ro')->leftJoin('hotel_order o','ro.orderid=o.id')->where($where)->sum('ro.yajin_money');
			$arr['money1'] = 0 + Db::name('hotel_order_yajin')->alias('ro')->leftJoin('hotel_order o','ro.orderid=o.id')->where($where)->where('ro.refund_status',0)->sum('ro.yajin_money');
			$arr['money2'] = 0 + Db::name('hotel_order_yajin')->alias('ro')->leftJoin('hotel_order o','ro.orderid=o.id')->where($where)->where('ro.refund_status',1)->sum('ro.yajin_money');
			$arr['money3'] = 0 + Db::name('hotel_order_yajin')->alias('ro')->leftJoin('hotel_order o','ro.orderid=o.id')->where($where)->where('ro.refund_status',2)->sum('ro.yajin_money');
			$arr['money4'] = 0 + Db::name('hotel_order_yajin')->alias('ro')->leftJoin('hotel_order o','ro.orderid=o.id')->where($where)->where('ro.refund_status',-1)->sum('ro.yajin_money');



			//var_dump(input('param.status'));
            if(input('param.status') === '0'){
                $where[] = ['ro.refund_status','=',0];
            }elseif(input('param.status') == 1){
                $where[] = ['ro.refund_status','=',1];
            }elseif(input('param.status') == 2){
                $where[] = ['ro.refund_status','=',2];
            }elseif(input('param.status') == 3){
                $where[] = ['ro.refund_status','=',3];
            }elseif(input('param.status') == 4){
                $where[] = ['ro.refund_status','=',-1];
            }
			//var_dump($where);
            $count = 0 + Db::name('hotel_order_yajin')->alias('ro')->leftJoin('hotel_order o','ro.orderid=o.id')->where($where)->count('ro.id');
            //echo M()->_sql();
            $list = Db::name('hotel_order_yajin')->alias('ro')->leftJoin('hotel_order o','ro.orderid=o.id')->where($where)->field('ro.*,o.tel')->page($page,$limit)->order($order)->select()->toArray();
            foreach($list as $k=>$vo){
				$order = Db::name('hotel_order')->field('title,hotelid,totalnum')->where('aid',aid)->where('id',$vo['orderid'])->find();
				$list[$k]['title'] = $order['title'];
				$list[$k]['totalnum'] = $order['totalnum'];
				$hotel = Db::name('hotel')->field('name')->where('aid',aid)->where('id',$order['hotelid'])->find();
				$list[$k]['hotelname'] = $hotel['name'];
			}
		


			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list,'arr'=>$arr]);
		}
		$hotellist =  Db::name('hotel')->where('aid',aid)->where('bid','<>',0)->where('status',1)->select()->toArray();
		View::assign('hotellist',$hotellist);
		View::assign('text',$this->text);
		View::assign('showtype',input('param.showtype'));
		return View::fetch();
    }
	//订单详情
	public function getdetail(){
		$orderid = input('param.orderid');
		if(bid != 0){
			$order = Db::name('hotel_order_yajin')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		}else{
			$order = Db::name('hotel_order_yajin')->where('aid',aid)->where('id',$orderid)->find();
		}
		$member = Db::name('member')->field('id,nickname,headimg,realname,tel,wxopenid,unionid')->where('id',$order['mid'])->find();
		if(!$member) $member = ['id'=>$order['mid'],'nickname'=>'','headimg'=>''];
		if(bid != 0){
			$orderdetail = Db::name('hotel_order')->where('id',$order['orderid'])->where('aid',aid)->where('bid',bid)->find();
		}else{
			$orderdetail = Db::name('hotel_order')->where('id',$order['orderid'])->where('aid',aid)->find();
		}
        //学校信息
        $rdata = ['order'=>$order,'member'=>$member,'orderdetail'=>$orderdetail];
        return json($rdata);
	}

	//退款审核
	public function refundCheck(){
		$orderid = input('post.orderid/d');
		$st = input('post.st/d');
		$remark = input('post.remark');
		if(bid == 0){
			$order = Db::name('hotel_order_yajin')->where('id',$orderid)->where('aid',aid)->find();
			$orderOrigin = Db::name('hotel_order')->where('id',$order['orderid'])->where('aid',aid)->find();
		}else{
			$order = Db::name('hotel_order_yajin')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
			$orderOrigin = Db::name('hotel_order')->where('id',$order['orderid'])->where('aid',aid)->where('bid',bid)->find();
		}

		if($st==2){
			Db::name('hotel_order_yajin')->where('id',$orderid)->where('aid',aid)->where('bid',$order['bid'])->update(['refund_status'=>-1,'refund_reason'=>$remark]);
			Db::name('hotel_order')->where('id',$order['orderid'])->where('aid',aid)->where('bid',bid)->update(['yajin_refund_status'=>-1]);
			//退款申请驳回通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的退款申请被商家驳回，可与商家协商沟通。';
			$tmplcontent['remark'] = $remark.'，请点击查看详情~';
			$tmplcontent['orderProductPrice'] = $order['rmoney'];
			$tmplcontent['orderProductName'] = $orderOrigin['title'];
			$tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $orderOrigin['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['money'];//退款金额
			\app\common\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['amount3'] = $order['refund_money'];
			$tmplcontent['thing2'] = $orderOrigin['title'];
			$tmplcontent['character_string1'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $order['refund_money'];
			$tmplcontentnew['thing8'] = $orderOrigin['title'];
			$tmplcontentnew['character_string4'] = $order['ordernum'];
			\app\common\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
			//短信通知
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($member['tel']){
				$tel = $member['tel'];
			}else{
				$tel = $orderOrigin['tel'];
			}
			$rs = \app\common\Sms::send(aid,$tel,'tmpl_tuierror',['ordernum'=>$order['ordernum'],'reason'=>$remark]);
			\app\common\System::plog($this->text['酒店'].'订单押金退款驳回'.$orderid);
			return json(['status'=>1,'msg'=>'退款已驳回']);
		}elseif($st == 1){
			if($orderOrigin['status']!=4){
				return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
			}
			$hotel = Db::name('hotel')->where('id',$orderOrigin['hotelid'])->find();
			if($hotel['iscancel_otherorder']==1){
				//其他使用押金的订单
				$otherlist = Db::name('hotel_order')->where('yajin_orderid',$order['id'])->where('mid',$order['mid'])->where('aid',aid)->where('status','in','1,2,3')->select()->toArray();
				//dump($otherlist);
				foreach($otherlist as $k=>$o){
					$realstarttime = strtotime($o['in_date']);
					$realendtime = strtotime($o['leave_date']);
					$datetime2 =  time();
					//提前了几天
					$beforedays = abs($datetime2 - $realendtime) / 86400;
					//如果退款时间大于 入住时间则直接退款
					if($datetime2<$realstarttime || $beforedays<1){
						//退抵扣的余额
						if($o['use_money']){
							\app\common\Member::addmoney(aid,$o['mid'],$o['use_money'],t('余额').'抵扣退款: '.$o['ordernum']);
						}
						//退押金退回
						$rs = \app\common\Order::refund($o,$o['totalprice'],'关联押金退款退回');
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
						Db::name('hotel_order')->where('id',$o['id'])->where('aid',aid)->update(['status'=>-1,'refund_status'=>2,'refund_money'=>$o['totalprice'],'refund_reason'=>'关联押金退款']);

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
			}else{
				//查看是否有进行中的订单
				$otherorder = Db::name('hotel_order')->where('id','<>',$order['orderid'])->where('yajin_orderid',$order['id'])->where('mid',$order['mid'])->where('aid',aid)->where('status','in','1,2,3')->where('yajin_money',0)->count();
				if($otherorder>0){
					return json(['status'=>0,'msg'=>'有未完成免押金订单，暂不可退押金']);
				}
			}

			if($order['refund_money'] > 0){
				$rs = \app\common\Order::refund($orderOrigin,$order['refund_money'],'退押金');
				if($rs['status']==0){
					return json(['status'=>0,'msg'=>$rs['msg']]);
				}
			}

			Db::name('hotel_order_yajin')->where('id',$orderid)->where('aid',aid)->where('bid',$order['bid'])->update(['refund_status'=>2,'refund_reason'=>$remark,'refund_time'=>time()]);
         	Db::name('hotel_order')->where('id',$orderOrigin['id'])->where('aid',aid)->where('bid',$order['bid'])->update(['yajin_refund_status'=>2,'yajin_refund_money'=>$order['refund_money']]);

			//退款成功通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的订单已经完成押金退款，¥'.$order['money'].'已经退回您的付款账户，请留意查收。';
			$tmplcontent['remark'] = $remark.'，请点击查看详情~';
			$tmplcontent['orderProductPrice'] = $order['money'];
			$tmplcontent['orderProductName'] = $orderOrigin['title'];
			$tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $order['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['money'];//退款金额
			\app\common\Wechat::sendtmpl(aid,$orderOrigin['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['amount6'] = $order['money'];
			$tmplcontent['thing3'] = $orderOrigin['title'];
			$tmplcontent['character_string2'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $order['money'];
			$tmplcontentnew['thing6'] = $orderOrigin['title'];
			$tmplcontentnew['character_string4'] = $order['ordernum'];
			\app\common\Wechat::sendwxtmpl(aid,$orderOrigin['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

			//短信通知
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($member['tel']){
				$tel = $member['tel'];
			}else{
				$tel = $orderOrigin['tel'];
			}
			$rs = \app\common\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$order['refund_money']]);
			
			\app\common\System::plog($this->text['酒店'].'退款审核通过并退款'.$orderid);
			return json(['status'=>1,'msg'=>'已退款成功']);
		}
	}

    //删除
    public function del(){
        $id = input('post.id/d');
		if(bid == 0){
			Db::name('hotel_order_yajin')->where('aid',aid)->where('id',$id)->delete();
		}else{
			Db::name('hotel_order_yajin')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
		}
        \app\common\System::plog($this->text['酒店'].'押金退款删除'.$id);
        return json(['status'=>1,'msg'=>'删除成功']);
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
		if(input('param.apply_time') ){
			$ctime = explode(' ~ ',input('param.apply_time'));
			$where[] = ['apply_time','>=',strtotime($ctime[0])];
			$where[] = ['apply_time','<',strtotime($ctime[1]) + 86400];
		}
		if(input('?param.status') && input('param.status')!==''){
			$where[] = ['status','=',input('param.status')];
		}
		$text = \app\model\Hotel::gettext(aid);

		$list = Db::name('hotel_order_yajin')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('hotel_order_yajin')->where($where)->order($order)->count();
		$title = array('订单号','下单人','酒店名称','房间名称','押金','申请时间','押金状态');
		$data = [];
		foreach($list as $k=>$vo){
			$member = Db::name('member')->where('id',$vo['mid'])->find();
			$order =  Db::name('hotel_order')->field('id,linkman,tel,title,hotelname')->where('id',$vo['orderid'])->find();
			$status='';
			if($vo['refund_status']==0){
				$status = '待申请';
			}elseif($vo['refund_status']==1){
				$status = '待审核';
			}elseif($vo['refund_status']==2){
				$status = '已退款';
			}elseif($vo['refund_status']==-1){
				$status = '已驳回';
			}
			$data[$k] = [
				' '.$vo['ordernum'],
				$member['nickname'],
				$order['hotelname'],
				$order['title'],
                $vo['refund_money'],
				$vo['apply_time']?date('Y-m-d H:i:s',$vo['apply_time']):'',
				$status,
			];
 
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
}
