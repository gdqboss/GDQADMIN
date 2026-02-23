<?php
// AI助手服务类
namespace app\custom;

use think\facade\Db;
use think\facade\Log;

class AiAssistant
{
    private $apiKey;
    private $secretKey;
    private $appId;
    private $aid;
    
    public function __construct($aid = 1, $appId = null, $apiKey = null, $secretKey = null)
    {
        $this->aid = intval($aid);
        
        // 从数据库获取AI助手配置
        $sysset = Db::name('ai_assistant_sysset')->where('aid', $aid)->find();
        if (empty($sysset)) {
            // 默认使用百度AI配置
            $this->appId = $appId ?: '';
            $this->apiKey = $apiKey ?: '';
            $this->secretKey = $secretKey ?: '';
        } else {
            $this->appId = $appId ?: $sysset['app_id'];
            $this->apiKey = $apiKey ?: $sysset['api_key'];
            $this->secretKey = $secretKey ?: $sysset['secret_key'];
        }
    }
    
    /**
     * 获取AI回复
     * @param string $message 用户消息
     * @param int $uid 用户ID
     * @return array 回复结果
     */
    public function getReply($message, $uid = 0)
    {
        try {
            // 根据配置选择AI服务提供商
            $sysset = Db::name('ai_assistant_sysset')->where('aid', $this->aid)->find();
            $provider = $sysset['provider'] ?? 'baidu';
            
            switch ($provider) {
                case 'baidu':
                    return $this->getBaiduReply($message, $uid);
                case 'openai':
                    return $this->getOpenAIReply($message, $uid);
                default:
                    return ['status' => 0, 'msg' => '未配置AI服务提供商'];
            }
        } catch (\Exception $e) {
            Log::error('AI助手错误: ' . $e->getMessage());
            return ['status' => 0, 'msg' => 'AI助手服务异常，请稍后重试'];
        }
    }
    
    /**
     * 获取百度AI回复
     * @param string $message 用户消息
     * @param int $uid 用户ID
     * @return array 回复结果
     */
    private function getBaiduReply($message, $uid = 0)
    {
        try {
            // 检查API配置是否完整
            if (empty($this->appId) || empty($this->apiKey) || empty($this->secretKey)) {
                return ['status' => 0, 'msg' => '百度AI配置不完整，请先配置API密钥'];
            }
            
            // 这里使用百度AI的对话API，具体实现根据百度AI文档调整
            // 示例实现，实际需要根据百度AI API文档进行调整
            $accessToken = $this->getBaiduAccessToken();
            if (!$accessToken) {
                return ['status' => 0, 'msg' => '获取百度AI访问令牌失败，请检查API密钥是否正确'];
            }
            
            $url = "https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop/chat/completions_pro?access_token={$accessToken}";
            
            $data = [
                'messages' => [
                    ['role' => 'user', 'content' => $message]
                ],
                'temperature' => 0.7,
                'top_p' => 0.95,
                'max_output_tokens' => 512, // 限制回复长度，提高速度
                'speed' => 1 // 提高回复速度
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8); // 设置8秒超时
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // 设置3秒连接超时
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 关闭SSL验证，提高速度
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $result = curl_exec($ch);
            
            // 检查curl错误
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                Log::error('百度AI请求错误: ' . $error);
                return ['status' => 0, 'msg' => 'AI服务连接超时，请稍后重试'];
            }
            
            curl_close($ch);
            
            $result = json_decode($result, true);
            
            if (isset($result['error_code'])) {
                Log::error('百度AI错误: ' . $result['error_msg']);
                $errorMsg = 'AI回复失败';
                if (in_array($result['error_code'], [110, 111, 112])) {
                    $errorMsg .= '，API密钥无效或已过期';
                } elseif (in_array($result['error_code'], [17, 18])) {
                    $errorMsg .= '，请求过于频繁，请稍后重试';
                }
                return ['status' => 0, 'msg' => $errorMsg];
            }
            
            if (!isset($result['result'])) {
                Log::error('百度AI返回格式错误: ' . json_encode($result));
                return ['status' => 0, 'msg' => 'AI回复格式错误，请稍后重试'];
            }
            
            // 保存对话记录
            $this->saveChatLog($uid, $message, $result['result']);
            
            return [
                'status' => 1,
                'data' => [
                    'reply' => $result['result'],
                    'provider' => 'baidu'
                ]
            ];
        } catch (\Exception $e) {
            Log::error('百度AI处理错误: ' . $e->getMessage());
            return ['status' => 0, 'msg' => 'AI处理异常，请稍后重试'];
        }
    }
    
    /**
     * 获取OpenAI回复
     * @param string $message 用户消息
     * @param int $uid 用户ID
     * @return array 回复结果
     */
    private function getOpenAIReply($message, $uid = 0)
    {
        try {
            // OpenAI API实现，需要根据OpenAI文档调整
            $sysset = Db::name('ai_assistant_sysset')->where('aid', $this->aid)->find();
            $openaiApiKey = $sysset['openai_api_key'] ?? '';
            
            if (empty($openaiApiKey)) {
                return ['status' => 0, 'msg' => '未配置OpenAI API密钥'];
            }
            
            $url = "https://api.openai.com/v1/chat/completions";
            
            $data = [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => '你是一个智能助手，帮助用户解答问题。'],
                    ['role' => 'user', 'content' => $message]
                ],
                'temperature' => 0.7,
                'max_tokens' => 512, // 限制回复长度，提高速度
                'top_p' => 0.9,
                'frequency_penalty' => 0,
                'presence_penalty' => 0
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $openaiApiKey
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8); // 设置8秒超时
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // 设置3秒连接超时
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 关闭SSL验证，提高速度
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $result = curl_exec($ch);
            
            // 检查curl错误
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                Log::error('OpenAI请求错误: ' . $error);
                return ['status' => 0, 'msg' => 'AI服务连接超时，请稍后重试'];
            }
            
            curl_close($ch);
            
            $result = json_decode($result, true);
            
            if (isset($result['error'])) {
                Log::error('OpenAI错误: ' . $result['error']['message']);
                return ['status' => 0, 'msg' => 'AI回复失败，错误: ' . $result['error']['message']];
            }
            
            if (!isset($result['choices'][0]['message']['content'])) {
                Log::error('OpenAI返回格式错误: ' . json_encode($result));
                return ['status' => 0, 'msg' => 'AI回复格式错误，请稍后重试'];
            }
            
            // 保存对话记录
            $this->saveChatLog($uid, $message, $result['choices'][0]['message']['content']);
            
            return [
                'status' => 1,
                'data' => [
                    'reply' => $result['choices'][0]['message']['content'],
                    'provider' => 'openai'
                ]
            ];
        } catch (\Exception $e) {
            Log::error('OpenAI处理错误: ' . $e->getMessage());
            return ['status' => 0, 'msg' => 'AI处理异常，请稍后重试'];
        }
    }
    
    /**
     * 获取百度AI访问令牌
     * @return string 访问令牌
     */
    private function getBaiduAccessToken()
    {
        $url = "https://aip.baidubce.com/oauth/2.0/token?grant_type=client_credentials&client_id={$this->apiKey}&client_secret={$this->secretKey}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8); // 设置8秒超时
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // 设置3秒连接超时
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 关闭SSL验证，提高速度
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $result = curl_exec($ch);
        
        // 检查curl错误
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            Log::error('获取百度AI令牌错误: ' . $error);
            return '';
        }
        
        curl_close($ch);
        
        $result = json_decode($result, true);
        
        // 检查返回结果
        if (isset($result['error'])) {
            Log::error('百度AI令牌获取失败: ' . $result['error_description']);
            return '';
        }
        
        return $result['access_token'] ?? '';
    }
    
    /**
     * 保存聊天记录
     * @param int $uid 用户ID
     * @param string $userMessage 用户消息
     * @param string $aiReply AI回复
     */
    private function saveChatLog($uid, $userMessage, $aiReply)
    {
        try {
            Db::name('ai_assistant_chatlog')->insert([
                'aid' => $this->aid,
                'uid' => $uid,
                'user_message' => $userMessage,
                'ai_reply' => $aiReply,
                'create_time' => time()
            ]);
        } catch (\Exception $e) {
            Log::error('保存聊天记录错误: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取聊天历史记录
     * @param int $uid 用户ID
     * @param int $limit 记录数量
     * @return array 聊天记录
     */
    public function getChatHistory($uid, $limit = 20)
    {
        try {
            $logs = Db::name('ai_assistant_chatlog')
                ->where('aid', $this->aid)
                ->where('uid', $uid)
                ->order('create_time desc')
                ->limit($limit)
                ->select()
                ->toArray();
            
            return array_reverse($logs);
        } catch (\Exception $e) {
            Log::error('获取聊天记录错误: ' . $e->getMessage());
            return [];
        }
    }
}
