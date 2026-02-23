<?php
// JK客户定制

// custom_file(lot_cerberuse)
namespace app\controller;
use app\BaseController;
use app\custom\Mqtt;
use think\facade\Db;

class ApiCerberusePost extends BaseController
{
    public function checkSign($data){
        $sign = $data['Sign'];
        if($data['Sign']){
            unset($data['Sign']);
        }
        // 参数按字典顺序排序
        $parts = '';
        foreach ($data as $k => $v) {
            $parts .= $k . '=' . $v;
        }
        $createsign = md5($parts);
        if($sign == $createsign){
            return true;
        }else{
            return  false;
        }
    }
    public function index(){
        \think\facade\Log::write('-------默认-----');
    }
    public function QueryCmd(){
        $rdata = [
            'CmdID' => '123456',
            'CmdCode' => 0,
            'CmdParams' => [
                'DateTime' =>date('Y-m-d H:i:s',time())
            ]
        ];
        return json($rdata);
    }
    public function  CheckCode(){
        $data =  $post = input('param.');
       
        //$data = json_decode($post,true);
        
        $issign = $this->checkSign($data);
        if(!$issign){
            return  json(['Status' => 0,'StatusDesc' => '签名错误']);
        }
        $cerberuse_order = [];
        $sy_time = 0;
        if($data['CodeType'] =='Q'){
            $cerberuse_order = Db::name('cerberuse_order')->where('ordernum',$data['CodeVal'])->find();
            //查找设备是否在运行中，如果运行中给加上当前的时间
            $have_cerberuse = Db::name('cerberuse_order')->where('proid',$cerberuse_order['proid'])->where('status',2)->order('id asc')->find();
            if($have_cerberuse ){
                $sy_time =  $have_cerberuse['endtime'] - time();
                $sy_time = $sy_time<=0?0:$sy_time;
            }
        }
        if(!$cerberuse_order ||  in_array($cerberuse_order['status'],[0,3])){
            return  json( ['Status' => 0,'StatusDesc' => '订单状态错误']);
        }
        //提前 10分钟（动态）开始刷码
        $sysset = Db::name('cerberuse_set')->where('aid',$cerberuse_order['aid'])->find();
        $wks_time =$cerberuse_order['starttime'] -  $sysset['remind_minute'] *60;
        if(time() < $wks_time ){
            return  json( ['Status' => 0,'StatusDesc' => '订单未开始']);
        }
        if($cerberuse_order['endtime'] <= time()){
            return  json( ['Status' => 0,'StatusDesc' => '订单已结束']);
        }
        
        //开门
        //操作4G继电器
        $cerberuse = Db::name('cerberuse')->where('aid',$cerberuse_order['aid'])->where('id',$cerberuse_order['proid'])->find();
        //购买总时长 
        $timeSec = 0;
        if($have_cerberuse['ordernum'] !=$data['CodeVal']){
            $timeSec = $cerberuse_order['time_length'] * 3600;
        }
        $total_time =   $timeSec + $sy_time;
        if($total_time >0){
            $mqtt = new Mqtt();
            $content = [
                'method' => 'start',
                'slotNum' => 1,
                'type' =>'TIME',
                'maxPower' => 0,
                'pullOutStop' => true,
                'pullOutStopPower' => 5 ,
                'chargeFullStop' => false,
                'chargeFullStopPower' =>  0,
                'chargeFullStopSec' => 0,
                'remark' => '扫码成功，开启电源',
                'frameId' => rand(100000,999999),
                'timeSec' =>$total_time
            ];
            $content_json = json_encode($content,JSON_UNESCAPED_UNICODE);
            //层级设置 aid/行为/设备id 
            $topic= $cerberuse_order['aid'].'/'.$cerberuse['imei'];

            $mqtt -> publish($topic,$content_json);
            Db::name('cerberuse_order')->where('ordernum',$data['CodeVal'])->update(['status' => 2,'usetime' => time()]);
        }
        return  json(['Status' => 1,'StatusDesc' => '成功']);
    }
    public function getMqtt(){
        $mqtt = new Mqtt();
        $content =[
            'method' => 'getDevStatus',
        ];
        $content_json = json_encode($content,JSON_UNESCAPED_UNICODE);
        $mqtt -> publish('1/865328068271336',$content_json);
    }
}