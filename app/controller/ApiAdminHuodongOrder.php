<?php
// JK客户定制

//custom_file(huodong_baoming)
//管理员中心 - 活动报名订单管理
namespace app\controller;
use think\facade\Db;
class ApiAdminHuodongOrder extends ApiAdmin
{
	//活动报名订单
	public function order(){
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
        if(input('param.keyword')){
              $where[] = ['ordernum|title', 'like', '%'.input('param.keyword').'%'];
        }
        if($st == 'all'){
			
		}elseif($st == '0'){
			$where[] = ['status','=',0];
		}elseif($st == '1'){
			$where[] = ['status','=',1];
		}elseif($st == '2'){
			$where[] = ['status','=',2];
		}elseif($st == '3'){
			$where[] = ['status','=',3];
		}elseif($st == '4'){
			$where[] = ['status','=',4];
		}elseif($st == '10'){
			$where[] = ['refund_status','>',0];
		}
		if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
     	$datalist = Db::name('huodong_baoming_order')->where($where)->order('id desc')->page($pagenum,$pernum)->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $key=>$v){
			$datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
			if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	//订单详情
	public function orderdetail(){
        $detail = Db::name('huodong_baoming_order')->where('id',input('param.id/d'))->where('aid',aid)->find();
		if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\model\Freight::getformdata($detail['id'],'huodong_baoming_order');
		if($detail['bid'] > 0){
			$detail['binfo'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->field('id,name,logo,province,city,district,address')->find();
		}else{
			$detail['binfo'] = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,province,city,district,address')->find();
		}
		$detail['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$detail['mid'])->find();
		if(!$detail['member']) $detail['member'] = [];
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['detail'] = $detail;
		return $this->json($rdata);
	}
	//备注
	public function setremark(){
		$post = input('post.');
		$type = $post['type'];
		$orderid = $post['orderid'];
		$content = $post['content'];
		Db::name($type.'_order')->where(['aid'=>aid,'bid'=>bid,'id'=>$orderid])->update(['remark'=>$content]);
        \app\common\System::plog('手机端后台活动设置备注'.$orderid);
		return $this->json(['status'=>1,'msg'=>'设置完成']);
	}
	//删除订单
	function delOrder(){
		$post = input('post.');
		$type = $post['type'];
		$orderid = input('post.orderid/d');
		$order = Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid])->find();
		if(!$order || $order['status']!=4){
			return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
		}else{
			$rs = Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid])->delete();
            \app\common\System::plog('手机端后台活动报名订单删除'.$orderid);
			return $this->json(['status'=>1,'msg'=>'删除成功']);
		}
	}
	//改为已支付
	function ispay(){
		if(bid != 0) return $this->json(['status'=>-4,'msg'=>'无操作权限']);
		$type = input('post.type');
		$orderid = input('post.orderid/d');

        $updata = [];
        $updata['status']  = 1;
        $updata['paytime'] = time();
        $updata['paytype'] = '后台支付';
		Db::name($type.'_order')->where(['aid'=>aid,'id'=>$orderid])->update($updata);
		$payfun = $type.'_pay';
		\app\model\Payorder::$payfun($orderid);
        \app\common\System::plog('手机端后台活动报名订单改为已支付'.$orderid);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}

    //改为核销
    function hexiao(){
        $type = input('post.type');
        $orderid = input('post.orderid/d');
        $order = Db::name($type.'_order')->where(['aid'=>aid,'id'=>$orderid])->find();
        $auth_data = json_decode($this->user['hexiao_auth_data'],true);
		
		if($this->user['isadmin']==0){
			if(!in_array($type,$auth_data)){
				return $this->json(['status'=>0,'msg'=>'您没有核销权限']);
			}
		}
        if($type=='huodong_baoming'){
			if($order['status']==3) return $this->json(['status'=>0,'msg'=>'订单已核销']);
            $data = array();
            $data['aid'] = aid;
            $data['bid'] = bid;
            $data['uid'] = $this->uid;
            $data['mid'] = $order['mid'];
            $data['orderid'] = $order['id'];
            $data['ordernum'] = $order['ordernum'];
            $data['title'] = $order['title'];
            $data['type'] = $type;
            $data['createtime'] = time();
            $data['remark'] = '核销员['.$this->user['un'].']核销';
            $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
			Db::name('hexiao_order')->insert($data);
            $remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];
            $rs = \app\common\Order::collect($order,$type, $this->user['mid']);
            if($rs['status']==0) return $this->json($rs);
            db($type.'_order')->where(['aid'=>aid,'id'=>$orderid])->update(['status'=>3,'collect_time'=>time(),'remark'=>$remark]);
            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\common\Order::wxShipping(aid,$order,'huodong_baoming');
            }

        }
        \app\common\System::plog('手机端后台活动报名订单改为核销'.$orderid);
        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }
}