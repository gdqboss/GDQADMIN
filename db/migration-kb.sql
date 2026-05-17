-- AI模型配置
CREATE TABLE IF NOT EXISTS ai_config (
  id INT PRIMARY KEY AUTO_INCREMENT,
  category VARCHAR(32) NOT NULL COMMENT 'llm/vision/tts/video',
  provider VARCHAR(64) NOT NULL COMMENT 'openai/anthropic/local/groq',
  base_url VARCHAR(255) DEFAULT '',
  api_key VARCHAR(512) DEFAULT '',
  model VARCHAR(128) NOT NULL,
  is_default TINYINT DEFAULT 0,
  status TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_category (category),
  INDEX idx_is_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 知识库文档
CREATE TABLE IF NOT EXISTS kb_documents (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  category VARCHAR(64) NOT NULL COMMENT 'manual/rule/faq/learned',
  source VARCHAR(32) NOT NULL COMMENT 'original/supplement/hermes',
  content TEXT,
  chunk_count INT DEFAULT 0,
  status TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_category (category),
  INDEX idx_source (source),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 知识块（embedding向量）
CREATE TABLE IF NOT EXISTS kb_chunks (
  id INT PRIMARY KEY AUTO_INCREMENT,
  doc_id INT NOT NULL,
  content TEXT NOT NULL,
  chunk_index INT DEFAULT 0,
  embedding LONGTEXT COMMENT 'JSON array of floats',
  metadata JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (doc_id) REFERENCES kb_documents(id) ON DELETE CASCADE,
  INDEX idx_doc_id (doc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 学习记录（Hermes自动记录模式）
CREATE TABLE IF NOT EXISTS kb_learning_log (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT DEFAULT NULL,
  session_id VARCHAR(128) DEFAULT '',
  user_message TEXT NOT NULL,
  ai_reply TEXT DEFAULT '',
  source VARCHAR(32) DEFAULT 'boss_chat' COMMENT 'boss_chat/kb/manual',
  intent VARCHAR(64) DEFAULT '',
  confidence FLOAT DEFAULT 0,
  kb_doc_id INT DEFAULT NULL,
  validated TINYINT DEFAULT 0 COMMENT '0=pending 1=confirmed 2=rejected',
  correct_answer TEXT DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_validated (validated),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 预设默认AI配置（如果有ai_config表的话）
INSERT IGNORE INTO ai_config (category, provider, base_url, api_key, model, is_default) VALUES
('llm', 'local', 'http://100.74.233.52:1234/v1', '', 'qwen/qwen3.5-9b-sushi-coder-rl', 1),
('vision', 'local', 'http://100.74.233.52:1234/v1', '', 'qwen/qwen3-vl-8b', 1);
