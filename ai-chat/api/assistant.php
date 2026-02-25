<?php
header('Content-Type: application/json');

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
if (!$conn) { echo json_encode(['code'=>0, 'msg'=>'DB error']); exit; }
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? '';

// 获取助手列表
if ($action === 'list') {
    $r = mysqli_query($conn, "SELECT id, name, description, model, enabled FROM ai_assistants WHERE enabled=1");
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
    echo json_encode(['code'=>1, 'data'=>$list]);
    exit;
}

// 添加助手（仅老板）
if ($action === 'add') {
    // 验证老板身份（简化：通过user_id=1）
    $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    $api_url = mysqli_real_escape_string($conn, $_POST['api_url'] ?? '');
    $api_key = mysqli_real_escape_string($conn, $_POST['api_key'] ?? '');
    $model = mysqli_real_escape_string($conn, $_POST['model'] ?? '');
    
    if (!$name) { echo json_encode(['code'=>0, 'msg'=>'请输入助手名称']); exit; }
    
    $now = time();
    mysqli_query($conn, "INSERT INTO ai_assistants (name, description, api_url, api_key, model, create_time) VALUES ('$name', '$description', '$api_url', '$api_key', '$model', $now)");
    
    echo json_encode(['code'=>1, 'msg'=>'助手添加成功']);
    exit;
}

// 删除助手
if ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    mysqli_query($conn, "DELETE FROM ai_assistants WHERE id=$id AND owner_id=0");
    echo json_encode(['code'=>1, 'msg'=>'删除成功']);
    exit;
}

// 更新助手
if ($action === 'update') {
    $id = intval($_POST['id'] ?? 0);
    $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    $api_url = mysqli_real_escape_string($conn, $_POST['api_url'] ?? '');
    $api_key = mysqli_real_escape_string($conn, $_POST['api_key'] ?? '');
    $model = mysqli_real_escape_string($conn, $_POST['model'] ?? '');
    
    $sql = "UPDATE ai_assistants SET name='$name', description='$description'";
    if ($api_url) $sql .= ", api_url='$api_url'";
    if ($api_key) $sql .= ", api_key='$api_key'";
    if ($model) $sql .= ", model='$model'";
    $sql .= " WHERE id=$id";
    
    mysqli_query($conn, $sql);
    echo json_encode(['code'=>1, 'msg'=>'更新成功']);
    exit;
}

// 获取所有助手（包括禁用的）
if ($action === 'all') {
    $r = mysqli_query($conn, "SELECT * FROM ai_assistants ORDER BY id");
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
    echo json_encode(['code'=>1, 'data'=>$list]);
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);
