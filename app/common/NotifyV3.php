<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 微支付通知
// +----------------------------------------------------------------------
namespace app\common;
use pay\wechatpay\WxPayV3;
use think\facade\Db;
use think\facade\Log;
class NotifyV3
{
	public $member;
	public $givescore=0;
	//商家转账回调
	public function transfer(){
        $params = file_get_contents('php://input');
        $headers = request()->header();
        writeLog('微信回调进入','notify_v3');
        writeLog('回调head数据'.json_encode($headers),'notify_v3');
        writeLog('回调body数据'.json_encode($params),'notify_v3');
        $paysdk = new WxPayV3();
        $response = $paysdk->notify_wx($headers,$params);
        writeLog('回调验证'.json_encode($response),'notify');
        if(!empty($response['trade_state']) && $response['trade_state'] === 'SUCCESS'){

        }else{
            exit('fail');
        }
	}
}