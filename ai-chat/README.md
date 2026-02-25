# GDQ AI 聊天平台 - 开发文档

> 一个内置AI助手的聊天平台，比Telegram更智能

## 📋 项目概述

### 目标
开发一个带有AI能力的聊天平台，每个用户都有专属AI助手

### 核心功能
1. **即时通讯** - 私聊/群聊/文件传输
2. **AI助手** - 内置OpenClaw，像朋友一样聊天
3. **智能摘要** - 群聊太多？AI一键总结
4. **自动回复** - AI根据上下文智能回复
5. **内容搜索** - 说话就能搜历史记录

---

## 🏗️ 技术架构

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  Web客户端  │────▶│  PHP后端    │────▶│  MySQL     │
│  (HTML/JS)  │     │  (Nginx)    │     │  数据库    │
└─────────────┘     └─────────────┘     └─────────────┘
                           │
                           ▼
                    ┌─────────────┐
                    │  OpenClaw  │
                    │  AI集成    │
                    └─────────────┘
```

---

## 📁 文件结构

```
/www/wwwroot/gdqshop.cn/
├── ai-chat/
│   ├── index.html          # 主页面
│   ├── api/
│   │   ├── chat.php        # 聊天API
│   │   ├── user.php        # 用户API
│   │   └── ai.php          # AI集成API
│   ├── js/
│   │   ├── app.js          # 主逻辑
│   │   └── ai.js           # AI功能
│   ├── css/
│   │   └── style.css       # 样式
│   └── README.md           # 本文档
```

---

## 🎯 开发计划

### Phase 1: MVP (第1周)
- [x] 项目结构创建
- [ ] 用户系统（登录/注册）
- [ ] 基础聊天（私聊/群聊）
- [ ] UI界面

### Phase 2: AI集成 (第2周)
- [ ] 接入OpenClaw API
- [ ] AI助手对话
- [ ] 智能回复

### Phase 3: 增强功能 (第3周)
- [ ] 群聊摘要
- [ ] 内容搜索
- [ ] 文件传输

### Phase 4: 优化 (第4周)
- [ ] 移动端适配
- [ ] 性能优化
- [ ] 安全加固

---

## 🔧 API 接口

### 用户
- `POST /api/user.php?action=register` - 注册
- `POST /api/user.php?action=login` - 登录
- `GET /api/user.php?action=list` - 用户列表

### 聊天
- `POST /api/chat.php?action=send` - 发送消息
- `GET /api/chat.php?action=list&room_id=xxx` - 消息列表
- `POST /api/chat.php?action=create_room` - 创建群聊

### AI
- `POST /api/ai.php?action=chat` - AI对话
- `POST /api/ai.php?action=summarize` - 总结群聊
- `POST /api/ai.php?action=reply` - 智能回复建议

---

## 📝 更新日志

- 2026-02-25: 项目创建，开始开发

---

## 🔗 相关链接

- 在线地址: https://bot.gdqshop.cn/ai-chat/
- GitHub: https://github.com/gdqboss/GDQADMIN
