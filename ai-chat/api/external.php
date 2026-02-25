<?php
/**
 * 外部助手接入API
 * 其他OpenClaw安装后可以通过这个API接入
 */

$memory_file = '/tmp/gdq_shared_memory.json';
$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
mysqli_query($conn, "SET NAMES utf8mb4");

function readMemory() {
    global $memory_file;
    $content = @file_get_contents($memory_file);
    return $content ? json_decode($content, true) : [];
}

function writeMemory($entry) {
    global $memory_file;
    $memory = readMemory();
    $memory[] = $entry;
    if (count($memory) > 100) $memory = array_slice($memory, -100);
    file_put_contents($memory_file, json_encode($memory, JSON_UNESCAPED_UNICODE));
}

$action = $_GET['action'] ?? '';

// 注册外部助手
if ($action === 'register') {
    $assistant_name = mysqli_real_escape_string($conn, $_POST['assistant_name'] ?? '');
    $owner_id = intval($_POST['owner_id'] ?? 0);
    $endpoint = mysqli_real_escape_string($conn, $_POST['endpoint'] ?? '');
    
    if (!$assistant_name || !$owner_id) {
        echo json_encode(['code'=>0, 'msg'=>'信息不完整']); exit;
    }
    
    // 生成密钥
    $secret = bin2hex(random_bytes(16));
    $now = time();
    
    mysqli_query($conn, "INSERT INTO ai_external_assistants (assistant_name, endpoint_url, owner_id, secret_key, create_time) VALUES ('$assistant_name', '$endpoint', $owner_id, '$secret', $now)");
    
    echo json_encode(['code'=>1, 'data'=>[
        'secret_key' => $secret,
        'assistant_id' => mysqli_insert_id($conn)
    ]]);
    exit;
}

// 验证助手
if ($action === 'verify') {
    $secret = $_GET['secret'] ?? '';
    
    $r = mysqli_query($conn, "SELECT * FROM ai_external_assistants WHERE secret_key='$secret' AND status='active'");
    if ($assistant = mysqli_fetch_assoc($r)) {
        echo json_encode(['code'=>1, 'data'=>$assistant]);
    } else {
        echo json_encode(['code'=>0, 'msg'=>'无效的密钥']);
    }
    exit;
}

// 外部助手发送消息
if ($action === 'send_message') {
    $secret = $_POST['secret'] ?? '';
    $content = $_POST['content'] ?? '';
    $role = $_POST['role'] ?? 'user'; // user 或 assistant
    
    // 验证助手
    $r = mysqli_query($conn, "SELECT * FROM ai_external_assistants WHERE secret_key='$secret' AND status='active'");
    if (!$assistant = mysqli_fetch_assoc($r)) {
        echo json_encode(['code'=>0, 'msg'=>'验证失败']); exit;
    }
    
    if (!$content) {
        echo json_encode(['code'=>0, 'msg'=>'内容为空']); exit;
    }
    
    // 保存消息
    writeMemory([
        'role' => $role,
        'content' => $content,
        'source' => 'external:' . $assistant['assistant_name'],
        'time' => time()
    ]);
    
    echo json_encode(['code'=>1, 'msg'=>'已保存']);
    exit;
}

// 获取对话上下文
if ($action === 'get_context') {
    $secret = $_GET['secret'] ?? '';
    
    $r = mysqli_query($conn, "SELECT * FROM ai_external_assistants WHERE secret_key='$secret'");
    if (!$assistant = mysqli_fetch_assoc($r)) {
        echo json_encode(['code'=>0, 'msg'=>'验证失败']); exit;
    }
    
    $memory = readMemory();
    $context = '';
    foreach (array_slice($memory, -10) as $m) {
        $context .= $m['role'] . ': ' . $m['content'] . "\n";
    }
    
    echo json_encode(['code'=>1, 'context'=>$context, 'assistant_name'=>$assistant['assistant_name']]);
    exit;
}

// 获取助手列表（给主人看）
if ($action === 'my_assistants') {
    $owner_id = intval($_GET['owner_id'] ?? 0);
    
    $r = mysqli_query($conn, "SELECT id, assistant_name, endpoint_url, status, create_time FROM ai_external_assistants WHERE owner_id=$owner_id");
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
    
    echo json_encode(['code'=>1, 'data'=>$list]);
    exit;
}

// 删除助手
if ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    mysqli_query($conn, "UPDATE ai_external_assistants SET status='deleted' WHERE id=$id");
    echo json_encode(['code'=>1, 'msg'=>'已删除']);
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);
