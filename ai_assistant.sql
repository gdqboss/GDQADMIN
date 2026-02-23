-- AI助手配置表
CREATE TABLE IF NOT EXISTS `ddwx_ai_assistant_sysset` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL COMMENT '应用ID',
  `app_id` varchar(100) NOT NULL COMMENT '百度AI AppID',
  `api_key` varchar(100) NOT NULL COMMENT '百度AI API Key',
  `secret_key` varchar(100) NOT NULL COMMENT '百度AI Secret Key',
  `provider` varchar(20) NOT NULL DEFAULT 'baidu' COMMENT 'AI服务提供商：baidu/openai',
  `openai_api_key` varchar(100) DEFAULT '' COMMENT 'OpenAI API Key',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：0关闭1开启',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `aid` (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI助手配置表';

-- AI助手聊天记录表
CREATE TABLE IF NOT EXISTS `ddwx_ai_assistant_chatlog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL COMMENT '应用ID',
  `uid` int(11) NOT NULL COMMENT '用户ID',
  `user_message` text NOT NULL COMMENT '用户消息',
  `ai_reply` text NOT NULL COMMENT 'AI回复',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `aid` (`aid`),
  KEY `uid` (`uid`),
  KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI助手聊天记录表';

-- 插入默认配置示例
INSERT INTO `ddwx_ai_assistant_sysset` (`aid`, `app_id`, `api_key`, `secret_key`, `provider`, `status`, `create_time`, `update_time`) VALUES
(1, '', '', '', 'baidu', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
