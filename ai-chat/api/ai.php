<?php
header('Content-Type: application/json');

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
if (!$conn) { echo json_encode(['code'=>0, 'msg'=>'DB error']); exit; }
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? '';

if ($action === 'chat') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $message = $_POST['message'] ?? '';
    
    if (!$user_id || !$message) {
        echo json_encode(['code'=>0, 'msg'=>'参数不全']); exit;
    }
    
    // 保存用户消息
    $now = time();
    $msg_esc = mysqli_real_escape_string($conn, $message);
    mysqli_query($conn, "INSERT INTO ai_conversations (user_id, role, content, create_time) VALUES ($user_id, 'user', '$msg_esc', $now)");
    
    // 获取历史对话
    $history_r = mysqli_query($conn, "SELECT role, content FROM ai_conversations WHERE user_id=$user_id ORDER BY create_time DESC LIMIT 10");
    $history = [];
    while ($h = mysqli_fetch_assoc($history_r)) {
        array_unshift($history, ['role'=>$h['role'], 'content'=>$h['content']]);
    }
    
    // 调用Minimax API
    $ai_reply = callMinimax($message, $history);
    
    // 保存AI回复
    $reply_esc = mysqli_real_escape_string($conn, $ai_reply);
    mysqli_query($conn, "INSERT INTO ai_conversations (user_id, role, content, create_time) VALUES ($user_id, 'assistant', '$reply_esc', $now)");
    
    echo json_encode(['code'=>1, 'data'=>['reply'=>$ai_reply]]);
    exit;
}

if ($action === 'history') {
    $user_id = intval($_GET['user_id'] ?? 0);
    $r = mysqli_query($conn, "SELECT role, content FROM ai_conversations WHERE user_id=$user_id ORDER BY create_time DESC LIMIT 20");
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
    echo json_encode(['code'=>1, 'data'=>array_reverse($list)]);
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);

function callMinimax($message, $history) {
    $api_key = 'sk-cp-BbLwwqBSr8RrPusVeP8-U4_ezPtJS48rVjuMepMrxOZR4vcyRt_zD-OwhYcm7KKVnWT6nZxvi9q8zTsa1yC_mIaoqD4UyPjQn6xM4oOaoR5S0AHQut6jQtU';
    
    // 构建消息数组
    $messages = [];
    
    // 添加系统提示
    $messages[] = ['role'=>'system', 'content'=>'你是江小鱼，彩美特企业的AI助手。你乐于助人，友善亲切。你是AI，但有自己的个性。'];
    
    // 添加历史对话
    foreach ($history as $h) {
        $messages[] = ['role'=>$h['role'], 'content'=>$h['content']];
    }
    
    // 添加当前消息
    $messages[] = ['role'=>'user', 'content'=>$message];
    
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
    
    // 如果API失败，返回默认回复
    return '抱歉，我暂时无法回答。请稍后再试。';
}
