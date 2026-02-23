<?php
// JK客户定制

//custom_file(video_spider)
namespace app\controller;
use think\facade\Db;
use app\custom\VideoSpider;
class ApiVideoSpider extends ApiCommon
{
    public function createOrder(){
        $params = input();
        $sysset = Db::name('videospider_sysset')->where('aid',aid)->find();
        $ordernum = \app\common\Common::generateOrderNo(aid);
        $pay_money = 0;//余额支付金额
        $pay_score = 0;//积分支付金额
        $need_pay = 1;//是否需要支付
        $able_time = 0;//有效期

        $member_info = Db::name('member')->where(['id'=>$this->mid])->field('id,videospider_time')->find();
        $end_time = $member_info['videospider_time']>time()?$member_info['videospider_time']:time();

        $pay_way = input('pay_way');
        if($end_time>time()){
            //在有效期内不用支付
            $pay_num = 0;
            $need_pay = 0;
        }else if($pay_way=='free'){
            //免费试用 查询已免费试用的次数
            $have_free = Db::name('videospider_order')->where(['status'=>1,'pay_way'=>'free','mid'=>$this->mid])->count();
            if($have_free>=$sysset['free_num']){
                return $this->json(['status'=>0,'data'=>'','msg'=>'免费试用次数已用完']);
            }
            $pay_num = 0;
            $need_pay = 0;
        }else if($pay_way=='once'){
            //单次
            $pay_num = $sysset['pay_num'];
        }else if($pay_way=='month'){
            //包月
            $pay_num = $sysset['pay_num_month'];
            $able_time = $end_time+86400*30;
        }else if($pay_way=='ji'){
            //包季度
            $pay_num = $sysset['pay_num_ji'];
            $able_time = $end_time+86400*90;
        }else if($pay_way=='year'){
            //包年
            $pay_num = $sysset['pay_num_year'];
            $able_time = $end_time+86400*365;
        }

        if($sysset['pay_type']==1){
            $pay_money = $pay_num;
        }else if($sysset['pay_type']==2){
            $pay_score = $pay_num;
        }else{
            $need_pay = 0;
        }


        //去除链接文字，提取完整链接
        preg_match(
            "/(https:|http:)(\/\/[A-Za-z0-9_#?.&=\/]+)([".chr(0xb0)."-".chr(0xf7)."][".chr(0xa1)."-".chr(0xfe)."])?(\s)?/i",
            $params['url'],
            $urls
        );
        if (!empty($urls)) {
            $url = $urls[0];
        }else{
            $url = $params['url'];
        }
        //$url = $params['url'];
        $data = [];
        $data['aid'] = aid;
        $data['mid'] = $this->mid;
        $data['ordernum'] = $ordernum;
        $data['url'] = $url;
        $data['pay_money'] = $pay_money;
        $data['pay_score'] = $pay_score;
        $data['pay_way'] = $pay_way;
        $data['w_time'] = time();
        $data['able_time'] = $able_time;

        $oid = Db::name('videospider_order')->insertGetId($data);
        if($need_pay){
            //创建支付订单
            $payorderid = \app\model\Payorder::createorder(aid,bid,$data['mid'],'videospider',$oid,$ordernum,'视频去水印',$pay_money,$pay_score);
            $result = [
                'payorderid' => $payorderid??0,
                'need_pay' => $need_pay,
                'oid' => $oid
            ];
            return $this->json(['status'=>1,'data'=>$result,'msg'=>'提交成功']);
        }else{
            //不需要支付时直接处理
            $videoapi = new VideoSpider();
            $res = $videoapi->parseUrl($data['url']);
            $data_u = [];
            $data_u['err_msg'] = $data_u['msg'];
            if($res['status']){
                $data_u['video_url'] = $res['data']['url'];
                $data_u['video_cover'] = $res['data']['cover'];
                $data_u['video_title'] = $res['data']['title'];
            }
            $data_u['status'] = 1;
            Db::name('videospider_order')->where('id',$oid)->update($data_u);
            $res['data']['need_pay'] = 0;
            $res['data']['oid'] = $oid;
            return $this->json($res);
        }
    }


    //获取支持的平台
    public function getCategory(){
        //查询条件
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['status','=',1];
        if(input('param.keyword')){
            $where[] = ['name','like','%'.input('param.keyword').'%'];
        }
        $datalist = Db::name('videospider_category')
            ->where($where)
            ->order('sort desc,id desc')
            ->field('id,name,pic')
            ->select()
            ->toArray();

        return $this->json(['status'=>1,'data'=>$datalist]);
    }
    //获取参数设置
    public function getSet(){
        $sysset = Db::name('videospider_sysset')->where('aid',aid)->find();
        $pay_money = 0;//余额支付金额
        $pay_score = 0;//积分支付金额
        $need_pay = 0;//是否需要支付
        $money_type = '';
        if($sysset['pay_type']==1){
            $pay_money = $sysset['pay_num'];
            $need_pay = 1;
            $money_type = t('余额',aid);
        }else if($sysset['pay_type']==2){
            $pay_score = $sysset['pay_num'];
            $need_pay = 1;
            $money_type = t('积分',aid);
        }
        //查询已免费试用的次数
        $have_free = Db::name('videospider_order')->where(['status'=>1,'pay_way'=>'free','mid'=>$this->mid])->count();
        $free_num = $sysset['free_num']-$have_free;
        $pay_ways = [
            [
                'type'=>'free',
                'name'=>'免费试用',
                'num' => $sysset['free_num'],
                'desc' => '剩余'.$free_num.'次',
            ],
            [
                'type'=>'once',
                'name'=>'单次使用',
                'num' => $sysset['pay_num'],
                'desc' => '消费'.$sysset['pay_num'].$money_type
            ],
            [
                'type'=>'month',
                'name'=>'包月(30天)',
                'num' => $sysset['pay_num_month'],
                'desc' => '消费'.$sysset['pay_num_month'].$money_type
            ],
            [
                'type'=>'ji',
                'name'=>'包季(90天)',
                'num' => $sysset['pay_num_ji'],
                'desc' => '消费'.$sysset['pay_num_ji'].$money_type
            ],
            [
                'type'=>'year',
                'name'=>'包年(365天)',
                'num' => $sysset['pay_num_year'],
                'desc' => '消费'.$sysset['pay_num_year'].$money_type
            ],
        ];
        $member_info = Db::name('member')->where(['id'=>$this->mid])->field('id,videospider_time')->find();
        $end_time = $member_info['videospider_time']>time()?$member_info['videospider_time']:0;
        $res = [
            'bgcolor' => $sysset['bgcolor'],
            'pay_money' => $pay_money,
            'pay_score' => $pay_score,
            'need_pay' => $need_pay,
            'pay_ways' => $pay_ways,
            'end_time' => $end_time>0?date('Y-m-d H:i',$end_time):''
        ];
        return $this->json(['status'=>1,'msg'=>'查询成功','data'=>$res]);
    }

    //获取订单详情
    public function getDetail(){
        $oid = input('id');
        $order = Db::name('videospider_order')->where('id',$oid)->find();
        $videoapi = new VideoSpider();
        $res = $videoapi->parseUrl($order['url']);
        $data_u = [];
        $data_u['err_msg'] = $data_u['msg'];
        if($res['status']){
            $data_u['video_url'] = $res['data']['url'];
            $data_u['video_cover'] = $res['data']['cover'];
            $data_u['video_title'] = $res['data']['title'];
        }
        Db::name('videospider_order')->where('id',$oid)->update($data_u);
        //解析失败 退回余额
        if($res['status']==0 && $order['pay_way']=='once'){
            if($order['pay_money']>0){
                \app\common\Member::addmoney(aid,mid,$order['pay_money'],'视频解析订单退款,订单号: '.$order['ordernum']);
            }else if($order['pay_score']>0){
                \app\common\Member::addscore(aid,mid,$order['pay_score'],'视频解析订单退款,订单号: '.$order['ordernum']);
            }

        }
        return $this->json($res);
    }

    //获取订单列表
    public function getLists(){
        //分页数据
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $pernum = 20;
        //查询条件
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['status','=',1];

        $datalist = Db::name('videospider_order')
            ->where($where)
            ->page($pagenum,$pernum)
            ->order('id desc')
            ->field('id,ordernum,url,video_title,video_cover,video_url,status')
            ->select()
            ->toArray();

        return $this->json(['status'=>1,'data'=>$datalist]);
    }
}