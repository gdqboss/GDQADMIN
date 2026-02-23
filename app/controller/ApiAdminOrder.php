<?php
// JK客户定制

//管理员中心 - 订单管理
namespace app\controller;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class ApiAdminOrder extends ApiCommon
{
    public function initialize(){
        parent::initialize();
        $this->checklogin();
    }

    //商城订单
    public function shoporder(){
        $st = input('param.st');
        if(!input('?param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        if(input('param.keyword')){
            $keywords = input('param.keyword');
            $orderids = Db::name('shop_order_goods')->where($where)->where('name','like','%'.input('param.keyword').'%')->column('orderid');
            if(!$orderids){
                $where[] = ['ordernum|title', 'like', '%'.$keywords.'%'];
            }
        }
        $whereMd = '';
        if($this->user['mdid']){
            $whereMd = 'mdid='.$this->user['mdid'];
            if(getcustom('mendian_no_select')) {
                $whereMd .= ' or find_in_set (' . $this->user['mdid'] . ',mdids)';
            }
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
        }elseif($st == '10'){
            $where[] = ['refund_status','>',0];
        }

        if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];

        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('shop_order')->where($where);
        if($orderids){
            $datalist->where(function ($query) use ($orderids,$keywords){
                $query->whereIn('id',$orderids)->whereOr('ordernum|title','like','%'.$keywords.'%');
            });
        }
        $datalist = $datalist->where($whereMd)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
        if(!$datalist) $datalist = array();
        foreach($datalist as $key=>$v){
            $datalist[$key]['prolist'] = [];
            $prolist = Db::name('shop_order_goods')->where('orderid',$v['id'])->select()->toArray();
            if(getcustom('product_glass')){
                foreach ($prolist as $pk=>$pv){
                    $prolist[$pk]['has_glassrecord'] = 0;
                    if($pv['glass_record_id']){
                        $glassrecord = \app\model\Glass::orderGlassRecord($pv['glass_record_id']);
                        if($glassrecord){
                            $prolist[$pk]['has_glassrecord'] = 1;
                            $prolist[$pk]['glassrecord'] = $glassrecord;
                        }
                    }
                }
            }
            if(getcustom('product_weight')){
                foreach ($prolist as $gk=>$gv){
                    $prolist[$gk]['product_type'] = $v['product_type'];
                    if($v['product_type']==2){
                        $prolist[$gk]['total_weight'] = round($gv['total_weight']/500,2);
                        $prolist[$gk]['real_total_weight'] = round($gv['real_total_weight']/500,2);
                    }
                }
            }
            if($prolist) $datalist[$key]['prolist'] = $prolist;
            $datalist[$key]['procount'] = Db::name('shop_order_goods')->where('orderid',$v['id'])->sum('num');
            $datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
            if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
        }
        $wifiprintAuth = false;
        if(getcustom('shop_order_mobile_wifiprint')){
            $wifiprintAuth = true;
        }
        $rdata = [];
        $rdata['wifiprintAuth'] = $wifiprintAuth;
        $rdata['datalist'] = $datalist;
        $rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');
        $rdata['st'] = $st;
        return $this->json($rdata);
    }
    //商城订单详情
    public function shoporderdetail(){
        $detail = Db::name('shop_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
        if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        $detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
        $detail['formdata'] = \app\model\Freight::getformdata($detail['id'],'shop_order');

        $member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
        $detail['nickname'] = $member['nickname'];
        $detail['headimg'] = $member['headimg'];
        $canFahuo = 0;
        if($detail['status']==1){
            $canFahuo = 1;
        }
        if(getcustom('erp_wangdiantong')){
            if($detail['status']==1 && $detail['wdt_status']==2) {
                $canFahuo = 1;
            }elseif($detail['status']==1 && $detail['wdt_status']==1){
                $canFahuo = 0;//由ERP进行发货
            }
        }
        if(getcustom('supply_zhenxin')){
            if($detail['issource'] == 1 && $detail['source'] == 'supply_zhenxin'){
                $canFahuo = 0;
            }
        }
        $detail['can_fahuo'] = $canFahuo;
        $storeinfo = [];
        $storelist = [];
        $detail['hidefahuo'] = false;
        if($detail['freight_type'] == 1){
            if($detail['mdid'] == -1){
                $freight = Db::name('freight')->where('id',$detail['freight_id'])->find();
                if($freight && $freight['hxbids']){
                    if($detail['longitude'] && $detail['latitude']){
                        $orderBy = Db::raw("({$detail['longitude']}-longitude)*({$detail['longitude']}-longitude) + ({$detail['latitude']}-latitude)*({$detail['latitude']}-latitude) ");
                    }else{
                        $orderBy = 'sort desc,id';
                    }
                    $storelist = Db::name('business')->where('aid',$freight['aid'])->where('id','in',$freight['hxbids'])->where('status',1)->field('id,name,logo pic,longitude,latitude,address')->order($orderBy)->select()->toArray();
                    foreach($storelist as $k2=>$v2){
                        if($detail['longitude'] && $detail['latitude'] && $v2['longitude'] && $v2['latitude']){
                            $v2['juli'] = '距离'.getdistance($detail['longitude'],$detail['latitude'],$v2['longitude'],$v2['latitude'],2).'千米';
                        }else{
                            $v2['juli'] = '';
                        }
                        $storelist[$k2] = $v2;
                    }
                }
            }else{
                $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('id,name,address,longitude,latitude')->find();
            }
            if(getcustom('freight_selecthxbids')){
                $detail['hidefahuo'] = true;
            }
        }

        $prolist = Db::name('shop_order_goods')->where('orderid',$detail['id'])->select()->toArray();
        $isjici = 0;
        foreach ($prolist as $pk=>$pv){
            $prolist[$pk]['is_quanyi'] = (isset($pv['product_type']) && $pv['product_type']==8) ? 1 : 0;
        }

        $shopsetfield = 'comment,autoclose,canrefund';

        if(getcustom('probgcolor')){
            $shopsetfield .= ',order_detail_toppic';
        }

        if(getcustom('product_service_fee')){
            $shopsetfield .= ',show_shd_remark';
        }

        if(getcustom('product_thali')){
            $shopsetfield .= ',product_shop_school';
        }

        $shopset = Db::name('shop_sysset')->where('aid',aid)->field($shopsetfield)->find();

        if($detail['status']==0 && $shopset['autoclose'] > 0){
            $lefttime = strtotime($detail['createtime']) + $shopset['autoclose']*60 - time();
            if($lefttime < 0) $lefttime = 0;
        }else{
            $lefttime = 0;
        }

        //弃用
        if($detail['field1']){
            $detail['field1data'] = explode('^_^',$detail['field1']);
        }
        if($detail['field2']){
            $detail['field2data'] = explode('^_^',$detail['field2']);
        }
        if($detail['field3']){
            $detail['field3data'] = explode('^_^',$detail['field3']);
        }
        if($detail['field4']){
            $detail['field4data'] = explode('^_^',$detail['field4']);
        }
        if($detail['field5']){
            $detail['field5data'] = explode('^_^',$detail['field5']);
        }
        $peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
        if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
        $detail['canpeisong'] = ($detail['freight_type']==2 && $peisong_set['status']==1) ? true : false;
        $detail['express_wx_status'] = $peisong_set['express_wx_status']==1 ? true : false;

        if(getcustom('express_maiyatian')){
            $detail['myt_status']    = $peisong_set['myt_status']==1 ? true : false;
            $detail['myt_set']       = true;
            $detail['myt_shop']      = false;
            $detail['myt_shoplist']  = [];
            if($detail['myt_shop']){
                $detail['myt_shoplist']  = Db::name('peisong_myt_shop')->where('aid',aid)->where('bid',bid)->where('is_del',0)->order('id asc')->field('id,origin_id,name')->select()->toArray();
                if(!$detail['myt_shoplist']){
                    $detail['myt_shoplist']  = [['id'=>0,'origin_id'=>0,'name'=>'无门店可选择']];
                }
            }
        }

        if($detail['freight_type'] == 2){
            $peisong = Db::name('peisong_order')->where('orderid',$detail['id'])->where('type','shop_order')->field('id,psid')->find();
            if($peisong){
                $detail['psid'] = $peisong['psid'];
            }
        }

        if($detail['checkmemid']){
            $detail['checkmember'] = Db::name('member')->field('id,nickname,headimg,realname,tel')->where('id',$detail['checkmemid'])->find();
        }else{
            $detail['checkmember'] = [];
        }
        if(getcustom('product_glass')){
            foreach($prolist as $k=>$pro){
                if($pro['glass_record_id']){
                    $glassrecord = \app\model\Glass::orderGlassRecord($pro['glass_record_id'],aid);
                }
                $prolist[$k]['glassrecord'] = $glassrecord??'';
            }
        }
        if(getcustom('product_weight') && $detail['product_type']==2){
            //称重商品，单价 重量kg
            foreach ($prolist as $k=>$v){
                $prolist[$k]['total_weight'] = round($v['total_weight']/500,2);
                $prolist[$k]['real_total_weight'] = round($v['real_total_weight']/500,2);
                $prolist[$k]['product_type'] = 2;
            }
        }
        if(getcustom('product_service_fee') && $shopset['show_shd_remark']==1){
            foreach ($prolist as &$vv){
                $vv['shd_remark'] = Db::name('shop_product')->where('id',$vv['proid'])->value('shd_remark');
            }
            unset($vv);
        }
        $detail['message'] = \app\model\ShopOrder::checkOrderMessage($detail['id'],$detail);
        if(getcustom('pay_money_combine')){
            if($detail['combine_money'] && $detail['combine_money'] > 0){
                if(!empty($detail['paytype'])){
                    $detail['paytype'] .= ' + '.t('余额').'支付';
                }else{
                    $detail['paytype'] .= t('余额').'支付';
                }
            }
        }
        if(getcustom('product_pickup_device')){
            if($detail['dgid']&& $detail['freight_type'] ==1){
                $f_device =  Db::name('product_pickup_device_goods')->alias('dg')
                    ->join('product_pickup_device d','d.id = dg.device_id')
                    ->where('dg.aid',aid)->where('dg.id',$detail['dgid'])->field('d.address,d.name')->find();
                $storeinfo['address'] = $f_device['address'];
                $storeinfo['name'] = $f_device['name'];
            }
        }
        $detail['is_quanyi'] = 0;
        if(getcustom('product_quanyi') && $detail['product_type']==8){
            $detail['hexiao_num_remain'] = $detail['hexiao_num_total']-$detail['hexiao_num_used'];
            $detail['is_quanyi'] = 1;
        }

        //如果是兑换订单显示兑换码
        if($detail['lipin_dhcode'] && $detail['paytype'] == '兑换码兑换'){
            $detail['paytype'] = $detail['paytype'].'('.$detail['lipin_dhcode'].')';
        }

        $detail['product_thali'] = false;
        if(getcustom('product_thali') && $shopset['product_shop_school'] == 1){
            $detail['product_thali'] = true;
        }

        $shopset['send_show'] = 1;
        $shopset['hexiao_show'] = 1;
        if($detail['bid'] > 0){
            $business_sysset = \db('business_sysset')->where('aid',aid)->find();
            if(getcustom('business_shop_order_send_show')){
                $shopset['send_show'] = $business_sysset['shop_order_send_show'];
            }
            if(getcustom('business_shop_order_hexiao_show')){
                $shopset['hexiao_show'] = $business_sysset['shop_order_hexiao_show'];
            }
        }

        $rdata = [];
        $rdata['detail'] = $detail;
        $rdata['prolist'] = $prolist;
        $rdata['shopset'] = $shopset;
        $rdata['storeinfo'] = $storeinfo;
        $rdata['lefttime'] = $lefttime;
        $rdata['expressdata'] = array_keys(express_data(['aid'=>aid,'bid'=>bid]));
        $rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');
        $rdata['storelist'] = $storelist;

        return $this->json($rdata);
    }
    //退款单列表
    public function shopRefundOrder(){
        $st = input('param.st');
        if(!input('param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        $order = ['id' => 'desc'];
        if($this->user['mdid']){
            $where[] = ['mdid','=',$this->user['mdid']];
        }
        if(input('param.keyword')) $where[] = ['refund_ordernum|ordernum|title', 'like', '%'.input('param.keyword').'%'];
        if($st == 'all'){

        }elseif($st == '0'){
            $where[] = ['refund_status','=',0];
        }elseif($st == '1'){
            $where[] = ['refund_status','=',1];
            $order['id'] = 'asc';
        }elseif($st == '2'){
            $where[] = ['refund_status','=',2];
        }elseif($st == '3'){
            $where[] = ['refund_status','=',3];
        }

        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;

        if(input('param.orderid/d')) {
            $where[] = ['orderid','=',input('param.orderid/d')];
            $pernum = 99;
        }

        $datalist = Db::name('shop_refund_order')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
        if(!$datalist) $datalist = array();
        foreach($datalist as $key=>$v){
            $datalist[$key]['prolist'] = Db::name('shop_refund_order_goods')->where('refund_orderid',$v['id'])->select()->toArray();
            if(!$datalist[$key]['prolist']) $datalist[$key]['prolist'] = [];
            $datalist[$key]['procount'] = Db::name('shop_refund_order_goods')->where('refund_orderid',$v['id'])->sum('refund_num');
            $datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
            if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
            if($v['refund_type'] == 'refund') {
                $datalist[$key]['refund_type_label'] = '退款';
            }elseif($v['refund_type'] == 'return') {
                $datalist[$key]['refund_type_label'] = '退货退款';
            }
        }
        $rdata = [];
        $rdata['datalist'] = $datalist;
        $rdata['st'] = $st;
        return $this->json($rdata);
    }
    public function shopRefundOrderDetail()
    {
        $where = [];
        $where[] = ['id','=',input('param.id/d')];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        if($this->user['mdid']){
            $where[] = ['mdid','=',$this->user['mdid']];
        }
        $detail = Db::name('shop_refund_order')->where($where)->find();
        if(!$detail) $this->json(['status'=>0,'msg'=>'退款单不存在']);
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        if($detail['refund_type'] == 'refund') {
            $detail['refund_type_label'] = '退款';
        }elseif($detail['refund_type'] == 'return') {
            $detail['refund_type_label'] = '退货退款';
        }
        if($detail['refund_pics']) {
            $detail['refund_pics'] = explode(',', $detail['refund_pics']);
        }
        unset($where['id']);
        $where[] = ['orderid', '=', $detail['orderid']];
        $detail['refundMoneyTotal'] =  Db::name('shop_refund_order')->where($where)->where('refund_status',2)->sum('refund_money');

        $member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
        $detail['nickname'] = $member['nickname'];
        $detail['headimg'] = $member['headimg'];

        $order = Db::name('shop_order')->where('id',$detail['orderid'])->where('aid',aid)->where('bid',bid)->find();
        $order['createtime'] = $order['createtime'] ? date('Y-m-d H:i:s',$order['createtime']) : '';
        $order['collect_time'] = $order['collect_time'] ? date('Y-m-d H:i:s',$order['collect_time']) : '';
        $order['paytime'] = $order['paytime'] ? date('Y-m-d H:i:s',$order['paytime']) : '';
        $order['refund_time'] = $order['refund_time'] ? date('Y-m-d H:i:s',$order['refund_time']) : '';
        $order['send_time'] = $order['send_time'] ? date('Y-m-d H:i:s',$order['send_time']) : '';
        $order['formdata'] = \app\model\Freight::getformdata($order['id'],'shop_order');

        $prolist = Db::name('shop_refund_order_goods')->where('refund_orderid',$detail['id'])->select()->toArray();
        if(getcustom('pay_money_combine')){
            if($detail['combine_money'] && $detail['combine_money'] > 0){
                if(!empty($detail['paytype'])){
                    $detail['paytype'] .= ' + '.t('余额').'支付';
                }else{
                    $detail['paytype'] .= t('余额').'支付';
                }
            }
        }

        $detail['cancheck'] = true;
        if(getcustom('supply_zhenxin')){
            if($order['issource'] && $order['source'] == 'supply_zhenxin'){
                $detail['cancheck'] = false;
            }
        }

        //门店自提和同城配送类型退货判断 
        if($detail['refund_type'] != 'refund'){
            if($order['freight_type'] == 1 || $order['freight_type'] == 2){
                //是否通过快递发货 ，不是则退款类型可直接退款
                if(empty($order['express_no']) && empty($order['express_content'])){
                    $detail['refund_type'] = 'refund';
                }
            }
        }

        $rdata = [];
        $rdata['detail'] = $detail;
        $rdata['order'] = $order;
        $rdata['prolist'] = $prolist;
        return $this->json($rdata);
    }
    //拼团订单
    public function collageorder(){
        $st = input('param.st');
        if(!input('?param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];

        if($this->user['mdid']){
            $where[] = ['mdid', '=', $this->user['mdid']];
        }
        if(getcustom('yx_collage_team_in_team')){
            $where[] = ['isteaminteam','=',0];
        }
        if(input('param.keyword')) $where[] = ['ordernum|title', 'like', '%'.input('param.keyword').'%'];
        if($st == 'all'){
            
        }elseif($st == '0'){
            $where[] = ['status','=',0];
        }elseif($st == '1'){
            $where[] = ['status','=',1];
            $where[] = ['order_pay_type','=',1];
        }elseif($st == '2'){
            $where[] = ['status','=',2];
        }elseif($st == '3'){
            $where[] = ['status','=',3];
        }elseif($st == '10'){
            $where[] = ['refund_status','>',0];
        }
        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('collage_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();

        if(!$datalist) $datalist = array();
        foreach($datalist as $key=>$v){
            $datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
            if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
            if($v['buytpe']!=1) $datalist[$key]['team'] = Db::name('collage_order_team')->where('id',$v['teamid'])->find();
        }
        $rdata = [];
        $rdata['datalist'] = $datalist;
        $rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');
        $rdata['st'] = $st;
        return $this->json($rdata);
    }
    //拼团订单详情
    public function collageorderdetail(){
        $detail = Db::name('collage_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
        if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        $detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
        $detail['formdata'] = \app\model\Freight::getformdata($detail['id'],'collage_order');

        $member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
        $detail['nickname'] = $member['nickname'];
        $detail['headimg'] = $member['headimg'];

        $storeinfo = [];
        if($detail['freight_type'] == 1){
            $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('name,address,longitude,latitude')->find();
        }
        
        if($detail['buytype'] != 1){
            $team = Db::name('collage_order_team')->where('id',$detail['teamid'])->find();
        }else{
            $team = [];
        }
        $peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
        if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
        $detail['canpeisong'] = ($detail['freight_type']==2 && $peisong_set['status']==1) ? true : false;

        if(getcustom('express_maiyatian')){
            $detail['myt_status']    = $peisong_set['myt_status']==1 ? true : false;
            $detail['myt_set']       = true;
            $detail['myt_shop']      = false;
            $detail['myt_shoplist']  = [];
            if($detail['myt_shop']){
                $detail['myt_shoplist']  = Db::name('peisong_myt_shop')->where('aid',aid)->where('bid',bid)->where('is_del',0)->order('id asc')->field('id,origin_id,name')->select()->toArray();
                if(!$detail['myt_shoplist']){
                    $detail['myt_shoplist']  = [['id'=>0,'origin_id'=>0,'name'=>'无门店可选择']];
                }
            }
        }

        $rdata = [];
        $rdata['detail'] = $detail;
        $rdata['team'] = $team;
        $rdata['storeinfo'] = $storeinfo;
        $rdata['expressdata'] = array_keys(express_data(['aid'=>aid,'bid'=>bid]));

        return $this->json($rdata);
    }
    
    
    //周期购订单列表
    public function cycleorder(){
        $this->checklogin();
        $st = input('param.st');
        if(!input('?param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        $where[] = ['delete','=',0];
        
        if($this->user['mdid']){
            $where[] = ['mdid', '=', $this->user['mdid']];
        }
    
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
        }elseif($st == '10'){
            $where[] = ['refund_status','>',0];
        }
        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('cycle_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            
        if(!$datalist) $datalist = array();
        foreach($datalist as $key=>$v){
            //发票
            $datalist[$key]['invoice'] = 0;
            if($v['bid']) {
                $datalist[$key]['invoice'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('invoice');
            } else {
                $datalist[$key]['invoice'] = Db::name('admin_set')->where('aid',aid)->value('invoice');
            }
        }
        $rdata = [];
        $rdata['st'] = $st;
        $rdata['datalist'] = $datalist;
        return $this->json($rdata);
    }
    /**
     * 获取周期列表
     */
    public function getCycleList(){
        $orderid = input('param.id/d');
        $this->checklogin();
        $detail = Db::name('cycle_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
        if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
        $list = Db::name('cycle_order_stage')
            ->where('orderid',$orderid)
            ->field('id,cycle_date,cycle_number,status')
            ->order('cycle_number asc')
            ->select()->toArray();
        foreach ($list as $k=>&$v){
            $v['title'] = '第'.$v['cycle_number'].'期';
        }
        return $this->json(['status'=>1,'data'=>$list,'detail' => $detail]);
    }
    public function cycleorderdetail(){
        $this->checklogin();

        $detail = Db::name('cycle_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
        if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
        $member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
        $detail['nickname'] = $member['nickname'];
        $detail['headimg'] = $member['headimg'];
        
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        $detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
        $detail['formdata'] = \app\model\Freight::getformdata($detail['id'],'cycle_order');
        //配送频率

        $ps_cycle = ['1' => '每日一期','2' => '每周一期' ,'3' => '每月一期'];
        $every_day = ['1' => '每天配送','2' => '工作日配送' ,'3' => '周末配送','4' => '隔天配送'];

        $detail['pspl'] = $ps_cycle[$detail['ps_cycle']];
        if($detail['ps_cycle'] == 1){
            $detail['every_day'] =$every_day[$detail['fwtc']];
        }else{
            $detail['every_day'] = '';

        }

        $storeinfo = [];
        if($detail['freight_type'] == 1){
            $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('id,name,address,longitude,latitude')->find();
        }

        $shopset = Db::name('cycle_sysset')->where('aid',aid)->find();
        if($detail['status']==0 && $shopset['autoclose'] > 0 && $detail['paytypeid'] != 5){
            $lefttime = strtotime($detail['createtime']) + $shopset['autoclose']*60 - time();
            if($lefttime < 0) $lefttime = 0;
        }else{
            $lefttime = 0;
        }

        $rdata = [];
        //发票
        $rdata['invoice'] = 0;
        if($detail['bid']) {
            $rdata['invoice'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->value('invoice');
        } else {
            $rdata['invoice'] = Db::name('admin_set')->where('aid',aid)->value('invoice');
        }
        $rdata['detail'] = $detail;
        $rdata['shopset'] = $shopset;
        $rdata['storeinfo'] = $storeinfo;
        $rdata['lefttime'] = $lefttime;
        return $this->json($rdata);
    }
    //核销
    public function cycleorderHexiao(){
        $post = input('post.');
        $orderid = intval($post['id']);
        $order = Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
         
        if(!$order || !in_array($order['status'], [1,2]) ){
            return $this->json(['status'=>0,'msg'=>'订单状态不符合完成要求1']);
        }
        $cycle_order = Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->find();
        if(!$cycle_order ||  $cycle_order['freight_type'] !=1){
            return $this->json(['status'=>0,'msg'=>'订单状态不符合完成要求2']);
        }
        Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);

        $order_stage_count = Db::name('cycle_order_stage')
            ->where('status','in','0,1,2')
            ->where('orderid',$order['orderid'])
            ->count();
        if($order_stage_count == 0){
            Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->update(['status'=>3,'collect_time'=>time()]);

            $rs = \app\common\Order::collect($cycle_order, 'cycle');
            if($rs['status']==0) return $rs;
        }else{
            Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->update(['status'=>2]);
        }

        //发货信息录入 微信小程序+微信支付
        if($cycle_order['platform'] == 'wx' && $cycle_order['paytypeid'] == 2){
            \app\common\Order::wxShipping(aid,$cycle_order,'cycle');
        }

        \app\common\System::plog('周期购周期订单确认核销'.$orderid);
        return $this->json(['status'=>1,'msg'=>'订单完成']);
    }
    //确认收货
    public function cycleorderStageCollect(){
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();

        if(!$order || !in_array($order['status'], [2])){
            return $this->json(['status'=>0,'msg'=>'订单状态不符合完成要求']);
        }
        Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);


        $order_stage_count = Db::name('cycle_order_stage')
            ->where('status','in','0,1,2')
            ->where('orderid',$order['orderid'])
            ->count();
        if($order_stage_count == 0){
            Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->update(['status'=>3,'collect_time'=>time()]);

            $cycle_order = Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->find();
            $rs = \app\common\Order::collect($cycle_order, 'cycle');
            if($rs['status']==0) return $rs;
        }

        \app\common\System::plog('周期购周期订单确认收货'.$orderid);
        return $this->json(['status'=>1,'msg'=>'订单完成']);
    }
    //拼团订单
    public function luckycollageorder(){
        $st = input('param.st');
        if(!input('?param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        if($this->user['mdid']){
          $where[] = ['mdid', '=', $this->user['mdid']];
        }
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
        }elseif($st == '10'){
            $where[] = ['refund_status','>',0];
        }
        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('lucky_collage_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
        if(!$datalist) $datalist = array();
        foreach($datalist as $key=>$v){
            $datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
            if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
            if($v['buytpe']!=1) $datalist[$key]['team'] = Db::name('collage_order_team')->where('id',$v['teamid'])->find();
        }
        $rdata = [];
        $rdata['datalist'] = $datalist;
        $rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');
        $rdata['st'] = $st;
        return $this->json($rdata);
    }
    //拼团订单详情
    public function luckycollageorderdetail(){
        $detail = Db::name('lucky_collage_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
        if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        $detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
        $detail['formdata'] = \app\model\Freight::getformdata($detail['id'],'collage_order');
    
        $member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
        $detail['nickname'] = $member['nickname'];
        $detail['headimg'] = $member['headimg'];
    
        $storeinfo = [];
        if($detail['freight_type'] == 1){
            $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('name,address,longitude,latitude')->find();
        }
        
        if($detail['buytype'] != 1){
            $team = Db::name('lucky_collage_order_team')->where('id',$detail['teamid'])->find();
        }else{
            $team = [];
        }
        $peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
        if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
        $detail['canpeisong'] = ($detail['freight_type']==2 && $peisong_set['status']==1) ? true : false;
    
        if(getcustom('express_maiyatian')){
            $detail['myt_status']    = $peisong_set['myt_status']==1 ? true : false;
            $detail['myt_set']       = true;
            $detail['myt_shop']      = false;
            $detail['myt_shoplist']  = [];
            if($detail['myt_shop']){
                $detail['myt_shoplist']  = Db::name('peisong_myt_shop')->where('aid',aid)->where('bid',bid)->where('is_del',0)->order('id asc')->field('id,origin_id,name')->select()->toArray();
                if(!$detail['myt_shoplist']){
                    $detail['myt_shoplist']  = [['id'=>0,'origin_id'=>0,'name'=>'无门店可选择']];
                }
            }
        }

        $rdata = [];
        $rdata['detail'] = $detail;
        $rdata['team'] = $team;
        $rdata['storeinfo'] = $storeinfo;
        $rdata['expressdata'] = array_keys(express_data(['aid'=>aid,'bid'=>bid]));
    
        return $this->json($rdata);
    }
    //砍价订单
    public function kanjiaorder(){
        $st = input('param.st');
        if(!input('?param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        if($this->user['mdid']){
            $where[] = ['mdid', '=', $this->user['mdid']];
        }
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
        }elseif($st == '10'){
            $where[] = ['refund_status','>',0];
        }
        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('kanjia_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
        if(!$datalist) $datalist = array();
        foreach($datalist as $key=>$v){
            $datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
            if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
        }
        $rdata = [];
        $rdata['datalist'] = $datalist;
        $rdata['st'] = $st;
        return $this->json($rdata);
    }
    //砍价订单详情
    public function kanjiaorderdetail(){
        $detail = Db::name('kanjia_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
        if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        $detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
        $detail['formdata'] = \app\model\Freight::getformdata($detail['id'],'kanjia_order');

        $member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
        $detail['nickname'] = $member['nickname'];
        $detail['headimg'] = $member['headimg'];

        $storeinfo = [];
        if($detail['freight_type'] == 1){
            $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('name,address,longitude,latitude')->find();
        }
        
        $peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
        if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
        $detail['canpeisong'] = ($detail['freight_type']==2 && $peisong_set['status']==1) ? true : false;
        if(getcustom('express_maiyatian')){
            $detail['myt_status']    = $peisong_set['myt_status']==1 ? true : false;
            $detail['myt_set']       = true;
            $detail['myt_shop']      = false;
            $detail['myt_shoplist']  = [];
            if($detail['myt_shop']){
                $detail['myt_shoplist']  = Db::name('peisong_myt_shop')->where('aid',aid)->where('bid',bid)->where('is_del',0)->order('id asc')->field('id,origin_id,name')->select()->toArray();
                if(!$detail['myt_shoplist']){
                    $detail['myt_shoplist']  = [['id'=>0,'origin_id'=>0,'name'=>'无门店可选择']];
                }
            }
        }

        $rdata = [];
        $rdata['detail'] = $detail;
        $rdata['storeinfo'] = $storeinfo;
        $rdata['expressdata'] = array_keys(express_data(['aid'=>aid,'bid'=>bid]));

        return $this->json($rdata);
    }

    /**
     * 专门用于weightOrderFahuo.vue页面的订单详情
     * 支持GET请求获取详情和POST请求处理发货
     */
    public function weightOrderFahuo(){
        $method = request()->method();
        $id = input('param.id/d');
        
        // 确保返回格式正确
        $result = [
            'status' => 1,
            'msg' => 'success',
            'detail' => [],
            'prolist' => [],
            'storeinfo' => []
        ];
        
        try {
            if ($method == 'GET') {
                // GET请求：获取订单详情
                // 先判断是商城订单还是拼团订单
                $shopOrder = Db::name('shop_order')->where('id', $id)->where('aid', aid)->find();
                $collageOrder = Db::name('collage_order')->where('id', $id)->where('aid', aid)->find();
                
                $order = $shopOrder ? $shopOrder : ($collageOrder ? $collageOrder : null);
                
                if (!$order) {
                    return $this->json(['status' => 0, 'msg' => '订单不存在']);
                }
                
                // 格式化时间字段
                $order['createtime'] = $order['createtime'] ? date('Y-m-d H:i:s', $order['createtime']) : '';
                $order['collect_time'] = $order['collect_time'] ? date('Y-m-d H:i:s', $order['collect_time']) : '';
                $order['paytime'] = $order['paytime'] ? date('Y-m-d H:i:s', $order['paytime']) : '';
                $order['refund_time'] = $order['refund_time'] ? date('Y-m-d H:i:s', $order['refund_time']) : '';
                $order['send_time'] = $order['send_time'] ? date('Y-m-d H:i:s', $order['send_time']) : '';
                
                // 获取收货信息
                $storeinfo = [];
                if ($order['freight_type'] == 1) {
                    $storeinfo = Db::name('mendian')->where('id', $order['mdid'])->field('id,name,address,longitude,latitude')->find();
                }
                
                $prolist = [];
                if ($shopOrder) {
                    // 商城订单，获取商品列表
                    $prolist = Db::name('shop_order_goods')->where('orderid', $order['id'])->select()->toArray();
                    // 处理重量信息
                    foreach ($prolist as &$item) {
                        $item['real_sell_price'] = $item['sell_price'];
                        $item['real_total_weight'] = $item['total_weight'];
                    }
                } else {
                    // 拼团订单，构建商品列表
                    $prolist = [
                        [
                            'name' => $order['proname'],
                            'ggname' => $order['ggname'],
                            'num' => $order['num'],
                            'sell_price' => $order['sell_price'],
                            'total_weight' => 0,
                            'real_total_weight' => 0,
                            'real_sell_price' => $order['sell_price']
                        ]
                    ];
                }
                
                $result['detail'] = $order;
                $result['prolist'] = $prolist;
                $result['storeinfo'] = $storeinfo;
                
            } elseif ($method == 'POST') {
                // POST请求：处理发货
                $prolist = input('post.prolist/a', []);
                
                if (!$prolist) {
                    return $this->json(['status' => 0, 'msg' => '请提供商品信息']);
                }
                
                // 根据订单类型处理发货
                $shopOrder = Db::name('shop_order')->where('id', $id)->where('aid', aid)->find();
                $collageOrder = Db::name('collage_order')->where('id', $id)->where('aid', aid)->find();
                
                if ($shopOrder) {
                    // 商城订单发货逻辑
                    // 更新商品重量信息
                    foreach ($prolist as $item) {
                        Db::name('shop_order_goods')
                            ->where('id', $item['id'])
                            ->update([
                                'real_total_weight' => $item['real_total_weight'],
                                'real_sell_price' => $item['real_sell_price']
                            ]);
                    }
                    
                    // 更新订单状态为已发货
                    Db::name('shop_order')->where('id', $id)->update([
                        'status' => 2,
                        'send_time' => time()
                    ]);
                    
                } else {
                    // 拼团订单发货逻辑
                    Db::name('collage_order')->where('id', $id)->update([
                        'status' => 2,
                        'send_time' => time()
                    ]);
                }
                
                $result['msg'] = '发货成功';
                
            } else {
                // 不支持的请求方法
                return $this->json(['status' => 0, 'msg' => '不支持的请求方法']);
            }
            
        } catch (\Exception $e) {
            $result['status'] = 0;
            $result['msg'] = '操作失败: ' . $e->getMessage();
        }
        
        return $this->json($result);
    }

    /**
     * 专门用于weightOrderFahuo.vue页面的订单列表
     * 简化版实现，确保返回格式正确
     */
    public function weightOrderFahuoList(){
        // 简单直接的实现，确保返回格式正确
        $result = [
            'status' => 1,
            'msg' => 'success',
            'datalist' => [],
            'suppliers' => [], // 新增供货商列表
            'userinfo' => [] // 新增用户信息，供前端判断权限使用
        ];
        
        try {
            // 获取当前用户信息
            $memberLevelId = $this->member['levelid'] ?? 0;
            $memberLevelName = '';
            
            // 查询会员级别名称
            if ($memberLevelId > 0) {
                $memberLevel = Db::name('member_level')->where('aid', aid)->where('id', $memberLevelId)->find();
                if ($memberLevel) {
                    $memberLevelName = $memberLevel['name'] ?? '';
                }
            }
            
            $result['userinfo'] = [
                'id' => $this->member['id'] ?? 0,
                'user_type' => $this->member['user_type'] ?? 0,
                'type' => $this->member['type'] ?? '',
                'member_level' => $memberLevelId,
                'member_level_name' => $memberLevelName, // 新增会员级别名称
                'supplier_id' => $this->member['supplier_id'] ?? 0,
                'supplierid' => $this->member['supplierid'] ?? 0
            ];
            
            // 获取请求参数
            $st = input('param.st', 'all');
            $keyword = input('param.keyword', '');
            $pagenum = input('param.pagenum', 1);
            $supplierId = input('param.supplierId', 0); // 新增供货商筛选参数
            $pernum = 10;
            
            // 获取当前用户的供应商ID
            $userSupplierId = $this->member['supplier_id'] ?? $this->member['supplierid'] ?? 0;
            
            // 检查用户是否是系统管理员（仅需要levelid=9 智慧家长）
            $isAdmin = false;
            $levelId = $this->member['levelid'] ?? 0;
            
            if ($levelId == 9) {
                $isAdmin = true;
            }
            
            // 非管理员用户只能查看自己供应商的订单
            if (!$isAdmin && $userSupplierId > 0) {
                $supplierId = $userSupplierId;
            }
            
            // 查询供货商列表（从product_supplier表获取）
            $suppliers = Db::name('product_supplier')
                ->field('id, supplier_name as name')
                ->where('aid', aid)
                ->where('supplier_status', 1) // 只显示状态正常的供货商
                ->order('id desc')
                ->select()->toArray();
            $result['suppliers'] = $suppliers;
            
            // 1. 查询商城订单
            $shopQuery = Db::name('shop_order')
                ->where('aid', aid);
            
            // 供货商筛选
            if ($supplierId > 0) {
                // 筛选指定供货商的订单
                $shopOrderIds = Db::name('shop_order_goods')
                    ->alias('og')
                    ->join('shop_product p', 'og.proid = p.id')
                    ->where('og.aid', aid)
                    ->where('p.supplier_id', $supplierId)
                    ->column('og.orderid');
                
                if (!empty($shopOrderIds)) {
                    $shopQuery->where('id', 'in', $shopOrderIds);
                } else {
                    // 如果没有匹配的订单，设置一个不可能的条件
                    $shopQuery->where('id', 0);
                }
            } else if ($supplierId === 0) {
                // 筛选未指定供货商的订单
                $shopOrderIds = Db::name('shop_order_goods')
                    ->alias('og')
                    ->join('shop_product p', 'og.proid = p.id')
                    ->where('og.aid', aid)
                    ->where(function($query) {
                        $query->where('p.supplier_id', 0)->whereOr('p.supplier_id', null);
                    })
                    ->column('og.orderid');
                
                if (!empty($shopOrderIds)) {
                    $shopQuery->where('id', 'in', $shopOrderIds);
                } else {
                    // 如果没有匹配的订单，设置一个不可能的条件
                    $shopQuery->where('id', 0);
                }
            }
            // 当$supplierId === -1时，不进行筛选，返回所有订单
            
            // 状态过滤
            if ($st != 'all') {
                if ($st == '10') {
                    $shopQuery->where('refund_status', '>', 0);
                } else {
                    $shopQuery->where('status', intval($st));
                }
            }
            
            // 关键词搜索
            if ($keyword) {
                $shopQuery->where('ordernum|title', 'like', '%' . $keyword . '%');
            }
            
            // 执行查询
            $shopOrders = $shopQuery->order('id desc')
                ->page($pagenum, $pernum)
                ->select()->toArray();
            
            // 处理商品数据
            foreach($shopOrders as &$order) {
                $order['order_type'] = 'shop';
                // 简化商品获取
                $order['prolist'] = Db::name('shop_order_goods')
                    ->where('orderid', $order['id'])
                    ->select()->toArray();
                $order['procount'] = count($order['prolist']);
            }
            
            // 2. 查询拼团订单
            $collageQuery = Db::name('collage_order')
                ->where('aid', aid);
            
            // 供货商筛选
            if ($supplierId > 0) {
                // 筛选指定供货商的订单
                $collageQuery->where('supplier_id', $supplierId);
            } else if ($supplierId === 0) {
                // 筛选未指定供货商的订单
                $collageQuery->where(function($query) {
                    $query->where('supplier_id', 0)->whereOr('supplier_id', null);
                });
            }
            // 当$supplierId === -1时，不进行筛选，返回所有订单
            
            // 状态过滤
            if ($st != 'all') {
                if ($st == '10') {
                    $collageQuery->where('refund_status', '>', 0);
                } else {
                    $collageQuery->where('status', intval($st));
                }
            }
            
            // 关键词搜索
            if ($keyword) {
                $collageQuery->where('ordernum|title', 'like', '%' . $keyword . '%');
            }
            
            // 执行查询
            $collageOrders = $collageQuery->order('id desc')
                ->page($pagenum, $pernum)
                ->select()->toArray();
            
            // 处理商品数据（拼团订单是扁平化结构，直接包含商品信息）
            foreach($collageOrders as &$order) {
                $order['order_type'] = 'collage';
                // 直接使用订单表中的商品信息构建prolist
                $order['prolist'] = [
                    [
                        'name' => $order['proname'],
                        'ggname' => $order['ggname'],
                        'num' => $order['num'],
                        'total_weight' => 0, // 拼团订单可能没有重量信息
                        'real_total_weight' => 0
                    ]
                ];
                $order['procount'] = 1;
                
                // 获取拼团团队状态
                if ($order['teamid'] > 0) {
                    $team = Db::name('collage_order_team')->where('id', $order['teamid'])->find();
                    $order['team_status'] = $team['status'] ?? 0;
                } else {
                    $order['team_status'] = 0;
                }
            }
            
            // 合并订单
            $allOrders = array_merge($shopOrders, $collageOrders);
            
            // 按ID排序
            usort($allOrders, function($a, $b) {
                return $b['id'] - $a['id'];
            });
            
            $result['datalist'] = $allOrders;
            
        } catch (\Exception $e) {
            $result['status'] = 0;
            $result['msg'] = '获取订单失败: ' . $e->getMessage();
            $result['datalist'] = [];
        }
        
        return $this->json($result);
    }

    /**
     * 订单发货
     */
    public function sendExpress(){
        set_time_limit(0);
        ini_set('memory_limit', -1);
        $orderid = input('post.orderid/d');
        if($this->bid == 0){
            $order = Db::name('shop_order')->where('aid',$this->aid)->where('id',$orderid)->find();
        }else{
            $order = Db::name('shop_order')->where('aid',$this->aid)->where('bid',$this->bid)->where('id',$orderid)->find();
        }

        if(getcustom('supply_zhenxin')){
            if($order['issource'] == 1 && $order['source'] == 'supply_zhenxin'){
                return json(['status'=>0,'msg'=>'甄新汇选商品订单不允许发货']);
            }
        }
        $refundingMoney = Db::name('shop_refund_order')->where('orderid',$order['id'])->where('aid',$this->aid)->whereIn('refund_status',[1,4])->sum('refund_money');
        if($refundingMoney > 0) {
            return json(['status'=>0,'msg'=>'请先处理完进行中的退款单']);
        }

        //如果选择了配送时间，未到配送时间内不可以进行配送
        if(getcustom('business_withdraw') && ($this->auth_data == 'all' || in_array('OrderSendintime',$this->auth_data))){ 
            if($order['freight_time']){ 
                $freight_time = explode('~',$order['freight_time']);
                $begin_time = strtotime($freight_time[0]);
                $date = explode(' ',$freight_time[0]);
                $end_time =strtotime($date[0].' '.$freight_time[1]);
                if(time()<$begin_time || (time()>$end_time)){
                    return json(['status'=>0,'msg'=>'未在配送时间范围内']);
                }
            }
        }

        if($order['status']!=1 && $order['status']!=2){ 
            return json(['status'=>0,'msg'=>'该订单状态不允许发货']);
        }
        if($order['freight_type']==4){ 
            $member = Db::name('member')->where('id',$order['mid'])->find();
            $og = Db::name('shop_order_goods')->where('orderid',$order['id'])->find();
            $codelist = Db::name('shop_codelist')->where('proid',$og['proid'])->where('status',0)->order('id')->limit($og['num'])->select()->toArray();
            if(!$codelist){ 
                return json(['status'=>0,'msg'=>'卡密库存不足']);
            }
            if($codelist && count($codelist) >= $og['num']){ 
                $pscontent = [];
                foreach($codelist as $codeinfo){ 
                    $pscontent[] = $codeinfo['content'];
                    Db::name('shop_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$order['id'],'ordernum'=>$order['ordernum'],'headimg'=>$member['headimg'],'nickname'=>$member['nickname'],'buytime'=>time(),'status'=>1]);
                }
                $pscontent = implode("\r\n",$pscontent);
                Db::name('shop_order')->where('id',$order['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
                Db::name('shop_order_goods')->where('orderid',$order['id'])->update(['status'=>2]);
            }
            if($order['fromwxvideo'] == 1){ 
                \app\common\Wxvideo::deliverysend($orderid);
            }

            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){ 
                \app\common\Order::wxShipping($this->aid,$order);
            }
            if(getcustom('plug_zhiming')){ 
                \app\common\Order::collect($order);
                Db::name('shop_order')->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
                Db::name('shop_order_goods')->where('orderid',$orderid)->update(['status'=>3,'endtime'=>time()]);
            }
            $express_com = '卡密订单';
            $express_no = '卡密订单';
            //订单发货通知
            $tmplcontent = [];
            $tmplcontent['first'] = '您的订单已发货';
            $tmplcontent['remark'] = '请点击查看详情~';
            $tmplcontent['keyword1'] = $order['title'];
            $tmplcontent['keyword2'] = $express_com;
            $tmplcontent['keyword3'] = $express_no;
            $tmplcontent['keyword4'] = $order['linkman'].' '.$order['tel'];
            $tmplcontentNew = [];
            $tmplcontentNew['thing4'] = $order['title'];//商品名称
            $tmplcontentNew['thing13'] = $express_com;//快递公司
            $tmplcontentNew['character_string14'] = $express_no;//快递单号
            $tmplcontentNew['thing16'] = $order['linkman'].' '.$order['tel'];//收货人
            \app\common\Wechat::sendtmpl($this->aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
            //订阅消息
            $tmplcontent = [];
            $tmplcontent['thing2'] = $order['title'];
            $tmplcontent['thing7'] = $express_com;
            $tmplcontent['character_string4'] = $express_no;
            $tmplcontent['thing11'] = $order['address'];

            $tmplcontentnew = [];
            $tmplcontentnew['thing29'] = $order['title'];
            $tmplcontentnew['thing1'] = $express_com;
            $tmplcontentnew['character_string2'] = $express_no;
            $tmplcontentnew['thing9'] = $order['address'];
            \app\common\Wechat::sendwxtmpl($this->aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

            //短信通知
            $member = Db::name('member')->where('id',$order['mid'])->find();
            if($member['tel']){ 
                $tel = $member['tel'];
            }else{
                $tel = $order['tel'];
            }
            $rs = \app\common\Sms::send($this->aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>$express_com,'express_no'=>$express_no]);

            \app\common\System::plog('商城订单发货'.$orderid);
            return json(['status'=>1,'msg'=>'操作成功']);
        }
        $express_isbufen = 0;
        $expres_content = '';
        if($order['freight_type']==10){ 
            $pic = input('post.pic');
            $fhname = input('post.fhname');
            $fhaddress = input('post.fhaddress');
            $shname = input('post.shname');
            $shaddress = input('post.shaddress');
            $remark = input('post.remark');
            $data = [];
            $data['aid'] = $this->aid;
            $data['pic'] = $pic;
            $data['fhname'] = $fhname;
            $data['fhaddress'] = $fhaddress;
            $data['shname'] = $shname;
            $data['shaddress'] = $shaddress;
            $data['remark'] = $remark;
            $data['createtime'] = time();
            $id = Db::name('freight_type10_record')->insertGetId($data);
            $express_com = '货运托运';
            $express_no = $id;
        }else{
            $express_comArr = input('post.express_com/a');
            $express_noArr = input('post.express_no/a');
            $express_ogidsArr = input('post.express_ogids/a');

            $express_ogidsAll = [];
            if(count($express_comArr) > 1){ 
                $express_com = '多单发货';
                $express_no = '';
                $express_content = [];
                foreach($express_comArr as $k=>$v){ 
                    $express_content[] = ['express_com'=>$v,'express_no'=>$express_noArr[$k],'express_ogids'=>$express_ogidsArr[$k]];
                    if($express_ogidsArr[$k]){ 
                        foreach(explode(',',$express_ogidsArr[$k]) as $ogid){ 
                            $express_ogidsAll[] = $ogid;
                        }
                    }
                }
                $express_content = jsonEncode($express_content);
            }else{
                $express_com = $express_comArr[0];
                $express_no = $express_noArr[0];
                $express_ogids = $express_ogidsArr[0];
                foreach(explode(',',$express_ogidsArr[0]) as $ogid){ 
                    $express_ogidsAll[] = $ogid;
                }
            }

            $oglist = Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',$this->aid)->select()->toArray();
            if(count($oglist) > 1 && $express_ogidsAll){ 
                foreach($oglist as $og){ 
                    if(!in_array($og['id'],$express_ogidsAll)){ 
                        $express_isbufen = 1;
                    }
                }
            }
        }
        
        if($order['status']!=1){ //修改物流信息
            Db::name('shop_order')->where('aid',$this->aid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no,'express_ogids'=>$express_ogids,'express_content'=>$express_content,'express_isbufen'=>$express_isbufen]);
            return json(['status'=>1,'msg'=>'操作成功']);
        }

        Db::name('shop_order')->where('aid',$this->aid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no,'express_ogids'=>$express_ogids,'express_content'=>$express_content,'send_time'=>time(),'status'=>2,'express_isbufen'=>$express_isbufen]);
        Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',$this->aid)->update(['status'=>2]);
        
        if($order['fromwxvideo'] == 1){ 
            \app\common\Wxvideo::deliverysend($orderid);
        }
        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){ 
            \app\common\Order::wxShipping($this->aid,$order,'shop',['express_com'=>$express_comArr[0],'express_no'=>$express_noArr[0]]);
        }
        //支付宝小程序交易组件订单状态同步
        if($order['platform']=='alipay' && $order['paytypeid'] == 3){ 
            if(getcustom('alipay_plugin_trade') && $order['alipay_component_orderid']){ 
                $pluginResult = \app\common\Alipay::pluginOrderSend($orderid,'shop');
            }
        }

        if(getcustom('cefang') && $this->aid==2){ //定制1 订单对接 同步到策方
            $order['status'] = 2;
            \app\custom\Cefang::api($order);
        }

        //订单发货通知
        $tmplcontent = [];
        $tmplcontent['first'] = '您的订单已发货';
        $tmplcontent['remark'] = '请点击查看详情~';
        $tmplcontent['keyword1'] = $order['title'];
        $tmplcontent['keyword2'] = $express_com;
        $tmplcontent['keyword3'] = $express_no;
        $tmplcontent['keyword4'] = $order['linkman'].' '.$order['tel'];
        $tmplcontentNew = [];
        $tmplcontentNew['thing4'] = $order['title'];//商品名称
        $tmplcontentNew['thing13'] = $express_com;//快递公司
        $tmplcontentNew['character_string14'] = $express_no;//快递单号
        $tmplcontentNew['thing16'] = $order['linkman'].' '.$order['tel'];//收货人
        \app\common\Wechat::sendtmpl($this->aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
        //订阅消息
        $tmplcontent = [];
        $tmplcontent['thing2'] = $order['title'];
        $tmplcontent['thing7'] = $express_com;
        $tmplcontent['character_string4'] = $express_no;
        $tmplcontent['thing11'] = $order['address'];

        $tmplcontentnew = [];
        $tmplcontentnew['thing29'] = $order['title'];
        $tmplcontentnew['thing1'] = $express_com;
        $tmplcontentnew['character_string2'] = $express_no;
        $tmplcontentnew['thing9'] = $order['address'];
        \app\common\Wechat::sendwxtmpl($this->aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

        //短信通知
        $member = Db::name('member')->where('id',$order['mid'])->find();
        if($member['tel']){ 
            $tel = $member['tel'];
        }else{
            $tel = $order['tel'];
        }
        $rs = \app\common\Sms::send($this->aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>$express_com,'express_no'=>$express_no]);
        
        \app\common\System::plog('商城订单发货'.$orderid);
        return json(['status'=>1,'msg'=>'操作成功']);
    }
}