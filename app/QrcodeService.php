<?php
namespace app\service;
use app\model\ProductQrcode;
use think\facade\Db;
use think\facade\Log;

class QrcodeService
{
    /**
     * 生成单个二维码
     * @param array $params 生成参数
     * @return array
     */
    public function generateQrcode($params)
    {
        // 参数验证
        if (empty($params['product_id'])) {
            return ['status' => 0, 'msg' => '商品ID不能为空'];
        }
        
        // 生成二维码
        $result = ProductQrcode::generateQrcode($params);
        if ($result['status'] == 1) {
            // 生成二维码图片
            $qrcodeUrl = $this->createQrcodeImage($result['code']);
            if ($qrcodeUrl) {
                // 更新二维码图片URL
                Db::name('product_qrcode')
                    ->where('id', $result['id'])
                    ->update(['qrcode_url' => $qrcodeUrl]);
                $result['qrcode_url'] = $qrcodeUrl;
            }
        }
        
        return $result;
    }

    /**
     * 批量生成二维码
     * @param array $params 生成参数
     * @param int $count 生成数量
     * @return array
     */
    public function batchGenerateQrcode($params, $count)
    {
        // 参数验证
        if (empty($params['product_id'])) {
            return ['status' => 0, 'msg' => '商品ID不能为空'];
        }
        
        if ($count <= 0 || $count > 1000) {
            return ['status' => 0, 'msg' => '生成数量必须在1-1000之间'];
        }
        
        // 批量生成二维码
        $result = ProductQrcode::batchGenerateQrcode($params, $count);
        if ($result['status'] == 1) {
            // 获取生成的二维码列表
            $qrcodes = Db::name('product_qrcode')
                ->where('product_id', $params['product_id'])
                ->order('create_time desc')
                ->limit($count)
                ->select()->toArray();
            
            // 生成二维码图片
            foreach ($qrcodes as &$qrcode) {
                $qrcodeUrl = $this->createQrcodeImage($qrcode['code']);
                if ($qrcodeUrl) {
                    Db::name('product_qrcode')
                        ->where('id', $qrcode['id'])
                        ->update(['qrcode_url' => $qrcodeUrl]);
                    $qrcode['qrcode_url'] = $qrcodeUrl;
                }
            }
            
            $result['qrcodes'] = $qrcodes;
        }
        
        return $result;
    }

    /**
     * 创建二维码图片
     * @param string $code 唯一码
     * @return string|null
     */
    private function createQrcodeImage($code)
    {
        try {
            // 二维码内容：包含商品信息的链接
            $qrcodeContent = $this->getQrcodeContent($code);
            
            // 调用现有的createqrcode函数生成二维码
            $qrcodeUrl = createqrcode($qrcodeContent);
            
            return $qrcodeUrl;
        } catch (\Exception $e) {
            Log::error('创建二维码图片失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 获取二维码内容
     * @param string $code 唯一码
     * @return string
     */
    private function getQrcodeContent($code)
    {
        // 构建二维码内容：包含唯一码的链接
        // 示例：https://example.com/qrcode?code=xxx
        return m_url('api/qrcode/scan?code=' . $code);
    }

    /**
     * 处理扫码请求
     * @param string $code 唯一码
     * @param array $scanData 扫码数据
     * @return array
     */
    public function handleScan($code, $scanData)
    {
        // 获取二维码信息
        $qrcode = ProductQrcode::getByCode($code);
        if (!$qrcode) {
            return ['status' => 0, 'msg' => '二维码不存在'];
        }
        
        if ($qrcode['status'] == 0) {
            return ['status' => 0, 'msg' => '二维码已禁用'];
        }
        
        // 记录扫码信息
        ProductQrcode::recordScan($qrcode['id'], $scanData);
        
        // 获取商品信息
        $productInfo = $this->getProductInfo($qrcode['product_id'], $qrcode['product_type']);
        
        // 获取服务记录
        $serviceLogModel = new \app\model\ProductQrcodeServiceLog();
        $serviceLogs = $serviceLogModel->getServiceLogs($qrcode['id'], 1, 10)['data']['list'];
        
        // 根据操作人类型返回不同的信息
        $operatorType = $scanData['operator_type'] ?? 7; // 默认客户
        
        // 基础响应数据
        $response = [
            'status' => 1,
            'data' => [
                'qrcode' => $qrcode,
                'product' => $productInfo,
                'service_logs' => $serviceLogs
            ]
        ];
        
        // 根据不同身份返回不同的显示信息和操作权限
        $this->buildResponseByIdentity($response, $operatorType, $qrcode, $productInfo);
        
        // 根据不同身份返回不同的操作权限
        $response['data']['actions'] = $this->getAvailableActions($operatorType, $qrcode['current_status']);
        
        return $response;
    }
    
    /**
     * 根据身份构建响应数据
     * @param array &$response 响应数据
     * @param int $operatorType 操作人类型
     * @param array $qrcode 二维码信息
     * @param array $productInfo 商品信息
     * @return void
     */
    private function buildResponseByIdentity(&$response, $operatorType, $qrcode, $productInfo)
    {
        // 身份类型映射
        $operatorTypeMap = [1 => '供货商', 2 => '门店', 3 => '销售员', 4 => '安装员', 5 => '客服', 6 => '技术员', 7 => '客户'];
        
        // 添加身份标识
        $response['data']['operator_type'] = $operatorType;
        $response['data']['operator_type_text'] = $operatorTypeMap[$operatorType] ?? '未知身份';
        
        // 根据不同身份返回不同的显示内容
        switch ($operatorType) {
            case 1: // 供货商
                // 显示：商品关联信息、基本二维码信息
                $response['data']['display_content'] = [
                    'title' => '供货商扫码',
                    'sections' => [
                        ['name' => '商品信息', 'content' => $productInfo],
                        ['name' => '二维码状态', 'content' => ['status' => $qrcode['status'], 'scan_count' => $qrcode['scan_count']]],
                        ['name' => '最近操作记录', 'content' => array_slice($response['data']['service_logs'], 0, 5)]
                    ]
                ];
                break;
                
            case 2: // 门店
            case 3: // 销售员
                // 显示：商品信息、客户信息、销售状态
                $response['data']['display_content'] = [
                    'title' => '销售员扫码',
                    'sections' => [
                        ['name' => '商品信息', 'content' => $productInfo],
                        ['name' => '销售信息', 'content' => [
                            'customer_name' => $qrcode['customer_name'],
                            'customer_phone' => $qrcode['customer_phone'],
                            'sale_time' => $qrcode['sale_time'] ? date('Y-m-d H:i:s', $qrcode['sale_time']) : '未销售',
                            'current_status' => $qrcode['current_status']
                        ]],
                        ['name' => '操作记录', 'content' => array_slice($response['data']['service_logs'], 0, 5)]
                    ]
                ];
                break;
                
            case 4: // 安装员
                // 显示：商品信息、安装状态、客户信息
                $response['data']['display_content'] = [
                    'title' => '安装员扫码',
                    'sections' => [
                        ['name' => '商品信息', 'content' => $productInfo],
                        ['name' => '安装信息', 'content' => [
                            'customer_name' => $qrcode['customer_name'],
                            'customer_phone' => $qrcode['customer_phone'],
                            'install_time' => $qrcode['install_time'] ? date('Y-m-d H:i:s', $qrcode['install_time']) : '未安装',
                            'current_status' => $qrcode['current_status']
                        ]],
                        ['name' => '服务记录', 'content' => array_slice($response['data']['service_logs'], 0, 5)]
                    ]
                ];
                break;
                
            case 5: // 客服
                // 显示：质保信息、销售信息、安装信息、客户信息
                $response['data']['display_content'] = [
                    'title' => '客服扫码',
                    'sections' => [
                        ['name' => '商品信息', 'content' => $productInfo],
                        ['name' => '质保信息', 'content' => [
                            'quality_start_time' => $qrcode['quality_start_time'] ? date('Y-m-d', $qrcode['quality_start_time']) : '未开始',
                            'quality_end_time' => $qrcode['quality_end_time'] ? date('Y-m-d', $qrcode['quality_end_time']) : '未设置',
                            'remaining_days' => $qrcode['quality_end_time'] ? max(0, floor(($qrcode['quality_end_time'] - time()) / (24 * 60 * 60))) : 0
                        ]],
                        ['name' => '责任信息', 'content' => [
                            'salesman_id' => $qrcode['salesman_id'],
                            'installer_id' => $qrcode['installer_id'],
                            'after_sales_id' => $qrcode['after_sales_id']
                        ]],
                        ['name' => '客户信息', 'content' => [
                            'customer_name' => $qrcode['customer_name'],
                            'customer_phone' => $qrcode['customer_phone']
                        ]],
                        ['name' => '服务记录', 'content' => array_slice($response['data']['service_logs'], 0, 5)]
                    ]
                ];
                break;
                
            case 6: // 技术员
                // 显示：商品信息、维修记录、客户信息
                $response['data']['display_content'] = [
                    'title' => '技术员扫码',
                    'sections' => [
                        ['name' => '商品信息', 'content' => $productInfo],
                        ['name' => '客户信息', 'content' => [
                            'customer_name' => $qrcode['customer_name'],
                            'customer_phone' => $qrcode['customer_phone']
                        ]],
                        ['name' => '服务记录', 'content' => $response['data']['service_logs']],
                        ['name' => '设备状态', 'content' => [
                            'current_status' => $qrcode['current_status'],
                            'last_service_time' => $qrcode['last_service_time'] ? date('Y-m-d H:i:s', $qrcode['last_service_time']) : '无记录'
                        ]]
                    ]
                ];
                break;
                
            case 7: // 客户
                // 显示：商品信息、销售信息、质保信息、服务记录
                $response['data']['display_content'] = [
                    'title' => '客户扫码',
                    'sections' => [
                        ['name' => '商品信息', 'content' => $productInfo],
                        ['name' => '销售信息', 'content' => [
                            'store' => $qrcode['store_id'],
                            'sales_time' => $qrcode['sale_time'] ? date('Y-m-d H:i:s', $qrcode['sale_time']) : '未销售',
                            'salesman' => $qrcode['salesman_id']
                        ]],
                        ['name' => '质保信息', 'content' => [
                            'quality_start_time' => $qrcode['quality_start_time'] ? date('Y-m-d', $qrcode['quality_start_time']) : '未开始',
                            'quality_end_time' => $qrcode['quality_end_time'] ? date('Y-m-d', $qrcode['quality_end_time']) : '未设置',
                            'remaining_days' => $qrcode['quality_end_time'] ? max(0, floor(($qrcode['quality_end_time'] - time()) / (24 * 60 * 60))) : 0
                        ]],
                        ['name' => '服务记录', 'content' => array_slice($response['data']['service_logs'], 0, 5)],
                        ['name' => '联系信息', 'content' => [
                            'after_sales_id' => $qrcode['after_sales_id'],
                            'customer_service_phone' => '400-123-4567' // 示例客服电话
                        ]]
                    ]
                ];
                break;
        }
    }
    
    /**
     * 获取可用操作列表
     * @param int $operatorType 操作人类型
     * @param int $currentStatus 当前状态
     * @return array
     */
    private function getAvailableActions($operatorType, $currentStatus)
    {
        $actions = [];
        
        // 根据操作人类型和当前状态返回可用操作
        switch ($operatorType) {
            case 1: // 供货商
                $actions = [
                    ['id' => 1, 'name' => '数据录入']
                ];
                break;
            case 2: // 门店
            case 3: // 销售员
                if ($currentStatus == 1) {
                    $actions[] = ['id' => 2, 'name' => '销售'];
                }
                $actions[] = ['id' => 5, 'name' => '质保查询'];
                break;
            case 4: // 安装员
                if ($currentStatus == 2) {
                    $actions[] = ['id' => 3, 'name' => '安装'];
                } else if (in_array($currentStatus, [3, 4, 5])) {
                    $actions[] = ['id' => 4, 'name' => '维修'];
                }
                $actions[] = ['id' => 5, 'name' => '质保查询'];
                break;
            case 5: // 客服
                $actions = [
                    ['id' => 5, 'name' => '质保查询']
                ];
                break;
            case 6: // 技术员
                $actions = [
                    ['id' => 4, 'name' => '维修'],
                    ['id' => 5, 'name' => '质保查询']
                ];
                break;
            case 7: // 客户
                $actions = [
                    ['id' => 5, 'name' => '质保查询'],
                    ['id' => 6, 'name' => '激活']
                ];
                if (in_array($currentStatus, [3, 4])) {
                    $actions[] = ['id' => 4, 'name' => '申请维修'];
                }
                break;
        }
        
        return $actions;
    }
    
    /**
     * 执行服务操作
     * @param array $params 操作参数
     * @return array
     */
    public function executeService($params)
    {
        try {
            $code = $params['code'];
            $operatorType = $params['operator_type'];
            $action = $params['action'];
            
            // 获取二维码信息
            $qrcode = ProductQrcode::getByCode($code);
            if (!$qrcode) {
                return ['status' => 0, 'msg' => '二维码不存在'];
            }
            
            // 检查操作权限
            $availableActions = $this->getAvailableActions($operatorType, $qrcode['current_status']);
            $hasPermission = false;
            foreach ($availableActions as $availableAction) {
                if ($availableAction['id'] == $action) {
                    $hasPermission = true;
                    break;
                }
            }
            
            if (!$hasPermission) {
                return ['status' => 0, 'msg' => '没有操作权限'];
            }
            
            // 执行操作
            $serviceData = [
                'qrcode_id' => $qrcode['id'],
                'code' => $code,
                'operator_id' => $params['operator_id'] ?? null,
                'operator_name' => $params['operator_name'] ?? null,
                'operator_type' => $operatorType,
                'action' => $action,
                'content' => $params['content'] ?? null,
                'result' => $params['result'] ?? 1,
                'remark' => $params['remark'] ?? null,
                'ip' => request()->ip()
            ];
            
            // 记录服务操作
            $result = \app\model\ProductQrcodeServiceLog::recordService($serviceData);
            if ($result['status'] == 1) {
                return ['status' => 1, 'msg' => '操作成功'];
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('执行服务操作失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '执行服务操作失败'];
        }
    }

    /**
     * 获取商品信息
     * @param int $productId 商品ID
     * @param int $productType 商品类型
     * @return array|null
     */
    private function getProductInfo($productId, $productType)
    {
        try {
            $table = $productType == 2 ? 'collage_product' : 'shop_product';
            return Db::name($table)->where('id', $productId)->find();
        } catch (\Exception $e) {
            Log::error('获取商品信息失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 获取二维码列表
     * @param array $params 查询参数
     * @return array
     */
    public function getQrcodeList($params)
    {
        try {
            $query = Db::name('product_qrcode');
            
            // 搜索条件
            if (!empty($params['keyword'])) {
                $query->where('code|customer_name|customer_phone', 'like', '%' . $params['keyword'] . '%');
            }
            
            if (!empty($params['product_id'])) {
                $query->where('product_id', $params['product_id']);
            }
            
            if (isset($params['status'])) {
                $query->where('status', $params['status']);
            }
            
            // 分页
            $page = $params['page'] ?? 1;
            $limit = $params['limit'] ?? 20;
            
            $total = $query->count();
            $list = $query->order('create_time desc')
                ->page($page, $limit)
                ->select()->toArray();
            
            // 关联商品信息
            foreach ($list as &$item) {
                $item['product_info'] = $this->getProductInfo($item['product_id'], $item['product_type']);
            }
            
            return [
                'status' => 1,
                'data' => [
                    'total' => $total,
                    'list' => $list
                ]
            ];
        } catch (\Exception $e) {
            Log::error('获取二维码列表失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '获取二维码列表失败'];
        }
    }

    /**
     * 更新二维码状态
     * @param int $id 二维码ID
     * @param int $status 状态
     * @return array
     */
    public function updateQrcodeStatus($id, $status)
    {
        return ProductQrcode::updateStatus($id, $status);
    }

    /**
     * 获取二维码详情
     * @param int $id 二维码ID
     * @return array
     */
    public function getQrcodeDetail($id)
    {
        try {
            $qrcode = Db::name('product_qrcode')->where('id', $id)->find();
            if (!$qrcode) {
                return ['status' => 0, 'msg' => '二维码不存在'];
            }
            
            // 获取商品信息
            $qrcode['product_info'] = $this->getProductInfo($qrcode['product_id'], $qrcode['product_type']);
            
            return ['status' => 1, 'data' => $qrcode];
        } catch (\Exception $e) {
            Log::error('获取二维码详情失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '获取二维码详情失败'];
        }
    }
}
