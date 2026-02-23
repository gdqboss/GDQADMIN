<?php
namespace app\middleware;

use think\facade\Config;
use think\Response;

class SecurityHeaders
{
    /**
     * 设置安全响应头
     * 
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        $response = $next($request);
        
        // 获取安全头配置
        $securityHeaders = Config::get('secure.headers', []);
        
        // 设置各种安全头
        foreach ($securityHeaders as $header => $value) {
            $response->header($header, $value);
        }
        
        // 如果是HTTPS请求，额外设置一些安全头
        if ($request->isSsl()) {
            $response->header('Content-Security-Policy', "default-src 'self' https://gdqshop.cn; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
        }
        
        return $response;
    }
}