<?php
// JK客户定制

//custom_file(member_overdraft_money)
namespace app\controller;
use think\facade\Db;
class ApiOverdraftMoney extends ApiCommon
{
	public function initialize(){
		parent::initialize();
		$this->checklogin();
	}

	//充值
	public function recharge(){
		if(request()->isPost()){
			$money = input('post.money');
			if($money>0){
				$ordernum = date('ymdHis').aid.rand(1000,9999);
				//增加消费记录
				$orderdata = [];
				$orderdata['aid'] = aid;
				$orderdata['mid'] = mid;
				$orderdata['createtime']= time();
				$orderdata['totalprice'] = $money;
				$orderdata['ordernum'] = $ordernum;
				$orderid = Db::name('overdraft_recharge_order')->insertGetId($orderdata);
				$payorderid = \app\model\Payorder::createorder(aid,0,$orderdata['mid'],'overdraft_recharge',$orderid,$ordernum,t('信用额度').'还款',$money);
				return $this->json(['status'=>1,'msg'=>'提交成功','orderid'=>$orderid,'payorderid'=>$payorderid]);
			}else{
				return $this->json(['status'=>0,'msg'=>'充值金额必须大于0']);
			}
		}
		$userinfo = [];
		$userinfo = Db::name('member')->field('overdraft_money,limit_overdraft_money,open_overdraft_money')->where('aid',aid)->where('id',$this->mid)->find();
		$limit_money = $userinfo['limit_overdraft_money'];
		$open_overdraft_money = $userinfo['open_overdraft_money'];
		$overdraft_money = $userinfo['overdraft_money']*-1;
		if(empty($limit_money)){
			$overdraft_money_now = 0; 
		}else{
			$overdraft_money_now = round($limit_money - $overdraft_money,2);
		}
		//额度
		if($open_overdraft_money == 1){
			$overdraft_money_now = '无限';
			$userinfo['limit_overdraft_money'] = '无限';
		}
		$userinfo['overdraft_money_use'] = $overdraft_money_now;
			
		//$userinfo['overdraft_money'] = Db::name('member')->where('aid',aid)->where('id',$this->mid)->value('overdraft_money');
		$rdata = [];
		$rdata['userinfo'] = $userinfo;
		return $this->json($rdata);
	}
    public function moneylog(){
        $st = input('param.st');
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $pernum = 20;
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['mid','=',mid];
        if($st == 1){//充值记录
            $datalist = Db::name('overdraft_recharge_order')->field("id,totalprice as money,`status`,from_unixtime(createtime) createtime")->where($where)->where('status=1')->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
        }else{ //余额明细
            $datalist = Db::name('member_overdraft_moneylog')->field("id,money,`after`,from_unixtime(createtime) createtime,remark")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
        }
        return $this->json(['status'=>1,'data'=>$datalist]);
    }
}