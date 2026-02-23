<?php
// JK客户定制


//custom_file(douyin_groupbuy)
namespace app\custom;
use think\facade\Db;
class DouyinGroupbuyCustom
{   
    //检查设置
    public static function checkset($aid,$bid){
        if(getcustom('douyin_groupbuy')){
            $set = Db::name('douyin_groupbuy_set')->where('aid',$aid)->where('bid',$bid)->find();
            if(!$set){
                return ['status'=>0,'msg'=>'抖音团购设置不存在'];
            }
            if(empty($set['client_key']) || empty($set['client_secret'])){
                return ['status'=>0,'msg'=>'抖音团购设置不完善'];
            }
            return ['status'=>1,'set'=>$set];
        }
    }

    // 获取抖音分类
    // @param $aid $bid
    // @param $category_id 行业类目ID，返回当前id下的直系子类目信息；传0或者不传，均返回所有一级行业类目
    // @param $query_category_type 类目查询方式 0 - 返回单层结构子类目信息 1 - 返回树形结构子类目信息
    public static function getcategory($aid,$bid=0,$category_id=0,$account_id=0,$query_category_type=1){
        if(getcustom('douyin_groupbuy')){
            $getDouyinAccessToken = self::getDouyinAccessToken($aid,$bid);
            if($getDouyinAccessToken['status'] == 0){
                return $getDouyinAccessToken;
            }
            $access_token = $getDouyinAccessToken['access_token'];

            $url = 'https://open.douyin.com/goodlife/v1/goods/category/get/';
            $data = '?access_token='.$access_token.'&category_id='.$category_id.'&account_id='.$account_id.'&query_category_type='.$query_category_type;
            $geturl = $url.$data;
            $res = curl_get($geturl);
            $res = json_decode($res,true);
            if($res['data']['error_code'] == 0){
                return ['status'=>1,'data'=>$res];
            }else{
                return ['status'=>0,'msg'=>$res['data']['description']];
            }
        }
    }
    // 获取商品
    // @param $aid $bid $account_id
    // @param $cursor 第一页不传，之后用前一次返回的next_cursor传入进行翻页 $count 分页数量，不传默认为5，最大上限50
    public static function getdyproduct($aid,$bid=0,$account_id=0,$cursor=0,$count=5){
        if(getcustom('douyin_groupbuy')){
            $getDouyinAccessToken = self::getDouyinAccessToken($aid,$bid);
            if($getDouyinAccessToken['status'] == 0){
                return $getDouyinAccessToken;
            }
            $access_token = $getDouyinAccessToken['access_token'];
            $headers = [
                'Content-Type: application/json',
                'access-token: '.$access_token,
            ];
            //goods_creator_type 0 - 查询服务商/开发者创建的商品（默认）1 - 查询商家（account_id）创建的商品
            $url = 'https://open.douyin.com/goodlife/v1/goods/product/online/query/';
            if($cursor){
                $data = '?goods_creator_type=1&account_id='.$account_id.'&cursor='.$cursor.'&count='.$count;
            }else{
                $data = '?goods_creator_type=1&account_id='.$account_id.'&count='.$count;
            }
            $geturl = $url.$data;
            $res = curl_get($geturl,'',$headers);
            $res = json_decode($res,true);
            if($res['data']['error_code'] == 0){
                return ['status'=>1,'data'=>$res];
            }else{
                return ['status'=>0,'msg'=>$res['data']['description']];
            }
        }
    }

    // 同步商品
    // @param $aid $bid
    // @param $data 提交数据
    public static function tongbu_product($aid,$bid=0,$data){
        if(getcustom('douyin_groupbuy')){
            //同步到抖音团购商品端
            $checkset = self::checkset(aid,bid);
            if($checkset['status'] == 0){
                return $checkset;
            }
            $set = $checkset['set'];
            if(empty($set['account_id'])){
                return ['status'=>0,'msg'=>'抖音团购设置不完善'];
            }
            $set['account_id'] = $set['account_id'];

            $getDouyinAccessToken = self::getDouyinAccessToken($aid,$bid);
            if($getDouyinAccessToken['status'] == 0){
                return $getDouyinAccessToken;
            }
            $access_token = $getDouyinAccessToken['access_token'];

            $url = 'https://open.douyin.com/goodlife/v1/goods/product/save/';
            $postdata = json_encode($data);

            $headers = [
                'Content-Type: application/json',
                'access-token: '.$access_token,
            ];
            $res = curl_post($url, $postdata,0,$headers);
            $res = json_decode($res,true);
            if($res){
                if($res['data']['error_code'] == 0){
                    return ['status'=>1,'data'=>$res];
                }else{
                    return ['status'=>0,'msg'=>$res['data']['description']];
                }
            }else{
                return ['status'=>0,'msg'=>'操作失败'];
            }
            
        }
    }

    // 验券准备
    // @param $aid $bid
    // @param $encrypted_data 从二维码解析出来的标识（传参前需要先进行 URL 编码）
    // @param $code 提交数据   原始的抖音团购券码。
    // @param $poi_id 提交数据 核销的抖音门店id。
    public static function getdycodeinfo($aid,$bid=0,$encrypted_data='',$code='',$poi_id=''){
        if(getcustom('douyin_groupbuy')){
            $getDouyinAccessToken = self::getDouyinAccessToken($aid,$bid);
            if($getDouyinAccessToken['status'] == 0){
                return $getDouyinAccessToken;
            }
            $access_token = $getDouyinAccessToken['access_token'];
            $headers = [
                'Content-Type: application/json',
                'access-token: '.$access_token,
            ];

            $url = 'https://open.douyin.com/goodlife/v1/fulfilment/certificate/prepare/';
            if($encrypted_data){
                $data = '?encrypted_data='.$encrypted_data;
            }else if($code){
                $data = '?code='.$code;
            }else{
                return ['status'=>0,'msg'=>'请输入抖音券码信息'];
            }
            if($poi_id){
                $data .= '&poi_id='.$poi_id;
            }
            $geturl = $url.$data;
            $res = curl_get($geturl,'',$headers);
            $res = json_decode($res,true);
            if($res['data']['error_code'] == 0){
                return ['status'=>1,'data'=>$res];
            }else{
                return ['status'=>0,'msg'=>$res['data']['description']];
            }
        }
    }

    // 核销抖音券
    // @param $aid
    // @param $poi_id  抖音门店ID
    // @param $verify_token 一次验券的标识 
    // @param $encrypted_data 从二维码解析出来的标识
    public static function hexiao_dygroupbuy($aid,$bid,$verify_token,$poi_id,$encrypted_codes=[]){
        if(getcustom('douyin_groupbuy')){
            $getDouyinAccessToken = self::getDouyinAccessToken($aid,$bid);
            if($getDouyinAccessToken['status'] == 0){
                return $getDouyinAccessToken;
            }
            $access_token = $getDouyinAccessToken['access_token'];

            $url = 'https://open.douyin.com/goodlife/v1/fulfilment/certificate/verify/';
            $data = array(
                'verify_token' => $verify_token,
                'poi_id' => ''.$poi_id.'',
                'encrypted_codes' => $encrypted_codes
            ); 
            $postdata = json_encode($data);

            $headers = [
                'Content-Type: application/json',
                'access-token: '.$access_token,
            ];

            $res = curl_post($url, $postdata,0,$headers);
            $res = json_decode($res,true);
            if($res){
                if($res['data']['error_code'] == 0){
                    return ['status'=>1,'data'=>$res];
                }else{
                    return ['status'=>0,'msg'=>$res['data']['description']];
                }
            }else{
                return ['status'=>0,'msg'=>'操作失败'];
            }
        }
    }

    // 撤回核销抖音券
    // @param $aid
    // @param $verify_id 代表券码一次核销的唯一标识（验券时返回）(次卡撤销多次时请填0) 
    // @param $certificate_id 代表一张券码的标识（验券时返回）
    public static function cancel_dygroupbuy($aid,$bid,$verify_id,$certificate_id){
        if(getcustom('douyin_groupbuy')){
            die;//暂时不能用 $verify_id报错
            $getDouyinAccessToken = self::getDouyinAccessToken($aid,$bid);
            if($getDouyinAccessToken['status'] == 0){
                return $getDouyinAccessToken;
            }
            $access_token = $getDouyinAccessToken['access_token'];

            $url = 'https://open.douyin.com/goodlife/v1/fulfilment/certificate/cancel/';
            $data = array(
                'verify_id' => $verify_id,
                'certificate_id' => ''.$certificate_id.'',
            ); 
            $postdata = json_encode($data);

            $headers = [
                'Content-Type: application/json',
                'access-token: '.$access_token,
            ];

            $res = curl_post($url, $postdata,0,$headers);
            $res = json_decode($res,true);
            if($res){
                if($res['data']['error_code'] == 0){
                    return ['status'=>1,'data'=>$res];
                }else{
                    return ['status'=>0,'msg'=>$res['data']['description']];
                }
            }else{
                return ['status'=>0,'msg'=>'操作失败'];
            }
        }
    }

    // 获取抖音AccessToken
    // @param $aid
    // @param $bid
    // @return mixed
    public static function getDouyinAccessToken($aid,$bid){
        if(getcustom('douyin_groupbuy')) {
            $checkset = self::checkset($aid,$bid);
            if($checkset['status'] == 0){
                return $checkset;
            }
            $set = $checkset['set'];
            $client_key    = $set['client_key'];
            $client_secret = $set['client_secret'];

            $access_token = cache($client_key . '_access_token');
            if ($access_token && !empty($access_token)) {        
                return ['status'=>1,'access_token'=>$access_token];
            } else {
                $url = 'https://open.douyin.com/oauth/client_token/';
                $data = [];
                $data['grant_type']    = 'client_credential';
                $data['client_key']    = $client_key;
                $data['client_secret'] = $client_secret;
                $postdata = json_encode($data);

                $headers = [
                    'Content-Type: application/json',
                ];
                $res = curl_post($url, $postdata,0,$headers);
                $res = json_decode($res,true);
                if($res){
                    if($res['data']['error_code'] == 0){
                        $access_token = $res['data']['access_token'];
                    }else{
                        return ['status' => 0, 'msg' =>$res['data']['description']];
                    }
                }else{
                    return ['status' => 0, 'msg' =>'获取token失败'];
                }
                cache($client_key.'_access_token', $access_token,  $res['expires_in'] - 100);
                return ['status'=>1,'access_token'=>$access_token];
            }
        } 
    }

    public static function autoclose(){
        if(getcustom('douyin_groupbuy')) {
            //关闭超时的订单
            $admins = Db::name('admin')->where('status',1)->field('id')->select()->toArray();
            if($admins){
                foreach($admins as $admin){
                    //查询设置
                    $sets = Db::name('douyin_groupbuy_set')->where('aid',$admin['id'])->field('id,aid,bid,autoclose')->select()->toArray();
                    if($sets){
                        foreach($sets as $set){
                            $autoclose = $set && $set['autoclose']?$set['autoclose']:0;
                            $endtime = time()-$autoclose*60;
                            Db::name('shop_order')->where('aid',$set['aid'])->where('bid',$set['bid'])->where('isdygroupbuy',1)->where('status',0)->where('createtime','<=',$endtime)->update(['status'=>4]);
                        }
                    }
                }
            }
        }
    }

    public static function deal_codeinfo($aid,$bid,$mendian,$set,$member,$codes='',$encrypted_datas='',$orderid=0){
        if(getcustom('douyin_groupbuy')) {
            //验证订单团购券信息

            $certificate_ids = [];//券码id集合
            $proids          = [];//商品id不重复集合
            $products        = [];//商品信息集合
            if($codes){
                foreach($codes as $cv){
                    //获取抖音券码信息
                    $res = self::getdycodeinfo($aid,$bid,'',$cv,$mendian['poi_id']);
                    if($res['status'] ==1){
                        $order_id = $res['data']['data']['order_id'];
                        //可用团购券列表
                        $certificates = $res['data']['data']['certificates'];
                        if(!$certificates || empty($certificates)){
                            return ['status'=>0, 'msg'=>'未获取到团购券信息'];
                        }
                        foreach($certificates as $cv){
                            $sku = $cv['sku'];
                            if($sku && $sku['sku_id']){
                                $deal_sku = self::deal_sku($aid,$bid,$set,$sku,$cv,$certificate_ids,$proids,$member,'',$orderid);
                                if($deal_sku['status'] == 1){
                                    $certificate_ids = $deal_sku['certificate_ids'];
                                    $proids          = $deal_sku['proids'];
                                    $products        = $deal_sku['products'];
                                }else{
                                    $msg = $deal_sku && $deal_sku['msg']?$deal_sku['msg']:'抖音兑换码获取信息失败';
                                    return ['status'=>0, 'msg'=>$msg];
                                    break;
                                }
                            }else{
                                return ['status'=>0, 'msg'=>'团购券商品未绑定此系统商品'];
                                break;
                            }
                        }
                    }else{
                        $msg = $res && $res['msg']?$res['msg']:'抖音兑换码获取信息失败';
                        return ['status'=>0, 'msg'=>$msg];
                    }
                }
                unset($cv);
                if(empty($certificate_ids)){
                    return ['status'=>0, 'msg'=>'团购券码未获取到有效信息'];
                }
                if(empty($products)){
                    return ['status'=>0, 'msg'=>'团购券码未获取到对应的商品'];
                }
            }
            if($encrypted_datas){
                //券码id集合2
                $certificate_ids2 = [];
                foreach($encrypted_datas as $ev){
                    $get_url   = shortUrlToLongUrl(urldecode($ev));
                    $qqData    = getPathParams($get_url);
                    $encrypted_data = $qqData['object_id'];
                    //获取抖音券码信息
                    $res = self::getdycodeinfo($aid,$bid,$encrypted_data,'',$mendian['poi_id']);
                    if($res['status'] ==1){
                        $order_id = $res['data']['data']['order_id'];
                        //可用团购券列表
                        $certificates = $res['data']['data']['certificates'];
                        if(!$certificates || empty($certificates)){
                            return ['status'=>0, 'msg'=>'未获取到团购券信息'];
                        }
                        foreach($certificates as $cv){
                            $sku = $cv['sku'];
                            if($sku && $sku['sku_id']){
                                $deal_sku = self::deal_sku($aid,$bid,$set,$sku,$cv,$certificate_ids,$proids,$member,$certificate_ids2,$orderid);
                                if($deal_sku['status'] == 1){
                                    $certificate_ids  = $deal_sku['certificate_ids'];
                                    $certificate_ids2 = $deal_sku['certificate_ids2'];
                                    $proids           = $deal_sku['proids'];
                                    $products         = $deal_sku['products'];
                                }else{
                                    $msg = $deal_sku && $deal_sku['msg']?$deal_sku['msg']:'抖音兑换码获取信息失败';
                                    return ['status'=>0, 'msg'=>$msg];
                                    break;
                                }
                            }else{
                                return ['status'=>0, 'msg'=>'团购券商品未绑定此系统商品'];
                                break;
                            }
                        }
                    }else{
                        $msg = $res && $res['msg']?$res['msg']:'抖音兑换码获取信息失败';
                        return ['status'=>0, 'msg'=>$msg];
                    }
                }
                unset($ev);
                if(empty($certificate_ids2)){
                    return ['status'=>0, 'msg'=>'扫码团购券未获取到对应的商品'];
                }
            }
            if(empty($certificate_ids)){
                return ['status'=>0, 'msg'=>'未获取到有效团购券'];
            }
            if(empty($products)){
                return ['status'=>0, 'msg'=>'未获取到团购券对应的商品'];
            }
            return ['status'=>1, 'msg'=>''];
        }
    }

    public static function deal_sku($aid,$bid,$set,$sku,$cv,$certificate_ids,$proids,$member,$certificate_ids2=[],$orderid=0){
        if(getcustom('douyin_groupbuy')) {
            if($sku['account_id'] != $set['account_id']){
                return ['status'=>0, 'msg'=>'团购券商户不是本系统商户'];
            }

            //查询是否存在正在兑换的订单
            $where = [];
            $where[] = ['aid','=',$aid];
            $where[] = Db::raw("find_in_set(".$cv['certificate_id'].",dycertificate_ids)");
            $where[] = ['status','=',0];
            if($orderid){
                $where[] = ['id','<>',$orderid];
            }
            $count  = 0+ Db::name('shop_order')->where($where)->count('id');
            if($count){
                return ['status'=>0, 'msg'=>'此团购券已提交过订单，请到订单列表结束订单后再操作'];
            }

            //如果是次卡查询剩余次数
            if($sku['groupon_type']== 3){
                if(!$sku['time_card'] || empty($sku['time_card'])){
                    return ['status'=>0, 'msg'=>'团购券次卡数据有误'];
                }
                //次卡总次数
                $time_count = $sku['time_card']['time_count'];
                if(!$time_count || $time_count<=0){
                    return ['status'=>0, 'msg'=>'团购券次卡总次数有误'];
                }
                //次卡已使用次数
                $times_used = $sku['time_card']['times_used'];
                if($times_used>=$time_count){
                    return ['status'=>0, 'msg'=>'团购券次卡次数不足'];
                }
                //查询是否存在已兑换完成数据，且等于次卡总次数
                $where = [];
                $where[] = ['aid','=',$aid];
                $where[] = Db::raw("find_in_set(".$cv['certificate_id'].",dycertificate_ids)");
                $where[] = ['paytime','>',0];
                $count  = 0+ Db::name('shop_order')->where($where)->count('id');
                if($count == $time_count){
                    return ['status'=>0, 'msg'=>'团购券次卡次数不足'];
                }
            }else{

                //查询是否存在已兑换完成数据
                $where = [];
                $where[] = ['aid','=',$aid];
                $where[] = Db::raw("find_in_set(".$cv['certificate_id'].",dycertificate_ids)");
                $where[] = ['paytime','>',0];
                $forder  = Db::name('shop_order')->where($where)->field('ordernum')->find();
                if($forder){
                    return ['status'=>0, 'msg'=>'存在团购券:已兑换过商品，订单号:'.$forder['ordernum']];
                }

            }

            if(in_array($cv['certificate_id'],$certificate_ids)){
                return ['status'=>0, 'msg'=>'存在重复团购券商品，请重新兑换'];
            }
            $certificate_id = ''.$cv['certificate_id'];
            array_push($certificate_ids,$certificate_id);
            array_push($certificate_ids2,$certificate_id);

            //处理商品信息
            $deal_product = self::deal_product($aid,$bid,$member,$sku['sku_id']);
            if($deal_product['status'] == 1){
                $product = $deal_product['product'];
            }else{
                $msg = $deal_product && $deal_product['msg']?$deal_product['msg']:'获取失败';
                return ['status'=>0, 'msg'=>$msg];
            }

            if(!in_array($product['id'],$proids)){
                array_push($proids,$product['id']);
            }
            $product['certificate_id'] = ''.$cv['certificate_id'];
            $product['num'] = 1;
            $product['ggname'] = '未选择';
            $product['ggid']   = 0;
            $product['ggpricetype'] = 0;//价格类型 0：不显示 1：显示
            $product['ggprice']     = 0;
            $products[] = $product;

            return ['status'=>1,'certificate_ids'=>$certificate_ids,'certificate_ids2'=>$certificate_ids2,'proids'=>$proids,'products'=>$products];
        }
    }

    public static function deal_product($aid,$bid,$member,$dyproid=0,$proid=0,$ggid=0){

        if(!$dyproid && !$proid){
            return ['status'=>0,'msg'=>'商品参数错误'];
        }

        $where = [];
        if($dyproid){
            $where[] = ['dyg_product_id','=',$dyproid];
        }
        if($proid){
            $where[] = ['id','=',$proid];
        }
        $where[] = ['aid','=',$aid];
        $where[] = ['bid','=',$bid];
        //查询商品信息
        $product = Db::name('shop_product')->where($where)->find();
        if(!$product){
            return ['status'=>0,'msg'=>'商品不存在或已下架'];
        }
        $proid = $product['id'];
        //判断商家是否还存在
        if($product['bid']>0){
            $business = Db::name('business')->where('id',$product['bid'])->find();
            if(!$business){
                return ['status'=>0,'msg'=>'商品['.$product['name'].']异常，请重新下单'];
            }
        }
        if($product['status']==0){
            return ['status'=>0,'msg'=>$product['name'].'商品未上架'];
        }
        if($product['status']==2 && (strtotime($product['start_time']) > time() || strtotime($product['end_time']) < time())){
            return ['status'=>0,'msg'=>$product['name'].'商品未上架'];
        }
        if($product['status']==3){
            $start_time = strtotime(date('Y-m-d '.$product['start_hours']));
            $end_time = strtotime(date('Y-m-d '.$product['end_hours']));
            if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
                return ['status'=>0,'msg'=>$product['name'].'商品未上架'];
            }
        }

        if($ggid >0){
            $guige = Db::name('shop_guige')->where('id',$ggid)->find();
            if(!$guige){
                Db::name('shop_cart')->where('aid',$aid)->where('ggid',$ggid)->delete();
                return ['status'=>0,'msg'=>'商品该规格不存在或已下架'];
            }
            if($guige['stock'] < $num){
                return ['status'=>0,'msg'=>'库存不足'];
            }
            $gettj = explode(',',$product['gettj']);
            if(!in_array('-1',$gettj) && !in_array($member['levelid'],$gettj) && (!in_array('0',$gettj) || $member['subscribe']!=1)){ //不是所有人
                if(!$product['gettjtip']) $product['gettjtip'] = '没有权限购买该商品';
                return ['status'=>0,'msg'=>$product['gettjtip'],'url'=>$product['gettjurl']];
            }
            // if($guige['limit_start'] > 0 && $num < $guige['limit_start']){
            //     return ['status'=>0,'msg'=>'['.$product['name'].']['.$guige['name'].'] '.$guige['limit_start'].'件起售'];
            // }
        }else{
            $guige = '';
        }
        
        // $limit_start_state = 1;
        // if($limit_start_state==1 && $product['limit_start'] > 0 && $num < $product['limit_start']){
        //     return ['status'=>0,'msg'=>'['.$product['name'].'] '.$product['limit_start'].'件起售'];
        // }
        // if($product['perlimitdan'] > 0 && $num > $product['perlimitdan']){
        //     return ['status'=>0,'msg'=>'['.$product['name'].'] 每单限购'.$product['perlimitdan'].'件'];
        // }
        // if($product['perlimit'] > 0){
        //     $buynum = $num + Db::name('shop_order_goods')->where('aid',$aid)->where('mid',mid)->where('proid',$product['id'])->where('status','in','0,1,2,3')->sum('num');
        //     if($buynum > $product['perlimit']){
        //         return ['status'=>0,'msg'=>'['.$product['name'].'] 每人限购'.$product['perlimit'].'件'];
        //     }
        // }
        //$guige = $this->formatguige($guige, $product['bid'],$product['lvprice']);
        return ['status'=>1,'product'=>$product,'guige'=>$guige];
    }

    //支付时抖音团购券再次验证
    public static function checkpay($aid,$order,$member){
        $dyset = Db::name('douyin_groupbuy_set')->where('aid',$aid)->where('bid',$order['bid'])->field('status,autoclose')->find();
        if(!$dyset || $dyset['status']!=1){
            return ['status'=>0,'msg'=>'抖音团购券兑换设置未开启'];
        }
        $endtime = time()-$dyset['autoclose']*60;
        if($order['createtime']<=$endtime){
            return ['status'=>0,'msg'=>'此订单兑换已超时间，请重新下单'];
        }
        if(empty($order['dycodes']) && empty($order['dyencrypted_datas'])){
            return ['status'=>0,'msg'=>'兑换出错，请重新下单'];
        }

        $mfield = 'id,name,tel,pic,province,city,district,area,address,longitude,latitude,poi_id';
        $mendian = Db::name('mendian')->where('id',$order['mdid'])->where('aid',$aid)->where('bid',$order['bid'])->where('status',1)->where('poi_id !=""')->field($mfield)->find();
        if(!$mendian) return ['status'=>0,'msg'=>'订单所选门店已失效'];

        $checkset = self::checkset($aid,$order['bid']);
        if($checkset['status'] == 0){
            return ['status'=>0,'msg'=>'系统暂未启用核销功能'];
        }
        $set = $checkset['set'];

        $dycodes = '';
        if($order['dycodes']) $dycodes = explode(',',$order['dycodes']);

        $dyencrypted_datas = '';
        if($order['dyencrypted_datas']) $dyencrypted_datas = explode(',',$order['dyencrypted_datas']);

        $deal_codeinfo = self::deal_codeinfo($aid,$order['bid'],$mendian,$set,$member,$dycodes,$dyencrypted_datas,$order['id']);
        if(!$deal_codeinfo || $deal_codeinfo['status']!=1){
            $msg = $deal_codeinfo && $deal_codeinfo['msg']?$deal_codeinfo['msg']:'抖音兑换码获取信息失败';
            return ['status'=>0,'msg'=>$msg];
        }
        return ['status'=>1,'msg'=>''];
    }

    public static function dealpayafter($aid,$order,$oglist){
        $dyisrefund = false;//是否退款
        $dydatas = json_decode($order['dydatas'],true);
        foreach($dydatas as $dv){
            $res = self::hexiao_dygroupbuy($aid,$order['bid'],$dv['verify_token'],$order['dypoi_id'],$dv['encrypted_codes']);
            if(!$res || $res['status'] != 1){
                //退款
                if($order['totalprice']>0){
                    $res2 = \app\common\Order::refund($order,$order['totalprice'],'核销抖音团购券失败'.$order['ordernum']);
                }else{
                    $res2 = ['status'=>1,'msg'=>''];
                }
                if($res2 && $res2['status'] == 1){
                    //关闭订单
                    $up = Db::name('shop_order')->where('id',$order['id'])->update(['status'=>4]);
                    if($up){
                        $dyisrefund = true;
                        //加库存
                        $oglist = Db::name('shop_order_goods')->where('orderid',$order['id'])->field('id,aid,ggid,num')->select()->toArray();
                        if($oglist){
                            foreach($oglist as $og){
                                Db::name('shop_guige')->where('aid',$aid)->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("IF(sales>=".$og['num'].",sales-".$og['num'].",0)")]);
                                Db::name('shop_product')->where('aid',$aid)->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("IF(sales>=".$og['num'].",sales-".$og['num'].",0)")]);
                            }
                            Db::name('shop_order_goods')->where('orderid',$order['id'])->update(['status'=>4]);
                        }
                        
                    }
                }
            }
        }
        if(!$dyisrefund){
            //查询此订单抖音团购设置，是否设置了核销完成
            $dypstype  = Db::name('douyin_groupbuy_set')->where('aid',$order['aid'])->where('bid',$order['bid'])->value('pstype');
            if($dypstype && $dypstype == 1){
                $res = \app\common\Order::collect($order);
                if($res && $res['status'] == 1){
                    Db::name('shop_order')->where('id',$order['id'])->update(['status'=>3,'collect_time'=>time()]);
                    Db::name('shop_order_goods')->where('orderid',$order['id'])->update(['status'=>3,'endtime'=>time()]);
                }
            }
        }
    }
}