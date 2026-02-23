<?php
/**
 * 供应商授权中间件
 * 用于验证供应商的登录状态和权限控制
 */
namespace app\middleware;

use think\Response;
use think\facade\Cache;
use think\facade\Config;

class AuthSupplier
{
    /**
     * 中间件处理
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        // 获取请求头中的Authorization token
        $token = $request->header('Authorization') ?? $request->param('token');
        
        if (!$token) {
            return json([
                'status' => 0,
                'msg' => '请先登录',
                'data' => []
            ]);
        }
        
        // 从缓存中获取供应商信息
        $supplierInfo = $this->getSupplierInfoByToken($token);
        
        if (!$supplierInfo) {
            return json([
                'status' => 0,
                'msg' => '登录已过期，请重新登录',
                'data' => []
            ]);
        }
        
        // 验证供应商状态
        if ($supplierInfo['supplier_status'] != 1) {
            return json([
                'status' => 0,
                'msg' => '供应商账号已被禁用',
                'data' => []
            ]);
        }
        
        // 将供应商信息存储到请求对象中，方便后续使用
        $request->supplier_id = $supplierInfo['id'];
        $request->supplier_info = $supplierInfo;
        
        // 延长token有效期
        $this->extendTokenExpire($token, $supplierInfo);
        
        // 继续处理请求
        return $next($request);
    }
    
    /**
     * 根据token获取供应商信息
     * @param string $token
     * @return array|null
     */
    private function getSupplierInfoByToken($token)
    {
        $cacheKey = 'supplier_token_' . $token;
        $supplierInfo = Cache::get($cacheKey);
        
        return $supplierInfo;
    }
    
    /**
     * 延长token有效期
     * @param string $token
     * @param array $supplierInfo
     */
    private function extendTokenExpire($token, $supplierInfo)
    {
        $cacheKey = 'supplier_token_' . $token;
        $expireTime = Config::get('auth.supplier_token_expire', 7200); // 默认2小时
        
        Cache::set($cacheKey, $supplierInfo, $expireTime);
    }
}