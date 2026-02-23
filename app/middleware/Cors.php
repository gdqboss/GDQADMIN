<?php
namespace app\middleware;

use think\facade\Config;
use think\Response;

class Cors
{
    /**
     * 处理跨域请求
     * 
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        // 获取CORS配置
        $corsConfig = Config::get('secure.cors', []);
        
        // 如果CORS未启用，则直接通过
        if (!($corsConfig['enabled'] ?? false)) {
            return $next($request);
        }
        
        // 处理预检请求
        if ($request->method() === 'OPTIONS') {
            $response = new Response('');
            $this->setCorsHeaders($response, $corsConfig);
            return $response;
        }
        
        $response = $next($request);
        $this->setCorsHeaders($response, $corsConfig);
        
        return $response;
    }
    
    /**
     * 设置CORS响应头
     * 
     * @param \think\Response $response
     * @param array $corsConfig
     */
    protected function setCorsHeaders(Response $response, array $corsConfig)
    {
        $origin = $corsConfig['origin'] ?? ['*'];
        $methods = $corsConfig['methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
        $headers = $corsConfig['headers'] ?? ['Origin', 'Content-Type', 'Accept', 'Authorization', 'X-Requested-With'];
        $credentials = $corsConfig['credentials'] ?? true;
        $maxAge = $corsConfig['max_age'] ?? 3600;
        
        // 设置允许的源
        if (in_array('*', $origin)) {
            $response->header('Access-Control-Allow-Origin', '*');
        } else {
            $requestOrigin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
            if (in_array($requestOrigin, $origin)) {
                $response->header('Access-Control-Allow-Origin', $requestOrigin);
            }
        }
        
        // 设置其他CORS头
        $response->header('Access-Control-Allow-Methods', implode(', ', $methods));
        $response->header('Access-Control-Allow-Headers', implode(', ', $headers));
        $response->header('Access-Control-Max-Age', $maxAge);
        
        if ($credentials) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }
    }
}