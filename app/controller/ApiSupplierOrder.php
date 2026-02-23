<?php
/**
 * 供应商订单控制器
 * 处理供应商订单管理和发货功能
 */
namespace app\controller;

use app\BaseController;
use think\facade\Db;
use think\facade\Log;

class ApiSupplierOrder extends BaseController
{
    /**
     * 当前供应商ID
     * @var int
     */
    protected $supplierId;
    
    /**
     * 当前供应商信息
     * @var array
     */
    protected $supplierInfo;
    
    /**
     * 初始化
     */
    protected function initialize()
    {
        parent::initialize();
        
        // 从请求对象中获取供应商信息（由中间件设置）
        $this->supplierId = $this->request->supplier_id;
        $this->supplierInfo = $this->request->supplier_info;
        
        if (!$this->supplierId) {
            $this->error('请先登录', [], 401);
        }
    }
    
    /**
     * 获取供应商订单列表
     * @return \think\response\Json
     */
    public function supplierOrderList()
    {
        try {
            // 获取查询参数
            $status = $this->request->get('status', ''); // 订单状态
            $keyword = $this->request->get('keyword', ''); // 搜索关键词（订单号/商品名称）
            $page = $this->request->get('page', 1, 'intval'); // 页码
            $pageSize = $this->request->get('pageSize', 10, 'intval'); // 每页数量
            $startDate = $this->request->get('startDate', ''); // 开始日期
            $endDate = $this->request->get('endDate', ''); // 结束日期
            
            // 构建查询
            $query = Db::name('order')
                ->field('id, order_num, create_time, total_amount, status, payment_method, is_pay')
                ->where('supplier_id', $this->supplierId)
                ->where('is_delete', 0);
            
            // 订单状态筛选
            if ($status !== '') {
                $query->where('status', $status);
            }
            
            // 关键词搜索
            if ($keyword) {
                $query->wh(function ($query) use ($keyword) {
                    $query->where('order_num', 'like', '%' . $keyword . '%')
                        ->orWhereExists(function ($query) use ($keyword) {
                            $query->name('order_goods')
                                ->where('order_id', 'exp', '=order.id')
                                ->where('goods_name', 'like', '%' . $keyword . '%');
                        });
                });
            }
            
            // 日期范围筛选
            if ($startDate) {
                $query->where('create_time', '>=', strtotime($startDate));
            }
            if ($endDate) {
                $query->where('create_time', '<=', strtotime($endDate . ' 23:59:59'));
            }
            
            // 获取总数
            $total = $query->count();
            
            // 获取分页数据
            $list = $query
                ->order('create_time', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();
            
            // 格式化订单状态
            $statusText = [
                0 => '待付款',
                1 => '待发货',
                2 => '已发货',
                3 => '已完成',
                -1 => '已取消'
            ];
            
            foreach ($list as &$item) {
                $item['status_text'] = $statusText[$item['status']] ?? '未知';
                $item['create_time_text'] = date('Y-m-d H:i:s', $item['create_time']);
            }
            
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => [
                    'list' => $list,
                    'total' => $total,
                    'page' => $page,
                    'pageSize' => $pageSize
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取供应商订单列表失败: ' . $e->ggetMessage());
            return json([
                'status' => 0,
                'msg' => '获取失败，请稍后重试',
                'data' => []
            ]);
        }
    }
    
    /**
     * 获取订单详情
     * @return \think\response\Json
     */
    public function supplierOrderDetail()
    {
        try {
            $orderId = $this->request->get('order_id', 0, 'intval');
            
            if (!$orderId) {
                return json([
                    'status' => 0,
                    'msg' => '订单ID不能为空',
                    'data' => []
                ]);
            }
            
            // 查询订单信息
            $order = Db::name('order')
                ->where('id', $orderId)
                ->where('supplier_id', $this->supplierId)
                ->where('is_delete', 0)
                ->find();
            
            if (!$order) {
                return json([
                    'status' => 0,
                    'msg' => '订单不存在或无权限查看',
                    'data' => []
                ]);
            }
            
            // 查询订单商品
            $goodsList = Db::name('order_goods')
                ->where('order_id', $orderId)
                ->select()
                ->toArray();
            
            // 查询收货地址
            $address = Db::name('order_address')
                ->where('order_id', $orderId)
                ->find();
            
            // 查询物流信息
            $expressInfo = Db::name('order_express')
                ->where('order_id', $orderId)
                ->find();
            
            // 格式化订单状态
            $statusText = [
                0 => '待付款',
                1 => '待发货',
                2 => '已发货',
                3 => '已完成',
                -1 => '已取消'
            ];
            
            $order['status_text'] = $statusText[$order['status']] ?? '未知';
            $order['create_time_text'] = date('Y-m-d H:i:s', $order['create_time']);
            $order['pay_time_text'] = $order['pay_time'] ? date('Y-m-d H:i:s', $order['pay_time']) : '';
            
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => [
                    'order' => $order,
                    'goods_list' => $goodsList,
                    'address' => $address,
                    'express_info' => $expressInfo
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取订单详情失败: ' . $e->ggetMessage());
            return json([
                'status' => 0,
                'msg' => '获取失败，请稍后重试',
                'data' => []
            ]);
        }
    }
    
    /**
     * 供应商发货
     * @return \think\response\Json
     */
    public function supplierSendExpress()
    {
        try {
            // 获取请求参数
            $orderId = $this->request->post('order_id', 0, 'intval');
            $expressCompany = $this->request->post('express_company', '');
            $expressNumber = $this->request->post('express_number', '');
            $expressRemark = $this->request->post('express_remark', '');
            
            // 参数验证
            if (!$orderId || !$expressCompany || !$expressNumber) {
                return json([
                    'status' => 0,
                    'msg' => '请填写完整的发货信息',
                    'data' => []
                ]);
            }
            
            // 开启事务
            Db::startTrans();
            
            try {
                // 查询订单信息
                $order = Db::name('order')
                    ->where('id', $orderId)
                    ->where('supplier_id', $this->supplierId)
                    ->where('status', 1) // 只能发待发货状态的订单
                    ->where('is_delete', 0)
                    ->find();
                
                if (!$order) {
                    return json([
                        'status' => 0,
                        'msg' => '订单不存在或状态不正确',
                        'data' => []
                    ]);
                }
                
                // 更新订单状态
                Db::name('order')
                    ->where('id', $orderId)
                    ->update([
                        'status' => 2,
                        'delivery_time' => time(),
                        'express_company' => $expressCompany,
                        'express_number' => $expressNumber
                    ]);
                
                // 添加物流信息
                Db::name('order_express')->insert([
                    'order_id' => $orderId,
                    'order_num' => $order['order_num'],
                    'express_company' => $expressCompany,
                    'express_number' => $expressNumber,
                    'express_remark' => $expressRemark,
                    'create_time' => time()
                ]);
                
                // 记录发货日志
                Db::name('supplier_send_log')->insert([
                    'supplier_id' => $this->supplierId,
                    'order_id' => $orderId,
                    'order_num' => $order['order_num'],
                    'express_company' => $expressCompany,
                    'express_number' => $expressNumber,
                    'send_time' => time()
                ]);
                
                // 提交事务
                Db::commit();
                
                // 发送发货通知
                $this->sendOrderSendNotice($order);
                
                return json([
                    'status' => 1,
                    'msg' => '发货成功',
                    'data' => []
                ]);
                
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }
            
        } catch (\Exception $e) {
            Log::error('供应商发货失败: ' . $e->ggetMessage());
            return json([
                'status' => 0,
                'msg' => '发货失败，请稍后重试',
                'data' => []
            ]);
        }
    }
    
    /**
     * 获取物流信息
     * @return \think\response\Json
     */
    public function getExpressInfo()
    {
        try {
            $orderId = $this->request->get('order_id', 0, 'intval');
            
            if (!$orderId) {
                return json([
                    'status' => 0,
                    'msg' => '订单ID不能为空',
                    'data' => []
                ]);
            }
            
            // 查询订单信息
            $order = Db::name('order')
                ->where('id', $orderId)
                ->where('supplier_id', $this->supplierId)
                ->where('is_delete', 0)
                ->find();
            
            if (!$order) {
                return json([
                    'status' => 0,
                    'msg' => '订单不存在或无权限查看',
                    'data' => []
                ]);
            }
            
            // 查询物流信息
            $expressInfo = Db::name('order_express')
                ->where('order_id', $orderId)
                ->find();
            
            if (!$expressInfo) {
                return json([
                    'status' => 0,
                    'msg' => '暂无物流信息',
                    'data' => []
                ]);
            }
            
            // 获取物流轨迹（模拟数据，实际应该调用物流接口）
            $expressTraces = $this->queryExpress($expressInfo['express_company'], $expressInfo['express_number']);
            
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => [
                    'express_info' => $expressInfo,
                    'traces' => $expressTraces
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取物流信息失败: ' . $e->ggetMessage());
            return json([
                'status' => 0,
                'msg' => '获取失败，请稍后重试',
                'data' => []
            ]);
        }
    }
    
    /**
     * 发送订单发货通知
     * @param array $order
     */
    protected function sendOrderSendNotice($order)
    {
        try {
            // 这里可以实现发送短信、邮件或推送通知
            // 目前仅记录日志
            Log::info('订单发货通知: 订单号' . $order['order_num'] . '已发货');
        } catch (\Exception $e) {
            Log::error('发送订单发货通知失败: ' . $e->ggetMessage());
        }
    }
    
    /**
     * 查询物流轨迹（模拟方法）
     * @param string $company
     * @param string $number
     * @return array
     */
    protected function queryExpress($company, $number)
    {
        // 这里应该调用真实的物流查询接口
        // 现在返回模拟数据
        return [
            [
                'time' => date('Y-m-d H:i:s'),
                'context' => '【北京市】您的订单已被签收，签收人：本人'
            ],
            [
                'time' => date('Y-m-d H:i:s', time() - 3600),
                'context' => '【北京市】快递员正在派送中，请保持电话畅通'
            ],
            [
                'time' => date('Y-m-d H:i:s', time() - 7200),
                'context' => '【北京市】包裹已到达北京朝阳区网点'
            ],
            [
                'time' => date('Y-m-d H:i:s', time() - 10800),
                'context' => '【上海市】包裹已发出'
            ]
        ];
    }
    
    /**
     * 获取订单统计数据
     * @return \think\response\Json
     */
    public function supplierOrderStats()
    {
        try {
            // 获取统计时间范围
            $days = $this->request->get('days', 7, 'intval');
            $startTime = time() - $days * 24 * 3600;
            $endTime = time();
            
            // 订单状态统计
            $orderStats = Db::name('order')
                ->where('supplier_id', $this->supplierId)
                ->where('is_delete', 0)
                ->group('status')
                ->field('status, count(*) as num')
                ->select()
                ->toArray();
            
            // 构建统计数组
            $stats = [
                'total_order' => 0,
                'waiting_pay' => 0,
                'waiting_ship' => 0,
                'shipped' => 0,
                'completed' => 0
            ];
            
            foreach ($orderStats as $item) {
                switch ($item['status']) {
                    case 0:
                        $stats['waiting_pay'] = $item['num'];
                        break;
                    case 1:
                        $stats['waiting_ship'] = $item['num'];
                        break;
                    case 2:
                        $stats['shipped'] = $item['num'];
                        break;
                    case 3:
                        $stats['completed'] = $item['num'];
                        break;
                }
                $stats['total_order'] += $item['num'];
            }
            
            // 销售额统计
            $salesStats = Db::name('order')
                ->where('supplier_id', $this->supplierId)
                ->where('is_delete', 0)
                ->where('status', 'in', [1, 2, 3]) // 已付款及以上订单
                ->sum('total_amount');
            
            $stats['total_sales'] = $salesStats ?? 0;
            
            // 近期销售趋势（按天）
            $trendData = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $dayStart = strtotime(date('Y-m-d', time() - $i * 24 * 3600));
                $dayEnd = $dayStart + 24 * 3600 - 1;
                
                $daySales = Db::name('order')
                    ->where('supplier_id', $this->supplierId)
                    ->where('is_delete', 0)
                    ->where('create_time', 'between', [$dayStart, $dayEnd])
                    ->where('status', 'in', [1, 2, 3])
                    ->sum('total_amount');
                
                $dayOrders = Db::name('order')
                    ->where('supplier_id', $this->supplierId)
                    ->where('is_delete', 0)
                    ->where('create_time', 'between', [$dayStart, $dayEnd])
                    ->where('status', 'in', [1, 2, 3])
                    ->count();
                
                $trendData[] = [
                    'date' => date('m-d', $dayStart),
                    'sales' => $daySales ?? 0,
                    'orders' => $dayOrders
                ];
            }
            
            // 热销商品TOP5
            $hotProducts = Db::name('order_goods')
                ->alias('og')
                ->join('order o', 'og.order_id = o.id')
                ->where('o.supplier_id', $this->supplierId)
                ->where('o.is_delete', 0)
                ->where('o.status', 'in', [1, 2, 3])
                ->where('o.create_time', 'between', [$startTime, $endTime])
                ->group('og.goods_id')
                ->field('og.goods_id, og.goods_name, og.goods_image, sum(og.total_price) as total_sales, sum(og.goods_num) as total_num')
                ->order('total_sales', 'desc')
                ->limit(5)
                ->select()
                ->toArray();
            
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => [
                    'stats' => $stats,
                    'trend_data' => $trendData,
                    'hot_products' => $hotProducts
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取订单统计失败: ' . $e->ggetMessage());
            return json([
                'status' => 0,
                'msg' => '获取失败，请稍后重试',
                'data' => []
            ]);
        }
    }
}