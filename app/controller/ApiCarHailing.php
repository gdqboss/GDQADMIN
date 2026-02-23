<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 约车 custom_file(car_hailing)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\Db;
class ApiCarHailing extends ApiCommon{
    public $category = ['1' => '租车','2' => '拼车' ,'3' => '包车'];
    public $category_list = [['id' =>1,'name' =>'租车'],['id' => 2,'name' => '拼车'],['id' =>3,'name' => '包车']];
	public function getprolist1(){
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['ischecked','=',1];
		$where[] = ['ischecked','=',1];
		//$where[] = ['status','=',1];
		$nowtime = time();
		$where[] = Db::raw("`status`=1  or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime)");

		if(input('param.bid')){
			$bid = input('param.bid/d');
		}else{
			$bid = 0;
		}
		if($bid){
			$where[] = ['bid','=',$bid];
		}else{
			$business_sysset = Db::name('business_sysset')->where('aid',aid)->find();
			if(!$business_sysset || $business_sysset['status']==0 || $business_sysset['product_isshow']==0){
				$where[] = ['bid','=',0];
			}
		}
		
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order').',sort,id desc';
		}else{
			$order = 'sort desc,id desc';
		}
		//分类 
		if(input('param.cid')){
			$cid = input('post.cid') ? input('post.cid/d') : input('param.cid/d');
            $cids = Db::name('car_hailing_category')->where('pid',$cid)->column('id');
            $cids[]=$cid;
           $where[] = ['pid','in',$cids]; 
		}
		if(input('param.keyword')){
			$where[] = ['name','like','%'.input('param.keyword').'%'];
		}
		
		if(input('?param.dateIndex')){
			$dateIndex = input('param.dateIndex/d');
		}else{
			$dateIndex = 0;
		}
	
        $datelist = [];
        $datelist[] = ['date'=>date('m.d'),'week'=>$this->getweek(date('w'))];
        $datelist[] = ['date'=>date('m.d',time()+86400),'week'=>$this->getweek(date('w',time()+86400))];
        $datelist[] = ['date'=>date('m.d',time()+86400*2),'week'=>$this->getweek(date('w',time()+86400*2))];
        $datelist[] = ['date'=>date('m.d',time()+86400*3),'week'=>$this->getweek(date('w',time()+86400*3))];
        $datelist[] = ['date'=>date('m.d',time()+86400*4),'week'=>$this->getweek(date('w',time()+86400*4))];
        $datelist[] = ['date'=>date('m.d',time()+86400*5),'week'=>$this->getweek(date('w',time()+86400*5))];
        $datelist[] = ['date'=>date('m.d',time()+86400*6),'week'=>$this->getweek(date('w',time()+86400*6))];

        $nowtime = time();
        $datalist = Db::name('car_hailing_product')->where('aid',aid)->where('bid',$bid)->where('ischecked',1)->whereRaw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime)")->where($where)->order('starttime,id')->group('starttime,endtime')->select()->toArray();
        
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
    public function getprolist(){
        if(input('param.bid')){
            $bid = input('param.bid/d');
        }else{
            $bid = 0;
        }
        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',$bid];
        $where[] = ['ischecked','=',1];
        $nowtime = time();
        $where[] = Db::raw("`status`=1  or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime)");
        if(input('param.dateIndex')){
            $dateIndex = input('param.dateIndex/d');
        }else{
            $dateIndex = 0;
        }
        $week = date('w',time() + 86400 * $dateIndex);
        $date = date('Y-m-d',time() + 86400 * $dateIndex);
        $timeday = 1 + $dateIndex;
        $where[] = Db::raw("(rqtype=1 and find_in_set('{$week}',yyzhouqi)) or (rqtype=2 and yybegintime<='{$date}' and yyendtime>='{$date}') or (rqtype=3 and find_in_set('{$timeday}',yytimeday))");
        
        if(input('param.starttime')){
            $where[] = ['starttime','=',input('param.starttime')];
            $where[] = ['cid','=',2];
        }
        if(input('param.endtime')){
            $where[] = ['endtime','=',input('param.endtime')];
            $where[] = ['cid','=',2];
        }
        //分类 
        if(input('param.cid')){
            $cid = input('post.cid') ? input('post.cid/d') : input('param.cid/d');
            $cids = Db::name('car_hailing_category')->where('pid',$cid)->column('id');
            $cids[]=$cid;
            $where[] = ['pid','in',$cids];
        }

        if(input('param.type')){
            $type = input('param.type/d');
            $where[] = ['cid','=',$type];
        }
        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order').',sort,id desc';
        }else{
            $order = 'sort desc,id desc';
        }
        $prolist = Db::name('car_hailing_product')
            ->page($pagenum,$pernum)
        ->where($where)->order($order)->select()->toArray();
        $timerangelist = [];
        foreach($prolist as $k=>$v){
            $timerangelist[] = ['starttime'=>$v['starttime'],'endtime'=>$v['endtime'],'rangestr'=>$v['starttime'].' ~ '.$v['endtime']];
            $yyorderlist = Db::name('car_hailing_order')->alias('car_hailing_order')->field('member.headimg,member.nickname')->join('member member','member.id=car_hailing_order.mid')->where('car_hailing_order.proid',$v['id'])->where('car_hailing_order.yy_date',$date)->where('car_hailing_order.status','in',[1,2,3])->select()->toArray();
            if(!$yyorderlist) $yyorderlist = [];
            
            $prolist[$k]['yyorderlist'] = $yyorderlist;
             
            $prolist[$k]['leftnum'] = $v['yynum'] - count($yyorderlist);
            $starttime = $v['starttime'];
            $endtime = $v['endtime'];

            $dateIndex = $dateIndex ==-1?0:$dateIndex;
            $changedate = date('Y-m-d',time() + 86400 * $dateIndex);
            if (strtotime(date('Y-m-d H:i')) > strtotime(date($changedate.' '. $starttime)) && strtotime(date('Y-m-d H:i')) < strtotime(date($changedate .' '. $endtime))) {
                    $yystatus = 0;
                } else {
                    $yystatus = 1;
                }
                $prolist[$k]['yystatus'] = $yystatus;
            }
            return $this->json(['status' => 1, 'data' => $prolist]);
    }
	public function prolist(){
		if(input('param.bid')){
			$bid = input('param.bid/d');
		}else{
			$bid = 0;
		}
		//分类
		$clist = $this->category_list;
		if(!$clist) $clist = [];
		//日期
		$datelist = [];
		$datelist[] = ['date'=>date('m.d'),'week'=>$this->getweek(date('w'))];
		$datelist[] = ['date'=>date('m.d',time()+86400),'week'=>$this->getweek(date('w',time()+86400))];
		$datelist[] = ['date'=>date('m.d',time()+86400*2),'week'=>$this->getweek(date('w',time()+86400*2))];
		$datelist[] = ['date'=>date('m.d',time()+86400*3),'week'=>$this->getweek(date('w',time()+86400*3))];
		$datelist[] = ['date'=>date('m.d',time()+86400*4),'week'=>$this->getweek(date('w',time()+86400*4))];
		$datelist[] = ['date'=>date('m.d',time()+86400*5),'week'=>$this->getweek(date('w',time()+86400*5))];
		$datelist[] = ['date'=>date('m.d',time()+86400*6),'week'=>$this->getweek(date('w',time()+86400*6))];
		
		$nowtime = time();
		$prolist = Db::name('car_hailing_product')->where('aid',aid)->where('bid',$bid)->where('ischecked',1)->whereRaw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime)")->order('starttime,id')->group('starttime,endtime')->select()->toArray();
		$timerangelist = [];
		foreach($prolist as $v){
			$timerangelist[] = ['starttime'=>$v['starttime'],'endtime'=>$v['endtime'],'rangestr'=>$v['starttime'].' ~ '.$v['endtime']];
		}
		return $this->json(['clist'=>$clist,'datelist'=>$datelist,'timerangelist'=>$timerangelist]);
	}
	public function getweek($week){
		if($week == 0) return '七';
		if($week == 1) return '一';
		if($week == 2) return '二';
		if($week == 3) return '三';
		if($week == 4) return '四';
		if($week == 5) return '五';
		if($week == 6) return '六';
		return '';
	}
	
	//分类商品
	public function classify(){
		if(input('param.bid')){
			$bid = input('param.bid/d');
		}else{
			$bid = 0;
		}
		$clist = $this->category_list;
		return $this->json(['status'=>1,'data'=>$clist]);
	}
	//商品
	public function product(){
		$proid = input('param.id/d');
		$product = Db::name('car_hailing_product')->where('id',$proid)->where('aid',aid)->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'商品不存在']);
		if($product['status']==0) return $this->json(['status'=>0,'msg'=>'商品未上架']);
		if($product['ischecked']!=1) return $this->json(['status'=>0,'msg'=>'商品未审核']);
		
		if($product['status']==2 && (strtotime($product['start_time']) > time() || strtotime($product['end_time']) < time())){
			return $this->json(['status'=>0,'msg'=>'商品未上架']);
		}
		if($product['status']==2) $product['status']=1;
		if(!$product['pics']) $product['pics'] = $product['pic'];
		$product['pics'] = explode(',',$product['pics']);

		if($product['bid']!=0){
			$business = Db::name('business')->where('aid',aid)->where('id',$product['bid'])->field('id,name,logo,desc,tel,address,sales,kfurl')->find();
		}else{
			$business = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,desc,tel,address,kfurl')->find();
		}
		$product['detail'] = \app\common\System::initpagecontent($product['detail'],aid,mid,platform);
       
	
		if(input('?param.dateIndex')){
			$dateIndex = input('param.dateIndex');
		}else{
			$dateIndex = 0;
		}
		if($product['cid'] ==2){
            $week = date('w',time() + 86400 * $dateIndex);
            $date = date('Y-m-d',time() + 86400 * $dateIndex);
            $timeday = 1 + $dateIndex;
            $product['yy_date'] = date('m月d日',time() + 86400 * $dateIndex);

            if($product['rqtype'] == 1 && !in_array($week,explode(',',$product['yyzhouqi']))){
                return $this->json(['status'=>0,'msg'=>'当前日期不可预约']);
            }
            if($product['rqtype'] == 2 && ($product['yybegintime'] > $date || $product['yyendtime'] < $date)){
                return $this->json(['status'=>0,'msg'=>'当前日期不可预约']);
            }
            if($product['rqtype'] == 3 && !in_array($timeday,explode(',',$product['yytimeday']))){
                return $this->json(['status'=>0,'msg'=>'当前日期不可预约']);
            }
            $starttime = $product['starttime'];
            $endtime = $product['endtime'];

            $dateIndex = $dateIndex ==-1?0:$dateIndex;
            $changedate = date('Y-m-d',time() + 86400 * $dateIndex);
            if (strtotime(date('Y-m-d H:i')) > strtotime(date($changedate.' '. $starttime)) && strtotime(date('Y-m-d H:i')) < strtotime(date($changedate .' '. $endtime))) {
                $yystatus = 0;
            } else {
                $yystatus = 1;
            }
            $product['yystatus'] = $yystatus;
        }
		

		
		//是否收藏
		$rs = Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','car_hailing')->find();
		if($rs){
			$isfavorite = true;
		}else{
			$isfavorite = false;
		}

		$yyorderlist = Db::name('car_hailing_order')->alias('car_hailing_order')->field('member.headimg,member.nickname')->join('member member','member.id=car_hailing_order.mid')->where('car_hailing_order.proid',$proid)->where('car_hailing_order.yy_date',$date)->where('car_hailing_order.status','in',[1,2,3])->select()->toArray();
		$product['leftnum'] = $product['yynum'] - count($yyorderlist);
		$sysset = Db::name('car_hailing_set')->where('aid',aid)->find();
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['title'] = $product['name'];
		$rdata['isfavorite'] = $isfavorite;
		$rdata['product'] = $product;
		$rdata['business'] = $business;
		$rdata['yyorderlist'] = $yyorderlist;
		$rdata['sysset'] = $sysset;
		return $this->json($rdata);
	}


	//商品海报
	function getposter(){
		$this->checklogin();
		$post = input('post.');
		$platform = platform;
		$page = '/carhailing/product';
		$scene = 'id_'.$post['proid'].'-pid_'.$this->member['id'];
		//if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
		//	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
		//}
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','car_hailing')->where('platform',$platform)->order('id')->find();

//		$posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','car_hailing')->where('posterid',$posterset['id'])->find();
		if(true ){
			$product = Db::name('car_hailing_product')->where('id',$post['proid'])->find();
			$sysset = Db::name('admin_set')->where('aid',aid)->find();
			$textReplaceArr = [
				'[头像]'=>$this->member['headimg'],
				'[昵称]'=>$this->member['nickname'],
				'[姓名]'=>$this->member['realname'],
				'[手机号]'=>$this->member['mobile'],
				'[商城名称]'=>$sysset['name'],
				'[商品名称]'=>$product['name'],
				'[商品销售价]'=>$product['sell_price'],
				'[商品市场价]'=>$product['sell_price'],
				'[商品图片]'=>$product['pic'],
			];

			$poster = $this->_getposter(aid,$product['bid'],$platform,$posterset['content'],$page,$scene,$textReplaceArr);
			$posterdata = [];
			$posterdata['aid'] = aid;
			$posterdata['mid'] = $this->member['id'];
			$posterdata['scene'] = $scene;
			$posterdata['page'] = $page;
			$posterdata['type'] = 'car_hailing';
			$posterdata['poster'] = $poster;
			$posterdata['createtime'] = time();
			Db::name('member_poster')->insert($posterdata);
		}
		return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
	}

	//订单提交页
	public function buy(){
		$this->checklogin();

		$proid = input('param.proid/d');

		//会员折扣
        $adminset = Db::name('admin_set')->where('aid',aid)->find();
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		$userinfo = [];
		$userinfo['id'] = $this->member['id'];
		$userinfo['realname'] = $this->member['realname'];
		$userinfo['tel'] = $this->member['tel'];
		$userinfo['discount'] = $userlevel['discount'];
        $userinfo['score2money'] = $adminset['score2money'];
        $userinfo['score'] = $this->member['score'];
        $userinfo['scoredk_money'] = round($userinfo['score'] * $userinfo['score2money'],2);
        $userinfo['scoredkmaxpercent'] = $adminset['scoredkmaxpercent'];
        $userinfo['scoremaxtype'] = 0; //0最大百分比 1最大抵扣金额
        
		$product = Db::name('car_hailing_product')->where('aid',aid)->where('ischecked',1)->where('id',$proid)->find();
		$bid = $product['bid'];

		if(!$product){
			return $this->json(['status'=>0,'msg'=>'商品已下架']);
		}
		if($product['status']==0){
			return $this->json(['status'=>0,'msg'=>'商品未上架']);
		}
		if($product['status']==2 && (strtotime($product['start_time']) > time() || strtotime($product['end_time']) < time())){
			return $this->json(['status'=>0,'msg'=>'商品未上架']);
		}
		$gettj = explode(',',$product['gettj']);
		if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj) && (!in_array('0',$gettj) || $this->member['subscribe']!=1)){ //不是所有人
			if(!$product['gettjtip']) $product['gettjtip'] = '没有权限';
			return $this->json(['status'=>-4,'msg'=>$product['gettjtip'],'url'=>$product['gettjurl']]);
		}

		if(input('?param.dateIndex')){
			$dateIndex = input('param.dateIndex');
		}else{
			$dateIndex = 0;
		}
        $week = date('w',time() + 86400 * $dateIndex);
        $date = date('Y-m-d',time() + 86400 * $dateIndex);
        $product['yy_date'] = date('m月d日',time() + 86400 * $dateIndex);
        if($product['cid'] ==2){
            $timeday = 1 + $dateIndex;
            if($product['rqtype'] == 1 && !in_array($week,explode(',',$product['yyzhouqi']))){
                return $this->json(['status'=>0,'msg'=>'当前日期不可预约']);
            }
            if($product['rqtype'] == 2 && ($product['yybegintime'] > $date || $product['yyendtime'] < $date)){
                return $this->json(['status'=>0,'msg'=>'当前日期不可预约']);
            }
            if($product['rqtype'] == 3 && !in_array($timeday,explode(',',$product['yytimeday']))){
                return $this->json(['status'=>0,'msg'=>'当前日期不可预约']);
            }
            //20240605修改
            $starttime = $product['starttime'];
            $endtime = $product['endtime'];
            $dateIndex = $dateIndex ==-1?0:$dateIndex;
            $changedate = date('Y-m-d',time() + 86400 * $dateIndex);
            if (strtotime(date('Y-m-d H:i')) > strtotime(date($changedate.' '. $starttime)) && strtotime(date('Y-m-d H:i')) < strtotime(date($changedate .' '. $endtime))) {
                return $this->json(['status'=>0,'msg'=>'非预约时间']);
            }

            $yyorderlist = Db::name('car_hailing_order')->alias('car_hailing_order')->field('member.headimg,member.nickname')->join('member member','member.id=car_hailing_order.mid')->where('car_hailing_order.proid',$product['id'])->where('car_hailing_order.yy_date',$date)->where('car_hailing_order.status','in',[0,1,2,3])->select()->toArray();
            $leftnum = $product['yynum'] - count($yyorderlist);

            if($leftnum <= 0) return $this->json(['status'=>0,'msg'=>'预约人数已满']);

        }

		$ykset = db('car_hailing_set')->where('aid',aid)->find();
        if($product['cid'] ==3){
            //统计当前日期下单的数量
            $ordercount =Db::name('car_hailing_order')->where('proid',$product['id'])->where('yy_date',$date)->where('status','in',[1,2,3])->sum('buynum');
            if($ordercount >= $product['car_num']){
                return $this->json(['status'=>0,'msg'=>'可供车辆不足，请您联系客服','tourl' => $ykset['tourl']]);
            }
        }
		$leveldk_money = 0;
		if($ykset['discount']==1 && $userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			$leveldk_money = $product['sell_price'] * (1 - $userlevel['discount'] * 0.1);
		}
		$userinfo['leveldk_money'] = round($leveldk_money,2);

		$manjian_money = 0;
		$newcouponlist = [];
		$couponList = [];
//		if($product['couponids']){
//			$couponList = Db::name('coupon_record')
//			->where("bid=-1 or bid=".$bid)->where('aid',aid)->where('mid',mid)->where('couponid','in',$product['couponids'])->where('status',0)->where('minprice','<=',$product['sell_price'])->where('starttime','<=',time())->where('endtime','>',time())
//			->order('id desc')->select()->toArray();
//		}
		if($product['is_coupon']==1){
			$couponList2 = Db::name('coupon_record')
			->where("bid=-1 or bid=".$bid)->where('aid',aid)->where('mid',mid)->where('type','in','1,10')->where('status',0)->where('minprice','<=',$product['sell_price'])->where('starttime','<=',time())->where('endtime','>',time())
			->order('id desc')->select()->toArray();
			$couponList = array_merge($couponList,$couponList2);
		}
        
		foreach($couponList as $k=>$v){
			$couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$v['couponid'])->find();
			if(empty($couponinfo)){
				continue;
			}
			//不可自用
			if($couponinfo['isgive']==2){
				continue;
			}
            //适用场景
            $fwscene = [0,3];
            if(!in_array($couponinfo['fwscene'],$fwscene)){//全部可用 
                continue;
            }
			//0全场通用   5约车专用
            if(!in_array($couponinfo['fwtype'],[0,5])){
                continue;
            }
            if($couponinfo['fwtype']==5){//指定商品可用
                $productids = explode(',',$couponinfo['carhailing_productids']);
                if(!in_array($product['id'],$productids)){
                    continue;
                }
            }
            
            if($v['bid'] > 0){
                $binfo = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->find();
                $v['bname'] = $binfo['name'];
            }
            $newcouponlist[] = $v;
			
		}
		$couponList = $newcouponlist;
        $address= Db::name('member_address')->where('aid',aid)->where('mid',mid)->where('isdefault',1)->find();
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['linkman'] = $address ? $address['name'] : strval($userinfo['realname']);
		$rdata['tel'] = $address ? $address['tel'] : strval($userinfo['tel']);
		if(!$rdata['linkman']){
			$lastorder = Db::name('car_hailing_order')->where('aid',aid)->where('mid',mid)->where('linkman','<>','')->find();
			if($lastorder){
				$rdata['linkman'] = $lastorder['linkman'];
				$rdata['tel'] = $lastorder['tel'];
			}
		}
		$rdata['userinfo'] = $userinfo;
		$rdata['ykset'] = $ykset;
		$rdata['product'] = $product;
		$rdata['couponList'] = $couponList;
		$rdata['couponCount'] = count($couponList);
		$rdata['formdata'] = json_decode($product['formdata'],true);
       
	    if($rdata['formdata']){
            foreach($rdata['formdata'] as &$val){
                if($val['key'] =='selector'){
                    for($i=0;$i < count($val['val2']['label']) ;$i++){
                        if($val['val2']['value'][$i] !='' && is_numeric($val['val2']['value'][$i])){
                            $val['val2'][$i] = $val['val2']['label'][$i].' (￥'.$val['val2']['value'][$i].')';
                        } elseif($val['val2']['value'][$i] !=''& !is_numeric($val['val2']['value'][$i]) && $val['val2']['value'][$i]!=''){
                            $val['val2'][$i] = $val['val2']['label'][$i].' '.$val['val2']['value'][$i];
                        } else{
                            $val['val2'][$i] = $val['val2']['label'][$i];
                        }
                        $val['valdata'][$i]['label'] = $val['val2']['label'][$i];
                        $val['valdata'][$i]['value'] = $val['val2']['value'][$i];
                        
                    }
                    unset($val['val2']['label']);
                    unset($val['val2']['value']);
                }
            }
        }
        $yytime = [];
        $years = [];
        if($product['cid'] ==1){
            //今天的数据
            $time1 = [];
            $n_start = date('H:i');
            $n_end =     '23:50';
            if($n_start){
                $exp_start =  explode(':',$n_start);
                $exp_end =  explode(':',$n_end);
                $time1['hour'] = [];
                if($exp_start[1] > 50){
                    $exp_start[0] = $exp_start[0]+1;
                }
                for($h=$exp_start[0];$h <= $exp_end[0];$h++){
                    $time1['hour'][] = $h;
                }
                $time1['min'] = ['00'];
                for($i=1;$i < 6 ;$i++){
                    $ii = $i* 10;
                    $time1['min'][] = $ii;
                }
                $time1['min1'] = [];
                for($i2=$exp_start[1];$i2 <= 50 ;$i2++){
                    if($i2%10 ==0 && $i2 !='00'){
                        $time1['min1'][] = $i2;
                    }
                }
                $time1['min1'] =  empty($time1['min1'])?$time1['min']:$time1['min1'];
            }

            //第二天的数据
            $next_start = '00:10';
            $next_end = '23:59';
            $time2 = [];

            if($next_start){
                $exp_start =  explode(':',$next_start);
                $exp_end =  explode(':',$next_end);
                $time2['hour'] = [];
                for($h=$exp_start[0];$h <= $exp_end[0];$h++){
                    $time2['hour'][] = $h < 10 && $h > 0? '0'.ltrim($h,'0'):$h ;
                }
                $time2['min']= ['00'];
                for($i=1;$i < 6 ;$i++){
                    $ii = $i* 10;
                    $time2['min'][] = $ii;
                }
            }
            //一年的数据
            $ykset['zc_select_months']  = $ykset['zc_select_months']?$ykset['zc_select_months']:6;
            $next_year = strtotime('+'.$ykset['zc_select_months'].' days');
            $current_time = time();
            $years = [date('Y-m-d')];
            while($current_time < $next_year){
                $current_time = strtotime('+1 day', $current_time);
                $years[] = date('Y-m-d', $current_time);
            }
            //顺延后的日期
            $endyears = [date('Y-m-d')];
            $end_d = $ykset['zc_select_months'] +$ykset['zc_max_day'];
            $end_current_time = time();
            $next_end_year =   strtotime('+'.$end_d.' days');
            while($end_current_time < $next_end_year){
                $end_current_time = strtotime('+1 day', $end_current_time);
                $endyears[] =date('Y-m-d', $end_current_time) ;
            }
            $yytime = [
                $time1,
                $time2
            ] ;
        }
        
        $rdata['yytime'] = $yytime;
        $rdata['years'] =   $years;
        $rdata['endyears'] =   $endyears;
        
		return $this->json($rdata);
	}

	public function createOrder(){
		$this->checklogin();
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		$post = input('post.');
        $num = $post['num']?$post['num']:1;
        $buynum = $post['buynum']?$post['buynum']:1;
		$address = ['id'=>0,'name'=>$post['linkman'],'tel'=>$post['tel'],'area'=>'','address'=>''];
		
		$proid = input('param.proid/d');

		//会员折扣
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		$userinfo = [];
		$userinfo['id'] = $this->member['id'];
		$userinfo['realname'] = $this->member['realname'];
		$userinfo['tel'] = $this->member['tel'];
		$userinfo['discount'] = $userlevel['discount'];
		$product = Db::name('car_hailing_product')->where('aid',aid)->where('ischecked',1)->where('id',$proid)->find();
		$bid = $product['bid'];
        $ykset = db('car_hailing_set')->where('aid',aid)->find();
        
		if(!$product){
			return $this->json(['status'=>0,'msg'=>'商品已下架']);
		}
		if($product['status']==0){
			return $this->json(['status'=>0,'msg'=>'商品未上架']);
		}
		if($product['status']==2 && (strtotime($product['start_time']) > time() || strtotime($product['end_time']) < time())){
			return $this->json(['status'=>0,'msg'=>'商品未上架']);
		}
		$gettj = explode(',',$product['gettj']);
		if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj) && (!in_array('0',$gettj) || $this->member['subscribe']!=1)){ //不是所有人
			if(!$product['gettjtip']) $product['gettjtip'] = '没有权限预约该课程';
			return $this->json(['status'=>-4,'msg'=>$product['gettjtip'],'url'=>$product['gettjurl']]);
		}
		
		if(input('?param.dateIndex')){
			$dateIndex = input('param.dateIndex');
		}else{
			$dateIndex = 0;
		}
        $date = date('Y-m-d',time() + 86400 * $dateIndex);
		if($product['cid'] ==2){
            $week = date('w',time() + 86400 * $dateIndex);
            $timeday = 1 + $dateIndex;
            $product['yy_date'] = date('m月d日',time() + 86400 * $dateIndex);

            if($product['rqtype'] == 1 && !in_array($week,explode(',',$product['yyzhouqi']))){
                return $this->json(['status'=>0,'msg'=>'当前日期不可预约']);
            }
            if($product['rqtype'] == 2 && ($product['yybegintime'] > $date || $product['yyendtime'] < $date)){
                return $this->json(['status'=>0,'msg'=>'当前日期不可预约']);
            }
            if($product['rqtype'] == 3 && !in_array($timeday,explode(',',$product['yytimeday']))){
                return $this->json(['status'=>0,'msg'=>'当前日期不可预约']);
            }


            //20240605修改
            $endtime = strtotime($date.' '.$product['endtime']);
            if( time() >$endtime - $product['prehour']*3600 ){
                return $this->json(['status'=>0,'msg'=>'已结束预约']);
            }
            $yyorderlist = Db::name('car_hailing_order')->alias('car_hailing_order')
                ->field('member.headimg,member.nickname')
                ->join('member member','member.id=car_hailing_order.mid')
                ->where('car_hailing_order.proid',$product['id'])
                ->where('car_hailing_order.yy_date',$date)
                ->where('car_hailing_order.status','in',[0,1,2,3])
                ->select()->toArray();
            $leftnum = $product['yynum'] - count($yyorderlist);

            if($leftnum <= 0) return $this->json(['status'=>0,'msg'=>'预约人数已满']);
    
        }
        if($product['cid'] ==3){
            //统计当前日期下单的数量
            $ordercount =Db::name('car_hailing_order')->where('proid',$product['id'])->where('yy_date',$date)->where('status','in',[1,2,3])->sum('buynum');
            if($ordercount+$buynum > $product['car_num']){
                $sy_count = $product['car_num'] - $ordercount ;
                $sy_count =  $sy_count <0?0:$sy_count;
                return $this->json(['status'=>0,'msg'=>'可供车辆'.$sy_count.'辆，请您联系客服','tourl' => $ykset['tourl']]);
            }
        }

		$leveldk_money = 0;
		if($ykset['discount']==1 && $userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			$leveldk_money = $product['sell_price'] * (1 - $userlevel['discount'] * 0.1);
		}
		$leveldk_money = round($leveldk_money,2);
		
		$totalprice = $product['sell_price']*$num*$buynum - $leveldk_money;

		//优惠券
		if($post['couponrid'] > 0){
			$couponrid = $post['couponrid'];
			$couponrecord = Db::name('coupon_record')->where("bid=-1 or bid=".$bid)->where('aid',aid)->where('mid',mid)->where('id',$couponrid)->find();
			if(!$couponrecord){
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不存在']);
			}elseif($couponrecord['status']!=0){
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'已使用过了']);
			}elseif($couponrecord['starttime'] > time()){
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'尚未开始使用']);	
			}elseif($couponrecord['endtime'] < time()){
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'已过期']);	
			}elseif($couponrecord['minprice'] > $totalprice){
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
			}elseif($couponrecord['type']!=1 && $couponrecord['type']!=4 && $couponrecord['type']!=10 && !in_array($couponrecord['couponid'],explode(',',$product['couponids']))){
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
			}
			$couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$couponrecord['couponid'])->find();
            if(empty($couponinfo)){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不存在或已作废']);
            }
            if($couponrecord['from_mid']==0 && $couponinfo && $couponinfo['isgive']==2){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'仅可转赠']);
            }
            //适用场景
            $fwscene = [0,3];
            if(!in_array($couponinfo['fwscene'],$fwscene)){//全部可用 
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'超出可用范围']);
            }
            //0全场通用,4指定服务商品
            if(!in_array($couponinfo['fwtype'],[0,5])){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'超出可用范围']);
            }
			if($couponrecord['type']==3){//计次券 抵扣全部金额
				$coupon_money = $totalprice;
			}elseif($couponrecord['type']==10){//折扣券
				$coupon_money = $totalprice * (100 - $couponrecord['discount']) * 0.01;
			}else{
				$coupon_money = $couponrecord['money'];
				if($coupon_money > $totalprice) $coupon_money = $totalprice;
			}

			if($couponrecord['type']==3){
				if($couponrecord['used_count'] >= $couponrecord['limit_count']) {
					return json(['status'=>0,'msg'=>'已使用全部次数']);
				}
				Db::name('coupon_record')->where('aid',aid)->where('id',$couponrecord['id'])->inc('used_count',1)->update();
				$hxorder = [];
				$hxorder['aid'] = aid;
				$hxorder['bid'] = $bid;
				$hxorder['uid'] = 0;
				$hxorder['mid'] = mid;
				$hxorder['orderid'] = $couponrecord['id'];
				$hxorder['ordernum'] = date('YmdHis');
				$hxorder['title'] = $couponrecord['couponname'];
				$hxorder['type'] = 'coupon';
				$hxorder['createtime'] = time();
				$hxorder['remark'] = '购买'.$product['name'];
				Db::name('hexiao_order')->insert($hxorder);
				if($couponrecord['used_count']+1>=$couponrecord['limit_count']){
					Db::name('coupon_record')->where('id',$couponrecord['id'])->update(['status'=>1,'usetime'=>time()]);
				}
			}else{
				Db::name('coupon_record')->where('id',$couponrid)->update(['status'=>1,'usetime'=>time()]);
			}
		}else{
			$couponrid = '';
			$coupon_money = 0;
		}
		$totalprice = $totalprice - $coupon_money;

        //积分抵扣
        $scoredkscore = 0;
        $scoredk_money = 0;
        $alltotalscore = 0;
        if($post['usescore']==1 ){
            $score2money = $this->sysset['score2money'];
            $scoredkmaxpercent = $this->sysset['scoredkmaxpercent'];
            $scoredk_money = ($this->member['score'] - $alltotalscore) * $score2money;
            
            if($scoredk_money > $totalprice) $scoredk_money = $totalprice;
            
            if($scoredkmaxpercent >= 0 && $scoredkmaxpercent < 100 && $scoredk_money > 0 && $scoredk_money > $totalprice * $scoredkmaxpercent * 0.01){
                $scoredk_money = $totalprice * $scoredkmaxpercent * 0.01;
            }
            $totalprice = $totalprice - $scoredk_money;
            if($scoredk_money > 0){
                $scoredkscore = intval($scoredk_money / $score2money);
            }
            $alltotalscore += $scoredkscore;
        }
		
		
	    //计算表单的数据
        if($post['formdata']){
            foreach($post['formdata'] as  $val){
                if(isset($val['label']) && floatval($val['value']) > 0 ){
                    $totalprice = bcadd($totalprice ,$val['value'],2); 
                }
            }
        }
		if($totalprice < 0) $totalprice = 0;
		$ordernum = date('ymdHis').rand(100000,999999);
		$orderdata = [];
        if($product['commissionset']!=-1){
            $commission_totalprice = $totalprice;
            if($this->member['pid']){
                $parent1 = Db::name('member')->where('aid',aid)->where('id',$this->member['pid'])->find();
                if($parent1){
                    $agleveldata1 = Db::name('member_level')->where('aid',aid)->where('id',$parent1['levelid'])->find();
                    if($agleveldata1['can_agent']!=0){
                        $orderdata['parent1'] = $parent1['id'];
                    }
                }
            }
            if($parent1['pid']){
                $parent2 = Db::name('member')->where('aid',aid)->where('id',$parent1['pid'])->find();
                if($parent2){
                    $agleveldata2 = Db::name('member_level')->where('aid',aid)->where('id',$parent2['levelid'])->find();
                    if($agleveldata2['can_agent']>1){
                        $orderdata['parent2'] = $parent2['id'];
                    }
                }
            }
            if($parent2['pid']){
                $parent3 = Db::name('member')->where('aid',aid)->where('id',$parent2['pid'])->find();
                if($parent3){
                    $agleveldata3 = Db::name('member_level')->where('aid',aid)->where('id',$parent3['levelid'])->find();
                    if($agleveldata3['can_agent']>2){
                        $orderdata['parent3'] = $parent3['id'];
                    }
                }
            }
            if($product['commissionset']==1){//按商品设置的分销比例
                $commissiondata = json_decode($product['commissiondata1'],true);
                if($commissiondata){
                    if($agleveldata1) $orderdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01;
                    if($agleveldata2) $orderdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $commission_totalprice * 0.01;
                    if($agleveldata3) $orderdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $commission_totalprice * 0.01;
                }
            }elseif($product['commissionset']==2){//按固定金额
                $commissiondata = json_decode($product['commissiondata2'],true);
                if($commissiondata){
                    if($agleveldata1) $orderdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'];
                    if($agleveldata2) $orderdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'];
                    if($agleveldata3) $orderdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'];
                }
            }elseif($product['commissionset']==3){//提成是积分
                $commissiondata = json_decode($product['commissiondata3'],true);
                if($commissiondata){
                    if($agleveldata1) $orderdata['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'];
                    if($agleveldata2) $orderdata['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'];
                    if($agleveldata3) $orderdata['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'];
                }
            }else{ //按会员等级设置的分销比例
                if($agleveldata1){
                    if($agleveldata1['commissiontype']==1){ //固定金额按单
                        $orderdata['parent1commission'] = $agleveldata1['commission1'];
                    }else{
                        $orderdata['parent1commission'] = $agleveldata1['commission1'] * $commission_totalprice * 0.01;
                    }
                }
                if($agleveldata2){
                    if($agleveldata2['commissiontype']==1){
                        $orderdata['parent2commission'] = $agleveldata2['commission2'];
                    }else{
                        $orderdata['parent2commission'] = $agleveldata2['commission2'] * $commission_totalprice * 0.01;
                    }
                }
                if($agleveldata3){
                    if($agleveldata3['commissiontype']==1){
                        $orderdata['parent3commission'] = $agleveldata3['commission3'];
                    }else{
                        $orderdata['parent3commission'] = $agleveldata3['commission3'] * $commission_totalprice * 0.01;
                    }
                }
            }

        }
		
		$orderdata['aid'] = aid;
		$orderdata['mid'] = mid;
		$orderdata['bid'] = $bid;
		$orderdata['ordernum'] = $ordernum;
        $orderdata['num'] = $num;
		$orderdata['title'] = $product['name'];
		$orderdata['linkman'] = $address['name'];
		$orderdata['tel'] = $address['tel'];
		$orderdata['area'] = $address['area'];
		$orderdata['address'] = $address['address'];
		$orderdata['longitude'] = $address['longitude'];
		$orderdata['latitude'] = $address['latitude'];
		$orderdata['area2'] = $address['province'].','.$address['city'].','.$address['district'];
		$orderdata['totalprice'] = $totalprice;
		if($totalprice == 0){
			$orderdata['status'] = 1;
			$orderdata['paytime'] = time();
		}
		$orderdata['leveldk_money'] = $leveldk_money;	//会员折扣
		$orderdata['product_price'] = $product['sell_price'];
		$orderdata['coupon_money'] = $coupon_money;		//优惠券抵扣
		$orderdata['coupon_rid'] = $couponrid;
		$orderdata['yy_date'] = $date; //预约时间
		$orderdata['yy_time'] = $date.' '.$product['starttime'].' ~ '.$product['endtime'];
        $orderdata['start_time'] =  $product['starttime'];
        $orderdata['end_time'] =  $product['endtime'];
		$orderdata['createtime'] = time();
		$orderdata['platform'] = platform;
		$orderdata['hexiao_code'] = random(16);
		$orderdata['remark'] = $post['remark'];
		$orderdata['proname'] = $product['name'];
		$orderdata['propic'] = $product['pic'];
		$orderdata['proid'] = $product['id'];
		$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=car_hailing&co='.$orderdata['hexiao_code']));
		//租车新增时间
        $orderdata['zc_start_time'] =    $post['start_time'];
        $orderdata['zc_end_time'] =    $post['end_time'];
        
        $orderdata['scoredk_money'] = $scoredk_money;
        $orderdata['scoredkscore'] = $scoredkscore;
        $orderdata['buynum'] = $buynum;
        
		$orderid = Db::name('car_hailing_order')->insertGetId($orderdata);
		//加入佣金记录
        if($orderdata['parent1'] && ($orderdata['parent1commission'] || $orderdata['parent1score'])){
            Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent1'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$product['id'],'type'=>'car_hailing','commission'=>$orderdata['parent1commission'],'score'=>$orderdata['parent1score'],'remark'=>'下级购买商品奖励','createtime'=>time()]);
        }
        if($orderdata['parent2'] && ($orderdata['parent2commission'] || $orderdata['parent2score'])){
            Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent2'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$product['id'],'type'=>'car_hailing','commission'=>$orderdata['parent2commission'],'score'=>$orderdata['parent2score'],'remark'=>'下二级购买商品奖励','createtime'=>time()]);
        }
        if($orderdata['parent3'] && ($orderdata['parent3commission'] || $orderdata['parent3score'])){
            Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent3'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$product['id'],'type'=>'car_hailing','commission'=>$orderdata['parent3commission'],'score'=>$orderdata['parent3score'],'remark'=>'下三级购买商品奖励','createtime'=>time()]);
        }
		$this->saveformdata($orderid,'car_hailing_order',$post['formdata'],$product['id']);
		if($orderdata['status']==0 && $orderdata['totalprice']>0){
			$payorderid = \app\model\Payorder::createorder(aid,$orderdata['bid'],$orderdata['mid'],'car_hailing',$orderid,$orderdata['ordernum'],$orderdata['title'],$orderdata['totalprice'],$alltotalscore);
		}
		if($orderdata['status']==1){
			//短信通知
			if($orderdata['tel']){
				$rs = \app\common\Sms::send(aid,$orderdata['tel'],'tmpl_yysucess',['name'=>$orderdata['title'],'time'=>$orderdata['yy_time']]);
			}
		}
		Db::name('car_hailing_product')->where('aid',aid)->where('id',$product['id'])->update(['sales'=>Db::raw("sales+1")]);
		return $this->json(['status'=>1,'payorderid'=>$payorderid,'msg'=>'提交成功']);
	}

	//保存自定义表单内容
	function saveformdata($orderid,$type='car_hailing_order',$formdata,$proid){
		if(!$orderid || !$formdata) return ['status'=>0];
		//根据orderid 取出proid
		$formfield = Db::name('car_hailing_product')->where('id',$proid)->find();
		$formdataSet = json_decode($formfield['formdata'],true);
		//var_dump($formdataSet);die;
		$data = [];
		foreach($formdataSet as $k=>$v){
		    if(!isset($formdata['form'.$k])){
		            continue;
            }
			$value = $formdata['form'.$k];
			if(is_array($value)){
				$value = implode(',',$value);
			}
			$value = strval($value);
			$data['form'.$k] = $v['val1'] . '^_^' .$value . '^_^' .$v['key'];
			if($v['val3']==1 && $value===''){
				return ['status'=>0,'msg'=>$v['val1'].' 必填'];
			}
		}
		$data['aid'] = aid;
		$data['type'] = 'car_hailing_order';
		$data['orderid'] = $orderid;
		$data['createtime'] = time();
		
		Db::name('freight_formdata')->insert($data);
		return ['status'=>1];
	}
	//订单列表
	function orderlist(){
		$this->checklogin();
		$st = input('param.st');
		if(!$st && $st!=='0') $st = 'all';
		$pagenum = input('param.pagenum') ? input('param.pagenum') : 1;
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
		}elseif($st == '2'){
			$where[] = ['status','=',2];
		}elseif($st == '3'){
			$where[] = ['status','=',3];
		}elseif($st == '4'){
			$where[] = ['status','=',4];
		}
		
		$datalist = Db::name('car_hailing_order')->where($where)->order('id desc')->page($pagenum,10)->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $key=>$v){
			if($v['bid']!=0){
				$datalist[$key]['binfo'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->field('id,name,logo')->find();
			}
			$datalist[$key]['senddate'] = date('Y-m-d H:i:s',$v['send_time']);
			$product = Db::name('car_hailing_product')->where('aid',aid)->where('id',$v['proid'])->field('id,cid')->find();
            $datalist[$key]['cid'] = $product['cid'];
		}
        $sysset = Db::name('car_hailing_set')->where('aid',aid)->find();
		$rdata = [];
		$rdata['st'] = $st;
		$rdata['datalist'] = $datalist;
		$rdata['sysset'] = $sysset;
		return $this->json($rdata);
	}

	public function orderdetail(){
		$detail = Db::name('car_hailing_order')->where('id',input('param.id/d'))->where('aid',aid)->where('mid',mid)->find();
		if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\model\Freight::getformdata($detail['id'],'car_hailing_order');
		if($detail['formdata']){
		    foreach($detail['formdata'] as  &$val){
		           if($val[2] =='selector'){
		                $str = explode( ',', $val[1]);
		                if($str && is_numeric($str[1])){
                            $val[1] = $str[0].'(￥'.$str[1].')';
                        }
                   }
            }
        }
		$storeinfo = [];
		if($detail['freight_type'] == 1){
            $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('id,name,address,longitude,latitude')->find();
		}
		
		if($detail['bid'] > 0){
			$detail['binfo'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->field('id,name,logo')->find();
			$iscommentdp = 0;
			$commentdp = Db::name('business_comment')->where('orderid',$detail['id'])->where('aid',aid)->where('mid',mid)->find();
			if($commentdp) $iscommentdp = 1;
		}else{
			$iscommentdp = 1;
		}
		
		$yuekeset = Db::name('car_hailing_set')->where('aid',aid)->field('autoclose')->find();
		
		if($detail['status']==0 && $yuekeset['autoclose'] > 0 && $detail['paytypeid'] != 5){
			$lefttime = strtotime($detail['createtime']) + $yuekeset['autoclose']*60 - time();
			if($lefttime < 0) $lefttime = 0;
		}else{
			$lefttime = 0;
		}
        $product = Db::name('car_hailing_product')->where('aid',aid)->where('id',$detail['proid'])->field('id,cid')->find();
        $detail['cid'] = $product['cid'];
		$rdata = [];
		$rdata['detail'] = $detail;
		$rdata['iscommentdp'] = $iscommentdp;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['lefttime'] = $lefttime;
        $sysset = Db::name('car_hailing_set')->where('aid',aid)->find();
        $rdata['sysset'] = $sysset;
        //发票
        $rdata['invoice'] = 0;
        if($detail['bid']) {
            $rdata['invoice'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->value('invoice');
        } else {
            $rdata['invoice'] = Db::name('admin_set')->where('aid',aid)->value('invoice');
        }
		return $this->json($rdata);
	}
	public function getRefundMoney(){
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('car_hailing_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
        $product =  Db::name('car_hailing_product')->where('aid',aid)->where('ischecked',1)->where('id',$order['proid'])->field('id,cid')->find();
        $sysset = Db::name('car_hailing_set')->where('aid',aid)->find();
        $start_time_ratio = json_decode($sysset['start_time_ratio'],true);
        $end_time_ratio = json_decode($sysset['end_time_ratio'],true);
        $refund_money = $order['totalprice'];
        $start_time = $order['yy_date'].' '.$order['start_time'];
        $end_time = $order['yy_date'].' '.$order['end_time'];
        $now_time = date('Y-m-d H:i:s');
        $refund_remark = '';
        if($product['cid'] ==2){
            //发车前
            if($now_time <= $start_time){
                $dif_time =( strtotime($start_time) - strtotime($now_time))/3600; //开始前几小时
                if($start_time_ratio && $dif_time > 0){
                    foreach($start_time_ratio as $val){
                        if($dif_time <= $val['start_time'] ){
                            $refund_money =bcmul( $refund_money , $val['start_ratio']*0.01,2);
                            $refund_remark = '退款时间在开始前'.$val['start_time'].'小时之内，退还金额的'.$val['start_ratio'].'%';
                        }
                    }
                }
            }
            //发车后
            if($now_time >= $end_time){
                $dif_time =( strtotime($now_time) - strtotime($end_time))/3600; //开始前几小时
                
                if($end_time_ratio && $dif_time > 0){
                    foreach($end_time_ratio as $v){
                        if($dif_time >= $v['end_time'] ){
                            $refund_money = bcmul($refund_money , $v['end_ratio']*0.01,2);
                            
                            $refund_remark = '退款时间在结束后'.intval($v['end_time']).'小时之后，退还金额的'.$v['end_ratio'].'%';
                        }
                    }
                }
            }

        }
       
        return $this->json(['refund_money' => $refund_money,'refund_remark' => $refund_remark]);
    }
	function refund(){//申请退款
		$this->checklogin();
		if(request()->isPost()){
			$post = input('post.');
			$orderid = intval($post['orderid']);
			$money = floatval($post['money']);
			$order = Db::name('car_hailing_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
			if($order['worker_orderid']>0){
				return $this->json(['status'=>0,'msg'=>'订单接单后不允许退款']);
			}
			if(!$order || ($order['status']!=1 && $order['status'] != 2) || $order['refund_status'] == 2){
				return $this->json(['status'=>0,'msg'=>'订单状态不符合退款要求']);
			}
			if($money < 0 || $money > $order['totalprice']){
				return $this->json(['status'=>0,'msg'=>'退款金额有误']);
			}
	
            Db::name('car_hailing_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['refund_time'=>time(),'refund_status'=>1,'refund_reason'=>$post['reason'],'refund_money'=>$money,'refund_checkremark' =>$post['refund_remark']]);
//            $tmplcontent = [];
//            $tmplcontent['first'] = '有服务订单客户申请退款';
//            $tmplcontent['remark'] = '点击进入查看~';
//            $tmplcontent['keyword1'] = $order['ordernum'];
//            $tmplcontent['keyword2'] = $money.'元';
//            $tmplcontent['keyword3'] = $post['reason'];
//            \app\common\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,m_url('admin/order/yuekeorder'),$order['mdid']);
//
//            $tmplcontent = [];
//            $tmplcontent['thing1'] = $order['title'];
//            $tmplcontent['character_string4'] = $order['ordernum'];
//            $tmplcontent['amount2'] = $order['totalprice'];
//            $tmplcontent['amount9'] = $money.'元';
//            $tmplcontent['thing10'] = $post['reason'];
//            \app\common\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,'admin/order/yuekeorder',$order['mdid']);

            return $this->json(['status'=>1,'msg'=>'提交成功,请等待商家审核']);
		}
		$rdata = [];
		$rdata['price'] = input('param.price/f');
		$rdata['orderid'] = input('param.orderid/d');
		$order = Db::name('car_hailing_order')->where('aid',aid)->where('mid',mid)->where('id',$rdata['orderid'])->find();
		$rdata['price'] = $order['totalprice'];
		return $this->json($rdata);
	}

	 //获取接下来一周的日期
    function GetWeeks($yyzhouqi) {
		//查看今天周几
		$zqarr = explode(',',$yyzhouqi);
        $i=0;
        $weeks=[];
        for ($i;$i<7;$i++){
			$year=date('Y',time()+86400*$i).'年';
            $month=date('m',time()+86400*$i).'月';
            $day=date('d',time()+86400*$i);
            $week=date('w',time()+86400*$i);
            if ($week=='1'){
                $week='周一';
				$key=1;
            }elseif ($week=='2'){
                $week='周二';
				$key=2;
            }elseif ($week=='3'){
                $week='周三';
				$key=3;
            }elseif ($week=='4'){
                $week='周四';
				$key=4;
            }elseif ($week=='5'){
                $week='周五';
				$key =5;
            }elseif ($week=='6'){
                $week='周六';
				$key=6;
            }elseif ($week=='0'){
                $week='周日';
				$key=0;
            }
			$weeks[$i]['key'] = $key;
			$weeks[$i]['weeks'] = $week;
			$weeks[$i]['date'] = $month.$day;
			$weeks[$i]['year'] = $year;
            //array_push($weeks,$month.$day."(".$week."）");
			$newweek = [];
			foreach($weeks as $k=>$w){
				if(!in_array($w['key'],$zqarr)){
					unset($weeks[$k]);
				}
			}
        }
	    $weeks=array_values($weeks);
        return $weeks;
    }
	
	function closeOrder(){
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('car_hailing_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || $order['status']>1){
			return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		//优惠券抵扣的返还
		if($order['coupon_rid'] > 0){
			//查看是不是计次卡
			$record = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('id',$order['coupon_rid'])->find();
			if($record['type']==3){  //将次数加回去
				Db::name('coupon_record')->where('aid',aid)->where('bid',$order['bid'])->where('id',$record['id'])->dec('used_count')->update();
				$hxorder = [];
				$hxorder['aid'] = aid;
				$hxorder['bid'] = $order['bid'];
				$hxorder['uid'] = 0;
				$hxorder['mid'] = mid;
				$hxorder['orderid'] = $record['id'];
				$hxorder['ordernum'] = date('YmdHis');
				$hxorder['title'] = $record['couponname'];
				$hxorder['type'] = 'coupon';
				$hxorder['createtime'] = time();
				$hxorder['remark'] = '订单取消:'.$order['title'];
				Db::name('hexiao_order')->insert($hxorder);
				if($record['status']==1)
					Db::name('coupon_record')->where('id',$record['id'])->update(['status'=>0,'usetime'=>'']);
			}else{
				Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);	
			}
		}
		$rs = Db::name('car_hailing_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>4]);
		return $this->json(['status'=>1,'msg'=>'取消成功']);
	}
	function delOrder(){
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('car_hailing_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || ($order['status']!=4 && $order['status']!=3)){
			return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
		}
		if($order['status']==3){
			$rs = Db::name('car_hailing_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['delete'=>1]);
		}else{
			$rs = Db::name('car_hailing_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->delete();
		}
		return $this->json(['status'=>1,'msg'=>'删除成功']);
	}
	function orderCollect(){ //确认完成
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('car_hailing_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
		if(!$order || ($order['status']!=1) || $order['paytypeid']==4){
			return $this->json(['status'=>0,'msg'=>'订单状态不符合收货要求']);
		}
		
		$rs = \app\common\Order::collect($order,'car_hailing');
		if($rs['status'] == 0) return $this->json($rs);

		Db::name('car_hailing_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);

		$return = ['status'=>1,'msg'=>'操作成功','url'=>true];
		
		$tmplcontent = [];
		$tmplcontent['first'] = '有订单客户已确认完成';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $this->member['nickname'];
		$tmplcontent['keyword2'] = $order['ordernum'];
		$tmplcontent['keyword3'] = $order['totalprice'].'元';
		$tmplcontent['keyword4'] = date('Y-m-d H:i',$order['paytime']);
        $tmplcontentNew = [];
        $tmplcontentNew['thing3'] = $this->member['nickname'];//收货人
        $tmplcontentNew['character_string7'] = $order['ordernum'];//订单号
        $tmplcontentNew['time8'] = date('Y-m-d H:i');//送达时间
		\app\common\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_ordershouhuo',$tmplcontent,m_url('carhailing/orderlist'),$order['mdid'],$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['character_string6'] = $order['ordernum'];
		$tmplcontent['thing3'] = $this->member['nickname'];
		$tmplcontent['date5'] = date('Y-m-d H:i');
		\app\common\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_ordershouhuo',$tmplcontent,'carhailing/orderlist',$order['mdid']);

		return $this->json($return);
	}
	
	public function getyytime($yydate,$proid){
		$yydate = explode('-',$yydate);
		//开始时间
		$begindate = date('Y年').$yydate[0];
		$begindate = preg_replace(['/年|月/','/日/'],['-',''],$begindate);
		$begintime = strtotime($begindate);
		$ends = explode(' ',$yydate[0]);

		$where[] = ['begintime','=',$begintime];
		$count = 0 + Db::name('car_hailing_order')->where($where)->where('aid',aid)->where('status','in','1,2')->where('proid',$proid)->count();
		return $count;
	}

	public function classify2(){
        if(input('param.bid')){
            $bid = input('param.bid/d');
            $clist = Db::name('car_hailing_category')->where('aid',aid)->where('bid',$bid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
            foreach($clist as $k=>$v){
                $child = Db::name('car_hailing_category')->where('aid',aid)->where('bid',$bid)->where('pid',$v['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
                if(!$child) $child = [];
                foreach($child as $k2=>$v2){
                    $child2 = Db::name('car_hailing_category')->where('aid',aid)->where('bid',$bid)->where('pid',$v2['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
                    $child[$k2]['child'] = $child2;
                }
                $clist[$k]['child'] = $child;
            }
        }else{
            
            $clist = Db::name('car_hailing_category')->where('aid',aid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
            foreach($clist as $k=>$v){
                $child = Db::name('car_hailing_category')->where('aid',aid)->where('pid',$v['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
                if(!$child) $child = [];
                foreach($child as $k2=>$v2){
                    $child2 = Db::name('car_hailing_category')->where('aid',aid)->where('pid',$v2['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
                    $child[$k2]['child'] = $child2;
                }
                $clist[$k]['child'] = $child;
            }
        }
        return $this->json(['status'=>1,'data'=>$clist]);
    }
}