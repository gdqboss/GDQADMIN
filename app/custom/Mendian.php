<?php
// JK客户定制
 //custom_file(mendian_upgrade)
namespace app\custom;
use think\facade\Db;
class Mendian
{
	  public static function mendian_hexiao_ticheng($order='',$mendian=''){
			$aid = $order['aid'];
			if($mendian){
				$givemoney = 0;
				$oglist = Db::name('shop_order_goods')->where(['aid'=>$aid,'orderid'=>$order['id']])->select()->toArray();
				$level = Db::name('mendian_level')->where('aid',aid)->where('id',$mendian['levelid'])->find();
				if($oglist){
					 foreach ($oglist as $og){
						$pro = Db::name('shop_product')->where('aid',$aid)->where('id',$og['proid'])->find();
						if($pro['mendian_hexiao_set']==0){
                            //commissiontype=0百分比， commissiontype=1 固定金额
							if($level['commissiontype']==1){
                                //commissiontype1_cal_type 固定金额提成计算方式：order_num按单量，goods_num按购买数量
                                if($level['commissiontype1_cal_type'] == 'goods_num'){
                                    $givemoney += $level['commission'] * $og['num'];
                                }else{
                                    $givemoney += $level['commission'];
                                }
							}else{
								$givemoney += $level['commission']* $og['real_totalprice'] * 0.01;
							}
						}elseif($pro['mendian_hexiao_set']==1){
						   $mendian_hexiao_data = json_decode($pro['mendianhexiaodata1'],true);
							if($mendian_hexiao_data){
								$givemoney += $mendian_hexiao_data[$mendian['levelid']]['ticheng'] * $og['real_totalprice'] * 0.01;
							}
						}elseif($pro['mendian_hexiao_set']==2){
							$mendian_hexiao_data2 = json_decode($pro['mendianhexiaodata2'],true);
                            //按单量order_num：此商品提成=订单数量*提成金额；按购买数量goods_num：此商品提成=商品购买数量*提成金额
                            if($pro['mendianhexiaodata2_cal_type'] == 'goods_num'){
                                $givemoney += $mendian_hexiao_data2[$mendian['levelid']]['ticheng'] * $og['num'];
                            }else{
                                //默认order_num计算
                                $givemoney += $mendian_hexiao_data2[$mendian['levelid']]['ticheng'];
                            }
						}elseif($pro['mendian_hexiao_set']==-1){
							$givemoney+=0;
						}
					 }
				}
				if($givemoney > 0){
					self::addcommission($aid,$mendian['id'],$givemoney,'核销订单'.$order['ordernum']);
				}
				//var_dump($givemoney);
				$mendianset = Db::name('mendian_sysset')->where('aid',aid)->find();
				if($mendian['pid']>0 && $mendianset['can_agent']>0){
					if($mendianset['can_agent']>=1){
						$pmendian = Db::name('mendian')->where('mid',$mendian['pid'])->where('aid',aid)->where('status',1)->find();
						if($pmendian){
							if($mendianset['commissiontype']==1){
								$parent1givemoney = $mendianset['commission1'];
							}else{
								$parent1givemoney = $givemoney * $mendianset['commission1']*0.01;
							}
							self::addcommission($aid,$pmendian['id'],$parent1givemoney,'推荐团长提成'.$order['ordernum']);
						}
					}
					if($mendianset['can_agent']>=2){
						$pmendian2 = Db::name('mendian')->where('mid',$pmendian['pid'])->where('aid',aid)->where('status',1)->find();
						if($pmendian2){
							if($mendianset['commissiontype']==1){
								$parent2givemoney = $mendianset['commission2'];
							}else{
								$parent2givemoney = $givemoney * $mendianset['commission2']*0.01;
							}
							self::addcommission($aid,$pmendian2['id'],$parent2givemoney,'推荐团长提成'.$order['ordernum']);
						}
					}

					if($mendianset['can_agent']>=3){
						$pmendian3 = Db::name('mendian')->where('mid',$pmendian2['pid'])->where('aid',aid)->where('status',1)->find();
						if($pmendian3){
							if($mendianset['commissiontype']==1){
								$parent3givemoney = $mendianset['commission3'];
							}else{
								$parent3givemoney = $givemoney * $mendianset['commission3']*0.01;
							}
							self::addcommission($aid,$pmendian3['id'],$parent3givemoney,'推荐团长提成'.$order['ordernum']);
						}
					}
				}	
			}
	  }

	//加佣金
	public static function addcommission($aid,$mdid,$money,$remark){
		if($money==0) return ;
		$mendian = Db::name('mendian')->where('aid',$aid)->where('id',$mdid)->find();
		if(!$mendian) return ['status'=>0,'msg'=>'门店不存在'];
		Db::name('mendian')->where('aid',$aid)->where('id',$mdid)->inc('money',$money)->update();
		Db::name('mendian')->where('aid',$aid)->where('id',$mdid)->inc('totalmoney',$money)->update();
		
		$data = [];
		$data['aid']    = $aid;
		$data['bid']    = $mendian['bid'];
		$data['mdid']   = $mdid;
		$data['money']  = $money;
		$data['after']  = $mendian['money'] + $money;
		$data['createtime'] = time();
		$data['remark'] = $remark;
		Db::name('mendian_moneylog')->insert($data);
		return ['status'=>1,'msg'=>''];
	}

	public function uplv($aid,$mendian){
		if($mendian['levelid'])
            $nowlv = Db::name('mendian_level')->where('aid',$aid)->where('id',$mendian['levelid'])->find();

		$lvlist = Db::name('mendian_level')->where('aid',$aid)->where('can_up',1)->where('sort','>',$nowlv['sort'])->order('sort,id')->select();
		$ordermoney = 0 + Db::name('shop_order')->where('aid',$aid)->where('status','3')->where('mdid','=',$mendian['id'])->sum('totalprice');
	    $newlv = $nowlv;
        foreach($lvlist as $lv){
            $isup = false;
		    if($lv['up_ordermoney']>0 && $lv['up_ordermoney'] <= $ordermoney){
				$isup=true;
		    }
			if($isup) $newlv = $lv;
		}
		if($newlv && $newlv['id'] != $mendian['levelid']) {
			Db::name('mendian')->where('aid', $aid)->where('id', $mendian['id'])->update(['levelid' => $newlv['id']]);
			 //升级记录
            $order = [
                'aid' => $aid,
                'mdid' => $mendian['id'],
                'levelid' => $newlv['id'] ,
                'title' => '自动升级',
                'totalprice' => 0,
                'createtime' => time(),
                'levelup_time' => time(),
                'beforelevelid' => $mendian['levelid'],
                'form0' => '类型^_^自动升级',
                'platform' => platform,
                'status' => 2,
            ];
            Db::name('mendian_levelup_order')->insert($order);
		}
	}


	  public static function createCommission($order=''){
			$aid = $order['aid'];
			$orderid = $order['id'];
			$mendian =  Db::name('mendian')->where('id',$order['mdid'])->find();
			if($mendian){
				$givemoney = 0;
				$oglist = Db::name('shop_order_goods')->where(['aid'=>$aid,'orderid'=>$order['id']])->select()->toArray();
				$level = Db::name('mendian_level')->where('aid',aid)->where('id',$mendian['levelid'])->find();
				if($oglist){
					 foreach ($oglist as $og){
						$pro = Db::name('shop_product')->where('aid',$aid)->where('id',$og['proid'])->find();
						if($pro['mendian_hexiao_set']==0){
                            //commissiontype=0百分比， commissiontype=1 固定金额
                            if($level['commissiontype']==1){
                                //commissiontype1_cal_type 固定金额提成计算方式：order_num按单量，goods_num按购买数量
                                if($level['commissiontype1_cal_type'] == 'goods_num'){
                                    $givemoney += $level['commission'] * $og['num'];
                                }else{
                                    $givemoney += $level['commission'];
                                }
							}else{
								$givemoney += $level['commission']* $og['real_totalprice'] * 0.01;
							}
						}elseif($pro['mendian_hexiao_set']==1){
						   $mendian_hexiao_data = json_decode($pro['mendianhexiaodata1'],true);
							if($mendian_hexiao_data){
								$givemoney += $mendian_hexiao_data[$mendian['levelid']]['ticheng'] * $og['real_totalprice'] * 0.01;
							}
						}elseif($pro['mendian_hexiao_set']==2){
							$mendian_hexiao_data2 = json_decode($pro['mendianhexiaodata2'],true);
                            //按单量order_num：此商品提成=订单数量*提成金额；按购买数量goods_num：此商品提成=商品购买数量*提成金额
                            if($pro['mendianhexiaodata2_cal_type'] == 'goods_num'){
                                $givemoney += $mendian_hexiao_data2[$mendian['levelid']]['ticheng'] * $og['num'];
                            }else{
                                //默认order_num计算
                                $givemoney += $mendian_hexiao_data2[$mendian['levelid']]['ticheng'];
                            }
						}elseif($pro['mendian_hexiao_set']==-1){
							$givemoney+=0;
						}
					 }
				}
				Db::name('mendian_commission_record')->insert(['aid'=>$aid,'mid'=>$mendian['mid'],'frommid'=>0,'orderid'=>$orderid,'commission'=>$givemoney,'remark'=>t('门店').'核销提成','createtime'=>time(),'mdid'=>$mendian['id']]);
				//var_dump($givemoney);
				$mendianset = Db::name('mendian_sysset')->where('aid',aid)->find();
				if($mendian['pid']>0 && $mendianset['can_agent']>0){
					if($mendianset['can_agent']>=1){
						$pmendian = Db::name('mendian')->where('mid',$mendian['pid'])->where('aid',aid)->where('status',1)->find();
						if($pmendian){
							if($mendianset['commissiontype']==1){
								$parent1givemoney = $mendianset['commission1'];
							}else{
								$parent1givemoney = $givemoney * $mendianset['commission1']*0.01;
							}
							Db::name('mendian_commission_record')->insert(['aid'=>$aid,'mid'=>$pmendian['mid'],'frommid'=>$order['mid'],'orderid'=>$orderid,'commission'=>$parent1givemoney,'remark'=>'推荐'.$mendian['name'].'核销提成','createtime'=>time(),'mdid'=>$pmendian['id']]);
							//self::addcommission($aid,$pmendian['id'],$parent1givemoney,'推荐团长提成'.$order['ordernum']);
						}
					}
					if($mendianset['can_agent']>=2){
						$pmendian2 = Db::name('mendian')->where('mid',$pmendian['pid'])->where('aid',aid)->where('status',1)->find();
						if($pmendian2){
							if($mendianset['commissiontype']==1){
								$parent2givemoney = $mendianset['commission2'];
							}else{
								$parent2givemoney = $givemoney * $mendianset['commission2']*0.01;
							}
							//self::addcommission($aid,$pmendian2['id'],$parent2givemoney,'推荐团长提成'.$order['ordernum']);
							Db::name('mendian_commission_record')->insert(['aid'=>$aid,'mid'=>$pmendian2['mid'],'frommid'=>$order['mid'],'orderid'=>$orderid,'commission'=>$parent2givemoney,'remark'=>'推荐'.$mendian['name'].'核销提成','createtime'=>time(),'mdid'=>$pmendian2['id']]);
						}
					}

					if($mendianset['can_agent']>=3){
						$pmendian3 = Db::name('mendian')->where('mid',$pmendian2['pid'])->where('aid',aid)->where('status',1)->find();
						if($pmendian3){
							if($mendianset['commissiontype']==1){
								$parent3givemoney = $mendianset['commission3'];
							}else{
								$parent3givemoney = $givemoney * $mendianset['commission3']*0.01;
							}
							//self::addcommission($aid,$pmendian3['id'],$parent3givemoney,'推荐团长提成'.$order['ordernum']);
							Db::name('mendian_commission_record')->insert(['aid'=>$aid,'mid'=>$pmendian3['mid'],'frommid'=>$order['mid'],'orderid'=>$orderid,'commission'=>$parent3givemoney,'remark'=>'推荐'.$mendian['name'].'核销提成','createtime'=>time(),'mdid'=>$pmendian3['id']]);
						}
					}
				}	
			}
	  }
	  public function givecommission($order){
			$aid = $order['aid'];
			$commission_record_list = Db::name('mendian_commission_record')->where('aid',$aid)->where('orderid',$order['id'])->where('status',0)->select();
			foreach($commission_record_list as $commission_record){
				Db::name('mendian_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
				if($commission_record['commission'] > 0){
					$commission = $commission_record['commission'];
					$mendian = Db::name('mendian')->where('mid',$commission_record['mid'])->where('aid',aid)->find();
					self::addcommission($aid,$mendian['id'],$commission,$commission_record['remark']);
				}
			}
	  }
	   public function updatecommission($order){
			$aid = $order['aid'];
			$commission_record_list = Db::name('mendian_commission_record')->where('aid',$aid)->where('orderid',$order['id'])->where('status',0)->select();
			foreach($commission_record_list as $commission_record){
				Db::name('mendian_commission_record')->where('id',$commission_record['id'])->update(['status'=>-1,'endtime'=>time()]);
			}
	  }
}