-- ============================================================
-- 多用户多 Agent 个人微信服务模块 (module_key: wechat-agent-server)
-- 设计: 江小鱼 2026-08-29
--
-- 架构: 微信用户 → [通道层 Channel] → 消息进系统 → [身份层 wx_user]
--       → [Agent 层 每用户专属 agent] → AI 回复 → 通道推回
--
-- 通道可插拔: 真实微信后接, 先内置 mock 通道测全流程
-- ============================================================

-- 1) 微信用户表: 每个加 bot 的微信用户一条记录
CREATE TABLE IF NOT EXISTS wechat_users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  openid        VARCHAR(128) NOT NULL COMMENT '微信 openid (mock 通道用 wx_mock_xxx)',
  unionid       VARCHAR(128) DEFAULT NULL COMMENT '微信 unionid (同主体跨应用)',
  nickname      VARCHAR(100) DEFAULT NULL COMMENT '微信昵称',
  avatar        VARCHAR(500) DEFAULT NULL COMMENT '头像 URL',
  channel_key   VARCHAR(50) NOT NULL DEFAULT 'mock' COMMENT '来源通道 (mock/wechat_padlocal/...)',
  status        ENUM('active','blocked','pending') NOT NULL DEFAULT 'active' COMMENT '状态: 正常/拉黑/待激活',
  agent_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用专属 agent 自动回复',
  agent_name    VARCHAR(100) DEFAULT NULL COMMENT '专属 agent 名称 (用户可见)',
  system_prompt TEXT DEFAULT NULL COMMENT '该用户专属 agent 的 system prompt (可独立配置)',
  model_key     VARCHAR(100) DEFAULT NULL COMMENT '该用户 agent 用的模型 (空=全局默认)',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_openid (openid),
  KEY idx_status (status),
  KEY idx_channel (channel_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信用户表(多租户多agent服务的用户)';

-- 2) 通道实例表: 每条真实微信通道的连接配置(可插拔插槽)
CREATE TABLE IF NOT EXISTS wechat_channels (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  channel_key   VARCHAR(50) NOT NULL COMMENT '通道唯一键 (mock / wechat_a / ...)',
  channel_type  VARCHAR(50) NOT NULL DEFAULT 'mock' COMMENT '通道类型 (mock/wechat_padlocal/ilink/...)',
  display_name  VARCHAR(100) DEFAULT NULL COMMENT '通道显示名',
  config        JSON DEFAULT NULL COMMENT '通道连接配置(签名/token/endpoint 等, 2级凭据不入此表)',
  status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
  remark        VARCHAR(255) DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_channel_key (channel_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信通道实例表(可插拔)';

-- 3) 消息表: 收发日志
CREATE TABLE IF NOT EXISTS wechat_messages (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  wx_user_id    INT NOT NULL COMMENT '关联 wechat_users.id',
  channel_key   VARCHAR(50) NOT NULL DEFAULT 'mock',
  direction     ENUM('in','out') NOT NULL COMMENT 'in=用户发来, out=agent 回复',
  msg_type      VARCHAR(20) NOT NULL DEFAULT 'text' COMMENT 'text/image/...',
  content       TEXT DEFAULT NULL COMMENT '消息文本 (图片=URL/文件名)',
  ai_reply      TEXT DEFAULT NULL COMMENT 'agent 回复原文 (in 消息附)',
  token_used    INT DEFAULT 0 COMMENT '本次消耗 token (对接 ai-token 计费)',
  agent_status  VARCHAR(20) DEFAULT NULL COMMENT 'agent 处理状态 ok/error/skipped',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (wx_user_id),
  KEY idx_channel (channel_key),
  KEY idx_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信消息收发日志';
