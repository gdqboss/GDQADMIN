<?php
// JK客户定制

namespace app\common;
use think\facade\Db;
class Menu
{
	//获取菜单数据
	public static function getdata($aid=0,$uid=0,$ismenu=false){
		$user = [];
		if($aid == 0){
			$platform = ['mp','wx','alipay','baidu','toutiao','qq','h5','app'];
		}else{
            $admin = Db::name('admin')->where('id',$aid)->find();
			$platform = explode(',',$admin['platform']);
		}
		if($uid > 0){
		$user = Db::name('admin_user')->where('id',$uid)->find();
		if($user['bid'] > 0){
			// Check if user themselves is an admin
			$isadmin = $user['isadmin'] > 0;
			if($user['auth_type'] == 1){
				$adminUser = Db::name('admin_user')->where('aid',$aid)->where('isadmin','>',0)->find();
				if($adminUser) {
					$user = $adminUser;
					$isadmin = true;
				}
			}
		}else{
			$isadmin = true;
		}
	}else{
		if($uid == -1){
			$isadmin = false;
			$user = Db::name('admin_user')->where('aid',$aid)->where('isadmin','>',0)->find();
			if($user) {
				$isadmin = true;
			}
		}else{
			$isadmin = true;
		}
	}
		$menudata = [];

		$shop_child = [];
		$shop_child[] = ['name'=>'商品管理','path'=>'ShopProduct/index','authdata'=>'ShopProduct/*,ShopCode/*'];
        if(getcustom('product_bonus_pool')){
            $shop_child[] = ['name'=>'奖金池','path'=>'ShopProduct/product_bonus_pool','authdata'=>'ShopProduct/product_bonus_pool','hide'=>true];
        }
        if(getcustom('product_batch_changeprice')){
            $shop_child[] = ['name'=>'批量改价','path'=>'product_batch_changeprice','authdata'=>'product_batch_changeprice','hide'=>true];
        }
        if($isadmin) {
            }
		$shop_child[] = ['name'=>'订单管理','path'=>'ShopOrder/index','authdata'=>'ShopOrder/*'];
        if(getcustom('mendian_upgrade') && bid == 0){
            if($admin['mendian_upgrade_status']==1 || $aid === 0){
                $shop_child[] = ['name'=>t('门店').'发货','path'=>'MendianOrder/index','authdata'=>'MendianOrder/*'];
                $shop_child[] = ['name'=>t('门店').'拣货单','path'=>'MendianOrder/list','authdata'=>'MendianOrder/*'];
            }
        }
        //        $shop_child[] = ['name'=>'订单管理','path'=>'ShopOrder/index','authdata'=>'ShopOrder/*'];
        $shop_child[] = ['name'=>'退款申请','path'=>'ShopRefundOrder/index','authdata'=>'ShopRefundOrder/*'];
        $shop_child[] = ['name'=>'评价管理','path'=>'ShopComment/index','authdata'=>'ShopComment/*'];
		if($isadmin){
			$shop_child[] = ['name'=>'商品分类','path'=>'ShopCategory/index','authdata'=>'ShopCategory/*'];
            $shop_child[] = ['name'=>'商品分组','path'=>'ShopGroup/index','authdata'=>'ShopGroup/*'];
            }else{
			$shop_child[] = ['name'=>'商品分类','path'=>'ShopCategory2/index','authdata'=>'ShopCategory2/*'];
		}
		$shop_child[] = ['name'=>'商品参数','path'=>'ShopParam/index','authdata'=>'ShopParam/*'];
		$shop_child[] = ['name'=>'商品服务','path'=>'ShopFuwu/index','authdata'=>'ShopFuwu/*'];
        if($isadmin){
			$shop_child[] = ['name'=>'商品海报','path'=>'ShopPoster/index','authdata'=>'ShopPoster/*'];
			$shop_child[] = ['name'=>'录入订单','path'=>'ShopOrderlr/index','authdata'=>'ShopOrderlr/*,ShopProduct/chooseproduct,ShopProduct/index,ShopProduct/getproduct,Member/choosemember'];
            if(getcustom('shop_add_stock')){
			    $shop_child[] = ['name'=>'录入库存','path'=>'ShopStock/index','authdata'=>'ShopStock/*,ShopProduct/chooseproduct,ShopProduct/index'];
            }
            if(getcustom('shop_stock_manage')){
                $shop_child[] = ['name'=>'库存管理','path'=>'ShopStock/manage','authdata'=>'ShopStock/*,ShopProduct/chooseproduct,ShopProduct/index'];
            }
            }
		$shop_child[] = ['name'=>'商品采集','path'=>'ShopTaobao/index','authdata'=>'ShopTaobao/*'];

		$shop_child[] = ['name'=>'销售统计','path'=>'ShopOrder/tongji','authdata'=>'ShopOrder/*'];
        if(getcustom('product_supplier')){
            $shop_child[] = ['name'=>'供应商','path'=>'ProductSupplier/index','authdata'=>'ProductSupplier/*'];
        }
       if(getcustom('business_withdraw')){
			$shop_child[] = ['name'=>'发货有效期','path'=>'OrderSendintime','authdata'=>'OrderSendintime','hide'=>true];
		}
		if($isadmin){
            $shop_child[] = ['name'=>'系统设置','path'=>'ShopSet/index','authdata'=>'ShopSet/*'];
            }else{
            }
        if($isadmin) {
            }
        if($isadmin) {
            }
		$menudata['shop'] = ['name'=>'商城','fullname'=>'商城系统','icon'=>'my-icon my-icon-shop','child'=>$shop_child];

        if($isadmin){
            $component_business = [];
            $component_business[] = ['name'=>'商户列表','path'=>'Business/index','authdata'=>'Business/*,BusinessFreight/*'];
            $component_business[] = ['name'=>'商户分类','path'=>'Business/category','authdata'=>'Business/*'];
            $component_business[] = ['name'=>'商户商品','path'=>'ShopProduct/index&showtype=2','authdata'=>'ShopProduct/*'];
            $component_business[] = ['name'=>'商户销量','path'=>'Business/sales','authdata'=>'Business/*'];
            $component_business[] = ['name'=>'商户订单','path'=>'ShopOrder/index&showtype=2','authdata'=>'ShopOrder/*'];
            $component_business[] = ['name'=>'商户评价','path'=>'BusinessComment/index&showtype=2','authdata'=>'BusinessComment/*'];
            $component_business[] = ['name'=>'拼团商品','path'=>'CollageProduct/index&showtype=2','authdata'=>'CollageProduct/*'];
            $component_business[] = ['name'=>'砍价商品','path'=>'KanjiaProduct/index&showtype=2','authdata'=>'KanjiaProduct/*'];
            $component_business[] = ['name'=>'秒杀商品','path'=>'SeckillProduct/index&showtype=2','authdata'=>'SeckillProduct/*'];
            $component_business[] = ['name'=>'团购商品','path'=>'TuangouProduct/index&showtype=2','authdata'=>'TuangouProduct/*'];
            $component_business[] = ['name'=>'服务商品','path'=>'YuyueList/index&showtype=2','authdata'=>'YuyueList/*'];
            $component_business[] = ['name'=>'周期购商品','path'=>'CycleProduct/index&showtype=2','authdata'=>'CycleProduct/*'];
            $component_business[] = ['name'=>'幸运拼团商品','path'=>'LuckyCollageProduct/index&showtype=2','authdata'=>'LuckyCollageProduct/*'];
            if(getcustom('business_selfscore') || getcustom('business_score_withdraw') || getcustom('business_score_jiesuan')){
                $component_businessC = [];
                $bset = Db::name('business_sysset')->where('aid',$aid)->find();
                if($isadmin==2 || $bset['business_selfscore'] == 1){
                    }
                if($component_businessC){
                    if(count($component_businessC)==1){
                        $component_business[] = $component_businessC[0];
                    }else{
                        $component_business[] = ['name'=>'商户积分','child'=>$component_businessC];
                    }
                }
            }
            if(getcustom('hotel')){
				$text = \app\model\Hotel::gettext(aid);
				$business_hotel = [];
				$business_hotel[] = ['name'=>$text['酒店'].'列表','path'=>'HotelList/index&showtype=2','authdata'=>'HotelList/*'];
                $business_hotel[] = ['name'=>'房型房态','path'=>'HotelRoomPrices/index&showtype=2','authdata'=>'HotelRoom/*'];
                $business_hotel[] = ['name'=>$text['酒店'].'销量','path'=>'HotelSales/index','authdata'=>'HotelSales/*'];
				$business_hotel[] = ['name'=>$text['酒店'].'订单','path'=>'HotelOrder/index&showtype=all','authdata'=>'HotelOrder/*'];
				$business_hotel[] = ['name'=>'押金列表','path'=>'HotelOrderYajin/index&showtype=2','authdata'=>'HotelOrderYajin/*'];

                $component_business[] = ['name'=>$text['酒店'].'管理','child'=>$business_hotel];
			}

            $component_business[] = ['name'=>'文章列表','path'=>'Article/index&showtype=2','authdata'=>'Article/*'];
            $component_business[] = ['name'=>'短视频列表','path'=>'Shortvideo/index&showtype=2','authdata'=>'Shortvideo/*'];
            if(false){}else{
                $component_business[] = ['name'=>t('自定义表单'),'path'=>'Form/index&showtype=2','authdata'=>'Form/*'];
            }
            $component_business[] = ['name'=>t('余额').'明细','path'=>'BusinessMoney/moneylog','authdata'=>'BusinessMoney/moneylog,BusinessMoney/moneylogexcel,BusinessMoney/moneylogsetst,BusinessMoney/moneylogdel'];
            $component_business[] = ['name'=>'提现记录','path'=>'BusinessMoney/withdrawlog','authdata'=>'BusinessMoney/*'];

            $component_business[] = ['name'=>'通知公告','path'=>'BusinessNotice/index','authdata'=>'BusinessNotice/*'];
            $component_business[] = ['name'=>'默认导航','path'=>'DesignerMenu/business','authdata'=>'DesignerMenu/*'];
            $component_business[] = ['name'=>'系统设置','path'=>'Business/sysset','authdata'=>'Business/sysset'];
            if($uid == 0){
                $component_business2[] = ['name'=>'商品分类','path'=>'ShopCategory2/index','authdata'=>'ShopCategory2/*','hide'=>true];
                $component_business2[] = ['name'=>'余额提现','path'=>'BusinessMoney/withdraw','authdata'=>'BusinessMoney/*','hide'=>true];
                $component_business2[] = ['name'=>'买单扣费','path'=>'BusinessMaidan/add','authdata'=>'BusinessMaidan/*','hide'=>true];
                $component_business2[] = ['name'=>'买单记录','path'=>'BusinessMaidan/index','authdata'=>'BusinessMaidan/*','hide'=>true];
                $component_business2[] = ['name'=>'收款码','path'=>'BusinessMaidan/set','authdata'=>'BusinessMaidan/*','hide'=>true];
                if(getcustom('business_hexiaoplatform')){
                    $component_business2[] = ['name'=>'平台核销','path'=>'business_hexiaoplatform','authdata'=>'business_hexiaoplatform','hide'=>true];
                }
                $component_business2[] = ['name'=>'店铺评价','path'=>'BusinessComment/index','authdata'=>'BusinessComment/*','hide'=>true];
                $component_business[] = ['name'=>'商户后台','child'=>$component_business2,'hide'=>true];
            }
            $menudata['business'] = ['name'=>'商户','fullname'=>'多商户','icon'=>'my-icon my-icon-shop','child'=>$component_business];
        }else{
            }

		if($isadmin){
			$member_child = [];
			$member_child[] = ['name'=>t('会员').'列表','path'=>'Member/index','authdata'=>'Member/index,Member/excel,Member/excel,Member/importexcel,Member/getplatform,Member/edit,Member/save,Member/del,Member/getcarddetail,Member/charts,Member/setst,Member/choosemember,Member/check'];
			$member_child[] = ['name'=>'充值','path'=>'Member/recharge','authdata'=>'Member/recharge','hide'=>true];
			$member_child[] = ['name'=>'加'.t('积分'),'path'=>'Member/addscore','authdata'=>'Member/addscore','hide'=>true];
			$member_child[] = ['name'=>'加'.t('佣金'),'path'=>'Member/addcommission','authdata'=>'Member/addcommission','hide'=>true];
			if(getcustom('commission_frozen')){
                if ($admin['commission_frozen'] == 1 || $aid == 0) {
                    $member_child[] = ['name'=>'解冻'.t('扶持金'),'path'=>'Member/unfrozenFuchi','authdata'=>'Member/unfrozenFuchi','hide'=>true];
                }
            }
            if(getcustom('up_giveparent')){
                $member_child[] = ['name'=>'脱离回归','path'=>'Member/huigui','authdata'=>'Member/huigui','hide'=>true];
            }
			$member_child[] = ['name'=>'等级及分销','path'=>'MemberLevel/index','authdata'=>'MemberLevel/*'];
			$member_child[] = ['name'=>'升级申请记录','path'=>'MemberLevel/applyorder','authdata'=>'MemberLevel/*'];
            if(getcustom('up_giveparent')){
                $member_child[] = ['name'=>'脱离记录','path'=>'MemberLevel/changePidLog','authdata'=>'MemberLevel/changePidLog'];
            }
            $member_child[] = ['name'=>t('会员').'关系图','path'=>'Member/charts','authdata'=>'Member/charts'];
			$member_child[] = ['name'=>'分享海报','path'=>'MemberPoster/index','authdata'=>'MemberPoster/*'];
			if(getcustom('up_giveparent')){
                $member_child[] = ['name'=>'升级脱离','path'=>'up_giveparent','authdata'=>'up_giveparent','hide'=>true];
            }
			$member_child[] = ['name'=>'团队分红','path'=>'teamfenhong','authdata'=>'teamfenhong','hide'=>true];
            if(getcustom('teamfenhong_pingji')){
                $member_child[] = ['name'=>'团队分红平级奖','path'=>'teamfenhong_pingji','authdata'=>'teamfenhong_pingji','hide'=>true];
            }
            if(getcustom('teamfenhong_share')){
                $member_child[] = ['name'=>'团队分红共享奖','path'=>'teamfenhong_share','authdata'=>'teamfenhong_share','hide'=>true];
            }
            $member_child[] = ['name'=>'股东分红','path'=>'gdfenhong','authdata'=>'gdfenhong','hide'=>true];
			$member_child[] = ['name'=>'区域分红','path'=>'areafenhong','authdata'=>'areafenhong','hide'=>true];
			if(getcustom('partner_jiaquan')){
				$member_child[] = ['name'=>'股东加权分红','path'=>'partner_jiaquan','authdata'=>'partner_jiaquan','hide'=>true];
			}
            if(getcustom('partner_gongxian')){
				$member_child[] = ['name'=>'股东贡献量分红','path'=>'partner_gongxian','authdata'=>'partner_gongxian','hide'=>true];
			}
			if(getcustom('areafenhong_jiaquan')){
				$member_child[] = ['name'=>'大区设置','path'=>'Largearea/index','authdata'=>'Largearea/*'];
			}
			if(getcustom('commission_parent_pj')){
                $member_child[] = ['name'=>'平级奖','path'=>'commission_parent_pj','authdata'=>'commission_parent_pj','hide'=>true];
            }
            if(getcustom('member_set')){
                $member_child[] = ['name'=>'资料自定义','path'=>'MemberSet/index','authdata'=>'MemberSet/*'];
            }
	    if(getcustom('member_tag')){
		$member_child[] = ['name'=>t('会员').'标签','path'=>'MemberTag/index','authdata'=>'MemberTag/*'];
		}
            if(getcustom('register_fields')){
                $member_child[] = ['name' => '注册自定义', 'path' => 'RegisterForm/index', 'authdata' => 'RegisterForm/*'];
            }
            if(getcustom('member_product_price')){
                $member_child[] = ['name'=>'一客一价','path'=>'MemberProduct/index','authdata'=>'MemberProduct/*'];
            }
            if(getcustom('member_overdraft_money')){
                $member_child[] = ['name'=>t('信用额度').'充值','path'=>'OverdraftMoney/recharge','authdata'=>'OverdraftMoney/recharge','hide'=>true];
                $member_child[] = ['name'=>'修改'.t('信用额度'),'path'=>'OverdraftMoney/batchLimitOverdrafmoney','authdata'=>'OverdraftMoney/batchLimitOverdrafmoney','hide'=>true];
            }
            if(getcustom('level_teamfenhong')){
                $member_child[] = ['name'=>t('等级团队分红'),'path'=>'level_teamfenhong','authdata'=>'level_teamfenhong','hide'=>true];
            }
	        $menudata['member'] = ['name'=>t('会员'),'fullname'=>t('会员').'管理','icon'=>'my-icon my-icon-member','child'=>$member_child];
            
		}else{
            }
		if(getcustom('mendian_upgrade') && bid == 0){
			if($admin['mendian_upgrade_status']==1 || $aid === 0){
				$mendian_child = [];
				$mendian_child[] = ['name'=>t('门店').'列表','path'=>'Mendian/index2','authdata'=>'Mendian/*'];
				$mendian_child[] = ['name'=>t('门店').'分组','path'=>'MendianGroup/index','authdata'=>'MendianGroup/*'];
				$mendian_child[] = ['name'=>t('门店').'等级','path'=>'MendianLevel/index','authdata'=>'MendianLevel/*'];
				$mendian_child[] = ['name'=>t('门店').'设置','path'=>'MendianSet/index','authdata'=>'MendianSet/*'];
				$menudata['mendian'] = ['name'=>t('门店'),'fullname'=>t('门店').'管理','icon'=>'my-icon my-icon-member','child'=>$mendian_child];
			}
		}

		if($isadmin){
			$finance_child = [];
			$finance_child[] = ['name'=>'消费明细','path'=>'Payorder/index','authdata'=>'Payorder/*'];
			$finance_child[] = ['name'=>t('余额').'明细','path'=>'Money/moneylog','authdata'=>'Money/moneylog,Money/moneylogexcel,Money/moneylogsetst,Money/moneylogdel'];
			$finance_child[] = ['name'=>'充值记录','path'=>'Money/rechargelog','authdata'=>'Money/rechargelog,Money/rechargelogexcel,Money/rechargelogdel'];
			$finance_child[] = ['name'=>t('余额').'提现','path'=>'Money/withdrawlog','authdata'=>'Money/*'];
            $finance_child[] = ['name'=>'微信转账记录','path'=>'Money/wx_transfer_log','authdata'=>'Money/*'];
            $finance_child[] = ['name'=>t('佣金').'记录','path'=>'Commission/record','authdata'=>'Commission/record'];
            $finance_child[] = ['name'=>t('佣金').'明细','path'=>'Commission/commissionlog','authdata'=>'Commission/commissionlog,Commission/commissionlogexcel,Commission/commissionlogdel'];
			$finance_child[] = ['name'=>t('佣金').'提现','path'=>'Commission/withdrawlog','authdata'=>'Commission/*'];
            $finance_child[] = ['name'=>t('积分').'明细','path'=>'Score/scorelog','authdata'=>'Score/*'];
            $finance_childC = [];
            if(getcustom('business_selfscore') || getcustom('business_score_withdraw') || getcustom('business_score_jiesuan')){
                $bset = Db::name('business_sysset')->where('aid',$aid)->find();
                if($isadmin==2 || $bset['business_selfscore'] == 1){
                    $finance_childC[] = ['name'=>t('积分').'明细','path'=>'BusinessScore/scorelog','authdata'=>'BusinessScore/*','hide'=>true];
                    if($bset['business_selfscore2'] == 1){
                        $finance_childC[] = ['name'=>t('会员').t('积分'),'path'=>'BusinessScore/memberscore','authdata'=>'BusinessScore/*','hide'=>true];
                    }
                    }
            }
            if($finance_childC){
                $finance_child[] = ['name'=>'商户'.t('积分'),'child'=>$finance_childC,'hide'=>true];
            }
            $maidan_child = [];
            $maidan_child[] = ['name'=>'买单扣费','path'=>'Maidan/add','authdata'=>'Maidan/*'];
            $maidan_child[] = ['name'=>'买单记录','path'=>'Maidan/index','authdata'=>'Maidan/*'];
			$maidan_child[] = ['name'=>'聚合收款码','path'=>'Maidan/set','authdata'=>'Maidan/set'];
            $finance_child[] = ['name'=>'买单收款','child'=>$maidan_child];

            $finance_child[] = ['name'=>'分红记录','path'=>'Commission/fenhonglog','authdata'=>'Commission/*'];
            if(getcustom('mendian_hexiao_givemoney') && !getcustom('xixie')){
				$menutitle = '门店余额';
				if(getcustom('mendian_upgrade') && $admin['mendian_upgrade_status']==1){
					$menutitle = t('门店').'佣金';
				}
				$finance_child[] = ['name'=>$menutitle.'明细','path'=>'MendianMoney/moneylog','authdata'=>'MendianMoney/moneylog,MendianMoney/moneylogexcel,MendianMoney/moneylogsetst,MendianMoney/moneylogdel'];
				$finance_child[] = ['name'=>$menutitle.'提现','path'=>'MendianMoney/withdrawlog','authdata'=>'MendianMoney/*'];
			}
            if(getcustom('commission_frozen')){
                if($admin['commission_frozen'] == 1 || $aid == 0){
                    $finance_child[] = ['name'=>t('扶持金').'记录','path'=>'Commission/fuchiRecord','authdata'=>'Commission/fuchiRecord'];
                    $finance_child[] = ['name'=>t('扶持金').'明细','path'=>'Commission/fuchiLog','authdata'=>'Commission/fuchiLog'];
                }
            }
            }else{
            //多商户
			$finance_child = [];
			$finance_child[] = ['name'=>'余额明细','path'=>'BusinessMoney/moneylog','authdata'=>'BusinessMoney/moneylog,BusinessMoney/moneylogexcel,BusinessMoney/moneylogsetst,BusinessMoney/moneylogdel'];
			$finance_child[] = ['name'=>'余额提现','path'=>'BusinessMoney/withdraw','authdata'=>'BusinessMoney/*'];
			$finance_child[] = ['name'=>'提现记录','path'=>'BusinessMoney/withdrawlog','authdata'=>'BusinessMoney/*'];
            $finance_childC = [];
            if(getcustom('business_selfscore') || getcustom('business_score_withdraw') || getcustom('business_score_jiesuan')){
                $bset = Db::name('business_sysset')->where('aid',$aid)->find();
                if($bset['business_selfscore'] == 1){
                    $finance_childC[] = ['name'=>t('积分').'明细','path'=>'BusinessScore/scorelog','authdata'=>'BusinessScore/*'];
                    if($bset['business_selfscore2'] == 1){
                        $finance_childC[] = ['name'=>t('会员').t('积分'),'path'=>'BusinessScore/memberscore','authdata'=>'BusinessScore/*'];
                    }
                    }
            }
            if($finance_childC){
                $finance_child[] = ['name'=>'商户'.t('积分'),'child'=>$finance_childC];
            }

			$maidan_child = [];
            $maidan_child[] = ['name'=>'买单扣费','path'=>'BusinessMaidan/add','authdata'=>'BusinessMaidan/*'];
            $maidan_child[] = ['name'=>'买单记录','path'=>'BusinessMaidan/index','authdata'=>'BusinessMaidan/*'];
            $maidan_child[] = ['name'=>'收款码','path'=>'BusinessMaidan/set','authdata'=>'BusinessMaidan/*'];
			$finance_child[] = ['name'=>'买单收款','child'=>$maidan_child];
            if(getcustom('mendian_hexiao_givemoney') && !getcustom('xixie')){
				$finance_child[] = ['name'=>'门店余额明细','path'=>'MendianMoney/moneylog','authdata'=>'MendianMoney/moneylog,MendianMoney/moneylogexcel,MendianMoney/moneylogsetst,MendianMoney/moneylogdel'];
				$finance_child[] = ['name'=>'门店余额提现','path'=>'MendianMoney/withdrawlog','authdata'=>'MendianMoney/*'];
			}
		}
		$finance_child[] = ['name'=>'核销记录','path'=>'Hexiao/index','authdata'=>'Hexiao/*'];
		if(getcustom('freight_selecthxbids') || getcustom('product_quanyi')){
			$finance_child[] = ['name'=>'计次核销记录','path'=>'Hexiao/shopproduct','authdata'=>'Hexiao/*'];
		}
		$finance_child[] = ['name'=>'发票管理','path'=>'Invoice/index','authdata'=>'Invoice/*'];
		if(getcustom('product_bonus_pool') ){
            $bonus_pool = [];
            $bonus_pool[] = ['name'=>'奖金池','path'=>'BonusPool/index','authdata'=>'BonusPool/index'];
            $bonus_pool[] = ['name'=>'奖金池分类','path'=>'BonusPool/category','authdata'=>'BonusPool/category'];
            $bonus_pool[] = ['name'=>t('贡献值').'记录','path'=>'BonusPool/record','authdata'=>'BonusPool/*'];
            $bonus_pool[] = ['name'=>t('贡献值').'明细','path'=>'BonusPool/commissionlog','authdata'=>'BonusPool/*'];
            $bonus_pool[] = ['name'=>t('贡献值').'提现记录','path'=>'BonusPool/withdrawlog','authdata'=>'BonusPool/*'];
            $bonus_pool[] = ['name'=>'奖金池','path'=>'ShopSet/bonuspoolset','authdata'=>'ShopSet/bonuspoolset','hide'=>true];
            $finance_child[] = ['name'=>'奖金池','child'=>$bonus_pool];
        }
        if(getcustom('member_overdraft_money')){
            $finance_child[] = ['name'=>t('信用额度').'明细','path'=>'OverdraftMoney/moneylog','authdata'=>'OverdraftMoney/moneylog,OverdraftMoney/moneylogexce'];
        }
        if(getcustom('finance_trade_report')){
            $finance_child[] = ['name'=>'营业报表','path'=>'Payorder/tradereport','authdata'=>'Payorder/tradereport'];
        }
        if(getcustom('expend')){
            $finance_child[] = ['name'=>'支出','path'=>'Expend/index','authdata'=>'expend/*'];
        }
        $menudata['finance'] = ['name'=>'财务','fullname'=>'财务管理','icon'=>'my-icon my-icon-finance','child'=>$finance_child];

		$yingxiao_child = [];
		$yingxiao_child[] = ['name'=>t('优惠券'),'path'=>'Coupon/index','authdata'=>'Coupon/*,ShopCategory/index,ShopCategory/choosecategory'];
		if($isadmin){
            $yingxiao_child[] = ['name'=>'注册赠送','path'=>'Member/registerGive','authdata'=>'Member/registerGive'];
            $yingxiao_child[] = ['name'=>'充值赠送','path'=>'Money/giveset','authdata'=>'Money/giveset'];
			$yingxiao_child[] = ['name'=>'购物满减','path'=>'Manjian/set','authdata'=>'Manjian/set'];
		}
		$yingxiao_child[] = ['name'=>'商品促销','path'=>'Cuxiao/index','authdata'=>'Cuxiao/*'];
		if($isadmin){
			$yingxiao_child[] = ['name'=>'购物返现','path'=>'Cashback/index','authdata'=>'Cashback/*'];
			if(getcustom('forcerebuy')){
				$yingxiao_child[] = ['name'=>'强制复购','path'=>'Forcerebuy/index','authdata'=>'Forcerebuy/*'];
			}
            if(getcustom('payaftergive')){
                $yingxiao_child[] = ['name'=>'支付后赠送','path'=>'Payaftergive/index','authdata'=>'Payaftergive/*'];
            }
            }else{
            if(getcustom('payaftergive')){
                $yingxiao_child[] = ['name'=>'支付后赠送','path'=>'Payaftergive/index','authdata'=>'Payaftergive/*'];
            }
        }
		$yingxiao_collage = [];
		$yingxiao_collage[] = ['name'=>'商品管理','path'=>'CollageProduct/index','authdata'=>'CollageProduct/*,CollageCode/*'];
		$yingxiao_collage[] = ['name'=>'订单管理','path'=>'CollageOrder/index','authdata'=>'CollageOrder/*'];
		$yingxiao_collage[] = ['name'=>'拼团管理','path'=>'CollageTeam/index','authdata'=>'CollageTeam/*'];
		$yingxiao_collage[] = ['name'=>'评价管理','path'=>'CollageComment/index','authdata'=>'CollageComment/*'];
		if($isadmin){
			$yingxiao_collage[] = ['name'=>'商品分类','path'=>'CollageCategory/index','authdata'=>'CollageCategory/*'];
			$yingxiao_collage[] = ['name'=>'分享海报','path'=>'CollagePoster/index','authdata'=>'CollagePoster/*'];
			$yingxiao_collage[] = ['name'=>'系统设置','path'=>'CollageSet/index','authdata'=>'CollageSet/*'];
		}
        $yingxiao_child[] = ['name'=>'多人拼团','child'=>$yingxiao_collage];

        $lucky_collage = [];
        $lucky_collage[] = ['name'=>'商品管理','path'=>'LuckyCollageProduct/index','authdata'=>'LuckyCollageProduct/*,LuckyCollageCode/*'];
        $lucky_collage[] = ['name'=>'订单管理','path'=>'LuckyCollageOrder/index','authdata'=>'LuckyCollageOrder/*'];
        $lucky_collage[] = ['name'=>'拼团管理','path'=>'LuckyCollageTeam/index','authdata'=>'LuckyCollageTeam/*'];
        $lucky_collage[] = ['name'=>'评价管理','path'=>'LuckyCollageComment/index','authdata'=>'LuckyCollageComment/*'];
        if($isadmin){
            $lucky_collage[] = ['name'=>'商品分类','path'=>'LuckyCollageCategory/index','authdata'=>'LuckyCollageCategory/*'];
            $lucky_collage[] = ['name'=>'分享海报','path'=>'LuckyCollagePoster/index','authdata'=>'LuckyCollagePoster/*'];
            $lucky_collage[] = ['name'=>'机器人管理','path'=>'LuckyCollageJiqiren/index','authdata'=>'LuckyCollageJiqiren/*'];
            $lucky_collage[] = ['name'=>'系统设置','path'=>'LuckyCollageSet/index','authdata'=>'LuckyCollageSet/*'];
        }
        $yingxiao_child[] = ['name'=>'幸运拼团','child'=>$lucky_collage];

		$yingxiao_kanjia = [];
		$yingxiao_kanjia[] = ['name'=>'商品管理','path'=>'KanjiaProduct/index','authdata'=>'KanjiaProduct/*,KanjiaCode/*'];
		$yingxiao_kanjia[] = ['name'=>'订单管理','path'=>'KanjiaOrder/index','authdata'=>'KanjiaOrder/*'];
		if($isadmin){
			$yingxiao_kanjia[] = ['name'=>'分享海报','path'=>'KanjiaPoster/index','authdata'=>'KanjiaPoster/*'];
			$yingxiao_kanjia[] = ['name'=>'系统设置','path'=>'KanjiaSet/index','authdata'=>'KanjiaSet/*'];
		}
		if(getcustom('fengkuangkanjia')){
			$yingxiao_kanjia[] = ['name'=>'疯狂砍价','path'=>'fengkuangkanjia','authdata'=>'fengkuangkanjia','hide'=>true];
		}

		$yingxiao_child[] = ['name'=>'砍价活动','child'=>$yingxiao_kanjia];

		$yingxiao_seckill= [];
		//$yingxiao_seckill[] = ['name'=>'商品设置','path'=>'SeckillProset/index','authdata'=>'SeckillProset/*,ShopProduct/chooseproduct,ShopProduct/getproduct'];
		//$yingxiao_seckill[] = ['name'=>'秒杀列表','path'=>'SeckillList/index','authdata'=>'SeckillList/*'];
		$yingxiao_seckill[] = ['name'=>'商品列表','path'=>'SeckillProduct/index','authdata'=>'SeckillProduct/*,SeckillCode/*'];
		$yingxiao_seckill[] = ['name'=>'订单列表','path'=>'SeckillOrder/index','authdata'=>'SeckillOrder/*'];
		$yingxiao_seckill[] = ['name'=>'用户评价','path'=>'SeckillComment/index','authdata'=>'SeckillComment/*'];
		if($isadmin){
			$yingxiao_seckill[] = ['name'=>'秒杀设置','path'=>'SeckillSet/index','authdata'=>'SeckillSet/*'];
		}
		$yingxiao_child[] = ['name'=>'整点秒杀','child'=>$yingxiao_seckill];

		$yingxiao_tuangou = [];
		$yingxiao_tuangou[] = ['name'=>'商品管理','path'=>'TuangouProduct/index','authdata'=>'TuangouProduct/*,TuangouCode/*'];
		$yingxiao_tuangou[] = ['name'=>'订单管理','path'=>'TuangouOrder/index','authdata'=>'TuangouOrder/*'];
		$yingxiao_tuangou[] = ['name'=>'评价管理','path'=>'TuangouComment/index','authdata'=>'TuangouComment/*'];
		$yingxiao_tuangou[] = ['name'=>'商品分类','path'=>'TuangouCategory/index','authdata'=>'TuangouCategory/*'];
		if($isadmin){
			$yingxiao_tuangou[] = ['name'=>'分享海报','path'=>'TuangouPoster/index','authdata'=>'TuangouPoster/*'];
			$yingxiao_tuangou[] = ['name'=>'系统设置','path'=>'TuangouSet/index','authdata'=>'TuangouSet/*'];
		}
		$yingxiao_child[] = ['name'=>'团购活动','child'=>$yingxiao_tuangou];

		if($isadmin){
			$yingxiao_scoreshop = [];
			$yingxiao_scoreshop[] = ['name'=>'商品管理','path'=>'ScoreshopProduct/index','authdata'=>'ScoreshopProduct/*,ScoreshopCode/*'];
			$yingxiao_scoreshop[] = ['name'=>'订单管理','path'=>'ScoreshopOrder/index','authdata'=>'ScoreshopOrder/*'];
			$yingxiao_scoreshop[] = ['name'=>'商品分类','path'=>'ScoreshopCategory/index','authdata'=>'ScoreshopCategory/*'];
			$yingxiao_scoreshop[] = ['name'=>'分享海报','path'=>'ScoreshopPoster/index','authdata'=>'ScoreshopPoster/*'];
			$yingxiao_scoreshop[] = ['name'=>'系统设置','path'=>'ScoreshopSet/index','authdata'=>'ScoreshopSet/*'];
            $yingxiao_child[] = ['name'=>t('积分').'兑换','child'=>$yingxiao_scoreshop];

			//$yingxiao_hongbao = [];
			//$yingxiao_hongbao[] = ['name'=>'活动列表','path'=>'Hongbao/index','authdata'=>'Hongbao/*'];
			//$yingxiao_hongbao[] = ['name'=>'领取记录','path'=>'Hongbao/record','authdata'=>'Hongbao/*'];
			//$yingxiao_child[] = ['name'=>'微信红包','child'=>$yingxiao_hongbao];

		}else{
			}
		if($isadmin){
			$yingxiao_choujiang = [];
			$yingxiao_choujiang[] = ['name'=>'活动列表','path'=>'Choujiang/index','authdata'=>'Choujiang/*'];
			$yingxiao_choujiang[] = ['name'=>'抽奖记录','path'=>'Choujiang/record','authdata'=>'Choujiang/*'];
			$yingxiao_child[] = ['name'=>'抽奖活动','child'=>$yingxiao_choujiang];
            }
        if($isadmin){
            }

		$short_video= [];
		$short_video[] = ['name'=>'分类列表','path'=>'ShortvideoCategory/index','authdata'=>'ShortvideoCategory/*'];
		$short_video[] = ['name'=>'视频列表','path'=>'Shortvideo/index','authdata'=>'Shortvideo/*'];
		$short_video[] = ['name'=>'评论列表','path'=>'ShortvideoComment/index','authdata'=>'ShortvideoComment/*'];
		$short_video[] = ['name'=>'回评列表','path'=>'ShortvideoCommentReply/index','authdata'=>'ShortvideoCommentReply/*'];
		if($isadmin){
			$short_video[] = ['name'=>'海报设置','path'=>'ShortvideoPoster/index','authdata'=>'ShortvideoPoster/*'];
			$short_video[] = ['name'=>'系统设置','path'=>'ShortvideoSysset/index','authdata'=>'ShortvideoSysset/*'];
		}
		$yingxiao_child[] = ['name'=>'短视频','child'=>$short_video];
        if(getcustom('yx_invite_cashback')){
            if($isadmin){
                $yingxiao_child[] = ['name'=>'邀请返现','path'=>'InviteCashback/index','authdata'=>'InviteCashback/*'];
            }
        }

		//$fifa_child = [];
		//$fifa_child[] = ['name'=>'竞猜设置','path'=>'Fifa/set','authdata'=>'Fifa/*'];
		//$fifa_child[] = ['name'=>'竞猜记录','path'=>'Fifa/record','authdata'=>'Fifa/*'];
		//$fifa_child[] = ['name'=>'海报设置','path'=>'Fifa/posterset','authdata'=>'Fifa/*'];
		//$yingxiao_child[] = ['name'=>'世界杯竞猜','child'=>$fifa_child];

        if(getcustom('yx_hbtk')){
            $yingxiao_hbtk = [];
            $yingxiao_hbtk[] = ['name'=>'活动列表','path'=>'HbtkActivity/index','authdata'=>'HbtkActivity/*'];
            $yingxiao_hbtk[] = ['name'=>'分享海报','path'=>'HbtkPoster/index','authdata'=>'HbtkPoster/*'];
            $yingxiao_child[] = ['name'=>'拓客活动','child'=>$yingxiao_hbtk];
        }
	    if(getcustom('yx_business_miandan')){
            
            $yingxiao_bsmiandan = [];
            $yingxiao_bsmiandan[] = ['name'=>'免单列表','path'=>'BusinessMiandan/index','authdata'=>'BusinessMiandan/*'];
            $yingxiao_bsmiandan[] = ['name'=>'免单订单列表','path'=>'BusinessMiandanOrder/index','authdata'=>'BusinessMiandanOrder/*'];
            $yingxiao_bsmiandan[] = ['name'=>'免单设置','path'=>'BusinessMiandanSet/set','authdata'=>'BusinessMiandanSet/*'];                
            
            $yingxiao_child[] = ['name'=>'商户免单','child'=>$yingxiao_bsmiandan];
            
        }

        if($isadmin){
            }
        if(getcustom('yx_team_yeji')){
            if($isadmin){
                $teamyeji_child[] = ['name'=>'团队业绩','path'=>'TeamSaleYeji/index','authdata'=>'TeamSaleYeji/*'];
                $teamyeji_child[] = ['name'=>'业绩奖设置','path'=>'TeamSaleYeji/set','authdata'=>'TeamSaleYeji/*'];
                $yingxiao_child[] = ['name'=>'团队业绩奖','child'=>$teamyeji_child];
            }
        }
        if(getcustom('yx_queue_free')){
            $queue_free_child[] = ['name'=>'排队记录','path'=>'QueueFree/index','authdata'=>'QueueFree/*'];
            $queue_free_child[] = ['name'=>t('免单设置'),'path'=>'QueueFreeSet/index','authdata'=>'QueueFreeSet/*'];
        }
        if(getcustom('yx_queue_free')){
            $yingxiao_child[] = ['name'=>t('排队免单'),'child'=>$queue_free_child];
        }
        if(getcustom('yx_order_discount_rand')){
            $yingxiao_child[] = ['name'=>'下单随机立减','path'=>'OrderDiscountRand/set','authdata'=>'OrderDiscountRand/*'];
        }
        $greenscore_max_custom = getcustom('greenscore_max');
        $menudata['yingxiao'] = ['name'=>'营销','fullname'=>'营销活动','icon'=>'my-icon my-icon-yingxiao','child'=>$yingxiao_child];
        $component_article = [];
		$component_article[] = ['name'=>'文章列表','path'=>'Article/index','authdata'=>'Article/*'];
		$component_article[] = ['name'=>'文章分类','path'=>'ArticleCategory/index','authdata'=>'ArticleCategory/*'];
		$component_article[] = ['name'=>'评论列表','path'=>'ArticlePinglun/index','authdata'=>'ArticlePinglun/*'];
		$component_article[] = ['name'=>'回评列表','path'=>'ArticlePlreply/index','authdata'=>'ArticlePlreply/*'];
		$component_article[] = ['name'=>'系统设置','path'=>'ArticleSet/set','authdata'=>'ArticleSet/*'];
		$component_child[] = ['name'=>'文章管理','child'=>$component_article];
        if($isadmin){
			$component_luntan = [];
			$component_luntan[] = ['name'=>'帖子列表','path'=>'Luntan/index','authdata'=>'Luntan/*'];
			$component_luntan[] = ['name'=>'分类管理','path'=>'LuntanCategory/index','authdata'=>'LuntanCategory/*'];
			$component_luntan[] = ['name'=>'评论列表','path'=>'LuntanPinglun/index','authdata'=>'LuntanPinglun/*'];
			$component_luntan[] = ['name'=>'回评列表','path'=>'LuntanPlreply/index','authdata'=>'LuntanPlreply/*'];
			$component_luntan[] = ['name'=>'系统设置','path'=>'Luntan/sysset','authdata'=>'Luntan/sysset'];
			$component_child[] = ['name'=>'用户论坛','child'=>$component_luntan];
		}
		if($isadmin){
			$component_sign = [];
			$component_sign[] = ['name'=>'签到记录','path'=>'Sign/record','authdata'=>'Sign/record,Sign/recordexcel,Sign/recorddel'];
			$component_sign[] = ['name'=>'签到设置','path'=>'Sign/set','authdata'=>'Sign/set'];
			$component_child[] = ['name'=>t('积分').'签到','child'=>$component_sign];
		}
		//预约服务
		$component_yuyue=[];
		$component_yuyue[] = ['name'=>'服务类型','path'=>'Yuyue/index','authdata'=>'Yuyue/*'];
		$component_yuyue[] = ['name'=>'服务商品','path'=>'YuyueList/index','authdata'=>'YuyueList/*'];
		$component_yuyue[] = ['name'=>'服务订单','path'=>'YuyueOrder/index','authdata'=>'YuyueOrder/*'];
		$component_yuyue[] = ['name'=>'商品服务','path'=>'YuyueFuwu/index','authdata'=>'YuyueFuwu/*'];
		$component_yuyue[] = ['name'=>'商品评价','path'=>'YuyueComment/index','authdata'=>'YuyueComment/*'];
		if($isadmin){
			$component_yuyue[] = ['name'=>'海报设置','path'=>'YuyuePoster/index','authdata'=>'YuyuePoster/*'];
		}
        //使用平台服务人员的，多商户不再管理人员
        $yuyueUserShow = true;
        if($yuyueUserShow){
            $component_yuyue[] = ['name'=>'人员类型','path'=>'YuyueWorkerCategory/index','authdata'=>'YuyueWorkerCategory/*'];
            $component_yuyue[] = ['name'=>'人员列表','path'=>'YuyueWorker/index','authdata'=>'YuyueWorker/*'];
            $component_yuyue[] = ['name'=>'人员评价','path'=>'YuyueWorkerComment/index','authdata'=>'YuyueWorkerComment/*'];
        }
		$component_yuyue[] = ['name'=>'提成明细','path'=>'YuyueMoney/moneylog','authdata'=>'YuyueMoney/*'];
		$component_yuyue[] = ['name'=>'提现记录','path'=>'YuyueMoney/withdrawlog','authdata'=>'YuyueMoney/*'];
		$component_yuyue[] = ['name'=>'系统设置','path'=>'YuyueSet/set','authdata'=>'YuyueSet/*'];
		$component_child[] = ['name'=>'预约服务','child'=>$component_yuyue];

		$component_kecheng=[];
		$component_kecheng[] = ['name'=>'课程分类','path'=>'KechengCategory/index','authdata'=>'KechengCategory/*'];
		$component_kecheng[] = ['name'=>'课程列表','path'=>'KechengList/index','authdata'=>'KechengList/*,KechengRecord/*'];
		$component_kecheng[] = ['name'=>'课程章节','path'=>'KechengChapter/index','authdata'=>'KechengChapter/*'];
		$component_kecheng[] = ['name'=>'题库管理','path'=>'KechengTiku/index','authdata'=>'KechengTiku/*'];
		$component_kecheng[] = ['name'=>'课程订单','path'=>'KechengOrder/index','authdata'=>'KechengOrder/*'];
		$component_kecheng[] = ['name'=>'学习记录','path'=>'KechengStudylog/index','authdata'=>'KechengStudylog/*'];
		if($isadmin){
            $component_kecheng[] = ['name'=>'课程海报','path'=>'KechengPoster/index','authdata'=>'KechengPoster/*'];
			$component_kecheng[] = ['name'=>'课程设置','path'=>'KechengSet/index','authdata'=>'KechengSet/*'];
		}
		$component_child[] = ['name'=>'知识付费','child'=>$component_kecheng];
        $component_child[] = ['name'=>'视频管理','path'=>'VideoList/index','authdata'=>'VideoList/*'];


		if($isadmin){
			$component_peisong = [];
			$component_peisong[] = ['name'=>'配送员列表','path'=>'PeisongUser/index','authdata'=>'PeisongUser/*'];
			$component_peisong[] = ['name'=>'配送单列表','path'=>'PeisongOrder/index','authdata'=>'PeisongOrder/*'];
			$component_peisong[] = ['name'=>'评价列表','path'=>'PeisongComment/index','authdata'=>'PeisongComment/*'];
			$component_peisong[] = ['name'=>'提成明细','path'=>'PeisongMoney/moneylog','authdata'=>'PeisongMoney/*'];
			$component_peisong[] = ['name'=>'提现记录','path'=>'PeisongMoney/withdrawlog','authdata'=>'Peisong/*'];
			$component_peisong[] = ['name'=>'系统设置','path'=>'Peisong/set','authdata'=>'Peisong/*'];
			$component_peisong[] = ['name'=>t('码科跑腿').'对接','path'=>'Peisong/makeset','authdata'=>'Peisong/*'];
			if(getcustom('express_maiyatian')){
                $component_peisong[] = ['name'=>'麦芽田设置','path'=>'Peisong/mytset','authdata'=>'Peisong/*'];
                $component_peisong[] = ['name'=>'麦芽田订单','path'=>'MytPeisongOrder/index','authdata'=>'MytPeisongOrder/*'];
            }
            if(getcustom('paotui')){
                $component_peisong[] = ['name'=>'跑腿设置','path'=>'PaotuiSet/index','authdata'=>'PaotuiSet/*'];
                $component_peisong[] = ['name'=>'跑腿订单','path'=>'PaotuiOrder/index','authdata'=>'PaotuiOrder/*'];
            }
			$component_child[] = ['name'=>'同城配送','child'=>$component_peisong];
            if(in_array('wx',$platform)){
                $component_child[] = ['name'=>'物流助手','path'=>'Miandan/index','authdata'=>'Miandan/*'];
            }
			$component_tp = [];
			$component_tp[] = ['name'=>'活动列表','path'=>'Toupiao/index','authdata'=>'Toupiao/*'];
			$component_tp[] = ['name'=>'选手列表','path'=>'Toupiao/joinlist','authdata'=>'Toupiao/*'];
			$component_tp[] = ['name'=>'投票记录','path'=>'Toupiao/helplist','authdata'=>'Toupiao/*'];
            $component_tp[] = ['name'=>'投票分组','path'=>'ToupiaoGroup/index','authdata'=>'ToupiaoGroup/*'];
			//$component_tp[] = ['name'=>'投票设置','path'=>'Toupiao/set','authdata'=>'Toupiao/*'];
			$component_child[] = ['name'=>'投票活动','child'=>$component_tp];
		}else{
            if(getcustom('express_maiyatian')){
            	$component_peisong = [];
				$component_peisong[] = ['name'=>'配送单列表','path'=>'PeisongOrder/index','authdata'=>'PeisongOrder/*'];
                $component_peisong[] = ['name'=>'麦芽田设置','path'=>'Peisong/mytset','authdata'=>'Peisong/*'];
                $component_child[] = ['name'=>'同城配送','child'=>$component_peisong];
            }
        }

		if(getcustom('yueke')){
			$component_yueke=[];
			$component_yueke[] = ['name'=>'课程类型','path'=>'YuekeCategory/index','authdata'=>'YuekeCategory/*'];
			$component_yueke[] = ['name'=>'课程列表','path'=>'YuekeProduct/index','authdata'=>'YuekeProduct/*'];
			$component_yueke[] = ['name'=>'约课记录','path'=>'YuekeOrder/index','authdata'=>'YuekeOrder/*'];
			$component_yuyue[] = ['name'=>'课程评价','path'=>'YuekeComment/index','authdata'=>'YuekeComment/*'];
			$component_yueke[] = ['name'=>t('教练').'列表','path'=>'YuekeWorker/index','authdata'=>'YuekeWorker/*'];
			//$component_yueke[] = ['name'=>'教练评价','path'=>'YuekeWorkerComment/index','authdata'=>'YuekeWorkerComment/*'];
			$component_yueke[] = ['name'=>'提成明细','path'=>'YuekeMoney/moneylog','authdata'=>'YuekeMoney/*'];
			$component_yueke[] = ['name'=>'提现记录','path'=>'YuekeMoney/withdrawlog','authdata'=>'YuekeMoney/*'];
			if($isadmin){
				$component_yueke[] = ['name'=>'海报设置','path'=>'YuekePoster/index','authdata'=>'YuekePoster/*'];
				$component_yueke[] = ['name'=>'系统设置','path'=>'YuekeSet/set','authdata'=>'YuekeSet/*'];
			}
			$component_child[] = ['name'=>'约课报名','child'=>$component_yueke];
		}
		if(getcustom('huodong_baoming')){
			$component_huodong_baoming=[];
			$component_huodong_baoming[] = ['name'=>'活动报名类型','path'=>'HuodongBaomingCategory/index','authdata'=>'HuodongBaomingCategory/*'];
			$component_huodong_baoming[] = ['name'=>'活动报名列表','path'=>'HuodongBaomingList/index','authdata'=>'HuodongBaomingList/*'];
			$component_huodong_baoming[] = ['name'=>'活动报名订单','path'=>'HuodongBaomingOrder/index','authdata'=>'HuodongBaomingOrder/*'];
            if($isadmin){
				$component_huodong_baoming[] = ['name'=>'海报设置','path'=>'HuodongBaomingPoster/index','authdata'=>'HuodongBaomingPoster/*'];
				$component_huodong_baoming[] = ['name'=>'系统设置','path'=>'HuodongBaomingSet/set','authdata'=>'HuodongBaomingSet/*'];
			}
			$component_child[] = ['name'=>'活动报名','child'=>$component_huodong_baoming];
		}
		if(getcustom('daily_jinju')){
            if($isadmin){
                $component_daily_jinju=[];
                $component_daily_jinju[] = ['name'=>'每日金句','path'=>'DailyjinjuProduct/index','authdata'=>'DailyjinjuProduct/*'];
                $component_daily_jinju[] = ['name'=>'打卡列表','path'=>'DailyjinjuDaka/index','authdata'=>'DailyjinjuDaka/*'];
                $component_daily_jinju[] = ['name'=>'感悟列表','path'=>'DailyjinjuGanwu/index','authdata'=>'DailyjinjuGanwu/*'];
                $component_daily_jinju[] = ['name'=>'海报设置','path'=>'DailyjinjuPoster/index','authdata'=>'DailyjinjuPoster/*'];
                $component_child[] = ['name'=>'每日金句','child'=>$component_daily_jinju];
			}
		}
		if(getcustom('car_hailing')){
            $car_hailing_child=[];
            $car_hailing_child[] = ['name'=>'约车类型','path'=>'CarHailingCategory/index','authdata'=>'CarHailingCategory/*'];
            $car_hailing_child[] = ['name'=>'约车列表','path'=>'CarHailingProduct/index','authdata'=>'CarHailingProduct/*'];
            $car_hailing_child[] = ['name'=>'约车记录','path'=>'CarHailingOrder/index','authdata'=>'CarHailingOrder/*'];
            $car_hailing_child[] = ['name'=>'约车海报','path'=>'CarHailingPoster/index','authdata'=>'CarHailingPoster/*'];
//            $car_hailing_child[] = ['name'=>'约车评价','path'=>'CarHailingComment/index','authdata'=>'CarHailingComment/*'];
            if($isadmin){
                $car_hailing_child[] = ['name'=>'系统设置','path'=>'CarHailingSet/set','authdata'=>'CarHailingSet/*'];
            }
            $menudata['carhailing'] = ['name'=>'约车','fullname'=>'约车管理','icon'=>'my-icon my-icon-kuozhan','child'=>$car_hailing_child];
        }

        $product_thali = false;
        $yingxiao_cycle = [];
		$yingxiao_cycle[] = ['name'=>'商品管理','path'=>'CycleProduct/index','authdata'=>'CycleProduct/*,CycleCode/*'];
		$yingxiao_cycle[] = ['name'=>'订单管理','path'=>'CycleOrder/index','authdata'=>'CycleOrder/*'];
		$yingxiao_cycle[] = ['name'=>'配送管理','path'=>'CycleOrder/cycle_list&status=1','authdata'=>'CycleOrder/*'];
		$yingxiao_cycle[] = ['name'=>'评价管理','path'=>'CycleComment/index','authdata'=>'CycleComment/*'];
		if($isadmin){
			$yingxiao_cycle[] = ['name'=>'商品分类','path'=>'CycleCategory/index','authdata'=>'CycleCategory/*'];
			$yingxiao_cycle[] = ['name'=>'分享海报','path'=>'CyclePoster/index','authdata'=>'CyclePoster/*'];
			$yingxiao_cycle[] = ['name'=>'系统设置','path'=>'CycleSet/index','authdata'=>'CycleSet/*'];
		}
		$component_child[] = ['name'=>'周期购','child'=>$yingxiao_cycle];

		if($isadmin){
			$component_mingpian=[];
			$component_mingpian[] = ['name'=>'名片列表','path'=>'Mingpian/index','authdata'=>'Mingpian/*'];
			$component_mingpian[] = ['name'=>'系统设置','path'=>'Mingpian/set','authdata'=>'Mingpian/*'];
			$component_child[] = ['name'=>'名片','child'=>$component_mingpian];
		}

        $cashier_child = [];
        $cashier_child[] = ['name' => '收银设置', 'path' => 'Cashier/index', 'authdata' => 'Cashier/*'];
        $cashier_child[] = ['name' => '收银订单', 'path' => 'CashierOrder/index', 'authdata' => 'CashierOrder/*'];
        $cashier_child[] = ['name' => '订单统计', 'path' => 'CashierOrder/tongji', 'authdata' => 'CashierOrder/*'];
        $component_child[] = ['name'=>'收银台','child'=>$cashier_child];
        $component_child[] = ['name'=>t('自定义表单'),'path'=>'Form/index','authdata'=>'Form/*'];
        if($isadmin){
            $lipin_child = [];
            $lipin_child[] = ['name' => '礼品卡', 'path' => 'Lipin/index', 'authdata' => 'Lipin/*'];
            $lipin_child[] = ['name' => '礼品卡兑换码', 'path' => 'Lipin/codelist', 'authdata' => 'Lipin/codelist'];
            $lipin_child[] = ['name'=>'兑换码生成','path'=>'Lipin/makecode','authdata'=>'Lipin/makecode','hide'=>true];
            $lipin_child[] = ['name'=>'兑换码导入','path'=>'Lipin/importexcel','authdata'=>'Lipin/importexcel','hide'=>true];
            $lipin_child[] = ['name'=>'兑换码导出','path'=>'Lipin/codelistexcel','authdata'=>'Lipin/codelistexcel','hide'=>true];
            $lipin_child[] = ['name'=>'兑换码修改状态','path'=>'Lipin/setst','authdata'=>'Lipin/setst','hide'=>true];
            $lipin_child[] = ['name'=>'兑换码删除','path'=>'Lipin/codelistdel','authdata'=>'Lipin/codelistdel','hide'=>true];
            $lipin_child[] = ['name' => '礼品卡分类', 'path' => 'LipinCategory/index', 'authdata' => 'LipinCategory/*'];
            $component_child[] = ['name'=>'礼品卡','child'=>$lipin_child];

			if(in_array('wx',$platform)){
				$component_child[] = ['name'=>'小程序直播','path'=>'Live/index','authdata'=>'Live/*'];
				/*
				$component_child[] = ['name'=>'视频号接入','child'=>[
					['name'=>'申请接入','path'=>'Wxvideo/apply','authdata'=>'Wxvideo/*'],
					['name'=>'商家信息','path'=>'Wxvideo/setinfo','authdata'=>'Wxvideo/*'],
					['name'=>'商品管理','path'=>'ShopProduct/index&fromwxvideo=1','authdata'=>'ShopProduct/*,ShopCode/*'],
					['name'=>'订单管理','path'=>'ShopOrder/index&fromwxvideo=1','authdata'=>'ShopOrder/*'],
					['name'=>'退款申请','path'=>'ShopRefundOrder/index&fromwxvideo=1','authdata'=>'ShopRefundOrder/*'],
					['name'=>'我的类目','path'=>'Wxvideo/category','authdata'=>'Wxvideo/*'],
					['name'=>'我的品牌','path'=>'Wxvideo/brand','authdata'=>'Wxvideo/*'],
					['name'=>'同步修复','path'=>'Wxvideo/deliverytongbu','authdata'=>'Wxvideo/*'],
				]];
				*/
			}
            if (getcustom('wx_fws_liuliangzhu')){
                $component_child[] = ['name'=>'微信广告','child'=>[
                    ['name'=>'广告列表','path'=>'Wxadvert/index','authdata'=>'Wxadvert/*'],
                    ['name'=>'汇总数据','path'=>'Wxadvert/addata','authdata'=>'Wxadvert/*'],
                    ['name'=>'结算数据','path'=>'Wxadvert/settle','authdata'=>'Wxadvert/*'],
                ]];
            }
			// if(in_array('toutiao',$platform)){
			// 	$component_child[] = ['name'=>'抖音接入','child'=>[
			// 		['name'=>'接入配置','path'=>'DouyinSet/index','authdata'=>'Douyin/*'],
			// 		['name'=>'商品管理','path'=>'ShopProduct/index&fromdouyin=1','authdata'=>'ShopProduct/*,ShopCode/*'],
			// 		['name'=>'商品管理','path'=>'DouyinProduct/index','authdata'=>'DouyinProduct/*'],
			// 	]];
			// }
            if(getcustom('headimg')){
                $component_child[] = ['name'=>'头像库','path'=>'Headimg/index','authdata'=>'Headimg/*'];
            }
            if(getcustom('renovation_calculator')){
                $component_child[] = ['name'=>'装修计算器','path'=>'RenovationCalculator/set','authdata'=>'RenovationCalculator/*'];
            }
            }
        if($isadmin) {
            }

        if($isadmin) {
            if (getcustom('image_ai')) {
                $aiimg_child = [];
                $aiimg_child[] = ['name' => '参数配置', 'path' => 'ImgaiSet/index', 'authdata' => 'ImgaiSet/*'];
                $aiimg_child[] = ['name' => '风格分类', 'path' => 'ImgaiCategory/index', 'authdata' => 'ImgaiCategory/*'];
                $aiimg_child[] = ['name' => '推荐关键词', 'path' => 'ImgaiKeyword/index', 'authdata' => 'ImgaiKeyword/*'];
                $aiimg_child[] = ['name' => '申请列表', 'path' => 'ImgaiOrder/index', 'authdata' => 'ImgaiOrder/*'];
                $component_child[] = ['name' => '文心AI绘画', 'child' => $aiimg_child];
            }
            
            // 码库功能菜单
            $qrcode_child = [];
            $qrcode_child[] = ['name' => '二维码列表', 'path' => 'ProductQrcode/index', 'authdata' => 'ProductQrcode/*'];
            $qrcode_child[] = ['name' => '生成二维码', 'path' => 'ProductQrcode/generate', 'authdata' => 'ProductQrcode/*'];
            $qrcode_child[] = ['name' => '批量生成', 'path' => 'ProductQrcode/batchGenerate', 'authdata' => 'ProductQrcode/*'];
            $qrcode_child[] = ['name' => '服务记录', 'path' => 'ProductQrcode/serviceLogs', 'authdata' => 'ProductQrcode/*'];
            $qrcode_child[] = ['name' => '扫码统计', 'path' => 'ProductQrcode/statistics', 'authdata' => 'ProductQrcode/*'];
            $qrcode_child[] = ['name' => '打印设计', 'path' => 'ProductQrcode/printDesign', 'authdata' => 'ProductQrcode/*'];
            $menudata['productqrcode'] = ['name' => '码库', 'fullname' => '码库管理', 'icon' => 'my-icon my-icon-kuozhan', 'child' => $qrcode_child];
            
            // 仓库管理系统功能菜单
$barcode_inventory_child = [];
$barcode_inventory_child[] = ['name' => '库存列表', 'path' => 'BarcodeInventory/index', 'authdata' => 'BarcodeInventory/*'];
$barcode_inventory_child[] = ['name' => '新增商品', 'path' => 'BarcodeInventory/edit', 'authdata' => 'BarcodeInventory/*'];
$barcode_inventory_child[] = ['name' => '出库管理', 'path' => 'BarcodeInventory/outbound', 'authdata' => 'BarcodeInventory/*'];
$barcode_inventory_child[] = ['name' => '出库记录', 'path' => 'BarcodeInventory/outbound_log', 'authdata' => 'BarcodeInventory/*'];
$barcode_inventory_child[] = ['name' => '库存盘点', 'path' => 'BarcodeInventory/check', 'authdata' => 'BarcodeInventory/*'];
$barcode_inventory_child[] = ['name' => '库存报表', 'path' => 'BarcodeInventory/report', 'authdata' => 'BarcodeInventory/*'];
$barcode_inventory_child[] = ['name' => '打印报表', 'path' => 'BarcodeInventory/print_report', 'authdata' => 'BarcodeInventory/*'];
$barcode_inventory_child[] = ['name' => '系统设置', 'path' => 'BarcodeInventory/settings', 'authdata' => 'BarcodeInventory/*'];
$menudata['barcode_inventory'] = ['name' => '仓库', 'fullname' => '仓库管理', 'icon' => 'my-icon my-icon-kuozhan', 'child' => $barcode_inventory_child];
            
            if (getcustom('map_mark')) {
                $aiimg_child = [];
                $aiimg_child[] = ['name' => '参数配置', 'path' => 'MapmarkSet/index', 'authdata' => 'MapmarkSet/*'];
                $aiimg_child[] = ['name' => '地图类型', 'path' => 'MapmarkCategory/index', 'authdata' => 'MapmarkCategory/*'];
                $aiimg_child[] = ['name' => '申请列表', 'path' => 'MapmarkOrder/index', 'authdata' => 'MapmarkOrder/*'];
                $component_child[] = ['name' => '地图标注', 'child' => $aiimg_child];
            }
            if (getcustom('video_spider')) {
                $aiimg_child = [];
                $aiimg_child[] = ['name' => '参数配置', 'path' => 'VideospiderSet/index', 'authdata' => 'VideospiderSet/*'];
                $aiimg_child[] = ['name' => '支持平台', 'path' => 'VideospiderCategory/index', 'authdata' => 'VideospiderCategory/*'];
                $aiimg_child[] = ['name' => '申请列表', 'path' => 'VideospiderOrder/index', 'authdata' => 'VideospiderOrder/*'];
                $component_child[] = ['name' => '视频水印', 'child' => $aiimg_child];
            }
        }
        //海康摄像机设置
        $qrcode_fenzhang =   getcustom('extend_qrcode_variable_fenzhang');
        if(getcustom('lot_cerberuse')){
            $cerberuse_child = [];
            $cerberuse_child[] = ['name' => '智能开门', 'path' => 'Cerberuse/index', 'authdata' => 'Cerberuse/*'];
            $cerberuse_child[] = ['name' => '订单记录', 'path' => 'CerberuseOrder/index', 'authdata' => 'CerberuseOrder/*'];
            $cerberuse_child[] = ['name' => '系统设置', 'path' => 'CerberuseSet/set', 'authdata' => 'CerberuseSet/*'];
            $component_child[] = ['name'=>'智能开门','child'=>$cerberuse_child];
        }

        if(getcustom('extend_certificate')){
            $certificate[] = ['name'=>'证书列表','path'=>'CertificateList/index','authdata'=>'CertificateList/*'];
            $certificate[] = ['name'=>'证书类别','path'=>'CertificateCategory/index','authdata'=>'CertificateCategory/*'];
            $certificate[] = ['name'=>'职业管理','path'=>'CertificateJob/index','authdata'=>'CertificateJob/*'];
            $certificate[] = ['name'=>'学历管理','path'=>'CertificateEducation/index','authdata'=>'CertificateEducation/*'];
            if($isadmin){
                $certificate[] = ['name'=>'证书设置','path'=>'CertificateList/set','authdata'=>'CertificateList/set'];
            }
            $menudata['certificate'] = ['name'=>'证书','fullname'=>'证书管理','icon'=>'my-icon my-icon-kuozhan','child'=>$certificate];
        }
if(getcustom('douyin_groupbuy')){
            $douyingroupbuyset = [];
            //$douyingroupbuyset[] = ['name'=>'抖音商品分类','path'=>'DouyinGroupbuyCategory/index','authdata'=>'DouyinGroupbuyCategory/*'];
            $douyingroupbuyset[] = ['name'=>'抖音团购商品','path'=>'DouyinGroupbuyProduct/index','authdata'=>'DouyinGroupbuyProduct/*'];
            if(!$ismenu && ($isadmin || $uid == -1)){
                $douyingroupbuyset[] = ['name'=>'获取所有抖音团购商品','path'=>'DouyinGroupbuyGetallproduct','authdata'=>'Getallproduct','hide'=>true];
            }
            $douyingroupbuyset[] = ['name'=>'核销订单列表','path'=>'ShopOrder/index&isdygroupbuy=1','authdata'=>'ShopOrder/*'];
            $douyingroupbuyset[] = ['name'=>'抖音设置','path'=>'DouyinGroupbuySet/index','authdata'=>'DouyinGroupbuySet/*'];
            $component_child[] = ['name'=>'抖音团购','child'=>$douyingroupbuyset];
        }
		$menudata['component'] = ['name'=>'扩展','fullname'=>'扩展功能','icon'=>'my-icon my-icon-kuozhan','child'=>$component_child];
		if(getcustom('restaurant')){
			$menudata['restaurant'] = \app\custom\Restaurant::getmenu($isadmin);
		}

		 if(getcustom('hotel')){
			$text = \app\model\Hotel::gettext(aid);
            $hotel_child = [];
            $hotel_child[] = ['name'=>$text['酒店'].'设置','path'=>'Hotel/index','authdata'=>'Hotel/*'];
			if($isadmin){
			  $hotel_child[] = ['name'=>$text['酒店'].'类型','path'=>'HotelCategory/index','authdata'=>'HotelCategory/*'];
			}
			$hotel_child[] = ['name'=>$text['酒店'].'相册','path'=>'HotelPhotos/index','authdata'=>'HotelPhotos/*'];
            $hotel_child[] = ['name'=>'房型列表','path'=>'HotelRoom/index','authdata'=>'HotelRoom/*'];
			$hotel_child[] = ['name'=>'房型分组','path'=>'HotelGroup/index','authdata'=>'HotelGroup/*'];
            $hotel_child[] = ['name'=>'房态房价','path'=>'HotelRoomPrices/index','authdata'=>'HotelRoomPrices/*'];
            $hotel_child[] = ['name'=>$text['酒店'].'订单','path'=>'HotelOrder/index','authdata'=>'HotelOrder/*'];
		    $hotel_child[] = ['name'=>'押金列表','path'=>'HotelOrderYajin/index','authdata'=>'HotelOrderYajin/*'];
		    $hotel_child[] = ['name'=>'订单评价','path'=>'HotelComment/index','authdata'=>'HotelComment/*'];
			$hotel_child[] = ['name'=>'优惠券','path'=>'HotelCoupon/index','authdata'=>'HotelCoupon/*'];
			if($isadmin){
				$hotel_child[] = ['name'=>'系统设置','path'=>'HotelSet/index','authdata'=>'HotelSet/*'];
			}
			$menudata['hotel'] = ['name'=>$text['酒店'],'fullname'=>$text['酒店'].'管理','icon'=>'my-icon my-icon-kuozhan','child'=>$hotel_child];
        }
        $jiemian_child = [];
        if(false){}else{
			$jiemian_child[] = ['name'=>'页面设计','path'=>'DesignerPage/index','authdata'=>'DesignerPage/*'];
		}
        if(getcustom('design_cat')) {
            $jiemian_child[] = ['name'=>'页面分类','path'=>'DesignerCategory/index','authdata'=>'DesignerCategory/*'];
        }
        $jiemian_child[] = ['name'=>'图标设计','path'=>'DesignerIcon/index','authdata'=>'DesignerIcon/*'];
        if($isadmin){
            $jiemian_child[] = ['name'=>'底部导航','path'=>'DesignerMenu/index','authdata'=>'DesignerMenu/*'];
            $jiemian_child[] = ['name'=>'内页导航','path'=>'DesignerMenu/menu2','authdata'=>'DesignerMenu/*'];
			$jiemian_child[] = ['name'=>'商品详情','path'=>'DesignerMenuShopdetail/shopdetail','authdata'=>'DesignerMenuShopdetail/*'];
        }else{
            $jiemian_child[] = ['name'=>'底部导航','path'=>'DesignerMenu/menu2','authdata'=>'DesignerMenu/*'];
			$jiemian_child[] = ['name'=>'商品详情','path'=>'DesignerMenuShopdetail/shopdetail','authdata'=>'DesignerMenuShopdetail/*'];
        }
        if($isadmin){
        	$jiemian_child[] = ['name'=>'登录页面','path'=>'DesignerLogin/index','authdata'=>'DesignerLogin/*'];
            $jiemian_child[] = ['name'=>'移动端后台','path'=>'DesignerMobile/index','authdata'=>'DesignerMobile/*'];
        }
        $jiemian_child[] = ['name'=>'分享设置','path'=>'DesignerShare/index','authdata'=>'DesignerShare/*'];
        $jiemian_child[] = ['name'=>'链接地址','path'=>'DesignerPage/chooseurl','params'=>'/type/geturl','authdata'=>'DesignerPage/chooseurl,DesignerPage/getwxqrcode'];
        
        $menudata['jiemian'] = ['name'=>'设计','fullname'=>'界面设计','icon'=>'my-icon my-icon-sheji','child'=>$jiemian_child];

        if($isadmin){
			$pingtai_child = [];
			if(in_array('mp',$platform)){
				$pingtai_child_mp = [];
				$pingtai_child_mp[] = ['name'=>'公众号绑定','path'=>'Binding/index','authdata'=>'Binding/*'];
				$pingtai_child_mp[] = ['name'=>'菜单设置','path'=>'Mpmenu/index','authdata'=>'Mpmenu/*'];
				$pingtai_child_mp[] = ['name'=>'支付设置','path'=>'Mppay/set','authdata'=>'Mppay/*'];
                $pingtai_child_mp[] = ['name'=>'随行付分账','path'=>'SxpayFenzhang/*','authdata'=>'SxpayFenzhang/*','hide'=>true];
				$pingtai_child_mp[] = ['name'=>'模板消息设置','path'=>'Mptmpl/tmplset','authdata'=>'Mptmpl/*'];
                $pingtai_child_mp[] = ['name'=>'类目模板消息','path'=>'Mptmpl/tmplsetNew','authdata'=>'Mptmpl/tmplsetNew'];
				$pingtai_child_mp[] = ['name'=>'已添加的模板','path'=>'Mptmpl/mytmpl','authdata'=>'Mptmpl/*'];
				$pingtai_child_mp[] = ['name'=>'被关注回复','path'=>'Mpkeyword/subscribe','authdata'=>'Mpkeyword/*'];
				$pingtai_child_mp[] = ['name'=>'关键字回复','path'=>'Mpkeyword/index','authdata'=>'Mpkeyword/*'];
				$pingtai_child_mp[] = ['name'=>'粉丝列表','path'=>'Mpfans/fanslist','authdata'=>'Mpfans/*'];
				//$pingtai_child_mp[] = ['name'=>'素材管理','path'=>'Mpfans/sourcelist','authdata'=>'Mpfans/*'];
				$pingtai_child_mp[] = ['name'=>'模板消息群发','path'=>'Mpfans/tmplsend','authdata'=>'Mpfans/*'];
				//$pingtai_child_mp[] = ['name'=>'活跃粉丝群发','path'=>'Mpfans/kfmsgsend','authdata'=>'Mpfans/*'];
				$pingtai_child[] = ['name'=>'微信公众号','child'=>$pingtai_child_mp];
				$pingtai_child_mpcard = [];
				$pingtai_child_mpcard[] = ['name'=>'领取记录','path'=>'Membercard/record','authdata'=>'Membercard/record'];
				$pingtai_child_mpcard[] = ['name'=>'会员卡/创建','path'=>'Membercard/index','authdata'=>'Membercard/*'];
                $pingtai_child[] = ['name'=>'微信会员卡','child'=>$pingtai_child_mpcard];
			}
			if(in_array('wx',$platform)){
				$pingtai_child_wx = [];
				$pingtai_child_wx[] = ['name'=>'小程序绑定','path'=>'Binding/index','authdata'=>'Binding/*'];
				$pingtai_child_wx[] = ['name'=>'支付设置','path'=>'Wxpay/set','authdata'=>'Wxpay/*'];
				$pingtai_child_wx[] = ['name'=>'订阅消息设置','path'=>'Wxtmpl/tmplset','authdata'=>'Wxtmpl/*'];
				$pingtai_child_wx[] = ['name'=>'服务类目','path'=>'Wxleimu/index','authdata'=>'Wxleimu/*'];
				$pingtai_child_wx[] = ['name'=>'外部链接','path'=>'Wxurl/index','authdata'=>'Wxurl/*'];
				//$pingtai_child_wx[] = ['name'=>'关键字回复','path'=>'Wxkeyword/index','authdata'=>'Wxkeyword/*'];
                $pingtai_child_wx[] = ['name'=>'半屏小程序','path'=>'Wxembedded/index','authdata'=>'Wxembedded/*'];
				$pingtai_child[] = ['name'=>'微信小程序','child'=>$pingtai_child_wx];
			}
			if(in_array('alipay',$platform)){
				$pingtai_child[] = ['name'=>'支付宝小程序','path'=>'Binding/alipay','authdata'=>'Binding/*'];
			}
			if(in_array('baidu',$platform)){
				$pingtai_child[] = ['name'=>'百度小程序','path'=>'Binding/baidu','authdata'=>'Binding/*'];
			}
			if(in_array('toutiao',$platform)){
				$pingtai_child[] = ['name'=>'抖音小程序','path'=>'Binding/toutiao','authdata'=>'Binding/*'];
			}
			if(in_array('qq',$platform)){
				$pingtai_child[] = ['name'=>'QQ小程序','path'=>'Binding/qq','authdata'=>'Binding/*'];
			}
			if(in_array('h5',$platform)){
				$pingtai_child[] = ['name'=>'手机H5','path'=>'Binding/h5','authdata'=>'Binding/*'];
			}
			if(in_array('app',$platform)){
				$pingtai_child[] = ['name'=>'手机APP','path'=>'Binding/app','authdata'=>'Binding/*'];
			}
            if(getcustom('wx_channels')){
                $pingtai_child_channels = [];
                $pingtai_child_channels[] = ['name'=>'小店绑定','path'=>'WxChannels/bind','authdata'=>'WxChannels/*'];
                $pingtai_child_channels[] = ['name'=>'商品管理','path'=>'WxChannelsProduct/index','authdata'=>'WxChannelsProduct/*'];
                $pingtai_child_channels[] = ['name'=>'商品类目','path'=>'WxChannelsCategory/index','authdata'=>'WxChannelsCategory/*'];
                $pingtai_child_channels[] = ['name'=>'品牌库','path'=>'WxChannelsBrand/index','authdata'=>'WxChannelsBrand/*'];
                $pingtai_child_channels[] = ['name'=>'订单管理','path'=>'WxChannelsOrder/index','authdata'=>'WxChannelsOrder/*'];
                $pingtai_child_channels[] = ['name'=>'售后管理','path'=>'WxChannelsAfterSales/index','authdata'=>'WxChannelsAfterSales/*'];
                $pingtai_child_channels[] = ['name'=>'地址管理','path'=>'WxChannelsAddress/index','authdata'=>'WxChannelsAddress/*'];
                $pingtai_child_channels[] = ['name'=>'行政区域','path'=>'WxChannelsArea/index','authdata'=>'WxChannelsArea/*'];
                $pingtai_child_channels[] = ['name'=>'电子面单','path'=>'WxChannelsEwaybill/index','authdata'=>'WxChannelsMiandan/*'];
                $pingtai_child_channels[] = ['name'=>'运费模板','path'=>'WxChannelsFreight/index','authdata'=>'WxChannelsFreight/*'];
                $pingtai_child_channels[] = ['name'=>'优惠券','path'=>'WxChannelsCoupon/index','authdata'=>'WxChannelsCoupon/*'];
                $pingtai_child_channels[] = ['name'=>'分享员','path'=>'WxChannelsSharer/index','authdata'=>'WxChannelsSharer/*'];
                $pingtai_child_channels[] = ['name'=>'结算账户','path'=>'WxChannelsBankacct/index','authdata'=>'WxChannelsBankacct/*'];
                $pingtai_child_channels[] = ['name'=>'资金结算','path'=>'WxChannelsFundsflow/index','authdata'=>'WxChannelsFundsflow/*'];
                $pingtai_child_channels[] = ['name'=>'预约直播设置','path'=>'WxChannelsLiveSet/set','authdata'=>'WxChannelsLiveSet/*'];
                $pingtai_child[] = ['name'=>'视频号小店','child'=>$pingtai_child_channels];
            }
			if($pingtai_child)
			    $menudata['pingtai'] = ['name'=>'平台','fullname'=>'平台设置','icon'=>'my-icon my-icon-pingtai','child'=>$pingtai_child];
		}

		$system_child = [];
		$system_child[] = ['name'=>'系统设置','path'=>'Backstage/sysset','authdata'=>'Backstage/sysset'];
        
		$system_child[] = ['name'=>'门店管理','path'=>'Mendian/index','authdata'=>'Mendian/*'];
		$system_child[] = ['name'=>'管理员列表','path'=>'User/index','authdata'=>'User/*,UserGroup/*'];
		$system_child[] = ['name'=>'配送方式','path'=>'Freight/index','authdata'=>'Freight/*'];
        $system_child[] = ['name'=>'快递设置','path'=>'ExpressData/index','authdata'=>'ExpressData/*'];
        $system_child[] = ['name'=>'送货单设置','path'=>'ShdSet/index','authdata'=>'ShdSet/*'];
		$system_child[] = ['name'=>'小票打印机','path'=>'Wifiprint/index','authdata'=>'Wifiprint/*'];
        if($isadmin){
			$system_child[] = ['name'=>'短信设置','path'=>'Sms/set','authdata'=>'Sms/*'];
		}else{
			$system_child[] = ['name'=>'店铺评价','path'=>'BusinessComment/index','authdata'=>'BusinessComment/*'];
		}
		if($isadmin) {
            $wanyue10086 = getcustom('wanyue10086');
            }
        $system_child[] = ['name'=>'操作日志','path'=>'Backstage/plog','authdata'=>'Backstage/plog'];
        // aid == 1不可移除
        $menudata['system'] = ['name'=>'系统','fullname'=>'系统设置','icon'=>'my-icon my-icon-sysset','child'=>$system_child];
        if($user && $user['auth_type']==0){
			if($user['groupid']){
				$user['auth_data'] = Db::name('admin_user_group')->where('id',$user['groupid'])->value('auth_data');
			}

			$auth_data = json_decode($user['auth_data'],true);
			foreach($menudata as $k=>$v){
				if($v['child']){
                    $needcheckchild = true;//需要检验子权限
                    if($needcheckchild){
                        foreach($v['child'] as $k1=>$v1){
                            if(!$v1['authdata'] && $v1['child']){
                                $path = array();
                                foreach($v1['child'] as $k2=>$v2){
                                    if(!in_array($v2['path'].','.$v2['authdata'],$auth_data)){
                                        unset($menudata[$k]['child'][$k1]['child'][$k2]);
                                    }
                                }
                                if(count($menudata[$k]['child'][$k1]['child'])==0){
                                    unset($menudata[$k]['child'][$k1]);
                                }
                            }else{
                                if(!in_array($v1['path'].','.$v1['authdata'],$auth_data)){
                                    unset($menudata[$k]['child'][$k1]);
                                }
                            }
                        }
                        if(count($menudata[$k]['child'])==0){
                            unset($menudata[$k]);
                        }
                    }
				}else{
					if(!in_array($v['path'].','.$v['authdata'],$auth_data)){
						unset($menudata[$k]);
					}
				}
			}
		}else{
            foreach($menudata as $k=>$v){
                if($v['child']){
                    foreach($v['child'] as $k1=>$v1){
                        if($v1['child']){
                            if(count($menudata[$k]['child'][$k1]['child'])==0){
                                unset($menudata[$k]['child'][$k1]);
                            }
                        }
                    }
                }else{
                    if(count($menudata[$k]['child'])==0){
                        unset($menudata[$k]);
                    }
                }
            }
        }
		return $menudata;
	}

    public static function getdata2($uid=0){
        $menudata = [];
        $menudata['user'] = ['name'=>'用户列表','path'=>'WebUser/index'];
        $menudata['wxpayset'] = ['name'=>'服务商配置','path'=>'WebSystem/wxpayset'];
        $menudata['wxpaylog'] = ['name'=>'微信支付记录','path'=>'WebSystem/wxpaylog'];
        $menudata['component'] = ['name'=>'开放平台设置','path'=>'WebSystem/component'];
        $child = [];
        $child[] = ['name'=>'系统设置','path'=>'WebSystem/set'];
        $menudata['sysset'] = ['name'=>'系统设置','path'=>'WebSystem/set','child'=>$child];
        $menudata['remote'] = ['name'=>'附件设置','path'=>'WebSystem/remote'];
        $menudata['help'] = ['name'=>'帮助中心','path'=>'WebHelp/index'];
        $menudata['webnotice'] = ['name'=>'通知公告','path'=>'WebNotice/index'];
        $menudata['upgrade'] = ['name'=>'系统升级','path'=>'WebUpgrade/index'];
        return $menudata;
    }

	//白名单 不校验权限
	public static function blacklist(){
		$data = [];
		$data[] = 'Backstage/index';
		$data[] = 'Backstage/welcome';
		$data[] = 'Backstage/welcomeOld';
		$data[] = 'Backstage/setpwd';
		$data[] = 'Backstage/about';
		$data[] = 'Help/*';
		$data[] = 'Upload/*';
		$data[] = 'DesignerPage/chooseurl';
        $data[] = 'DesignerPage/getwxqrcode';
		$data[] = 'Peisong/getpeisonguser';
		$data[] = 'Peisong/peisong';
		$data[] = 'Miandan/addorder';
		$data[] = 'Wxset/*';
		$data[] = 'Notice/*';
		$data[] = 'notice/*';
		$data[] = 'SxpayIncome/*';
		$data[] = 'Member/inputlockpwd';
		$data[] = 'MemberLevel/inputlockpwd';
		$data[] = 'ShopProduct/inputlockpwd';
		$data[] = 'Member/dolock';
		$data[] = 'MemberLevel/dolock';
		$data[] = 'ShopProduct/dolock';
		$data[] = 'MemberArchives/*';
		$data[] = 'Map/*';
		$data[] = 'DesignerPage/choosezuobiao';
		return $data;
	}

}
