<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 约课 - 约课记录 custom_file(yueke)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;
class YuekeOrder extends Common
{
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
			if(input('param.bid') == 'all'){
			
			}else{
				$where[] = ['bid','=',bid];
			}
			if(input('param.orderids')) $where[] = ['id','in',input('param.orderids')];
			if(input('param.orderid')) $where[] = ['id','=',input('param.orderid')];
			if(input('param.mid/d')) $where[] = ['mid','=',input('param.mid/d')];
			if(input('param.proname')) $where[] = ['proname','like','%'.input('param.proname').'%'];
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
	    	if(input('param.worker_id')){
				 	$where[] = ['workerid','=',input('param.worker_id')];
			}
			$count = 0 + Db::name('yueke_order')->where($where)->count();
			$list = Db::name('yueke_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			foreach($list as $k=>$vo){
				$member = Db::name('member')->where('id',$vo['mid'])->find();
				$list[$k]['goodsdata'] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
					'<img src="'.$vo['propic'].'" style="max-width:60px;float:left">'.
					'<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
						'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$vo['proname'].'</div>'.
						'<div style="padding-top:0px;color:#f60;font-size:12px">￥'.$vo['product_price'].'</div>'.
					'</div>'.
				'</div>';
				$list[$k]['nickname'] = $member['nickname'];
				$list[$k]['headimg'] = $member['headimg'];
				$list[$k]['platform'] = getplatformname($vo['platform']);

				$workerinfo = db('yueke_worker')->where(['id'=>$vo['workerid']])->find();
				$list[$k]['fwname'] = $workerinfo['realname'];
				$list[$k]['fwtel'] = $workerinfo['tel'];
				$list[$k]['yuyue_model'] = 1;
                }
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		$where = [];
		if(input('param.')) $where = input('param.');
		$where = json_encode($where);
		View::assign('where',$where);

        $this->defaultSet();
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
        $page = input('param.page');
        $limit = input('param.limit');
		$where = [];
		$where[] = ['aid','=',aid];
		if(input('param.bid') == 'all'){
			
		}else{
			$where[] = ['bid','=',bid];
		}
		if($this->mdid){
			$where[] = ['mdid','=',$this->mdid];
		}
        if(input('param.worker_id')){
            $where[] = ['workerid','=',input('param.worker_id')];
        }
		if(input('param.proname')) $where[] = ['proname','like','%'.input('param.proname').'%'];
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
		$list = Db::name('yueke_order')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('yueke_order')->where($where)->count();
		$title = array('订单号','下单人','商品名称','总价','实付款','支付方式','预约时间','客户信息','教练信息','客户留言','备注','下单时间','订单状态');
		$data = [];

        $add = false;
        foreach($list as $k=>$vo){
			$member = Db::name('member')->where('id',$vo['mid'])->find();
			$worker = Db::name('yueke_worker')->where('id',$vo['workerid'])->find();
			$status='';
			if($vo['status']==0){
				$status = '未支付';
			}elseif($vo['status']==2){
				$status = '已派单';
			}elseif($vo['status']==1){
				$status = '已支付';
			}elseif($vo['status']==3){
				$status = '已完成';
			}elseif($vo['status']==4){
				$status = '已关闭';
			}
			$resetdata = [
				' '.$vo['ordernum'],
				$member['nickname'],
				$vo['title'],
				$vo['product_price'],
				$vo['totalprice'],
				$vo['paytype'],
				$vo['yy_time'],
				$vo['linkman'].'('.$vo['tel'].') '.$vo['area'].' '.$vo['address'],
				$worker['realname'].' '.$worker['tel'],
				$vo['message'],
				$vo['beizhu'],
				date('Y-m-d H:i:s',$vo['createtime']),
				$status
			];

            if($add){
                $resetdata[] = $vo['total_kecheng_num'];
                //剩余课程
                $surplusKechengNum = $vo['total_kecheng_num'] - $vo['used_kecheng_num'] - $vo['refund_kecheng_num'];
                if($surplusKechengNum < 0) $surplusKechengNum = 0;
                $resetdata[] = $surplusKechengNum;
                $resetdata[] = $vo['refund_kecheng_num'];
            }

            $data[] = $resetdata;
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//订单详情
	public function getdetail(){
		$orderid = input('post.orderid');
		$order = Db::name('yueke_order')->where('aid',aid)->where('id',$orderid)->find();
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');

		if($order['coupon_rid']){
			$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
		}else{
			$couponrecord = false;
		}
		$formdata = Db::name('freight_formdata')->where('aid',aid)->where('orderid',$orderid)->where('type','yueke_order')->find();
		$data = [];
		for($i=0;$i<=30;$i++){
			if($formdata['form'.$i]){
				$thisdata = explode('^_^',$formdata['form'.$i]);
				if($thisdata[1]!==''){
					$data[] = $thisdata;
				}
			}
		}
		$order['formdata'] = $data;
		$member = Db::name('member')->field('id,nickname,headimg,realname,tel')->where('id',$order['mid'])->find();
		if(!$member) $member = ['id'=>$order['mid'],'nickname'=>'','headimg'=>''];

        $comdata = array();
        return json(['order'=>$order,'member'=>$member,'couponrecord'=>$couponrecord ,'comdata'=>$comdata]);
	}
	//设置备注
	public function setremark(){
		$orderid = input('post.orderid/d');
		$content = input('post.content');
		if(bid == 0){
			Db::name('yueke_order')->where('aid',aid)->where('id',$orderid)->update(['remark'=>$content]);
		}else{
			Db::name('yueke_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['remark'=>$content]);
		}
		return json(['status'=>1,'msg'=>'设置完成']);
	}
	//改价格
	public function changeprice(){
		$orderid = input('post.orderid/d');
		$newprice = input('post.newprice/f');
		if(bid == 0){
			Db::name('yueke_order')->where('aid',aid)->where('id',$orderid)->update(['totalprice'=>$newprice,'ordernum'=>date('ymdHis').aid.rand(1000,9999)]);
		}else{
			Db::name('yueke_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['totalprice'=>$newprice,'ordernum'=>date('ymdHis').aid.rand(1000,9999)]);
		}
		return json(['status'=>1,'msg'=>'修改完成']);
	}
	//关闭订单
	public function closeOrder(){
		$orderid = input('post.orderid/d');
		$order = Db::name('yueke_order')->where('id',$orderid)->where('aid',aid)->find();
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if(!$order || $order['status']>1){
			return json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		//优惠券抵扣的返还
		if($order['coupon_rid'] > 0){
			//查看是不是计次卡
			$record = Db::name('coupon_record')->where('aid',aid)->where('mid',$order['mid'])->where('id',$order['coupon_rid'])->find();
			if($record['type']==3){  //将次数加回去
				if($record['used_count']>1){
					Db::name('coupon_record')->where('aid',aid)->where('bid',$order['bid'])->where('id',$record['id'])->dec('used_count')->update();
					$hxorder = [];
					$hxorder['aid'] = aid;
					$hxorder['bid'] = $order['bid'];
					$hxorder['uid'] = 0; //师傅的id
					$hxorder['mid'] = $order['mid'];
					$hxorder['orderid'] = $record['id'];
					$hxorder['ordernum'] = date('YmdHis');
					$hxorder['title'] = $record['couponname'];
					$hxorder['type'] = 'coupon';
					$hxorder['createtime'] = time();
					$hxorder['remark'] = '订单取消:'.$order['title'];
					Db::name('hexiao_order')->insert($hxorder);
				}
				if($record['status']==1)
					Db::name('coupon_record')->where('id',$couponrecord['id'])->update(['status'=>0,'usetime'=>'']);
			}else{
				Db::name('coupon_record')->where('aid',aid)->where('mid',$order['mid'])->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);	
			}
		}
		if($order['status']==1){
			//如果订单金额大于0 走退款
			if($order['totalprice']>0){
				$rs = \app\common\Order::refund($order,$order['totalprice'],'后台退款');
				if($rs['status']==0){
					return json(['status'=>0,'msg'=>$rs['msg']]);
				}
			}
			Db::name('yueke_order')->where('id',$orderid)->where('aid',aid)->where('bid',$order['bid'])->update(['status'=>4,'refund_money'=>$order['totalprice'],'refund_status'=>2]);
			//积分抵扣的返还
			if($order['scoredkscore'] > 0){
				\app\common\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
			}
			//扣除消费赠送积分
            \app\common\Member::decscorein(aid,'yueke',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
			//退款成功通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的订单已经完成退款，¥'.$order['totalprice'].'已经退回您的付款账户，请留意查收。';
			$tmplcontent['remark'] = '请点击查看详情~';
			$tmplcontent['orderProductPrice'] = (string) $order['totalprice'];
			$tmplcontent['orderProductName'] = $order['title'];
			$tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $order['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['totalprice'];//退款金额
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
		}else{
			$rs = Db::name('yueke_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4]);	
		}
		
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//改为已支付
	public function ispay(){
		if(bid > 0) showmsg('无权限操作');
		$orderid = input('post.orderid/d');
		$order = Db::name('yueke_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        if(!$order){
            return json(['status'=>0,'msg'=>'订单不存在']);
        }
        Db::name('yueke_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>1,'paytime'=>time(),'paytype'=>'后台支付']);
        \app\model\Payorder::yueke_pay($orderid);
        \app\common\System::plog('预约课程订单改为已支付'.$orderid);
        return json(['status'=>1,'msg'=>'操作成功']);
	}
	//发货
	public function sendExpress(){
		$orderid = input('post.orderid/d');
		$order = Db::name('yueke_order')->where('id',$orderid)->where('aid',aid)->find();
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if($order['freight_type']==10){
			$pic = input('post.pic');
			$fhname = input('post.fhname');
			$fhaddress = input('post.fhaddress');
			$shname = input('post.shname');
			$shaddress = input('post.shaddress');
			$remark = input('post.remark');
			$data = [];
			$data['aid'] = aid;
			$data['pic'] = $pic;
			$data['fhname'] = $fhname;
			$data['fhaddress'] = $fhaddress;
			$data['shname'] = $shname;
			$data['shaddress'] = $shaddress;
			$data['remark'] = $remark;
			$data['createtime'] = time();
			$id = Db::name('freight_type10_record')->insertGetId($data);
			$express_com = '货运托运';
			$express_no = $id;
		}else{
			$express_com = input('post.express_com');
			$express_no = input('post.express_no');
		}
		
		if($order['status']!=1){ //修改物流信息
			Db::name('yueke_order')->where('aid',aid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no]);
			return json(['status'=>1,'msg'=>'操作成功']);
		}

		Db::name('yueke_order')->where('aid',aid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no,'send_time'=>time(),'status'=>2]);
		
		
		//订单发货通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的订单已发货';
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['keyword1'] = $order['title'];
		$tmplcontent['keyword2'] = $express_com;
		$tmplcontent['keyword3'] = $express_no;
		$tmplcontent['keyword4'] = $order['linkman'].' '.$order['tel'];
        $tmplcontentNew = [];
        $tmplcontentNew['thing4'] = $order['title'];//商品名称
        $tmplcontentNew['thing13'] = $express_com;//快递公司
        $tmplcontentNew['character_string14'] = $express_no;//快递单号
        $tmplcontentNew['thing16'] = $order['linkman'].' '.$order['tel'];//收货人
		\app\common\Wechat::sendtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['thing7'] = $express_com;
		$tmplcontent['character_string4'] = $express_no;
		$tmplcontent['thing11'] = $order['address'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['thing29'] = $order['title'];
		$tmplcontentnew['thing1'] = $express_com;
		$tmplcontentnew['character_string2'] = $express_no;
		$tmplcontentnew['thing9'] = $order['address'];
		\app\common\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

		//短信通知
		$member = Db::name('member')->where('id',$order['mid'])->find();
		if($member['tel']){
			$tel = $member['tel'];
		}else{
			$tel = $order['tel'];
		}
		$rs = \app\common\Sms::send(aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>$express_com,'express_no'=>$express_no]);

		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//查物流
	public function getExpress(){
		$orderid = input('post.orderid/d');
		$order = Db::name('yueke_order')->where('id',$orderid)->where('aid',aid)->find();
		$list =$this->getjindu($order['worker_orderid']);
		return json(['status'=>1,'data'=>$list]);
	}
	//退款审核
	public function refundCheck(){
		$orderid = input('post.orderid/d');
		$st = input('post.st/d');
		$remark = input('post.remark');
		$order = Db::name('yueke_order')->where('id',$orderid)->where('aid',aid)->find();
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if($st==2){
            $refund['refund_status']= 3;
            $refund['refund_checkremark'] = $remark;
            Db::name('yueke_order')->where('id',$orderid)->where('aid',aid)->update($refund);
			//退款申请驳回通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的退款申请被商家驳回，可与商家协商沟通。';
			$tmplcontent['remark'] = $remark.'，请点击查看详情~';
			$tmplcontent['orderProductPrice'] = (string) $order['refund_money'];
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

			return json(['status'=>1,'msg'=>'退款已驳回']);
		}elseif($st == 1){
			if($order['status']!=1 && $order['status']!=2){
				return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
			}
			$rs = \app\common\Order::refund($order,$order['refund_money'],$order['refund_reason']);
			if($rs['status']==0){
				return json(['status'=>0,'msg'=>$rs['msg']]);
			}
            if(false){}else{
                $refundorder['status'] = 4;
            }
            $refundorder['refund_status']= 2;
			Db::name('yueke_order')->where('id',$orderid)->where('aid',aid)->update($refundorder);

			//积分抵扣的返还
			if($order['scoredkscore'] > 0){
				\app\common\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
			}
			//扣除消费赠送积分
            \app\common\Member::decscorein(aid,'yueke',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
			//退款退还佣金
			//退款成功通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的订单已经完成退款，¥'.$order['refund_money'].'已经退回您的付款账户，请留意查收。';
			$tmplcontent['remark'] = '请点击查看详情~';
			$tmplcontent['orderProductPrice'] = (string) $order['refund_money'];
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
			return json(['status'=>1,'msg'=>'已退款成功']);
		}
	}
	//删除
	public function del(){
		$id = input('post.id/d');
		if(bid == 0){
			Db::name('yueke_order')->where('aid',aid)->where('id',$id)->delete();
		}else{
			Db::name('yueke_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
		}
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	public function getjindu($express_no){
			$psorderinfo =Db::name('yueke_worker_order')->where('id',$express_no)->find();
			$psuser = Db::name('yueke_worker')->where('id',$psorderinfo['worker_id'])->find();
			//查看是服务方式
			$order = Db::name('yueke_order')->where('id',$psorderinfo['orderid'])->find();
			if($order['fwtype']==1){
					$list = [];
					if($psorderinfo['createtime']){
						$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['createtime']),'context'=>'已发布服务单'];
					}
					if($psorderinfo['starttime']){
						$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['starttime']),'context'=>'等待顾客'.$order['linkman'].'('.$order['tel'].')'.'到店'];
					}
					if($psorderinfo['daodiantime']){
						$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['daodiantime']),'context'=>'顾客已到店'];
					}
					if($psorderinfo['endtime']){
						$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['endtime']),'context'=>'服务完成'];
					}
			}else{
					$list = [];
					if($psorderinfo['createtime']){
						$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['createtime']),'context'=>'已发布服务单'];
					}
					if(false){}else{
						if($psorderinfo['starttime']){
							$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['starttime']),'context'=>'服务人员'.$psuser['realname'].'('.$psuser['tel'].')'.'正在途中'];
						}
						if($psorderinfo['daodiantime']){
							$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['daodiantime']),'context'=>'服务人员已到达现场'];
						}
					}
					if($psorderinfo['endtime']){
						$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['endtime']),'context'=>'服务完成'];
					}
			}

			$list = array_reverse($list);
			return $list;
	}
	public function collect(){ //确认完成
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('yueke_order')->where('aid',aid)->where('id',$orderid)->find();
		if($order['status']!=1){
			return json(['status'=>0,'msg'=>'订单状态不符合']);
		}
		$updata = [];
		$updata['status'] = 3;
		$updata['endtime'] = time();
		db('yueke_order')->where(['aid'=>aid,'id'=>$orderid])->update(['status'=>3,'collect_time'=>time()]);
		$rs = \app\common\Order::collect($order,'yueke');
		if($rs['status'] == 0) return json($rs);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
    function defaultSet(){
        $set = Db::name('yueke_set')->where('bid',bid)->where('aid',aid)->find();
        if(!$set){
            Db::name('yueke_set')->insert(['aid'=>aid,'bid' => bid]);
        }
    }

    //修改服务人员
    public function changeWorker(){
        }

    public function changeOrderWorker(){
        }

    //学习记录
    public function studyrecord(){
        }
}
