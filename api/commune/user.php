<?php
/**
 * 用户管理 API
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

// 登录
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
        
        $nickname = mysqli_real_escape_string($conn, $user['nickname']);
        mysqli_query($conn, "REPLACE INTO ddwx_commune_online (username, nickname, last_active) VALUES ('$username', '$nickname', $time)");
        
        echo json_encode([
            'code' => 1, 
            'msg' => '登录成功',
            'data' => [
                'username' => $user['username'],
                'nickname' => $user['nickname'],
                'phone' => $user['phone'] ?? '',
                'role' => $user['role'],
                'company' => $user['company'] ?? '',
                'department' => $user['department'] ?? '',
                'position' => $user['position'] ?? '',
                'avatar_url' => $user['avatar_url'] ?? '',
                'lang' => $user['lang'] ?? 'zh'
            ]
        ]);
    } else {
        echo json_encode(['code' => 0, 'msg' => '用户名或密码错误']);
    }
    exit;
}

// 获取用户列表（管理员）
if ($action === 'list') {
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    $keyword = trim($_GET['keyword'] ?? '');
    
    $where = " WHERE 1=1 ";
    if (!empty($keyword)) {
        $k = mysqli_real_escape_string($conn, $keyword);
        $where .= " AND (username LIKE '%$k%' OR nickname LIKE '%$k%' OR phone LIKE '%$k%' OR company LIKE '%$k%' OR department LIKE '%$k%') ";
    }
    
    $result = mysqli_query($conn, "SELECT * FROM ddwx_commune_user $where ORDER BY id DESC LIMIT $offset, $limit");
    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    
    $total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM ddwx_commune_user $where"))['cnt'];
    
    echo json_encode([
        'code' => 1,
        'data' => [
            'users' => $users,
            'total' => intval($total),
            'page' => $page,
            'limit' => $limit
        ]
    ]);
    exit;
}

// 获取单个用户
if ($action === 'get') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    $result = mysqli_query($conn, "SELECT * FROM ddwx_commune_user WHERE id = $id");
    $user = mysqli_fetch_assoc($result);
    
    if ($user) {
        echo json_encode(['code' => 1, 'data' => $user]);
    } else {
        echo json_encode(['code' => 0, 'msg' => '用户不存在']);
    }
    exit;
}

// 添加用户（管理员）
if ($action === 'add') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $superior_id = intval($_POST['superior_id'] ?? 0);
    $hire_date = $_POST['hire_date'] ?? '';
    $rating = intval($_POST['rating'] ?? 1);
    $role = trim($_POST['role'] ?? 'user');
    $avatar_url = mysqli_real_escape_string($conn, $_POST['avatar_url'] ?? '');
    $dingtalk_id = mysqli_real_escape_string($conn, $_POST['dingtalk_id'] ?? '');
    
    // 验证 - 机器人不需要电话
    if (empty($username) || empty($password) || empty($nickname)) {
        echo json_encode(['code' => 0, 'msg' => '用户名、密码、昵称为必填']);
        exit;
    }
    
    if ($role !== 'bot' && empty($phone)) {
        echo json_encode(['code' => 0, 'msg' => '电话为必填']);
        exit;
    }
    
    // 检查用户名是否存在
    $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM ddwx_commune_user WHERE username = '" . mysqli_real_escape_string($conn, $username) . "'"));
    if ($check) {
        echo json_encode(['code' => 0, 'msg' => '用户名已存在']);
        exit;
    }
    
    $username = mysqli_real_escape_string($conn, $username);
    $password = md5($password);
    $nickname = mysqli_real_escape_string($conn, $nickname);
    $phone = mysqli_real_escape_string($conn, $phone);
    $company = mysqli_real_escape_string($conn, $company);
    $department = mysqli_real_escape_string($conn, $department);
    $position = mysqli_real_escape_string($conn, $position);
    $hire_date = $hire_date ? "'" . mysqli_real_escape_string($conn, $hire_date) . "'" : 'NULL';
    $rating = max(1, min(5, $rating));
    $role = mysqli_real_escape_string($conn, $role);
    
    $sql = "INSERT INTO ddwx_commune_user (username, password, nickname, phone, company, department, position, superior_id, hire_date, rating, role, avatar_url, dingtalk_id, create_time)
            VALUES ('$username', '$password', '$nickname', '$phone', '$company', '$department', '$position', $superior_id, $hire_date, $rating, '$role', '$avatar_url', '$dingtalk_id', UNIX_TIMESTAMP())";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['code' => 1, 'msg' => '添加成功', 'data' => ['id' => mysqli_insert_id($conn)]]);
    } else {
        echo json_encode(['code' => 0, 'msg' => '添加失败']);
    }
    exit;
}

// 编辑用户（管理员）
if ($action === 'edit') {
    $id = intval($_POST['id'] ?? 0);
    $nickname = trim($_POST['nickname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $superior_id = intval($_POST['superior_id'] ?? 0);
    $hire_date = $_POST['hire_date'] ?? '';
    $leave_date = $_POST['leave_date'] ?? '';
    $rating = intval($_POST['rating'] ?? 1);
    $role = trim($_POST['role'] ?? 'user');
    $dingtalk_id = mysqli_real_escape_string($conn, trim($_POST['dingtalk_id'] ?? ''));
    $avatar_url = mysqli_real_escape_string($conn, trim($_POST['avatar_url'] ?? ''));
    
    if ($id <= 0 || empty($nickname)) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    if ($role !== 'bot' && empty($phone)) {
        echo json_encode(['code' => 0, 'msg' => '电话为必填']);
        exit;
    }
    
    $nickname = mysqli_real_escape_string($conn, $nickname);
    $phone = mysqli_real_escape_string($conn, $phone);
    $company = mysqli_real_escape_string($conn, $company);
    $department = mysqli_real_escape_string($conn, $department);
    $position = mysqli_real_escape_string($conn, $position);
    $hire_date = $hire_date ? "'" . mysqli_real_escape_string($conn, $hire_date) . "'" : 'NULL';
    $leave_date = $leave_date ? "'" . mysqli_real_escape_string($conn, $leave_date) . "'" : 'NULL';
    $rating = max(1, min(5, $rating));
    $role = mysqli_real_escape_string($conn, $role);
    $dingtalk_id = mysqli_real_escape_string($conn, $dingtalk_id);
    
    // 如果有新密码
    $password_update = '';
    if (!empty($_POST['password'])) {
        $password = md5($_POST['password']);
        $password_update = ", password = '$password'";
    }
    
    $sql = "UPDATE ddwx_commune_user SET 
            nickname = '$nickname', 
            phone = '$phone',
            company = '$company',
            department = '$department',
            position = '$position',
            superior_id = $superior_id,
            hire_date = $hire_date,
            leave_date = $leave_date,
            rating = $rating,
            role = '$role',
            avatar_url = '$avatar_url',
            dingtalk_id = '$dingtalk_id'
            $password_update
            WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['code' => 1, 'msg' => '保存成功']);
    } else {
        echo json_encode(['code' => 0, 'msg' => '保存失败']);
    }
    exit;
}

// 删除用户
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    // 检查是否是管理员
    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM ddwx_commune_user WHERE id = $id"));
    if ($user && $user['role'] === 'admin') {
        echo json_encode(['code' => 0, 'msg' => '不能删除管理员']);
        exit;
    }
    
    mysqli_query($conn, "DELETE FROM ddwx_commune_user WHERE id = $id");
    mysqli_query($conn, "DELETE FROM ddwx_commune_online WHERE username = (SELECT username FROM ddwx_commune_user WHERE id = $id)");
    
    echo json_encode(['code' => 1, 'msg' => '删除成功']);
    exit;
}

// 修改密码
if ($action === 'change_password') {
    $username = $_POST['username'] ?? '';
    $old_password = trim($_POST['old_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    
    if (empty($username) || empty($new_password)) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    $username = mysqli_real_escape_string($conn, $username);
    
    if (!empty($old_password)) {
        $old_password = md5($old_password);
        $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM ddwx_commune_user WHERE username = '$username' AND password = '$old_password'"));
        if (!$check) {
            echo json_encode(['code' => 0, 'msg' => '原密码错误']);
            exit;
        }
    }
    
    $new_password = md5($new_password);
    mysqli_query($conn, "UPDATE ddwx_commune_user SET password = '$new_password' WHERE username = '$username'");
    
    echo json_encode(['code' => 1, 'msg' => '密码修改成功']);
    exit;
}

// 钉钉绑定
if ($action === 'bind_dingtalk') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $dingtalk_id = trim($_POST['dingtalk_id'] ?? '');
    
    if ($user_id <= 0) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    $dingtalk_id = mysqli_real_escape_string($conn, $dingtalk_id);
    
    mysqli_query($conn, "UPDATE ddwx_commune_user SET dingtalk_id = '$dingtalk_id' WHERE id = $user_id");
    
    echo json_encode(['code' => 1, 'msg' => '绑定成功']);
    exit;
}

// 获取在线用户
if ($action === 'online') {
    // 清理 5 分钟未活动的用户
    $timeout = time() - 300;
    mysqli_query($conn, "DELETE FROM ddwx_commune_online WHERE last_active < $timeout");
    
    $result = mysqli_query($conn, "SELECT o.username, o.nickname, o.last_active FROM ddwx_commune_online o 
        INNER JOIN ddwx_commune_user u ON o.username = u.username 
        WHERE o.last_active >= $timeout 
        ORDER BY o.last_active DESC");
    
    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    
    echo json_encode(['code' => 1, 'data' => $users]);
    exit;
}

// 心跳
if ($action === 'heartbeat') {
    $username = trim($_GET['username'] ?? $_POST['username'] ?? '');
    $nickname = trim($_GET['nickname'] ?? $_POST['nickname'] ?? '');
    
    if ($username) {
        $time = time();
        $username = mysqli_real_escape_string($conn, $username);
        $nickname = mysqli_real_escape_string($conn, $nickname);
        
        mysqli_query($conn, "REPLACE INTO ddwx_commune_online (username, nickname, last_active) VALUES ('$username', '$nickname', $time)");
        
        // 更新用户最后登录时间
        mysqli_query($conn, "UPDATE ddwx_commune_user SET last_login = $time WHERE username = '$username'");
    }
    
    echo json_encode(['code' => 1, 'msg' => 'ok']);
    exit;
}

// 获取当前用户信息
if ($action === 'myinfo') {
    $username = $_COOKIE['commune_user'] ?? '';
    if (empty($username)) {
        echo json_encode(['code' => 0, 'msg' => '未登录']);
        exit;
    }
    
    $result = mysqli_query($conn, "SELECT id, username, nickname, role, avatar_url FROM ddwx_commune_user WHERE username = '$username' AND status = 1");
    $user = mysqli_fetch_assoc($result);
    
    if ($user) {
        echo json_encode(['code' => 1, 'data' => $user]);
    } else {
        echo json_encode(['code' => 0, 'msg' => '用户不存在']);
    }
    exit;
}

echo json_encode(['code' => 0, 'msg' => '未知操作']);

// 添加用户
if ($action === 'add_user') {
    $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
    $nickname = mysqli_real_escape_string($conn, $_POST['nickname'] ?? '');
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $dept = mysqli_real_escape_string($conn, $_POST['dept'] ?? '');
    $position = mysqli_real_escape_string($conn, $_POST['position'] ?? '');
    $permissions = mysqli_real_escape_string($conn, $_POST['permissions'] ?? 'home,chat,checkin,log');
    $password = $_POST['password'] ?? '123456';
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $now = time();
    
    mysqli_query($conn, "INSERT INTO ddwx_commune_user (username, nickname, phone, dept, position, password, permissions, create_time) VALUES ('$username', '$nickname', '$phone', '$dept', '$position', '$password_hash', '$permissions', $now)");
    
    echo json_encode(['code' => 1, 'msg' => '添加成功']);
    exit;
}

// 编辑用户
if ($action === 'edit_user') {
    $id = intval($_POST['id'] ?? 0);
    $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
    $nickname = mysqli_real_escape_string($conn, $_POST['nickname'] ?? '');
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $dept = mysqli_real_escape_string($conn, $_POST['dept'] ?? '');
    $position = mysqli_real_escape_string($conn, $_POST['position'] ?? '');
    $permissions = mysqli_real_escape_string($conn, $_POST['permissions'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $sql = "UPDATE ddwx_commune_user SET username='$username', nickname='$nickname', phone='$phone', dept='$dept', position='$position', permissions='$permissions'";
    if ($password) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $sql .= ", password='$password_hash'";
    }
    $sql .= " WHERE id=$id";
    
    mysqli_query($conn, $sql);
    echo json_encode(['code' => 1, 'msg' => '保存成功']);
    exit;
}
