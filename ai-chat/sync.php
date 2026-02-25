<?php
/**
 * Telegram 消息同步
 * 从OpenClaw获取Telegram会话历史，保存到统一数据库
 */

$conn = mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
if (!$conn) {
    file_put_contents('/tmp/sync.log', date('Y-m-d H:i:s') . " - DB error\n", FILE_APPEND);
    exit;
}
mysqli_query($conn, "SET NAMES utf8mb4");

$user_map = [
    '861063885' => 1,  // 波哥: Telegram ID => web user_id
];

// 获取Telegram消息（通过OpenClaw sessions API）
// 注意：这里需要OpenClaw API支持

// 目前先检查是否需要同步
$last_sync = intval(file_get_contents('/tmp/last_sync_time') ?: 0);
$now = time();

// 每5分钟同步一次
if ($now - $last_sync > 300) {
    // 这里可以调用OpenClaw API获取Telegram消息
    // 目前简化处理
    file_put_contents('/tmp/last_sync_time', $now);
}

file_put_contents('/tmp/sync.log', date('Y-m-d H:i:s') . " - Sync check\n", FILE_APPEND);

echo "OK";
