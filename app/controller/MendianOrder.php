<?php
// JK客户定制

//custom_file(mendian_upgrade)
// +----------------------------------------------------------------------
// | 商城-发货门店
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\Log;
use think\facade\View;
use think\facade\Db;
class MendianOrder extends Common
{
    public function initialize(){
    	if(!getcustom('mendian_upgrade')){
    		showmsg('无权限操作');
    	}
        parent::initialize();
    }

	//订单列表
    public function index(){
    	if(!getcustom('mendian_upgrade')){
    		showmsg('无权限操作');
    	}
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'order.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'order.id desc';
			}
			$where = [];
			$where[] = ['order.aid','=',aid];
			if(bid==0){
				// if(input('param.bid')){
				// 	$where[] = ['order.bid','=',input('param.bid')];
				// }elseif(input('param.showtype')==2){
				// 	$where[] = ['order.bid','<>',0];
    //             }elseif(input('param.showtype')=='all'){
    //                 $where[] = ['order.bid','>=',0];
				// }else{
				// 	$where[] = ['order.bid','=',0];
				// }
			}else{
				$where[] = ['order.bid','=',bid];
			}

			if($this->user['isadmin']!=1 && $this->mdid){
				$where[] = ['order.mdid','=',$this->mdid];
			}
			if(input('?param.ogid')){
				if(input('param.ogid')==''){
					$where[] = ['1','=',0];
				}else{
					$ids = Db::name('shop_order_goods')->where('id','in',input('param.ogid'))->column('orderid');
					$where[] = ['order.id','in',$ids];
				}
			}

			if(input('param.mid')){
                $where[] = ['order.mid','=',input('param.mid')];
            }
            
            if(input('param.bid')){
                $where[] = ['order.bid','=',input('param.bid')];
            }
			if(input('param.orderid')) $where[] = ['order.id','=',input('param.orderid')];
            $where[] = ['order.freight_type','in',[1,5]];
			if(input('param.proname')) $where[] = ['order.proname','like','%'.input('param.proname').'%'];
			if(input('param.ordernum')) $where[] = ['order.ordernum','like','%'.input('param.ordernum').'%'];
            if(input('param.nickname')) $where[] = ['member.nickname|member.realname','like','%'.input('param.nickname').'%'];
            if(input('param.linkman')) $where[] = ['order.linkman|order.tel','like','%'.input('param.linkman').'%'];
			if(input('param.tel')) $where[] = ['order.tel','like','%'.input('param.tel').'%'];
			if(input('param.proid')){
				$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('proid',input('param.proid'))->column('orderid');
				$where[] = ['order.id','in',$orderids];
			}
			if(input('param.ctime')){
				$ctime = explode(' ~ ',input('param.ctime'));
				if(input('param.time_type') == 1){ //下单时间
					$where[] = ['order.createtime','>=',strtotime($ctime[0])];
					$where[] = ['order.createtime','<',strtotime($ctime[1])];
				}elseif(input('param.time_type') == 2){ //付款时间
					$where[] = ['order.paytime','>=',strtotime($ctime[0])];
					$where[] = ['order.paytime','<',strtotime($ctime[1])];
				}elseif(input('param.time_type') == 3){ //发货时间
					$where[] = ['order.send_time','>=',strtotime($ctime[0])];
					$where[] = ['order.send_time','<',strtotime($ctime[1])];
				}elseif(input('param.time_type') == 4){ //完成时间
					$where[] = ['order.collect_time','>=',strtotime($ctime[0])];
					$where[] = ['order.collect_time','<',strtotime($ctime[1])];
				}
			}
			if(input('param.keyword')){
				$keyword = input('param.keyword');
				$keyword_type = input('param.keyword_type');
				if($keyword_type == 1){ //订单号
					$where[] = ['order.ordernum','like','%'.$keyword.'%'];
				}elseif($keyword_type == 2){ //会员ID
					$where[] = ['order.mid','=',$keyword];
				}elseif($keyword_type == 3){ //会员信息
					$where[] = ['member.nickname|member.realname','like','%'.$keyword.'%'];
				}elseif($keyword_type == 4){ //收货信息
					$where[] = ['order.linkman|order.tel|order.area|order.address','like','%'.$keyword.'%'];
				}elseif($keyword_type == 5){ //快递单号
					$where[] = ['order.express_no','like','%'.$keyword.'%'];
				}elseif($keyword_type == 6){ //商品ID
					$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('proid',$keyword)->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 7){ //商品名称
					$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('name','like','%'.$keyword.'%')->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 8){ //商品编码
					$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('procode','like','%'.$keyword.'%')->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 9){ //核销员
					$orderids = Db::name('hexiao_order')->where('aid',aid)->where('type','shop')->where('remark','like','%'.$keyword.'%')->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 10){ //所属门店
					$mdids = Db::name('mendian')->where('aid',aid)->where('name','like','%'.$keyword.'%')->column('id');
					if(getcustom('mendian_upgrade')){
						//门店分组
						if(input('param.mdgid')){
							$mdids2 = Db::name('mendian')->where('groupid',input('param.mdgid'))->where('aid',aid)->where('bid',bid)->column('id');
							if(!empty($mdids2)){
								if(!empty($mdids)){
									$mdids = array_merge($mdids,$mdids2);
								}else{
									$mdids = $mdids2;
								}
							}
						}
						//门店id
						if(input('param.mdid')) $mdids[] = input('param.mdid');
						$mdids = array_unique($mdids);
					}
					$where[] = ['order.mdid','in',$mdids];
				}elseif($keyword_type == 21){ //兑换卡号
					$where[] = ['order.duihuan_cardno','=',$keyword];
				}
			}


			if(getcustom('mendian_upgrade')){
				if(!input('param.keyword') || input('param.keyword_type')!=10){
					//门店分组
					if(input('param.mdgid')){
						$mdids = [];
						$mdids2 = Db::name('mendian')->where('groupid',input('param.mdgid'))->where('aid',aid)->where('bid',bid)->column('id');
						if(!empty($mdids2)){
							$mdids = $mdids2;
						}
						//门店id
						if(input('param.mdid')) $mdids[] = input('param.mdid');
						$mdids = array_unique($mdids);
						if(!empty($mdids)){
							$where[] = ['order.mdid','in',$mdids];
						}else{
							$where[] = ['order.id','=',0];
						}
					}else{
						if(input('param.mdid')) $where[] = ['order.mdid','=',input('param.mdid')];
					}
				}
			}
			$where[] = ['order.status','=',1];
			$totalcommission = 0;
			if(input('param.fxmid')){
				$fxmid = input('param.fxmid/d');
				if($page == 1){
					$where1 = $where;
					$where1[] = Db::raw("order.id in (select orderid from ".table_name('shop_order_goods')." where parent1={$fxmid})");
					$totalcommission1 = Db::name('shop_order_goods')->where('orderid','in',function($query)use($where1){
						$query->name('shop_order')->alias('order')->leftJoin('member member','member.id=order.mid')->where($where1)->field('order.id');
					})->sum('parent1commission');
					$where2 = $where;
					$where2[] = Db::raw("order.id in (select orderid from ".table_name('shop_order_goods')." where parent2={$fxmid})");
					$totalcommission2 = Db::name('shop_order_goods')->where('orderid','in',function($query)use($where2){
						$query->name('shop_order')->alias('order')->leftJoin('member member','member.id=order.mid')->where($where2)->field('order.id');
					})->sum('parent2commission');
					$where3 = $where;
					$where3[] = Db::raw("order.id in (select orderid from ".table_name('shop_order_goods')." where parent3={$fxmid})");
					$totalcommission3 = Db::name('shop_order_goods')->where('orderid','in',function($query)use($where3){
						$query->name('shop_order')->alias('order')->leftJoin('member member','member.id=order.mid')->where($where3)->field('order.id');
					})->sum('parent3commission');

					$totalcommission = round($totalcommission1 + $totalcommission2 + $totalcommission3,2);
				}

				$where[] = Db::raw("order.id in (select orderid from ".table_name('shop_order_goods')." where parent1={$fxmid} or parent2={$fxmid} or parent3={$fxmid})");
			}

			$count = 0 + Db::name('shop_order')->alias('order')->leftJoin('member member','member.id=order.mid')->where($where)->count();
			//echo M()->_sql();
			$list = Db::name('shop_order')->alias('order')->field('order.*')->leftJoin('member member','member.id=order.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			if(getcustom('mendian_upgrade')){
				$mendian_upgrade_status = Db::name('admin')->where('id',aid)->value('mendian_upgrade_status');
			}

			foreach($list as $k=>$vo){
                // 订单添加备注信息
                $formfield = Db::name('freight')->where('id',$vo['freight_id'])->find();
                $formdataSet = json_decode($formfield['formdata'],true);
                foreach($formdataSet as $k1=>$v){
                    if($v['val1'] == '备注'){
                        $message =Db::name('freight_formdata')->where('type','shop_order')->where('orderid',$vo['id'])->value('form'.$k1);
                        $value = explode('^_^',$message);
                        if($value[1] !== ''){
                            $list[$k]['message'] = $value[1];
                        }
                        break;
                    }
                }

				$member = Db::name('member')->where('id',$vo['mid'])->find();
				$oglist = Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$vo['id'])->select()->toArray();
				$goodsdata=array();
				foreach($oglist as $og){
				    $grstr = '';
                    $ogremark = '';
                    if($og['gtype']==1){
                        $ogremark = '<span style="color:#f00;">【赠品】</span>';
                    }
					$goodshtml = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
						'<div class="table-imgbox"><img lay-src="'.$og['pic'].'" src="'.PRE_URL.'/static/admin/layui/css/modules/layer/default/loading-2.gif"></div>'.
						'<div style="float: left;width:180px;margin-left: 10px;white-space:normal;line-height:16px;">'.
							'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['name'].$ogremark.'</div>'.
							'<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$og['ggname'].'</span></div>'.$grstr;

					$goodshtml.='<div style="padding-top:0px;color:#f60;">￥'.$og['sell_price'].' × '.$og['num'].'</div>';
					$goodshtml.='</div>';
					$goodshtml.='</div>';
					
                    $goodsdata[] = $goodshtml;
				}

				$list[$k]['goodsdata'] = implode('',$goodsdata);
				if($vo['bid'] > 0){
					$list[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$vo['bid'])->value('name');
				}else{
					$list[$k]['bname'] = '平台自营';
				}
                $refundOrder = Db::name('shop_refund_order')->where('refund_status','>',0)->where('aid',aid)->where('orderid',$vo['id'])->count();
                $list[$k]['refundCount'] = $refundOrder;
                $list[$k]['payorder'] = [];
                if($vo['paytypeid'] == 5) {
                    $list[$k]['payorder'] = Db::name('payorder')->where('id',$vo['payorderid'])->where('aid',aid)->find();
                }
				$list[$k]['nickname'] = $member['nickname'];
				$list[$k]['headimg'] = $member['headimg'];
				$list[$k]['m_remark'] = $member['remark'];
				$list[$k]['platform'] = getplatformname($vo['platform']);
				if($order_show_member_apply) {
                    $member_level = Db::name('member_level')->where('id',$member['levelid'])->find();
                    $list[$k]['member_apply_info'] = $member['realname'] . '<br>'.$member['tel'] .'<br>'. $member_level['name'];
                }
                $list[$k]['yuding_type'] = $vo['yuding_type']??0;
				if($mendian_upgrade_status && $vo['mdid']){
					$mendian = Db::name('mendian')->where('aid',aid)->where('id',$vo['mdid'])->find();
					$list[$k]['mdname'] = $mendian['name']??'';
					$list[$k]['mdtel'] = $mendian['tel']??'';
					$list[$k]['mdxqname'] = $mendian['xqname']??'';
				}
                $canFahuo = 0;
                if($vo['status']==1){
                    $canFahuo = 1;
                }

                $list[$k]['can_fahuo'] = $canFahuo;

                $canPay = true;$canEdit = true;

                $list[$k]['canPay']  = $canPay;
                $list[$k]['canEdit'] = $canEdit;

			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list,'totalcommission'=>$totalcommission]);
		}
		$machinelist = Db::name('wifiprint_set')->where('aid',aid)->where('status',1)->where('bid',bid)->select()->toArray();
		$hasprint = 0;
		if($machinelist){
			$hasprint = 1;
		}
		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
        $freight = Db::name('freight')->where('aid',aid)->where('bid',bid)->select()->toArray();
		
		$adminset = Db::name('admin_set')->where('aid',aid)->find();
		$shopset = Db::name('shop_sysset')->where('aid',aid)->find();

		
		if(getcustom('mendian_upgrade')){
			$mendian_upgrade_status = Db::name('admin')->where('id',aid)->value('mendian_upgrade_status');
			if($mendian_upgrade_status==1){
				 View::assign('mendian_upgrade',1);
			}
			$mendian_groups = Db::name('mendian_group')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('id desc')->column('name','id');
			View::assign('mendian_groups',$mendian_groups);
		}  
		if(input('param.showtype') ==2){
		    $business_list = Db::name('business')->where('aid',aid)->field('id,name')->select()->toArray();
            View::assign('business_list',$business_list);
        }
        View::assign('freight',$freight);
		View::assign('peisong_set',$peisong_set);
		View::assign('hasprint',$hasprint);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
		View::assign('adminset',$adminset);
		View::assign('shopset',$shopset);
		// 门店
		$mendians = Db::name('mendian')->where('aid',aid)->where('bid',bid)->column('name','id');

        View::assign('mendians',$mendians);

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
		$where[] = ['order.aid','=',aid];
		if(bid==0){
			if(input('param.bid')){
				$where[] = ['order.bid','=',input('param.bid')];
			}elseif(input('param.showtype')==2){
				$where[] = ['order.bid','<>',0];
            }elseif(input('param.showtype')=='all'){
                $where[] = ['order.bid','>=',0];
			}else{
				$where[] = ['order.bid','=',0];
			}
		}else{
			$where[] = ['order.bid','=',bid];
		}
		if($this->mdid){
			$where[] = ['order.mdid','=',$this->mdid];
		}

        if(input('param.orderid')) $where[] = ['order.id','=',input('param.orderid')];
        if(input('param.freight_id')) $where[] = ['order.freight_id','=',input('param.freight_id')];
		if(input('param.mid')) $where[] = ['order.mid','=',input('param.mid')];
		if(input('param.proname')) $where[] = ['order.proname','like','%'.input('param.proname').'%'];
		if(input('param.ordernum')) $where[] = ['order.ordernum','like','%'.input('param.ordernum').'%'];
        if(input('param.nickname')) $where[] = ['member.nickname|member.realname','like','%'.input('param.nickname').'%'];
        if(input('param.linkman')) $where[] = ['order.linkman','like','%'.input('param.linkman').'%'];
		if(input('param.tel')) $where[] = ['order.tel','like','%'.input('param.tel').'%'];

		if(input('?param.status') && input('param.status')!==''){
			if(input('param.status') == 5){
				$where[] = ['order.refund_status','=',1];
			}elseif(input('param.status') == 6){
				$where[] = ['order.refund_status','=',2];
			}elseif(input('param.status') == 7){
				$where[] = ['order.refund_status','=',3];
			}else{
				$where[] = ['order.status','=',input('param.status')];
			}
		}
		if(input('param.ctime')){
			$ctime = explode(' ~ ',input('param.ctime'));
			if(input('param.time_type') == 1){ //下单时间
				$where[] = ['order.createtime','>=',strtotime($ctime[0])];
				$where[] = ['order.createtime','<',strtotime($ctime[1])];
			}elseif(input('param.time_type') == 2){ //付款时间
				$where[] = ['order.paytime','>=',strtotime($ctime[0])];
				$where[] = ['order.paytime','<',strtotime($ctime[1])];
			}elseif(input('param.time_type') == 3){ //发货时间
				$where[] = ['order.send_time','>=',strtotime($ctime[0])];
				$where[] = ['order.send_time','<',strtotime($ctime[1])];
			}elseif(input('param.time_type') == 4){ //完成时间
				$where[] = ['order.collect_time','>=',strtotime($ctime[0])];
				$where[] = ['order.collect_time','<',strtotime($ctime[1])];
			}
		}
		if(input('param.keyword')){
			$keyword = input('param.keyword');
			$keyword_type = input('param.keyword_type');
			if($keyword_type == 1){ //订单号
				$where[] = ['order.ordernum','like','%'.$keyword.'%'];
			}elseif($keyword_type == 2){ //会员ID
				$where[] = ['order.mid','=',$keyword];
			}elseif($keyword_type == 3){ //会员信息
				$where[] = ['member.nickname|member.realname','like','%'.$keyword.'%'];
			}elseif($keyword_type == 4){ //收货信息
				$where[] = ['order.linkman|order.tel|order.area|order.address','like','%'.$keyword.'%'];
			}elseif($keyword_type == 5){ //快递单号
				$where[] = ['order.express_no','like','%'.$keyword.'%'];
			}elseif($keyword_type == 6){ //商品ID
				$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('proid',$keyword)->column('orderid');
				$where[] = ['order.id','in',$orderids];
			}elseif($keyword_type == 7){ //商品名称
				$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('name','like','%'.$keyword.'%')->column('orderid');
				$where[] = ['order.id','in',$orderids];
			}elseif($keyword_type == 8){ //商品编码
				$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('procode','like','%'.$keyword.'%')->column('orderid');
				$where[] = ['order.id','in',$orderids];
			}elseif($keyword_type == 9){ //核销员
				$orderids = Db::name('hexiao_order')->where('aid',aid)->where('type','shop')->where('remark','like','%'.$keyword.'%')->column('orderid');
				$where[] = ['order.id','in',$orderids];
			}elseif($keyword_type == 10){ //所属门店
				$mdids = Db::name('mendian')->where('aid',aid)->where('name','like','%'.$keyword.'%')->column('id');
				$where[] = ['order.mdid','in',$mdids];
			}elseif($keyword_type == 11){
				if(getcustom('shop_buy_worknum')){
	            	$where[] = ['order.worknum','like','%'.$keyword.'%'];//工号
		        }
			}elseif($keyword_type == 21){ //兑换卡号
				$where[] = ['order.duihuan_cardno','=',$keyword];
			}
		}
		
		if(input('param.fxmid')){
			$fxmid = input('param.fxmid/d');
			$where[] = Db::raw("order.id in (select orderid from ".table_name('shop_order_goods')." where parent1={$fxmid} or parent2={$fxmid} or parent3={$fxmid})");
		}

		$count = Db::name('shop_order')->alias('order')->field('order.*')->leftJoin('member member','member.id=order.mid')->where($where)->count();
		$list = Db::name('shop_order')->alias('order')->field('order.*')->leftJoin('member member','member.id=order.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
        //学校学生信息处理
        $need_school = 0;


		$bArr = Db::name('business')->where('aid',aid)->column('name','id');
		if(!$bArr) $bArr = [];
		$bArr['0'] = '自营';
        $title1 = array('订单号','openid','来源','所属商家','下单人','姓名','电话','收货地址','商品名称','商品编码','规格','数量','退款数量','总价','单价','成本','折扣金额','实付款','支付方式','配送方式','卡密','配送/提货时间','运费','积分抵扣','满额立减','优惠券优惠','状态','退款状态','退款金额','下单时间','付款时间','发货时间','完成时间','快递信息');

		$mendian_upgrade_status = 0;
		if(getcustom('mendian_upgrade')){
			$mendian_upgrade_status = Db::name('admin')->where('id',aid)->value('mendian_upgrade_status');
		}
		if($mendian_upgrade_status){
			$title1[] = '社区/'.t('门店');
		}

        $title2 = array('表单信息','商家备注','核销员','核销门店','佣金总额','一级佣金','会员信息','二级佣金','会员信息','三级佣金','会员信息');
        $title = array_merge($title1,$title2);
		$data = [];
		foreach($list as $k=>$vo){

			$status='';
			$refund_status = '';
			$refund_money = '';
			if($vo['status']==0){
				$status = '未支付';
			}elseif($vo['status']==2){
				$status = '已发货';
			}elseif($vo['status']==1){
				$status = '已支付';
			}elseif($vo['status']==3){
				$status = '已收货';
			}elseif($vo['status']==4){
				$status = '已关闭';
			}

            $allcolumn = false;

			$member = Db::name('member')->where('id',$vo['mid'])->find();
			$oglist = Db::name('shop_order_goods')->where('orderid',$vo['id'])->select()->toArray();
			//$xm=array();
			foreach($oglist as $k2=>$og){
                $ogremark = '';
                if($og['gtype']==1){
                    $ogremark = '【赠品】';
                }
				$refund_status='';
				$refund_money = '';
                // 导出的订单里退款的商品退款记录
                $ro = Db::name('shop_refund_order')->where('orderid',$vo['id'])->where('refund_status','not in',[0,3])->select()->toArray();
                foreach ($ro as $v){
                    $isrefund = Db::name('shop_refund_order_goods')->where('refund_orderid',$v['id'])->find();
                    if($isrefund['ogid'] == $og['id']){
                        switch ($v['refund_status']){
                            case 0:
                                $refund_status = '退款已取消';
                                $refund_money  = $isrefund['refund_money'];
                                break;
                            case 1:
                                $refund_status = '退款待审核';
                                $refund_money  = $isrefund['refund_money'];
                                break;
                            case 2:
                                $refund_status = '已退款';
                                $refund_money  = $isrefund['refund_money'];
                                break;
                            case 3:
                                $refund_status = '退款驳回';
                                $refund_money  = $isrefund['refund_money'];
                                break;
                            case 4:
                                $refund_status = '审核通过，待退货';
                                $refund_money  = $isrefund['refund_money'];
                                break;
                            default:
                                $refund_status = '状态未找到';
                                $refund_money  = '0';
                                break;
						
                        }
                        break;
                    }
                }
                $ogstatus='';
                if($og['status']==0){
                    $ogstatus = '未支付';
                }elseif($og['status']==2){
                    $ogstatus = '已发货';
                }elseif($og['status']==1){
                    $ogstatus = '已支付';
                }elseif($og['status']==3){
                    $ogstatus = '已收货';
                }elseif($og['status']==4){
                    $ogstatus = '已关闭';
                }
			    $barcode = '';
			    if($og['barcode'])  $barcode = "(".$og['barcode'].")";
				//$xm[] = $og['name'].$barcode."/".$og['ggname']." × ".$og['num']."";
				
				$parent1commission = $og['parent1'] ? $og['parent1commission'] : 0;
				$parent2commission = $og['parent2'] ? $og['parent2commission'] : 0;
				$parent3commission = $og['parent3'] ? $og['parent3commission'] : 0;
				$totalcommission = $parent1commission+$parent2commission+$parent3commission;
				if($og['parent1']){
					$parent1 = Db::name('member')->where('id',$og['parent1'])->find();
					$parent1str = $parent1['nickname'].'(会员ID:'.$parent1['id'].')';
				}else{
					$parent1str = '';
				}
				if($og['parent2']){
					$parent2 = Db::name('member')->where('id',$og['parent2'])->find();
					$parent2str = $parent2['nickname'].'(会员ID:'.$parent2['id'].')';
				}else{
					$parent2str = '';
				}
				if($og['parent3']){
					$parent3 = Db::name('member')->where('id',$og['parent3'])->find();
					$parent3str = $parent3['nickname'].'(会员ID:'.$parent3['id'].')';
				}else{
					$parent3str = '';
				}
				//配送自定义表单
				$vo['formdata'] = \app\model\Freight::getformdata($vo['id'],'shop_order');
				$formdataArr = [];
				$message = '';
				if($vo['formdata']) {
					foreach ($vo['formdata'] as $formdata) {
						if($formdata[2] != 'upload') {
							//if($formdata[0] == '备注') {
							//	$message = $formdata[1];
							//} else {
								$formdataArr[] = $formdata[0].':'.$formdata[1];
							//}
						}
					}
				}
				$formdatastr = implode("\r\n",$formdataArr);

				if(in_array($vo['freight_type'], [1,5]) && $vo['status'] == 3){
					$hexiao_order = Db::name('hexiao_order')->where('aid',aid)->where('orderid',$vo['id'])->where('type','shop')->find();
					if($hexiao_order){
						$hexiao_order['uname'] = Db::name('admin_user')->where('id',$hexiao_order['uid'])->value('un');
						$hexiao_order['mendian'] = Db::name('mendian')->where('id',$vo['mdid'])->value('name');
					}
				}
				$paytype = $vo['paytype'];

				if($k2 == 0 || $allcolumn){
					$tmpdata1 = [
						' '.$vo['ordernum'],
						$member[$vo['platform'].'openid'],
						getplatformname($vo['platform']),
						$bArr[$vo['bid']],
						$member['nickname'],
						$vo['linkman'],
						$vo['tel'],
						$vo['area'].' '.$vo['address'],
						$og['name'].$ogremark,
						$og['procode'],
						$og['ggname'].$barcode,
						$og['num'],
                        $og['refund_num'],
						$og['totalprice'],
						$og['sell_price'],
						$og['cost_price'],
						$vo['leveldk_money'],
						$vo['totalprice'],
						$paytype,
						$vo['freight_text'],
						' '.$vo['freight_content'],
						$vo['freight_time'],
						$vo['freight_price'],
						$vo['scoredk_money'],
						$vo['manjian_money'],
						$vo['coupon_money'],
						$status,
                        $refund_status,
                        $refund_money,
						date('Y-m-d H:i:s',$vo['createtime']),
						$vo['paytime'] ? date('Y-m-d H:i:s',$vo['paytime']) : '',
						$vo['send_time'] ? date('Y-m-d H:i:s',$vo['send_time']) : '',
						$vo['collect_time'] ? date('Y-m-d H:i:s',$vo['collect_time']) : '',
						($vo['express_com'] ? $vo['express_com'].'('.$vo['express_no'].')':''),
					];
					if($need_school){
                        $tmpdata1[] = $school_name;
                        $tmpdata1[] = $class_name;
                        $tmpdata1[] = $grade_name;
                        $tmpdata1[] = $member_content;
                    }
					if($mendian_upgrade_status){
						$mendian = Db::name('mendian')->where('aid',aid)->where('id',$vo['mdid'])->find();
						$tmpdata1[] =  $mendian['name'].$mendian['tel'].$mendian['xqname'];
					}
					$tmpdata2 = [
					    $formdatastr,
                        $vo['remark'],
                        $hexiao_order['uname'],
                        $hexiao_order['mendian'],
                        $totalcommission,
                        $parent1commission,
                        $parent1str,
                        $parent2commission,
                        $parent2str,
                        $parent3commission,
                        $parent3str,
                    ];
					$data[] = array_merge($tmpdata1,$tmpdata2);
				}else{
					$tmpdata1 = [
                        ' '.$vo['ordernum'],
						'',
						'',
						'',
						'',
                        $vo['linkman'],
                        $vo['tel'],
                        $vo['area'].' '.$vo['address'],
						$og['name'].$ogremark,
                        $og['procode'],
						$og['ggname'].$barcode,
						$og['num'],
                        $og['refund_num'],
						$og['totalprice'],
						$og['sell_price'],
						$og['cost_price'],
						'',
						'',
						'',
						'',
						'',
						'',
						'',
						'',
						'',
						'',
                        $ogstatus,//status
                        $refund_status,
                        $refund_money,
						'',
						'',
						'',
						'',
						''
                    ];
					if($need_school){
                        $tmpdata1[] = $school_name;
                        $tmpdata1[] = $class_name;
                        $tmpdata1[] = $grade_name;
                        $tmpdata1[] = $member_content;
                    }

					$tmpdata2 = [
					    '',
						'',
						'',
						'',
						$totalcommission,
						$parent1commission,
						$parent1str,
						$parent2commission,
						$parent2str,
						$parent3commission,
						$parent3str,
					];
                    $data[] = array_merge($tmpdata1,$tmpdata2);
				}
			}

		}

        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//订单详情
	public function getdetail(){
		$orderid = input('param.orderid');
		$optionType = input('param.optionType',0);
		if(bid != 0){
			$order = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		}else{
			$order = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->find();
		}
        $order['school_info'] = '';

		if($order['coupon_rid']){
			$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
			$couponnames = Db::name('coupon_record')->where('id','in',$order['coupon_rid'])->column('couponname');
			$couponnames = implode('，',$couponnames);
		}else{
			$couponrecord = false;
			$couponnames = '';
		}
        $ogwhere = [];
        $ogwhere[] = ['aid','=',aid];
        $ogwhere[] = ['orderid','=',$orderid];

		$oglist = Db::name('shop_order_goods')->where($ogwhere)->select()->toArray();
		$member = Db::name('member')->field('id,nickname,headimg,realname,tel,wxopenid,unionid')->where('id',$order['mid'])->find();
		if(!$member) $member = ['id'=>$order['mid'],'nickname'=>'','headimg'=>''];
		$comdata = array();
		$comdata['parent1'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
		$comdata['parent2'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
		$comdata['parent3'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
		$ogids = [];
		foreach($oglist as $gk=>$v){
			$ogids[] = $v['id'];
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

            if(getcustom('product_service_fee')){
                $shd_remark = Db::name('shop_product')->where('id',$v['proid'])->value('shd_remark');
                $oglist[$gk]['shd_remark'] = $shd_remark;
            }
		}
		$comdata['parent1']['money'] = round($comdata['parent1']['money'],2);
		$comdata['parent2']['money'] = round($comdata['parent2']['money'],2);
		$comdata['parent3']['money'] = round($comdata['parent3']['money'],2);

		$order['formdata'] = \app\model\Freight::getformdata($order['id'],'shop_order');
		//弃用
		if($order['field1']){
			$order['field1data'] = explode('^_^',$order['field1']);
		}
		if($order['field2']){
			$order['field2data'] = explode('^_^',$order['field2']);
		}
		if($order['field3']){
			$order['field3data'] = explode('^_^',$order['field3']);
		}
		if($order['field4']){
			$order['field4data'] = explode('^_^',$order['field4']);
		}
		if($order['field5']){
			$order['field5data'] = explode('^_^',$order['field5']);
		}
		if($order['freight_type']==11){
			$order['freight_content'] = json_decode($order['freight_content'],true);
		}
		$miandanst = Db::name('admin_set')->where('aid',aid)->value('miandanst');
		if(bid==0 && $miandanst==1 && in_array('wx',$this->platform) && ($member['wxopenid'] || $member['unionid'])){ //可以使用小程序物流助手发货
			$canmiandan = 1;
		}else{
			$canmiandan = 0;
		}

		if($order['checkmemid']){
			$checkmember = Db::name('member')->field('id,nickname,headimg,realname,tel')->where('id',$order['checkmemid'])->find();
		}else{
			$checkmember = [];
		}

        $payorder = [];
        if($order['paytypeid'] == 5) {
            $payorder = Db::name('payorder')->where('id',$order['payorderid'])->where('aid',aid)->find();
            if($payorder) {
                if($payorder['check_status'] === 0) {
                    $payorder['check_status_label'] = '待审核';
                }elseif($payorder['check_status'] == 1) {
                    $payorder['check_status_label'] = '通过';
                }elseif($payorder['check_status'] == 2) {
                    $payorder['check_status_label'] = '驳回';
                }else{
                    $payorder['check_status_label'] = '未上传';
                }
                if($payorder['paypics']) {
                    $payorder['paypics'] = explode(',', $payorder['paypics']);
                    foreach ($payorder['paypics'] as $item) {
                        $payorder['paypics_html'] .= '<img src="'.$item.'" width="200" onclick="preview(this)"/>';
                    }
                }
            }
        }
		if($order['express_content']) $order['express_content'] = json_decode($order['express_content'],true);
		if($order['status'] == 1){
			$order['express_ogids'] = implode(',',$ogids);
		}
		if($order['express_ogids']){
			$order['express_ogids'] = explode(',',$order['express_ogids']);
		}else{
			$order['express_ogids'] = [];
		}
		foreach($order['express_content'] as $k=>$v){
			if(!$v['express_ogids']){
				$v['express_ogids'] = [];
			}else{
				$v['express_ogids'] = explode(',',$v['express_ogids']);
			}
			$order['express_content'][$k] = $v;
		}

		if(getcustom('pay_money_combine')){
            if($order['combine_money'] && $order['combine_money'] > 0){
                if(!empty($order['paytype'])){
                	$order['paytype'] .= ' + '.t('余额').'支付';
                }else{
                	$order['paytype'] .= t('余额').'支付';
                }
            }
        }
        $order['canFahuo'] = 1;
        $order['canPay'] = 1;

		return json(['order'=>$order,'couponrecord'=>$couponrecord,'couponnames'=>$couponnames,'oglist'=>$oglist,'member'=>$member,'comdata'=>$comdata,'canmiandan'=>$canmiandan,'checkmember'=>$checkmember,'payorder' => $payorder]);
	}
	
	//设置备注
	public function setremark(){
		$orderid = input('post.orderid/d');
		$content = input('post.content');
		if(bid == 0){
			Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->update(['remark'=>$content]);
		}else{
			Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['remark'=>$content]);
		}
		\app\common\System::plog('商城订单设置备注'.$orderid);
		return json(['status'=>1,'msg'=>'设置完成']);
	}


	//单门店发货
	public function plfh2(){
		set_time_limit(0);
		ini_set('memory_limit', -1);

		Db::startTrans();
		$ids = input('post.ids/a');
		$express_com = '';
		$express_no = '';
		$mdid = input('post.mdid');
		
		
		if(input('post.type') == 1){
			if(bid == 0){
				$orderlist = Db::name('shop_order')->where('aid',aid)->where('mdid',$mdid)->where('id','in',$ids)->where('status',1)->select()->toArray();
			}else{
				$orderlist = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('mdid',$mdid)->where('id','in',$ids)->where('status',1)->select()->toArray();
			}
		}else{
			$where = [];
			$where[] = ['order.aid','=',aid];
			if(bid==0){
				if(input('param.bid')){
					$where[] = ['order.bid','=',input('param.bid')];
				}elseif(input('param.showtype')==2){
					$where[] = ['order.bid','<>',0];
				}elseif(input('param.showtype')=='all'){
					$where[] = ['order.bid','>=',0];
				}else{
					$where[] = ['order.bid','=',0];
				}
			}else{
				$where[] = ['order.bid','=',bid];
			}
			if($this->mdid){
				$where[] = ['order.mdid','=',$this->mdid];
				$mdid = $this->mdid;
			}else{
				if(!$mdid){
					Db::rollback();
					return json(['status'=>0,'msg'=>'请选择门店']);
				}
				$where[] = ['order.mdid','=',$mdid];
			}
			if(input('param.mid')) $where[] = ['order.mid','=',input('param.mid')];
			if(input('param.proname')) $where[] = ['order.proname','like','%'.input('param.proname').'%'];
			if(input('param.ordernum')) $where[] = ['order.ordernum','like','%'.input('param.ordernum').'%'];
			if(input('param.nickname')) $where[] = ['member.nickname|member.realname','like','%'.input('param.nickname').'%'];
			if(input('param.linkman')) $where[] = ['order.linkman','like','%'.input('param.linkman').'%'];
			if(input('param.tel')) $where[] = ['order.tel','like','%'.input('param.tel').'%'];

			if(getcustom('pay_transfer')){
				if(input('?param.transfer_check') && input('param.transfer_check')!==''){
					$where[] = ['order.paytypeid','=',5];
					$where[] = ['order.transfer_check','=',input('param.transfer_check')];
				}
			}

			if(input('?param.status') && input('param.status')!==''){
				if(input('param.status') == 5){
				$where[] = ['order.refund_status','=',1];
				}elseif(input('param.status') == 6){
					$where[] = ['order.refund_status','=',2];
				}elseif(input('param.status') == 7){
					$where[] = ['order.refund_status','=',3];
				}else{
					$where[] = ['order.status','=',input('param.status')];
				}
			}
			if(input('param.ctime')){
				$ctime = explode(' ~ ',input('param.ctime'));
				if(input('param.time_type') == 1){ //下单时间
					$where[] = ['order.createtime','>=',strtotime($ctime[0])];
					$where[] = ['order.createtime','<',strtotime($ctime[1])];
				}elseif(input('param.time_type') == 2){ //付款时间
					$where[] = ['order.paytime','>=',strtotime($ctime[0])];
					$where[] = ['order.paytime','<',strtotime($ctime[1])];
				}elseif(input('param.time_type') == 3){ //发货时间
					$where[] = ['order.send_time','>=',strtotime($ctime[0])];
					$where[] = ['order.send_time','<',strtotime($ctime[1])];
				}elseif(input('param.time_type') == 4){ //完成时间
					$where[] = ['order.collect_time','>=',strtotime($ctime[0])];
					$where[] = ['order.collect_time','<',strtotime($ctime[1])];
				}
			}
			if(input('param.keyword')){
				$keyword = input('param.keyword');
				$keyword_type = input('param.keyword_type');
				if($keyword_type == 1){ //订单号
					$where[] = ['order.ordernum','like','%'.$keyword.'%'];
				}elseif($keyword_type == 2){ //会员ID
					$where[] = ['order.mid','=',$keyword];
				}elseif($keyword_type == 3){ //会员信息
					$where[] = ['member.nickname|member.realname','like','%'.$keyword.'%'];
				}elseif($keyword_type == 4){ //收货信息
					$where[] = ['order.linkman|order.tel|order.area|order.address','like','%'.$keyword.'%'];
				}elseif($keyword_type == 5){ //快递单号
					$where[] = ['order.express_no','like','%'.$keyword.'%'];
				}elseif($keyword_type == 6){ //商品ID
					$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('proid',$keyword)->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 7){ //商品名称
					$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('name','like','%'.$keyword.'%')->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 8){ //商品编码
					$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('procode','like','%'.$keyword.'%')->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 9){ //核销员
					$orderids = Db::name('hexiao_order')->where('aid',aid)->where('type','shop')->where('remark','like','%'.$keyword.'%')->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 10){ //所属门店
					$mdids = Db::name('mendian')->where('aid',aid)->where('name','like','%'.$keyword.'%')->column('id');
					$where[] = ['order.mdid','in',$mdids];
				}elseif($keyword_type == 11){
					
				}elseif($keyword_type == 21){ //兑换卡号
					$where[] = ['order.duihuan_cardno','=',$keyword];
				}
			}
			if(input('param.fxmid')){
				$fxmid = input('param.fxmid/d');
				$where[] = Db::raw("order.id in (select orderid from ".table_name('shop_order_goods')." where parent1={$fxmid} or parent2={$fxmid} or parent3={$fxmid})");
			}
			$orderlist = Db::name('shop_order')->alias('order')->field('order.*')->leftJoin('member member','member.id=order.mid')->where($where)->where('status',1)->select()->toArray();

		}
		if(empty($orderlist)){
			Db::rollback();
			return json(['status'=>0,'msg'=>'数据有误，请重新选择']);
		}

		$countnum = count($orderlist);
		$successnum = 0;
		$errornum = 0;
		$pronum = 0;
		$proarr = [];
		$detailarr = [];
		$time = time();

		$orderlistids = array_column($orderlist,'id');
		$orderlistids = implode(",",$orderlistids);

		foreach($orderlist as $order){
			if(!$order || $order['status'] != 1 && $order['status'] != 2){
				$errornum++;
				continue;
			}
			$orderid = $order['id'];
			$refundOrder = Db::name('shop_refund_order')->where('refund_status','in',[1,4])->where('aid',aid)->where('orderid',$orderid)->count();
	        if($refundOrder){
	        	Db::rollback();
	            return json(['status'=>0,'msg'=>'请先处理完进行中的退款单']);
	        }

			$updata = [];
			// if($order['express_content'] && !empty($order['express_content'])){
			// 	$express_content = json_decode($order['express_content'],true);
			// 	if($express_content){
			// 		$add = true;
			// 		foreach($express_content as $ev){
			// 			if($ev['express_com'] == $express_com && $ev['express_no'] == $express_no){
			// 				$add = false;
			// 			}
			// 		}
			// 		if(!$add){
			// 			continue;
			// 		}
			// 	}
			// }else{
			// 	$express_content = [];
			// 	$updata['express_com'] = $express_com;
			// 	$updata['express_no']  = $express_no;
			// }
			// $express_content[] = ['express_com'=>$express_com,'express_no'=>$express_no];
			// $updata['express_content'] = json_encode($express_content);
			$updata['send_time'] = time();
			$updata['status']    = 2;
			$up = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update($updata);
			if(!$up){
				continue;
			}
			// 明细
			$detail = [];
			$detail['orderid'] = $order['id'];
			$detail['aid'] = aid;
			$detail['bid'] = bid;
			$detail['mid'] = $order['mid'];
			$detail['totalprice'] = $order['totalprice'];
			$detail['tel'] =  substr($order['tel'],0,3).'****'.substr($order['tel'],-4);
			$detail['linkman'] =  $order['linkman'];
			$detail['remark'] = $order['remark'];
			$detail['createtime'] = $time;
			$detaillist = [];
			$order_goods_num = 0;
			// 统计商品
			$shop_order_goods = Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->where('status',1)->select()->toArray();

			foreach ($shop_order_goods as $key => $value) {

				if($value['refund_num']>0){
					if($value['num'] > $value['refund_num']){
						$value['num'] -= $value['refund_num'];
					}else{
						continue;
					}
				}

				// 总
				if(isset($proarr[$value['proid']][$value['ggid']])){
					$proarr[$value['proid']][$value['ggid']]['num'] += $value['num'];
				}else{
					$proarr[$value['proid']][$value['ggid']]['proid'] = $value['proid'];
					$proarr[$value['proid']][$value['ggid']]['ggid'] = $value['ggid'];
					$proarr[$value['proid']][$value['ggid']]['name'] = $value['name'];
					$proarr[$value['proid']][$value['ggid']]['ggname'] = $value['ggname'];
					$proarr[$value['proid']][$value['ggid']]['num'] = $value['num'];
				}
				$goods_detail = [];
				$goods_detail['ordernum'] = $value['ordernum'];
				$goods_detail['proid'] = $value['proid'];
				$goods_detail['pic'] = $value['pic'];
				$goods_detail['name'] = $value['name'];
				$goods_detail['ggid'] = $value['ggid'];
				$goods_detail['ggname'] = $value['ggname'];
				$goods_detail['num'] = $value['num'];
				$goods_detail['totalprice'] = $value['totalprice'];
				$goods_detail['cid'] = $value['cid'];
				$order_goods_num += $value['num'];
				
				$detaillist[] = $goods_detail;

				$pronum += $value['num'];
			}
			$detail['num'] = $order_goods_num;
			if(empty($detaillist)){
				continue;
			}
			$detail['detaillist'] = $detaillist;
			$detailarr[] = $detail;

			Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->where('status',1)->update(['status'=>2]);

            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\common\Order::wxShipping(aid,$order,'shop',['express_com'=>$express_com,'express_no'=>$express_no]);
            }
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
			$successnum++;
		}

		// 生成新的发货单
		$data = [];
		$data['aid'] = aid;
		$data['bid'] = bid;
		$data['mdid'] = $mdid;
		$data['orderids'] = $orderlistids;
		$data['number'] = $pronum;
		$data['info'] = json_encode($proarr);
		$data['detail'] = json_encode($detailarr);
		$data['createtime'] = time();
		Db::name('mendian_orders')->insert($data);

		Db::commit();
		\app\common\System::plog('门店订单批量发货');
		return json(['status'=>1,'msg'=>'共操作 '.$countnum.' 条数据，成功发货 '.$successnum.' 条，失败 '.$errornum.' 条']);
	}

	//选择全部发货
	public function fahuoadd(){
		$mendians = Db::name('mendian')->where('aid',aid)->where('bid',bid)->column('name','id');
        View::assign('mendians',$mendians);
        return View::fetch();
	}
	//选中项门店批量发货
	public function plfh(){

		set_time_limit(0);
		ini_set('memory_limit', -1);
		
		$ids = input('post.ids/a');
		$express_com = input('post.express_com');
		$express_no = input('post.express_no');

		$where = [];
		$where[] = ['aid','=',aid];
		if(bid != 0){
			$where[] = ['bid','=',bid];
		}
		$where[] = ['status','=',1];
		$where[] = ['freight_type','in',[1,5]];

		if(input('param.ctime')){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['createtime','>=',strtotime($ctime[0])];
			$where[] = ['createtime','<',strtotime($ctime[1])];
		}
		$mdids = input('post.mdids');
		// 默认全部门店
		if(empty($mdids)){
			if(bid == 0){
				$mdids = Db::name('mendian')->where('aid',aid)->column('id');
			}else{
				$mdids = Db::name('mendian')->where('aid',aid)->where('bid',bid)->column('id');
			}
			
		}
		if(!is_array($mdids)){
			$mdids = explode(',', $mdids);
		}
		// dump(input());

		Db::startTrans();

		$time = time();
		$countnum_tottal = 0;
		$successnum_tottal = 0;
		$errornum_tottal = 0;

		foreach ($mdids as $mdid) {

			// dump($where);
			$orderlist = Db::name('shop_order')->where($where)->where('mdid',$mdid)->select()->toArray();
			// dump($orderlist);

			if(!empty($orderlist)){
				$countnum = count($orderlist);
				$countnum_tottal += $countnum;
				$successnum = 0;
				$errornum = 0;
				$pronum = 0;
				$proarr = [];
				$detailarr = [];
				

				$orderlistids = array_column($orderlist,'id');
				$orderlistids = implode(",",$orderlistids);

				foreach($orderlist as $order){

					if(!$order || $order['status'] != 1 && $order['status'] != 2){
						$errornum++;
						continue;
					}
					$orderid = $order['id'];
					$refundOrder = Db::name('shop_refund_order')->where('refund_status','in',[1,4])->where('aid',aid)->where('orderid',$orderid)->count();
			        if($refundOrder){
			        	Db::rollback();
			            return json(['status'=>0,'msg'=>'请先处理完进行中的退款单']);
			        }

					$updata = [];

					$updata['send_time'] = time();
					$updata['status']    = 2;
					$up = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->update($updata);
					if(!$up){
						$errornum++;
						continue;
					}
					// 明细
					$detail = [];
					$detail['orderid'] = $order['id'];
					$detail['aid'] = aid;
					$detail['bid'] = bid;
					$detail['mid'] = $order['mid'];
					$detail['totalprice'] = $order['totalprice'];
					$detail['tel'] =  substr($order['tel'],0,3).'****'.substr($order['tel'],-4);
					$detail['linkman'] =  $order['linkman'];
					$detail['remark'] = $order['remark'];
					$detail['createtime'] = $time;
					$detaillist = [];
					$order_goods_num = 0;
					// 统计商品
					$shop_order_goods = Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->where('status',1)->select()->toArray();
					if(empty($shop_order_goods)){
						continue;
					}

					foreach ($shop_order_goods as $key => $value) {

						if($value['refund_num']>0){
							if($value['num'] > $value['refund_num']){
								$value['num'] -= $value['refund_num'];
							}else{
								continue;
							}
						}

						// 总
						if(isset($proarr[$value['proid']][$value['ggid']])){
							$proarr[$value['proid']][$value['ggid']]['num'] += $value['num'];
						}else{
							$proarr[$value['proid']][$value['ggid']]['proid'] = $value['proid'];
							$proarr[$value['proid']][$value['ggid']]['ggid'] = $value['ggid'];
							$proarr[$value['proid']][$value['ggid']]['name'] = $value['name'];
							$proarr[$value['proid']][$value['ggid']]['ggname'] = $value['ggname'];
							$proarr[$value['proid']][$value['ggid']]['num'] = $value['num'];
						}
						$goods_detail = [];
						$goods_detail['ordernum'] = $value['ordernum'];
						$goods_detail['proid'] = $value['proid'];
						$goods_detail['pic'] = $value['pic'];
						$goods_detail['name'] = $value['name'];
						$goods_detail['ggid'] = $value['ggid'];
						$goods_detail['ggname'] = $value['ggname'];
						$goods_detail['num'] = $value['num'];
						$goods_detail['totalprice'] = $value['totalprice'];
						$goods_detail['cid'] = $value['cid'];
						$order_goods_num += $value['num'];
						
						$detaillist[] = $goods_detail;

						$pronum += $value['num'];

					}
					$detail['num'] = $order_goods_num;
					if(empty($detaillist)){
						$errornum++;
						continue;
					}
					$detail['detaillist'] = $detaillist;
					$detailarr[] = $detail;

					Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->where('status',1)->update(['status'=>2]);

		            //发货信息录入 微信小程序+微信支付
		            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
		                \app\common\Order::wxShipping(aid,$order,'shop',['express_com'=>$express_com,'express_no'=>$express_no]);
		            }
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
					$successnum++;
					
				}

				$successnum_tottal += $successnum;
				$errornum_tottal += $errornum;
				if($pronum <=0 ){
					continue;
				}

				// 生成新的发货单
				$data = [];
				$data['aid'] = aid;
				$data['bid'] = bid;
				$data['mdid'] = $mdid;
				$data['orderids'] = $orderlistids;
				$data['number'] = $pronum;
				$data['info'] = json_encode($proarr);
				$data['detail'] = json_encode($detailarr);
				$data['createtime'] = time();
				Db::name('mendian_orders')->insert($data);
			}
		}
		// die;
		Db::commit();
		\app\common\System::plog('门店订单批量发货');
		return json(['status'=>1,'msg'=>'共操作 '.$countnum_tottal.' 条数据，成功发货 '.$successnum_tottal.' 条，失败 '.$errornum_tottal.' 条']);
	}
    
    //拣货单
    public function list(){
        // 门店
        $mendians = Db::name('mendian')->where('aid',aid)->where('bid',bid)->column('name','id');
        View::assign('mendians',$mendians);
        $mendian_groups = Db::name('mendian_group')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('id desc')->column('name','id');
        View::assign('mendian_groups',$mendian_groups);
		if(request()->isAjax()){

			$page = input('param.page')?:1;
			$limit = input('param.limit')?:20;
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'o.id desc';
			}
			$where = [];
			$where[] = ['o.aid','=',aid];

			// if(input('?param.status') && input('param.status')!=='') $where[] = ['o.status','=',input('param.status')];
            if(input('param.ctime')){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['o.createtime','>=',strtotime($ctime[0])];
                $where[] = ['o.createtime','<',strtotime($ctime[1])];
            }
            //门店分组
            if(input('param.mdgid')){
                $mdids = [];
                $mdids2 = Db::name('mendian')->where('groupid',input('param.mdgid'))->where('aid',aid)->where('bid',bid)->column('id');
                if(!empty($mdids2)){
                    $mdids = $mdids2;
                }
                //门店id
                if(input('param.mdid')) $mdids[] = input('param.mdid');
                $mdids = array_unique($mdids);
                if(!empty($mdids)){
                    $where[] = ['o.mdid','in',$mdids];
                }else{
                    $where[] = ['o.id','=',0];
                }
            }else{
                if(input('param.mdid')) $where[] = ['o.mdid','=',input('param.mdid')];
            }
			$count = 0 + Db::name('mendian_orders')->alias('o')->join('mendian m','m.id=o.mdid' )->where($where)->count();
			$data = Db::name('mendian_orders')->alias('o')
				->join('mendian m','m.id=o.mdid' )
				->where($where)
				->field('o.*,m.name,m.tel,m.area,m.address,m.pic,m.mid as mdmid')
				->page($page,$limit)->order($order)->select()->toArray();
            if($data){
                foreach($data as $k=>$vo){
                    $data[$k]['pic'] = Db::name('member')->where('aid',aid)->where('id',$vo['mdmid'])->value('headimg');
                }
            }

			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }

	//核销并确认收货
    function orderHexiao(){
        $post = input('post.');
        $orderid = intval($post['orderid']);
		if(bid == 0){
			$order = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->find();
		}else{
			$order = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		}
        if(!$order || !in_array($order['status'], [1,2,8]) || !in_array($order['freight_type'], [1,5])){
            return json(['status'=>0,'msg'=>'订单状态不符合核销收货要求']);
        }

        $refundOrder = Db::name('shop_refund_order')->where('refund_status','in',[1,4])->where('aid',aid)->where('orderid',$orderid)->count();
        if($refundOrder){
            return json(['status'=>0,'msg'=>'有正在进行的退款，无法核销']);
        }
        try {
            Db::startTrans();

            $data = array();
            $data['aid'] = aid;
            $data['bid'] = $order['bid'];
            $data['uid'] = $this->uid;
            $data['mid'] = $order['mid'];
            $data['orderid'] = $order['id'];
            $data['ordernum'] = $order['ordernum'];
            $data['title'] = $order['title'];
            $data['type'] = 'shop';
            $data['createtime'] = time();
            $data['remark'] = '核销员['.$this->user['un'].']核销';
            $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
            Db::name('hexiao_order')->insert($data);

            $rs = \app\common\Order::collect($order, 'shop', $this->user['mid']);
            if($rs['status']==0) return $rs;
            Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
            Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->update(['status'=>3,'endtime'=>time()]);
            if(getcustom('ciruikang_fenxiao')){
                //一次购买升级
                \app\common\Member::uplv(aid,$order['mid'],'shop',['onebuy'=>1,'onebuy_orderid'=>$order['id']]);
            }else{
            	\app\common\Member::uplv(aid,$order['mid']);
            }

            if(getcustom('member_shougou_parentreward')){
                //首购解锁
                Db::name('member_commission_record')->where('orderid',$order['id'])->where('type','shop')->where('status',0)->where('islock',1)->where('aid',$order['aid'])->where('remark','like','%首购奖励')->update(['islock'=>0]);
            }

            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\common\Order::wxShipping(aid,$order);
            }
            Db::commit();
            if(getcustom('shop_zthx_backmoney')){
	            if(in_array($order['freight_type'], [1,5])){
	            	$prolist = Db::name('shop_order_goods')->where(['orderid'=>$order['id']])->field('id,market_price,num')->select()->toArray();
	                $levelid = Db::name('member')->where('id',$order['mid'])->value('levelid');
	                if(!empty($prolist) && !empty($levelid) && $levelid>0){
	                    //自提核销返现
	                    $zthx_backmoney = Db::name('member_level')->where('id',$levelid)->where('aid',aid)->value('zthx_backmoney');
	                    if($zthx_backmoney && $zthx_backmoney>0){
	                        $back_money = 0;
                            foreach($prolist as $pv){
                                $back_money += $pv['market_price']*$pv['num'];
                            }
                            unset($pv);
	                        $back_money = $back_money*$zthx_backmoney/100;
	                        $back_money = round($back_money,2);
	                        if($back_money>0){
	                            \app\common\Member::addmoney(aid,$order['mid'],$back_money,'自提核销返回，订单号: '.$order['ordernum']);
	                        }
	                    }
	                }
	            }
	        }
	        // 分销商开启门店核销后，对应核销的商品金额自动换算成对应积分赠送到核销管理员
            if(getcustom('mendian_hexiao_money_to_score')){
                \app\common\Order::mendian_hexiao_money_to_score(aid,$this->user['mid'],$order['totalprice']);
            }
	        if(getcustom('mendian_hexiao_givemoney') && $order['mdid']){
                $mendian = Db::name('mendian')->where('aid',aid)->where('id',$order['mdid'])->find();
                if($mendian){
                    $givemoney = 0;
                    $oglist = Db::name('shop_order_goods')->where(['aid'=>aid,'orderid'=>$order['id']])->select()->toArray();
                    if($oglist){
                        if(getcustom('product_mendian_hexiao_givemoney')){
                            foreach ($oglist as $og){
                                $pro = Db::name('shop_product')->where('aid',aid)->where('id',$og['proid'])->find();
                                $hexiao_set = Db::name('shop_product_mendian_hexiaoset')->where('aid',aid)->where('mdid',$order['mdid'])->where('proid',$og['proid'])->find();
                                if($hexiao_set['hexiaogivepercent']>0 || $hexiao_set['hexiaogivemoney']>0){
                                    $givemoney += $hexiao_set['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $hexiao_set['hexiaogivemoney'];
                                }
                                elseif(!is_null($pro['hexiaogivepercent']) || !is_null($pro['hexiaogivemoney'])){

                                    $givemoney += $pro['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $pro['hexiaogivemoney'];
                                }else{
                                    $givemoney += $mendian['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $mendian['hexiaogivemoney'];
                                }
                            }
                        }else{
                            foreach ($oglist as $og){
                                $pro = Db::name('shop_product')->where('aid',aid)->where('id',$og['proid'])->find();
                                if(!is_null($pro['hexiaogivepercent']) || !is_null($pro['hexiaogivemoney'])){
                                    $givemoney += $pro['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $pro['hexiaogivemoney']*$og['num'];
                                }else{
                                    $givemoney += $mendian['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $mendian['hexiaogivemoney'];
                                }
                            }
                        }
                    }
                    if($givemoney > 0){

                        // 分润
                        if(getcustom('commission_mendian_hexiao_coupon') && !empty($mendian['fenrun'])){
                            $fenrun = json_decode($mendian['fenrun'],true);
                            $givemoney_old = $givemoney;
        
                            // {"bili":["10","10","20","20","10"],"mids":["3980,3996,4000","3980,3995","","3993,3995,4001","3992,3999"]}
                            $data_bonus = [];
                            foreach ($fenrun['bili'] as $key => $bili) {
                                if($bili > 0 && !empty($fenrun['mids'][$key])){

                                    $send_commission_total = dd_money_format($bili*$givemoney_old/100,2);

                                    $givemoney -= $send_commission_total;
                                    $mids = $fenrun['mids'][$key];
                                    $mids = explode(',', $mids);
                                    $mnum = count($mids);
                                    $send_commission = dd_money_format($send_commission_total/$mnum,2);
                
                                    if($send_commission > 0){
                                        foreach ($mids as $k => $mid) {
                                            $data_shop_bonus = ['aid'=>aid,'bid'=>$mendian['bid'],'mid'=>$mid,'frommid'=>$order['mid'],'orderid'=>$order['id'],'totalcommission'=>$send_commission_total,'commission'=>$send_commission,'bili'=>$bili,'createtime'=>time()];
                                            $data_bonus[] = $data_shop_bonus;

                                        }
                                    }
                                }
                            }
                            if(!empty($data_bonus)){
                                Db::name('mendian_coupon_commission_log')->insertAll($data_bonus);
                            }
                        }
                        if($givemoney > 0){
                            \app\common\Mendian::addmoney(aid,$mendian['id'],$givemoney,'核销订单'.$order['ordernum']);
                        }
                    }
                    if(getcustom('business_platform_auth')){
                        if($mendian['bid']>0 && $order['bid']!=$mendian['bid']){
                            $business = Db::name('business')->where('aid',aid)->where('id',$mendian['bid'])->find();
                            if($business['isplatform_auth']==1){
                                \app\common\Business::addmoney(aid,$mendian['bid'],$givemoney,$mendian['name'].'核销平台商品 订单号：'.$order['ordernum']);
                            }
                        }
                    }
                }
            }
            \app\common\System::plog('商城订单核销确认收货'.$orderid);
            if(getcustom('hexiao_auto_wifiprint')){
                \app\common\Wifiprint::print($order['aid'],'shop',$order['id'],0,-1,$order['bid'],'shop',-1,['opttype' => 'hexiao']);
            }
            return json(['status'=>1,'msg'=>'核销成功']);
        } catch (\Exception $e) {
            Log::write([
                'file' => __FILE__ . ' L' . __LINE__,
                'function' => __FUNCTION__,
                'error' => $e->getMessage(),
            ]);
            Db::rollback();
            return json(['status'=>0,'msg'=>'系统繁忙','error'=>$e->getMessage()]);
        }
    }

	//删除
	public function del(){
		if(input('post.id')){
			$ids = [input('post.id/d')];
		}else{
			$ids = input('post.ids/a');
		}
		foreach($ids as $id){
			if(bid == 0){
				Db::name('shop_order')->where('aid',aid)->where('id',$id)->delete();
				Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$id)->delete();

				Db::name('shop_refund_order')->where('aid',aid)->where('orderid',$id)->delete();
				Db::name('shop_refund_order_goods')->where('aid',aid)->where('orderid',$id)->delete();
			}else{
				Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
				Db::name('shop_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$id)->delete();

				Db::name('shop_refund_order')->where('aid',aid)->where('bid',bid)->where('orderid',$id)->delete();
				Db::name('shop_refund_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$id)->delete();
			}
			Db::name('invoice')->where('aid',aid)->where('bid',bid)->where('order_type','shop')->where('orderid',$id)->delete();
			\app\common\System::plog('商城订单删除'.$id);
		}
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//删除
	public function delorder(){
		$id = input('post.id/d');
		Db::name('mendian_orders')
				->where('aid',aid)
				->where('id',$id)
				->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//送货单
	public function shd(){
		$orderid = input('param.id/d');

		$info = Db::name('mendian_orders')->alias('o')
				->join('mendian m','m.id=o.mdid' )
				->where('o.aid',aid)->where('o.id',$orderid)
				->field('o.*,m.name,m.tel,m.area,m.address,m.pic')
				->find();
		if(!$info || (bid !=0 && $info['bid'] != bid)) showmsg('订单不存在');

		$infos = json_decode($info['info'],true);
		$info['info'] = $infos;
		$lists = [];
		foreach ($infos as $key => $value) {
			foreach ($value as $k => $v) {
				$lists[] = $v;
			}
		}

		$info['lists'] = $lists;
		$info['detail'] = json_decode($info['detail'],true);

		// dump($info);die;
		View::assign('info',$info);
		return View::fetch();
	}

	//送货单
	public function allshd(){
		$orderids = input('param.ids');

		$where = [];
		$where[] = ['o.aid','=',aid];
		if($orderids == 'all'){
			if(bid == 0){
				$mdids = Db::name('mendian')->where('aid',aid)->column('id');
			}else{
				$mdids = Db::name('mendian')->where('aid',aid)->where('bid',bid)->column('id');
			}
			$where[] = ['o.mdid','in',$mdids];
			$where[] = ['o.status','=',0];
		}else{
			if(!is_array($orderids)){
				$orderids = explode(',', $orderids);
			}
			$where[] = ['o.id','in',$orderids];
		}

		$infos = Db::name('mendian_orders')->alias('o')
				->join('mendian m','m.id=o.mdid' )
				->where($where)
				->field('o.*,m.name,m.tel,m.area,m.address,m.pic')
				->order('o.id desc')
				->select()->toArray();

		foreach ($infos as &$info) {

			if(!$info || (bid !=0 && $info['bid'] != bid)) continue;

			$content = json_decode($info['info'],true);
			$info['info'] = $content;
			$lists = [];
			foreach ($content as $key => $value) {
				foreach ($value as $k => $v) {
					$lists[] = $v;
				}
			}

			$info['lists'] = $lists;
			$info['detail'] = json_decode($info['detail'],true);
		}

		// dump($infos);die;
		View::assign('infos',$infos);
		return View::fetch();
	}


	// 收货
	public function receive(){
		$ids = input('param.ids');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['status','=',0];
		if($ids == 'all'){
			if(bid == 0){
				$mdids = Db::name('mendian')->where('aid',aid)->column('id');
			}else{
				$mdids = Db::name('mendian')->where('aid',aid)->where('bid',bid)->column('id');
			}
			$where[] = ['mdid','in',$mdids];
		}else{
			if(!is_array($ids)){
				$ids = explode(',', $ids);
			}
			$where[] = ['id','in',$ids];
		}

		$infos = Db::name('mendian_orders')->where($where)->select()->toArray();

		if(!empty($infos)){
			Db::startTrans();
			foreach ($infos as $info) {

				Db::name('mendian_orders')->where('aid',aid)->where('id',$info['id'])->update(['status'=>1]);

				if($info['orderids']){
					$orderids = explode(',', $info['orderids']);
					$where = [];
					$where[] = ['aid','=',aid];
					$where[] = ['mdid','=',$info['mdid']];
					$where[] = ['id','in',$orderids];
					$where[] = ['freight_type','in',[1,5]];
					$where[] = ['status','=',2];
					Db::name('shop_order')->where($where)->update(['status'=>8]);

					$where = [];
					$where[] = ['aid','=',aid];
					$where[] = ['orderid','in',$orderids];
					$where[] = ['status','=',2];
					Db::name('shop_order_goods')->where($where)->update(['status'=>8]);
				}
			}
			Db::commit();
		}
		return json(['status'=>1,'msg'=>'收货成功']);

	}

}
