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
    if (count($memory) > 50) $memory = array_slice($memory, -50);
    file_put_contents($memory_file, json_encode($memory, JSON_UNESCAPED_UNICODE));
}

$action = $_GET['action'] ?? '';

if ($action === 'chat') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $message = $_POST['message'] ?? '';
    $username = $_POST['username'] ?? '用户';
    
    if (!$message) {
        echo json_encode(['code'=>0, 'msg'=>'消息为空']); exit;
    }
    
    // 保存用户消息到共享记忆
    writeMemory([
        'role' => 'user',
        'content' => $message,
        'source' => 'web',
        'time' => time()
    ]);
    
    // 获取共享记忆作为上下文
    $memory = readMemory();
    $context = '';
    foreach (array_slice($memory, -8) as $m) {
        $context .= $m['role'] . ': ' . $m['content'] . "\n";
    }
    
    // 调用AI
    $reply = callAI($message, $context, $username);
    
    // 保存AI回复到共享记忆
    writeMemory([
        'role' => 'assistant',
        'content' => $reply,
        'source' => 'web',
        'time' => time()
    ]);
    
    echo json_encode(['code'=>1, 'data'=>['reply'=>$reply]]);
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

function callAI($message, $context, $username) {
    $api_key = 'sk-cp-BbLwwqBSr8RrPusVeP8-U4_ezPtJS48rVjuMepMrxOZR4vcyRt_zD-OwhYcm7KKVnWT6nZxvi9q8zTsa1yC_mIaoqD4UyPjQn6xM4oOaoR5S0AHQut6jQtU';
    
    $system = "你是江小鱼，彩美特企业的AI助手。你是用户的朋友，了解他们的生意（白酒、箱包、红茶、进出口贸易）。";
    if ($context) $system .= "\n\n最近的对话:\n" . $context;
    
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
