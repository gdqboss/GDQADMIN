-- Migration: Customer Service (kefu) tables
-- Run this on existing databases to add the new tables.

-- 客服会话表
CREATE TABLE IF NOT EXISTS kefu_conversations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  user_name VARCHAR(100),
  user_type ENUM('customer', 'agent', 'admin') DEFAULT 'customer',
  status ENUM('active', 'closed', 'pending') DEFAULT 'active',
  unread INT DEFAULT 0,
  last_message TEXT,
  last_time DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 客服消息表
CREATE TABLE IF NOT EXISTS kefu_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  role ENUM('customer', 'agent', 'system') NOT NULL,
  content TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES kefu_conversations(id) ON DELETE CASCADE
);

-- 添加索引
CREATE INDEX IF NOT EXISTS idx_kefu_conv_last ON kefu_conversations(last_time DESC);
CREATE INDEX IF NOT EXISTS idx_kefu_msg_conv ON kefu_messages(conversation_id);