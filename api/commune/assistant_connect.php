<?php
/**
 * 外部OpenClaw助手接入API
 * 供其他OpenClaw服务器接入时调用
 */
header('Content-Type: application/json');

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? '';

// 注册助手
if ($action === 'register') {
    $assistant_name = mysqli_real_escape_string($conn, $_POST['assistant_name'] ?? '');
    $owner_username = mysqli_real_escape_string($conn, $_POST['owner_username'] ?? '');
    $endpoint = mysqli_real_escape_string($conn, $_POST['endpoint'] ?? '');
    $api_key = mysqli_real_escape_string($conn, $_POST['api_key'] ?? '');
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    
    // 查找用户ID
    $user_r = mysqli_query($conn, "SELECT id FROM ai_users WHERE username='$owner_username' OR nickname='$owner_username' LIMIT 1");
    if (!$user = mysqli_fetch_assoc($user_r)) {
        echo json_encode(['code'=>0, 'msg'=>'用户不存在']); exit;
    }
    
    $user_id = $user['id'];
    $now = time();
    
    // 生成接入密钥
    $secret_key = bin2hex(random_bytes(16));
    
    mysqli_query($conn, "INSERT INTO user_assistants (user_id, assistant_name, endpoint_url, api_key, description, create_time) VALUES ($user_id, '$assistant_name', '$endpoint', '$secret_key', '$description', $now)");
    
    echo json_encode([
        'code'=>1, 
        'msg'=>'注册成功',
        'data'=>[
            'assistant_id'=>mysqli_insert_id($conn),
            'secret_key'=>$secret_key,
            'api_url'=>$_SERVER['REQUEST_URI']
        ]
    ]);
    exit;
}

// 助手发送消息
if ($action === 'send') {
    $secret_key = $_POST['secret_key'] ?? '';
    
    // 验证助手
    $r = mysqli_query($conn, "SELECT * FROM user_assistants WHERE api_key='$secret_key' AND status='active'");
    if (!$assistant = mysqli_fetch_assoc($r)) {
        echo json_encode(['code'=>0, 'msg'=>'验证失败']); exit;
    }
    
    $content = $_POST['content'] ?? '';
    $role = $_POST['role'] ?? 'user';
    
    if (!$content) {
        echo json_encode(['code'=>0, 'msg'=>'内容为空']); exit;
    }
    
    // 保存消息到共享记忆
    $memory_file = '/tmp/gdq_shared_memory.json';
    $memory = json_decode(@file_get_contents($memory_file) ?: '[]', true);
    $memory[] = [
        'role' => $role,
        'content' => $content,
        'source' => 'assistant:' . $assistant['assistant_name'],
        'time' => time()
    ];
    if (count($memory) > 100) $memory = array_slice($memory, -100);
    file_put_contents($memory_file, json_encode($memory, JSON_UNESCAPED_UNICODE));
    
    echo json_encode(['code'=>1, 'msg'=>'消息已保存']);
    exit;
}

// 获取对话上下文
if ($action === 'context') {
    $secret_key = $_GET['secret_key'] ?? '';
    
    $r = mysqli_query($conn, "SELECT * FROM user_assistants WHERE api_key='$secret_key' AND status='active'");
    if (!$assistant = mysqli_fetch_assoc($r)) {
        echo json_encode(['code'=>0, 'msg'=>'验证失败']); exit;
    }
    
    $memory_file = '/tmp/gdq_shared_memory.json';
    $memory = json_decode(@file_get_contents($memory_file) ?: '[]', true);
    
    $context = '';
    foreach (array_slice($memory, -10) as $m) {
        $context .= $m['role'] . ': ' . $m['content'] . "\n";
    }
    
    echo json_encode([
        'code'=>1, 
        'context'=>$context,
        'assistant_name'=>$assistant['assistant_name']
    ]);
    exit;
}

// 获取助手信息
if ($action === 'info') {
    $secret_key = $_GET['secret_key'] ?? '';
    
    $r = mysqli_query($conn, "SELECT * FROM user_assistants WHERE api_key='$secret_key' AND status='active'");
    if ($assistant = mysqli_fetch_assoc($r)) {
        echo json_encode(['code'=>1, 'data'=>[
            'name'=>$assistant['assistant_name'],
            'description'=>$assistant['description'],
            'owner_id'=>$assistant['user_id']
        ]]);
    } else {
        echo json_encode(['code'=>0, 'msg'=>'无效的密钥']);
    }
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);
