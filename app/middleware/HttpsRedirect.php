<?php
namespace app\middleware;

use think\facade\Config;
use think\Response;

class HttpsRedirect
{
    /**
     * 处理HTTPS重定向
     * 
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        // 获取HTTPS配置
        $httpsConfig = Config::get('secure.https', []);
        $forceHttps = $httpsConfig['force'] ?? true;
        
        // 如果配置了强制HTTPS且当前不是HTTPS请求，则重定向
        if ($forceHttps && !$request->isSsl()) {
            $httpsUrl = str_replace('http://', 'https://', $request->url(true));
            return redirect($httpsUrl, 301);
        }
        
        return $next($request);
    }
    
    /**
     * 响应后设置HSTS头
     * 
     * @param \think\Request $request
     * @param \think\Response $response
     */
    public function end(&$request, &$response)
    {
        if ($request->isSsl()) {
            $hstsConfig = Config::get('secure.https.hsts', []);
            if ($hstsConfig['enabled'] ?? false) {
                $maxAge = $hstsConfig['max_age'] ?? 31536000;
                $includeSubDomains = $hstsConfig['include_subdomains'] ?? true;
                $hstsValue = "max-age={$maxAge}";
                
                if ($includeSubDomains) {
                    $hstsValue .= "; includeSubDomains";
                }
                
                $response->header('Strict-Transport-Security', $hstsValue);
            }
        }
    }
}