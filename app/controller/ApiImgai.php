<?php
// JK客户定制

//custom_file(image_ai)
namespace app\controller;
use think\facade\Db;
use app\custom\BaiduAi;
class ApiImgai extends ApiCommon
{
    /**
     * 创建请求订单
     * @param ai_text 创作文本（不可超过100字）
     * @param ai_style 风格
     * @param img_bili 图片比例 1=>1:1 2=>2:3 3=>3:2
     * @return array|\think\response\Json
     */
	public function createOrder(){
	    $params = input();
	    if(empty($params['ai_text'])){
	        return $this->json(['status'=>0,'msg'=>'请输入创作文本']);
        }
	    //文本不超过100个字(百度规定的)
        $str_len = mb_strlen($params['ai_text']);
	    if($str_len>100){
            return $this->json(['status'=>0,'msg'=>'输入内容不可超过100个字']);
        }
	    if(empty($params['ai_style'])){
            return $this->json(['status'=>0,'msg'=>'请选择风格']);
        }
	    $img_bili = $params['img_bili']??1;
	    //图片尺寸，百度就支持这三种
        $resolution_arr = [
            1 => '1024*1024',
            2 => '1024*1536',
            3 => '1536*1024',
        ];
        if(empty($resolution_arr[$img_bili])){
            return $this->json(['status'=>0,'msg'=>'请选择正确的图片比例']);
        }
        $resolution = $resolution_arr[$img_bili];
        $params['resolution'] = $resolution;
	    //发送创作请求

        $sysset = Db::name('imgai_sysset')->where('aid',aid)->find();
        $ordernum = \app\common\Common::generateOrderNo(aid);

        $pay_money = 0;//余额支付金额
        $pay_score = 0;//积分支付金额
        $need_pay = 1;//是否需要支付
        $able_time = 0;//有效期

        $member_info = Db::name('member')->where(['id'=>$this->mid])->field('id,imgai_time')->find();
        $end_time = $member_info['imgai_time']>time()?$member_info['imgai_time']:time();

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

        //记录请求订单
        $data = [];
        $data['aid'] = aid;
        $data['mid'] = $this->mid;
        $data['ordernum'] = $ordernum;
        $data['ai_text'] = $params['ai_text'];
        $data['ai_style'] = $params['ai_style'];
        $data['pay_money'] = $pay_money;
        $data['pay_score'] = $pay_score;
        $data['pay_way'] = $pay_way;
        $data['able_time'] = $able_time;
        //不需要支付时直接请求创建
        if($need_pay==0){
            $baidu_ai = new BaiduAi();
            $res = $baidu_ai->txt2img($params);
            if(!$res['status']){
                return $this->json(['status'=>0,'msg'=>$res['msg']]);
            }
            $data['response'] = json_encode($res);
            $data['taskId'] = $res['taskId'];
            $data['log_id'] = $res['log_id'];
            $data['status'] = 1;
        }
        $data['w_time'] = time();
        $oid = Db::name('imgai_order')->insertGetId($data);
        if($need_pay){
            //创建支付订单
            $payorderid = \app\model\Payorder::createorder(aid,bid,$data['mid'],'imgai',$oid,$ordernum,$params['ai_style'],$pay_money,$pay_score);
        }
        $result = [
            'payorderid' => $payorderid??0,
            'need_pay' => $need_pay,
            'oid' => $oid
        ];
        return $this->json(['status'=>1,'data'=>$result,'msg'=>'提交成功']);
    }

    /**
     * 根据创建订单时获取的taskId查询图片地址
     * @param oid 订单数据id
     * @return \think\response\Json
     */
    public function getImg(){
	    $oid = input('id');
	    $order_info = Db::name('imgai_order')->where('id',$oid)->where('mid',$this->mid)->find();
	    if(empty($order_info)){
	        return ['status'=>0,'msg'=>'订单不存在'];
        }
	    if($order_info['pic']){
	        return ['status'=>1,'data'=>['img'=>$order_info['pic']]];
        }
	    //去百度查询图片
        $baidu_ai = new BaiduAi();
        $res = $baidu_ai->getImg(['taskId'=>$order_info['taskId']]);
        if(!$res['status']){
            return ['status'=>0,'msg'=>$res['msg']];
        }

        $img_arr = array_column($res['data']['data']['imgUrls'],'image');

        //更新订单数据
        $img_str = implode(',',$img_arr);
        $img_str = rtrim($img_str,',');
        $wait = $res['wait']??0;
        if($wait>0){
            //返回的是2分钟，但有可能几秒就生成了，所以固定按10秒
            $wait = 10;
        }


        Db::name('imgai_order')->where('id',$oid)->update(['pic'=>$img_str,'query_res'=>json_encode($res)]);
        //echo Db::getLastSql();exit;
        return ['status'=>1,'data'=>$img_str,'wait'=>$wait];
    }

    /**
     * 获取绘画风格
     */
    public function getStyle(){
        //分页数据
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $pernum = 20;
        //查询条件
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['status','=',1];
        if(input('param.keyword')){
            $where[] = ['name','like','%'.input('param.keyword').'%'];
        }
        $datalist = Db::name('imgai_category')
            ->where($where)
            ->page($pagenum,$pernum)
            ->order('sort desc,id desc')
            ->field('id,name,pic')
            ->select()
            ->toArray();

        return $this->json(['status'=>1,'data'=>$datalist]);
    }

    /**
     * 获取后台设置的关键词组
     */
    public function getKeywords(){
        //分页数据
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $pernum = 20;
        //查询条件
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['status','=',1];
        if(input('param.keyword')){
            $where[] = ['name','like','%'.input('param.keyword').'%'];
        }
        $datalist = Db::name('imgai_keyword')
            ->where($where)
            ->page($pagenum,$pernum)
            ->order('sort desc,id desc')
            ->field('id,name,keyword')
            ->select()
            ->toArray();
        return $this->json(['status'=>1,'data'=>$datalist]);
    }

    //获取后台支付设置
    public function getSet(){
        $sysset = Db::name('imgai_sysset')->where('aid',aid)->find();
        $pay_money = 0;//余额支付金额
        $pay_score = 0;//积分支付金额
        $need_pay = 0;//是否需要支付
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
        $have_free = Db::name('imgai_order')->where(['status'=>1,'pay_way'=>'free','mid'=>$this->mid])->count();
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
        $member_info = Db::name('member')->where(['id'=>$this->mid])->field('id,imgai_time')->find();
        $end_time = $member_info['imgai_time']>time()?$member_info['imgai_time']:0;
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

    //获取订单信息
    public function getDetail(){
        $id = input('id');
        $info = Db::name('imgai_order')->where('id',$id)->find();
        $wait = 0;
        if($info['taskId'] && !$info['pic']){
            $res = $this->getImg();
            if($res['status']){
                $info['pic'] = $res['data'];
                $wait = $res['wait'];
            }else{
                //创建失败 退回余额
                if($info['pay_way']=='once'){
                    if($info['pay_money']>0){
                        \app\common\Member::addmoney(aid,mid,$info['pay_money'],'AI绘画订单退款,订单号: '.$info['ordernum']);
                    }else if($info['pay_score']>0){
                        \app\common\Member::addscore(aid,mid,$info['pay_score'],'AI绘画订单退款,订单号: '.$info['ordernum']);
                    }

                }
                return $this->json(['status'=>0,'msg'=>$res['msg']]);
            }
        }
        return $this->json(['status'=>1,'data'=>$info,'wait'=>$wait]);
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

        $datalist = Db::name('imgai_order')
            ->where($where)
            ->page($pagenum,$pernum)
            ->order('id desc')
            ->field('id,ordernum,ai_text,ai_style,taskId,pic,status')
            ->select()
            ->toArray();

        return $this->json(['status'=>1,'data'=>$datalist]);
    }
}