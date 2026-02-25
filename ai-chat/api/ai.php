<?php
header('Content-Type: application/json');

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
if (!$conn) { echo json_encode(['code'=>0, 'msg'=>'DB error']); exit; }
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? '';

// AI对话
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
    
    // 获取历史对话（最近10条）
    $history_r = mysqli_query($conn, "SELECT role, content FROM ai_conversations WHERE user_id=$user_id ORDER BY create_time DESC LIMIT 10");
    $history = [];
    while ($h = mysqli_fetch_assoc($history_r)) {
        array_unshift($history, ['role'=>$h['role'], 'content'=>$h['content']]);
    }
    
    // 调用OpenClaw API（通过本地HTTP）
    $ch = curl_init('http://127.0.0.1:18789/api/chat');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'message' => $message,
        'history' => $history,
        'user_id' => $user_id
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    $ai_reply = $result['message']['content'] ?? $result['text'] ?? 'AI响应失败';
    
    // 保存AI回复
    $reply_esc = mysqli_real_escape_string($conn, $ai_reply);
    $now = time();
    mysqli_query($conn, "INSERT INTO ai_conversations (user_id, role, content, create_time) VALUES ($user_id, 'assistant', '$reply_esc', $now)");
    
    echo json_encode(['code'=>1, 'data'=>['reply'=>$ai_reply]]);
    exit;
}

// 获取对话历史
if ($action === 'history') {
    $user_id = intval($_GET['user_id'] ?? 0);
    $limit = intval($_GET['limit'] ?? 20);
    
    $r = mysqli_query($conn, "SELECT role, content, create_time FROM ai_conversations WHERE user_id=$user_id ORDER BY create_time DESC LIMIT $limit");
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
    echo json_encode(['code'=>1, 'data'=>array_reverse($list)]);
    exit;
}

// 总结群聊
if ($action === 'summarize') {
    $room_id = intval($_GET['room_id'] ?? 0);
    
    $r = mysqli_query($conn, "SELECT u.nickname, m.content, m.create_time FROM ai_messages m 
            JOIN ai_users u ON m.user_id = u.id 
            WHERE m.room_id=$room_id ORDER BY m.create_time DESC LIMIT 50");
    
    $msgs = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $msgs[] = "{$row['nickname']}: {$row['content']}";
    }
    $context = implode("\n", array_reverse($msgs));
    
    // 简化调用 - 返回提示让用户使用AI
    echo json_encode(['code'=>1, 'data'=>[
        'summary'=>'请使用AI助手功能进行智能总结',
        'message_count'=>count($msgs)
    ]]);
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);
