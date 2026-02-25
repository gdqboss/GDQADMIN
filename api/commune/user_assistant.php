<?php
/**
 * 用户助手关联API
 * 用于管理用户关联的OpenClaw助手
 */
header('Content-Type: application/json');

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? '';

// 获取用户的助手列表
if ($action === 'list') {
    $user_id = intval($_GET['user_id'] ?? 0);
    $r = mysqli_query($conn, "SELECT * FROM user_assistants WHERE user_id=$user_id AND status='active'");
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
    echo json_encode(['code'=>1, 'data'=>$list]);
    exit;
}

// 添加助手关联
if ($action === 'add') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $name = mysqli_real_escape_string($conn, $_POST['assistant_name'] ?? '');
    $endpoint = mysqli_real_escape_string($conn, $_POST['endpoint_url'] ?? '');
    $api_key = mysqli_real_escape_string($conn, $_POST['api_key'] ?? '');
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    
    if (!$user_id || !$name) {
        echo json_encode(['code'=>0, 'msg'=>'信息不完整']); exit;
    }
    
    $now = time();
    mysqli_query($conn, "INSERT INTO user_assistants (user_id, assistant_name, endpoint_url, api_key, description, create_time) VALUES ($user_id, '$name', '$endpoint', '$api_key', '$description', $now)");
    
    echo json_encode(['code'=>1, 'msg'=>'添加成功', 'id'=>mysqli_insert_id($conn)]);
    exit;
}

// 更新助手
if ($action === 'update') {
    $id = intval($_POST['id'] ?? 0);
    $name = mysqli_real_escape_string($conn, $_POST['assistant_name'] ?? '');
    $endpoint = mysqli_real_escape_string($conn, $_POST['endpoint_url'] ?? '');
    $api_key = mysqli_real_escape_string($conn, $_POST['api_key'] ?? '');
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    
    $sql = "UPDATE user_assistants SET assistant_name='$name'";
    if ($endpoint) $sql .= ", endpoint_url='$endpoint'";
    if ($api_key) $sql .= ", api_key='$api_key'";
    if ($description) $sql .= ", description='$description'";
    $sql .= " WHERE id=$id";
    
    mysqli_query($conn, $sql);
    echo json_encode(['code'=>1, 'msg'=>'更新成功']);
    exit;
}

// 删除助手关联
if ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    mysqli_query($conn, "UPDATE user_assistants SET status='deleted' WHERE id=$id");
    echo json_encode(['code'=>1, 'msg'=>'删除成功']);
    exit;
}

// 验证助手连接
if ($action === 'test') {
    $id = intval($_GET['id'] ?? 0);
    $r = mysqli_query($conn, "SELECT * FROM user_assistants WHERE id=$id");
    $assistant = mysqli_fetch_assoc($r);
    
    if (!$assistant) {
        echo json_encode(['code'=>0, 'msg'=>'助手不存在']); exit;
    }
    
    // 尝试调用助手API
    if ($assistant['endpoint_url']) {
        $ch = curl_init($assistant['endpoint_url'] . '/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            echo json_encode(['code'=>1, 'msg'=>'连接成功']);
        } else {
            echo json_encode(['code'=>0, 'msg'=>'连接失败']);
        }
    } else {
        echo json_encode(['code'=>1, 'msg'=>'本地助手，无需测试']);
    }
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);
