<?php
// API入口文件 - 用于处理供应商登录请求
header('Content-Type: application/json');

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];
// 获取请求URI路径部分
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 简单路由处理
if ($method === 'POST' && strpos($path, '/login') !== false) {
    // 包含控制器
    require_once '../../../controller/ApiSupplierLogin.php';
    
    // 创建控制器实例并调用login方法
    $controller = new ApiSupplierLogin();
    $controller->login();
    exit;
}

// 处理其他API端点或返回404
http_response_code(404);
echo json_encode([
    'code' => 404,
    'message' => 'API端点不存在',
    'timestamp' => time()
]);
