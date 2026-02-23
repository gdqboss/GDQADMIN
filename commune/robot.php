<?php
/**
 * AI 机器人 - DeepSeek API 接入
 */
header('Content-Type: text/html; charset=utf-8');

// DeepSeek API 配置
$apiKey = 'sk-5bbae3e7abd449b7b25397abd1408e56';

$conn = @mysqli_connect('127.0.0.1', 'root', '', 'ddshop');
if (!$conn) exit;

mysqli_query($conn, "SET NAMES utf8mb4");

$botName = '江小鱼';
$botUsername = 'GDQFISH';
$lastFile = '/tmp/commune_last_id_v4.txt';
$lastId = (int)@file_get_contents($lastFile);

// 系统提示词
$systemPrompt = "You are 江小鱼 (Jiang Xiaoyu), a friendly and lively AI assistant in a seafood chat room. 
- Keep responses short and concise
- Use appropriate emojis
- Call the user '波哥' (Bo Ge)
- Be honest if you don't know something
- You can chat about weather, news, tech, jokes, or any topic
- Be warm and welcoming";

$sql = "SELECT * FROM ddwx_commune_chat 
        WHERE status = 1 AND id > $lastId 
        AND (msg_type = 'public' OR to_user = '$botUsername' OR to_user = '$botName')
        ORDER BY id ASC LIMIT 10";
$result = mysqli_query($conn, $sql);

$newLastId = $lastId;
$conversations = [];

while ($row = mysqli_fetch_assoc($result)) {
    $newLastId = $row['id'];
    $content = trim($row['content']);
    $msgFrom = $row['username'];
    $msgFromUser = $row['from_user'];
    
    // 跳过机器人消息
    if ($row['is_robot'] == 1) {
        continue;
    }
    
    // 跳过空消息
    if (empty($content)) continue;
    
    // 加入对话历史
    $conversations[] = ['role' => 'user', 'content' => $content];
    
    // 需要回复这条消息
    if (count($conversations) > 0) {
        // 调用 DeepSeek API
        $reply = callDeepSeekAPI($apiKey, $systemPrompt, $conversations);
        
        if ($reply) {
            // 保存回复到数据库
            $replyContent = mysqli_real_escape_string($conn, $reply);
            $time = time();
            
            mysqli_query($conn, "INSERT INTO ddwx_commune_chat (content, username, msg_type, from_user, is_robot, status, create_time) 
                    VALUES ('$replyContent', '$botName', 'public', '$botUsername', 1, 1, $time)");
            
            echo "🤖 江小鱼: $reply\n";
        }
        
        // 清空对话历史
        $conversations = [];
    }
}

file_put_contents($lastFile, $newLastId);
mysqli_close($conn);

// DeepSeek API 调用函数
function callDeepSeekAPI($apiKey, $systemPrompt, $messages) {
    $url = 'https://api.deepseek.com/chat/completions';
    
    // 构建消息
    $msgList = [['role' => 'system', 'content' => $systemPrompt]];
    foreach ($messages as $msg) {
        $msgList[] = ['role' => 'user', 'content' => $msg['content']];
    }
    
    $data = [
        'model' => 'deepseek-chat',
        'messages' => $msgList,
        'temperature' => 0.7,
        'max_tokens' => 300
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }
    }
    
    // 如果 API 失败，记录错误
    echo "DeepSeek API Error: HTTP $httpCode - $error\n";
    echo "Response: " . substr($response, 0, 200) . "\n";
    return null;
}
