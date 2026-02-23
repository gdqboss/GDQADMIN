<?php
namespace app\controller;
use think\facade\Db;

class ApiCollage extends ApiCommon
{
    public function product(){
        try {
            $proid = input('param.id/d');
            $where = [];
            $where[] = ['id', '=', $proid];
            $where[] = ['aid', '=', $this->aid];
            
            $product = Db::name('collage_product')
                ->where($where)
                ->find();
            if(!$product) {
                return $this->json(['status'=>0,'msg'=>'商品不存在']);
            }
            
            if($product['status'] == 0) {
                return $this->json(['status'=>0,'msg'=>'商品已下架']);
            }
            
            if($product['ischecked'] != 1) {
                return $this->json(['status'=>0,'msg'=>'商品未审核']);
            }

            if(!$product['pics']) {
                $product['pics'] = $product['pic'];
            }
            $product['pics'] = explode(',', $product['pics']);
            
            if($product['fuwupoint']) {
                $product['fuwupoint'] = explode(' ', preg_replace("/\s+/", ' ', str_replace('　', ' ', trim($product['fuwupoint']))));
            }

            // 获取规格列表 - 从collage_guige表获取真实规格数据
            $guigelist = array();
            // 从collage_guige表获取完整的规格数据
            $guigeDataFromTable = Db::name('collage_guige')
                ->where('aid', $this->aid)
                ->where('proid', $product['id'])
                ->select()
                ->toArray();
            
            // 处理规格数据 - 从collage_guige表获取后构建
            $guigedata = json_decode($product['guigedata'], true);
            $ggselected = [];
            if ($guigedata) {
                foreach($guigedata as $v) {
                    $ggselected[] = 0;
                }
            } else {
                // 如果没有规格数据，使用默认的0作为ks值
                $ggselected = [0];
                $guigedata = [];
            }
            
            if (!empty($guigeDataFromTable)) {
                // 如果有规格数据，使用collage_guige表的数据
                // 初始化一个临时数组，用于存储规格数据
                $tempGuigeList = [];
                foreach ($guigeDataFromTable as $key => $gg) {
                    $tempGuigeList[] = [
                        'id' => $gg['id'],
                        'name' => $gg['name'],
                        'goods_sn' => $gg['goods_sn'],
                        'market_price' => $gg['market_price'],
                        'sell_price' => $gg['sell_price'],
                        'leader_price' => $gg['leader_price'],
                        'leader_commission_rate' => $gg['leader_commission_rate'],
                        'weight' => $gg['weight'],
                        'stock' => $gg['stock'],
                        'pic' => $gg['pic'],
                        'proid' => $product['id'],
                    ];
                }
                
                // 根据guigedata生成正确的ks字符串作为索引
                if (!empty($guigedata)) {
                    // 计算所有可能的规格组合
                    $combinations = [[]];
                    foreach ($guigedata as $group) {
                        $temp = [];
                        foreach ($combinations as $comb) {
                            foreach ($group['items'] as $item) {
                                $newComb = $comb;
                                $newComb[] = $item['k'];
                                $temp[] = $newComb;
                            }
                        }
                        $combinations = $temp;
                    }
                    
                    // 为每个组合生成ks字符串，并将其作为guigelist的索引
                    foreach ($combinations as $index => $comb) {
                        $ks = implode(',', $comb);
                        if (isset($tempGuigeList[$index])) {
                            $guigelist[$ks] = $tempGuigeList[$index];
                            $guigelist[$ks]['ks'] = $ks;
                        }
                    }
                } else {
                    // 如果没有guigedata，使用默认的'0'作为索引
                    if (isset($tempGuigeList[0])) {
                        $guigelist['0'] = $tempGuigeList[0];
                        $guigelist['0']['ks'] = '0';
                    }
                }
            } else {
                // 如果没有规格数据，使用商品自身作为规格
                $guigelist['0'] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'ks' => '0',
                    'goods_sn' => $product['goods_sn'],
                    'market_price' => $product['market_price'],
                    'sell_price' => $product['sell_price'],
                    'leader_price' => $product['leader_price'],
                    'leader_commission_rate' => $product['leader_commission_rate'],
                    'weight' => $product['weight'],
                    'stock' => $product['stock'],
                    'pic' => $product['pic'],
                    'proid' => $product['id'],
                ];
            }

            // 生成ks字符串
            $ks = implode(',', $ggselected);

            // 获取评论
            $commentlist = Db::name('collage_comment')
                ->where('aid', $this->aid)
                ->where('proid', $proid)
                ->where('status', 1)
                ->limit(10)
                ->select()
                ->toArray();
            if(!$commentlist) $commentlist = [];
            foreach($commentlist as $k=>$pl) {
                $commentlist[$k]['createtime'] = date('Y-m-d H:i', $pl['createtime']);
                if($commentlist[$k]['content_pic']) {
                    $commentlist[$k]['content_pic'] = explode(',', $commentlist[$k]['content_pic']);
                }
            }
            $commentcount = Db::name('collage_comment')
                ->where('aid', $this->aid)
                ->where('proid', $proid)
                ->where('status', 1)
                ->count();

            // 正在拼团的
            $teamCount = Db::name('collage_order_team')
                ->where('aid', $this->aid)
                ->where('proid', $product['id'])
                ->where('status', 1)
                ->count();
                
            $teamList = Db::name('collage_order_team')
                ->where('aid', $this->aid)
                ->where('proid', $product['id'])
                ->where('status', 1)
                ->limit(10)
                ->select()
                ->toArray();
                
            if($teamList) {
                foreach($teamList as $k=>$o) {
                    $order = Db::name('collage_order')
                        ->where('aid', $this->aid)
                        ->where('teamid', $o['id'])
                        ->where('buytype', 2)
                        ->find();
                        
                    if($order) {
                        $member = Db::name('member')
                            ->field('nickname, headimg')
                            ->where('id', $order['mid'])
                            ->find();
                            
                        $teamList[$k]['nickname'] = $member['nickname'];
                        $teamList[$k]['headimg'] = $member['headimg'];
                    }
                    
                    // 处理拼团时间
                    $teamList[$k]['start_time'] = date('Y-m-d H:i:s', $o['createtime']);
                    $teamList[$k]['end_time'] = date('Y-m-d H:i:s', $o['createtime'] + $o['teamhour'] * 3600);
                    $teamList[$k]['now_time'] = time();
                    $teamList[$k]['remain_time'] = max(0, $o['createtime'] + $o['teamhour'] * 3600 - time()); // 剩余时间（秒）
                }
            }

            // 检查是否收藏
            $rs = Db::name('member_favorite')
                ->where('aid', $this->aid)
                ->where('mid', $this->mid)
                ->where('proid', $proid)
                ->where('type', 'collage')
                ->find();
            $isfavorite = $rs ? true : false;

            // 添加浏览历史
            if($this->member) {
                $rs = Db::name('member_history')
                    ->where(array(
                        'aid' => $this->aid,
                        'mid' => $this->mid,
                        'proid' => $proid,
                        'type' => 'collage'
                    ))
                    ->find();
                if($rs) {
                    Db::name('member_history')
                        ->where(array('id' => $rs['id']))
                        ->update(['createtime' => time()]);
                } else {
                    Db::name('member_history')
                        ->insert(array(
                            'aid' => $this->aid,
                            'mid' => $this->mid,
                            'proid' => $proid,
                            'type' => 'collage',
                            'createtime' => time()
                        ));
                }
            }

            // 获取系统设置
            $shopset = Db::name('collage_sysset')
                ->field('comment, showjd')
                ->where('aid', $this->aid)
                ->find();
                
            $sysset = Db::name('admin_set')
                ->field('name, logo, desc, tel, kfurl')
                ->where('aid', $this->aid)
                ->find();

            // 处理商品详情内容
            $product['detail'] = \app\common\System::initpagecontent($product['detail'], $this->aid, $this->mid, input('param.platform', 'wx'));
            $product['comment_starnum'] = floor($product['comment_score']);

            // 获取商家信息
            if($product['bid'] != 0) {
                $business = Db::name('business')
                    ->where('aid', $this->aid)
                    ->where('id', $product['bid'])
                    ->field('id, aid, cid, name, logo, desc, tel, address, sales, kfurl')
                    ->find();
                if(!$business) {
                    return $this->json(['status'=>0,'msg'=>'商家不存在']);
                }
            } else {
                $business = $sysset;
            }

            // 展示成团信息，开启不退款的去掉
            $product['show_teamnum'] = 1;
            // 关闭单独购买
            $product['isopen_buy'] = 1;

            // 返回数据
            $rdata = array();
            $rdata['status'] = 1;
            $rdata['product'] = $product;
            $rdata['business'] = $business;
            $rdata['guigelist'] = $guigelist;
            $rdata['guigedata'] = $guigedata;
            $rdata['ggselected'] = $ggselected;
            $rdata['ks'] = $ks;
            $rdata['commentlist'] = $commentlist;
            $rdata['commentcount'] = $commentcount;
            $rdata['shopset'] = $shopset;
            $rdata['sysset'] = $sysset;
            $rdata['teamCount'] = $teamCount;
            $rdata['teamList'] = $teamList;
            $rdata['nowtime'] = time();
            $rdata['isfavorite'] = $isfavorite;

            return $this->json($rdata);
        } catch (\Exception $e) {
            // 记录详细错误信息
            $error_info = array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'params' => $_REQUEST,
                'time' => date('Y-m-d H:i:s')
            );

            // 写入错误日志到文件
            file_put_contents(
                'd:\\Adev\\gdqshop.cn\\runtime\\error_log.txt',
                json_encode($error_info, JSON_UNESCAPED_UNICODE) . "\n",
                FILE_APPEND
            );

            return $this->json([
                'status' => 0,
                'msg' => '系统异常，请稍后重试',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function buy(){
        try {
            $this->checklogin();
            $proid = input('param.proid/d');
            $ggid = input('param.ggid/d');
            $totalnum = input('param.num/d');
            if(!$totalnum) $totalnum = 1;
            $buytype = input('param.buytype/d');

            $product = Db::name('collage_product')
                ->where('aid', $this->aid)
                ->where('status', 1)
                ->where('ischecked', 1)
                ->where('id', $proid)
                ->find();
            if(!$product){
                return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
            }

            // 从collage_guige表获取规格数据
            $guige = Db::name('collage_guige')
                ->where('aid', $this->aid)
                ->where('id', $ggid)
                ->find();
            
            // 如果没有找到规格数据，检查是否是使用商品自身作为规格
            if(!$guige){
                // 检查ggid是否等于商品id（商品自身作为规格的情况）
                if($ggid == $product['id']) {
                    // 使用商品自身作为规格
                    $guige = [
                        'id' => $product['id'],
                        'name' => $product['name'],
                        'proid' => $product['id'],
                        'stock' => $product['stock'],
                        'sell_price' => $product['sell_price'],
                        'market_price' => $product['market_price'],
                        'leader_price' => $product['leader_price'],
                        'leader_commission_rate' => $product['leader_commission_rate'],
                        'weight' => $product['weight'],
                        'cost_price' => $product['cost_price'],
                        'sales' => $product['sales'],
                        'pic' => $product['pic']
                    ];
                } else {
                    return $this->json(['status'=>0,'msg'=>'产品该规格不存在或已下架']);
                }
            }

            if($guige['stock'] < $totalnum){
                return $this->json(['status'=>0,'msg'=>'库存不足']);
            }

            $isZunGuiKehu = Db::name('member_level')
                ->where('aid', $this->aid)
                ->where('id', $this->member['levelid'])
                ->value('name') == '尊贵客户';
            if($isZunGuiKehu && $product['buymax'] > 0){
                $buynum = $totalnum + Db::name('collage_order')
                    ->where('aid', $this->aid)
                    ->where('mid', $this->mid)
                    ->where('proid', $proid)
                    ->where('status', 'in', '0,1,2,3')
                    ->sum('num');
                if($buynum > $product['buymax']){
                    return $this->json(['status'=>0,'msg'=>'每人限购'.$product['buymax'].'件']);
                }
            }

            $bid = $product['bid'];
            if($bid != 0){
                $business = Db::name('business')
                    ->where('id', $bid)
                    ->field('id,aid,cid,name,logo,tel,address,sales,longitude,latitude')
                    ->find();
            }else{
                $business = Db::name('admin_set')
                    ->where('aid', $this->aid)
                    ->field('id,name,logo,desc,tel')
                    ->find();
            }

            $product_price = $guige['sell_price'] * $totalnum;
            $totalweight = $guige['weight'] * $totalnum;

            // 处理运费
            if($product['freighttype'] == 0){
                $fids = explode(',', $product['freightdata']);
                $freightList = \app\model\Freight::getList([
                    ['status', '=', 1],
                    ['aid', '=', $this->aid],
                    ['bid', '=', $bid],
                    ['id', 'in', $fids]
                ]);
                // 确保每个配送方式都有名称
                foreach($freightList as &$item) {
                    if(empty($item['name'])) {
                        $item['name'] = $item['pstype'] == 1 ? '到店消费' : '快递配送';
                    }
                }
                unset($item);
            }elseif($product['freighttype'] == 3 || $product['freighttype'] == 4){
                $freightList = [
                    [
                        'id' => 0,
                        'name' => ($product['freighttype'] == 3 ? '自动发货' : '在线卡密'),
                        'pstype' => $product['freighttype']
                    ]
                ];
            }else{
                $freightList = \app\model\Freight::getList([
                    ['status', '=', 1],
                    ['aid', '=', $this->aid],
                    ['bid', '=', $bid]
                ]);
                // 确保每个配送方式都有名称
                foreach($freightList as &$item) {
                    if(empty($item['name'])) {
                        $item['name'] = $item['pstype'] == 1 ? '到店消费' : '快递配送';
                    }
                }
                unset($item);
            }

            $havetongcheng = 0;
            foreach($freightList as $k=>$v){
                if($v['pstype'] == 2){
                    // 同城配送
                    $havetongcheng = 1;
                }
            }

            if($havetongcheng){
                $address = Db::name('member_address')
                    ->where('aid', $this->aid)
                    ->where('mid', $this->mid)
                    ->where('latitude', '>', 0)
                    ->order('isdefault desc,id desc')
                    ->find();
            }else{
                $address = Db::name('member_address')
                    ->where('aid', $this->aid)
                    ->where('mid', $this->mid)
                    ->order('isdefault desc,id desc')
                    ->find();
            }
            if(!$address) $address = [];

            $needLocation = 0;

            $rs = \app\model\Freight::formatFreightList($freightList, $address, $product_price, $totalnum, $totalweight);
            $freightList = $rs['freightList'];
            $freightArr = $rs['freightArr'];
            if($rs['needLocation'] == 1) $needLocation = 1;

            // 处理用户信息
            $userlevel = Db::name('member_level')
                ->where('aid', $this->aid)
                ->where('id', $this->member['levelid'])
                ->find();
            $adminset = Db::name('admin_set')
                ->where('aid', $this->aid)
                ->find();

            $userinfo = [];
            $userinfo['discount'] = $userlevel['discount'];
            $userinfo['score'] = $this->member['score'];
            $userinfo['score2money'] = $adminset['score2money'];
            $userinfo['scoredk_money'] = round($userinfo['score'] * $userinfo['score2money'], 2);
            $userinfo['scoredkmaxpercent'] = $adminset['scoredkmaxpercent'];
            $userinfo['scoremaxtype'] = 0; // 积分抵扣按照系统设置抵扣
            $userinfo['realname'] = $this->member['realname'];
            $userinfo['tel'] = $this->member['tel'];

            // 计算价格
            $totalprice = $product_price;
            $leadermoney = 0;
            if($buytype == 2 && $product['leadermoney'] > 0){
                $leadermoney = $product['leadermoney'];
            }
            $totalprice = $totalprice - $leadermoney;

            $leveldk_money = 0;
            if($userlevel && $userlevel['discount'] > 0 && $userlevel['discount'] < 10){
                $leveldk_money = $product_price * (1 - $userlevel['discount'] * 0.1);
            }
            $leveldk_money = round($leveldk_money, 2);
            $totalprice = $totalprice - $leveldk_money;

            // 处理优惠券
            $couponList = [];
            if($bid > 0){
                $business = Db::name('business')
                    ->where('aid', $this->aid)
                    ->where('id', $bid)
                    ->find();
                $bcids = $business['cid'] ? explode(',', $business['cid']) : [];
            }else{
                $bcids = [];
            }

            if($bcids){
                $whereCid = [];
                foreach($bcids as $bcid){
                    $whereCid[] = "find_in_set({$bcid}, canused_bcids)";
                }
                $whereCids = implode(' or ', $whereCid);
            }else{
                $whereCids = '0=1';
            }

            $couponList = Db::name('coupon_record')
                ->where('aid', $this->aid)
                ->where('mid', $this->mid)
                ->where('type', 'in', '1,4')
                ->where('status', 0)
                ->whereRaw("bid=-1 or bid=".$bid." or (bid=0 and (canused_bids='all' or find_in_set(".$bid.", canused_bids) or (".$whereCids.")))")
                ->where('minprice', '<=', $totalprice)
                ->where('starttime', '<=', time())
                ->where('endtime', '>', time())
                ->order('id desc')
                ->select()
                ->toArray();

            if($couponList){
                foreach($couponList as $k=>$v){
                    $couponinfo = Db::name('coupon')
                        ->where('aid', $this->aid)
                        ->where('id', $v['couponid'])
                        ->find();
                    if(empty($couponinfo) || $couponinfo['fwtype'] !== 0 || $couponinfo['isgive'] == 2 || $couponinfo['fwscene'] !== 0){
                        unset($couponList[$k]);
                    }
                }
                $couponList = array_values($couponList);
            }

            // 处理返回数据
            $rdata = array();
            $rdata['havetongcheng'] = $havetongcheng;
            $rdata['status'] = 1;
            $rdata['address'] = $address;
            $rdata['linkman'] = $address ? $address['name'] : strval($userinfo['realname']);
            $rdata['tel'] = $address ? $address['tel'] : strval($userinfo['tel']);

            if(!$rdata['linkman']){
                $lastorder = Db::name('collage_order')
                    ->where('aid', $this->aid)
                    ->where('mid', $this->mid)
                    ->where('linkman', '<>', '')
                    ->find();
                if($lastorder){
                    $rdata['linkman'] = $lastorder['linkman'];
                    $rdata['tel'] = $lastorder['tel'];
                }
            }

            $rdata['product'] = $product;
            $rdata['guige'] = $guige;
            $rdata['business'] = $business;
            $rdata['freightList'] = $freightList;
            $rdata['freightArr'] = $freightArr;
            $rdata['userinfo'] = $userinfo;
            $rdata['couponList'] = $couponList;
            $rdata['buytype'] = $buytype;
            $rdata['totalnum'] = $totalnum;
            $rdata['leadermoney'] = $leadermoney;
            $rdata['product_price'] = $product_price;
            $rdata['leveldk_money'] = $leveldk_money;
            $rdata['needLocation'] = $needLocation;
            $rdata['scorebdkyf'] = Db::name('admin_set')
                ->where('aid', $this->aid)
                ->value('scorebdkyf');

            return $this->json($rdata);
        } catch (\Exception $e) {
            // 记录详细错误信息
            $error_info = array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'params' => $_REQUEST,
                'time' => date('Y-m-d H:i:s')
            );

            // 写入错误日志到文件
            file_put_contents(
                'd:\\Adev\\gdqshop.cn\\runtime\\error_log.txt',
                json_encode($error_info, JSON_UNESCAPED_UNICODE) . "\n",
                FILE_APPEND
            );

            return $this->json([
                'status' => 0,
                'msg' => '系统异常',
                'error' => $e->message
            ]);
        }
    }

    public function createOrder(){
        try {
            $this->checklogin();
            $post = input('post.');
            
            // 1. 基本参数验证
            if(empty($post['proid']) || empty($post['ggid'])){
                return $this->json(['status'=>0,'msg'=>'产品数据错误']);
            }
            
            $proid = $post['proid'];
            $ggid = $post['ggid'];
            $num = intval($post['num'] ? $post['num'] : 1);
            $buytype = $post['buytype'];
            $teamid = $post['teamid'];
            $order_pay_type = isset($post['order_pay_type']) ? intval($post['order_pay_type']) : 0;
            
            if($num <= 0) {
                return $this->json(['status'=>0,'msg'=>'产品数量错误']);
            }
            
            // 2. 验证商品和规格
            $product = Db::name('collage_product')
                ->where('aid', $this->aid)
                ->where('status', 1)
                ->where('ischecked', 1)
                ->where('id', $proid)
                ->find();
            
            if(!$product) {
                return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
            }
            
            // 从collage_guige表获取规格数据
            $guige = Db::name('collage_guige')
                ->where('aid', $this->aid)
                ->where('id', $ggid)
                ->find();
            
            // 如果没有找到规格数据，检查是否是使用商品自身作为规格
            if(!$guige) {
                // 检查ggid是否等于商品id（商品自身作为规格的情况）
                if($ggid == $product['id']) {
                    // 使用商品自身作为规格
                    $guige = [
                        'id' => $product['id'],
                        'name' => $product['name'],
                        'proid' => $product['id'],
                        'stock' => $product['stock'],
                        'sell_price' => $product['sell_price'],
                        'market_price' => $product['market_price'],
                        'leader_price' => $product['leader_price'],
                        'leader_commission_rate' => $product['leader_commission_rate'],
                        'weight' => $product['weight'],
                        'cost_price' => $product['cost_price'],
                        'sales' => $product['sales'],
                        'pic' => $product['pic']
                    ];
                } else {
                    return $this->json(['status'=>0,'msg'=>'产品规格不存在或已下架']);
                }
            }
            
            if($guige['stock'] < $num) {
                return $this->json(['status'=>0,'msg'=>'库存不足']);
            }
            
            // 3. 尊贵客户限购检查
            $isZunGuiKehu = Db::name('member_level')
                ->where('aid', $this->aid)
                ->where('id', $this->member['levelid'])
                ->value('name') == '尊贵客户';
            
            if($isZunGuiKehu && $product['buymax'] > 0) {
                $mybuycount = $num + Db::name('collage_order')
                    ->where('aid', $this->aid)
                    ->where('proid', $product['id'])
                    ->where('mid', $this->mid)
                    ->where('status', 'in', '0,1,2,3')
                    ->sum('num');
                
                if($mybuycount > $product['buymax']) {
                    return $this->json(['status'=>0,'msg'=>'每人限购'.$product['buymax'].'件']);
                }
            }
            
            // 4. 参团判断
            $tuan = array();
            if($buytype == 3) {
                $tuan = Db::name('collage_order_team')
                    ->where('aid', $this->aid)
                    ->where('id', $teamid)
                    ->find();
                
                if(!$tuan || $tuan['status'] == 0) {
                    return $this->json(['status'=>0,'msg'=>'没有找到该团']);
                }
                
                if($tuan['status'] == 3) {
                    return $this->json(['status'=>0,'msg'=>'该团已失败']);
                }
                
                if($tuan['status'] == 2 || $tuan['num'] >= $tuan['teamnum']) {
                    return $this->json(['status'=>0,'msg'=>'该团已满员']);
                }
                
                $hasJoined = Db::name('collage_order')
                    ->where('aid', $this->aid)
                    ->where('teamid', $teamid)
                    ->where('mid', $this->mid)
                    ->where('status', '>', 0)
                    ->find();
                
                if($hasJoined) {
                    return $this->json(['status'=>0,'msg'=>'您已经参与该团了']);
                }
            }
            
            // 5. 团长开团类型检查
            if($buytype == 2 && !$order_pay_type) {
                return $this->json(['status'=>0,'msg'=>'请选择类型']);
            }
            
            // 6. 价格计算
            $bid = $product['bid'];
            $leader_price = isset($guige['leader_price']) ? floatval($guige['leader_price']) : 0;
            $leader_commission_rate = isset($guige['leader_commission_rate']) ? floatval($guige['leader_commission_rate']) : 0;
            $collage_price = $guige['sell_price'];
            $market_price = $guige['market_price'];
            
            // 计算商品基础价格
            switch($buytype) {
                case 2: // 团长购买
                    $product_price = $leader_price * $num;
                    break;
                case 3: // 团员购买
                    $product_price = $collage_price * $num;
                    break;
                default: // 单独购买
                    $product_price = $market_price * $num;
                    break;
            }
            
            // 7. 初始化变量
            $leadermoney = 0;
            $leveldk_money = 0;
            $scoredk_money = 0;
            $scoredkscore = 0;
            $coupon_money = 0;
            $freight_price = 0;
            $weight = 0;
            $givescore = 0;
            
            // 8. 处理收货地址
            if(empty($post['addressid'])) {
                $address = array(
                    'id' => 0,
                    'name' => $post['linkman'] ? $post['linkman'] : '',
                    'tel' => $post['tel'] ? $post['tel'] : '',
                    'area' => '',
                    'address' => '',
                    'province' => '',
                    'city' => '',
                    'district' => '',
                    'longitude' => '',
                    'latitude' => ''
                );
            } else {
                $address = Db::name('member_address')
                    ->where('id', $post['addressid'])
                    ->where('aid', $this->aid)
                    ->where('mid', $this->mid)
                    ->find();
            }
            
            // 9. 处理运费
            $freight = array();
            if($post['freightid']) {
                $freight = Db::name('freight')
                    ->where('aid', $this->aid)
                    ->where('bid', $bid)
                    ->where('id', $post['freightid'])
                    ->find();
                
                if((empty($address['name']) || empty($address['tel'])) && 
                   ($freight['pstype'] == 1 || $freight['pstype'] == 3) && 
                   $freight['needlinkinfo'] == 1) {
                    return $this->json(['status'=>0,'msg'=>'请填写联系人和联系电话']);
                }
                
                $rs = \app\model\Freight::getFreightPrice($freight, $address, $product_price, $num, $weight);
                if($rs['status'] == 0) {
                    return $this->json($rs);
                }
                $freight_price = $rs['freight_price'];
                
                // 配送时间选择检查
                if($freight['pstimeset'] == 1) {
                    $freight_times = explode('~', $post['freight_time']);
                    if($freight_times[1]) {
                        $freighttime = strtotime(explode(' ', $freight_times[0])[0] . ' ' . $freight_times[1]);
                    } else {
                        $freighttime = strtotime($freight_times[0]);
                    }
                    
                    if(time() + $freight['psprehour'] * 3600 > $freighttime) {
                        $type = ($freight['pstype'] == 0 || $freight['pstype'] == 2 || $freight['pstype'] == 10) ? '配送' : '提货';
                        return $this->json(['status'=>0,'msg'=>$type.'时间必须在'.$freight['psprehour'].'小时之后']);
                    }
                }
            } else {
                // 根据商品类型设置默认运费
                $freight_type = $product['freighttype'];
                $freight = array(
                    'id' => 0,
                    'name' => $freight_type == 3 ? '自动发货' : ($freight_type == 4 ? '在线卡密' : '包邮'),
                    'pstype' => $freight_type
                );
                
                // 联系信息检查
                if(($freight_type == 3 || $freight_type == 4) && $product['contact_require'] == 1) {
                    if(empty($address['name']) || empty($address['tel'])) {
                        return $this->json(['status'=>0,'msg'=>'请填写联系人和联系电话']);
                    }
                    if(!empty($address['tel']) && !checkTel($address['tel'])) {
                        return $this->json(['status'=>0,'msg'=>'请填写正确的联系电话']);
                    }
                }
            }
            
            // 10. 计算订单总价
            $totalprice = $product_price + $freight_price;
            $totalprice = max(0, $totalprice);
            
            // 11. 会员折扣
            $userlevel = Db::name('member_level')
                ->where('aid', $this->aid)
                ->where('id', $this->member['levelid'])
                ->find();
            
            if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
                $leveldk_money = $totalprice * (1 - $userlevel['discount'] * 0.1);
                $totalprice = $totalprice - $leveldk_money;
            }
            
            // 12. 优惠券处理
            $couponrid = isset($post['couponrid']) ? intval($post['couponrid']) : 0;
            $couponrecord = null;
            if($couponrid > 0) {
                // 构建优惠券适用条件
                $where = array();
                array_push($where, array('aid', '=', $this->aid));
                array_push($where, array('mid', '=', $this->mid));
                array_push($where, array('id', '=', $couponrid));
                array_push($where, array('status', '=', 0));
                array_push($where, array('type', 'in', array(1, 4)));
                
                // 商家优惠券适用条件
                $business_where = "bid=-1 or bid=" . $bid;
                if($bid > 0) {
                    $business = Db::name('business')
                        ->where('aid', $this->aid)
                        ->where('id', $bid)
                        ->find();
                    $bcids = $business['cid'] ? explode(',', $business['cid']) : array();
                    if($bcids) {
                        $whereCid = array();
                        foreach($bcids as $bcid) {
                            array_push($whereCid, "find_in_set(" . $bcid . ", canused_bcids)");
                        }
                        $whereCids = implode(' or ', $whereCid);
                        $business_where .= " or (bid=0 and (canused_bids='all' or find_in_set(" . $bid . ", canused_bids) or (" . $whereCids . ")))";
                    }
                }
                
                $couponrecord = Db::name('coupon_record')
                    ->where($where)
                    ->whereRaw($business_where)
                    ->find();
                
                if(!$couponrecord) {
                    return $this->json(['status'=>0,'msg'=>'该优惠券不存在']);
                }
                
                // 优惠券状态检查
                if($couponrecord['status'] != 0) {
                    return $this->json(['status'=>0,'msg'=>'该优惠券已使用过了']);
                }
                if($couponrecord['starttime'] > time()) {
                    return $this->json(['status'=>0,'msg'=>'该优惠券尚未开始使用']);
                }
                if($couponrecord['endtime'] < time()) {
                    return $this->json(['status'=>0,'msg'=>'该优惠券已过期']);
                }
                if($couponrecord['minprice'] > $totalprice) {
                    return $this->json(['status'=>0,'msg'=>'该优惠券不符合条件']);
                }
                
                $couponinfo = Db::name('coupon')
                    ->where('aid', $this->aid)
                    ->where('id', $couponrecord['couponid'])
                    ->find();
                
                if(empty($couponinfo)) {
                    return $this->json(['status'=>0,'msg'=>'该优惠券不存在或已作废']);
                }
                
                // 优惠券类型检查
                if($couponinfo['fwtype'] !== 0) {
                    return $this->json(['status'=>0,'msg'=>'该优惠券不符合条件']);
                }
                
                // 适用场景检查
                $fwscene = array(0);
                if(!in_array($couponinfo['fwscene'], $fwscene)) {
                    return $this->json(['status'=>0,'msg'=>'该优惠券不符合条件']);
                }
                
                // 使用优惠券
                Db::name('coupon_record')
                    ->where('id', $couponrid)
                    ->update(array('status'=>1, 'usetime'=>time()));
                
                // 计算优惠券抵扣金额
                if($couponrecord['type'] == 4) {
                    $coupon_money = $freight_price;
                } else {
                    $coupon_money = $couponrecord['money'];
                    if($coupon_money > $totalprice) $coupon_money = $totalprice;
                }
                
                $totalprice = $totalprice - $coupon_money;
                $totalprice = $totalprice + $freight_price;
            }
            
            // 13. 处理团队创建或参团
            if($buytype == 2) {
                // 创建团
                $tdata = array();
                $tdata['aid'] = $this->aid;
                $tdata['bid'] = $bid;
                $tdata['mid'] = $this->mid;
                $tdata['proid'] = $product['id'];
                $tdata['teamhour'] = $product['teamhour'];
                $tdata['teamnum'] = $product['teamnum'];
                $tdata['status'] = 0;
                $tdata['num'] = 0;
                $tdata['createtime'] = time();
                $teamid = Db::name('collage_order_team')->insertGetId($tdata);
                
                // 团长奖励积分
                if($product['leaderscore'] > 0) {
                    $givescore += $product['leaderscore'];
                }
            }
            
            // 14. 生成订单数据
            $ordernum = date('ymdHis') . $this->aid . rand(1000, 9999);
            $orderdata = array();
            $orderdata['aid'] = $this->aid;
            $orderdata['bid'] = $bid;
            $orderdata['mid'] = $this->mid;
            $orderdata['ordernum'] = $ordernum;
            $orderdata['title'] = removeEmoj($product['name']);
            $orderdata['proid'] = $product['id'];
            $orderdata['proname'] = $product['name'];
            $orderdata['propic'] = $guige['pic'] ? $guige['pic'] : $product['pic'];
            $orderdata['ggid'] = $guige['id'];
            $orderdata['ggname'] = $guige['name'];
            $orderdata['cost_price'] = $guige['cost_price'];
            $orderdata['sell_price'] = $guige['sell_price'];
            $orderdata['num'] = $num;
            $orderdata['linkman'] = $address['name'];
            $orderdata['tel'] = $address['tel'];
            $orderdata['area'] = $address['area'];
            $orderdata['area2'] = $address['province'] . ',' . $address['city'] . ',' . $address['district'];
            $orderdata['address'] = $address['address'];
            $orderdata['longitude'] = $address['longitude'];
            $orderdata['latitude'] = $address['latitude'];
            $orderdata['totalprice'] = $totalprice;
            $orderdata['product_price'] = $product_price;
            $orderdata['freight_price'] = $freight_price;
            $orderdata['leveldk_money'] = $leveldk_money;
            $orderdata['scoredk_money'] = $scoredk_money;
            $orderdata['scoredkscore'] = $scoredkscore;
            $orderdata['order_pay_type'] = $order_pay_type;
            $orderdata['coupon_rid'] = $couponrid;
            $orderdata['coupon_money'] = $coupon_money;
            $orderdata['leader_money'] = $leadermoney;
            $orderdata['buytype'] = $buytype;
            // 积分奖励：交易额*100
            $orderdata['givescore'] = intval($product_price * 100);
            $orderdata['teamid'] = $teamid;
            $orderdata['hexiao_code'] = random(16);
            $orderdata['createtime'] = time();
            
            // 生成核销二维码
            $orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=collage&co=' . $orderdata['hexiao_code']));
            
            // 平台类型
            $platform = input('get.platform');
            if(!$platform) $platform = $this->request->param('platform', 'wx');
            $orderdata['platform'] = $platform;
            
            // 17. 获取表字段，确保只插入存在的字段
            $order_fields = Db::name('collage_order')->getFields();
            
            // 添加supplier_id - 确保总是写入订单数据库
            // 优先使用前端传递的supplier_id，然后是商品表的supplier_id
            $supplier_id = 0;
            
            // 1. 优先从前端传递的supplier_id
            if(isset($post['supplier_id'])) {
                $supplier_id = intval($post['supplier_id']);
            }
            
            // 2. 如果前端没有传递或值为0，从商品表获取supplier_id
            if(empty($supplier_id) && isset($product['supplier_id'])) {
                $supplier_id = intval($product['supplier_id']);
            }
            
            // 3. 如果商品表没有supplier_id，最后从规格表获取
            if(empty($supplier_id) && isset($guige['supplier_id'])) {
                $supplier_id = intval($guige['supplier_id']);
            }
            
            // 强制写入supplier_id，不依赖字段检查
            $orderdata['supplier_id'] = $supplier_id;
            
            // 确保supplier_id字段存在于表中
            if(!in_array('supplier_id', $order_fields)) {
                try {
                    Db::query("ALTER TABLE `collage_order` ADD COLUMN `supplier_id` INT(11) NOT NULL DEFAULT 0 COMMENT '供货商ID' AFTER `proid`");
                } catch (\Exception $e) {
                    // 忽略添加字段失败的错误
                }
            }
            
            // 运费文本和类型 - 确保总是设置，只在字段存在时添加
            if(in_array('freight_type', $order_fields)) {
                // 根据用户说明：配送方式只有3种可能性
                // 1. 普通拼团：邮寄 (pstype=0或10)
                // 2. 普通拼团：到店取货 (pstype=1)
                // 3. 名店推广拼团：到店消费 (pstype=1，bid>0)
                
                $base_freight_type = 0; // 默认邮寄
                
                if($freight) {
                    $base_freight_type = $freight['pstype'];
                } else {
                    $base_freight_type = $product['freighttype'];
                }
                
                // 确保freight_type不为null，且只取有效值
                if(is_null($base_freight_type) || !in_array($base_freight_type, [0, 1, 2, 3, 4, 10])) {
                    // 如果值无效，根据商品类型设置默认值
                    if($product['bid'] > 0) {
                        // 名店推广拼团 - 到店消费
                        $orderdata['freight_type'] = 1;
                    } else {
                        // 普通拼团 - 默认邮寄
                        $orderdata['freight_type'] = 0;
                    }
                } else {
                    $orderdata['freight_type'] = $base_freight_type;
                }
            }
            
            if(in_array('freight_text', $order_fields)) {
                if($freight) {
                    if(in_array($freight['pstype'], array(0, 10))) {
                        $orderdata['freight_text'] = $freight['name'] . '(' . $freight_price . '元)';
                    } elseif($freight['pstype'] == 1) {
                        $storename = Db::name('mendian')
                            ->where('aid', $this->aid)
                            ->where('id', $post['storeid'] ?? 0)
                            ->value('name') ?? '';
                        $orderdata['freight_text'] = $freight['name'] . ($storename ? '[' . $storename . ']' : '');
                    } elseif(in_array($freight['pstype'], array(2, 3, 4))) {
                        $orderdata['freight_text'] = $freight['pstype'] == 2 ? $freight['name'] . '(' . $freight_price . '元)' : $freight['name'];
                    } else {
                        $orderdata['freight_text'] = $freight['name'];
                    }
                } else {
                    $freight_type = $product['freighttype'];
                    $orderdata['freight_text'] = $freight_type == 3 ? '自动发货' : ($freight_type == 4 ? '在线卡密' : '包邮');
                }
            }
            
            // 确保mdid字段在需要时被设置
            if(in_array('mdid', $order_fields) && $freight && $freight['pstype'] == 1 && isset($post['storeid'])) {
                $orderdata['mdid'] = $post['storeid'];
            }
            
            if(in_array('freight_id', $order_fields)) {
                $orderdata['freight_id'] = $freight['id'] ?? 0;
            }
            if(in_array('freight_time', $order_fields)) {
                $orderdata['freight_time'] = isset($post['freight_time']) ? $post['freight_time'] : '';
            }
            
            // 15. 商家相关计算
            if($product['bid'] > 0) {
                $bset = Db::name('business_sysset')
                    ->where('aid', $this->aid)
                    ->find();
                
                $scoredkmoney = 0;
                if($bset['scoredk_kouchu'] == 1) {
                    $scoredkmoney = $scoredk_money;
                }
                
                $business_feepercent = Db::name('business')
                    ->where('aid', $this->aid)
                    ->where('id', $product['bid'])
                    ->value('feepercent');
                
                $totalprice_business = $product_price - $coupon_money - $leadermoney - $scoredkmoney;
                
                // 商品独立费率
                if($product['feepercent'] !== '' && $product['feepercent'] !== null && $product['feepercent'] >= 0) {
                    $orderdata['business_total_money'] = $totalprice_business * (100 - $product['feepercent']) * 0.01;
                } else {
                    $orderdata['business_total_money'] = $totalprice_business * (100 - $business_feepercent) * 0.01;
                }
            }
            
            // 16. 佣金计算
            $commission_totalprice = $product_price;
            $sysset = Db::name('admin_set')
                ->where('aid', $this->aid)
                ->find();
            
            if($sysset['fxjiesuantype'] == 1 || $sysset['fxjiesuantype'] == 2) {
                $commission_totalprice = $product_price - $leveldk_money - $scoredk_money;
                if($couponrid > 0 && ($couponrecord['type'] != 4)) {
                    $commission_totalprice -= $coupon_money;
                }
            }
            
            // 团长佣金设置 - 佣金公式：拼团价*团长佣金比例*（拼团人数-1）
            if($order_pay_type == 2 && $buytype == 2) {
                if(in_array('leader_commission_rate', $order_fields)) {
                    $orderdata['leader_commission_rate'] = $leader_commission_rate;
                }
                if(in_array('leader_commission_unit', $order_fields)) {
                    // 计算单位佣金：拼团价 * 佣金比例
                    $orderdata['leader_commission_unit'] = $collage_price * $leader_commission_rate * 0.01;
                }
                if(in_array('leader_commission', $order_fields)) {
                    // 计算总佣金：拼团价 * 佣金比例 * (拼团人数 - 1)
                    $orderdata['leader_commission'] = $collage_price * $leader_commission_rate * 0.01 * ($product['teamnum'] - 1);
                }
            } else {
                if(in_array('leader_commission_rate', $order_fields)) {
                    $orderdata['leader_commission_rate'] = 0;
                }
                if(in_array('leader_commission_unit', $order_fields)) {
                    $orderdata['leader_commission_unit'] = 0;
                }
                if(in_array('leader_commission', $order_fields)) {
                    $orderdata['leader_commission'] = 0;
                }
            }
            
            // 18. 清理不存在的字段，排除createtime、freight_type和supplier_id字段
            $check_fields = array(
                'pleader_id', 'pleader_commission', 'pleader_score', 
                'parent1', 'parent2', 'parent3', 
                'parent1commission', 'parent2commission', 'parent3commission',
                'parent1score', 'parent2score', 'parent3score',
                'order_pay_type', 'freight_text', 'mdid',
                'freight_id', 'freight_time', 'coupon_rid',
                'coupon_money', 'leader_money', 'givescore', 'hexiao_code',
                'hexiao_qr', 'platform', 'business_total_money',
                'createtime', 'freight_type', 'supplier_id' // 添加这三个字段到检查列表
            );
            
            foreach($check_fields as $field) {
                if (isset($orderdata[$field]) && !in_array($field, $order_fields)) {
                    unset($orderdata[$field]);
                }
            }
            
            // 19. 处理团长佣金
            // 获取团长信息
            $agleveldata = Db::name('member_level')->where('aid', $this->aid)->where('id', $this->member['levelid'])->find();
            $leder = $this->member;
            
            if($buytype == 3 && !empty($tuan)){
                $leder = Db::name('member')->where('aid', $this->aid)->where('id', $tuan['mid'])->find();
                $agleveldata = Db::name('member_level')->where('aid', $this->aid)->where('id', $leder['levelid'])->find();
            }
            
            if($agleveldata['can_agent'] == 4) {
                // 获取上级
                if($leder['pid']) {
                    $parent1 = Db::name('member')
                        ->where('aid', $this->aid)
                        ->where('id', $leder['pid'])
                        ->find();
                    
                    if($parent1) {
                        $agleveldata1 = Db::name('member_level')
                            ->where('aid', $this->aid)
                            ->where('id', $parent1['levelid'])
                            ->find();
                        
                        if($agleveldata1['can_agent'] != 0) {
                            $orderdata['pleader_id'] = $parent1['id'];
                            
                            // 计算佣金
                            if($product['commissionset'] == 1) {
                                $commissiondata = json_decode($product['commissiondata1'], true);
                                if($commissiondata) {
                                    $orderdata['pleader_commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01;
                                }
                            } elseif($product['commissionset'] == 2) {
                                $commissiondata = json_decode($product['commissiondata2'], true);
                                if($commissiondata) {
                                    $orderdata['pleader_commission'] = $commissiondata[$agleveldata1['id']]['commission1'];
                                }
                            } elseif($product['commissionset'] == 3) {
                                $commissiondata = json_decode($product['commissiondata3'], true);
                                if($commissiondata) {
                                    $orderdata['pleader_score'] = $commissiondata[$agleveldata1['id']]['commission1'];
                                }
                            } else {
                                if($agleveldata1['commissiontype'] == 1) {
                                    $orderdata['pleader_commission'] = $agleveldata1['commission1'];
                                } else {
                                    $orderdata['pleader_commission'] = $agleveldata1['commission1'] * $commission_totalprice * 0.01;
                                }
                            }
                        } else {
                            $orderdata['pleader_id'] = 0;
                        }
                    }
                }
            } else {
                // 获取团长
                if($product['commissionset'] != -1) {
                    // 处理三级分销
                    $parent1 = null;
                    $parent2 = null;
                    $parent3 = null;
                    $agleveldata1 = null;
                    $agleveldata2 = null;
                    $agleveldata3 = null;
                    
                    // 一级分销
                    if($this->member['pid']) {
                        $parent1 = Db::name('member')
                            ->where('aid', $this->aid)
                            ->where('id', $this->member['pid'])
                            ->find();
                        
                        if($parent1) {
                            $agleveldata1 = Db::name('member_level')
                                ->where('aid', $this->aid)
                                ->where('id', $parent1['levelid'])
                                ->find();
                            
                            if($agleveldata1['can_agent'] != 0) {
                                $orderdata['parent1'] = $parent1['id'];
                            }
                        }
                    }
                    
                    // 二级分销
                    if($parent1 && $parent1['pid']) {
                        $parent2 = Db::name('member')
                            ->where('aid', $this->aid)
                            ->where('id', $parent1['pid'])
                            ->find();
                        
                        if($parent2) {
                            $agleveldata2 = Db::name('member_level')
                                ->where('aid', $this->aid)
                                ->where('id', $parent2['levelid'])
                                ->find();
                            
                            if($agleveldata2['can_agent'] > 1) {
                                $orderdata['parent2'] = $parent2['id'];
                            }
                        }
                    }
                    
                    // 三级分销
                    if($parent2 && $parent2['pid']) {
                        $parent3 = Db::name('member')
                            ->where('aid', $this->aid)
                            ->where('id', $parent2['pid'])
                            ->find();
                        
                        if($parent3) {
                            $agleveldata3 = Db::name('member_level')
                                ->where('aid', $this->aid)
                                ->where('id', $parent3['levelid'])
                                ->find();
                            
                            if($agleveldata3['can_agent'] > 2) {
                                $orderdata['parent3'] = $parent3['id'];
                            }
                        }
                    }
                    
                    // 计算三级分销佣金
                    if($product['commissionset'] == 1) {
                        $commissiondata = json_decode($product['commissiondata1'], true);
                        if($commissiondata) {
                            if($agleveldata1) $orderdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01;
                            if($agleveldata2) $orderdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $commission_totalprice * 0.01;
                            if($agleveldata3) $orderdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $commission_totalprice * 0.01;
                        }
                    } elseif($product['commissionset'] == 2) {
                        $commissiondata = json_decode($product['commissiondata2'], true);
                        if($commissiondata) {
                            if($agleveldata1) $orderdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'];
                            if($agleveldata2) $orderdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'];
                            if($agleveldata3) $orderdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'];
                        }
                    } elseif($product['commissionset'] == 3) {
                        $commissiondata = json_decode($product['commissiondata3'], true);
                        if($commissiondata) {
                            if($agleveldata1) $orderdata['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'];
                            if($agleveldata2) $orderdata['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'];
                            if($agleveldata3) $orderdata['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'];
                        }
                    } else {
                        // 按会员等级设置的分销比例
                        if($agleveldata1) {
                            if($agleveldata1['commissiontype'] == 1) {
                                $orderdata['parent1commission'] = $agleveldata1['commission1'];
                            } else {
                                $orderdata['parent1commission'] = $agleveldata1['commission1'] * $commission_totalprice * 0.01;
                            }
                        }
                        
                        if($agleveldata2) {
                            if($agleveldata2['commissiontype'] == 1) {
                                $orderdata['parent2commission'] = $agleveldata2['commission2'];
                            } else {
                                $orderdata['parent2commission'] = $agleveldata2['commission2'] * $commission_totalprice * 0.01;
                            }
                        }
                        
                        if($agleveldata3) {
                            if($agleveldata3['commissiontype'] == 1) {
                                $orderdata['parent3commission'] = $agleveldata3['commission3'];
                            } else {
                                $orderdata['parent3commission'] = $agleveldata3['commission3'] * $commission_totalprice * 0.01;
                            }
                        }
                    }
                }
            }
            
            // 简化处理：直接从product获取supplier_id并写入orderdata
            $orderdata['supplier_id'] = intval($product['supplier_id'] ?? 0);
            
            // 确保createtime字段存在
            $orderdata['createtime'] = time();
            
            // 20. 插入订单
            $orderid = Db::name('collage_order')->insertGetId($orderdata);
            
            // 调试：如果插入后发现supplier_id没有被正确写入，执行更新操作
            Db::name('collage_order')->where('id', $orderid)->update([
                'supplier_id' => intval($product['supplier_id'] ?? 0),
                'createtime' => time()
            ]);
            
            // 21. 插入佣金记录
            if(isset($orderdata['parent1']) && ($orderdata['parent1commission'] || $orderdata['parent1score'])) {
                Db::name('member_commission_record')->insert(array(
                    'aid' => $this->aid,
                    'mid' => $orderdata['parent1'],
                    'frommid' => $this->mid,
                    'orderid' => $orderid,
                    'ogid' => $product['id'],
                    'type' => 'collage',
                    'commission' => isset($orderdata['parent1commission']) ? $orderdata['parent1commission'] : 0,
                    'score' => isset($orderdata['parent1score']) ? $orderdata['parent1score'] : 0,
                    'remark' => '下级购买商品奖励',
                    'createtime' => time()
                ));
            }
            
            if(isset($orderdata['parent2']) && ($orderdata['parent2commission'] || $orderdata['parent2score'])) {
                Db::name('member_commission_record')->insert(array(
                    'aid' => $this->aid,
                    'mid' => $orderdata['parent2'],
                    'frommid' => $this->mid,
                    'orderid' => $orderid,
                    'ogid' => $product['id'],
                    'type' => 'collage',
                    'commission' => isset($orderdata['parent2commission']) ? $orderdata['parent2commission'] : 0,
                    'score' => isset($orderdata['parent2score']) ? $orderdata['parent2score'] : 0,
                    'remark' => '下二级购买商品奖励',
                    'createtime' => time()
                ));
            }
            
            if(isset($orderdata['parent3']) && ($orderdata['parent3commission'] || $orderdata['parent3score'])) {
                Db::name('member_commission_record')->insert(array(
                    'aid' => $this->aid,
                    'mid' => $orderdata['parent3'],
                    'frommid' => $this->mid,
                    'orderid' => $orderid,
                    'ogid' => $product['id'],
                    'type' => 'collage',
                    'commission' => isset($orderdata['parent3commission']) ? $orderdata['parent3commission'] : 0,
                    'score' => isset($orderdata['parent3score']) ? $orderdata['parent3score'] : 0,
                    'remark' => '下三级购买商品奖励',
                    'createtime' => time()
                ));
            }
            
            // 下级团长开团，团员参团所得奖励
            if(isset($orderdata['pleader_commission'])) {
                Db::name('member_commission_record')->insert(array(
                    'aid' => $this->aid,
                    'mid' => $orderdata['pleader_id'],
                    'frommid' => $this->mid,
                    'orderid' => $orderid,
                    'ogid' => $product['id'],
                    'type' => 'collage',
                    'commission' => isset($orderdata['pleader_commission']) ? $orderdata['pleader_commission'] : 0,
                    'score' => isset($orderdata['pleader_score']) ? $orderdata['pleader_score'] : 0,
                    'remark' => '下级团长开团，团员参团所得奖励',
                    'createtime' => time()
                ));
            }
            
            // 22. 保存运费表单数据
            if($post['formdata']) {
                \app\model\Freight::saveformdata($orderid, 'collage_order', $freight['id'], $post['formdata']);
            }
            
            // 23. 创建支付订单
            $payorderid = \app\model\Payorder::createorder(
                $this->aid,
                $orderdata['bid'],
                $orderdata['mid'],
                'collage',
                $orderid,
                $ordernum,
                $orderdata['title'],
                $orderdata['totalprice'],
                isset($orderdata['scoredkscore']) ? $orderdata['scoredkscore'] : 0
            );
            
            // 24. 更新库存和销量
            $stock = $guige['stock'] - $num;
            if($stock < 0) $stock = 0;
            
            $pstock = $product['stock'] - $num;
            if($pstock < 0) $pstock = 0;
            
            $sales = $guige['sales'] + $num;
            $psales = $product['sales'] + $num;
            
            // 检查是否是使用商品自身作为规格
            if($guige['id'] == $product['id']) {
                // 只更新商品表的库存和销量（因为规格就是商品自身）
                Db::name('collage_product')
                    ->where('aid', $this->aid)
                    ->where('id', $product['id'])
                    ->update(array('stock' => $pstock, 'sales' => $psales));
            } else {
                // 正常更新规格表和商品表的库存和销量
                Db::name('collage_guige')
                    ->where('aid', $this->aid)
                    ->where('id', $guige['id'])
                    ->update(array('stock' => $stock, 'sales' => $sales));
                
                Db::name('collage_product')
                    ->where('aid', $this->aid)
                    ->where('id', $product['id'])
                    ->update(array('stock' => $pstock, 'sales' => $psales));
            }
            
            // 25. 返回结果
            return $this->json(array(
                'status' => 1,
                'orderid' => $orderid,
                'payorderid' => $payorderid,
                'msg' => '提交成功'
            ));
        } catch (\Exception $e) {
            // 记录详细错误信息
            $error_info = array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'params' => $post ? $post : array(),
                'time' => date('Y-m-d H:i:s')
            );
            
            // 写入错误日志到文件
            file_put_contents(
                'd:\\Adev\\gdqshop.cn\\runtime\\error_log.txt',
                json_encode($error_info, JSON_UNESCAPED_UNICODE) . "\n",
                FILE_APPEND
            );
            
            return $this->json([
                'status' => 0,
                'msg' => '系统异常，请稍后重试',
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // 拼团订单列表
    public function orderlist(){
        $this->checklogin();
        $st = input('param.st');
        if(!input('?param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',$this->aid];
        $where[] = ['mid','=',$this->mid];
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
        }elseif($st == '10'){
            $where[] = ['refund_status','>',0];
        }
        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('collage_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
        if(!$datalist) $datalist = array();
        foreach($datalist as $key=>$v){
            $datalist[$key]['team'] = [];
            if($v['buytype']!=1) $datalist[$key]['team'] = Db::name('collage_order_team')->where('id',$v['teamid'])->find();
            //发票
            $datalist[$key]['invoice'] = 0;
            if($v['bid']) {
                $datalist[$key]['invoice'] = Db::name('business')->where('aid',$this->aid)->where('id',$v['bid'])->value('invoice');
            } else {
                $datalist[$key]['invoice'] = Db::name('admin_set')->where('aid',$this->aid)->value('invoice');
            }
            $datalist[$key]['show_send'] = 1;
        }

        return $this->json(['status'=>1, 'datalist'=>$datalist, 'total'=>$total]);
    }

    /**
     * 查看团详情
     * 使用原始方式读取拼团订单
     */
    function team(){
        $this->checklogin();
        $teamid = input('param.teamid/d');
        $team = Db::name('collage_order_team')->where('aid',constant('aid'))->where('id',$teamid)->find();
        $product = Db::name('collage_product')->where('aid',constant('aid'))->where('id',$team['proid'])->find();
        if(!$product) return $this->json(['status'=>0,'msg'=>'商品不存在']);
        if($product['status']==0) return $this->json(['status'=>0,'msg'=>'商品已下架']);
        if($product['ischecked']!=1) return $this->json(['status'=>0,'msg'=>'商品未审核']);
        
        if(!$product['pics']) $product['pics'] = $product['pic'];
        $product['pics'] = explode(',',$product['pics']);
        if($product['fuwupoint']){ 
            $product['fuwupoint'] = explode(' ',preg_replace("/\s+/",' ',str_replace('　',' ',trim($product['fuwupoint']))));
        }
        // 从collage_guige表获取规格数据
        $gglist = Db::name('collage_guige')->where('aid',constant('aid'))->where('proid',$product['id'])->select()->toArray();
        $guigelist = array();
        
        if (!empty($gglist)) {
            // 如果有规格数据，使用collage_guige表的数据
            foreach($gglist as $k=>$v){
                $guigelist[$v['ks']] = $v;
            }
        } else {
            // 如果没有规格数据，使用商品自身作为规格
            $guigelist[0] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'ks' => 0,
                'goods_sn' => $product['goods_sn'],
                'market_price' => $product['market_price'],
                'sell_price' => $product['sell_price'],
                'leader_price' => $product['leader_price'],
                'leader_commission_rate' => $product['leader_commission_rate'],
                'weight' => $product['weight'],
                'stock' => $product['stock'],
                'pic' => $product['pic'],
                'proid' => $product['id'],
            ];
        }
        $guigedata = json_decode($product['guigedata'],true);
        $ggselected = [];
        foreach($guigedata as $v) {
            $ggselected[] = 0;
        }
        $ks = implode(',',$ggselected);

        $orderlist = Db::name('collage_order')->where('aid',constant('aid'))->where('teamid',$teamid)->where('status','in','1,2,3')->select()->toArray();
        $userlist = [];
        $haveme = 0;
        $show_mingpian = 0;
        $is_group =  $team['mid']== constant('mid')?1:0;
        foreach($orderlist as $v){
            $user = Db::name('member')->field('id,nickname,headimg,province,city,sex')->where('aid',constant('aid'))->where('id',$v['mid'])->find();

            if($show_mingpian){
                $mingpian_id = Db::name('mingpian')->where('aid',constant('aid'))->where('mid',$user['id'])->value('id');
                $user['mingpian_id'] = $mingpian_id;
            }
            if($user){
                $userlist[] = $user;
                if($user['id'] == constant('mid')) $haveme =1;
            }
        }
        if($team['teamnum'] > $team['num']){ 
            for($i=0;$i<$team['teamnum'] - $team['num'];$i++){
                $userlist[] = ['id'=>'','nickanme'=>'','headimg'=>''];
            }
        }
        // 计算剩余时间并格式化，避免NaN问题
        $rtime = max(0, $team['createtime'] + $team['teamhour'] * 3600 - time());
        $hours = floor($rtime / 3600);
        $minutes = floor(($rtime % 3600) / 60);
        $seconds = $rtime % 60;
        $formatted_time = "{$hours}小时{$minutes}分{$seconds}秒";
        
        // 添加时间格式化字段
        $team['remaining_time'] = [
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
            'formatted' => $formatted_time
        ];
        $team['time_left'] = $formatted_time;
        
        $set = Db::name('admin_set')->field('name,logo,desc,tel')->where('aid',constant('aid'))->find();
        $shopset = Db::name('collage_sysset')->field('comment,showjd')->where('aid',constant('aid'))->find();
        $product['detail'] = \app\common\System::initpagecontent($product['detail'],constant('aid'),constant('mid'),platform);
        $rdata = [];
        $rdata['status'] = 1;
        $rdata['team'] = $team;
        $rdata['product'] = $product;
        $rdata['guigelist'] = $guigelist;
        $rdata['guigedata'] = $guigedata;
        $rdata['ggselected'] = $ggselected;
        $rdata['ks'] = $ks;
        $rdata['sysset'] = $set;
        $rdata['shopset'] = $shopset;
        $rdata['userlist'] = $userlist;
        $rdata['rtime'] = $rtime;
        $rdata['haveme'] = $haveme;
        $rdata['show_mingpian'] = $show_mingpian;
        $rdata['is_group'] = $is_group;
        return $this->json($rdata);
    }

    /**
     * 修复后的团详情查看方法 - 用于测试
     */
    public function testTeam(){
        try {
            $teamid = input('param.teamid/d');
            if(!$teamid){
                return $this->json(['status' => 0, 'msg' => '参数错误']);
            }

            // 获取团队信息
            $team = Db::name('collage_order_team')
                ->where('aid', $this->aid)
                ->where('id', $teamid)
                ->find();
            if(!$team){
                return $this->json(['status' => 0, 'msg' => '没有找到该团']);
            }

            // 处理拼团时间
            $team['start_time'] = gmdate('Y-m-d H:i:s', $team['createtime'] + 8 * 3600);
            $team['end_time'] = gmdate('Y-m-d H:i:s', $team['createtime'] + $team['teamhour'] * 3600 + 8 * 3600);
            $team['now_time'] = time();
            $team['start_time_text'] = date('Y年m月d日 H:i:s', $team['createtime']);
            $team['end_time_text'] = date('Y年m月d日 H:i:s', $team['createtime'] + $team['teamhour'] * 3600);

            // 获取团队下的所有订单
            $orderList = Db::name('collage_order')
                ->where('aid', $this->aid)
                ->where('teamid', $teamid)
                ->select()
                ->toArray();

            // 强制按照原则：所有拼团订单的创建者（team.mid）都是团长
            $leader_mid = $team['mid']; // 团长的mid是团队表中的mid字段，这是唯一确定的
            
            // 1. 优先处理团长信息 - 无论用户是否存在，都创建团长信息
            $leader = Db::name('member')
                ->field('id, nickname, headimg')
                ->where('id', $leader_mid)
                ->find();
                
            // 强制创建团长信息
            $leaderInfo = [
                'id' => $leader ? $leader['id'] : $leader_mid,
                'nickname' => $leader ? $leader['nickname'] : '未知团长',
                'headimg' => $leader ? $leader['headimg'] : '',
                'orderid' => 0,
                'buytype' => 2,
                'is_leader' => 1, // 强制设置为团长
                'is_team_creator' => 1 // 明确是团队创建者
            ];
            
            // 查找团长对应的订单
            foreach($orderList as $order) {
                if($order['mid'] == $leader_mid) {
                    $leaderInfo['orderid'] = $order['id'];
                    $leaderInfo['buytype'] = $order['buytype'];
                    break;
                }
            }
            
            // 2. 处理团员信息
            $otherMembers = [];
            foreach($orderList as $order) {
                // 跳过团长的订单
                if($order['mid'] == $leader_mid) {
                    continue;
                }
                
                $member = Db::name('member')
                    ->field('id, nickname, headimg')
                    ->where('id', $order['mid'])
                    ->find();
                
                if($member) {
                    $otherMembers[] = [
                        'id' => $member['id'],
                        'nickname' => $member['nickname'],
                        'headimg' => $member['headimg'],
                        'orderid' => $order['id'],
                        'buytype' => $order['buytype'],
                        'is_leader' => 0, // 普通团员
                        'is_team_creator' => 0
                    ];
                }
            }
            
            // 重组成员列表 - 团长始终在第一位
            $memberList = [$leaderInfo]; // 团长必须在第一位
            $memberList = array_merge($memberList, $otherMembers);
            
            // 强制设置团长标识信息
            $team['leader_mid'] = $leader_mid;
            $team['has_leader'] = 1; // 始终有团长

            // 获取商品信息
            $product = Db::name('collage_product')
                ->where('aid', $this->aid)
                ->where('id', $team['proid'])
                ->find();

            // 设置is_group字段
            $is_group = ($this->mid == $team['mid']) ? 1 : 0;
            
            // 记录调试信息
            file_put_contents(
                'd:\Adev\gdqshop.cn\runtime\test_team_debug.log',
                date('Y-m-d H:i:s') . " - 测试团队ID: {$teamid}, 团长ID: {$leader_mid}, 当前用户ID: {$this->mid}\n",
                FILE_APPEND
            );
            
            // 返回数据
            $rdata = [
                'status' => 1,
                'team' => $team,
                'memberList' => $memberList,
                'product' => $product,
                'leader_mid' => $leader_mid,
                'is_group' => $is_group,
                'debug_info' => [
                    'team_id' => $teamid,
                    'team_mid' => $team['mid'],
                    'current_user_mid' => $this->mid,
                    'is_group' => $is_group,
                    'leader_mid' => $leader_mid,
                    'member_count' => count($memberList),
                    'is_test_mode' => true
                ]
            ];

            return $this->json($rdata);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 0,
                'msg' => '系统异常，请稍后重试',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取团队海报
     */
    public function getTeamPoster(){        
        try {
            $teamid = input('param.teamid/d');
            if(!$teamid || !is_numeric($teamid)){
                return $this->json(['status' => 0, 'msg' => '参数错误']);
            }

            // 获取团队信息
            $team = Db::name('collage_order_team')
                ->where('aid', $this->aid)
                ->where('id', $teamid)
                ->find();
            
            // 检查团队数据格式
            if(!$team || !is_array($team) || empty($team)){
                return $this->json(['status' => 0, 'msg' => '没有找到该团']);
            }
            
            // 检查proid字段存在且有效
            if(!isset($team['proid']) || !is_numeric($team['proid'])){
                return $this->json(['status' => 0, 'msg' => '数据格式错误']);
            }

            // 获取商品信息
            $product = Db::name('collage_product')
                ->where('aid', $this->aid)
                ->where('id', $team['proid'])
                ->find();
            
            // 检查商品数据格式
            if(!$product || !is_array($product) || empty($product)){
                return $this->json(['status' => 0, 'msg' => '商品不存在']);
            }

            // 获取团长信息
            $leader = Db::name('member')
                ->field('id, nickname, headimg')
                ->where('id', $team['mid'] ?? 0)
                ->find();
            
            // 确保leader是数组，即使查询失败
            if(!$leader || !is_array($leader)){
                $leader = ['nickname' => '团长', 'id' => 0, 'headimg' => ''];
            }

            // 设置海报数据
            $posterdata = [
                'poster_bg' => (string)(PRE_URL.'/static/imgsrc/posterbg.jpg'),
                'poster_data' => [
                    [
                        'type' => 'img',
                        'src' => isset($product['pic']) && $product['pic'] ? (string)$product['pic'] : (string)(PRE_URL.'/static/imgsrc/nopic.png'),
                        'left' => '20px',
                        'top' => '20px',
                        'width' => '300px',
                        'height' => '300px'
                    ],
                    [
                        'type' => 'text',
                        'content' => '[商品名称]',
                        'left' => '20px',
                        'top' => '340px',
                        'width' => '300px',
                        'height' => '40px',
                        'size' => '14px',
                        'color' => '#333333'
                    ],
                    [
                        'type' => 'text',
                        'content' => '拼团价：[拼团价格]',
                        'left' => '20px',
                        'top' => '380px',
                        'width' => '150px',
                        'height' => '30px',
                        'size' => '16px',
                        'color' => '#FF5000'
                    ],
                    [
                        'type' => 'text',
                        'content' => '[团长昵称]邀请你一起拼团',
                        'left' => '20px',
                        'top' => '420px',
                        'width' => '300px',
                        'height' => '30px',
                        'size' => '14px',
                        'color' => '#666666'
                    ],
                    [
                        'type' => 'qrwx',
                        'left' => '240px',
                        'top' => '460px',
                        'width' => '80px',
                        'height' => '80px'
                    ],
                    [
                        'type' => 'text',
                        'content' => '长按识别二维码参与拼团',
                        'left' => '20px',
                        'top' => '550px',
                        'width' => '300px',
                        'height' => '20px',
                        'size' => '12px',
                        'color' => '#999999'
                    ]
                ]
            ];

            // 创建文本替换数组
            $textReplaceArr = [
                '[商品名称]' => isset($product['name']) && is_string($product['name']) ? (mb_strlen($product['name']) > 20 ? mb_substr($product['name'], 0, 20) . '...' : $product['name']) : '商品',
                '[拼团价格]' => isset($product['sell_price']) && is_numeric($product['sell_price']) ? '¥' . number_format(floatval($product['sell_price']), 2) : '¥0.00',
                '[商品价格]' => isset($product['sell_price']) && is_numeric($product['sell_price']) ? '¥' . number_format(floatval($product['sell_price']), 2) : '¥0.00',
                '[团长昵称]' => isset($leader['nickname']) && is_string($leader['nickname']) ? $leader['nickname'] : '团长'
            ];

            // 生成二维码场景值，使用key_value格式
            $scene = 'teamid_' . (string)$teamid;
            // 生成二维码页面路径
            $page = 'activity/collage/team';
            
            // 调用海报生成方法
            $posterUrl = $this->_createTeamPoster($this->aid, 0, 'wx', json_encode($posterdata), $page, $scene, $textReplaceArr);

            if ($posterUrl === false) {
                return $this->json(['status' => 0, 'msg' => '生成海报失败，请稍后重试']);
            }

            return $this->json(['status' => 1, 'poster' => $posterUrl]);
        } catch (\Exception $e) {
            // 记录详细错误信息
            $error_info = array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'params' => $_REQUEST,
                'time' => date('Y-m-d H:i:s')
            );

            // 写入错误日志到文件
            file_put_contents(
                'd:\Adev\gdqshop.cn\runtime\error_log.txt',
                json_encode($error_info, JSON_UNESCAPED_UNICODE) . "\n",
                FILE_APPEND
            );

            return $this->json([
                'status' => 0,
                'msg' => '系统异常，请稍后重试',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 创建团队海报
     */
    private function _createTeamPoster($aid, $bid, $platform, $posterdata, $page, $scene, $textReplaceArr) {
        try {
            set_time_limit(0);
            $posterdata = json_decode($posterdata, true);

            // 检查JSON解析是否成功
            if (!is_array($posterdata) || !isset($posterdata['poster_bg']) || !isset($posterdata['poster_data'])) {
                // 如果解析失败，使用默认数据
                $posterdata = [
                    'poster_bg' => PRE_URL.'/static/imgsrc/posterbg.jpg',
                    'poster_data' => []
                ];
            }

            $poster_bg = $posterdata['poster_bg'];
            $poster_data = $posterdata['poster_data'];
            
            // 确保poster_data是数组
            if (!is_array($poster_data)) {
                $poster_data = [];
            }
            @ini_set('memory_limit', -1);

            if(strpos($poster_bg,'http') === false){
                $poster_bg = PRE_URL.$poster_bg;
            }
            $bg = imagecreatefromstring(request_get($poster_bg));
            if($bg){
                $orig_width = imagesx($bg);
                $orig_height = imagesy($bg);
                $bgwidth = 680;
                $bgheight = floor($orig_height * ($bgwidth / $orig_width));
                if($bgheight/$bgwidth > 1.92) $bgheight = floor($bgwidth * 1.92);
                $target = imagecreatetruecolor($bgwidth, $bgheight);
                imagecopyresampled($target, $bg, 0, 0, 0, 0, $bgwidth, $bgheight, $orig_width, $orig_height);
                imagedestroy($bg);
            } else {
                $bgwidth = 680;
                $bgheight = 1080;
                $target = imagecreatetruecolor(680, 1080);
                imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
            }
            $huansuan = $bgwidth/344;
            
            $font = ROOT_PATH."static/fonts/msyh.ttf";
            foreach ($poster_data as $d){
                $d['left'] = intval(str_replace('px', '', $d['left'])) * $huansuan;
                $d['top'] = intval(str_replace('px', '', $d['top'])) * $huansuan;
                $d['width'] = intval(str_replace('px', '', $d['width'])) * $huansuan;
                $d['height'] = intval(str_replace('px', '', $d['height'])) * $huansuan;
                $d['size'] = intval(str_replace('px', '', $d['size'])) * $huansuan;
                
                if ($d['type'] == 'qrwx' || $d['type'] == 'qrwxreg') {
                    // 直接传递scene字符串，让getQRCode方法处理
                    $errmsg = \app\common\Wechat::getQRCode($aid, 'wx', ltrim($page,'/'), $scene, $bid, false);
                    $res = $errmsg['buffer'];
                    if($errmsg['status'] != 1) {
                        // 二维码生成失败，返回错误信息给调用者
                        throw new Exception($errmsg['msg'] ?? '生成二维码失败');
                    } else {
                        $img = imagecreatefromstring($res);
                        imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'], imagesx($img), imagesy($img));
                    }
                } else if ($d['type'] == 'qrmp') {
                    $qrcode = createqrcode(PRE_URL .'/h5/'.$aid.'.html#'.$page.'?scene='.$scene.'&t='.time());
                    $img = imagecreatefromstring(request_get($qrcode));
                    if($img) {
                        imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'], imagesx($img), imagesy($img));
                    }
                } else if ($d['type'] == 'img') {
                    if($d['src'][0] == '/') $d['src'] = PRE_URL.$d['src'];
                    $img = imagecreatefromstring(request_get($d['src']));
                    if($img) {
                        imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'], imagesx($img), imagesy($img));
                    }
                } else if ($d['type'] == 'text') {
                    $d['content'] = str_replace(array_keys($textReplaceArr), array_values($textReplaceArr), $d['content']);
                    $colors = hex2rgb($d['color']);
                    $color = imagecolorallocate($target, $colors['red'], $colors['green'], $colors['blue']);
                    $box = imagettfbbox($d['size'], 0, $font, $d['content']);
                    if($box !== false) {
                        $textHeight = $box[3] - $box[7];
                        imagettftext($target, $d['size'], 0, $d['left'], $d['top'] + $textHeight, $color, $font, $d['content']);
                    }
                }
            }
            
            // 保存海报图片
            $url = "/upload/{$aid}/collage_poster/" . date('Ym/d_His') . rand(1000, 9999) . '.jpg';
            $filepath = ROOT_PATH . ltrim($url, '/');
            mk_dir(dirname($filepath));
            imagejpeg($target, $filepath, 100);
            imagedestroy($target);
            
            return PRE_URL . $url;
        } catch (Exception $e) {
            // 记录详细错误信息
            file_put_contents(
                'd:\Adev\gdqshop.cn\runtime\error_log.txt',
                'POSTER ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . "\n",
                FILE_APPEND
            );
            // 返回错误信息
            return false;
        }
    }
    
    // 关闭订单
    public function closeOrder(){
        return $this->json([
            'status' => 1,
            'msg' => '订单已关闭'
        ]);
    }
    
    /**
     * 获取拼团商品海报
     */
    public function getposter(){
        try {
            $this->checklogin();
            $post = input('post.');
            $platform = platform;
            $page = '/pages/shop/product';
            $scene = 'id_'.$post['proid'].'-pid_'.$this->member['id'];
            
            $posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','product')->where('platform',$platform)->order('id')->find();

            // 关闭缓存
            if(true || !$posterdata){
                $product = Db::name('collage_product')->where('id',$post['proid'])->find();
                $sysset = Db::name('admin_set')->where('aid',aid)->find();
                $textReplaceArr = [
                    '[头像]'=>$this->member['headimg'],
                    '[昵称]'=>$this->member['nickname'],
                    '[姓名]'=>$this->member['realname'],
                    '[手机号]'=>$this->member['mobile'],
                    '[商城名称]'=>$sysset['name'],
                    '[商品名称]'=>$product['name'],
                    '[商品销售价]'=>$product['sell_price'],
                    '[商品市场价]'=>$product['market_price'],
                    '[商品图片]'=>$product['pic'],
                ];

                $poster = $this->_getposter(aid,$product['bid'],$platform,$posterset['content'],$page,$scene,$textReplaceArr);
            }
            return $this->json(['status'=>1,'poster'=>$poster]);
        } catch (\Exception $e) {
            // 记录详细错误信息
            $error_info = array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'params' => $_REQUEST,
                'time' => date('Y-m-d H:i:s')
            );

            // 写入错误日志到文件
            file_put_contents(
                'd:\Adev\gdqshop.cn\runtime\error_log.txt',
                json_encode($error_info, JSON_UNESCAPED_UNICODE) . "\n",
                FILE_APPEND
            );

            return $this->json([
                'status' => 0,
                'msg' => '系统异常，请稍后重试',
                'error' => $e->getMessage()
            ]);
        }
    }

    // 删除订单
    public function delOrder(){
        try {
            $this->checklogin();
            $orderid = input('post.orderid/d');
            if(!$orderid) {
                return $this->json(['status'=>0,'msg'=>'订单ID错误']);
            }
            
            $where = [];
            $where[] = ['aid','=',$this->aid];
            $where[] = ['mid','=',$this->mid];
            $where[] = ['id','=',$orderid];
            
            $order = Db::name('collage_order')->where($where)->find();
            if(!$order) {
                return $this->json(['status'=>0,'msg'=>'订单不存在']);
            }
            
            // 软删除订单
            $result = Db::name('collage_order')
                ->where($where)
                ->update(['delete'=>1]);
            
            if($result) {
                return $this->json(['status'=>1,'msg'=>'订单已删除']);
            } else {
                return $this->json(['status'=>0,'msg'=>'删除订单失败']);
            }
        } catch (\Exception $e) {
            // 记录详细错误信息
            $error_info = array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'params' => $_REQUEST,
                'time' => date('Y-m-d H:i:s')
            );

            // 写入错误日志到文件
            file_put_contents(
                'd:\Adev\gdqshop.cn\runtime\error_log.txt',
                json_encode($error_info, JSON_UNESCAPED_UNICODE) . "\n",
                FILE_APPEND
            );

            return $this->json([
                'status' => 0,
                'msg' => '系统异常，请稍后重试',
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // 确认收货
    public function orderCollect(){
        try {
            $this->checklogin();
            $orderid = input('post.orderid/d');
            if(!$orderid) {
                return $this->json(['status'=>0,'msg'=>'订单ID错误']);
            }
            
            $where = [];
            $where[] = ['aid','=',$this->aid];
            $where[] = ['mid','=',$this->mid];
            $where[] = ['id','=',$orderid];
            
            $order = Db::name('collage_order')->where($where)->find();
            if(!$order) {
                return $this->json(['status'=>0,'msg'=>'订单不存在']);
            }
            
            if($order['status'] != 2) {
                return $this->json(['status'=>0,'msg'=>'该订单状态不允许确认收货']);
            }
            
            // 更新订单状态为已完成
            $result = Db::name('collage_order')
                ->where($where)
                ->update(['status'=>3, 'collecttime'=>time()]);
            
            if($result) {
                return $this->json(['status'=>1,'msg'=>'已确认收货']);
            } else {
                return $this->json(['status'=>0,'msg'=>'确认收货失败']);
            }
        } catch (\Exception $e) {
            // 记录详细错误信息
            $error_info = array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'params' => $_REQUEST,
                'time' => date('Y-m-d H:i:s')
            );

            // 写入错误日志到文件
            file_put_contents(
                'd:\Adev\gdqshop.cn\runtime\error_log.txt',
                json_encode($error_info, JSON_UNESCAPED_UNICODE) . "\n",
                FILE_APPEND
            );

            return $this->json([
                'status' => 0,
                'msg' => '系统异常，请稍后重试',
                'error' => $e->getMessage()
            ]);
        }
    }
    
    public function orderdetail(){
        try {
            $orderid = input('param.id/d');
            $where = [];
            $where[] = ['id', '=', $orderid];
            $where[] = ['aid', '=', $this->aid];
            $where[] = ['mid', '=', $this->mid];
            
            $order = Db::name('collage_order')
                ->where($where)
                ->find();
            
            if(!$order) {
                return $this->json(['status'=>0,'msg'=>'订单不存在']);
            }
            
            // 确保supplier_id字段存在且有值
            if(!isset($order['supplier_id']) || is_null($order['supplier_id']) || $order['supplier_id'] === '') {
                $order['supplier_id'] = 0;
            }
            
            // 确保createtime字段存在且有值
            if(!isset($order['createtime']) || is_null($order['createtime']) || $order['createtime'] == 0) {
                $order['createtime'] = time();
            }
            
            // 获取订单商品详情
            $product = Db::name('collage_product')
                ->where('aid', $this->aid)
                ->where('id', $order['proid'])
                ->find();
            
            if($product) {
                // 前端期望的字段名映射
                $order['proname'] = $product['name'];
                $order['propic'] = $product['pic'];
                $order['product_name'] = $product['name'];
                $order['product_pic'] = $product['pic'];
                // 只在订单没有supplier_id且商品有有效supplier_id时才从商品获取
                if(empty($order['supplier_id']) && isset($product['supplier_id']) && !empty($product['supplier_id'])) {
                    $order['supplier_id'] = $product['supplier_id'];
                }
            }

            // 格式化订单创建时间 - 确保时间戳正确处理
            if(is_numeric($order['createtime']) && $order['createtime'] > 0) {
                $order['createtime'] = date('Y-m-d H:i:s', $order['createtime']);
            } else {
                // 如果时间戳无效，尝试从数据库重新获取或设置默认值
                $order['createtime'] = date('Y-m-d H:i:s');
            }

            // 格式化支付时间
            if($order['paytime']) {
                $order['paytime'] = date('Y-m-d H:i:s', $order['paytime']);
            }

            // 确保freight_type有合理的默认值
            // 根据用户说明：配送方式只有3种可能性
            // 1. 普通拼团：邮寄 (pstype=0或10)
            // 2. 普通拼团：到店取货 (pstype=1)
            // 3. 名店推广拼团：到店消费 (pstype=1，bid>0)
            
            if(!isset($order['freight_type']) || is_null($order['freight_type']) || !in_array($order['freight_type'], [0, 1, 2, 3, 4, 10])) {
                if($product && $product['bid'] > 0) {
                    // 名店推广拼团 - 到店消费
                    $order['freight_type'] = 1;
                } else {
                    // 普通拼团 - 默认邮寄
                    $order['freight_type'] = 0;
                }
            }
            
            // 确保freight_text也不为null
            if(!isset($order['freight_text']) || is_null($order['freight_text']) || $order['freight_text'] === '') {
                switch($order['freight_type']) {
                    case 1:
                        $order['freight_text'] = '到店取货';
                        break;
                    case 2:
                        $order['freight_text'] = '商家配送';
                        break;
                    case 3:
                        $order['freight_text'] = '自动发货';
                        break;
                    case 4:
                        $order['freight_text'] = '在线卡密';
                        break;
                    case 10:
                        $order['freight_text'] = '包邮';
                        break;
                    default:
                        $order['freight_text'] = '邮寄';
                        break;
                }
            }
            
            // 获取商品市场价
            if($product) {
                $order['market_price'] = isset($product['market_price']) ? $product['market_price'] : $order['sell_price'];
            } else {
                $order['market_price'] = $order['sell_price'];
            }
            
            // 获取订单团队信息
            if($order['teamid']) {
                $team = Db::name('collage_order_team')
                    ->where('id', $order['teamid'])
                    ->find();
                $order['team_info'] = $team;
            }
            
            // 获取订单地址
            if($order['addressid']) {
                $address = Db::name('member_address')
                    ->where('id', $order['addressid'])
                    ->find();
                $order['address_info'] = $address;
            }
            
            // 返回数据
            return $this->json([
                'status' => 1,
                'msg' => '获取订单详情成功',
                'detail' => $order
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 0,
                'msg' => '系统异常，请稍后重试',
                'error' => $e->getMessage()
            ]);
        }
    }
}