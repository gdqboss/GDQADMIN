<?php
/**
 * 实时聊天API - 类似Telegram的工作方式
 * 用户发消息 -> API调用OpenClaw -> 直接返回结果
 */

header('Content-Type: application/json');

$memory_file = '/tmp/gdq_shared_memory.json';
$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
mysqli_query($conn, "SET NAMES utf8mb4");

function readMemory() {
    global $memory_file;
    $c = @file_get_contents($memory_file);
    return $c ? json_decode($c, true) : [];
}

function writeMemory($entry) {
    global $memory_file;
    $memory = readMemory();
    $memory[] = $entry;
    if (count($memory) > 100) $memory = array_slice($memory, -100);
    file_put_contents($memory_file, json_encode($memory, JSON_UNESCAPED_UNICODE));
}

$action = $_GET['action'] ?? '';

// 实时聊天 - 直接调用OpenClaw处理
if ($action === 'chat') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $message = $_POST['message'] ?? '';
    $username = $_POST['username'] ?? '用户';
    
    if (!$message) {
        echo json_encode(['code'=>0, 'msg'=>'消息为空']); exit;
    }
    
    // 记录消息
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
    
    // 调用OpenClaw处理（通过API）
    $reply = processByOpenClaw($message, $context, $username);
    
    // 记录回复
    writeMemory([
        'role' => 'assistant',
        'content' => $reply,
        'source' => 'web',
        'time' => time()
    ]);
    
    echo json_encode(['code'=>1, 'data'=>['reply'=>$reply]]);
    exit;
}

// 获取对话历史
if ($action === 'history') {
    $memory = readMemory();
    echo json_encode(['code'=>1, 'data'=>$memory]);
    exit;
}

// 清空对话
if ($action === 'clear') {
    file_put_contents($memory_file, '[]');
    echo json_encode(['code'=>1, 'msg'=>'已清空']);
    exit;
}

// 实际调用OpenClaw处理
function processByOpenClaw($message, $context, $username) {
    // 方式1: 调用Minimax API（当前使用）
    return callMinimax($message, $context, $username);
    
    // 方式2: 如果有OpenClaw API，可以直接调用
    // return callOpenClawAPI($message, $context, $username);
}

function callMinimax($message, $context, $username) {
    $api_key = 'sk-cp-BbLwwqBSr8RrPusVeP8-U4_ezPtJS48rVjuMepMrxOZR4vcyRt_zD-OwhYcm7KKVnWT6nZxvi9q8zTsa1yC_mIaoqD4UyPjQn6xM4oOaoR5S0AHQut6jQtU';
    
    $system = "你是江小鱼，彩美特企业的AI助手。用户'$username'的朋友。你可以直接回答问题，也可以帮助用户完成开发任务。";
    if ($context) {
        $system .= "\n\n对话历史:\n" . $context;
    }
    
    $ch = curl_init('https://api.minimax.chat/v1/text/chatcompletion_v2');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'abab6.5s-chat',
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
