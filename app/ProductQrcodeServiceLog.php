<?php
namespace app\model;
use think\facade\Db;
use think\facade\Log;

class ProductQrcodeServiceLog
{
    /**
     * 记录服务操作
     * @param array $data 服务数据
     * @return array
     */
    public static function recordService($data)
    {
        try {
            $now = time();
            
            $serviceData = [
                'qrcode_id' => $data['qrcode_id'],
                'code' => $data['code'],
                'operator_id' => $data['operator_id'] ?? null,
                'operator_name' => $data['operator_name'] ?? null,
                'operator_type' => $data['operator_type'],
                'action' => $data['action'],
                'content' => $data['content'] ?? null,
                'result' => $data['result'] ?? 1,
                'remark' => $data['remark'] ?? null,
                'ip' => $data['ip'] ?? null,
                'create_time' => $now
            ];
            
            $id = Db::name('product_qrcode_service')->insertGetId($serviceData);
            
            // 更新二维码的最后服务时间和状态
            self::updateQrcodeStatus($data['qrcode_id'], $data['action'], $now);
            
            return ['status' => 1, 'id' => $id];
        } catch (\Exception $e) {
            Log::error('记录服务操作失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '记录服务操作失败'];
        }
    }
    
    /**
     * 更新二维码状态
     * @param int $qrcodeId 二维码ID
     * @param int $action 操作类型
     * @param int $now 当前时间
     * @return void
     */
    private static function updateQrcodeStatus($qrcodeId, $action, $now)
    {
        try {
            $updateData = [
                'last_service_time' => $now
            ];
            
            // 根据操作类型更新不同的状态
            switch ($action) {
                case 1: // 数据录入
                    $updateData['current_status'] = 1;
                    break;
                case 2: // 销售
                    $updateData['current_status'] = 2;
                    $updateData['sale_time'] = $now;
                    break;
                case 3: // 安装
                    $updateData['current_status'] = 3;
                    $updateData['install_time'] = $now;
                    // 安装后自动开始质保（假设质保期为1年）
                    $updateData['quality_start_time'] = $now;
                    $updateData['quality_end_time'] = $now + 365 * 24 * 60 * 60;
                    break;
                case 4: // 维修
                    $updateData['current_status'] = 5;
                    break;
                case 6: // 激活
                    $updateData['current_status'] = 4;
                    break;
            }
            
            Db::name('product_qrcode')
                ->where('id', $qrcodeId)
                ->update($updateData);
        } catch (\Exception $e) {
            Log::error('更新二维码状态失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取二维码的服务记录
     * @param int $qrcodeId 二维码ID
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public static function getServiceLogs($qrcodeId, $page = 1, $limit = 20)
    {
        try {
            $total = Db::name('product_qrcode_service')
                ->where('qrcode_id', $qrcodeId)
                ->count();
            
            $list = Db::name('product_qrcode_service')
                ->where('qrcode_id', $qrcodeId)
                ->order('create_time desc')
                ->page($page, $limit)
                ->select()->toArray();
            
            // 格式化操作类型和操作人类型
            $actionMap = [1 => '数据录入', 2 => '销售', 3 => '安装', 4 => '维修', 5 => '质保查询', 6 => '激活'];
            $operatorTypeMap = [1 => '供货商', 2 => '门店', 3 => '销售员', 4 => '安装员', 5 => '客服', 6 => '技术员', 7 => '客户'];
            
            foreach ($list as &$item) {
                $item['action_text'] = $actionMap[$item['action']] ?? '未知操作';
                $item['operator_type_text'] = $operatorTypeMap[$item['operator_type']] ?? '未知身份';
                $item['result_text'] = $item['result'] == 1 ? '成功' : '失败';
            }
            
            return [
                'status' => 1,
                'data' => [
                    'total' => $total,
                    'list' => $list
                ]
            ];
        } catch (\Exception $e) {
            Log::error('获取服务记录失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '获取服务记录失败'];
        }
    }
    
    /**
     * 获取操作类型列表
     * @return array
     */
    public static function getActionTypes()
    {
        return [
            ['id' => 1, 'name' => '数据录入'],
            ['id' => 2, 'name' => '销售'],
            ['id' => 3, 'name' => '安装'],
            ['id' => 4, 'name' => '维修'],
            ['id' => 5, 'name' => '质保查询'],
            ['id' => 6, 'name' => '激活']
        ];
    }
    
    /**
     * 获取操作人类型列表
     * @return array
     */
    public static function getOperatorTypes()
    {
        return [
            ['id' => 1, 'name' => '供货商'],
            ['id' => 2, 'name' => '门店'],
            ['id' => 3, 'name' => '销售员'],
            ['id' => 4, 'name' => '安装员'],
            ['id' => 5, 'name' => '客服'],
            ['id' => 6, 'name' => '技术员'],
            ['id' => 7, 'name' => '客户']
        ];
    }
    
    /**
     * 获取状态类型列表
     * @return array
     */
    public static function getStatusTypes()
    {
        return [
            ['id' => 1, 'name' => '未销售'],
            ['id' => 2, 'name' => '已销售'],
            ['id' => 3, 'name' => '已安装'],
            ['id' => 4, 'name' => '已激活'],
            ['id' => 5, 'name' => '已维修']
        ];
    }
}
