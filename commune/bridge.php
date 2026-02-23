<?php
/**
 * 聊天室消息转发 - 供外部调用
 * 
 * 使用方式：
 * http://bot.gdqshop.cn/commune/bridge.php?key=机器人key&msg=消息内容
 */

$botKey = $_GET['key'] ?? '';
$msg = $_GET['msg'] ?? '';

if (empty($botKey) || empty($msg)) {
    echo "使用方法: bridge.php?key=机器人KEY&msg=消息内容";
    exit;
}

// 验证机器人
$conn = @mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
if (!$conn) {
    echo "数据库连接失败";
    exit;
}

$botKeyEsc = mysqli_real_escape_string($conn, $botKey);
$result = mysqli_query($conn, "SELECT * FROM ddwx_commune_bot WHERE bot_key = '$botKeyEsc' AND status = 1");
$bot = mysqli_fetch_assoc($result);

if (!$bot) {
    echo "机器人验证失败";
    exit;
}

$msgEsc = mysqli_real_escape_string($conn, $msg);
$time = time();

// 发送消息
mysqli_query($conn, "INSERT INTO ddwx_commune_chat (content, username, msg_type, from_user, is_robot, status, create_time) 
        VALUES ('$msgEsc', '{$bot['bot_name']}', 'public', '{$bot['bot_key']}', 1, 1, $time)");

// 更新在线状态
mysqli_query($conn, "REPLACE INTO ddwx_commune_online (username, nickname, last_active) VALUES ('{$bot['bot_key']}', '{$bot['bot_name']}', $time)");

echo "OK: " . $bot['bot_name'] . " 说: " . $msg;
