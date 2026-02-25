<?php
/**
 * Telegram 对话API - 让OpenClaw可以调用来获取共享记忆
 */

$memory_file = '/tmp/gdq_shared_memory.json';

header('Content-Type: application/json');

// 获取共享记忆
$content = @file_get_contents($memory_file);
$memory = $content ? json_decode($content, true) : [];

// 构建上下文
$context = '';
foreach (array_slice($memory, -10) as $m) {
    $source = isset($m['source']) ? '['.$m['source'].']' : '';
    $context .= $m['role'] . $source . ': ' . $m['content'] . "\n";
}

echo json_encode([
    'context' => $context,
    'count' => count($memory),
    'last_message' => end($memory)
]);
