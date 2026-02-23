<?php
// JK客户定制

//custom_file(image_ai)
namespace app\custom;

use think\facade\Db;
use think\facade\Log;

class BaiduAi
{
    private $appId;
    private $apiKey;
    private $secretKey;
    private $aid;
    private $searchNum;
    public function __construct($aid=1,$appId=null,$apiKey=null,$secretKey=null,$searchNum=30){
        $this->aid = intval($aid);
        if(getcustom('image_ai')){
            if($appId){
                $this->appId = trim($appId);
                $this->apiKey = trim($apiKey);
                $this->secretKey = trim($secretKey);
                $this->searchNum = intval($searchNum);
            }else{
                $sysset = Db::name('imgai_sysset')->where('aid',$aid)->find();
                if(empty($sysset) || empty($sysset['app_id']) || empty($sysset['api_key']) || empty($sysset['secret_key'])) {
                    return ['stauts' =>0, 'msg' => '请检查配置'];
                }
                $this->appId = trim($sysset['app_id']);
                $this->apiKey = trim($sysset['api_key']);
                $this->secretKey = trim($sysset['secret_key']);
            }
        }

    }

    //获取token
    public function getAccessToken(){
        if(cache('baidu_access_token_'.$this->aid)){
            return ['status'=>true,'token'=>cache('baidu_access_token_'.$this->aid)];
        }
        $api_url = 'https://aip.baidubce.com/oauth/2.0/token?client_id='.$this->apiKey.'&client_secret='.$this->secretKey.'&grant_type=client_credentials';
        $response = curl_get($api_url);
        $res = json_decode($response,true);
        if(empty($res['access_token'])){
            return ['status'=>false,'msg'=>$res['error'].'::'.$res['error_description']];
        }else{
            cache('baidu_access_token_'.$this->aid,$res['access_token'],29*86400);
            return ['status'=>true,'token'=>$res['access_token']];
        }
    }
    //提交创建请求
    public function txt2img($params = []){
        //获取token 发起请求
        $res_token = $this->getAccessToken();
        if(!$res_token['status']){
            return $res_token;
        }
        $api_url = 'https://aip.baidubce.com/rpc/2.0/ernievilg/v1/txt2img?access_token='.$res_token['token'];
        $data = [
            'text' => $params['ai_text'],//输入内容，长度不超过100个字
            'resolution' => $params['resolution'],//图片分辨率，可支持1024*1024、1024*1536、1536*1024
            'style' => $params['ai_style'],//目前支持风格有：探索无限、古风、二次元、写实风格、浮世绘、low poly 、未来主义、像素风格、概念艺术、赛博朋克、洛丽塔风格、巴洛克风格、超现实主义、水彩画、蒸汽波艺术、油画、卡通画
            'num' => 1 //图片生成数量，支持1-6张
        ];
        $response = curl_post($api_url,json_encode($data));
        writeLog('api:'.$api_url.';params:'.json_encode($data).';result:'.$response,'baiduai.log');
        //{"data":{"taskId":14868484},"log_id":1638798436783917600}
        $res = json_decode($response,true);
        if(empty($res['data']['taskId'])){
            return ['status'=>0,'msg'=>'请求失败'];
        }
        return ['status'=>1,'taskId'=>$res['data']['taskId'],'log_id'=>$res['log_id']];
    }
    //查询请求结果
    public function getImg($params = []){
        $res_token = $this->getAccessToken();
        if(!$res_token['status']){
            return $res_token;
        }
        $api_url = 'https://aip.baidubce.com/rpc/2.0/ernievilg/v1/getImg?access_token='.$res_token['token'];
        $data = [
            'taskId' => $params['taskId'],
        ];
        //dump($api_url);
        //dump($data);
        $response = curl_post($api_url,json_encode($params));
        writeLog('api:'.$api_url.';params:'.json_encode($data).';result:'.$response,'baiduai.log');
        //{"data":{"taskId":14868016},"log_id":1638790645794280777}
        $res = json_decode($response,true);
        $wait = 0;
        if(!empty($res['data']['waiting'])){
            if(strpos($res['data']['waiting'],'m')!==false){
                $wait = intval($res['data']['waiting']) * 60;
            }else{
                $wait =  intval($res['data']['waiting']);
            }
            return ['status'=>true,'data'=>$res,'wait'=>$wait];
        }
        if(empty($res['data']['imgUrls'])){
            return ['status'=>0,'msg'=>'请求失败'];
        }


        return ['status'=>true,'data'=>$res,'wait'=>$wait];
    }

    //订单支付完成后处理
    public function afterPay($payorderid){
        $order = Db::name('imgai_order')->where('id',$payorderid)->find();
        //已处理过的直接跳过
        if(!empty($order['taskId'])){
            return true;
        }
        $params = [
            'ai_text' => $order['ai_text'],
            'resolution' => $order['resolution'],
            'ai_style' => $order['ai_style'],
        ];
        $res =$this->txt2img($params);
        if(!$res['status']){
            return ['status'=>0,'msg'=>$res['msg']];
        }
        $data = [];
        $data['response'] = json_encode($res);
        $data['taskId'] = $res['taskId'];
        $data['log_id'] = $res['log_id'];
        Db::name('imgai_order')->where('id',$order['id'])->update($data);
        return true;
    }
}