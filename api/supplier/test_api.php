<?php
// 测试API文件
header('Content-Type: application/json');

// 返回基本系统信息
$response = array(
    "status" => "success",
    "message" => "API测试成功",
    "timestamp" => time(),
    "php_version" => phpversion(),
    "server" => $_SERVER['SERVER_SOFTWARE'] ?? "未知",
    "method" => $_SERVER['REQUEST_METHOD'] ?? "未知"
);

echo json_encode($response);
?>
