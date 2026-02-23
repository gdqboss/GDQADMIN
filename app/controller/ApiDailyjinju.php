<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 每日金句 custom_file(daily_jinju)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\Db;
class ApiDailyjinju extends ApiCommon{
	public function getprolist(){
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['is_del','=',0];
		$where[] = ['status','=',1];
		$where[] = ['fabutime','<',time()];

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
		$datalist = Db::name('dailyjinju_product')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
		if(!$datalist) $datalist = [];
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		foreach($datalist as $k=>$v){
			$datalist[$k]['fabutime'] = date("Y-m-d",$v['fabutime']);
			$datalist[$k]['shop_name'] = $sysset['name'];
			$datalist[$k]['shop_logo'] = $sysset['logo'];
		}
		return $this->json(['status'=>1,'datalist'=>$datalist]);
	}
	//详情
	public function getdetail(){
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['is_del','=',0];
		$where[] = ['status','=',1];
		if(input('param.bid')){
			$bid = input('param.bid/d');
		}else{
			$bid = 0;
		}
		$time = time();
		$where[] = ['bid','=',$bid];
		if(input('param.id')){
			$id = input('param.id/d');
			$where[] = ['id','=',$id];
			$info = Db::name('dailyjinju_product')->where($where)->find();
		}else{
			$start_time = strtotime(date('Y-m-d 00:00:00'));
			$end_time = strtotime(date('Y-m-d 23:59:59'));
			$where_time[] = ['fabutime','between',[$start_time,$end_time]];
			$info = Db::name('dailyjinju_product')->where($where)->where($where_time)->find();
			if(!$info){
				$where_time2[] = ['fabutime','<',$time];
				$info = Db::name('dailyjinju_product')->where($where)->where($where_time2)->order('fabutime desc')->find();
			}
		}
		
		if(!$info){
			return $this->json(['status'=>0,'msg'=>'数据不存在']);
		}
		$info['tourldata'] = json_decode($info['tourldata']);
		$info['fabutime'] = date('Y-m-d',$info['fabutime']);
		$ganwu_order= Db::name('dailyjinju_ganwu_order')->where('aid',aid)->where('mid',mid)->where('proid',$info['id'])->find();
		 $is_ganwu = 0;
		if($ganwu_order){
			$is_ganwu = 1;
		}
		$daka_order= Db::name('dailyjinju_daka_order')->where('aid',aid)->where('mid',mid)->where('proid',$info['id'])->find();
		$is_daka = 0;
		if($daka_order){
			$is_daka = 1;
		}
		$can_daka = 1;
		if($info['daka_limit_day'] > 0){
			$daka_limit_day = $info['daka_limit_day'] * 86400;
			if($time > $daka_limit_day + $info['fabutime']){
				$can_daka = 0;
			}
		}
		$info['can_daka'] = $can_daka;
		$info['is_ganwu'] = $is_ganwu;
		$info['is_daka'] = $is_daka;
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		Db::name('dailyjinju_product')->where('aid',aid)->where('id',$info['id'])->update(['viewnum'=>Db::raw("viewnum+1")]);

		$data=[
			'info'=>$info,
			'sysset'=>$sysset,
			//'ganwulist'=>$info,
		];
		return $this->json(['status'=>1,'data'=>$data]);
	}
	//保存打卡
	public function daka(){
		$this->checklogin();
		if(input('param.bid')){
			$bid = input('param.bid/d');
		}else{
			$bid = 0;
		}
		$post = input('post.');
		$proid = input('param.proid/d');

		$product = Db::name('dailyjinju_product')->where('aid',aid)->where('status',1)->where('id',$proid)->find();

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
		$orderdata['platform']    = platform;
		$orderdata['createtime']  = time();
	
		$orderid = Db::name('dailyjinju_daka_order')->insertGetId($orderdata);
		Db::name('dailyjinju_product')->where('aid',aid)->where('id',$product['id'])->update(['dakanum'=>Db::raw("dakanum+1")]);
		return $this->json(['status'=>1,'order'=>$orderdata,'msg'=>'提交成功']);
	}
	//保存感悟并打卡
	public function saveganwu(){
		$this->checklogin();
		if(input('param.bid')){
			$bid = input('param.bid/d');
		}else{
			$bid = 0;
		}
		$post = input('post.');
		$proid = input('param.proid/d');
		$ganwu = $post['ganwu'];

		$product = Db::name('dailyjinju_product')->where('aid',aid)->where('status',1)->where('id',$proid)->find();

		$ordernum = date('ymdHis').rand(100000,999999);
		$orderdata = [];
		$orderdata['aid'] = aid;
		$orderdata['mid'] = mid;
		$orderdata['bid'] = $bid;
		$orderdata['ordernum'] = $ordernum;
		$orderdata['ganwu'] = $ganwu;

		if($product['ganwu_checked'] == 1){
			$orderdata['is_checked'] = 0;
		}else{
			$orderdata['is_checked'] = 1;
		}
		
		$orderdata['title'] = $product['name'];
		$orderdata['proname']  = $product['name'];
		$orderdata['propic']   = $product['pic'];
		$orderdata['proid']   = $product['id'];
		$orderdata['platform']    = platform;
		$orderdata['createtime']  = time();
	
		$orderid = Db::name('dailyjinju_ganwu_order')->insertGetId($orderdata);
		Db::name('dailyjinju_product')->where('aid',aid)->where('id',$product['id'])->update(['ganwunum'=>Db::raw("ganwunum+1")]);
		//保存打卡
		$daka_order = Db::name('dailyjinju_daka_order')->where('aid',aid)->where('proid',$proid)->find();
		if(!$daka_order){
			$this->daka();	
		}		
		return $this->json(['status'=>1,'order'=>$orderdata,'msg'=>'提交成功']);
	}
    public function ganwulist(){
        $this->checklogin();
        $proid= input('param.proid');
        $where = [];
        $where[] = ['order.aid','=',aid];
        $where[] = ['order.is_checked','=',1];
        if($proid){
			$where[] = ['proid','=',$proid];
        }

        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('dailyjinju_ganwu_order')->alias('order')->field('order.*,member.headimg,member.nickname')->leftJoin('member member','member.id=order.mid')->where($where)->page($pagenum,$pernum)->order('order.id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			$is_star = 0;
			$fanwu_order = Db::name('dailyjinju_ganwu_star')->where('aid',aid)->where('ganwu_orderid',$v['id'])->find();
			if($fanwu_order && $fanwu_order['status'] == 1){
				$is_star = 1;
			}
			$datalist[$k]['is_star'] = $is_star;
			$datalist[$k]['createtime'] = date("Y-m-d H:i",$v['createtime']);
		}
        $rdata = [];
        $rdata['datalist'] = $datalist;
        return $this->json($rdata);
    }
	//标星记录
    public function savestar(){
        $orderid = input('param.orderid');
        $status = input('param.status');
        $ganwu_order = Db::name('dailyjinju_ganwu_star')->where('aid',aid)->where('ganwu_orderid',$orderid)->find();
		if($ganwu_order){
			$data['status'] = $status;
            $up = Db::name('dailyjinju_ganwu_star')->where('ganwu_orderid',$orderid)->where('aid',aid)->update($data);
		}else{
			$orderdata = [];
			$orderdata['aid'] = aid;
			$orderdata['mid'] = mid;
			$orderdata['status'] = $status;
			$orderdata['ganwu_orderid'] = $orderid;
			$orderdata['createtime']  = time();		
			$orderid = Db::name('dailyjinju_ganwu_star')->insertGetId($orderdata);
		}
		if($status == 1){
			Db::name('dailyjinju_ganwu_order')->where('aid',aid)->where('id',$orderid)->update(['star_num'=>Db::raw("star_num+1")]);
		}else{
			Db::name('dailyjinju_ganwu_order')->where('aid',aid)->where('id',$orderid)->update(['star_num'=>Db::raw("star_num-1")]);
		}
		$ganwu = Db::name('dailyjinju_ganwu_order')->where('aid',aid)->where('id',$orderid)->find();
        return $this->json(['status'=>1,'msg'=>'操作成功','star_num'=>$ganwu['star_num']]);
    }
		//活动海报
		function getposter(){
			$this->checklogin();
			$post = input('post.');
			$platform = platform;
			$page = '/pagesB/huodongbaoming/product';
			if($post['mid']){
				$mid = $post['mid'];
			}else{
				$mid = mid;
			}
			
			//if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
			//	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
			//}
			$proid = $post['proid'];
			$ganwu_order = Db::name('dailyjinju_ganwu_order')->where('aid',aid)->where('proid',$proid)->where('mid',$mid)->find();
			$member = Db::name('member')->where('aid',aid)->where('id',$mid)->find();
			$scene = 'id_'.$post['proid'].'-pid_'.$mid.'-order_'.$ganwu_order['id'];
			Db::name('dailyjinju_ganwu_order')->where('aid',aid)->where('id',$ganwu_order['id'])->update(['share_num'=>Db::raw("share_num+1")]);
			$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','dailyjinju')->where('platform',$platform)->order('id')->find();
	
			$posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',$mid)->where('scene',$scene)->where('type','dailyjinju')->where('posterid',$posterset['id'])->find();
			if(!$posterdata){
				$product = Db::name('dailyjinju_product')->where('id',$post['proid'])->find();
				$sysset = Db::name('admin_set')->where('aid',aid)->find();
				$textReplaceArr = [
					'[头像]'=>$member['headimg'],
					'[昵称]'=>$member['nickname'].'的感悟',
					'[姓名]'=>$member['realname'],
					'[手机号]'=>$member['mobile'],
					'[平台名称]'=>'- '.$sysset['name'],
					'[标题]'=>$product['name'],
					'[每日金句内容]'=>strip_tags($product['content']),
					'[商品图片]'=>$product['pic'],
					'[感悟]'=>$ganwu_order['ganwu'],
				];
	
				$poster = $this->_getposter(aid,$product['bid'],$platform,$posterset['content'],$page,$scene,$textReplaceArr);
				$posterdata = [];
				$posterdata['aid'] = aid;
				$posterdata['mid'] = $mid;
				$posterdata['scene'] = $scene;
				$posterdata['page'] = $page;
				$posterdata['type'] = 'dailyjinju';
				$posterdata['poster'] = $poster;
				$posterdata['posterid'] = $posterset['id'];
				$posterdata['createtime'] = time();
				Db::name('member_poster')->insert($posterdata);
			}
			return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
		}


}