<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 活动报名-订单记录custom_file(huodong_baoming)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\Log;
use think\facade\View;
use think\facade\Db;
class HuodongBaomingOrder extends Common
{
	//订单列表
    public function index(){
        $allBidOrder = false;
        $canPaidan = true;
        $businessArr = [];
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
                if(!$allBidOrder){
                    $where[] = ['bid','=',bid];
                }else{
                    if(is_numeric(input('param.bid'))){
                        $where[] = ['bid','=',input('param.bid')];
                    }
                }
			}
			if(input('param.orderid')) $where[] = ['id','=',input('param.orderid')];
			if(input('param.proid')) $where[] = ['proid','=',input('param.proid')];
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
					$where[] = ['status','=',input('param.status')];
			}
			$count = 0 + Db::name('huodong_baoming_order')->where($where)->count();
			$list = Db::name('huodong_baoming_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($list as $k=>$vo){
				$member = Db::name('member')->where('id',$vo['mid'])->find();
				$list[$k]['goodsdata'] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
					'<img src="'.$vo['propic'].'" style="max-width:60px;float:left">'.
					'<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
						'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$vo['proname'].'</div>'.
						'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$vo['ggname'].'X'.$vo['num'].'</div>'.
						'<div style="padding-top:0px;color:#f60;font-size:12px">购买价￥'.$vo['product_price'].'</div>'.
					'</div>'.
				'</div>';
				$list[$k]['nickname'] = $member['nickname'];
				$list[$k]['headimg'] = $member['headimg'];
				$list[$k]['platform'] = getplatformname($vo['platform']);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		$where = [];
		if(input('param.')) $where = input('param.');
		$where = json_encode($where);
		View::assign('where',$where);
		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
		View::assign('peisong_set',$peisong_set);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
		View::assign('businesslist',$businessArr);
        View::assign('allBidOrder',$allBidOrder);
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
        $allBidOrder = false;
		$where = [];
		$where[] = ['aid','=',aid];
		if(input('param.bid') == 'all'){
			
		}else{
            if(!$allBidOrder){
                $where[] = ['bid','=',bid];
            }else{
                if(is_numeric(input('param.bid'))){
                    $where[] = ['bid','=',input('param.bid')];
                }
            }
		}
		if($this->mdid){
			$where[] = ['mdid','=',$this->mdid];
		}
		if(input('param.proid')) $where[] = ['proid','=',input('param.proid')];
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
				$where[] = ['status','=',input('param.status')];

		}
		$list = Db::name('huodong_baoming_order')->where($where)->order($order)->select()->toArray();
		$title = array('订单号','下单人','商品名称','总价','实付款','支付方式','客户信息','备注','下单时间','订单状态','表单信息');
		$data = [];
		foreach($list as $k=>$vo){
			$member = Db::name('member')->where('id',$vo['mid'])->find();
			$status='';
			if($vo['status']==0){
				$status = '未支付';
			}elseif($vo['status']==1){
				$status = '已支付';
			}elseif($vo['status']==3){
				$status = '已完成';
			}elseif($vo['status']==4){
				$status = '已关闭';
			}

            $vo['formdata'] = \app\model\Freight::getformdata($vo['id'],'huodong_baoming_order');
            $formdataArr = [];
            if($vo['formdata']) {
                foreach ($vo['formdata'] as $formdata) {
                    if($formdata[2] != 'upload') {
                        $formdataArr[] = $formdata[0].':'.$formdata[1];
                    }
                }
            }
            $formdatastr = implode("\r\n",$formdataArr);
            $data1 = [
				' '.$vo['ordernum'],
				$member['nickname'],
				$vo['title'],
				$vo['product_price'],
				$vo['totalprice'],
				$vo['paytype'],
				$vo['linkman'].'('.$vo['tel'].') ',
				$vo['remark'],
				date('Y-m-d H:i:s',$vo['createtime']),
				$status,
                $formdatastr
			];

			$data[] = $data1;
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//订单详情
	public function getdetail(){
		$orderid = input('post.orderid');
        $allBids = false;

        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','=',$orderid];
        if(!$allBids){
            $where[] = ['bid','=',bid];
        }
		$order = Db::name('huodong_baoming_order')->where($where)->find();
		if(empty($order)) showmsg('订单数据有误');

		if($order['coupon_rid']){
			$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
		}else{
			$couponrecord = false;
		}
		$formdata = Db::name('freight_formdata')->where('aid',aid)->where('orderid',$orderid)->where('type','huodong_baoming_order')->find();
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

		return json(['order'=>$order,'member'=>$member,'couponrecord'=>$couponrecord]);
	}
	//设置备注
	public function setremark(){
		$orderid = input('post.orderid/d');
		$content = input('post.content');
        $allBids = false;

        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','=',$orderid];
        if(!$allBids){
            $where[] = ['bid','=',bid];
        }
        Db::name('huodong_baoming_order')->where($where)->update(['remark'=>$content]);
		\app\common\System::plog('活动报名订单设置备注'.$orderid);
		return json(['status'=>1,'msg'=>'设置完成']);
	}
	//关闭订单
	public function closeOrder(){
		$orderid = input('post.orderid/d');
        $allBids = false;

        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','=',$orderid];
        if(!$allBids){
            $where[] = ['bid','=',bid];
        }
		$order = Db::name('huodong_baoming_order')->where($where)->find();
//		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if(!$order || $order['status']>1){
			return json(['status'=>0,'msg'=>'关闭失败,订单信息错误']);
		}
		//优惠券抵扣的返还
		if($order['coupon_rid'] > 0){
			//查看是不是计次卡
			$record = Db::name('coupon_record')->where('aid',aid)->where('mid',$order['mid'])->where('id',$order['coupon_rid'])->find();
			if(false){}else{
				Db::name('coupon_record')->where('aid',aid)->where('mid',$order['mid'])->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);	
			} 
		}	
		$rs = Db::name('huodong_baoming_order')->where('id',$orderid)->where('aid',aid)->where('bid',$order['bid'])->update(['status'=>4]);
		\app\common\System::plog('活动报名订单关闭'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
    //退款
    public function refund(){
        $orderid = input('post.orderid/d');
        $reason = input('post.reason');
        if(bid == 0){
            $order = Db::name('huodong_baoming_order')->where('id',$orderid)->where('aid',aid)->find();
        }else{
            $order = Db::name('huodong_baoming_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
        }

        if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
        if(($order['status']!=1 && $order['status']!=2)){
            return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
        }

        try {
            Db::startTrans();
            $post = input('post.');
            $orderid = intval($post['orderid']);
            $data['refund_money'] = $order['totalprice'];
            if ($data['refund_money'] > 0) {
                $params = [];
                $params['refund_order'] = $data;
                $rs = \app\common\Order::refund($order, $data['refund_money'], $reason,$params);
                if ($rs['status'] == 0) {
                    if($order['balance_price'] > 0){
                        $order2 = $order;
                        $order2['totalprice'] = $order2['totalprice'] - $order2['balance_price'];
                        $order2['ordernum'] = $order2['ordernum'].'_0';
                        $rs = \app\common\Order::refund($order2,$data['refund_money'],$reason);
                        if($rs['status']==0){
                            return json(['status'=>0,'msg'=>$rs['msg']]);
                        }
                    }else{
                        return json(['status'=>0,'msg'=>$rs['msg']]);
                    }
                }
            }

            // 删除销量
            Db::name('huodong_baoming_product')->where('aid', aid)->where('id', $order['id'])->update(['sales' => Db::raw("sales-" . $order['num'])]);

            //积分抵扣的返还
            if ($order['totalscore'] > 0) {
                \app\common\Member::addscore(aid, $order['mid'], $order['totalscore'], '活动报名订单退款返还');
            }

            //优惠券抵扣的返还
            if($order['coupon_rid'] > 0){
                //查看是不是计次卡
                $record = Db::name('coupon_record')->where('aid',aid)->where('mid',$order['mid'])->where('id',$order['coupon_rid'])->find();
                if(false){}else{
                    Db::name('coupon_record')->where('aid',aid)->where('mid',$order['mid'])->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
                }
            }
            $rs = Db::name('huodong_baoming_order')->where('id',$orderid)->where('aid',aid)->where('bid',$order['bid'])->update(['status'=>4, 'refund_status' => 2, 'refund_time' => time(), 'refund_money' => 2]);
            Db::commit();
        } catch (\Exception $e) {
            Log::write([
                'file' => __FILE__ . ' L' . __LINE__,
                'function' => __FUNCTION__,
                'error' => $e->getMessage(),
            ]);
            Db::rollback();
            return json(['status'=>0,'msg'=>'提交失败,请重试']);
        }

        \app\common\System::plog('活动报名订单退款'.$orderid);
        return json(['status'=>1,'msg'=>'已退款成功']);
    }
	//改为已支付
	public function ispay(){
		if(bid > 0) showmsg('无权限操作');
		$orderid = input('post.orderid/d');
        $allBids = false;
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','=',$orderid];
        if(!$allBids){
            $where[] = ['bid','=',bid];
        }
		$order = Db::name('huodong_baoming_order')->where($where)->find();
        if($order['status']!=0){
            return json(['status'=>0,'msg'=>'订单状态不支持该操作']);
        }
		Db::name('huodong_baoming_order')->where('id',$order['id'])->update(['status'=>1,'paytime'=>time(),'paytype'=>'后台支付']);
		Db::name('payorder')->where('orderid',$order['id'])->where('type','huodong_baoming')->update(['status'=>1,'paytime'=>time(),'paytype'=>'后台支付']);
		Db::name('huodong_baoming_product')->where('aid',aid)->where('id',$order['proid'])->update(['sales'=>Db::raw("sales+".$order['num'])]);
		//奖励积分
//		$order = Db::name('huodong_baoming_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		if($order['givescore'] > 0){
			\app\common\Member::addscore(aid,$order['mid'],$order['givescore'],'报名活动奖励'.t('积分'));
		}
		\app\common\System::plog('活动报名订单改为支付'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}

	//删除
	public function del(){
		$id = input('post.id/d');
		if(bid == 0){
            $where = $where1 = [];
            $where[] = ['aid','=',aid];
            $where[] = ['id','=',$id];

			Db::name('huodong_baoming_order')->where($where)->delete();

		}else{
			Db::name('huodong_baoming_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();

		}
		\app\common\System::plog('活动报名订单删除'.$id);
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	
	function collect(){ //确认完成
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('huodong_baoming_order')->where('aid',aid)->where('id',$orderid)->find();
		if($order['status']!=1) return json(['status'=>0,'msg'=>'订单状态不符合']);
		$updata = [];
		$updata['status'] = 3;
		$updata['endtime'] = time();
		db('huodong_baoming_order')->where(['aid'=>aid,'id'=>$orderid])->update(['status'=>3,'collect_time'=>time()]);
        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\common\Order::wxShipping(aid,$order,'huodong_baoming');
        }
		\app\common\System::plog('活动报名订单确认完成'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
    function defaultSet(){
        $set = Db::name('huodong_baoming_set')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            Db::name('huodong_baoming_set')->insert(['aid'=>aid,'bid' => bid]);
        }
    }
}
