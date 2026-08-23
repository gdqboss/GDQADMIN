-- ============================================================
-- AI Token 平台 Phase 1 数据库迁移
-- 执行: mysql -u gdq -p -S /run/mysqld/mysqld.sock gdq < db/migration-token-platform.sql
-- ============================================================

-- 1. token用户表（独立于SmartBiz主用户）
CREATE TABLE IF NOT EXISTS ai_token_users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) UNIQUE NOT NULL,
  phone VARCHAR(50),
  password_hash VARCHAR(255) NOT NULL,
  balance DECIMAL(12,4) DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_phone (phone)
);

-- 2. API密钥表
CREATE TABLE IF NOT EXISTS ai_token_keys (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  key_prefix VARCHAR(16) NOT NULL,        -- 显示用的前缀 sk-tok-xxx
  key_hash VARCHAR(255) NOT NULL,         -- 存储SHA256后的Key
  key_name VARCHAR(100),                   -- Key别名，如"项目A密钥"
  quota_day DECIMAL(12,4) DEFAULT 0,       -- 日额度上限，0=不限
  quota_month DECIMAL(12,4) DEFAULT 0,    -- 月额度上限，0=不限
  expires_at DATETIME,                     -- 过期时间，NULL=永不过期
  ip_whitelist TEXT,                      -- IP白名单，逗号分隔，NULL=不限
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES ai_token_users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_key_prefix (key_prefix)
);

-- 3. 上游渠道配置表
CREATE TABLE IF NOT EXISTS ai_token_channels (
  id INT PRIMARY KEY AUTO_INCREMENT,
  provider VARCHAR(50) NOT NULL,           -- deepseek / doubao / qwen
  channel_name VARCHAR(100) NOT NULL,      -- 渠道显示名
  base_url VARCHAR(500) NOT NULL,         -- 上游API地址
  api_key VARCHAR(500) NOT NULL,          -- 加密存储
  routing_strategy ENUM('price','stable','specified') DEFAULT 'price',
  priority INT DEFAULT 100,               -- 越小越优先
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 4. 模型配置表
CREATE TABLE IF NOT EXISTS ai_token_models (
  id INT PRIMARY KEY AUTO_INCREMENT,
  channel_id INT NOT NULL,
  provider_model_name VARCHAR(200) NOT NULL,  -- 上游模型名 deepseek-v3
  display_name VARCHAR(200) NOT NULL,         -- 显示名 DeepSeek-V3
  input_multiplier DECIMAL(6,4) DEFAULT 1,    -- 输入倍率
  output_multiplier DECIMAL(6,4) DEFAULT 1,   -- 输出倍率
  is_active TINYINT(1) DEFAULT 1,
  FOREIGN KEY (channel_id) REFERENCES ai_token_channels(id),
  INDEX idx_channel_id (channel_id)
);

-- 5. 用量日志表
CREATE TABLE IF NOT EXISTS ai_token_usage (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  key_id INT NOT NULL,
  model VARCHAR(200) NOT NULL,
  input_tokens INT DEFAULT 0,
  output_tokens INT DEFAULT 0,
  cost DECIMAL(12,6) DEFAULT 0,           -- 实际扣费金额
  response_time_ms INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (key_id) REFERENCES ai_token_keys(id) ON DELETE CASCADE,
  INDEX idx_key_id (key_id),
  INDEX idx_created_at (created_at)
);

-- 6. 充值记录表
CREATE TABLE IF NOT EXISTS ai_token_recharges (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  amount DECIMAL(12,4) NOT NULL,
  payment_method ENUM('alipay','wechat','stripe','manual') DEFAULT 'manual',
  payment_status ENUM('pending','completed','failed') DEFAULT 'pending',
  payment_ref VARCHAR(255),              -- 第三方支付单号
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES ai_token_users(id),
  INDEX idx_user_id (user_id),
  INDEX idx_status (payment_status)
);
