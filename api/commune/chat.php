<?php
/**
 * 聊天室 API（含私聊功能）
 */

header('Content-Type: application/json;charset=utf-8');
error_reporting(0);

$conn = @mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
if (!$conn) {
    echo json_encode(['code' => 0, 'msg' => '数据库连接失败']);
    exit;
}
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 获取消息列表
if ($action === 'list') {
    $last_id = (int)($_GET['last_id'] ?? 0);
    $limit = 50;
    $my_user = $_COOKIE['commune_user'] ?? '';
    $local_user = $_GET['local_user'] ?? '';
    
    // 使用 localStorage 的用户名如果 cookie 没有
    if (!$my_user && $local_user) {
        $my_user = $local_user;
    }
    
    // 公开消息 + 我的私聊消息 + 发给我的私聊消息
    // 获取最新的 50 条消息（先倒序取，再正序显示）
    $sql = "SELECT c.*, COALESCE(u.nickname, c.username) as nickname
            FROM ddwx_commune_chat c
            LEFT JOIN ddwx_commune_user u ON c.username = u.username
            WHERE c.status = 1 
            AND (c.to_user = '' OR c.to_user = '$my_user' OR c.from_user = '$my_user')
            ORDER BY c.id DESC LIMIT $limit";
    $result = mysqli_query($conn, $sql);
    
    $messages = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $messages[] = $row;
    }
    
    // 反转数组，让最旧的在前，最新的在后
    $messages = array_reverse($messages);
    
    echo json_encode(['code' => 1, 'data' => $messages]);
    exit;
}

// 发送消息（支持私聊）
if ($action === 'send') {
    $content = trim($_POST['content'] ?? '');
    $username = trim($_POST['username'] ?? '匿名用户');
    $to_user = trim($_POST['to_user'] ?? ''); // 私聊目标用户
    
    if (empty($content)) {
        echo json_encode(['code' => 0, 'msg' => '内容不能为空']);
        exit;
    }
    
    $content = mysqli_real_escape_string($conn, $content);
    $username = mysqli_real_escape_string($conn, $username);
    $to_user = mysqli_real_escape_string($conn, $to_user);
    $time = time();
    
    // 判断是否是私聊
    $msg_type = $to_user ? 'private' : 'public';
    
    $sql = "INSERT INTO ddwx_commune_chat (content, username, msg_type, from_user, to_user, is_robot, status, create_time) 
            VALUES ('$content', '$username', '$msg_type', '$username', '$to_user', 0, 1, $time)";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['code' => 1, 'msg' => '发送成功', 'id' => mysqli_insert_id($conn)]);
    } else {
        echo json_encode(['code' => 0, 'msg' => '发送失败']);
    }
    exit;
}

// 上传文件
if ($action === 'upload') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['code' => 0, 'msg' => '上传失败']);
        exit;
    }
    
    $file = $_FILES['file'];
    $username = trim($_POST['username'] ?? '匿名用户');
    $to_user = trim($_POST['to_user'] ?? '');
    $msg_type = trim($_POST['msg_type'] ?? 'file');
    
    $upload_dir = '/www/wwwroot/gdqshop.cn/public/uploads/chat/' . date('Ymd');
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_name = time() . '_' . rand(1000, 9999) . '.' . $ext;
    $path = $upload_dir . '/' . $new_name;
    
    if (move_uploaded_file($file['tmp_name'], $path)) {
        $url = '/public/uploads/chat/' . date('Ymd') . '/' . $new_name;
        $content = mysqli_real_escape_string($conn, $url);
        $username = mysqli_real_escape_string($conn, $username);
        $to_user = mysqli_real_escape_string($conn, $to_user);
        $time = time();
        
        // 如果没有指定 msg_type，根据文件类型判断
        if ($msg_type === 'image' || in_array(strtolower($ext), ['jpg','jpeg','png','gif','webp'])) {
            $msg_type = 'image';
        } else {
            $msg_type = $to_user ? 'private' : 'file';
        }
        
        mysqli_query($conn, "INSERT INTO ddwx_commune_chat (content, username, msg_type, from_user, to_user, file_name, is_robot, status, create_time) 
                VALUES ('$content', '$username', '$msg_type', '$username', '$to_user', '{$file['name']}', 0, 1, $time)");
        
        echo json_encode(['code' => 1, 'msg' => '上传成功', 'url' => $url]);
    } else {
        echo json_encode(['code' => 0, 'msg' => '保存失败']);
    }
    exit;
}

// 管理员：清理消息
if ($action === 'clear') {
    $before_time = time() - 30 * 86400;
    mysqli_query($conn, "UPDATE ddwx_commune_chat SET status = 0 WHERE create_time < $before_time");
    echo json_encode(['code' => 1, 'msg' => '清理完成']);
    exit;
}

// 管理员：清空所有消息
if ($action === 'clear_all') {
    mysqli_query($conn, "UPDATE ddwx_commune_chat SET status = 0");
    echo json_encode(['code' => 1, 'msg' => '已清空所有聊天记录']);
    exit;
}

// 管理员：删除消息
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        mysqli_query($conn, "UPDATE ddwx_commune_chat SET status = 0 WHERE id = $id");
        echo json_encode(['code' => 1, 'msg' => '删除成功']);
    } else {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
    }
    exit;
}

echo json_encode(['code' => 0, 'msg' => '未知操作']);
