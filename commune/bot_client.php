<?php
/**
 * 聊天室机器人客户端 - 供其他 OpenClaw 使用
 * 
 * 使用方法：
 * 1. 设置下方的配置
 * 2. 设置定时任务每分钟运行此脚本
 */

$config = [
    'bot_key' => 'bot_jiangxiaoyu_001',  // 机器人的 KEY
    'api_url' => 'http://bot.gdqshop.cn/api/commune/bot.php',
    'name' => '江小鱼'  // 机器人名称
];

// 获取机器人收到的消息
$url = $config['api_url'] . '?action=get_messages&bot_key=' . $config['bot_key'];
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['code'] == 1 && !empty($data['data'])) {
    foreach ($data['data'] as $msg) {
        $content = $msg['content'];
        $from = $msg['username'];
        
        echo "收到消息 [$from]: $content\n";
        
        // 这里处理消息，返回回复
        $reply = processMessage($content, $from);
        
        if ($reply) {
            // 发送回复
            $sendUrl = $config['api_url'] . '?action=report_result';
            $postData = http_build_query([
                'bot_key' => $config['bot_key'],
                'result' => $reply
            ]);
            
            $ch = curl_init($sendUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
            
            echo "回复: $reply\n";
        }
    }
}

// 处理消息的函数 - 在这里编写 AI 逻辑
function processMessage($content, $from) {
    $content = trim($content);
    
    // 去掉 @机器人名称
    $content = preg_replace('/^@\S+\s+/', '', $content);
    
    // 简单的回复逻辑
    if (strpos($content, '你好') !== false || strpos($content, 'hi') !== false) {
        return "你好！$from 有什么可以帮你的？";
    }
    
    if (strpos($content, '天气') !== false) {
        return "今天天气不错！☀️";
    }
    
    if (strpos($content, '时间') !== false || strpos($content, '几点了') !== false) {
        return "现在是 " . date('Y-m-d H:i:s');
    }
    
    if (strpos($content, '帮助') !== false) {
        return "我可以帮你查天气、时间等。有什么想问的？";
    }
    
    // 默认回复
    return "收到你的消息: $content";
}
