<?php
/**
 * 供应商登录控制器
 * 处理供应商登录认证逻辑
 */
namespace app\controller;

use app\BaseController;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Log;

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
            $username = $this->request->post('username');
            $password = $this->request->post('password');
            
            // 参数验证
            if (!$username || !$password) {
                return json([
                    'status' => 0,
                    'msg' => '请填写账号和密码',
                    'data' => []
                ]);
            }
            
            // 查询供应商信息 - 使用正确的表名和字段名
            $supplier = Db::name('ddwx_product_supplier')
                ->where('account', $username)
                ->find();
            
            // 验证供应商是否存在
            if (!$supplier) {
                return json([
                    'status' => 0,
                    'msg' => '供应商账号不存在',
                    'data' => []
                ]);
            }
            
            // 验证密码 - 支持明文和加密
            $password_valid = false;
            
            // 数据库中密码是明文存储，直接比较
            if ($supplier['password'] == $password) {
                $password_valid = true;
            } 
            // 同时支持md5加密验证
            elseif (strlen($supplier['password']) == 32 && $supplier['password'] == md5($password)) {
                $password_valid = true;
            } 
            // 支持password_hash加密
            elseif (password_verify($password, $supplier['password'])) {
                $password_valid = true;
            }
            
            if (!$password_valid) {
                return json([
                    'status' => 0,
                    'msg' => '密码错误',
                    'data' => []
                ]);
            }
            
            // 生成token
            $token = $this->generateToken($supplier['id']);
            
            // 缓存供应商信息
            $cacheKey = 'supplier_token_' . $token;
            $expireTime = Config::get('auth.supplier_token_expire', 7200); // 默认2小时
            
            // 移除密码等敏感信息
            unset($supplier['password']);
            
            Cache::set($cacheKey, $supplier, $expireTime);
            
            // 记录登录日志
            $this->logLogin($supplier['id'], $supplier['account']);
            
            return json([
                'status' => 1,
                'msg' => '登录成功',
                'data' => [
                    'token' => $token,
                    'supplier_info' => $supplier,
                    'expire_time' => $expireTime
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('供应商登录失败: ' . $e->getMessage());
            return json([
                'status' => 0,
                'msg' => '登录失败，请稍后重试',
                'data' => []
            ]);
        }
    }
    
    /**
     * 生成登录token
     * @param int $supplierId
     * @return string
     */
    private function generateToken($supplierId)
    {
        $str = md5(uniqid(mt_rand(), true) . $supplierId . time());
        return substr($str, 0, 32);
    }
    
    /**
     * 记录登录日志
     * @param int $supplierId
     * @param string $username
     */
    private function logLogin($supplierId, $username)
    {
        try {
            // 简化处理，不记录详细登录日志，只更新登录时间
            // 更新最后登录时间到正确的表
            Db::name('ddwx_product_supplier')
                ->where('id', $supplierId)
                ->update([
                    'last_login_time' => time(),
                    'last_login_ip' => $this->request->ip()
                ]);
        } catch (\Exception $e) {
            // 日志记录失败不影响登录流程
            Log::error('记录供应商登录信息失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 供应商退出登录
     * @return \think\response\Json
     */
    public function logout()
    {
        try {
            $token = $this->request->header('Authorization') ?? $this->request->param('token');
            
            if ($token) {
                $cacheKey = 'supplier_token_' . $token;
                Cache::delete($cacheKey);
            }
            
            return json([
                'status' => 1,
                'msg' => '退出成功',
                'data' => []
            ]);
        } catch (\Exception $e) {
            Log::error('供应商退出登录失败: ' . $e->getMessage());
            return json([
                'status' => 1,
                'msg' => '退出成功',
                'data' => []
            ]);
        }
    }
}