<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 多商户免单 custom_file(yx_business_miandan)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\Db;
class ApiBusinessMiandan extends ApiCommon{
	public function getprolist(){
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['is_del','=',0];
		$where[] = ['status','=',1];
		$where[] = ['ischecked','=',1];

		if(input('param.bid')){
			$bid = input('param.bid/d');
		}else{
			$bid = 0;
		}
		
		$where[] = ['bid','=',$bid];
		$order = 'id desc';


		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('business_miandan')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
		}
		return $this->json(['status'=>1,'data'=>$datalist]);
	}

	public function createOrder(){
		$this->checklogin();
		if(input('param.bid')){
			$bid = input('param.bid/d');
		}else{
			$bid = 0;
		}
		$sysset = Db::name('business_sysset')->where('aid',aid)->find();
		$set = Db::name('business_miandan_set')->where('aid',aid)->where('bid',$bid)->find();
		$post = input('post.');
		$tourl = '/pages/my/usercenter';
		if($sysset['miandan_tourl']){
			$tourl = $sysset['miandan_tourl'];
		}
		if($set['tourl']){
			$tourl = $set['tourl'];
		}
		
		$proid = input('param.proid/d');

		$product = Db::name('business_miandan')->where('aid',aid)->where('status',1)->where('id',$proid)->find();
		$bid = $product['bid'];
		$gettj = explode(',',$sysset['miandan_mianfei_gettj']);
		if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
			return $this->json(['status'=>2,'msg'=>'您还未参与该免单节活动','url'=>$tourl]);
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$where[] = ['bid','=',$bid];
		$order = Db::name('business_miandan_order')->where($where)->where('status',0)->where('proid',$proid)->order('id asc')->find();
		if($order){
			return $this->json(['status'=>1,'order'=>$order,'msg'=>'获取成功']);
		}
		if($product['limit_num'] > 0){			
			$buy_num = Db::name('business_miandan_order')->where($where)->count();
			if($buy_num > 0){
				return $this->json(['status'=>0,'msg'=>'您已参与过该免单活动了']);
			}
			
		}
		$ordernum = date('ymdHis').rand(100000,999999);
		$orderdata = [];
		$orderdata['aid'] = aid;
		$orderdata['mid'] = mid;
		$orderdata['bid'] = $bid;
		$orderdata['ordernum'] = $ordernum;

		$orderdata['title'] = $product['name'];
		$orderdata['proname']  = $product['name'];
		$orderdata['propic']   = $product['pic'];
		$orderdata['proid']   = $product['id'];

		$orderdata['sell_price'] = $product['sell_price'];
		$orderdata['num']        = 1;
		$orderdata['status']     = 0;
		$orderdata['totalprice'] = $product['sell_price'];
		$orderdata['product_price'] = $product['sell_price'];

		$orderdata['hexiao_code'] = random(16);
		$orderdata['hexiao_qr']   = createqrcode(m_url('admin/hexiao/hexiao?type=business_miandan&co='.$orderdata['hexiao_code']));
		$orderdata['platform']    = platform;
		$orderdata['createtime']  = time();
	
		$orderid = Db::name('business_miandan_order')->insertGetId($orderdata);		
		return $this->json(['status'=>1,'order'=>$orderdata,'msg'=>'提交成功']);
	}
    public function orderlist(){
        $this->checklogin();
        $st = input('param.st');
        if(!input('?param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['mid','=',mid];
        $where[] = ['delete','=',0];
        if(input('param.keyword')) $where[] = ['ordernum|title', 'like', '%'.input('param.keyword').'%'];
        if($st == 'all'){

        }elseif($st == '0'){
            $where[] = ['status','=',0];
        }elseif($st == '1'){
            $where[] = ['status','=',1];
        }
        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('business_miandan_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
        foreach($datalist as $key=>$v){
			if($v['bid']!=0){
				$datalist[$key]['binfo'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->field('id,name,logo')->find();
			}
		}
        $rdata = [];
        $rdata['st'] = $st;
        $rdata['datalist'] = $datalist;
        return $this->json($rdata);
    }
	function delOrder(){
        $this->checklogin();
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('business_miandan_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
        if(!$order || $order['status']!=1){
            return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
        }
        if($order['status']==1){
            $rs = Db::name('gift_bag_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['delete'=>1]);
        }
        return $this->json(['status'=>1,'msg'=>'删除成功']);
    }


}