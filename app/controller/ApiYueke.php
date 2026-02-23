<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 约课 custom_file(yueke)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\Db;
class ApiYueke extends ApiCommon{
	public function getprolist(){
		$where = [];
		$where[] = ['aid','=',aid];
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
			//子分类
			$clist = Db::name('yueke_category')->where('aid',aid)->where('pid',$cid)->column('id');
			if($clist){
				$clist2 = Db::name('yueke_category')->where('aid',aid)->where('pid','in',$clist)->column('id');
				$cCate = array_merge($clist, $clist2, [$cid]);
				if($cCate){
					$whereCid = [];
					foreach($cCate as $k => $c2){
						$whereCid[] = "find_in_set({$c2},cid)";
					}
                    $where[] = Db::raw(implode(' or ',$whereCid));
				}
			} else {
                $where[] = Db::raw("find_in_set(".$cid.",cid)");
            }
		}
		if(input('param.keyword')){
			$where[] = ['name','like','%'.input('param.keyword').'%'];
		}
		if(input('?param.dateIndex')){
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
		}
		if(input('param.endtime')){
			$where[] = ['endtime','=',input('param.endtime')];
		}

		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('yueke_product')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
		if(!$datalist) $datalist = [];

		$yueke_extend = getcustom('yueke_extend');
        foreach($datalist as $k=>$v){
            $workerinfo = Db::name('yueke_worker')->field('realname,headimg,tel,dengji')->where('id',$v['workerid'])->find();
            //一对一陪教 随机分配老师不显示老师信息
            if($yueke_extend && $v['select_worker'] == 1){
                $workerinfo = '';
            }
            $datalist[$k]['workerinfo'] = $workerinfo;
            $yyorderwhere = [];
            //一对一陪教 不受时间限制
            if($yueke_extend || ($v['yuyue_model'] && $v['yuyue_model'] == 2)) {
                $dateTime = time() + 86400 * $dateIndex;
                $todayEnd = strtotime(date('Y-m-d 23:59:59',$dateTime))+1;//当天的结束时间
                $todayStart = strtotime(date('Y-m-d 00:00:00',$dateTime));//当天的开始时间
                $yyorderwhere[] = ['yueke_order.createtime','between',[$todayStart,$todayEnd]];
            }else{
                $yyorderwhere[] = ['yueke_order.yy_date','=',$date];
            }
            $yyorderlist = Db::name('yueke_order')->alias('yueke_order')->field('member.headimg,member.nickname')->join('member member','member.id=yueke_order.mid')->where('yueke_order.proid',$v['id'])->where($yyorderwhere)->where('yueke_order.status','in',[1,2,3])->select()->toArray();
			if(!$yyorderlist) $yyorderlist = [];
			$datalist[$k]['yyorderlist'] = $yyorderlist;

            //一对一陪教 无剩余预约名额限制
            if($yueke_extend && isset($v['yuyue_model']) && $v['yuyue_model'] == 2) {
                $datalist[$k]['leftnum'] = 0;
                $datalist[$k]['isend'] = false;
            }else{
                $datalist[$k]['leftnum'] = $v['yynum'] - count($yyorderlist);
                $starttime = strtotime($date.' '.$v['starttime']);
                if($starttime < time() + $v['prehour']*3600){
                    $datalist[$k]['isend'] = true;
                }else{
                    $datalist[$k]['isend'] = false;
                }
            }
		}
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	public function prolist(){
		if(input('param.bid')){
			$bid = input('param.bid/d');
		}else{
			$bid = 0;
		}
		//分类
		$clist = Db::name('yueke_category')->where('aid',aid)->where('bid',$bid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
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
		$prolist = Db::name('yueke_product')->where('aid',aid)->where('bid',$bid)->where('ischecked',1)->whereRaw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime)")->order('starttime,id')->group('starttime,endtime')->select()->toArray();
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
		$clist = Db::name('yueke_category')->where('aid',aid)->where('pid',0)->where('bid',$bid)->where('status',1)->order('sort desc,id')->select()->toArray();
		foreach($clist as $k=>$v){
			$rs = Db::name('yueke_category')->where('aid',aid)->where('pid',$v['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$rs) $rs = [];
			$clist[$k]['child'] = $rs;
		}
		return $this->json(['status'=>1,'data'=>$clist]);
	}
	//商品
	public function product(){
		$proid = input('param.id/d');
		$product = Db::name('yueke_product')->where('id',$proid)->where('aid',aid)->find();
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
		

		//$where[] = Db::raw("(rqtype=1 and find_in_set('{$week}',yyzhouqi)) or (rqtype=2 and yybegintime<='{$date}' and yyendtime>='{$date}') or (rqtype=3 and find_in_set('{$timeday}',yytimeday))");

		if(input('?param.dateIndex')){
			$dateIndex = input('param.dateIndex');
		}else{
			$dateIndex = 0;
		}
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
		
		$starttime = strtotime($date.' '.$product['starttime']);
		if($starttime < time() + $product['prehour']*3600){
			$product['isend'] = true;
		}else{
			$product['isend'] = false;
		}

		//是否收藏
		$rs = Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','yueke')->find();
		if($rs){
			$isfavorite = true;
		}else{
			$isfavorite = false;
		}

		$workerinfo = Db::name('yueke_worker')->where('aid',aid)->where('id',$product['workerid'])->field('id,realname,tel,headimg,dengji,desc')->find();
        //一对一陪教 随机分配老师不显示老师信息
        if(getcustom('yueke_extend')){
            if($product['select_worker'] == 1){
                $workerinfo = '';
            }
        }
        $orderWhere = [];
        //一对一陪教 根据下单时间查询预约人
        if(getcustom('yueke_extend') || ($product['yuyue_model'] && $product['yuyue_model'] == 2)) {
            $dateTime = time() + 86400 * $dateIndex;
            $todayEnd = strtotime(date('Y-m-d 23:59:59',$dateTime))+1;//当天的结束时间
            $todayStart = strtotime(date('Y-m-d 00:00:00',$dateTime));//当天的开始时间
            $orderWhere[] = ['yueke_order.createtime','between',[$todayStart,$todayEnd]];
        }else{
            $orderWhere[] = ['yueke_order.yy_date','=',$date];
        }
        $yyorderlist = Db::name('yueke_order')->alias('yueke_order')->field('member.headimg,member.nickname')->join('member member','member.id=yueke_order.mid')->where('yueke_order.proid',$proid)->where($orderWhere)->where('yueke_order.status','in',[1,2,3])->select()->toArray();
		$product['leftnum'] = $product['yynum'] - count($yyorderlist);

        $rdata = [];
        if(getcustom('yueke_extend')){
            $gglist = Db::name('yueke_guige')->where('proid',$product['id'])->select()->toArray();
            $guigelist = array();
            foreach($gglist as $k=>$v){
                $guigelist[$v['ks']] = $v;
            }
            $guigedata = json_decode($product['guigedata'],true);
            $ggselected = [];
            foreach($guigedata as $v) {
                $ggselected[] = 0;
            }
            $ks = implode(',',$ggselected);
            $rdata['guigelist'] = $guigelist;
            $rdata['guigedata'] = $guigedata;
            $rdata['ggselected'] = $ggselected;
            $rdata['ks'] = $ks;
        }
		$rdata['status'] = 1;
		$rdata['title'] = $product['name'];
		$rdata['isfavorite'] = $isfavorite;
		$rdata['product'] = $product;
		$rdata['business'] = $business;
		$rdata['workerinfo'] = $workerinfo;
		$rdata['yyorderlist'] = $yyorderlist;
		return $this->json($rdata);
	}
	//教练详情
	public function workerinfo(){
		$workerid = input('param.id/d');
		$workerinfo = Db::name('yueke_worker')->where('aid',aid)->where('id',$workerid)->field('id,realname,tel,headimg,dengji,desc,content')->find();
		$rdata = [];
		$rdata['info'] = $workerinfo;
		return $this->json($rdata);
	}
	//商品评价
	public function commentlist(){
		$proid = input('param.proid/d');
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['proid','=',$proid];
		$where[] = ['status','=',1];
		$datalist = Db::name('yueke_comment')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$pl){
			$datalist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
			if($datalist[$k]['content_pic']) $datalist[$k]['content_pic'] = explode(',',$datalist[$k]['content_pic']);
		}
		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}
	//商品海报
	function getposter(){
		$this->checklogin();
		$post = input('post.');
		$platform = platform;
		$page = '/pagesExt/yueke/product';
		$scene = 'id_'.$post['proid'].'-pid_'.$this->member['id'];
		//if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
		//	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
		//}
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','yueke')->where('platform',$platform)->order('id')->find();

		$posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','yueke')->where('posterid',$posterset['id'])->find();
		if(true || !$posterdata){
			$product = Db::name('yueke_product')->where('id',$post['proid'])->find();
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
			$posterdata['type'] = 'yueke';
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
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		$userinfo = [];
		$userinfo['id'] = $this->member['id'];
		$userinfo['realname'] = $this->member['realname'];
		$userinfo['tel'] = $this->member['tel'];
		$userinfo['discount'] = $userlevel['discount'];
		$product = Db::name('yueke_product')->where('aid',aid)->where('ischecked',1)->where('id',$proid)->find();
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
			if(!$product['gettjtip']) $product['gettjtip'] = '没有权限预约该课程';
			return $this->json(['status'=>-4,'msg'=>$product['gettjtip'],'url'=>$product['gettjurl']]);
		}

        if(!getcustom('yueke_extend') || ($product['yuyue_model'] && $product['yuyue_model'] == 1)) {
            if(input('?param.dateIndex')){
                $dateIndex = input('param.dateIndex');
            }else{
                $dateIndex = 0;
            }
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

            $starttime = strtotime($date.' '.$product['starttime']);
            if($starttime < time() + $product['prehour']*3600){
                return $this->json(['status'=>0,'msg'=>'已结束预约']);
            }
        }

		$workerinfo = Db::name('yueke_worker')->where('aid',aid)->where('id',$product['workerid'])->field('id,realname,tel,headimg,dengji,desc')->find();
		$yyorderlist = Db::name('yueke_order')->alias('yueke_order')->field('member.headimg,member.nickname')->join('member member','member.id=yueke_order.mid')->where('yueke_order.proid',$v['id'])->where('yueke_order.yy_date',$date)->where('yueke_order.status','in',[0,1,2,3])->select()->toArray();
		$leftnum = $product['yynum'] - count($yyorderlist);

		if(!getcustom('yueke_extend')){
            if($leftnum <= 0) return $this->json(['status'=>0,'msg'=>'预约人数已满']);
        }

		
		$ykset = db('yueke_set')->where('aid',aid)->find();

		$leveldk_money = 0;
		if($ykset['discount']==1 && $userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			$leveldk_money = $product['sell_price'] * (1 - $userlevel['discount'] * 0.1);
		}
		$userinfo['leveldk_money'] = round($leveldk_money,2);

		$manjian_money = 0;
		$newcouponlist = [];
		$couponList = [];
		if($product['couponids']){
			$couponList = Db::name('coupon_record')
			->where("bid=-1 or bid=".$bid)->where('aid',aid)->where('mid',mid)->where('couponid','in',$product['couponids'])->where('status',0)->where('minprice','<=',$product['sell_price'])->where('starttime','<=',time())->where('endtime','>',time())
			->order('id desc')->select()->toArray();
		}
		if($ykset['iscoupon']==1){
			$couponList2 = Db::name('coupon_record')
			->where("bid=-1 or bid=".$bid)->where('aid',aid)->where('mid',mid)->where('type','in','1,10')->where('status',0)->where('minprice','<=',$product['sell_price'])->where('starttime','<=',time())->where('endtime','>',time())
			->order('id desc')->select()->toArray();
			$couponList = array_merge($couponList,$couponList2);
		}
		if($couponList) $ykset['iscoupon'] = 1;

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
            if($couponinfo['fwscene']!==0){
                continue;
            }
			//0全场通用,4指定服务商品
			if(in_array($couponinfo['fwtype'],[0])){
				if($v['bid'] > 0){
					$binfo = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->find();
					$v['bname'] = $binfo['name'];
				}
				$newcouponlist[] = $v;
			}else{
				continue;
			}
		}
		$couponList = $newcouponlist;

        if(getcustom('yueke_extend') && $product['yuyue_model'] && $product['yuyue_model'] == 2){
            // 预约模式 一对一陪教 多规格
            list($ggid,$num) = explode(',',input('param.prodata'));
            $guige = Db::name('yueke_guige')->where('id',$ggid)->find();
            if(!$guige){
                return $this->json(['status'=>0,'msg'=>'课程该规格不存在或已下架']);
            }

            if($guige['limit_start'] > 0 && $num < $guige['limit_start']){
                return $this->json(['status'=>0,'msg'=>'['.$product['name'].']['.$guige['name'].'] '.$guige['limit_start'].'件起售']);
            }

            $guige['gg_group_title'] = '';
            $guigedata = json_decode($product['guigedata'],true);
            foreach($guigedata as $pk=>$pg){
                $guige['gg_group_title'] .= $pg['title'].',';
            }
            $guige['gg_group_title'] = trim($guige['gg_group_title'],',');

            $product['guige'] = $guige;
            $product['num'] = $num;
            $product['ggid'] = $ggid;
            $product['sell_price'] = $guige['sell_price'];
            $product['total_kecheng_num'] = $guige['kecheng_num'] * $num;
            $product['duration'] = $guige['duration'];
        }


		$rdata = [];
		$rdata['status'] = 1;
		$rdata['address'] = $address??'';
		$rdata['linkman'] = $address ? $address['name'] : strval($userinfo['realname']);
		$rdata['tel'] = $address ? $address['tel'] : strval($userinfo['tel']);
		if(!$rdata['linkman']){
			$lastorder = Db::name('yueke_order')->where('aid',aid)->where('mid',mid)->where('linkman','<>','')->find();
			if($lastorder){
				$rdata['linkman'] = $lastorder['linkman'];
				$rdata['tel'] = $lastorder['tel'];
			}
		}
		$rdata['userinfo'] = $userinfo;
		$rdata['ykset'] = $ykset;
		$rdata['product'] = $product;
		$rdata['workerinfo'] = $workerinfo;
		$rdata['couponList'] = $couponList;
		$rdata['couponCount'] = count($couponList);
		$rdata['formdata'] = json_decode($product['formdata'],true);

		return $this->json($rdata);
	}

	public function createOrder(){
		$this->checklogin();
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		$post = input('post.');
		$address = ['id'=>0,'name'=>$post['linkman'],'tel'=>$post['tel'],'area'=>'','address'=>''];
		
		$proid = input('param.proid/d');

		//会员折扣
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		$userinfo = [];
		$userinfo['id'] = $this->member['id'];
		$userinfo['realname'] = $this->member['realname'];
		$userinfo['tel'] = $this->member['tel'];
		$userinfo['discount'] = $userlevel['discount'];
		$product = Db::name('yueke_product')->where('aid',aid)->where('ischecked',1)->where('id',$proid)->find();
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
			if(!$product['gettjtip']) $product['gettjtip'] = '没有权限预约该课程';
			return $this->json(['status'=>-4,'msg'=>$product['gettjtip'],'url'=>$product['gettjurl']]);
		}
        $num = 1; //默认购买数量
        // 预约模式 一对一陪教 多规格
        if(getcustom('yueke_extend') && $product['yuyue_model'] && $product['yuyue_model'] == 2){
            if(isset($post['prodata'])){
                list($ggid,$num) = explode(',',$post['prodata']);

                $guige = Db::name('yueke_guige')->where('id',$ggid)->find();
                if(!$guige){
                    return $this->json(['status'=>0,'msg'=>'课程该规格不存在或已下架']);
                }

                if($guige['limit_start'] > 0 && $num < $guige['limit_start']){
                    return $this->json(['status'=>0,'msg'=>'['.$product['name'].']['.$guige['name'].'] '.$guige['limit_start'].'件起售']);
                }

                //判断是否是随机服务人员
                if($product['select_worker'] == 1){
                    $product['workerid'] = Db::name('yueke_worker')->where('aid',aid)->where('status',1)->orderRaw('rand()')->value('id');
                }

                $product['sell_price'] = $guige['sell_price'] * $num;
            }
        }else{
            if (input('?param.dateIndex')) {
                $dateIndex = input('param.dateIndex');
            } else {
                $dateIndex = 0;
            }
            $week = date('w', time() + 86400 * $dateIndex);
            $date = date('Y-m-d', time() + 86400 * $dateIndex);
            $timeday = 1 + $dateIndex;
            $product['yy_date'] = date('m月d日', time() + 86400 * $dateIndex);

            if ($product['rqtype'] == 1 && !in_array($week, explode(',', $product['yyzhouqi']))) {
                return $this->json(['status' => 0, 'msg' => '当前日期不可预约']);
            }
            if ($product['rqtype'] == 2 && ($product['yybegintime'] > $date || $product['yyendtime'] < $date)) {
                return $this->json(['status' => 0, 'msg' => '当前日期不可预约']);
            }
            if ($product['rqtype'] == 3 && !in_array($timeday, explode(',', $product['yytimeday']))) {
                return $this->json(['status' => 0, 'msg' => '当前日期不可预约']);
            }

            $starttime = strtotime($date . ' ' . $product['starttime']);
            if ($starttime < time() + $product['prehour'] * 3600) {
                return $this->json(['status' => 0, 'msg' => '已结束预约']);
            }

            $workerinfo = Db::name('yueke_worker')->where('aid', aid)->where('id', $product['workerid'])->field('id,realname,tel,headimg,dengji,desc')->find();
            $yyorderlist = Db::name('yueke_order')->alias('yueke_order')->field('member.headimg,member.nickname')->join('member member', 'member.id=yueke_order.mid')->where('yueke_order.proid', $v['id'])->where('yueke_order.yy_date', $date)->where('yueke_order.status', 'in', [0, 1, 2, 3])->select()->toArray();
            $leftnum = $product['yynum'] - count($yyorderlist);

            if ($leftnum <= 0) return $this->json(['status' => 0, 'msg' => '预约人数已满']);
        }
		$ykset = db('yueke_set')->where('aid',aid)->find();

		$leveldk_money = 0;
		if($ykset['discount']==1 && $userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			$leveldk_money = $product['sell_price'] * (1 - $userlevel['discount'] * 0.1);
		}
		$leveldk_money = round($leveldk_money,2);
		
		$totalprice = $product['sell_price'] - $leveldk_money;

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
            //0全场通用,4指定服务商品
            if(!in_array($couponinfo['fwtype'],[0])){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'超出可用范围']);
            }
            if(!in_array($couponinfo['fwscene'],[0])){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合使用条件']);
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
		if($totalprice < 0) $totalprice = 0;

		$ordernum = date('ymdHis').rand(100000,999999);

		$orderdata = [];
		$orderdata['aid'] = aid;
		$orderdata['mid'] = mid;
		$orderdata['bid'] = $bid;
		$orderdata['ordernum'] = $ordernum;

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
		$orderdata['createtime'] = time();
		$orderdata['platform'] = platform;
		$orderdata['hexiao_code'] = random(16);
		$orderdata['remark'] = $post['remark'];
		$orderdata['proname'] = $product['name'];
		$orderdata['num'] = $num;
		$orderdata['propic'] = $product['pic'];
		$orderdata['proid'] = $product['id'];
		$orderdata['workerid'] = $product['workerid'];
		$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=yueke&co='.$orderdata['hexiao_code']));

        if(getcustom('yueke_extend') && $product['yuyue_model'] && $product['yuyue_model'] == 2){
            if(isset($guige) && isset($ggid)){
                $orderdata['ggid'] = $ggid;
                $orderdata['ggname'] = $guige['name'];
                $orderdata['sell_price'] = $guige['sell_price'];
                $orderdata['total_kecheng_num'] = $guige['kecheng_num'] * $num;

                //分销
                if($product['commissionset'] != -1){

                    //计算一节课的价格
                    $price = $guige['sell_price'] * $num;
                    $kecheng_price = number_format($price / $orderdata['total_kecheng_num'], 2, '.', '');

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
                            if($agleveldata1) $orderdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $kecheng_price * 0.01;
                            if($agleveldata2) $orderdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $kecheng_price * 0.01;
                            if($agleveldata3) $orderdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $kecheng_price * 0.01;
                        }
                        //服务人员佣金
                        $workercommissiondata = json_decode($product['workercommissiondata'],true);
                        if($workercommissiondata){
                            $orderdata['workercommission'] = $workercommissiondata['commission1'] * $kecheng_price * 0.01;
                        }
                    }elseif($product['commissionset']==2){//按固定金额
                        $commissiondata = json_decode($product['commissiondata2'],true);
                        if($commissiondata){
                            if($agleveldata1) $orderdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'];
                            if($agleveldata2) $orderdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'];
                            if($agleveldata3) $orderdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'];
                        }
                        //服务人员佣金
                        $workercommissiondata = json_decode($product['workercommissiondata'],true);
                        if($workercommissiondata){
                            $orderdata['workercommission'] = $workercommissiondata['commission2'];
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
                                $orderdata['parent1commission'] = $agleveldata1['commission1'] * $kecheng_price * 0.01;
                            }
                        }
                        if($agleveldata2){
                            if($agleveldata2['commissiontype']==1){
                                $orderdata['parent2commission'] = $agleveldata2['commission2'];
                            }else{
                                $orderdata['parent2commission'] = $agleveldata2['commission2'] * $kecheng_price * 0.01;
                            }
                        }
                        if($agleveldata3){
                            if($agleveldata3['commissiontype']==1){
                                $orderdata['parent3commission'] = $agleveldata3['commission3'];
                            }else{
                                $orderdata['parent3commission'] = $agleveldata3['commission3'] * $kecheng_price * 0.01;
                            }
                        }
                    }
                }
            }
        }

		$orderid = Db::name('yueke_order')->insertGetId($orderdata);

		$this->saveformdata($orderid,'yueke_order',$post['formdata'],$product['id']);
		if($orderdata['status']==0 && $orderdata['totalprice']>0){
			$payorderid = \app\model\Payorder::createorder(aid,$orderdata['bid'],$orderdata['mid'],'yueke',$orderid,$orderdata['ordernum'],$orderdata['title'],$orderdata['totalprice']);
		}
		if($orderdata['status']==1){
			//短信通知
			if($orderdata['tel']){
				$rs = \app\common\Sms::send(aid,$orderdata['tel'],'tmpl_yysucess',['name'=>$orderdata['title'],'time'=>$orderdata['yy_time']]);
			}
		}
		Db::name('yueke_product')->where('aid',aid)->where('id',$product['id'])->update(['sales'=>Db::raw("sales+1")]);


        return $this->json(['status'=>1,'payorderid'=>$payorderid,'msg'=>'提交成功']);
	}

	//保存自定义表单内容
	function saveformdata($orderid,$type='yueke_order',$formdata,$proid){
		if(!$orderid || !$formdata) return ['status'=>0];
		//根据orderid 取出proid
		$formfield = Db::name('yueke_product')->where('id',$proid)->find();
		$formdataSet = json_decode($formfield['formdata'],true);
		//var_dump($formdataSet);die;
		$data = [];
		foreach($formdataSet as $k=>$v){
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
		$data['type'] = 'yueke_order';
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
		
		$datalist = Db::name('yueke_order')->where($where)->order('id desc')->page($pagenum,10)->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $key=>$v){
			if($v['bid']!=0){
				$datalist[$key]['binfo'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->field('id,name,logo')->find();
			}
			$datalist[$key]['workerinfo'] = Db::name('yueke_worker')->where('aid',aid)->where('id',$v['workerid'])->field('id,realname,tel,headimg,dengji')->find();
			$datalist[$key]['senddate'] = date('Y-m-d H:i:s',$v['send_time']);
            if(getcustom('yueke_extend')){
                $datalist[$key]['kechengnum'] = $v['total_kecheng_num'] - $v['refund_kecheng_num'];
                $surpluskechengnum = $v['total_kecheng_num'] - $v['used_kecheng_num'] - $v['refund_kecheng_num'];
                if($surpluskechengnum < 0) $surpluskechengnum = 0;
                $datalist[$key]['surpluskechengnum'] = $surpluskechengnum;
            }
        }
		$ishowpaidan=false;
		if(getcustom('hmy_yueke')){
			$ishowpaidan=true;
		}
		$rdata = [];
		$rdata['ishowpaidan'] = $ishowpaidan;
		$rdata['st'] = $st;
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}

	public function orderdetail(){
		$detail = Db::name('yueke_order')->where('id',input('param.id/d'))->where('aid',aid)->where('mid',mid)->find();
		if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\model\Freight::getformdata($detail['id'],'yueke_order');
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

		$workerinfo = Db::name('yueke_worker')->where('aid',aid)->where('id',$detail['workerid'])->field('id,realname,tel,headimg,dengji')->find();

		//$prolist = Db::name('yueke_order')->where('id',$detail['id'])->find();
		
		$yuekeset = Db::name('yueke_set')->where('aid',aid)->field('autoclose')->find();
		
		if($detail['status']==0 && $yuekeset['autoclose'] > 0 && $detail['paytypeid'] != 5){
			$lefttime = strtotime($detail['createtime']) + $yuekeset['autoclose']*60 - time();
			if($lefttime < 0) $lefttime = 0;
		}else{
			$lefttime = 0;
		}

        if(getcustom('yueke_extend')){
            $detail['product'] = Db::name('yueke_product')
                ->field('id,name,sell_price,yuyue_model')
                ->where('aid',aid)
                ->where('id',$detail['proid'])
                ->find();

            $detail['guige'] = Db::name('yueke_guige')
                ->field('id,name,sell_price,kecheng_num,duration,limit_start')
                ->where('aid',aid)
                ->where('id',$detail['ggid'])
                ->find();

            $detail['surplus_kecheng_num'] = $detail['total_kecheng_num'] - $detail['used_kecheng_num'] - $detail['refund_kecheng_num'];
            if($detail['surplus_kecheng_num'] < 0){
                $detail['surplus_kecheng_num'] = 0;
            }
        }

		$rdata = [];
		$rdata['detail'] = $detail;
		$rdata['iscommentdp'] = $iscommentdp;
		$rdata['workerinfo'] = $workerinfo;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['lefttime'] = $lefttime;

        //发票
        $rdata['invoice'] = 0;
        if($detail['bid']) {
            $rdata['invoice'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->value('invoice');
        } else {
            $rdata['invoice'] = Db::name('admin_set')->where('aid',aid)->value('invoice');
        }
		return $this->json($rdata);
	}
	function refund(){//申请退款
		$this->checklogin();
		if(request()->isPost()){
			$post = input('post.');
			$orderid = intval($post['orderid']);
			$money = floatval($post['money']);
			$order = Db::name('yueke_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();

            if(!$order || ($order['status']!=1 && $order['status'] != 2) || $order['refund_status'] == 2){
				return $this->json(['status'=>0,'msg'=>'订单状态不符合退款要求']);
			}
			if($money < 0 || $money > $order['totalprice']){
				return $this->json(['status'=>0,'msg'=>'退款金额有误']);
			}
            if(getcustom('hmy_yueke')){
                Db::name('yueke_order')->where('id',$orderid)->where('aid',aid)->where('bid',$order['bid'])->update(['refund_time'=>time(),'status'=>4,'refund_status'=>2,'refund_reason'=>$post['reason'],'refund_money'=>$money]);
                $order = Db::name('yueke_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
                $rs = \app\custom\Yuyue::refund($order);
                return $this->json(['status'=>1,'msg'=>'退款成功']);
            }

            $update = [];
            $update['refund_time'] = time();
            $update['refund_status'] = 1;
            $update['refund_reason'] = $post['reason'];
            $update['refund_money'] = $money;

            if(getcustom('yueke_extend')){
                $update['refund_kecheng_num'] = $post['refundNum'];
            }

            Db::name('yueke_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update($update);
            $tmplcontent = [];
            $tmplcontent['first'] = '有服务订单客户申请退款';
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = $order['ordernum'];
            $tmplcontent['keyword2'] = $money.'元';
            $tmplcontent['keyword3'] = $post['reason'];
            $tmplcontentNew = [];
            $tmplcontentNew['number2'] = $order['ordernum'];//订单号
            $tmplcontentNew['amount4'] = $money;//退款金额
            \app\common\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,m_url('admin/order/yuekeorder'),$order['mdid'],$tmplcontentNew);

            $tmplcontent = [];
            $tmplcontent['thing1'] = $order['title'];
            $tmplcontent['character_string4'] = $order['ordernum'];
            $tmplcontent['amount2'] = $order['totalprice'];
            $tmplcontent['amount9'] = $money.'元';
            $tmplcontent['thing10'] = $post['reason'];
            \app\common\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,'admin/order/yuekeorder',$order['mdid']);

            return $this->json(['status'=>1,'msg'=>'提交成功,请等待商家审核']);
		}
		$rdata = [];
		$rdata['price'] = input('param.price/f');
		$rdata['orderid'] = input('param.orderid/d');
		$order = Db::name('yueke_order')->where('aid',aid)->where('mid',mid)->where('id',$rdata['orderid'])->find();
		$rdata['price'] = $order['totalprice'];
		return $this->json($rdata);
	}

    //申请退款详情
    public function refundinit(){
        $id = input('param.id/d');
        //查询订单信息
        $detail = Db::name('yueke_order')->where('id',$id)->where('aid',aid)->where('mid',mid)->find();
        if(!$detail){
            return $this->json(['status'=>0,'msg'=>'订单不存在']);
        }
        //总课节数 - 已用课节数 - 退款课节数
        $detail['canRefundNum'] = $detail['total_kecheng_num'] - $detail['used_kecheng_num'] - $detail['refund_kecheng_num'];
        $detail['refund_num'] = $detail['refund_kecheng_num'];

        $totalprice = $detail['totalprice'];
        //计算剩余章节价格
        if($detail['used_kecheng_num'] == 0){
            $returnTotalprice = $totalprice;
        }else{
            //(支付价格 / 总课节数) * 可退款课节数
            $returnTotalprice = ($totalprice / $detail['total_kecheng_num']) * $detail['canRefundNum'];
        }

        $returnTotalprice = max($returnTotalprice, 0);
        $detail['returnTotalprice'] = round($returnTotalprice,2);
        $rdata = [];
        $rdata['detail'] = $detail;
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
	function logistics(){
		$get = input('param.');
		$worker_order = Db::name('yueke_worker_order')->where('id',$get['express_no'])->find();
        $worker = Db::name('yueke_worker')->where('id',$worker_order['worker_id'])->find();
        if(getcustom('hmy_yueke')){
			//获取师傅信息
			$rs = \app\custom\Yuyue::getMaster($worker_order['worker_id']);
			$worker = [];
			$worker['realname'] =$rs['data']['name']; 
			$worker['tel'] = $rs['data']['phone']?$rs['data']['phone']:''; 
			//$worker['lon'] =$rs['data']['lon']; 
			//$worker['lat'] =$rs['data']['lat']; 
		}
		$orderinfo = json_decode($worker_order['orderinfo'],true);

		$order = Db::name('yueke_order')->field('mid')->where('id',$orderinfo['id'])->find();
		$orderinfo['mid'] = $order['mid']; 
		$binfo = json_decode($worker_order['binfo'],true);
		$prolist = json_decode($worker_order['prolist'],true);
		
		if($worker_order['juli']> 1000){
			$worker_order['juli'] = round($worker_order['juli']/1000,1);
			$worker_order['juli_unit'] = 'km';
		}else{
			$worker_order['juli_unit'] = 'm';
		}
		//服务人员距用户的距离 骑行距离
        $mapqq = new \app\common\MapQQ();
        $bicycl = $mapqq->getDirectionDistance($worker_order['longitude2'],$worker_order['latitude2'],$worker['longitude'],$worker['latitude'],1);
        if($bicycl && $bicycl['status']==1){
            $juli2 = $bicycl['distance'];
        }else{
            $juli2 = getdistance($worker_order['longitude2'],$worker_order['latitude2'],$worker['longitude'],$worker['latitude'],1);
        }
		$worker_order['juli2'] = $juli2;
		if($juli2> 1000){
			$worker_order['juli2'] = round($juli2/1000,1);
			$worker_order['juli2_unit'] = 'km';
		}else{
			$worker_order['juli2_unit'] = 'm';
		}
		$worker_order['leftminute'] = ceil(($worker_order['yujitime'] - time()) / 60);
		$worker_order['ticheng'] = round($worker_order['ticheng'],2);
		if($worker_order['status']==3){
			$worker_order['useminute'] = ceil(($worker_order['endtime'] - $worker_order['createtime']) / 60);
			$worker_order['useminute2'] = ceil(($worker_order['endtime'] - $worker_order['starttime']) / 60); 
		}
	   $info = Db::name('yueke_set')->where('aid',aid)->find();
		
		$yueke_sign = false;
		if(getcustom('yueke_apply')){
			$yueke_sign=true;
		}

		$rdata = [];
		$rdata['worker_order'] = $worker_order;
		$rdata['mid'] = mid;
		$rdata['binfo'] = $binfo;
		$rdata['worker'] = $worker;
		$rdata['orderinfo'] = $orderinfo;
		$rdata['prolist'] = $prolist;
		$rdata['set'] = $info;
		$rdata['yueke_sign'] =$yueke_sign;
		return $this->json($rdata);
	}
	function closeOrder(){
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('yueke_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
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
		$rs = Db::name('yueke_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>4]);
		return $this->json(['status'=>1,'msg'=>'取消成功']);
	}
	function delOrder(){
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('yueke_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || ($order['status']!=4 && $order['status']!=3)){
			return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
		}
		if($order['status']==3){
			$rs = Db::name('yueke_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['delete'=>1]);
		}else{
			$rs = Db::name('yueke_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->delete();
		}
		return $this->json(['status'=>1,'msg'=>'删除成功']);
	}
	function orderCollect(){ //确认完成
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('yueke_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
		if(!$order || ($order['status']!=1) || $order['paytypeid']==4){
			return $this->json(['status'=>0,'msg'=>'订单状态不符合收货要求']);
		}
		
		$rs = \app\common\Order::collect($order,'yueke');
		if($rs['status'] == 0) return $this->json($rs);

		Db::name('yueke_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);

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
		\app\common\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_ordershouhuo',$tmplcontent,m_url('admin/order/yuekeorder'),$order['mdid'],$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['character_string6'] = $order['ordernum'];
		$tmplcontent['thing3'] = $this->member['nickname'];
		$tmplcontent['date5'] = date('Y-m-d H:i');
		\app\common\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_ordershouhuo',$tmplcontent,'admin/order/yuekeorder',$order['mdid']);

		return $this->json($return);
	}
	//评价
	public function comment(){
		$oid = input('param.oid/d');
		$order = Db::name('yueke_order')->where('id',$oid)->where('mid',mid)->find();
		if(!$order){
			return $this->json(['status'=>0,'msg'=>'未查找到相关记录']);
		}
		$comment = Db::name('yueke_comment')->where('orderid',$oid)->where('aid',aid)->where('mid',mid)->find();
		if(request()->isPost()){
			if($comment){
				return $this->json(['status'=>0,'msg'=>'您已经评价过了']);
			}		
			$content = input('post.content');
			$content_pic = input('post.content_pic');
			$score = input('post.score/d');
			if($score < 1){
				return $this->json(['status'=>0,'msg'=>'请打分']);
			}
            $yuekeset = Db::name('yueke_set')->where('aid',aid)->where('bid',0)->find();
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['bid'] = $order['bid'];
			$data['proid'] =$order['proid'];
			$data['proname'] = $order['proname'];
			$data['propic'] = $order['propic'];
			$data['orderid']= $order['id'];
			$data['ordernum']= $order['ordernum'];
			$data['score'] = $score;
			$data['content'] = $content;
			$data['openid']= $this->member['openid'];
			$data['nickname']= $this->member['nickname'];
			$data['headimg'] = $this->member['headimg'];
			$data['createtime'] = time();
			$data['content_pic'] = $content_pic;
			$data['ggid'] = $order['ggid'];
			$data['ggname'] = $order['ggname'];
			$data['status'] = ($yuekeset['comment_check']==1 ? 0 : 1);
			Db::name('yueke_comment')->insert($data);
			Db::name('yueke_order')->where('aid',aid)->where('mid',mid)->where('id',$oid)->update(['iscomment'=>1]);

			//如果不需要审核 增加产品评论数及评分
			if($yuekeset['comment_check']==0){
				$countnum = Db::name('yueke_comment')->where('proid',$order['proid'])->where('status',1)->count();
				$score = Db::name('yueke_comment')->where('proid',$order['proid'])->where('status',1)->avg('score'); //平均评分
				$haonum = Db::name('yueke_comment')->where('proid',$order['proid'])->where('status',1)->where('score','>',3)->count(); //好评数
				if($countnum > 0){
					$haopercent = $haonum/$countnum*100;
				}else{
					$haopercent = 100;
				}
				Db::name('yueke_product')->where('id',$order['proid'])->update(['comment_num'=>$countnum,'comment_score'=>$score,'comment_haopercent'=>$haopercent]);
			}
			return $this->json(['status'=>1,'msg'=>'评价成功']);
		}
		$rdata = [];
		$rdata['order'] = $order;
		$rdata['comment'] = $comment;
		return $this->json($rdata);
	}
	//评价服务人员
	public function commentps(){
		$id = input('param.id/d');
		$worker_order = Db::name('yueke_worker_order')->where('id',$id)->where('mid',mid)->find();
		if(!$worker_order) return $this->json(['status'=>0,'msg'=>'未找到相关记录']);
		$comment = Db::name('yueke_worker_comment')->where('orderid',$id)->where('aid',aid)->where('mid',mid)->find();
		if(request()->isPost()){
			if($comment){
				return $this->json(['status'=>0,'msg'=>'您已经评价过了']);
			}
			$content = input('post.content');
			$content_pic = input('post.content_pic');
			$score = input('post.score/d');
			if($score < 1){
				return $this->json(['status'=>0,'msg'=>'请打分']);
			}
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['bid'] = $worker_order['bid'];
			$data['worker_id'] = $worker_order['worker_id'];
			$data['orderid']= $worker_order['id'];
			$data['ordernum']= $worker_order['ordernum'];
			$data['score'] = $score;
			$data['content'] = $content;
			$data['content_pic'] = $content_pic;
			$data['nickname']= $this->member['nickname'];
			$data['headimg'] = $this->member['headimg'];
			$data['createtime'] = time();
			$data['status'] = 1;
			Db::name('yueke_worker_comment')->insert($data);
			
			//如果不需要审核 增加配送员评论数及评分
			$countnum = Db::name('yueke_worker_comment')->where('worker_id',$worker_order['worker_id'])->where('status',1)->count();
			$score = Db::name('yueke_worker_comment')->where('worker_id',$worker_order['worker_id'])->where('status',1)->avg('score'); //平均评分
			$haonum = Db::name('yueke_worker_comment')->where('worker_id',$worker_order['worker_id'])->where('status',1)->where('score','>',3)->count(); //好评数
			if($countnum > 0){
				$haopercent = $haonum/$countnum*100;
			}else{
				$haopercent = 100;
			}
			Db::name('yueke_worker_order')->where('id',$worker_order['worker_id'])->update(['comment_num'=>$countnum,'comment_score'=>$score,'comment_haopercent'=>$haopercent]);

			return $this->json(['status'=>1,'msg'=>'评价成功']);
		}
		$rdata = [];
		$rdata['worker_order'] = $worker_order;
		$rdata['comment'] = $comment;
		return $this->json($rdata);
	}

	public function selectpeople(){
		$bid = input('param.bid');
		if(!$bid) $bid = 0;
		$type = input('param.type');
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		if($type=='list'){
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',$bid];
			$where[] = Db::raw("`status`=1");

			$longitude = input('post.longitude');
			$latitude = input('post.latitude');
			if(getcustom('yueke_apply') && $longitude && $latitude){
				$orderBy = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) ");
			}else{
				$orderBy = 'sort desc,id';
			}
			if(input('param.cid')){
						$where[] = ['cid','=',input('param.cid')];	
			}
			if(input('param.keyword')){
				$where[] = ['realname','like','%'.input('param.keyword').'%'];
			}
			$datalist = Db::name('yueke_worker')->where($where)->page($pagenum,$pernum)->order($orderBy)->select()->toArray();
			foreach($datalist as &$d){
				$type =   Db::name('yueke_worker_category')->where(['id'=>$d['cid']])->find();
				$d['typename'] = $type['name'];
			}
		}else{
			$pro =explode(',',input('param.prodata'));
			$yydate = input('param.yydate');
			$product = Db::name('yueke_product')->field('fwpeoid')->where('id',$pro[0])->find();
			$peoarr = explode(',',$product['fwpeoid']);
			$datalist = Db::name('yueke_worker')->where('aid',aid)->where('status',1)->where('id','in',$peoarr)->order('sort desc,id')->select()->toArray();
			//查看该时间是否已经预约出去
			foreach($datalist as &$d){
				$type = Db::name('yueke_worker_category')->where(['id'=>$d['cid']])->find();
				$d['typename'] = $type['name'];
				$order = Db::name('yueke_order')->where('aid',aid)->where('worker_id',$d['id'])->where('status','in','1,2')->where('yy_time',$yydate)->find();
				$d['yystatus']=1;
				if($order){
					$d['yystatus']=-1;
				}
			}
		}
		
		if(!$datalist) $datalist = [];
		return $this->json(['status'=>1,'data'=>$datalist]);
	}

	//人员分类
	public function peocategory(){
		$bid = input('param.bid');
		if(!$bid) $bid = 0;
		$clist = Db::name('yueke_worker_category')->where('aid',aid)->where('bid',$bid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
		return $this->json(['status'=>1,'data'=>$clist]);
	}

	//人员详情
	public function peodetail(){
		$id = input('param.id/d');
		$detail = Db::name('yueke_worker')->where('id',$id)->where('aid',aid)->find();
		$type = Db::name('yueke_worker_category')->where(['id'=>$detail['cid']])->find();
		$detail['typename'] = $type['name'];
		if(getcustom('yueke_apply')){
			if($detail['sex']=='1') $detail['sex']='男';
			if($detail['sex']=='2') $detail['sex']='女';
		}
		//服务商品数量
		$detail['count'] = 0+Db::name('yueke_product')->where('aid',aid)->where("find_in_set({$id},fwpeoid)")->count();
		$detail['showdesc'] = false;
		if(getcustom('yueke_apply')){
			$detail['showdesc'] = true;
		}
        //获取设置
        $set = Db::name('yueke_set')->field('ad_status,ad_pic,ad_link,video_status,video_tag,video_title')->where('aid',aid)->where('bid',0)->find();
        if($set['video_tag']) $set['video_tag'] = explode(',',$set['video_tag']);
        else $set['video_tag'] = [];
		if(!$detail) return $this->json(['status'=>0,'msg'=>'不存在']);
		return $this->json(['status'=>1,'data'=>$detail,'set'=>$set]);
	}
	public function getdlist(){
		$id = input('param.id/d');
		$type =	input('param.curTopIndex/d');
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
        $order = 'id desc';
		if($type==0){
			//服务商品
			$datalist = Db::name('yueke_product')->where('aid',aid)->where("find_in_set({$id},fwpeoid)")->page($pagenum,$pernum)->order($order)->select()->toArray();
		}
		if($type==1){
			//	//评价列表
			$datalist = Db::name('yueke_worker_comment')->where(['aid'=>aid,'worker_id'=>$id])->page($pagenum,$pernum)->order($order)->select()->toArray();
			foreach($datalist as $k=>$pl){
				$datalist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
				if($datalist[$k]['content_pic']) $datalist[$k]['content_pic'] = explode(',',$datalist[$k]['content_pic']);
			}
		}
		if(!$datalist) $datalist = [];
		$datalist = $this->formatprolist($datalist);
		return $this->json(['status'=>1,'data'=>$datalist]);
	}

	public function isgettime(){
		$date = input('param.date/t');
		if(strpos($date,'年') === false){
			$date = date('Y').'年'.$date;
		}
		$proid = input('param.proid/d');
		//获取设置
	    $product = Db::name('yueke_product')->where('aid',aid)->where('id',$proid)->find();
		$sets = Db::name('yueke_set')->where('aid',aid)->find();
		if($product['datetype']==1){
			$timearr = [];
			$j=0;
			$nowdate =strtotime(date('H:i',time()))+$product['pdprehour']*60*60;
			for($i=strtotime($product['zaohour'].':00') ;$i<=strtotime($product['wanhour'].':00') ; $i=$i+60*$product['timejg']){
				$j++;
				$time =strtotime(preg_replace(['/年|月/','/日/'],['-',''],$date.' '.date("H:i",$i)));
				$count = Db::name('yueke_order')->where('aid',aid)->where('proid',$proid)->where('begintime','=',$time)->where('status','in','1,2')->count();
				$order=Db::name('yueke_order')->where('aid',aid)->where('proid',$proid)->where('begintime','=',$time)->where('status','in','1,2')->find();
			
					if($count>=$product['yynum'] || $time<$nowdate){
						$timearr[$j]['status'] = 0;
					}else{
						$timearr[$j]['status'] = 1;
					}
					$timearr[$j]['time'] = date("H:i",$i);
					$timearr[$j]['timeint'] = str_replace(':','',date("H:i",$i));
			 }
		}
		if($product['datetype']==2){
			$timearr = [];
			$timearrs = explode(',',$product['timepoint']); 
			$nowdate =strtotime(date('H:i',time()))+$product['pdprehour']*60*60;	
			foreach($timearrs as $k=>$t){
				$time =strtotime(preg_replace(['/年|月/','/日/'],['-',''],$date.' '.$t));
				$count = Db::name('yueke_order')->where('aid',aid)->where('proid',$proid)->where('begintime','=',$time)->where('status','in','1,2')->count();
				if($count>=$product['yynum'] || $time<$nowdate){
						$timearr[$k]['status'] = 0;
				}else{
						$timearr[$k]['status'] = 1;
				}
				$timearr[$k]['time'] = $t;
				$timearr[$k]['timeint'] = str_replace(':','',$t);
			}
		}
		return $this->json(['status'=>1,'data'=>$timearr]);
	}
	public function getyytime($yydate,$proid){
		$yydate = explode('-',$yydate);
		//开始时间
		$begindate = date('Y年').$yydate[0];
		$begindate = preg_replace(['/年|月/','/日/'],['-',''],$begindate);
		$begintime = strtotime($begindate);
		$ends = explode(' ',$yydate[0]);

		$where[] = ['begintime','=',$begintime];
		$count = 0 + Db::name('yueke_order')->where($where)->where('aid',aid)->where('status','in','1,2')->where('proid',$proid)->count();
		return $count;
	}

	public function apply(){
        $this->checklogin();
		if(getcustom('yueke_extend')){
            //查询他是否申请过
            $worker = Db::name('yueke_worker')->where('aid',aid)->where('mid',mid)->find();
            if($worker && $worker['shstatus'] == 1){
                return $this->json(['status'=>2,'msg'=>'您已成功入驻']);
            }

            if($worker){
                //头像处理
                $worker['pic'] = $worker['headimg'] ? [$worker['headimg']] : [];
            }

            if(request()->isPost()){
                $data = input('post.info/a');
                if(empty($data['realname'])){
                    return $this->json(['status'=>0,'msg'=>'姓名不能为空']);
                }

                if(empty($data['tel'])){
                    return $this->json(['status'=>0,'msg'=>'手机号不能为空']);
                }

                if(!preg_match('/^1[3-9]\d{9}$/',$data['tel'])){
                    return $this->json(['status'=>0,'msg'=>'手机号格式不正确']);
                }

                //修改时不验证账号
                if(!isset($data['id'])){
                    if(empty($data['un'])){
                        return $this->json(['status'=>0,'msg'=>'登录账号不能为空']);
                    }

                    if(empty($data['pwd'])){
                        return $this->json(['status'=>0,'msg'=>'登录密码不能为空']);
                    }

                    if($data['pwd'] != $data['repwd']){
                        return $this->json(['status'=>0,'msg'=>'两次密码输入不一致']);
                    }

                    $hasun = Db::name('admin_user')->where('un',$data['un'])->find();
                    if($hasun){
                        return $this->json(['status'=>0,'msg'=>'该账号已存在']);
                    }
                    $res = Db::name('yueke_worker')->insert([
                        'aid' => aid,
                        'mid' => mid,
                        'un'  => $data['un'],
                        'pwd' => md5($data['pwd']),
                        'realname'=> $data['realname'],
                        'tel' => $data['tel'],
                        'headimg' => $data['pic'],
                        'status' => 0,
                        'shstatus' => 0,
                        'desc' =>$data['desc'],
                        'createtime' => time(),
                    ]);
                }else{
                    $res = Db::name('yueke_worker')->where('id',$data['id'])->update([
                        'realname'=> $data['realname'],
                        'tel' => $data['tel'],
                        'headimg' => $data['pic'],
                        'status' => 0,
                        'shstatus' => 0,
                        'desc' => $data['desc']
                    ]);
                }

                if($res){
                    return $this->json(['status'=>1,'msg'=>'提交成功，请等待审核']);
                }
                return $this->json(['status'=>0,'msg'=>'提交失败']);
            }

            return $this->json(['status'=>1,'data'=>$worker]);
        }
	}

	//教练员登录
	public function workerlogin(){
		if(request()->isPost()){
			$username = trim(input('post.username'));
			$password = trim(input('post.password'));
			$captcha = trim(input('post.captcha'));
			if($username=='' || $password==''){
				return $this->json(['status'=>0,'msg'=>'用户名和密码不能为空']);
			}elseif($captcha == ''){
				return $this->json(['status'=>0,'msg'=>'验证码不能为空']);
			}elseif(strtolower($captcha) != strtolower(cache($this->sessionid.'_captcha'))){
				 return $this->json(['status'=>0,'msg'=>'验证码错误']);
			}
			$rs = Db::name('yueke_worker')->where('aid',aid)->where(['un'=>$username,'pwd'=>md5($password)])->find();
			if($rs){
				$aid = $rs['aid'];
				if($rs['status']!=1) return ['status'=>0,'msg'=>'账号未启用'];
				Db::name('yueke_worker')->where('aid',aid)->where('mid',mid)->update(['mid'=>'']);
				Db::name('yueke_worker')->where('id',$rs['id'])->update(['mid'=>mid]);
				if(!$rs['headimg']){
					Db::name('yueke_worker')->where('id',$rs['id'])->update(['headimg'=>$rs['headimg']]);
				}
				return $this->json(['status'=>1,'msg'=>'登录成功']);
			}else{
				return $this->json(['status'=>2,'msg'=>'账号或密码错误']);
			}
		}else{
			return $this->json(['status'=>1]);
		}
	}
	//修改密码
	public function workersetpwd(){
        $this->checklogin();
		if(request()->isPost()){
			$user = Db::name('yueke_worker')->where('aid',aid)->where('mid',mid)->find();
			$oldpwd = input('post.oldpwd');
			$pwd = input('post.pwd');
			if(md5($oldpwd)!=$user['pwd']){
				return $this->json(['status'=>0,'msg'=>'原密码输入错误']);
			}
			Db::name('yueke_worker')->where('aid',aid)->where('id',$user['id'])->update(['pwd'=>md5($pwd)]);
			return $this->json(['status'=>1,'msg'=>'修改成功']);
		}
		$user = Db::name('yueke_worker')->field('id,un')->where('mid',mid)->find();
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['user'] = $user;
		return $this->json($rdata);
	}
	
	//订单列表
	function workerorderlist(){
		$this->checklogin();

		$worker = Db::name('yueke_worker')->where('aid',aid)->where('mid',mid)->find();
		if(!$worker || $worker['status'] != 1) return $this->json(['status'=>-4,'msg'=>'请先登录教练账号','url'=>'workerlogin']);

		$st = input('param.st');
		if(!$st && $st!=='0') $st = 'all';
		$pagenum = input('param.pagenum') ? input('param.pagenum') : 1;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['workerid','=',$worker['id']];
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

        //查询包含该教练的学习记录订单
        $record_orderids = Db::name('yueke_study_record')->where('aid',aid)->where('workerid',$worker['id'])->column('orderid');
        $whereor = [];
        if($record_orderids){
            $whereor[] = ['id','in',$record_orderids];
        }
		$datalist = Db::name('yueke_order')->where($where)->whereOr($whereor)->order('id desc')->page($pagenum,10)->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $key=>$v){
			if($v['bid']!=0){
				$datalist[$key]['binfo'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->field('id,name,logo')->find();
			}
			$datalist[$key]['workerinfo'] = Db::name('yueke_worker')->where('aid',aid)->where('id',$v['workerid'])->field('id,realname,tel,headimg,dengji')->find();
			$datalist[$key]['senddate'] = date('Y-m-d H:i:s',$v['send_time']);
			$datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
			if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
            $datalist[$key]['yuyue_model'] = 1; //预约方式 一对多模式
            if(getcustom('yueke_extend')){
                $datalist[$key]['kechengnum'] = $v['total_kecheng_num'] - $v['refund_kecheng_num'];
                $surpluskechengnum = $v['total_kecheng_num'] - $v['used_kecheng_num'] - $v['refund_kecheng_num'];
                if($surpluskechengnum < 0) $surpluskechengnum = 0;
                $datalist[$key]['surpluskechengnum'] = $surpluskechengnum;
                $datalist[$key]['yuyue_model'] = Db::name('yueke_product')->where('aid',aid)->where('id',$v['proid'])->value('yuyue_model');
            }
		}
		$rdata = [];
		$rdata['st'] = $st;
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}

	public function workerorderdetail(){
		$worker = Db::name('yueke_worker')->where('aid',aid)->where('mid',mid)->find();
		if(!$worker || $worker['status'] != 1) return $this->json(['status'=>-4,'msg'=>'请先登录教练账号','url'=>'workerlogin']);


		$detail = Db::name('yueke_order')->where('id',input('param.id/d'))->where('aid',aid)->where('workerid',$worker['id'])->find();
		if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\model\Freight::getformdata($detail['id'],'yueke_order');
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

		
		$member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
		$detail['nickname'] = $member['nickname'];
		$detail['headimg'] = $member['headimg'];

		$workerinfo = Db::name('yueke_worker')->where('aid',aid)->where('id',$detail['workerid'])->field('id,realname,tel,headimg,dengji')->find();

		$prolist = Db::name('yueke_order')->where('id',$detail['id'])->find();
		$yuekeset = Db::name('yueke_set')->where('aid',aid)->field('autoclose')->find();
		if($detail['status']==0 && $yuekeset['autoclose'] > 0 && $detail['paytypeid'] != 5){
			$lefttime = strtotime($detail['createtime']) + $yuekeset['autoclose']*60 - time();
			if($lefttime < 0) $lefttime = 0;
		}else{
			$lefttime = 0;
		}

        if(getcustom('yueke_extend')){
            $detail['product'] = Db::name('yueke_product')
                ->field('id,name,sell_price,yuyue_model')
                ->where('aid',aid)
                ->where('id',$detail['proid'])
                ->find();

            $detail['guige'] = Db::name('yueke_guige')
                ->field('id,name,sell_price,kecheng_num,duration,limit_start')
                ->where('aid',aid)
                ->where('id',$detail['ggid'])
                ->find();

            $detail['surplus_kecheng_num'] = $detail['total_kecheng_num'] - $detail['used_kecheng_num'] - $detail['refund_kecheng_num'];
            if($detail['surplus_kecheng_num'] < 0){
                $detail['surplus_kecheng_num'] = 0;
            }
        }

		$rdata = [];
		$rdata['detail'] = $detail;
		$rdata['iscommentdp'] = $iscommentdp;
		$rdata['workerinfo'] = $workerinfo;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['lefttime'] = $lefttime;

        //发票
        $rdata['invoice'] = 0;
        if($detail['bid']) {
            $rdata['invoice'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->value('invoice');
        } else {
            $rdata['invoice'] = Db::name('admin_set')->where('aid',aid)->value('invoice');
        }
		return $this->json($rdata);
	}

    //约课
    public function yueke(){
        if(getcustom('yueke_extend')){
            if(request()->isPost()){
                $id = input('post.id/d');
                $orderid = input('post.orderid/d');
                $date = input('post.date');
                $time = input('post.time');

                if(empty($id) || empty($orderid)){
                    return $this->json(['status'=>0,'msg'=>'ID或订单ID不能为空']);
                }

                if(empty($date) || empty($time)){
                    return $this->json(['status'=>0,'msg'=>'日期或时间不能为空']);
                }

                //日期时间组合
                $date = strtotime($date.' '.$time);
                if($date < time()){
                    return $this->json(['status'=>0,'msg'=>'日期时间不能小于当前时间']);
                }

                $order = Db::name('yueke_order')->where('aid',aid)->where('id',$orderid)->where('status',1)->find();
                if(empty($order)){
                    return $this->json(['status'=>0,'msg'=>'订单不存在或已取消']);
                }

                $kechengnum = $order['total_kecheng_num'] - $order['refund_kecheng_num'];

                $res = Db::name('yueke_study_record')->where('aid',aid)->where('id',$id)->update([
                    'start_study_time' => $date,
                    'status' => 0
                ]);
                if($res){
                    return $this->json(['status'=>1,'msg'=>'约课成功']);
                }
                return $this->json(['status'=>0,'msg'=>'约课失败']);
            }
            $id = input('get.id/d');
            if(empty($id)){
                return $this->json(['status'=>0,'msg'=>'参数异常']);
            }
            $record = Db::name('yueke_study_record')->where('aid',aid)->where('id',$id)->find();
            $data = [];
            if($record['start_study_time']){
                $data['date'] = date('Y-m-d',$record['start_study_time']);
                $data['time'] = date('H:i',$record['start_study_time']);
            }
            return $this->json(['status'=>1,'data' => $data]);
        }
    }

    //学习记录
    public function studyrecord(){
        $this->checklogin();
        $orderid = input('param.orderid/d');
        if(empty($orderid)){
            return $this->json(['status'=>0,'msg'=>'订单ID不能为空']);
        }

        $order = Db::name('yueke_order')->where('aid',aid)->where('id',$orderid)->find();
        if(empty($order)){
            return $this->json(['status'=>0,'msg'=>'订单不存在']);
        }

        $pagenum = input('param.pagenum') ? input('param.pagenum') : 1;
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['orderid','=',$orderid];
        $datalist = Db::name('yueke_study_record')
            ->where($where)
            ->order('id desc')
            ->page($pagenum,10)
            ->select()
            ->toArray();
        if(!$datalist) $datalist = [];
        foreach($datalist as $key=>$v){
            $datalist[$key]['workerinfo'] = Db::name('yueke_worker')->where('aid',aid)->where('id',$v['workerid'])->field('id,realname,tel,headimg,dengji')->find();
            $datalist[$key]['member'] = Db::name('member')->where('aid',aid)->where('id',$v['mid'])->field('id,realname,nickname')->find();
            $datalist[$key]['start_study_time'] = $v['start_study_time'] ? date('Y-m-d H:i:s',$v['start_study_time']) : '';
            $datalist[$key]['hx_time'] = $v['hx_time'] ? date('Y-m-d H:i:s',$v['hx_time']) : '';
        }
        $rdata = [];
        $rdata['datalist'] = $datalist;
        return $this->json($rdata);
    }

    //老师端学习记录
    public function workerstudyrecord(){
        $this->checklogin();
        $worker = Db::name('yueke_worker')->where('aid',aid)->where('mid',mid)->find();
        if(!$worker || $worker['status'] != 1) return $this->json(['status'=>-4,'msg'=>'请先登录教练账号','url'=>'workerlogin']);

        $orderid = input('param.orderid/d');
        if(empty($orderid)){
            return $this->json(['status'=>0,'msg'=>'订单ID不能为空']);
        }

        $order = Db::name('yueke_order')->where('aid',aid)->where('id',$orderid)->find();
        if(empty($order)){
            return $this->json(['status'=>0,'msg'=>'订单不存在']);
        }

        $pagenum = input('param.pagenum') ? input('param.pagenum') : 1;
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['orderid','=',$orderid];
        $where[] = ['workerid','=',$worker['id']];
        $datalist = Db::name('yueke_study_record')
            ->where($where)
            ->order('id desc')
            ->page($pagenum,10)
            ->select()
            ->toArray();
        if(!$datalist) $datalist = [];
        foreach($datalist as $key=>$v){
            $datalist[$key]['workerinfo'] = Db::name('yueke_worker')->where('aid',aid)->where('id',$v['workerid'])->field('id,realname,tel,headimg,dengji')->find();
            $datalist[$key]['member'] = Db::name('member')->where('aid',aid)->where('id',$v['mid'])->field('id,realname,nickname')->find();
            $datalist[$key]['start_study_time'] = $v['start_study_time'] ? date('Y-m-d H:i:s',$v['start_study_time']) : '';
            $datalist[$key]['hx_time'] = $v['hx_time'] ? date('Y-m-d H:i:s',$v['hx_time']) : '';
        }
        $rdata = [];
        $rdata['datalist'] = $datalist;
        return $this->json($rdata);
    }


    //取消约课
    public function cancelyueke(){
        if(getcustom('yueke_extend')){
            if(request()->isPost()){
                $id = input('post.id/d');
                if(empty($id)){
                    return $this->json(['status'=>0,'msg'=>'ID不能为空']);
                }

                $record = Db::name('yueke_study_record')->where('aid',aid)->where('id',$id)->find();

                if(empty($record)){
                    return $this->json(['status'=>0,'msg'=>'记录不存在']);
                }

                $res = Db::name('yueke_study_record')->where('id',$id)->update(['status' => 4]);
                if($res){
                    return $this->json(['status'=>1,'msg'=>'取消成功']);
                }
                return $this->json(['status'=>0,'msg'=>'取消失败']);
            }
            return $this->json(['status'=>0,'msg'=>'error']);
        }
    }

    //开始上课
    public function gotoclass(){
        if(getcustom('yueke_extend')){
            if(request()->isPost()){
                $id = input('post.id/d');
                if(empty($id)){
                    return $this->json(['status'=>0,'msg'=>'ID不能为空']);
                }

                $record = Db::name('yueke_study_record')->where('aid',aid)->where('id',$id)->find();

                if(empty($record)){
                    return $this->json(['status'=>0,'msg'=>'记录不存在']);
                }

                $res = Db::name('yueke_study_record')->where('id',$id)->update(['status' => 1]);
                if($res){
                    return $this->json(['status'=>1,'msg'=>'上课成功']);
                }
                return $this->json(['status'=>0,'msg'=>'上课失败']);
            }
            return $this->json(['status'=>0,'msg'=>'error']);
        }
    }

    //核销
    public function hexiao(){
        if(getcustom('yueke_extend')){
            if(request()->isPost()){
                $id = input('post.id/d');
                if(empty($id)){
                    return $this->json(['status'=>0,'msg'=>'ID不能为空']);
                }

                $record = Db::name('yueke_study_record')->where('aid',aid)->where('id',$id)->find();

                if(empty($record)){
                    return $this->json(['status'=>0,'msg'=>'记录不存在']);
                }
                //验证时间
                if(time() < $record['start_study_time']){
                    return $this->json(['status'=>0,'msg'=>'未到课程开始时间无法核销']);
                }

                $order = Db::name('yueke_order')->where('aid',aid)->where('id',$record['orderid'])->find();
                if($order['status'] == 3){
                    return $this->json(['status'=>0,'msg'=>'订单已完成，无法核销']);
                }

                Db::startTrans();
                try{
                    //ogid: 学习记录id
                    $order['ogid'] = $id;
                    //发放佣金
                    \app\common\Order::giveCommission($order,'yueke');

                    //增加老师余额
                    $worker = Db::name('yueke_worker')->where('aid',aid)->where('id',$record['workerid'])->find();
                    $after = $worker['money'] + $order['workercommission'];
                    Db::name('yueke_worker')->where('aid',aid)->where('id',$record['workerid'])->update([
                        'money' => Db::raw('money+'.$order['workercommission']),
                        'totalmoney' => Db::raw('totalmoney+'.$order['workercommission'])
                    ]);

                    //增加记录
                    Db::name('yueke_worker_moneylog')->insert([
                        'aid' => $record['aid'],
                        'uid' => $worker['id'],
                        'money' => $order['workercommission'],
                        'after' => $after,
                        'createtime' => time(),
                        'remark' => '老师上课佣金'
                    ]);

                    //修改学习记录状态
                    Db::name('yueke_study_record')->where('aid',aid)->where('id',$id)->update(['status' => 2,'iscommission' => 1,'hx_time' =>time()]);

                    //修改订单状态
                    $update = [];

                    //判断本次是否是订单中最后一节课程
                    $usedKechengNum = $order['used_kecheng_num'] + 1;
                    $kechengNum = $order['total_kecheng_num'] - $order['refund_kecheng_num'] - $usedKechengNum;

                    if($kechengNum == 0){
                        $update['status'] = 3;
                    }
                    $update['used_kecheng_num'] = $usedKechengNum;
                    Db::name('yueke_order')->where('aid',aid)->where('id',$record['orderid'])->update($update);
                    Db::commit();

                    return $this->json(['status'=>1,'msg'=>'核销成功']);
                }catch(\Exception $e){
                    Db::rollback();
                    return $this->json(['status'=>0,'msg'=>$e->getMessage()]);
                }
            }
        }
    }

    //评价
    public function setcomment(){
        if(getcustom('yueke_extend')) {
            if (request()->isPost()) {
                $id = input('post.id/d');
                $comment = input('post.comment');

                if(empty($comment)){
                    return $this->json(['status'=>0,'msg'=>'评价内容不能为空']);
                }

                $record = Db::name('yueke_study_record')->where('aid',aid)->where('id',$id)->find();
                if(empty($record)){
                    return $this->json(['status'=>0,'msg'=>'记录不存在']);
                }

                $res = Db::name("yueke_study_record")->where('aid',aid)->where('id',$id)->update(['comment' => $comment]);
                if($res){
                    return $this->json(['status'=>1,'msg'=>'评价成功']);
                }
                return $this->json(['status'=>0,'msg'=>'评价失败']);
            }
        }
    }

    //我的
    public function my(){
        $this->checklogin();
        $worker = Db::name('yueke_worker')->where('aid',aid)->where('mid',mid)->find();
        if(!$worker || $worker['status'] != 1) return $this->json(['status'=>-4,'msg'=>'请先登录'.t('教练').'账号','url'=>'/pagesExt/yueke/workerlogin']);

        //查看状态
        if(getcustom('yueke_extend')){
            if($worker['shstatus']!=1){
                echojson(['status'=>-4,'msg'=>'账号审核未通过','url'=>'/pagesB/yueke/apply']);
            }
        }
        if(!$worker['totalmoney']) $worker['totalmoney'] = 0;

        return $this->json(['status'=>1,'worker'=>$worker]);
    }

    //余额明细
    public function moneylog(){
        $this->checklogin();
        $worker = Db::name('yueke_worker')->where('aid',aid)->where('mid',mid)->find();
        if(!$worker || $worker['status'] != 1) return $this->json(['status'=>-4,'msg'=>'请先登录'.t('教练').'账号','url'=>'workerlogin']);

        $st = input('param.st');
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $pernum = 20;
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['uid','=',$worker['id']];
        if($st ==2){//提现记录
            $datalist = Db::name('yueke_worker_withdrawlog')->field("id,money,txmoney,`status`,createtime")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
        }else{ //余额明细
            $datalist = Db::name('yueke_worker_moneylog')->field("id,money,`after`,createtime,remark")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
        }
        return $this->json(['status'=>1,'data'=>$datalist]);
    }

    //提现设置
    public function set(){
        $this->checklogin();
        $smsset = Db::name('admin_set_sms')->where('aid',aid)->find();
        $needsms = 0;
        if($smsset && $smsset['status'] == 1 && $smsset['tmpl_smscode'] && $smsset['tmpl_smscode_st']==1){
            $needsms = 1;
        }
        if(request()->isPost()){
            $formdata = input('post.');
            $worker = Db::name('yueke_worker')
                ->where('aid',aid)
                ->where('mid',mid)
                ->field('id,realname,tel,weixin,aliaccount,bankname,bankcarduser,bankcardnum')
                ->find();

            if($needsms==1){
                if(md5($worker['tel'].'-'.$formdata['code']) != cache(input('param.session_id').'_smscode') || cache(input('param.session_id').'_smscodetimes') > 5){
                    return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
                }
            }
            cache(input('param.session_id').'_smscode',null);
            cache(input('param.session_id').'_smscodetimes',null);
            $info = [];
            $info['weixin'] = $formdata['weixin'];
            $info['aliaccount'] = $formdata['aliaccount'];
            $info['bankname'] = $formdata['bankname'];
            $info['bankcarduser'] = $formdata['bankcarduser'];
            $info['bankcardnum'] = $formdata['bankcardnum'];
            Db::name('yueke_worker')->where('aid',aid)->where('id',$worker['id'])->update($info);
            return $this->json(['status'=>1,'msg'=>'修改成功']);
        }
        $userinfo = Db::name('yueke_worker')
            ->where('aid',aid)
            ->where('mid',mid)
            ->field('id,realname,tel,weixin,aliaccount,bankname,bankcarduser,bankcardnum')
            ->find();
        $rdata = [];
        $rdata['needsms'] = $needsms;
        $rdata['userinfo'] = $userinfo;
        return $this->json($rdata);
    }

    //余额提现
    public function withdraw(){
        $this->checklogin();
        $set = Db::name('yueke_set')->where('aid',aid)->field('withdrawmin,withdrawfee,withdraw_weixin,withdraw_aliaccount,withdraw_bankcard')->find();
        if(request()->isPost()){
            $post = input('post.');
            $binfo = Db::name('yueke_worker')->where('aid',aid)->where('mid',mid)->find();
            if($post['paytype']=='支付宝' && $binfo['aliaccount']==''){
                return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
            }
            if($post['paytype']=='银行卡' && ($binfo['bankname']==''||$binfo['bankcarduser']==''||$binfo['bankcardnum']=='')){
                return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
            }

            $money = $post['money'];
            if($money<=0 || $money < $set['withdrawmin']){
                return $this->json(['status'=>0,'msg'=>'提现金额必须大于'.($set['withdrawmin']?$set['withdrawmin']:0)]);
            }
            if($money > $binfo['money']){
                return $this->json(['status'=>0,'msg'=>'可提现余额不足']);
            }

            $ordernum = date('ymdHis').aid.rand(1000,9999);
            $record = [];
            $record['aid'] = aid;
            $record['bid'] = $binfo['bid'];
            $record['uid'] = $binfo['id'];
            $record['createtime']= time();
            $record['money'] = $money*(1-$set['withdrawfee']*0.01);
            $record['txmoney'] = $money;
            if($post['paytype']=='微信钱包'){
                $record['weixin'] = $binfo['weixin'];
            }
            if($post['paytype']=='支付宝'){
                $record['aliaccount'] = $binfo['aliaccount'];
            }
            if($post['paytype']=='银行卡'){
                $record['bankname'] = $binfo['bankname'];
                $record['bankcarduser'] = $binfo['bankcarduser'];
                $record['bankcardnum'] = $binfo['bankcardnum'];
            }
            $record['ordernum'] = $ordernum;
            $record['paytype'] = $post['paytype'];
            $recordid = db('yueke_worker_withdrawlog')->insertGetId($record);

            \app\common\YuekeWorker::addmoney(aid,$binfo['bid'],$binfo['id'],-$money,'余额提现');
            return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款']);
        }
        $userinfo = Db::name('yueke_worker')->where('aid',aid)->where('mid',mid)->field('id,money,weixin,aliaccount,bankname,bankcarduser,bankcardnum')->find();
        $rdata = [];
        $rdata['userinfo'] = $userinfo;
        $rdata['sysset'] = $set;
        return $this->json($rdata);
    }
}