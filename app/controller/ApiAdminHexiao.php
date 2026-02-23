<?php
// JK客户定制

//管理员中心 -- 核销订单
namespace app\controller;
use think\facade\Db;
class ApiAdminHexiao extends ApiAdmin
{	
	//核销 每增加一种核销类型都要加到权限里面（view/public/user_auth.html、view/web_user/edit.html）
	public function hexiao(){
		$type = input('param.type');
        if(!$type || empty($type)){
            return $this->json(['status'=>0,'msg'=>'无效二维码，不支持核销']);
        }
		$code = input('param.co');
        $mendian_no_select = 0;
        if(getcustom('mendian_no_select')){
            //甘尔定制，不需要选择门店
            $mendian_no_select = 1;
        }
		//多商户可以核销平台的商品
		$plat_hexiao = false;
		if(getcustom('business_hexiaoplatform') && bid > 0){
			$adminuser = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->find();
			if($adminuser['auth_type']==0){
				$auth_data = json_decode($adminuser['auth_data'],true);
				$auth_path = [];
				foreach($auth_data as $v){
					$auth_path = array_merge($auth_path,explode(',',$v));
				}
				$auth_data = $auth_path;
			}else{
				$auth_data = 'all';
			}
			if($auth_data=='all' || in_array('business_hexiaoplatform',$auth_data)){
				$plat_hexiao = true;
			}
		}
        //核销次数
        if(getcustom('user_hexiao_num')){
            if($this->user['hexiao_num'] !=-1 &&  $this->user['hexiao_num'] <=0 ){
                return $this->json(['status'=>0,'msg'=>'该账号核销数量已到上限']);
            }
        }
		if($type=='coupon'){
			$order = Db::name('coupon_record')->where('aid',aid)->where('code', $code)->find();
			if($order['bid']!=bid && !$plat_hexiao) return $this->json(['status'=>0,'msg'=>'登录的账号不是该商家的管理账号']);
            if(!$order) return $this->json(['status'=>0,'msg'=>t('优惠券').'不存在']);
            if($order['status']==1) return $this->json(['status'=>0,'msg'=>t('优惠券').'已使用']);
            if($order['starttime'] > time()) return $this->json(['status'=>0,'msg'=>t('优惠券').'尚未生效']);
            if($order['endtime'] < time()) return $this->json(['status'=>0,'msg'=>t('优惠券').'已过期']);
            if($order['type']==3 && $order['used_count']>=$order['limit_count']) return $this->json(['status'=>0,'msg'=>'已达到使用次数']);
            $order['show_addnum'] = 0;
            if(getcustom('coupon_view_range')){
                if($order['type']==3){
                    $order['show_addnum'] = 1;
                }
            }
            if($order['type']==3 && $order['limit_perday'] > 0){ //是否达到每天使用次数限制
                $dayhxnum = Db::name('hexiao_order')->where('orderid',$order['id'])->where('type','coupon')->where('createtime','between',[strtotime(date('Y-m-d 00:00:00')),strtotime(date('Y-m-d 23:59:59'))])->count();
                if($dayhxnum >= $order['limit_perday']){
                    return $this->json(['status'=>0,'msg'=>'该计次券每天最多核销'.$order['limit_perday'].'次']);
                }
            }

			$coupon = Db::name('coupon')->where('id',$order['couponid'])->find();
			$order['usetips'] = $coupon['usetips'];
            $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            $order['usetime'] = date('Y-m-d H:i:s',$order['usetime']);
            $order['starttime'] = date('Y-m-d H:i:s',$order['starttime']);
            $order['endtime'] = date('Y-m-d H:i:s',$order['endtime']);
            $order['is_show_mendians'] = 0;
            // 门店核销奖励
            if(getcustom('mendian_hexiao_coupon_reward')){
                if(!$this->user['mdid']){
                    $where_mendian = [];
                    $where_mendian[] = ['aid','=',aid]; 
                    $where_mendian[] = ['bid','=',bid];
                    $mendians = Db::name('mendian')->where($where_mendian)->column('name','id');
                    $order['mendians'] = $mendians;
                    if(!empty($mendians)){
                        $order['is_show_mendians'] = 1;
                    }
                }
            }

		}
        elseif($type=='cycle'){
            $order_state = db('cycle_order_stage')->where(['aid'=>aid,'hexiao_code'=>$code])->find();
            if($order_state['bid']!=bid && !$plat_hexiao) return $this->json(['status'=>2,'msg'=>'登录的账号不是该商家的管理账号，是否切换账号？']);
            if(!$order_state) return $this->json(['status'=>0,'msg'=>'订单不存在']);
            if($order_state['status']==0) return $this->json(['status'=>0,'msg'=>'订单未支付']);
            if($order_state['status']==3) return $this->json(['status'=>0,'msg'=>'订单已核销']);
            $order = db('cycle_order')->where(['aid'=>aid,'id'=>$order_state['orderid']])->find();
            $order['prolist'] = [['name'=>$order['proname'],'pic'=>$order['propic'],'ggname'=>$order['ggname'],'sell_price'=>$order['sell_price'],'num'=>$order['num']]];
            $order['stage'] =$order_state;
            if($order['freight_type'] == 1){
                $order['storeinfo'] = Db::name('mendian')->where('id',$order['mdid'])->field('name,address,longitude,latitude')->find();
            }
            $member = Db::name('member')->where('id',$order['mid'])->field('id,nickname,headimg')->find();
            $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            $order['paytime'] = date('Y-m-d H:i:s',$order['paytime']);
            $order['nickname'] = $member['nickname'];
            $order['headimg'] = $member['headimg'];
            
        }
        elseif($type=='choujiang'){
			if(bid!=0 && !$plat_hexiao) return $this->json(['status'=>0,'msg'=>'登录的账号不是该商家的管理账号']);
			$order = Db::name('choujiang_record')->where(['aid'=>aid,'code'=>$code])->find();
			if(!$order) return $this->json(['status'=>0,'msg'=>'奖品不存在']);
			if($order['status']==1) return $this->json(['status'=>0,'msg'=>'奖品已兑换']);
			$order['formdata'] = json_decode($order['formdata'],true);
        }
        elseif($type=='huodong_baoming'){
            if(getcustom('huodong_baoming')){
                if(bid!=0 && !$plat_hexiao) return $this->json(['status'=>0,'msg'=>'登录的账号不是该商家的管理账号']);
                $order = Db::name('huodong_baoming_order')->where(['aid'=>aid,'hexiao_code'=>$code])->find();
                if(!$order) return $this->json(['status'=>0,'msg'=>'活动不存在']);
                if($order['status']==3) return $this->json(['status'=>0,'msg'=>'已核销']);
            }
        }
        elseif($type=='shopproduct'){
            $og = Db::name('shop_order_goods')->where('aid',aid)->where('hexiao_code',$code)->find();
            if(!$og) return $this->json(['status'=>0,'msg'=>'核销码已失效']);

            $order = Db::name('shop_order')->where('aid',aid)->where('id',$og['orderid'])->find();
            if(!$order) return $this->json(['status'=>0,'msg'=>'订单已删除']);

            if(!empty($og['mdids'])){
                $mdids = explode(',',$og['mdids']);
                if ($this->user['mdid'] != 0 && !in_array($this->user['mdid'] ,$mdids) && !in_array('-1' ,$mdids) && !$plat_hexiao) {
                    return $this->json(['status' => 0, 'msg' => '您没有该门店核销权限']);
                }
            }
            if($order['bid']!=bid){
                $freight = Db::name('freight')->where('aid',aid)->where('id',$order['freight_id'])->find();
                if($freight['hxbids'] && !in_array(bid,explode(',',$freight['hxbids']))){
                    return $this->json(['status'=>0,'msg'=>'登录的账号不能核销平台商品']);
                }
            }
            $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            $order['paytime'] = date('Y-m-d H:i:s',$order['paytime']);

            $order['ogdata'] = $og;
            $order['hxnum'] = input('param.hxnum');
            $is_quanyi = 0;//是否权益商品
            if(getcustom('product_quanyi') && $og['product_type']==8){
                $is_quanyi = 1;
            }
            if($is_quanyi){
                //权益核销处理
                $quanyi_res = \app\common\Order::quanyihexiao($og['id'],1,input('param.hxnum'));
                if(!$quanyi_res['status']){
                    return $this->json($quanyi_res);
                }
            }else{
                if($order['hxnum'] > $og['num'] - $og['hexiao_num']) return $this->json(['status'=>0,'msg'=>'可核销数量不足']);
            }

        }
        elseif($type=='takeaway_order_product'){
            $og = Db::name('restaurant_takeaway_order_goods')->where('aid',aid)->where('hexiao_code',$code)->find();
            if(!$og) return $this->json(['status'=>0,'msg'=>'核销码已失效']);

            $set = Db::name('restaurant_takeaway_sysset')->where('aid',aid)->where('bid',$og['bid'])->field('alone_hexiao_status')->find();
            if(!$set || !$set['alone_hexiao_status']){
                return $this->json(['status'=>0,'msg'=>'商家未开启外卖菜品单独核销功能']);
            }

            $order = Db::name('restaurant_takeaway_order')->where('aid',aid)->where('id',$og['orderid'])->find();
            if(!$order) return $this->json(['status'=>0,'msg'=>'订单已删除']);

            if($order['bid']!=bid){
                $freight = Db::name('freight')->where('aid',aid)->where('id',$order['freight_id'])->find();
                if($freight['hxbids'] && !in_array(bid,explode(',',$freight['hxbids']))){
                    return $this->json(['status'=>0,'msg'=>'登录的账号不能核销平台商品']);
                }
            }
            $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            $order['paytime'] = date('Y-m-d H:i:s',$order['paytime']);

            $order['ogdata']    = $og;
            $order['hxnum']     = $og['num'];
            $order['now_hxnum'] = $og['num']-$order['hexiao_num'];

        }
        elseif($type=='business_miandan'){
            if(getcustom('yx_business_miandan')){
                $order = Db::name('business_miandan_order')->where('aid',aid)->where('bid',bid)->where('hexiao_code',$code)->find();
                if(!$order) return $this->json(['status'=>0,'msg'=>'核销码已失效']);
                if($order['status']==1) return $this->json(['status'=>0,'msg'=>'活动已核销']);

                if($order['bid']!=bid){
                    return $this->json(['status'=>0,'msg'=>'登录的账号不能核销平台商品']);
                }
            }

        }
        elseif($type=='hbtk'){
            if(!$plat_hexiao && !getcustom('yx_hbtk')) return $this->json(['status'=>0,'msg'=>'登录的账号不是该商家的管理账号']);
            $order = Db::name('hbtk_order')->where(['aid'=>aid,'code'=>$code])->find();
            if(!$order) return $this->json(['status'=>0,'msg'=>'活动不存在']);
            if($order['status']==2) return $this->json(['status'=>0,'msg'=>'活动已核销']);
            //邀请人数
            $order['yqnum'] = 0 + Db::name('hbtk_order')->where('aid',aid)->where('pid',$order['mid'])->where('hid',$order['hid'])->count();
            $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            if($order['paytime']){
                $order['paytime'] = date('Y-m-d H:i:s',$order['paytime']);
            }
            $hd = Db::name('hbtk_activity')->where('id',$order['hid'])->find();
            $order['pic'] = $hd['fmpic'];
            $yqlist = Db::name('hbtk_order')->where('aid',aid)->where('hid',$order['hid'])->where('pid',$order['mid'])->where('status','in',[1,2])->select()->toArray();
            $order['yqlist'] = $yqlist?$yqlist:[];
        }
        elseif($type=='gift_bag_goods'){
        	if(getcustom('extend_gift_bag')){

                $og = Db::name('gift_bag_order_goods')->where('aid',aid)->where('hexiao_code',$code)->find();
                if(!$og || $og['status'] ==3) return $this->json(['status'=>0,'msg'=>'核销码已失效']);

                $order = Db::name('gift_bag_order')->where('aid',aid)->where('id',$og['orderid'])->find();
                if(!$order) return $this->json(['status'=>0,'msg'=>'订单已删除']);

                $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
                $order['paytime'] = date('Y-m-d H:i:s',$order['paytime']);

                $order['ogdata'] = $og;
                $order['hxnum']  = input('param.hxnum');

                if($order['hxnum'] > $og['num'] - $og['hexiao_num']) return $this->json(['status'=>0,'msg'=>'可核销数量不足']);
            }
        }
        elseif($type=='verifyauth'){
            if(getcustom('restaurant_cashdesk_auth_enter')) {
                //权限验证
                $log = Db::name('restaurant_verify_auth_log')->where('aid', aid)->where('code', $code)->find();
                if (!$log) {
                    return $this->json(['status' => 0, 'msg' => '二维码已失效']);
                }
                if ($log['expiretime'] <= time()) {
                    return $this->json(['status' => 0, 'msg' => '二维码已过期']);
                }
                if ($log['status'] !=0) {
                    return $this->json(['status' => 0, 'msg' => '二维码已失效']);
                }
                if($this->user['auth_type']==0){
                    if($this->user['groupid']){
                        $this->user['auth_data'] = Db::name('admin_user_group')->where('id',$this->user['groupid'])->value('auth_data');
                    }
                    $auth_data = json_decode($this->user['auth_data'],true);
                    $auth_path = [];
                    foreach($auth_data as $v) {
                        $auth_path = array_merge($auth_path,explode(',',$v));
                    }
                }else{
                    $auth_path ='all';
                }
                $is_error = 0;
                if($log['type']=='refund'){
                    if(!in_array('RestaurantCashdesk/refund',$auth_path) && $auth_path !='all'){
                        $is_error = 1;
                    }
                }elseif($log['type']=='cancel'){
                    if(!in_array('RestaurantCashdesk/cancel',$auth_path)  && $auth_path !='all'){
                        $is_error = 1;
                    }
                }elseif ($log['type']=='discount'){
                    if(!in_array('RestaurantCashdesk/discount',$auth_path)  && $auth_path !='all'){
                        $is_error = 1;
                    }
                }else{
                    $is_error = 1;
                }
                if($is_error){                                                      
                    Db::name('restaurant_verify_auth_log')->where('code',$code)->update(['status' => 2]);
                    return json(['status'=>0,'msg'=>'您没有权限']);
                }
                $order = [];
                $cashdesk_un = Db::name('admin_user')->where('id', $log['uid'])->value('un');
                $type_arr = ['refund' => '退款', 'cancel' => '取消订单', 'discount' => '直接优惠'];
                $title = '收银员' . $cashdesk_un . '正在操作' . $type_arr[$log['type']] . '，需要' . $type_arr[$log['type']] . '权限';
                $order['title'] = $title;
                $order['tip'] = $type_arr[$log['type']].'授权';
            }
        }
        elseif($type=='hotel'){
			$order = db($type.'_order')->where(['aid'=>aid,'hexiao_code'=>$code])->find();
			if(!$order) return $this->json(['status'=>0,'msg'=>'订单不存在']);
			if($order['status']==0) return $this->json(['status'=>0,'msg'=>'订单未支付']);
			if($order['status']==3) return $this->json(['status'=>0,'msg'=>'订单已核销']);
			if($order['status']==-1) return $this->json(['status'=>0,'msg'=>'订单已关闭']);
			$member = Db::name('member')->where('id',$order['mid'])->field('id,nickname,headimg')->find();
			$order['nickname'] = $member['nickname'];
			$order['headimg'] = $member['headimg'];
            if($order['createtime']){
                $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            }
            if($order['paytime']){
                $order['paytime'] = date('Y-m-d H:i:s',$order['paytime']);
            }
        }
        elseif($type=='form'){
            $order = db($type.'_order')->where(['aid'=>aid,'hexiao_code'=>$code])->find();
            if(!$order) return $this->json(['status'=>0,'msg'=>'数据不存在']);
            if($order['status']==2) return $this->json(['status'=>0,'msg'=>'订单已驳回']);
            if($order['hexiao_status'] == 1) return $this->json(['status'=>0,'msg'=>'核销码已失效']);
            $form = Db::name('form')->where('aid',aid)->where('id',$order['formid'])->find();
            if(!$form) return $this->json(['status'=>0,'msg'=>'表单不存在']);
            if($form['payset'] == 1){
                if($order['paystatus']==0) return $this->json(['status'=>0,'msg'=>'订单未支付']);
            }
            $formcontent = json_decode($form['content'],true);
            $linkitemArr = [];
            foreach($formcontent as $k=>$v){
                if(($v['key'] == 'radio' || $v['key'] == 'selector') && $order['form'.$k]!==''){
                    $linkitemArr[] = $v['val1'].'|'.$form['form'.$k];
                }
            }
            foreach($formcontent as $k=>$v){
                if($v['linkitem'] && !in_array($v['linkitem'],$linkitemArr)){
                    $formcontent[$k]['hidden'] = true;
                }
                if(!getcustom('form_map')){
                    $formcontent[$k]['val12'] = 1;
                }
                if($v['key'] == 'upload_pics'){
                    $pics = $form['form'.$k];
                    if($pics){
                        $form['form'.$k] = explode(",",$pics);
                    }
                }
            }
            $order['formdata'] = $formcontent;

            $member = Db::name('member')->where('id',$order['mid'])->field('id,nickname,headimg')->find();
            $order['nickname'] = $member['nickname'];
            $order['headimg'] = $member['headimg'];
            if($order['createtime']){
                $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            }
            if($order['paytime']){
                $order['paytime'] = date('Y-m-d H:i:s',$order['paytime']);
            }
		}
        else{
			$order = db($type.'_order')->where(['aid'=>aid,'hexiao_code'=>$code])->find();

			if($order['bid']!=bid && !$plat_hexiao){
				if(getcustom('freight_selecthxbids') && $order['freight_id']){
					$freight = Db::name('freight')->where('aid',aid)->where('id',$order['freight_id'])->find();
					if($freight['hxbids'] && !in_array(bid,explode(',',$freight['hxbids']))){
						return $this->json(['status'=>2,'msg'=>'登录的账号不能核销平台商品']);
					}
				}else{
					return $this->json(['status'=>2,'msg'=>'登录的账号不是该商家的管理账号，是否切换账号？']);
				}
            }

			//if($order['bid']!=bid && !$plat_hexiao && !getcustom('mendian_hexiao_givemoney')) return $this->json(['status'=>2,'msg'=>'登录的账号不是该商家的管理账号，是否切换账号？']);
			if(!$order) return $this->json(['status'=>0,'msg'=>'订单不存在']);
			if($order['status']==0) return $this->json(['status'=>0,'msg'=>'订单未支付']);
			if($order['status']==3) return $this->json(['status'=>0,'msg'=>'订单已核销']);
			if($order['status']==4) return $this->json(['status'=>0,'msg'=>'订单已关闭']);
			if(\app\common\Order::hasOrderGoodsTable($type)){
				if($type=='gift_bag'){
					$prolist = Db::name($type.'_order_goods')->where('orderid',$order['id'])->where('status','between',[1,2])->select()->toArray();
				}else{
					$prolist = Db::name($type.'_order_goods')->where(['orderid'=>$order['id']])->select()->toArray();
				}
                if(getcustom('goods_hexiao')){
                    if($type=='restaurant_takeaway'){
                        //处理已核销的
                        foreach($prolist as $pk=>$pv){
                            if($pv['status']==3 || $pv['hexiao_num']>0){
                                unset($prolist[$pk]);
                            }
                        }
                        unset($pk);
                    }
                }
                $order['prolist'] = $prolist;
			}elseif($type=='lucky_collage' ){
				$order['prolist'] = [['name'=>$order['proname'],'pic'=>$order['propic'],'ggname'=>$order['ggname'],'sell_price'=>$order['sell_price'],'num'=>$order['num']]];
			}else{
				$order['prolist'] = [['name'=>$order['proname'],'pic'=>$order['propic'],'ggname'=>$order['ggname'],'sell_price'=>$order['sell_price'],'num'=>$order['num']]];
			}
			if($order['freight_type'] == 1){
				$order['storeinfo'] = Db::name('mendian')->where('id',$order['mdid'])->field('name,address,longitude,latitude')->find();
				if(!$order['storeinfo']) $order['storeinfo'] = [];
			}
			$member = Db::name('member')->where('id',$order['mid'])->field('id,nickname,headimg')->find();
			$order['nickname'] = $member['nickname'];
			$order['headimg'] = $member['headimg'];
            if($order['createtime']){
                $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            }
            if($order['paytime']){
                $order['paytime'] = date('Y-m-d H:i:s',$order['paytime']);
            }
		}
        //权限注意判断，有的类型没在后台权限管理里面
        if($this->user['isadmin']==0 && !getcustom('freight_selecthxbids')){
            $auth_data = json_decode($this->user['hexiao_auth_data'],true);
            $auth_type = $type=='shopproduct'?'shop':$type;
            if(!in_array($auth_type,$auth_data) && $type != 'takeaway_order_product'){
                return $this->json(['status'=>0,'msg'=>'您没有核销权限']);
            }elseif(!in_array('restaurant_takeaway',$auth_data) && $type == 'takeaway_order_product'){
                return $this->json(['status'=>0,'msg'=>'您没有核销权限']);
            }
            if($auth_type=='shop' || $auth_type=='collage' || $auth_type=='kanjia' || $auth_type=='scoreshop' || $auth_type=='cycle'){
                if(!$mendian_no_select) {
                    if ($this->user['mdid'] != 0 && $this->user['mdid'] != $order['mdid'] && !in_array('-1' ,$mdids) && !$plat_hexiao) {
                        return $this->json(['status' => 0, 'msg' => '您没有该门店核销权限']);
                    }
                }
            }
        }
        if($mendian_no_select && $type=='shop'){
            $prolist = Db::name($type.'_order_goods')->where(['orderid'=>$order['id']])->select()->toArray();
            $have_hx_proids = Db::name('hexiao_order')->where('orderid',$order['id'])->column('proids');
            $have_hx_proids = array_unique(explode(',',implode(',',$have_hx_proids)));
            foreach($prolist as $k=>$pro){
                $bind_mendian_ids = Db::name('shop_product')->where('id',$pro['proid'])->value('bind_mendian_ids');
                $bind_mendian_ids = explode(',',$bind_mendian_ids);
                if($pro['bind_mendian_ids']!='-1' && $this->user['mdid'] != 0 && !in_array($this->user['mdid'],$bind_mendian_ids) && !in_array('-1',$bind_mendian_ids)){
                    unset($prolist[$k]);
                }
                if(in_array($pro['proid'],$have_hx_proids)){
                    unset($prolist[$k]);
                }
            }
            $prolist = array_values($prolist);

            if(empty($prolist)){
                return $this->json(['status'=>0,'msg'=>'暂无待核销产品']);
            }
        }

        //核销
		if(input('post.op') == 'confirm'){
		    Db::startTrans();
            $order['createtime'] = strtotime($order['createtime']);
            $order['paytime']    = strtotime($order['paytime']);
            $typeArr = ['shop','collage','lucky_collage','kanjia','scoreshop','seckill','yueke','restaurant_shop','restaurant_takeaway','tuangou','gift_bag'];
			if(in_array($type,$typeArr)){
                $is_quanyi = 0;//是否权益商品
                if(getcustom('product_quanyi') && $type=='shop' && $order['product_type']==8){
                    $is_quanyi = 1;
                }

                $data = array();
				$data['aid'] = aid;
				$data['bid'] = bid;
				$data['uid'] = $this->uid;
				$data['mid'] = $order['mid'];
				$data['orderid']  = $order['id'];
				$data['ordernum'] = $order['ordernum'];
				$data['title']    = $order['title'];
                if($mendian_no_select && $type=='shop'){
                    $pro_ids = Db::name('shop_order_goods')->where('orderid',$order['id'])->where('is_hx','=',0)->column('proid');
                    if($this->user['mdid']!=0){
                        $pro_ids = Db::name('shop_product')->where('id','in',$pro_ids)->where('find_in_set('.$this->user['mdid'].',bind_mendian_ids) or find_in_set("-1",bind_mendian_ids)')->column('id');
                    }
                    $data['proids'] = implode(',',$pro_ids);
                    $title_arr = Db::name('shop_product')->where('id','in',$pro_ids)->column('name');
                    $data['title'] = implode(',',$title_arr);
                }
                if(getcustom('hexiao_search_mendian_product')){
                    //核销商品
                    $pro_ids = Db::name('shop_order_goods')->where('orderid',$order['id'])->where('is_hx','=',0)->column('proid');
                    $title_arr = Db::name('shop_product')->where('id','in',$pro_ids)->column('name');
                    $data['title'] = implode(',',$title_arr);
                }
				$data['type']     = $type;
				$data['createtime'] = time();
				$data['remark'] = '核销员['.$this->user['un'].']核销';
				if(getcustom('business_platform_auth')){
					$mdid = $this->user['mdid'];
					if(!$mdid){
						$mendian = Db::name('mendian')->where('aid',aid)->where('bid',bid)->order('id asc')->find();
						$mdid = $mendian['id'];
					}
					$data['mdid'] = $mdid;
				}else{
					$data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
				}
                Db::name('hexiao_order')->insert($data);
				$remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];
                $is_confirm = 1;
                if($mendian_no_select && $type=='shop'){
                    //不需要选择门店的，多产品可能会在不同门店核销，全部核销完成之后再收货
                    $pro_count = Db::name('shop_order_goods')->where('orderid',$order['id'])->where('refund_num',0)->count();
                    $hx_pro = Db::name('hexiao_order')->where('orderid',$order['id'])->column('proids');
                    $hx_pro = array_unique(explode(',',implode(',',$hx_pro)));
                    if($pro_count>count($hx_pro)){
                        $is_confirm = 0;
                    }
                    $hx_mdids = $order['hx_mdids'].','.$this->mendian['id'];
                    $hx_mdids = ltrim($hx_mdids,',');
                    Db::name('shop_order')->where('id',$order['id'])->update(['hx_mdids'=>$hx_mdids]);
                    Db::name('shop_order_goods')->where('orderid',$order['id'])->where('proid','in',$data['proids'])->update(['is_hx'=>1,'hx_mdid'=>$this->mendian['id']]);
                }
                if($is_quanyi==1){
                    //权益核销处理
                    $quanyi_res = \app\common\Order::quanyihexiao($order['id']);
                    if(!$quanyi_res['status']){
                        return $this->json($quanyi_res);
                    }else{
                        //权益商品全部核销完成才收货
                        $is_confirm = $quanyi_res['is_collect'];
                    }
                    //发货信息录入 微信小程序+微信支付
                    if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                        \app\common\Order::wxShipping(aid,$order,$type);
                    }
                }
                if($is_confirm==1){
                    //发货信息录入 微信小程序+微信支付
                    if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                        \app\common\Order::wxShipping(aid,$order,$type);
                    }

                    $rs = \app\common\Order::collect($order,$type, $this->user['mid']);
                    if($rs['status']==0) return $this->json($rs);

                    if($type == 'restaurant_shop'){
                        $rs = \app\custom\Restaurant::shop_orderconfirm($order['id']);
                        if($rs['status']==0){
                            return $this->json($rs);
                        }
                    }else if($type == 'restaurant_takeaway'){
                        $rs = \app\custom\Restaurant::takeaway_orderconfirm($order['id']);
                        if($rs['status']==0){
                            return $this->json($rs);
                        }
                    }else if($type == 'gift_bag'){
                        if(getcustom('extend_gift_bag')){
                            $gbdata = [];
                            $gbdata['status']       = 3;
                            $gbdata['remark']       = $remark;
                            $gbdata['hexiao_uid']   = $data['uid'];
                            $gbdata['hexiao_mid']   = $data['mid'];
                            $gbdata['hexiao_mdid']  = $data['mdid'];

                            $orderdata = [];
                            $orderdata = $gbdata;
                            $orderdata['collect_time'] = time();
                            //更新订单状态
                            Db::name('gift_bag_order')->where(['aid'=>aid,'hexiao_code'=>$code])->update($orderdata);

                            $goodsdata = [];
                            $goodsdata = $gbdata;
                            $goodsdata['endtime']      = time();
                            $goodsdata['hexiao_code']  = '';
                            $goodsdata['hexiao_num']   = 1;
                            //更新订单产品状态
                            Db::name('gift_bag_order_goods')->where('aid',aid)->where('orderid',$order['id'])->where('status','between',[1,2])->update($goodsdata);
                        }
                    }else{
                        db($type.'_order')->where(['aid'=>aid,'hexiao_code'=>$code])->update(['status'=>3,'collect_time'=>time(),'remark'=>$remark]);
                        if(in_array($type,['scoreshop'])){
                            Db::name($type.'_order_goods')->where(['aid'=>aid,'orderid'=>$order['id']])->update(['status'=>3,'endtime'=>time()]);
                        }
                    }
                    if($type == 'shop'){
                        Db::name('shop_order_goods')->where(['aid'=>aid,'orderid'=>$order['id']])->update(['status'=>3,'endtime'=>time()]);
                        if(getcustom('ciruikang_fenxiao')){
                            //一次购买升级
                            \app\common\Member::uplv(aid,$order['mid'],'shop',['onebuy'=>1,'onebuy_orderid'=>$order['id']]);
                        }else{
                            \app\common\Member::uplv(aid,$order['mid']);
                        }
                        if(getcustom('member_shougou_parentreward')){
                            //首购解锁
                            Db::name('member_commission_record')->where('orderid',$order['id'])->where('type','shop')->where('status',0)->where('islock',1)->where('aid',$order['aid'])->where('remark','like','%首购奖励')->update(['islock'=>0]);
                        }
                        if(getcustom('shop_zthx_backmoney')){
                            if($order['freight_type'] == 1){
                                $levelid = Db::name('member')->where('id',$order['mid'])->value('levelid');
                                if(!empty($levelid) && $levelid>0){
                                    //自提核销返现
                                    $zthx_backmoney = Db::name('member_level')->where('id',$levelid)->where('aid',aid)->value('zthx_backmoney');
                                    if($zthx_backmoney && $zthx_backmoney>0){
                                        $back_money = 0;
                                        if($order['prolist']){
                                            foreach($order['prolist'] as $pv){
                                                $back_money += $pv['market_price']*$pv['num'];
                                            }
                                            unset($pv);
                                        }

                                        $back_money = $back_money*$zthx_backmoney/100;
                                        $back_money = round($back_money,2);
                                        if($back_money>0){
                                            \app\common\Member::addmoney(aid,$order['mid'],$back_money,'自提核销返回，订单号: '.$order['ordernum']);
                                        }
                                    }
                                }
                            }
                        }

                        // 分销商开启门店核销后，对应核销的商品金额自动换算成对应积分赠送到核销管理员
                        if(getcustom('mendian_hexiao_money_to_score')){
                            \app\common\Order::mendian_hexiao_money_to_score(aid,$this->user['mid'],$order['totalprice']);
                        }
                    }

                    // 核销送积分
                    if(getcustom('mendian_hexiao_give_score') && $order['mdid']){
                        $mendian = Db::name('mendian')->where('aid',aid)->where('id',$order['mdid'])->find();
                        if($mendian && $type == 'shop'){
                            $givescore = 0;
                            $oglist = Db::name('shop_order_goods')->where(['aid'=>aid,'orderid'=>$order['id']])->select()->toArray();
                            if($oglist){
                                foreach ($oglist as $og){
                                    $pro = Db::name('shop_product')->where('aid',aid)->where('id',$og['proid'])->find();
                                    if(!is_null($pro['hexiao_give_score_bili'])){
                                        $givescore += $pro['hexiao_give_score_bili'] * 0.01 * $og['real_totalprice'];
                                    }else{
                                        $givescore += $mendian['hexiao_give_score_bili'] * 0.01 * $og['real_totalprice'];
                                    }
                                }
                            }
                            $givescore = floor($givescore);
                            if($givescore > 0){
                                \app\common\Member::addscore(aid,$this->user['mid'],$givescore,'核销订单'.$order['ordernum']);
                            }
                        }
                    }

                    if(getcustom('mendian_hexiao_givemoney') && $order['mdid']){
                        $mendian = Db::name('mendian')->where('aid',aid)->where('id',$order['mdid'])->find();
                        if($mendian){
                            $givemoney = 0;
                            $commission_to_money = 0;
                            if($type == 'shop'){
                                $oglist = Db::name('shop_order_goods')->where(['aid'=>aid,'orderid'=>$order['id']])->select()->toArray();
                                if($oglist){
                                    if(getcustom('product_mendian_hexiao_givemoney')){
                                        foreach ($oglist as $og){
                                            $pro = Db::name('shop_product')->where('aid',aid)->where('id',$og['proid'])->find();
                                            $hexiao_set = Db::name('shop_product_mendian_hexiaoset')->where('aid',aid)->where('mdid',$order['mdid'])->where('proid',$og['proid'])->find();
                                            if($hexiao_set['hexiaogivepercent']>0 || $hexiao_set['hexiaogivemoney']>0){
                                                $givemoney += $hexiao_set['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $hexiao_set['hexiaogivemoney'];
                                            }
                                            elseif(!is_null($pro['hexiaogivepercent']) || !is_null($pro['hexiaogivemoney'])){

                                                $givemoney += $pro['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $pro['hexiaogivemoney'];
                                            }else{
                                                $givemoney += $mendian['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $mendian['hexiaogivemoney'];
                                            }
                                        }
                                    }else{
                                        foreach ($oglist as $og){
                                            $pro = Db::name('shop_product')->where('aid',aid)->where('id',$og['proid'])->find();
                                            if(!is_null($pro['hexiaogivepercent']) || !is_null($pro['hexiaogivemoney'])){
                                                $givemoney += $pro['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $pro['hexiaogivemoney']*$og['num'];
                                                if(getcustom('mendian_hexiao_commission_to_money') && $pro['commission_to_money']){
                                                    $commission_to_money += $pro['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $pro['hexiaogivemoney']*$og['num'];
                                                }
                                            }else{
                                                $givemoney += $mendian['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $mendian['hexiaogivemoney'];
                                                if(getcustom('mendian_hexiao_commission_to_money') && $mendian['commission_to_money']){
                                                    $commission_to_money += $mendian['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $mendian['hexiaogivemoney'];
                                                }
                                            }
                                        }
                                    }
                                }
                            }elseif(($mendian['hexiaogivepercent'] || $mendian['hexiaogivemoney'])){
                                $givemoney = $mendian['hexiaogivepercent'] * 0.01 * $order['totalprice'] + $mendian['hexiaogivemoney'];
                            }
                            if($givemoney > 0){

                                // 分润
                                if(getcustom('commission_mendian_hexiao_coupon') && !empty($mendian['fenrun'])){
                                    $fenrun = json_decode($mendian['fenrun'],true);
                                    $givemoney_old = $givemoney;
                
                                    // {"bili":["10","10","20","20","10"],"mids":["3980,3996,4000","3980,3995","","3993,3995,4001","3992,3999"]}
                                    $data_bonus = [];
                                    foreach ($fenrun['bili'] as $key => $bili) {
                                        if($bili > 0 && !empty($fenrun['mids'][$key])){

                                            $send_commission_total = dd_money_format($bili*$givemoney_old/100,2);

                                            $givemoney -= $send_commission_total;
                                            $mids = $fenrun['mids'][$key];
                                            $mids = explode(',', $mids);
                                            $mnum = count($mids);
                                            $send_commission = dd_money_format($send_commission_total/$mnum,2);
                        
                                            if($send_commission <= 0) continue;
                                            foreach ($mids as $k => $mid) {
                                                $data_shop_bonus = ['aid'=>aid,'bid'=>bid,'mid'=>$mid,'frommid'=>$order['mid'],'orderid'=>$order['id'],'totalcommission'=>$send_commission_total,'commission'=>$send_commission,'bili'=>$bili,'createtime'=>time()];
                                                $data_bonus[] = $data_shop_bonus;
                                            }
                                        }
                                    }
                                    if(!empty($data_bonus)){
                                        Db::name('mendian_coupon_commission_log')->insertAll($data_bonus);
                                    }
                                }
                                if(getcustom('mendian_hexiao_commission_to_money') && $commission_to_money > 0){
                                    \app\common\Member::addmoney(aid,$this->user['mid'],$commission_to_money,'核销订单'.$order['ordernum']);
                                    $givemoney -= $commission_to_money;
                                }
                                if($givemoney > 0){
                                    \app\common\Mendian::addmoney(aid,$mendian['id'],$givemoney,'核销订单'.$order['ordernum']);
                                }
                            }
                            if(getcustom('business_platform_auth')){
                                if($mendian['bid']>0 && $order['bid']!=$mendian['bid']){
                                    $business = Db::name('business')->where('aid',aid)->where('id',$mendian['bid'])->find();
                                    if($business['isplatform_auth']==1){
                                        \app\common\Business::addmoney(aid,$mendian['bid'],$givemoney,$mendian['name'].'核销平台商品 订单号：'.$order['ordernum']);
                                    }
                                }
                            }
                        }
                    }
                }
			}
            elseif($type=='coupon'){
			    //如果是计次优惠券，则需要判断使用间隔
                if(getcustom('coupon_times_expire') || getcustom('coupon_times_use_gap')){
                    $cheRes = $this->couponTimesCheck(aid,bid,$order);
                    if($cheRes['status']!=1){
                        return $this->json(['status'=>0,'msg'=>$cheRes['msg']]);
                    }
                }
				$data = array();
				$data['aid'] = aid;
				$data['bid'] = bid;
				$data['uid'] = $this->uid;
				$data['mid'] = $order['mid'];
				$data['orderid'] = $order['id'];
				$data['ordernum'] = date('ymdHis').aid.rand(1000,9999);
				$data['title'] = $order['couponname'];
				$data['type'] = $type;
				$data['createtime'] = time();
				$data['remark'] = '核销员['.$this->user['un'].']核销';
                if(getcustom('coupon_view_range')){
                    $hxnum = input('param.hxnum',1);
                    $hxnum =   $hxnum?$hxnum:1;
                    $data['remark'] =  $data['remark'].'，次数：'.$hxnum.'次';
                }
				$data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
                Db::name('hexiao_order')->insert($data);
				$remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];
				if($order['type']==3){//计次券
				    $hxnum = 1;
				    if(getcustom('coupon_view_range')){
                        $hxnum = input('param.hxnum',1);
                        $hxnum =   $hxnum?$hxnum:1;
                        $synum = $order['limit_count'] - $order['used_count'];
                        if($hxnum > $synum ){
                            $this->json(['status'=>0,'msg'=>'剩余核销次数不足']);
                        } 
                    }
					Db::name($type.'_record')->where(['aid'=>aid,'code'=>$code])->inc('used_count',$hxnum)->update();
					if($order['used_count']+$hxnum>=$order['limit_count']){
						Db::name($type.'_record')->where(['aid'=>aid,'code'=>$code])->update(['status'=>1,'usetime'=>time(),'remark'=>$remark]);
					}
                    // 据计次券设置的价格，门店可以核销计次券，每核销一次，计次券减少次数，核销的门店对应得到核销奖励
                    if(getcustom('mendian_hexiao_coupon_reward')){

                        if($this->user['mdid']){
                            $mdid = $this->user['mdid'];
                        }else{
                            $mdid = input('param.mdid',0);
                        }

                        $givemoney = 0;
                        $coupon = Db::name('coupon')->where('aid',aid)->where('id',$order['couponid'])->find();
                        $mendian = Db::name('mendian')->where('aid',aid)->where('id',$mdid)->find();

                        if($coupon['hexiaogivepercent']>0 || $coupon['hexiaogivemoney']>0){
                            $givemoney = dd_money_format($coupon['hexiaogivepercent'] * 0.01 * $coupon['price'] + $coupon['hexiaogivemoney'],2);
                        }else{
                            if(!empty($mendian) && getcustom('mendian_hexiao_givemoney')){
                                if($mendian['hexiaogivepercent']>0 || $mendian['hexiaogivemoney']>0){
                                    $givemoney = dd_money_format($mendian['hexiaogivepercent'] * 0.01 * $coupon['price'] + $mendian['hexiaogivemoney'],2);
                                }
                            }
                        }
                        if($givemoney > 0 && !empty($mendian)){
                            $givemoney_old = $givemoney;
                            // 分润
                            if(getcustom('commission_mendian_hexiao_coupon') && !empty($mendian['fenrun'])){
                                $fenrun = json_decode($mendian['fenrun'],true);
            
                                // {"bili":["10","10","20","20","10"],"mids":["3980,3996,4000","3980,3995","","3993,3995,4001","3992,3999"]}
                                $data_bonus = [];
                                foreach ($fenrun['bili'] as $key => $bili) {
                                    if($bili > 0 && !empty($fenrun['mids'][$key])){
                                        $send_commission_total = dd_money_format($bili*$givemoney_old/100,2);
                                        $givemoney -= $send_commission_total;
                                        $mids = $fenrun['mids'][$key];
                                        $mids = explode(',', $mids);
                                        $mnum = count($mids);
                                        $send_commission = dd_money_format($send_commission_total/$mnum,2);
                    
                                        if($send_commission <= 0) continue;
                                        foreach ($mids as $k => $mid) {
                                            $data_shop_bonus = ['aid'=>aid,'bid'=>bid,'mid'=>$mid,'frommid'=>$order['mid'],'orderid'=>$order['id'],'totalcommission'=>$send_commission_total,'commission'=>$send_commission,'bili'=>$bili,'createtime'=>time()];
                                            $data_bonus[] = $data_shop_bonus;
                                        }
                                    }
                                }
                                if(!empty($data_bonus)){
                                    Db::name('mendian_coupon_commission_log')->insertAll($data_bonus);
                                }
                            }
                            if($givemoney > 0){
                                \app\common\Mendian::addmoney(aid,$mendian['id'],$givemoney,'核销计次券 '.$order['id']);
                            }
                        }
                    }

                    // 发送消息模板
                    if(getcustom('coupon_cika_wxtmpl')){
                        //订阅消息
                        $tmplcontent = [];
  
                        $tmplcontent_new = [];
                        $tmplcontent_new['number12'] = $order['limit_count'] - $order['used_count'] -$hxnum;
                        $tmplcontent_new['thing10'] = $order['couponname'];
                        $tmplcontent_new['number9'] = $hxnum;

                        $rs = \app\common\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_couponhexiao',$tmplcontent,'pages/my/usercenter',$tmplcontent_new);
                    }
				}else{
					Db::name($type.'_record')->where(['aid'=>aid,'code'=>$code])->update(['status'=>1,'usetime'=>time(),'remark'=>$remark]);
                    $record = Db::name($type.'_record')->where(['aid'=>aid,'code'=>$code])->find();
                    \app\common\Coupon::useCoupon(aid,$record['id'],'hexiao');
				}
				\app\common\Wechat::updatemembercard(aid,$order['mid']);
			}
            elseif($type=='cycle'){
                $data = array();
                $data['aid'] = aid;
                $data['bid'] = bid;
                $data['uid'] = $this->uid;
                $data['mid'] = $order['mid'];
                $data['orderid'] = $order_state['id'];
                $data['ordernum'] = $order_state['ordernum'];
                $data['title'] = $order['title'];
                $data['type'] = $type;
                $data['createtime'] = time();
                $data['remark'] = '核销员['.$this->user['un'].']核销';
                $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
                Db::name('hexiao_order')->insert($data);
                $remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];

                db($type.'_order_stage')->where(['aid'=>aid,'hexiao_code'=>$code])->update(['status'=>3,'collect_time'=>time(),'remark'=>$remark]);
                $order_stage_count = Db::name('cycle_order_stage')
                    ->where('status','in','0,1,2')
                    ->where('orderid',$order['id'])
                    ->count();
                if($order_stage_count == 0){
                    Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['id'])->update(['status'=>3,'collect_time'=>time()]);
                    $rs = \app\common\Order::collect($order,$type, $this->user['mid']);
                    if($rs['status']==0) return $this->json($rs);
                }else{
                    Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['id'])->update(['status'=>2]);
                }
            }
            elseif($type=='choujiang'){
				$data = array();
				$data['aid'] = aid;
				$data['bid'] = bid;
				$data['uid'] = $this->uid;
				$data['mid'] = $order['mid'];
				$data['orderid'] = $order['id'];
				$data['ordernum'] = date('ymdHis').aid.rand(1000,9999);
				$data['title'] = $order['jxmc'];
				$data['type'] = $type;
				$data['createtime'] = time();
				$data['remark'] = '核销员['.$this->user['un'].']核销';
				if(getcustom('business_platform_auth')){
					$mdid = $this->user['mdid'];
					if(!$mdid){
						$mendian = Db::name('mendian')->where('aid',aid)->where('bid',bid)->order('id asc')->find();
						$mdid = $mendian['id'];
					}
					$data['mdid'] = $mdid;
				}else{
					$data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
				}
                Db::name('hexiao_order')->insert($data);
				$remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];
				Db::name($type.'_record')->where(['aid'=>aid,'code'=>$code])->update(['status'=>1,'remark'=>$remark]);
				//查看是否有设置核销奖励
				if(getcustom('choujiang_hexiao_reward')){
					$record = Db::name($type.'_record')->where(['aid'=>aid,'code'=>$code])->find();
					$choujiang = Db::name('choujiang')->field('hexiao_reward_st,hexiaogivemoney,hexiaogivescore')->where(['id'=>$record['hid']])->find();
					if($choujiang['hexiao_reward_st']==1){
						$hxmember = Db::name('member')->field('id')->where('id',$this->user['mid'])->find();
						if($hxmember){
							if($choujiang['hexiaogivemoney']>0){
								$rs = \app\common\Member::addmoney(aid,$hxmember['id'],$choujiang['hexiaogivemoney'],'核销奖励');
							}
							if($choujiang['hexiaogivescore']>0){
								$rs = \app\common\Member::addscore(aid,$hxmember['id'],$choujiang['hexiaogivescore'],'核销奖励');
							}
						}
					}
				}
			
			}
            elseif($type=='huodong_baoming'){
                if(getcustom('huodong_baoming')){
                    $data = array();
                    $data['aid'] = aid;
                    $data['bid'] = bid;
                    $data['uid'] = $this->uid;
                    $data['mid'] = $order['mid'];
                    $data['orderid'] = $order['id'];
                    $data['ordernum'] = date('ymdHis').aid.rand(1000,9999);
                    $data['title'] = $order['jxmc'];
                    $data['type'] = $type;
                    $data['createtime'] = time();
                    $data['remark'] = '核销员['.$this->user['un'].']核销'; 
                    $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];                    
                    Db::name('hexiao_order')->insert($data);
                    $remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];
                    Db::name($type.'_order')->where(['aid'=>aid,'hexiao_code'=>$code])->update(['status'=>3,'remark'=>$remark]);
				}
			
			}else if($type == 'business_miandan'){
                if(getcustom('yx_business_miandan')){
                    $data = array();
                    $data['aid'] = aid;
                    $data['bid'] = bid;
                    $data['uid'] = $this->uid;
                    $data['mid'] = $order['mid'];
                    $data['orderid'] = $order['id'];
                    $data['ordernum'] = date('ymdHis').aid.rand(1000,9999);
                    $data['title'] = $order['jxmc'];
                    $data['type'] = $type;
                    $data['createtime'] = time();
                    $data['remark'] = '核销员['.$this->user['un'].']核销'; 
                    $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];                    
                    Db::name('hexiao_order')->insert($data);
                    $gbdata = [];
                    $gbdata['status']       = 1;
                    $gbdata['remark']       = '核销员['.$this->user['un'].']核销';
                    $gbdata['hexiao_uid']   = $data['uid'];
                    $gbdata['hexiao_mid']   = $data['mid'];
                    $gbdata['hexiao_mdid']  = $data['mdid'];

                    $orderdata = [];
                    $orderdata = $gbdata;
                    $orderdata['collect_time'] = time();
                    //更新订单状态
                    Db::name('business_miandan_order')->where(['aid'=>aid,'bid'=>bid,'hexiao_code'=>$code])->update($orderdata);
                    
                    Db::name('business_miandan')->where('aid',aid)->where('id',$order['proid'])->update(['sales'=>Db::raw("sales+1")]);
                }
            }
            elseif($type=='shopproduct' || $type == 'gift_bag_goods'){
                $is_quanyi = 0;//是否权益商品
                $is_collect = 0;
                if(getcustom('product_quanyi') && $og['product_type']==8){
                    $is_quanyi = 1;
                }
                if($is_quanyi){
                    //权益核销处理
                    $hexiao_mdid = $this->user['mdid']?:$order['mdid'];
                    $quanyi_res = \app\common\Order::quanyihexiao($og['id'],0,input('param.hxnum'),$hexiao_mdid);
                    if(!$quanyi_res['status']){
                        return $this->json($quanyi_res);
                    }
                    $is_collect = $quanyi_res['is_collect'];
                }
			    $data = array();
				$data['aid'] = aid;
				$data['bid'] = bid;
				$data['uid'] = $this->uid;
				$data['mid'] = $order['mid'];
				$data['orderid'] = $order['ogdata']['id'];
				$data['ordernum'] = $order['ordernum'];
				if($type=='shopproduct'){
					$data['title'] = $order['ogdata']['name'].'('.$order['ogdata']['ggname'].')';
				}else if($type=='gift_bag_goods'){
					$data['title'] = $order['ogdata']['name'];
				}
				$data['type'] = $type;
				$data['createtime'] = time();
				$data['remark'] = '核销员['.$this->user['un'].']核销';
				$data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
                $hexiao_order_id = Db::name('hexiao_order')->insertGetId($data);
				$remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];

				if($type=='shopproduct'){
					Db::name('shop_order_goods')->where('id',$order['ogdata']['id'])->inc('hexiao_num',$order['hxnum'])->update(['hexiao_code'=>random(18)]);
				}else if($type=='gift_bag_goods'){
					if(getcustom('extend_gift_bag')){
						$gbdata = [];
	                    $gbdata['status']       = 3;
	                    $gbdata['remark']       = $remark;
	                    $gbdata['hexiao_uid']   = $data['uid'];
	                    $gbdata['hexiao_mid']   = $data['mid'];
	                    $gbdata['hexiao_mdid']  = $data['mdid'];

						$goodsdata = [];
	                    $goodsdata = $gbdata;
	                    $goodsdata['endtime']      = time();
	                    $goodsdata['hexiao_code']  = '';
                        $goodsdata['hexiao_num']   = 1;
	                    //更新订单产品状态
						Db::name('gift_bag_order_goods')->where('id',$order['ogdata']['id'])->update($goodsdata);
					}
				}

				$pdata = [];
				$pdata['aid'] = aid;
				$pdata['bid'] = bid;
				$pdata['uid'] = $this->uid;
				$pdata['mid'] = $order['mid'];
				$pdata['orderid'] = $order['ogdata']['id'];
				$pdata['ordernum'] = $order['ordernum'];
				if($type=='shopproduct'){
					$pdata['title'] = $order['ogdata']['name'].'('.$order['ogdata']['ggname'].')';
				}else if($type=='gift_bag_goods'){
					$pdata['title'] = $order['ogdata']['name'];
				}
				$pdata['createtime'] = time();
				$pdata['remark'] = '核销员['.$this->user['un'].']核销';
				$pdata['proid'] = $order['ogdata']['proid'];
				$pdata['name'] = $order['ogdata']['name'];
				$pdata['pic'] = $order['ogdata']['pic'];
				
				$pdata['num'] = $order['hxnum'];
				$pdata['ogid'] = $order['ogdata']['id'];
				if($type=='shopproduct'){
					$pdata['ggid'] = $order['ogdata']['ggid'];
					$pdata['ggname'] = $order['ogdata']['ggname'];
					if($is_quanyi){
                        $pdata['hexiao_order_id'] = $hexiao_order_id;
                    }
					Db::name('hexiao_shopproduct')->insert($pdata);
				}else if($type=='gift_bag_goods'){
					if(getcustom('extend_gift_bag')){
						Db::name('hexiao_giftbagproduct')->insert($pdata);
					}
				}

				if($order['hxnum'] + $order['ogdata']['hexiao_num'] == $order['ogdata']['num'] || $is_collect==1){
					if($type=='shopproduct'){
					    if(!$is_quanyi){
                            $totalhxnum = Db::name('shop_order_goods')->where('orderid',$order['id'])->sum('hexiao_num');
                            $totalnum   = Db::name('shop_order_goods')->where('orderid',$order['id'])->sum('num');
                            if($totalhxnum >= $totalnum){
                                $is_collect = 1;
                            }
                        }else{
                            //发货信息录入 微信小程序+微信支付
                            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                                \app\common\Order::wxShipping(aid,$order,$type);
                            }
                        }
						if($is_collect){
							$rs = \app\common\Order::collect($order,'shop', $this->user['mid']);
							//if($rs['status']==0) return $this->json($rs);
							Db::name('shop_order')->where('id',$order['id'])->update(['status'=>3,'collect_time'=>time(),'remark'=>'已核销']);
							Db::name('shop_order_goods')->where(['aid'=>aid,'orderid'=>$order['id']])->update(['status'=>3,'endtime'=>time()]);
                            if(getcustom('ciruikang_fenxiao')){
                                //一次购买升级
                                \app\common\Member::uplv(aid,$order['mid'],'shop',['onebuy'=>1,'onebuy_orderid'=>$order['id']]);
                            }else{
                                \app\common\Member::uplv(aid,$order['mid']);
                            }
                            if(getcustom('member_shougou_parentreward')){
                                //首购解锁
                                Db::name('member_commission_record')->where('orderid',$order['id'])->where('type','shop')->where('status',0)->where('islock',1)->where('aid',$order['aid'])->where('remark','like','%首购奖励')->update(['islock'=>0]);
                            }
						}
					}else if($type=='gift_bag_goods'){
						if(getcustom('extend_gift_bag')){
                            $totalhxnum = Db::name('gift_bag_order_goods')->where('orderid',$order['id'])->sum('hexiao_num');
                            $totalnum   = Db::name('gift_bag_order_goods')->where('orderid',$order['id'])->sum('num');
                            if($totalhxnum >= $totalnum){
                                $rs = \app\common\Order::collect($order,'gift_bag', $this->user['mid']);
                                $orderdata = [];
                                $orderdata = $gbdata;
                                $orderdata['collect_time'] = time();
                                //更新订单状态
                                Db::name('gift_bag_order')->where('id',$order['id'])->update($orderdata);
                            }
                        }
					}
				}
			}
            elseif($type=='takeaway_order_product'){
                $data = array();
                $data['aid'] = aid;
                $data['bid'] = bid;
                $data['uid'] = $this->uid;
                $data['mid'] = $order['mid'];
                $data['orderid'] = $order['ogdata']['id'];
                $data['ordernum'] = $order['ordernum'];
                $data['title'] = $order['ogdata']['name'].'('.$order['ogdata']['ggname'].')';
                $data['type'] = $type;
                $data['createtime'] = time();
                $data['remark'] = '核销员['.$this->user['un'].']核销';
                $up = $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
                Db::name('hexiao_order')->insert($data);

                //$remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];

                Db::name('restaurant_takeaway_order_goods')->where('id',$order['ogdata']['id'])->inc('hexiao_num',$order['hxnum'])->update(['hexiao_code'=>random(18),'status'=>3,'endtime'=>time()]);

                $pdata = [];
                $pdata['aid']  = aid;
                $pdata['bid']  = bid;
                $pdata['type'] = 1;
                $pdata['uid']  = $this->uid;
                $pdata['mid']  = $order['mid'];

                $pdata['orderid']    = $order['ogdata']['id'];
                $pdata['ordernum']   = $order['ordernum'];
                $pdata['title']      = $order['ogdata']['name'].'('.$order['ogdata']['ggname'].')';
                $pdata['remark']     = '核销员['.$this->user['un'].']核销';
                $pdata['proid']      = $order['ogdata']['proid'];
                $pdata['name']       = $order['ogdata']['name'];
                $pdata['pic']        = $order['ogdata']['pic'];
                $pdata['ggid']       = $order['ogdata']['ggid'];
                $pdata['ggname']     = $order['ogdata']['ggname'];
                $pdata['num']        = $order['hxnum'];
                $pdata['ogid']       = $order['ogdata']['id'];

                $pdata['createtime'] = time();
                Db::name('hexiao_restaurantproduct')->insert($pdata);

                if($order['hxnum'] + $order['ogdata']['hexiao_num'] == $order['ogdata']['num']){
                    $totalhxnum = Db::name('restaurant_takeaway_order_goods')->where('orderid',$order['id'])->sum('hexiao_num');
                    $totalnum   = Db::name('restaurant_takeaway_order_goods')->where('orderid',$order['id'])->sum('num');
                    if($totalhxnum >= $totalnum){
                        $rs = \app\custom\Restaurant::takeaway_orderconfirm($order['id']);
                        if($rs['status']==0){
                            return $this->json($rs);
                        }
                    }
                }
            } elseif($type=='hbtk'){
                if(!$plat_hexiao && !getcustom('yx_hbtk')) return $this->json(['status'=>0,'msg'=>'登录的账号不是该商家的管理账号']);
                $order = Db::name('hbtk_order')->where(['aid'=>aid,'code'=>$code])->find();
                if(!$order) return $this->json(['status'=>0,'msg'=>'活动不存在']);
                if($order['status']==2) return $this->json(['status'=>0,'msg'=>'活动已核销']);
                $data = array();
                $data['aid'] = aid;
                $data['bid'] = bid;
                $data['uid'] = $this->uid;
                $data['mid'] = $order['mid'];
                $data['orderid'] = $order['id'];
                $data['ordernum'] = date('ymdHis').aid.rand(1000,9999);
                $data['title'] = $order['name'];
                $data['type'] = $type;
                $data['createtime'] = time();
                $data['remark'] = '核销员['.$this->user['un'].']核销';
                $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
                Db::name('hexiao_order')->insert($data);
                Db::name($type.'_order')->where(['aid'=>aid,'code'=>$code])->update(['status'=>2,'hxtime' => time()]);
                //核销
                if($order['pid'] > 0){
                    $hd = Db::name('hbtk_activity')->where('id',aid)->where('id',$order['hid'])->find();
                    $jxtp =  $order['jxtp'];
                    $jxmc = $order['jxmc'];
                    $oldjxmc = $jxmc;
                    if($jxtp==1){

                    }elseif($jxtp==2){
                        $jxmc =  $jxmc.'元红包';
                    }elseif($jxtp==3){
                        $jxmc =  '优惠券(ID:'.$jxmc.')';
                    }elseif($jxtp==4){
                        $jxmc =  $jxmc.'积分';
                    }elseif($jxtp==5){
                        $jxmc =  $jxmc.'元'.t('余额');
                    }
                    if($jxtp == 2){
                        srand(microtime(true) * 1000);
                        $moneyArr = explode('-',str_replace('~','-',$jxmc));
                        if(!$moneyArr[1]) $moneyArr[1] = $moneyArr[0];
                        $ss = rand($moneyArr[0]*100,$moneyArr[1]*100).PHP_EOL;
                        $money = number_format($ss/100, 2, '.', '');
                        $jxmc = $money.'元红包';
                        $rs = \app\common\Wxpay::sendredpackage(aid,$order['pid'],platform,$money,mb_substr($order['name'],0,10),'微信红包','恭喜发财','微信红包',$hd['scene_id']);
                        if($rs['status']==0){ //发放失败
                            Db::name('hbtk_order')->where('id',$order['id'])->update(['jxmc'=>$jxmc,'remark'=>$rs['msg']]);
                        }else{
                            Db::name('hbtk_order')->where('id',$order['id'])->update(['jxmc'=>$jxmc,'status'=>2,'remark'=>'发放成功']);
                            if(platform == 'wx'){//小程序红包
                                $appinfo = \app\common\System::appinfo(aid,platform);
                                $appid = $appinfo['appid'];
                                $mchkey = $appinfo['wxpay_mchkey'];
                                $spdata = [];
                                $spdata['appId'] = $appid;
                                $spdata['timeStamp'] = strval(time());
                                $spdata['nonceStr'] = random(16);
                                $spdata['package'] = urlencode($rs['resp']['package']);
                                ksort($spdata, SORT_STRING);
                                $string1 = '';
                                foreach ($spdata as $key => $v) {
                                    if (empty($v)) {
                                        continue;
                                    }
                                    $string1 .= "{$key}={$v}&";
                                }
                                $string1 .= "key={$mchkey}";
                                $spdata['signType'] = 'MD5';
                                $spdata['paySign'] = md5($string1);
                            }
                        }
                    }
                    //优惠券
                    if($jxtp==3){
                        $rs = \app\common\Coupon::send(aid,$order['pid'],$oldjxmc);
                        if($rs['status']==0){ //发放失败
                            Db::name('hbtk_order')->where('id',$order['id'])->update(['jxmc'=>$jxmc,'remark'=>$rs['msg']]);
                        }else{
                            Db::name('hbtk_order')->where('id',$order['id'])->update(['jxmc'=>$jxmc,'status'=>2,'remark'=>'发放成功']);
                        }
                    }
                    //积分
                    if($jxtp==4){
                        $rs = \app\common\Member::addscore(aid,$order['pid'],$oldjxmc,$order['name'].'-拓客活动');
                        if($rs['status']==0){ //发放失败
                            Db::name('hbtk_order')->where('id',$order['id'])->update(['jxmc'=>$jxmc,'remark'=>$rs['msg']]);
                        }else{
                            Db::name('hbtk_order')->where('id',$order['id'])->update(['jxmc'=>$jxmc,'status'=>2,'remark'=>'发放成功']);
                        }
                    }
                    //余额
                    if($jxtp==5){
                        $rs = \app\common\Member::addmoney(aid,$order['pid'],$oldjxmc,$order['name']);
                        if($rs['status']==0){ //发放失败
                            Db::name('hbtk_order')->where('id',$order['id'])->update(['jxmc'=>$jxmc,'remark'=>$rs['msg']]);
                        }else{
                            Db::name('hbtk_order')->where('id',$order['id'])->update(['jxmc'=>$jxmc,'status'=>2,'remark'=>'发放成功']);
                        }
                    }
                }
            }elseif($type=='verifyauth'){
                if(getcustom('restaurant_cashdesk_auth_enter')) {
                    $log = Db::name('restaurant_verify_auth_log')->where('aid', aid)->where('code', $code)->find();
                    if (!$log) {
                        return $this->json(['status' => 0, 'msg' => '二维码已失效']);
                    }
                    if ($log['expiretime'] <= time()) {
                        return $this->json(['status' => 0, 'msg' => '二维码已过期']);
                    }
                    if ($log['status'] !=0) {
                        return $this->json(['status' => 0, 'msg' => '二维码已失效']);
                    }
                    if($this->user['auth_type']==0){
                        if($this->user['groupid']){
                            $this->user['auth_data'] = Db::name('admin_user_group')->where('id',$this->user['groupid'])->value('auth_data');
                        }
                        $auth_data = json_decode($this->user['auth_data'],true);
                        $auth_path = [];
                        foreach($auth_data as $v) {
                            $auth_path = array_merge($auth_path,explode(',',$v));
                        }
                    }else{
                        $auth_path ='all';
                    }
                    $is_error = 0;
                    if($log['type']=='refund'){
                        if(!in_array('RestaurantCashdesk/refund',$auth_path) && $auth_path !='all'){
                            $is_error = 1;
                        }
                    }elseif($log['type']=='cancel'){
                        if(!in_array('RestaurantCashdesk/cancel',$auth_path)  && $auth_path !='all'){
                            $is_error = 1;
                        }
                    }elseif ($log['type']=='discount'){
                        if(!in_array('RestaurantCashdesk/discount',$auth_path)  && $auth_path !='all'){
                            $is_error = 1;
                        }
                    }else{
                        $is_error = 1;
                    }
                    if($is_error){
                        Db::name('restaurant_verify_auth_log')->where('aid',aid)->where('code',$code)->update(['status' => 2]);  
                        return json(['status'=>0,'msg'=>'您没有权限']);
                    }
                    Db::name('restaurant_verify_auth_log')->where('aid',aid)->where('code',$code)->update(['status' => 1,'auth_uid' =>$this->uid]);
                }
            }else if($type=='hotel'){
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
				db($type.'_order')->where(['aid'=>aid,'hexiao_code'=>$code])->update(['status'=>3,'daodian_time'=>time(),'remark'=>$remark]);
			}else if($type=='form'){
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
                db($type.'_order')->where(['aid'=>aid,'hexiao_code'=>$code])->update(['hexiao_status'=>1,'hexiao_time'=>time(),'remark'=>$remark]);
            }

            if(getcustom('user_hexiao_num')){  
                if($this->user['hexiao_num'] != -1){
                    $sy_num =$this->user['hexiao_num'] -1;
                    if($sy_num >= 0){
                        Db::name('admin_user')->where('aid',aid)->where('bid',bid)->where('id',$this->user['id'])->update(['hexiao_num' => $sy_num]);
                    }
                }
            }
            
            if(getcustom('hexiao_auto_wifiprint')){
                if(in_array($type,['shop'])){
                    \app\common\Wifiprint::print($order['aid'],$type,$order['id'],0,-1,$order['bid'],'shop',-1,['opttype' => 'hexiao']);
                }
            }
            if(getcustom('transfer_order_parent_check')){
                //确认收货增加有效金额
                \app\common\Fenxiao::addTotalOrderNum($order['aid'], $order['mid'], $order['id'], 3);
            }
            Db::commit();
			return $this->json(['status'=>1,'msg'=>'核销成功']);
		}
        $hexiao_type = 0;
        if(getcustom('goods_hexiao')) {
            //外卖订单商品单独核销
            $hexiao_type = 1;
        }
		return $this->json(['order'=>$order,'status'=>1,'hexiao_type'=>$hexiao_type,'type'=>$type,'mendian_no_select'=>$mendian_no_select]);
	}
	//核销记录
	public function record(){
		$pagenum = input('post.pagenum');
		$type = input('post.type/d',0);
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['order.aid','=',aid];
		$where[] = ['order.bid','=',bid];

		if(getcustom('mendian_no_select')){
		    if($this->user['isadmin']==0){
                $where[] = ['uid','=',$this->user['id']];
            }
        }else{
            if($this->user['mdid']){
                $where[] = ['uid','=',$this->user['id']];
            }
        }
		if(input('param.keyword')) $where[] = ['member.nickname','like','%'.trim(input('param.keyword')).'%'];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_moneylog.status','=',input('param.status')];
		$datalist = Db::name('hexiao_order')->alias('order')->field('member.nickname,member.headimg,order.*')->join('member member','member.id=order.mid')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if($pagenum==1){
			$count = 0 + Db::name('hexiao_order')->alias('order')->field('member.nickname,member.headimg,order.*')->join('member member','member.id=order.mid')->where($where)->count();
		}
		$product_quanyi = getcustom('product_quanyi',aid);
		foreach($datalist as $k=>$v){
		    if($product_quanyi){
		        if($v['type']=='shopproduct'){
                    //hexiao_order_id字段是2025-1-6才加的，之前没有
                    $hexiao_num = Db::name('hexiao_shopproduct')
                        ->where('aid',$v['aid'])
                        ->where('uid',$v['uid'])
                        ->where('ordernum',$v['ordernum'])
                        ->where('hexiao_order_id',$v['id'])
                        ->value('num');
                    if(!$hexiao_num){
                        $hexiao_num = Db::name('hexiao_shopproduct')
                            ->where('aid',$v['aid'])
                            ->where('uid',$v['uid'])
                            ->where('ordernum',$v['ordernum'])
                            ->where('createtime',$v['createtime'])
                            ->value('num');
                    }
                }else{
                    $hexiao_num = 1;
                }
                $datalist[$k]['remark'] = $v['remark'].' 核销数量：'.$hexiao_num;
            }
        }
        //核销记录按年月分组
        $showgroup = false;
        if(getcustom('hexiao_record_group') && $type!=1){
            $showgroup = true;
        }
		return $this->json(['status'=>1,'count'=>$count,'data'=>$datalist,'showgroup'=>$showgroup]);
	}
    /*
     * 1.本年度按月分
     * 2.之前年份按年分且折叠
     */
    public function recordGroup(){
        if(getcustom('hexiao_record_group')){
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            if($this->user['mdid']){
                $where[] = ['uid','=',$this->user['id']];
            }
            $currentYear = date('Y',time());
            $currentMonth = date('m');
            $endYear = $currentYear;
            //核销最早年份
            $info = Db::name('hexiao_order')->where($where)->order('createtime asc')->find();
            if($info){
                $startYear = date('Y',$info['createtime']);
            }else{
                $startYear = $currentYear;
            }
            $yearList = [];
            for ($endYear;$endYear>=$startYear;$endYear--){
                $yearitem = [
                    'name'=>$endYear.'年',
                    'val'=> $endYear,
                    'monthlist' =>[],
                    'isshow'=>false,
                ];
                $monthlist = [];
                if($endYear==$currentYear){
                    //当前年份，月份倒叙，截止到当前月
                    $yuenum = date('m');
                    $yearitem['isshow'] = true;
                }else{
                    $yuenum = 12;
                }
                for ($i=$yuenum;$i>=1;$i--){
                    $isMshow = false;
                    if($endYear==$currentYear && $i==$currentMonth){
                        $isMshow = true;
                    }
                    $monthlist[] = [
                        'name'=> intval($i).'月',
                        'val'=>$i,
                        'list'=>[],
                        'isshow'=>$isMshow
                    ];
                }
                $yearitem['monthlist'] = $monthlist;
                $yearList[] = $yearitem;
            }
            //当月的记录
            return $this->json(['status'=>1,'yearlist'=>$yearList]);
        }
        return $this->json(['status'=>0,'msg'=>'功能未开放']);
    }
    public function recordMonthList(){
        if(getcustom('hexiao_record_group')){
            $year = input('year');
            $month = input('month');
            $pagenum = input('post.pagenum');
            if(!$pagenum) $pagenum = 1;
            $pernum = 10;
            $days = date("t", strtotime($year . "-" . $month . "-01"));
            $date = $year.'-'.$month;
            $starttime = strtotime($date.'-01 00:00:00');
            $endtime = $starttime + 86400 * $days;
            $where = [];
            $where[] = ['order.aid','=',aid];
            $where[] = ['order.bid','=',bid];
            if($this->user['mdid']){
                $where[] = ['uid','=',$this->user['id']];
            }
            $where[] = ['order.createtime','between',[$starttime,$endtime]];
            if($this->user['mdid']){
                $where[] = ['order.uid','=',$this->user['id']];
            }

            if(input('param.keyword')) $where[] = ['member.nickname','like','%'.trim(input('param.keyword')).'%'];
            if(input('?param.status') && input('param.status')!=='') $where[] = ['member_moneylog.status','=',input('param.status')];
            $datalist = Db::name('hexiao_order')->alias('order')->field('member.nickname,member.headimg,order.*')->join('member member','member.id=order.mid')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            $count = 0;
            if($pagenum==1){
                $count = 0 + Db::name('hexiao_order')->alias('order')->field('member.nickname,member.headimg,order.*')->join('member member','member.id=order.mid')->where($where)->count();
            }
            return $this->json(['status'=>1,'count'=>$count,'data'=>$datalist]);
        }
        return $this->json(['status'=>0,'msg'=>'功能未开放']);
    }



    public function couponTimesCheck($aid,$bid,$couponrecord=[]){
        if(getcustom('coupon_times_expire') || getcustom('coupon_times_use_gap')){
            if($couponrecord['type']!=3){
                return ['status'=>1,'msg'=>''];
            }
            $where  = [];
            $where['aid'] = $aid;
            $where['bid'] = $bid;
            $where['mid'] = $couponrecord['mid'];
            $where['orderid'] = $couponrecord['id'];
            $where['type'] = 'coupon';
            $exist = Db::name('hexiao_order')->where($where)->order('id desc')->find();
            if(empty($exist)){
                return ['status'=>1,'msg'=>''];
            }
            $couponinfo = Db::name('coupon')->where('id',$couponrecord['couponid'])->find();
            if(empty($couponinfo)){
                return ['status'=>1,'msg'=>''];
            }
            $use_gap = $couponinfo['use_gap'];
            if($use_gap<=0){
                return ['status'=>1,'msg'=>''];
            }
            $useGapTime = $use_gap * 86400;
            $use_gap = round($use_gap,2);
            if(time() - $exist['createtime']<$useGapTime){
                return ['status'=>0,'msg'=>"该优惠券使用间隔必须大于{$use_gap}天"];
            }
            return ['status'=>1,'msg'=>''];
        }
    }

}