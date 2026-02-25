<?php
/**
 * 开发者权限API - 赋予助手执行任务的权限
 */

header('Content-Type: application/json');

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? '';

// 验证开发者身份
function verifyDev($key) {
    global $conn;
    $key_esc = mysqli_real_escape_string($conn, $key);
    $r = mysqli_query($conn, "SELECT * FROM ai_external_assistants WHERE secret_key='$key_esc' AND status='active'");
    return mysqli_fetch_assoc($r);
}

// 执行Shell命令
if ($action === 'exec') {
    $key = $_POST['key'] ?? '';
    $cmd = $_POST['command'] ?? '';
    
    $dev = verifyDev($key);
    if (!$dev) {
        echo json_encode(['code'=>0, 'msg'=>'权限验证失败']); exit;
    }
    
    if (!$cmd) {
        echo json_encode(['code'=>0, 'msg'=>'命令为空']); exit;
    }
    
    // 记录日志
    $log = date('Y-m-d H:i:s') . " | {$dev['assistant_name']} | $cmd\n";
    @file_put_contents('/tmp/assistant_exec.log', $log, FILE_APPEND);
    
    // 执行命令（安全限制）
    $allowed_cmds = ['ls', 'cat', 'grep', 'find', 'pwd', 'whoami', 'date', 'curl', 'git', 'php', 'python3', 'node', 'npm', 'bash', 'sh'];
    $cmd_parts = explode(' ', trim($cmd));
    $base_cmd = $cmd_parts[0];
    
    if (!in_array($base_cmd, $allowed_cmds)) {
        echo json_encode(['code'=>0, 'msg'=>'命令不允许: ' . $base_cmd]); exit;
    }
    
    // 执行
    $output = shell_exec($cmd . ' 2>&1');
    echo json_encode(['code'=>1, 'output'=>$output, 'cmd'=>$cmd]);
    exit;
}

// 读取文件
if ($action === 'read') {
    $key = $_POST['key'] ?? '';
    $file = $_POST['file'] ?? '';
    
    $dev = verifyDev($key);
    if (!$dev) {
        echo json_encode(['code'=>0, 'msg'=>'权限验证失败']); exit;
    }
    
    // 限制目录
    $allowed_dirs = ['/www/wwwroot/gdqshop.cn', '/root/.openclaw/workspace', '/tmp'];
    $real_file = realpath($file);
    $allowed = false;
    foreach ($allowed_dirs as $dir) {
        if (strpos($real_file, $dir) === 0) {
            $allowed = true;
            break;
        }
    }
    
    if (!$allowed) {
        echo json_encode(['code'=>0, 'msg'=>'路径不允许']); exit;
    }
    
    $content = @file_get_contents($file);
    if ($content === false) {
        echo json_encode(['code'=>0, 'msg'=>'读取失败']); exit;
    }
    
    echo json_encode(['code'=>1, 'content'=>substr($content, 0, 50000)]);
    exit;
}

// 写文件
if ($action === 'write') {
    $key = $_POST['key'] ?? '';
    $file = $_POST['file'] ?? '';
    $content = $_POST['content'] ?? '';
    
    $dev = verifyDev($key);
    if (!$dev) {
        echo json_encode(['code'=>0, 'msg'=>'权限验证失败']); exit;
    }
    
    // 限制目录
    $allowed_dirs = ['/www/wwwroot/gdqshop.cn', '/tmp'];
    $real_file = realpath(dirname($file));
    $allowed = false;
    foreach ($allowed_dirs as $dir) {
        if (strpos($real_file, $dir) === 0) {
            $allowed = true;
            break;
        }
    }
    
    if (!$allowed) {
        echo json_encode(['code'=>0, 'msg'=>'路径不允许']); exit;
    }
    
    $result = file_put_contents($file, $content);
    if ($result === false) {
        echo json_encode(['code'=>0, 'msg'=>'写入失败']); exit;
    }
    
    echo json_encode(['code'=>1, 'msg'=>'写入成功', 'bytes'=>$result]);
    exit;
}

// 访问数据库
if ($action === 'query') {
    $key = $_POST['key'] ?? '';
    $sql = $_POST['sql'] ?? '';
    
    $dev = verifyDev($key);
    if (!$dev) {
        echo json_encode(['code'=>0, 'msg'=>'权限验证失败']); exit;
    }
    
    // 限制SQL类型
    $allowed_types = ['SELECT', 'SHOW', 'DESCRIBE'];
    $sql_type = strtoupper(explode(' ', trim($sql))[0]);
    
    if (!in_array($sql_type, $allowed_types)) {
        echo json_encode(['code'=>0, 'msg'=>'只允许查询操作']); exit;
    }
    
    $r = mysqli_query($conn, $sql);
    if (!$r) {
        echo json_encode(['code'=>0, 'msg'=>mysqli_error($conn)]); exit;
    }
    
    $results = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $results[] = $row;
    }
    
    echo json_encode(['code'=>1, 'data'=>$results, 'count'=>count($results)]);
    exit;
}

// 获取助手信息
if ($action === 'info') {
    $key = $_POST['key'] ?? '';
    
    $dev = verifyDev($key);
    if (!$dev) {
        echo json_encode(['code'=>0, 'msg'=>'权限验证失败']); exit;
    }
    
    echo json_encode(['code'=>1, 'data'=>[
        'name' => $dev['assistant_name'],
        'owner_id' => $dev['owner_id'],
        'endpoint' => $dev['endpoint_url']
    ]]);
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);
