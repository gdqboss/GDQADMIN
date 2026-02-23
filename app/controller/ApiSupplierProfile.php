<?php
namespace app\controller;

use app\BaseController;
use think\facade\Db;
use think\facade\Session;
use think\facade\Cache;
use think\facade\Request;

class ApiSupplierProfile extends BaseController
{
    /**
     * 初始化方法
     * 验证供应商身份
     */
    protected function initialize()
    {
        // 验证供应商身份，确保已登录
        $supplierId = Session::get('supplier_id');
        if (!$supplierId) {
            $this->error('未登录或登录已过期', null, 401);
        }
        $this->supplierId = $supplierId;
    }
    
    /**
     * 获取供应商个人信息
     */
    public function getProfile()
    {
        try {
            $supplier = Db::name('supplier')
                ->where('id', $this->supplierId)
                ->field('id, supplier_name, contact_name, contact_tel, email, create_time, status')
                ->find();
            
            if (!$supplier) {
                return json(['status' => 0, 'msg' => '供应商信息不存在']);
            }
            
            // 格式化创建时间
            $supplier['create_time'] = date('Y-m-d H:i:s', $supplier['create_time']);
            
            return json(['status' => 1, 'data' => $supplier]);
        } catch (\Exception $e) {
            return json(['status' => 0, 'msg' => '获取供应商信息失败: ' . $e->getMessage()]);
        }
    }
    
    /**
     * 更新供应商个人信息
     */
    public function updateProfile()
    {
        try {
            $data = Request::param();
            
            // 验证参数
            $validate = $this->validate($data, [
                'contact_name|联系人' => 'require',
                'contact_tel|联系电话' => 'regex:/^1[3-9]\\d{9}$/|max:11',
                'email|邮箱' => 'email|max:100'
            ]);
            
            if ($validate !== true) {
                return json(['status' => 0, 'msg' => $validate]);
            }
            
            // 更新信息
            $updateData = [];
            if (isset($data['contact_name'])) {
                $updateData['contact_name'] = $data['contact_name'];
            }
            if (isset($data['contact_tel'])) {
                $updateData['contact_tel'] = $data['contact_tel'];
            }
            if (isset($data['email'])) {
                $updateData['email'] = $data['email'];
            }
            
            if (!empty($updateData)) {
                $updateData['update_time'] = time();
                Db::name('supplier')
                    ->where('id', $this->supplierId)
                    ->update($updateData);
            }
            
            return json(['status' => 1, 'msg' => '更新成功']);
        } catch (\Exception $e) {
            return json(['status' => 0, 'msg' => '更新失败: ' . $e->getMessage()]);
        }
    }
    
    /**
     * 修改密码
     */
    public function changePassword()
    {
        try {
            $data = Request::param();
            
            // 验证参数
            $validate = $this->validate($data, [
                'oldPassword|旧密码' => 'require',
                'newPassword|新密码' => 'require|min:6|max:20',
                'confirmPassword|确认密码' => 'require|confirm:newPassword'
            ]);
            
            if ($validate !== true) {
                return json(['status' => 0, 'msg' => $validate]);
            }
            
            // 获取当前供应商信息
            $supplier = Db::name('supplier')
                ->where('id', $this->supplierId)
                ->field('id, password')
                ->find();
            
            // 验证旧密码
            if (password_verify($data['oldPassword'], $supplier['password'])) {
                // 密码正确，更新新密码
                $newPasswordHash = password_hash($data['newPassword'], PASSWORD_DEFAULT);
                Db::name('supplier')
                    ->where('id', $this->supplierId)
                    ->update(['password' => $newPasswordHash]);
                
                return json(['status' => 1, 'msg' => '密码修改成功']);
            } else {
                // 旧密码错误
                return json(['status' => 0, 'msg' => '当前密码错误']);
            }
        } catch (\Exception $e) {
            return json(['status' => 0, 'msg' => '密码修改失败: ' . $e->getMessage()]);
        }
    }
    
    /**
     * 获取登录日志
     */
    public function getLoginLog()
    {
        try {
            $page = Request::param('page', 1, 'intval');
            $pageSize = Request::param('pageSize', 10, 'intval');
            
            $query = Db::name('supplier_login_log')
                ->where('supplier_id', $this->supplierId)
                ->order('login_time', 'desc');
            
            $total = $query->count();
            $list = $query
                ->page($page, $pageSize)
                ->select()
                ->toArray();
            
            // 格式化登录时间
            foreach ($list as &$item) {
                $item['login_time'] = date('Y-m-d H:i:s', $item['login_time']);
            }
            
            $data = [
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPage' => ceil($total / $pageSize)
            ];
            
            return json(['status' => 1, 'data' => $data]);
        } catch (\Exception $e) {
            return json(['status' => 0, 'msg' => '获取登录日志失败: ' . $e->getMessage()]);
        }
    }
    
    /**
     * 更新供应商头像
     */
    public function updateAvatar()
    {
        try {
            // 获取上传的文件
            $file = Request::file('avatar');
            
            if (!$file) {
                return json(['status' => 0, 'msg' => '请选择要上传的头像']);
            }
            
            // 验证文件
            $validate = $file->validate([
                'size' => 2048000,
                'ext' => 'jpg,jpeg,png,gif'
            ]);
            
            if (!$validate->check()) {
                return json(['status' => 0, 'msg' => $file->getError()]);
            }
            
            // 生成保存路径
            $savePath = 'uploads/supplier/' . $this->supplierId . '/avatar/';
            $saveDir = root_path() . 'public/' . $savePath;
            
            // 确保目录存在
            if (!is_dir($saveDir)) {
                mkdir($saveDir, 0755, true);
            }
            
            // 移动文件
            $info = $file->move($saveDir);
            
            if (!$info) {
                return json(['status' => 0, 'msg' => '文件上传失败: ' . $file->getError()]);
            }
            
            // 更新数据库
            $avatarUrl = '/' . $savePath . $info->getFilename();
            Db::name('supplier')
                ->where('id', $this->supplierId)
                ->update(['avatar' => $avatarUrl]);
            
            return json(['status' => 1, 'msg' => '头像更新成功', 'data' => ['avatar_url' => $avatarUrl]]);
        } catch (\Exception $e) {
            return json(['status' => 0, 'msg' => '头像更新失败: ' . $e->getMessage()]);
        }
    }
}
