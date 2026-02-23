<?php
namespace app\middleware;

use app\util\JwtToken;
use think\facade\Cookie;
use think\facade\Log;

/**
 * 供应商认证中间件
 */
class AuthSupplier
{
    /**
     * 处理请求
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        try {
            // 从请求头中获取Token
            $token = $request->header('Authorization', '');
            
            // 如果请求头中没有Token，从Cookie中获取
            if (empty($token)) {
                $token = Cookie::get('supplier_token', '');
            } else {
                // 如果Token带有Bearer前缀，去掉前缀
                $token = trim(str_replace('Bearer', '', $token));
            }
            
            // 验证Token是否存在
            if (empty($token)) {
                return json(['code' => 401, 'msg' => '请先登录']);
            }
            
            // 创建JWT工具实例
            $jwtToken = new JwtToken();
            
            // 验证Token
            $payload = $jwtToken->verifyToken($token);
            
            if (empty($payload)) {
                // Token无效或已过期，清除Cookie中的Token
                Cookie::delete('supplier_token');
                return json(['code' => 401, 'msg' => '登录已过期，请重新登录']);
            }
            
            // 检查Token是否即将过期（剩余时间少于30分钟）
            $expireTime = $payload['exp'];
            $now = time();
            $remainingTime = $expireTime - $now;
            
            // 如果Token即将过期，生成新的Token
            if ($remainingTime < 1800) {
                $newToken = $jwtToken->createToken([
                    'id' => $payload['id'],
                    'username' => $payload['username']
                ]);
                
                // 更新Cookie中的Token
                Cookie::set('supplier_token', $newToken, ['expire' => 7200, 'httponly' => true]);
            }
            
            // 将用户信息存储到请求对象中
            $request->userInfo = [
                'id' => $payload['id'],
                'username' => $payload['username']
            ];
            
            return $next($request);
        } catch (\Exception $e) {
            Log::error('供应商认证失败: ' . $e->getMessage());
            // 清除可能的无效Token
            Cookie::delete('supplier_token');
            return json(['code' => 401, 'msg' => '认证失败，请重新登录']);
        }
    }
}