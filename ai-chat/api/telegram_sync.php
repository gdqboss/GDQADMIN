<?php
/**
 * Telegram 同步API - 供OpenClaw调用来保存/读取对话
 */

$memory_file = '/tmp/gdq_shared_memory.json';

function readMemory() {
    global $memory_file;
    $content = @file_get_contents($memory_file);
    return $content ? json_decode($content, true) : [];
}

function writeMemory($entry) {
    global $memory_file;
    $memory = readMemory();
    $memory[] = $entry;
    if (count($memory) > 50) $memory = array_slice($memory, -50);
    file_put_contents($memory_file, json_encode($memory, JSON_UNESCAPED_UNICODE));
}

$action = $_GET['action'] ?? '';

// 保存Telegram用户消息
if ($action === 'save_user') {
    $content = $_GET['content'] ?? $_POST['content'] ?? '';
    if ($content) {
        writeMemory([
            'role' => 'user',
            'content' => $content,
            'source' => 'telegram',
            'time' => time()
        ]);
    }
    echo json_encode(['code'=>1]);
    exit;
}

// 保存AI回复（Telegram）
if ($action === 'save_ai') {
    $content = $_GET['content'] ?? $_POST['content'] ?? '';
    if ($content) {
        writeMemory([
            'role' => 'assistant',
            'content' => $content,
            'source' => 'telegram',
            'time' => time()
        ]);
    }
    echo json_encode(['code'=>1]);
    exit;
}

// 获取所有对话历史
if ($action === 'get') {
    $memory = readMemory();
    echo json_encode(['code'=>1, 'data'=>$memory]);
    exit;
}

// 获取上下文（用于AI回复）
if ($action === 'context') {
    $memory = readMemory();
    $context = '';
    foreach (array_slice($memory, -10) as $m) {
        $context .= $m['role'] . ': ' . $m['content'] . "\n";
    }
    echo json_encode(['code'=>1, 'context'=>$context]);
    exit;
}

// 清空记忆
if ($action === 'clear') {
    file_put_contents($memory_file, '[]');
    echo json_encode(['code'=>1, 'msg'=>'已清空']);
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);
