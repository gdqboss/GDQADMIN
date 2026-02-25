<?php
header('Content-Type: application/json');

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? '';

// 获取/创建用户设置
if ($action === 'get_settings') {
    $user_id = intval($_GET['user_id'] ?? 0);
    
    $r = mysqli_query($conn, "SELECT * FROM ai_user_settings WHERE user_id=$user_id");
    if ($row = mysqli_fetch_assoc($r)) {
        echo json_encode(['code'=>1, 'data'=>$row]);
    } else {
        // 创建默认设置
        $now = time();
        mysqli_query($conn, "INSERT INTO ai_user_settings (user_id, room_name, create_time) VALUES ($user_id, '彩美特AI聊天室', $now)");
        echo json_encode(['code'=>1, 'data'=>['user_id'=>$user_id,'room_name'=>'彩美特AI聊天室']]);
    }
    exit;
}

// 更新设置
if ($action === 'update_settings') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $room_name = mysqli_real_escape_string($conn, $_POST['room_name'] ?? '');
    
    if (!$room_name) {
        echo json_encode(['code'=>0, 'msg'=>'名称不能为空']); exit;
    }
    
    $r = mysqli_query($conn, "SELECT id FROM ai_user_settings WHERE user_id=$user_id");
    if (mysqli_num_rows($r) > 0) {
        mysqli_query($conn, "UPDATE ai_user_settings SET room_name='$room_name' WHERE user_id=$user_id");
    } else {
        $now = time();
        mysqli_query($conn, "INSERT INTO ai_user_settings (user_id, room_name, create_time) VALUES ($user_id, '$room_name', $now)");
    }
    
    echo json_encode(['code'=>1, 'msg'=>'保存成功']);
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);
