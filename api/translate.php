<?php
/**
 * 智能翻译API - 使用AI进行高质量翻译
 */
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// 翻译
if ($action === 'translate') {
    $text = $_POST['text'] ?? '';
    $from = $_POST['from'] ?? 'auto';
    $to = $_POST['to'] ?? 'zh';
    
    if (!$text) {
        echo json_encode(['code'=>0, 'msg'=>'文本为空']); exit;
    }
    
    $result = translateText($text, $from, $to);
    echo json_encode(['code'=>1, 'data'=>['text'=>$result]]);
    exit;
}

// 语言检测
if ($action === 'detect') {
    $text = $_POST['text'] ?? '';
    if (!$text) {
        echo json_encode(['code'=>0, 'msg'=>'文本为空']); exit;
    }
    
    $lang = detectLanguage($text);
    echo json_encode(['code'=>1, 'data'=>['language'=>$lang]]);
    exit;
}

// 获取支持的语言
if ($action === 'languages') {
    echo json_encode(['code'=>1, 'data'=>[
        ['code'=>'zh','name'=>'中文'],
        ['code'=>'en','name'=>'英语'],
        ['code'=>'yue','name'=>'粤语'],
        ['code'=>'ja','name'=>'日语'],
        ['code'=>'ko','name'=>'韩语'],
        ['code'=>'es','name'=>'西班牙语'],
        ['code'=>'fr','name'=>'法语'],
        ['code'=>'de','name'=>'德语'],
        ['code'=>'ru','name'=>'俄语'],
        ['code'=>'ar','name'=>'阿拉伯语'],
        ['code'=>'th','name'=>'泰语'],
        ['code'=>'vi','name'=>'越南语'],
        ['code'=>'ms','name'=>'马来语'],
        ['code'=>'tl','name'=>'菲律宾语'],
        ['code'=>'id','name'=>'印尼语']
    ]]);
    exit;
}

echo json_encode(['code'=>0, 'msg'=>'unknown action']);

function translateText($text, $from, $to) {
    $api_key = 'sk-cp-BbLwwqBSr8RrPusVeP8-U4_ezPtJS48rVjuMepMrxOZR4vcyRt_zD-OwhYcm7KKVnWT6nZxvi9q8zTsa1yC_mIaoqD4UyPjQn6xM4oOaoR5S0AHQut6jQtU';
    
    $lang_names = [
        'zh'=>'中文', 'en'=>'英语', 'yue'=>'粤语', 'ja'=>'日语', 'ko'=>'韩语',
        'es'=>'西班牙语', 'fr'=>'法语', 'de'=>'德语', 'ru'=>'俄语', 'ar'=>'阿拉伯语',
        'th'=>'泰语', 'vi'=>'越南语', 'ms'=>'马来语', 'tl'=>'菲律宾语', 'id'=>'印尼语'
    ];
    
    $from_name = $lang_names[$from] ?? $from;
    $to_name = $lang_names[$to] ?? $to;
    
    $prompt = "你是一个专业的翻译助手。请将以下{$from_name}翻译成{$to_name}，保持原意，翻译要自然流畅。如果原文是{$to_name}，则翻译成{$from_name}。\n\n翻译内容：\n" . $text;
    
    $ch = curl_init('https://api.minimax.chat/v1/text/chatcompletion_v2');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'abab6.5s-chat',
        'messages' => [
            ['role'=>'system', 'content'=>$prompt],
            ['role'=>'user', 'content'=>$text]
        ]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer '.$api_key, 'Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    
    return $result['choices'][0]['message']['content'] ?? $text;
}

function detectLanguage($text) {
    // 简单语言检测
    if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $text)) return 'zh'; // 中文
    if (preg_match('/[\x{3040}-\x{309f}\x{30a0}-\x{30ff}]/u', $text)) return 'ja'; // 日语
    if (preg_match('/[\x{AC00}-\x{D7AF}]/u', $text)) return 'ko'; // 韩语
    if (preg_match('/[\x{0e00}-\x{0e7f}]/u', $text)) return 'th'; // 泰语
    if (preg_match('/[\x{0600}-\x{06ff}]/u', $text)) return 'ar'; // 阿拉伯语
    if (preg_match('/[a-zA-Z]/', $text)) return 'en'; // 默认英语
    return 'zh';
}
