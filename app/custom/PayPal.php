<?php
// JK客户定制


//custom_file(paypal)
namespace app\custom;

/*
https://blog.51cto.com/u_15057858/4369575
http://www.bailiuli.com/t/1575.html
https://developer.paypal.com/developer/applications/
*/

use think\facade\Db;
use think\facade\Log;

class PayPal
{
	//创建微支付参数
	public static function build($aid,$platform,$payorder){
		$package = array();
		$appinfo = \app\common\System::appinfo($aid,'h5');
		$clientId = $appinfo['paypal_clientid'];
		$clientSecret = $appinfo['paypal_clientsecret'];
		$oAuth = new \PayPal\Auth\OAuthTokenCredential($clientId, $clientSecret);
		$apiContext =  new \PayPal\Rest\ApiContext($oAuth);
		//if(env('APP_DEBUG') === false ){
			$apiContext->setConfig(['mode' => 'live']);//设置线上环境,默认是sandbox
		//}
		$money_usd = self::money_transform($payorder['money']);
		Db::name('payorder')->where('id',$payorder['id'])->update(['money_usd'=>$money_usd]);

		$shippingPrice = 0;
		$taxPrice = 0;
		$subTotal = $money_usd;
		$item1 = new \PayPal\Api\Item();
		$item1->setName($payorder['title'])->setCurrency("USD")->setQuantity(1)->setPrice($money_usd);

		$itemList = new \PayPal\Api\ItemList();
		$itemList->setItems([$item1]);

		// Set payment details
		$details = new \PayPal\Api\Details();
		$details->setShipping($shippingPrice)->setTax($taxPrice)->setSubtotal($subTotal);

		// Set payment amount
		//注意，此处的subtotal，必须是产品数*产品价格，所有值必须是正确的，否则会报错
		$total = $shippingPrice + $subTotal + $taxPrice;
		$amount = new \PayPal\Api\Amount();
		$amount->setCurrency("USD")->setTotal($total)->setDetails($details);

		// Set transaction object
		$transaction = new \PayPal\Api\Transaction();
		$transaction->setAmount($amount)->setItemList($itemList)->setDescription($payorder['title'])
		  ->setInvoiceNumber($payorder['ordernum']);//setInvoiceNumber为支付唯一标识符,在使用时建议改成订单号

		$payer = new \PayPal\Api\Payer();
		$payer->setPaymentMethod('paypal');//["credit_card", "paypal"]
		$redirectUrls = new \PayPal\Api\RedirectUrls();

		$params = 'id='.$payorder['id'].'&aid='.$aid.'&session_id='.input('param.session_id').'&fromplat='.$platform;
		if($platform == 'h5' || $platform == 'mp'){
			$redirectUrl = m_url('/pagesExt/pay/pay?paypal=success&'.$params);//支付成功跳转的回调
			$cancelUrl = m_url('/pagesExt/pay/pay?paypal=cancel&'.$params);//取消支付的回调
		}else{
			$redirectUrl = PRE_URL.'/?s=/ApiPay/webviewjump&paypal=success&'.$params;
			$cancelUrl = PRE_URL.'/?s=/ApiPay/webviewjump&paypal=cancel&'.$params;
		}

		$redirectUrls->setReturnUrl($redirectUrl)->setCancelUrl($cancelUrl);

		// Create the full payment object
		$payment = new \PayPal\Api\Payment();
		$payment->setIntent("sale")->setPayer($payer)->setRedirectUrls($redirectUrls)->addTransaction($transaction);

		try {
			$payment->create($apiContext);
			$approvalUrl = $payment->getApprovalLink(); //这个是请求支付的链接，在浏览器中请求此链接就会跳转到支付页面
		} catch (\Exception $e) {
			//var_dump($e->getMessage());//错误提示
			return ['status'=>0,'msg'=>$e->getMessage()];
		}
		//header("location:" . $approvalUrl);
		return ['status'=>1,'data'=>$approvalUrl,'$redirectUrl'=>$redirectUrl];
	}

	//回调处理
	public static function payRedirect(){
		$orderid = input('param.orderid/d');
		$payorder = Db::name('payorder')->where('id',$orderid)->find();
		$aid = $payorder['aid'];
		$paymentID = input('param.paymentId');
		$payerId = input('param.PayerID');
		$appinfo = \app\common\System::appinfo($aid,'h5');
		$clientId = $appinfo['paypal_clientid'];
		$clientSecret = $appinfo['paypal_clientsecret'];
		$oAuth = new \PayPal\Auth\OAuthTokenCredential($clientId, $clientSecret);
		$apiContext =  new \PayPal\Rest\ApiContext($oAuth);
		//if(env('APP_DEBUG') === false ){
			$apiContext->setConfig(['mode' => 'live']);//设置线上环境,默认是sandbox
		//}

		$payment = \PayPal\Api\Payment::get($paymentID, $apiContext);

		$execute = new \PayPal\Api\PaymentExecution();
		$execute->setPayerId($payerId);

		try{
			$payment = $payment->execute($execute, $apiContext);//执行,从paypal获取支付结果
			$paymentState = $payment->getState();//Possible values: created, approved, failed.
			$invoiceNum = $payment->getTransactions()[0]->getInvoiceNumber();
			$payNum = $payment->getTransactions()[0]->getRelatedResources()[0]->getSale()->getId();//这是支付的流水单号，必须保存，在退款时会使用到
			$total = $payment->getTransactions()[0]->getRelatedResources()[0]->getSale()->getAmount()->getTotal();//支付总金额
			$transactionState = $payment->getTransactions()[0]->getRelatedResources()[0]->getSale()->getState();//Possible values: completed, partially_refunded, pending, refunded, denied.

			//var_dump($paymentState);
			//var_dump($invoiceNum);
			//var_dump($payNum);
			//var_dump($total);
			//var_dump($transactionState);
			if($paymentState == 'approved' && $transactionState == 'completed'){
				//处理成功的逻辑，例如：判断支付金额与订单金额，更新订单状态等

				if($payorder['status']!=0) return ['status'=>0,'msg'=>'订单已支付'];

				if($payorder['score'] > 0){
					$rs = \app\common\Member::addscore($aid,$payorder['mid'],-$payorder['score'],'支付订单，订单号：'.$payorder['ordernum']);
					if($rs['status'] == 0){
						$order = $payorder;
						$order['totalprice'] = $order['money'];
						$order['paytypeid'] = 51;
						$rs = \app\common\Order::refund($order,$order['money'],'积分扣除失败退款');
						Log::write($rs);
						return ['status'=>0,'msg'=>'已退款'];
					}
				}
				$rs = \app\model\Payorder::payorder($payorder['id'],'PayPal',51,$payNum);
				if($rs['status']==0) return $rs;

				$set = Db::name('admin_set')->where('aid',$aid)->find();
                $total_fee = $payorder['money'];
                $tablename = $payorder['type'];
                $iszs = true;
                //消费送积分
				if($tablename != 'recharge' && $set['scorein_money']>0 && $set['scorein_score']>0 && $iszs){
					$givescore = floor($total_fee*0.01 / $set['scorein_money']) * $set['scorein_score'];
					$res = \app\common\Member::addscore($aid,$payorder['mid'],$givescore,'消费送'.t('积分'));
					if($res && $res['status'] == 1){
						//记录消费赠送积分记录
						\app\common\Member::scoreinlog($aid,0,$payorder['mid'],$payorder['type'],$payorder['orderid'],$payorder['ordernum'],$givescore,$total_fee);
					}
				}
				//充值送积分
				if($tablename == 'recharge' && $set['scorecz_money']>0 && $set['scorecz_score']>0){
					$givescore = floor($total_fee*0.01 / $set['scorecz_money']) * $set['scorecz_score'];
					\app\common\Member::addscore($aid,$payorder['mid'],$givescore,'充值送'.t('积分'));
				}
				return ['status'=>1,'msg'=>'付款成功'];
			}else{
				//paypal回调错误,paypal状态不正确
				return ['status'=>1,'msg'=>'支付失败'];
			}
		}catch(\Exception $e){
			return ['status'=>0,'msg'=>$e->getMessage()];
		}
	}

	//退款
	public static function refund($aid,$platform,$ordernum,$totalprice,$refund_money,$reason){
		try{
			$payorder = Db::name('payorder')->where('aid',$aid)->where('ordernum',$ordernum)->find();
			$refund_money_usd = self::money_transform($refund_money);
			if($refund_money_usd > $payorder['money_usd']) $refund_money_usd = $payorder['money_usd'];
			$appinfo = \app\common\System::appinfo($aid,'h5');
			$clientId = $appinfo['paypal_clientid'];
			$clientSecret = $appinfo['paypal_clientsecret'];
			$oAuth = new \PayPal\Auth\OAuthTokenCredential($clientId, $clientSecret);
			$apiContext =  new \PayPal\Rest\ApiContext($oAuth);
			//if(env('APP_DEBUG') === false ){
				$apiContext->setConfig(['mode' => 'live']);//设置线上环境,默认是sandbox
			//}

			$refundRequest = new \PayPal\Api\RefundRequest();
			$amount = new \PayPal\Api\Amount();
			$amount->setCurrency("USD")->setTotal($refund_money_usd);//退总金额
			$refundRequest->setAmount($amount);
			$refundRequest->setDescription($reason);
			$sale = new \PayPal\Api\Sale();
			$sale->setId($payorder['paynum']);//支付单号,支付成功时保存的支付流水单号

			$detailedRefund = $sale->refundSale($refundRequest, $apiContext);//调接口
			$refundState = $detailedRefund->getState();//Possible values: pending, completed, cancelled, failed.

			//var_dump($refundedSale);
			if($refundState == 'completed'){
			  return ['status'=>1,'msg'=>'退款成功'];
			}else{
			 return ['status'=>0,'msg'=>'退款失败'];
			}
		}catch (\Exception $exception){
			return ['status'=>0,'msg'=>$exception->getMessage()];
		}
	}

	//阿里云 汇率转换
	function money_transform($money){
		$host = "https://ali-waihui.showapi.com";//api访问链接
		$path = "/waihui-transform";//API访问后缀
		$method = "GET";
		$appcode = "609ee6436aaf444a8d17feebe6feb8f0";//替换成自己的阿里云appcode
		$headers = array();
		array_push($headers, "Authorization:APPCODE " . $appcode);
		$querys = "fromCode=CNY&toCode=USD&money=".$money;  //参数写在这里
		$bodys = "";
		$url = $host . $path . "?" . $querys;//url拼接

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_FAILONERROR, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, false);
		//curl_setopt($curl, CURLOPT_HEADER, true); 如不输出json, 请打开这行代码，打印调试头部状态码。
		//状态码: 200 正常；400 URL无效；401 appCode错误； 403 次数用完； 500 API网管错误
		if (1 == strpos("$".$host, "https://"))
		{
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		}
		$rs = curl_exec($curl);

		$rs = json_decode($rs,true);
		if($rs['showapi_res_body'] && $rs['showapi_res_body']['money']){
			return $rs['showapi_res_body']['money'];
		}else{
			echojson(['status'=>0,'msg'=>'换算失败']);
		}
	}
}