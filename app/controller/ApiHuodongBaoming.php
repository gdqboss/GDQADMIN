<?php
// JK客户定制

//custom_file(huodong_baoming)
namespace app\controller;
use think\facade\Db;
class ApiHuodongBaoming extends ApiCommon{
	public function getprolist(){
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['status','=',1];
		$nowtime = time();
		$where[] = Db::raw("`status`=1");

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
			$clist = Db::name('huodong_baoming_category')->where('aid',aid)->where('pid',$cid)->column('id');
			if($clist){
				$clist2 = Db::name('huodong_baoming_category')->where('aid',aid)->where('pid','in',$clist)->column('id');
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
        //优惠券可用活动列表
        $cpid = input('param.cpid/d');
        if($cpid > 0){
            $coupon = Db::name('coupon')->where('id',$cpid)->find();
            $where[] = ['bid','=',$coupon['bid']];
            if($coupon['fwtype']==4){ //指定活动
                $where[] = ['id','in',$coupon['huodong_baoming_productids']];
            }
        }

		if(input('param.keyword')){
			$where[] = ['name','like','%'.input('param.keyword').'%'];
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('huodong_baoming_product')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
		$score_weishu = 0;
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			$datalist[$k]['sales'] = $v['sales'] + $v['start_sales'];
			$end_time = strtotime($v['end_time']);
			if($end_time < time()){
				$datalist[$k]['isend'] = true;
			}else{
				$datalist[$k]['isend'] = false;
			}
			// 判断有没有开始
			$start_time = strtotime($v['start_time']);
			if($start_time > time()){
				$datalist[$k]['isstart'] = false;
			}else{
				$datalist[$k]['isstart'] = true;
			}
			//判断报名结束时间
			 
			//
			$datalist[$k]['score_price'] = dd_money_format($v['score_price'],$score_weishu);
			//活动结束倒计时

			if($v['mianfei_memberlevel_open'] == 1){
				$is_price = 1;
				$gettj = explode(',',$v['mianfei_gettj']);
				if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
					if(in_array('0',$gettj)){ //关注用户才能领
						if($this->member['subscribe']!=1){
							$is_price = 0;
						}
					}else{
						$is_price = 0;
					}
				}
				if($is_price ==1){
					$datalist[$k]['sell_price']  = 0;	
					$datalist[$k]['score_price']  = 0;				
				}
			}
			 
			$datalist[$k]['countdown'] = $end_time - time()>0? $this->timeToString($end_time - time()):'';
			//已报名状态
			$datalist[$k]['apply_num'] = $v['start_sales']+$v['sales'];
			$datalist[$k]['isapply'] = Db::name('huodong_baoming_order')->where('aid',aid)->where('status','>',0)->where('proid',$v['id'])->where('mid',mid)->count();
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
		if(input('param.cid')){
			$clist = Db::name('huodong_baoming_category')->where('aid',aid)->where('pid',input('param.cid/d'))->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$clist) $clist = [];
		}else{
			$clist = Db::name('huodong_baoming_category')->where('aid',aid)->where('bid',$bid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$clist) $clist = [];
		}
		return $this->json(['clist'=>$clist]);
	}
	
	//分类活动
	public function classify(){
		if(input('param.bid')){
			$bid = input('param.bid/d');
		}else{
			$bid = 0;
		}
		$clist = Db::name('huodong_baoming_category')->where('aid',aid)->where('pid',0)->where('bid',$bid)->where('status',1)->order('sort desc,id')->select()->toArray();
		foreach($clist as $k=>$v){
			$rs = Db::name('huodong_baoming_category')->where('aid',aid)->where('pid',$v['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$rs) $rs = [];
			$clist[$k]['child'] = $rs;
		}
		return $this->json(['status'=>1,'data'=>$clist]);
	}
	//活动
	public function product(){
		$proid = input('param.id/d');
		Db::name('huodong_baoming_product')->where('aid',aid)->where('id',$proid)->update(['viewnum'=>Db::raw("viewnum+1")]);
		$product = Db::name('huodong_baoming_product')->where('id',$proid)->where('aid',aid)->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'活动不存在']);
		if($product['status']==0) return $this->json(['status'=>0,'msg'=>'活动未上架']);
		
		if($product['status']==2 && (strtotime($product['start_time']) > time() || strtotime($product['end_time']) < time())){
			return $this->json(['status'=>0,'msg'=>'活动未上架']);
		}
		if($product['status']==2) $product['status']=1;
		if(!$product['pics']) $product['pics'] = $product['pic'];
		$product['pics'] = explode(',',$product['pics']);
		$product = $this->formatproduct($product);		
		//优惠券
		$couponlist = Db::name('coupon')->where('aid',aid)->where('bid',$product['bid'])->where('tolist',1)->where('type','in','1,4')->where("unix_timestamp(starttime)<=".time()." and unix_timestamp(endtime)>=".time())->order('sort desc')->select()->toArray();
		$newcplist = [];
		foreach($couponlist as $k=>$v){
			$gettj = explode(',',$v['gettj']);
			if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
				continue;
			}
            if($v['isgive'] == 2) continue;//仅转赠
            //0全场通用,4指定服务活动
            if(!in_array($v['fwtype'],[0,4])){
                continue;
            }
            if($v['fwtype']==4){//指定服务活动可用
                $productids = explode(',',$v['huodong_baoming_productids']);
                if(!in_array($product['id'],$productids)){
                    continue;
                }
            }
			$haveget = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('couponid',$v['id'])->count();
			$v['haveget'] = $haveget;

            if($v['bid'] > 0){
                $v['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
            }
			$newcplist[] = $v;
		}
		//是否收藏
		$rs = Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','huodong_baoming')->find();
		if($rs){
			$isfavorite = true;
		}else{
			$isfavorite = false;
		}

		if((strtotime($product['end_time']) < time())){
			$product['isend'] = true;
		}else{
			$product['isend'] = false;
		}

		//添加浏览历史
		if(mid){
			$rs = Db::name('member_history')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','huodong_baoming')->find();
			if($rs){
				Db::name('member_history')->where('id',$rs['id'])->update(['createtime'=>time()]);
			}else{
				Db::name('member_history')->insert(['aid'=>aid,'mid'=>mid,'proid'=>$proid,'type'=>'huodong_baoming','createtime'=>time()]);
			}
		}

		$orderlist = Db::name('huodong_baoming_order')->alias('order')->field('order.id,member.headimg,member.nickname')->leftJoin('member member','member.id=order.mid')->where('order.aid',aid)->where('order.proid',$proid)->where('order.status','in','1,2,3')->order('order.id desc')->limit(7)->select()->toArray();
		if(!$orderlist) $orderlist = [];
		foreach($orderlist as $k=>$pl){
			$orderlist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
			// nickname展示两个字
			if(mb_strlen($pl['nickname'])>2){
				$orderlist[$k]['nickname'] = mb_substr($pl['nickname'],0,2).'*';
			}else{
				$orderlist[$k]['nickname'] = $pl['nickname'];
			}
		}



		$sysset = Db::name('admin_set')->where('aid',aid)->field('name,logo,desc,fxjiesuantype,tel,kfurl,gzts,ddbb')->find();

		if($product['bid']!=0){
			$business = Db::name('business')->where('aid',aid)->where('id',$product['bid'])->field('id,name,logo,desc,tel,address,sales,kfurl')->find();
		}else{
			$business = $sysset;
		}
		$product['detail'] = \app\common\System::initpagecontent($product['detail'],aid,mid,platform);

		
		$sysset['showgzts'] = false;
		//关注提示
		if(platform == 'mp'){
			$sysset['gzts'] = explode(',',$sysset['gzts']);
			if(in_array('2',$sysset['gzts']) && $this->member['subscribe']==0){
				$appinfo = \app\common\System::appinfo(aid,'mp');
				$sysset['qrcode'] = $appinfo['qrcode'];
				$sysset['gzhname'] = $appinfo['nickname'];
				$sysset['showgzts'] = true;
			}
		}
		//获取设置
		$set = Db::name('huodong_baoming_set')->where('aid',aid)->where('bid',0)->find();

		$minprice = 999999999999999;
		$maxprice = 0;
		$gglist = Db::name('huodong_baoming_guige')->where('proid',$product['id'])->select()->toArray();

		foreach($gglist as $k=>$v){
			if($v['sell_price'] < $minprice){
				$minprice = $v['sell_price'];
			}
			if($v['sell_price'] > $maxprice){
				$maxprice = $v['sell_price'];
			}
		}
		$product['min_price'] = round($minprice,2);
		$product['max_price'] = round($maxprice,2);

		$product['sales'] = $product['sales']+$product['start_sales'];
		$product['viewnum'] = $product['viewnum']+$product['start_viewnum'];

		//多个主办单位
		if($product['huodong_danwei']){
			$product['huodong_danwei'] = json_decode($product['huodong_danwei'],true);
		}else{
			$product['huodong_danwei'] =[];
		}
		if(!$set['textset']) {
			$textset = ['活动单位'=>'活动单位','联系电话'=>'联系电话','活动地址'=>'活动地址','活动时间'=>'活动时间'];
		} else{
			$textset = json_decode($set['textset'], true);
		}


		//积分保留几位小数
		$score_weishu = 0;
		$product['score_price'] = dd_money_format($product['score_price'],$score_weishu);

		// 根据$product['end_time']计算剩余时间
		$subTime = strtotime($product['end_time'])-time();
		// 根据时间差换算成时间
	 
		if($product['mianfei_memberlevel_open'] == 1){
			$is_price = 1;
			$gettj = explode(',',$product['mianfei_gettj']);
			if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
				if(in_array('0',$gettj)){ //关注用户才能领
					if($this->member['subscribe']!=1){
						$is_price = 0;
					}
				}else{
					$is_price = 0;
				}
			}
			if($is_price ==1){
				$product['sell_price']  = 0;	
				$product['score_price']  = 0;				
			}
		}
		 

		 
		$start_time = strtotime($product['start_time']);
		if($start_time> time()){
			$product['is_start'] = false;
		}else{
			$product['is_start'] = true;
		}
        $shopset_field = 'showjd,showcommission,hide_sales,hide_stock,show_lvupsavemoney';
        $shopset = Db::name('shop_sysset')->where('aid',aid)->field($shopset_field)->find();
		$rdata = [];
		$rdata['date'] =$this->timeToString($subTime);
		$rdata['status'] = 1;
		$rdata['title'] = $product['name'];
        $rdata['set'] = $set ? $set : [];
		$rdata['isfavorite'] = $isfavorite;
		$rdata['product'] = $product;
		
	
		$rdata['business'] = $business;
		$rdata['shopset'] = $shopset;
		$rdata['sysset'] = $sysset;
		$rdata['orderlist'] = $orderlist;
		$rdata['text'] = $textset;
		$rdata['huodong_end_time_date'] = strtotime($product['end_time']);
		$rdata['huodong_start_time_date'] = strtotime($product['start_time']);
		$rdata['now_time'] = time();
		//$rdata['couponlist'] = $newcplist;
		return $this->json($rdata);
	}
	//时间秒转天时分
	public function timeToString(int $second)
	{
		$d = floor($second/(3600*24));
		$second = $second%(3600*24);//除去整天之后剩余的时间
		$h = floor($second/3600);
		$second = $second%3600;//除去整小时之后剩余的时间
		$m = floor($second/60);
		$s = $second%60;//除去整分钟之后剩余的时间
	
		if($d>'0'){
			return $d.'天'.$h.'小时'.$m.'分';
		}elseif ($h>0){
			return $h.'小时'.$m.'分';
		}elseif($m>0){
			return $m.'分';
		} 
	}
	//获取活动详情
	public function getproductdetail(){
		$proid = input('param.id/d');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','=',$proid];
		$field = "bid,id,pic,name,sales,sell_price,guigedata,status,start_time,end_time,perlimit";

		$product = Db::name('huodong_baoming_product')->field($field)->where($where)->find();
		if(!$product){
			return $this->json(['status'=>0,'msg'=>'活动不存在']);
		}
		//$product = $this->formatproduct($product);
		if($product['status']==0) return $this->json(['status'=>0,'msg'=>'活动已下架']);

		if($product['status']==2 && (strtotime($product['start_time']) > time() || strtotime($product['end_time']) < time())){
			return $this->json(['status'=>0,'msg'=>'活动未上架']);
		}

		$gglist = Db::name('huodong_baoming_guige')->where('proid',$product['id'])->select()->toArray();

		$score_weishu = 0;
		foreach($gglist as &$gg){
			$gg['score_price'] = dd_money_format($gg['score_price'],$score_weishu);
		}

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
		$set = Db::name('huodong_baoming_set')->where('aid',aid)->find();
		return $this->json(['status'=>1,'product'=>$product,'guigelist'=>$guigelist,'guigedata'=>$guigedata,'ggselected'=>$ggselected,'ks'=>$ks,'set'=>$set]);
	}

	//活动海报
	function getposter(){
		$this->checklogin();
		$post = input('post.');
		$platform = platform;
		$page = '/pagesB/huodongbaoming/product';
		$scene = 'id_'.$post['proid'].'-pid_'.$this->member['id'];
		//if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
		//	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
		//}
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','huodong_baoming')->where('platform',$platform)->order('id')->find();

		$posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','huodong_baoming')->where('posterid',$posterset['id'])->find();
		if(true || !$posterdata){
			$product = Db::name('huodong_baoming_product')->where('id',$post['proid'])->find();
			$product = $this->formatproduct($product);
			$sysset = Db::name('admin_set')->where('aid',aid)->find();
			$textReplaceArr = [
				'[头像]'=>$this->member['headimg'],
				'[昵称]'=>$this->member['nickname'],
				'[姓名]'=>$this->member['realname'],
				'[手机号]'=>$this->member['mobile'],
				'[商城名称]'=>$sysset['name'],
				'[活动名称]'=>$product['name'],
				'[活动销售价]'=>$product['sell_price'],
				'[商品图片]'=>$product['pic'],
			];

			$poster = $this->_getposter(aid,$product['bid'],$platform,$posterset['content'],$page,$scene,$textReplaceArr);
			$posterdata = [];
			$posterdata['aid'] = aid;
			$posterdata['mid'] = $this->member['id'];
			$posterdata['scene'] = $scene;
			$posterdata['page'] = $page;
			$posterdata['type'] = 'huodong_baoming';
			$posterdata['poster'] = $poster;
			$posterdata['createtime'] = time();
			Db::name('member_poster')->insert($posterdata);
		}
		return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
	}


	//订单提交页
	public function buy(){
		$this->checklogin();
		$prodata = explode('-',input('param.prodata'));

		$adminset = Db::name('admin_set')->where('aid',aid)->find();
		//会员折扣
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		$userinfo = [];
		$userinfo['id'] = $this->member['id'];
		$userinfo['realname'] = $this->member['realname'];
		$userinfo['tel'] = $this->member['tel'];
		$userinfo['discount'] = $userlevel['discount'];

		$allbuydata = [];
		$autofahuo  = 0;

		foreach($prodata as $key=>$gwc){
			list($proid,$ggid,$num) = explode(',',$gwc);
			$product = Db::name('huodong_baoming_product')->where('aid',aid)->where('id',$proid)->find();
			if($product['status']==0){
				return $this->json(['status'=>0,'msg'=>'活动未上架']);
			}
			if(strtotime($product['start_time']) > time()){
				return $this->json(['status'=>0,'msg'=>'活动报名未开始']);
			}
			if(strtotime($product['end_time']) < time()){
				return $this->json(['status'=>0,'msg'=>'活动报名已结束']);
			}
			if($product['stock']>0 && $product['sales']+$num > $product['stock']){
				return $this->json(['status'=>0,'msg'=>'活动名额已满']);
			}
			if($product['perlimit']>0 && $product['sales']+$num > $product['stock']){
				$where = [];
				$where[] = ['aid','=',aid];
				$where[] = ['proid','=',$proid];
				$where[] = ['mid','=',mid];
				$where[] = ['status','in','0,1,2,3'];
				$buy_num = Db::name('huodong_baoming_order')->where($where)->count();
				if($buy_num+$num > $product['perlimit']){
					return $this->json(['status'=>0,'msg'=>'超过报名限制']);
				}				
			}
			if($ggid == 0){
				$guige = [
					'sell_price'=>0
				];
			}else{
				$guige = Db::name('huodong_baoming_guige')->where('id',$ggid)->find();
			}

			//免费用户级别
			if($product['mianfei_memberlevel_open'] == 1){
				$is_price = 1;
				$gettj = explode(',',$product['mianfei_gettj']);
				if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
					if(in_array('0',$gettj)){ //关注用户才能领
						if($this->member['subscribe']!=1){
							$is_price = 0;
						}
					}else{
						$is_price = 0;
					}
				}
				if($is_price ==1){
					$product['sell_price'] = 0;					
					$guige['sell_price'] = 0;	
					$product['score_price'] = 0;					
					$guige['score_price'] = 0;				
				}
			}
			
			

			if(!$allbuydata[$product['bid']]) $allbuydata[$product['bid']] = [];
			if(!$allbuydata[$product['bid']]['prodata']) $allbuydata[$product['bid']]['prodata'] = [];
			$allbuydata[$product['bid']]['prodata'][] = ['product'=>$product,'guige'=>$guige,'num'=>$num];
		}
		



		$address = Db::name('member_address')->where('aid',aid)->where('mid',mid)->order('isdefault desc,id desc')->find();
		if(!$address) $address = [];
		$needLocation = 0;
		$allproduct_price = 0;
		foreach($allbuydata as $bid=>$buydata){
			if($bid!=0){
				$business = Db::name('business')->where('id',$bid)->field('id,aid,cid,name,logo,tel,address,sales,longitude,latitude,start_hours,end_hours,start_hours2,end_hours2,start_hours3,end_hours3,end_buy_status,invoice,invoice_type,province,city,district')->find();
				
			}else{
				$business = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,desc,tel,province,city,district,address')->find();
                $business['province'] = $business['province']?$business['province']:'';
                $business['city'] = $business['city']?$business['city']:'';
                $business['district'] = $business['district']?$business['district']:'';
			}
		
			$product_priceArr = [];
			$product_price = 0;
			$needzkproduct_price = 0;
			$totalweight = 0;
			$totalnum = 0;
			$prodataArr = [];
			$proids = [];
			$cids = [];
			foreach($buydata['prodata'] as $prodata){
				$product_priceArr[] = $prodata['guige']['sell_price'] * $prodata['num'];
				$product_price += $prodata['guige']['sell_price'] * $prodata['num'];
				//积分
				$score_price += $prodata['guige']['score_price'] * $prodata['num'];
				$needzkproduct_price += $prodata['guige']['sell_price'] * $prodata['num'];
				$totalprice = $prodata['guige']['sell_price'] * $prodata['num'];
				$totalnum += $prodata['num'];
				$prodataArr[] = $prodata['product']['id'].','.$prodata['guige']['id'].','.$prodata['num'];
				$proids[] = $prodata['product']['id'];
				$cids = array_merge(explode(',',$prodata['product']['cid']),$cids);
			}
			$prodatastr = implode('-',$prodataArr);
			
			$yyset = db('huodong_baoming_set')->where('aid',aid)->find();
			if(!$yyset) $yyset=[];
			$leveldk_money = 0;
			if($yyset['discount']==1 && $userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
				$leveldk_money = $needzkproduct_price * (1 - $userlevel['discount'] * 0.1);
			}
			$leveldk_money = round($leveldk_money,2);
			$price = $product_price - $leveldk_money;
            $manjian_money = 0;
			$newcouponlist = [];
			if($yyset['iscoupon']==1){
                $bwhere2 = [];
                $bwhere2 = [['type','in','1,10']];
				if($bid > 0){
					$business = Db::name('business')->where('aid',aid)->where('id', $bid)->find();
					$bcids = $business['cid'] ? explode(',',$business['cid']) : [];
				}else{
					$bcids = [];
				}
				if($bcids){
					$whereCid = [];
					foreach($bcids as $bcid){
						$whereCid[] = "find_in_set({$bcid},canused_bcids)";
					}
					$whereCids = implode(' or ',$whereCid);
				}else{
					$whereCids = '0=1';
				}

				$couponList = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where($bwhere2)->where('status',0)
					->whereRaw("bid=-1 or bid=".$bid." or (bid=0 and (canused_bids='all' or find_in_set(".$bid.",canused_bids) or ($whereCids)))")
					->where('minprice','<=',$price - $manjian_money)->where('starttime','<=',time())->where('endtime','>',time())
				    ->order('id desc')->select()->toArray();

				if(!$couponList) $couponList = [];
				foreach($couponList as $k=>$v){
					//$couponList[$k]['starttime'] = date('m-d H:i',$v['starttime']);
					//$couponList[$k]['endtime'] = date('m-d H:i',$v['endtime']);
					$couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$v['couponid'])->find();
                    if(empty($couponinfo)){
                        continue;
                    }
                    //不可自用
                    if($couponinfo['isgive']==2){
                        continue;
                    }
                    //适用场景
                    if($couponinfo['fwscene'] !==0){
                        continue;
                    }
                    //0全场通用,4指定服务活动
                    if(in_array($couponinfo['fwtype'],[0,4])){
                        if($couponinfo['fwtype']==4){//指定服务活动可用
                            $productids = explode(',',$couponinfo['huodong_baoming_productids']);
                            if(!array_intersect($proids,$productids)){
                                continue;
                            }
                        }
                        if($v['bid'] > 0){
                            $binfo = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->find();
                            $v['bname'] = $binfo['name'];
                        }
                        $newcouponlist[] = $v;
                    }else{
                        continue;
                    }
				}
			}

			//取出设置的自定义表单
			//$yyset = db('huodong_baoming_set')->where('aid',aid)->find();
			$couponList = $newcouponlist;
		
			$allbuydata[$bid]['bid'] = $bid;
			$allbuydata[$bid]['business'] = $business;
			$allbuydata[$bid]['prodatastr'] = $prodatastr;
			$allbuydata[$bid]['couponList'] = $couponList;
			$allbuydata[$bid]['couponCount'] = count($couponList);
			$allbuydata[$bid]['sell_price'] = round($price,2);
			$allbuydata[$bid]['leveldk_money'] = $leveldk_money;
			$allbuydata[$bid]['coupon_money'] = 0;
			$allbuydata[$bid]['coupontype'] = 1;
			$allbuydata[$bid]['couponrid'] = 0;
			$allbuydata[$bid]['editorFormdata'] = [];
			$allbuydata[$bid]['product_price'] = round($product_price,2);
			$allbuydata[$bid]['score_price'] = round($score_price,2);
			$allbuydata[$bid]['formdata'] = json_decode($product['formdata'],true);
			$allbuydata[$bid]['bid'] = $bid;
			$allproduct_price += $product_price;
		}
		

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['address'] = $address;
		$rdata['linkman'] = $address ? $address['name'] : strval($userinfo['realname']);
		$rdata['tel'] = $address ? $address['tel'] : strval($userinfo['tel']);
		if(!$rdata['linkman']){
			$lastorder = Db::name('huodong_baoming_order')->where('aid',aid)->where('mid',mid)->where('linkman','<>','')->find();
			if($lastorder){
				$rdata['linkman'] = $lastorder['linkman'];
				$rdata['tel'] = $lastorder['tel'];
			}
		}
		$rdata['userinfo'] = $userinfo;
		$rdata['allbuydata'] = $allbuydata;
		$rdata['yyset'] = $yyset;
		return $this->json($rdata);
	}

	public function createOrder(){
		$this->checklogin();
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		$post = input('post.');
		$buydata = $post['buydata'];

	    $userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();

		$couponridArr = [];
		foreach($buydata as $data){ //判断有没有重复选择的优惠券
			if($data['couponrid'] && in_array($data['couponrid'],$couponridArr)){
				return $this->json(['status'=>0,'msg'=>t('优惠券').'不可重复使用']);
			}elseif($data['couponrid']){
				$couponridArr[] = $data['couponrid'];
			}
		}
		$ordernum = date('ymdHis').rand(100000,999999);
		$i = 0;
		$alltotalprice = 0;

		foreach($buydata as $data){
			$i++;
			if($data['prodata']){
				$prodata = explode('-',$data['prodata']);
			}else{
				return $this->json(['status'=>0,'msg'=>'产品数据错误']);
			}
			$bid = $data['bid'];
			$product_priceArr = [];
			$product_price = 0;
			$balance_price = 0;
			$needzkproduct_price = 0;
			$givescore = 0;
			$totalnum = 0;
			$prolist = [];
			$proids = [];
			$cids = [];

			foreach($prodata as $key=>$pro){
				$sdata = explode(',',$pro);
				$sdata[2] = intval($sdata[2]);
				$num = intval($sdata[2]);
				$proid = intval($sdata[0]);
				if($sdata[2] <= 0) return $this->json(['status'=>0,'msg'=>'购买数量有误']);
				$product = Db::name('huodong_baoming_product')->where('aid',aid)->where('id',$sdata[0])->find();
				
				if(!$product) return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
				if($product['status']==0){
					return $this->json(['status'=>0,'msg'=>'活动未上架']);
				}				
				if(strtotime($product['start_time']) > time() || strtotime($product['end_time']) < time()){
					return $this->json(['status'=>0,'msg'=>'活动已结束']);
				}
				if($product['stock']>0 && $product['sales']+$num > $product['stock']){
					return $this->json(['status'=>0,'msg'=>'活动名额已满']);
				}
				if($product['perlimit']>0 && $product['sales']+$num > $product['stock']){
					$where = [];
					$where[] = ['aid','=',aid];
					$where[] = ['proid','=',$proid];
					$where[] = ['mid','=',mid];
					$where[] = ['status','in','0,1,2,3'];
					$buy_num = Db::name('huodong_baoming_order')->where($where)->count();
					if($buy_num+$num > $product['perlimit']){
						return $this->json(['status'=>0,'msg'=>'超过报名限制']);
					}				
				}
				if($product['fanwei'] == 1){
					$juli = getdistance(input('post.longitude'),input('post.latitude'),$product['fanwei_lng'],$product['fanwei_lat'],2);
					if($juli > $product['fanwei_range']/1000){
						return $this->json(['status'=>0,'msg'=>'超出活动范围']);
					}
				}
				$gettj = explode(',',$product['gettj']);
				if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
					if(in_array('0',$gettj)){ //关注用户才能领
						if($this->member['subscribe']!=1){
							$appinfo = \app\common\System::appinfo(aid,'mp');
							return $this->json(['status'=>0,'msg'=>'请先关注'.$appinfo['nickname'].'公众号']);
						}
					}else{
						return $this->json(['status'=>0,'msg'=>'您没有参与权限']);
					}
				}

				if($key==0) $title = $product['name'];
				if($sdata[1] > 0){
					$guige = Db::name('huodong_baoming_guige')->where('aid',aid)->where('id',$sdata[1])->find();

					if(!$guige) return $this->json(['status'=>0,'msg'=>'产品规格不存在或已下架']);
					$totalnum += $sdata[2];
					

					$product_priceArr[] = $guige['sell_price'] * $sdata[2];
					$product_price += $guige['sell_price'] * $sdata[2];
					if($product['lvprice']==0 && $product['no_discount'] == 0){ //未开启会员价
						$needzkproduct_price += $guige['sell_price'] * $sdata[2];
					}
					$prolist[] = ['product'=>$product,'guige'=>$guige,'num'=>$sdata[2]];
					$proids[] = $product['id'];
					$cids = array_merge($cids,explode(',',$product['cid']));
				}else{
					$totalnum +=0;
					$product_price +=0;
				}
				$totalscore += $product['score_price'] * $num;
			}
			$totalprice = $product_price;

		}

		$yyset = db('huodong_baoming_set')->where('aid',aid)->find();
		//会员折扣
		$leveldk_money = 0;
		if($yyset['discount']==1 && $userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			$leveldk_money = $needzkproduct_price * (1 - $userlevel['discount'] * 0.1);
		}
		if($product_price>=$leveldk_money){
			$totalprice = $product_price - $leveldk_money;
		}else{
			$totalprice = 0;
		}
		if($product['mianfei_memberlevel_open'] == 1){
			$is_price = 1;
			$gettj = explode(',',$product['mianfei_gettj']);
			if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
				if(in_array('0',$gettj)){ //关注用户才能领
					if($this->member['subscribe']!=1){
						$is_price = 0;
					}
				}else{
					$is_price = 0;
				}
			}
			if($is_price ==1){
				$totalprice = 0;	
				$totalscore = 0;				
			}
		}
		if($product['is_fufei'] == 0){
			    $totalprice = 0;	
				$totalscore = 0;
		}

		//优惠券
		if($data['couponrid'] > 0){
			$couponrid = $data['couponrid'];

			$bid = $data['bid'];
			if($bid > 0){
				$business = Db::name('business')->where('aid',aid)->where('id', $bid)->find();
				$bcids = $business['cid'] ? explode(',',$business['cid']) : [];
			}else{
				$bcids = [];
			}
			if($bcids){
				$whereCid = [];
				foreach($bcids as $bcid){
					$whereCid[] = "find_in_set({$bcid},canused_bcids)";
				}
				$whereCids = implode(' or ',$whereCid);
			}else{
				$whereCids = '0=1';
			}
			$couponrecord = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('id',$couponrid)
				->whereRaw("bid=-1 or bid=".$bid." or (bid=0 and (canused_bids='all' or find_in_set(".$bid.",canused_bids) or ($whereCids)))")->find();
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
			}

			$couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$couponrecord['couponid'])->find();
            if(empty($couponinfo)){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不存在或已作废']);
            }
            if($couponrecord['from_mid']==0 && $couponinfo && $couponinfo['isgive']==2){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'仅可转赠']);
            }
            //适用场景
            if($couponinfo['fwscene']!==0){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
            }
            //0全场通用,4指定服务活动
            if(!in_array($couponinfo['fwtype'],[0,4])){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'超出可用范围']);
            }
            if($couponinfo['fwtype']==4){//指定服务活动可用
                $productids = explode(',',$couponinfo['huodong_baoming_productids']);
                if(!array_intersect($proids,$productids)){
                    return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'超出可用范围']);
                }
            }
			if($couponrecord['type']==1 || $couponrecord['type']==4){
				Db::name('coupon_record')->where('id',$couponrid)->update(['status'=>1,'usetime'=>time()]);
			}

			if($couponrecord['type']==4){//运费抵扣券
				$coupon_money = 0;
			}elseif($couponrecord['type']==3){//计次券 抵扣全部金额
				$coupon_money = $totalprice;
			}elseif($couponrecord['type']==10){//折扣券
				$coupon_money = $totalprice * (100 - $couponrecord['discount']) * 0.01;
			}else{
				$coupon_money = $couponrecord['money'];
				if($coupon_money > $totalprice) $coupon_money = $totalprice;
			}
		}else{
			$coupon_money = 0;
		}
		$totalprice = $totalprice - $coupon_money;

		$orderdata = [];


		$orderdata['aid'] = aid;
		$orderdata['mid'] = mid;
		$orderdata['bid'] = $data['bid'];
		if(count($buydata) > 1){
			$orderdata['ordernum'] = $ordernum.'_'.$i;
		}else{
			$orderdata['ordernum'] = $ordernum;
		}

		$orderdata['title'] = $title.(count($prodata)>1?'等':'');
		$orderdata['linkman'] = $post['linkman'];
		$orderdata['tel'] = $post['tel'];
		$orderdata['totalprice'] = $totalprice;
		if($totalprice == 0 && $totalscore==0){
			$orderdata['status'] = 1;
			$orderdata['paytime'] = time();
			Db::name('huodong_baoming_product')->where('aid',aid)->where('id',$product['id'])->update(['sales'=>Db::raw("sales+$num")]);	
			if($product['givescore']){
				\app\common\Member::addscore(aid,mid,$product['givescore'],'参与报名活动赠送'.t('积分'));
			}
		}
		$orderdata['leveldk_money'] = $leveldk_money;	//会员折扣
		$orderdata['product_price'] = $product_price;
		$orderdata['cost_price'] = $guige['cost_price'];
		$orderdata['givescore'] = $product['givescore'];
		$orderdata['coupon_money'] = $coupon_money;		//优惠券抵扣
		$orderdata['coupon_rid'] = $couponrid;
		$orderdata['createtime'] = time();
		$orderdata['platform'] = platform;
		$orderdata['hexiao_code'] = random(16);
		$orderdata['remark'] = $post['remark'];
		$orderdata['proname'] = $product['name'];
		$orderdata['protype'] = $product['type'];
		$orderdata['ggname'] = $guige['name'];
		$orderdata['num'] = $sdata[2];
		$orderdata['propic'] = $guige['pic'] ? $guige['pic'] : $product['pic'];
		$orderdata['proid'] = $product['id'];
		$orderdata['ggid'] = $guige['id'];

		//所需积分
		$orderdata['totalscore'] = $totalscore;
		$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=huodong_baoming&co='.$orderdata['hexiao_code']));

		$orderid = Db::name('huodong_baoming_order')->insertGetId($orderdata);

		 $this->saveformdata($orderid,'huodong_baoming_order',$data['formdata'],$product['id']);
		if(($orderdata['status']==0 && ($orderdata['totalprice']>0 || $orderdata['totalscore']>0))){
			$payorderid = \app\model\Payorder::createorder(aid,$orderdata['bid'],$orderdata['mid'],'huodong_baoming',$orderid,$orderdata['ordernum'],$orderdata['title'],$orderdata['totalprice'],$orderdata['totalscore']);
		}else{
			$payorderid = 0;
		}

        if($bid)
            $store_info = Db::name('business')->where('aid',aid)->where('id',$orderdata['bid'])->find();
        else
            $store_info = Db::name('admin_set')->where('aid',aid)->find();
        $store_name = $store_info['name'];

        //公众号通知 订单提交成功
        $tmplcontent = [];
        $tmplcontent['first'] = '有新活动报名订单提交成功';
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = $store_name; //店铺
        $tmplcontent['keyword2'] = date('Y-m-d H:i:s',$orderdata['createtime']);//下单时间
        $tmplcontent['keyword3'] = $orderdata['title'];//活动
        $tmplcontent['keyword4'] = $orderdata['totalprice'].'元';//金额
        $tempconNew = [];
        $tempconNew['character_string2'] = $orderdata['ordernum'];//订单号
        $tempconNew['thing8'] = $store_name;//门店名称
        $tempconNew['thing3'] = $orderdata['title'];//活动名称
        $tempconNew['amount7'] = $orderdata['totalprice'];//金额
        $tempconNew['time4'] = date('Y-m-d H:i:s',$orderdata['createtime']);//下单时间
       $re= \app\common\Wechat::sendhttmpl(aid,$orderdata['bid'],'tmpl_orderconfirm',$tmplcontent,m_url('adminExt/huodongbaoming/order'),$orderdata['mdid'],$tempconNew);
 
        $tmplcontent = [];
        $tmplcontent['thing11'] = $orderdata['title'];
        $tmplcontent['character_string2'] = $orderdata['ordernum'];
        $tmplcontent['phrase10'] = $orderdata['status']==0?'待付款':'已付款';
        $tmplcontent['amount13'] = $orderdata['totalprice'].'元';
        $tmplcontent['thing27'] = $this->member['nickname'];
        \app\common\Wechat::sendhtwxtmpl(aid,$orderdata['bid'],'tmpl_orderconfirm',$tmplcontent,'adminExt/huodongbaoming/order',$orderdata['mdid']);

		if($orderdata['status']==1){
			//短信通知
			if($orderdata['tel']){
				$rs = \app\common\Sms::send(aid,$orderdata['tel'],'tmpl_yysucess',['name'=>$orderdata['title'],'time'=>$orderdata['yy_time']]);
			}
		}
		$num = $sdata[2];

		return $this->json(['status'=>1,'payorderid'=>$payorderid,'msg'=>'提交成功']);
	}

	//保存自定义表单内容
	public function saveformdata($orderid,$type='huodong_baoming_order',$formdata,$proid){
		if(!$orderid || !$formdata) return ['status'=>0];
		//根据orderid 取出proid
		$formfield = Db::name('huodong_baoming_product')->where('id',$proid)->find();
		$formdataSet = json_decode($formfield['formdata'],true);
		//var_dump($formdataSet);die;
		$data = [];
		foreach($formdataSet as $k=>$v){
			$value = $formdata['form'.$k];
			if(is_array($value)){
				$value = implode(',',$value);
			}
			$value = strval($value);
			$data['form'.$k] = $v['val1'] . '^_^' .$value . '^_^' .$v['key']. '^_^' .$v['val4'];
			if($v['val3']==1 && $value===''){
				return ['status'=>0,'msg'=>$v['val1'].' 必填'];
			}
		}
		$data['aid'] = aid;
		$data['type'] = 'huodong_baoming_order';
		$data['orderid'] = $orderid;
		$data['createtime'] = time();
		Db::name('freight_formdata')->insert($data);
		return ['status'=>1];
	}

	//订单列表
	public function orderlist(){
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
		
		$datalist = Db::name('huodong_baoming_order')->where($where)->order('id desc')->page($pagenum,10)->select()->toArray();
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

	public function orderdetail(){
		$detail = Db::name('huodong_baoming_order')->where('id',input('param.id/d'))->where('aid',aid)->where('mid',mid)->find();
		if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\model\Freight::getformdata($detail['id'],'huodong_baoming_order');
		$storeinfo = [];
		if($detail['freight_type'] == 1){
            $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('id,name,address,longitude,latitude')->find();
		}
		
		if($detail['bid'] > 0){
			$detail['binfo'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->field('id,name,logo,province,city,district,address')->find();
		}else{
			$detail['binfo'] = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,province,city,district,address')->find();
        }

		$prolist = Db::name('huodong_baoming_order')->where('id',$detail['id'])->find();		
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['detail'] = $detail;
		$rdata['prolist'] = $prolist;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');

        //发票
//        $rdata['invoice'] = 0;
//        if($detail['bid']) {
//            $rdata['invoice'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->value('invoice');
//        } else {
//            $rdata['invoice'] = Db::name('admin_set')->where('aid',aid)->value('invoice');
//        }

		return $this->json($rdata);
	}

	public function refund(){//申请退款
		$this->checklogin();
		if(request()->isPost()){
			$post = input('post.');
			$orderid = intval($post['orderid']);
			$money = floatval($post['money']);
			$order = Db::name('huodong_baoming_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();

			if(!$order || ($order['status']!=1 && $order['status'] != 2) || $order['refund_status'] == 2){
				return $this->json(['status'=>0,'msg'=>'订单状态不符合退款要求']);
			}
			if($money < 0 || $money > $order['totalprice']){
				return $this->json(['status'=>0,'msg'=>'退款金额有误']);
			}

            Db::name('huodong_baoming_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['refund_time'=>time(),'refund_status'=>1,'refund_reason'=>$post['reason'],'refund_money'=>$money]);
            $tmplcontent = [];
            $tmplcontent['first'] = '有服务订单客户申请退款';
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = $order['ordernum'];
            $tmplcontent['keyword2'] = $money.'元';
            $tmplcontent['keyword3'] = $post['reason'];
            $tmplcontentNew = [];
            $tmplcontentNew['number2'] = $order['ordernum'];//订单号
            $tmplcontentNew['amount4'] = $money;//退款金额
            \app\common\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,m_url('adminExt/huodongbaoming/order'),$order['mdid'],$tmplcontentNew);

            $tmplcontent = [];
            $tmplcontent['thing1'] = $order['title'];
            $tmplcontent['character_string4'] = $order['ordernum'];
            $tmplcontent['amount2'] = $order['totalprice'];
            $tmplcontent['amount9'] = $money.'元';
            $tmplcontent['thing10'] = $post['reason'];
            \app\common\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,'adminExt/huodongbaoming/order',$order['mdid']);

            return $this->json(['status'=>1,'msg'=>'提交成功,请等待商家审核']);
		}
		$rdata = [];
		$rdata['price'] = input('param.price/f');
		$rdata['orderid'] = input('param.orderid/d');
		$order = Db::name('huodong_baoming_order')->where('aid',aid)->where('mid',mid)->where('id',$rdata['orderid'])->find();
		$rdata['price'] = $order['totalprice'];
		return $this->json($rdata);
	}

	public function closeOrder(){
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('huodong_baoming_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || $order['status']>1){
			return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		//优惠券抵扣的返还
		if($order['coupon_rid'] > 0){
			//查看是不是计次卡
			$record = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('id',$order['coupon_rid'])->find();
			Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);	
			
		}
		$rs = Db::name('huodong_baoming_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>4]);
		return $this->json(['status'=>1,'msg'=>'取消成功']);
	}

	public function delOrder(){
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('huodong_baoming_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || ($order['status']!=4 && $order['status']!=3)){
			return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
		}
		if($order['status']==3){
			$rs = Db::name('huodong_baoming_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['delete'=>1]);
		}else{
			$rs = Db::name('huodong_baoming_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->delete();
		}
		return $this->json(['status'=>1,'msg'=>'删除成功']);
	}

	public function orderCollect(){ //确认完成
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('huodong_baoming_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
		if(!$order || ($order['status']!=1) || $order['paytypeid']==4){
			return $this->json(['status'=>0,'msg'=>'订单状态不符合收货要求']);
		}
		// $rs = \app\common\Order::collect($order,'huodong_baoming');
		// if($rs['status'] == 0) return $this->json($rs);

		Db::name('huodong_baoming_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);

		$return = ['status'=>1,'msg'=>'确认成功','url'=>true];

		return $this->json($return);
	}

	//订单列表
	public function proorderlist(){
		$this->checklogin();
		$st = input('param.st');
		$proid = input('param.proid');
		if(!$st && $st!=='0') $st = 'all';
		$pagenum = input('param.pagenum') ? input('param.pagenum') : 1;
		$where = [];
		$where[] = ['order.aid','=',aid];
		$where[] = ['order.proid','=',$proid];
		$where[] = ['order.status','in','1,2,3'];
		
		$datalist = Db::name('huodong_baoming_order')->alias('order')->field('order.id,order.createtime,member.headimg,member.nickname')->leftJoin('member member','member.id=order.mid')->where($where)->order('id desc')->page($pagenum,10)->select()->toArray();
		if(!$datalist) $datalist = [];
		$formdata = Db::name('huodong_baoming_product')->where('id',$proid)->value('formdata');
		$formArr = json_decode($formdata,true);
 
		foreach($datalist as $key=>$v){
			  $formdataBaomingNew=[];
			  $formdataBaoming = \app\model\Freight::getformdata($v['id'],'huodong_baoming_order');	
			  foreach($formdataBaoming as $k1=>$v1){	
				if($v1['3'] == 1){
					$formdataBaomingNew[]=$v1;
				} 
			  }
			  $datalist[$key]['formdata'] = $formdataBaomingNew; 
		}
		$rdata = [];
		$rdata['st'] = $st;
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}

}