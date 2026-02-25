<?php
/**
 * AI聊天 + 任务队列
 * 如果识别到需要执行的任务，添加到队列等待处理
 */
header('Content-Type: application/json');

$memory_file = '/tmp/gdq_shared_memory.json';
$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
mysqli_query($conn, "SET NAMES utf8mb4");

function readMemory() {
    global $memory_file;
    $c = @file_get_contents($memory_file);
    return $c ? json_decode($c, true) : [];
}

function writeMemory($entry) {
    global $memory_file;
    $memory = readMemory();
    $memory[] = $entry;
    if (count($memory) > 100) $memory = array_slice($memory, -100);
    file_put_contents($memory_file, json_encode($memory, JSON_UNESCAPED_UNICODE));
}

$action = $_GET['action'] ?? '';

// 聊天
if ($action === 'chat') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $message = $_POST['message'] ?? '';
    $username = $_POST['username'] ?? '用户';
    
    if (!$message) {
        echo json_encode(['code'=>0, 'msg'=>'消息为空']); exit;
    }
    
    // 记录用户消息
    writeMemory([
        'role' => 'user',
        'content' => $message,
        'source' => 'web',
        'time' => time()
    ]);
    
    // 获取上下文
    $memory = readMemory();
    $context = '';
    foreach (array_slice($memory, -8) as $m) {
        $context .= $m['role'] . ': ' . $m['content'] . "\n";
    }
    
    // 判断是否需要执行任务
    $needTask = shouldAddTask($message);
    
    if ($needTask) {
        // 添加到任务队列
        $task_esc = mysqli_real_escape_string($conn, $message);
        mysqli_query($conn, "INSERT INTO assistant_tasks (user_id, task, status, create_time) VALUES ($user_id, '$task_esc', 'pending', ".time().")");
        $taskId = mysqli_insert_id($conn);
        
        $reply = "好的，我已经把你的需求添加到任务队列。\n\n📝 任务内容：$message\n\n⏳ 我会尽快处理，完成后把结果返回给你。";
        
        // 保存助手回复
        writeMemory([
            'role' => 'assistant',
            'content' => $reply,
            'source' => 'web',
            'time' => time()
        ]);
        
        echo json_encode(['code'=>1, 'data'=>['reply'=>$reply, 'task_added'=>true, 'task_id'=>$taskId]]);
    } else {
        // 普通对话
        $reply = callAI($message, $context, $username);
        
        writeMemory([
            'role' => 'assistant',
            'content' => $reply,
            'source' => 'web',
            'time' => time()
        ]);
        
        echo json_encode(['code'=>1, 'data'=>['reply'=>$reply]]);
    }
    exit;
}

// 获取任务结果
if ($action === 'my_results') {
    $user_id = intval($_GET['user_id'] ?? 0);
    $r = mysqli_query($conn, "SELECT * FROM assistant_tasks WHERE user_id=$user_id AND status='done' ORDER BY done_time DESC LIMIT 5");
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
    echo json_encode(['code'=>1, 'data'=>$list]);
    exit;
}

// 获取待处理任务（供我调用）
if ($action === 'pending_tasks') {
    $r = mysqli_query($conn, "SELECT * FROM assistant_tasks WHERE status='pending' ORDER BY create_time ASC LIMIT 10");
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
    echo json_encode(['code'=>1, 'data'=>$list]);
    exit;
}

// 完成任务（我调用）
if ($action === 'complete_task') {
    $task_id = intval($_POST['task_id'] ?? 0);
    $result = mysqli_real_escape_string($conn, $_POST['result'] ?? '');
    
    if ($task_id) {
        mysqli_query($conn, "UPDATE assistant_tasks SET status='done', result='$result', done_time=".time()." WHERE id=$task_id");
    }
    echo json_encode(['code'=>1]);
    exit;
}

// 读取记忆
if ($action === 'memory') {
    $memory = readMemory();
    echo json_encode(['code'=>1, 'data'=>$memory]);
    exit;
}

// 清空记忆
if ($action === 'clear_memory') {
    file_put_contents($memory_file, '[]');
    echo json_encode(['code'=>1, 'msg'=>'记忆已清空']);
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);

// 判断是否需要添加任务
function shouldAddTask($message) {
    $keywords = [
        '开发', '修改', '添加', '删除', '更新', '修复', '创建',
        '查看', '检查', '分析', '统计',
        '部署', '上线', '测试',
        '执行', '运行', '操作',
        '配置', '设置', '安装',
        '优化', '提升', '改进',
        '文件', '代码', '数据库',
        '推送', '发送', '通知'
    ];
    
    $msg = mb_strtolower($message, 'utf-8');
    
    foreach ($keywords as $k) {
        if (mb_strpos($msg, mb_strtolower($k, 'utf-8')) !== false) {
            return true;
        }
    }
    
    return false;
}

function callAI($message, $context, $username) {
    $api_key = 'sk-cp-BbLwwqBSr8RrPusVeP8-U4_ezPtJS48rVjuMepMrxOZR4vcyRt_zD-OwhYcm7KKVnWT6nZxvi9q8zTsa1yC_mIaoqD4UyPjQn6xM4oOaoR5S0AHQut6jQtU';
    
    $system = "你是江小鱼，彩美特企业的AI助手。用户的朋友。";
    if ($context) $system .= "\n\n对话历史:\n" . $context;
    
    $ch = curl_init('https://api.minimax.chat/v1/text/chatcompletion_v2');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'abab6.5s-chat',
        'messages' => [
            ['role'=>'system', 'content'=>$system],
            ['role'=>'user', 'content'=>$message]
        ]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer '.$api_key, 'Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    return $result['choices'][0]['message']['content'] ?? '抱歉，我暂时无法回答。';
}
