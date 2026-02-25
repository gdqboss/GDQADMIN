<?php
/**
 * 任务队列系统
 * 助手接收任务 -> 存入队列 -> 我(OpenClaw)处理 -> 返回结果
 */
header('Content-Type: application/json');

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? '';

// 添加任务
if ($action === 'add') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $task = mysqli_real_escape_string($conn, $_POST['task'] ?? '');
    
    if (!$user_id || !$task) {
        echo json_encode(['code'=>0, 'msg'=>'信息不完整']); exit;
    }
    
    $now = time();
    mysqli_query($conn, "INSERT INTO assistant_tasks (user_id, task, status, create_time) VALUES ($user_id, '$task', 'pending', $now)");
    
    echo json_encode(['code'=>1, 'msg'=>'任务已添加到队列', 'task_id'=>mysqli_insert_id($conn)]);
    exit;
}

// 获取待处理任务
if ($action === 'pending') {
    $r = mysqli_query($conn, "SELECT * FROM assistant_tasks WHERE status='pending' ORDER BY create_time ASC LIMIT 10");
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
    echo json_encode(['code'=>1, 'data'=>$list]);
    exit;
}

// 获取我的任务结果
if ($action === 'my_results') {
    $user_id = intval($_GET['user_id'] ?? 0);
    $r = mysqli_query($conn, "SELECT * FROM assistant_tasks WHERE user_id=$user_id AND status='done' ORDER BY done_time DESC LIMIT 10");
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
    echo json_encode(['code'=>1, 'data'=>$list]);
    exit;
}

// 我处理完成（只有我能调用）
if ($action === 'complete') {
    $task_id = intval($_POST['task_id'] ?? 0);
    $result = mysqli_real_escape_string($conn, $_POST['result'] ?? '');
    
    if (!$task_id) {
        echo json_encode(['code'=>0, 'msg'=>'task_id required']); exit;
    }
    
    $now = time();
    mysqli_query($conn, "UPDATE assistant_tasks SET status='done', result='$result', done_time=$now WHERE id=$task_id");
    
    echo json_encode(['code'=>1, 'msg'=>'任务完成']);
    exit;
}

// 标记任务开始处理
if ($action === 'start') {
    $task_id = intval($_POST['task_id'] ?? 0);
    mysqli_query($conn, "UPDATE assistant_tasks SET status='processing' WHERE id=$task_id");
    echo json_encode(['code'=>1]);
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);
