<?php
// JK客户定制

//管理员中心 - 财务管理
namespace app\controller;
use pay\wechatpay\WxPayV3;
use think\facade\Db;
class ApiAdminFinance extends ApiAdmin
{
	//财务管理
	function index(){
		$aid = aid;
		// 获取时间范围参数
		$time_range = input('post.time_range', 'today');
		// 计算不同时间范围
		$nowtime = time();
		$todayStart = strtotime(date('Y-m-d'));
		$todayEnd = $todayStart + 86400;
		$lastDayStart = strtotime(date('Y-m-d',time()-86400));
		$lastDayEnd = $lastDayStart + 86400;
		$thisMonthStart = strtotime(date('Y-m-1'));
		
		// 根据time_range参数计算查询时间范围
		$startTime = 0;
		$endTime = $nowtime;
		
		switch($time_range) {
			case 'today':
				$startTime = $todayStart;
				$endTime = $todayEnd;
				break;
			case 'week':
				// 本周开始时间（周一）
				$weekday = date('w');
				if($weekday == 0) $weekday = 7; // 周日转为7
				$startTime = strtotime(date('Y-m-d') . ' -' . ($weekday - 1) . ' days');
				$endTime = $nowtime;
				break;
			case 'month':
				$startTime = $thisMonthStart;
				$endTime = $nowtime;
				break;
			case 'quarter':
				// 本季度开始时间
				$quarter = ceil(date('n') / 3);
				$startTime = strtotime(date('Y') . '-' . (($quarter - 1) * 3 + 1) . '-01');
				$endTime = $nowtime;
				break;
			case 'year':
				// 本年开始时间
				$startTime = strtotime(date('Y') . '-01-01');
				$endTime = $nowtime;
				break;
			case 'all':
				$startTime = 0;
				$endTime = $nowtime;
				break;
		}
		
		// 保存时间范围用于后续查询
		$this->startTime = $startTime;
		$this->endTime = $endTime;
		$info = [];

		//退款金额
		$where   = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        if($this->user['mdid']){
        	$where[] = ['mdid','=',$this->user['mdid']];
        }
        $where[] = ['refund_status','=',2];
		$where[] = ['refund_time','>=',$this->startTime];
		$where[] = ['refund_time','<',$this->endTime];
		$info['refundCount'] = Db::name('shop_order')->where($where)->sum('refund_money');

		$where   = [];
        $where[] = ['ro.aid','=',aid];
        $where[] = ['ro.bid','=',bid];
        if($this->user['mdid']){
        	$where[] = ['o.mdid','=',$this->user['mdid']];
        }
        $where[] = ['ro.refund_status','=',2];
		$info['refundLastDayCount']   = Db::name('shop_refund_order')->alias('ro')->join('shop_order o','o.id = ro.orderid')->where($where)->where('ro.refund_time','>=',$lastDayStart)->where('ro.refund_time','<',$lastDayEnd)->sum('ro.refund_money');
		$info['refundThisMonthCount'] = Db::name('shop_refund_order')->alias('ro')->join('shop_order o','o.id = ro.orderid')->where($where)->where('ro.refund_time','>=',$thisMonthStart)->where('ro.refund_time','<',$nowtime)->sum('ro.refund_money');
		$info['show_tixian'] = 0;
		if(bid == 0){
			//收款金额
			if($this->user['mdid']){
				// 普通订单
				// 按时间范围查询总金额
				$where   = [];
		        $where[] = ['aid','=',aid];
		        $where[] = ['bid','=',bid];
		        $where[] = ['mdid','=',$this->user['mdid']];
		        $where[] = ['status','in',[1,2,3]];
		        $where[] = ['paytypeid','not in',[1,4]];
		        $where[] = ['paytime','>=',$this->startTime];
		        $where[] = ['paytime','<',$this->endTime];
				$shop_order_money = Db::name('shop_order')->where($where)->sum('totalprice');
		        
		        // 昨日金额（保留原逻辑）
		        unset($where[5], $where[6]);
		        $where[] = ['paytime','between',[$lastDayStart,$lastDayEnd]];
				$shop_order_money_day = Db::name('shop_order')->where($where)->sum('totalprice');
		        
		        // 本月金额（保留原逻辑）
		        unset($where[5]);
		        $where[] = ['paytime','between',[$thisMonthStart,$nowtime]];
				$shop_order_money_month = Db::name('shop_order')->where($where)->sum('totalprice');
				
				// 快速买单
				// 按时间范围查询总金额
				$where   = [];
		        $where[] = ['aid','=',aid];
		        $where[] = ['bid','=',bid];
		        $where[] = ['mdid','=',$this->user['mdid']];
		        $where[] = ['status','=',1];
		        $where[] = ['paytypeid','not in',[1,4]];
		        $where[] = ['paytime','>=',$this->startTime];
		        $where[] = ['paytime','<',$this->endTime];
				$maidan_order_money = Db::name('maidan_order')->where($where)->sum('paymoney');
		        
		        // 昨日金额（保留原逻辑）
		        unset($where[5], $where[6]);
		        $where[] = ['paytime','between',[$lastDayStart,$lastDayEnd]];
				$maidan_order_money_day = Db::name('maidan_order')->where($where)->sum('paymoney');
		        
		        // 本月金额（保留原逻辑）
		        unset($where[5]);
		        $where[] = ['paytime','between',[$thisMonthStart,$nowtime]];
				$maidan_order_money_month = Db::name('maidan_order')->where($where)->sum('paymoney');

				$info['wxpayCount'] = $shop_order_money + $maidan_order_money;
				$info['wxpayCount'] = round($info['wxpayCount'],2);

				$info['wxpayLastDayCount'] = $shop_order_money_day + $maidan_order_money_day;
				$info['wxpayLastDayCount'] = round($info['wxpayLastDayCount'],2);

				$info['wxpayThisMonthCount'] = $shop_order_money_month + $maidan_order_money_month;
				$info['wxpayThisMonthCount'] = round($info['wxpayThisMonthCount'],2);

				// 门店提现
				if(getcustom('mendian_hexiao_givemoney')){
					$info['show_tixian'] = 1;
					$where = [];
					$where[] = ['aid','=',aid];
					$where[] = ['bid','=',bid];
					$where[] = ['mdid','=',$this->user['mdid']];
					$where[] = ['status','=',3];
					$mendian_withdraw_money = Db::name('mendian_withdrawlog')->where($where)->sum('money');
		        	$where[] = ['createtime','between',[$lastDayStart,$lastDayEnd]];
		        	$mendian_withdraw_money_day = Db::name('mendian_withdrawlog')->where($where)->sum('money');
		        	unset($where[4]);
		        	$where[] = ['createtime','between',[$thisMonthStart,$nowtime]];
		        	$mendian_withdraw_money_month = Db::name('mendian_withdrawlog')->where($where)->sum('money');

		        	$info['withdrawCount'] = $mendian_withdraw_money;
		        	$info['withdrawCount'] = round($info['withdrawCount'],2);
		        	$info['withdrawLastDayCount'] = $mendian_withdraw_money_day;
		        	$info['withdrawLastDayCount'] = round($info['withdrawLastDayCount'],2);
		        	$info['withdrawThisMonthCount'] = $mendian_withdraw_money_month;
		        	$info['withdrawThisMonthCount'] = round($info['withdrawThisMonthCount'],2);
				}

			}else{
				// 按时间范围查询总金额
				$info['wxpayCount'] = Db::name('payorder')->where('aid',aid)->where('bid',bid)->where('status',1)->where('paytypeid','not in','1,4')->where('type','<>','daifu')->where('paytime','>=',$this->startTime)->where('paytime','<',$this->endTime)->sum('money');
				$info['wxpayCount'] = round($info['wxpayCount'],2);
				
				// 昨日金额（保留原逻辑）
				$info['wxpayLastDayCount'] = Db::name('payorder')->where('aid',aid)->where('bid',bid)->where('status',1)->where('paytypeid','not in','1,4')->where('type','<>','daifu')->where('paytime','>=',$lastDayStart)->where('paytime','<',$lastDayEnd)->sum('money');
				$info['wxpayLastDayCount'] = round($info['wxpayLastDayCount'],2);
				
				// 本月金额（保留原逻辑）
				$info['wxpayThisMonthCount'] = 0 + Db::name('payorder')->where('aid',aid)->where('bid',bid)->where('status',1)->where('paytypeid','not in','1,4')->where('paytime','>=',$thisMonthStart)->where('paytime','<',$nowtime)->sum('money');
				$info['wxpayThisMonthCount'] = round($info['wxpayThisMonthCount'],2);

				//提现金额
				$info['show_tixian'] = 1;
				// 按时间范围查询总提现金额
				$info['withdrawCount'] = Db::name('member_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$this->startTime)->where('createtime','<',$this->endTime)->sum('money') + Db::name('member_commission_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$this->startTime)->where('createtime','<',$this->endTime)->sum('money');
				$info['withdrawCount'] = round($info['withdrawCount'],2);
				
				// 昨日提现金额（保留原逻辑）
				$info['withdrawLastDayCount'] = Db::name('member_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$lastDayStart)->where('createtime','<',$lastDayEnd)->sum('money') + Db::name('member_commission_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$lastDayStart)->where('createtime','<',$lastDayEnd)->sum('money');
				$info['withdrawLastDayCount'] = round($info['withdrawLastDayCount'],2);
				
				// 本月提现金额（保留原逻辑）
				$info['withdrawThisMonthCount'] = Db::name('member_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$thisMonthStart)->where('createtime','<',$nowtime)->sum('money') + Db::name('member_commission_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$thisMonthStart)->where('createtime','<',$nowtime)->sum('money');
				$info['withdrawThisMonthCount'] = round($info['withdrawThisMonthCount'],2);
			}
			
		}else{
			//收款金额
			if($this->user['mdid']){
				// 普通订单
				// 按时间范围查询总金额
				$where   = [];
		        $where[] = ['aid','=',aid];
		        $where[] = ['bid','=',bid];
		        $where[] = ['mdid','=',$this->user['mdid']];
		        $where[] = ['status','in',[1,2,3]];
		        $where[] = ['paytypeid','<>',4];
		        $where[] = ['paytime','>=',$this->startTime];
		        $where[] = ['paytime','<',$this->endTime];
				$shop_order_money = Db::name('shop_order')->where($where)->sum('totalprice');
		        
		        // 昨日金额（保留原逻辑）
		        unset($where[5], $where[6]);
		        $where[] = ['paytime','between',[$lastDayStart,$lastDayEnd]];
				$shop_order_money_day = Db::name('shop_order')->where($where)->sum('totalprice');
		        
		        // 本月金额（保留原逻辑）
		        unset($where[5]);
		        $where[] = ['paytime','between',[$thisMonthStart,$nowtime]];
				$shop_order_money_month = Db::name('shop_order')->where($where)->sum('totalprice');
				
				// 快速买单
				// 按时间范围查询总金额
				$where   = [];
		        $where[] = ['aid','=',aid];
		        $where[] = ['bid','=',bid];
		        $where[] = ['mdid','=',$this->user['mdid']];
		        $where[] = ['status','=',1];
		        $where[] = ['paytypeid','<>',4];
		        $where[] = ['paytime','>=',$this->startTime];
		        $where[] = ['paytime','<',$this->endTime];
				$maidan_order_money = Db::name('maidan_order')->where($where)->sum('paymoney');
		        
		        // 昨日金额（保留原逻辑）
		        unset($where[5], $where[6]);
		        $where[] = ['paytime','between',[$lastDayStart,$lastDayEnd]];
				$maidan_order_money_day = Db::name('maidan_order')->where($where)->sum('paymoney');
		        
		        // 本月金额（保留原逻辑）
		        unset($where[5]);
		        $where[] = ['paytime','between',[$thisMonthStart,$nowtime]];
				$maidan_order_money_month = Db::name('maidan_order')->where($where)->sum('paymoney');

				$info['wxpayCount'] = $shop_order_money + $maidan_order_money;
				$info['wxpayCount'] = round($info['wxpayCount'],2);

				$info['wxpayLastDayCount'] = $shop_order_money_day + $maidan_order_money_day;
				$info['wxpayLastDayCount'] = round($info['wxpayLastDayCount'],2);

				$info['wxpayThisMonthCount'] = $shop_order_money_month + $maidan_order_money_month;
				$info['wxpayThisMonthCount'] = round($info['wxpayThisMonthCount'],2);
			}else{
				// 按时间范围查询总金额
				$info['wxpayCount'] = Db::name('payorder')->where('aid',aid)->where('bid',bid)->where('status',1)->where('paytypeid','<>','4')->where('paytime','>=',$this->startTime)->where('paytime','<',$this->endTime)->sum('money');
				$info['wxpayCount'] = round($info['wxpayCount'],2);
				
				// 昨日金额（保留原逻辑）
				$info['wxpayLastDayCount'] = Db::name('payorder')->where('aid',aid)->where('bid',bid)->where('status',1)->where('paytypeid','<>','4')->where('paytime','>=',$lastDayStart)->where('paytime','<',$lastDayEnd)->sum('money');
				$info['wxpayLastDayCount'] = round($info['wxpayLastDayCount'],2);
				
				// 本月金额（保留原逻辑）
				$info['wxpayThisMonthCount'] = 0 + Db::name('payorder')->where('aid',aid)->where('bid',bid)->where('status',1)->where('paytypeid','<>','4')->where('paytime','>=',$thisMonthStart)->where('paytime','<',$nowtime)->sum('money');
				$info['wxpayThisMonthCount'] = round($info['wxpayThisMonthCount'],2);
			}
			
		}
        $moeny_weishu = 2;
        if(getcustom('fenhong_money_weishu')){
            $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
            $moeny_weishu = $moeny_weishu?$moeny_weishu:2;
        }
		
		// 佣金数据 - 按时间范围过滤
		// 累计佣金总额（从member表sum字段获取，可能是累计值）
		$commissiontotal = Db::name('member')->where('aid',aid)->sum('totalcommission');
		$info['commissiontotal'] = dd_money_format($commissiontotal,$moeny_weishu);
		
		// 待提佣金（从member表sum字段获取，可能是累计值）
		$commission = Db::name('member')->where('aid',aid)->sum('commission');
        $info['commission'] = dd_money_format($commission,$moeny_weishu);
		
		// 已提佣金 - 按时间范围过滤
		$info['commissionwithdraw'] = Db::name('member_commission_withdrawlog')
			->where('aid',aid)
			->where('status',3)
			->where('createtime','>=',$this->startTime)
			->where('createtime','<',$this->endTime)
			->sum('txmoney');

		$rdata = [];
		$rdata['status'] = 1;

		//余额宝收益
		$rdata['showyuebao_moneylog'] = false;
		//余额宝提现
		$rdata['showyuebao_withdrawlog'] = false;
		if(getcustom('plug_yuebao')){
			if($this->user['auth_type']==0){
				$auth_data = json_decode($this->user['auth_data'],true);
				$auth_path = [];
				foreach($auth_data as $v){
					$auth_path = array_merge($auth_path,explode(',',$v));
				}
				$auth_data = $auth_path;
			}else{
				$auth_data = 'all';
			}
			if($auth_data=='all'){
				//余额宝收益
				$rdata['showyuebao_moneylog'] = true;
				//余额宝提现
				$rdata['showyuebao_withdrawlog'] = true;
			}else{
				if(in_array('Yuebao/*',$auth_data)){
					//余额宝收益
					$rdata['showyuebao_moneylog'] = true;
					//余额宝提现
					$rdata['showyuebao_withdrawlog'] = true;
				}else{
					if(in_array('Yuebao/moneylog',$auth_data)){
						//余额宝收益
						$rdata['showyuebao_moneylog'] = true;
					}
					if(in_array('Yuebao/withdrawlog',$auth_data)){
						//余额宝提现
						$rdata['showyuebao_withdrawlog'] = true;
					}
				}
			}
			// 余额宝提现金额 - 按时间范围过滤
			$info['yuebaowithdrawCount'] = Db::name('member_yuebao_withdrawlog')
				->where('aid',aid)
				->where('status',3)
				->where('createtime','>=',$this->startTime)
				->where('createtime','<',$this->endTime)
				->sum('money');
			$info['withdrawCount'] += round($info['yuebaowithdrawCount'],2);

			// 余额宝昨日提现金额（保留原逻辑）
			$info['yuebaowithdrawLastDayCount'] = Db::name('member_yuebao_withdrawlog')
				->where('aid',aid)
				->where('status',3)
				->where('createtime','>=',$lastDayStart)
				->where('createtime','<',$lastDayEnd)
				->sum('money');
			$info['withdrawLastDayCount'] += round($info['yuebaowithdrawLastDayCount'],2);

			// 余额宝本月提现金额（保留原逻辑）
			$info['yuebaowithdrawThisMonthCount'] = Db::name('member_yuebao_withdrawlog')
				->where('aid',aid)
				->where('status',3)
				->where('createtime','>=',$thisMonthStart)
				->where('createtime','<',$nowtime)
				->sum('money');
			$info['withdrawThisMonthCount'] += round($info['yuebaowithdrawThisMonthCount'],2);
		}

		$rdata['bid'] = bid;
		$rdata['mdid'] = $this->user['mdid'];
		$rdata['showmdmoney'] = 0;
		if(getcustom('mendian_hexiao_givemoney') && $this->user['mdid'] > 0){
			$rdata['showmdmoney'] = 1;
		}
		$rdata['showbscore'] = false;
		if((getcustom('business_selfscore') || getcustom('business_score_withdraw') || getcustom('business_score_jiesuan')) && bid > 0){
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			if($bset['business_selfscore'] == 1){
				$rdata['showbscore'] = true;
				$info['score'] = Db::name('business')->where('id',bid)->value('score');
			}
		}
		$rdata['showcouponmoney'] = false;
		if(getcustom('business_canuseplatcoupon')){
			$rdata['showcouponmoney'] = true;
		}
        $show = [];
        if(getcustom('admin_m_show_scorelog')){
            $show['scorelog'] = true;
        }
		$show['finance'] = true;
		if(getcustom('mendian_apply')){
			if($this->user['mdid'] > 0){
			    $show['finance'] = false;
			}
		}
        $show['showdepositlog'] = false;
        if(getcustom('business_deposit')){
            $show['showdepositlog'] = true;
        }
        //默认全部展示
        $mobile_index_data = ['all'];
        if (getcustom('plug_siming')) {
          $mobile_index_data = Db::name("admin")->where('id', aid)->value('mobile_index_data');
          $mobile_index_data = explode(',', $mobile_index_data);
        }
        $rdata['index_data'] = $mobile_index_data;

		$show['show_salesquota'] = false;
		if(getcustom('business_sales_quota')){
			if($this->user['bid'] > 0){
				$business =  Db::name('business')->field('sales_quota,total_sales_quota')->where('id',$this->user['bid'])->find();
			    $show['show_salesquota'] = true;
				$info['sales_quota'] = $business['sales_quota'];
				$info['total_sales_quota'] = $business['total_sales_quota'];
			}
		}

        $show['bonus_pool_gold'] = false;
        if(getcustom('bonus_pool_gold') && bid>0){
            //奖金池
            $show['bonus_pool_gold'] = true;
            $info['gold'] = Db::name('business')->where('id',bid)->value('gold');
            $info['gold_price'] = Db::name('bonuspool_gold_set')->where('aid',aid)->value('gold_price');
        }

        $rdata['show'] = $show;
		$rdata['info'] = $info;
       
        if(getcustom('finance_trade_report')){
            if($this->user['auth_type']==0){
                $auth_data = json_decode($this->user['auth_data'],true);
                $auth_path = [];
                foreach($auth_data as $v){
                    $auth_path = array_merge($auth_path,explode(',',$v));
                }
                $auth_data = $auth_path;
            }else{
                $auth_data = 'all';
            }
            if($auth_data =='all' || in_array('Payorder/tradereport',$auth_data)){
                $this->auth_data['tradereport'] = true;
            }
        }

        $rdata['auth_data'] = $this->auth_data;
        $rdata['wxauth_data'] = $this->user['wxauth_data'] ? json_decode($this->user['wxauth_data'],true) : [];

        // 菜单权限
        if($this->user['auth_type']==0){
            $auth_data = json_decode($this->user['auth_data'],true);
            $auth_path = [];
            foreach($auth_data as $v){
                $auth_path = array_merge($auth_path,explode(',',$v));
            }
            $auth_data = $auth_path;
        }else{
            $auth_data = ['all'];
        }
        $rdata['auth_data_menu'] = $auth_data;

        //手机端设置
        $designer_mobile = Db::name('designer_mobile')->where('aid',aid)->find();
        $designer_mobile_data = [];
        if ($designer_mobile && isset($designer_mobile['data'])) {
            $tmp = json_decode($designer_mobile['data'], true);
            if (is_array($tmp)) {
                $designer_mobile_data = $tmp;
            }
        }
        $set['bgimg'] = isset($designer_mobile_data['bgimg']) && $designer_mobile_data['bgimg'] ? $designer_mobile_data['bgimg'] : '';
        $icons_defaults = [
            'saoyisao'    => PRE_URL.'/static/img/admin/saoyisao.png',
            'setup'       => PRE_URL.'/static/img/admin/setup.png',
            'jieshiicon'  => PRE_URL.'/static/img/admin/jieshiicon.png',
            'menu1'       => PRE_URL.'/static/img/admin/menu1.png',
            'menu2'       => PRE_URL.'/static/img/admin/menu2.png',
            'menu3'       => PRE_URL.'/static/img/admin/menu3.png',
            'menu4'       => PRE_URL.'/static/img/admin/menu4.png',
            'menu5'       => PRE_URL.'/static/img/admin/menu5.png',
            'titletips'   => PRE_URL.'/static/img/admin/titletips.png',
            'jiantou'     => PRE_URL.'/static/img/admin/jiantou.png',
            'financenbg1' => PRE_URL.'/static/img/admin/financenbg1.png',
            'financenbg2' => PRE_URL.'/static/img/admin/financenbg2.png',
            'financenbg3' => PRE_URL.'/static/img/admin/financenbg3.png',
            'financenbg4' => PRE_URL.'/static/img/admin/financenbg4.png',
            'financenbg5' => PRE_URL.'/static/img/admin/financenbg5.png',
            'financenbg6' => PRE_URL.'/static/img/admin/financenbg6.png',
            'financenbg7' => PRE_URL.'/static/img/admin/financenbg7.png',
            'financenbg8' => PRE_URL.'/static/img/admin/financenbg8.png',
            'financenbg9' => PRE_URL.'/static/img/admin/financenbg9.png',
            'financejiantou' => PRE_URL.'/static/img/admin/financejiantou.png',
            'financenbg10' => PRE_URL.'/static/img/admin/financenbg10.png',
            'wm5'         => PRE_URL.'/static/img/admin/wm5.png',
            'wm7'         => PRE_URL.'/static/img/admin/wm7.png',
            'wm2'         => PRE_URL.'/static/img/admin/wm2.png',
            'wm1'         => PRE_URL.'/static/img/admin/wm1.png',
            'dismendian'  => PRE_URL.'/static/img/admin/dismendian.png',
            'dishm6'      => PRE_URL.'/static/img/admin/dishm6.png',
            'dishm8'      => PRE_URL.'/static/img/admin/dishm8.png',
            'zixun'       => PRE_URL.'/static/img/admin/zixun.png',
            'member'      => PRE_URL.'/static/img/admin/member.png',
            'finance'     => PRE_URL.'/static/img/admin/finance.png',
            'finance2'    => PRE_URL.'/static/img/admin/finance2.png',
            'my'          => PRE_URL.'/static/img/admin/my.png',
            'my2'         => PRE_URL.'/static/img/admin/my2.png',
            'right_black' => PRE_URL.'/static/img/location/right-black.png',
        ];
        // ensure defaults exist in DB, then always prefer DB values
        $needs_update = false;
        foreach($icons_defaults as $k=>$v){
            if(!isset($designer_mobile_data[$k]) || !$designer_mobile_data[$k]){
                $designer_mobile_data[$k] = $v;
                $needs_update = true;
            }
        }
        if($needs_update && $designer_mobile){
            Db::name('designer_mobile')->where('aid',aid)->update([
                'data' => jsonEncode($designer_mobile_data),
                'updatetime' => time(),
            ]);
        }
        $set['icons'] = $designer_mobile_data;
        $rdata['set'] = $set;

		return $this->json($rdata);
	}
	//余额充值记录
	function rechargelog(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$order = 'id desc';
		$where = [];
		$where[] = ['recharge_order.aid','=',aid];
        if(!getcustom('money_recharge_transfer')){
            $where[] = ['recharge_order.status','=',1];
        }else{
            $where[] = ['recharge_order.paytype','<>','null'];
        }

		if(input('param.keyword')) $where[] = ['member.nickname','like','%'.trim(input('param.keyword')).'%'];
		$datalist = Db::name('recharge_order')->alias('recharge_order')->field('member.nickname,member.headimg,recharge_order.*')->join('member member','member.id=recharge_order.mid')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
		if($pagenum==1){
			$count = 0 + Db::name('recharge_order')->alias('recharge_order')->field('member.nickname,member.headimg,recharge_order.*')->join('member member','member.id=recharge_order.mid')->where($where)->count();
		}
        foreach ($datalist as &$v){
            if(getcustom('money_recharge_transfer')){
                $v['money_recharge_transfer'] = true;
                $v['payorder_check_status'] = Db::name('payorder')->where('aid',aid)->where('type','recharge')->where('orderid',$v['id'])->value('check_status');
            }

            if($v['status']==1){
                $v['status_name'] = '充值成功';
            }else{
                if(getcustom('money_recharge_transfer') && $v['paytypeid'] == 5 && $v['paytype'] != '随行付支付'){
                    if($v['transfer_check'] == 1){
                        if($v['payorder_check_status'] == 2){
                            $v['status_name'] = '凭证被驳回';
                        }else if($v['payorder_check_status'] == 1){
                            $v['status_name'] = '审核通过';
                        }else{
                            $v['status_name'] = '凭证待审核';
                        }
                    }else if($v['transfer_check'] == -1){
                        $v['status_name'] = '已驳回';
                    }else {
                        $v['status_name'] = '待审核';
                    }
                }else{
                    $v['status_name'] = '充值失败';
                }
            }
        }
		return $this->json(['status'=>1,'count'=>$count,'data'=>$datalist]);
	}
    public function getrechargeorderdetail()
    {
        $orderid = input('param.orderid');
        $order = Db::name('recharge_order')->where('aid',aid)->where('id',$orderid)->find();
        $payorder = Db::name('payorder')->where('id',$order['payorderid'])->where('type','recharge')->where('aid',aid)->find();
        if($order['paytypeid'] == 5) {
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
        return $this->json(['status'=>1,'order'=>$order,'payorder' => $payorder]);
    }
    //转账审核
    public function transferCheck(){
        if(getcustom('money_recharge_transfer')){
            $orderid = input('post.orderid/d');
            $st = input('post.st/d');

            $order = Db::name('recharge_order')->where('id',$orderid)->where('aid',aid)->field('id,status')->find();
            if($order['status']!=0){
                return $this->json(['status'=>0,'msg'=>'该订单状态不允许审核']);
            }

            if($st==1){
                $up = Db::name('recharge_order')->where('id',$orderid)->where('aid',aid)->update(['transfer_check'=>1]);
                if($up){
                    \app\common\System::plog('余额充值订单转账审核驳回'.$orderid);
                    return $this->json(['status'=>1,'msg'=>'审核通过']);
                }else{
                    return $this->json(['status'=>0,'msg'=>'操作失败']);
                }
            }else{
                $up = Db::name('recharge_order')->where('id',$orderid)->where('aid',aid)->update(['transfer_check'=>-1]);
                if($up){
                    \app\common\System::plog('余额充值订单转账审核通过'.$orderid);
                    return $this->json(['status'=>1,'msg'=>'转账已驳回']);
                }else{
                    return $this->json(['status'=>0,'msg'=>'操作失败']);
                }
            }
        }
    }
    //付款审核
    public function payCheck(){
        if(getcustom('money_recharge_transfer')){
            $orderid = input('post.orderid/d');
            $st = input('post.st/d');
            $remark = input('post.remark');
            $order = Db::name('recharge_order')->where('id',$orderid)->where('aid',aid)->find();

            if($order['status']!=0){
                return $this->json(['status'=>0,'msg'=>'该订单状态不允许审核付款']);
            }

            if($st==2){
                $check_data = [];
                $check_data['check_status'] = 2;
                if($remark){
                    $check_data['check_remark'] = $remark;
                }
                Db::name('payorder')->where('id',$order['payorderid'])->where('aid',aid)->where('type','recharge')->update($check_data);
                \app\common\System::plog('余额充值订单付款审核驳回'.$orderid);
                return $this->json(['status'=>1,'msg'=>'付款已驳回']);
            }elseif($st == 1){
                $check_data = [];
                $check_data['check_status'] = 1;
                if($remark){
                    $check_data['check_remark'] = $remark;
                }
                \app\model\Payorder::payorder($order['payorderid'],t('转账汇款'),5,'');
                Db::name('payorder')->where('id',$order['payorderid'])->where('type','recharge')->where('aid',aid)->update($check_data);

                Db::name('recharge_order')->where('id',$orderid)->where('aid',aid)->update(['status'=>1,'paytime' => time()]);

                \app\common\System::plog('余额充值订单付款审核通过'.$orderid);
                return $this->json(['status'=>1,'msg'=>'审核通过']);
            }
        }
    }
	//余额明细
	function moneylog(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$order = 'id desc';
		$pernum = 20;
		$where = [];
		$where[] = ['member_moneylog.aid','=',aid];
		if(input('param.keyword')) $where[] = ['member.nickname','like','%'.trim(input('param.keyword')).'%'];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_moneylog.status','=',input('param.status')];
		$datalist = Db::name('member_moneylog')->alias('member_moneylog')->field('member.nickname,member.headimg,member_moneylog.*')->join('member member','member.id=member_moneylog.mid')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
		if($pagenum==1){
			$count = 0 + Db::name('member_moneylog')->alias('member_moneylog')->field('member.nickname,member.headimg,member_moneylog.*')->join('member member','member.id=member_moneylog.mid')->where($where)->count();
		}
		return $this->json(['status'=>1,'count'=>$count,'data'=>$datalist]);
	}
	//佣金明细
	function commissionlog(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$order = 'id desc';
		$pernum = 20;
		$where = [];
		$where[] = ['member_commissionlog.aid','=',aid];
		if(input('param.keyword')) $where[] = ['member.nickname','like','%'.trim(input('param.keyword')).'%'];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_commissionlog.status','=',input('param.status')];
		$datalist = Db::name('member_commissionlog')->alias('member_commissionlog')->field('member.nickname,member.headimg,member_commissionlog.*')->join('member member','member.id=member_commissionlog.mid')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
		if($pagenum==1){
			$count = 0 + Db::name('member_commissionlog')->alias('member_commissionlog')->field('member.nickname,member.headimg,member_commissionlog.*')->join('member member','member.id=member_commissionlog.mid')->where($where)->count();
		}
		return $this->json(['status'=>1,'count'=>$count,'data'=>$datalist]);
	}

    //明细
    function scoreloglist(){
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $order = 'id desc';
        $pernum = 20;
        $where = [];
        $where[] = ['member_scorelog.aid','=',aid];
        if(bid > 0){
            $where[] = ['member_scorelog.bid','=',bid];
        }
        if(input('param.keyword')) $where[] = ['member.nickname','like','%'.trim(input('param.keyword')).'%'];
        if(input('?param.status') && input('param.status')!=='') $where[] = ['member_scorelog.status','=',input('param.status')];
        $datalist = Db::name('member_scorelog')->alias('member_scorelog')->field('member.nickname,member.headimg,member_scorelog.*')->join('member member','member.id=member_scorelog.mid')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
        if($pagenum==1){
            $count = 0 + Db::name('member_scorelog')->alias('member_scorelog')->field('member.nickname,member.headimg,member_scorelog.*')->join('member member','member.id=member_scorelog.mid')->where($where)->count();
        }
        return $this->json(['status'=>1,'count'=>$count,'data'=>$datalist]);
    }
	//余额提现记录
	function withdrawlog(){
		$pagenum = input('post.pagenum');
		$st = input('post.st');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['withdrawlog.aid','=',aid];
		if($st == 'all'){

		}elseif($st == '0'){
			$where[] = ['status','=',0];
		}elseif($st == '1'){
			$where[] = ['status','=',1];
		}elseif($st == '2'){
			$where[] = ['status','=',2];
		}elseif($st == '3'){
			$where[] = ['status','=',3];
		}

		if(input('param.keyword')) $where[] = ['member.nickname','like','%'.trim(input('param.keyword')).'%'];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['withdrawlog.status','=',input('param.status')];
		$datalist = Db::name('member_withdrawlog')->alias('withdrawlog')->field('member.nickname,member.headimg,withdrawlog.*')->join('member member','member.id=withdrawlog.mid')->where($where)->page($pagenum,$pernum)->order('withdrawlog.id desc')->select()->toArray();
		if($pagenum==1){
			$count = 0 + Db::name('member_withdrawlog')->alias('withdrawlog')->field('member.nickname,member.headimg,withdrawlog.*')->join('member member','member.id=withdrawlog.mid')->where($where)->count();
		}
		return $this->json(['status'=>1,'count'=>$count,'data'=>$datalist]);
	}
	//余额提现明细
	function withdrawdetail(){
		$id = input('param.id/d');
		$info = Db::name('member_withdrawlog')->where(['aid'=>aid,'id'=>$id])->find();
		$member = Db::name('member')->where(['id'=>$info['mid']])->find();
		$info['nickname'] = $member['nickname'];
		$info['headimg'] = $member['headimg'];
		return $this->json(['status'=>1,'info'=>$info]);
	}
	//余额提现审核通过
	function widthdrawpass(){
		$id = input('post.id/d');
		$info = Db::name('member_withdrawlog')->where(['aid'=>aid,'id'=>$id])->update(['status'=>1,'reason'=>'']);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	//余额提现审核不通过
	function widthdrawnopass(){
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('member_withdrawlog')->where(['aid'=>aid,'id'=>$id])->update(['status'=>2,'reason'=>$reason]);
		$info = Db::name('member_withdrawlog')->where(['aid'=>aid,'id'=>$id])->find();
		\app\common\Member::addmoney(aid,$info['mid'],$info['txmoney'],t('余额').'提现返还');
		//提现失败通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的提现申请被商家驳回，可与商家协商沟通。';
		$tmplcontent['remark'] = $reason.'，请点击查看详情~';
		$tmplcontent['money'] = (string) $info['txmoney'];
		$tmplcontent['time'] = date('Y-m-d H:i',$info['createtime']);
		\app\common\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixianerror',$tmplcontent,m_url('/pages/my/usercenter'));
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['amount1'] = $info['txmoney'];
		$tmplcontent['time3'] = date('Y-m-d H:i',$info['createtime']);
		$tmplcontent['thing4'] = $reason;
		
		$tmplcontentnew = [];
		$tmplcontentnew['thing1'] = '提现失败';
		$tmplcontentnew['amount2'] = $info['txmoney'];
		$tmplcontentnew['date4'] = date('Y-m-d H:i',$info['createtime']);
		$tmplcontentnew['thing12'] = $reason;
		\app\common\Wechat::sendwxtmpl(aid,$info['mid'],'tmpl_tixianerror',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
		//短信通知
		$member = Db::name('member')->where(['id'=>$info['mid']])->find();
		if($member['tel']){
			\app\common\Sms::send(aid,$member['tel'],'tmpl_tixianerror',['reason'=>$reason]);
		}
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	//余额提现改为打款
	function widthdsetydk(){
		$id = input('post.id/d');
		Db::name('member_withdrawlog')->where(['aid'=>aid,'id'=>$id])->update(['status'=>3,'reason'=>'']);
		$info = Db::name('member_withdrawlog')->where(['aid'=>aid,'id'=>$id])->find();
        $info['money'] = dd_money_format($info['money']);
		//提现成功通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['money'] = (string) $info['money'];
		$tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
        $tempconNew = [];
        $tempconNew['amount2'] = (string) $info['money'];//提现金额
        $tempconNew['time3'] = date('Y-m-d H:i',$info['createtime']);//提现时间
		\app\common\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['amount1'] = $info['money'];
		$tmplcontent['thing3'] = $info['paytype'];
		$tmplcontent['time5'] = date('Y-m-d H:i');
		
		$tmplcontentnew = [];
		$tmplcontentnew['amount3'] = $info['money'];
		$tmplcontentnew['phrase9'] = $info['paytype'];
		$tmplcontentnew['date8'] = date('Y-m-d H:i');
		\app\common\Wechat::sendwxtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
		//短信通知
		$member = Db::name('member')->where(['id'=>$info['mid']])->find();
		if($member['tel']){
			\app\common\Sms::send(aid,$member['tel'],'tmpl_tixiansuccess',['money'=>$info['money']]);
		}
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	//余额提现 微信打款
	function widthdwxdakuan(){
		$id = input('post.id/d');
		$info = Db::name('member_withdrawlog')->where(['aid'=>aid,'id'=>$id])->find();
		if($info['status']!=1) return $this->json(['status'=>0,'msg'=>'已审核状态才能打款']);
        $info['money'] =  dd_money_format($info['money']);
		$rs = \app\common\Wxpay::transfers(aid,$info['mid'],$info['money'],$info['ordernum'],$info['platform'],t('余额').'提现');
		if($rs['status']==0){
			return $this->json(['status'=>0,'msg'=>$rs['msg']]);
		}else{
			Db::name('member_withdrawlog')->where(['aid'=>aid,'id'=>$id])->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
			//提现成功通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
			$tmplcontent['remark'] = '请点击查看详情~';
			$tmplcontent['money'] = (string) $info['money'];
			$tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
            $tempconNew = [];
            $tempconNew['amount2'] = (string) $info['money'];//提现金额
            $tempconNew['time3'] = date('Y-m-d H:i',$info['createtime']);//提现时间
			\app\common\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['amount1'] = $info['money'];
			$tmplcontent['thing3'] = $info['paytype'];
			$tmplcontent['time5'] = date('Y-m-d H:i');
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $info['money'];
			$tmplcontentnew['phrase9'] = $info['paytype'];
			$tmplcontentnew['date8'] = date('Y-m-d H:i');
			\app\common\Wechat::sendwxtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
			//短信通知
			$member = Db::name('member')->where(['id'=>$info['mid']])->find();
			if($member['tel']){
				$tel = $member['tel'];
				\app\common\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$info['money']]);
			}
			return $this->json(['status'=>1,'msg'=>$rs['msg']]);
		}
	}
	//佣金提现记录
	function comwithdrawlog(){
		$pagenum = input('post.pagenum');
		$st = input('post.st');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['withdrawlog.aid','=',aid];
		if($st == 'all'){

		}elseif($st == '0'){
			$where[] = ['status','=',0];
		}elseif($st == '1'){
			$where[] = ['status','=',1];
		}elseif($st == '2'){
			$where[] = ['status','=',2];
		}elseif($st == '3'){
			$where[] = ['status','=',3];
		}

		if(input('param.keyword')) $where[] = ['member.nickname','like','%'.trim(input('param.keyword')).'%'];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['withdrawlog.status','=',input('param.status')];
		$datalist = Db::name('member_commission_withdrawlog')->alias('withdrawlog')->field('member.nickname,member.headimg,withdrawlog.*')->join('member member','member.id=withdrawlog.mid')->where($where)->page($pagenum,$pernum)->order('withdrawlog.id desc')->select()->toArray();
		if($pagenum==1){
			$count = 0 + Db::name('member_commission_withdrawlog')->alias('withdrawlog')->field('member.nickname,member.headimg,withdrawlog.*')->join('member member','member.id=withdrawlog.mid')->where($where)->count();
		}
		return $this->json(['status'=>1,'count'=>$count,'data'=>$datalist]);
	}
	function comwithdrawdetail(){
		$id = input('param.id/d');
		$info = Db::name('member_commission_withdrawlog')->where(['aid'=>aid,'id'=>$id])->find();

		$comwithdrawbl = Db::name('admin_set')->where('aid',aid)->value('comwithdrawbl');
		if($comwithdrawbl > 0 && $comwithdrawbl < 100){
			$money = $info['money'];
			$info['money'] = round($money * $comwithdrawbl * 0.01,2);
			$info['tomoney'] = round($money - $info['money'],2);
		}else{
			$info['tomoney'] = 0;
		}

		$member = Db::name('member')->where(['id'=>$info['mid']])->find();
		$info['nickname'] = $member['nickname'];
		$info['headimg'] = $member['headimg'];
		return $this->json(['status'=>1,'info'=>$info]);
	}
	function comwidthdrawpass(){
		$id = input('post.id/d');
		$info = Db::name('member_commission_withdrawlog')->where(['aid'=>aid,'id'=>$id])->update(['status'=>1]);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	function comwidthdrawnopass(){
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('member_commission_withdrawlog')->where(['aid'=>aid,'id'=>$id])->update(['status'=>2,'reason'=>$reason]);
		$info = Db::name('member_commission_withdrawlog')->where(['aid'=>aid,'id'=>$id])->find();
		\app\common\Member::addcommission(aid,$info['mid'],0,$info['txmoney'],t('佣金').'提现返还',0,'withdraw_back');
		//提现失败通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的提现申请被商家驳回，可与商家协商沟通。';
		$tmplcontent['remark'] = $reason.'，请点击查看详情~';
		$tmplcontent['money'] = (string) $info['txmoney'];
		$tmplcontent['time'] = date('Y-m-d H:i',$info['createtime']);
		\app\common\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixianerror',$tmplcontent,m_url('activity/commission/commissionlog?st=1'));
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['amount1'] = $info['txmoney'];
		$tmplcontent['time3'] = date('Y-m-d H:i',$info['createtime']);
		$tmplcontent['thing4'] = $reason;

		$tmplcontentnew = [];
		$tmplcontentnew['thing1'] = '提现失败';
		$tmplcontentnew['amount2'] = $info['txmoney'];
		$tmplcontentnew['date4'] = date('Y-m-d H:i',$info['createtime']);
		$tmplcontentnew['thing12'] = $reason;
		\app\common\Wechat::sendwxtmpl(aid,$info['mid'],'tmpl_tixianerror',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
		//短信通知
		$member = Db::name('member')->where(['id'=>$info['mid']])->find();
		if($member['tel']){
			$tel = $member['tel'];
			\app\common\Sms::send(aid,$tel,'tmpl_tixianerror',['reason'=>$reason]);
		}
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	function comwidthdsetydk(){
		$id = input('post.id/d');

		$info = Db::name('member_commission_withdrawlog')->where(['aid'=>aid,'id'=>$id])->find();

		Db::name('member_commission_withdrawlog')->where(['aid'=>aid,'id'=>$id])->update(['status'=>3]);

		if(getcustom('fengdanjiangli')){
			$tomoney = $info['tomoney']??0;
			// 提现时计算$tomoney
			if($tomoney > 0){
				\app\common\Member::addmoney(aid,$info['mid'],$tomoney,t('佣金').'提现');
			}
		}

        $info['money'] =dd_money_format($info['money']);
		//提现成功通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['money'] = (string) $info['money'];
		$tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
        $tempconNew = [];
        $tempconNew['amount2'] = (string) $info['money'];//提现金额
        $tempconNew['time3'] = date('Y-m-d H:i',$info['createtime']);//提现时间
		\app\common\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['amount1'] = $info['money'];
		$tmplcontent['thing3'] = $info['paytype'];
		$tmplcontent['time5'] = date('Y-m-d H:i');
		
		$tmplcontentnew = [];
		$tmplcontentnew['amount3'] = $info['money'];
		$tmplcontentnew['phrase9'] = $info['paytype'];
		$tmplcontentnew['date8'] = date('Y-m-d H:i');
		\app\common\Wechat::sendwxtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
		//短信通知
		$member = Db::name('member')->where(['id'=>$info['mid']])->find();
		if($member['tel']){
			$tel = $member['tel'];
			\app\common\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$info['money']]);
		}
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	function comwidthdwxdakuan(){
		$id = input('post.id/d');
		$info = db('member_commission_withdrawlog')->where(['aid'=>aid,'id'=>$id])->find();
		if($info['status']!=1) return ['status'=>0,'msg'=>'已审核状态才能打款'];
        $info['money'] =dd_money_format($info['money']);
		$comwithdrawbl = Db::name('admin_set')->where('aid',aid)->value('comwithdrawbl');
		if($comwithdrawbl > 0 && $comwithdrawbl < 100){
			$paymoney = round($info['money'] * $comwithdrawbl * 0.01,2);
			$tomoney = round($info['money'] - $paymoney,2);
		}else{
			$paymoney = $info['money'];
			$tomoney = 0;
		}

		$rs = \app\common\Wxpay::transfers(aid,$info['mid'],$paymoney,$info['ordernum'],$info['platform'],t('佣金').'提现');
		if($rs['status']==0){
			return $this->json(['status'=>0,'msg'=>$rs['msg']]);
		}else{
			if($tomoney > 0){
				\app\common\Member::addmoney(aid,$info['mid'],$tomoney,t('佣金').'提现');
			}
			Db::name('member_commission_withdrawlog')->where(['aid'=>aid,'id'=>$id])->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
			//提现成功通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
			$tmplcontent['remark'] = '请点击查看详情~';
			$tmplcontent['money'] = (string) $info['money'];
			$tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
            $tempconNew = [];
            $tempconNew['amount2'] = (string) $info['money'];//提现金额
            $tempconNew['time3'] = date('Y-m-d H:i',$info['createtime']);//提现时间
			\app\common\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['amount1'] = $info['money'];
			$tmplcontent['thing3'] = $info['paytype'];
			$tmplcontent['time5'] = date('Y-m-d H:i');
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $info['money'];
			$tmplcontentnew['phrase9'] = $info['paytype'];
			$tmplcontentnew['date8'] = date('Y-m-d H:i');
			\app\common\Wechat::sendwxtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
			//短信通知
			$member = Db::name('member')->where(['id'=>$info['mid']])->find();
			if($member['tel']){
				$tel = $member['tel'];
				\app\common\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$info['money']]);
			}

			return $this->json(['status'=>1,'msg'=>$rs['msg']]);
		}
	}

	//商家余额明细
	public function bmoneylog(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		$datalist = Db::name('business_moneylog')->field("id,money,`after`,createtime,remark")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	//商家余额提现记录
	public function bwithdrawlog(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		$datalist = Db::name('business_withdrawlog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	//提现信息设置
	public function txset(){
		if(request()->isPost()){
			$postinfo = input('post.');
			$data = [];
			$data['weixin'] = $postinfo['weixin'];
			if( getcustom('alipay_auto_transfer')){
                $data['aliaccountname'] = $postinfo['aliaccountname'];
            }
			$data['aliaccount'] = $postinfo['aliaccount'];
			$data['bankname'] = $postinfo['bankname'];
			$data['bankcarduser'] = $postinfo['bankcarduser'];
			$data['bankcardnum'] = $postinfo['bankcardnum'];
			Db::name('business')->where('id',bid)->update($data);
			return $this->json(['status'=>1,'msg'=>'保存成功']);
		}
		$field = 'id,weixin,aliaccount,bankname,bankcarduser,bankcardnum';
		if(getcustom('alipay_auto_transfer')){
		    $field.=',aliaccountname';
        }
		$info = Db::name('business')->field($field)->where(['id'=>bid])->find();
        if(getcustom('alipay_auto_transfer')){
            $info['show_aliaccountname']  =1;
        }
		return $this->json(['status'=>1,'info'=>$info]);
	}
	public function bwithdraw(){
	    $field = 'withdrawmin,withdrawfee,withdraw_weixin,withdraw_aliaccount,withdraw_bankcard,commission_autotransfer';
	    if(getcustom('business_withdraw_otherset')){
            $field .=',withdrawmax,day_withdraw_num';
        }
		$set = Db::name('business_sysset')->where(['aid'=>aid])->field($field)->find();
        $wx_transfer_type = Db::name('admin_set')->where('aid',aid)->value('wx_transfer_type');
		if(request()->isPost()){
			$post = input('post.');
			//if($set['withdraw'] == 0){
			//	return ['status'=>0,'msg'=>'余额提现功能未开启'];
			//}
			$binfo = Db::name('business')->where('id',bid)->find();
			if($post['paytype']=='支付宝' && $binfo['aliaccount']==''){
				return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
			}
            if($post['paytype']=='支付宝'){
                if(getcustom('alipay_auto_transfer') && $binfo['aliaccountname']==''){
                    return $this->json(['status'=>0,'msg'=>'请设置支付宝户名']);
                }
            }

			if($post['paytype']=='银行卡' && ($binfo['bankname']==''||$binfo['bankcarduser']==''||$binfo['bankcardnum']=='')){
				return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
			}

			$money = $post['money'];
			if($money<=0 || $money < $set['withdrawmin']){
				return $this->json(['status'=>0,'msg'=>'提现金额必须大于'.($set['withdrawmin']?$set['withdrawmin']:0)]);
			}
            if(getcustom('business_withdraw_otherset')){
                if($set['withdrawmax']>0 && $money > $set['withdrawmax']){
                    return json(['status'=>0,'msg'=>'提现金额过大，单笔'.t('余额').'提现最高金额为'.$set['withdrawmax'].'元']);
                }
                if($set['day_withdraw_num']<0){
                    return json(['status'=>0,'msg'=>'暂时不可提现']);
                }else if($set['day_withdraw_num']>0){
                    $start_time = strtotime(date('Y-m-d 00:00:01'));
                    $end_time = strtotime(date('Y-m-d 23:59:59'));
                    $day_withdraw_num = 0 + Db::name('business_withdrawlog')->where('aid',aid)->where('bid',bid)->where('createtime','between',[$start_time,$end_time])->count();
                    $daynum = $day_withdraw_num+1;
                    if($daynum>$set['day_withdraw_num']){
                        return json(['status'=>0,'msg'=>'今日申请提现次数已满，请明天继续申请提现']);
                    }
                }
            }
			if($money > $binfo['money']){
				return $this->json(['status'=>0,'msg'=>'可提现余额不足']);
			}

			$ordernum = date('ymdHis').aid.rand(1000,9999);
			$record['aid'] = aid;
			$record['bid'] = bid;
			$record['createtime']= time();
			$record['money'] = dd_money_format($money*(1-$set['withdrawfee']*0.01));
			$record['txmoney'] = $money;
			$record['ordernum'] = $ordernum;
			$record['paytype'] = $post['paytype'];
			if($post['paytype']=='微信' || $post['paytype']=='微信钱包'){
				if($set['commission_autotransfer']==1 && $wx_transfer_type==0){
					\app\common\Business::addmoney(aid,bid,-$money,'余额提现');
					$mid = Db::name('admin_user')->where('aid',aid)->where('bid',bid)->where('isadmin',1)->value('mid');
					if(!$mid) return json(['status'=>0,'msg'=>'商户主管理员未绑定微信']);
                    $rs = \app\common\Wxpay::transfers(aid,$mid,$record['money'],$record['ordernum'],'','余额提现');
					if($rs['status']==0){
						\app\common\Business::addmoney(aid,bid,$money,'余额提现失败返还');
						return json(['status'=>0,'msg'=>$rs['msg']]);
					}else{
						$record['weixin'] = t('会员').'ID：'.$mid;
						$record['status'] = 3;
						$record['paytime'] = time();
						$record['paynum'] = $rs['resp']['payment_no'];
						$id = db('business_withdrawlog')->insertGetId($record);

						//提现成功通知
						$tmplcontent = [];
						$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
						$tmplcontent['remark'] = '请点击查看详情~';
						$tmplcontent['money'] = (string) $record['money'];
						$tmplcontent['timet'] = date('Y-m-d H:i',$record['createtime']);
                        $tempconNew = [];
                        $tempconNew['amount2'] = (string) $record['money'];//提现金额
                        $tempconNew['time3'] = date('Y-m-d H:i',$record['createtime']);//提现时间
						\app\common\Wechat::sendtmpl(aid,$mid,'tmpl_tixiansuccess',$tmplcontent,m_url('admin/index/index'),$tempconNew);
						//短信通知
						$member = Db::name('member')->where('id',$mid)->find();
						if($member['tel']){
							$tel = $member['tel'];
							\app\common\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$record['money']]); 
						}
						return json(['status'=>1,'msg'=>$rs['msg']]);
					}
				}
				if($binfo['weixin']==''){
					return json(['status'=>0,'msg'=>'请填写完整提现信息','url'=>'txset']);
				}
				$record['weixin'] = $binfo['weixin'];
			}
			if($post['paytype']=='支付宝'){
                if($set['commission_autotransfer']==1){
                    $rs = \app\common\Alipay::transfers(aid,$record['ordernum'],$money,t('余额').'提现',$binfo['aliaccount'],$binfo['aliaccountname'],t('余额').'提现');
                    if($rs && $rs['status']==1){
                        \app\common\Business::addmoney(aid,bid,-$money,'余额提现');
                        $record['aliaccount'] =$binfo['aliaccount'] ;
                        $record['status'] = 3;
                        $record['paytime'] = time();
                        $record['paynum'] = $rs['resp']['payment_no'];
                        $id = db('business_withdrawlog')->insertGetId($record);
                        \app\common\System::plog('商家提现支付宝打款'.$id);
                        return json(['status'=>1,'msg'=>$rs['msg'],'url'=>(string)url('withdrawlog')]);
                    }
                    if($rs && $rs['sub_msg'] && $rs['status']==0){
                        $record['reason']  =  $rs['sub_msg'];
                    }
                }
				$record['aliaccount'] = $binfo['aliaccount'];
			}
			if($post['paytype']=='银行卡'){
				$record['bankname'] = $binfo['bankname'];
				$record['bankcarduser'] = $binfo['bankcarduser'];
				$record['bankcardnum'] = $binfo['bankcardnum'];
			}
            $record['platform'] = platform;
			$recordid = db('business_withdrawlog')->insertGetId($record);

			\app\common\Business::addmoney(aid,bid,-$money,'余额提现');

            $need_confirm = 0;

            if($set['commission_autotransfer']==1 && $wx_transfer_type==1){
                //使用了新版的商家转账功能
                $paysdk = new WxPayV3(aid,mid,platform);
                $rs = $paysdk->transfer($record['ordernum'],$record['txmoney'],'','余额提现','business_withdrawlog',$recordid);
                if($rs['status']==1){
                    $data = [
                        'status' => '4',//状态改为处理中，用户确认收货后再改为已打款
                        'wx_package_info' => $rs['data']['package_info'],//用户确认页面的信息
                        'wx_state' => $rs['data']['state'],//转账状态
                        'wx_transfer_bill_no' => $rs['data']['transfer_bill_no'],//微信单号
                    ];
                    Db::name('business_withdrawlog')->where('id',$recordid)->update($data);
                    $need_confirm = 1;
                }else{
                    $data = [
                        'wx_transfer_msg' => $rs['msg'],
                    ];
                    Db::name('business_withdrawlog')->where('id',$recordid)->update($data);
                }
            }
			return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款','need_confirm'=>$need_confirm,'id'=>$recordid]);
		}
		$field  ='id,money,weixin,aliaccount,bankname,bankcarduser,bankcardnum';
        if(getcustom('alipay_auto_transfer')){
            $field.=',aliaccountname';
        }
		$userinfo = db('business')->where(['id'=>bid])->field($field)->find();

		$rdata = [];
		$rdata['userinfo'] = $userinfo;

		if(getcustom('alipay_auto_transfer')){
			//提现说明
			$withdraw_desc = Db::name('admin_set')->where('aid',aid)->value('withdraw_desc');
			$set['withdraw_desc'] = $withdraw_desc?$withdraw_desc:'';
		}
		$rdata['sysset'] = $set;
		return $this->json($rdata);
	}
	//余额宝明细
	function yuebaolog(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$order = 'id desc';
		$pernum = 20;
		$where = [];
		$where[] = ['member_moneylog.aid','=',aid];
		if(input('param.keyword')) $where[] = ['member.nickname','like','%'.trim(input('param.keyword')).'%'];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_moneylog.status','=',input('param.status')];
		$datalist = Db::name('member_yuebao_moneylog')->alias('member_moneylog')->field('member.nickname,member.headimg,member_moneylog.*')->join('member member','member.id=member_moneylog.mid')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
		if($pagenum==1){
			$count = 0 + Db::name('member_yuebao_moneylog')->alias('member_moneylog')->field('member.nickname,member.headimg,member_moneylog.*')->join('member member','member.id=member_moneylog.mid')->where($where)->count();
		}
		return $this->json(['status'=>1,'count'=>$count,'data'=>$datalist]);
	}
	//余额宝提现记录
	function yuebaowithdrawlog(){
		$pagenum = input('post.pagenum');
		$st = input('post.st');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['withdrawlog.aid','=',aid];
		if($st == 'all'){

		}elseif($st == '0'){
			$where[] = ['status','=',0];
		}elseif($st == '1'){
			$where[] = ['status','=',1];
		}elseif($st == '2'){
			$where[] = ['status','=',2];
		}elseif($st == '3'){
			$where[] = ['status','=',3];
		}

		if(input('param.keyword')) $where[] = ['member.nickname','like','%'.trim(input('param.keyword')).'%'];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['withdrawlog.status','=',input('param.status')];
		$datalist = Db::name('member_yuebao_withdrawlog')->alias('withdrawlog')->field('member.nickname,member.headimg,withdrawlog.*')->join('member member','member.id=withdrawlog.mid')->where($where)->page($pagenum,$pernum)->order('withdrawlog.id desc')->select()->toArray();
		if($pagenum==1){
			$count = 0 + Db::name('member_yuebao_withdrawlog')->alias('withdrawlog')->field('member.nickname,member.headimg,withdrawlog.*')->join('member member','member.id=withdrawlog.mid')->where($where)->count();
		}
		return $this->json(['status'=>1,'count'=>$count,'data'=>$datalist]);
	}
	//余额宝提现明细
	function yuebaowithdrawdetail(){
		$id = input('param.id/d');
		$info = Db::name('member_yuebao_withdrawlog')->where(['aid'=>aid,'id'=>$id])->find();
		$member = Db::name('member')->where(['id'=>$info['mid']])->find();
		$info['nickname'] = $member['nickname'];
		$info['headimg'] = $member['headimg'];
		return $this->json(['status'=>1,'info'=>$info]);
	}
	//余额宝提现审核通过
	function yuebaowithdrawpass(){
		$id = input('post.id/d');
		$info = Db::name('member_yuebao_withdrawlog')->where(['aid'=>aid,'id'=>$id])->update(['status'=>1,'reason'=>'']);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	//余额宝提现审核不通过
	function yuebaowithdrawnopass(){
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('member_yuebao_withdrawlog')->where(['aid'=>aid,'id'=>$id])->update(['status'=>2,'reason'=>$reason]);
		$info = Db::name('member_yuebao_withdrawlog')->where(['aid'=>aid,'id'=>$id])->find();
		\app\common\Member::addyuebaomoney(aid,$info['mid'],$info['txmoney'],t('余额宝').'收益提现返还',4);
		//提现失败通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的提现申请被商家驳回，可与商家协商沟通。';
		$tmplcontent['remark'] = $reason.'，请点击查看详情~';
		$tmplcontent['money'] = (string) $info['txmoney'];
		$tmplcontent['time'] = date('Y-m-d H:i',$info['createtime']);
		\app\common\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixianerror',$tmplcontent,m_url('/pages/my/usercenter'));
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['amount1'] = $info['txmoney'];
		$tmplcontent['time3'] = date('Y-m-d H:i',$info['createtime']);
		$tmplcontent['thing4'] = $reason;

		$tmplcontentnew = [];
		$tmplcontentnew['thing1'] = '提现失败';
		$tmplcontentnew['amount2'] = $info['txmoney'];
		$tmplcontentnew['date4'] = date('Y-m-d H:i',$info['createtime']);
		$tmplcontentnew['thing12'] = $reason;
		\app\common\Wechat::sendwxtmpl(aid,$info['mid'],'tmpl_tixianerror',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
		//短信通知
		$member = Db::name('member')->where(['id'=>$info['mid']])->find();
		if($member['tel']){
			\app\common\Sms::send(aid,$member['tel'],'tmpl_tixianerror',['reason'=>$reason]);
		}
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	//余额宝提现改为打款
	function yuebaowidthdsetydk(){
		$id = input('post.id/d');
		Db::name('member_yuebao_withdrawlog')->where(['aid'=>aid,'id'=>$id])->update(['status'=>3,'reason'=>'']);
		$info = Db::name('member_yuebao_withdrawlog')->where(['aid'=>aid,'id'=>$id])->find();
        $info['money'] = dd_money_format($info['money']);
		//提现成功通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['money'] = (string) $info['money'];
		$tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
        $tempconNew = [];
        $tempconNew['amount2'] = (string) $info['money'];//提现金额
        $tempconNew['time3'] = date('Y-m-d H:i',$info['createtime']);//提现时间
		\app\common\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['amount1'] = $info['money'];
		$tmplcontent['thing3'] = $info['paytype'];
		$tmplcontent['time5'] = date('Y-m-d H:i');
		
		$tmplcontentnew = [];
		$tmplcontentnew['amount3'] = $info['money'];
		$tmplcontentnew['phrase9'] = $info['paytype'];
		$tmplcontentnew['date8'] = date('Y-m-d H:i');
		\app\common\Wechat::sendwxtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
		//短信通知
		$member = Db::name('member')->where(['id'=>$info['mid']])->find();
		if($member['tel']){
			\app\common\Sms::send(aid,$member['tel'],'tmpl_tixiansuccess',['money'=>$info['money']]);
		}
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	//余额宝提现 微信打款
	function yuebaowidthdwxdakuan(){
		$id = input('post.id/d');
		$info = Db::name('member_yuebao_withdrawlog')->where(['aid'=>aid,'id'=>$id])->find();
		if($info['status']!=1) return $this->json(['status'=>0,'msg'=>'已审核状态才能打款']);
		$info['money'] = dd_money_format($info['money']);
		$rs = \app\common\Wxpay::transfers(aid,$info['mid'],$info['money'],$info['ordernum'],$info['platform'],t('余额宝').'提现');
		if($rs['status']==0){
			return $this->json(['status'=>0,'msg'=>$rs['msg']]);
		}else{
			Db::name('member_yuebao_withdrawlog')->where(['aid'=>aid,'id'=>$id])->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
			//提现成功通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
			$tmplcontent['remark'] = '请点击查看详情~';
			$tmplcontent['money'] = (string) $info['money'];
			$tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
            $tempconNew = [];
            $tempconNew['amount2'] = (string) $info['money'];//提现金额
            $tempconNew['time3'] = date('Y-m-d H:i',$info['createtime']);//提现时间
			\app\common\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['amount1'] = $info['money'];
			$tmplcontent['thing3'] = $info['paytype'];
			$tmplcontent['time5'] = date('Y-m-d H:i');
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $info['money'];
			$tmplcontentnew['phrase9'] = $info['paytype'];
			$tmplcontentnew['date8'] = date('Y-m-d H:i');
			\app\common\Wechat::sendwxtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
			//短信通知
			$member = Db::name('member')->where(['id'=>$info['mid']])->find();
			if($member['tel']){
				$tel = $member['tel'];
				\app\common\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$info['money']]);
			}
			return $this->json(['status'=>1,'msg'=>$rs['msg']]);
		}
	}
	
	public function scorelog(){
		$pagenum = input('post.pagenum');
        $st = input('post.st');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		$datalist = Db::name('business_scorelog')->field('id,score,after,remark,from_unixtime(createtime)createtime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
        $businessTransfer = false;
        $businessScoreWithdraw = false;
        $business = [];
        if($pagenum == 1){
            $business= Db::name('business')->where('id',bid)->find();
            if(getcustom('score_business_to_member')){
                $businessTransfer = true;
            }
            //是否可提现
            if(getcustom('business_score_withdraw')){
                $bset = Db::name('business_sysset')->where('aid',aid)->find();
                if($bset['business_score_withdraw']==1){
                    $businessScoreWithdraw = true;
                }
            }
        }
		return $this->json(['status'=>1,'data'=>$datalist,'myscore'=>$business['score'],'businessTransfer'=>$businessTransfer,'scoreWithdraw'=>$businessScoreWithdraw]);
	}


	//门店余额明细
	public function mdmoneylog(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mdid','=',$this->user['mdid']];
		$datalist = Db::name('mendian_moneylog')->field("id,money,`after`,createtime,remark")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	//门店余额提现记录
	public function mdwithdrawlog(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mdid','=',$this->user['mdid']];
		$datalist = Db::name('mendian_withdrawlog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	//提现信息设置
	public function mdtxset(){
		if(request()->isPost()){
			$postinfo = input('post.');
			$data = [];
			$data['weixin'] = $postinfo['weixin'];
			$data['aliaccountname'] = $postinfo['aliaccountname'];
			$data['aliaccount'] = $postinfo['aliaccount'];
			$data['bankname'] = $postinfo['bankname'];
			$data['bankcarduser'] = $postinfo['bankcarduser'];
			$data['bankcardnum'] = $postinfo['bankcardnum'];
			Db::name('mendian')->where('id',$this->user['mdid'])->update($data);
			return $this->json(['status'=>1,'msg'=>'保存成功']);
		}
		$info = Db::name('mendian')->field('id,weixin,aliaccountname,aliaccount,bankname,bankcarduser,bankcardnum')->where(['id'=>$this->user['mdid']])->find();
		return $this->json(['status'=>1,'info'=>$info]);
	}
	public function mdwithdraw(){
        $field = 'withdrawmin,withdraw_weixin,withdraw_aliaccount,withdraw_bankcard,withdraw_autotransfer';
        if(getcustom('mendian_money_transfer')){
            $field .= ',mendian_money_transfer';
        }
		$set = Db::name('admin_set')->where(['aid'=>aid])->field($field)->find();
		$mendian = Db::name('mendian')->where('id',$this->user['mdid'])->find();
		$set['withdrawfee'] = $mendian['withdrawfee'];
		if(request()->isPost()){
			$post = input('post.');
			if($post['paytype']=='支付宝' && $mendian['aliaccount']==''){
				return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
			}
			if($post['paytype']=='银行卡' && ($mendian['bankname']==''||$mendian['bankcarduser']==''||$mendian['bankcardnum']=='')){
				return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
			}

			$money = $post['money'];
			if($money<=0 || $money < $set['withdrawmin']){
				return $this->json(['status'=>0,'msg'=>'提现金额必须大于'.($set['withdrawmin']?$set['withdrawmin']:0)]);
			}
			if($money > $mendian['money']){
				return $this->json(['status'=>0,'msg'=>'可提现余额不足']);
			}
			if(empty($this->user['mid'])){
				return $this->json(['status'=>0,'msg'=>'此账号未绑定用户，请绑定']);
			}
			$ordernum = date('ymdHis').aid.rand(1000,9999);
			$record['aid'] = aid;
			$record['bid'] = $mendian['bid'];
			$record['mid'] = $this->user['mid'];
			$record['mdid'] = $mendian['id'];
			$record['createtime']= time();
			$record['money'] =dd_money_format( $money*(1-$set['withdrawfee']*0.01));
			$record['txmoney'] = $money;
			$record['ordernum'] = $ordernum;
			$record['paytype'] = $post['paytype'];
			if(empty($mendian['bid']) && ($post['paytype']=='微信' || $post['paytype']=='微信钱包')){
				if($set['commission_autotransfer']==1){
					if($record['bid']>0){
		                //查询多商户的金额
		                $business = Db::name('business')->where('id',$record['bid'])->where('aid',aid)->field('money')->find();
		                if($business['money']<$record['money']){
		                    return json(['status'=>0,'msg'=>'提现失败，商户余额不足']);
		                }
		            }
					\app\common\Mendian::addmoney(aid,$mendian['id'],-$money,'余额提现');
					$mid = $this->user['mid'];
					if(!$mid) return json(['status'=>0,'msg'=>'未绑定微信']);
					$rs = \app\common\Wxpay::transfers(aid,$mid,$record['money'],$record['ordernum'],'','余额提现');
					if($rs['status']==0){
						\app\common\Mendian::addmoney(aid,$mendian['id'],$money,'余额提现失败返还');
						return json(['status'=>0,'msg'=>$rs['msg']]);
					}else{
						$record['weixin'] = t('会员').'ID：'.$mid;
						$record['status'] = 3;
						$record['paytime'] = time();
						$record['paynum'] = $rs['resp']['payment_no'];
						$id = db('mendian_withdrawlog')->insertGetId($record);

						//提现成功通知
						$tmplcontent = [];
						$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
						$tmplcontent['remark'] = '请点击查看详情~';
						$tmplcontent['money'] = (string) $record['money'];
						$tmplcontent['timet'] = date('Y-m-d H:i',$record['createtime']);
                        $tempconNew = [];
                        $tempconNew['amount2'] = (string) $record['money'];//提现金额
                        $tempconNew['time3'] = date('Y-m-d H:i',$record['createtime']);//提现时间
						\app\common\Wechat::sendtmpl(aid,$mid,'tmpl_tixiansuccess',$tmplcontent,m_url('admin/index/index'),$tempconNew);
						//短信通知
						$member = Db::name('member')->where('id',$mid)->find();
						if($member['tel']){
							$tel = $member['tel'];
							\app\common\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$record['money']]);
						}
						return json(['status'=>1,'msg'=>$rs['msg']]);
					}
				}
				if($mendian['weixin']==''){
					return json(['status'=>0,'msg'=>'请填写完整提现信息','url'=>'mdtxset']);
				}
				$record['weixin'] = $mendian['weixin'];
			}else{
				if($post['paytype']=='微信' || $post['paytype']=='微信钱包'){
					if($mendian['weixin']==''){
						return json(['status'=>0,'msg'=>'请填写完整提现信息','url'=>'mdtxset']);
					}
					$record['weixin'] = $mendian['weixin'];
				}
			}
			if($post['paytype']=='支付宝'){
				$record['aliaccountname'] = $mendian['aliaccountname'];
				$record['aliaccount'] = $mendian['aliaccount'];
			}
			if($post['paytype']=='银行卡'){
				$record['bankname'] = $mendian['bankname'];
				$record['bankcarduser'] = $mendian['bankcarduser'];
				$record['bankcardnum'] = $mendian['bankcardnum'];
			}
			$recordid = db('mendian_withdrawlog')->insertGetId($record);

			\app\common\Mendian::addmoney(aid,$mendian['id'],-$money,'余额提现');

			return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款']);
		}
		$userinfo = db('mendian')->where(['id'=>$mendian['id']])->field('id,money,weixin,aliaccount,aliaccountname,bankname,bankcarduser,bankcardnum')->find();
		$rdata = [];
		$rdata['userinfo'] = $userinfo;
		$rdata['sysset'] = $set;
		return $this->json($rdata);
	}
    public function bscorewithdraw()
    {
        if(getcustom('business_score_withdraw')) {
            $adminset = Db::name('admin_set')->where('aid', aid)->field('aid,score2money')->find();
            $set = Db::name('business_sysset')->where(['aid' => aid])->field('business_score_withdraw,business_score_withdrawfee,withdraw_weixin,withdraw_aliaccount,withdraw_bankcard,commission_autotransfer')->find();
            $set['score2money'] = $exchangeRate = $adminset['score2money'] ?? 0.01;
            if (request()->isPost()) {
                $post = input('post.');
                if ($set['business_score_withdraw'] == 0) {
                    return ['status' => 0, 'msg' => t('积分') . '提现功能未开启'];
                }
                $binfo = Db::name('business')->where('id', bid)->find();
                if ($post['paytype'] == '支付宝' && $binfo['aliaccount'] == '') {
                    return $this->json(['status' => 0, 'msg' => '请先设置支付宝账号']);
                }
                if ($post['paytype'] == '银行卡' && ($binfo['bankname'] == '' || $binfo['bankcarduser'] == '' || $binfo['bankcardnum'] == '')) {
                    return $this->json(['status' => 0, 'msg' => '请先设置完整银行卡信息']);
                }

                $score = $post['score'];
                if ($score > $binfo['score']) {
                    return $this->json(['status' => 0, 'msg' => '可提现' . t('积分') . '不足']);
                }
                $money = round($score * $exchangeRate, 2);
                $ordernum = date('ymdHis') . aid . rand(1000, 9999);
                $record['aid'] = aid;
                $record['bid'] = bid;
                $record['createtime'] = time();
                $record['score'] = $score;
                $record['money'] = dd_money_format($money * (1 - $set['business_score_withdrawfee'] * 0.01));
                $record['txmoney'] = $money;
                $record['ordernum'] = $ordernum;
                $record['paytype'] = $post['paytype'];
                if ($post['paytype'] == '微信' || $post['paytype'] == '微信钱包') {
                    if ($set['commission_autotransfer'] == 1) {
                        \app\common\Business::addscore(aid, bid, -$score, t('积分') . '提现');
                        $mid = Db::name('admin_user')->where('aid', aid)->where('bid', bid)->where('isadmin', 1)->value('mid');
                        if (!$mid) return json(['status' => 0, 'msg' => '商户主管理员未绑定微信']);
                        $rs = \app\common\Wxpay::transfers(aid, $mid, $record['money'], $record['ordernum'], '', '余额提现');
                        if ($rs['status'] == 0) {
                            \app\common\Business::addscore(aid, bid, $score, t('积分') . '提现失败返还');
                            return json(['status' => 0, 'msg' => $rs['msg']]);
                        } else {
                            $record['weixin'] = t('会员') . 'ID：' . $mid;
                            $record['status'] = 3;
                            $record['paytime'] = time();
                            $record['paynum'] = $rs['resp']['payment_no'];
                            $id = db('business_score_withdrawlog')->insertGetId($record);

                            //提现成功通知
                            $tmplcontent = [];
                            $tmplcontent['first'] = '您的提现申请已打款，请留意查收';
                            $tmplcontent['remark'] = '请点击查看详情~';
                            $tmplcontent['money'] = (string)$record['money'];
                            $tmplcontent['timet'] = date('Y-m-d H:i', $record['createtime']);
                            $tempconNew = [];
                            $tempconNew['amount2'] = (string)$record['money'];//提现金额
                            $tempconNew['time3'] = date('Y-m-d H:i', $record['createtime']);//提现时间
                            \app\common\Wechat::sendtmpl(aid, $mid, 'tmpl_tixiansuccess', $tmplcontent, m_url('admin/index/index'), $tempconNew);
                            //短信通知
                            $member = Db::name('member')->where('id', $mid)->find();
                            if ($member['tel']) {
                                $tel = $member['tel'];
                                \app\common\Sms::send(aid, $tel, 'tmpl_tixiansuccess', ['money' => $record['money']]);
                            }
                            return json(['status' => 1, 'msg' => $rs['msg']]);
                        }
                    }
    //                if($binfo['weixin']==''){
    //                    return json(['status'=>0,'msg'=>'请填写完整提现信息','url'=>'txset']);
    //                }
    //                $record['weixin'] = $binfo['weixin'];
                }
                if ($post['paytype'] == '支付宝') {
                    $record['aliaccount'] = $binfo['aliaccount'];
                }
                if ($post['paytype'] == '银行卡') {
                    $record['bankname'] = $binfo['bankname'];
                    $record['bankcarduser'] = $binfo['bankcarduser'];
                    $record['bankcardnum'] = $binfo['bankcardnum'];
                }
                $recordid = db('business_score_withdrawlog')->insertGetId($record);

                \app\common\Business::addscore(aid, bid, -$score, t('积分') . '提现', false, 'business_score_withdrawlog', $ordernum);

                return $this->json(['status' => 1, 'msg' => '提交成功,请等待打款']);
            }
            $userinfo = db('business')->where(['id' => bid])->field('id,score,weixin,aliaccount,bankname,bankcarduser,bankcardnum')->find();

            $rdata = [];
            $rdata['userinfo'] = $userinfo;
            $rdata['sysset'] = $set;
            return $this->json($rdata);
        }
    }
    //商家积分提现记录
    public function bscorewithdrawlog(){
        if(getcustom('business_score_withdraw')) {
            $pagenum = input('post.pagenum');
            if (!$pagenum) $pagenum = 1;
            $pernum = 20;
            $where = [];
            $where[] = ['aid', '=', aid];
            $where[] = ['bid', '=', bid];
            if(is_numeric(input('param.st'))) $where[] = ['status', '=', input('param.st/d')];
            $datalist = Db::name('business_score_withdrawlog')->where($where)->page($pagenum, $pernum)->order('id desc')->select()->toArray();
            foreach ($datalist as $k=>$v){
                $datalist[$k]['showtime'] = date('Y-m-d H:i:s',$v['createtime']);
                $datalist[$k]['reason'] = $v['reason']??'';
            }
            if (!$datalist) $datalist = [];
            return $this->json(['status' => 1, 'data' => $datalist]);
        }
    }
    //所有的支付方式
	public function getpaytypelist(){
         $list = [
             ['id' => '1','title' => '余额支付'],
             ['id' => '2','title' => '微信支付'],
             ['id' => '3','title' => '支付宝支付'],
         ];
         if(getcustom('restaurant_shop_cashdesk')){
             $list[] = ['id' => '0','title' => '现金收款-餐饮收银台'];
         }
        if(getcustom('cashdesk_sxpay')){
            $list[] = ['id' => '81','title' => '随行付收款-餐饮收银台'];
        }
        if(getcustom('restaurant_douyin_qrcode_hexiao')){
            $list[] = ['id' => '121','title' => '抖音团购券-餐饮收银台'];
        }
        if(getcustom('restaurant_cashdesk_custom_pay')){
            $custom_paylist = Db::name('restaurant_cashdesk_custom_pay')->where('aid',aid)->where('bid',bid)->where('status',1)->order('sort desc,id desc')->select()->toArray();
            foreach($custom_paylist as $ck=>$cv){
                $list[] = ['id' => 10000+ $cv['id'],'title' => $cv['title'].'-餐饮收银台'];
            }
        }
        return $this->json(['status'=>1,'data'=>$list]);
    }
    public function gettradereport(){
	    if(getcustom('finance_trade_report')){
            $other['datetype'] ='today';
            $paytypeid = input('param.paytypeid');
            if($paytypeid !='')$other['search_paytype'] =  $paytypeid;
            $ctime = input('param.ctime');
            if($ctime){
                $other['starttime'] =$ctime[0];
                $other['endtime'] =$ctime[1];
                $other['datetype'] ='custom';
            }
            $data = \app\model\Payorder::tradeReport(aid,bid,0,2,$other);
            $isprint = input('param.isprint');
            if($isprint){
                \app\common\Wifiprint::jiaobanPrint($data);
                return $this->json(['status'=>1,'msg'=>'打印成功']);
            }
            return $this->json(['status'=>1,'data'=>$data]);
        }
    }
   
    public function depositloglist(){
	    if(getcustom('business_deposit')){
            $pagenum = input('post.pagenum');
            if(!$pagenum) $pagenum = 1;
            $pernum = 20;
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            $datalist = Db::name('business_depositlog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
            $count =  Db::name('business_depositlog')->field('id,after,remark,from_unixtime(createtime)createtime')->where($where)->count();
            $business = Db::name('business')->where('aid',aid)->where('id', bid)->find();
            $deposit_refund =Db::name('business_sysset')->where('aid',aid)->value('business_deposit_refund');
            $business_deposit_refund = 0;
            if($deposit_refund && $business['deposit'] > 0){
                $business_deposit_refund = 1;
            }
            $refund_status = 0;
            //申请状态
            $order = Db::name('business_deposit_order')->where('aid',aid)->where('bid',bid)->find();
            if($order){
                $refund_status = $order['refund_status'];
            }
            return $this->json(['status'=>1,'data'=>$datalist,'deposit' => $business['deposit'],'count' => $count,'business_deposit_refund' => $business_deposit_refund,'refund_status' => $refund_status]);
        }
    }
    public function depositrefund(){
	    if(getcustom('business_deposit_refund')){
            $business = Db::name('business')->where('aid',aid)->where('id', bid)->find();
            if(!$business['deposit']){
                return $this->json(['status'=>1,'msg'=>'保证金不足']);
            }
            $order = Db::name('business_deposit_order')->where('aid',aid)->where('bid',bid)->find();
            $money = $business['deposit'];
            if(!$order){
                $ordernum = date('ymdHis').aid.rand(1000,9999);
                $orderdata = [];
                $orderdata['aid'] = aid;
                $orderdata['mid'] = mid;
                $orderdata['bid'] = bid;
                $orderdata['createtime']= time();
                $orderdata['money'] = $money;
                $orderdata['ordernum'] = $ordernum;
                $orderdata['refund_status'] = 1;
                $orderdata['refund_time'] = time();
                $orderid = Db::name('business_deposit_order')->insertGetId($orderdata);
            }else{
                if($order['refund_status'] ==1){
                    return $this->json(['status'=>1,'msg'=>'申请成功，等待审核']);
                }
                $order['refund_status'] = 1;
                $order['refund_time'] = time();
                Db::name('business_deposit_order')->where('id',$order['id'])->update($order);
            }
            return $this->json(['status'=>1,'msg'=>'申请成功，等待审核']);
        }
    }

    /**
     * 门店余额转账
     * 定制功能 开发文档：https://doc.weixin.qq.com/doc/w3_AT4AYwbFACw48hyU006QESmU9gvsS?scode=AHMAHgcfAA0Lo2NuezAeYAOQYKALU
     * @author: liud
     * @time: 2024/11/11 上午11:23
     */
    public function transferMendianMoney()
    {
        if(getcustom('mendian_money_transfer')) {
            $mid = input('post.mid/d');
            $mendian = Db::name('mendian')->where('id',$this->user['mdid'])->find();
            $set = Db::name('admin_set')->where('aid',aid)->find();
            if(!$set['mendian_money_transfer']){
                return $this->json(['status'=>0,'msg'=>'功能未开启']);
            }
            if(request()->isPost()){
                $mobile = input('post.mobile');
                $mid = input('post.mid/d');
                $money = input('post.money/f');
                if ($money < 0.01){
                    return $this->json(['status'=>0,'msg'=>'请输入正确的金额，最小金额为：0.01']);
                }

                if (input('?post.mid') && $mid > 0) {
                    $member = Db::name('member')->where('aid', aid)->where('id', $mid)->find();
                }
                if(!$member) return $this->json(['status'=>0,'msg'=>'未找到该'.t('会员')]);
                $user_id = $member['id'];

//                if ($user_id == $business['mid']) {
//                    return $this->json(['status'=>0,'msg'=>'不能转账给自己']);
//                }
                if ($money > $mendian['money']){
                    return $this->json(['status'=>0,'msg'=>'您的'.t('余额').'不足']);
                }

                $midMsg = sprintf("转给：%s",$member['nickname']);
                $toMidMsg = sprintf("来自门店%s的转账", $mendian["name"]);
                //减去门店余额
                $rs = \app\common\Mendian::addmoney(aid,$mendian['id'],$money * -1, $midMsg);
                if ($rs['status'] == 1) {
                    \app\common\Member::addmoney(aid,$user_id,$money,$toMidMsg,$mendian["id"]);
                }else{
                    return $this->json(['status'=>0, 'msg' => '转账失败']);
                }
                return $this->json(['status'=>1, 'msg' => '转账成功', 'url'=>'/admin/finance/mdwithdraw']);
            }
            $tomember = [];
            if($mid){
                $tomember = Db::name('member')->where('aid',aid)->where('id',$mid)->field('id,money,nickname,headimg')->find();
            }
            $rdata['status'] = 1;
            $rdata['mymoney'] = $mendian['money'];
            $rdata['moneyList'] = [];//可选金额列表
            $rdata['tomember'] = $tomember?$tomember:['nickname'=>''];//转给谁
            return $this->json($rdata);
        }
    }

    //金币明细
    function goldlog(){
        if(getcustom('bonus_pool_gold')){
            $pagenum = input('post.pagenum');
            if(!$pagenum) $pagenum = 1;
            $order = 'id desc';
            $pernum = 20;
            $where = [];
            $where[] = ['l.aid','=',aid];
            $where[] = ['l.mid','=',bid];
            $where[] = ['l.is_business','=',1];
            $datalist = Db::name('member_gold_log')->alias('l')
                ->field('l.*')
                ->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
            if($pagenum==1){
                $count = 0 + Db::name('member_gold_log')->alias('l')->field('l.*')->where($where)->count();
            }
            return $this->json(['status'=>1,'count'=>$count,'data'=>$datalist]);
        }
    }
    //金币兑换
    public function goldwithdraw(){
        if(getcustom('bonus_pool_gold')) {
            $field = '*';
            $set = Db::name('bonuspool_gold_set')->where(['aid' => aid])->field($field)->find();
            if (request()->isPost()) {
                $params = input('');
                $withdraw_num = $params['money'];
                $paytype = 'money';
                $set = Db::name('bonuspool_gold_set')->where('aid',aid)->find();
                $gold_price = $set['gold_price'];
                $gold_value = bcmul($withdraw_num,$gold_price,2);
                $cash_fee = bcmul($gold_value, $set['cash_fee'] / 100, 2);
                $real_gold_value = bcsub($gold_value, $cash_fee, 2);
                Db::startTrans();
                if($paytype=='money'){
                    $money = $real_gold_value;
                    //兑换余额
                    $res = \app\common\Business::addmoney(aid,bid,$money,t('金币').'兑换');
                }

                //插入提取记录表
                $log = [];
                $log['aid'] = aid;
                $log['mid'] = bid;
                $log['is_business'] = 1;
                $log['money'] = $gold_value?:0;
                $log['fee'] = $cash_fee;
                $log['gold_num'] = $withdraw_num;
                $log['gold_price'] = $gold_price;
                $log['to_commission'] = 0;
                $log['to_money'] = $money?:0;
                $log['to_score'] = 0;
                $log['remark'] = '兑换';
                $log['createtime'] = time();
                $log_id = Db::name('gold_withdraw_log')->insertGetId($log);
                //扣除金币
                $res = \app\custom\BonusPoolGold::addbusinessgold(aid,bid,-$withdraw_num,t('金币').'兑换','gold_withdraw_log',$log_id);
                if(!$res['status']){
                    return json(['status'=>0,'msg'=>'兑换失败'.$res['msg']]);
                }
                //手续费回流奖金池
                $res = \app\custom\BonusPoolGold::addbonuspool(aid,0,$cash_fee,'商户'.bid.t('金币').'兑换手续费回流','gold_withdraw_log',$log_id,0,0,bid);
                Db::commit();
                return json(['status'=>1,'msg'=>'兑换成功']);
            }
            $field = 'gold';
            $userinfo = db('business')->where(['id' => bid])->field($field)->find();

            $rdata = [];
            $rdata['userinfo'] = $userinfo;

            $rdata['sysset'] = $set;
            return $this->json($rdata);
        }
    }
}