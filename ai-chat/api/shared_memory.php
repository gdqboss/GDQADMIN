<?php
header('Content-Type: application/json');

$memory_file = '/tmp/gdq_shared_memory.json';

// 读取共享记忆
function readMemory() {
    global $memory_file;
    $content = @file_get_contents($memory_file);
    if (!$content) return [];
    return json_decode($content, true) ?: [];
}

// 写入共享记忆
function writeMemory($data) {
    global $memory_file;
    file_put_contents($memory_file, json_encode($data, JSON_UNESCAPED_UNICODE));
}

$action = $_GET['action'] ?? '';

if ($action === 'add') {
    $role = $_POST['role'] ?? 'user';
    $content = $_POST['content'] ?? '';
    $source = $_POST['source'] ?? 'web'; // web 或 telegram
    
    if (!$content) {
        echo json_encode(['code'=>0, 'msg'=>'内容为空']); exit;
    }
    
    $memory = readMemory();
    $memory[] = [
        'role' => $role,
        'content' => $content,
        'source' => $source,
        'time' => time()
    ];
    
    // 只保留最近50条
    if (count($memory) > 50) {
        $memory = array_slice($memory, -50);
    }
    
    writeMemory($memory);
    echo json_encode(['code'=>1, 'msg'=>'已保存']);
    exit;
}

if ($action === 'get') {
    $memory = readMemory();
    echo json_encode(['code'=>1, 'data'=>$memory]);
    exit;
}

if ($action === 'clear') {
    writeMemory([]);
    echo json_encode(['code'=>1, 'msg'=>'已清空']);
    exit;
}

if ($action === 'last') {
    $memory = readMemory();
    $last = array_slice($memory, -10);
    echo json_encode(['code'=>1, 'data'=>$last]);
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);
