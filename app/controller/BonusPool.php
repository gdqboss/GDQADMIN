<?php
// JK客户定制


// +---------------------------------------------------------------------- 
// | 奖金池管理   custom_file(product_bonus_pool)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class BonusPool extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
	public function index(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'id desc,status desc';
            }
            $where = [];
            if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
            if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
            if(input('param.cid')) $where[] = ['cid','=',input('param.cid')];
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            $count =Db::name('bonus_pool')->where($where)->order($order)->count(); 
            $data = Db::name('bonus_pool')->where($where)->order($order)->page($page,$limit)->select()->toArray();
            foreach($data as $key=>$val){
                 $data[$key]['nickname'] = '';
                 $data[$key]['headimg'] = '';
                 if($val['mid'] > 0){
                     $member = Db::name('member')->where('id',$val['mid'])->find();
                     $data[$key]['nickname'] =  $member['nickname'];
                     $data[$key]['headimg'] =  $member['headimg'];
                 }
                 $cname = Db::name('bonus_pool_category')->where('aid',aid)->where('bid',bid)->where('id',$val['cid'])->value('name');
                $data[$key]['cname'] =  $cname??'暂无';
                
             }
            //总金额
            $totalmoney =Db::name('bonus_pool')->where($where)->sum('money');
            $status = input('param.status')??'';
            $yfwhere = $wfwhere = $where;
            if($status =='') {
                 $yfwhere[] = ['status' ,'=',1];
                 $wfwhere[] = ['status' ,'=',0];
            }
            
            $yfmoney =Db::name('bonus_pool')->where($yfwhere)->sum('money');
            $wfmoney =Db::name('bonus_pool')->where($wfwhere)->sum('money');
            if($status =='1')$wfmoney = 0;

            if($status =='0')$yfmoney = 0;
            $money = [
                'totalmoney' => $totalmoney,
                'yfmoney' => $yfmoney,
                'wfmoney' => $wfmoney,
            ];
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'money' => $money]);
        }
        $categorylist =  Db::name('bonus_pool_category')->where('aid',aid)->where('bid',bid)->select()->toArray();
        View::assign('categorylist',$categorylist);
        return View::fetch();
    }
	//佣金记录
    public function record(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_bonus_pool_record.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_bonus_pool_record.id desc';
			}
			$where = [];
			$where[] = ['member_bonus_pool_record.aid','=',aid];
			$where[] = ['member_bonus_pool_record.status','in',[0,1]];
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_bonus_pool_record.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_bonus_pool_record.status','=',input('param.status')];
			$count = 0 + Db::name('member_bonus_pool_record')->alias('member_bonus_pool_record')->field('member.nickname,member.headimg,member_bonus_pool_record.*','left')->join('member member','member.id=member_bonus_pool_record.mid')->where($where)->count();
			$data = Db::name('member_bonus_pool_record')->alias('member_bonus_pool_record')->field('member.nickname,member.headimg,member_bonus_pool_record.*')->join('member member','member.id=member_bonus_pool_record.mid','left')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			foreach($data as $k=>$v){
				if($v['type'] == 'levelup'){
					$data[$k]['orderstatus'] = 1;
				}else{
					$data[$k]['orderstatus'] = Db::name($v['type'].'_order')->where('id',$v['orderid'])->value('status');
				}
				if($v['frommid']){
					$frommember = Db::name('member')->where('id',$v['frommid'])->find();
					if($frommember){
						$data[$k]['fromheadimg'] = $frommember['headimg'];
						$data[$k]['fromnickname'] = $frommember['nickname'];
					}
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//佣金记录导出
	public function recordexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'member_bonus_pool_record.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'member_bonus_pool_record.id desc';
		}
        $page = input('param.page');
        $limit = input('param.limit');
		$where = [];
		$where[] = ['member_bonus_pool_record.aid','=',aid];
		
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['member_bonus_pool_record.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_bonus_pool_record.status','=',input('param.status')];
		$list = Db::name('member_bonus_pool_record')->alias('member_bonus_pool_record')->field('member.nickname,member.headimg,member_bonus_pool_record.*')
            ->join('member member','member.id=member_bonus_pool_record.mid')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('member_bonus_pool_record')->alias('member_bonus_pool_record')->field('member.nickname,member.headimg,member_bonus_pool_record.*')
            ->join('member member','member.id=member_bonus_pool_record.mid')->where($where)->count();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '佣金';
		$title[] = '积分';
		$title[] = '状态';
		$title[] = '产生时间';
		$title[] = '发放时间';
		$title[] = '备注';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = $v['commission'] ? $v['commission'] : 0;
			$tdata[] = $v['score'] ? $v['score'] : 0;
			$tdata[] = $v['status']==0 ? '未发放' : '已发放';
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = date('Y-m-d H:i:s',$v['endtime']);
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//佣金记录删除
	public function recorddel(){
		$ids = input('post.ids/a');
		Db::name('member_bonus_pool_record')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\common\System::plog('删除佣金记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}


    //佣金记录
    public function tongji(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 'member_bonus_pool_record.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'member_bonus_pool_record.id desc';
            }
            $where = [];
            $where[] = ['member_bonus_pool_record.aid','=',aid];
            $where[] = ['member_bonus_pool_record.status','in',[0,1]];

            if(input('param.level')) {
                if(!input('param.mid')){
                    return json(['code'=>1,'msg'=>'请先指定'.t('会员').'ID']);
                }
                if(input('param.fromid')){
                    return json(['code'=>1,'msg'=>'层级和下级ID不可同时设置']);
                }
                $mid = trim(input('param.mid'));
                $level = input('param.level/d');
                if($level <= 0) return json(['code'=>1,'msg'=>'请输入大于0的层级']);
                if($level == 1){
                    $childrenids = \app\common\Member::getdownmids(aid,$mid,$level);
                }
                if($level > 1){
                    $childrenids1 = \app\common\Member::getdownmids(aid,$mid,$level-1);
                    $childrenids2 = \app\common\Member::getdownmids(aid,$mid,$level);
                    $childrenids = array_diff($childrenids2,$childrenids1);
                }
                if($childrenids)
                    $where[] = ['member_bonus_pool_record.frommid','in',$childrenids];
            }
            if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
            if(input('param.mid')) $where[] = ['member_bonus_pool_record.mid','=',trim(input('param.mid'))];
            if(input('param.fromid')) $where[] = ['member_bonus_pool_record.frommid','=',trim(input('param.fromid'))];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['member_bonus_pool_record.createtime','>=',strtotime($ctime[0])];
                $where[] = ['member_bonus_pool_record.createtime','<',strtotime($ctime[1]) + 86400];
            }
            $where2 = [];
            $where2[] = ['aid','=',aid];
//            $where2[] = ['status','in',[1,2,3]];
            if(input('param.cid') ){
                $where2[] = Db::raw('find_in_set('.input('param.cid/d').',cid)');
            }
            if(input('param.proid') ){
                $where2[] = ['proid','=',input('param.proid/d')];
            }
            if(input('param.cid') || input('param.proid')){
                $where_ogids = Db::name('shop_order_goods')->where($where2)->column('id');
//                if($where_ogids){
                    $where[] = ['member_bonus_pool_record.ogid','in',$where_ogids];
//                }
            }
            if(input('?param.status') && input('param.status')!=='') $where[] = ['member_bonus_pool_record.status','=',input('param.status')];
            $count = 0 + Db::name('member_bonus_pool_record')->alias('member_bonus_pool_record')->field('member.nickname,member.headimg,member_bonus_pool_record.*')->join('member member','member.id=member_bonus_pool_record.mid')->where($where)->count();
            $data = Db::name('member_bonus_pool_record')->alias('member_bonus_pool_record')->field('member.nickname,member.headimg,member_bonus_pool_record.*')->join('member member','member.id=member_bonus_pool_record.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();

            foreach($data as $k=>$v){
                if($v['type'] == 'levelup'){
                    $data[$k]['orderstatus'] = 1;
                }else{
                    $data[$k]['orderstatus'] = Db::name($v['type'].'_order')->where('id',$v['orderid'])->value('status');
                }
                if($v['frommid']){
                    $frommember = Db::name('member')->where('id',$v['frommid'])->find();
                    if($frommember){
                        $data[$k]['fromheadimg'] = $frommember['headimg'];
                        $data[$k]['fromnickname'] = $frommember['nickname'];
                    }
                }
            }
            $ogids = Db::name('member_bonus_pool_record')->alias('member_bonus_pool_record')->field('member.nickname,member.headimg,member_bonus_pool_record.*')->join('member member','member.id=member_bonus_pool_record.mid')->where($where)->column('ogid');
            if($ogids) $total = 0+Db::name('shop_order_goods')->whereIn('id',$ogids)->sum('real_totalprice');
            $total = round($total,2);
            $totalCommission = 0+Db::name('member_bonus_pool_record')->alias('member_bonus_pool_record')->field('member.nickname,member.headimg,member_bonus_pool_record.*')->join('member member','member.id=member_bonus_pool_record.mid')->where($where)->sum(Db::raw('member_bonus_pool_record.commission'));
            $totalCommission = round($totalCommission,2);
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'total'=>$total,'totalCommission'=>$totalCommission]);
        }
        return View::fetch();
    }

	//佣金明细
    public function commissionlog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_bonus_pool_log.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_bonus_pool_log.id desc';
			}
			$where = [];
			$where[] = ['member_bonus_pool_log.aid','=',aid];
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_bonus_pool_log.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_bonus_pool_log.status','=',input('param.status')];
			$count = 0 + Db::name('member_bonus_pool_log')->alias('member_bonus_pool_log')->field('member.nickname,member.headimg,member_bonus_pool_log.*')->join('member member','member.id=member_bonus_pool_log.mid')->where($where)->count();
			$data = Db::name('member_bonus_pool_log')->alias('member_bonus_pool_log')->field('member.nickname,member.headimg,member_bonus_pool_log.*')->join('member member','member.id=member_bonus_pool_log.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            $moeny_weishu = 2;
            
			foreach($data as $k=>$v){
				if($v['frommid']){
					$frommember = Db::name('member')->where('id',$v['frommid'])->find();
					if($frommember){
						$data[$k]['fromheadimg'] = $frommember['headimg'];
						$data[$k]['fromnickname'] = $frommember['nickname'];
					}
				}
				$data[$k]['commission'] = dd_money_format($v['commission'],$moeny_weishu);
                $data[$k]['after'] = dd_money_format($v['after'],$moeny_weishu);
			}

			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//佣金明细导出
	public function commissionlogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'member_bonus_pool_log.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'member_bonus_pool_log.id desc';
		}
        $page = input('param.page');
        $limit = input('param.limit');
		$where = [];
		$where[] = ['member_bonus_pool_log.aid','=',aid];
		
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['member_bonus_pool_log.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_bonus_pool_log.status','=',input('param.status')];
		$list = Db::name('member_bonus_pool_log')->alias('member_bonus_pool_log')->field('member.nickname,member.headimg,member_bonus_pool_log.*')
            ->join('member member','member.id=member_bonus_pool_log.mid')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('member_bonus_pool_log')->alias('member_bonus_pool_log')->field('member.nickname,member.headimg,member_bonus_pool_log.*')
            ->join('member member','member.id=member_bonus_pool_log.mid')->where($where)->count();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '变更金额';
		$title[] = '变更后金额';
		$title[] = '变更时间';
		$title[] = '备注';
		$data = array();
        $moeny_weishu = 2;
       
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = dd_money_format($v['commission'],$moeny_weishu);
			$tdata[] = dd_money_format($v['after'],$moeny_weishu);
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//佣金明细删除
	public function commissionlogdel(){
		$ids = input('post.ids/a');
		Db::name('member_bonus_pool_log')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\common\System::plog('删除佣金明细'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//提现记录
	public function withdrawlog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_bonus_pool_withdrawlog.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_bonus_pool_withdrawlog.id desc';
			}
			$where = array();
			$where[] = ['member_bonus_pool_withdrawlog.aid','=',aid];
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_bonus_pool_withdrawlog.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_bonus_pool_withdrawlog.status','=',input('param.status')];
			$count = 0 + Db::name('member_bonus_pool_withdrawlog')->alias('member_bonus_pool_withdrawlog')->field('member.nickname,member.headimg,member_bonus_pool_withdrawlog.*')->join('member member','member.id=member_bonus_pool_withdrawlog.mid')->where($where)->count();
			$data = Db::name('member_bonus_pool_withdrawlog')->alias('member_bonus_pool_withdrawlog')->field('member.nickname,member.headimg,member_bonus_pool_withdrawlog.*')->join('member member','member.id=member_bonus_pool_withdrawlog.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			$comwithdrawbl = Db::name('admin_set')->where('aid',aid)->value('comwithdrawbl');
			foreach($data as $k=>$v){
				if($comwithdrawbl > 0 && $comwithdrawbl < 100){
					$data[$k]['paymoney'] = round($v['money'] * $comwithdrawbl * 0.01,2);
					$data[$k]['tomoney'] = round($v['money'] - $data[$k]['paymoney'],2);
				}else{
					$data[$k]['tomoney'] = 0;
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//提现记录导出
	public function withdrawlogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'member_bonus_pool_withdrawlog.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'member_bonus_pool_withdrawlog.id desc';
		}
        $page = input('param.page');
        $limit = input('param.limit');
		$where = [];
		$where[] = ['member_bonus_pool_withdrawlog.aid','=',aid];
		
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['member_bonus_pool_withdrawlog.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_bonus_pool_withdrawlog.status','=',input('param.status')];
		$list = Db::name('member_bonus_pool_withdrawlog')->alias('member_bonus_pool_withdrawlog')->field('member.nickname,member.headimg,member_bonus_pool_withdrawlog.*')
            ->join('member member','member.id=member_bonus_pool_withdrawlog.mid')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('member_bonus_pool_withdrawlog')->alias('member_bonus_pool_withdrawlog')->field('member.nickname,member.headimg,member_bonus_pool_withdrawlog.*')
            ->join('member member','member.id=member_bonus_pool_withdrawlog.mid')->where($where)->order($order)->page($page,$limit)->select()->toArray();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '提现金额';
		$title[] = '打款金额';
		$title[] = '提现方式';
		$title[] = '收款账号';
		$title[] = '提现时间';
		$title[] = '状态';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = $v['txmoney'];
			$tdata[] = $v['money'];
			$tdata[] = $v['paytype'];
			if($v['paytype'] == '支付宝'){
				$tdata[] = $v['aliaccountname'].' '.$v['aliaccount'];
			}elseif($v['paytype'] == '银行卡'){
				$tdata[] = $v['bankname'] . ' - ' .$v['bankcarduser']. ' - '.$v['bankcardnum'];
			}else{
				$tdata[] = '';
			}
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$st = '';
			if($v['status']==0){
				$st = '审核中';
			}elseif($v['status']==1){
				$st = '已审核';
			}elseif($v['status']==2){
				$st = '已驳回';
			}elseif($v['status']==3){
				$st = '已打款';
			}
			$tdata[] = $st;
			$data[] = $tdata;
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//提现记录改状态
	public function withdrawlogsetst(){
		$id = input('post.id/d');
		$st = input('post.st/d');
		$reason = input('post.reason');
		Db::startTrans();
		$info = Db::name('member_bonus_pool_withdrawlog')->where('aid',aid)->where('id',$id)->find();
        $info['money'] = dd_money_format($info['money']);
		$comwithdrawbl = Db::name('admin_set')->where('aid',aid)->value('comwithdrawbl');
		if($comwithdrawbl > 0 && $comwithdrawbl < 100){
			$paymoney = round($info['money'] * $comwithdrawbl * 0.01,2);
			$tomoney = round($info['money'] - $paymoney,2);
		}else{
			$paymoney = $info['money'];
			$tomoney = 0;
		}
        $paymoney = dd_money_format($paymoney);
		if($st==10){//微信打款
			if($info['status']!=1) return json(['status'=>0,'msg'=>'已审核状态才能打款']);
			$rs = \app\common\Wxpay::transfers(aid,$info['mid'],$paymoney,$info['ordernum'],$info['platform'],t('佣金').'提现');
			if($rs['status']==0){
				return json(['status'=>0,'msg'=>$rs['msg']]);
			}else{
				if($tomoney > 0){
					\app\common\Member::addmoney(aid,$info['mid'],$tomoney,t('佣金').'提现');
				}
				Db::name('member_bonus_pool_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
				//提现成功通知
				$tmplcontent = [];
				$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
				$tmplcontent['remark'] = '请点击查看详情~';
				$tmplcontent['money'] = (string) round($info['money'],2);
				$tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
                $tempconNew = [];
                $tempconNew['amount2'] = (string) round($info['money'],2);//提现金额
                $tempconNew['time3'] = date('Y-m-d H:i',$info['createtime']);//提现时间
				\app\common\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
				//订阅消息
				$tmplcontent = [];
				$tmplcontent['amount1'] = $info['money'];
				$tmplcontent['thing3'] = '微信打款';
				$tmplcontent['time5'] = date('Y-m-d H:i');
				
				$tmplcontentnew = [];
				$tmplcontentnew['amount3'] = $info['money'];
				$tmplcontentnew['phrase9'] = '微信打款';
				$tmplcontentnew['date8'] = date('Y-m-d H:i');
				\app\common\Wechat::sendwxtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
				//短信通知
				$member = Db::name('member')->where('id',$info['mid'])->find();
				if($member['tel']){
					$tel = $member['tel'];
					\app\common\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$info['money']]);
				}
                \app\common\System::plog('佣金提现微信打款'.$id);
                return json(['status'=>1,'msg'=>$rs['msg']]);
			}
		}else{
			$up_data = [];
			$up_data['status'] = $st;
			if($reason){
				$up_data['reason'] = $reason;
			}
			Db::name('member_bonus_pool_withdrawlog')->where('aid',aid)->where('id',$id)->update($up_data);
			if($st == 2){//驳回返还佣金
				$member = Db::name('member')->where('id',$info['mid'])->find();
                $bonus_pool_money = dd_money_format($member['bonus_pool_money'] +$info['txmoney'] );	
                $log = [
                    'aid' =>aid,
                    'mid' =>$info['mid'],
                    'frommid' => 0,
                    'commission' => $info['txmoney'],
                    'after' => $bonus_pool_money,
                    'createtime' => time(),
                    'remark' => t('贡献值').'提现返还'
                ];
                Db::name('member_bonus_pool_log') ->insert($log);
                //返还上限
                $bonus_pool_max_money = dd_money_format($member['bonus_pool_max_money'] + $info['txmoney'] );
                Db::name('member')->where('id',$info['mid'])->update(['bonus_pool_money' => $bonus_pool_money,'bonus_pool_max_money' => $bonus_pool_max_money]);
				//提现失败通知
				$tmplcontent = [];
				$tmplcontent['first'] = '您的提现申请被商家驳回，可与商家协商沟通。';
				$tmplcontent['remark'] = $reason.'，请点击查看详情~';
				$tmplcontent['money'] = (string) round($info['txmoney'],2);
				$tmplcontent['time'] = date('Y-m-d H:i',$info['createtime']);
				\app\common\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixianerror',$tmplcontent,m_url('pages/my/usercenter'));
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
				$member = Db::name('member')->where('id',$info['mid'])->find();
				if($member['tel']){
					$tel = $member['tel'];
					\app\common\Sms::send(aid,$tel,'tmpl_tixianerror',['reason'=>$reason]);
				}
				\app\common\System::plog('佣金提现驳回'.$id);
			}
			if($st==3){
				if($tomoney > 0){
					\app\common\Member::addmoney(aid,$info['mid'],$tomoney,t('佣金').'提现');
				}
                //记录用户累计提现
                //提现成功通知
				$tmplcontent = [];
				$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
				$tmplcontent['remark'] = '请点击查看详情~';
				$tmplcontent['money'] = (string) round($info['money'],2);
				$tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
                $tempconNew = [];
                $tempconNew['amount2'] = (string) round($info['money'],2);//提现金额
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
				$member = Db::name('member')->where('id',$info['mid'])->find();
				if($member['tel']){
					$tel = $member['tel'];
					\app\common\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$info['money']]);
				}
				\app\common\System::plog('佣金提现改为已打款'.$id);
			}
		}
		Db::commit();
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//提现记录删除
	public function withdrawlogdel(){
		$ids = input('post.ids/a');
		Db::name('member_bonus_pool_withdrawlog')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\common\System::plog('佣金提现记录删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    //删除
    public function del(){
        $ids = input('post.ids/a');
        Db::name('bonus_pool')->where('aid',aid)->where('id','in',$ids)->delete();
        return json(['status'=>1,'msg'=>'删除成功']);
    }
    
    //奖金池分类
    public function category(){
        if(request()->isAjax()){    
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'sort desc,id desc';
            }
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            $data = Db::name('bonus_pool_category')->where('aid',aid)->where('bid',bid)->order($order)->page($page,$limit)->select()->toArray();
            foreach ($data as $key=>$val){
                $gettj = explode(',',$val['gettj']);
                $lname1 = [];
                if(in_array(-1,$gettj)){
                    $lname1[] ='所有等级';
                }
                $lname2 = Db::name('member_level')->where('id','in',$val['gettj'])->column('name');
                $lname = array_merge($lname1,$lname2);
                $levelname = implode(',',$lname);
                $data[$key]['levelname'] = $levelname;
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>count($data),'data'=>$data]);
        }
        return View::fetch();
    }
    //奖金池分类
    public function categoryedit(){
        if(input('param.id')){
            $info = Db::name('bonus_pool_category')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
        }else{
            $info = array('id'=>'');
        }
        if(input('param.pid')) $info['pid'] = input('param.pid');
        $info['gettj'] = explode(',',$info['gettj']);
        View::assign('info',$info);
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
        View::assign('levellist',$levellist);
        return View::fetch();
    }
    //奖金池分类
    public function categorysave(){
        $info = input('post.info/a');
        $info['gettj'] = implode(',', $info['gettj']);
        if($info['id']){
            Db::name('bonus_pool_category')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
            \app\common\System::plog('编辑奖金池分类'.$info['id']);
        }else{
            $info['aid'] = aid;
            $info['bid'] = bid;
            $info['createtime'] = time();
            $id = Db::name('bonus_pool_category')->insertGetId($info);
            \app\common\System::plog('添加奖金池分类'.$id);
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
    //删除
    public function categorydel(){
        $ids = input('post.ids/a');
        Db::name('bonus_pool_category')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
        \app\common\System::plog('删除奖金池分类'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
}
