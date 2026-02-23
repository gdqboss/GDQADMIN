<?php
// JK客户定制

//custom_file(order_add_mobile)
// +----------------------------------------------------------------------
// | 管理员中心 - 订单录入
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\Db;
class ApiAdminOrderlr extends ApiAdmin
{
	public function createOrder(){
		$data = input('post.');
		$prodata = explode('-',$data['prodata']);		
		if(!$data['mid'] && $data['add_member']){
			$tel = $data['tel'];
			$member = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
			if(!$member){
				$reg_data = [];
				$reg_data['aid'] = aid;
				$reg_data['tel'] = $tel;
				$reg_data['nickname'] = substr($tel,0,3).'****'.substr($tel,-4);
				$reg_data['sex'] = 3;
				$reg_data['headimg'] = PRE_URL.'/static/img/touxiang.png';
				$reg_data['createtime'] = time();
				$reg_data['last_visittime'] = time();
				$reg_data['platform'] = platform;
				$mid = \app\model\Member::add(aid,$reg_data);
			}else{
				$data['mid'] = $member['id'];
			}
			
			$data['mid'] = $mid;
		}
		$member = Db::name('member')->where('aid',aid)->where('id',$data['mid'])->find();
		if(!$member){
			$member = [];
			$data['mid'] = 0;
			return $this->json(['status'=>0,'msg'=>'未找到该'.t('会员')]);
		}
		$prolist = [];
		$pstype = 0;
		$givescore = 0; //奖励积分 确认收货后赠送
		$givescore2 = 0; //奖励积分2 付款后赠送
		foreach($prodata as $key=>$pro){
			$sdata = explode(',',$pro);
			$product = Db::name('shop_product')->where('aid',aid)->where('id',$sdata[0])->find();
			if(!$product) return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
			$guige = Db::name('shop_guige')->where('aid',aid)->where('id',$sdata[1])->find();
			if(!$guige) return $this->json(['status'=>0,'msg'=>'产品规格不存在或已下架']);
			if($guige['stock'] < $sdata[2]){
				return $this->json(['status'=>0,'msg'=>'库存不足']);
			}
            if($product['lvprice']==1 && $member) {
                $lvprice_data = json_decode($guige['lvprice_data'],true);
                if($lvprice_data)
                    $guige['sell_price'] = $lvprice_data[$member['levelid']];
            }

			if(isset($sdata[3])){
				$guige['sell_price'] = $sdata[3];
			}
			if($key==0) $title = $product['name'];
            $prolist[] = ['product'=>$product,'guige'=>$guige,'num'=>$sdata[2]];

			if($product['freighttype']==3){
				$pstype = 3;
			}elseif($product['freighttype']==4){
				$pstype = 4;
			}
			if($product['givescore_time'] == 0){
				$givescore += $guige['givescore'] * $sdata[2];
			}else{
				$givescore2 += $guige['givescore'] * $sdata[2];
			}
		}
		if(($pstype == 3 || $pstype == 4) && count($prolist) > 1){
			return $this->json(['status'=>0,'msg'=>($pstype==3 ? '自动发货' : '在线卡密').'商品需要单独录入']);
		}
		$sysset = Db::name('admin_set')->where('aid',aid)->find();

		$ordernum = \app\common\Common::generateOrderNo(aid);
		//不存在地址，追加新地址
		$data['address'] =str_replace($data['province'],'',$data['address']);
		$data['address'] =str_replace($data['city'],'',$data['address']);
		$data['address'] =str_replace($data['district'],'',$data['address']);
		$this->saveaddress($data);
		if($data['freight_id']){
			$freight = Db::name('freight')->where('aid',aid)->where('id',$data['freight_id'])->find();
			if($freight['pstype']==11){
				$freight['type11key'] = $data['type11key'];
			}
			
			if(getcustom('mendian_upgrade')){
				$admin = Db::name('admin')->field('mendian_upgrade_status')->where('id',aid)->find();
				if($admin['mendian_upgrade_status']==1){
					$mendianset =  Db::name('mendian_sysset')->field('fwdistance')->where('aid',aid)->find();

					if($mendianset['fwdistance']>0){
						$mendian = Db::name('mendian')->where('id',$data['storeid'])->find();
					}
				}
			}
		}
		$freight_price = 0;
		

		$orderdata = [];
		$orderdata['aid'] = aid;
		$orderdata['mid'] = $data['mid'];
		$orderdata['bid'] = bid;
		$orderdata['ordernum'] = $ordernum;
		$orderdata['title'] = $title.(count($prodata)>1?'等':'');
		
		$orderdata['linkman'] = $data['linkman'];
		$orderdata['tel'] = $data['tel'];
		$orderdata['area'] = $data['province'].$data['city'].$data['district'];
		$orderdata['area2'] = $data['province']?$data['province'].','.$data['city'].','.$data['district']:'';
		$orderdata['address'] = $data['address'];
		$orderdata['totalprice'] = $data['totalprice'];
		$orderdata['product_price'] = $data['goodsprice'];
		$orderdata['leveldk_money'] = 0;  //会员折扣
		$orderdata['scoredk_money'] = 0;	//积分抵扣
		$orderdata['scoredkscore'] = 0;	//抵扣掉的积分
		$orderdata['freight_price'] = $data['freightprice']; //运费
		$orderdata['message'] = '';
		// $orderdata['freight_text'] = $data['freight'];
		// $orderdata['freight_id'] = 0;
		// $orderdata['freight_type'] = $pstype;
		$orderdata['freight_id'] = $data['freight_id']??0;
		if($freight && ($freight['pstype']==0 || $freight['pstype']==10)){ //快递
			$orderdata['freight_text'] = $freight['name'].'('.$freight_price.'元)';
			$orderdata['freight_type'] = $freight['pstype'];
		}elseif($freight && $freight['pstype']==1){ //到店自提
			$orderdata['mdid'] = $data['storeid'];
			$mendian = Db::name('mendian')->where('aid',aid)->where('id',$data['storeid'])->find();
			$orderdata['freight_text'] = $freight['name'].'['.$mendian['name'].']';
			$orderdata['area2'] = $mendian['area'];
			$orderdata['freight_type'] = 1;
		}elseif($freight && $freight['pstype']==5){ //门店配送
			$orderdata['mdid'] = $data['storeid'];
			$mendian = Db::name('mendian')->where('aid',aid)->where('id',$data['storeid'])->find();
			$orderdata['freight_text'] = $freight['name'].'['.$mendian['name'].']';
			$orderdata['area2'] = $mendian['area'];
			$orderdata['freight_type'] = 5;
		}elseif($freight && $freight['pstype']==2){ //同城配送
			if(false){}else{
				$orderdata['freight_text'] = $freight['name'].'('.$freight_price.'元)';
			}
			$orderdata['freight_type'] = 2;
		}elseif($freight && $freight['pstype']==12){ //app配送
			$orderdata['freight_text'] = $freight['name'].'('.$freight_price.'元)';
			$orderdata['freight_type'] = 2;
		}elseif($freight && ($freight['pstype']==3 || $freight['pstype']==4)){ //自动发货 在线卡密
			$orderdata['freight_text'] = $freight['name'];
			$orderdata['freight_type'] = $freight['pstype'];
		}elseif($freight && $freight['pstype']==11){ //选择物流配送
			$type11pricedata = json_decode($freight['type11pricedata'],true);
			$orderdata['freight_text'] = $type11pricedata[$freight['type11key']]['name'].'('.$freight_price.'元)';
			$orderdata['freight_type'] = $freight['pstype'];
			$orderdata['freight_content'] = jsonEncode($type11pricedata[$freight['type11key']]);
		}else{
			$orderdata['freight_text'] = '包邮';
		}


		$orderdata['platform'] = 'admin';
		$orderdata['hexiao_code'] = random(16);
		$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=shop&co='.$orderdata['hexiao_code']));
		$orderdata['status'] = 1;
		$orderdata['paytype'] = $data['paytype'];

		$orderdata['createtime'] = time();
		$orderdata['paytime'] = time();
		
		//会员支付1==paycheck
		if($data['paycheck'] == 1){
			$orderdata['status'] = 0;
			$orderdata['paytime'] = '';
		}

			
		$remark = '手机端录入';			
		$remark .= $data['remark'];
		
		$orderdata['remark'] = $remark;
		$orderdata['givescore'] = $givescore;
		$orderdata['givescore2'] = $givescore2;
		$orderid = Db::name('shop_order')->insertGetId($orderdata);
		
		if($orderdata['status'] == 0){
			$payorderid = \app\model\Payorder::createorder(aid,0,$orderdata['mid'],'shop',$orderid,$ordernum,$orderdata['title'],$orderdata['totalprice']);
		}
		

		$istc = 0; //设置了按单固定提成时 只将佣金计算到第一个商品里
        $istc1 = 0;
        $istc2 = 0;
        $istc3 = 0;
		foreach($prolist as $key=>$v){
			$product = $v['product'];
			$guige = $v['guige'];
			$num = $v['num'];
			if($product['product_type'] == 4){
				$guigedata = json_decode($product['guigedata'],true);
				$gg_name_arr = explode(',',$guige['name']);
				foreach($guigedata as $pk=>$pg){
					foreach($pg['items'] as $pgt){
						if(isset($pgt['ggpic_wholesale']) && !empty($pgt['ggpic_wholesale'])){							
							if(in_array($pgt['title'],$gg_name_arr)){
								$guige['pic'] = $pgt['ggpic_wholesale'];
							}
						}							
					}
				}
			}
			$ogdata = [];
			$ogdata['aid'] = aid;
			$ogdata['bid'] = $product['bid'];
			$ogdata['mid'] = $data['mid'];
			$ogdata['orderid'] = $orderid;
			$ogdata['ordernum'] = $orderdata['ordernum'];
			$ogdata['proid'] = $product['id'];
			$ogdata['name'] = $product['name'];
			$ogdata['pic'] = $guige['pic']?$guige['pic']:$product['pic'];
			$ogdata['procode'] = $product['procode'];
            $ogdata['barcode'] = $product['barcode'];
			$ogdata['ggid'] = $guige['id'];
			$ogdata['ggname'] = $guige['name'];
			$ogdata['cid'] = $product['cid'];
			$ogdata['num'] = $num;
			$ogdata['cost_price'] = $guige['cost_price'];
			$ogdata['sell_price'] = $guige['sell_price'];
			$ogdata['totalprice'] = $num * $guige['sell_price'];
			$ogdata['status'] = 1;
			$ogdata['createtime'] = time();
            if($product['fenhongset'] == 0){ //不参与分红
                $ogdata['isfenhong'] = 2;
            }
            
			$agleveldata = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
			if($istc!=1){
				$og_totalprice = $ogdata['totalprice'];
				$leveldk_money = 0;
				$coupon_money = 0;
				$scoredk_money = 0;
				$manjian_money = $orderdata['product_price'] + $orderdata['freight_price'] - $orderdata['totalprice'];

				//计算商品实际金额  商品金额 - 会员折扣 - 积分抵扣 - 满减抵扣 - 优惠券抵扣
				if($sysset['fxjiesuantype'] == 1 || $sysset['fxjiesuantype'] == 2){
					$allproduct_price = $orderdata['product_price'];
					$og_leveldk_money = 0;
					$og_coupon_money = 0;
					$og_scoredk_money = 0;
					$og_manjian_money = 0;
					if($allproduct_price > 0 && $og_totalprice > 0){
						if($leveldk_money){
							$og_leveldk_money = $og_totalprice / $allproduct_price * $leveldk_money;
						}
						if($coupon_money){
							$og_coupon_money = $og_totalprice / $allproduct_price * $coupon_money;
						}
						if($scoredk_money){
							$og_scoredk_money = $og_totalprice / $allproduct_price * $scoredk_money;
						}
						if($manjian_money){
							$og_manjian_money = $og_totalprice / $allproduct_price * $manjian_money;
						}
					}
					$og_totalprice = round($og_totalprice - $og_coupon_money - $og_scoredk_money - $og_manjian_money,2);
					if($og_totalprice < 0) $og_totalprice = 0;
				}
				$ogdata['real_totalprice'] = $og_totalprice; //实际商品销售金额
				
				//计算佣金的商品金额
				$commission_totalprice = $ogdata['totalprice']; 
				if($sysset['fxjiesuantype'] == 1){
					$commission_totalprice = $ogdata['real_totalprice'];
				}
				if($sysset['fxjiesuantype']==2){ //按利润提成
					$commission_totalprice = $ogdata['real_totalprice'] - $guige['cost_price'] * $num;
				}
                if($commission_totalprice < 0) $commission_totalprice = 0;
                $commission_totalpriceCache = $commission_totalprice;

				$agleveldata = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
				if($agleveldata['can_agent'] > 0 && $agleveldata['commission1own']==1){
					$member['pid'] = $member['id'];
				}
				if($product['commissionset']!=-1){
                    if(!getcustom('fenxiao_manage')){
                        $sysset['fenxiao_manage_status'] = 0;
                    }
                    if($sysset['fenxiao_manage_status']){
                        $commission_data = \app\common\Fenxiao::fenxiao_jicha($sysset,$member,$product,$num,$commission_totalprice);
                    }else{
                        $commission_data = \app\common\Fenxiao::fenxiao($sysset,$member,$product,$num,$commission_totalprice,0,$istc1,$istc2,$istc3);
                    }
                    $ogdata['parent1'] = $commission_data['parent1']??0;
                    $ogdata['parent2'] = $commission_data['parent2']??0;
                    $ogdata['parent3'] = $commission_data['parent3']??0;
                    $ogdata['parent4'] = $commission_data['parent4']??0;
                    $ogdata['parent1commission'] = $commission_data['parent1commission']??0;
                    $ogdata['parent2commission'] = $commission_data['parent2commission']??0;
                    $ogdata['parent3commission'] = $commission_data['parent3commission']??0;
                    $ogdata['parent4commission'] = $commission_data['parent4commission']??0;
                    $ogdata['parent1score'] = $commission_data['parent1score']??0;
                    $ogdata['parent2score'] = $commission_data['parent2score']??0;
                    $ogdata['parent3score'] = $commission_data['parent3score']??0;
                    $istc1 = $commission_data['istc1']??0;
                    $istc2 = $commission_data['istc2']??0;
                    $istc3 = $commission_data['istc3']??0;
				}
			}
			$ogid = Db::name('shop_order_goods')->insertGetId($ogdata);
            if(getcustom('member_product_price')){
                if($guige['is_member_product'] ==1){
                    $buylog = [
                        'aid' => aid,
                        'mid' => $member['id'],
                        'ordernum' => $orderdata['ordernum'],
                        'type' =>'admin',
                        'proid' => $ogdata['proid'],
                        'ggid' => $ogdata['ggid'],
                        'orderid' => $orderid,
                        'sell_price' => $ogdata['sell_price'],
                        'num' => $ogdata['num'],
                        'createtime' => time()
                    ];
                    Db::name('member_product_buylog')->insert($buylog);
                }
            }

            $totalcommission = 0;
			if($ogdata['parent1'] && ($ogdata['parent1commission'] > 0 || $ogdata['parent1score'] > 0)){
				Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent1'],'frommid'=>$member['id'],'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogdata['parent1commission'],'score'=>$ogdata['parent1score'],'remark'=>'下级购买商品奖励','createtime'=>time()]);
                $totalcommission += $ogdata['parent1commission'];
			}
			if($ogdata['parent2'] && ($ogdata['parent2commission'] || $ogdata['parent2score'])){
				Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent2'],'frommid'=>$member['id'],'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogdata['parent2commission'],'score'=>$ogdata['parent2score'],'remark'=>'下二级购买商品奖励','createtime'=>time()]);
                $totalcommission += $ogdata['parent2commission'];
			}
			if($ogdata['parent3'] && ($ogdata['parent3commission'] || $ogdata['parent3score'])){
				Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent3'],'frommid'=>$member['id'],'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogdata['parent3commission'],'score'=>$ogdata['parent3score'],'remark'=>'下三级购买商品奖励','createtime'=>time()]);
                $totalcommission += $ogdata['parent3commission'];
			}
			
			if($product['commissionset4']==1 && $product['lvprice']==1){ //极差分销
                if($member['path']){
					$parentList = Db::name('member')->where('id','in',$member['path'])->order(Db::raw('field(id,'.$member['path'].')'))->select()->toArray();
					if($parentList){
						$parentList = array_reverse($parentList);
						$lvprice_data = json_decode($guige['lvprice_data'],true);
						$nowprice = $commission_totalpriceCache;
						$giveidx = 0;
						foreach($parentList as $k=>$parent){
							if($parent['levelid'] && $lvprice_data[$parent['levelid']]){
								$thisprice = floatval($lvprice_data[$parent['levelid']]) * $num;
								if($nowprice > $thisprice){
									$commission = $nowprice - $thisprice;
									$nowprice = $thisprice;
									$giveidx++;
									//if($giveidx <=3){
									//	$ogupdate['parent'.$giveidx] = $parent['id'];
									//	$ogupdate['parent'.$giveidx.'commission'] = $commission;
									//}
									Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$parent['id'],'frommid'=>$member['id'],'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$commission,'score'=>0,'remark'=>'下级购买商品差价','createtime'=>time()]);
								}
							}
						}
					}
				}
			}
			Db::name('shop_guige')->where('aid',aid)->where('id',$guige['id'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
			Db::name('shop_product')->where('aid',aid)->where('id',$product['id'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
				
		}
		if($orderdata['status'] == 1){
			\app\model\Payorder::shop_pay($orderid);
		}		
		Db::name('shop_cartlr')->where('aid',aid)->where('mid',$data['mid'])->delete();
		\app\common\System::plog('商城手机端订单录入'.$orderid);
		$url = '/pagesExt/order/detail?id='.$orderid;
		return $this->json(['status'=>1,'msg'=>'录单成功','url'=>$url]);
	}
	//未存在的地址新增保存
	public function saveaddress($post){
		$check = Db::name('member_address')->where('aid',aid)->where('mid',$post['mid'])->where(['name'=>$post['linkman'],'tel'=>$post['tel'],'province'=>$post['province'],'city'=>$post['city'],'district'=>$post['district']])->find();
		if(!$check){
			$data = array();
			$data['aid'] = aid;
			$data['mid'] = $post['mid'];
			$data['name'] = $post['linkman'];
			$data['tel'] = $post['tel'];
			$data['address'] = $post['address'];
			$data['createtime'] = time();
			$data['province'] = $post['province'];
			$data['city'] = $post['city'];
			$data['district'] = $post['district'];
			$data['area'] = $data['province'].$data['city'].$data['district'];
			$addressid = Db::name('member_address')->insertGetId($data);
		}
		return true;
		
	}
	public function address(){
		$mid = input('param.mid');
		if(!$mid){
			return $this->json(['status'=>0,'msg'=>'请选择会员','data'=>[]]);
		}		
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',$mid];
		if(input('param.keyword')){
			$where[] = ['name|tel','like','%'.input('param.keyword').'%'];
		}
		$datalist = Db::name('member_address')->where($where)->order('isdefault desc,id desc')->select()->toArray();
		return $this->json(['status'=>1,'data'=>$datalist]);
	}

	public function cart(){
		//$this->checklogin();
		$mid = input('param.mid');
		if(getcustom('shop_add_stock_mobile')){
            $mid = $mid?$mid:mid;
        }
		$address = input('param.address');
		$gwcdata = [];
		$field = 'id,bid,proid,ggid,num';
        if(input('param.bid')){
			$cartlist = Db::name('shop_cartlr')->field($field)->where('aid',aid)->where('bid',input('param.bid'))->where('mid',$mid)->order('createtime desc')->select()->toArray();
		}else{
			$cartlist = Db::name('shop_cartlr')->field($field)->where('aid',aid)->where('mid',$mid)->order('createtime desc')->select()->toArray();
		}
        $glassProductNum = 0;
		if(!$cartlist) $cartlist = [];

			$prolist = [];
			$total = 0;
			$totalprice = 0;
			$totalweight = 0;
			foreach($cartlist as $gwc){
				$product = Db::name('shop_product')->where('aid',aid)->where('status','<>',0)->where('id',$gwc['proid'])->find();
				if(!$product){
					Db::name('shop_cartlr')->where('aid',aid)->where('mid',$mid)->where('proid',$gwc['proid'])->delete();continue;
				}
				$guige = Db::name('shop_guige')->where('id',$gwc['ggid'])->find();
				if(!$guige){
					Db::name('shop_cartlr')->where('aid',aid)->where('mid',$mid)->where('ggid',$gwc['ggid'])->delete();continue;
				}
                $guige = $this->formatguige($guige,$product['bid'],$product['lvprice']);
				// if($product['perlimitdan'] > 0 && $gwc['num'] > $product['perlimitdan']){
				// 	$gwc['num'] = $product['perlimitdan'];
				// 	Db::name('shop_cartlr')->where('aid',aid)->where('id',$gwc['id'])->update(['num'=>$gwc['num']]);
				// }
				if($gwc['sell_price']){
					$guige['sell_price'] = $gwc['sell_price'];
				}
				if(getcustom('product_wholesale') && $product['product_type'] == 4){
					$jieti_num = $gwc['num'];
					$jieti_discount_type = $product['jieti_discount_type'];
					if($jieti_discount_type == 0){
						$jieti_num = Db::name('shop_cartlr')->where('mid',$mid)->where('proid',$product['id'])->sum('num');
					}				
					$guige = $this->formatguigewholesale($guige,$product,$jieti_num);
				}
                $tmpitem = ['id'=>$gwc['id'],'checked'=>true,'product'=>$product,'guige'=>$guige,'num'=>$gwc['num']];
				$prolist[] = $tmpitem;
				$total += $gwc['num'];
				$totalprice += $guige['sell_price'] * $gwc['num'];
				$totalweight += $guige['weight'] * $gwc['num'];
			}
		$totalprice = round($totalprice,2);	
		$freightList = \app\model\Freight::getList([['status','=',1],['aid','=',aid],['bid','=',bid]]);
		$rs = \app\model\Freight::formatFreightList($freightList,$address,$totalprice,$total,$totalweight);
		$freightList = $rs['freightList'];
		$rdata = [];
		$rdata['cartlist'] = array_values($prolist);
		$rdata['cart'] = ['total'=>$total,'totalprice'=>$totalprice];
		$rdata['freightList'] = $freightList;
        $rdata['hasglassproduct'] = $glassProductNum>0?1:0;
		return $this->json($rdata);
	}
	public function formatguigewholesale($guige, $product,$num){
		if(!$this->member) return $guige;
		if(empty($product['jieti_discount_data'])) return $guige;
		$jieti_discount_data = json_decode($product['jieti_discount_data'],true);
		$sell_price = $guige['sell_price'];
		foreach($jieti_discount_data as $k=>$v){			
			if($num>=$v['start_num'] && $v['ratio']>0){
				$guige['sell_price'] = round($sell_price*$v['ratio']*0.01,2);
			}			
		}
		return $guige;
	}

	public function addcart(){
		$post = input('post.');
		$oldnum = 0;
		$num = intval($post['num']);
		$mid = input('param.mid');
        if(getcustom('shop_add_stock_mobile')){
            $mid = $mid?$mid:mid;
        }
		if(!$mid){
			return $this->json(['status'=>0,'msg'=>'先选择会员id']);
		}
		
		$product = Db::name('shop_product')->where('aid',aid)->where('status','<>',0)->where('ischecked',1)->where('id',$post['proid'])->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
		if($product['freighttype']==3 || $product['freighttype']==4) return $this->json(['status'=>0,'msg'=>'虚拟商品不能加入购物车']);
		if(!$post['ggid']){
			if($num > 0){
				$post['ggid'] = Db::name('shop_guige')->where('proid',$post['proid'])->value('id');
			}else{
				$post['ggid'] = Db::name('shop_cartlr')->where('aid',aid)->where('mid',$mid)->where('proid',$post['proid'])->order('id desc')->value('ggid');
			}
		}

		$gwc = Db::name('shop_cartlr')->where('aid',aid)->where('mid',$mid)->where('proid',$post['proid'])->where('ggid',$post['ggid'])->find();
		if($gwc) $oldnum = $gwc['num'];
		if($num > 0 && $product['limit_start'] > 0 && $oldnum + $num < $product['limit_start']){
			$num = $product['limit_start'];
		}
		if($num > 0 && $product['perlimitdan'] > 0 && $oldnum + $num > $product['perlimitdan']){
			return $this->json(['status'=>0,'msg'=>'每单限购'.$product['perlimitdan'].'件']);
			$num = $product['perlimitdan'];
		}
		if($num == 0){
			Db::name('shop_cartlr')->where('aid',aid)->where('mid',$mid)->where('proid',$post['proid'])->where('ggid',$post['ggid'])->delete();
		}
		if($gwc){
		    $updata['createtime'] = time();
		    $updata['sell_price'] = $post['sell_price'];
			Db::name('shop_cartlr')->where('aid',aid)->where('mid',$mid)->where('proid',$post['proid'])->where('ggid',$post['ggid'])->inc('num',$num)->update($updata);
		}else{
			$data = [];
			$data['aid'] = aid;
			$data['bid'] = $product['bid'];
			$data['mid'] = $mid;
			$data['ggid'] = $post['ggid'];
			$data['createtime'] = time();
			$data['proid'] = $post['proid'];
			$data['sell_price'] = $post['sell_price'];
			$data['num'] = $num;
            Db::name('shop_cartlr')->insert($data);
		}
		$cartnum = Db::name('shop_cartlr')->where('aid',aid)->where('mid',$mid)->sum('num');
		return $this->json(['status'=>1,'msg'=>'加入成功','cartnum'=>$cartnum]);
	}
	public function cartChangenum(){
		$post = input('post.');
		$id = input('post.id/d');
        $num = intval($post['num']);
		$mid = input('param.mid');
		if(!$mid){
			return $this->json(['status'=>0,'msg'=>'先选择会员id']);
		}
		
		$product = Db::name('shop_product')->where('aid',aid)->where('status','<>',0)->where('ischecked',1)->where('id',$post['proid'])->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
		if($product['freighttype']==3 || $product['freighttype']==4) return $this->json(['status'=>0,'msg'=>'虚拟商品不能加入购物车']);
		if(!$post['ggid']){
			if($num > 0){
				$post['ggid'] = Db::name('shop_guige')->where('proid',$post['proid'])->value('id');
			}else{
				$post['ggid'] = Db::name('shop_cartlr')->where('aid',aid)->where('mid',$mid)->where('proid',$post['proid'])->order('id desc')->value('ggid');
			}
		}
        // if(!$type){
    	// 	$cart = Db::name('shop_cartlr')->where('aid',aid)->where('id',$id)->where('mid',mid)->find();
        //     $product = Db::name('shop_product')->where('aid',aid)->where('id',$cart['proid'])->find();
        //     $guige = Db::name('shop_guige')->where('aid',aid)->where('id',$cart['ggid'])->find();
        //     if($guige['limit_start'] > 0 && $num < $guige['limit_start']){
        //         return $this->json(['status'=>0,'msg'=>'该商品规格'.$guige['limit_start'].'件起售']);
        //     }
        //     if($product['limit_start'] > 0 && $num < $product['limit_start']){
        //         return $this->json(['status'=>0,'msg'=>'该商品'.$product['limit_start'].'件起售']);
        //     }
        // }
		$gwc = Db::name('shop_cartlr')->where('aid',aid)->where('mid',$mid)->where('proid',$post['proid'])->where('ggid',$post['ggid'])->find();
		if($num == 0){
			Db::name('shop_cartlr')->where('aid',aid)->where('mid',$mid)->where('proid',$post['proid'])->where('ggid',$post['ggid'])->delete();
		}
		if($gwc){
		    $updata['createtime'] = time();
		    $updata['sell_price'] = $post['sell_price'];
		    $updata['num'] = $num;
			Db::name('shop_cartlr')->where('aid',aid)->where('mid',$mid)->where('proid',$post['proid'])->where('ggid',$post['ggid'])->inc('num',$num)->update($updata);
		}else{
			$data = [];
			$data['aid'] = aid;
			$data['bid'] = $product['bid'];
			$data['mid'] = $mid;
			$data['ggid'] = $post['ggid'];
			$data['createtime'] = time();
			$data['proid'] = $post['proid'];
			$data['sell_price'] = $post['sell_price'];
			$data['num'] = $num;
			Db::name('shop_cartlr')->insert($data);
		}
		$cartnum = Db::name('shop_cartlr')->where('aid',aid)->where('mid',$mid)->sum('num');
		return $this->json(['status'=>1,'msg'=>'修改成功','cartnum'=>$cartnum]);
		// Db::name('shop_cartlr')->where('id',$id)->where('mid',mid)->update(['num'=>$num]);
		// return $this->json(['status'=>1,'msg'=>'修改成功']);
	}
	public function cartdelete(){
		$this->checklogin();
		$cartid = input('post.cartid/d');
		$mid = input('param.mid');
        if(getcustom('shop_add_stock_mobile')){
            $mid = $mid?$mid:mid;
        }
		if(!$cartid){
			Db::name('shop_cartlr')->where('aid',aid)->where('mid',$mid)->delete();
			return $this->json(['status'=>1,'msg'=>'删除成功']);
		}

		Db::name('shop_cartlr')->where('id',$cartid)->where('mid',$mid)->delete();
		return $this->json(['status'=>1,'msg'=>'删除成功']);
	}
	public function getpaytype(){
		$data = [
			'线下支付'=>'线下支付',
			'月结账户'=>'赊账月结',
			'微信支付'=>'微信支付',
			'支付宝支付'=>'支付宝支付',
			'货到付款'=>'货到付款',
			'信用额度支付'=>t('信用额度')
		];
		return $this->json(['status'=>1,'msg'=>'获取成功','datalist'=>$data]);
	}
	
	public function shopsotckSave(){
	    if(getcustom('shop_add_stock_mobile')){
	        $prodata = input('param.prodata');
            $prolist = [];
            foreach($prodata as $key=>$pro){
                $product = Db::name('shop_product')->where('aid',aid)->where('id',$pro['proid'])->find();
                if(!$product) continue;
                $guige = Db::name('shop_guige')->where('aid',aid)->where('id',$pro['ggid'])->find();
                if(!$guige) continue;
                $prolist[$key] = ['product'=>$product,'guige'=>$guige,'num'=>$pro['buynum'],'totalprice'=>$pro['buytotal']];
                //进货费用$sdata[3]
            }
            $ordernum = date('YmdHis').rand(1,99);
            $user = Db::name('admin_user')->where('id',$this->uid)->find();
            foreach($prolist as $key=>$v){
                $product = $v['product'];
                $guige = $v['guige'];
                $num = $v['num'];
                $ogdata = [];
                $ogdata['aid'] = aid;
                $ogdata['bid'] = $product['bid'];
                $ogdata['ordernum'] = $ordernum;
                $ogdata['proid'] = $product['id'];
                $ogdata['ggid'] = $guige['id'];
                $ogdata['stock'] = $num;
                $ogdata['afterstock'] = $guige['stock'] + $num;
                $ogdata['uid'] = $this->uid;
                $ogdata['uname'] = $user['un'];
                $ogdata['createtime'] = time();

                $ogid = Db::name('shop_stock_order_goods')->insertGetId($ogdata);
                
                Db::name('shop_guige')->where('aid',aid)->where('id',$guige['id'])->update(['stock'=>Db::raw("stock+$num")]);
                Db::name('shop_product')->where('aid',aid)->where('id',$product['id'])->update(['stock'=>Db::raw("stock+$num")]);
            }
            Db::name('shop_cartlr')->where('aid',aid)->where('mid',mid)->delete();
            \app\common\System::plog('商城录入库存'.$ogid);
            return json(['status'=>1,'msg'=>'库存录入成功','url'=>true]);
        }
    }
}