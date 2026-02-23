<?php
// AI助手API控制器
namespace app\controller;

use app\BaseController;
use app\custom\AiAssistant;

class ApiAiAssistant extends BaseController
{
    /**
     * 获取AI回复
     * @return json
     */
    public function getReply()
    {
        $message = input('param.message', '', 'trim');
        if (empty($message)) {
            return json(['status' => 0, 'msg' => '请输入消息内容']);
        }
        
        $uid = input('param.uid', 0, 'intval'); // 使用输入参数或默认值
        $aid = input('param.aid', 1, 'intval'); // 默认使用aid=1
        
        // 调用AI助手服务
        $aiAssistant = new AiAssistant($aid);
        $result = $aiAssistant->getReply($message, $uid);
        
        return json($result);
    }
    
    /**
     * 获取聊天历史记录
     * @return json
     */
    public function getHistory()
    {
        $uid = input('param.uid', 0, 'intval'); // 使用输入参数或默认值
        $aid = input('param.aid', 1, 'intval'); // 默认使用aid=1
        $limit = input('param.limit', 20, 'intval');
        
        // 调用AI助手服务获取聊天历史
        $aiAssistant = new AiAssistant($aid);
        $history = $aiAssistant->getChatHistory($uid, $limit);
        
        // 格式化聊天历史
        $formattedHistory = [];
        foreach ($history as $item) {
            $formattedHistory[] = [
                'content' => $item['user_message'],
                'isUser' => true,
                'timestamp' => $item['create_time']
            ];
            $formattedHistory[] = [
                'content' => $item['ai_reply'],
                'isUser' => false,
                'timestamp' => $item['create_time']
            ];
        }
        
        return json([
            'status' => 1,
            'data' => $formattedHistory
        ]);
    }
}
