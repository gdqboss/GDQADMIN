<?php
namespace app\controller;

use think\facade\View;
use think\facade\Db;
use think\facade\Request;
use think\facade\Validate;
use think\facade\Cache;

class BarcodeInventory extends Base
{
    // 库存列表
    public function index()
    {
        try {
            if (Request::isAjax()) {
                // 确保返回JSON格式
                header('Content-Type: application/json');
                
                $page = input('param.page/d', 1);
                $limit = input('param.limit/d', 10);
                $proname = input('param.proname/s', '');
                
                // 获取aid，确保有默认值
                $aid = 1; // 设置默认值，避免session问题
                
                // 记录session信息
                $sessionAid = session('aid');
                Db::name('barcode_inventory_log')->insert([
                    'aid' => $aid,
                    'bid' => session('bid') ?? 0,
                    'type' => 'index_debug',
                    'request_data' => json_encode(Request::param()),
                    'error_msg' => 'Session aid: ' . ($sessionAid ?? 'null') . ', Using aid: ' . $aid,
                    'create_time' => time()
                ]);
                
                $where = [['bi.aid', '=', $aid]];
                if ($proname) {
                    $where[] = ['bi.name', 'like', '%' . $proname . '%'];
                }
                
                // 直接查询数据库，不使用join
                $count = Db::name('barcode_inventory')
                    ->where('aid', $aid)
                    ->when($proname, function($query) use ($proname) {
                        $query->where('name', 'like', '%' . $proname . '%');
                    })
                    ->count();
                
                $list = Db::name('barcode_inventory')
                ->where('aid', $aid)
                ->field('id, name, pic, stock, english_name, supplier_id')
                ->when($proname, function($query) use ($proname) {
                    $query->where('name', 'like', '%' . $proname . '%');
                })
                ->order('id desc')
                ->page($page, $limit)
                ->select()->toArray();
                
                // 处理商品数据
                if (!empty($list)) {
                    // 获取所有供应商ID
                    $supplierIds = array_column($list, 'supplier_id');
                    $supplierIds = array_unique(array_filter($supplierIds));
                    
                    // 批量查询供应商信息
                    $suppliers = [];
                    if (!empty($supplierIds)) {
                        $suppliers = Db::name('product_supplier')
                            ->where('id', 'in', $supplierIds)
                            ->field('id, supplier_name')
                            ->select()->toArray();
                        
                        // 构建供应商ID到名称的映射
                        $supplierMap = [];
                        foreach ($suppliers as $supplier) {
                            $supplierMap[$supplier['id']] = $supplier['supplier_name'];
                        }
                    }
                    
                    foreach ($list as &$item) {
                        // 处理图片，不使用getpic函数，避免依赖问题
                        if ($item['pic']) {
                            // 直接返回图片路径，不做额外处理
                            $item['pic'] = $item['pic'];
                        } else {
                            $item['pic'] = '';
                        }
                        
                        // 添加供应商名称
                        $item['supplier_name'] = $supplierMap[$item['supplier_id']] ?? '';
                        
                        // 简化规格处理，直接获取规格数据
                        $item['guige'] = Db::name('barcode_inventory_guige')
                            ->where('proid', $item['id'])
                            ->field('id, name, stock, cost_price, market_price, sell_price, barcode, code_type, pic')
                            ->select()->toArray();
                    }
                }
                
                return json(['code' => 0, 'msg' => t('查询成功'), 'count' => $count, 'data' => $list]);
            }
            
            $title = t('库存列表');
            View::assign('title', $title);
            return View::fetch();
        } catch (\Exception $e) {
            // 记录异常日志
            Db::name('barcode_inventory_log')->insert([
                'aid' => 1, // 使用默认值
                'bid' => session('bid') ?? 0,
                'type' => 'index_exception',
                'request_data' => json_encode(Request::param()),
                'error_msg' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'create_time' => time()
            ]);
            
            // 如果是Ajax请求，返回详细的错误信息
            if (Request::isAjax()) {
                header('Content-Type: application/json');
                return json(['code' => 1, 'msg' => t('数据查询失败：') . $e->getMessage(), 'count' => 0, 'data' => []]);
            }
            
            // 非Ajax请求，显示错误页面
            return showmsg(t('数据查询失败：') . $e->getMessage());
        }
    }

    // 入库管理
    public function inbound()
    {
        if (Request::isPost()) {
            $data = input('post.');
            
            // 验证参数
            $validate = Validate::rule([
                'proid' => 'require|number|gt:0',
                'num' => 'require|number|gt:0',
                'price' => 'require|float|egt:0'
            ]);
            
            if (!$validate->check($data)) {
                return json(['status' => 0, 'msg' => $validate->getError()]);
            }
            
            $proid = $data['proid'];
            $ggid = $data['ggid'];
            $num = $data['num'];
            $price = $data['price'];
            
            // 获取商品信息
            $product = $this->getProductInfo($proid);
            
            if (is_array($product) && isset($product['error'])) {
                return json(['status' => 0, 'msg' => $product['error']]);
            }
            
            // 处理默认规格情况
            $guige = null;
            if ($ggid && $ggid != 'default') {
                $guige = $this->getGuigeInfo($ggid, $proid);
                
                if (is_array($guige) && isset($guige['error'])) {
                    return json(['status' => 0, 'msg' => $guige['error']]);
                }
            } else {
                // 默认规格处理
                $guige = [
                    'id' => 0,
                    'proid' => $proid,
                    'stock' => 0,
                    'price' => $price
                ];
            }
            
            // 生成入库单号
            $ordernum = 'IN' . date('YmdHis') . rand(100, 999);
            
            // 记录库存变动
            $this->recordStockChange($product, $guige, $num, $ordernum, 'in', $price);
            
            // 更新商品和规格库存
            $this->updateStock($proid, $ggid, $num);
            
            // 清除相关缓存
            Cache::delete('barcode_inventory:product_list:' . aid);
            Cache::delete('barcode_inventory:guige:' . $proid);
            
            return json(['status' => 1, 'msg' => t('入库成功'), 'url' => true]);
        }
        
        // 获取商品列表用于选择
        $products = $this->getProductList();
        
        $title = t('入库管理');
        View::assign('title', $title);
        View::assign('products', $products);
        return View::fetch();
    }

    // 出库管理
    public function outbound()
    {
        $aid = 1;
        
        if (Request::isPost()) {
            $data = input('post.');
            
            // 验证参数
            $validate = Validate::rule([
                'proid' => 'require|number|gt:0',
                'specs' => 'require',
                'outbound_type' => 'require|in:1,2',
                'dealer_id' => function($value, $data) {
                    if ($data['outbound_type'] == 2 && (empty($value) || $value <= 0)) {
                        return t('请选择经销商');
                    }
                    return true;
                },
            ]);
            
            if (!$validate->check($data)) {
                return json(['status' => 0, 'msg' => $validate->getError()]);
            }
            
            $proid = $data['proid'];
            $specs = json_decode($data['specs'], true);
            $outboundType = $data['outbound_type'];
            $dealerId = $data['dealer_id'] ?? 0;
            
            // 验证规格数据
            if (!is_array($specs) || empty($specs)) {
                return json(['status' => 0, 'msg' => t('请选择至少一个规格')]);
            }
            
            // 获取商品信息
            $product = $this->getProductInfo($proid);
            if (is_array($product) && isset($product['error'])) {
                return json(['status' => 0, 'msg' => $product['error']]);
            }
            
            // 生成出库单号
            $ordernum = 'OUT' . date('YmdHis') . rand(100, 999);
            
            // 开启事务
            Db::startTrans();
            
            try {
                foreach ($specs as $spec) {
                    $ggid = $spec['ggid'];
                    $num = $spec['num'];
                    
                    // 获取规格信息
                    $guige = $this->getGuigeInfo($ggid, $proid);
                    if (is_array($guige) && isset($guige['error'])) {
                        throw new Exception($guige['error']);
                    }
                    
                    // 检查库存是否充足
                    if ($guige['stock'] < $num) {
                        throw new Exception(t('库存不足') . '：' . $guige['name']);
                    }
                    
                    // 记录库存变动（出库为负数）
                    $this->recordStockChange($product, $guige, -$num, $ordernum, 'out', $guige['sell_price'], $outboundType, $dealerId);
                    
                    // 更新商品和规格库存
                    $this->updateStock($proid, $ggid, -$num);
                }
                
                // 提交事务
                Db::commit();
                
                // 清除相关缓存
                Cache::delete('barcode_inventory:product_list:' . $aid);
                Cache::delete('barcode_inventory:guige:' . $proid);
                
                return json(['status' => 1, 'msg' => t('出库成功'), 'url' => true]);
            } catch (Exception $e) {
                // 回滚事务
                Db::rollback();
                
                return json(['status' => 0, 'msg' => $e->getMessage()]);
            }
        }
        
        // 获取商品列表用于选择
        $products = $this->getProductList();
        
        // 获取经销商列表
        $dealers = Db::name('barcode_dealer')
            ->order('id desc')
            ->select()->toArray();
        
        $title = t('出库管理');
        View::assign('title', $title);
        View::assign('products', $products);
        View::assign('dealers', $dealers);
        return View::fetch();
    }

    // 库存盘点记录列表
    public function check()
    {
        // 检测是否是表格请求
        $isTableRequest = input('param.page/d', 0) > 0 && input('param.limit/d', 0) > 0;
        
        if ($isTableRequest) {
            $page = input('param.page/d', 1);
            $limit = input('param.limit/d', 10);
            $check_name = input('param.check_name/s', '');
            $status = input('param.status/d', -1);
            
            // 使用固定值1作为aid，与其他方法保持一致
            $aid = 1;
            
            $where = [['aid', '=', $aid]];
            if ($check_name) {
                $where[] = ['check_name', 'like', '%' . $check_name . '%'];
            }
            if ($status != -1) {
                $where[] = ['status', '=', $status];
            }
            
            $count = Db::name('barcode_inventory_check')
                ->where($where)
                ->count();
            
            $list = Db::name('barcode_inventory_check')
                ->where($where)
                ->field('id, check_sn, check_name, check_time, check_user, total_products, total_diff, total_diff_amount, status, remark, createtime')
                ->order('createtime desc')
                ->page($page, $limit)
                ->select()->toArray();
            
            // 格式化数据
            foreach ($list as &$item) {
                $item['check_time'] = date('Y-m-d H:i:s', $item['check_time']);
                $item['createtime'] = date('Y-m-d H:i:s', $item['createtime']);
                $item['status_text'] = $item['status'] == 1 ? t('已完成') : t('未完成');
            }
            
            return json(['code' => 0, 'msg' => t('查询成功'), 'count' => $count, 'data' => $list]);
        }
        
        $title = t('库存盘点');
        View::assign('title', $title);
        return View::fetch();
    }
    
    // 创建盘点任务
    public function create_check()
    {
        if (Request::isPost()) {
            $data = input('post.');
            
            // 验证数据
            $validate = Validate::rule([
                'check_name' => 'require|max:100',
                'remark' => 'max:500'
            ]);
            
            if (!$validate->check($data)) {
                return json(['status' => 0, 'msg' => $validate->getError()]);
            }
            
            // 生成盘点单号
            $check_sn = 'CHECK' . date('YmdHis') . rand(100, 999);
            
            // 使用固定值1作为aid，与其他方法保持一致
            $aid = 1;
            $bid = 1;
            
            // 获取待盘点的商品规格
            $products = Db::name('barcode_inventory')
                ->alias('bi')
                ->join('barcode_inventory_guige big', 'bi.id = big.proid', 'left')
                ->where('bi.aid', $aid)
                ->field('bi.id as proid, bi.name as pro_name, big.id as ggid, big.name as gg_name, big.barcode, big.stock as system_stock, big.sell_price as price')
                ->select()->toArray();
            
            // 开启事务
            Db::startTrans();
            try {
                // 创建盘点记录
                $checkId = Db::name('barcode_inventory_check')->insertGetId([
                    'aid' => $aid,
                    'bid' => $bid,
                    'check_sn' => $check_sn,
                    'check_name' => $data['check_name'],
                    'check_time' => time(),
                    'check_user' => isset($this->user['username']) ? $this->user['username'] : 'admin',
                    'total_products' => count($products),
                    'total_diff' => 0,
                    'total_diff_amount' => 0.00,
                    'status' => 0,
                    'remark' => $data['remark'] ?? '',
                    'createtime' => time(),
                    'updatetime' => time()
                ]);
                
                // 创建盘点详情
                if (!empty($products)) {
                    $detailData = [];
                    foreach ($products as $product) {
                        $detailData[] = [
                            'aid' => $aid,
                            'check_id' => $checkId,
                            'proid' => $product['proid'],
                            'ggid' => $product['ggid'],
                            'pro_name' => $product['pro_name'],
                            'gg_name' => $product['gg_name'],
                            'barcode' => $product['barcode'],
                            'system_stock' => $product['system_stock'],
                            'actual_stock' => $product['system_stock'], // 默认与系统库存一致
                            'diff_stock' => 0,
                            'price' => $product['price'],
                            'diff_amount' => 0.00,
                            'remark' => '',
                            'createtime' => time()
                        ];
                    }
                    Db::name('barcode_inventory_check_detail')->insertAll($detailData);
                }
                
                // 提交事务
                Db::commit();
                
                // 将URL对象转换为字符串
                $detailUrl = (string)url('BarcodeInventory/check_detail', ['id' => $checkId]);
                return json(['status' => 1, 'msg' => t('盘点任务创建成功'), 'url' => $detailUrl]);
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return json(['status' => 0, 'msg' => t('创建失败：') . $e->getMessage()]);
            }
        }
        
        $title = t('创建盘点任务');
        View::assign('title', $title);
        return View::fetch('create_check');
    }
    
    // 盘点详情和执行盘点
    public function check_detail()
    {
        $id = input('param.id/d', 0);
        
        // 使用固定值1作为aid，与其他方法保持一致
        $aid = 1;
        
        // 获取盘点记录
        $checkInfo = Db::name('barcode_inventory_check')
            ->where('id', $id)
            ->where('aid', $aid)
            ->find();
            
        if (!$checkInfo) {
            showmsg(t('盘点记录不存在'));
        }
        
        // 获取盘点详情 - 检测是否是表格请求
        $isTableRequest = input('param.page/d', 0) > 0 && input('param.limit/d', 0) > 0;
        
        if ($isTableRequest) {
            $page = input('param.page/d', 1);
            $limit = input('param.limit/d', 20);
            $pro_name = input('param.pro_name/s', '');
            
            $where = [['aid', '=', $aid], ['check_id', '=', $id]];
            if ($pro_name) {
                $where[] = ['pro_name', 'like', '%' . $pro_name . '%'];
            }
            
            $count = Db::name('barcode_inventory_check_detail')
                ->where($where)
                ->count();
            
            $list = Db::name('barcode_inventory_check_detail')
                ->where($where)
                ->field('id, proid, ggid, pro_name, gg_name, barcode, system_stock, actual_stock, diff_stock, price, diff_amount, remark')
                ->page($page, $limit)
                ->select()->toArray();
            
            return json(['code' => 0, 'msg' => t('查询成功'), 'count' => $count, 'data' => $list]);
        }
        
        $title = t('盘点详情');
        View::assign('title', $title);
        View::assign('checkInfo', $checkInfo);
        return View::fetch('check_detail');
    }
    
    // 更新盘点数量
    public function update_check_stock()
    {
        $id = input('param.id/d', 0);
        $actual_stock = input('param.actual_stock/d', 0);
        
        // 验证参数
        if (empty($id) || $actual_stock < 0) {
            return json(['status' => 0, 'msg' => t('参数错误')]);
        }
        
        // 使用固定值1作为aid，与其他方法保持一致
        $aid = 1;
        
        // 获取盘点详情
        $detail = Db::name('barcode_inventory_check_detail')
            ->where('id', $id)
            ->where('aid', $aid)
            ->find();
            
        if (!$detail) {
            return json(['status' => 0, 'msg' => t('盘点详情不存在')]);
        }
        
        // 计算差异
        $diff_stock = $actual_stock - $detail['system_stock'];
        $diff_amount = $diff_stock * $detail['price'];
        
        // 更新盘点详情
        $result = Db::name('barcode_inventory_check_detail')
            ->where('id', $id)
            ->update([
                'actual_stock' => $actual_stock,
                'diff_stock' => $diff_stock,
                'diff_amount' => $diff_amount
            ]);
        
        if ($result) {
            // 重新计算盘点记录的总差异
            $checkId = $detail['check_id'];
            $totalDiff = Db::name('barcode_inventory_check_detail')
                ->where('check_id', $checkId)
                ->where('aid', $aid)
                ->sum('diff_stock');
                
            $totalDiffAmount = Db::name('barcode_inventory_check_detail')
                ->where('check_id', $checkId)
                ->where('aid', $aid)
                ->sum('diff_amount');
                
            Db::name('barcode_inventory_check')
                ->where('id', $checkId)
                ->update([
                    'total_diff' => $totalDiff,
                    'total_diff_amount' => $totalDiffAmount
                ]);
                
            return json(['status' => 1, 'msg' => t('更新成功'), 'diff_stock' => $diff_stock, 'diff_amount' => $diff_amount]);
        } else {
            return json(['status' => 0, 'msg' => t('更新失败')]);
        }
    }
    
    // 完成盘点
    public function finish_check()
    {
        $id = input('param.id/d', 0);
        
        // 使用固定值1作为aid，与其他方法保持一致
        $aid = 1;
        
        // 获取盘点记录
        $checkInfo = Db::name('barcode_inventory_check')
            ->where('id', $id)
            ->where('aid', $aid)
            ->where('status', 0) // 只能完成未完成的盘点
            ->find();
            
        if (!$checkInfo) {
            return json(['status' => 0, 'msg' => t('盘点记录不存在或已完成')]);
        }
        
        // 开启事务
        Db::startTrans();
        try {
            // 1. 更新盘点记录状态
                Db::name('barcode_inventory_check')
                    ->where('id', $id)
                    ->update([
                        'status' => 1,
                        'updatetime' => time()
                    ]);
            
            // 2. 更新商品规格的实际库存
            $details = Db::name('barcode_inventory_check_detail')
                ->where('check_id', $id)
                ->where('aid', $aid)
                ->select()->toArray();
                
            foreach ($details as $detail) {
                if ($detail['diff_stock'] != 0) {
                    // 更新规格库存
                    Db::name('barcode_inventory_guige')
                        ->where('id', $detail['ggid'])
                        ->update(['stock' => $detail['actual_stock']]);
                    
                    // 更新商品总库存
                    $proStock = Db::name('barcode_inventory_guige')
                        ->where('proid', $detail['proid'])
                        ->sum('stock');
                        
                    Db::name('barcode_inventory')
                        ->where('id', $detail['proid'])
                        ->update(['stock' => $proStock]);
                }
            }
            
            // 提交事务
            Db::commit();
            
            return json(['status' => 1, 'msg' => t('盘点完成成功'), 'url' => url('BarcodeInventory/check')]);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return json(['status' => 0, 'msg' => t('盘点完成失败：') . $e->getMessage()]);
        }
    }
    
    // 盘点差异分析
    public function check_analysis()
    {
        $id = input('param.id/d', 0);
        
        // 使用固定值1作为aid，与其他方法保持一致
        $aid = 1;
        
        // 获取盘点记录
        $checkInfo = Db::name('barcode_inventory_check')
            ->where('id', $id)
            ->where('aid', $aid)
            ->find();
            
        if (!$checkInfo) {
            showmsg(t('盘点记录不存在'));
        }
        
        // 获取差异分析数据
        $analysis = [
            'total_products' => $checkInfo['total_products'],
            'total_diff' => $checkInfo['total_diff'],
            'total_diff_amount' => $checkInfo['total_diff_amount'],
            'overstock' => 0, // 盘盈数量
            'shortage' => 0, // 盘亏数量
            'overstock_amount' => 0.00, // 盘盈金额
            'shortage_amount' => 0.00, // 盘亏金额
            'no_diff' => 0, // 无差异数量
        ];
        
        // 统计盘盈盘亏
        $details = Db::name('barcode_inventory_check_detail')
            ->where('check_id', $id)
            ->where('aid', $aid)
            ->field('diff_stock, diff_amount')
            ->select()->toArray();
            
        foreach ($details as $detail) {
            if ($detail['diff_stock'] > 0) {
                $analysis['overstock'] += $detail['diff_stock'];
                $analysis['overstock_amount'] += $detail['diff_amount'];
            } elseif ($detail['diff_stock'] < 0) {
                $analysis['shortage'] += abs($detail['diff_stock']);
                $analysis['shortage_amount'] += abs($detail['diff_amount']);
            } else {
                $analysis['no_diff']++;
            }
        }
        
        // 格式化金额
        $analysis['overstock_amount'] = round($analysis['overstock_amount'], 2);
        $analysis['shortage_amount'] = round($analysis['shortage_amount'], 2);
        
        $title = t('盘点差异分析');
        View::assign('title', $title);
        View::assign('checkInfo', $checkInfo);
        View::assign('analysis', $analysis);
        return View::fetch('check_analysis');
    }

    // 库存报表
    public function report()
    {
        if (Request::isAjax()) {
            $type = input('param.type/s', 'inventory');
            $start_date = input('param.start_date/s', '');
            $end_date = input('param.end_date/s', '');
            
            switch ($type) {
                case 'inventory':
                    // 库存统计
                    $data = $this->getInventoryReport($start_date, $end_date);
                    break;
                case 'movement':
                    // 库存变动
                    $data = $this->getInventoryMovementReport($start_date, $end_date);
                    break;
                case 'summary':
                default:
                    // 库存汇总
                    $data = $this->getInventorySummaryReport();
                    break;
            }
            
            return json(['status' => 1, 'data' => $data]);
        }
        
        $title = t('库存报表');
        View::assign('title', $title);
        return View::fetch();
    }
    
    // 库存汇总报表
    private function getInventorySummaryReport()
    {
        // 使用固定值1作为aid，与其他方法保持一致
        $aid = 1;
        
        // 总库存商品数
        $totalProducts = Db::name('barcode_inventory')
            ->where('aid', $aid)
            ->count();
        
        // 总规格数
        $totalGuiges = Db::name('barcode_inventory_guige')
            ->where('aid', $aid)
            ->count();
        
        // 总库存数量
        $totalStock = Db::name('barcode_inventory_guige')
            ->where('aid', $aid)
            ->sum('stock');
        
        // 总库存金额
        $totalAmount = Db::name('barcode_inventory_guige')
            ->where('aid', $aid)
            ->sum(Db::raw('stock * sell_price'));
        
        // 库存预警商品数
        $warningStock = Db::name('barcode_inventory_guige')
            ->where('aid', $aid)
            ->where('stock', '<=', 0)
            ->count();
        
        return [
            'total_products' => $totalProducts,
            'total_guiges' => $totalGuiges,
            'total_stock' => $totalStock,
            'total_amount' => round($totalAmount, 2),
            'warning_stock' => $warningStock
        ];
    }
    
    // 库存明细报表
    private function getInventoryReport($start_date = '', $end_date = '')
    {
        // 使用固定值1作为aid，与其他方法保持一致
        $aid = 1;
        
        $where = [['g.aid', '=', $aid]];
        
        if ($start_date && $end_date) {
            $where[] = ['g.update_time', 'between', [strtotime($start_date), strtotime($end_date) + 86399]];
        }
        
        $list = Db::name('barcode_inventory_guige')
            ->alias('g')
            ->join('barcode_inventory p', 'g.proid = p.id')
            ->field('p.id as product_id, p.name as product_name, g.id as guige_id, g.name as guige_name, g.stock, g.sell_price as price, 0 as warn_stock')
            ->where($where)
            ->order('p.id, g.id')
            ->select()->toArray();
        
        return $list;
    }
    
    // 库存变动报表
    private function getInventoryMovementReport($start_date = '', $end_date = '')
    {
        // 使用固定值1作为aid，与其他方法保持一致
        $aid = 1;
        
        // 暂时返回空数组，因为仓库系统可能没有专门的库存变动日志表
        return [];
    }

    // 系统设置
    public function settings()
    {
        $title = t('系统设置');
        View::assign('title', $title);
        return View::fetch();
    }
    
    // 打印报表
    public function print_report()
    {
        $type = input('param.type/s', 'outbound');
        $start_date = input('param.start_date/s', '');
        $end_date = input('param.end_date/s', '');
        $proid = input('param.proid/d', 0);
        
        // 使用固定值1作为aid，与其他方法保持一致
        $aid = 1;
        
        $where = [['aid', '=', $aid]];
        
        if ($type == 'outbound') {
            $where[] = ['type', '=', 'out'];
        } elseif ($type == 'in') {
            $where[] = ['type', '=', 'in'];
        }
        
        if ($start_date && $end_date) {
            $where[] = ['createtime', 'between', [strtotime($start_date), strtotime($end_date) + 86399]];
        }
        
        if ($proid) {
            $where[] = ['proid', '=', $proid];
        }
        
        // 获取商品列表，用于筛选
        $products = Db::name('barcode_inventory')
            ->where('aid', $aid)
            ->field('id, name')
            ->select()->toArray();
        
        // 获取报表数据
        $reportData = [];
        if (Request::isAjax()) {
            $page = input('param.page/d', 1);
            $limit = input('param.limit/d', 20);
            
            $count = Db::name('barcode_stock_log')
                ->where($where)
                ->count();
            
            $list = Db::name('barcode_stock_log')
                ->where($where)
                ->field('id, ordernum, pro_name, gg_name, num, type, price, total_amount, createtime, remark')
                ->order('createtime desc')
                ->page($page, $limit)
                ->select()->toArray();
            
            // 格式化数据
            foreach ($list as &$item) {
                $item['createtime'] = date('Y-m-d H:i:s', $item['createtime']);
                $item['type_text'] = $item['type'] == 'in' ? t('入库') : t('出库');
                $item['total_amount'] = $item['num'] * $item['price'];
            }
            
            return json(['code' => 0, 'msg' => t('查询成功'), 'count' => $count, 'data' => $list]);
        } else {
            // 直接获取所有数据用于打印
            $reportData = Db::name('barcode_stock_log')
                ->where($where)
                ->field('id, ordernum, pro_name, gg_name, num, type, price, total_amount, createtime, remark')
                ->order('createtime desc')
                ->select()->toArray();
            
            // 格式化数据
            foreach ($reportData as &$item) {
                $item['createtime'] = date('Y-m-d H:i:s', $item['createtime']);
                $item['type_text'] = $item['type'] == 'in' ? t('入库') : t('出库');
                $item['total_amount'] = $item['num'] * $item['price'];
            }
        }
        
        $title = t('打印报表');
        View::assign('title', $title);
        View::assign('type', $type);
        View::assign('start_date', $start_date);
        View::assign('end_date', $end_date);
        View::assign('proid', $proid);
        View::assign('products', $products);
        View::assign('reportData', $reportData);
        
        return View::fetch('print_report');
    }
    
    // 出库记录列表
    public function outbound_log()
    {
        // 检测是否是表格请求
        $isTableRequest = input('param.page/d', 0) > 0 && input('param.limit/d', 0) > 0;
        
        if ($isTableRequest) {
            try {
                // 表格请求处理
                $page = max(1, input('param.page/d', 1));
                $limit = max(10, input('param.limit/d', 10));
                $proname = input('param.proname/s', '');
                $start_date = input('param.start_date/s', '');
                $end_date = input('param.end_date/s', '');
                
                // 使用固定值1作为aid
                $aid = 1;
                
                // 构建查询条件
                $where = [
                    ['aid', '=', $aid],
                    ['type', '=', 'out']
                ];
                
                if ($proname) {
                    $where[] = ['pro_name', 'like', '%' . $proname . '%'];
                }
                
                // 处理日期范围，默认使用当天日期
                $today = date('Y-m-d');
                $start_date = $start_date ?: $today;
                $end_date = $end_date ?: $today;
                
                $where[] = ['createtime', '>=', strtotime($start_date)];
                $where[] = ['createtime', '<=', strtotime($end_date) + 86399];
                
                // 查询数据
                $count = Db::name('barcode_stock_log')
                    ->where($where)
                    ->count();
                
                $list = Db::name('barcode_stock_log')
                    ->where($where)
                    ->field('id, ordernum, pro_name, gg_name, num, type, price, total_amount, createtime, remark')
                    ->order('createtime desc')
                    ->page($page, $limit)
                    ->select()->toArray();
                
                // 格式化数据
                foreach ($list as &$item) {
                    $item['num'] = abs(intval($item['num']));
                    $item['price'] = floatval($item['price']);
                    $item['total_amount'] = floatval($item['total_amount']);
                    $item['createtime'] = date('Y-m-d H:i:s', $item['createtime']);
                }
                
                // 返回JSON响应
                return json([
                    'code' => 0,
                    'msg' => '查询成功',
                    'count' => $count,
                    'data' => $list
                ]);
            } catch (Exception $e) {
                // 错误处理
                return json([
                    'code' => 1,
                    'msg' => '查询失败：' . $e->getMessage(),
                    'count' => 0,
                    'data' => []
                ]);
            }
        } else {
            // 非表格请求，使用模板引擎渲染页面
            $title = t('出库记录');
            View::assign('title', $title);
            return View::fetch('outbound_log');
        }
    }
    
    // ====================== 经销商管理 ======================
    
    // 经销商列表
    public function dealer_list()
    {
        if (Request::isAjax()) {
            $page = input('param.page/d', 1);
            $limit = input('param.limit/d', 10);
            $dealer_name = input('param.dealer_name/s', '');
            
            $where = [];
            if ($dealer_name) {
                $where[] = ['dealer_name', 'like', '%' . $dealer_name . '%'];
            }
            
            $count = Db::name('barcode_dealer')->where($where)->count();
            
            $list = Db::name('barcode_dealer')
                ->where($where)
                ->order('id desc')
                ->page($page, $limit)
                ->select()->toArray();
            
            return json(['code' => 0, 'msg' => t('查询成功'), 'count' => $count, 'data' => $list]);
        }
        
        $title = t('经销商管理');
        View::assign('title', $title);
        return View::fetch('dealer_list');
    }
    
    // 添加经销商
    public function add_dealer()
    {
        if (Request::isPost()) {
            $data = input('post.');
            
            // 验证数据
            $validate = Validate::rule([
                'dealer_name' => 'require|max:255',
                'contact_person' => 'require|max:100',
                'phone' => 'require|max:20',
                'address' => 'require|max:255'
            ]);
            
            if (!$validate->check($data)) {
                return json(['status' => 0, 'msg' => $validate->getError()]);
            }
            
            // 开启事务
            Db::startTrans();
            try {
                // 添加经销商
                $dealerData = [
                    'dealer_name' => $data['dealer_name'],
                    'contact_person' => $data['contact_person'],
                    'phone' => $data['phone'],
                    'address' => $data['address'],
                    'created_at' => time(),
                    'updated_at' => time()
                ];
                
                $dealerId = Db::name('barcode_dealer')->insertGetId($dealerData);
                
                // 添加默认主店
                $storeData = [
                    'dealer_id' => $dealerId,
                    'store_name' => $data['dealer_name'] . ' - 主店',
                    'contact_person' => $data['contact_person'],
                    'phone' => $data['phone'],
                    'address' => $data['address'],
                    'is_main' => 1,
                    'created_at' => time(),
                    'updated_at' => time()
                ];
                
                Db::name('barcode_store')->insert($storeData);
                
                // 提交事务
                Db::commit();
                
                return json(['status' => 1, 'msg' => t('添加成功'), 'url' => url('BarcodeInventory/dealer_list')]);
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return json(['status' => 0, 'msg' => t('添加失败：') . $e->getMessage()]);
            }
        }
        
        $title = t('添加经销商');
        View::assign('title', $title);
        return View::fetch('dealer_edit');
    }
    
    // 编辑经销商
    public function edit_dealer()
    {
        $id = input('param.id/d', 0);
        
        if (Request::isPost()) {
            $data = input('post.');
            
            // 验证数据
            $validate = Validate::rule([
                'id' => 'require|number|gt:0',
                'dealer_name' => 'require|max:255',
                'contact_person' => 'require|max:100',
                'phone' => 'require|max:20',
                'address' => 'require|max:255'
            ]);
            
            if (!$validate->check($data)) {
                return json(['status' => 0, 'msg' => $validate->getError()]);
            }
            
            // 更新经销商信息
            $updateData = [
                'dealer_name' => $data['dealer_name'],
                'contact_person' => $data['contact_person'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'updated_at' => time()
            ];
            
            $result = Db::name('barcode_dealer')->where('id', $data['id'])->update($updateData);
            
            if ($result) {
                return json(['status' => 1, 'msg' => t('编辑成功'), 'url' => url('BarcodeInventory/dealer_list')]);
            } else {
                return json(['status' => 0, 'msg' => t('编辑失败')]);
            }
        }
        
        if (!$id) {
            showmsg(t('参数错误'));
        }
        
        $info = Db::name('barcode_dealer')->where('id', $id)->find();
        if (!$info) {
            showmsg(t('经销商不存在'));
        }
        
        $title = t('编辑经销商');
        View::assign('title', $title);
        View::assign('info', $info);
        return View::fetch('dealer_edit');
    }
    
    // 删除经销商
    public function del_dealer()
    {
        $id = input('param.id/d', 0);
        
        if (empty($id)) {
            return json(['status' => 0, 'msg' => t('参数错误')]);
        }
        
        try {
            // 开启事务
            Db::startTrans();
            
            // 删除经销商
            $result = Db::name('barcode_dealer')->where('id', $id)->delete();
            
            // 提交事务
            Db::commit();
            
            if ($result) {
                return json(['status' => 1, 'msg' => t('删除成功')]);
            } else {
                return json(['status' => 0, 'msg' => t('删除失败')]);
            }
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return json(['status' => 0, 'msg' => t('删除失败：') . $e->getMessage()]);
        }
    }
    
    // 获取经销商信息
    public function get_dealer_info()
    {
        $id = input('param.id/d', 0);
        
        if (empty($id)) {
            return json(['status' => 0, 'msg' => t('参数错误')]);
        }
        
        $info = Db::name('barcode_dealer')->where('id', $id)->find();
        
        if ($info) {
            return json(['status' => 1, 'data' => $info]);
        } else {
            return json(['status' => 0, 'msg' => t('经销商不存在')]);
        }
    }
    
    // ====================== 门店管理 ======================
    
    // 门店列表
    public function store_list()
    {
        if (Request::isAjax()) {
            $page = input('param.page/d', 1);
            $limit = input('param.limit/d', 10);
            $dealer_id = input('param.dealer_id/d', 0);
            $store_name = input('param.store_name/s', '');
            
            $where = [];
            if ($dealer_id) {
                $where[] = ['dealer_id', '=', $dealer_id];
            }
            if ($store_name) {
                $where[] = ['store_name', 'like', '%' . $store_name . '%'];
            }
            
            $count = Db::name('barcode_store')->where($where)->count();
            
            $list = Db::name('barcode_store')
                ->where($where)
                ->order('is_main desc, id desc')
                ->page($page, $limit)
                ->select()->toArray();
            
            // 获取经销商名称
            $dealerIds = array_column($list, 'dealer_id');
            if (!empty($dealerIds)) {
                $dealers = Db::name('barcode_dealer')->where('id', 'in', $dealerIds)->column('dealer_name', 'id');
                foreach ($list as &$item) {
                    $item['dealer_name'] = $dealers[$item['dealer_id']] ?? '';
                    $item['is_main_text'] = $item['is_main'] ? t('是') : t('否');
                }
            }
            
            return json(['code' => 0, 'msg' => t('查询成功'), 'count' => $count, 'data' => $list]);
        }
        
        $title = t('门店管理');
        
        // 获取经销商列表
        $dealers = Db::name('barcode_dealer')->order('id desc')->select()->toArray();
        
        View::assign('title', $title);
        View::assign('dealers', $dealers);
        return View::fetch('store_list');
    }
    
    // 添加门店
    public function add_store()
    {
        if (Request::isPost()) {
            $data = input('post.');
            
            // 验证数据
            $validate = Validate::rule([
                'dealer_id' => 'require|number|gt:0',
                'store_name' => 'require|max:255',
                'contact_person' => 'require|max:100',
                'phone' => 'require|max:20',
                'address' => 'require|max:255'
            ]);
            
            if (!$validate->check($data)) {
                return json(['status' => 0, 'msg' => $validate->getError()]);
            }
            
            $storeData = [
                'dealer_id' => $data['dealer_id'],
                'store_name' => $data['store_name'],
                'contact_person' => $data['contact_person'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'is_main' => isset($data['is_main']) ? 1 : 0,
                'created_at' => time(),
                'updated_at' => time()
            ];
            
            // 开启事务
            Db::startTrans();
            try {
                // 如果设置为主店，将其他门店设为非主店
                if ($storeData['is_main']) {
                    Db::name('barcode_store')->where('dealer_id', $data['dealer_id'])->update(['is_main' => 0]);
                }
                
                // 添加门店
                $result = Db::name('barcode_store')->insert($storeData);
                
                // 提交事务
                Db::commit();
                
                if ($result) {
                    return json(['status' => 1, 'msg' => t('添加成功'), 'url' => url('BarcodeInventory/store_list')]);
                } else {
                    return json(['status' => 0, 'msg' => t('添加失败')]);
                }
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return json(['status' => 0, 'msg' => t('添加失败：') . $e->getMessage()]);
            }
        }
        
        $title = t('添加门店');
        
        // 获取经销商列表
        $dealers = Db::name('barcode_dealer')->order('id desc')->select()->toArray();
        
        View::assign('title', $title);
        View::assign('dealers', $dealers);
        return View::fetch('store_edit');
    }
    
    // 编辑门店
    public function edit_store()
    {
        $id = input('param.id/d', 0);
        
        if (Request::isPost()) {
            $data = input('post.');
            
            // 验证数据
            $validate = Validate::rule([
                'id' => 'require|number|gt:0',
                'dealer_id' => 'require|number|gt:0',
                'store_name' => 'require|max:255',
                'contact_person' => 'require|max:100',
                'phone' => 'require|max:20',
                'address' => 'require|max:255'
            ]);
            
            if (!$validate->check($data)) {
                return json(['status' => 0, 'msg' => $validate->getError()]);
            }
            
            $storeData = [
                'dealer_id' => $data['dealer_id'],
                'store_name' => $data['store_name'],
                'contact_person' => $data['contact_person'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'is_main' => isset($data['is_main']) ? 1 : 0,
                'updated_at' => time()
            ];
            
            // 开启事务
            Db::startTrans();
            try {
                // 如果设置为主店，将其他门店设为非主店
                if ($storeData['is_main']) {
                    Db::name('barcode_store')->where('dealer_id', $data['dealer_id'])->update(['is_main' => 0]);
                }
                
                // 更新门店信息
                $result = Db::name('barcode_store')->where('id', $data['id'])->update($storeData);
                
                // 提交事务
                Db::commit();
                
                if ($result) {
                    return json(['status' => 1, 'msg' => t('编辑成功'), 'url' => url('BarcodeInventory/store_list')]);
                } else {
                    return json(['status' => 0, 'msg' => t('编辑失败')]);
                }
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return json(['status' => 0, 'msg' => t('编辑失败：') . $e->getMessage()]);
            }
        }
        
        if (!$id) {
            showmsg(t('参数错误'));
        }
        
        $info = Db::name('barcode_store')->where('id', $id)->find();
        if (!$info) {
            showmsg(t('门店不存在'));
        }
        
        $title = t('编辑门店');
        
        // 获取经销商列表
        $dealers = Db::name('barcode_dealer')->order('id desc')->select()->toArray();
        
        View::assign('title', $title);
        View::assign('info', $info);
        View::assign('dealers', $dealers);
        return View::fetch('store_edit');
    }
    
    // 删除门店
    public function del_store()
    {
        $id = input('param.id/d', 0);
        
        if (empty($id)) {
            return json(['status' => 0, 'msg' => t('参数错误')]);
        }
        
        try {
            // 删除门店
            $result = Db::name('barcode_store')->where('id', $id)->delete();
            
            if ($result) {
                return json(['status' => 1, 'msg' => t('删除成功')]);
            } else {
                return json(['status' => 0, 'msg' => t('删除失败')]);
            }
        } catch (\Exception $e) {
            return json(['status' => 0, 'msg' => t('删除失败：') . $e->getMessage()]);
        }
    }
    
    // 设置主店
    public function set_main_store()
    {
        $id = input('param.id/d', 0);
        
        if (empty($id)) {
            return json(['status' => 0, 'msg' => t('参数错误')]);
        }
        
        // 获取门店信息
        $store = Db::name('barcode_store')->where('id', $id)->find();
        if (!$store) {
            return json(['status' => 0, 'msg' => t('门店不存在')]);
        }
        
        try {
            // 开启事务
            Db::startTrans();
            
            // 将该经销商的所有门店设为非主店
            Db::name('barcode_store')->where('dealer_id', $store['dealer_id'])->update(['is_main' => 0]);
            
            // 将指定门店设为主店
            Db::name('barcode_store')->where('id', $id)->update(['is_main' => 1]);
            
            // 提交事务
            Db::commit();
            
            return json(['status' => 1, 'msg' => t('设置成功')]);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return json(['status' => 0, 'msg' => t('设置失败：') . $e->getMessage()]);
        }
    }
    
    // 获取指定经销商的门店列表
    public function get_dealer_stores()
    {
        $dealer_id = input('param.dealer_id/d', 0);
        
        if (empty($dealer_id)) {
            return json(['status' => 0, 'msg' => t('参数错误')]);
        }
        
        $stores = Db::name('barcode_store')
            ->where('dealer_id', $dealer_id)
            ->order('is_main desc, id desc')
            ->select()->toArray();
        
        return json(['status' => 1, 'data' => $stores]);
    }
    
    // 获取商品规格
    public function get_guige()
    {
        try {
            $proid = input('param.proid/d', 0);
            
            if (empty($proid)) {
                return json(['status' => 0, 'msg' => t('参数错误')]);
            }
            
            // 直接从数据库获取，不使用缓存，避免缓存问题
            $guige = Db::name('barcode_inventory_guige')
                ->where('proid', $proid)
                ->field('id, name, stock, sell_price as price, pic')
                ->select()->toArray();
            
            return json(['status' => 1, 'data' => $guige]);
        } catch (Exception $e) {
            // 不记录日志，避免日志表字段问题
            return json(['status' => 1, 'data' => []]);
        }
    }
    
    // 获取商品主图
    public function getProductPic()
    {
        try {
            $proid = input('param.proid/d', 0);
            
            if (empty($proid)) {
                return json(['status' => 0, 'msg' => t('参数错误')]);
            }
            
            // 直接从数据库获取商品主图
            $product = Db::name('barcode_inventory')
                ->where('id', $proid)
                ->field('pic')
                ->find();
            
            if ($product) {
                return json(['status' => 1, 'data' => $product['pic']]);
            } else {
                return json(['status' => 0, 'msg' => t('商品不存在')]);
            }
        } catch (Exception $e) {
            return json(['status' => 0, 'msg' => t('获取商品主图失败')]);
        }
    }
    
    // 商品编辑
    public function edit()
    {
        $id = input('param.id/d', 0);
        $info = [];
        $gglist = [];
        
        // 获取aid，使用固定值1，与index和save_product方法保持一致
        $aid = 1;
        
        if ($id) {
            $info = Db::name('barcode_inventory')->where('aid', $aid)->where('id', $id)->find();
            if (!$info) {
                showmsg('商品不存在');
            }
            
            // 获取商品规格
            $gglist = Db::name('barcode_inventory_guige')->where('aid', $aid)->where('proid', $id)->select()->toArray();
        }
        
        // 获取当前用户信息
        $uid = session('uid');
        $is_admin = false;
        $user_supplier_id = 0;
        $user_supplier_name = '';
        
        if ($uid) {
            // 获取用户信息
            $user = Db::name('admin_user')->where('id', $uid)->find();
            if ($user) {
                // 判断是否为管理员
                $is_admin = $user['isadmin'] > 0;
                
                if (!$is_admin) {
                    // 非管理员，从ddwx_member表获取supplier_id
                    $member = Db::name('ddwx_member')->where('uid', $uid)->find();
                    if ($member && !empty($member['supplier_id'])) {
                        $user_supplier_id = $member['supplier_id'];
                        // 获取供应商名称
                        $supplier = Db::name('product_supplier')->where('id', $user_supplier_id)->where('aid', $aid)->find();
                        if ($supplier) {
                            $user_supplier_name = $supplier['supplier_name'];
                        }
                    }
                }
            }
        }
        
        // 获取供应商列表（仅管理员需要）
        $suppliers = [];
        if (getcustom('product_supplier') && $is_admin) {
            $suppliers = Db::name('product_supplier')->where('aid', $aid)->select()->toArray();
        }
        
        View::assign('info', $info);
        View::assign('gglist', $gglist);
        View::assign('suppliers', $suppliers);
        View::assign('is_admin', $is_admin);
        View::assign('user_supplier_id', $user_supplier_id);
        View::assign('user_supplier_name', $user_supplier_name);
        View::assign('title', $id ? t('编辑商品') : t('添加商品'));
        
        return View::fetch();
    }
    
    // 保存商品
    public function save_product()
    {
        // 直接返回JSON响应，避免框架错误页面
        header('Content-Type: application/json');
        
        try {
            $data = input('post.');
            $id = $data['id'] ?? 0;
            
            // 获取aid和bid，确保与index方法使用相同的默认值
            $aid = 1; // 与index方法保持一致，使用固定值1
            $bid = session('bid') ?? 0;
            
            // 记录请求数据
            Db::name('barcode_inventory_log')->insert([
                'aid' => $aid,
                'bid' => $bid,
                'type' => 'save_product',
                'request_data' => json_encode($data),
                'create_time' => time()
            ]);
            
            // 验证基本信息
            $validate = Validate::rule([
                'name' => 'require|max:200',
                'pic' => 'max:200',
            ]);
            
            if (!$validate->check($data)) {
                // 记录验证失败
                Db::name('barcode_inventory_log')->insert([
                    'aid' => $aid,
                    'bid' => $bid,
                    'type' => 'save_product_error',
                    'request_data' => json_encode($data),
                    'error_msg' => $validate->getError(),
                    'create_time' => time()
                ]);
                return json(['status' => 0, 'msg' => $validate->getError()]);
            }
        
        // 处理商品数据
        $productData = [
            'name' => $data['name'],
            'english_name' => $data['english_name'] ?? '',
            'supplier_id' => $data['supplier_id'] ?? 0,
            'pic' => $data['pic'] ?? '',
            'production_date' => $data['production_date'] ?? null,
            'warehouse_date' => $data['warehouse_date'] ?? null,
            'update_time' => time(),
        ];
        

        
        // 保存商品到独立的库存表
        if ($id) {
            // 编辑商品
            Db::name('barcode_inventory')->where('aid', $aid)->where('id', $id)->update($productData);
        } else {
            // 添加商品
            $productData['aid'] = $aid;
            $productData['bid'] = $bid;
            $productData['createtime'] = time();
            $productData['stock'] = 0; // 初始库存为0
            $id = Db::name('barcode_inventory')->insertGetId($productData);
        }
        
        // 处理商品规格
        if (!empty($data['option'])) {
            // 先获取现有规格ID列表
            $existingGgIds = [];
            if ($id) {
                $existingGgIds = Db::name('barcode_inventory_guige')
                    ->where('proid', $id)
                    ->where('aid', $aid)
                    ->column('id');
            }
            
            // 验证规格数据
            $processedGgIds = [];
            $totalStock = 0;
            
            foreach ($data['option'] as $ks => $v) {
                if (empty($v['name'])) continue;
                
                $guigeValidate = Validate::rule([
                    'name' => 'require|max:100',
                    'stock' => 'number|egt:0',
                    'cost_price' => 'float|egt:0',
                    'market_price' => 'float|egt:0',
                    'sell_price' => 'float|egt:0',
                    'barcode' => 'max:100',
                    'code_type' => 'in:1,2'
                ]);
                
                if (!$guigeValidate->check($v)) {
                    return json(['status' => 0, 'msg' => t('规格数据验证失败') . ': ' . $guigeValidate->getError()]);
                }
                
                $ggStock = $v['stock'] ?? 0;
                $totalStock += $ggStock;
                
                $guigeData = [
                    'name' => $v['name'],
                    'proid' => $id,
                    'stock' => $ggStock,
                    'cost_price' => $v['cost_price'] ?? 0,
                    'market_price' => $v['market_price'] ?? 0,
                    'sell_price' => $v['sell_price'] ?? 0,
                    'barcode' => $v['barcode'] ?? '',
                    'code_type' => $v['code_type'] ?? 1, // 1: 条码, 2: 二维码
                    'pic' => $v['pic'] ?? '', // 规格图片
                    'update_time' => time(),
                ];
                
                if (!empty($v['id'])) {
                    // 编辑规格
                    Db::name('barcode_inventory_guige')->where('aid', $aid)->where('id', $v['id'])->update($guigeData);
                    $processedGgIds[] = $v['id'];
                } else {
                    // 添加规格
                    $guigeData['aid'] = $aid;
                    $guigeData['createtime'] = time();
                    $insertedId = Db::name('barcode_inventory_guige')->insertGetId($guigeData);
                    $processedGgIds[] = $insertedId;
                }
            }
            
            // 更新商品总库存
            Db::name('barcode_inventory')
                ->where('aid', $aid)
                ->where('id', $id)
                ->update(['stock' => $totalStock]);
            
            // 删除未处理的旧规格
            $ggIdsToDelete = array_diff($existingGgIds, $processedGgIds);
            if (!empty($ggIdsToDelete)) {
                Db::name('barcode_inventory_guige')
                    ->where('id', 'in', $ggIdsToDelete)
                    ->where('aid', $aid)
                    ->delete();
            }
        } else {
            // 没有规格，清空旧规格并将总库存设为0
            if ($id) {
                Db::name('barcode_inventory_guige')
                    ->where('proid', $id)
                    ->where('aid', $aid)
                    ->delete();
                    
                Db::name('barcode_inventory')
                    ->where('aid', $aid)
                    ->where('id', $id)
                    ->update(['stock' => 0]);
            }
        }
        
        // 清除相关缓存
        Cache::delete('barcode_inventory:product_list:' . $aid);
        if (!empty($id)) {
            Cache::delete('barcode_inventory:guige:' . $id);
        }
        
        // 生成返回URL并记录
        $returnUrl = '/?s=/BarcodeInventory/index';
        
        // 记录成功日志，包含生成的URL
        Db::name('barcode_inventory_log')->insert([
            'aid' => $aid,
            'bid' => $bid,
            'type' => 'save_product_success',
            'request_data' => json_encode($data),
            'product_id' => $id,
            'error_msg' => 'Generated URL: ' . $returnUrl,
            'create_time' => time()
        ]);
        
        // 直接返回列表页面的URL字符串，避免重定向问题
        return json(['status' => 1, 'msg' => $id ? t('编辑成功') : t('添加成功'), 'url' => $returnUrl]);
        } catch (\Exception $e) {
            // 记录异常日志
            Db::name('barcode_inventory_log')->insert([
                'aid' => $aid,
                'bid' => $bid,
                'type' => 'save_product_exception',
                'request_data' => json_encode($data),
                'error_msg' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'create_time' => time()
            ]);
            
            return json(['status' => 0, 'msg' => t('保存失败：') . $e->getMessage()]);
        }
    }
    
    // 删除商品
    public function del_product()
    {
        header('Content-Type: application/json');
        
        $id = input('post.id/d', 0);
        $aid = 1; // 与其他方法保持一致，使用固定值1
        
        if (empty($id)) {
            return json(['status' => 0, 'msg' => t('参数错误')]);
        }
        
        try {
            // 开启事务
            Db::startTrans();
            
            // 删除商品规格
            Db::name('barcode_inventory_guige')
                ->where('proid', $id)
                ->where('aid', $aid)
                ->delete();
            
            // 删除商品
            $result = Db::name('barcode_inventory')
                ->where('id', $id)
                ->where('aid', $aid)
                ->delete();
            
            if ($result) {
                // 提交事务
                Db::commit();
                return json(['status' => 1, 'msg' => t('删除成功')]);
            } else {
                // 回滚事务
                Db::rollback();
                return json(['status' => 0, 'msg' => t('删除失败')]);
            }
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return json(['status' => 0, 'msg' => t('删除失败：') . $e->getMessage()]);
        }
    }
    
    // ====================== 公共方法 ======================
    
    /**
     * 获取商品列表（用于选择）
     * @return array
     */
    protected function getProductList()
    {
        // 获取aid，与其他方法保持一致，使用固定值1
        $aid = 1;
        
        $cacheKey = 'barcode_inventory:product_list:' . $aid;
        $cacheExpire = 3600; // 缓存1小时
        
        // 尝试从缓存获取
        $products = Cache::get($cacheKey);
        
        if (empty($products)) {
            // 缓存不存在，从独立的库存表获取
            $products = Db::name('barcode_inventory')
                ->where('aid', $aid)
                ->field('id, name, english_name, supplier_id')
                ->select()->toArray();
            
            // 如果有商品，获取供应商信息
            if (!empty($products)) {
                // 获取所有供应商ID
                $supplierIds = array_column($products, 'supplier_id');
                $supplierIds = array_unique(array_filter($supplierIds));
                
                // 批量查询供应商信息
                $suppliers = [];
                if (!empty($supplierIds)) {
                    $suppliers = Db::name('product_supplier')
                        ->where('id', 'in', $supplierIds)
                        ->field('id, supplier_name')
                        ->select()->toArray();
                    
                    // 构建供应商ID到名称的映射
                    $supplierMap = [];
                    foreach ($suppliers as $supplier) {
                        $supplierMap[$supplier['id']] = $supplier['supplier_name'];
                    }
                    
                    // 添加供应商名称到商品列表
                    foreach ($products as &$product) {
                        $product['supplier_name'] = $supplierMap[$product['supplier_id']] ?? '';
                    }
                }
            }
            
            // 存入缓存
            Cache::set($cacheKey, $products, $cacheExpire);
        }
        
        return $products;
    }
    
    /**
     * 获取商品信息
     * @param int $proid 商品ID
     * @return array|object 商品信息或错误数组
     */
    protected function getProductInfo($proid)
    {
        // 获取aid，与其他方法保持一致，使用固定值1
        $aid = 1;
        
        $product = Db::name('barcode_inventory')
            ->where('id', $proid)
            ->where('aid', $aid)
            ->find();
            
        if (!$product) {
            return ['error' => t('商品不存在')];
        }
        
        return $product;
    }
    
    /**
     * 获取规格信息
     * @param int $ggid 规格ID
     * @param int $proid 商品ID
     * @return array|object 规格信息或错误数组
     */
    protected function getGuigeInfo($ggid, $proid)
    {
        // 获取aid，与其他方法保持一致，使用固定值1
        $aid = 1;
        
        $guige = Db::name('barcode_inventory_guige')
            ->where('id', $ggid)
            ->where('proid', $proid)
            ->where('aid', $aid)
            ->field('id, proid, name, stock, sell_price, cost_price, market_price')
            ->find();
            
        if (!$guige) {
            return ['error' => t('规格不存在')];
        }
        
        return $guige;
    }
    
    /**
     * 记录库存变动
     * @param array $product 商品信息
     * @param array $guige 规格信息
     * @param int $num 变动数量
     * @param string $ordernum 订单号
     * @param string $type 操作类型（in:入库, out:出库）
     * @param float $price 成本价格
     */
    protected function recordStockChange($product, $guige, $num, $ordernum, $type, $price = 0, $outboundType = 0, $dealerId = 0)
    {
        // 获取aid，与其他方法保持一致，使用固定值1
        $aid = 1;
        
        try {
            // 计算变动后库存
            $afterStock = $guige['stock'] + $num;
            
            // 计算总金额
            $totalAmount = $price * $num;
            
            // 构建日志数据，确保包含所有必填字段（根据数据库结构，uid和uname也是必填字段）
            $logData = [
                'aid' => $aid,
                'bid' => isset($product['bid']) ? $product['bid'] : 0,
                'ordernum' => $ordernum,
                'proid' => $product['id'],
                'ggid' => $guige['id'],
                'pro_name' => $product['name'],
                'gg_name' => $guige['name'],
                'num' => $num,
                'type' => $type,
                'price' => $price,
                'total_amount' => $totalAmount,
                'stock' => $afterStock,
                'uid' => 1, // 使用固定值1作为默认uid，避免Field 'uid' doesn't have a default value错误
                'uname' => 'admin', // 使用固定值'admin'作为默认uname，避免Field 'uname' doesn't have a default value错误
                'createtime' => time()
            ];
            
            // 添加其他可选字段
            $remark = ($type == 'out' ? '出库' : '入库') . '操作';
            $remark .= '，订单号：' . $ordernum;
            if ($outboundType > 0) {
                $remark .= '，出库类型：' . ($outboundType == 1 ? '本站商城' : '经销商');
                $logData['outbound_type'] = $outboundType;
            } else {
                $logData['outbound_type'] = 0;
            }
            if ($dealerId > 0) {
                $remark .= '，经销商ID：' . $dealerId;
                $logData['dealer_id'] = $dealerId;
            } else {
                $logData['dealer_id'] = 0;
            }
            $logData['remark'] = $remark;
            
            // 尝试插入日志
            Db::name('barcode_stock_log')->insert($logData);
        } catch (Exception $e) {
            // 不抛出异常，避免影响主流程
            // 可以考虑写入文件日志，但不使用数据库日志
        }
    }
    
    /**
     * 更新商品和规格库存
     * @param int $proid 商品ID
     * @param int|string $ggid 规格ID（可以是字符串'default'）
     * @param int $num 变动数量
     */
    protected function updateStock($proid, $ggid, $num)
    {
        // 确保num是整数，防止SQL注入
        $num = intval($num);
        
        // 获取aid，与其他方法保持一致，使用固定值1
        $aid = 1;
        
        try {
            // 只有当ggid不是default且大于0时，才更新规格库存
            if ($ggid && $ggid != 'default' && $ggid > 0) {
                // 更新规格库存
                Db::name('barcode_inventory_guige')
                    ->where('id', $ggid)
                    ->where('aid', $aid)
                    ->update(['stock' => Db::raw('stock + ' . $num)]);
            }        
            
            // 更新商品总库存
            $totalStock = Db::name('barcode_inventory_guige')
                ->where('proid', $proid)
                ->where('aid', $aid)
                ->sum('stock');
            
            Db::name('barcode_inventory')
                ->where('id', $proid)
                ->where('aid', $aid)
                ->update(['stock' => $totalStock]);
        } catch (Exception $e) {
            throw $e; // 重新抛出异常，以便上层处理
        }
    }
}