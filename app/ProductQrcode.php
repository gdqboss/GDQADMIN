<?php
namespace app\model;
use think\facade\Db;
use think\facade\Log;

class ProductQrcode
{
    /**
     * 生成唯一码
     * @param int $length 唯一码长度
     * @return string
     */
    public static function generateUniqueCode($length = 16)
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        // 确保唯一码不存在
        while (Db::name('product_qrcode')->where('code', $code)->count() > 0) {
            $code = self::generateUniqueCode($length);
        }
        return $code;
    }

    /**
     * 生成二维码
     * @param array $data 二维码数据
     * @return array
     */
    public static function generateQrcode($data)
    {
        try {
            $code = self::generateUniqueCode();
            $now = time();
            
            $qrcodeData = [
                'code' => $code,
                'product_id' => $data['product_id'],
                'product_type' => $data['product_type'] ?? 1,
                'specs_id' => $data['specs_id'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'delivery_id' => $data['delivery_id'] ?? null,
                'salesman_id' => $data['salesman_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'store_id' => $data['store_id'] ?? null,
                'merchant_id' => $data['merchant_id'] ?? 0,
                'after_sales_id' => $data['after_sales_id'] ?? null,
                'installer_id' => $data['installer_id'] ?? null,
                'remark' => $data['remark'] ?? null,
                'status' => 1,
                'scan_count' => 0,
                'create_time' => $now,
                'update_time' => $now
            ];
            
            $id = Db::name('product_qrcode')->insertGetId($qrcodeData);
            return ['status' => 1, 'id' => $id, 'code' => $code];
        } catch (\Exception $e) {
            Log::error('生成二维码失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '生成二维码失败'];
        }
    }

    /**
     * 批量生成二维码
     * @param array $data 生成数据
     * @param int $count 生成数量
     * @return array
     */
    public static function batchGenerateQrcode($data, $count)
    {
        try {
            $qrcodes = [];
            $now = time();
            
            for ($i = 0; $i < $count; $i++) {
                $code = self::generateUniqueCode();
                $qrcodeData = [
                    'code' => $code,
                    'product_id' => $data['product_id'],
                    'product_type' => $data['product_type'] ?? 1,
                    'specs_id' => $data['specs_id'] ?? null,
                    'merchant_id' => $data['merchant_id'] ?? 0,
                    'status' => 1,
                    'scan_count' => 0,
                    'create_time' => $now,
                    'update_time' => $now
                ];
                $qrcodes[] = $qrcodeData;
            }
            
            Db::name('product_qrcode')->insertAll($qrcodes);
            return ['status' => 1, 'count' => $count];
        } catch (\Exception $e) {
            Log::error('批量生成二维码失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '批量生成二维码失败'];
        }
    }

    /**
     * 记录扫码信息
     * @param int $qrcodeId 二维码ID
     * @param array $scanData 扫码数据
     * @return array
     */
    public static function recordScan($qrcodeId, $scanData)
    {
        try {
            $now = time();
            
            // 更新二维码扫码次数
            Db::name('product_qrcode')
                ->where('id', $qrcodeId)
                ->update([
                    'scan_count' => Db::raw('scan_count + 1'),
                    'update_time' => $now
                ]);
            
            // 记录扫码信息
            $scanRecord = [
                'qrcode_id' => $qrcodeId,
                'user_id' => $scanData['user_id'] ?? null,
                'openid' => $scanData['openid'] ?? null,
                'ip' => $scanData['ip'] ?? null,
                'scan_time' => $now,
                'platform' => $scanData['platform'] ?? null
            ];
            
            Db::name('product_qrcode_scan')->insert($scanRecord);
            return ['status' => 1];
        } catch (\Exception $e) {
            Log::error('记录扫码信息失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '记录扫码信息失败'];
        }
    }

    /**
     * 根据唯一码获取二维码信息
     * @param string $code 唯一码
     * @return array|null
     */
    public static function getByCode($code)
    {
        return Db::name('product_qrcode')->where('code', $code)->find();
    }

    /**
     * 更新二维码状态
     * @param int $id 二维码ID
     * @param int $status 状态
     * @return array
     */
    public static function updateStatus($id, $status)
    {
        try {
            Db::name('product_qrcode')
                ->where('id', $id)
                ->update([
                    'status' => $status,
                    'update_time' => time()
                ]);
            return ['status' => 1];
        } catch (\Exception $e) {
            Log::error('更新二维码状态失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '更新二维码状态失败'];
        }
    }
}