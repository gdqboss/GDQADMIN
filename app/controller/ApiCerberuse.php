<?php
// JK客户定制

// custom_file(lot_cerberuse)
namespace app\controller;
use think\facade\Db;
class ApiCerberuse extends ApiCommon
{   
    public function initialize(){
        parent::initialize();
        if(!getcustom('extend_qrcode')  || bid>0) showmsg('无访问权限');
    }
    
    public function getlist(){
        $pagenum = input('param.pagenum/d');
        if(!$pagenum) $pagenum = 1;
        $where =[];
        $where[] = ['status','=',1];
        if(input('param.keyword')){
            $where[] = ['title','like','%'.input('param.keyword').'%'];
        }
        $datalist = Db::name('cerberuse')->where('aid',aid)->page($pagenum,20)->where($where)->select()->toArray();
        if(!$datalist) $datalist = [];
        return $this->json(['status'=>1,'data'=>$datalist]);
    }
    
    //详情
    public function detail(){
        $id = input('param.id');
        $detail = Db::name('cerberuse')->where('aid',aid)->where('id',$id)->find();
        
        if(!$detail){
            return $this->json(['status' => 0, 'msg' => '设备不存在']);
        }
        $detail['pics'] = explode(',',$detail['pics']);
        $rdata = [];
        $rdata['status'] =1;
        $rdata['data'] = $detail;
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
    public function isgettime(){
        $date = input('param.date/t');
        if(strpos($date,'年') === false){
            $date = date('Y').'年'.$date;
        }
        $proid = input('param.proid/d');
         
        //获取设置
        $product = Db::name('cerberuse')->where('aid',aid)->where('device_id',$proid)->find();
        $product['pdprehour'] = 0;
        $timearr = [];
        $j=0;
        $nowdate =strtotime(date('H:i',time()))+$product['pdprehour']*60*60;
        for($i=strtotime($product['zaohour'].':00') ;$i<=strtotime($product['wanhour'].':00') ; $i=$i+60*$product['timejg']){
            $j++;
            $time =strtotime(preg_replace(['/年|月/','/日/'],['-',''],$date.' '.date("H:i",$i)));
            if($time<=$nowdate){
                $timearr[$j]['status'] = 0;
            }else{
                $timearr[$j]['status'] = 1;
            }
            $timearr[$j]['time'] = date("H:i",$i);
            $timearr[$j]['timeint'] = str_replace(':','',date("H:i",$i));
        }
        return $this->json(['status'=>1,'data'=>$timearr]);
    }
    //创建订单
    public function buy(){
        $id = input('param.proid');
        $product = Db::name('cerberuse')->where('aid',aid)->where('id',$id)->find();
        if(!$product ){
            return $this->json(['status' => 0, 'msg' => '产品不存在']);
        }
        //会员折扣
        $userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
        $userinfo = [];
        $userinfo['id'] = $this->member['id'];
        $userinfo['realname'] = $this->member['realname'];
        $userinfo['tel'] = $this->member['tel'];
        $userinfo['discount'] = $userlevel['discount'];

        $leveldk_money = 0;
        if( $userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
            $leveldk_money = $product['sell_price'] * (1 - $userlevel['discount'] * 0.1);
        }
        $userinfo['leveldk_money'] = round($leveldk_money,2);
        
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
        //
        $days  = 7;
        $next_year = strtotime('+'.$days.' days');
        $current_time = time();
        $years = [date('Y-m-d')];
        while($current_time < $next_year){
            $current_time = strtotime('+1 day', $current_time);
            $years[] = date('Y-m-d', $current_time);
        }
        $yytime = [
            $time1,
            $time2
        ] ;
        $rdata['status'] = 1;
        $rdata['yytime'] = $yytime;
        $rdata['years'] =   $years;
        
        $rdata['product'] =   $product;
        $rdata['userinfo'] =   $userinfo;
        return $this->json($rdata);
    }
    //创建订单
    public function createOrder(){
        $linkman = input('param.linkman');
        $tel = input('param.tel');
        $proid = input('param.proid');
        $couponrid = input('param.couponrid');
        $num = input('param.num');
        $start_time = input('param.start_time');
        $end_time = input('param.end_time');
        $time =  time();
        $product = Db::name('cerberuse')->where('aid',aid)->where('id',$proid)->find();
        if(!$product ){
            return $this->json(['status' => 0, 'msg' => '设备不存在']);
        }
        //计算小时
         if(!$start_time || !$end_time){
             return $this->json(['status' => 0, 'msg' => '请选择时间']);
         }
         if(strtotime($end_time) < strtotime($start_time)){
             return $this->json(['status' => 0, 'msg' => '结束时间小于开始时间']);
         }
         //判断是否该时间段是否被预约
         $s_start_time = strtotime($start_time);
         $s_end_time = strtotime($end_time);
         $h_where = [];
         $h_where[] = ['aid','=',aid];
         $h_where[] = ['status','in',[0,1,2,3]];
         $h_where[] = ['proid','=',$proid];
         $h_where[] = Db::raw(" (starttime <= ".$s_start_time." and endtime >= ".$s_start_time." ) or (starttime <= ".$s_end_time." and endtime >= ".$s_end_time." )");
         $ishave = Db::name('cerberuse_order')->where($h_where)->find();
        if($ishave){
            return $this->json(['status' => 0, 'msg' => date('Y-m-d H:i',$ishave['starttime']).' 至 '.date('Y-m-d H:i',$ishave['endtime']).' 已被预约']);
        }
        $product_price = $totalprice = $product['price'] * $num;
        $userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
        $leveldk_money = 0;
        if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
            $leveldk_money = $totalprice * (1 - $userlevel['discount'] * 0.1);
        }
        $totalprice = $totalprice - $leveldk_money;
        //优惠券
        if($couponrid > 0){
            $couponrid = $couponrid;
            $bid = $product['bid'];
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
            $fwscene = [0];
            if(!in_array($couponinfo['fwscene'],$fwscene)){//全部可用 
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'超出可用范围']);
            }
            //0全场通用,4指定服务商品
            if(!in_array($couponinfo['fwtype'],[0])){
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
        
        $ordernum = \app\common\Common::generateOrderNo(aid);
        //二维码数据

        $qrcode_url = createqrcode($ordernum);
        $data = [
            'aid' => aid,
            'bid' => bid,
            'mid' => mid,
            'title' => $product['title'],
            'ordernum' => $ordernum,
            'price' =>$product['price'],
            'leveldek_money' => $leveldk_money,
            'product_price' => $product_price,
            'totalprice' => $totalprice,
            'starttime' =>  strtotime($start_time),
            'endtime' => strtotime($end_time),
            'time_length' => $num,
            'createtime' => time(),
            'qrcode_url' =>$qrcode_url,
            'linkman' => $linkman,
            'tel' => $tel,
            'proid' => $proid,
            'device_id' => $product['device_id'],
            'coupon_money' =>$coupon_money
        ];
        $orderid = Db::name('cerberuse_order')->insertGetId($data);
        $payorderid = \app\model\Payorder::createorder(aid,0,mid,'cerberuse',$orderid,$ordernum,'智能开门',$totalprice,0);
        return $this->json(['status'=>1,'payorderid'=>$payorderid,'msg'=>'提交成功']);
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

        $datalist = Db::name('cerberuse_order')->where($where)->order('id desc')->page($pagenum,10)->select()->toArray();
        if(!$datalist) $datalist = [];
        foreach($datalist as $key=>$v){
            if($v['bid']!=0){
                $datalist[$key]['binfo'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->field('id,name,logo')->find();
            }
            $datalist[$key]['senddate'] = date('Y-m-d H:i:s',$v['send_time']);
            $product = Db::name('cerberuse')->where('aid',aid)->where('id',$v['proid'])->field('id,pic')->find();
            $datalist[$key]['product'] = $product;
        }

        $rdata = [];
        $rdata['st'] = $st;
        $rdata['datalist'] = $datalist;
        return $this->json($rdata);
    }

    public function orderdetail(){
        $detail = Db::name('cerberuse_order')->where('id',input('param.id/d'))->where('aid',aid)->where('mid',mid)->find();
        if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
         
        $detail['starttime'] = $detail['starttime'] ? date('Y-m-d H:i',$detail['starttime']) : '';
        $detail['endtime'] = $detail['endtime'] ? date('Y-m-d H:i',$detail['endtime']) : '';
        
        $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        $detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
        $detail['formdata'] = \app\model\Freight::getformdata($detail['id'],'cerberuse_order');
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

        $product = Db::name('cerberuse')->where('aid',aid)->where('id',$detail['proid'])->field('id,title,pic')->find();
        $detail['title'] = $product['title'];
        $detail['pic'] = $product['pic'];
        $rdata = [];
        $rdata['detail'] = $detail;
        $rdata['iscommentdp'] = $iscommentdp;
        $rdata['storeinfo'] = $storeinfo;
        $rdata['product'] = $product;
        //发票
        $rdata['invoice'] = 0;
        if($detail['bid']) {
            $rdata['invoice'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->value('invoice');
        } else {
            $rdata['invoice'] = Db::name('admin_set')->where('aid',aid)->value('invoice');
        }
        return $this->json($rdata);
    }
    function closeOrder(){
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('cerberuse_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
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
        $rs = Db::name('cerberuse_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>4]);
        return $this->json(['status'=>1,'msg'=>'取消成功']);
    }
    function delOrder(){
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('cerberuse_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
        if(!$order || ($order['status']!=4 && $order['status']!=3)){
            return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
        }
        if($order['status']==3){
            $rs = Db::name('cerberuse_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['delete'=>1]);
        }else{
            $rs = Db::name('cerberuse_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->delete();
        }
        return $this->json(['status'=>1,'msg'=>'删除成功']);
    }

    public function getRefundMoney(){
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('cerberuse_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
        $refund_money = $order['totalprice'];
        $refund_remark = '';
        return $this->json(['refund_money' => $refund_money,'refund_remark' => $refund_remark]);
    }
    function refund(){//申请退款
        $this->checklogin();
        if(request()->isPost()){
            $post = input('post.');
            $orderid = intval($post['orderid']);
            $money = floatval($post['money']);
            $order = Db::name('cerberuse_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
            
            if(!$order || ($order['status']!=1 ) || $order['refund_status'] == 2){
                return $this->json(['status'=>0,'msg'=>'订单状态不符合退款要求']);
            }
            if($money < 0 || $money > $order['totalprice']){
                return $this->json(['status'=>0,'msg'=>'退款金额有误']);
            }

            Db::name('cerberuse_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['refund_time'=>time(),'refund_status'=>1,'refund_reason'=>$post['reason'],'refund_money'=>$money,'refund_checkremark' =>$post['refund_remark']]);
            return $this->json(['status'=>1,'msg'=>'提交成功,请等待商家审核']);
        }
        $rdata = [];
        $rdata['price'] = input('param.price/f');
        $rdata['orderid'] = input('param.orderid/d');
        $order = Db::name('cerberuse_order')->where('aid',aid)->where('mid',mid)->where('id',$rdata['orderid'])->find();
        $rdata['price'] = $order['totalprice'];
        return $this->json($rdata);
    }
}