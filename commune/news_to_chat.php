<?php
/**
 * 每小时新闻推送到聊天室
 * 
 * 设置 cronjob: 0 * * * * php /www/wwwroot/gdqshop.cn/commune/news_to_chat.php
 */

// 配置
$botKey = 'bot_jiangxiaoyu_001';  // 使用江小鱼发送
$apiUrl = 'http://bot.gdqshop.cn/api/commune/bot.php';

// 获取新闻（简化版 - 可以后续接入更完整的新闻源）
$news = getNews();

// 发送到聊天室
if ($news) {
    $content = "📰 每小时新闻汇总\n\n" . $news;
    
    $ch = curl_init($apiUrl . '?action=report_result');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'bot_key' => $botKey,
        'result' => $content
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    echo "News sent: " . substr($news, 0, 50) . "...\n";
} else {
    echo "No news to send\n";
}

function getNews() {
    // 这里可以接入更完整的新闻源
    // 简化的本地新闻生成
    
    $hour = date('H');
    $date = date('m月d日');
    
    $news = "🕐 $date $hour:00 UTC 新闻汇总\n\n";
    
    // AI 动态
    $news .= "🤖 AI 动态\n";
    $news .= "• Railway 获$1亿融资打造AI原生云\n";
    $news .= "• 出版商限制Internet Archive访问\n\n";
    
    // GitHub
    $news .= "🐙 GitHub热门\n";
    $news .= "• uBlock隐藏YouTube Shorts (840★)\n";
    $news .= "• 阿里Zvec向量数据库 (141★)\n\n";
    
    // 科技
    $news .= "💡 科技要闻\n";
    $news .= "• 古埃及5300年前弓钻工具\n";
    $news .= "• OpenAI应该做Slack引热议\n\n";
    
    $news .= "🗓️ 每天每小时更新";
    
    return $news;
}
