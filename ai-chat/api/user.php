<?php
header('Content-Type: application/json');

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
if (!$conn) { echo json_encode(['code'=>0, 'msg'=>'DB error']); exit; }
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? '';

// 注册
if ($action === 'register') {
    $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $nickname = mysqli_real_escape_string($conn, $_POST['nickname'] ?? $username);
    
    if (!$username || !$password) {
        echo json_encode(['code'=>0, 'msg'=>'请填写用户名和密码']); exit;
    }
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $now = time();
    
    $r = mysqli_query($conn, "SELECT id FROM ai_users WHERE username='$username'");
    if (mysqli_num_rows($r) > 0) {
        echo json_encode(['code'=>0, 'msg'=>'用户名已存在']); exit;
    }
    
    mysqli_query($conn, "INSERT INTO ai_users (username, nickname, password, create_time) VALUES ('$username', '$nickname', '$hash', $now)");
    echo json_encode(['code'=>1, 'msg'=>'注册成功', 'data'=>['id'=>mysqli_insert_id($conn)]]);
    exit;
}

// 登录
if ($action === 'login') {
    $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $r = mysqli_query($conn, "SELECT * FROM ai_users WHERE username='$username'");
    if (!$row = mysqli_fetch_assoc($r)) {
        echo json_encode(['code'=>0, 'msg'=>'用户不存在']); exit;
    }
    
    if (!password_verify($password, $row['password'])) {
        echo json_encode(['code'=>0, 'msg'=>'密码错误']); exit;
    }
    
    echo json_encode(['code'=>1, 'msg'=>'登录成功', 'data'=>[
        'id'=>$row['id'], 
        'username'=>$row['username'], 
        'nickname'=>$row['nickname'],
        'avatar'=>$row['avatar']
    ]]);
    exit;
}

// 用户列表
if ($action === 'list') {
    $r = mysqli_query($conn, "SELECT id, username, nickname, avatar, status FROM ai_users ORDER BY id DESC");
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
    echo json_encode(['code'=>1, 'data'=>$list]);
    exit;
}

// 获取用户
if ($action === 'me') {
    $user_id = intval($_GET['user_id'] ?? 0);
    $r = mysqli_query($conn, "SELECT id, username, nickname, avatar FROM ai_users WHERE id=$user_id");
    if ($row = mysqli_fetch_assoc($r)) {
        echo json_encode(['code'=>1, 'data'=>$row]);
    } else {
        echo json_encode(['code'=>0, 'msg'=>'用户不存在']);
    }
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);
