<template>
    <view class="ai-assistant-container">
        <!-- AI助手按钮 -->
        <view class="ai-btn" @tap="toggleAssistant" :class="{ active: isOpen }">
            <image class="ai-icon" :src="pre_url + '/static/img/ai-assistant.png'" mode="aspectFit"></image>
        </view>
        
        <!-- AI助手面板 -->
        <view class="ai-panel" :class="{ open: isOpen }">
            <!-- 头部 -->
            <view class="ai-header">
                <view class="ai-title">智能助手</view>
                <view class="ai-close" @tap="toggleAssistant">
                    <image src="/static/img/close.png" mode="aspectFit"></image>
                </view>
            </view>
            
            <!-- 聊天内容 -->
            <scroll-view class="ai-content" scroll-y="true" :scroll-top="scrollTop">
                <!-- 欢迎消息 -->
                <view class="ai-message ai-message-ai" v-if="messages.length === 0">
                    <view class="ai-avatar">
                        <image src="/static/img/ai-assistant.png" mode="aspectFit"></image>
                    </view>
                    <view class="ai-text">
                        你好！我是智能助手，有什么可以帮助你的吗？
                    </view>
                </view>
                
                <!-- 聊天记录 -->
                <view v-for="(message, index) in messages" :key="index" 
                      :class="['ai-message', message.isUser ? 'ai-message-user' : 'ai-message-ai']">
                    <view class="ai-avatar">
                        <image v-if="message.isUser" src="/static/img/user-avatar.png" mode="aspectFit"></image>
                        <image v-else src="/static/img/ai-assistant.png" mode="aspectFit"></image>
                    </view>
                    <view class="ai-text">
                        {{ message.content }}
                    </view>
                </view>
                
                <!-- 正在输入 -->
                <view class="ai-message ai-message-ai" v-if="isTyping">
                    <view class="ai-avatar">
                        <image src="/static/img/ai-assistant.png" mode="aspectFit"></image>
                    </view>
                    <view class="ai-text typing">
                        <view class="typing-dot"></view>
                        <view class="typing-dot"></view>
                        <view class="typing-dot"></view>
                    </view>
                </view>
            </scroll-view>
            
            <!-- 输入区域 -->
            <view class="ai-input-area">
                <input type="text" class="ai-input" placeholder="请输入您的问题..." 
                       v-model="inputMessage" @confirm="sendMessage" />
                <button class="ai-send-btn" @tap="sendMessage" :disabled="!inputMessage.trim()">
                    <image src="/static/img/send.png" mode="aspectFit"></image>
                </button>
            </view>
        </view>
    </view>
</template>

<script>
    var app = getApp();
    export default {
        data() {
            return {
                isOpen: false,
                messages: [],
                inputMessage: '',
                isTyping: false,
                scrollTop: 0,
                pre_url: app.globalData.pre_url
            };
        },
        onShow() {
            // 加载聊天历史
            this.loadChatHistory();
        },
        methods: {
            // 切换AI助手面板
            toggleAssistant() {
                this.isOpen = !this.isOpen;
                if (this.isOpen) {
                    // 延迟滚动到底部
                    this.$nextTick(() => {
                        this.scrollToBottom();
                    });
                }
            },
            
            // 发送消息
            sendMessage() {
                if (!this.inputMessage.trim()) return;
                
                let message = this.inputMessage.trim();
                
                // 添加用户消息
                this.messages.push({
                    content: message,
                    isUser: true,
                    timestamp: Date.now()
                });
                
                this.inputMessage = '';
                this.isTyping = true;
                
                // 滚动到底部
                this.scrollToBottom();
                
                // 发送请求获取AI回复
                this.getAIReply(message);
            },
            
            // 获取AI回复
            getAIReply(message) {
                var that = this;
                
                app.post('ApiAiAssistant/getReply', {
                    message: message
                }, function(res) {
                    that.isTyping = false;
                    
                    if (res.status == 1) {
                        // 添加AI回复
                        that.messages.push({
                            content: res.data.reply,
                            isUser: false,
                            timestamp: Date.now()
                        });
                        
                        // 滚动到底部
                        that.scrollToBottom();
                    } else {
                        // 错误处理
                        that.messages.push({
                            content: '抱歉，暂时无法回答您的问题，请稍后重试。',
                            isUser: false,
                            timestamp: Date.now()
                        });
                        
                        // 滚动到底部
                        that.scrollToBottom();
                    }
                });
            },
            
            // 滚动到底部
            scrollToBottom() {
                this.$nextTick(() => {
                    let query = uni.createSelectorQuery().in(this);
                    query.select('.ai-content').boundingClientRect((rect) => {
                        this.scrollTop = rect.scrollHeight;
                    }).exec();
                });
            },
            
            // 加载聊天历史
            loadChatHistory() {
                var that = this;
                
                app.post('ApiAiAssistant/getHistory', {}, function(res) {
                    if (res.status == 1 && res.data.length > 0) {
                        that.messages = res.data.map(item => ({
                            content: item.content,
                            isUser: item.isUser,
                            timestamp: item.timestamp
                        }));
                        
                        // 滚动到底部
                        that.scrollToBottom();
                    }
                });
            }
        }
    };
</script>

<style scoped>
    .ai-assistant-container {
        position: fixed;
        bottom: 100rpx;
        right: 30rpx;
        z-index: 9999;
    }
    
    /* AI助手按钮 */
    .ai-btn {
        width: 120rpx;
        height: 120rpx;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .ai-btn.active {
        transform: rotate(90deg);
        box-shadow: 0 6rpx 30rpx rgba(0, 0, 0, 0.3);
    }
    
    .ai-btn .ai-icon {
        width: 60rpx;
        height: 60rpx;
    }
    
    /* AI助手面板 */
    .ai-panel {
        position: fixed;
        bottom: 150rpx;
        right: 30rpx;
        width: 600rpx;
        height: 800rpx;
        background: #fff;
        border-radius: 20rpx;
        box-shadow: 0 10rpx 40rpx rgba(0, 0, 0, 0.2);
        transform: translateY(100%);
        opacity: 0;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        z-index: 9998;
    }
    
    .ai-panel.open {
        transform: translateY(0);
        opacity: 1;
    }
    
    /* 头部 */
    .ai-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20rpx 30rpx;
        border-bottom: 1rpx solid #eee;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        border-radius: 20rpx 20rpx 0 0;
    }
    
    .ai-title {
        font-size: 32rpx;
        font-weight: bold;
    }
    
    .ai-close {
        width: 40rpx;
        height: 40rpx;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    
    .ai-close image {
        width: 24rpx;
        height: 24rpx;
    }
    
    /* 聊天内容 */
    .ai-content {
        flex: 1;
        padding: 30rpx;
        overflow-y: auto;
        background: #f8f9fa;
    }
    
    /* 聊天消息 */
    .ai-message {
        display: flex;
        margin-bottom: 30rpx;
        align-items: flex-start;
    }
    
    .ai-message-user {
        flex-direction: row-reverse;
    }
    
    /* 头像 */
    .ai-avatar {
        width: 60rpx;
        height: 60rpx;
        border-radius: 50%;
        overflow: hidden;
        margin: 0 20rpx;
        background: #fff;
        box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.1);
    }
    
    .ai-avatar image {
        width: 100%;
        height: 100%;
    }
    
    /* 消息内容 */
    .ai-text {
        max-width: 400rpx;
        padding: 20rpx 30rpx;
        border-radius: 20rpx;
        font-size: 28rpx;
        line-height: 1.6;
        word-wrap: break-word;
    }
    
    .ai-message-ai .ai-text {
        background: #fff;
        color: #333;
        box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.1);
        border-bottom-left-radius: 8rpx;
    }
    
    .ai-message-user .ai-text {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.1);
        border-bottom-right-radius: 8rpx;
    }
    
    /* 正在输入 */
    .typing {
        display: flex;
        align-items: center;
    }
    
    .typing-dot {
        width: 12rpx;
        height: 12rpx;
        background: #999;
        border-radius: 50%;
        margin: 0 6rpx;
        animation: typing 1.4s infinite ease-in-out;
    }
    
    .typing-dot:nth-child(1) {
        animation-delay: -0.32s;
    }
    
    .typing-dot:nth-child(2) {
        animation-delay: -0.16s;
    }
    
    @keyframes typing {
        0%, 80%, 100% {
            transform: scale(0);
        }
        40% {
            transform: scale(1);
        }
    }
    
    /* 输入区域 */
    .ai-input-area {
        display: flex;
        padding: 20rpx 30rpx;
        border-top: 1rpx solid #eee;
        background: #fff;
        border-radius: 0 0 20rpx 20rpx;
    }
    
    .ai-input {
        flex: 1;
        height: 80rpx;
        background: #f5f5f5;
        border: none;
        border-radius: 40rpx;
        padding: 0 30rpx;
        font-size: 28rpx;
        margin-right: 20rpx;
    }
    
    .ai-send-btn {
        width: 80rpx;
        height: 80rpx;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
    }
    
    .ai-send-btn image {
        width: 40rpx;
        height: 40rpx;
    }
    
    .ai-send-btn:disabled {
        background: #ccc;
    }
</style>
