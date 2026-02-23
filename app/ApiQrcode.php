<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 二维码API控制器
// +----------------------------------------------------------------------
namespace app\controller;
use app\service\QrcodeService;
use app\model\ProductQrcode;
use think\facade\Db;

class ApiQrcode extends ApiBase
{
    private $qrcodeService;
    
    public function initialize()
    {
        parent::initialize();
        $this->qrcodeService = new QrcodeService();
    }
    
    /**
     * 扫码处理
     * @return void
     */
    public function scan()
    {
        $code = input('get.code');
        if (empty($code)) {
            return json(['status' => 0, 'msg' => '参数错误']);
        }
        
        // 获取扫码数据
        $scanData = [
            'ip' => request()->ip(),
            'platform' => platform,
            'openid' => input('get.openid') ?? null,
            'operator_type' => input('get.operator_type/d') ?? 7, // 默认客户
            'operator_id' => input('get.operator_id/d') ?? null,
            'operator_name' => input('get.operator_name') ?? null
        ];
        
        // 处理扫码
        $result = $this->qrcodeService->handleScan($code, $scanData);
        
        return json($result);
    }
    
    /**
     * 获取二维码信息
     * @return void
     */
    public function getQrcodeInfo()
    {
        $code = input('post.code');
        if (empty($code)) {
            return json(['status' => 0, 'msg' => '参数错误']);
        }
        
        $qrcode = ProductQrcode::getByCode($code);
        if (!$qrcode) {
            return json(['status' => 0, 'msg' => '二维码不存在']);
        }
        
        // 获取商品信息
        $qrcodeService = new QrcodeService();
        $qrcode['product_info'] = $qrcodeService->getProductInfo($qrcode['product_id'], $qrcode['product_type']);
        
        return json(['status' => 1, 'data' => $qrcode]);
    }
    
    /**
     * 生成二维码
     * @return void
     */
    public function generate()
    {
        $params = input('post.');
        
        $result = $this->qrcodeService->generateQrcode($params);
        
        return json($result);
    }
    
    /**
     * 批量生成二维码
     * @return void
     */
    public function batchGenerate()
    {
        $params = input('post.');
        $count = input('post.count/d', 10);
        
        $result = $this->qrcodeService->batchGenerateQrcode($params, $count);
        
        return json($result);
    }
    
    /**
     * 获取扫码记录
     * @return void
     */
    public function getScanRecords()
    {
        $qrcodeId = input('post.qrcode_id/d');
        if (empty($qrcodeId)) {
            return json(['status' => 0, 'msg' => '参数错误']);
        }
        
        $page = input('post.page/d', 1);
        $limit = input('post.limit/d', 20);
        
        $where = ['qrcode_id' => $qrcodeId];
        
        $scanModel = new \app\model\ProductQrcodeScan();
        $result = $scanModel->getScanList($where, $page, $limit);
        
        return json($result);
    }
    
    /**
     * 执行服务操作
     * @return void
     */
    public function executeService()
    {
        $params = input('post.');
        $result = $this->qrcodeService->executeService($params);
        return json($result);
    }
    
    /**
     * 获取服务记录列表
     * @return void
     */
    public function getServiceLogs()
    {
        $qrcodeId = input('post.qrcode_id/d');
        if (empty($qrcodeId)) {
            return json(['status' => 0, 'msg' => '参数错误']);
        }
        
        $page = input('post.page/d', 1);
        $limit = input('post.limit/d', 20);
        
        $serviceLogModel = new \app\model\ProductQrcodeServiceLog();
        $result = $serviceLogModel->getServiceLogs($qrcodeId, $page, $limit);
        
        return json($result);
    }
    
    /**
     * 获取状态类型列表
     * @return void
     */
    public function getStatusTypes()
    {
        $serviceLogModel = new \app\model\ProductQrcodeServiceLog();
        $types = $serviceLogModel->getStatusTypes();
        return json(['status' => 1, 'data' => $types]);
    }
    
    /**
     * 获取操作类型列表
     * @return void
     */
    public function getActionTypes()
    {
        $serviceLogModel = new \app\model\ProductQrcodeServiceLog();
        $types = $serviceLogModel->getActionTypes();
        return json(['status' => 1, 'data' => $types]);
    }
    
    /**
     * 获取操作人类型列表
     * @return void
     */
    public function getOperatorTypes()
    {
        $serviceLogModel = new \app\model\ProductQrcodeServiceLog();
        $types = $serviceLogModel->getOperatorTypes();
        return json(['status' => 1, 'data' => $types]);
    }
}
