<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 公众号支付设置
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class Mppay extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//公众号支付设置
    public function set(){
		if(request()->isPost()){
			$info = input('post.info/a');
//			$rs = Db::name('admin_setapp_mp')->where('aid',aid)->find();
			$info['wxpay_mchid'] = trim($info['wxpay_mchid']);
			$info['wxpay_mchkey'] = trim($info['wxpay_mchkey']);
			$info['wxpay_sub_mchid'] = trim($info['wxpay_sub_mchid']);
			$info['wxpay_apiclient_cert'] = str_replace(PRE_URL.'/','',$info['wxpay_apiclient_cert']);
			$info['wxpay_apiclient_key'] = str_replace(PRE_URL.'/','',$info['wxpay_apiclient_key']);
            $info['wxpay_wechatpay_pem'] = str_replace(PRE_URL.'/','',$info['wxpay_wechatpay_pem']);
			if(!empty($info['wxpay_apiclient_cert']) && substr($info['wxpay_apiclient_cert'], -4) != '.pem'){
				return json(['status'=>0,'msg'=>'PEM证书格式错误']);
			}
			if(!empty($info['wxpay_apiclient_key']) && substr($info['wxpay_apiclient_key'], -4) != '.pem'){
				return json(['status'=>0,'msg'=>'证书密钥格式错误']);
			}
            if(!empty($info['wxpay_wechatpay_pem']) && substr($info['wxpay_wechatpay_pem'], -4) != '.pem'){
                return json(['status'=>0,'msg'=>'平台证书格式错误']);
            }
			$info['sxpay_mno'] = trim($info['sxpay_mno']);
            $info['transfer_scene_id'] = trim($info['transfer_scene_id']);
            $info['transfer_scene_type'] = trim($info['transfer_scene_type']);
            $info['transfer_scene_content'] = trim($info['transfer_scene_content']);
			Db::name('admin_setapp_mp')->where('aid',aid)->update($info);
			\app\common\System::plog('公众号支付设置');
			return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
		}
		$info = Db::name('admin_setapp_mp')->where('aid',aid)->find();
		if(!$info) Db::name('admin_setapp_mp')->insert(['aid'=>aid]);
		View::assign('info',$info);
        //随行付进件状态
        $incomeStatus = \app\custom\Sxpay::getIncomeStatus(aid);
        View::assign('incomeStatus',$incomeStatus);
		return View::fetch();
	}
	public function download_wechatkey(){
        //windows下测试成功，linux下测试失败，暂不启用
        $wxpay_mchkey_v3 = input('wxpay_mchkey_v3');
        $wxpay_mchid = input('wxpay_mchid');
        $wxpay_apiclient_key = input('wxpay_apiclient_key');
        $wxpay_serial_no = input('wxpay_serial_no');
        $res = $this->wechatkey(aid,$wxpay_mchkey_v3,$wxpay_mchid,$wxpay_apiclient_key,$wxpay_serial_no);
        return json($res);
    }

    //下载微信V3支付用的平台证书
    public function wechatkey($aid,$apiV3key,$wxpay_mchid,$wxpay_apiclient_key,$wxpay_serial_no){
        $certificate = ROOT_PATH.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'CertificateDownloader.php';//生成证书的工具路径
        $apiclient_key = ROOT_PATH.$wxpay_apiclient_key;//商户私钥
        $mchSerialNo = $wxpay_serial_no;//商户私钥证书序列号
        $out_path = ROOT_PATH.'upload'.DIRECTORY_SEPARATOR.$aid.DIRECTORY_SEPARATOR.'wechatkey'.DIRECTORY_SEPARATOR;
        if(!file_exists($out_path)){
            mk_dir($out_path, 0775);
        }
        //dump('php -f '.$certificate.' -- -k '.$apiV3key.' -m '.$wxpay_mchid.' -f '.$apiclient_key.' -s '.$mchSerialNo.' '.$out_path);
        //此处好像需要php版本才可以，待确认 todo
        $res = shell_exec('php -f '.$certificate.' -- -k '.$apiV3key.' -m '.$wxpay_mchid.' -f '.$apiclient_key.' -s '.$mchSerialNo.' -o '.$out_path);
        $files = scandir($out_path);
        $key_file = '';
        foreach($files as $file){
            if(strpos($file,'.pem')!==false){
                $key_file = $file;
                break;
            }
        }
        $new_file_name = 'wechatkey_'.$aid.'.pem';
        if (rename($out_path.$key_file, $out_path.$new_file_name)) {
            $new_file = str_replace(ROOT_PATH, '', $out_path.$new_file_name);
            return ['status'=>1,'msg'=>'下载成功','data'=>$new_file];
        } else {
            return ['status'=>0,'msg'=>'下载失败，请手动上传'];
        }
    }
}