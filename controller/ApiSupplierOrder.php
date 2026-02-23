<?php
namespace app\controller;

use app\BaseController;
use think\facade\Db;
use think\facade\Log;

/**
 * 供应商订单管理控制器
 */
class ApiSupplierOrder extends BaseController
{
    /**
     * 获取订单列表
     * @return \think\response\Json
     */
    public function orderList()
    {
        try {
            // 获取供应商ID
            $supplierId = $this->request->userInfo['id'];
            
            // 获取查询参数
            $page = $this->request->get('page', 1, 'intval');
            $limit = $this->request->get('limit', 10, 'intval');
            $orderNo = $this->request->get('orderNo', '', 'trim');
            $goodsName = $this->request->get('goodsName', '', 'trim');
            $status = $this->request->get('status', '', 'trim');
            $expressNo = $this->request->get('expressNo', '', 'trim');
            $startTime = $this->request->get('startTime', '', 'trim');
            $endTime = $this->request->get('endTime', '', 'trim');
            
            // 构建查询条件
            $where = [];
            $where[] = ['o.supplier_id', '=', $supplierId];
            
            if (!empty($orderNo)) {
                $where[] = ['o.order_no', 'like', '%' . $orderNo . '%'];
            }
            
            if (!empty($goodsName)) {
                $where[] = ['g.goods_name', 'like', '%' . $goodsName . '%'];
            }
            
            if (!empty($status)) {
                $where[] = ['o.status', '=', $status];
            }
            
            if (!empty($expressNo)) {
                $where[] = ['o.express_no', 'like', '%' . $expressNo . '%'];
            }
            
            if (!empty($startTime)) {
                $where[] = ['o.create_time', '>=', strtotime($startTime)];
            }
            
            if (!empty($endTime)) {
                $where[] = ['o.create_time', '<=', strtotime($endTime . ' 23:59:59')];
            }
            
            // 获取订单总数
            $total = Db::name('order')
                ->alias('o')
                ->join('order_goods og', 'o.id = og.order_id')
                ->join('goods g', 'og.goods_id = g.id')
                ->where($where)
                ->group('o.id')
                ->count();
            
            // 获取订单列表
            $orders = Db::name('order')
                ->alias('o')
                ->join('order_goods og', 'o.id = og.order_id')
                ->join('goods g', 'og.goods_id = g.id')
                ->join('user_address ua', 'o.address_id = ua.id', 'left')
                ->select([
                    'o.id', 'o.order_no', 'o.status', 'o.total_amount', 'o.pay_amount', 
                    'o.pay_time', 'o.create_time', 'o.express_name', 'o.express_no', 
                    'o.ship_time', 'g.goods_name', 'g.goods_sn', 'og.goods_price', 
                    'og.goods_num', 'ua.consignee', 'ua.mobile', 'ua.address'
                ])
                ->where($where)
                ->order('o.create_time DESC')
                ->page($page, $limit)
                ->select();
            
            // 格式化数据
            $result = [];
            $orderMap = [];
            
            foreach ($orders as $order) {
                $orderId = $order['id'];
                
                if (!isset($orderMap[$orderId])) {
                    $orderMap[$orderId] = [
                        'id' => $order['id'],
                        'order_no' => $order['order_no'],
                        'status' => $order['status'],
                        'total_amount' => $order['total_amount'],
                        'pay_amount' => $order['pay_amount'],
                        'pay_time' => $order['pay_time'] ? date('Y-m-d H:i:s', $order['pay_time']) : '',
                        'create_time' => date('Y-m-d H:i:s', $order['create_time']),
                        'express_name' => $order['express_name'],
                        'express_no' => $order['express_no'],
                        'ship_time' => $order['ship_time'] ? date('Y-m-d H:i:s', $order['ship_time']) : '',
                        'consignee' => $order['consignee'],
                        'mobile' => $order['mobile'],
                        'address' => $order['address'],
                        'goods' => []
                    ];
                }
                
                // 添加商品信息
                $orderMap[$orderId]['goods'][] = [
                    'goods_name' => $order['goods_name'],
                    'goods_sn' => $order['goods_sn'],
                    'goods_price' => $order['goods_price'],
                    'goods_num' => $order['goods_num']
                ];
            }
            
            $result = array_values($orderMap);
            
            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $result,
                'total' => $total
            ]);
        } catch (\Exception $e) {
            Log::error('获取订单列表失败: ' . $e->getMessage());
            return json(['code' => 500, 'msg' => '获取订单列表失败']);
        }
    }
    
    /**
     * 获取订单详情
     * @return \think\response\Json
     */
    public function orderDetail()
    {
        try {
            // 获取供应商ID
            $supplierId = $this->request->userInfo['id'];
            
            // 获取订单ID
            $orderId = $this->request->get('id', 0, 'intval');
            
            if (empty($orderId)) {
                return json(['code' => 400, 'msg' => '订单ID不能为空']);
            }
            
            // 获取订单信息
            $order = Db::name('order')
                ->alias('o')
                ->join('user_address ua', 'o.address_id = ua.id', 'left')
                ->select([
                    'o.*', 'ua.consignee', 'ua.mobile', 'ua.address'
                ])
                ->where([
                    ['o.id', '=', $orderId],
                    ['o.supplier_id', '=', $supplierId]
                ])
                ->find();
            
            if (empty($order)) {
                return json(['code' => 404, 'msg' => '订单不存在']);
            }
            
            // 获取订单商品信息
            $goodsList = Db::name('order_goods')
                ->alias('og')
                ->join('goods g', 'og.goods_id = g.id')
                ->select([
                    'og.*', 'g.goods_name', 'g.goods_sn', 'g.goods_image'
                ])
                ->where('og.order_id', '=', $orderId)
                ->select();
            
            // 格式化时间
            $order['create_time'] = date('Y-m-d H:i:s', $order['create_time']);
            $order['pay_time'] = $order['pay_time'] ? date('Y-m-d H:i:s', $order['pay_time']) : '';
            $order['ship_time'] = $order['ship_time'] ? date('Y-m-d H:i:s', $order['ship_time']) : '';
            $order['confirm_time'] = $order['confirm_time'] ? date('Y-m-d H:i:s', $order['confirm_time']) : '';
            
            // 组装返回数据
            $result = [
                'order' => $order,
                'goodsList' => $goodsList
            ];
            
            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('获取订单详情失败: ' . $e->getMessage());
            return json(['code' => 500, 'msg' => '获取订单详情失败']);
        }
    }
    
    /**
     * 订单发货
     * @return \think\response\Json
     */
    public function sendExpress()
    {
        try {
            // 获取供应商ID
            $supplierId = $this->request->userInfo['id'];
            
            // 获取请求参数
            $orderId = $this->request->get('orderId', 0, 'intval');
            $expressName = $this->request->get('expressName', '', 'trim');
            $expressNo = $this->request->get('expressNo', '', 'trim');
            $shipRemarks = $this->request->get('shipRemarks', '', 'trim');
            
            // 参数验证
            if (empty($orderId) || empty($expressName) || empty($expressNo)) {
                return json(['code' => 400, 'msg' => '请填写完整的发货信息']);
            }
            
            // 开启事务
            Db::startTrans();
            try {
                // 获取订单信息
                $order = Db::name('order')
                    ->where([
                        ['id', '=', $orderId],
                        ['supplier_id', '=', $supplierId],
                        ['status', '=', 10] // 待发货状态
                    ])
                    ->find();
                
                if (empty($order)) {
                    Db::rollback();
                    return json(['code' => 400, 'msg' => '订单不存在或状态不正确']);
                }
                
                // 更新订单状态
                $result = Db::name('order')
                    ->where('id', '=', $orderId)
                    ->update([
                        'status' => 20, // 已发货
                        'express_name' => $expressName,
                        'express_no' => $expressNo,
                        'ship_time' => time()
                    ]);
                
                if (!$result) {
                    Db::rollback();
                    return json(['code' => 500, 'msg' => '发货失败']);
                }
                
                // 记录发货日志
                Db::name('order_ship_log')->insert([
                    'order_id' => $orderId,
                    'order_no' => $order['order_no'],
                    'supplier_id' => $supplierId,
                    'express_name' => $expressName,
                    'express_no' => $expressNo,
                    'remarks' => $shipRemarks,
                    'create_time' => time()
                ]);
                
                // 提交事务
                Db::commit();
                
                return json(['code' => 200, 'msg' => '发货成功']);
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                Log::error('订单发货失败: ' . $e->getMessage());
                return json(['code' => 500, 'msg' => '发货失败']);
            }
        } catch (\Exception $e) {
            Log::error('订单发货异常: ' . $e->getMessage());
            return json(['code' => 500, 'msg' => '发货异常']);
        }
    }
    
    /**
     * 获取物流信息
     * @return \think\response\Json
     */
    public function getExpressInfo()
    {
        try {
            // 获取供应商ID
            $supplierId = $this->request->userInfo['id'];
            
            // 获取订单ID
            $orderId = $this->request->get('orderId', 0, 'intval');
            
            if (empty($orderId)) {
                return json(['code' => 400, 'msg' => '订单ID不能为空']);
            }
            
            // 获取订单信息
            $order = Db::name('order')
                ->where([
                    ['id', '=', $orderId],
                    ['supplier_id', '=', $supplierId]
                ])
                ->find();
            
            if (empty($order)) {
                return json(['code' => 404, 'msg' => '订单不存在']);
            }
            
            if (empty($order['express_no'])) {
                return json(['code' => 400, 'msg' => '订单未发货，无物流信息']);
            }
            
            // 这里模拟物流信息，实际项目中应该调用第三方物流API
            $expressData = [
                'express_name' => $order['express_name'],
                'express_no' => $order['express_no'],
                'logistics' => [
                    [
                        'time' => date('Y-m-d H:i:s', time() - 3600),
                        'context' => '包裹已由【' . $order['consignee'] . '】签收，感谢您使用我们的服务'
                    ],
                    [
                        'time' => date('Y-m-d H:i:s', time() - 7200),
                        'context' => '快递员【张三 138****1234】正在为您派送，请保持电话畅通'
                    ],
                    [
                        'time' => date('Y-m-d H:i:s', time() - 10800),
                        'context' => '包裹已到达【' . $order['address'] . '】网点'
                    ],
                    [
                        'time' => date('Y-m-d H:i:s', time() - 21600),
                        'context' => '包裹已从【广州转运中心】发出'
                    ],
                    [
                        'time' => date('Y-m-d H:i:s', time() - 25200),
                        'context' => '包裹已到达【广州转运中心】'
                    ],
                    [
                        'time' => date('Y-m-d H:i:s', time() - 28800),
                        'context' => '包裹已由【' . $order['express_name'] . '】揽收'
                    ]
                ]
            ];
            
            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $expressData
            ]);
        } catch (\Exception $e) {
            Log::error('获取物流信息失败: ' . $e->getMessage());
            return json(['code' => 500, 'msg' => '获取物流信息失败']);
        }
    }
    
    /**
     * 获取订单统计数据
     * @return \think\response\Json
     */
    public function orderStats()
    {
        try {
            // 获取供应商ID
            $supplierId = $this->request->userInfo['id'];
            
            // 获取时间范围参数
            $timeRange = $this->request->get('timeRange', 7, 'intval');
            $startTime = time() - $timeRange * 86400;
            
            // 查询条件
            $where = [
                ['supplier_id', '=', $supplierId],
                ['create_time', '>=', $startTime]
            ];
            
            // 统计订单总数
            $totalOrders = Db::name('order')->where($where)->count();
            
            // 统计销售总额
            $totalSales = Db::name('order')
                ->where($where)
                ->sum('pay_amount');
            
            // 统计已完成订单数
            $completedOrders = Db::name('order')
                ->where(array_merge($where, [['status', '=', 30]]))
                ->count();
            
            // 统计已取消订单数
            $cancelledOrders = Db::name('order')
                ->where(array_merge($where, [['status', '=', 40]]))
                ->count();
            
            // 获取每日订单数和销售额趋势
            $trendData = [];
            for ($i = $timeRange - 1; $i >= 0; $i--) {
                $dayStart = strtotime(date('Y-m-d', time() - $i * 86400));
                $dayEnd = $dayStart + 86399;
                
                // 查询当日订单数据
                $dayOrders = Db::name('order')
                    ->where([
                        ['supplier_id', '=', $supplierId],
                        ['create_time', '>=', $dayStart],
                        ['create_time', '<=', $dayEnd]
                    ])
                    ->select(['create_time', 'pay_amount']);
                
                // 统计当日订单数和销售额
                $orderCount = count($dayOrders);
                $daySales = array_sum(array_column($dayOrders, 'pay_amount'));
                
                $trendData[] = [
                    'date' => date('Y-m-d', $dayStart),
                    'orderCount' => $orderCount,
                    'sales' => $daySales
                ];
            }
            
            // 获取热销商品Top 10
            $topGoods = Db::name('order_goods')
                ->alias('og')
                ->join('goods g', 'og.goods_id = g.id')
                ->join('order o', 'og.order_id = o.id')
                ->where($where)
                ->where('o.status', '!=', 40) // 排除已取消订单
                ->order('og.goods_num DESC')
                ->group('og.goods_id')
                ->select([
                    'g.goods_name', 'g.goods_sn', 
                    Db::raw('SUM(og.goods_num) as total_sales'),
                    Db::raw('SUM(og.goods_num * og.goods_price) as total_amount')
                ])
                ->limit(10)
                ->select();
            
            // 组装返回数据
            $result = [
                'totalOrders' => $totalOrders,
                'totalSales' => round($totalSales, 2),
                'completedOrders' => $completedOrders,
                'cancelledOrders' => $cancelledOrders,
                'trendData' => $trendData,
                'topGoods' => $topGoods
            ];
            
            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('获取订单统计数据失败: ' . $e->getMessage());
            return json(['code' => 500, 'msg' => '获取订单统计数据失败']);
        }
    }
}