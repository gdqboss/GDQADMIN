<?php
header('Content-Type: application/json');

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
if (!$conn) { echo json_encode(['code'=>0, 'msg'=>'DB error']); exit; }
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? '';

// 发送消息
if ($action === 'send') {
    $room_id = intval($_POST['room_id'] ?? 0);
    $user_id = intval($_POST['user_id'] ?? 0);
    $content = mysqli_real_escape_string($conn, $_POST['content'] ?? '');
    $type = in_array($_POST['type'] ?? 'text', ['text', 'image', 'file', 'ai']) ? $_POST['type'] : 'text';
    
    if (!$room_id || !$user_id || !$content) {
        echo json_encode(['code'=>0, 'msg'=>'参数不全']); exit;
    }
    
    $now = time();
    mysqli_query($conn, "INSERT INTO ai_messages (room_id, user_id, content, type, create_time) VALUES ($room_id, $user_id, '$content', '$type', $now)");
    
    echo json_encode(['code'=>1, 'msg'=>'发送成功', 'data'=>['id'=>mysqli_insert_id($conn), 'time'=>$now]]);
    exit;
}

// 获取消息
if ($action === 'list') {
    $room_id = intval($_GET['room_id'] ?? 0);
    $limit = intval($_GET['limit'] ?? 50);
    $before = intval($_GET['before'] ?? 0);
    
    $sql = "SELECT m.*, u.nickname, u.avatar FROM ai_messages m 
            LEFT JOIN ai_users u ON m.user_id = u.id 
            WHERE m.room_id = $room_id";
    if ($before > 0) $sql .= " AND m.create_time < $before";
    $sql .= " ORDER BY m.create_time DESC LIMIT $limit";
    
    $r = mysqli_query($conn, $sql);
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
    echo json_encode(['code'=>1, 'data'=>array_reverse($list)]);
    exit;
}

// 创建群聊
if ($action === 'create_room') {
    $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '新群聊');
    $type = $_POST['type'] === 'group' ? 'group' : 'private';
    $user_id = intval($_POST['user_id'] ?? 0);
    $now = time();
    
    mysqli_query($conn, "INSERT INTO ai_rooms (name, type, created_by, create_time) VALUES ('$name', '$type', $user_id, $now)");
    $room_id = mysqli_insert_id($conn);
    
    // 创建者自动加入
    mysqli_query($conn, "INSERT INTO ai_room_members (room_id, user_id, role, join_time) VALUES ($room_id, $user_id, 'admin', $now)");
    
    echo json_encode(['code'=>1, 'msg'=>'创建成功', 'data'=>['id'=>$room_id]]);
    exit;
}

// 获取用户房间列表
if ($action === 'rooms') {
    $user_id = intval($_GET['user_id'] ?? 0);
    
    $sql = "SELECT r.* FROM ai_rooms r 
            JOIN ai_room_members rm ON r.id = rm.room_id 
            WHERE rm.user_id = $user_id 
            ORDER BY r.create_time DESC";
    
    $r = mysqli_query($conn, $sql);
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) {
        // 获取最后一条消息
        $msg_r = mysqli_query($conn, "SELECT content, create_time FROM ai_messages WHERE room_id={$row['id']} ORDER BY create_time DESC LIMIT 1");
        if ($msg = mysqli_fetch_assoc($msg_r)) {
            $row['last_msg'] = $msg['content'];
            $row['last_time'] = $msg['create_time'];
        }
        $list[] = $row;
    }
    echo json_encode(['code'=>1, 'data'=>$list]);
    exit;
}

// 加入房间
if ($action === 'join_room') {
    $room_id = intval($_POST['room_id'] ?? 0);
    $user_id = intval($_POST['user_id'] ?? 0);
    $now = time();
    
    $check = mysqli_query($conn, "SELECT id FROM ai_room_members WHERE room_id=$room_id AND user_id=$user_id");
    if (mysqli_num_rows($check) > 0) {
        echo json_encode(['code'=>0, 'msg'=>'已在群中']); exit;
    }
    
    mysqli_query($conn, "INSERT INTO ai_room_members (room_id, user_id, role, join_time) VALUES ($room_id, $user_id, 'member', $now)");
    echo json_encode(['code'=>1, 'msg'=>'加入成功']);
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);
