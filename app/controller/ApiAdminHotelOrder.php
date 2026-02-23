<?php
// JK客户定制

//custom_file(hotel)
//管理员中心 - 订单管理
namespace app\controller;
use think\facade\Db;
class ApiAdminHotelOrder extends ApiAdmin
{
	
	//酒店订单
	public function getorder(){
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];

        if(input('param.keyword')) $where[] = ['ordernum|title|tel|linkman', 'like', '%'.input('param.keyword').'%'];
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
		}elseif($st == '-1'){
			$where[] = ['status','=',-1];
		}elseif($st == '10'){
			$where[] = ['refund_status','>',0];
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('hotel_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $key=>$v){
			$datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
			if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
			if($v['buytpe']!=1) $datalist[$key]['team'] = Db::name('collage_order_team')->where('id',$v['teamid'])->find();
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['st'] = $st;
		$text = \app\model\Hotel::gettext(aid);
		$rdata['text'] = $text;
		return $this->json($rdata);
	}
	//订单详情
	public function detail(){
		$detail = Db::name('hotel_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
		if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['daodian_time'] = $detail['daodian_time'] ? date('Y-m-d H:i:s',$detail['daodian_time']) : '';

		$hotel =  Db::name('hotel')->field('name')->where('id',$detail['hotelid'])->find();

		$member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
		$detail['nickname'] = $member['nickname'];
		$detail['headimg'] = $member['headimg'];

		$hotelset = Db::name('hotel_set')->where('aid',aid)->field('autoclose')->find();
		if($detail['status']==0 && $hotelset['autoclose'] > 0){
			$lefttime = strtotime($detail['createtime']) + $hotelset['autoclose']*60 - time();
			if($lefttime < 0) $lefttime = 0;
		}else{
			$lefttime = 0;
		}


		$rdata = [];
		$rdata['detail'] = $detail;
		$rdata['hotel'] = $hotel;
		$rdata['lefttime'] = $lefttime;
		$text = \app\model\Hotel::gettext(aid);
		$rdata['text'] = $text;
		return $this->json($rdata);
	}
	
	function qrdaodian(){ //确认到店
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->find();
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if(!$order || ($order['status']!=2)){
			$return = ['status'=>0,'msg'=>'订单状态有误'];
		}else{
			if($order['yajin_orderid']>0){
				$yajin = Db::name('hotel_order_yajin')->where('aid',aid)->where('id',$order['yajin_orderid'])->find();
				if($yajin['refund_status']==1 || $yajin['refund_status']==2){
					$return = ['status'=>0,'msg'=>'有退押金待审核，暂不可入住'];
				}
			}
			//$rs = \app\common\Order::collect($order, 'scoreshop', $this->user['mid']);
            // if($rs['status']==0) return $rs;
			Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->update(['status'=>3,'daodian_time'=>time()]);
			$return = ['status'=>1,'msg'=>'确认成功'];
		}
		return json($return);
	}
	
	//确认离店
	function confirmleave(){
		$text = \app\model\Hotel::gettext(aid);
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->find();
		if(bid != 0 && $order['bid']!=bid) 	$return = ['status'=>0,'msg'=>'无权限操作'];
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
			if($beforedays>0){
				$rs1 = \app\model\Hotel::beforeEnd($order,$beforedays);
				if($rs1['refund_money']>0){
					\app\common\Member::addmoney(aid,$order['mid'],$rs1['refund_money'],'提前离店'.t('余额').'抵扣退款: '.$orderdata['ordernum']);
				}
				//提前退服务费
				$fuwufei = $beforedays*$order['totalnum']*$order['fuwu_money_dj'];
				$refund_price = $fuwufei+$rs1['refund_price'];
				if($refund_price>0){
					$rs = \app\common\Order::refund($order,$refund_price,'提前离店');
					if($rs['status']==0){
						return json(['status'=>0,'msg'=>$rs['msg']]);
					}
				}
				$real_fuwu_money = round($order['fuwu_money']-$fuwufei,2);
				//实际支付
				Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->update(['isbefore'=>1,'real_usemoney'=>$rs1['real_usemoney'],'real_roomprice'=>$rs1['real_roomprice'],'real_fuwu_money'=>$real_fuwu_money]);
				//提前的日期增加库存
				for($i=0;$i<$beforedays;$i++){
					$meiday = $datetime1+$i*86400;
					$meidate = date('Y-m-d',$meiday);
					Db::name('hotel_room_prices')->where('aid',$order['aid'])->where('roomid',$order['roomid'])->where('datetime',$meidate)->inc('stock',$order['totalnum'])->update();
				}
				//退款金额大于0时 发送消息通知
				if($refund_price>0){
					//退款成功通知
					$tmplcontent = [];
					$tmplcontent['first'] = '您的'.$text['酒店'].'订单,离店退款，¥'.$refund_price.'已经退回您的付款账户，请留意查收。';
					$tmplcontent['remark'] = $remark.'，请点击查看详情~';
					$tmplcontent['orderProductPrice'] = $refund_price.'元';
					$tmplcontent['orderProductName'] = $order['title'];
					$tmplcontent['orderName'] = $order['ordernum'];
					$tmplcontentNew = [];
					$tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
					$tmplcontentNew['thing2'] = $order['title'];//
					$tmplcontentNew['amount3'] = $refund_price;//退款金额
					\app\common\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
					//订阅消息
					$tmplcontent = [];
					$tmplcontent['amount6'] = $refund_price;
					$tmplcontent['thing3'] = $order['title'];
					$tmplcontent['character_string2'] = $order['ordernum'];
					
					$tmplcontentnew = [];
					$tmplcontentnew['amount3'] = $refund_price;
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
				}


			}
			$rs = \app\common\Order::collect($order, 'hotel');

            if($rs['status']==0) return $rs;
			Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->update(['status'=>4,'collect_time'=>time(),'fuwu_refund_money'=>$fuwufei,'fuwu_refund_time'=>time(),'real_leavedate'=>$real_leavedate]);
			return json(['status'=>1,'msg'=>'确认成功']);
		}
		return json($return);
	}
	//备注
	public function setremark(){
		$post = input('post.');
		$orderid = $post['orderid'];
		$content = $post['content'];
		Db::name('hotel_order')->where(['aid'=>aid,'bid'=>bid,'id'=>$orderid])->update(['remark'=>$content]);
		return $this->json(['status'=>1,'msg'=>'设置完成']);
	}

	function confirmorder(){ //确认订单
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->find();
		if(bid != 0 && $order['bid']!=bid) 	$return = ['status'=>0,'msg'=>'无权限操作'];
		if(!$order || ($order['status']!=1)){
			$return = ['status'=>0,'msg'=>'订单状态有误'];
		}else{
			//$rs = \app\common\Order::collect($order, 'scoreshop', $this->user['mid']);
            // if($rs['status']==0) return $rs;
			Db::name('hotel_order')->where('aid',aid)->where('id',$orderid)->update(['status'=>2,'confirm_time'=>time()]);
			$return = ['status'=>1,'msg'=>'确认成功'];
		}
		return json($return);
	}

	//退款通过
	function refundpass(){
		$text = \app\model\Hotel::gettext(aid);
		$type = input('post.type');
		$orderid = input('post.orderid/d');
		$refund_desc = input('post.reason');
        $release = input('post.release');
		$order = Db::name($type.'_order')->where('id',$orderid)->find();
		if($order['status']!=1 && $order['status']!=2){
			return $this->json(['status'=>0,'msg'=>'该订单状态不允许退款']);
		}
		$rs = \app\common\Order::refund($order,$order['refund_money'],$refund_desc);
		if($rs['status']==0){
			return $this->json(['status'=>0,'msg'=>$rs['msg']]);
		}

		Db::name($type.'_order')->where('id',$orderid)->update(['status'=>-1,'refund_status'=>2,'refund_reason'=>$refund_desc]);

		$orderyajin =  Db::name('hotel_order_yajin')->where(['orderid'=>$orderid,'aid'=>aid, 'mid' => $order['mid'],'refund_status'=>0])->find();
		if($orderyajin){
			 Db::name('hotel_order_yajin')->where('id',$orderyajin['id'])->update(['refund_status'=>1,'refund_time'=>time(),'refund_reason'=>$refund_desc]);
		}

		 //退款减去销量 增加库存
		\app\model\Hotel::addroomsales($order,-$order['totalnum']);
		//余额返还
		if($order['use_money'] > 0){
			\app\common\Member::addmoney(aid,$order['mid'],$order['use_money'],$text['酒店'].'订单退款返还');
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

		//退款成功通知
		$tmplcontent = [];
		if($type=='scoreshop'){
			$tmplcontent['first'] = '您的订单已经完成退款，'.$order['totalscore'].t('积分').' + ¥'.$order['refund_money'].'已经退回您的付款账户，请留意查收。';
			$tmplcontent['orderProductPrice'] = $order['totalscore'].t('积分').' + ¥'.$order['refund_money'];
		}else{
			$tmplcontent['first'] = '您的订单已经完成退款，¥'.$order['refund_money'].'已经退回您的付款账户，请留意查收。';
			$tmplcontent['orderProductPrice'] = $order['refund_money'];
		}
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['orderProductName'] = $order['title'];
		$tmplcontent['orderName'] = $order['ordernum'];
		$tmplcontentNew = [];
		$tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
		$tmplcontentNew['thing2'] = $order['title'];//商品名称
		$tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
		\app\common\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('/pages/my/usercenter'),$tmplcontentNew);
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
		$member = Db::name('member')->where(['id'=>$order['mid']])->find();
		$rs = \app\common\Sms::send(aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$order['refund_money']]);
		return $this->json(['status'=>1,'msg'=>'已退款成功']);
	}

	//退押金
	public function refundYajin(){
		$orderid = input('post.orderid/d');
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
		$otherorder = Db::name('hotel_order')->where('id','<>',$orderid)->where('mid',$order['mid'])->where('aid',aid)->where('status','in','2,3')->where('yajin_money',0)->count();
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
		return json(['status'=>1,'msg'=>'已退款成功']);
	}


	//退款单列表
    public function refundyajinOrder(){
        $st = input('param.st');
        if(!input('param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        $order = ['id' => 'desc'];
        if(input('param.keyword')) $where[] = ['refund_ordernum|ordernum|title', 'like', '%'.input('param.keyword').'%'];
        if($st == 'all'){

        }elseif($st == '0'){
            $where[] = ['refund_status','=',0];
        }elseif($st == '1'){
            $where[] = ['refund_status','=',1];
            $order['id'] = 'asc';
        }elseif($st == '2'){
            $where[] = ['refund_status','=',2];
        }elseif($st == '-1'){
            $where[] = ['refund_status','=',-1];
        }
        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        if(input('param.orderid/d')) {
            $where[] = ['orderid','=',input('param.orderid/d')];
            $pernum = 99;
        }
        $datalist = Db::name('hotel_order_yajin')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
        if(!$datalist) $datalist = array();
        foreach($datalist as $key=>$v){
            $datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
            if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
			$order = Db::name('hotel_order')->field('title,hotelid,totalnum,pic')->where('aid',aid)->where('id',$v['orderid'])->find();
			$datalist[$key]['title'] = $order['title'];
			$datalist[$key]['totalnum'] = $order['totalnum'];
			$datalist[$key]['pic'] = $order['pic'];
			$hotel = Db::name('hotel')->field('name')->where('aid',aid)->where('id',$order['hotelid'])->find();
			$datalist[$key]['hotelname'] = $hotel['name'];
        }
        $rdata = [];
        $rdata['datalist'] = $datalist;
        $rdata['st'] = $st;
        return $this->json($rdata);
    }
    public function yajinRefundOrderDetail()
    {
        $where = [];
        $where[] = ['id','=',input('param.id/d')];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];

        $detail = Db::name('hotel_order_yajin')->where($where)->find();
        if(!$detail) $this->json(['status'=>0,'msg'=>'退款单不存在']);
        $detail['apply_time'] = $detail['apply_time'] ? date('Y-m-d H:i:s',$detail['apply_time']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';

        if($detail['refund_pics']) {
            $detail['refund_pics'] = explode(',', $detail['refund_pics']);
        }
        unset($where['id']);
        $where[] = ['orderid', '=', $detail['orderid']];

        $member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
        $detail['nickname'] = $member['nickname'];
        $detail['headimg'] = $member['headimg'];

        $order = Db::name('hotel_order')->where('id',$detail['orderid'])->where('aid',aid)->where('bid',bid)->find();
        $order['createtime'] = $order['createtime'] ? date('Y-m-d H:i:s',$order['createtime']) : '';
        $order['collect_time'] = $order['collect_time'] ? date('Y-m-d H:i:s',$order['collect_time']) : '';
        $order['paytime'] = $order['paytime'] ? date('Y-m-d H:i:s',$order['paytime']) : '';
        $order['refund_time'] = $order['refund_time'] ? date('Y-m-d H:i:s',$order['refund_time']) : '';
        $order['daodian_time'] = $order['daodian_time'] ? date('Y-m-d H:i:s',$order['daodian_time']) : '';

        $rdata = [];
        $rdata['detail'] = $detail;
        $rdata['order'] = $order;
        return $this->json($rdata);
    }

	//退押金驳回
	public function refundYajinnopass(){
		$orderid = input('post.orderid/d');
		$remark = input('post.remark');
		$orderyajin = Db::name('hotel_order_yajin')->where('id',$orderid)->where('aid',aid)->find();
		if(!$orderyajin){
			return json(['status'=>0,'msg'=>'退款申请不存在']);
		}
		$order = Db::name('hotel_order')->where('id',$orderyajin['orderid'])->where('aid',aid)->where('bid',bid)->find();
       	Db::name('hotel_order_yajin')->where('id',$orderid)->where('aid',aid)->update(['refund_status'=>-1,'refund_reason'=>$remark]);
		Db::name('hotel_order')->where('id',$orderyajin['orderid'])->where('aid',aid)->where('bid',bid)->update(['yajin_refund_status'=>-1]);

		//退款申请驳回通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的退款申请被商家驳回，可与商家协商沟通。';
		$tmplcontent['remark'] = $remark.'，请点击查看详情~';
		$tmplcontent['orderProductPrice'] = $orderyajin['refund_money'];
		$tmplcontent['orderProductName'] = $order['title'];
		$tmplcontent['orderName'] = $order['ordernum'];
		$tmplcontentNew = [];
		$tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
		$tmplcontentNew['thing2'] = $order['title'];//商品名称
		$tmplcontentNew['amount3'] = $orderyajin['refund_money'];//退款金额
		\app\common\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontent,m_url('/pages/my/usercenter'),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['amount3'] = $orderyajin['refund_money'];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['character_string1'] = $order['ordernum'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['amount3'] = $orderyajin['refund_money'];
		$tmplcontentnew['thing8'] = $order['title'];
		$tmplcontentnew['character_string4'] = $order['ordernum'];
		\app\common\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
		//短信通知
		$member = Db::name('member')->where(['id'=>$order['mid']])->find();
		$rs = \app\common\Sms::send(aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_tuierror',['ordernum'=>$order['ordernum'],'reason'=>$remark]);

		$text = \app\model\Hotel::gettext(aid);
        \app\common\System::plog('手机端后台'.$text['酒店'].'押金驳回退款'.$orderid);
		return $this->json(['status'=>1,'msg'=>'退款已驳回']);
	}
	//押金退款通过
	function refundYajinpass(){
		$orderid = input('post.orderid/d');
		$refund_desc = input('post.reason');
		Db::startTrans();
		if(bid == 0){
			$order = Db::name('hotel_order_yajin')->where('id',$orderid)->where('aid',aid)->find();
			$orderOrigin = Db::name('hotel_order')->where('id',$order['orderid'])->where('aid',aid)->find();
		}else{
			$order = Db::name('hotel_order_yajin')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
			$orderOrigin = Db::name('hotel_order')->where('id',$order['orderid'])->where('aid',aid)->where('bid',bid)->find();
		}

		if($orderOrigin['status']!=4){
			return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
		}

		$hotel = Db::name('hotel')->where('id',$orderOrigin['hotelid'])->find();
		//if($hotel['iscancel_otherorder']==1){
		$otherrzcount = Db::name('hotel_order')->where('yajin_orderid',$order['id'])->where('mid',$order['mid'])->where('aid',aid)->where('status','in','3')->count();
		if($otherrzcount>0){
			return json(['status'=>0,'msg'=>'有进行中的免押金订单，暂不可退押金']);
		}	

		//其他使用押金的订单
		$otherlist = Db::name('hotel_order')->where('yajin_orderid',$order['id'])->where('mid',$order['mid'])->where('aid',aid)->where('id','<>',$order['orderid'])->where('status','in','1,2,4')->select()->toArray();
		//dump($otherlist);
		foreach($otherlist as $k=>$o){
			$realstarttime = strtotime($o['in_date']);
			$realendtime = strtotime($o['leave_date']);
			$datetime2 =  time();
			//提前了几天
			$beforedays = abs($datetime2 - $realendtime) / 86400;
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
		}

		$rs = \app\common\Order::refund($orderOrigin,$order['refund_money'],$refund_desc?$refund_desc:'退押金');
		if($rs['status']==0){
			return $this->json(['status'=>0,'msg'=>$rs['msg']]);
		}
		Db::name('hotel_order_yajin')->where('id',$orderid)->where('aid',aid)->where('bid',$order['bid'])->update(['refund_status'=>2,'refund_reason'=>$remark,'refund_time'=>time()]);
		Db::name('hotel_order')->where('id',$orderOrigin['id'])->where('aid',aid)->where('bid',$order['bid'])->update(['yajin_refund_status'=>2,'yajin_refund_money'=>$order['refund_money']]);
		Db::commit();

	
		//退款成功通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的押金已完成退款，¥'.$order['refund_money'].'已经退回您的付款账户，请留意查收。';
		$tmplcontent['orderProductPrice'] = $order['refund_money'];
	
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['orderProductName'] = $order['title'];
		$tmplcontent['orderName'] = $order['ordernum'];
		$tmplcontentNew = [];
		$tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
		$tmplcontentNew['thing2'] = $order['title'];//商品名称
		$tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
		\app\common\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('/pages/my/usercenter'),$tmplcontentNew);
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
		$member = Db::name('member')->where(['id'=>$order['mid']])->find();
		$rs = \app\common\Sms::send(aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$order['refund_money']]);
		$text = \app\model\Hotel::gettext(aid);
        \app\common\System::plog('手机端后台'.$text['酒店'].'押金通过退款'.$orderid);
	
	
		return $this->json(['status'=>1,'msg'=>'已退款成功']);
	}

}