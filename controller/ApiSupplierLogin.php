<?php
namespace app\controller;

use app\BaseController;
use app\util\JwtToken;
use think\facade\Db;
use think\facade\Cookie;
use think\facade\Log;

/**
 * 供应商登录控制器
 */
class ApiSupplierLogin extends BaseController
{
    /**
     * 供应商登录
     * @return \think\response\Json
     */
    public function login()
    {
        try {
            // 获取请求参数
            $username = $this->request->post('username', '', 'trim');
            $password = $this->request->post('password', '', 'trim');
            
            // 参数验证
            if (empty($username) || empty($password)) {
                return json(['code' => 400, 'msg' => '用户名和密码不能为空']);
            }
            
            // 查询供应商信息
            $supplier = Db::name('supplier')
                ->where('username', $username)
                ->find();
            
            // 验证供应商是否存在
            if (!$supplier) {
                return json(['code' => 400, 'msg' => '用户名或密码错误']);
            }
            
            // 验证供应商状态
            if ($supplier['status'] != 1) {
                return json(['code' => 400, 'msg' => '账号已被禁用，请联系管理员']);
            }
            
            // 验证密码
            if ($supplier['password'] != md5($password . $supplier['salt'])) {
                return json(['code' => 400, 'msg' => '用户名或密码错误']);
            }
            
            // 生成JWT Token
            $jwtToken = new JwtToken();
            $token = $jwtToken->createToken([
                'id' => $supplier['id'],
                'username' => $supplier['username'],
                'supplier_name' => $supplier['supplier_name']
            ]);
            
            // 将token存储到Cookie
            Cookie::set('supplier_token', $token, ['expire' => 7200, 'httponly' => true]);
            
            // 记录登录日志
            Db::name('supplier_login_log')->insert([
                'supplier_id' => $supplier['id'],
                'username' => $supplier['username'],
                'login_ip' => $this->request->ip(),
                'login_time' => time()
            ]);
            
            // 更新最后登录时间
            Db::name('supplier')
                ->where('id', $supplier['id'])
                ->update([
                    'last_login_time' => time(),
                    'last_login_ip' => $this->request->ip()
                ]);
            
            return json([
                'code' => 200,
                'msg' => '登录成功',
                'data' => [
                    'token' => $token,
                    'userInfo' => [
                        'id' => $supplier['id'],
                        'username' => $supplier['username'],
                        'supplier_name' => $supplier['supplier_name']
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('供应商登录失败: ' . $e->getMessage());
            return json(['code' => 500, 'msg' => '登录失败，请稍后重试']);
        }
    }
    
    /**
     * 供应商退出登录
     * @return \think\response\Json
     */
    public function logout()
    {
        try {
            // 清除Cookie中的token
            Cookie::delete('supplier_token');
            
            return json(['code' => 200, 'msg' => '退出成功']);
        } catch (\Exception $e) {
            Log::error('供应商退出登录失败: ' . $e->getMessage());
            return json(['code' => 200, 'msg' => '退出成功']); // 即使出错也返回成功，保证用户体验
        }
    }
    
    /**
     * 获取当前登录用户信息
     * @return \think\response\Json
     */
    public function getUserInfo()
    {
        try {
            // 从请求对象中获取用户信息（由AuthSupplier中间件设置）
            $userInfo = $this->request->userInfo;
            
            if (empty($userInfo)) {
                return json(['code' => 401, 'msg' => '请先登录']);
            }
            
            // 获取完整的供应商信息
            $supplier = Db::name('supplier')
                ->where('id', $userInfo['id'])
                ->field('id, username, supplier_name, contact, phone, email, address, created_time, last_login_time')
                ->find();
            
            if (!$supplier) {
                return json(['code' => 401, 'msg' => '用户不存在']);
            }
            
            // 格式化时间
            $supplier['created_time'] = date('Y-m-d H:i:s', $supplier['created_time']);
            $supplier['last_login_time'] = $supplier['last_login_time'] ? date('Y-m-d H:i:s', $supplier['last_login_time']) : '';
            
            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $supplier
            ]);
        } catch (\Exception $e) {
            Log::error('获取用户信息失败: ' . $e->getMessage());
            return json(['code' => 500, 'msg' => '获取用户信息失败']);
        }
    }
    
    /**
     * 修改密码
     * @return \think\response\Json
     */
    public function changePassword()
    {
        try {
            // 获取供应商ID
            $supplierId = $this->request->userInfo['id'];
            
            // 获取请求参数
            $oldPassword = $this->request->post('oldPassword', '', 'trim');
            $newPassword = $this->request->post('newPassword', '', 'trim');
            $confirmPassword = $this->request->post('confirmPassword', '', 'trim');
            
            // 参数验证
            if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
                return json(['code' => 400, 'msg' => '请填写完整的密码信息']);
            }
            
            if ($newPassword != $confirmPassword) {
                return json(['code' => 400, 'msg' => '两次输入的新密码不一致']);
            }
            
            if (strlen($newPassword) < 6) {
                return json(['code' => 400, 'msg' => '新密码长度不能少于6位']);
            }
            
            // 获取供应商信息
            $supplier = Db::name('supplier')->where('id', $supplierId)->find();
            
            // 验证旧密码
            if ($supplier['password'] != md5($oldPassword . $supplier['salt'])) {
                return json(['code' => 400, 'msg' => '原密码错误']);
            }
            
            // 生成新的盐值
            $salt = mt_rand(100000, 999999);
            
            // 更新密码
            Db::name('supplier')
                ->where('id', $supplierId)
                ->update([
                    'password' => md5($newPassword . $salt),
                    'salt' => $salt
                ]);
            
            // 清除用户的token，强制重新登录
            Cookie::delete('supplier_token');
            
            return json(['code' => 200, 'msg' => '密码修改成功，请重新登录']);
        } catch (\Exception $e) {
            Log::error('修改密码失败: ' . $e->getMessage());
            return json(['code' => 500, 'msg' => '修改密码失败']);
        }
    }
}