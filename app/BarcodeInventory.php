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
                'ggid' => 'require|number|gt:0',
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
            
            // 获取商品和规格信息
            $product = $this->getProductInfo($proid);
            $guige = $this->getGuigeInfo($ggid, $proid);
            
            if (is_array($product) && isset($product['error'])) {
                return json(['status' => 0, 'msg' => $product['error']]);
            }
            
            if (is_array($guige) && isset($guige['error'])) {
                return json(['status' => 0, 'msg' => $guige['error']]);
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
        if (Request::isPost()) {
            $data = input('post.');
            
            // 验证参数
            $validate = Validate::rule([
                'proid' => 'require|number|gt:0',
                'ggid' => 'require|number|gt:0',
                'num' => 'require|number|gt:0'
            ]);
            
            if (!$validate->check($data)) {
                return json(['status' => 0, 'msg' => $validate->getError()]);
            }
            
            $proid = $data['proid'];
            $ggid = $data['ggid'];
            $num = $data['num'];
            
            // 获取商品和规格信息
            $product = $this->getProductInfo($proid);
            $guige = $this->getGuigeInfo($ggid, $proid);
            
            if (is_array($product) && isset($product['error'])) {
                return json(['status' => 0, 'msg' => $product['error']]);
            }
            
            if (is_array($guige) && isset($guige['error'])) {
                return json(['status' => 0, 'msg' => $guige['error']]);
            }
            
            // 检查库存是否充足
            if ($guige['stock'] < $num) {
                return json(['status' => 0, 'msg' => t('库存不足')]);
            }
            
            // 生成出库单号
            $ordernum = 'OUT' . date('YmdHis') . rand(100, 999);
            
            // 记录库存变动（出库为负数）
            $this->recordStockChange($product, $guige, -$num, $ordernum, 'out');
            
            // 更新商品和规格库存
            $this->updateStock($proid, $ggid, -$num);
            
            // 清除相关缓存
            Cache::delete('barcode_inventory:product_list:' . aid);
            Cache::delete('barcode_inventory:guige:' . $proid);
            
            return json(['status' => 1, 'msg' => t('出库成功'), 'url' => true]);
        }
        
        // 获取商品列表用于选择
        $products = $this->getProductList();
        
        $title = t('出库管理');
        View::assign('title', $title);
        View::assign('products', $products);
        return View::fetch();
    }

    // 库存盘点记录列表
    public function check()
    {
        if (Request::isAjax()) {
            $page = input('param.page/d', 1);
            $limit = input('param.limit/d', 10);
            $check_name = input('param.check_name/s', '');
            $status = input('param.status/d', -1);
            
            $where = [['aid', '=', aid]];
            if ($check_name) {
                $where[] = ['check_name', 'like', '%' . $check_name . '%'];
            }
            if ($status != -1) {
                $where[] = ['status', '=', $status];
            }
            
            $count = Db::name('shop_inventory_check')
                ->where($where)
                ->count();
            
            $list = Db::name('shop_inventory_check')
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
            
            // 获取待盘点的商品规格
            $products = Db::name('shop_product')
                ->alias('sp')
                ->join('shop_guige sg', 'sp.id = sg.proid', 'left')
                ->where('sp.aid', aid)
                ->field('sp.id as proid, sp.name as pro_name, sg.id as ggid, sg.name as gg_name, sg.barcode, sg.stock as system_stock, sg.price')
                ->select()->toArray();
            
            // 开启事务
            Db::startTrans();
            try {
                // 创建盘点记录
                $checkId = Db::name('shop_inventory_check')->insertGetId([
                    'aid' => aid,
                    'bid' => bid,
                    'check_sn' => $check_sn,
                    'check_name' => $data['check_name'],
                    'check_time' => time(),
                    'check_user' => $this->un,
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
                            'aid' => aid,
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
                    Db::name('shop_inventory_check_detail')->insertAll($detailData);
                }
                
                // 提交事务
                Db::commit();
                
                return json(['status' => 1, 'msg' => t('盘点任务创建成功'), 'url' => url('BarcodeInventory/check_detail', ['id' => $checkId])]);
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
        
        // 获取盘点记录
        $checkInfo = Db::name('shop_inventory_check')
            ->where('id', $id)
            ->where('aid', aid)
            ->find();
            
        if (!$checkInfo) {
            showmsg(t('盘点记录不存在'));
        }
        
        // 获取盘点详情
        if (Request::isAjax()) {
            $page = input('param.page/d', 1);
            $limit = input('param.limit/d', 20);
            $pro_name = input('param.pro_name/s', '');
            
            $where = [['aid', '=', aid], ['check_id', '=', $id]];
            if ($pro_name) {
                $where[] = ['pro_name', 'like', '%' . $pro_name . '%'];
            }
            
            $count = Db::name('shop_inventory_check_detail')
                ->where($where)
                ->count();
            
            $list = Db::name('shop_inventory_check_detail')
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
        
        // 获取盘点详情
        $detail = Db::name('shop_inventory_check_detail')
            ->where('id', $id)
            ->where('aid', aid)
            ->find();
            
        if (!$detail) {
            return json(['status' => 0, 'msg' => t('盘点详情不存在')]);
        }
        
        // 计算差异
        $diff_stock = $actual_stock - $detail['system_stock'];
        $diff_amount = $diff_stock * $detail['price'];
        
        // 更新盘点详情
        $result = Db::name('shop_inventory_check_detail')
            ->where('id', $id)
            ->update([
                'actual_stock' => $actual_stock,
                'diff_stock' => $diff_stock,
                'diff_amount' => $diff_amount
            ]);
        
        if ($result) {
            // 重新计算盘点记录的总差异
            $checkId = $detail['check_id'];
            $totalDiff = Db::name('shop_inventory_check_detail')
                ->where('check_id', $checkId)
                ->where('aid', aid)
                ->sum('diff_stock');
                
            $totalDiffAmount = Db::name('shop_inventory_check_detail')
                ->where('check_id', $checkId)
                ->where('aid', aid)
                ->sum('diff_amount');
                
            Db::name('shop_inventory_check')
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
        
        // 获取盘点记录
        $checkInfo = Db::name('shop_inventory_check')
            ->where('id', $id)
            ->where('aid', aid)
            ->where('status', 0) // 只能完成未完成的盘点
            ->find();
            
        if (!$checkInfo) {
            return json(['status' => 0, 'msg' => t('盘点记录不存在或已完成')]);
        }
        
        // 开启事务
        Db::startTrans();
        try {
            // 1. 更新盘点记录状态
            Db::name('shop_inventory_check')
                ->where('id', $id)
                ->update([
                    'status' => 1,
                    'updatetime' => time()
                ]);
            
            // 2. 更新商品规格的实际库存
            $details = Db::name('shop_inventory_check_detail')
                ->where('check_id', $id)
                ->where('aid', aid)
                ->select()->toArray();
                
            foreach ($details as $detail) {
                if ($detail['diff_stock'] != 0) {
                    // 更新规格库存
                    Db::name('shop_guige')
                        ->where('id', $detail['ggid'])
                        ->update(['stock' => $detail['actual_stock']]);
                    
                    // 更新商品总库存
                    $proStock = Db::name('shop_guige')
                        ->where('proid', $detail['proid'])
                        ->sum('stock');
                        
                    Db::name('shop_product')
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
        
        // 获取盘点记录
        $checkInfo = Db::name('shop_inventory_check')
            ->where('id', $id)
            ->where('aid', aid)
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
        $details = Db::name('shop_inventory_check_detail')
            ->where('check_id', $id)
            ->where('aid', aid)
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
        // 总库存商品数
        $totalProducts = Db::name('shop_product')
            ->where('aid', aid)
            ->count();
        
        // 总规格数
        $totalGuiges = Db::name('shop_guige')
            ->where('aid', aid)
            ->count();
        
        // 总库存数量
        $totalStock = Db::name('shop_guige')
            ->where('aid', aid)
            ->sum('stock');
        
        // 总库存金额
        $totalAmount = Db::name('shop_guige')
            ->where('aid', aid)
            ->sum(Db::raw('stock * price'));
        
        // 库存预警商品数
        $warningStock = Db::name('shop_guige')
            ->where('aid', aid)
            ->where('stock', '<', Db::raw('warn_stock'))
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
        $where = [['sg.aid', '=', aid]];
        
        if ($start_date && $end_date) {
            $where[] = ['sg.update_time', 'between', [strtotime($start_date), strtotime($end_date) + 86399]];
        }
        
        $list = Db::name('shop_guige')
            ->alias('sg')
            ->join('shop_product sp', 'sp.id = sg.proid')
            ->field('sp.id as product_id, sp.name as product_name, sg.id as guige_id, sg.name as guige_name, sg.stock, sg.price, sg.warn_stock')
            ->where($where)
            ->order('sp.id, sg.id')
            ->select()->toArray();
        
        return $list;
    }
    
    // 库存变动报表
    private function getInventoryMovementReport($start_date = '', $end_date = '')
    {
        $where = [['aid', '=', aid]];
        
        if ($start_date && $end_date) {
            $where[] = ['createtime', 'between', [strtotime($start_date), strtotime($end_date) + 86399]];
        }
        
        $list = Db::name('shop_inventory_log')
            ->alias('sil')
            ->join('shop_product sp', 'sp.id = sil.proid')
            ->join('shop_guige sg', 'sg.id = sil.ggid')
            ->field('sil.id, sil.type, sil.proid, sil.ggid, sp.name as product_name, sg.name as guige_name, sil.num, sil.price, sil.createtime, sil.remark')
            ->where($where)
            ->order('sil.createtime desc')
            ->limit(1000)
            ->select()->toArray();
        
        return $list;
    }

    // 系统设置
    public function settings()
    {
        $title = t('系统设置');
        View::assign('title', $title);
        return View::fetch();
    }
    
    // 获取商品规格
    public function get_guige()
    {
        $proid = input('param.proid/d', 0);
        
        if (empty($proid)) {
            return json(['status' => 0, 'msg' => t('参数错误')]);
        }
        
        // 获取aid，使用固定值1，与index和save_product方法保持一致
        $aid = 1;
        
        $cacheKey = 'barcode_inventory:guige:' . $proid;
        $cacheExpire = 3600; // 缓存1小时
        
        // 尝试从缓存获取
        $guige = Cache::get($cacheKey);
        
        if (empty($guige)) {
            // 缓存不存在，从独立的库存规格表获取
            $guige = Db::name('barcode_inventory_guige')
                ->where('proid', $proid)
                ->where('aid', $aid)
                ->field('id, name, stock, sell_price as price')
                ->select()->toArray();
            
            // 存入缓存
            Cache::set($cacheKey, $guige, $cacheExpire);
        }
        
        return json(['status' => 1, 'data' => $guige]);
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
        
        // 获取供应商列表
        $suppliers = [];
        if (getcustom('product_supplier')) {
            $suppliers = Db::name('product_supplier')->where('aid', $aid)->select()->toArray();
        }
        
        View::assign('info', $info);
        View::assign('gglist', $gglist);
        View::assign('suppliers', $suppliers);
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
                ->field('id, name')
                ->select()->toArray();
            
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
    protected function recordStockChange($product, $guige, $num, $ordernum, $type, $price = 0)
    {
        // 获取aid，与其他方法保持一致，使用固定值1
        $aid = 1;
        
        $stockData = [
            'aid' => $aid,
            'bid' => $product['bid'],
            'ordernum' => $ordernum,
            'proid' => $product['id'],
            'ggid' => $guige['id'],
            'stock' => $num,
            'afterstock' => $guige['stock'] + $num,
            'uid' => $this->uid,
            'uname' => $this->un,
            'createtime' => time(),
        ];
        
        // 入库时添加成本价和总价
        if ($type == 'in') {
            $stockData['totalprice'] = $price * $num;
            $stockData['cost_price'] = $price;
        }
        
        // 暂时使用现有表记录库存变动，后续可考虑迁移到独立表
        Db::name('shop_stock_order_goods')->insert($stockData);
    }
    
    /**
     * 更新商品和规格库存
     * @param int $proid 商品ID
     * @param int $ggid 规格ID
     * @param int $num 变动数量
     */
    protected function updateStock($proid, $ggid, $num)
    {
        // 确保num是整数，防止SQL注入
        $num = intval($num);
        
        // 获取aid，与其他方法保持一致，使用固定值1
        $aid = 1;
        
        // 更新规格库存
        Db::name('barcode_inventory_guige')
            ->where('id', $ggid)
            ->where('aid', $aid)
            ->update(['stock' => Db::raw('stock + ' . $num)]);
        
        // 更新商品总库存
        Db::name('barcode_inventory')
            ->where('id', $proid)
            ->where('aid', $aid)
            ->update(['stock' => Db::raw('stock + ' . $num)]);
    }
}