<?php
/**
 * 用户资料 API - 完整版
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

// 获取用户资料
if ($action === 'profile') {
    $username = $_GET['username'] ?? '';
    
    if (empty($username)) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    $username = mysqli_real_escape_string($conn, $username);
    $result = mysqli_query($conn, "SELECT id, username, nickname, avatar, role, company, department, position, login_type FROM ddwx_commune_user WHERE username = '$username'");
    $user = mysqli_fetch_assoc($result);
    
    if ($user) {
        echo json_encode(['code' => 1, 'data' => $user]);
    } else {
        echo json_encode(['code' => 0, 'msg' => '用户不存在']);
    }
    exit;
}

// 更新用户资料
if ($action === 'update_profile') {
    $username = $_POST['username'] ?? '';
    $nickname = trim($_POST['nickname'] ?? '');
    $avatar = trim($_POST['avatar'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $position = trim($_POST['position'] ?? '');
    
    if (empty($username)) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    $username = mysqli_real_escape_string($conn, $username);
    $nickname = mysqli_real_escape_string($conn, $nickname);
    $avatar = mysqli_real_escape_string($conn, $avatar);
    $company = mysqli_real_escape_string($conn, $company);
    $department = mysqli_real_escape_string($conn, $department);
    $position = mysqli_real_escape_string($conn, $position);
    
    $updates = [];
    if ($nickname) $updates[] = "nickname = '$nickname'";
    if ($avatar) $updates[] = "avatar = '$avatar'";
    if ($company) $updates[] = "company = '$company'";
    if ($department) $updates[] = "department = '$department'";
    if ($position) $updates[] = "position = '$position'";
    
    if (!empty($password)) {
        $password = md5($password);
        $updates[] = "password = '$password'";
    }
    
    if (!empty($updates)) {
        $sql = "UPDATE ddwx_commune_user SET " . implode(', ', $updates) . " WHERE username = '$username'";
        mysqli_query($conn, $sql);
    }
    
    echo json_encode(['code' => 1, 'msg' => '更新成功']);
    exit;
}

// 钉钉扫码登录（待开发）
if ($action === 'dingtalk_login') {
    $code = $_GET['code'] ?? '';
    
    if (empty($code)) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    // TODO: 调用钉钉 API 换取用户信息
    // 这里需要配置钉钉的 AppKey 和 AppSecret
    
    echo json_encode(['code' => 0, 'msg' => '钉钉登录功能开发中']);
    exit;
}

// 登录（返回完整资料）
if ($action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        echo json_encode(['code' => 0, 'msg' => '用户名和密码不能为空']);
        exit;
    }
    
    $username = mysqli_real_escape_string($conn, $username);
    $password = md5($password);
    
    $result = mysqli_query($conn, "SELECT * FROM ddwx_commune_user WHERE username = '$username' AND password = '$password' AND status = 1");
    $user = mysqli_fetch_assoc($result);
    
    if ($user) {
        $time = time();
        mysqli_query($conn, "UPDATE ddwx_commune_user SET last_login = $time WHERE id = {$user['id']}");
        
        // 更新在线状态
        mysqli_query($conn, "REPLACE INTO ddwx_commune_online (username, nickname, last_active) VALUES ('$username', '{$user['nickname']}', $time)");
        
        echo json_encode([
            'code' => 1, 
            'msg' => '登录成功',
            'data' => [
                'username' => $user['username'],
                'nickname' => $user['nickname'],
                'role' => $user['role'],
                'company' => $user['company'] ?? '',
                'department' => $user['department'] ?? '',
                'position' => $user['position'] ?? '',
                'login_type' => $user['login_type'] ?? 'password',
                'lang' => $user['lang'] ?? 'zh'
            ]
        ]);
    } else {
        echo json_encode(['code' => 0, 'msg' => '用户名或密码错误']);
    }
    exit;
}

// 设置语言
if ($action === 'set_lang') {
    $username = $_POST['username'] ?? '';
    $lang = $_POST['lang'] ?? 'zh';
    
    if (empty($username)) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    $username = mysqli_real_escape_string($conn, $username);
    $lang = mysqli_real_escape_string($conn, $lang);
    
    mysqli_query($conn, "UPDATE ddwx_commune_user SET lang = '$lang' WHERE username = '$username'");
    
    echo json_encode(['code' => 1, 'msg' => '语言已保存']);
    exit;
}

echo json_encode(['code' => 0, 'msg' => '未知操作']);

// 更新个人资料（用户只能修改自己的）
if ($action === 'update_my_profile') {
    $username = $_POST['username'] ?? '';
    $nickname = trim($_POST['nickname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $avatar_url = trim($_POST['avatar_url'] ?? '');
    $old_password = trim($_POST['old_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    
    if (empty($username)) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    $username = mysqli_real_escape_string($conn, $username);
    $nickname = mysqli_real_escape_string($conn, $nickname);
    $phone = mysqli_real_escape_string($conn, $phone);
    $avatar_url = mysqli_real_escape_string($conn, $avatar_url);
    
    $updates = [];
    if ($nickname) $updates[] = "nickname = '$nickname'";
    if ($phone) $updates[] = "phone = '$phone'";
    if ($avatar_url) $updates[] = "avatar_url = '$avatar_url'";
    
    // 修改密码
    if (!empty($old_password) && !empty($new_password)) {
        $old_password = md5($old_password);
        $new_password = md5($new_password);
        
        $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM ddwx_commune_user WHERE username = '$username' AND password = '$old_password'"));
        if (!$check) {
            echo json_encode(['code' => 0, 'msg' => '原密码错误']);
            exit;
        }
        $updates[] = "password = '$new_password'";
    }
    
    if (!empty($updates)) {
        $sql = "UPDATE ddwx_commune_user SET " . implode(', ', $updates) . " WHERE username = '$username'";
        mysqli_query($conn, $sql);
    }
    
    echo json_encode(['code' => 1, 'msg' => '更新成功']);
    exit;
}
