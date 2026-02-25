<?php
header('Content-Type: application/json');

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
if (!$conn) { echo json_encode(['code'=>0, 'msg'=>'DB error']); exit; }
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? '';

if ($action === 'chat') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $message = $_POST['message'] ?? '';
    $username = $_POST['username'] ?? '用户';
    
    if (!$user_id || !$message) {
        echo json_encode(['code'=>0, 'msg'=>'参数不全']); exit;
    }
    
    // 保存用户消息到共享表
    $now = time();
    $msg_esc = mysqli_real_escape_string($conn, $message);
    $user_esc = mysqli_real_escape_string($conn, $username);
    mysqli_query($conn, "INSERT INTO ai_conversations (user_id, username, role, content, create_time) VALUES ($user_id, '$user_esc', 'user', '$msg_esc', $now)");
    
    // 获取历史（用于上下文）
    $history_r = mysqli_query($conn, "SELECT role, content FROM ai_conversations WHERE user_id=$user_id ORDER BY create_time DESC LIMIT 6");
    $history = [];
    while ($h = mysqli_fetch_assoc($history_r)) {
        array_unshift($history, $h['role'] . ': ' . $h['content']);
    }
    $context = implode("\n", array_reverse($history));
    
    // 通过本地API调用OpenClaw（模拟）
    // 由于无法直接调用OpenClaw，使用Minimax但带入更多上下文
    $ai_reply = callAI($message, $context, $username);
    
    // 保存AI回复
    $reply_esc = mysqli_real_escape_string($conn, $ai_reply);
    mysqli_query($conn, "INSERT INTO ai_conversations (user_id, username, role, content, create_time) VALUES ($user_id, '江小鱼', 'assistant', '$reply_esc', $now)");
    
    echo json_encode(['code'=>1, 'data'=>['reply'=>$ai_reply]]);
    exit;
}

if ($action === 'history') {
    $user_id = intval($_GET['user_id'] ?? 0);
    $r = mysqli_query($conn, "SELECT role, content, create_time FROM ai_conversations WHERE user_id=$user_id ORDER BY create_time DESC LIMIT 50");
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
    echo json_encode(['code'=>1, 'data'=>array_reverse($list)]);
    exit;
}

if ($action === 'clear') {
    $user_id = intval($_GET['user_id'] ?? 0);
    mysqli_query($conn, "DELETE FROM ai_conversations WHERE user_id=$user_id");
    echo json_encode(['code'=>1, 'msg'=>'已清空对话']);
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);

function callAI($message, $context, $username) {
    $api_key = 'sk-cp-BbLwwqBSr8RrPusVeP8-U4_ezPtJS48rVjuMepMrxOZR4vcyRt_zD-OwhYcm7KKVnWT6nZxvi9q8zTsa1yC_mIaoqD4UyPjQn6xM4oOaoR5S0AHQut6jQtU';
    
    // 构建带上下文的对话
    $system_prompt = "你是江小鱼，彩美特企业的AI助手。你是'$username'的AI伙伴，了解他们的生意（白酒、箱包、红茶）和日常。你说话友好、有个性，记得之前的对话内容。";
    
    if ($context) {
        $system_prompt .= "\n\n之前的对话:\n" . $context;
    }
    
    $messages = [
        ['role'=>'system', 'content'=>$system_prompt],
        ['role'=>'user', 'content'=>$message]
    ];
    
    $data = [
        'model' => 'abab6.5s-chat',
        'messages' => $messages
    ];
    
    $ch = curl_init('https://api.minimax.chat/v1/text/chatcompletion_v2');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        return $result['choices'][0]['message']['content'];
    }
    
    return '抱歉，我暂时无法回答。';
}
