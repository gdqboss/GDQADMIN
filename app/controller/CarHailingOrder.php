<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 约车 - 约车记录 custom_file(car_hailing)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;
class CarHailingOrder extends Common
{
    public $category = ['1' => '租车','2' => '拼车' ,'3' => '包车'];
    public $category_list = [['id' =>1,'name' =>'租车'],['id' => 2,'name' => '拼车'],['id' =>3,'name' => '包车']];
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
            if(input('param.yy_date') ){
                $where[] = ['yy_date','=',input('param.yy_date')];
            }
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
            if(input('param.zctime') ){
                $zctime = explode(' ~ ',input('param.zctime'));
                $where[] = ['zc_start_time','>=',$zctime[0]];
                $where[] = ['zc_end_time','<',$zctime[1]];;
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
            if(input('param.cid') ){
               $proid = Db::name('car_hailing_product')->where('cid',input('param.cid'))->column('id');
               $where[] = ['proid','in',$proid];
            }
            if(input('param.pid') ){
                $clist = Db::name('car_hailing_category')->where('aid',aid)->where('pid',input('param.pid'))->column('id');
               
                $clist[] =  input('param.pid');
              
                $proid = Db::name('car_hailing_product')->where('pid','in',$clist)->column('id');
                $where[] = ['proid','in',$proid];
            }
           
			$count = 0 + Db::name('car_hailing_order')->where($where)->count();
			$list = Db::name('car_hailing_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($list as $k=>$vo){
				$member = Db::name('member')->where('id',$vo['mid'])->find();
				$cid = Db::name('car_hailing_product')->where('id',$vo['proid'])->value('cid');
			    $cname_ar=['1' => '租车','2' =>'拼车','3' => '包车'];
				$cname = $cname_ar[$cid];
				$unit =   $cid==1 || $cid==3?' /天':' /人';
				
				$list[$k]['goodsdata'] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
					'<img src="'.$vo['propic'].'" style="max-width:60px;float:left">'.
					'<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
					'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$vo['proname'].'</div>'.
                    
					'<div style="padding-top:0px;color:#f60;font-size:12px; font-weight: 700">￥'.$vo['product_price'].$unit.'</div>'.
					'</div>'.
				'</div>';
				$list[$k]['nickname'] = $member['nickname'];
				$list[$k]['headimg'] = $member['headimg'];
				$list[$k]['cname'] = $cname;
				$list[$k]['cid'] = $cid;
				
				$list[$k]['platform'] = getplatformname($vo['platform']);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		$where = [];
		if(input('param.')) $where = input('param.');
		$where = json_encode($where);
		View::assign('where',$where);
        $clist = $this->category_list;
        View::assign('clist',$clist);

        //分类
        $plist = Db::name('car_hailing_category')->field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray();
        foreach($plist as $k=>$v){
            $plist[$k]['child'] = Db::name('car_hailing_category')->field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
        }
        View::assign('plist',$plist);
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
		$where = [];
		$where[] = ['aid','=',aid];
		if(input('param.bid') == 'all'){
			
		}else{
			$where[] = ['bid','=',bid];
		}
		if($this->mdid){
			$where[] = ['mdid','=',$this->mdid];
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
        if(input('param.cid') ){
            $proid = Db::name('car_hailing_product')->where('cid',input('param.cid'))->column('id');
            $where[] = ['proid','in',$proid];
        }
        if(input('param.yy_date') ){
            $where[] = ['yy_date','=',input('param.yy_date')];
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
        if(input('param.pid') ){
            $clist = Db::name('car_hailing_category')->where('aid',aid)->where('pid',input('param.pid'))->column('id');

            $clist[] =  input('param.pid');

            $proid = Db::name('car_hailing_product')->where('pid','in',$clist)->column('id');
            $where[] = ['proid','in',$proid];
        }
		$list = Db::name('car_hailing_order')->where($where)->order($order)->select()->toArray();
		$title = array('订单号','下单人','商品名称','类别','单价','数量','实付款','支付方式','预约时间','客户信息','客户留言','备注','下单时间','租车时间','订单状态','表单数据');
	
		$data = [];

		foreach($list as $k=>$vo){
		    $product = Db::name('car_hailing_product')->field('id,cid')->where('id',$vo['proid'])->find();
		    $cname =   $this->category[$product['cid']];
			$member = Db::name('member')->where('id',$vo['mid'])->find();
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
             $edata= [
				' '.$vo['ordernum'],
				$member['nickname'],
				$vo['title'],
                $cname,
				$vo['product_price'],
                $vo['num'],
				$vo['totalprice'],
				$vo['paytype'],
                $product['cid']==2?$vo['yy_time']:'暂无',
				$vo['linkman'].'('.$vo['tel'].') '.$vo['area'].' '.$vo['address'],
				$vo['message'],
				$vo['remark'],
				date('Y-m-d H:i:s',$vo['createtime']),
                 $product['cid']==1?$vo['zc_start_time'].' 至 '.$vo['zc_end_time']:'', 
				$status,
			];
            $formdata = \app\model\Freight::getformdata($vo['id'],'car_hailing_order');
            $formarr = [];
            if($formdata){
                foreach($formdata as  $key=>&$val){
                    if($val[2] =='selector'){
                        $str = explode( ',', $val[1]);
                        if($str && is_numeric($str[1])){
                            $val[1] = $str[0].'(￥'.$str[1].')';
                        }
                    }
                    $formarr  []=   $val[0].'-'.$val[1] ;
                }

            }
            $data[] = array_merge($edata,$formarr);
        }
	
		$this->export_excel($title,$data);
	}
	//订单详情
	public function getdetail(){
		$orderid = input('post.orderid');
		$order = Db::name('car_hailing_order')->where('aid',aid)->where('id',$orderid)->find();
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');

		if($order['coupon_rid']){
			$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
		}else{
			$couponrecord = false;
		}
		$cid = Db::name('car_hailing_product')->where('aid',aid)->where('id',$order['proid'])->value('cid');
		$cname = $this->category[$cid];
		$order['cid'] = $cid;
		$order['cname'] = $cname;
		if($cid ==2){
		    $order['unit'] = '/人';
        }
        if($cid ==1 || $cid ==3){
            $order['unit'] = '/天';
        }
		$formdata = Db::name('freight_formdata')->where('aid',aid)->where('orderid',$orderid)->where('type','car_hailing_order')->find();
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
        if($order['formdata']){
            foreach($order['formdata'] as  &$val){
                if($val[2] =='selector'){
                    $str = explode( ',', $val[1]);
                    if($str && is_numeric($str[1])){
                        $val[1] = $str[0].'(￥'.$str[1].')';
                    }
                }
            }
        }
		$member = Db::name('member')->field('id,nickname,headimg,realname,tel')->where('id',$order['mid'])->find();
		if(!$member) $member = ['id'=>$order['mid'],'nickname'=>'','headimg'=>''];
		return json(['order'=>$order,'member'=>$member,'couponrecord'=>$couponrecord]);
	}
	//设置备注
	public function setremark(){
		$orderid = input('post.orderid/d');
		$content = input('post.content');
		if(bid == 0){
			Db::name('car_hailing_order')->where('aid',aid)->where('id',$orderid)->update(['remark'=>$content]);
		}else{
			Db::name('car_hailing_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['remark'=>$content]);
		}
		return json(['status'=>1,'msg'=>'设置完成']);
	}
	//改价格
	public function changeprice(){
		$orderid = input('post.orderid/d');
		$newprice = input('post.newprice/f');
		if(bid == 0){
			Db::name('car_hailing_order')->where('aid',aid)->where('id',$orderid)->update(['totalprice'=>$newprice,'ordernum'=>date('ymdHis').aid.rand(1000,9999)]);
		}else{
			Db::name('car_hailing_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['totalprice'=>$newprice,'ordernum'=>date('ymdHis').aid.rand(1000,9999)]);
		}
		return json(['status'=>1,'msg'=>'修改完成']);
	}
	//关闭订单
	public function closeOrder(){
		$orderid = input('post.orderid/d');
		$order = Db::name('car_hailing_order')->where('id',$orderid)->where('aid',aid)->find();
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
					Db::name('coupon_record')->where('id',$record['id'])->update(['status'=>0,'usetime'=>'']);
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
			Db::name('car_hailing_order')->where('id',$orderid)->where('aid',aid)->where('bid',$order['bid'])->update(['status'=>4,'refund_money'=>$order['totalprice'],'refund_status'=>2]);
			//积分抵扣的返还
			if($order['scoredkscore'] > 0){
				\app\common\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
			}
			//扣除消费赠送积分
        	\app\common\Member::decscorein(aid,'car_hailing',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
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
			$rs = Db::name('car_hailing_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4]);	
		}
		
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//改为已支付
	public function ispay(){
		if(bid > 0) showmsg('无权限操作');
		$orderid = input('post.orderid/d');
		$order = Db::name('car_hailing_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		Db::name('car_hailing_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>1,'paytime'=>time(),'paytype'=>'后台支付']);
		
		//奖励积分
		$order = Db::name('car_hailing_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		if($order['givescore'] > 0){
			\app\common\Member::addscore(aid,$order['mid'],$order['givescore'],'购买产品奖励'.t('积分'));
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}

	//退款审核
	public function refundCheck(){
		$orderid = input('post.orderid/d');
		$st = input('post.st/d');
		$remark = input('post.remark');
		$order = Db::name('car_hailing_order')->where('id',$orderid)->where('aid',aid)->find();
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if($st==2){
			Db::name('car_hailing_order')->where('id',$orderid)->where('aid',aid)->update(['refund_status'=>3,'refund_checkremark'=>$remark]);
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

			Db::name('car_hailing_order')->where('id',$orderid)->where('aid',aid)->update(['status'=>4,'refund_status'=>2]);

			//积分抵扣的返还
			if($order['scoredkscore'] > 0){
				\app\common\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
			}

			//扣除消费赠送积分
        	\app\common\Member::decscorein(aid,'car_hailing',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
			
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
			Db::name('car_hailing_order')->where('aid',aid)->where('id',$id)->delete();
		}else{
			Db::name('car_hailing_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
		}
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	public function collect(){ //确认完成
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('car_hailing_order')->where('aid',aid)->where('id',$orderid)->find();
		if($order['status']!=1){
			return json(['status'=>0,'msg'=>'订单状态不符合']);
		}
		$updata = [];
		$updata['status'] = 3;
		$updata['endtime'] = time();
		db('car_hailing_order')->where(['aid'=>aid,'id'=>$orderid])->update(['status'=>3,'collect_time'=>time()]);
		$rs = \app\common\Order::collect($order,'car_hailing');
		if($rs['status'] == 0) return json($rs);
		return json(['status'=>1,'msg'=>'操作成功']);
	}

}
