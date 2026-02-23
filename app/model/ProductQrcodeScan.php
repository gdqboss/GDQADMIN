<?php
namespace app\model;
use think\facade\Db;
use think\facade\Log;

class ProductQrcodeScan
{
    /**
     * 获取扫码记录列表
     * @param array $where 查询条件
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public static function getScanList($where = [], $page = 1, $limit = 20)
    {
        try {
            $query = Db::name('product_qrcode_scan')
                ->alias('s')
                ->join('product_qrcode q', 's.qrcode_id = q.id', 'left')
                ->field('s.*, q.code, q.product_id, q.product_type');
            
            if (!empty($where)) {
                $query->where($where);
            }
            
            $total = $query->count();
            $list = $query->order('s.scan_time desc')
                ->page($page, $limit)
                ->select()->toArray();
            
            return ['status' => 1, 'total' => $total, 'list' => $list];
        } catch (\Exception $e) {
            Log::error('获取扫码记录列表失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '获取扫码记录列表失败'];
        }
    }

    /**
     * 获取二维码的扫码统计
     * @param int $qrcodeId 二维码ID
     * @return array
     */
    public static function getScanStats($qrcodeId)
    {
        try {
            // 总扫码次数
            $total = Db::name('product_qrcode_scan')
                ->where('qrcode_id', $qrcodeId)
                ->count();
            
            // 今日扫码次数
            $today = strtotime(date('Y-m-d'));
            $todayCount = Db::name('product_qrcode_scan')
                ->where('qrcode_id', $qrcodeId)
                ->where('scan_time', '>=', $today)
                ->count();
            
            // 昨日扫码次数
            $yesterday = strtotime(date('Y-m-d', strtotime('-1 day')));
            $yesterdayCount = Db::name('product_qrcode_scan')
                ->where('qrcode_id', $qrcodeId)
                ->where('scan_time', '>=', $yesterday)
                ->where('scan_time', '<', $today)
                ->count();
            
            // 最近7天扫码统计
            $weekStats = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime('-{$i} day'));
                $startTime = strtotime($date);
                $endTime = $startTime + 86400;
                $count = Db::name('product_qrcode_scan')
                    ->where('qrcode_id', $qrcodeId)
                    ->where('scan_time', '>=', $startTime)
                    ->where('scan_time', '<', $endTime)
                    ->count();
                $weekStats[] = [
                    'date' => $date,
                    'count' => $count
                ];
            }
            
            return [
                'status' => 1,
                'data' => [
                    'total' => $total,
                    'today' => $todayCount,
                    'yesterday' => $yesterdayCount,
                    'week_stats' => $weekStats
                ]
            ];
        } catch (\Exception $e) {
            Log::error('获取扫码统计失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '获取扫码统计失败'];
        }
    }

    /**
     * 获取扫码平台分布
     * @param array $where 查询条件
     * @return array
     */
    public static function getPlatformDistribution($where = [])
    {
        try {
            $result = Db::name('product_qrcode_scan')
                ->where($where)
                ->field('platform, count(*) as count')
                ->group('platform')
                ->select()->toArray();
            
            return ['status' => 1, 'data' => $result];
        } catch (\Exception $e) {
            Log::error('获取扫码平台分布失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '获取扫码平台分布失败'];
        }
    }
}
