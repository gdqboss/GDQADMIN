<?php
/**
 * 助手任务系统（安全版）
 * 只提供信息查询功能
 */
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// 只读操作
if ($action === 'info') {
    echo json_encode([
        'code'=>1,
        'info'=>[
            'php_version'=>phpversion(),
            'server'=>$_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'time'=>date('Y-m-d H:i:s')
        ]
    ]);
    exit;
}

if ($action === 'read') {
    $file = $_GET['file'] ?? '';
    $allowed = ['/www/wwwroot/gdqshop.cn/README.md', '/www/wwwroot/gdqshop.cn/ai-chat/README.md'];
    
    if (in_array($file, $allowed)) {
        $content = @file_get_contents($file);
        echo json_encode(['code'=>1, 'content'=>$content]);
    } else {
        echo json_encode(['code'=>0, 'msg'=>'不允许访问']);
    }
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'仅提供信息查询']);
