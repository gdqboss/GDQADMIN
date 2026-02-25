<?php
header('Content-Type: application/json');

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
    if (count($memory) > 100) $memory = array_slice($memory, -100);
    file_put_contents($memory_file, json_encode($memory, JSON_UNESCAPED_UNICODE));
}

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? '';

// 聊天 - 所有助手都会回复
if ($action === 'chat') {
    $message = $_POST['message'] ?? '';
    $username = $_POST['username'] ?? '用户';
    
    if (!$message) {
        echo json_encode(['code'=>0, 'msg'=>'消息为空']); exit;
    }
    
    // 保存用户消息
    writeMemory([
        'role' => 'user',
        'content' => $message,
        'source' => 'web',
        'time' => time()
    ]);
    
    // 获取上下文
    $memory = readMemory();
    $context = '';
    foreach (array_slice($memory, -10) as $m) {
        $context .= $m['role'] . ': ' . $m['content'] . "\n";
    }
    
    // 获取所有启用的助手
    $assistants_r = mysqli_query($conn, "SELECT * FROM ai_assistants WHERE enabled=1");
    $replies = [];
    
    while ($assistant = mysqli_fetch_assoc($assistants_r)) {
        $reply = callAI($message, $context, $username, $assistant);
        $replies[] = [
            'assistant' => $assistant['name'],
            'reply' => $reply
        ];
        
        // 保存助手回复
        writeMemory([
            'role' => 'assistant',
            'content' => $reply,
            'source' => 'assistant:' . $assistant['name'],
            'time' => time()
        ]);
    }
    
    // 返回第一个助手的回复（简化版）
    if (count($replies) > 0) {
        echo json_encode(['code'=>1, 'data'=>['reply'=>$replies[0]['reply'], 'assistants'=>$replies]]);
    } else {
        echo json_encode(['code'=>0, 'msg'=>'没有启用的助手']);
    }
    exit;
}

if ($action === 'memory') {
    $memory = readMemory();
    echo json_encode(['code'=>1, 'data'=>$memory]);
    exit;
}

if ($action === 'clear_memory') {
    file_put_contents($memory_file, '[]');
    echo json_encode(['code'=>1, 'msg'=>'记忆已清空']);
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);

function callAI($message, $context, $username, $assistant) {
    // 使用助手配置或默认Minimax
    $api_key = $assistant['api_key'] ?: 'sk-cp-BbLwwqBSr8RrPusVeP8-U4_ezPtJS48rVjuMepMrxOZR4vcyRt_zD-OwhYcm7KKVnWT6nZxvi9q8zTsa1yC_mIaoqD4UyPjQn6xM4oOaoR5S0AHQut6jQtU';
    $model = $assistant['model'] ?: 'abab6.5s-chat';
    
    $system = "你是{$assistant['name']}，" . ($assistant['description'] ?: 'AI助手');
    $system .= "。你是用户的朋友，了解他们的生意。";
    if ($context) $system .= "\n\n对话历史:\n" . $context;
    
    $ch = curl_init('https://api.minimax.chat/v1/text/chatcompletion_v2');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => $model,
        'messages' => [
            ['role'=>'system', 'content'=>$system],
            ['role'=>'user', 'content'=>$message]
        ]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer '.$api_key, 'Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    return $result['choices'][0]['message']['content'] ?? '抱歉，我暂时无法回答。';
}
