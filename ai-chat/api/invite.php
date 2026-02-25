<?php
header('Content-Type: application/json');

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
if (!$conn) { echo json_encode(['code'=>0, 'msg'=>'DB error']); exit; }
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? '';

// 创建邀请码（老板用）
if ($action === 'create_invite') {
    $inviter_id = intval($_POST['inviter_id'] ?? 0);
    if (!$inviter_id) { echo json_encode(['code'=>0, 'msg'=>'请先登录']); exit; }
    
    // 生成邀请码
    $code = strtoupper(bin2hex(random_bytes(8)));
    $now = time();
    
    mysqli_query($conn, "INSERT INTO ai_user_invites (inviter_id, invite_code, create_time) VALUES ($inviter_id, '$code', $now)");
    
    $invite_link = "https://bot.gdqshop.cn/ai-chat/?invite=" . $code;
    
    echo json_encode(['code'=>1, 'data'=>['code'=>$code, 'link'=>$invite_link]]);
    exit;
}

// 使用邀请码注册
if ($action === 'register_with_invite') {
    $invite_code = strtoupper($_POST['invite_code'] ?? '');
    $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
    $nickname = mysqli_real_escape_string($conn, $_POST['nickname'] ?? $username);
    $password = $_POST['password'] ?? '';
    
    if (!$invite_code || !$username || !$password) {
        echo json_encode(['code'=>0, 'msg'=>'信息不完整']); exit;
    }
    
    // 检查邀请码
    $r = mysqli_query($conn, "SELECT * FROM ai_user_invites WHERE invite_code='$invite_code' AND status='pending'");
    if (!$invite = mysqli_fetch_assoc($r)) {
        echo json_encode(['code'=>0, 'msg'=>'邀请码无效或已使用']); exit;
    }
    
    // 创建用户
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $now = time();
    
    mysqli_query($conn, "INSERT INTO ai_users (username, nickname, password, create_time) VALUES ('$username', '$nickname', '$hash', $now)");
    $new_user_id = mysqli_insert_id($conn);
    
    // 关联到老板
    mysqli_query($conn, "INSERT INTO ai_user_links (owner_id, user_id, create_time) VALUES ({$invite['inviter_id']}, $new_user_id, $now)");
    
    // 标记邀请码已使用
    mysqli_query($conn, "UPDATE ai_user_invites SET status='used', use_time=$now WHERE id={$invite['id']}");
    
    echo json_encode(['code'=>1, 'msg'=>'注册成功！已关联到老板', 'data'=>['id'=>$new_user_id]]);
    exit;
}

// 获取我的下属列表（老板用）
if ($action === 'my_staff') {
    $owner_id = intval($_GET['owner_id'] ?? 0);
    
    $r = mysqli_query($conn, "SELECT u.id, u.username, u.nickname, ul.create_time as join_time 
        FROM ai_user_links ul 
        JOIN ai_users u ON ul.user_id = u.id 
        WHERE ul.owner_id=$owner_id");
    
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
    
    echo json_encode(['code'=>1, 'data'=>$list]);
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);
