<?php
// JK客户定制

//custom_file(express_maiyatian) 文档https://doc.weixin.qq.com/doc/w3_AY0AGwZcAB8ZRhkGLkcQemGzbuJMr?scode=AHMAHgcfAA0QpCCzaJAT4AYwbFACw
namespace app\custom;
use think\facade\Db;
class MaiYaTianCustom
{
    public static $post_url = 'http://open.maiyatian.com';//测试域名 http://open.test.maiyatian.com 生产域名 http://open.maiyatian.com
    public static function get_sign($set,$data = [])
    {
        if(getcustom('express_maiyatian')){

            ksort($data, SORT_STRING);
            $string = $set['myt_appsecret'];
            foreach ($data as $key => $v) {
                $string .= "{$key}{$v}";
            }
            $string .= $set['myt_appsecret'];
            $sign = strtoupper(md5($string));

            return $sign;
        }
    }

    public static function check_info($aid,$set,$order=[],$prolist=[],$other=[])
    {
        if(getcustom('express_maiyatian')){

            if(!$set['myt_status']) return ['status'=>0,'msg'=>'麦芽田配送未开启'];
            if(!$set['myt_appkey'] || !$set['myt_appsecret']) return ['status'=>0,'msg'=>'麦芽田配送必要配置未填写'];

            $shop = Db::name('peisong_myt_shop')->where('aid',$aid)->where('bid',$order['bid'])->where('is_del',0)->find();
            if(!$shop) return ['status'=>0,'msg'=>'门店不存在'];
            if($shop['status'] !=1) return ['status'=>0,'msg'=>'门店状态异常'];

            //查询总平台账号余额及多商户余额
            $res_balance = \app\custom\MaiYaTianCustom::merchant_balance($aid,$set);
            if(!$res_balance ['status'] == 1) return ['status'=>0,'msg'=>$res_balance['msg']];

            if($order){
                //预估配送费
                $res_price = \app\custom\MaiYaTianCustom::order_price($aid,$set,$order,$prolist,$other);
                if($res_price['status']== 0) return ['status'=>0,'msg'=>$res_price['msg']];

                //配送费信息
                $psfee_info = $res_price['data'];
                $psfee_min  = $psfee_info['min_price'];
                $psfee_max  = $psfee_info['max_price'];

                //总账号余额
                $p_money = $res_balance['data'];
                if($p_money<=0){
                    return ['status'=>0,'msg'=>'配送失败，平台账号余额不足'];
                }
                if($p_money<$psfee_max){
                    return ['status'=>0,'msg'=>'配送失败，平台账号余额不足'];
                }

                if($order['bid']>0){
                    $business = Db::name('business')->where('id',$order['bid'])->field('id,name,money')->find();
                    if(!$business){
                        return ['status'=>0,'msg'=>'配送失败，商户不存在'];
                    }
                    //查询多商户
                    $b_money = $business['money'];
                    if($b_money<=0){
                        return ['status'=>0,'msg'=>'配送失败，商户余额不足'];
                    }
                    if($b_money<$psfee_max){
                        return ['status'=>0,'msg'=>'配送失败，商户余额不足'];
                    }
                }
            }else{
                return ['status'=>0,'msg'=>'订单信息不存在'];
            }
            return ['status'=>1,'msg'=>''];
        }
    }

    //门店操作 添加或者编辑
    public static function shop_opt($aid,$shopdata,$set=[],$type = 1)
    {
        if(getcustom('express_maiyatian')){
            //返回我方门店ID "{"code":1,"message":"success","data":{"shop_id":724335}}"
            if(!$set){
                $set = Db::name('peisong_set')->where('aid',$aid)->find();
                if(!$set){
                    return ['status'=>0,'msg'=>'系统设置不存在'];
                }
            }

            $data = [];
            $data['app_key']   = $set['myt_appkey'];
            $data['timestamp'] = time();
            $data['version']   = '1';

            $params = [];
            $params['origin_id'] = $shopdata['origin_id'];//你方门店ID
            $params['name']      = $shopdata['name'];//门店名称
            $params['city']      = $shopdata['city'];//城市编码（根据城市列表接口获取）
            $params['phone']     = $shopdata['phone'];//手机号码
            $params['address']   = $shopdata['address'];//门店地址

            $params['longitude'] = $shopdata['longitude'];//门店经度
            $params['latitude']  = $shopdata['latitude'];//门店纬度

            $params['category']  = $shopdata['category'];//物品类别，见附录-数据字典
            $params['map_type']  = $shopdata['map_type'];//坐标类型，见附录-数据字典

            $data['params']    = json_encode($params);

            $data['sign'] = self::get_sign($set,$data);

            $postdata = json_encode($data);

            if($type == 1){
                $res = curl_post(self::$post_url.'/shop/add/', $postdata);
            }else{
                $res = curl_post(self::$post_url.'/shop/update/', $postdata);
            }
            $res = json_decode($res,true);
            if(!$res['code'] || $res['code'] !=1){
                return ['status'=>0,'msg'=>$res['message']];
            }else{
                return ['status'=>1,'msg'=>'','data'=>$res['data']['shop_id']];
            }
        }
    }

    //门店查询
    public static function shop_query($aid,$bid)
    {

        if(getcustom('express_maiyatian')){
            die;

            if(!$set){
                $set = Db::name('peisong_set')->where('aid',$aid)->find();
                if(!$set){
                    return ['status'=>0,'msg'=>'系统设置不存在'];
                }
            }
            $shop = Db::name('peisong_myt_shop')->where('aid',$aid)->where('bid',$bid)->where('is_del',0)->find();
            if(!$shop){
                return ['status'=>0,'msg'=>'门店不存在'];
            }
            if($shop['status'] !=1){
                return ['status'=>0,'msg'=>'门店状态异常'];
            }

            $data = [];
            $data['app_key']   = $set['myt_appkey'];
            $data['timestamp'] = time();
            $data['version']   = '1';

            $params = [];
            $params['offset'] = 0;
            $params['count']  = 1;

            $data['params']    = json_encode($params);

            $data['sign'] = self::get_sign($set,$data);

            $postdata = json_encode($data);
            $res = curl_post(self::$post_url.'/shop/query/', $postdata);
            echo "<pre>";
            var_dump($res);die;
        }
    }
    //门店详情查询
    public static function shop_detail($aid,$origin_id,$set=[])
    {
        if(getcustom('express_maiyatian')){
            if(!$set){
                $set = Db::name('peisong_set')->where('aid',$aid)->find();
                if(!$set){
                    return ['status'=>0,'msg'=>'系统设置不存在'];
                }
            }

            $data = [];
            $data['app_key']   = $set['myt_appkey'];
            $data['timestamp'] = time();
            $data['version']   = '1';

            $params = [];
            $params['origin_id'] = $origin_id;
            $data['params']    = json_encode($params);

            $data['sign']      = self::get_sign($set,$data);

            $postdata = json_encode($data);
            $res = curl_post(self::$post_url.'/shop/detail/', $postdata);
            $res = json_decode($res,true);
            if(!$res['code'] || $res['code'] !=1){
                return ['status'=>0,'msg'=>$res['message']];
            }else{
                //$shop_id = $res['data']['shop_id'];
                return ['status'=>1,'msg'=>'获取成功','data'=>$res['data']];
            }
        }
    }

    //余额查询
    public static function merchant_balance($aid,$set=[])
    {
        if(getcustom('express_maiyatian')){
            //返回我方门店ID "{"code":1,"message":"success","data":{"shop_id":724335}}"
            if(!$set){
                $set = Db::name('peisong_set')->where('aid',$aid)->find();
                if(!$set){
                    return ['status'=>0,'msg'=>'系统设置不存在'];
                }
            }

            $data = [];
            $data['app_key']   = $set['myt_appkey'];
            $data['timestamp'] = time();
            $data['version']   = '1';

            $data['sign'] = self::get_sign($set,$data);

            $postdata = json_encode($data);
            $res = curl_post(self::$post_url.'/merchant/balance/', $postdata);
            $res = json_decode($res,true);
            if(!$res['code'] || $res['code'] !=1){
                return ['status'=>0,'msg'=>$res['message']];
            }else{
                $balance = $res['data']['balance'];
                //更新余额
                $up = Db::name('peisong_set')->where('id',$set['id'])->update(['myt_balance'=>$balance]);
                return ['status'=>1,'msg'=>'获取','data'=>$balance];
            }
        }
    }

    //计算运费
    public static function order_price($aid,$set,$order,$prolist=[],$other=[],$type=0)
    {

        if(getcustom('express_maiyatian')){

            if(!$set){
                $set = Db::name('peisong_set')->where('aid',$aid)->find();
                if(!$set){
                    return ['status'=>0,'msg'=>'系统设置不存在'];
                }
            }
            if(!$set['myt_status']){
                return ['status'=>0,'msg'=>'麦芽田配送未开启'];
            }
            if(!$set['myt_appkey'] || !$set['myt_appsecret']){
                return ['status'=>0,'msg'=>'麦芽田配送必要配置未填写'];
            }
            $shop = Db::name('peisong_myt_shop')->where('aid',$aid)->where('bid',$order['bid'])->where('is_del',0)->find();
            if(!$shop){
                return ['status'=>0,'msg'=>'门店不存在'];
            }
            if($shop['status'] !=1){
                return ['status'=>0,'msg'=>'门店状态异常'];
            }

            //查询门店
            if($other && $other['myt_shop_id']){
                $shop_id = $other['myt_shop_id'];
                $shop = Db::name('peisong_myt_shop')->where('origin_id',$shop_id)->where('aid',$aid)->where('bid',$order['bid'])->where('is_del',0)->field("id,name,address,status,'' as logo,phone as tel,longitude,latitude")->find();
                if(!$shop){
                    return ['status'=>0,'msg'=>'门店不存在'];
                }
                if($shop['status'] !=1){
                    return ['status'=>0,'msg'=>'门店状态异常'];
                }
            }else{
                $shop = Db::name('peisong_myt_shop')->where('aid',$aid)->where('bid',$order['bid'])->where('is_del',0)->order('id asc')->field("id,name,address,status,'' as logo,phone as tel,longitude,latitude")->find();
                if(!$shop){
                    return ['status'=>0,'msg'=>'门店不存在'];
                }
                if($shop['status'] !=1){
                    return ['status'=>0,'msg'=>'门店状态异常'];
                }
                $shop_id = $shop['id'];
            }

            $data = [];
            $data['app_key']   = $set['myt_appkey'];
            $data['timestamp'] = time();
            $data['version']   = '1';

            $params = [];
            $params['dispatch_mode'] = $set['myt_dispatchmode'];//发单模式: 1.省钱 2.最快 3.指派 4.价格从低到高依次呼叫
            if($set['myt_dispatchmode'] == 3){
                $params['logistic']  = $set['myt_logistic'];//指派模式3:单个示例：uupt多个示例: dada,uupt(英文逗号拼接)详见👉配送平台枚举值
            }
            $params['shop_id']       = $shop_id;//你方门店ID
            //$params['origin_id']     = $order['ordernum'];//你方订单号

            $params['receiver_longitude'] = $order['longitude'];//收件人经度（必须和计算价格接口一致）
            $params['receiver_latitude']  = $order['latitude'];//收件人纬度（必须和计算价格接口一致）
            $params['receiver_address']   = $order['area']?$order['area']:'';//收件人地址
            //$params['receiver_name']      = $order['linkman']?$order['linkman']:'';//收件人姓名
            //$params['receiver_phone']     = $order['tel']?$order['tel']:'';//收件人手机号 【虚拟号格式（手机号_分机号码）示例：13700000000_1111 】或 非虚拟号 示例：13700000000

            // $goods = [];
            // if($prolist){
            //     foreach($prolist as $pv){
            //         $gdata = [];
            //         $gdata['name']   = $pv['proname'].' '.$pv['ggname'];
            //         $gdata['number'] = $pv['num'];
            //         $gdata['price']  = $pv['sell_price']*100;//单位分
            //         $gdata['total']  = $pv['num']*$pv['sell_price']*100;//单位分
            //         array_push($goods,$gdata);
            //     }
            // }
            // $params['goods'] = $goods;

            //$params['goods_value'] = $order['totalprice'];//物品价值(单位:分)
            $params['goods_weight']   = $other['myt_weight'] && !empty($other['myt_weight'])?$other['myt_weight']:1;//物品重量[单位：kg]示例：3

            $params['goods_category'] = $other['myt_category'] && !empty($other['myt_category'])?$other['myt_category']:!empty($shop['category'])?$shop['category']:99;//物品类型详见👉物品类别枚举值

            // $subscribe_time = 0;
            // if($order['freight_time']){
            //     $freight_time_arr = explode('~',$order['freight_time']);
            //     if($freight_time_arr && $freight_time_arr[0]){
            //         $subscribe_time = strtotime($freight_time_arr[0]);
            //     }
            // }
            // $params['delivery_time']= $subscribe_time;//期望送达时间 ：Unix时间戳

            $params['map_type']    = 1;//坐标类型高德：1 百度：2

            // $params['order_source']  = 'other';//订单来源
            // $params['order_source_no'] = $order['ordernum'];//订单来源单号（美团必传）
            if($type == 1){
                $params['need_return_error'] = true;//是否包含配送方计价失败的信息 示例： true 或者 false,为true后code返回成功，需要自己处理信息
            }
            $data['params']    = json_encode($params);

            $data['sign'] = self::get_sign($set,$data);

            $postdata = json_encode($data);
            $res = curl_post(self::$post_url.'/order/price/', $postdata);
            $res = json_decode($res,true);
            if(!$res['code'] || $res['code'] !=1){
                return ['status'=>0,'msg'=>$res['message']];
            }else{
                return ['status'=>1,'msg'=>'获取成功','data'=>$res['data']];
            }
        }
    }

    //添加订单
    public static function order_add($aid,$set,$psorderid,$order,$prolist,$other=[])
    {

        if(getcustom('express_maiyatian')){

            if(!$set){
                $set = Db::name('peisong_set')->where('aid',$aid)->find();
                if(!$set){
                    return ['status'=>0,'msg'=>'系统设置不存在'];
                }
            }
            if(!$set['myt_status']){
                return ['status'=>0,'msg'=>'麦芽田配送未开启'];
            }
            if(!$set['myt_appkey'] || !$set['myt_appsecret']){
                return ['status'=>0,'msg'=>'麦芽田配送必要配置未填写'];
            }
            $shop = Db::name('peisong_myt_shop')->where('aid',$aid)->where('bid',$order['bid'])->where('is_del',0)->find();
            if(!$shop){
                return ['status'=>0,'msg'=>'门店不存在'];
            }
            if($shop['status'] !=1){
                return ['status'=>0,'msg'=>'门店状态异常'];
            }

            $data = [];
            $data['app_key']   = $set['myt_appkey'];
            $data['timestamp'] = time();
            $data['version']   = '1';

            $params = [];
            $params['dispatch_mode'] = $set['myt_dispatchmode'];//发单模式: 1.省钱 2.最快 3.指派 4.价格从低到高依次呼叫
            if($set['myt_dispatchmode'] == 3){
                $params['logistic']  = $set['myt_logistic'];//指派模式3:单个示例：uupt多个示例: dada,uupt(英文逗号拼接)详见👉配送平台枚举值
            }

            //查询门店
            if($other && $other['myt_shop_id']){
                $shop_id = $other['myt_shop_id'];
                $shop = Db::name('peisong_myt_shop')->where('origin_id',$shop_id)->where('aid',$aid)->where('bid',$order['bid'])->where('is_del',0)->field("id,name,address,status,'' as logo,phone as tel,longitude,latitude")->find();
                if(!$shop){
                    return ['status'=>0,'msg'=>'门店不存在'];
                }
                if($shop['status'] !=1){
                    return ['status'=>0,'msg'=>'门店状态异常'];
                }
            }else{
                $shop = Db::name('peisong_myt_shop')->where('aid',$aid)->where('bid',$order['bid'])->where('is_del',0)->order('id asc')->field("id,name,address,status,'' as logo,phone as tel,longitude,latitude")->find();
                if(!$shop){
                    return ['status'=>0,'msg'=>'门店不存在'];
                }
                if($shop['status'] !=1){
                    return ['status'=>0,'msg'=>'门店状态异常'];
                }
                $shop_id = $shop['id'];
            }

            $params['shop_id']       = $shop_id;//你方门店ID
            $params['origin_id']     = $order['ordernum'];//你方订单号
            $params['order_source']  = 'other';//订单来源
            $params['order_source_no'] = $order['ordernum'];//订单来源单号（美团必传）
            $params['is_subscribe']  = $set['myt_issubscribe'];//是否预约单 0 否 1 是

            $subscribe_time = 0;
            if($order['freight_time']){
                $freight_time_arr = explode('~',$order['freight_time']);
                if($freight_time_arr && $freight_time_arr[0]){
                    $subscribe_time = strtotime($freight_time_arr[0]);
                }
            }
            $params['subscribe_time']= $subscribe_time;//期望送达时间 ：Unix时间戳

            $params['receiver_longitude'] = $order['longitude'];//收件人经度（必须和计算价格接口一致）
            $params['receiver_latitude']  = $order['latitude'];//收件人纬度（必须和计算价格接口一致）
            $params['receiver_address']   = $order['area']?$order['area']:'';//收件人地址
            $params['receiver_address_detail'] = $order['address']?$order['address']:'';//收件人详细地址
            $params['receiver_name']      = $order['linkman']?$order['linkman']:'';//收件人姓名
            $params['receiver_phone']     = $order['tel']?$order['tel']:'';//收件人手机号 【虚拟号格式（手机号_分机号码）示例：13700000000_1111 】或 非虚拟号 示例：13700000000

            $goods = [];

            if($prolist){
                foreach($prolist as $pv){
                    $gdata = [];
                    $gdata['name']   = $pv['proname'].' '.$pv['ggname'];
                    $gdata['number'] = $pv['num'];
                    $gdata['price']  = $pv['sell_price']*100;//单位分
                    $gdata['total']  = $pv['num']*$pv['sell_price']*100;//单位分
                    array_push($goods,$gdata);
                }
            }
            $params['goods'] = $goods;

            $params['goods_value'] = $order['totalprice']*100;//物品价值(单位:分)

            if($other){
                if($other['myt_weight']){
                    $params['goods_weight'] = $other['myt_weight'];
                }
                if($other['myt_remark']){
                    $params['order_remark'] = $other['myt_remark'];
                }
            }
            $params['map_type']    = 1;//坐标类型高德：1 百度：2
            //$params['callback_url']= $set['myt_callbackurl'];//回调地址
            $params['callback_url']= "https://".$_SERVER['HTTP_HOST'].'/?s=/ApiMytNotify/index';

            $params['return_price']= 0;//是否返回运费信息 0-不返回 1-返回

            $data['params']  = json_encode($params);
            $data['sign']    = self::get_sign($set,$data);

            $postdata = json_encode($data);
            $res = curl_post(self::$post_url.'/order/add/', $postdata);
            $res = json_decode($res,true);
            if(!$res['code'] || $res['code'] !=1){
                return ['status'=>0,'msg'=>$res['message']];
            }else{
                //更新
                $updata = [];
                $updata['longitude'] = $shop['longitude'];
                $updata['latitude']  = $shop['latitude'];

                $juli = getdistance($order['longitude'],$order['latitude'],$shop['longitude'],$shop['latitude'],1);
                $updata['juli']  = $juli;

                $updata['binfo'] = jsonEncode($shop);
                $updata['myt_order_id'] = $res['data']['order_id'];
                $updata['myt_shop_id']  = $shop_id;
                if($other){
                    if($other['myt_weight']){
                        $updata['myt_weight'] = $other['myt_weight'];
                    }
                    if($other['myt_remark']){
                        $updata['myt_remark'] = $other['myt_remark'];
                    }
                }
                $up = Db::name('peisong_order')->where('id',$psorderid)->update($updata);

                //创建管理表
                $mytdata = [];
                $mytdata['aid'] = $order['aid'];
                $mytdata['bid'] = $order['bid'];
                $mytdata['mid'] = $order['mid'];
                $mytdata['poid']= $psorderid;

                $mytdata['order_id'] = $res['data']['order_id'];
                $mytdata['shop_id']  = $shop_id;
                if($shop_id){
                    $mytdata['shop_latitude']  = $shop['latitude'];
                    $mytdata['shop_longitude'] = $shop['longitude'];
                }
                if($other){
                    if($other['myt_weight']){
                        $mytdata['weight'] = $other['myt_weight'];
                    }
                    if($other['myt_remark']){
                        $mytdata['remark']     = $other['myt_remark'];
                    }
                }

                $mytdata['createtime'] = time();

                Db::name('peisong_order_myt')->insert($mytdata);

                return ['status'=>1,'msg'=>'配送成功','data'=>$res['data']];
            }
        }
    }

     //取消订单
    public static function order_cancel($aid,$bid,$set,$origin_id,$cancel_reason_code = '',$cancel_reason='')
    {

        if(getcustom('express_maiyatian')){


            if(!$set){
                $set = Db::name('peisong_set')->where('aid',$aid)->find();
                if(!$set){
                    return ['status'=>0,'msg'=>'系统设置不存在'];
                }
            }
            $shop = Db::name('peisong_myt_shop')->where('aid',$aid)->where('bid',$bid)->where('is_del',0)->find();
            if(!$shop){
                return ['status'=>0,'msg'=>'门店不存在'];
            }
            if($shop['status'] !=1){
                return ['status'=>0,'msg'=>'门店状态异常'];
            }

            $data = [];
            $data['app_key']   = $set['myt_appkey'];
            $data['timestamp'] = time();
            $data['version']   = '1';

            $params = [];
            $params['origin_id'] = $origin_id;
            if($cancel_reason_code){
                $params['cancel_reason_code'] = $cancel_reason_code;
            }
            if($cancel_reason){
                $params['cancel_reason'] = $cancel_reason;
            }
            $data['params']    = json_encode($params);

            $data['sign'] = self::get_sign($set,$data);

            $postdata = json_encode($data);
            $res = curl_post(self::$post_url.'/order/cancel/', $postdata);
            $res = json_decode($res,true);
            if(!$res['code'] || $res['code'] !=1){
                return ['status'=>0,'msg'=>$res['message']];
            }else{
                return ['status'=>1,'msg'=>'获取','data'=>$res['data']];
            }
        }
    }
    //骑手位置
    public static function delivery_location($aid,$bid,$set,$origin_id)
    {

        if(getcustom('express_maiyatian')){


            if(!$set){
                $set = Db::name('peisong_set')->where('aid',$aid)->find();
                if(!$set){
                    return ['status'=>0,'msg'=>'系统设置不存在'];
                }
            }
            $shop = Db::name('peisong_myt_shop')->where('aid',$aid)->where('bid',$bid)->where('is_del',0)->find();
            if(!$shop){
                return ['status'=>0,'msg'=>'门店不存在'];
            }
            if($shop['status'] !=1){
                return ['status'=>0,'msg'=>'门店状态异常'];
            }

            $data = [];
            $data['app_key']   = $set['myt_appkey'];
            $data['timestamp'] = time();
            $data['version']   = '1';

            $params = [];
            $params['origin_id'] = $origin_id;
            $data['params']    = json_encode($params);

            $data['sign'] = self::get_sign($set,$data);

            $postdata = json_encode($data);
            $res = curl_post(self::$post_url.'/delivery/location/', $postdata);
            $res = json_decode($res,true);
            if(!$res['code'] || $res['code'] !=1){
                return ['status'=>0,'msg'=>$res['message']];
            }else{
                return ['status'=>1,'msg'=>'获取','data'=>$res['data']];
            }
        }
    }

     //查询订单详情
    public static function order_detail($aid,$bid,$set,$origin_id)
    {

        if(getcustom('express_maiyatian')){

            if(!$set){
                $set = Db::name('peisong_set')->where('aid',$aid)->find();
                if(!$set){
                    return ['status'=>0,'msg'=>'系统设置不存在'];
                }
            }

            $shop = Db::name('peisong_myt_shop')->where('aid',$aid)->where('bid',$bid)->where('is_del',0)->find();
            if(!$shop){
                return ['status'=>0,'msg'=>'门店不存在'];
            }
            if($shop['status'] !=1){
                return ['status'=>0,'msg'=>'门店状态异常'];
            }

            $data = [];
            $data['app_key']   = $set['myt_appkey'];
            $data['timestamp'] = time();
            $data['version']   = '1';

            $params = [];
            $params['origin_id'] = $origin_id;

            $data['params']    = json_encode($params);

            $data['sign'] = self::get_sign($set,$data);

            $postdata = json_encode($data);
            $res = curl_post(self::$post_url.'/order/detail/', $postdata);
            $res = json_decode($res,true);
            if(!$res['code'] || $res['code'] !=1){
                return ['status'=>0,'msg'=>$res['message']];
            }else{
                return ['status'=>1,'msg'=>'获取','data'=>$res['data']];
            }
        }
    }


    public static function getweight($aid,$order,$type)
    {
        if(getcustom('express_maiyatian')){
            //获取重量
            $weight = 1;//默认一千克
            if($type == 'cycle_order_stage'){
                $orderinfo = Db::name('cycle_order')->where('id',$order['orderid'])->find();
                if($orderinfo){
                    $guige = Db::name('collage_guige')->where('id',$orderinfo['ggid'])->where('aid',$aid)->field('id,weight')->find();
                    if($guige && $guige['weight'] && !empty($guige['weight'])){
                        $weight = $order['num'] * $guige['weight']/1000;
                    }
                }
            }else if($type == 'restaurant_takeaway_order'){
                $ordergoods = Db::name('restaurant_takeaway_order_goods')->where('orderid',$order['id'])->field('id,orderid,proid,ggid,num')->select()->toArray();
                if($ordergoods){
                    foreach($ordergoods as $ogv){
                        $guige = Db::name('restaurant_product_guige')->where('id',$ogv['ggid'])->where('aid',$aid)->find();
                        if($guige && $guige['weight'] && !empty($guige['weight'])){
                            $weight += $ogv['num'] * $guige['weight']/1000;
                        }
                    }
                    unset($ogv);
                }
            }else{
                //查询_order位置
                $pos = strrpos($type,'_order');
                //截取前面
                $pre = substr($type,0,$pos);

                if($type == 'collage_order' || $type == 'lucky_collage_order' || $type == 'seckill_order'){
                    $guige = Db::name($pre.'_guige')->where('id',$order['ggid'])->where('aid',$aid)->field('id,weight')->find();
                    if($guige && $guige['weight'] && !empty($guige['weight'])){
                        $weight = $order['num'] * $guige['weight']/1000;
                    }
                }else if($type == 'kanjia_order' || $type == 'tuangou_order'){
                    $product = Db::name($pre.'_product')->where('id',$order['proid'])->where('aid',$aid)->field('id,weight')->find();
                    if($product && $product['weight'] && !empty($product['weight'])){
                        $weight = $order['num'] * $product['weight']/1000;
                    }
                }else if($type == 'shop_order' || $type == 'scoreshop_order'){
                    $ordergoods = Db::name($pre.'_order_goods')->where('orderid',$order['id'])->field('id,orderid,proid,ggid,num')->select()->toArray();

                    if($ordergoods){
                        foreach($ordergoods as $ogv){
                            $guige = Db::name($pre.'_guige')->where('id',$ogv['ggid'])->where('aid',$aid)->field('id,weight')->find();
                            if($guige && $guige['weight'] && !empty($guige['weight'])){
                                $weight += $ogv['num'] * $guige['weight']/1000;
                            }
                        }
                        unset($ogv);
                    }
                }
            }
            return $weight;
        }
    }

    public static function auto_push($aid,$orderid,$order,$type)
    {
        }

}