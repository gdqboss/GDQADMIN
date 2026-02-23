<?php
namespace app\middleware;

use think\facade\Cache;
use think\facade\Request;

class Auth
{
    /**
     * 处理请求
     * @param $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // 获取token
        $token = Request::header('Authorization') ?: $request->get('token');
        
        if (empty($token)) {
            return json(['status' => 0, 'msg' => '请先登录', 'code' => 401]);
        }
        
        // 验证token
        $userInfo = Cache::get('supplier_token_' . $token);
        
        if (empty($userInfo)) {
            return json(['status' => 0, 'msg' => '登录已过期，请重新登录', 'code' => 401]);
        }
        
        // 将用户信息保存到请求中
        $request->user = $userInfo;
        
        // 延长token有效期
        Cache::set('supplier_token_' . $token, $userInfo, 3600 * 24 * 7);
        
        return $next($request);
    }
}
