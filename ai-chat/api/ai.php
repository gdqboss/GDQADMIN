<?php
header('Content-Type: application/json');

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
if (!$conn) { echo json_encode(['code'=>0, 'msg'=>'DB error']); exit; }
mysqli_query($conn, "SET NAMES utf8mb4");

$action = $_GET['action'] ?? '';

// AI对话
if ($action === 'chat') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $message = $_POST['message'] ?? '';
    
    if (!$user_id || !$message) {
        echo json_encode(['code'=>0, 'msg'=>'参数不全']); exit;
    }
    
    // 保存用户消息
    $now = time();
    $msg_esc = mysqli_real_escape_string($conn, $message);
    mysqli_query($conn, "INSERT INTO ai_conversations (user_id, role, content, create_time) VALUES ($user_id, 'user', '$msg_esc', $now)");
    
    // 尝试调用OpenClaw API
    $ai_reply = callOpenClaw($message, $user_id);
    
    // 保存AI回复
    $reply_esc = mysqli_real_escape_string($conn, $ai_reply);
    mysqli_query($conn, "INSERT INTO ai_conversations (user_id, role, content, create_time) VALUES ($user_id, 'assistant', '$reply_esc', $now)");
    
    echo json_encode(['code'=>1, 'data'=>['reply'=>$ai_reply]]);
    exit;
}

// 获取对话历史
if ($action === 'history') {
    $user_id = intval($_GET['user_id'] ?? 0);
    $limit = intval($_GET['limit'] ?? 20);
    
    $r = mysqli_query($conn, "SELECT role, content, create_time FROM ai_conversations WHERE user_id=$user_id ORDER BY create_time DESC LIMIT $limit");
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
    echo json_encode(['code'=>1, 'data'=>array_reverse($list)]);
    exit;
}

// 总结群聊
if ($action === 'summarize') {
    echo json_encode(['code'=>1, 'data'=>['summary'=>'请使用AI助手功能进行智能总结']]);
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);

// 调用OpenClaw的函数
function callOpenClaw($message, $user_id) {
    // 尝试通过Telegram发送消息给自己（触发AI回复）
    // 或者使用其他方式
    
    // 简单模拟回复（等OpenClaw API修复后改为真实调用）
    $replies = [
        "好的，我明白了！还有什么需要帮忙的吗？",
        "收到！我正在学习更多知识来帮助你。",
        "这个问题很有意思，让我们一起探讨一下。",
        "我理解你的意思。有什么具体需要我做的吗？",
        "明白了！还有其他问题吗？",
    ];
    
    // 检查消息中是否有特定关键词
    $msg_lower = mb_strtolower($message, 'utf-8');
    
    if (strpos($msg_lower, '你好') !== false || strpos($msg_lower, 'hi') !== false || strpos($msg_lower, 'hello') !== false) {
        return "你好！我是江小鱼AI助手，很高兴见到你！有什么我可以帮你的吗？";
    }
    
    if (strpos($msg_lower, '名字') !== false) {
        return "我叫江小鱼！是彩美特企业的AI助手。";
    }
    
    if (strpos($msg_lower, '天气') !== false) {
        return "抱歉，我暂时还查不到天气信息。但你可以告诉我你在哪个城市，下次我学会了可以帮你查！";
    }
    
    if (strpos($msg_lower, '时间') !== false || strpos($msg_lower, '几点') !== false) {
        return "现在时间是 " . date("Y年m月d日 H:i:s");
    }
    
    // 随机回复
    return $replies[array_rand($replies)];
}
