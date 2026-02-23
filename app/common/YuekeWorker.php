<?php
// JK客户定制

//custom_file(yueke)
namespace app\common;
use think\facade\Db;
class YuekeWorker
{
	//加余额
	public static function addmoney($aid,$bid,$uid,$money,$remark,$addtotal=1){
		if($money==0) return ;
		$user = Db::name('yueke_worker')->where('aid',$aid)->where('id',$uid)->find();
		if(!$user) return ['status'=>0,'msg'=>t('教练').'不存在'];

		if($money > 0 && $addtotal==1){
			$totalmoney = $user['totalmoney'] + $money;
		}else{
			$totalmoney = $user['totalmoney'];
		}
		$after = $user['money'] + $money;
		Db::name('yueke_worker')->where('aid',$aid)->where('id',$uid)->update(['totalmoney'=>$totalmoney,'money'=>$after]);
		
		$data = [];
		$data['aid'] = $aid;
		$data['uid'] = $uid;
		$data['bid'] = $bid;
		$data['money'] = $money;
		$data['after'] = $after;
		$data['createtime'] = time();
		$data['remark'] = $remark;
		Db::name('yueke_worker_moneylog')->insert($data);
		return ['status'=>1,'msg'=>''];
	}
}