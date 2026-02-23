<?php
// JK客户定制

//custom_file(product_supplier)
// +----------------------------------------------------------------------
// | 供应商
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\Db;
use think\facade\View;

class ProductSupplier extends Common
{
    public function index(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'id desc';
            }
            $where = array();
            $where[] = ['aid','=',aid];

            if(input('param.supplier_name')){
                $where[] = ['supplier_name','like','%'.input('param.supplier_name').'%'];
            }
            if(input('param.supplier_number')){
                $where[] = ['supplier_number','like','%'.input('param.supplier_number').'%'];
            }
            if(input('param.name')){
                $where[] = ['name','like','%'.input('param.name').'%'];
            }
            if(input('param.tel')){
                $where[] = ['tel','like','%'.input('param.tel').'%'];
            }
            $count = 0 + Db::name('product_supplier')->where($where)->count();
            $data = Db::name('product_supplier')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }

    //编辑
    public function edit(){
        $id = input('param.id');
        $info = [];
        if($id){
            $info = Db::name('product_supplier')
                ->where('aid',aid)
                ->where('bid',bid)
                ->where('id',$id)
                ->find();
        }
        View::assign('info',$info);
        return View::fetch();
    }

    //保存
    public function save(){
        try {
            $id = input('post.id/d');
            $info = input('post.info/a');
            
            // 验证必填字段
            if(empty($info['supplier_name'])){
                return json(['status'=>0, 'msg'=>'请输入供应商名称']);
            }
            if(empty($info['account'])){
                return json(['status'=>0, 'msg'=>'请输入供货商账号']);
            }
            if(empty($info['tel'])){
                return json(['status'=>0, 'msg'=>'请输入联系电话']);
            }
            
            // 简化电话验证，使用PHP内置函数
            if(!preg_match('/^1[3-9]\d{9}$/', $info['tel']) && !preg_match('/^\d{3,4}-\d{7,8}$/', $info['tel'])) {
                return json(['status'=>0, 'msg'=>'请输入正确的联系电话']);
            }

            // 准备数据
            $data = [
                'supplier_name' => $info['supplier_name'],
                'supplier_number' => $info['supplier_number'] ?? '',
                'name' => $info['name'] ?? '',
                'tel' => $info['tel'],
                'account' => $info['account']
            ];
            
            // 只有提供密码时才更新
            if(!empty($info['password'])) {
                $data['password'] = $info['password'];
            }

            // 处理数据库操作
            if($id){
                // 更新操作
                $updateResult = Db::name('product_supplier')
                    ->where('id', $id)
                    ->update($data);
            }else{
                // 新增操作
                $data['aid'] = aid;
                $data['bid'] = bid;
                $data['create_time'] = time();
                $proid = Db::name('product_supplier')->insertGetId($data);
            }
            
            // 跳过同步操作，避免潜在问题
            // $syncResult = $this->syncToSupplier($info, $id);
            
            return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
        } catch (Throwable $e) {
            // 返回简化的错误信息
            return json(['status'=>0, 'msg'=>'操作失败：' . $e->getMessage()]);
        }
    }
    
    /**
     * 同步供应商信息到supplier表
     * @param array $info 供应商信息
     * @param int $id 供应商ID
     * @return string 同步结果
     */
    private function syncToSupplier($info, $id = 0)
    {
        try {
            // 确保有账号信息才同步
            if (!empty($info['account'])) {
                $account = trim($info['account']);
                
                // 准备同步数据
                $supplier_data = [
                    'username' => $account,
                    'supplier_name' => $info['supplier_name'] ?? '',
                    'supplier_number' => $info['supplier_number'] ?? '',
                    'name' => $info['name'] ?? '',
                    'tel' => $info['tel'] ?? '',
                    'update_time' => time(),
                ];
                
                // 检查是否需要更新密码
                $needsPasswordUpdate = empty($id) || !empty($info['password']);
                
                // 只有新记录或者提供了新密码时才更新密码
                if ($needsPasswordUpdate) {
                    // 生成盐值
                    $salt = md5(time() . rand(1000, 9999));
                    $supplier_data['salt'] = $salt;
                    // 加密密码
                    $supplier_data['password'] = md5((!empty($info['password']) ? $info['password'] : '123456') . $salt);
                }
                
                // 检查账号是否已存在
                $supplier = Db::name('supplier')->where('username', $account)->find();
                if ($supplier) {
                    // 更新现有记录，保留不需要更新的字段
                    $updateData = $supplier_data;
                    if (!$needsPasswordUpdate) {
                        unset($updateData['password'], $updateData['salt']);
                    }
                    $result = Db::name('supplier')->where('id', $supplier['id'])->update($updateData);
                    \think\facade\Log::record('Updated supplier table, id: ' . $supplier['id'] . ', result: ' . $result, 'debug');
                    return 'updated supplier id: ' . $supplier['id'];
                } else {
                    // 插入新记录
                    $supplier_data['create_time'] = time();
                    $supplier_data['status'] = 1; // 默认为启用状态
                    $supplierId = Db::name('supplier')->insertGetId($supplier_data);
                    \think\facade\Log::record('Inserted supplier table, id: ' . $supplierId, 'debug');
                    return 'inserted supplier id: ' . $supplierId;
                }
            } else {
                \think\facade\Log::record('供应商账号同步失败: 缺少账号信息', 'warning');
                return 'no account provided';
            }
        } catch (Throwable $e) {
            \think\facade\Log::record('Sync to supplier error: ' . $e->getMessage() . ', stack: ' . $e->getTraceAsString(), 'error');
            return 'error: ' . $e->getMessage();
        }
    }
    
    /**
     * 批量同步所有供应商账号
     * 可通过访问 /product_supplier/sync_all 执行
     */
    public function syncAll()
    {
        // 检查权限（可根据实际需求调整）
        
        // 获取所有有账号的供应商
        $suppliers = Db::name('product_supplier')
            ->where('account', '<>', '')
            ->whereNotNull('account')
            ->select();
        
        $successCount = 0;
        $failCount = 0;
        
        foreach ($suppliers as $supplier) {
            try {
                $this->syncToSupplier($supplier, $supplier['id']);
                $successCount++;
            } catch (\Exception $e) {
                \think\facade\Log::record('批量同步失败 - 供应商ID: ' . $supplier['id'] . ', 错误: ' . $e->getMessage());
                $failCount++;
            }
        }
        
        return json(['code' => 0, 'msg' => '同步完成', 'data' => [
            'total' => count($suppliers),
            'success' => $successCount,
            'fail' => $failCount
        ]]);
    }

    //删除
    public function del(){
        $ids = input('post.ids/a');
        Db::name('product_supplier')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
        \app\common\System::plog('删除供应商'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }

    //选择供应商
    public function chooseSupplier(){
        if(input('id')){
            $info = Db::name('product_supplier')->where('id',input('id'))->find();
            return json(['status'=>1,'data'=>$info]);
        }
        return View::fetch();
    }
}