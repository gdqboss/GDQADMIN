<?php
/**
 * 江小蟹 - 聊天室机器人客户端
 * 
 * 复制到你的 OpenClaw 服务器上，设置定时任务每分钟运行
 */

// ==================== 配置 ====================
$config = [
    'bot_key' => 'bot_jxx_1771147782',   // 机器人 KEY
    'api_url' => 'http://bot.gdqshop.cn/api/commune/bot.php',  // API 地址
    'name' => '江小蟹'  // 机器人名字
];
// ==================== 配置结束 ====================

// 获取发给机器人的消息
$url = $config['api_url'] . '?action=get_messages&bot_key=' . $config['bot_key'];
$response = @file_get_contents($url);

if (!$response) {
    exit;
}

$data = json_decode($response, true);

if ($data['code'] == 1 && !empty($data['data'])) {
    foreach ($data['data'] as $msg) {
        $content = trim($msg['content']);
        $from = $msg['username'];
        
        // 去掉 @机器人名称
        $cmd = preg_replace('/^@\S+\s+/', '', $content);
        
        echo "[" . date('H:i:s') . "] [$from] $cmd\n";
        
        // 处理消息，返回回复
        $reply = processMessage($cmd, $from);
        
        if ($reply) {
            // 发送回复
            $postData = http_build_query([
                'bot_key' => $config['bot_key'],
                'result' => $reply
            ]);
            
            $ch = curl_init($config['api_url'] . '?action=report_result');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
            
            echo "[" . date('H:i:s') . "] 回复: $reply\n";
        }
    }
}

// 处理消息的函数 - 在这里编写 AI 逻辑
function processMessage($content, $from) {
    $content = trim($content);
    $contentLower = mb_strtolower($content, 'utf-8');
    
    // 问候
    if (strpos($contentLower, '你好') !== false || strpos($contentLower, 'hi') !== false || strpos($contentLower, '在吗') !== false) {
        return "你好！$from 有什么可以帮你的？ 🦀";
    }
    
    // 帮助
    if (strpos($contentLower, '帮助') !== false || strpos($contentLower, 'help') !== false) {
        return "我是江小蟹，有什么问题可以问我！";
    }
    
    // 时间
    if (strpos($contentLower, '时间') !== false || strpos($contentLower, '几点了') !== false) {
        return "现在是 " . date('Y-m-d H:i:s');
    }
    
    // 天气
    if (strpos($contentLower, '天气') !== false) {
        return "天气功能开发中... 🔧";
    }
    
    // 笑话
    if (strpos($contentLower, '笑话') !== false) {
        $jokes = [
            "为什么小蟹横着走？因为它不想撞到别人的钳子~ 🦀",
            "你知道吗？螃蟹的牙齿在胃里！所以它吃嘛嘛香~"
        ];
        return $jokes[array_rand($jokes)];
    }
    
    // 感谢
    if (strpos($contentLower, '谢谢') !== false) {
        return "不客气！有问题随时叫我~ 😊";
    }
    
    // 再见
    if (strpos($contentLower, '再见') !== false || strpos($contentLower, '拜拜') !== false) {
        return "再见！有空来玩~ 🦀";
    }
    
    // 默认回复
    $replies = [
        "收到！还有什么需要吗？ 🦀",
        "明白！还有其他问题吗？",
        "好的，让我思考一下...",
        "收到消息！我在这里~"
    ];
    
    return $replies[array_rand($replies)];
}
